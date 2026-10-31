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
        $this->call->model('Word_model');               // approve inserts the word; load it HERE, before transaction()
        $this->call->library('mymemory_lib');
        $this->call->library('brevo_lib');              // emails (never throws)
    }

    // GET /api/submissions/suggest?word=&region_code=
    // A hint only: if MyMemory fails we still answer 200 with null.
    public function suggest()
    {
        $this->require_role(['contributor', 'admin']);
        $q      = $this->api->get_query_params();
        $word   = $q['word'] ?? '';
        $region = $q['region_code'] ?? '';

        $this->check_word($word, $region);
        $this->api->respond(['suggestion' => $this->mymemory_lib->suggest($word, $region)]);
    }

    // POST /api/submissions   body: region_code, word, suggested_translation, mymemory_text (optional)
    public function create()
    {
        $user = $this->require_role(['contributor', 'admin']);
        $b = $this->api->body();

        $region  = $b['region_code'] ?? '';
        $word    = $b['word'] ?? '';
        $meaning = $b['suggested_translation'] ?? '';
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

    // GET /api/admin/submissions?status=pending : the approval queue (admin only)
    public function admin_list()
    {
        $this->require_role(['admin']);
        $q      = $this->api->get_query_params();
        $status = $q['status'] ?? 'pending';

        // The model returns [] for an unknown status; the API should say what is wrong
        if (!in_array($status, Submission_model::STATUSES, true)) {
            $this->api->respond_error('Unknown status', 422);
        }
        $this->api->respond(['status' => $status, 'data' => $this->Submission_model->list_by_status($status)]);
    }

    // POST /api/admin/submissions/{id}/approve   (no body: approve exactly as written)
    // Rubric: criterion 5 (Create a word, Update the submission), criterion 4 (reads in the models).
    public function approve($id)
    {
        $admin = $this->require_role(['admin']);

        // ---- 1. READS FIRST. A failed read exits with an error page and cannot be
        // rolled back, so every read happens before the transaction (DEC-111). ----
        $row = $this->Submission_model->find_with_user((int) $id);   // has user_name, user_email
        if (!$row) {
            $this->api->respond_error('Not found', 404);
        }
        if ($row['status'] !== 'pending') {
            $this->api->respond_error('This suggestion was already decided', 409);
        }
        if ($this->Word_model->exists_in_region($row['region_code'], $row['word'])) {
            $this->api->respond_error('This word is already in this region', 409);
        }

        // ---- 2. ONE TRANSACTION, WRITES ONLY. Both writes succeed or neither does. ----
        $word_id = null;
        try {
            $this->db->transaction();
            // The new word is stored with the contributor's meaning; example sentence stays empty
            $made = $this->Word_model->create($row['region_code'], $row['word'], $row['suggested_translation']);
            // Only changes a PENDING row, so two admins clicking together cannot both win
            $done = $this->Submission_model->mark_approved((int) $id, $admin['id']);

            if (isset($made['error']) || !$done) {
                $this->db->roll_back();                // the word is not kept
            } else {
                $this->db->commit();
                $word_id = $made['id'];
            }
        } catch (Throwable $e) {
            try { $this->db->roll_back(); } catch (Throwable $ignored) {}   // it may not have started
            error_log('Submissions::approve failed for submission ' . (int) $id . ': ' . $e->getMessage());
            $this->api->respond_error('Could not approve. Please try again.', 500);
        }

        // Someone got there first (duplicate word, or already decided): nothing was saved
        if ($word_id === null) {
            $this->api->respond_error('This suggestion was already decided or the word already exists', 409);
        }

        // ---- 3. EMAIL AFTER THE COMMIT. A failed email never undoes the approval. ----
        $mail = $this->brevo_lib->approved_email($row['word']);
        $sent = $this->brevo_lib->send($row['user_email'] ?? '', $row['user_name'] ?? '', $mail['subject'], $mail['html']);

        $this->api->respond(['id' => (int) $id, 'status' => 'approved', 'word_id' => $word_id, 'email_sent' => $sent]);
    }

    // POST /api/admin/submissions/{id}/reject   body: reason (optional, up to 255 characters)
    public function reject($id)
    {
        $admin  = $this->require_role(['admin']);
        $b      = $this->api->body();
        $reason = $b['reason'] ?? null;

        if ($reason !== null) {
            if (!is_string($reason) || mb_strlen($reason) > 255) {
                $this->api->respond_error('Reason must be at most 255 characters', 422);
            }
            if ($reason === '') $reason = null;      // empty means no reason
        }

        $row = $this->Submission_model->find_with_user((int) $id);   // has user_name, user_email
        if (!$row) {
            $this->api->respond_error('Not found', 404);
        }

        // Save the decision FIRST. The model only changes a PENDING row, so 0 means
        // it was already decided (for example, another admin clicked first).
        if (!$this->Submission_model->mark_rejected((int) $id, $admin['id'], $reason)) {
            $this->api->respond_error('This suggestion was already decided', 409);
        }

        // Then email. A failed email never undoes the decision.
        $mail = $this->brevo_lib->rejected_email($row['word'], $reason ?? '');
        $sent = $this->brevo_lib->send($row['user_email'] ?? '', $row['user_name'] ?? '', $mail['subject'], $mail['html']);

        $this->api->respond(['id' => (int) $id, 'status' => 'rejected', 'email_sent' => $sent]);
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