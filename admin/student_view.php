<?php
require_once __DIR__ . '/../backend/helpers/query.php';
require_role('admin');
$pdo = pdo();
$admin = current_user();
$studentId = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$student = $studentId ? fetch_student_detail($studentId) : null;
if (!$student) {
    set_flash('error', 'Student record not found.');
    redirect_to('admin/students.php');
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'save_profile') {
        $status = $_POST['account_status'] ?? 'active';
        $canSubmit = isset($_POST['can_submit']) ? 1 : 0;
        $sectionId = (int) ($_POST['section_id'] ?? $student['section_id']);
        $pdo->prepare('UPDATE students SET full_name = ?, email = ?, username = ?, section_id = ?, account_status = ?, can_submit = ? WHERE id = ?')
            ->execute([trim($_POST['full_name'] ?? ''), trim($_POST['email'] ?? ''), trim($_POST['username'] ?? ''), $sectionId, $status, $canSubmit, $studentId]);
        create_notification('student', $studentId, 'Account updated', 'Your account settings were updated by the administrator.', 'info');
        set_flash('success', 'Student profile updated.');
    }
    if ($action === 'reset_password') {
        $newPassword = trim($_POST['new_password'] ?? '');
        if (strlen($newPassword) >= 6) {
            $pdo->prepare('UPDATE students SET password_hash = ? WHERE id = ?')->execute([password_hash($newPassword, PASSWORD_DEFAULT), $studentId]);
            create_notification('student', $studentId, 'Password reset', 'Your account password was reset by the administrator. Please update it after your next login.', 'warning');
            set_flash('success', 'Student password reset successfully.');
        } else {
            set_flash('error', 'Password must be at least 6 characters.');
        }
    }
    redirect_to('admin/student_view.php?id=' . $studentId);
}
$student = fetch_student_detail($studentId);
$sections = all_sections();
$subjects = student_subjects((int) $student['section_id']);
$submissions = pdo()->prepare('SELECT id, assigned_system, status, grade, submitted_at FROM submissions WHERE student_id = ? ORDER BY submitted_at DESC');
$submissions->execute([$studentId]);
$submissions = $submissions->fetchAll();
$notes = pdo()->prepare('SELECT * FROM notifications WHERE user_type = "student" AND user_id = ? ORDER BY created_at DESC LIMIT 6');
$notes->execute([$studentId]);
$notes = $notes->fetchAll();
$title = 'Student Detail';
$subtitle = 'Deep student profile, subject inheritance, access controls, and history';
require_once __DIR__ . '/../backend/partials/header.php';
?>
<div class="detail-grid">
  <div class="detail-section">
    <div class="card">
      <div class="split-header"><div><h3 class="section-title">Student profile</h3><div class="muted small">Search target for reactivation and section reassignment</div></div><?= status_badge($student['account_status']) ?></div>
      <form method="post" class="form-grid">
        <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
        <input type="hidden" name="action" value="save_profile">
        <input type="hidden" name="id" value="<?= (int) $studentId ?>">
        <div><label>Student ID</label><input value="<?= h($student['student_id']) ?>" disabled></div>
        <div><label>Username</label><input name="username" value="<?= h($student['username']) ?>"></div>
        <div><label>Full name</label><input name="full_name" value="<?= h($student['full_name']) ?>"></div>
        <div><label>Email</label><input type="email" name="email" value="<?= h($student['email']) ?>"></div>
        <div><label>Section</label><select name="section_id"><?php foreach ($sections as $section): ?><option value="<?= (int) $section['id'] ?>" <?= (int)$student['section_id']===(int)$section['id']?'selected':'' ?>><?= h($section['section_name']) ?> · <?= h($section['school_year']) ?></option><?php endforeach; ?></select></div>
        <div><label>Account status</label><select name="account_status"><?php foreach (['active','view_only','inactive'] as $s): ?><option value="<?= h($s) ?>" <?= $student['account_status']===$s?'selected':'' ?>><?= h(ucwords(str_replace('_', ' ', $s))) ?></option><?php endforeach; ?></select></div>
        <div class="full"><label><input type="checkbox" name="can_submit" value="1" <?= $student['can_submit'] ? 'checked' : '' ?>> Allow student to submit to active subjects</label></div>
        <div class="full form-actions"><button class="btn" type="submit">Save profile</button><a class="btn btn-secondary" href="<?= h(url('admin/students.php')) ?>">Back to students</a></div>
      </form>
    </div>
    <div class="card">
      <div class="split-header"><div><h3 class="section-title">Submission history</h3><div class="muted small">Projects, grades, and review state</div></div></div>
      <div class="table-wrap"><table><thead><tr><th>System</th><th>Status</th><th>Grade</th><th>Date</th><th></th></tr></thead><tbody><?php foreach ($submissions as $submission): ?><tr><td><?= h($submission['assigned_system']) ?></td><td><?= status_badge($submission['status']) ?></td><td><?= h($submission['grade'] ?: '—') ?></td><td><?= h($submission['submitted_at']) ?></td><td><a class="muted-link" href="<?= h(url('admin/submission_view.php?id=' . (int) $submission['id'])) ?>">Open</a></td></tr><?php endforeach; ?><?php if (!$submissions): ?><tr><td colspan="5" class="empty-state">No submissions for this student yet.</td></tr><?php endif; ?></tbody></table></div>
    </div>
  </div>
  <div class="detail-section">
    <div class="card">
      <h3 class="section-title">Academic context</h3>
      <div class="info-list">
        <div class="row"><span>Current section</span><strong><?= h($student['section_name']) ?></strong></div>
        <div class="row"><span>Section status</span><?= status_badge($student['section_status']) ?></div>
        <div class="row"><span>School year</span><strong><?= h($student['school_year']) ?></strong></div>
        <div class="row"><span>Semester</span><strong><?= h($student['semester']) ?></strong></div>
      </div>
      <div class="callout" style="margin-top:16px;">This student automatically inherits subject access from the current section. Reassign the section to change the visible subjects immediately.</div>
    </div>
    <div class="card">
      <h3 class="section-title">Visible subjects</h3>
      <div class="timeline-list"><?php foreach ($subjects as $subject): ?><div class="timeline-item"><strong><?= h($subject['subject_name']) ?></strong><div class="muted small"><?= h($subject['subject_code']) ?> · <?= h($subject['teacher_name']) ?></div></div><?php endforeach; ?><?php if (!$subjects): ?><div class="empty-state">No subjects linked to this student’s section.</div><?php endif; ?></div>
    </div>
    <div class="card">
      <h3 class="section-title">Reset password</h3>
      <form method="post" class="grid">
        <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
        <input type="hidden" name="action" value="reset_password">
        <input type="hidden" name="id" value="<?= (int) $studentId ?>">
        <input type="password" name="new_password" placeholder="New password (min 6 chars)">
        <button class="btn btn-secondary" type="submit">Reset password</button>
      </form>
    </div>
    <div class="card">
      <h3 class="section-title">Recent notifications</h3>
      <div class="timeline-list"><?php foreach ($notes as $note): ?><div class="timeline-item"><div class="notification-title-row"><strong><?= h($note['title']) ?></strong><?= status_badge($note['type']) ?></div><p><?= h($note['message']) ?></p><div class="muted small"><?= h($note['created_at']) ?></div></div><?php endforeach; ?><?php if (!$notes): ?><div class="empty-state">No notifications sent yet.</div><?php endif; ?></div>
    </div>
  </div>
</div>
<?php require_once __DIR__ . '/../backend/partials/footer.php'; ?>
