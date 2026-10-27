<?php

/**
 * Migration 010 (Member B): regions
 *
 * One row per language region shown as a pin on the map.
 * Other members' tables (game_attempts, stamps, submissions) store
 * regions.code, not regions.id, so a code must never change once used.
 */
class Create_regions_table {

    private $_lava;

    public function __construct()
    {
        $this->_lava = lava_instance();
        $this->_lava->call->dbforge();
    }

    public function up()
    {
        // Safe to run twice: skip if the table is already there.
        if ($this->_lava->dbforge->table_exists('regions')) {
            return;
        }

        $this->_lava->dbforge
            ->add_field([
                'id' => [
                    'type'           => 'INT',
                    'constraint'     => 11,
                    'unsigned'       => TRUE,
                    'auto_increment' => TRUE,
                    'null'           => FALSE,
                ],
                // Short, stable id used in URLs and in other tables:
                // 'tagalog', 'cebuano', 'hiligaynon'.
                'code' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 32,
                    'null'       => FALSE,
                ],
                // Label shown to learners, e.g. 'Cebuano (Bisaya)'.
                'name' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 100,
                    'null'       => FALSE,
                ],
                // The "Area" row in the map side panel and the caption on the
                // word list ("Luzon · 5 words"), e.g. 'Luzon', 'Central Visayas'.
                'area' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 100,
                    'null'       => FALSE,
                ],
                // One or two sentences for the map side panel.
                'description' => [
                    'type' => 'TEXT',
                    'null' => TRUE,
                ],
                // Where the Leaflet pin is drawn. DECIMAL(9,6) is about
                // 10 cm of precision, far more than a pin needs.
                'latitude' => [
                    'type'       => 'DECIMAL',
                    'constraint' => '9,6',
                    'null'       => FALSE,
                ],
                'longitude' => [
                    'type'       => 'DECIMAL',
                    'constraint' => '9,6',
                    'null'       => FALSE,
                ],
                'created_at' => [
                    'type'    => 'DATETIME',
                    'null'    => FALSE,
                    'default' => 'CURRENT_TIMESTAMP',
                ],
            ])
            ->add_key('id', primary: TRUE)
            // Two regions can never share a code.
            ->add_key('code', unique: TRUE, name: 'regions_code_unique')
            ->create_table('regions');
    }

    public function down()
    {
        $this->_lava->dbforge->drop_table('regions');
    }
}
