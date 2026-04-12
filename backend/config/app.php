<?php
if (defined('BACKEND_CONFIG_APP_PHP_LOADED')) { return; }
define('BACKEND_CONFIG_APP_PHP_LOADED', true);

// backend/config/app.php - cleaned

require_once __DIR__ . '/../helpers/security.php';

// HTTPS detection and session cookie security
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['SERVER_PORT'] ?? '') === '443');
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// Security headers
header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
header("Content-Security-Policy: default-src 'self'; script-src 'self' https://cdn.jsdelivr.net https://cdn.jsdelivr.net/npm; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdn.jsdelivr.net/npm; img-src 'self' data: https:; font-src 'self' https://cdn.jsdelivr.net https://cdn.jsdelivr.net/npm; connect-src 'self'; frame-ancestors 'self'; base-uri 'self'; form-action 'self'");

// App constants
if (!defined('APP_ROOT')) define('APP_ROOT', dirname(__DIR__, 2));
if (!defined('APP_NAME')) define('APP_NAME', getenv('APP_NAME') ?: 'Project Submission Management System');
if (!defined('APP_ENV')) define('APP_ENV', getenv('APP_ENV') ?: 'production');

error_reporting(E_ALL);
ini_set('log_errors', '1');
$phpErrorLog = APP_ROOT . '/storage/logs/php-error.log';
if (!is_dir(dirname($phpErrorLog))) {
    @mkdir(dirname($phpErrorLog), 0775, true);
}
ini_set('error_log', $phpErrorLog);
ini_set('display_errors', APP_ENV === 'production' ? '0' : '1');
ini_set('display_startup_errors', APP_ENV === 'production' ? '0' : '1');

if (!defined('BACKUP_DIR')) define('BACKUP_DIR', getenv('BACKUP_DIR') ?: (APP_ROOT . '/storage/backups'));
if (!defined('BACKUP_UPLOADS_DIR')) define('BACKUP_UPLOADS_DIR', getenv('BACKUP_UPLOADS_DIR') ?: (APP_ROOT . '/uploads'));
if (!defined('BACKUP_RETENTION_COUNT')) define('BACKUP_RETENTION_COUNT', (int) (getenv('BACKUP_RETENTION_COUNT') ?: 5));
if (!defined('BACKUP_GDRIVE_ENABLED')) define('BACKUP_GDRIVE_ENABLED', (int) (getenv('BACKUP_GDRIVE_ENABLED') ?: 0));
if (!defined('BACKUP_GDRIVE_FOLDER_ID')) define('BACKUP_GDRIVE_FOLDER_ID', getenv('BACKUP_GDRIVE_FOLDER_ID') ?: '');
if (!defined('BACKUP_GDRIVE_SERVICE_ACCOUNT_JSON')) define('BACKUP_GDRIVE_SERVICE_ACCOUNT_JSON', getenv('BACKUP_GDRIVE_SERVICE_ACCOUNT_JSON') ?: '');

$scriptPath = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/project_submission_app');
$segments = array_values(array_filter(explode('/', trim($scriptPath, '/'))));
$baseSegment = $segments[0] ?? 'project_submission_app';
if (!defined('APP_URL')) define('APP_URL', '/' . $baseSegment);

if (!defined('DB_HOST')) define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
if (!defined('DB_NAME')) define('DB_NAME', getenv('DB_NAME') ?: 'project_submission_db');
if (!defined('DB_USER')) define('DB_USER', getenv('DB_USER') ?: 'root');
if (!defined('DB_PASS')) define('DB_PASS', getenv('DB_PASS') !== false ? getenv('DB_PASS') : '');
if (!defined('DB_PORT')) define('DB_PORT', getenv('DB_PORT') ?: '3306');
if (!defined('CSRF_KEY')) define('CSRF_KEY', '_csrf_token');
if (!defined('FLASH_KEY')) define('FLASH_KEY', '_flash_messages');

if (!defined('DEMO_CREDENTIAL_SECRET')) define('DEMO_CREDENTIAL_SECRET', getenv('DEMO_CREDENTIAL_SECRET') ?: 'change-me-before-production');

// Include DB and query helpers after constants are defined
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/../helpers/query.php';

// Small helpers (non-conflicting)
function url(string $path = ''): string {
    // Base app URL (no trailing slash)
    $base = rtrim(APP_URL, '/');
    // Requested path, ensure leading slash
    $path = '/' . ltrim($path, '/');

    // Split into segments for robust deduplication of repeated segments like '/admin/admin/...'
    $baseSegments = $base === '' ? [] : explode('/', trim($base, '/'));
    $pathSegments = $path === '/' ? [] : explode('/', trim($path, '/'));

    // Remove duplicate leading segment(s) from the path when they match the last segment of the base
    while (!empty($baseSegments) && !empty($pathSegments) && end($baseSegments) === reset($pathSegments)) {
        array_shift($pathSegments);
    }

    $combined = array_merge($baseSegments, $pathSegments);
    if (empty($combined)) {
        return '/';
    }
    return '/' . implode('/', $combined);
}
function url_for(string $path = ''): string { return url($path); }

function asset_url(string $file): string {
    $relative = 'backend/assets/' . ltrim($file, '/');
    $absolute = dirname(__DIR__) . '/assets/' . ltrim($file, '/');
    $version = is_file($absolute) ? ('?v=' . filemtime($absolute)) : '';
    return url($relative) . $version;
}

function page_title(string $title): string { return $title . ' | ' . APP_NAME; }

function h(?string $value): string { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }

function normalize_public_url(?string $value): ?string {
    $value = trim((string) $value);
    if ($value === '') return null;
    if (!filter_var($value, FILTER_VALIDATE_URL)) return null;
    $scheme = strtolower((string) parse_url($value, PHP_URL_SCHEME));
    if (!in_array($scheme, ['http', 'https'], true)) return null;
    return $value;
}

function safe_public_url(?string $value): ?string {
    return normalize_public_url($value);
}

// Mail config
if (!defined('MAIL_ENABLED')) { require_once __DIR__ . '/mail.php'; }
