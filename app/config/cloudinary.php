<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

/*
| -------------------------------------------------------------------
| Cloudinary settings (used by app/libraries/Cloudinary_lib.php)
| -------------------------------------------------------------------
| Loaded on demand with $this->_lava->config->load('cloudinary'),
| the same way scheme/libraries/Api.php loads app/config/api.php.
| Config::load() merges these keys into get_config(), so they are
| then readable with config_item('cloudinary_cloud_name') etc.
|
| Secrets are never written here: the values come from environment
| variables (.env locally, filled in by LavaLust.php with putenv();
| the Render dashboard in production). "?: ''" turns a missing
| variable (getenv() returns FALSE) into an empty string, which the
| library checks before calling Cloudinary.
*/

// Cloud name of the Cloudinary account (CLOUDINARY_CLOUD_NAME)
$config['cloudinary_cloud_name'] = getenv('CLOUDINARY_CLOUD_NAME') ?: '';

// API key of the account (CLOUDINARY_API_KEY)
$config['cloudinary_api_key']    = getenv('CLOUDINARY_API_KEY') ?: '';

// API secret of the account (CLOUDINARY_API_SECRET); never log or return it
$config['cloudinary_api_secret'] = getenv('CLOUDINARY_API_SECRET') ?: '';

// Cloudinary folder where word audio is stored (not a secret)
// Folder for word audio. Local .env uses a different folder so tests never overwrite real audio.
$config['cloudinary_folder']     = getenv('CLOUDINARY_FOLDER') ?: 'salitaquest/audio';
