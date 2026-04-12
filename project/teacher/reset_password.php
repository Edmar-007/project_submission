<?php
require_once __DIR__ . '/../backend/config/auth.php';
$token = trim($_GET['token'] ?? $_POST['token'] ?? '');
$stmt = pdo()->prepare('SELECT * FROM password_reset_tokens WHERE token = ? AND user_type = "teacher" AND used_at IS NULL AND expires_at > NOW() LIMIT 1');
$stmt->execute([$token]);
$record = $stmt->fetch();
if (!$record) {
    $title = 'Reset Password';
    $subtitle = 'Link invalid or expired';
    require_once __DIR__ . '/../backend/partials/header.php';
    echo '<div class="card"><div class="empty-state">This reset link is invalid or expired.</div></div>';
    require_once __DIR__ . '/../backend/partials/footer.php';
    exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';
    if (strlen($password) < 8 || $password !== $confirm) {
        set_flash('error', 'Password must be at least 8 characters and match confirmation.');
        redirect(url('teacher/reset_password.php?token=' . urlencode($token)));
    }
    pdo()->prepare('UPDATE teachers SET password_hash = ? WHERE id = ?')->execute([password_hash($password, PASSWORD_DEFAULT), $record['user_id']]);
    pdo()->prepare('UPDATE password_reset_tokens SET used_at = NOW() WHERE id = ?')->execute([$record['id']]);
    set_flash('success', 'Password updated. You can now log in.');
    redirect_to('teacher/login.php');
}
$title = 'Reset Password';
$subtitle = 'Choose a new password';
require_once __DIR__ . '/../backend/partials/header.php';
?>
<div class="auth-shell"><div class="card auth-card"><h2>Reset password</h2><form method="post"><input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>"><input type="hidden" name="token" value="<?= h($token) ?>"><div class="grid"><div><label>New password</label><input type="password" name="password" required></div><div><label>Confirm password</label><input type="password" name="confirm_password" required></div></div><div class="form-actions"><button class="btn" type="submit">Update password</button></div></form></div></div>
<?php require_once __DIR__ . '/../backend/partials/footer.php'; ?>
