<?php
// Migration 004: turn the stock users table (001) into the Google-login table.
// 001 is NOT edited (DEC-24); this changes it afterwards.
class Alter_users_for_google_login {

    private $_lava;

    public function __construct()
    {
        $this->_lava = lava_instance();
    }

    public function up()
    {
        $db = $this->_lava->db;

        // Safety: the old role values (user/moderator) cannot be converted, so only run on an empty table.
        $count = (int) $db->raw('SELECT COUNT(*) AS c FROM users')->fetch(PDO::FETCH_ASSOC)['c'];
        if ($count > 0) {
            throw new RuntimeException('users has rows; refusing to alter it. Empty it or migrate the data by hand.');
        }

        $db->raw("ALTER TABLE users DROP COLUMN username");   // its unique index goes with it
        $db->raw("ALTER TABLE users DROP COLUMN password");
        $db->raw("ALTER TABLE users DROP COLUMN is_active");
        // google_id is NULL for dev-login users; MySQL allows many NULLs in a unique column.
        $db->raw("ALTER TABLE users ADD COLUMN google_id VARCHAR(64) NULL AFTER id");
        $db->raw("ALTER TABLE users ADD COLUMN name VARCHAR(255) NOT NULL AFTER email");
        $db->raw("ALTER TABLE users MODIFY COLUMN role ENUM('learner','contributor','admin') NOT NULL DEFAULT 'learner'");
        $db->raw("ALTER TABLE users DROP INDEX email_idx");   // the plain index is replaced by a unique one
        $db->raw("ALTER TABLE users ADD UNIQUE KEY users_email_unique (email)");
        $db->raw("ALTER TABLE users ADD UNIQUE KEY users_google_id_unique (google_id)");
    }

    public function down()
    {
        // Not reversible on purpose: dropping google_id would lose every Google account link.
        throw new RuntimeException('004 cannot be rolled back automatically.');
    }
}