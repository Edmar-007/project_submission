<?php
if (defined('FILE_TEACHER_FORGOT_PASSWORD_PHP_LOADED')) { return; }
define('FILE_TEACHER_FORGOT_PASSWORD_PHP_LOADED', true);

require_once __DIR__ . '/../backend/config/app.php';
require_once __DIR__ . '/../backend/helpers/mailer.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $identity = trim($_POST['identity'] ?? '');
    $stmt = pdo()->prepare('SELECT id, full_name, email FROM teachers WHERE (username = ? OR email = ?) LIMIT 1');
    $stmt->execute([$identity, $identity]);
    if ($user = $stmt->fetch()) {
        $token = bin2hex(random_bytes(24));
        pdo()->prepare('INSERT INTO password_reset_tokens (user_type, user_id, token, expires_at) VALUES ("teacher", ?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR))')->execute([$user['id'], $token]);
        $link = url('teacher/reset_password.php?token=' . $token);
        send_system_mail($user['email'], 'Teacher password reset', "Hello {$user['full_name']},\n\nUse this link to reset your password:\n{$link}\n\nThis link expires in 1 hour.");
    }
    set_flash('success', 'If the account exists, a reset link has been generated and logged/emailed.');
    redirect_to('teacher/login.php');
}
$title = 'Forgot Password';
$subtitle = 'Reset your teacher account password';
require_once __DIR__ . '/../backend/partials/header.php';
?>
<div class="auth-shell"><div class="card auth-card"><h2>Forgot password</h2><form method="post"><input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>"><div class="grid"><div><label>Username or email</label><input name="identity" required></div></div><div class="form-actions"><button class="btn" type="submit">Send reset link</button><a class="btn btn-secondary" href="<?= h(url('teacher/login.php')) ?>">Back</a></div></form></div></div>
<?php require_once __DIR__ . '/../backend/partials/footer.php'; ?>
