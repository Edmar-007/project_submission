<?php
require_once __DIR__ . '/../backend/helpers/query.php';
require_role('teacher');
$pdo = pdo();
$teacher = current_user();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $fullName = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $fresh = $pdo->prepare('SELECT * FROM teachers WHERE id = ?');
    $fresh->execute([$teacher['id']]);
    $row = $fresh->fetch();
    if (!$row || !password_verify($currentPassword, $row['password_hash'])) {
        set_flash('error', 'Current password is incorrect.');
    } else {
        $hash = $row['password_hash'];
        if ($newPassword) {
            $hash = password_hash($newPassword, PASSWORD_DEFAULT);
        }
        $pdo->prepare('UPDATE teachers SET full_name = ?, email = ?, username = ?, password_hash = ? WHERE id = ?')->execute([$fullName, $email, $username, $hash, $teacher['id']]);
        $teacher['full_name'] = $fullName;
        $teacher['email'] = $email;
        $teacher['username'] = $username;
        set_current_user_session($teacher, 'teacher');
        set_flash('success', 'Teacher profile updated successfully.');
    }
    redirect_to('teacher/profile.php');
}
$metrics = [
    'subjects' => count_for_query('SELECT COUNT(*) FROM subjects WHERE teacher_id = ?', [$teacher['id']]),
    'submissions' => count_for_query('SELECT COUNT(*) FROM submissions sub JOIN subjects subj ON subj.id = sub.subject_id WHERE subj.teacher_id = ?', [$teacher['id']]),
    'graded' => count_for_query('SELECT COUNT(*) FROM submissions sub JOIN subjects subj ON subj.id = sub.subject_id WHERE subj.teacher_id = ? AND sub.status = "graded"', [$teacher['id']]),
];
$title = 'Teacher Profile';
$subtitle = 'Update account credentials and review your teaching footprint';
require_once __DIR__ . '/../backend/partials/header.php';
?>
<div class="settings-shell" data-settings-tabs>
  <div class="settings-tabbar" role="tablist" aria-label="Teacher settings sections">
    <button type="button" class="settings-tab is-active" role="tab" aria-selected="true" data-settings-target="teacher-profile-tab">Profile</button>
    <button type="button" class="settings-tab" role="tab" aria-selected="false" data-settings-target="teacher-security-tab">Security</button>
    <button type="button" class="settings-tab" role="tab" aria-selected="false" data-settings-target="teacher-preferences-tab">Preferences</button>
  </div>

  <form method="post" class="stack">
    <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">

    <section id="teacher-profile-tab" class="settings-tab-panel is-active" role="tabpanel">
      <div class="grid cols-2">
        <div class="card">
          <h3 class="section-title">Profile settings</h3>
          <div class="form-grid">
            <div><label>Full name</label><input name="full_name" value="<?= h($teacher['full_name']) ?>" required></div>
            <div><label>Email</label><input type="email" name="email" value="<?= h($teacher['email']) ?>" required></div>
            <div class="full"><label>Username</label><input name="username" value="<?= h($teacher['username']) ?>" required></div>
          </div>
        </div>
        <div class="card">
          <h3 class="section-title">Identity notice</h3>
          <div class="callout">Teacher accounts are tied to the school-managed identity used for classroom access. Keep the visible contact details accurate, and ask the administrator for official identity corrections when needed.</div>
        </div>
      </div>
    </section>

    <section id="teacher-security-tab" class="settings-tab-panel" role="tabpanel" hidden>
      <div class="grid cols-2">
        <div class="card">
          <h3 class="section-title">Security</h3>
          <div class="form-grid">
            <div><label>Current password</label><input type="password" name="current_password" required></div>
            <div><label>New password</label><input type="password" name="new_password" placeholder="Leave blank to keep current"></div>
          </div>
          <div class="form-actions"><button class="btn" type="submit">Save profile</button></div>
        </div>
        <div class="card">
          <h3 class="section-title">Security reminders</h3>
          <div class="info-list">
            <div class="row"><span>Portal role</span><strong>Teacher</strong></div>
            <div class="row"><span>Session safety</span><strong>Use only trusted devices</strong></div>
            <div class="row"><span>Password guidance</span><strong>Choose a unique password</strong></div>
          </div>
        </div>
      </div>
    </section>

    <section id="teacher-preferences-tab" class="settings-tab-panel" role="tabpanel" hidden>
      <div class="grid cols-2">
        <div class="card">
          <h3 class="section-title">Teaching summary</h3>
          <div class="kpi-strip">
            <div class="segment"><div class="muted small">Subjects</div><strong><?= (int)$metrics['subjects'] ?></strong></div>
            <div class="segment"><div class="muted small">Submissions</div><strong><?= (int)$metrics['submissions'] ?></strong></div>
            <div class="segment"><div class="muted small">Graded</div><strong><?= (int)$metrics['graded'] ?></strong></div>
          </div>
        </div>
        <div class="card">
          <h3 class="section-title">School-managed preferences</h3>
          <div class="callout">This portal is school-ID based. Core identity and access preferences are managed by the institution, so this area stays intentionally simple.</div>
        </div>
      </div>
    </section>
  </form>
</div>
<?php require_once __DIR__ . '/../backend/partials/footer.php'; ?>
