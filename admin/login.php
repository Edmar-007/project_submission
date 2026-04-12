<?php
if (defined('FILE_ADMIN_LOGIN_PHP_LOADED')) { return; }
define('FILE_ADMIN_LOGIN_PHP_LOADED', true);

require_once __DIR__ . '/../backend/config/app.php';
if (current_role() === 'admin') redirect_to('admin/dashboard.php');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $user = authenticate_table('admins', trim($_POST['username'] ?? ''), $_POST['password'] ?? '');
    if ($user) {
        login_user('admin', $user);
        set_flash('success', 'Welcome back, ' . $user['full_name'] . '.');
        redirect_to('admin/dashboard.php');
    }
    set_flash('error', 'Invalid admin credentials.');
}
$title = 'Admin Login';
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
<body class="landing-body auth-page auth-surface auth-admin-surface">
  <div class="auth-split-shell admin-split-shell">
    <section class="auth-split-side">
      <div class="auth-brand">Project Submission Platform</div>
      <div class="auth-copy-block">
        <h1>Control the academic submission platform</h1>
        <p>Super admins manage sections, subjects, teachers, restrictions, reports, and lifecycle controls from one secure workspace.</p>
      </div>
      <div class="auth-side-feature auth-side-feature-key">
        <span class="auth-feature-icon">✦</span>
        <div>
          <strong>Super admin access</strong>
          <span>Protected control center for platform-wide actions.</span>
        </div>
      </div>
    </section>

    <section class="auth-split-main">
      <div class="auth-floating-card">
        <div class="auth-panel-head compact">
          <h2>Admin Login</h2>
          <p class="muted">Sign in to continue to the control center.</p>
        </div>
        <?php foreach ($flashes as $flash): ?>
          <div class="flash <?= h($flash['type']) ?>"><?= h($flash['message']) ?></div>
        <?php endforeach; ?>
        <form method="post" class="auth-form-grid modern-auth-form">
          <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
          <div>
            <label>Username</label>
            <input name="username" placeholder="Enter admin username" required>
          </div>
          <div>
            <label>Password</label>
            <input type="password" name="password" placeholder="Enter password" required>
          </div>
          <button class="btn btn-block" type="submit">Super admin login</button>
        </form>
        <div class="auth-link-stack compact-links">
          <div class="portal-link-mini">
            <strong>Shareable teacher link</strong>
            <code><?= h(url('teacher/')) ?></code>
          </div>
          <div class="portal-link-mini">
            <strong>Shareable student link</strong>
            <code><?= h(url('student/')) ?></code>
          </div>
        </div>
      </div>
    </section>
  </div>
</body>
</html>
