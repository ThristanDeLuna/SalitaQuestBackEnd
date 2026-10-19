<?php

// Member C: audio, game_attempts, stamps, streaks.
// No foreign keys to other members' tables yet (agreed in DECISIONS.md).
class Create_game_tables
{

    private $_lava;

    public function __construct()
    {
        $this->_lava = lava_instance();
        $this->_lava->call->dbforge();
    }

    public function up()
    {
        $forge = $this->_lava->dbforge;

        // audio: one Cloudinary recording per word
        if (!$forge->table_exists('audio')) {
            $forge->add_field([
                'id'                   => ['type' => 'INT', 'constraint' => 11, 'unsigned' => TRUE, 'auto_increment' => TRUE, 'null' => FALSE],
                'word_id'              => ['type' => 'INT', 'constraint' => 11, 'unsigned' => TRUE, 'null' => FALSE],
                'cloudinary_public_id' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => FALSE],
                'url'                  => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => FALSE],
                'uploaded_by'          => ['type' => 'INT', 'constraint' => 11, 'unsigned' => TRUE, 'null' => TRUE, 'default' => NULL],
                'created_at'           => ['type' => 'DATETIME', 'null' => FALSE, 'default' => 'CURRENT_TIMESTAMP'],
            ])
                ->add_key('id', primary: TRUE)
                ->add_key('word_id', unique: TRUE, name: 'audio_word_unique')
                ->create_table('audio');
        }

        // game_attempts: every attempt is saved, passed or not.
        // 'game' is VARCHAR (not ENUM); the controller only accepts matching or listening.
        if (!$forge->table_exists('game_attempts')) {
            $forge->add_field([
                'id'          => ['type' => 'INT', 'constraint' => 11, 'unsigned' => TRUE, 'auto_increment' => TRUE, 'null' => FALSE],
                'user_id'     => ['type' => 'INT', 'constraint' => 11, 'unsigned' => TRUE, 'null' => FALSE],
                'region_code' => ['type' => 'VARCHAR', 'constraint' => 32, 'null' => FALSE],
                'game'        => ['type' => 'VARCHAR', 'constraint' => 16, 'null' => FALSE],
                'score'       => ['type' => 'INT', 'constraint' => 11, 'unsigned' => TRUE, 'null' => FALSE],
                'max_score'   => ['type' => 'INT', 'constraint' => 11, 'unsigned' => TRUE, 'null' => FALSE],
                'passed'      => ['type' => 'TINYINT', 'constraint' => 1, 'unsigned' => TRUE, 'null' => FALSE, 'default' => 0],
                'created_at'  => ['type' => 'DATETIME', 'null' => FALSE, 'default' => 'CURRENT_TIMESTAMP'],
            ])
                ->add_key('id', primary: TRUE)
                ->add_key(['user_id', 'region_code'], name: 'attempts_user_region_idx')
                ->create_table('game_attempts');
        }

        // stamps: at most one per user and region
        if (!$forge->table_exists('stamps')) {
            $forge->add_field([
                'id'          => ['type' => 'INT', 'constraint' => 11, 'unsigned' => TRUE, 'auto_increment' => TRUE, 'null' => FALSE],
                'user_id'     => ['type' => 'INT', 'constraint' => 11, 'unsigned' => TRUE, 'null' => FALSE],
                'region_code' => ['type' => 'VARCHAR', 'constraint' => 32, 'null' => FALSE],
                'earned_at'   => ['type' => 'DATETIME', 'null' => FALSE, 'default' => 'CURRENT_TIMESTAMP'],
            ])
                ->add_key('id', primary: TRUE)
                ->add_key(['user_id', 'region_code'], unique: TRUE, name: 'stamps_user_region_unique')
                ->create_table('stamps');
        }

        // streaks: one row per user, user_id is the primary key
        if (!$forge->table_exists('streaks')) {
            $forge->add_field([
                'user_id'          => ['type' => 'INT', 'constraint' => 11, 'unsigned' => TRUE, 'null' => FALSE],
                'current_streak'   => ['type' => 'INT', 'constraint' => 11, 'unsigned' => TRUE, 'null' => FALSE, 'default' => 0],
                'longest_streak'   => ['type' => 'INT', 'constraint' => 11, 'unsigned' => TRUE, 'null' => FALSE, 'default' => 0],
                'last_active_date' => ['type' => 'DATE', 'null' => TRUE, 'default' => NULL],
            ])
                ->add_key('user_id', primary: TRUE)
                ->create_table('streaks');
        }
    }

    public function down()
    {
        // Only our four tables, in reverse order.
        $forge = $this->_lava->dbforge;
        $forge->drop_table('streaks');
        $forge->drop_table('stamps');
        $forge->drop_table('game_attempts');
        $forge->drop_table('audio');
    }
}
