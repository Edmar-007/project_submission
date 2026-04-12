<?php
if (defined('FILE_STUDENT_SUBJECT_PREVIEW_PHP_LOADED')) { return; }
define('FILE_STUDENT_SUBJECT_PREVIEW_PHP_LOADED', true);

require_once __DIR__ . '/../backend/config/app.php';
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
<div class="preview-shell detail-preview-shell ui-modal__body">
  <div class="preview-hero ui-modal__header" style="padding:0 0 16px;">
    <div>
      <span class="ui-section__eyebrow">Quick view</span>
      <h2 class="ui-modal__title"><?= h($subject['subject_name']) ?></h2>
      <p class="ui-modal__subtitle"><?= h($subject['description'] ?: 'No subject description provided yet.') ?></p>
    </div>
    <div class="preview-hero-actions">
      <?php $previewState = !empty($subject['submission_locked']) ? 'Locked' : (string) ($submission['status'] ?? 'Ready'); ?>
      <span class="ui-badge <?= !empty($subject['submission_locked']) ? 'ui-badge--danger' : 'ui-badge--open' ?>"><?= h($previewState) ?></span>
      <a class="btn ui-btn ui-btn--primary" href="<?= h(url('student/submit.php?subject_id=' . $subjectId)) ?>">Open submit page</a>
    </div>
  </div>

  <div class="ui-info-strip" style="margin-bottom:16px;">
    <div class="ui-info-item"><span class="ui-info-item__label">Teacher</span><strong class="ui-info-item__value"><?= h($subject['teacher_name']) ?></strong></div>
    <div class="ui-info-item"><span class="ui-info-item__label">Deadline</span><strong class="ui-info-item__value"><?= h($subject['deadline_window']['label'] ?? 'No active deadlines') ?></strong></div>
    <div class="ui-info-item"><span class="ui-info-item__label">Team status</span><strong class="ui-info-item__value"><?= h($team ? ucfirst((string) $team['role']) : 'No team yet') ?></strong></div>
  </div>

  <?php if (!empty($subject['teacher_submission_lock_note'])): ?>
    <div class="callout" style="margin-top:16px;">
      <strong>Teacher lock note</strong>
      <div class="muted small"><?= h($subject['teacher_submission_lock_note']) ?></div>
    </div>
  <?php endif; ?>

  <div class="ui-modal__grid" style="margin-top:16px;">
    <section class="card preview-panel compact-panel ui-panel-card">
      <div class="split-header">
        <div>
          <h4 class="section-title ui-form-section__title">Current submission snapshot</h4>
          <div class="muted small ui-form-section__hint">See the latest status before opening the full workspace.</div>
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
          <div class="action-row ui-action-row" style="margin-top:14px;">
            <?php if ($projectHref): ?><a class="btn btn-secondary ui-btn ui-btn--secondary" href="<?= h($projectHref) ?>" target="_blank" rel="noopener">Open project</a><?php endif; ?>
            <?php if ($videoHref): ?><a class="btn btn-outline ui-btn ui-btn--ghost" href="<?= h($videoHref) ?>" target="_blank" rel="noopener">Open video</a><?php endif; ?>
            <a class="btn btn-ghost ui-btn ui-btn--ghost" href="<?= h(url('student/my_submissions.php')) ?>">Open history</a>
          </div>
      <?php else: ?>
        <div class="ui-empty-state"><div class="ui-empty-state__icon">○</div><h4 class="ui-empty-state__title">No submission yet</h4><p class="ui-empty-state__text">Open the submit page to start your first version.</p></div>
      <?php endif; ?>
    </section>

    <section class="card preview-panel compact-panel ui-panel-card">
      <div class="split-header">
        <div>
          <h4 class="section-title ui-form-section__title">Subject resources</h4>
          <div class="muted small ui-form-section__hint">Only files shared for students in this subject.</div>
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
              <a class="btn btn-outline ui-btn ui-btn--ghost" href="<?= h(url($resource['file_path'])) ?>" target="_blank" rel="noopener">Open</a>
            </div>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <div class="ui-empty-state"><div class="ui-empty-state__icon">○</div><h4 class="ui-empty-state__title">No resources shared yet</h4><p class="ui-empty-state__text">Materials from your teacher will appear here.</p></div>
      <?php endif; ?>
    </section>
  </div>
</div>
