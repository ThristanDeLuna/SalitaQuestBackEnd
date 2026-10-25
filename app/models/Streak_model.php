<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

/**
 * Streak_model: one streak row per user (table "streaks", migration 020).
 *
 * Queries only. The new numbers come from Game_rules::update_streak() (DEC-13);
 * the controller passes them here to be stored.
 *
 * last_active_date is the Manila date the controller passes in. It is never
 * taken from MySQL (no CURDATE() or NOW()), so it does not depend on the
 * MySQL server's time zone.
 *
 * Load in a controller with: $this->call->model('Streak_model');
 * Then use:                  $this->Streak_model->find_by_user($user_id);
 */
class Streak_model extends Model
{
    protected $table = 'streaks';

    // user_id is the primary key (not auto-increment), so the built-in
    // find(), update() and delete() all work by user_id
    protected $primary_key = 'user_id';

    // Columns that insert()/update() may write; user_id must be listed because we supply it
    protected $fillable = ['user_id', 'current_streak', 'longest_streak', 'last_active_date'];

    public function __construct()
    {
        parent::__construct();
        // The database is not autoloaded (DEC-11); this returns the shared connection
        $this->call->database();
    }

    /**
     * Create: the first streak row of a user. (Criterion 5 Create, via built-in insert())
     *
     * insert() returns last_id(), which is MySQL's AUTO_INCREMENT value. streaks
     * has no AUTO_INCREMENT column, so that value is 0 and means nothing here;
     * we return the user_id we inserted instead.
     *
     * user_id is the primary key, so a second row for the same user is refused
     * by MySQL (error 1062) and returned as 'duplicate', as in the other models.
     *
     * @param string|null $last_active_date Manila 'Y-m-d', or NULL if never active
     * @return array ['user_id' => user id] or ['error' => 'duplicate']
     */
    public function create($user_id, $current_streak = 0, $longest_streak = 0, $last_active_date = NULL)
    {
        try
        {
            $this->insert([
                'user_id'          => $user_id,
                'current_streak'   => $current_streak,
                'longest_streak'   => $longest_streak,
                'last_active_date' => $last_active_date,
            ]);
            return ['user_id' => (int) $user_id];
        }
        catch (PDOException $e)
        {
            if (strpos($e->getMessage(), '1062') !== FALSE) return ['error' => 'duplicate'];
            throw $e; // any other database error is a real failure
        }
    }

    /**
     * Read: the streak row of one user. (Criterion 4, via built-in find() on the user_id key)
     *
     * @return array|false the row, or FALSE when the user has no streak row yet
     */
    public function find_by_user($user_id)
    {
        return $this->find($user_id);
    }

    /**
     * Update: store the new numbers from Game_rules::update_streak().
     * (Criterion 5 Update, via built-in update(), which uses WHERE user_id = ?)
     *
     * @param string $last_active_date Manila 'Y-m-d' passed in by the controller
     * @return int rows changed (0 if no row, or if the values were already the same)
     */
    public function save_streak($user_id, $current_streak, $longest_streak, $last_active_date)
    {
        return $this->update($user_id, [
            'current_streak'   => $current_streak,
            'longest_streak'   => $longest_streak,
            'last_active_date' => $last_active_date,
        ]);
    }

    /**
     * Delete: remove the streak row of one user, e.g. when the account is deleted.
     * (Criterion 5 Delete, via built-in delete(), which uses WHERE user_id = ?)
     *
     * @return int rows deleted (0 or 1)
     */
    public function remove($user_id)
    {
        return $this->delete($user_id);
    }
}
