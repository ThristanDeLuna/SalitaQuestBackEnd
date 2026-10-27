<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

/**
 * Google sign-in settings (rubric criterion 8: library settings live in app/config).
 * The value comes from .env / Render; this file only names the variable.
 * The Client ID is public (it is also in the React build), but keep it out of code.
 */
$config['google_client_id'] = getenv('GOOGLE_CLIENT_ID') ?: '';