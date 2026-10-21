<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');
require_once __DIR__ . '/ApiController.php';

class Auth extends ApiController
{
    // Test login for one of three fixed users. Local use only.
    public function dev_login()
    {
        // Second lock, in case the route ever exists by mistake.
        if (getenv('APP_ENV') !== 'development') {
            $this->api->respond_error('Not found', 404);
        }
        $this->api->require_method('POST');

        $body = $this->api->body();
        $role = $body['role'] ?? 'learner';
        if (!in_array($role, ['learner', 'contributor', 'admin'], true)) {
            $this->api->respond_error('Invalid role', 400);
        }

        $email = "dev-{$role}@example.test";
        $find  = 'SELECT id, email, name, role FROM users WHERE email = ? LIMIT 1';
        $user  = $this->db->raw($find, [$email])->fetch(PDO::FETCH_ASSOC);
        if (!$user) {
            $this->db->raw('INSERT INTO users (email, name, role) VALUES (?, ?, ?)',
                           [$email, "Dev {$role}", $role]);
            $user = $this->db->raw($find, [$email])->fetch(PDO::FETCH_ASSOC);
        }

        $this->token_response($user);
    }

    // GET /auth/me: who am I? Proves the token and the database lookup work.
    public function me()
    {
        $this->api->require_method('GET');
        $u = $this->require_login();   // 401 if the token is missing, bad or expired
        $row = $this->db->raw('SELECT id, email, name, role FROM users WHERE id = ? LIMIT 1',
                              [$u['id']])->fetch(PDO::FETCH_ASSOC);
        $this->api->respond($row);
    }

    // POST /auth/google: exchange a Google ID token for our own tokens.
    public function google()
    {
        $this->api->require_method('POST');
        $body = $this->api->body();

        $this->call->library('google_auth_lib');
        $g = $this->google_auth_lib->verify((string) ($body['id_token'] ?? ''));
        if (!$g) {
            $this->api->respond_error('Google sign-in failed', 401);   // no user is created
        }

        $cols = 'SELECT id, email, name, role FROM users ';

        // 1. A Google account we have seen before.
        $user = $this->db->raw($cols . 'WHERE google_id = ? LIMIT 1', [$g['sub']])->fetch(PDO::FETCH_ASSOC);

        // 2. A row created by email before this person's first sign-in (for example the first Admin).
        //    Safe only because verify() must return an email that Google marked as verified.
        if (!$user) {
            $user = $this->db->raw($cols . 'WHERE email = ? AND google_id IS NULL LIMIT 1',
                                   [$g['email']])->fetch(PDO::FETCH_ASSOC);
            if ($user) {
                $this->db->raw('UPDATE users SET google_id = ?, name = COALESCE(name, ?) WHERE id = ?',
                               [$g['sub'], $g['name'], $user['id']]);
            }
        }

        // 3. A new user always starts as a Learner (the column default).
        if (!$user) {
            // Check first: LavaLust's raw() shows its own error page on a failed query,
            // so a try/catch around the INSERT would not give us a clean 409.
            $taken = $this->db->raw('SELECT id FROM users WHERE email = ? LIMIT 1',
                                    [$g['email']])->fetch(PDO::FETCH_ASSOC);
            if ($taken) {
                $this->api->respond_error('This email is already linked to another account', 409);
            }
            $this->db->raw('INSERT INTO users (google_id, email, name) VALUES (?, ?, ?)',
                           [$g['sub'], $g['email'], $g['name']]);
            $user = $this->db->raw($cols . 'WHERE google_id = ? LIMIT 1', [$g['sub']])->fetch(PDO::FETCH_ASSOC);
        }

        $this->token_response($user);
    }

    // POST /auth/refresh: swap a valid refresh token for a new pair.
    // The library checks the signature, the type, the database row and the user,
    // deletes the old row (so each refresh token works once) and answers by itself.
    public function refresh()
    {
        $this->api->require_method('POST');
        $body = $this->api->body();
        $this->api->refresh_access_token((string) ($body['refresh_token'] ?? ''));
    }

    // POST /auth/logout: delete the refresh token so it can never be used again.
    // Always answers 200, so calling it twice is harmless.
    public function logout()
    {
        $this->api->require_method('POST');
        $body = $this->api->body();
        $rt = (string) ($body['refresh_token'] ?? '');
        if ($rt !== '') {
            $this->api->revoke_refresh_token($rt);
        }
        $this->api->respond(['message' => 'Signed out']);
    }
}
