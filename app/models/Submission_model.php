<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

/**
 * Submission_model (Member D): words suggested by contributors, waiting for an
 * admin to approve or reject them (table "submissions", migration 030).
 *
 * Queries only. Validation, role checks, the MyMemory suggestion, the Brevo
 * emails and the insert into "words" (B's table) belong to the controller and
 * to B's Word_model, not here.
 *
 * Load in a controller with: $this->call->model('Submission_model');
 * Then use:                  $this->Submission_model->for_user($user_id);
 */
class Submission_model extends Model
{
    protected $table       = 'submissions';
    protected $primary_key = 'id';

    // The app writes the dates itself, in UTC (DEC-82). The framework must not
    // add its own created_at / updated_at.
    protected $timestamps = false;

    // Columns that insert() / update_where() may write; id is set by MySQL.
    protected $fillable = [
        'user_id', 'region_code', 'word', 'suggested_translation', 'mymemory_text',
        'status', 'reviewed_by', 'reviewed_at', 'reject_reason', 'created_at',
    ];

    // The three values of the status column (ENUM in migration 030)
    const STATUSES = ['pending', 'approved', 'rejected'];

    // Columns for the admin lists: the submission plus the contributor's name
    // and email (from "users"), needed for the queue and for the result email.
    // Listed one by one: the Query Builder refuses "s.*".
    const LIST_COLUMNS = 's.id, s.user_id, s.region_code, s.word, s.suggested_translation, '
                       . 's.mymemory_text, s.status, s.reviewed_by, s.reviewed_at, '
                       . 's.reject_reason, s.created_at, u.name AS user_name, u.email AS user_email';

       public function __construct()
    {
        parent::__construct();
        // Load once per request: call->database() cannot tell it is already loaded,
        // and every call would open a NEW connection (DEC-89, DEC-102).
        if ( ! array_key_exists('db', lava_instance()->properties)) { $this->call->database(); }
    }

    /**
     * Create: save a new suggestion, always as "pending". (Criterion 5 Create, via insert())
     *
     * @param string      $translation   what the contributor finally typed
     * @param string|null $mymemory_text the untouched MyMemory result, NULL if it failed
     * @return array ['id' => new id]
     */
    public function create($user_id, $region_code, $word, $translation, $mymemory_text = NULL)
    {
        $id = $this->insert([
            'user_id'               => $user_id,
            'region_code'           => $region_code,
            'word'                  => $word,
            'suggested_translation' => $translation,
            'mymemory_text'         => $mymemory_text,
            'status'                => 'pending',
            'created_at'            => gmdate('Y-m-d H:i:s'), // UTC, not the server's zone
        ]);
        return ['id' => (int) $id];
    }

    /**
     * Read: one submission by id. (Criterion 4, via built-in find())
     *
     * @return array|false the row, or FALSE when it does not exist
     */
    public function find_one($id)
    {
        return $this->find($id);
    }

    /**
     * Read: one submission with the contributor's name and email, for the
     * approval or rejection email. (Criterion 4 Query Builder, left_join)
     *
     * @return array|false the row (with user_name and user_email), or FALSE
     */
    public function find_with_user($id)
    {
        return $this->with_user()->where('s.id', $id)->get();
    }

    /**
     * Read: all submissions of one user, newest first ("my submissions").
     * (Criterion 4 Query Builder)
     *
     * @return array list of rows (empty when none)
     */
    public function for_user($user_id)
    {
        return $this->db->table($this->table)
                        ->where('user_id', $user_id)
                        ->order_by('id', 'DESC') // id grows with time, so newest first
                        ->get_all() ?: [];
    }

    /**
     * Read: the admin list for one status. "pending" is oldest first (a queue);
     * "approved" and "rejected" are newest first (a history).
     * (Criterion 4 Query Builder: where, left_join, order_by)
     *
     * @param string $status 'pending', 'approved' or 'rejected'
     * @return array list of rows with user_name and user_email (empty for an unknown status)
     */
    public function list_by_status($status)
    {
        if ( ! in_array($status, self::STATUSES, TRUE)) return [];

        return $this->with_user()
                    ->where('s.status', $status)
                    ->order_by('s.id', $status === 'pending' ? 'ASC' : 'DESC')
                    ->get_all() ?: [];
    }

    /**
     * Read: is this word already waiting for review in this region?
     * MySQL's collation ignores case and accents, so "Mahal" and "mahal" match.
     * (Criterion 4, via built-in exists())
     *
     * @return bool
     */
    public function pending_exists($region_code, $word)
    {
        return $this->exists([
            'region_code' => $region_code,
            'word'        => $word,
            'status'      => 'pending',
        ]);
    }

    /**
     * Read: how many pending submissions one user has (the controller caps it).
     * (Criterion 4 Query Builder, count)
     *
     * @return int
     */
    public function count_pending($user_id)
    {
        return (int) $this->db->table($this->table)
                              ->where('user_id', $user_id)
                              ->where('status', 'pending')
                              ->count();
    }

    /**
     * Update: an admin approves a submission. (Criterion 5 Update, via update_where())
     *
     * Only a PENDING row changes: "WHERE id = ? AND status = 'pending'". So two
     * admins clicking at the same time cannot both succeed.
     * The controller inserts the word into "words" (B) in the same transaction.
     *
     * @return int rows changed: 1, or 0 when it does not exist or is already decided
     */
    public function mark_approved($id, $admin_id)
    {
        return $this->update_where(
            ['id' => $id, 'status' => 'pending'],
            [
                'status'      => 'approved',
                'reviewed_by' => $admin_id,
                'reviewed_at' => gmdate('Y-m-d H:i:s'), // UTC (DEC-82)
            ]
        );
    }

    /**
     * Update: an admin rejects a submission, with an optional reason that goes
     * into the rejection email. Same pending-only rule as mark_approved().
     *
     * @param string|null $reason up to 255 characters (checked by the controller)
     * @return int rows changed: 1, or 0 when it does not exist or is already decided
     */
    public function mark_rejected($id, $admin_id, $reason = NULL)
    {
        return $this->update_where(
            ['id' => $id, 'status' => 'pending'],
            [
                'status'        => 'rejected',
                'reviewed_by'   => $admin_id,
                'reviewed_at'   => gmdate('Y-m-d H:i:s'), // UTC (DEC-82)
                'reject_reason' => $reason,
            ]
        );
    }

    /**
     * Delete: a contributor withdraws their OWN pending submission.
     * (Criterion 5 Delete, via delete_where())
     *
     * Approved and rejected rows are never deleted (they are the history), and a
     * user cannot delete someone else's row.
     *
     * @return int rows deleted: 1, or 0 (not found, not yours, or already decided)
     */
    public function withdraw($id, $user_id)
    {
        return $this->delete_where([
            'id'      => $id,
            'user_id' => $user_id,
            'status'  => 'pending',
        ]);
    }

    /**
     * Start a query on submissions (alias "s") joined with users (alias "u").
     * The join condition is fixed text, never user input.
     */
    private function with_user()
    {
        return $this->db->table($this->table . ' s')
                        ->select(self::LIST_COLUMNS)
                        ->left_join('users u', 's.user_id = u.id');
    }
}
