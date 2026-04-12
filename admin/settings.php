<?php
if (defined('FILE_ADMIN_SETTINGS_PHP_LOADED')) { return; }
define('FILE_ADMIN_SETTINGS_PHP_LOADED', true);

require_once __DIR__ . '/../backend/config/app.php';
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
<div class="settings-shell" data-settings-tabs>
  <div class="settings-tabbar" role="tablist" aria-label="Admin settings sections">
    <button type="button" class="settings-tab is-active" role="tab" aria-selected="true" data-settings-target="admin-profile-tab">Profile</button>
    <button type="button" class="settings-tab" role="tab" aria-selected="false" data-settings-target="admin-security-tab">Security</button>
    <button type="button" class="settings-tab" role="tab" aria-selected="false" data-settings-target="admin-preferences-tab">Preferences</button>
  </div>

  <form method="post" class="stack" style="max-width:920px;">
    <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">

    <section id="admin-profile-tab" class="settings-tab-panel is-active" role="tabpanel">
      <div class="grid cols-2">
        <div class="card">
          <div class="form-grid">
            <div><label>Full name</label><input name="full_name" value="<?= h($admin['full_name']) ?>" required></div>
            <div><label>Username</label><input name="username" value="<?= h($admin['username']) ?>" required></div>
          </div>
        </div>
        <div class="card">
          <h3 class="section-title">Admin identity notice</h3>
          <div class="callout">Use this page for your visible admin profile and access password. School-controlled account ownership should still be handled centrally when needed.</div>
        </div>
      </div>
    </section>

    <section id="admin-security-tab" class="settings-tab-panel" role="tabpanel" hidden>
      <div class="grid cols-2">
        <div class="card">
          <div class="form-grid">
            <div><label>Current password</label><input type="password" name="current_password" required></div>
            <div><label>New password</label><input type="password" name="new_password" placeholder="Leave blank to keep current"></div>
          </div>
          <div class="form-actions"><button class="btn" type="submit">Save settings</button></div>
        </div>
        <div class="card">
          <h3 class="section-title">Security reminders</h3>
          <div class="info-list">
            <div class="row"><span>Access scope</span><strong>Full system administration</strong></div>
            <div class="row"><span>Password rule</span><strong>Use a unique strong password</strong></div>
            <div class="row"><span>Shared devices</span><strong>Avoid storing sessions</strong></div>
          </div>
        </div>
      </div>
    </section>

    <section id="admin-preferences-tab" class="settings-tab-panel" role="tabpanel" hidden>
      <div class="grid cols-2">
        <div class="card">
          <h3 class="section-title">Preferences</h3>
          <div class="callout">This school-ID based platform keeps admin preferences lightweight. More controls can be added later without changing the current design language.</div>
        </div>
        <div class="card">
          <h3 class="section-title">System note</h3>
          <p class="muted">Portal-level personalization is intentionally limited so the account remains consistent across administrative workstations.</p>
        </div>
      </div>
    </section>
  </form>
</div>
<?php require_once __DIR__ . '/../backend/partials/footer.php'; ?>
