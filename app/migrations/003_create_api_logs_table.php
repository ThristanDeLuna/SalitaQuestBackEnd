<?php
// Migration 003: one row per call to an outside service (Google, Cloudinary, Brevo, MyMemory).
class Create_api_logs_table {

    private $_lava;

    public function __construct()
    {
        $this->_lava = lava_instance();
        $this->_lava->call->dbforge();
    }

    public function up()
    {
        if ($this->_lava->dbforge->table_exists('api_logs')) {
            return;
        }

        $this->_lava->dbforge
            ->add_field([
                'id'          => ['type' => 'INT', 'unsigned' => TRUE, 'auto_increment' => TRUE, 'null' => FALSE],
                'service'     => ['type' => 'VARCHAR', 'constraint' => 32,  'null' => FALSE],  // e.g. google
                'endpoint'    => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => FALSE],  // path only, never a query string
                'status_code' => ['type' => 'SMALLINT', 'unsigned' => TRUE, 'null' => TRUE],   // NULL if no reply came back
                'success'     => ['type' => 'TINYINT', 'constraint' => 1, 'null' => FALSE, 'default' => 0],
                'duration_ms' => ['type' => 'INT', 'unsigned' => TRUE, 'null' => TRUE],
                'user_id'     => ['type' => 'INT', 'unsigned' => TRUE, 'null' => TRUE],       // NULL when nobody is signed in
                'created_at'  => ['type' => 'DATETIME', 'null' => FALSE, 'default' => 'CURRENT_TIMESTAMP'],
            ])
            ->add_key('id', primary: TRUE)
            ->add_key(['service', 'created_at'], name: 'api_logs_service_created_idx') // schema doc index
            ->create_table('api_logs');
    }

    public function down()
    {
        $this->_lava->dbforge->drop_table('api_logs');
    }
}