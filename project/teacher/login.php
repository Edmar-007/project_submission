<?php
require_once __DIR__ . '/../backend/config/auth.php';
if (current_role() === 'teacher') redirect_to('teacher/dashboard.php');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $user = authenticate_table('teachers', trim($_POST['username'] ?? ''), $_POST['password'] ?? '');
    if ($user) {
        login_user('teacher', $user);
        set_flash('success', 'Welcome back, ' . $user['full_name'] . '.');
        redirect_to('teacher/dashboard.php');
    }
    set_flash('error', 'Invalid teacher credentials.');
}
$title = 'Teacher Login';
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
<body class="landing-body auth-page auth-surface auth-teacher-surface">
  <div class="auth-split-shell teacher-split-shell">
    <section class="auth-split-side">
      <div class="auth-brand">Project Submission System</div>
      <div class="auth-copy-block">
        <h1>Review submissions and guide students faster</h1>
        <p>Manage subjects, review submission links, grade projects, and distribute the student portal from one teacher-only workspace.</p>
      </div>
      <div class="auth-side-cta">
        <a class="btn btn-light" href="<?= h(url('teacher/')) ?>">Teacher Login →</a>
      </div>
    </section>

    <section class="auth-split-main">
      <div class="auth-floating-card">
        <div class="auth-panel-head compact">
          <h2>Teacher Login</h2>
          <p class="muted">Use your assigned teacher account.</p>
        </div>
        <?php foreach ($flashes as $flash): ?>
          <div class="flash <?= h($flash['type']) ?>"><?= h($flash['message']) ?></div>
        <?php endforeach; ?>
        <form method="post" class="auth-form-grid modern-auth-form">
          <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
          <div>
            <label>Username</label>
            <input name="username" placeholder="Enter teacher username" required>
          </div>
          <div>
            <label>Password</label>
            <input type="password" name="password" placeholder="Enter password" required>
          </div>
          <div class="auth-inline-actions">
            <button class="btn" type="submit">Login</button>
            <a class="btn btn-secondary" href="<?= h(url('teacher/')) ?>">Back to portal</a>
          </div>
        </form>
        <div class="portal-link-stack teacher-link-stack">
          <div class="portal-link-box slim">
            <div>
              <strong>Student landing</strong>
              <code><?= h(url('student/')) ?></code>
            </div>
            <a class="btn btn-outline" href="<?= h(url('student/')) ?>">Open</a>
          </div>
          <div class="portal-link-box slim">
            <div>
              <strong>Teacher landing</strong>
              <code><?= h(url('teacher/')) ?></code>
            </div>
            <a class="btn btn-outline" href="<?= h(url('teacher/')) ?>">Open</a>
          </div>
        </div>
      </div>
    </section>
  </div>
</body>
</html>
