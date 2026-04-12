<?php
require_once __DIR__ . '/../backend/helpers/query.php';
require_role('teacher');
$teacher = current_user();
$submissionId = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
if (!$submissionId || !teacher_can_access_submission((int) $teacher['id'], $submissionId)) {
    set_flash('error', 'Submission not found or not assigned to your account.');
    redirect_to('teacher/submissions.php');
}
$submission = fetch_submission_detail($submissionId);
$historyRows = fetch_submission_history($submissionId);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $status = $_POST['status'] ?? 'pending';
    if (!in_array($status, ['pending', 'reviewed', 'graded'], true)) {
        set_flash('error', 'Invalid review status.');
        redirect_to('teacher/submission_view.php?id=' . $submissionId);
    }
    $grade = trim($_POST['grade'] ?? '');
    $feedback = trim($_POST['teacher_feedback'] ?? '');
    $reviewNotes = trim($_POST['review_notes'] ?? '');
    pdo()->prepare('UPDATE submissions SET status = ?, grade = ?, teacher_feedback = ?, review_notes = ? WHERE id = ?')->execute([$status, $grade ?: null, $feedback ?: null, $reviewNotes ?: null, $submissionId]);
    snapshot_submission_history($submissionId, $status === 'graded' ? 'graded' : 'reviewed', 'teacher', (int) $teacher['id'], (string) $teacher['full_name']);
    foreach (team_member_rows((int) $submission['team_id']) as $member) {
        create_notification('student', (int) $member['id'], 'Submission reviewed', 'Your submission for ' . $submission['subject_name'] . ' has been updated to ' . $status . ($grade ? ' with grade ' . $grade : '') . '.', $status === 'graded' ? 'success' : 'info');
    }
    set_flash('success', 'Submission review saved and added to the history trail.');
    redirect_to('teacher/submission_view.php?id=' . $submissionId);
}
$submission = fetch_submission_detail($submissionId);
$members = fetch_submission_members($submissionId);
$historyRows = fetch_submission_history($submissionId);
$title = 'Teacher Review Workspace';
$subtitle = 'Submission context, links, demo access, and a sticky grading panel';
require_once __DIR__ . '/../backend/partials/header.php';
?>
<div class="detail-grid review-workspace-grid">
  <div class="detail-section">
    <div class="card">
      <div class="split-header">
        <div>
          <h3 class="section-title"><?= h($submission['assigned_system']) ?></h3>
          <div class="muted small"><?= h($submission['subject_name']) ?> · <?= h($submission['section_name']) ?></div>
        </div>
        <?= status_badge($submission['status']) ?>
      </div>
      <div class="info-list">
        <div class="row"><span>Student</span><strong><?= h($submission['full_name']) ?> (<?= h($submission['student_code']) ?>)</strong></div>
        <div class="row"><span>Company / Brand</span><strong><?= h($submission['company_name']) ?></strong></div>
        <div class="row"><span>Contact email</span><strong><?= h($submission['contact_email']) ?></strong></div>
        <div class="row"><span>Submitted</span><strong><?= h($submission['submitted_at']) ?></strong></div>
      </div>
      <div class="form-actions" style="margin-top:16px;">
        <?php $projectHref = safe_public_url($submission['project_url'] ?? null); $videoHref = safe_public_url($submission['video_url'] ?? null); ?>
        <?php if ($projectHref): ?><a class="btn btn-secondary" target="_blank" rel="noopener" href="<?= h($projectHref) ?>">Open project</a><?php endif; ?>
        <?php if ($videoHref): ?><a class="btn btn-outline" target="_blank" rel="noopener" href="<?= h($videoHref) ?>">Open video</a><?php endif; ?>
        <a class="btn btn-ghost" target="_blank" href="<?= h(url('teacher/print_submission.php?id=' . (int) $submissionId)) ?>">Print copy</a>
        <a class="btn btn-secondary" href="<?= h(url('teacher/export_submission.php?format=xlsx&submission_id=' . (int) $submissionId)) ?>">Export Excel</a>
      </div>
    </div>

    <div class="card">
      <h3 class="section-title">Members</h3>
      <div class="timeline-list"><?php foreach ($members as $member): ?><div class="timeline-item"><strong><?= h($member['member_name']) ?></strong></div><?php endforeach; ?><?php if (!$members): ?><div class="empty-state">No member list was submitted.</div><?php endif; ?></div>
    </div>

    <div class="card">
      <h3 class="section-title">Demo access and files</h3>
      <?php if (has_demo_access($submission)): ?>
        <div class="callout">
          <strong>Demo credentials</strong>
          <div class="muted small" style="margin-top:8px;">Username: <?= h(demo_decrypt($submission['admin_username'] ?? '') ?: '—') ?></div>
          <div class="muted small">Password: <?= h(demo_decrypt($submission['admin_password'] ?? '') ?: '—') ?></div>
        </div>
      <?php else: ?><div class="empty-state" style="padding:18px;">No demo credentials were provided.</div><?php endif; ?>
      <?php if (!empty($submission['attachment_path'])): ?><div class="callout" style="margin-top:12px;"><strong>Attachment</strong><div class="muted small"><a target="_blank" href="<?= h(url($submission['attachment_path'])) ?>">Open uploaded file</a></div></div><?php endif; ?>
    </div>


    <div class="card">
      <div class="split-header">
        <div>
          <h3 class="section-title">Version history</h3>
          <div class="muted small">Every student and teacher update is captured as an audit-friendly snapshot.</div>
        </div>
        <span class="pill"><?= count($historyRows) ?> versions</span>
      </div>
      <div class="table-wrap" style="margin-top:16px;">
        <table class="table-redesign compact-table">
          <thead>
            <tr><th>Version</th><th>Action</th><th>Actor</th><th>Status</th><th>Grade</th><th>Captured</th></tr>
          </thead>
          <tbody>
            <?php foreach ($historyRows as $history): ?>
              <tr>
                <td><strong>v<?= (int) $history['version_no'] ?></strong></td>
                <td><?= action_badge((string) $history['action_type']) ?></td>
                <td><?= h($history['actor_name'] ?: ucfirst((string) $history['actor_role'])) ?></td>
                <td><?= status_badge((string) $history['status']) ?></td>
                <td><?= h($history['grade'] ?: '—') ?></td>
                <td><?= h(date('M d, Y g:i A', strtotime((string) $history['created_at']))) ?></td>
              </tr>
            <?php endforeach; ?>
            <?php if (!$historyRows): ?><tr><td colspan="6" class="empty-state">No history captured yet.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
  <div class="detail-section sticky-actions">
    <div class="card sticky-review-panel">
      <h3 class="section-title">Grade and feedback</h3>
      <form method="post" class="stack">
        <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
        <input type="hidden" name="id" value="<?= (int) $submissionId ?>">
        <div><label>Status</label><select name="status"><?php foreach (['pending','reviewed','graded'] as $s): ?><option value="<?= h($s) ?>" <?= $submission['status'] === $s ? 'selected' : '' ?>><?= h(ucfirst($s)) ?></option><?php endforeach; ?></select></div>
        <div><label>Grade</label><input name="grade" value="<?= h((string) $submission['grade']) ?>" placeholder="Grade"></div>
        <div><label>Student-facing feedback</label><textarea name="teacher_feedback" placeholder="Visible to the student/team"><?= h((string) $submission['teacher_feedback']) ?></textarea></div>
        <div><label>Private review notes</label><textarea name="review_notes" placeholder="Internal notes for your review workspace"><?= h((string) ($submission['review_notes'] ?? '')) ?></textarea></div>
        <button class="btn" type="submit">Save review</button>
      </form>
    </div>
  </div>
</div>
<?php require_once __DIR__ . '/../backend/partials/footer.php'; ?>
