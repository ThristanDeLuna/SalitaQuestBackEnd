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
            $this->_lava->call->database(); $this->_lava->db->raw(
                'INSERT INTO api_logs (service, endpoint, status_code, success, duration_ms, user_id)
                 VALUES (?, ?, ?, ?, ?, ?)',
                [$service, $endpoint, $status_code, $success ? 1 : 0, $duration_ms, $user_id]
            );
        } catch (Throwable $e) {
            // Same fallback C's Cloudinary library uses until this exists.
            error_log('api_logs insert failed for service ' . $service);
        }
    }
}