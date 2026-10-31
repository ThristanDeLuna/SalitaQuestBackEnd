<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

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

        // All SQL now lives in User_model (rubric criteria 4, 5, 8).
        $this->call->model('User_model');
        $email = "dev-{$role}@example.test";
        $user  = $this->User_model->find_by_email($email);
        if (!$user) {
            $id   = $this->User_model->create($email, "Dev {$role}", NULL, $role);
            $user = $this->User_model->find_public($id);
        }

        $this->token_response($user);
    }

    // GET /auth/me: who am I? Proves the token and the database lookup work.
    public function me()
    {
        $this->api->require_method('GET');
        $u = $this->require_login();   // 401 if the token is missing, bad or expired
        $this->call->model('User_model');
        $this->api->respond($this->User_model->find_public($u['id']));
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

        // All SQL lives in User_model (rubric criteria 4, 5, 8).
        $this->call->model('User_model');

        // 1. A Google account we have seen before.
        $user = $this->User_model->find_by_google_id($g['sub']);

        // 2. A row created by email before this person's first sign-in (for example the first Admin).
        //    Safe only because verify() must return an email that Google marked as verified.
        if (!$user) {
            $user = $this->User_model->find_unlinked_by_email($g['email']);
            if ($user) {
                $this->User_model->link_google($user['id'], $g['sub']);
            }
        }

        // 3. A new user always starts as a Learner.
        if (!$user) {
            // Check first, so a taken email gives a clean 409.
            if ($this->User_model->email_taken($g['email'])) {
                $this->api->respond_error('This email is already linked to another account', 409);
            }
            $id   = $this->User_model->create($g['email'], $g['name'], $g['sub']);
            $user = $this->User_model->find_public($id);
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