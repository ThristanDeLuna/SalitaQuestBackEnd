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
        // Load once per request: call->database() cannot tell it is already loaded.
        if ( ! array_key_exists('db', lava_instance()->properties)) { $this->call->database(); }
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
     * Update: change the translation and example sentence of one word, and
     * optionally its spelling. (Criterion 5 Update, via update_where())
     *
     * The region is NEVER changed here. The spelling ($word) is only written when
     * it is passed; the controller must refuse (409) to rename a word that has a
     * recording, because B's rule is that a recorded word is not renamed.
     *
     * @param string|null $word NULL keeps the spelling as it is
     * @return int rows changed (0 if the id does not exist or nothing changed)
     */
    public function update_details($id, $translation, $example_sentence = NULL, $word = NULL)
    {
        $data = [
            'translation'      => $translation,
            'example_sentence' => ($example_sentence === '' ? NULL : $example_sentence),
        ];
        if ($word !== NULL) { $data['word'] = $word; }

        return $this->update_where(['id' => $id], $data);
    }

    /**
     * Read: the row of one word in one region, or FALSE. Used to tell "this word
     * already exists" from "this is the same row" when a word is renamed. The
     * comparison uses MySQL's collation (case and accents are ignored), so do NOT
     * lowercase the word in PHP first. (Criterion 4 Query Builder: where, get)
     *
     * @return array|false
     */
    public function find_in_region($region_code, $word)
    {
        return $this->db->table($this->table)
                        ->where('region_code', $region_code)
                        ->where('word', $word)
                        ->get();
    }

    /**
     * Delete: remove one word. (Criterion 5 Delete, via delete_where())
     * The controller refuses (409) while the word still has a recording (DEC-115),
     * so a recording is never left without its word.
     *
     * @return int rows deleted (0 or 1)
     */
    public function remove($id)
    {
        return $this->delete_where(['id' => $id]);
    }

    /**
     * Read: the words with the URL of their recording (NULL when there is none).
     * Used by the public word list and by the "ready" counts. One query, no loop.
     * (Criterion 4 Query Builder: left_join, where, order_by)
     *
     * order_by() keeps only its last call, so the rows are sorted by word only.
     *
     * @param string|null $region_code only this region, or NULL for all regions
     * @return array rows: id, region_code, word, translation, example_sentence, audio_url
     */
    public function with_audio($region_code = NULL)
    {
        $query = $this->db->table('words')
                          ->select('words.id, words.region_code, words.word, words.translation, words.example_sentence, audio.url AS audio_url')
                          ->left_join('audio', 'audio.word_id = words.id');

        if ($region_code !== NULL && $region_code !== '')
        {
            $query->where('words.region_code', $region_code);
        }

        return $query->order_by('words.word', 'ASC')->get_all() ?: [];
    }

    
}