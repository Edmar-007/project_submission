<?php
require_once __DIR__ . '/../backend/helpers/query.php';
require_role('admin');
$submissionId = (int)($_GET['id'] ?? 0);
$submission = $submissionId ? fetch_submission_detail($submissionId) : null;
if (!$submission) { die('Submission not found.'); }
$members = fetch_submission_members($submissionId);
$title = 'Printable Submission';
$subtitle = 'Print-friendly review sheet';
require_once __DIR__ . '/../backend/partials/header.php';
?>
<div class="print-only"><h2>Project Submission Review Sheet</h2></div>
<div class="card">
  <div class="split-header"><div><h3 class="section-title"><?= h($submission['assigned_system']) ?></h3><div class="muted small"><?= h($submission['subject_name']) ?> · <?= h($submission['section_name']) ?></div></div><?= status_badge($submission['status']) ?></div>
  <div class="info-list">
    <div class="row"><span>Student</span><strong><?= h($submission['full_name']) ?> (<?= h($submission['student_code']) ?>)</strong></div>
    <div class="row"><span>Company</span><strong><?= h($submission['company_name']) ?></strong></div>
    <div class="row"><span>Project URL</span><strong><?= h($submission['project_url']) ?></strong></div>
    <div class="row"><span>Video URL</span><strong><?= h($submission['video_url']) ?></strong></div>
    <div class="row"><span>Grade</span><strong><?= h($submission['grade'] ?: 'Not graded') ?></strong></div>
  </div>
</div>
<div class="grid cols-2" style="margin-top:18px;">
  <div class="card"><h3 class="section-title">Members</h3><?php foreach ($members as $m): ?><div class="timeline-item"><strong><?= h($m['member_name']) ?></strong></div><?php endforeach; ?></div>
  <div class="card"><h3 class="section-title">Feedback</h3><p><?= nl2br(h($submission['teacher_feedback'] ?: 'No feedback yet.')) ?></p><h3 class="section-title">Review notes</h3><p><?= nl2br(h($submission['review_notes'] ?: 'No internal notes.')) ?></p></div>
</div>
<script>window.print();</script>
<?php require_once __DIR__ . '/../backend/partials/footer.php'; ?>
