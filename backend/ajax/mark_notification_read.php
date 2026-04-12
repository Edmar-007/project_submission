<?php
if (defined('FILE_BACKEND_AJAX_MARK_NOTIFICATION_READ_PHP_LOADED')) { return; }
define('FILE_BACKEND_AJAX_MARK_NOTIFICATION_READ_PHP_LOADED', true);

require_once __DIR__ . '/../../backend/helpers/query.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

$role = current_role();
$user = current_user();
if (!$role || !$user) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Unauthenticated']);
    exit;
}

$token = trim((string) ($_POST['_csrf'] ?? ''));
if (!$token || !hash_equals($_SESSION[CSRF_KEY] ?? '', $token)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Invalid CSRF token']);
    exit;
}

$userId = (int) $user['id'];
$notificationId = (int) ($_POST['notification_id'] ?? 0);
$markAll = (string) ($_POST['mark_all'] ?? '') === '1';

if ($markAll) {
    mark_notifications_read($role, $userId);
} elseif ($notificationId > 0) {
    mark_notification_read($role, $userId, $notificationId);
} else {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Missing notification_id or mark_all']);
    exit;
}

$unreadCount = count_unread_notifications($role, $userId);
echo json_encode(['ok' => true, 'unread_count' => $unreadCount], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
exit;
