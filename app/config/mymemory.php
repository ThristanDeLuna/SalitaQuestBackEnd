<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

/*
| -------------------------------------------------------------------
| MyMemory settings (used by app/libraries/Mymemory_lib.php)
| -------------------------------------------------------------------
| Loaded with $this->_lava->config->load('mymemory') and read with
| config_item('mymemory_...'), the same way as app/config/api.php.
| The only value taken from the environment is the optional contact
| email (MYMEMORY_EMAIL in .env); "?: ''" turns a missing variable
| into an empty string. Nothing secret is written in this file.
*/

// MyMemory translation endpoint
$config['mymemory_url']     = 'https://api.mymemory.translated.net/get';

// Optional contact email sent as the "de" parameter (raises the free quota)
$config['mymemory_email']   = getenv('MYMEMORY_EMAIL') ?: '';

// Seconds to wait for MyMemory before giving up (no suggestion is shown)
$config['mymemory_timeout'] = 6;