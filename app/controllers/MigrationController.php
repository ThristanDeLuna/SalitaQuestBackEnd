<?php
defined('PREVENT_DIRECT_ACCESS') or exit('No direct script access allowed');

// Command line only. Never reachable from a browser.
// No dry-run or preview: this library's migrate() takes no argument, so such a
// command would really change the database.
class MigrationController extends Controller
{
    public function __construct()
    {
        if (php_sapi_name() !== 'cli') {
            http_response_code(404);
            exit('Not found');
        }
        parent::__construct();
        $this->call->library('migration');
    }

    // Runs every pending migration.
    public function migrate()
    {
        $this->migration->migrate();
    }

    // Lists which migrations have run and which are pending. Changes nothing.
    public function status()
    {
        $this->migration->status();
    }

    // Undoes ONE migration (the latest). Never run this on the shared Aiven database.
    public function rollback()
    {
        $this->migration->rollback();
    }
}
