<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

/**
 * Stamp_model: at most one passport stamp per user and region (table "stamps", migration 020).
 *
 * Queries only. Whether a stamp is earned (a passing matching AND a passing
 * listening attempt in the region) is decided by Game_rules (DEC-13).
 *
 * Load in a controller with: $this->call->model('Stamp_model');
 * Then use:                  $this->Stamp_model->for_user($user_id);
 */
class Stamp_model extends Model
{
    protected $table       = 'stamps';
    protected $primary_key = 'id';
    // The app writes dates itself, in UTC (DEC-82); the framework must not add its own
    protected $timestamps = false;

    // Columns that insert() may write; id is set by MySQL.
    // earned_at is written in UTC from PHP so it does not depend on the MySQL server's time zone.
    protected $fillable = ['user_id', 'region_code', 'earned_at'];

    public function __construct()
    {
        parent::__construct();
        // The database is not autoloaded (DEC-11); this returns the shared connection
        $this->call->database();
    }

    /**
     * Create: give a user the stamp of one region. (Criterion 5 Create, via built-in insert())
     *
     * stamps is UNIQUE on (user_id, region_code), so a second stamp for the same
     * region is refused by MySQL. Database::exec() rethrows that as a PDOException
     * without the error code, so the duplicate is recognised from the message
     * (MySQL error 1062). The controller can treat 'duplicate' as "already earned".
     *
     * @return array ['id' => new id] or ['error' => 'duplicate']
     */
    public function award($user_id, $region_code)
    {
        try
        {
            $id = $this->insert([
                'user_id'     => $user_id,
                'region_code' => $region_code,
                'earned_at'   => gmdate('Y-m-d H:i:s'), // UTC, not the server's zone
            ]);
            return ['id' => (int) $id];
        }
        catch (PDOException $e)
        {
            if (strpos($e->getMessage(), '1062') !== FALSE) return ['error' => 'duplicate'];
            throw $e; // any other database error is a real failure
        }
    }

    /**
     * Read: all stamps of one user for the passport, oldest first. (Criterion 4 Query Builder)
     *
     * @return array list of rows (empty when none)
     */
    public function for_user($user_id)
    {
        return $this->db->table($this->table)
                        ->where('user_id', $user_id)
                        ->order_by('earned_at', 'ASC')
                        ->get_all() ?: [];
    }

    /**
     * Read: does the user already have this region's stamp? (Criterion 4, via built-in exists())
     *
     * @return bool
     */
    public function has_stamp($user_id, $region_code)
    {
        return $this->exists(['user_id' => $user_id, 'region_code' => $region_code]);
    }

    /**
     * Delete: remove one stamp, e.g. for an admin correction or test cleanup.
     * (Criterion 5 Delete, via built-in delete_where())
     *
     * @return int rows deleted (0 or 1)
     */
    public function revoke($user_id, $region_code)
    {
        return $this->delete_where(['user_id' => $user_id, 'region_code' => $region_code]);
    }
}
