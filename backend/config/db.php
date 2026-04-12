<?php
if (defined('BACKEND_CONFIG_DB_PHP_LOADED')) { return; }
define('BACKEND_CONFIG_DB_PHP_LOADED', true);

if (!function_exists('pdo')) {
function pdo(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $host = defined('DB_HOST') ? DB_HOST : (getenv('DB_HOST') ?: '127.0.0.1');
    $port = (string) (defined('DB_PORT') ? DB_PORT : (getenv('DB_PORT') ?: '3306'));
    $db   = defined('DB_NAME') ? DB_NAME : (getenv('DB_NAME') ?: 'project_submission_db');
    $user = defined('DB_USER') ? DB_USER : (getenv('DB_USER') ?: 'root');
    $pass = defined('DB_PASS') ? DB_PASS : (getenv('DB_PASS') !== false ? getenv('DB_PASS') : '');

    $dsn = "mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];

    $pdo = new PDO($dsn, $user, $pass, $options);
    return $pdo;
}
}
