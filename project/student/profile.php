<?php
require_once __DIR__ . '/../backend/helpers/query.php';
require_once __DIR__ . '/../backend/helpers/uploads.php';
require_role('student');
$pdo = pdo();
$student = current_user();
$studentDetail = fetch_student_detail((int) ($student['id'] ?? 0)) ?: $student;

function student_profile_avatar_initial(array $student): string {
    return strtoupper(substr(trim((string) ($student['full_name'] ?? $student['username'] ?? 'U')), 0, 1));
}

function student_avatar_upload(array $file, int $studentId): array {
    $result = store_uploaded_file(
        $file,
        'uploads/avatars/students',
        'student_' . $studentId,
        ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'],
        2 * 1024 * 1024,
        ['jpg', 'jpeg', 'png', 'webp']
    );
    if (!$result['ok']) {
        if ($result['message'] === 'Uploaded file exceeds the allowed size.') {
            $result['message'] = 'Avatar must be 2 MB or smaller.';
        } elseif ($result['message'] === 'File type is not allowed.') {
            $result['message'] = 'Only JPG, PNG, and WEBP avatar images are allowed.';
        }
    }
    return $result;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['profile_action'] ?? 'profile_update';

    if ($action === 'profile_update') {
        $fullName = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        if ($fullName === '' || $email === '') {
            set_flash('error', 'Full name and email are required.');
            redirect_to('student/profile.php');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            set_flash('error', 'Please enter a valid email address.');
            redirect_to('student/profile.php');
        }
        $conflict = $pdo->prepare('SELECT id FROM students WHERE email = ? AND id <> ? LIMIT 1');
        $conflict->execute([$email, $student['id']]);
        if ($conflict->fetch()) {
            set_flash('error', 'That email address is already being used by another student account.');
            redirect_to('student/profile.php');
        }
        $fresh = $pdo->prepare('SELECT * FROM students WHERE id = ?');
        $fresh->execute([$student['id']]);
        $row = $fresh->fetch();
        if (!$row) {
            set_flash('error', 'Unable to load your student profile.');
            redirect_to('student/profile.php');
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
            $previousAvatarPath = trim((string) $avatarPath);
            $upload = student_avatar_upload($_FILES['avatar'], (int) $student['id']);
            if (!$upload['ok']) {
                set_flash('error', $upload['message']);
                redirect_to('student/profile.php');
            }
            if (!empty($upload['path'])) {
                $avatarPath = $upload['path'];
                if ($previousAvatarPath !== '' && $previousAvatarPath !== $avatarPath) {
                    @unlink(APP_ROOT . '/' . ltrim($previousAvatarPath, '/'));
                }
            }
        }

        $pdo->prepare('UPDATE students SET full_name = ?, email = ?, avatar_path = ? WHERE id = ?')
            ->execute([$fullName, $email, $avatarPath, $student['id']]);
        $student['full_name'] = $fullName;
        $student['email'] = $email;
        $student['avatar_path'] = $avatarPath;
        set_current_user_session($student, 'student');
        set_flash('success', 'Student profile updated successfully.');
        redirect_to('student/profile.php');
    }

    if ($action === 'security_update') {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        if ($currentPassword === '') {
            set_flash('error', 'Enter your current password first.');
            redirect_to('student/profile.php#security');
        }
        if ($newPassword !== '' && strlen($newPassword) < 8) {
            set_flash('error', 'New password must be at least 8 characters long.');
            redirect_to('student/profile.php#security');
        }
        $fresh = $pdo->prepare('SELECT * FROM students WHERE id = ?');
        $fresh->execute([$student['id']]);
        $row = $fresh->fetch();
        if (!$row || !password_verify($currentPassword, $row['password_hash'])) {
            set_flash('error', 'Current password is incorrect.');
            redirect_to('student/profile.php#security');
        }
        if ($newPassword === '') {
            set_flash('info', 'No new password entered, so your current password was kept.');
            redirect_to('student/profile.php#security');
        }
        $pdo->prepare('UPDATE students SET password_hash = ? WHERE id = ?')->execute([password_hash($newPassword, PASSWORD_DEFAULT), $student['id']]);
        set_flash('success', 'Password updated successfully.');
        redirect_to('student/profile.php#security');
    }
}

$title = 'Profile';
$subtitle = 'Update your personal details, avatar, and password separately';
$avatarInitial = student_profile_avatar_initial($student);
$avatarPath = trim((string) ($student['avatar_path'] ?? ''));
$avatarUrl = $avatarPath !== '' ? url($avatarPath) : '';
$avatarStyle = $avatarUrl !== '' ? ' style="background-image:url(' . h($avatarUrl) . ')"' : '';
$avatarClass = 'student-profile-avatar';
if ($avatarUrl !== '') { $avatarClass .= ' has-image'; }
require_once __DIR__ . '/../backend/partials/header.php';
?>
<section class="student-page-shell">
  <div class="student-page-card settings-shell" data-settings-tabs>
    <div class="student-page-toolbar student-simple-toolbar">
      <div>
        <div class="eyebrow">Account Settings</div>
        <h2>Profile</h2>
        <p>Normal profile updates are now separate from password changes, and your avatar is stored on the server instead of only in the browser.</p>
      </div>
    </div>
    <div class="settings-tabbar" role="tablist" aria-label="Student settings sections">
      <button type="button" class="settings-tab is-active" role="tab" aria-selected="true" data-settings-target="student-profile-tab">Profile</button>
      <button type="button" class="settings-tab" role="tab" aria-selected="false" data-settings-target="student-security-tab">Security</button>
      <button type="button" class="settings-tab" role="tab" aria-selected="false" data-settings-target="student-preferences-tab">Preferences</button>
    </div>

    <section id="student-profile-tab" class="settings-tab-panel is-active" role="tabpanel">
      <form method="post" enctype="multipart/form-data" class="stack">
        <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
        <input type="hidden" name="profile_action" value="profile_update">
        <div class="student-profile-layout">
          <article class="card student-profile-media-card">
            <h3>Profile picture</h3>
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
            <h3>Profile settings</h3>
            <div class="form-grid">
              <div><label>Full name</label><input name="full_name" value="<?= h($student['full_name']) ?>" required></div>
              <div><label>Email</label><input type="email" name="email" value="<?= h($student['email']) ?>" required></div>
              <div><label>Student ID</label><input value="<?= h($student['student_id'] ?? $student['username'] ?? '') ?>" readonly></div>
              <div><label>Login name</label><input value="<?= h($student['username'] ?? '') ?>" readonly></div>
              <div><label>Section</label><input value="<?= h($studentDetail['section_name'] ?? 'Not assigned') ?>" readonly></div>
              <div><label>School year</label><input value="<?= h($studentDetail['school_year'] ?? 'Not set') ?>" readonly></div>
              <div><label>Semester</label><input value="<?= h($studentDetail['semester'] ?? 'Not set') ?>" readonly></div>
              <div><label>Account status</label><input value="<?= h(ucwords(str_replace('_', ' ', (string) ($studentDetail['account_status'] ?? 'active')))) ?>" readonly></div>
            </div>
            <div class="form-actions"><button class="btn" type="submit">Update profile</button></div>
          </article>
        </div>
        <article class="card" style="margin-top:1rem;">
          <div class="split-header">
            <div>
              <h3 class="section-title">Student information</h3>
              <div class="muted small">Read-only academic details linked to your school-managed account.</div>
            </div>
            <?= status_badge((string) ($studentDetail['account_status'] ?? 'active')) ?>
          </div>
          <div class="info-list">
            <div class="row"><span>Section</span><strong><?= h($studentDetail['section_name'] ?? 'Not assigned') ?></strong></div>
            <div class="row"><span>School year</span><strong><?= h($studentDetail['school_year'] ?? 'Not set') ?></strong></div>
            <div class="row"><span>Semester</span><strong><?= h($studentDetail['semester'] ?? 'Not set') ?></strong></div>
            <div class="row"><span>Section status</span><strong><?= h(ucwords(str_replace('_', ' ', (string) ($studentDetail['section_status'] ?? 'active')))) ?></strong></div>
            <div class="row"><span>Submission access</span><strong><?= (int) ($studentDetail['can_submit'] ?? 1) === 1 ? 'Allowed' : 'Restricted' ?></strong></div>
            <div class="row"><span>Account created</span><strong><?= h(!empty($studentDetail['created_at']) ? date('M d, Y', strtotime((string) $studentDetail['created_at'])) : 'Unknown') ?></strong></div>
          </div>
        </article>
      </form>
    </section>

    <section id="student-security-tab" class="settings-tab-panel" role="tabpanel" hidden>
      <form method="post" class="card stack">
        <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
        <input type="hidden" name="profile_action" value="security_update">
        <h3 class="section-title">Security</h3>
        <div class="form-grid">
          <div><label>Current password</label><input type="password" name="current_password" required></div>
          <div><label>New password</label><input type="password" name="new_password" placeholder="Leave blank to keep current"></div>
        </div>
        <p class="muted small">Use a strong password with at least 8 characters.</p>
        <div class="form-actions"><button class="btn" type="submit">Save password</button></div>
      </form>
    </section>

    <section id="student-preferences-tab" class="settings-tab-panel" role="tabpanel" hidden>
      <div class="grid cols-2">
        <article class="card">
          <h3>School-managed account</h3>
          <div class="info-list">
            <div class="row"><span>Account source</span><strong>School ID roster</strong></div>
            <div class="row"><span>Identity edits</span><strong>Managed by staff</strong></div>
            <div class="row"><span>Portal access</span><strong>Student-only</strong></div>
          </div>
        </article>
        <article class="card">
          <h3>Preferences</h3>
          <div class="callout">This tab is reserved for future student preferences such as notification choices and appearance options.</div>
        </article>
      </div>
    </section>
  </div>
</section>
<?php require_once __DIR__ . '/../backend/partials/footer.php'; ?>
