<?php
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
                'id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => TRUE,
                         'auto_increment' => TRUE, 'null' => FALSE],
                // google, cloudinary, brevo or mymemory
                'service' => ['type' => 'VARCHAR', 'constraint' => 32, 'null' => FALSE],
                // What was called. Never include tokens or keys.
                'endpoint' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => FALSE],
                // NULL when the call never got a response.
                'status_code' => ['type' => 'SMALLINT', 'unsigned' => TRUE, 'null' => TRUE],
                'success' => ['type' => 'TINYINT', 'constraint' => 1, 'unsigned' => TRUE,
                              'null' => FALSE, 'default' => 0],
                'duration_ms' => ['type' => 'INT', 'unsigned' => TRUE, 'null' => TRUE],
                // Who triggered it, if known.
                'user_id' => ['type' => 'INT', 'unsigned' => TRUE, 'null' => TRUE],
                'created_at' => ['type' => 'DATETIME', 'null' => FALSE,
                                 'default' => 'CURRENT_TIMESTAMP'],
            ])
            ->add_key('id', primary: TRUE)
            ->add_key(['service', 'created_at'], name: 'service_created_idx')
            ->create_table('api_logs');
    }

    public function down()
    {
        $this->_lava->dbforge->drop_table('api_logs');
    }
}