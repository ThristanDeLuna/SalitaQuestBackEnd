<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

// Base class for every JSON endpoint. A child controller file starts with:
//   require_once __DIR__ . '/ApiController.php';
class ApiController extends Controller
{
    /** Set by require_login(): ['id' => int, 'role' => string] */
    protected $user = null;

    public function __construct()
    {
        parent::__construct();
        $this->call->database();       // not loaded by default
        $this->call->library('api');   // stops the request if the secrets are missing
    }

    /** 401 unless the token is valid and the user still exists. */
    protected function require_login(): array
    {
        $payload = $this->api->require_jwt();   // role is read from the users table
        $this->user = ['id' => (int) $payload['sub'], 'role' => $payload['role']];
        return $this->user;
    }

    /** 403 unless the role is in the list, for example ['contributor', 'admin']. */
    protected function require_role(array $roles): array
    {
        $user = $this->require_login();
        if (!in_array($user['role'], $roles, true)) {
            $this->api->respond_error('Forbidden', 403);
        }
        return $user;
    }

    /** Our own pass: tokens plus the public user fields. */
    protected function token_response(array $user): void
    {
        $tokens = $this->api->issue_tokens([
            'id' => $user['id'], 'role' => $user['role'], 'scopes' => ['read'],
        ]);
        $this->api->respond([
            'tokens' => $tokens,
            'user'   => ['id' => (int) $user['id'], 'name' => $user['name'] ?? null,
                         'email' => $user['email'] ?? null, 'role' => $user['role']],
        ]);
    }
}