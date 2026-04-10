<?php
require_once __DIR__ . '/../backend/helpers/query.php';
require_role('student');
$pdo = pdo();
$student = current_user();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $fullName = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $fresh = $pdo->prepare('SELECT * FROM students WHERE id = ?');
    $fresh->execute([$student['id']]);
    $row = $fresh->fetch();
    if (!$row || !password_verify($currentPassword, $row['password_hash'])) {
        set_flash('error', 'Current password is incorrect.');
    } else {
        $hash = $row['password_hash'];
        if ($newPassword) {
            $hash = password_hash($newPassword, PASSWORD_DEFAULT);
        }
        $pdo->prepare('UPDATE students SET full_name = ?, email = ?, password_hash = ? WHERE id = ?')->execute([$fullName, $email, $hash, $student['id']]);
        $student['full_name'] = $fullName;
        $student['email'] = $email;
        set_current_user_session($student, 'student');
        set_flash('success', 'Profile updated successfully.');
    }
    redirect_to('student/profile.php');
}
$title = 'Profile';
$subtitle = 'Update your personal details and password';
require_once __DIR__ . '/../backend/partials/header.php';
$avatarInitial = strtoupper(substr(trim((string) ($student['full_name'] ?? $student['username'] ?? 'U')), 0, 1));
?>
<section class="student-page-shell">
  <div class="student-page-card">
    <div class="student-page-toolbar student-simple-toolbar">
      <div>
        <div class="eyebrow">Account Settings</div>
        <h2>Profile</h2>
        <p>Update your personal details, password, and how your student profile appears inside the portal.</p>
      </div>
    </div>

    <div class="student-profile-layout">
      <article class="card student-profile-media-card">
        <h3>Profile Picture</h3>
        <div class="student-profile-avatar-wrap">
          <div class="student-profile-avatar" data-student-avatar data-avatar-initial="<?= h($avatarInitial) ?>"><?= h($avatarInitial) ?></div>
        </div>
        <button class="btn" type="button" data-open-modal="student-avatar-modal">Upload New Picture</button>
        <p class="muted small">Supported formats: JPG, PNG. This preview is saved in your browser for the demo interface.</p>
      </article>

      <article class="card student-profile-form-card">
        <form method="post" class="form-grid">
          <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
          <div><label>Full name</label><input name="full_name" value="<?= h($student['full_name']) ?>" required></div>
          <div><label>Email</label><input type="email" name="email" value="<?= h($student['email']) ?>" required></div>
          <div><label>Current password</label><input type="password" name="current_password" required placeholder="Enter current password to make changes"></div>
          <div><label>New password</label><input type="password" name="new_password" placeholder="Leave blank to keep current"></div>
          <div class="full form-actions"><button class="btn" type="submit">Save profile</button></div>
        </form>
      </article>
    </div>
  </div>
</section>

<div class="modal-backdrop" data-modal="student-avatar-modal" aria-hidden="true">
  <div class="modal-card student-avatar-modal-card" role="dialog" aria-modal="true" aria-labelledby="student-avatar-modal-title">
    <div class="modal-head">
      <div>
        <span class="pill soft">Profile picture</span>
        <h3 id="student-avatar-modal-title">Adjust Your Profile Picture</h3>
        <p class="muted">Choose an image to preview inside the student portal interface.</p>
      </div>
      <button type="button" class="icon-btn modal-close" data-close-modal aria-label="Close profile picture dialog">✕</button>
    </div>

    <div class="student-avatar-modal-preview">
      <div class="student-profile-avatar is-large" data-student-avatar-preview data-avatar-initial="<?= h($avatarInitial) ?>"><?= h($avatarInitial) ?></div>
    </div>

    <div class="student-avatar-form-controls">
      <label class="btn btn-secondary student-upload-trigger">
        Choose image
        <input type="file" accept="image/*" data-student-avatar-input hidden>
      </label>
      <button type="button" class="btn btn-ghost" data-student-avatar-reset>Reset</button>
    </div>

    <div class="form-actions modal-actions">
      <button type="button" class="btn btn-outline" data-close-modal>Cancel</button>
      <button type="button" class="btn" data-student-avatar-save>Save &amp; Apply</button>
    </div>
  </div>
</div>
<?php require_once __DIR__ . '/../backend/partials/footer.php'; ?>
