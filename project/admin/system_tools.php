<?php
require_once __DIR__ . '/../backend/helpers/query.php';
require_once __DIR__ . '/../backend/helpers/uploads.php';
require_once __DIR__ . '/../backend/helpers/backup.php';
require_role('admin');
$pdo = pdo();
$admin = current_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'run_backup') {
        try {
            $result = backup_make_zip((int) $admin['id']);
            log_action('admin', (int) $admin['id'], 'backup_run', 'system', 0, 'Generated ZIP backup package');
            set_flash('success', 'Backup generated: ' . basename($result['zip_path']));
        } catch (Throwable $e) {
            set_flash('error', 'Backup failed: ' . $e->getMessage());
        }
        redirect_to('admin/system_tools.php');
    }

    if ($action === 'download_backup') {
        $backupId = (int) ($_POST['backup_id'] ?? 0);
        $stmt = $pdo->prepare('SELECT * FROM backup_runs WHERE id = ? LIMIT 1');
        $stmt->execute([$backupId]);
        $row = $stmt->fetch();
        if ($row) {
            $path = APP_ROOT . '/' . ltrim((string) $row['zip_path'], '/');
            if (is_file($path)) {
                log_action('admin', (int) $admin['id'], 'backup_download', 'system', (int) $row['id'], 'Downloaded ZIP backup from system tools');
                header('Content-Type: application/zip');
                header('Content-Disposition: attachment; filename="' . basename($path) . '"');
                header('Content-Length: ' . filesize($path));
                readfile($path);
                exit;
            }
        }
        set_flash('error', 'Backup file was not found.');
        redirect_to('admin/system_tools.php');
    }
}

$runs = array_values(array_filter($pdo->query('SELECT * FROM backup_runs ORDER BY id DESC LIMIT 24')->fetchAll(), static function (array $run): bool {
    $path = APP_ROOT . '/' . ltrim((string) ($run['zip_path'] ?? ''), '/');
    return is_file($path);
}));
$title = 'System Tools';
$subtitle = 'ZIP backups, backup history, retention, and environment-based backup settings';
require_once __DIR__ . '/../backend/partials/header.php';
?>
<div class="grid cols-2">
  <div class="card">
    <h3 class="section-title">Backup center</h3>
    <p class="muted">Generate a full ZIP backup package that includes <code>database.sql</code>, <code>manifest.json</code>, and the <code>uploads/</code> folder.</p>
    <form method="post" class="form-actions">
      <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
      <input type="hidden" name="action" value="run_backup">
      <button class="btn" type="submit">Run full backup</button>
    </form>
    <div class="callout" style="margin-top:12px;"><strong>Retention</strong><div class="muted small">Keeps the newest <?= (int) BACKUP_RETENTION_COUNT ?> backup packages locally.</div></div>
    <div class="callout" style="margin-top:12px;"><strong>Google Drive</strong><div class="muted small"><?= BACKUP_GDRIVE_ENABLED === 1 ? 'Configured in environment settings.' : 'Disabled in environment settings.' ?></div></div>
  </div>
  <div class="card">
    <h3 class="section-title">Backup history</h3>
    <div class="table-wrap"><table class="table-redesign"><thead><tr><th>Run</th><th>Status</th><th>Notes</th><th>Action</th></tr></thead><tbody><?php foreach ($runs as $run): ?><tr><td><strong><?= h($run['run_key']) ?></strong><div class="muted small"><?= h($run['created_at']) ?></div></td><td><?= status_badge($run['status']) ?></td><td><?= h($run['notes'] ?: '—') ?></td><td class="text-end"><form method="post" class="inline-icon-form"><input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>"><input type="hidden" name="action" value="download_backup"><input type="hidden" name="backup_id" value="<?= (int) $run['id'] ?>"><button class="icon-action" type="submit" title="download backup" aria-label="download backup"><i class="bi bi-download"></i></button></form></td></tr><?php endforeach; ?><?php if (!$runs): ?><tr><td colspan="4" class="empty-state">No backup runs yet.</td></tr><?php endif; ?></tbody></table></div>
  </div>
</div>
<?php require_once __DIR__ . '/../backend/partials/footer.php'; ?>
