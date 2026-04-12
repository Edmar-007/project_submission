<?php
require_once __DIR__ . '/../backend/helpers/query.php';
require_role('student');
$student = current_user();
$subjectId = (int) ($_GET['subject_id'] ?? 0);
$subjects = student_subjects((int) $student['section_id']);
$subject = null;
foreach ($subjects as $row) {
    if ((int) $row['id'] === $subjectId) {
        $subject = $row;
        break;
    }
}
if (!$subject) {
    http_response_code(404);
    echo '<div class="empty-state">Subject not found or not assigned to your section.</div>';
    exit;
}
$team = student_team_for_subject((int) $student['id'], $subjectId);
$submission = null;
foreach (student_team_submissions((int) $student['id'], true) as $row) {
    if ((int) $row['subject_id'] === $subjectId) {
        $submission = $row;
        break;
    }
}
$resources = student_visible_subject_resources($subjectId);
$projectHref = safe_public_url($submission['project_url'] ?? null);
$videoHref = safe_public_url($submission['video_url'] ?? null);
?>
<div class="preview-shell detail-preview-shell">
  <div class="preview-hero">
    <div>
      <span class="pill soft"><?= h($subject['subject_code']) ?></span>
      <h3><?= h($subject['subject_name']) ?></h3>
      <p class="muted"><?= h($subject['description'] ?: 'No subject description provided yet.') ?></p>
    </div>
    <div class="preview-hero-actions">
      <?= status_badge(!empty($subject['submission_locked']) ? 'locked' : ($submission['status'] ?? 'ready')) ?>
      <a class="btn" href="<?= h(url('student/submit.php?subject_id=' . $subjectId)) ?>">Open submit page</a>
    </div>
  </div>

  <div class="modal-summary-grid modal-summary-grid-3">
    <div class="segment"><span class="muted small">Teacher</span><strong><?= h($subject['teacher_name']) ?></strong></div>
    <div class="segment"><span class="muted small">Deadline</span><strong><?= h($subject['deadline_window']['label'] ?? 'No deadline set') ?></strong></div>
    <div class="segment"><span class="muted small">Team status</span><strong><?= h($team ? ucfirst((string) $team['role']) : 'No team yet') ?></strong></div>
  </div>

  <?php if (!empty($subject['teacher_submission_lock_note'])): ?>
    <div class="callout" style="margin-top:16px;">
      <strong>Teacher lock note</strong>
      <div class="muted small"><?= h($subject['teacher_submission_lock_note']) ?></div>
    </div>
  <?php endif; ?>

  <div class="preview-grid two-col" style="margin-top:16px;">
    <section class="card preview-panel compact-panel">
      <div class="split-header">
        <div>
          <h4 class="section-title">Current submission snapshot</h4>
          <div class="muted small">See the latest status before opening the full workspace.</div>
        </div>
        <?php if ($submission): ?><span class="pill">Latest</span><?php endif; ?>
      </div>
      <?php if ($submission): ?>
        <div class="info-list">
          <div class="row"><span>Project</span><strong><?= h($submission['assigned_system'] ?: 'Untitled Project') ?></strong></div>
          <div class="row"><span>Status</span><span><?= status_badge((string) $submission['status']) ?></span></div>
          <div class="row"><span>Grade</span><strong><?= h($submission['grade'] ?: '—') ?></strong></div>
          <div class="row"><span>Updated</span><strong><?= h(date('M d, Y g:i A', strtotime((string) $submission['updated_at']))) ?></strong></div>
        </div>
        <div class="action-row" style="margin-top:14px;">
          <?php if ($projectHref): ?><a class="btn btn-secondary" href="<?= h($projectHref) ?>" target="_blank" rel="noopener">Open project</a><?php endif; ?>
          <?php if ($videoHref): ?><a class="btn btn-outline" href="<?= h($videoHref) ?>" target="_blank" rel="noopener">Open video</a><?php endif; ?>
          <a class="btn btn-ghost" href="<?= h(url('student/my_submissions.php')) ?>">Open history</a>
        </div>
      <?php else: ?>
        <div class="empty-state">No team submission yet for this subject. Open the submit page to start your first version.</div>
      <?php endif; ?>
    </section>

    <section class="card preview-panel compact-panel">
      <div class="split-header">
        <div>
          <h4 class="section-title">Subject resources</h4>
          <div class="muted small">Only files shared for students in this subject.</div>
        </div>
        <span class="pill"><?= count($resources) ?></span>
      </div>
      <?php if ($resources): ?>
        <div class="preview-resource-list">
          <?php foreach ($resources as $resource): ?>
            <div class="portal-link-box">
              <div>
                <strong><?= h($resource['title']) ?></strong>
                <div class="muted small"><?= h(basename((string) $resource['file_path'])) ?></div>
              </div>
              <a class="btn btn-outline" href="<?= h(url($resource['file_path'])) ?>" target="_blank" rel="noopener">Open</a>
            </div>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <div class="empty-state">No student-facing resources have been shared yet.</div>
      <?php endif; ?>
    </section>
  </div>
</div>
