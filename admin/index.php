<?php
if (defined('FILE_ADMIN_INDEX_PHP_LOADED')) { return; }
define('FILE_ADMIN_INDEX_PHP_LOADED', true);

require_once __DIR__ . '/../backend/config/app.php';
$title = 'Super Admin Portal';
$studentLink = url('student/');
$teacherLink = url('teacher/');
$adminLink = url('admin/login.php');
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= h(page_title($title)) ?></title>
  <link rel="stylesheet" href="<?= h(asset_url('app.css')) ?>">
</head>
<body class="landing-body auth-surface auth-admin-surface">
  <div class="portal-split-shell portal-admin-shell">
    <section class="portal-split-side">
      <div class="auth-brand">Project Submission Platform</div>
      <div class="portal-copy-block">
        <h1>Super admin access</h1>
        <p>Manage sections, subjects, teachers, restrictions, reports, and lifecycle actions from one protected control center.</p>
      </div>
      <div class="auth-side-feature auth-side-feature-key">
        <span class="auth-feature-icon">⌘</span>
        <div>
          <strong>Centralized control</strong>
          <span>Admin-only entry with protected workflows.</span>
        </div>
      </div>
    </section>

    <section class="portal-split-main">
      <div class="auth-floating-card portal-login-card">
        <div class="auth-panel-head compact">
          <h2>Admin Login</h2>
          <p class="muted">Enter the control center.</p>
        </div>
        <div class="portal-actions split-stacked">
          <a class="btn btn-block" href="<?= h($adminLink) ?>">Super admin login</a>
        </div>
        <div class="portal-link-grid modern-link-grid two-up">
          <div class="portal-link-mini tall">
            <strong>Shareable teacher link</strong>
            <code><?= h($teacherLink) ?></code>
            <a class="btn btn-secondary btn-block" href="<?= h($teacherLink) ?>">Open</a>
          </div>
          <div class="portal-link-mini tall">
            <strong>Shareable student link</strong>
            <code><?= h($studentLink) ?></code>
            <a class="btn btn-secondary btn-block" href="<?= h($studentLink) ?>">Open</a>
          </div>
        </div>
      </div>
    </section>
  </div>
</body>
</html>
