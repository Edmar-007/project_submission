<?php
$envFile = dirname(__DIR__, 2) . '/.env';
if (is_file($envFile) && is_readable($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = array_map('trim', explode('=', $line, 2));
        $value = trim($value, "\"'");
        if ($key !== '' && getenv($key) === false) {
            putenv($key . '=' . $value);
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }
}

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

header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
header("Content-Security-Policy: default-src 'self'; script-src 'self' https://cdn.jsdelivr.net https://cdn.jsdelivr.net/npm; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdn.jsdelivr.net/npm; img-src 'self' data: https:; font-src 'self' https://cdn.jsdelivr.net https://cdn.jsdelivr.net/npm; connect-src 'self'; frame-ancestors 'self'; base-uri 'self'; form-action 'self'");

define('APP_ROOT', dirname(__DIR__, 2));
define('APP_NAME', getenv('APP_NAME') ?: 'Project Submission Management System');

define('APP_ENV', getenv('APP_ENV') ?: 'production');

define('BACKUP_DIR', getenv('BACKUP_DIR') ?: (APP_ROOT . '/storage/backups'));
define('BACKUP_UPLOADS_DIR', getenv('BACKUP_UPLOADS_DIR') ?: (APP_ROOT . '/uploads'));
define('BACKUP_RETENTION_COUNT', (int) (getenv('BACKUP_RETENTION_COUNT') ?: 5));
define('BACKUP_GDRIVE_ENABLED', (int) (getenv('BACKUP_GDRIVE_ENABLED') ?: 0));
define('BACKUP_GDRIVE_FOLDER_ID', getenv('BACKUP_GDRIVE_FOLDER_ID') ?: '');
define('BACKUP_GDRIVE_SERVICE_ACCOUNT_JSON', getenv('BACKUP_GDRIVE_SERVICE_ACCOUNT_JSON') ?: '');

$scriptPath = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/project_submission_app');
$segments = array_values(array_filter(explode('/', trim($scriptPath, '/'))));
$baseSegment = $segments[0] ?? 'project_submission_app';
define('APP_URL', '/' . $baseSegment);

define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('DB_NAME', getenv('DB_NAME') ?: 'project_submission_db');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') !== false ? getenv('DB_PASS') : '');
define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('CSRF_KEY', '_csrf_token');
define('FLASH_KEY', '_flash_messages');

define('DEMO_CREDENTIAL_SECRET', getenv('DEMO_CREDENTIAL_SECRET') ?: 'change-me-before-production');

function demo_encrypt(?string $value): ?string {
    if ($value === null || $value === '') {
        return $value;
    }
    if (!function_exists('openssl_encrypt')) {
        return base64_encode($value);
    }
    $key = hash('sha256', DEMO_CREDENTIAL_SECRET, true);
    $iv = random_bytes(16);
    $ciphertext = openssl_encrypt($value, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
    if ($ciphertext === false) {
        return base64_encode($value);
    }
    return 'enc:' . base64_encode($iv . $ciphertext);
}

function demo_decrypt(?string $value): ?string {
    if ($value === null || $value === '') {
        return $value;
    }
    if (str_starts_with($value, 'enc:') && function_exists('openssl_decrypt')) {
        $payload = base64_decode(substr($value, 4), true);
        if ($payload !== false && strlen($payload) > 16) {
            $key = hash('sha256', DEMO_CREDENTIAL_SECRET, true);
            $iv = substr($payload, 0, 16);
            $ciphertext = substr($payload, 16);
            $plain = openssl_decrypt($ciphertext, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
            if ($plain !== false) {
                return $plain;
            }
        }
    }
    $decoded = base64_decode($value, true);
    return $decoded === false ? $value : $decoded;
}

function url(string $path = ''): string {
    return rtrim(APP_URL, '/') . '/' . ltrim($path, '/');
}
function url_for(string $path = ''): string { return url($path); }
function redirect(string $target): void { header('Location: ' . $target); exit; }
function redirect_to(string $path): void { redirect(url($path)); }
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
    if ($value === '') {
        return null;
    }
    if (!filter_var($value, FILTER_VALIDATE_URL)) {
        return null;
    }
    $scheme = strtolower((string) parse_url($value, PHP_URL_SCHEME));
    if (!in_array($scheme, ['http', 'https'], true)) {
        return null;
    }
    return $value;
}
function safe_public_url(?string $value): ?string {
    return normalize_public_url($value);
}
function set_flash(string $arg1, string $arg2 = 'success'): void {
    $knownTypes = ['success', 'error', 'info', 'warning'];
    if (in_array($arg1, $knownTypes, true) && !in_array($arg2, $knownTypes, true)) { $type = $arg1; $message = $arg2; }
    else { $message = $arg1; $type = $arg2; }
    $_SESSION[FLASH_KEY][] = ['message' => $message, 'type' => $type];
}
function get_flashes(): array { $flashes = $_SESSION[FLASH_KEY] ?? []; unset($_SESSION[FLASH_KEY]); return is_array($flashes) ? $flashes : []; }
function csrf_token(): string { if (empty($_SESSION[CSRF_KEY])) { $_SESSION[CSRF_KEY] = bin2hex(random_bytes(32)); } return $_SESSION[CSRF_KEY]; }
function verify_csrf(): void {
    $token = $_POST['_csrf'] ?? '';
    if (!$token || !hash_equals($_SESSION[CSRF_KEY] ?? '', $token)) {
        http_response_code(419);
        exit('Invalid CSRF token');
    }
}
function now_label(): string { return date('M d, Y h:i A'); }
function selected(string $left, string $right): string { return $left === $right ? 'selected' : ''; }
if (!defined('MAIL_ENABLED')) { require_once __DIR__ . '/mail.php'; }
