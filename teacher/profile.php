<?php
if (defined('FILE_TEACHER_PROFILE_PHP_LOADED')) { return; }
define('FILE_TEACHER_PROFILE_PHP_LOADED', true);

require_once __DIR__ . '/../backend/config/app.php';
require_role('teacher');
$pdo = pdo();
$teacher = current_user();

function teacher_profile_avatar_initial(array $teacher): string {
    return strtoupper(substr(trim((string) ($teacher['full_name'] ?? $teacher['username'] ?? 'U')), 0, 1));
}

function teacher_avatar_upload(array $file, int $teacherId): array {
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return ['ok' => true, 'path' => null, 'message' => ''];
    }
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'message' => 'Avatar upload failed. Please try again.'];
    }
    if (($file['size'] ?? 0) > 2 * 1024 * 1024) {
        return ['ok' => false, 'message' => 'Avatar must be 2 MB or smaller.'];
    }

    $tmp = (string) ($file['tmp_name'] ?? '');
    $mime = '';
    if (is_file($tmp) && function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo) {
            $mime = (string) finfo_file($finfo, $tmp);
            finfo_close($finfo);
        }
    }

    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];
    $ext = $allowed[$mime] ?? '';
    if ($ext === '') {
        $original = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
        $ext = in_array($original, ['jpg', 'jpeg', 'png', 'webp'], true) ? ($original === 'jpeg' ? 'jpg' : $original) : '';
    }
    if ($ext === '') {
        return ['ok' => false, 'message' => 'Only JPG, PNG, and WEBP avatar images are allowed.'];
    }

    $dir = APP_ROOT . '/uploads/avatars/teachers';
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        return ['ok' => false, 'message' => 'Unable to create the avatar upload folder.'];
    }

    foreach (glob($dir . '/teacher_' . $teacherId . '.*') ?: [] as $existing) {
        @unlink($existing);
    }

    $filename = 'teacher_' . $teacherId . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
    $destination = $dir . '/' . $filename;
    if (!move_uploaded_file($tmp, $destination)) {
        return ['ok' => false, 'message' => 'Unable to save the uploaded avatar image.'];
    }

    return ['ok' => true, 'path' => 'uploads/avatars/teachers/' . $filename, 'message' => ''];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['profile_action'] ?? 'profile_update';

    if ($action === 'profile_update') {
        $fullName = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $username = trim($_POST['username'] ?? '');

        if ($fullName === '' || $email === '' || $username === '') {
            set_flash('error', 'Full name, email, and username are required.');
            redirect_to('teacher/profile.php');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            set_flash('error', 'Please enter a valid email address.');
            redirect_to('teacher/profile.php');
        }

        $conflict = $pdo->prepare('SELECT id FROM teachers WHERE (email = ? OR username = ?) AND id <> ? LIMIT 1');
        $conflict->execute([$email, $username, $teacher['id']]);
        if ($conflict->fetch()) {
            set_flash('error', 'That email or username is already being used by another teacher account.');
            redirect_to('teacher/profile.php');
        }

        $fresh = $pdo->prepare('SELECT * FROM teachers WHERE id = ?');
        $fresh->execute([$teacher['id']]);
        $row = $fresh->fetch();
        if (!$row) {
            set_flash('error', 'Unable to load your teacher profile.');
            redirect_to('teacher/profile.php');
        }

        $avatarPath = $row['avatar_path'] ?? null;
        if (!empty($_POST['remove_avatar'])) {
            $existing = trim((string) $avatarPath);
            if ($existing !== '') {
                @unlink(APP_ROOT . '/' . ltrim($existing, '/'));
            }
            $avatarPath = null;
        }
        if (!empty($_FILES['avatar']['name'])) {
            $upload = teacher_avatar_upload($_FILES['avatar'], (int) $teacher['id']);
            if (!$upload['ok']) {
                set_flash('error', $upload['message']);
                redirect_to('teacher/profile.php');
            }
            if (!empty($upload['path'])) {
                $avatarPath = $upload['path'];
            }
        }

        $pdo->prepare('UPDATE teachers SET full_name = ?, email = ?, username = ?, avatar_path = ? WHERE id = ?')
            ->execute([$fullName, $email, $username, $avatarPath, $teacher['id']]);

        $teacher['full_name'] = $fullName;
        $teacher['email'] = $email;
        $teacher['username'] = $username;
        $teacher['avatar_path'] = $avatarPath;
        set_current_user_session($teacher, 'teacher');
        set_flash('success', 'Teacher profile updated successfully.');
        redirect_to('teacher/profile.php');
    }

    if ($action === 'security_update') {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';

        if ($currentPassword === '') {
            set_flash('error', 'Enter your current password first.');
            redirect_to('teacher/profile.php#security');
        }
        if ($newPassword !== '' && strlen($newPassword) < 8) {
            set_flash('error', 'New password must be at least 8 characters long.');
            redirect_to('teacher/profile.php#security');
        }

        $fresh = $pdo->prepare('SELECT * FROM teachers WHERE id = ?');
        $fresh->execute([$teacher['id']]);
        $row = $fresh->fetch();
        if (!$row || !password_verify($currentPassword, $row['password_hash'])) {
            set_flash('error', 'Current password is incorrect.');
            redirect_to('teacher/profile.php#security');
        }

        if ($newPassword === '') {
            set_flash('info', 'No new password entered, so your current password was kept.');
            redirect_to('teacher/profile.php#security');
        }

        $pdo->prepare('UPDATE teachers SET password_hash = ? WHERE id = ?')
            ->execute([password_hash($newPassword, PASSWORD_DEFAULT), $teacher['id']]);
        set_flash('success', 'Password updated successfully.');
        redirect_to('teacher/profile.php#security');
    }
}

$metrics = [
    'subjects' => count_for_query('SELECT COUNT(*) FROM subjects WHERE teacher_id = ?', [$teacher['id']]),
    'submissions' => count_for_query('SELECT COUNT(*) FROM submissions sub JOIN subjects subj ON subj.id = sub.subject_id WHERE subj.teacher_id = ?', [$teacher['id']]),
    'graded' => count_for_query('SELECT COUNT(*) FROM submissions sub JOIN subjects subj ON subj.id = sub.subject_id WHERE subj.teacher_id = ? AND sub.status = "graded"', [$teacher['id']]),
];
$title = 'Teacher Profile';
$subtitle = 'Update account credentials and review your teaching footprint';
$avatarInitial = teacher_profile_avatar_initial($teacher);
$avatarPath = trim((string) ($teacher['avatar_path'] ?? ''));
$avatarUrl = $avatarPath !== '' ? url($avatarPath) : '';
$avatarStyle = $avatarUrl !== '' ? ' style="background-image:url(' . h($avatarUrl) . ')"' : '';
$avatarClass = 'student-profile-avatar';
if ($avatarUrl !== '') {
    $avatarClass .= ' has-image';
}
require_once __DIR__ . '/../backend/partials/header.php';
?>
<div class="settings-shell" data-settings-tabs>
  <div class="settings-tabbar" role="tablist" aria-label="Teacher settings sections">
    <button type="button" class="settings-tab is-active" role="tab" aria-selected="true" data-settings-target="teacher-profile-tab">Profile</button>
    <button type="button" class="settings-tab" role="tab" aria-selected="false" data-settings-target="teacher-security-tab">Security</button>
    <button type="button" class="settings-tab" role="tab" aria-selected="false" data-settings-target="teacher-preferences-tab">Preferences</button>
  </div>

  <section id="teacher-profile-tab" class="settings-tab-panel is-active" role="tabpanel">
    <p class="muted" style="margin: 0 0 1rem; max-width: 900px;">Teacher accounts are tied to the school-managed identity used for classroom access. Keep your visible contact details accurate, and contact the administrator for official identity corrections when needed.</p>

    <form method="post" enctype="multipart/form-data" class="card stack">
      <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
      <input type="hidden" name="profile_action" value="profile_update">

      <div class="student-profile-layout">
        <article class="card student-profile-media-card">
          <h3 class="section-title">Avatar</h3>
          <div class="student-profile-avatar-wrap">
            <div class="<?= h($avatarClass) ?>" data-student-avatar data-avatar-initial="<?= h($avatarInitial) ?>"<?= $avatarStyle ?>><?= h($avatarInitial) ?></div>
          </div>
          <div class="stack" style="gap:.75rem; width:100%;">
            <div>
              <label>Upload new avatar</label>
              <input type="file" name="avatar" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
            </div>
            <?php if ($avatarUrl !== ''): ?>
              <label class="check-inline"><input type="checkbox" name="remove_avatar" value="1"> Remove current avatar</label>
            <?php endif; ?>
            <p class="muted small">Supported formats: JPG, PNG, WEBP. Maximum file size: 2 MB.</p>
          </div>
        </article>

        <article class="card student-profile-form-card">
          <h3 class="section-title">Profile settings</h3>
          <div class="form-grid">
            <div><label>Full name</label><input name="full_name" value="<?= h($teacher['full_name']) ?>" required></div>
            <div><label>Email</label><input type="email" name="email" value="<?= h($teacher['email']) ?>" required></div>
            <div><label>Username</label><input name="username" value="<?= h($teacher['username']) ?>" required></div>
            <div><label>Teacher ID</label><input value="<?= h($teacher['teacher_id'] ?? '') ?>" readonly></div>
          </div>
          <div class="form-actions"><button class="btn" type="submit">Update Profile</button></div>
        </article>
      </div>
    </form>
  </section>

  <section id="teacher-security-tab" class="settings-tab-panel" role="tabpanel" hidden>
    <form method="post" class="card stack">
      <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
      <input type="hidden" name="profile_action" value="security_update">
      <h3 class="section-title">Security</h3>
      <div class="form-grid">
        <div><label>Current password</label><input type="password" name="current_password" required></div>
        <div><label>New password</label><input type="password" name="new_password" placeholder="Leave blank to keep current"></div>
      </div>
      <p class="muted small">Use a strong password with at least 8 characters.</p>
      <div class="form-actions"><button class="btn" type="submit">Save Password</button></div>
    </form>
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
</div>
<?php require_once __DIR__ . '/../backend/partials/footer.php'; ?>
