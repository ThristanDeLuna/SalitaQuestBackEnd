<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

/** User_model (Member A): queries on "users". No validation or tokens here. */
class User_model extends Model
{
    protected $table       = 'users';
    protected $primary_key = 'id';
    protected $timestamps  = false;   // we write dates ourselves, in UTC (DEC-82)
    protected $fillable    = ['google_id', 'email', 'name', 'role', 'created_at'];

    // Never return google_id to the API.
    const PUBLIC_COLUMNS = 'id, email, name, role';

    public function __construct()
    {
        parent::__construct();
        // Load once per request: call->database() cannot tell it is already loaded.
        if ( ! array_key_exists('db', lava_instance()->properties)) { $this->call->database(); }
    }

    /** Read: one user by id, public columns. @return array|false */
    public function find_public($id)
    {
        return $this->db->table($this->table)->select(self::PUBLIC_COLUMNS)
                        ->where('id', $id)->get();
    }

    /** Read: by email (dev login). @return array|false */
    public function find_by_email($email)
    {
        return $this->db->table($this->table)->select(self::PUBLIC_COLUMNS)
                        ->where('email', $email)->get();
    }

    /** Read: by Google account id. @return array|false */
    public function find_by_google_id($sub)
    {
        return $this->db->table($this->table)->select(self::PUBLIC_COLUMNS)
                        ->where('google_id', $sub)->get();
    }

    /** Read: a row made by email that has not signed in with Google yet. */
    public function find_unlinked_by_email($email)
    {
        return $this->db->table($this->table)->select(self::PUBLIC_COLUMNS)
                        ->where('email', $email)->where_null('google_id')->get();
    }

    /** Read: is this email already used? (built-in exists()) */
    public function email_taken($email)
    {
        return $this->exists(['email' => $email]);
    }

    /** Create: a new user. Role defaults to learner. @return int new id */
    public function create($email, $name, $google_id = NULL, $role = 'learner')
    {
        return (int) $this->insert([
            'google_id'  => $google_id,
            'email'      => $email,
            'name'       => $name,
            'role'       => $role,
            'created_at' => gmdate('Y-m-d H:i:s'),   // UTC (DEC-82)
        ]);
    }

    /** Update: link a Google id to an existing row. @return int rows changed */
    public function link_google($id, $sub)
    {
        return $this->update_where(['id' => $id], ['google_id' => $sub]);
    }
}