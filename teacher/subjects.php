<?php
require_once __DIR__ . '/../backend/helpers/query.php';
require_role('teacher');
$teacher = current_user();
$pdo = pdo();
$stmt = $pdo->prepare('SELECT subj.*, GROUP_CONCAT(sec.section_name ORDER BY sec.section_name SEPARATOR ", ") AS sections, COUNT(sub.id) AS total_submissions FROM subjects subj LEFT JOIN section_subjects ss ON ss.subject_id = subj.id LEFT JOIN sections sec ON sec.id = ss.section_id LEFT JOIN submissions sub ON sub.subject_id = subj.id WHERE subj.teacher_id = ? GROUP BY subj.id ORDER BY subj.subject_name');
$stmt->execute([(int)$teacher['id']]);
$subjects = $stmt->fetchAll();
$title = 'My Subjects';
$subtitle = 'Teacher subject management is now table-first with compact action icons.';
require_once __DIR__ . '/../backend/partials/header.php';
?>
<div class="kpi-grid">
  <div class="kpi-card"><span class="label">Subjects</span><strong><?= count($subjects) ?></strong><span class="muted small">Assigned to your account</span></div>
  <div class="kpi-card"><span class="label">Open Windows</span><strong><?= count(array_filter($subjects, fn($row) => empty($row['submission_locked']))) ?></strong><span class="muted small">Still accepting submissions</span></div>
  <div class="kpi-card"><span class="label">Locked</span><strong><?= count(array_filter($subjects, fn($row) => !empty($row['submission_locked']))) ?></strong><span class="muted small">Deadline reached</span></div>
  <div class="kpi-card"><span class="label">Submissions</span><strong><?= array_sum(array_map(fn($row) => (int)$row['total_submissions'], $subjects)) ?></strong><span class="muted small">Across your subjects</span></div>
</div>
<section class="table-card table-bootstrap-shell">
  <div class="module-header"><div><div class="eyebrow">Teacher Subjects</div><h3 class="mb-1">Owned Subject Table</h3><p class="muted mb-0">Keep deadline and section awareness in one table, then open the full subject page only when needed.</p></div><div class="module-actions"><span class="badge-soft"><i class="bi bi-book"></i> <?= count($subjects) ?> rows</span></div></div>
  <div class="table-responsive"><table class="table table-hover align-middle"><thead><tr><th>Subject</th><th>Sections</th><th>Submission Window</th><th>Status</th><th class="text-end">Actions</th></tr></thead><tbody><?php foreach ($subjects as $row): ?><tr><td><strong><?= h($row['subject_name']) ?></strong><div class="muted small"><?= h($row['subject_code']) ?></div><div class="muted small"><?= h($row['description']) ?></div></td><td><?= h($row['sections'] ?: 'No sections') ?><div class="muted small"><?= (int) $row['total_submissions'] ?> submissions</div></td><td><?= deadline_badge_html($row) ?><div class="muted small mt-1"><?= h(($row['submission_deadline'] ?? '') ?: 'No deadline set') ?></div></td><td><?= status_badge($row['status']) ?></td><td class="text-end"><div class="icon-action-group justify-content-end"><a class="icon-action" href="<?= h(url('teacher/subject_view.php?id=' . (int) $row['id'])) ?>" title="Open subject"><i class="bi bi-eye"></i></a></div></td></tr><?php endforeach; ?><?php if (!$subjects): ?><tr><td colspan="5" class="table-empty">No subjects are currently assigned to your account.</td></tr><?php endif; ?></tbody></table></div>
</section>
<?php require_once __DIR__ . '/../backend/partials/footer.php'; ?>
