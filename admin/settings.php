<?php
require_once __DIR__ . '/../backend/helpers/query.php';
require_role('admin');
$pdo = pdo();
$admin = current_user();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $fullName = trim($_POST['full_name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $dbUser = $pdo->prepare('SELECT * FROM admins WHERE id = ?');
    $dbUser->execute([$admin['id']]);
    $fresh = $dbUser->fetch();
    if (!$fresh || !password_verify($currentPassword, $fresh['password_hash'])) {
        set_flash('error', 'Current password is incorrect.');
    } else {
        $hash = $fresh['password_hash'];
        if ($newPassword) {
            $hash = password_hash($newPassword, PASSWORD_DEFAULT);
        }
        $pdo->prepare('UPDATE admins SET full_name = ?, username = ?, password_hash = ? WHERE id = ?')->execute([$fullName, $username, $hash, $admin['id']]);
        $admin['full_name'] = $fullName;
        $admin['username'] = $username;
        set_current_user_session($admin, 'admin');
        set_flash('success', 'Admin settings updated.');
    }
    redirect_to('admin/settings.php');
}
$title = 'Admin Settings';
$subtitle = 'Update your admin profile and password';
require_once __DIR__ . '/../backend/partials/header.php';
?>
<div class="card" style="max-width:720px;">
  <form method="post" class="form-grid">
    <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
    <div><label>Full name</label><input name="full_name" value="<?= h($admin['full_name']) ?>" required></div>
    <div><label>Username</label><input name="username" value="<?= h($admin['username']) ?>" required></div>
    <div><label>Current password</label><input type="password" name="current_password" required></div>
    <div><label>New password</label><input type="password" name="new_password" placeholder="Leave blank to keep current"></div>
    <div class="full form-actions"><button class="btn" type="submit">Save settings</button></div>
  </form>
</div>
<?php require_once __DIR__ . '/../backend/partials/footer.php'; ?>
