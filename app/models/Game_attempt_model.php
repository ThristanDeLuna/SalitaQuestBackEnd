<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

/**
 * Game_attempt_model: every game played, passed or not (table "game_attempts", migration 020).
 *
 * Queries only. Scoring and the pass mark are decided by Game_rules (DEC-13);
 * the controller passes the results here to be saved.
 *
 * Load in a controller with: $this->call->model('Game_attempt_model');
 * Then use:                  $this->Game_attempt_model->today_for_user($user_id, $manila_date);
 */
class Game_attempt_model extends Model
{
    protected $table       = 'game_attempts';
    protected $primary_key = 'id';
    // The app writes dates itself, in UTC (DEC-82); the framework must not add its own
    protected $timestamps = false;

    // Columns that insert() may write; id is set by MySQL.
    // created_at is written in UTC from PHP so it does not depend on the MySQL server's time zone.
    protected $fillable = ['user_id', 'region_code', 'game', 'score', 'max_score', 'passed', 'created_at'];

    public function __construct()
    {
        parent::__construct();
        // The database is not autoloaded (DEC-11); this returns the shared connection
        $this->call->database();
    }

    /**
     * Create: save one attempt. (Criterion 5 Create, via built-in insert())
     *
     * game_attempts has no unique key, so every attempt is a new row and no
     * duplicate check is needed. Any database error is a real failure and is
     * left to throw (Database::exec() rethrows it as a PDOException).
     *
     * @param string $game   'matching' or 'listening' (checked by the controller, DEC-14)
     * @param bool   $passed result from Game_rules
     * @return array ['id' => new id]
     */
    public function create($user_id, $region_code, $game, $score, $max_score, $passed)
    {
        $id = $this->insert([
            'user_id'     => $user_id,
            'region_code' => $region_code,
            'game'        => $game,
            'score'       => $score,
            'max_score'   => $max_score,
            'passed'      => $passed ? 1 : 0, // TINYINT(1)
            'created_at'  => gmdate('Y-m-d H:i:s'), // UTC, which today_for_user() relies on
        ]);
        return ['id' => (int) $id];
    }

    /**
     * Read: all attempts of one user, newest first. (Criterion 4 Query Builder)
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
     * Read: the attempts of one user on one Manila calendar day. (Criterion 4 Query Builder)
     *
     * created_at is written in UTC by create(), but "today" for the player is a
     * Manila date (UTC+8). The Manila day is turned into a UTC range in PHP:
     *   '2026-10-25' (Manila) -> created_at >= '2026-10-24 16:00:00' AND < '2026-10-25 16:00:00'
     * A range on the plain column needs no MySQL time zone tables (CONVERT_TZ
     * would) and lets MySQL use the user_id index.
     *
     * @param string $manila_date 'Y-m-d', e.g. from Game_rules or date('Y-m-d') in Asia/Manila
     * @return array list of rows (empty when none)
     */
    public function today_for_user($user_id, $manila_date)
    {
        // Strict parse: '!' sets the time to 00:00:00; anything not Y-m-d is refused
        $manila = new DateTimeZone('Asia/Manila');
        $start  = DateTime::createFromFormat('!Y-m-d', (string) $manila_date, $manila);
        if ($start === FALSE || $start->format('Y-m-d') !== $manila_date)
        {
            throw new InvalidArgumentException('manila_date must be Y-m-d');
        }

        // Manila midnight -> UTC, and the next Manila midnight -> UTC
        $utc  = new DateTimeZone('UTC');
        $from = $start->setTimezone($utc)->format('Y-m-d H:i:s');
        $to   = $start->modify('+1 day')->format('Y-m-d H:i:s'); // still in UTC

        return $this->db->table($this->table)
                        ->where('user_id', $user_id)
                        ->where('created_at', '>=', $from) // from Manila 00:00
                        ->where('created_at', '<', $to)    // up to, not including, the next Manila 00:00
                        ->order_by('id', 'ASC')
                        ->get_all() ?: [];
    }
}
