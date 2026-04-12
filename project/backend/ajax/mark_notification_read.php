<?php
/**
 * AJAX endpoint: mark a single notification (or all) as read.
 * Called by the notification bell dropdown and notification page JS.
 * Returns JSON: { ok: bool, unread_count: int, error?: string }
 */
require_once __DIR__ . '/../../backend/helpers/query.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

// Only POST accepted
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

// CSRF validation (reuse the session token directly — same logic as verify_csrf())
$token = trim((string) ($_POST['_csrf'] ?? ''));
if (!$token || !hash_equals($_SESSION[CSRF_KEY] ?? '', $token)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Invalid CSRF token']);
    exit;
}

$userId       = (int) $user['id'];
$notifId      = (int) ($_POST['notification_id'] ?? 0);
$markAll      = (($_POST['mark_all'] ?? '') === '1');

if ($markAll) {
    mark_notifications_read($role, $userId);
} elseif ($notifId > 0) {
    mark_notification_read($role, $userId, $notifId);
} else {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Missing notification_id or mark_all']);
    exit;
}

$unreadCount = count_unread_notifications($role, $userId);

echo json_encode(['ok' => true, 'unread_count' => $unreadCount]);
exit;
