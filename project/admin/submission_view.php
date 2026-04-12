<?php
require_once __DIR__ . '/../backend/helpers/query.php';
require_role('admin');
$pdo = pdo();
$admin = current_user();
$submissionId = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$submission = $submissionId ? fetch_submission_detail($submissionId) : null;
if (!$submission) {
    set_flash('error', 'Submission not found.');
    redirect_to('admin/submissions.php');
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $status = $_POST['status'] ?? 'pending';
    if (!in_array($status, ['pending', 'reviewed', 'graded'], true)) {
        set_flash('error', 'Invalid review status.');
        redirect_to('admin/submission_view.php?id=' . $submissionId);
    }
    $grade = trim($_POST['grade'] ?? '');
    $feedback = trim($_POST['teacher_feedback'] ?? '');
    $reviewNotes = trim($_POST['review_notes'] ?? '');
    $pdo->prepare('UPDATE submissions SET status = ?, grade = ?, teacher_feedback = ?, review_notes = ? WHERE id = ?')->execute([$status, $grade ?: null, $feedback ?: null, $reviewNotes ?: null, $submissionId]);
    snapshot_submission_history($submissionId, $status === 'graded' ? 'graded' : 'reviewed', 'admin', (int) $admin['id'], (string) $admin['full_name']);
    foreach (team_member_rows((int) $submission['team_id']) as $member) {
        create_notification('student', (int) $member['id'], 'Submission updated', 'Your submission for ' . $submission['subject_name'] . ' is now ' . $status . ($grade ? ' with grade ' . $grade : '') . '.', $status === 'graded' ? 'success' : 'info');
    }
    set_flash('success', 'Submission review saved.');
    redirect_to('admin/submission_view.php?id=' . $submissionId);
}
$submission = fetch_submission_detail($submissionId);
$members = fetch_submission_members($submissionId);
$title = 'Submission Detail';
$subtitle = 'Review submission content, system credentials, members, grading, and feedback in one place';
require_once __DIR__ . '/../backend/partials/header.php';
?>
<div class="detail-grid">
  <div class="detail-section">
    <div class="card">
      <div class="split-header"><div><h3 class="section-title"><?= h($submission['assigned_system']) ?></h3><div class="muted small"><?= h($submission['subject_name']) ?> · <?= h($submission['subject_code']) ?><a class="btn btn-outline" target="_blank" href="<?= h(url('admin/print_submission.php?id=' . (int) $submissionId)) ?>">Print copy</a><a class="btn btn-secondary" href="<?= h(url('admin/export_report.php?type=submissions&format=xlsx&submission_id=' . (int) $submissionId)) ?>">Export Excel</a></div></div><?= status_badge($submission['status']) ?></div>
      <div class="info-list">
        <div class="row"><span>Student</span><strong><?= h($submission['full_name']) ?> (<?= h($submission['student_code']) ?>)</strong></div>
        <div class="row"><span>Section</span><strong><?= h($submission['section_name']) ?></strong></div>
        <div class="row"><span>Teacher</span><strong><?= h($submission['teacher_name']) ?></strong></div>
        <div class="row"><span>Company</span><strong><?= h($submission['company_name']) ?></strong></div>
        <div class="row"><span>Submitted</span><strong><?= h($submission['submitted_at']) ?></strong></div>
      </div>
      <div class="form-actions" style="margin-top:16px;"><?php $projectHref = safe_public_url($submission['project_url'] ?? null); $videoHref = safe_public_url($submission['video_url'] ?? null); ?><?php if ($projectHref): ?><a class="btn btn-secondary" target="_blank" rel="noopener" href="<?= h($projectHref) ?>">Open project</a><?php endif; ?><?php if ($videoHref): ?><a class="btn btn-secondary" target="_blank" rel="noopener" href="<?= h($videoHref) ?>">Open video</a><?php endif; ?><a class="btn btn-outline" href="<?= h(url('admin/student_view.php?id=' . (int) $submission['student_id'])) ?>">Open student</a></div>
    </div>
    <div class="card">
      <h3 class="section-title">Submitted members</h3>
      <div class="timeline-list"><?php foreach ($members as $member): ?><div class="timeline-item"><strong><?= h($member['member_name']) ?></strong></div><?php endforeach; ?><?php if (!$members): ?><div class="empty-state">No member list was submitted.</div><?php endif; ?></div>
    </div>
    <div class="card">
      <h3 class="section-title">Submitted demo access</h3>
      <div class="grid cols-2">
        <div class="callout"><strong>Demo login</strong><div class="muted small">Username: <?= h(demo_decrypt($submission['admin_username'] ?? '') ?: '—') ?></div><div class="muted small">Password: <?= h(demo_decrypt($submission['admin_password'] ?? '') ?: '—') ?></div></div>
        <div class="callout"><strong>Legacy extra login</strong><div class="muted small">Username: <?= h(demo_decrypt($submission['user_username'] ?? '') ?: '—') ?></div><div class="muted small">Password: <?= h(demo_decrypt($submission['user_password'] ?? '') ?: '—') ?></div></div>
      </div>
      <div class="callout" style="margin-top:12px;"><strong>Contact email</strong><div class="muted small"><?= h($submission['contact_email']) ?></div></div>
      <?php if (!empty($submission['attachment_path'])): ?><div class="callout" style="margin-top:12px;"><strong>Attachment</strong><div class="muted small"><a target="_blank" href="<?= h(url($submission['attachment_path'])) ?>">Open uploaded file</a></div></div><?php endif; ?>
    </div>
  </div>
  <div class="detail-section">
    <div class="card">
      <h3 class="section-title">Review panel</h3>
      <form method="post" class="grid">
        <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
        <input type="hidden" name="id" value="<?= (int) $submissionId ?>">
        <select name="status"><?php foreach (['pending','reviewed','graded'] as $s): ?><option value="<?= h($s) ?>" <?= $submission['status']===$s?'selected':'' ?>><?= h(ucfirst($s)) ?></option><?php endforeach; ?></select>
        <input name="grade" value="<?= h($submission['grade']) ?>" placeholder="Grade">
        <textarea name="teacher_feedback" placeholder="Teacher feedback"><?= h($submission['teacher_feedback']) ?></textarea>
        <textarea name="review_notes" placeholder="Internal review notes"><?= h($submission['review_notes']) ?></textarea>
        <button class="btn" type="submit">Save review</button>
      </form>
    </div>
    <div class="card">
      <h3 class="section-title">Student contact</h3>
      <div class="info-list"><div class="row"><span>Email</span><strong><?= h($submission['student_email']) ?></strong></div><div class="row"><span>Latest grade</span><strong><?= h($submission['grade'] ?: 'Not graded') ?></strong></div></div>
    </div>
  </div>
</div>
<?php require_once __DIR__ . '/../backend/partials/footer.php'; ?>
