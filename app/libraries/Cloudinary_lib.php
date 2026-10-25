<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

/**
 * Cloudinary_lib: uploads and deletes word audio on Cloudinary (DEC-06).
 *
 * The Cloudinary SDK (cloudinary/cloudinary_php) is installed with Composer and
 * found through vendor/autoload.php, which LavaLust loads because
 * $config['composer_autoload'] = TRUE in app/config/config.php. No require() here.
 *
 * Settings live in app/config/cloudinary.php and come from environment variables.
 *
 * Load in a controller with: $this->call->library('cloudinary_lib');
 * Then use:                  $this->cloudinary_lib->upload_audio($tmp_path, $word_id);
 *
 * Every method returns an array: the result on success, or ['error' => '...'].
 */
class Cloudinary_lib
{
    // Cloudinary has no "audio" type: audio files (mp3, wav, ...) are stored as "video"
    const RESOURCE_TYPE = 'video';

    /** @var object LavaLust super object (same pattern as scheme/libraries/Api.php) */
    private $_lava;

    /** @var \Cloudinary\Cloudinary|null SDK client, NULL when credentials are missing */
    private $cloudinary = NULL;

    /** @var string Cloudinary folder for word audio, from config */
    private $folder;

    /** @var string API secret, kept only to hide it from error messages */
    private $secret;

    public function __construct()
    {
        // Load app/config/cloudinary.php so config_item() can read its keys
        $this->_lava = lava_instance();
        $this->_lava->config->load('cloudinary');

        $cloud_name   = config_item('cloudinary_cloud_name');
        $api_key      = config_item('cloudinary_api_key');
        $this->secret = config_item('cloudinary_api_secret');
        $this->folder = config_item('cloudinary_folder');

        // Build the SDK client only when all three credentials are set;
        // otherwise each method returns "not configured" instead of crashing
        if ($cloud_name !== '' && $api_key !== '' && $this->secret !== '')
        {
            $this->cloudinary = new \Cloudinary\Cloudinary([
                'cloud' => [
                    'cloud_name' => $cloud_name,
                    'api_key'    => $api_key,
                    'api_secret' => $this->secret,
                ],
                'url' => ['secure' => TRUE], // return https:// URLs
            ]);
        }
    }

    /**
     * Upload one audio file for a word.
     *
     * @param string $file_path Local path, e.g. $_FILES['audio']['tmp_name']
     * @param int    $word_id   Word the audio belongs to (used in the public ID)
     * @return array ['public_id' => ..., 'url' => ...] or ['error' => ...]
     */
    public function upload_audio($file_path, $word_id)
    {
        if ($this->cloudinary === NULL)
        {
            return ['error' => 'Cloudinary is not configured'];
        }

        // Check the input before spending an API call
        if ( ! is_file($file_path))
        {
            return ['error' => 'Audio file not found'];
        }

        try
        {
            $result = $this->cloudinary->uploadApi()->upload($file_path, [
                'resource_type' => self::RESOURCE_TYPE,
                // One file per word, e.g. salitaquest/audio/word_12
                'public_id'     => $this->folder . '/word_' . (int) $word_id,
                'overwrite'     => TRUE, // re-uploading replaces the old audio
                'invalidate'    => TRUE, // clear the CDN copy of the old audio
            ]);

            return [
                'public_id' => $result['public_id'],
                'url'       => $result['secure_url'],
            ];
        }
        catch (\Exception $e)
        {
            return $this->fail('upload_audio', $e, 'Audio upload failed');
        }
    }

    /**
     * Delete one audio file from Cloudinary.
     *
     * @param string $public_id The public ID saved after upload_audio()
     * @return array ['public_id' => ..., 'url' => NULL] or ['error' => ...]
     */
    public function delete($public_id)
    {
        if ($this->cloudinary === NULL)
        {
            return ['error' => 'Cloudinary is not configured'];
        }

        try
        {
            // resource_type must match the upload: destroy() defaults to "image"
            $result = $this->cloudinary->uploadApi()->destroy($public_id, [
                'resource_type' => self::RESOURCE_TYPE,
                'invalidate'    => TRUE,
            ]);

            // Cloudinary answers "ok", or "not found" for an unknown public ID
            if ($result['result'] !== 'ok')
            {
                return ['error' => 'Audio not deleted: ' . $result['result']];
            }

            // url is NULL because the file no longer exists
            return ['public_id' => $public_id, 'url' => NULL];
        }
        catch (\Exception $e)
        {
            return $this->fail('delete', $e, 'Audio delete failed');
        }
    }

    /**
     * Log an SDK error and return a short error for the caller.
     *
     * TODO: switch error_log() to Api_logger::log() after PR #8 merges (DEC-25).
     */
    private function fail($method, \Exception $e, $message)
    {
        // Hide the secret in case it ever appears in an SDK message
        $detail = str_replace($this->secret, '[hidden]', $e->getMessage());
        error_log('Cloudinary_lib::' . $method . ' ' . get_class($e) . ': ' . $detail);

        // The caller only gets the short message, never SDK details
        return ['error' => $message];
    }
}
