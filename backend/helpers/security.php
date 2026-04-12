<?php
if (defined('BACKEND_HELPERS_SECURITY_PHP_LOADED')) { return; }
define('BACKEND_HELPERS_SECURITY_PHP_LOADED', true);

/**
 * Security & Common Helper Functions
 * Includes HTML escaping, form helpers, CSRF, flashes, notifications, etc.
 */

// if (!defined('APP_ROOT')) {
//     die('Direct access denied');
// }


if (!function_exists('h')) {
function h(string $str, int $flags = ENT_QUOTES | ENT_HTML5): string {
    return htmlspecialchars($str, $flags, 'UTF-8');
}
}

if (!function_exists('selected')) {
function selected(mixed $value, mixed $compare, string $attr = 'selected'): string {
    return ($value === $compare) ? $attr . '="' . $attr . '"' : '';
}
}

if (!function_exists('status_badge')) {
function status_badge(string $type): string {
    $type = strtolower(trim($type));
    $classes = 'badge info';
    $label = 'Info';
    if ($type === 'success') {
        $classes = 'badge success';
        $label = 'Success';
    } elseif (in_array($type, ['warning', 'warn'])) {
        $classes = 'badge warning';
        $label = 'Warning';
    } elseif (in_array($type, ['error', 'danger'])) {
        $classes = 'badge danger';
        $label = 'Error';
    }
    return '<span class="' . h($classes) . '">' . h($label) . '</span>';
}
}

if (!function_exists('format_deadline_for_input')) {
function format_deadline_for_input(?string $datetime): string {
    if (empty($datetime)) return '';
    try {
        $dt = new DateTime($datetime);
        return $dt->format('Y-m-d\TH:i');
    } catch (Throwable $e) {
        return '';
    }
}
}

if (!function_exists('csrf_token')) {
function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}
}

if (!function_exists('verify_csrf')) {
function verify_csrf(): void {
    $token = trim((string) ($_POST['_csrf'] ?? ''));
    if (!hash_equals(csrf_token(), $token)) {
        throw new RuntimeException('Invalid CSRF token');
    }
}
}

if (!function_exists('get_flashes')) {
function get_flashes(): array {
    $flashes = $_SESSION['flashes'] ?? [];
    unset($_SESSION['flashes']);
    return $flashes;
}
}

if (!function_exists('set_flash')) {
function set_flash(string $type, string $message): void {
    $_SESSION['flashes'][] = ['type' => $type, 'message' => $message];
}
}

if (!function_exists('redirect_to')) {
function redirect_to(string $url, int $status = 302): void {
    // Accept absolute URLs and root-relative paths as-is; otherwise build an app-aware absolute path.
    $target = $url;
    if (!preg_match('/^https?:\/\//i', $url) && !str_starts_with($url, '/')) {
        // Use the url() helper to build a normalized path within APP_URL
        $target = url($url);
    }
    header('Location: ' . $target, true, $status);
    exit;
}
}

