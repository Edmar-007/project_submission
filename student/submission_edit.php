<?php
if (defined('FILE_STUDENT_SUBMISSION_EDIT_PHP_LOADED')) { return; }
define('FILE_STUDENT_SUBMISSION_EDIT_PHP_LOADED', true);

require_once __DIR__ . '/../backend/config/app.php';
require_once __DIR__ . '/../backend/helpers/uploads.php';
require_role('student');

$student = current_user();
$submissionId = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$submission = $submissionId ? student_submission_row_for_manage((int) $student['id'], $submissionId) : null;
if (!$submission) {
    set_flash('error', 'Submission not found.');
    redirect_to('student/my_submissions.php');
}
if (($submission['member_role'] ?? '') !== 'leader') {
    set_flash('error', 'Only the team leader can edit this submission.');
    redirect_to('student/my_submissions.php');
}
if (in_array($submission['status'], ['graded', 'archived'], true)) {
    set_flash('error', 'This submission is already locked from student editing.');
    redirect_to('student/my_submissions.php');
}

$members = team_member_rows((int) $submission['team_id']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $assignedSystem = trim($_POST['assigned_system'] ?? '');
    $companyName = trim($_POST['company_name'] ?? '');
    $projectUrl = normalize_public_url($_POST['project_url'] ?? '');
    $videoUrl = normalize_public_url($_POST['video_url'] ?? '');
    $contactEmail = trim($_POST['contact_email'] ?? '');
    $demoUsername = trim($_POST['demo_username'] ?? '');
    $demoPassword = trim($_POST['demo_password'] ?? '');
    $removeAttachment = !empty($_POST['remove_attachment']);

    if ($assignedSystem === '' || $companyName === '' || !$projectUrl || $contactEmail === '') {
        set_flash('error', 'Please complete the required fields.');
        redirect_to('student/submission_edit.php?id=' . $submissionId);
    }
    if (!filter_var($contactEmail, FILTER_VALIDATE_EMAIL)) {
        set_flash('error', 'Enter a valid contact email.');
        redirect_to('student/submission_edit.php?id=' . $submissionId);
    }
    if (!$projectUrl) {
        set_flash('error', 'Enter a valid project URL that starts with http:// or https://.');
        redirect_to('student/submission_edit.php?id=' . $submissionId);
    }
    if (trim((string) ($_POST['video_url'] ?? '')) !== '' && !$videoUrl) {
        set_flash('error', 'Enter a valid video URL that starts with http:// or https://, or leave it blank.');
        redirect_to('student/submission_edit.php?id=' . $submissionId);
    }

    $oldAttachment = (string) ($submission['attachment_path'] ?? '');
    $newAttachment = $oldAttachment;
    if ($removeAttachment) {
        $newAttachment = null;
    }

    if (!empty($_FILES['attachment']) && ($_FILES['attachment']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        $upload = store_uploaded_file(
            $_FILES['attachment'],
            'uploads/submissions',
            'submission-edit-' . $submissionId,
            ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'],
            5 * 1024 * 1024,
            ['pdf', 'jpg', 'jpeg', 'png', 'webp']
        );
        if (!$upload['ok']) {
            set_flash('error', $upload['message']);
            redirect_to('student/submission_edit.php?id=' . $submissionId);
        }
        $newAttachment = $upload['path'];
    }

    pdo()->prepare('UPDATE submissions SET assigned_system = ?, company_name = ?, project_url = ?, video_url = ?, contact_email = ?, admin_username = ?, admin_password = ?, updated_at = CURRENT_TIMESTAMP, attachment_path = ? WHERE id = ?')
        ->execute([
            $assignedSystem,
            $companyName,
            $projectUrl,
            $videoUrl ?: null,
            $contactEmail,
            $demoUsername !== '' ? demo_encrypt($demoUsername) : null,
            $demoPassword !== '' ? demo_encrypt($demoPassword) : null,
            $newAttachment,
            $submissionId,
        ]);

    if ($oldAttachment && $newAttachment !== $oldAttachment) {
        $oldAbsolute = APP_ROOT . '/' . ltrim($oldAttachment, '/');
        if (is_file($oldAbsolute)) {
            @unlink($oldAbsolute);
        }
    }

    snapshot_submission_history($submissionId, 'edited', 'student', (int) $student['id'], (string) $student['full_name']);

    foreach ($members as $member) {
        create_notification('student', (int) $member['id'], 'Submission updated', 'The team submission for ' . $submission['subject_name'] . ' was updated by the team leader.', 'info');
    }
    create_notification('teacher', (int) $submission['teacher_id'], 'Submission updated', 'A student team updated their submission for ' . $submission['subject_name'] . '.', 'info');

    set_flash('success', 'Submission updated successfully. A new history version has been recorded.');
    redirect_to('student/my_submissions.php');
}

$title = 'Edit Submission';
$subtitle = 'Update a team submission before final grading';
require_once __DIR__ . '/../backend/partials/header.php';
?>
<section class="student-page-shell">
  <div class="student-page-card">
    <div class="split-header">
      <div>
        <h3 class="section-title">Edit submission</h3>
        <div class="muted small"><?= h($submission['subject_name']) ?> · <?= h($submission['subject_code']) ?> · <?= h($submission['team_name']) ?></div>
      </div>
      <?= status_badge($submission['status']) ?>
    </div>

    <div class="callout" style="margin-top:16px; margin-bottom:16px;">
      <strong>Leader controls</strong>
      <div class="muted small">You can update the project details until the teacher finalizes grading. Once graded, the record becomes read only for students.</div>
    </div>

    <form method="post" enctype="multipart/form-data" class="stack">
      <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
      <input type="hidden" name="id" value="<?= (int) $submissionId ?>">

      <div class="form-grid">
        <div>
          <label>Assigned system</label>
          <input name="assigned_system" required value="<?= h((string) $submission['assigned_system']) ?>">
        </div>
        <div>
          <label>Company / Brand</label>
          <input name="company_name" required value="<?= h((string) $submission['company_name']) ?>">
        </div>
        <div>
          <label>Project URL</label>
          <input type="url" name="project_url" required value="<?= h((string) $submission['project_url']) ?>">
        </div>
        <div>
          <label>Video URL</label>
          <input type="url" name="video_url" value="<?= h((string) $submission['video_url']) ?>">
        </div>
        <div>
          <label>Contact email</label>
          <input type="email" name="contact_email" required value="<?= h((string) $submission['contact_email']) ?>">
        </div>
        <div>
          <label>Demo username</label>
          <input name="demo_username" value="<?= h((string) (demo_decrypt($submission['admin_username'] ?? '') ?: '')) ?>">
        </div>
        <div>
          <label>Demo password</label>
          <input name="demo_password" value="<?= h((string) (demo_decrypt($submission['admin_password'] ?? '') ?: '')) ?>">
        </div>
        <div>
          <label>Replace attachment</label>
          <input type="file" name="attachment" accept=".pdf,image/*">
        </div>
        <div class="full">
          <label>Team members</label>
          <div class="callout">
            <?php foreach ($members as $member): ?>
              <div class="muted small"><?= h($member['full_name']) ?> (<?= h($member['student_id']) ?>) — <?= h(ucfirst($member['role'])) ?></div>
            <?php endforeach; ?>
          </div>
        </div>
        <?php if (!empty($submission['attachment_path'])): ?>
          <div class="full">
            <label class="checkbox-inline"><input type="checkbox" name="remove_attachment" value="1"> Remove current attachment</label>
            <div class="muted small">Current file: <a target="_blank" href="<?= h(url($submission['attachment_path'])) ?>">Open attachment</a></div>
          </div>
        <?php endif; ?>
      </div>

      <div class="form-actions">
        <a class="btn btn-secondary" href="<?= h(url('student/my_submissions.php')) ?>">Back</a>
        <button class="btn" type="submit">Save changes</button>
      </div>
    </form>
  </div>
</section>
<?php require_once __DIR__ . '/../backend/partials/footer.php'; ?>
