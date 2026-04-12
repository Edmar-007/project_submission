<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../helpers/uploads.php';
require_once __DIR__ . '/../helpers/backup.php';
$result = backup_make_zip(null);
echo "Created backup: {$result['zip_path']}\n";
