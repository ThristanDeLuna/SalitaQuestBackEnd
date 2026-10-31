<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

/**
 * Member D: contributor side of submissions.
 * Thin controller: it checks the request, calls the model/library, and answers.
 * All queries live in Submission_model.
 */
class Submissions extends ApiController   // no require: MY_ApiController is loaded at boot (DEC-87)
{
    public function __construct()
    {
        parent::__construct();                          // loads db and the Api class
        $this->config->load('submissions');             // app/config/submissions.php (check exact call in LavaLust docs)
        $this->call->model('Submission_model');         // load BEFORE any transaction (connection finding)
        $this->call->library('mymemory_lib');
    }

    // GET /api/submissions/suggest?word=&region_code=
    // A hint only: if MyMemory fails we still answer 200 with null.
    public function suggest()
    {
        $this->require_role(['contributor', 'admin']);
        $q      = $this->api->get_query_params();
        // trim first: a word made only of spaces must count as empty
        $word   = is_string($q['word'] ?? null) ? trim($q['word']) : '';
        $region = $q['region_code'] ?? '';

        $this->check_word($word, $region);
        $this->api->respond(['suggestion' => $this->mymemory_lib->suggest($word, $region)]);
    }

    // POST /api/submissions   body: region_code, word, suggested_translation, mymemory_text (optional)
    public function create()
    {
        $user = $this->require_role(['contributor', 'admin']);
        $b = $this->api->body();

        // trim first (A's review of #25): "   " must not pass the checks
        $region  = $b['region_code'] ?? '';
        $word    = is_string($b['word'] ?? null) ? trim($b['word']) : '';
        $meaning = is_string($b['suggested_translation'] ?? null) ? trim($b['suggested_translation']) : '';
        $hint    = $b['mymemory_text'] ?? null;        // the client echoes what MyMemory said

        $this->check_word($word, $region);
        if (!is_string($meaning) || $meaning === '' || mb_strlen($meaning) > config_item('submissions_meaning_max')) {
            $this->api->respond_error('Meaning must be 1 to ' . config_item('submissions_meaning_max') . ' characters', 422);
        }
        if ($hint !== null && (!is_string($hint) || mb_strlen($hint) > 255)) {
            $hint = null;                              // a hint is optional, so drop a bad one
        }

        // Same word already waiting in this region? 409
        if ($this->Submission_model->pending_exists($region, $word)) {
            $this->api->respond_error('This word is already waiting for review', 409);
        }
        // Too many pending from this user? 429
        if ($this->Submission_model->count_pending($user['id']) >= config_item('submissions_max_pending')) {
            $this->api->respond_error('Too many pending suggestions. Wait for review first.', 429);
        }

        $row = $this->Submission_model->create($user['id'], $region, $word, $meaning, $hint);
        $this->api->respond(['id' => $row['id'], 'status' => 'pending'], 201);
    }

    // GET /api/submissions : my own submissions, newest first
    public function mine()
    {
        $user = $this->require_role(['contributor', 'admin']);
        $this->api->respond(['data' => $this->Submission_model->for_user($user['id'])]);
    }

    // DELETE /api/submissions/{id} : withdraw my own pending submission
    public function withdraw($id)
    {
        $user = $this->require_role(['contributor', 'admin']);
        $row  = $this->Submission_model->find_one((int) $id);

        // Someone else's row looks the same as a missing one (do not reveal it exists)
        if (!$row || (int) $row['user_id'] !== $user['id']) {
            $this->api->respond_error('Not found', 404);
        }
        if ($row['status'] !== 'pending') {
            $this->api->respond_error('Only a pending suggestion can be withdrawn', 409);
        }
        $this->Submission_model->withdraw((int) $id, $user['id']);
        $this->api->respond(['id' => (int) $id, 'withdrawn' => true]);
    }

    // ---- STUBS: replaced in the next slice ----
    public function admin_list()
    {
        $this->require_role(['admin']);
        $this->api->respond(['stub' => true, 'data' => []]);
    }

    public function approve($id)
    {
        $this->require_role(['admin']);
        $this->api->respond(['stub' => true, 'id' => (int) $id, 'status' => 'approved']);
    }

    public function reject($id)
    {
        $this->require_role(['admin']);
        $this->api->respond(['stub' => true, 'id' => (int) $id, 'status' => 'rejected']);
    }

    // Shared check for word and region (private: not a route)
    private function check_word($word, $region)
    {
        if (!in_array($region, config_item('submissions_regions'), true)) {
            $this->api->respond_error('Unknown region', 422);
        }
        if (!is_string($word) || $word === '' || mb_strlen($word) > config_item('submissions_word_max')) {
            $this->api->respond_error('Word must be 1 to ' . config_item('submissions_word_max') . ' characters', 422);
        }
    }
}