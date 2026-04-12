<?php
require_once __DIR__ . '/../backend/config/app.php';
$title = 'Student Portal';
$loginLink = url('student/login.php');
$accessLink = url('student/register.php');
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= h(page_title($title)) ?></title>
  <link rel="stylesheet" href="<?= h(asset_url('app.css')) ?>">
</head>
<body class="landing-body auth-surface auth-student-surface">
  <div class="portal-split-shell portal-student-shell">
    <section class="portal-split-side">
      <div class="auth-brand">Aether PSMS</div>
      <div class="portal-copy-block">
        <h1>Student Portal</h1>
        <p>Access your team project, grades, teacher feedback, and notifications from one centralized student hub.</p>
      </div>
      <div class="student-feature-grid landing-tiles">
        <div class="student-feature-tile"><strong>Team Projects</strong></div>
        <div class="student-feature-tile"><strong>Grades &amp; Feedback</strong></div>
        <div class="student-feature-tile"><strong>Teacher Communications</strong></div>
      </div>
    </section>

    <section class="portal-split-main">
      <div class="auth-floating-card student-login-card">
        <div class="auth-panel-head compact">
          <h2>Student access</h2>
          <p class="muted">Use the right option below.</p>
        </div>
        <div class="student-login-actions stacked">
          <a class="btn btn-block" href="<?= h($loginLink) ?>">Student Login</a>
          <a class="btn btn-outline btn-block" href="<?= h($accessLink) ?>">Create account</a>
        </div>
        <div class="student-login-note">Dedicated student-only entry. Same shared link, centralized portal.</div>
      </div>
    </section>
  </div>
</body>
</html>
