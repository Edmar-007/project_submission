<?php
require_once __DIR__ . '/../backend/helpers/query.php';
require_role('teacher');
$teacher = current_user();
$submissionId = (int)($_GET['id'] ?? 0);
if (!$submissionId || !teacher_can_access_submission((int) $teacher['id'], $submissionId)) { die('Submission not found.'); }
$submission = fetch_submission_detail($submissionId);
$members = fetch_submission_members($submissionId);
$title = 'Teacher Printable Submission';
$subtitle = 'Print-friendly review copy';
require_once __DIR__ . '/../backend/partials/header.php';
?>
<div class="card">
  <div class="split-header"><div><h3 class="section-title"><?= h($submission['assigned_system']) ?></h3><div class="muted small"><?= h($submission['subject_name']) ?> · <?= h($submission['section_name']) ?></div></div><?= status_badge($submission['status']) ?></div>
  <div class="info-list"><div class="row"><span>Student</span><strong><?= h($submission['full_name']) ?></strong></div><div class="row"><span>Submitted</span><strong><?= h($submission['submitted_at']) ?></strong></div><div class="row"><span>Grade</span><strong><?= h($submission['grade'] ?: 'Not graded') ?></strong></div></div>
</div>
<div class="grid cols-2" style="margin-top:18px;">
  <div class="card"><h3 class="section-title">Members</h3><?php foreach ($members as $m): ?><div class="timeline-item"><strong><?= h($m['member_name']) ?></strong></div><?php endforeach; ?></div>
  <div class="card"><h3 class="section-title">Feedback</h3><p><?= nl2br(h($submission['teacher_feedback'] ?: 'No feedback yet.')) ?></p><?php if (has_demo_access($submission)): ?><div class="callout" style="margin-top:16px;"><strong>Demo access</strong><div class="muted small">Username: <?= h(demo_decrypt($submission['admin_username'] ?? '') ?: '—') ?></div><div class="muted small">Password: <?= h(demo_decrypt($submission['admin_password'] ?? '') ?: '—') ?></div></div><?php endif; ?></div>
</div>
<script>window.print();</script>
<?php require_once __DIR__ . '/../backend/partials/footer.php'; ?>
