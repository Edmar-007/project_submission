<?php
if (defined('FILE_ADMIN_AUDIT_LOGS_PHP_LOADED')) { return; }
define('FILE_ADMIN_AUDIT_LOGS_PHP_LOADED', true);

require_once __DIR__ . '/../backend/config/app.php';
require_role('admin');
$pdo = pdo();
$q = trim($_GET['q'] ?? '');
$sql = 'SELECT * FROM audit_logs WHERE 1=1';
$params = [];
if ($q !== '') {
    $sql .= ' AND (actor_type LIKE ? OR action LIKE ? OR target_type LIKE ? OR description LIKE ?)';
    $like = '%' . $q . '%';
    $params = [$like, $like, $like, $like];
}
$sql .= ' ORDER BY created_at DESC LIMIT 200';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();
$title = 'Audit Logs';
$subtitle = 'Track critical changes across sections, subjects, students, and reactivation decisions';
require_once __DIR__ . '/../backend/partials/header.php';
?>
<div class="card highlight-card">
  <form method="get" class="filter-row">
    <input name="q" value="<?= h($q) ?>" placeholder="Search action, actor type, target type, or description">
    <button class="btn" type="submit">Search</button>
  </form>
</div>
<div class="card" style="margin-top:18px;">
  <div class="table-wrap"><table><thead><tr><th>Date</th><th>Actor</th><th>Action</th><th>Target</th><th>Description</th></tr></thead><tbody><?php foreach ($rows as $row): ?><tr><td><?= h($row['created_at']) ?></td><td><strong><?= h($row['actor_type']) ?></strong><div class="muted small">ID <?= (int)$row['actor_id'] ?></div></td><td><?= h($row['action']) ?></td><td><?= h($row['target_type']) ?><div class="muted small">ID <?= (int)$row['target_id'] ?></div></td><td><?= h($row['description']) ?></td></tr><?php endforeach; ?><?php if (!$rows): ?><tr><td colspan="5" class="empty-state">No audit log entries matched your search.</td></tr><?php endif; ?></tbody></table></div>
</div>
<?php require_once __DIR__ . '/../backend/partials/footer.php'; ?>
