<?php
require_once __DIR__ . '/../backend/helpers/query.php';
require_role('admin');
$submissionId = (int) ($_GET['id'] ?? 0);
$submission = fetch_submission_detail($submissionId);
if (!$submission) {
    http_response_code(404);
    echo '<div class="empty-state">Submission not found.</div>';
    exit;
}
$members = fetch_submission_members($submissionId);
$history = fetch_submission_history($submissionId);
$projectHref = safe_public_url($submission['project_url'] ?? null);
$videoHref = safe_public_url($submission['video_url'] ?? null);
?>
<div class="preview-shell detail-preview-shell">
  <div class="preview-hero">
    <div>
      <span class="pill soft"><?= h($submission['subject_code']) ?></span>
      <h3><?= h($submission['assigned_system'] ?: 'Untitled Project') ?></h3>
      <p class="muted"><?= h($submission['full_name']) ?> · <?= h($submission['student_code']) ?> · <?= h($submission['section_name']) ?></p>
    </div>
    <div class="preview-hero-actions">
      <?= status_badge((string) $submission['status']) ?>
      <a class="btn" href="<?= h(url('admin/submission_view.php?id=' . $submissionId)) ?>">Open full page</a>
    </div>
  </div>
  <div class="modal-summary-grid modal-summary-grid-3">
    <div class="segment"><span class="muted small">Subject</span><strong><?= h($submission['subject_name']) ?></strong></div>
    <div class="segment"><span class="muted small">Grade</span><strong><?= h($submission['grade'] ?: '—') ?></strong></div>
    <div class="segment"><span class="muted small">Submitted</span><strong><?= h(date('M d, Y g:i A', strtotime((string) $submission['submitted_at']))) ?></strong></div>
  </div>
  <div class="preview-grid two-col" style="margin-top:16px;">
    <section class="card preview-panel compact-panel">
      <h4 class="section-title">Links and feedback</h4>
      <div class="info-list">
        <div class="row"><span>Company</span><strong><?= h($submission['company_name'] ?: '—') ?></strong></div>
        <div class="row"><span>Teacher</span><strong><?= h($submission['teacher_name']) ?></strong></div>
        <div class="row"><span>Feedback</span><strong><?= h($submission['teacher_feedback'] ?: 'No feedback yet') ?></strong></div>
      </div>
      <div class="action-row" style="margin-top:14px;">
        <?php if ($projectHref): ?><a class="btn btn-secondary" href="<?= h($projectHref) ?>" target="_blank" rel="noopener">Project</a><?php endif; ?>
        <?php if ($videoHref): ?><a class="btn btn-outline" href="<?= h($videoHref) ?>" target="_blank" rel="noopener">Video</a><?php endif; ?>
        <?php if (!empty($submission['attachment_path'])): ?><a class="btn btn-ghost" href="<?= h(url($submission['attachment_path'])) ?>" target="_blank">Attachment</a><?php endif; ?>
      </div>
    </section>
    <section class="card preview-panel compact-panel">
      <div class="split-header"><h4 class="section-title">Team members</h4><span class="pill"><?= count($members) ?></span></div>
      <?php if ($members): ?>
        <div class="preview-resource-list">
          <?php foreach ($members as $member): ?>
            <div class="portal-link-box"><div><strong><?= h($member['member_name']) ?></strong><div class="muted small">Team member</div></div><span class="pill soft">Member</span></div>
          <?php endforeach; ?>
        </div>
      <?php else: ?><div class="empty-state">No member records stored.</div><?php endif; ?>
    </section>
  </div>
  <section class="card preview-panel compact-panel" style="margin-top:16px;">
    <div class="split-header"><h4 class="section-title">History snapshot</h4><span class="pill"><?= count($history) ?></span></div>
    <div class="table-wrap"><table class="table-redesign compact-table"><thead><tr><th>Version</th><th>Action</th><th>Status</th><th>Grade</th><th>Captured</th></tr></thead><tbody><?php foreach (array_slice($history, 0, 5) as $item): ?><tr><td>v<?= (int) $item['version_no'] ?></td><td><?= action_badge((string) $item['action_type']) ?></td><td><?= status_badge((string) $item['status']) ?></td><td><?= h($item['grade'] ?: '—') ?></td><td><?= h(date('M d, Y g:i A', strtotime((string) $item['created_at']))) ?></td></tr><?php endforeach; ?><?php if (!$history): ?><tr><td colspan="5" class="empty-state">No history entries yet.</td></tr><?php endif; ?></tbody></table></div>
  </section>
</div>
