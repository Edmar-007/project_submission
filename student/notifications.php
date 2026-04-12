<?php
if (defined('FILE_STUDENT_NOTIFICATIONS_PHP_LOADED')) { return; }
define('FILE_STUDENT_NOTIFICATIONS_PHP_LOADED', true);

require_once __DIR__ . '/../backend/config/app.php';
require_role('student');
$student = current_user();
// If a single notification clicked from the topbar, mark it as read and jump to it
if (isset($_GET['mark'])) {
    $markId = (int) $_GET['mark'];
    if ($markId > 0) {
        mark_notification_read('student', (int) $student['id'], $markId);
        redirect_to('student/notifications.php#notification-' . $markId);
    }
}
$state = trim($_GET['state'] ?? '');
$type = trim($_GET['type'] ?? '');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = trim((string) ($_POST['action'] ?? ''));
    if ($action === 'mark_all_read') {
        mark_notifications_read('student', (int) $student['id']);
        set_flash('success', 'All notifications marked as read.');
        redirect_to('student/notifications.php');
    }
    if ($action === 'mark_read') {
        $noteId = (int) ($_POST['notification_id'] ?? 0);
        if ($noteId > 0) {
            mark_notification_read('student', (int) $student['id'], $noteId);
            set_flash('success', 'Notification marked as read.');
            redirect_to('student/notifications.php#notification-' . $noteId);
        }
    }
}
$rows = fetch_notification_filters('student', (int) $student['id'], $state, $type);
$title = 'Notifications';
$subtitle = 'System messages about restrictions, grading, feedback, and account updates';
require_once __DIR__ . '/../backend/partials/header.php';
?>
<section class="student-page-shell">
  <div class="student-page-card">
    <div class="student-page-toolbar student-simple-toolbar">
      <div>
        <div class="eyebrow">Inbox</div>
        <h2>Notifications</h2>
        <p>Stay updated on feedback, grading, restrictions, account updates, and shared team activity.</p>
      </div>
      <div class="student-toolbar-actions">
        <span class="student-soft-badge info">Unread: <?= count_unread_notifications('student', (int) $student['id']) ?></span>
        <form method="post"><input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>"><input type="hidden" name="action" value="mark_all_read"><button class="btn btn-secondary" type="submit" data-notification-mark-all>Mark all as read</button></form>
      </div>
    </div>

    <div class="student-notification-filter card">
      <form method="get" class="filter-row student-filter-row">
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

    <div class="student-notification-list">
      <?php foreach ($rows as $row): ?>
        <article id="notification-<?= (int) $row['id'] ?>" class="student-notification-card <?= (int) $row['is_read'] === 0 ? 'is-unread' : '' ?>" data-notif-id="<?= (int) $row['id'] ?>" data-notif-read="<?= (int) $row['is_read'] ?>">
          <div class="student-notification-head">
            <div>
              <h3><?= h($row['title']) ?></h3>
              <div class="muted small"><?= h($row['created_at']) ?></div>
            </div>
            <div class="student-notification-actions">
              <?= status_badge($row['type']) ?>
              <?php if ((int) $row['is_read'] === 0): ?><form method="post"><input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>"><input type="hidden" name="action" value="mark_read"><input type="hidden" name="notification_id" value="<?= (int) $row['id'] ?>"><button class="btn btn-ghost btn-sm" type="submit" data-notification-mark="<?= (int) $row['id'] ?>">Mark as read</button></form><?php endif; ?>
            </div>
          </div>
          <p><?= h($row['message']) ?></p>
        </article>
      <?php endforeach; ?>
      <?php if (!$rows): ?><div class="card empty-state">No notifications matched your filters.</div><?php endif; ?>
    </div>
  </div>
</section>
<?php require_once __DIR__ . '/../backend/partials/footer.php'; ?>
