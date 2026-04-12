<?php
if (defined('FILE_STUDENT_REGISTER_PHP_LOADED')) { return; }
define('FILE_STUDENT_REGISTER_PHP_LOADED', true);

require_once __DIR__ . '/../backend/config/app.php';
$title = 'Student Account Access';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= h(page_title($title)) ?></title>
  <link rel="stylesheet" href="<?= h(asset_url('app.css')) ?>">
</head>
<body class="auth-page auth-student">
  <div class="auth-layout auth-layout-wide">
    <section class="auth-showcase">
      <div class="eyebrow">Student access guide</div>
      <h1>Accounts are teacher-invited for safer onboarding</h1>
      <p class="lead">Students do not create random accounts manually. Teachers invite the correct student using official school data, then the student activates the account securely by email.</p>
      <div class="auth-benefits">
        <div><strong>Cleaner records</strong><span>Prevents duplicate or incorrect student accounts.</span></div>
        <div><strong>Safer activation</strong><span>Students create their own password from a secure email link.</span></div>
        <div><strong>Section-based access</strong><span>Teachers connect students to the correct class from the start.</span></div>
      </div>
    </section>

    <section class="auth-panel-wrap">
      <div class="auth-panel card auth-guide-panel">
        <div class="auth-panel-head">
          <h2>How student access works</h2>
          <p class="muted">Three simple steps from teacher invite to first login.</p>
        </div>
        <div class="timeline-list auth-timeline-spacious">
          <div class="timeline-item"><strong>1. Teacher creates your account</strong><p>Your teacher enters your official student ID, full name, email address, and section.</p></div>
          <div class="timeline-item"><strong>2. You receive an activation email</strong><p>Open the secure link and create your own password. No temporary password is sent publicly.</p></div>
          <div class="timeline-item"><strong>3. Sign in to the student portal</strong><p>After activation, use your student ID or approved email address on the login page.</p></div>
        </div>
        <div class="form-actions auth-actions-tight">
          <a class="btn btn-block" href="<?= h(url('student/login.php')) ?>">Go to student login</a>
          <a class="btn btn-secondary btn-block" href="<?= h(url('student/')) ?>">Back to student portal</a>
        </div>
      </div>
    </section>
  </div>
</body>
</html>
