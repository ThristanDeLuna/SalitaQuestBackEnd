<?php

/**
 * Migration 011 (Member B): words
 *
 * Only APPROVED words live here. Pending words stay in Member D's
 * `submissions` table, so a learner can never see an unapproved word.
 * When an admin approves a submission, D inserts a row into this table.
 *
 * No foreign key to regions yet (agreed team decision: keys are added in a
 * later migration once everyone's tables exist). words.region_code logically
 * points to regions.code. Until then, code must delete a word's audio row
 * itself (see the delete endpoint).
 */
class Create_words_table {

    private $_lava;

    public function __construct()
    {
        $this->_lava = lava_instance();
        $this->_lava->call->dbforge();
    }

    public function up()
    {
        if ($this->_lava->dbforge->table_exists('words')) {
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
                // Logical link to regions.code (no foreign key yet).
                'region_code' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 32,
                    'null'       => FALSE,
                ],
                // The word in the regional language (needs utf8mb4).
                'word' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 255,
                    'null'       => FALSE,
                ],
                // English meaning. Used by the list and the matching game.
                'translation' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 255,
                    'null'       => FALSE,
                ],
                // Optional in the table; our 30 seed words should all have one.
                'example_sentence' => [
                    'type' => 'TEXT',
                    'null' => TRUE,
                ],
                'created_at' => [
                    'type'    => 'DATETIME',
                    'null'    => FALSE,
                    'default' => 'CURRENT_TIMESTAMP',
                ],
            ])
            ->add_key('id', primary: TRUE)
            // The same word cannot be added twice in one region. Because
            // region_code is the FIRST column, this index also speeds up
            // "all words of a region", so no separate region_code index.
            ->add_key(['region_code', 'word'], unique: TRUE, name: 'words_region_word_unique')
            ->create_table('words');
    }

    public function down()
    {
        $this->_lava->dbforge->drop_table('words');
    }
}
