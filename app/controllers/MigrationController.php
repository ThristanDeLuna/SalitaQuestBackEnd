<?php
defined('PREVENT_DIRECT_ACCESS') or exit('No direct script access allowed');

// Command line only. Never reachable from a browser.
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

    public function migrate()
    {
        $this->migration->migrate(false);
    }
    public function dry_run()
    {
        $this->migration->migrate(true);
    }   // shows what would run
    public function status()
    {
        $this->migration->status();
    }
    public function preview()
    {
        $this->migration->preview();
    }
    public function rollback($steps = 1)
    {
        $this->migration->rollback($steps);
    }
}
