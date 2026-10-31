<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

/**
 * AdminAudio (Member C): the Admin screen for word audio. Full CRUD on the "audio" table.
 *
 *   GET    /api/admin/audio              Read          every word, with its audio URL or null
 *   POST   /api/admin/audio/{word_id}    Create/Update upload the audio, or replace it
 *   DELETE /api/admin/audio/{word_id}    Delete        remove the audio
 *
 * Thin controller (criterion 3): SQL lives in Audio_model (criteria 4 and 5),
 * Cloudinary calls live in Cloudinary_lib (criterion 6). Design: DESIGN_C_admin_audio.md
 */
class AdminAudio extends ApiController
{
    // Limits for one recording (a spoken word is far smaller than 1 MB)
    // Just under the framework's api_max_body (1,048,576 bytes in app/config/config.php). That guard counts the whole multipart request, so anything bigger is rejected with 413 before this controller runs.
    const MAX_BYTES  = 1000000;
    const EXTENSIONS = ['mp3', 'm4a', 'wav', 'ogg'];
    // Real file type, read from the file itself with finfo (not from its name).
    // Some systems report an m4a as video/mp4, so that one is allowed too.
    const MIMES = ['audio/mpeg', 'audio/mp4', 'audio/x-m4a', 'video/mp4',
                   'audio/wav', 'audio/x-wav', 'audio/wave', 'audio/ogg', 'application/ogg'];

    // GET /api/admin/audio
    public function index()
    {
        $this->api->require_method('GET');
        $this->require_role(['admin']);                  // 401 no token, 403 wrong role

        $this->call->model('Audio_model');

        // Optional filter: GET /api/admin/audio?region=tagalog
        $region = isset($_GET['region']) ? trim($_GET['region']) : NULL;

        // A plain list: word_id, region_code, word, translation, url, uploaded_at, has_audio
        $this->api->respond($this->Audio_model->admin_list($region));
    }

    // POST /api/admin/audio/{word_id}   (multipart form, file field name: file)
    public function upload($word_id)
    {
        $this->api->require_method('POST');
        $admin   = $this->require_role(['admin']);
        $word_id = (int) $word_id;

        $this->call->model('Audio_model');
        $this->call->library('cloudinary_lib');

        if ($word_id < 1 || ! $this->Audio_model->word_exists($word_id)) {
            $this->api->respond_error('Word not found', 404);
        }

        $file  = $_FILES['file'] ?? NULL;
        $error = $this->check_file($file);
        if ($error !== NULL) {
            $this->api->respond_error($error, 422);
        }

        // Upload first. The file name is fixed per word and overwrites the old one,
        // so a replace never needs a separate delete.
        $up = $this->cloudinary_lib->upload_audio($file['tmp_name'], $word_id);
        if (isset($up['error'])) {
            $this->api->respond_error('Audio upload failed', 502);
        }

        if ($this->Audio_model->find_by_word($word_id)) {
            $this->Audio_model->replace_file($word_id, $up['public_id'], $up['url'], $admin['id']);
            $status = 200;                               // replaced (Update)
        } else {
            $made = $this->Audio_model->create($word_id, $up['public_id'], $up['url'], $admin['id']);
            if (isset($made['error'])) {                 // another request added it first
                $this->Audio_model->replace_file($word_id, $up['public_id'], $up['url'], $admin['id']);
                $status = 200;
            } else {
                $status = 201;                           // created (Create)
            }
        }

        $this->api->respond(['word_id' => $word_id, 'url' => $up['url'], 'replaced' => ($status === 200)], $status);
    }

    // DELETE /api/admin/audio/{word_id}
    public function remove($word_id)
    {
        $this->api->require_method('DELETE');
        $this->require_role(['admin']);
        $word_id = (int) $word_id;

        $this->call->model('Audio_model');
        $this->call->library('cloudinary_lib');

        $row = $this->Audio_model->find_by_word($word_id);
        if ( ! $row) {
            $this->api->respond_error('No audio for this word', 404);
        }

        // File first, row second. Cloudinary is the step most likely to fail, so if it
        // fails nothing has changed: the row stays and the admin sees an error to retry.
        // "not found" means the file is already gone, which is what we want, so the
        // row is still removed (otherwise that row could never be deleted).
        $gone = $this->cloudinary_lib->delete($row['cloudinary_public_id']);
        if (isset($gone['error']) && strpos($gone['error'], 'not found') === FALSE) {
            $this->api->respond_error('Audio delete failed', 502);
        }

        $this->Audio_model->remove_by_word($word_id);

        $this->api->respond(['message' => 'Audio deleted']);
    }

    /**
     * Check one uploaded file. Returns an error message, or NULL when the file is fine.
     * (We do not use the framework's Upload library: it moves the file to local disk,
     * and our files go to Cloudinary. DEC-86 exception, see DESIGN_C_admin_audio.md.)
     */
    private function check_file($file)
    {
        if ( ! is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return 'No audio file sent (form field name: file)';
        }
        if ($file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE
            || $file['size'] > self::MAX_BYTES) {
            return 'File is too large (maximum 1 MB)';
        }
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return 'Upload failed, please try again';
        }
        if ($file['size'] < 1) {
            return 'File is empty';
        }

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ( ! in_array($ext, self::EXTENSIONS, TRUE)) {
            return 'Only mp3, m4a, wav or ogg files are allowed';
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        if ( ! in_array($mime, self::MIMES, TRUE)) {
            return 'File type not allowed';
        }

        return NULL;
    }
}
