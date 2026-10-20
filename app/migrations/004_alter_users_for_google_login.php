<?php
class Alter_users_for_google_login {

    private $_lava;

    public function __construct()
    {
        $this->_lava = lava_instance();
        $this->_lava->call->dbforge();
    }

    public function up()
    {
        $f  = $this->_lava->dbforge;
        $db = $this->_lava->db;

        // 1. Accept old and new role names together so existing rows can be converted.
        $f->modify_column('users', ['role' => ['type' => 'ENUM', 'null' => FALSE,
            'constraint' => "'admin','moderator','user','learner','contributor'",
            'default' => 'learner']]);

        // 2. Convert old roles. admin stays admin.
        $db->raw("UPDATE users SET role = 'learner' WHERE role = 'user'");
        $db->raw("UPDATE users SET role = 'contributor' WHERE role = 'moderator'");

        // 3. Final role list.
        $f->modify_column('users', ['role' => ['type' => 'ENUM', 'null' => FALSE,
            'constraint' => "'learner','contributor','admin'",
            'default' => 'learner']]);

        // 4. Google columns. google_id may be NULL so an Admin row can exist
        //    before that person signs in for the first time.
        if (!$f->column_exists('users', 'google_id')) {
            $f->add_column('users', ['google_id' => ['type' => 'VARCHAR', 'constraint' => 64,
                'null' => TRUE, 'after' => 'id']]);
            $db->raw('ALTER TABLE users ADD UNIQUE KEY google_id_unique (google_id)');
        }
        if (!$f->column_exists('users', 'name')) {
            $f->add_column('users', ['name' => ['type' => 'VARCHAR', 'constraint' => 255,
                'null' => TRUE, 'after' => 'email']]);
        }

        // 5. Remove the password-login columns.
        foreach (['username', 'password', 'is_active'] as $col) {
            if ($f->column_exists('users', $col)) {
                $f->drop_column('users', $col);
            }
        }
    }

    public function down()
    {
        // Intentionally a no-op: the dropped columns cannot be rebuilt safely.
        // Never roll back on the shared Aiven database (DEC-18).
    }
}