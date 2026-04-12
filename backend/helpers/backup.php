<?php
if (defined('BACKEND_HELPERS_BACKUP_PHP_LOADED')) { return; }
define('BACKEND_HELPERS_BACKUP_PHP_LOADED', true);

if (!function_exists('backup_dump_sql')) {
function backup_dump_sql(PDO $pdo): string {
    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    $dump = "-- Backup generated on " . date('Y-m-d H:i:s') . "\n\nSET FOREIGN_KEY_CHECKS=0;\n\n";
    foreach ($tables as $table) {
        $create = $pdo->query("SHOW CREATE TABLE `{$table}`")->fetch();
        $dump .= "DROP TABLE IF EXISTS `{$table}`;\n" . $create['Create Table'] . ";\n\n";
        $rows = $pdo->query("SELECT * FROM `{$table}`")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $row) {
            $cols = array_map(fn($c) => "`{$c}`", array_keys($row));
            $vals = array_map(fn($v) => $v === null ? 'NULL' : $pdo->quote((string) $v), array_values($row));
            $dump .= "INSERT INTO `{$table}` (" . implode(', ', $cols) . ") VALUES (" . implode(', ', $vals) . ");\n";
        }
        $dump .= "\n";
    }
    $dump .= "SET FOREIGN_KEY_CHECKS=1;\n";
    return $dump;
}
}

if (!function_exists('backup_make_zip')) {
function backup_make_zip(?int $adminId = null): array {
    $pdo = pdo();
    ensure_upload_dir(trim(str_replace(APP_ROOT . '/', '', BACKUP_DIR), '/'));
    $runKey = 'backup_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4));
    $runDir = rtrim(BACKUP_DIR, '/') . '/' . $runKey;
    if (!is_dir($runDir) && !mkdir($runDir, 0775, true) && !is_dir($runDir)) {
        throw new RuntimeException('Unable to create backup folder.');
    }
    $sqlPath = $runDir . '/database.sql';
    file_put_contents($sqlPath, backup_dump_sql($pdo));

    $manifest = [
        'run_key' => $runKey,
        'generated_at' => date('c'),
        'app_name' => APP_NAME,
        'uploads_included' => is_dir(BACKUP_UPLOADS_DIR),
        'google_drive_enabled' => BACKUP_GDRIVE_ENABLED === 1,
    ];
    $manifestPath = $runDir . '/manifest.json';
    file_put_contents($manifestPath, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    $zipPath = $runDir . '/' . $runKey . '.zip';
    $zip = new ZipArchive();
    if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException('Unable to create backup ZIP.');
    }
    $zip->addFile($sqlPath, 'database.sql');
    $zip->addFile($manifestPath, 'manifest.json');
    if (is_dir(BACKUP_UPLOADS_DIR)) {
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(BACKUP_UPLOADS_DIR, FilesystemIterator::SKIP_DOTS));
        foreach ($files as $file) {
            if ($file->isDir()) { continue; }
            $local = 'uploads/' . ltrim(str_replace(rtrim(BACKUP_UPLOADS_DIR, '/') . '/', '', $file->getPathname()), '/');
            $zip->addFile($file->getPathname(), $local);
        }
    }
    $zip->close();

    $driveNote = BACKUP_GDRIVE_ENABLED === 1 ? 'Google Drive upload configured but not executed in this local package.' : 'Google Drive upload disabled.';
    $pdo->prepare('INSERT INTO backup_runs (run_key, zip_path, manifest_path, uploaded_to_drive, status, notes, created_by_admin_id) VALUES (?, ?, ?, ?, ?, ?, ?)')
        ->execute([$runKey, str_replace(APP_ROOT . '/', '', $zipPath), str_replace(APP_ROOT . '/', '', $manifestPath), 0, 'success', $driveNote, $adminId]);

    $rows = $pdo->query('SELECT id, zip_path, manifest_path FROM backup_runs ORDER BY id DESC')->fetchAll();
    $kept = 0;
    $deleteStmt = $pdo->prepare('DELETE FROM backup_runs WHERE id = ?');
    foreach ($rows as $row) {
        $kept++;
        if ($kept <= max(1, BACKUP_RETENTION_COUNT)) { continue; }
        $path = APP_ROOT . '/' . ltrim((string) $row['zip_path'], '/');
        $manifestFile = trim((string) ($row['manifest_path'] ?? ''));
        $manifestPath = $manifestFile !== '' ? APP_ROOT . '/' . ltrim($manifestFile, '/') : dirname($path) . '/manifest.json';
        $dir = dirname($path);
        @unlink($path);
        @unlink($dir . '/database.sql');
        @unlink($manifestPath);
        @rmdir($dir);
        $deleteStmt->execute([(int) $row['id']]);
    }

    return ['run_key' => $runKey, 'zip_path' => $zipPath, 'manifest_path' => $manifestPath, 'notes' => $driveNote];
}
}
