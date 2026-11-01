<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

/**
 * Word_model (Member B): the words of each region (table "words", migration 011).
 *
 * Queries only. Role checks and validation belong to the controller. Deciding
 * who may approve a submission belongs to D's Submissions controller, which
 * calls create() here inside its transaction (CF-05).
 *
 * Load in a controller with: $this->call->model('Word_model');
 * Then use:                  $this->Word_model->for_region('cebuano');
 */
class Word_model extends Model
{
    protected $table       = 'words';
    protected $primary_key = 'id';
    // The app writes the date itself, in UTC (DEC-82); the framework must not add its own
    protected $timestamps = false;

    // Columns that insert() / update_where() may write; id is set by MySQL.
    protected $fillable = ['region_code', 'word', 'translation', 'example_sentence', 'created_at'];

    public function __construct()
    {
        parent::__construct();
                          // The database is not autoloaded (DEC-11); load it here so the model can query.
           // Each call->database() opens its own connection (D's test, DEC-89).
           $this->call->database();
    }

    /**
     * Create: add one word to a region. (Criterion 5 Create, via built-in insert())
     *
     * words is UNIQUE on (region_code, word) and the collation ignores case and
     * accents (CF-06), so "Mahal" and "mahal" collide. Callers should check
     * exists_in_region() first and answer 409 themselves. This catch is only the
     * safety net: Database::exec() rethrows the MySQL error as a PDOException
     * without the code, so the duplicate is recognised from the message (1062),
     * the same way Audio_model and Stamp_model do it.
     *
     * @param string|null $example_sentence NULL or '' is stored as NULL
     * @return array ['id' => new id] or ['error' => 'duplicate']
     */
    public function create($region_code, $word, $translation, $example_sentence = NULL)
    {
        try
        {
            $id = $this->insert([
                'region_code'      => $region_code,
                'word'             => $word,
                'translation'      => $translation,
                'example_sentence' => ($example_sentence === '' ? NULL : $example_sentence),
                'created_at'       => gmdate('Y-m-d H:i:s'), // UTC, not the server's zone
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
     * Read: one word by id. (Criterion 4, via built-in find())
     *
     * @return array|false the row, or FALSE when it does not exist
     */
    public function find_one($id)
    {
        return $this->find($id);
    }

    /**
     * Read: is this word already in this region? Used before approving a
     * submission (409 if true). The comparison uses MySQL's collation, so do NOT
     * lowercase the word in PHP first. (Criterion 4, via built-in exists())
     *
     * @return bool
     */
    public function exists_in_region($region_code, $word)
    {
        return $this->exists(['region_code' => $region_code, 'word' => $word]);
    }

    /**
     * Read: all words of one region, A to Z. (Criterion 4 Query Builder)
     *
     * @return array list of rows (empty when none)
     */
    public function for_region($region_code)
    {
        return $this->db->table($this->table)
                        ->where('region_code', $region_code)
                        ->order_by('word', 'ASC')
                        ->get_all() ?: [];
    }

    /**
     * Read: how many words a region has (used for "coming soon", DEC-71).
     * (Criterion 4 Query Builder, count)
     *
     * @return int
     */
    public function count_in_region($region_code)
    {
        return (int) $this->db->table($this->table)
                              ->where('region_code', $region_code)
                              ->count();
    }

    /**
     * Update: change the translation and example sentence of one word.
     * (Criterion 5 Update, via update_where())
     *
     * The word text and region are NOT changed here: audio is tied to the word's
     * id, and B's rule is that a recorded word is not renamed.
     *
     * @return int rows changed (0 if the id does not exist or nothing changed)
     */
    public function update_details($id, $translation, $example_sentence = NULL)
    {
        return $this->update_where(
            ['id' => $id],
            [
                'translation'      => $translation,
                'example_sentence' => ($example_sentence === '' ? NULL : $example_sentence),
            ]
        );
    }

    /**
     * Delete: remove one word. (Criterion 5 Delete, via delete_where())
     * The controller must delete its audio first (Audio_model::remove_by_word).
     *
     * @return int rows deleted (0 or 1)
     */
    public function remove($id)
    {
        return $this->delete_where(['id' => $id]);
    }
}