<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class Health extends Controller
{
    public function index()
    {
        $db = 'down';
        try {
            // Plain PDO on purpose: LavaLust's own DB layer exits on a failed
            // connection, so a try/catch around it never runs (tested Oct 20).
            $dsn = 'mysql:host=' . getenv('DB_HOST') . ';port=' . (getenv('DB_PORT') ?: 3306)
                 . ';dbname=' . getenv('DB_NAME');
            $options = [PDO::ATTR_TIMEOUT => 3, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION];
            // Aiven needs SSL; same switch as Database.php (DB_SSL_CA empty locally)
            if (getenv('DB_SSL_CA')) {
                $name = defined('Pdo\\Mysql::ATTR_SSL_CA') ? 'Pdo\\Mysql::ATTR_SSL_CA' : 'PDO::MYSQL_ATTR_SSL_CA';
                $options[constant($name)] = getenv('DB_SSL_CA');
            }
            $pdo = new PDO($dsn, getenv('DB_USER'), getenv('DB_PASSWORD'), $options);
            $pdo->query('SELECT 1');   // needs no table
            $db = 'up';
        } catch (Throwable $e) {
            // db stays 'down'. Nothing is logged here on purpose, so no host or credential can leak.
        }

        http_response_code($db === 'up' ? 200 : 503);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['status' => 'ok', 'db' => $db]);
    }
}