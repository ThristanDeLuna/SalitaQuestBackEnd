<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

/**
 * AdminWords (Member B): the Admin screen for the words of every region.
 * Full CRUD on the "words" table (the Read side for visitors is Regions::words).
 *
 *   GET    /api/admin/words[?region=cebuano]   Read    every word, with "has_audio"
 *   POST   /api/admin/words                    Create  one word in a region
 *   PUT    /api/admin/words/{id}               Update  translation, example sentence, spelling
 *   DELETE /api/admin/words/{id}               Delete  one word (409 while a recording exists)
 *
 * Admin only: require_role(['admin']) answers 401 without a token and 403 for another role.
 * Thin controller (criterion 3): the SQL is in Word_model (criteria 4 and 5).
 */
class AdminWords extends ApiController
{
    // Same sizes as the columns: words.word and words.translation are VARCHAR(255)
    const WORD_MAX        = 255;
    const TRANSLATION_MAX = 255;
    const EXAMPLE_MAX     = 500;   // example_sentence is TEXT; 500 characters is plenty for a sample sentence

    public function __construct()
    {
        parent::__construct();
        // Load every model here, before any work: each call->model() may open a connection (DEC-89)
        $this->call->model('Word_model');
        $this->call->model('Region_model');
        $this->call->model('Audio_model');
    }

    // GET /api/admin/words  (optional ?region=cebuano)
    public function index()
    {
        $this->api->require_method('GET');
        $this->require_role(['admin']);

        $q      = $this->api->get_query_params();
        $region = (isset($q['region']) && is_string($q['region']) && $q['region'] !== '')
                  ? strtolower($q['region']) : NULL;

        $out = [];
        foreach ($this->Word_model->with_audio($region) as $w)
        {
            $out[] = $this->present($w);
        }
        $this->api->respond(['data' => $out]);
    }

    // POST /api/admin/words   body: region_code, word, translation, example_sentence (optional)
    public function create()
    {
        $this->api->require_method('POST');
        $this->require_role(['admin']);
        $b = $this->api->body();

        $region  = $this->text($b, 'region_code');
        $region  = strtolower($region);
        $word    = $this->text($b, 'word');
        $meaning = $this->text($b, 'translation');
        $example = $this->optional_text($b, 'example_sentence');

        $this->check_fields($word, $meaning, $example);
        if ( ! preg_match('/^[a-z0-9_-]{1,32}$/', $region) || ! $this->Region_model->code_exists($region))
        {
            $this->api->respond_error('Unknown region', 422);
        }
        // One spelling per region (UNIQUE region_code + word, CF-06)
        if ($this->Word_model->exists_in_region($region, $word))
        {
            $this->api->respond_error('This word is already in this region', 409);
        }

        $made = $this->Word_model->create($region, $word, $meaning, $example);
        if (isset($made['error']))   // someone added it between the check and the insert
        {
            $this->api->respond_error('This word is already in this region', 409);
        }

        $this->api->respond($this->present($this->Word_model->find_one($made['id'])), 201);
    }

    // PUT /api/admin/words/{id}   body: translation (required), example_sentence, word (optional)
    public function update($id)
    {
        $this->api->require_method('PUT');
        $this->require_role(['admin']);
        $id  = (int) $id;
        $row = $id > 0 ? $this->Word_model->find_one($id) : FALSE;
        if ( ! $row)
        {
            $this->api->respond_error('Word not found', 404);
        }

        $b       = $this->api->body();
        $word    = array_key_exists('word', $b) ? $this->text($b, 'word') : $row['word'];
        $meaning = $this->text($b, 'translation');
        $example = $this->optional_text($b, 'example_sentence');
        $this->check_fields($word, $meaning, $example);

        // Changing the spelling is the risky part: recordings are tied to the word (CF-06)
        $rename = ($word !== $row['word']);
        if ($rename)
        {
            if ($this->Audio_model->find_by_word($id))
            {
                $this->api->respond_error('This word has a recording and cannot be renamed', 409);
            }
            // MySQL ignores case and accents, so "kaon" finds "Kaon": only another row is a clash
            $other = $this->Word_model->find_in_region($row['region_code'], $word);
            if ($other && (int) $other['id'] !== $id)
            {
                $this->api->respond_error('This word is already in this region', 409);
            }
        }

        $this->Word_model->update_details($id, $meaning, $example, $rename ? $word : NULL);

        $this->api->respond($this->present($this->find_with_audio($row)));
    }

    // DELETE /api/admin/words/{id}
    public function remove($id)
    {
        $this->api->require_method('DELETE');
        $this->require_role(['admin']);
        $id = (int) $id;

        if ($id < 1 || ! $this->Word_model->find_one($id))
        {
            $this->api->respond_error('Word not found', 404);
        }
        // DEC-115: a recorded word is never deleted. Delete the recording first (Admin Audio).
        if ($this->Audio_model->find_by_word($id))
        {
            $this->api->respond_error('This word has a recording. Delete the recording first.', 409);
        }

        $this->Word_model->remove($id);
        $this->api->respond(['message' => 'Word deleted']);
    }

    // ---------- private helpers (not routes) ----------

    // A text field from the body, trimmed; '' when it is missing or not text
    private function text(array $b, $key)
    {
        return (isset($b[$key]) && is_string($b[$key])) ? trim($b[$key]) : '';
    }

    // Optional text: NULL when it is missing or empty
    private function optional_text(array $b, $key)
    {
        $v = $this->text($b, $key);
        return $v === '' ? NULL : $v;
    }

    private function check_fields($word, $meaning, $example)
    {
        if ($word === '' || mb_strlen($word) > self::WORD_MAX)
        {
            $this->api->respond_error('Word must be 1 to ' . self::WORD_MAX . ' characters', 422);
        }
        if ($meaning === '' || mb_strlen($meaning) > self::TRANSLATION_MAX)
        {
            $this->api->respond_error('Meaning must be 1 to ' . self::TRANSLATION_MAX . ' characters', 422);
        }
        if ($example !== NULL && mb_strlen($example) > self::EXAMPLE_MAX)
        {
            $this->api->respond_error('Example sentence must be at most ' . self::EXAMPLE_MAX . ' characters', 422);
        }
    }

    // The word as the API shows it. $w may come from with_audio() (has audio_url) or from find_one() (has not).
    private function present(array $w)
    {
        $audio = isset($w['audio_url']) ? $w['audio_url'] : NULL;
        return [
            'id'               => (int) $w['id'],
            'region_code'      => $w['region_code'],
            'word'             => $w['word'],
            'translation'      => $w['translation'],
            'example_sentence' => $w['example_sentence'],
            'audio_url'        => $audio,
            'has_audio'        => ($audio !== NULL),
        ];
    }

    // The same word again, after an update, with its audio URL (one read of the region, picked by id)
    private function find_with_audio(array $row)
    {
        foreach ($this->Word_model->with_audio($row['region_code']) as $w)
        {
            if ((int) $w['id'] === (int) $row['id']) return $w;
        }
        return $this->Word_model->find_one($row['id']);
    }
}
