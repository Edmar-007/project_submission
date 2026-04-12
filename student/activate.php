<?php
if (defined('FILE_STUDENT_ACTIVATE_PHP_LOADED')) { return; }
define('FILE_STUDENT_ACTIVATE_PHP_LOADED', true);

require_once __DIR__ . '/../backend/config/app.php';
$token = trim($_GET['token'] ?? $_POST['token'] ?? '');
$record = null;
$student = null;
if ($token !== '') {
    $stmt = pdo()->prepare('SELECT aat.*, st.full_name, st.student_id, st.email FROM account_activation_tokens aat JOIN students st ON st.id = aat.student_id WHERE aat.token = ? AND aat.used_at IS NULL AND aat.expires_at > NOW() LIMIT 1');
    $stmt->execute([$token]);
    $record = $stmt->fetch();
    if ($record) {
        $student = ['id' => $record['student_id'], 'full_name' => $record['full_name'], 'student_id' => $record['student_id'], 'email' => $record['email']];
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';
    if (!$record) {
        set_flash('error', 'This activation link is invalid or has expired.');
    } elseif (strlen($password) < 8) {
        set_flash('error', 'Password must be at least 8 characters long.');
    } elseif ($password !== $confirm) {
        set_flash('error', 'Passwords do not match.');
    } else {
        $pdo = pdo();
        $pdo->beginTransaction();
        $pdo->prepare('UPDATE students SET password_hash = ?, username = student_id, account_status = "active", can_submit = 1 WHERE id = ?')->execute([password_hash($password, PASSWORD_DEFAULT), $record['student_id']]);
        $pdo->prepare('UPDATE account_activation_tokens SET used_at = NOW() WHERE id = ?')->execute([$record['id']]);
        create_notification('student', (int)$record['student_id'], 'Account activated', 'Your student portal access is now active. You may sign in using your student ID or email address.', 'success');
        $pdo->commit();
        set_flash('success', 'Account activated successfully. Please log in.');
        redirect_to('student/login.php');
    }
}
$title = 'Activate Student Account';
$subtitle = 'Set your password securely using the invitation link sent by your teacher';
require_once __DIR__ . '/../backend/partials/header.php';
?>
<div class="auth-shell">
  <div class="card auth-card" style="max-width:680px;">
    <h2>Activate your account</h2>
    <?php if ($record && $student): ?>
      <div class="callout" style="margin-bottom:16px;"><strong><?= h($student['full_name']) ?></strong><br><span class="muted">Student ID: <?= h($student['student_id']) ?> · <?= h($student['email']) ?></span></div>
      <form method="post" class="grid">
        <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
        <input type="hidden" name="token" value="<?= h($token) ?>">
        <div><label>New password</label><input type="password" name="password" required minlength="8"></div>
        <div><label>Confirm password</label><input type="password" name="confirm_password" required minlength="8"></div>
        <div class="form-actions"><button class="btn" type="submit">Activate account</button><a class="btn btn-secondary" href="<?= h(url('student/login.php')) ?>">Go to login</a></div>
      </form>
    <?php else: ?>
      <div class="notice">This activation link is invalid or expired. Ask your teacher to resend your invitation email.</div>
      <div class="form-actions"><a class="btn btn-secondary" href="<?= h(url('student/')) ?>">Back to student portal</a></div>
    <?php endif; ?>
  </div>
</div>
<?php require_once __DIR__ . '/../backend/partials/footer.php'; ?>
