<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class Google_auth_lib
{
    private $_lava;

    public function __construct()
    {
        $this->_lava = lava_instance();
        $this->_lava->call->library('api_logger');
    }

    /** Returns ['sub', 'email', 'name'] if the token is valid for our app, else NULL. */
        public function verify(string $id_token): ?array
    {
        $this->_lava->config->load('google');          // reads app/config/google.php
        $client_id = config_item('google_client_id');  // same value, now via config
        if (!$client_id || $id_token === '' || strlen($id_token) > 4096) {
            return null;
        }

        $start = microtime(true);
        $ch = curl_init('https://oauth2.googleapis.com/tokeninfo?id_token=' . urlencode($id_token));
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 8]);
        $body   = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $ms = (int) round((microtime(true) - $start) * 1000);

        $data = is_string($body) ? json_decode($body, true) : null;
        $ok = $status === 200 && is_array($data)
            && ($data['aud'] ?? '') === $client_id                       // issued for OUR app
            && in_array($data['iss'] ?? '', ['accounts.google.com', 'https://accounts.google.com'], true)
            && (int) ($data['exp'] ?? 0) > time()                        // not expired
            && in_array($data['email_verified'] ?? false, [true, 'true'], true)
            && !empty($data['sub']) && !empty($data['email']);

        // Log host and path only. The URL above contains the token, so never log it.
        $this->_lava->api_logger->log('google', 'oauth2.googleapis.com/tokeninfo',
                                      $status ?: null, $ok, $ms);

        return $ok ? ['sub' => $data['sub'], 'email' => $data['email'],
                      'name' => $data['name'] ?? $data['email']] : null;
    }
}