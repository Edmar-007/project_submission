<?php
require_once __DIR__ . '/../backend/helpers/query.php';
require_role('teacher');
$teacher = current_user();
$submissionId = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
if (!$submissionId || !teacher_can_access_submission((int) $teacher['id'], $submissionId)) {
    set_flash('Submission not found or not assigned to your account.', 'error');
    redirect_to('teacher/submissions.php');
}
$submission = fetch_submission_detail($submissionId);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $status = $_POST['status'] ?? 'pending';
    $grade = trim($_POST['grade'] ?? '');
    $feedback = trim($_POST['teacher_feedback'] ?? '');
    pdo()->prepare('UPDATE submissions SET status = ?, grade = ?, teacher_feedback = ? WHERE id = ?')->execute([$status, $grade ?: null, $feedback ?: null, $submissionId]);
    create_notification('student', (int) $submission['student_id'], 'Submission reviewed', 'Your submission for ' . $submission['subject_name'] . ' has been updated to ' . $status . ($grade ? ' with grade ' . $grade : '') . '.', $status === 'graded' ? 'success' : 'info');
    set_flash('Submission saved.', 'success');
    redirect_to('teacher/submission_view.php?id=' . $submissionId);
}
$submission = fetch_submission_detail($submissionId);
$members = fetch_submission_members($submissionId);
$title = 'Teacher Review';
$subtitle = 'Focused grading workspace for one submission';
require_once __DIR__ . '/../backend/partials/header.php';
?>
<div class="detail-grid">
  <div class="detail-section">
    <div class="card">
      <div class="split-header">
        <div>
          <h3 class="section-title"><?= h($submission['assigned_system']) ?></h3>
          <div class="muted small"><?= h($submission['subject_name']) ?> · <?= h($submission['section_name']) ?> <a class="btn btn-outline" target="_blank" href="<?= h(url('teacher/print_submission.php?id=' . (int) $submissionId)) ?>">Print copy</a></div>
        </div>
        <?= status_badge($submission['status']) ?>
      </div>
      <div class="info-list">
        <div class="row"><span>Student</span><strong><?= h($submission['full_name']) ?> (<?= h($submission['student_code']) ?>)</strong></div>
        <div class="row"><span>Company / Brand</span><strong><?= h($submission['company_name']) ?></strong></div>
        <div class="row"><span>Submitted</span><strong><?= h($submission['submitted_at']) ?></strong></div>
        <div class="row"><span>Contact email</span><strong><?= h($submission['contact_email']) ?></strong></div>
      </div>
      <div class="form-actions" style="margin-top:16px;">
        <a class="btn btn-secondary" target="_blank" href="<?= h($submission['project_url']) ?>">Open project</a>
        <?php if (!empty($submission['video_url'])): ?><a class="btn btn-outline" target="_blank" href="<?= h($submission['video_url']) ?>">Open video</a><?php endif; ?>
      </div>
    </div>
    <div class="card">
      <h3 class="section-title">Submitted members</h3>
      <div class="timeline-list"><?php foreach ($members as $member): ?><div class="timeline-item"><strong><?= h($member['member_name']) ?></strong></div><?php endforeach; ?><?php if (!$members): ?><div class="empty-state">No member list was submitted.</div><?php endif; ?></div>
    </div>
  </div>
  <div class="detail-section">
    <div class="card">
      <h3 class="section-title">Demo access</h3>
      <?php if (has_demo_access($submission)): ?>
        <div class="callout">
          <strong>Use these credentials only for project checking</strong>
          <div class="muted small" style="margin-top:8px;">Username: <?= h($submission['admin_username'] ?: '—') ?></div>
          <div class="muted small">Password: <?= h($submission['admin_password'] ?: '—') ?></div>
        </div>
      <?php else: ?>
        <div class="empty-state" style="padding:18px;">This team did not provide demo credentials. Review through the project link only.</div>
      <?php endif; ?>
      <?php if (!empty($submission['attachment_path'])): ?><div class="callout" style="margin-top:12px;"><strong>Attachment</strong><div class="muted small"><a target="_blank" href="<?= h(url($submission['attachment_path'])) ?>">Open uploaded file</a></div></div><?php endif; ?>
    </div>
    <div class="card">
      <h3 class="section-title">Grade and feedback</h3>
      <form method="post" class="grid">
        <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
        <input type="hidden" name="id" value="<?= (int) $submissionId ?>">
        <select name="status"><?php foreach (['pending','reviewed','graded'] as $s): ?><option value="<?= h($s) ?>" <?= $submission['status']===$s?'selected':'' ?>><?= h(ucfirst($s)) ?></option><?php endforeach; ?></select>
        <input name="grade" value="<?= h($submission['grade']) ?>" placeholder="Grade">
        <textarea name="teacher_feedback" placeholder="Teacher feedback"><?= h($submission['teacher_feedback']) ?></textarea>
        <button class="btn" type="submit">Save review</button>
      </form>
    </div>
    <div class="card highlight-card">
      <h3 class="section-title">Access rule</h3>
      <p class="muted" style="margin:0;">Only the teacher account assigned to this subject can open this workspace, print it, or save review updates. Other teachers are blocked even if they teach similar coursework.</p>
    </div>
  </div>
</div>
<?php require_once __DIR__ . '/../backend/partials/footer.php'; ?>
