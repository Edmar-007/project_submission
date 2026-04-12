<?php
if (defined('FILE_ADMIN_NOTIFICATIONS_PHP_LOADED')) { return; }
define('FILE_ADMIN_NOTIFICATIONS_PHP_LOADED', true);

require_once __DIR__ . '/../backend/config/app.php';
require_role('admin');
$admin = current_user();
// If a single notification clicked from the topbar, mark it as read and jump to it
if (isset($_GET['mark'])) {
    $markId = (int) $_GET['mark'];
    if ($markId > 0) {
        mark_notification_read('admin', (int) $admin['id'], $markId);
        redirect_to('admin/notifications.php#notification-' . $markId);
    }
}
$state = trim($_GET['state'] ?? '');
$type = trim($_GET['type'] ?? '');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = trim((string) ($_POST['action'] ?? ''));
    if ($action === 'mark_all_read') {
        mark_notifications_read('admin', (int) $admin['id']);
        set_flash('success', 'All notifications marked as read.');
        redirect_to('admin/notifications.php');
    }
    if ($action === 'mark_read') {
        $noteId = (int) ($_POST['notification_id'] ?? 0);
        if ($noteId > 0) {
            mark_notification_read('admin', (int) $admin['id'], $noteId);
            set_flash('success', 'Notification marked as read.');
            redirect_to('admin/notifications.php#notification-' . $noteId);
        }
    }
}
$rows = fetch_notification_filters('admin', (int) $admin['id'], $state, $type);
$title = 'Notifications';
$subtitle = 'Admin inbox for system activity, approvals, and lifecycle events';
require_once __DIR__ . '/../backend/partials/header.php';
?>
<div class="split-header">
  <div>
    <h3 class="section-title">Admin inbox</h3>
    <div class="muted small">Unread: <?= count_unread_notifications('admin', (int) $admin['id']) ?></div>
  </div>
  <form method="post"><input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>"><input type="hidden" name="action" value="mark_all_read"><button class="btn btn-secondary" type="submit" data-notification-mark-all>Mark all as read</button></form>
</div>
<div class="card" style="margin-top:18px;">
  <form method="get" class="filter-row">
    <select name="state">
      <option value="">All states</option>
      <option value="unread" <?= selected($state, 'unread') ?>>Unread</option>
      <option value="read" <?= selected($state, 'read') ?>>Read</option>
    </select>
    <select name="type">
      <option value="">All types</option>
      <option value="info" <?= selected($type, 'info') ?>>Info</option>
      <option value="success" <?= selected($type, 'success') ?>>Success</option>
      <option value="warning" <?= selected($type, 'warning') ?>>Warning</option>
    </select>
    <button class="btn" type="submit">Filter</button>
  </form>
</div>
<div class="timeline-list" style="margin-top:18px;">
  <?php foreach ($rows as $row): ?>
    <div id="notification-<?= (int) $row['id'] ?>" class="timeline-item <?= (int) $row['is_read'] === 0 ? 'unread' : '' ?>" data-notif-id="<?= (int) $row['id'] ?>" data-notif-read="<?= (int) $row['is_read'] ?>">
      <div class="notification-title-row">
        <strong><?= h($row['title']) ?></strong>
        <div class="table-actions">
          <?= status_badge($row['type']) ?>
          <?php if ((int) $row['is_read'] === 0): ?><form method="post"><input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>"><input type="hidden" name="action" value="mark_read"><input type="hidden" name="notification_id" value="<?= (int) $row['id'] ?>"><button class="btn btn-outline" type="submit" data-notification-mark="<?= (int) $row['id'] ?>">Mark read</button></form><?php endif; ?>
        </div>
      </div>
      <p><?= h($row['message']) ?></p>
      <div class="muted small"><?= h($row['created_at']) ?></div>
    </div>
  <?php endforeach; ?>
  <?php if (!$rows): ?><div class="card empty-state">No notifications matched your filters.</div><?php endif; ?>
</div>
<?php require_once __DIR__ . '/../backend/partials/footer.php'; ?>
