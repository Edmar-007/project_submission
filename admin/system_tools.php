<?php
require_once __DIR__ . '/../backend/helpers/query.php';
require_role('admin');
$pdo = pdo();
$admin = current_user();
if (isset($_GET['download']) && $_GET['download'] === 'backup') {
    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    $dump = "-- Backup generated on " . date('Y-m-d H:i:s') . "\n\nSET FOREIGN_KEY_CHECKS=0;\n\n";
    foreach ($tables as $table) {
        $create = $pdo->query("SHOW CREATE TABLE `{$table}`")->fetch();
        $dump .= "DROP TABLE IF EXISTS `{$table}`;\n" . $create['Create Table'] . ";\n\n";
        $rows = $pdo->query("SELECT * FROM `{$table}`")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $row) {
            $cols = array_map(fn($c) => "`{$c}`", array_keys($row));
            $vals = array_map(fn($v) => $v === null ? 'NULL' : $pdo->quote((string)$v), array_values($row));
            $dump .= "INSERT INTO `{$table}` (" . implode(', ', $cols) . ") VALUES (" . implode(', ', $vals) . ");\n";
        }
        $dump .= "\n";
    }
    $dump .= "SET FOREIGN_KEY_CHECKS=1;\n";
    log_action('admin', (int)$admin['id'], 'backup_download', 'system', 0, 'Downloaded SQL backup from system tools');
    header('Content-Type: application/sql');
    header('Content-Disposition: attachment; filename="project_submission_backup_' . date('Ymd_His') . '.sql"');
    echo $dump;
    exit;
}
$title = 'System Tools';
$subtitle = 'Backup utilities and deployment configuration reminders';
require_once __DIR__ . '/../backend/partials/header.php';
?>
<div class="grid cols-2">
  <div class="card">
    <h3 class="section-title">Database backup</h3>
    <p class="muted">Generate a fresh SQL export of the current database for safekeeping before large changes or semester rollover.</p>
    <div class="form-actions"><a class="btn" href="<?= h(url('admin/system_tools.php?download=backup')) ?>">Download SQL backup</a></div>
  </div>
  <div class="card">
    <h3 class="section-title">Mail configuration</h3>
    <p class="muted">Email is optional. When disabled, outgoing messages are written to <code>backend/logs/mail.log</code>.</p>
    <div class="callout"><strong>Config file</strong><div class="muted small">backend/config/mail.php</div></div>
    <div class="callout" style="margin-top:12px;"><strong>Uploads folder</strong><div class="muted small">uploads/ (PDF or image, max 5 MB per submission)</div></div>
  </div>
</div>
<?php require_once __DIR__ . '/../backend/partials/footer.php'; ?>
