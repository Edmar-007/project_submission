<?php
if (defined('FILE_TEACHER_INDEX_PHP_LOADED')) { return; }
define('FILE_TEACHER_INDEX_PHP_LOADED', true);

require_once __DIR__ . '/../backend/config/app.php';
$title = 'Teacher Portal';
$studentLink = url('student/');
$teacherLogin = url('teacher/login.php');
$teacherLink = url('teacher/');
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= h(page_title($title)) ?></title>
  <link rel="stylesheet" href="<?= h(asset_url('app.css')) ?>">
</head>
<body class="landing-body auth-surface auth-teacher-surface">
  <div class="portal-split-shell portal-teacher-shell">
    <section class="portal-split-side">
      <div class="auth-brand">Project Submission System</div>
      <div class="portal-copy-block">
        <h1>Teacher Portal</h1>
        <p>Manage subjects, review submissions, grade projects, and distribute portal links without exposing admin pages.</p>
      </div>
      <div class="auth-side-cta">
        <a class="btn btn-light" href="<?= h($teacherLogin) ?>">Teacher Login →</a>
      </div>
    </section>

    <section class="portal-split-main">
      <div class="auth-floating-card portal-login-card">
        <div class="auth-panel-head compact">
          <h2>Teacher landing</h2>
          <p class="muted">Open the correct portal in one click.</p>
        </div>
        <div class="portal-link-stack teacher-link-stack compact">
          <div class="portal-link-box slim">
            <div>
              <strong>Student landing</strong>
              <code><?= h($studentLink) ?></code>
            </div>
            <a class="btn btn-secondary" href="<?= h($studentLink) ?>">Open</a>
          </div>
          <div class="portal-link-box slim">
            <div>
              <strong>Teacher landing</strong>
              <code><?= h($teacherLink) ?></code>
            </div>
            <a class="btn btn-secondary" href="<?= h($teacherLink) ?>">Open</a>
          </div>
        </div>
      </div>
    </section>
  </div>
</body>
</html>
