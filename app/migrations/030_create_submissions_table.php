<?php

// Member D: submissions (words suggested by contributors, waiting for admin review).
// No foreign keys yet, same agreement as the other members' tables in DECISIONS.md.
class Create_submissions_table
{

    private $_lava;

    public function __construct()
    {
        $this->_lava = lava_instance();
        $this->_lava->call->dbforge();
    }

    public function up()
    {
        // Safe to run twice: do nothing if the table already exists.
        if ($this->_lava->dbforge->table_exists('submissions')) {
            return;
        }

        $this->_lava->dbforge
            ->add_field([
                'id'                   => ['type' => 'INT', 'constraint' => 11, 'unsigned' => TRUE, 'auto_increment' => TRUE, 'null' => FALSE],
                // The contributor (users.id)
                'user_id'              => ['type' => 'INT', 'constraint' => 11, 'unsigned' => TRUE, 'null' => FALSE],
                // regions.code, e.g. tagalog, cebuano, hiligaynon
                'region_code'          => ['type' => 'VARCHAR', 'constraint' => 32, 'null' => FALSE],
                'word'                 => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => FALSE],
                // What the contributor finally typed (starts as the MyMemory suggestion, editable)
                'suggested_translation' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => FALSE],
                // The untouched MyMemory result, kept to show "machine said X, human changed it to Y". NULL if MyMemory failed.
                'mymemory_text'        => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => TRUE, 'default' => NULL],
                'status'               => ['type' => 'ENUM', 'constraint' => "'pending','approved','rejected'", 'null' => FALSE, 'default' => 'pending'],
                // The admin who decided (users.id), NULL while pending
                'reviewed_by'          => ['type' => 'INT', 'constraint' => 11, 'unsigned' => TRUE, 'null' => TRUE, 'default' => NULL],
                'reviewed_at'          => ['type' => 'DATETIME', 'null' => TRUE, 'default' => NULL],
                // Optional reason, shown in the rejection email
                'reject_reason'        => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => TRUE, 'default' => NULL],
                'created_at'           => ['type' => 'DATETIME', 'null' => FALSE, 'default' => 'CURRENT_TIMESTAMP'],
            ])
            ->add_key('id', primary: TRUE)
            // Pending approvals list (admin)
            ->add_key('status', name: 'submissions_status_idx')
            // "My submissions" list (contributor)
            ->add_key('user_id', name: 'submissions_user_idx')
            ->create_table('submissions');
    }

    public function down()
    {
        $this->_lava->dbforge->drop_table('submissions');
    }
}