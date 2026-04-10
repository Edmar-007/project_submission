<?php
require_once __DIR__ . '/../backend/helpers/query.php';
require_role('teacher');
$teacher = current_user();
$state = trim($_GET['state'] ?? '');
$type = trim($_GET['type'] ?? '');
if (isset($_GET['mark']) && $_GET['mark'] === 'all') {
    mark_notifications_read('teacher', (int) $teacher['id']);
    set_flash('success', 'All notifications marked as read.');
    redirect_to('teacher/notifications.php');
}
if (isset($_GET['note'])) {
    mark_notification_read('teacher', (int) $teacher['id'], (int) $_GET['note']);
    set_flash('success', 'Notification marked as read.');
    redirect_to('teacher/notifications.php');
}
$rows = fetch_notification_filters('teacher', (int) $teacher['id'], $state, $type);
$title = 'Notifications';
$subtitle = 'Messages about new submissions and subject activity';
require_once __DIR__ . '/../backend/partials/header.php';
?>
<div class="split-header">
  <div>
    <h3 class="section-title">Teacher notifications</h3>
    <div class="muted small">Unread: <?= count_unread_notifications('teacher', (int) $teacher['id']) ?></div>
  </div>
  <a class="btn btn-secondary" href="<?= h(url('teacher/notifications.php?mark=all')) ?>">Mark all as read</a>
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
    <div class="timeline-item <?= (int) $row['is_read'] === 0 ? 'unread' : '' ?>">
      <div class="notification-title-row">
        <strong><?= h($row['title']) ?></strong>
        <div class="table-actions">
          <?= status_badge($row['type']) ?>
          <?php if ((int) $row['is_read'] === 0): ?><a class="btn btn-outline" href="<?= h(url('teacher/notifications.php?note=' . (int) $row['id'])) ?>">Mark read</a><?php endif; ?>
        </div>
      </div>
      <p><?= h($row['message']) ?></p>
      <div class="muted small"><?= h($row['created_at']) ?></div>
    </div>
  <?php endforeach; ?>
  <?php if (!$rows): ?><div class="card empty-state">No notifications matched your filters.</div><?php endif; ?>
</div>
<?php require_once __DIR__ . '/../backend/partials/footer.php'; ?>
