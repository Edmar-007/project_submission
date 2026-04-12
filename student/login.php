<?php
if (defined('FILE_STUDENT_LOGIN_PHP_LOADED')) { return; }
define('FILE_STUDENT_LOGIN_PHP_LOADED', true);

require_once __DIR__ . '/../backend/config/app.php';
if (current_role() === 'student') redirect_to('student/dashboard.php');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $user = authenticate_table('students', trim($_POST['username'] ?? ''), $_POST['password'] ?? '');
    if ($user) {
        login_user('student', $user);
        set_flash('success', 'Welcome back, ' . $user['full_name'] . '.');
        redirect_to('student/dashboard.php');
    }
    set_flash('error', 'Invalid credentials or your account has not been activated yet.');
}
$title = 'Student Login';
$flashes = get_flashes();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= h(page_title($title)) ?></title>
  <link rel="stylesheet" href="<?= h(asset_url('app.css')) ?>">
</head>
<body class="landing-body auth-page auth-surface auth-student-surface">
  <div class="auth-split-shell student-split-shell">
    <section class="auth-split-side">
      <div class="auth-brand">Aether PSMS</div>
      <div class="auth-copy-block">
        <h1>Manage your project access from one student hub</h1>
        <p>Track shared team submissions, grades, links, feedback, and teacher notices without opening any admin or teacher pages.</p>
      </div>
      <div class="student-feature-grid">
        <div class="student-feature-tile"><strong>Team Projects</strong></div>
        <div class="student-feature-tile"><strong>Grades &amp; Feedback</strong></div>
        <div class="student-feature-tile"><strong>Teacher Communications</strong></div>
      </div>
      <div class="student-side-nav">
        <span>Dashboard</span>
        <span>Subjects</span>
        <span>Submissions</span>
      </div>
    </section>

    <section class="auth-split-main">
      <div class="auth-floating-card student-login-card">
        <div class="auth-panel-head compact">
          <h2>Student Login</h2>
          <p class="muted">Use your student ID or school email.</p>
        </div>
        <?php foreach ($flashes as $flash): ?>
          <div class="flash <?= h($flash['type']) ?>"><?= h($flash['message']) ?></div>
        <?php endforeach; ?>
        <form method="post" class="auth-form-grid modern-auth-form">
          <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
          <div>
            <label>Student ID or Email</label>
            <input name="username" placeholder="Enter your invited account" required>
          </div>
          <div>
            <label>Password</label>
            <input type="password" name="password" placeholder="Enter password" required>
          </div>
          <a class="auth-text-link" href="<?= h(url('student/forgot_password.php')) ?>">Forgot Password?</a>
          <button class="btn btn-block" type="submit">Student Login</button>
        </form>
        <div class="student-login-actions">
          <a class="btn btn-secondary" href="<?= h(url('student/register.php')) ?>">How to get access</a>
          <a class="btn btn-outline" href="<?= h(url('student/register.php')) ?>">Create account</a>
        </div>
        <div class="student-login-note">Secure, dedicated student-only access. Same shared link, centralized portal.</div>
      </div>
    </section>
  </div>
</body>
</html>
