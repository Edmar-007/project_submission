<?php
if (defined('FILE_ADMIN_SUBJECT_PREVIEW_PHP_LOADED')) { return; }
define('FILE_ADMIN_SUBJECT_PREVIEW_PHP_LOADED', true);

require_once __DIR__ . '/../backend/config/app.php';
require_role('admin');
$subjectId = (int) ($_GET['id'] ?? 0);
$subject = fetch_subject_detail($subjectId);
if (!$subject) {
    http_response_code(404);
    echo '<div class="empty-state">Subject not found.</div>';
    exit;
}
$sectionsStmt = pdo()->prepare('SELECT sec.section_name FROM section_subjects ss JOIN sections sec ON sec.id = ss.section_id WHERE ss.subject_id = ? ORDER BY sec.section_name');
$sectionsStmt->execute([$subjectId]);
$sections = $sectionsStmt->fetchAll();
$submissionCount = count_for_query('SELECT COUNT(*) FROM submissions WHERE subject_id = ?', [$subjectId]);
?>
<div class="preview-shell detail-preview-shell">
  <div class="preview-hero">
    <div>
      <span class="pill soft"><?= h($subject['subject_code']) ?></span>
      <h3><?= h($subject['subject_name']) ?></h3>
      <p class="muted"><?= h($subject['description'] ?: 'No description added yet.') ?></p>
    </div>
    <div class="preview-hero-actions">
      <?= status_badge((string) $subject['status']) ?>
      <a class="btn" href="<?= h(url('admin/subject_view.php?id=' . $subjectId)) ?>">Open full page</a>
    </div>
  </div>
  <div class="modal-summary-grid modal-summary-grid-3">
    <div class="segment"><span class="muted small">Teacher</span><strong><?= h($subject['teacher_name']) ?></strong></div>
    <div class="segment"><span class="muted small">Term</span><strong><?= h($subject['school_year']) ?> · <?= h($subject['semester']) ?></strong></div>
    <div class="segment"><span class="muted small">Submissions</span><strong><?= $submissionCount ?></strong></div>
  </div>
  <div class="preview-grid two-col" style="margin-top:16px;">
    <section class="card preview-panel compact-panel">
      <h4 class="section-title">Submission window</h4>
      <div class="info-list">
        <div class="row"><span>Deadline</span><strong><?= h($subject['deadline_window']['label'] ?? 'No deadline set') ?></strong></div>
        <div class="row"><span>Warning hours</span><strong><?= h((string) ($subject['deadline_warning_hours'] ?? '72')) ?></strong></div>
        <div class="row"><span>Student access</span><strong><?= !empty($subject['submission_locked']) ? 'Locked' : 'Open' ?></strong></div>
      </div>
    </section>
    <section class="card preview-panel compact-panel">
      <div class="split-header"><h4 class="section-title">Assigned sections</h4><span class="pill"><?= count($sections) ?></span></div>
      <?php if ($sections): ?><div class="preview-resource-list"><?php foreach ($sections as $section): ?><div class="portal-link-box"><strong><?= h($section['section_name']) ?></strong></div><?php endforeach; ?></div><?php else: ?><div class="empty-state">No sections mapped yet.</div><?php endif; ?>
    </section>
  </div>
</div>
