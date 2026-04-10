<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

define('APP_ROOT', dirname(__DIR__, 2));
define('APP_NAME', 'Project Submission Management System');

$scriptPath = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/project_submission_app');
$segments = array_values(array_filter(explode('/', trim($scriptPath, '/'))));
$baseSegment = $segments[0] ?? 'project_submission_app';
define('APP_URL', '/' . $baseSegment);

define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('DB_NAME', getenv('DB_NAME') ?: 'project_submission_db');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') !== false ? getenv('DB_PASS') : '');
define('DB_PORT', getenv('DB_PORT') ?: '33060');
define('CSRF_KEY', '_csrf_token');
define('FLASH_KEY', '_flash_messages');

function url(string $path = ''): string {
    return rtrim(APP_URL, '/') . '/' . ltrim($path, '/');
}

function url_for(string $path = ''): string {
    return url($path);
}

function redirect(string $target): void {
    header('Location: ' . $target);
    exit;
}

function redirect_to(string $path): void {
    redirect(url($path));
}

function asset_url(string $file): string {
    return url('backend/assets/' . ltrim($file, '/'));
}

function page_title(string $title): string {
    return $title . ' | ' . APP_NAME;
}

function h(?string $value): string {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function set_flash(string $arg1, string $arg2 = 'success'): void {
    $knownTypes = ['success', 'error', 'info', 'warning'];
    if (in_array($arg1, $knownTypes, true) && !in_array($arg2, $knownTypes, true)) {
        $type = $arg1;
        $message = $arg2;
    } else {
        $message = $arg1;
        $type = $arg2;
    }
    $_SESSION[FLASH_KEY][] = ['message' => $message, 'type' => $type];
}

function get_flashes(): array {
    $flashes = $_SESSION[FLASH_KEY] ?? [];
    unset($_SESSION[FLASH_KEY]);
    return is_array($flashes) ? $flashes : [];
}

function csrf_token(): string {
    if (empty($_SESSION[CSRF_KEY])) {
        $_SESSION[CSRF_KEY] = bin2hex(random_bytes(32));
    }
    return $_SESSION[CSRF_KEY];
}

function verify_csrf(): void {
    $token = $_POST['_csrf'] ?? '';
    if (!$token || !hash_equals($_SESSION[CSRF_KEY] ?? '', $token)) {
        http_response_code(419);
        exit('Invalid CSRF token');
    }
}

function now_label(): string {
    return date('M d, Y h:i A');
}

function selected(string $left, string $right): string {
    return $left === $right ? 'selected' : '';
}

if (!defined('MAIL_ENABLED')) {
    require_once __DIR__ . '/mail.php';
}
