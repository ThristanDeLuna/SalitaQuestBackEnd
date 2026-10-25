<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

/**
 * Audio_model: one Cloudinary recording per word (table "audio", migration 020).
 *
 * Queries only. Uploading the file is Cloudinary_lib's job; this model stores
 * the public ID and URL that Cloudinary_lib returns.
 *
 * Load in a controller with: $this->call->model('Audio_model');
 * Then use:                  $this->Audio_model->find_by_word($word_id);
 */
class Audio_model extends Model
{
    protected $table       = 'audio';
    protected $primary_key = 'id';

    // Columns that insert()/update_where() may write; id is set by MySQL.
    // created_at is written in UTC from PHP so it does not depend on the MySQL server's time zone.
    protected $fillable = ['word_id', 'cloudinary_public_id', 'url', 'uploaded_by', 'created_at'];

    public function __construct()
    {
        parent::__construct();
        // The database is not autoloaded (DEC-11); this returns the shared connection
        $this->call->database();
    }

    /**
     * Create: save the audio of one word. (Criterion 5 Create, via built-in insert())
     *
     * audio.word_id is UNIQUE, so a second row for the same word is refused by
     * MySQL. Database::exec() rethrows that as a PDOException without the error
     * code, so the duplicate is recognised from the message (MySQL error 1062).
     *
     * @return array ['id' => new id] or ['error' => 'duplicate']
     */
    public function create($word_id, $public_id, $url, $uploaded_by = NULL)
    {
        try
        {
            $id = $this->insert([
                'word_id'              => $word_id,
                'cloudinary_public_id' => $public_id,
                'url'                  => $url,
                'uploaded_by'          => $uploaded_by,
                'created_at'           => gmdate('Y-m-d H:i:s'), // UTC, not the server's zone
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
     * Read: the audio row of one word. (Criterion 4 Query Builder, via built-in find_by())
     *
     * @return array|false the row, or FALSE when the word has no audio
     */
    public function find_by_word($word_id)
    {
        return $this->find_by('word_id', $word_id);
    }

    /**
     * Read: audio rows for several words, e.g. the 5 listening questions.
     * (Criterion 4 Query Builder: in() builds "word_id IN (?, ?, ...)" with bound values)
     *
     * @return array list of rows (empty when none)
     */
    public function for_words(array $word_ids)
    {
        if (empty($word_ids)) return [];

        return $this->db->table($this->table)
                        ->in('word_id', array_map('intval', $word_ids))
                        ->get_all() ?: [];
    }

    /**
     * Update: point a word at a newly uploaded file. (Criterion 5 Update, via built-in update_where())
     *
     * @return int rows changed (0 if the word has no audio or nothing changed)
     */
    public function replace_file($word_id, $public_id, $url)
    {
        return $this->update_where(
            ['word_id' => $word_id],
            ['cloudinary_public_id' => $public_id, 'url' => $url]
        );
    }

    /**
     * Delete: remove the audio row of one word. (Criterion 5 Delete, via built-in delete_where())
     * Delete the Cloudinary file first with Cloudinary_lib::delete().
     *
     * @return int rows deleted (0 or 1)
     */
    public function remove_by_word($word_id)
    {
        return $this->delete_where(['word_id' => $word_id]);
    }
}
