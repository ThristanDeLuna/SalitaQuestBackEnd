<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

/*
| -------------------------------------------------------------------
| Brevo settings (used by app/libraries/Brevo_lib.php)
| -------------------------------------------------------------------
| Loaded with $this->_lava->config->load('brevo') and read with
| config_item('brevo_...'). The values come from environment variables
| (.env locally, the Render dashboard in production); this file never
| holds a real key. "?: ''" turns a missing variable into an empty
| string, which the library checks before calling Brevo.
*/

// Brevo transactional email endpoint (HTTP API, not SMTP: DEC-06)
$config['brevo_url']          = 'https://api.brevo.com/v3/smtp/email';

// API key (BREVO_API_KEY): never log or return it
$config['brevo_api_key']      = getenv('BREVO_API_KEY') ?: '';

// Sender address, must be a verified sender in Brevo (BREVO_SENDER_EMAIL)
$config['brevo_sender_email'] = getenv('BREVO_SENDER_EMAIL') ?: '';

// Sender display name (BREVO_SENDER_NAME), default "Salita Quest"
$config['brevo_sender_name']  = getenv('BREVO_SENDER_NAME') ?: 'Salita Quest';

// Seconds to wait for Brevo before treating the email as failed
$config['brevo_timeout']      = 8;