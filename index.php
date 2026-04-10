<?php
require_once __DIR__ . '/backend/config/app.php';
$stats = ['subjects'=>0,'sections'=>0,'students'=>0];
try {
    $pdo = pdo();
    $stats['subjects'] = (int) $pdo->query("SELECT COUNT(*) FROM subjects WHERE status = 'active'")->fetchColumn();
    $stats['sections'] = (int) $pdo->query("SELECT COUNT(*) FROM sections WHERE status = 'active'")->fetchColumn();
    $stats['students'] = (int) $pdo->query('SELECT COUNT(*) FROM students')->fetchColumn();
} catch (Throwable $e) {}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= h(page_title('Portal Directory')) ?></title>
  <link rel="stylesheet" href="<?= h(asset_url('app.css')) ?>">
</head>
<body class="landing-body">
  <div class="landing-shell">
    <div class="hero-card portal-hero-tight">
      <div class="eyebrow">Portal Directory</div>
      <h1>Project Submission Management System</h1>
      <p class="lead">Use the direct portal links provided to your role. Each user group has its own landing page and login path for a cleaner deployment on your domain.</p>
      <div class="stats-row">
        <div><strong><?= $stats['subjects'] ?></strong><span>Active Subjects</span></div>
        <div><strong><?= $stats['sections'] ?></strong><span>Active Sections</span></div>
        <div><strong><?= $stats['students'] ?></strong><span>Registered Students</span></div>
      </div>
      <div class="portal-root-links">
        <a class="btn" href="<?= h(url('student/')) ?>">Student landing</a>
        <a class="btn btn-secondary" href="<?= h(url('teacher/')) ?>">Teacher landing</a>
        <a class="btn btn-secondary" href="<?= h(url('admin/')) ?>">Super admin landing</a>
      </div>
      <div class="hero-footer">
        <span>Give students only the student link</span>
        <span>Teachers can share the student portal</span>
        <span>Admin access stays separate</span>
      </div>
    </div>
  </div>
</body>
</html>
