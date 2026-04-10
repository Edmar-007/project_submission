<?php
require_once __DIR__ . '/../backend/helpers/query.php';
require_role('student');
$student = current_user();
$state = trim($_GET['state'] ?? '');
$type = trim($_GET['type'] ?? '');
if (isset($_GET['mark']) && $_GET['mark'] === 'all') {
    mark_notifications_read('student', (int) $student['id']);
    set_flash('success', 'All notifications marked as read.');
    redirect_to('student/notifications.php');
}
if (isset($_GET['note'])) {
    mark_notification_read('student', (int) $student['id'], (int) $_GET['note']);
    set_flash('success', 'Notification marked as read.');
    redirect_to('student/notifications.php');
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
        <a class="btn btn-secondary" href="<?= h(url('student/notifications.php?mark=all')) ?>">Mark all as read</a>
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
        <article class="student-notification-card <?= (int) $row['is_read'] === 0 ? 'is-unread' : '' ?>">
          <div class="student-notification-head">
            <div>
              <h3><?= h($row['title']) ?></h3>
              <div class="muted small"><?= h($row['created_at']) ?></div>
            </div>
            <div class="student-notification-actions">
              <?= status_badge($row['type']) ?>
              <?php if ((int) $row['is_read'] === 0): ?><a class="btn btn-ghost btn-sm" href="<?= h(url('student/notifications.php?note=' . (int) $row['id'])) ?>">Mark as read</a><?php endif; ?>
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
