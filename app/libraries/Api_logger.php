<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class Api_logger
{
    private $_lava;

    public function __construct()
    {
        $this->_lava = lava_instance();
    }

    public function log(string $service, string $endpoint, ?int $status_code,
                        bool $success, ?int $duration_ms = null, ?int $user_id = null): void
    {
        try {
    // Load the DB only if this request has not done it yet (see DEC-89).
    if ( ! array_key_exists('db', $this->_lava->properties)) { $this->_lava->call->database(); }
    // Query Builder insert, no raw SQL.
    $this->_lava->db->table('api_logs')->insert([
        'service'     => $service,
        'endpoint'    => $endpoint,
        'status_code' => $status_code,
        'success'     => $success ? 1 : 0,
        'duration_ms' => $duration_ms,
        'user_id'     => $user_id,
    ]);
        } catch (Throwable $e) {
            // Same fallback C's Cloudinary library uses until this exists.
            error_log('api_logs insert failed for service ' . $service);
        }
    }
}