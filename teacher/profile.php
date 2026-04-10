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
<div class="grid cols-2">
  <div class="card">
    <h3 class="section-title">Profile settings</h3>
    <form method="post" class="form-grid">
      <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
      <div><label>Full name</label><input name="full_name" value="<?= h($teacher['full_name']) ?>" required></div>
      <div><label>Email</label><input type="email" name="email" value="<?= h($teacher['email']) ?>" required></div>
      <div><label>Username</label><input name="username" value="<?= h($teacher['username']) ?>" required></div>
      <div><label>Current password</label><input type="password" name="current_password" required></div>
      <div class="full"><label>New password</label><input type="password" name="new_password" placeholder="Leave blank to keep current"></div>
      <div class="full form-actions"><button class="btn" type="submit">Save profile</button></div>
    </form>
  </div>
  <div class="card">
    <h3 class="section-title">Teaching summary</h3>
    <div class="kpi-strip">
      <div class="segment"><div class="muted small">Subjects</div><strong><?= (int)$metrics['subjects'] ?></strong></div>
      <div class="segment"><div class="muted small">Submissions</div><strong><?= (int)$metrics['submissions'] ?></strong></div>
      <div class="segment"><div class="muted small">Graded</div><strong><?= (int)$metrics['graded'] ?></strong></div>
    </div>
    <div class="callout" style="margin-top:16px;">Use this page to replace demo credentials and keep your teacher account secure.</div>
  </div>
</div>
<?php require_once __DIR__ . '/../backend/partials/footer.php'; ?>
