<?php
require_once __DIR__ . '/../backend/helpers/query.php';
require_role('admin');
$pdo = pdo();
$rows = $pdo->query('SELECT st.full_name, st.student_id, sec.section_name, subj.subject_name, sub.status, COALESCE(sub.grade, "-") AS grade, sub.submitted_at FROM submissions sub JOIN students st ON st.id = sub.student_id JOIN sections sec ON sec.id = sub.section_id JOIN subjects subj ON subj.id = sub.subject_id ORDER BY sub.submitted_at DESC')->fetchAll();
$title = 'Printable Submission Report';
$subtitle = 'Compact report for offline review or filing';
require_once __DIR__ . '/../backend/partials/header.php';
?>
<div class="print-only"><h2>Submission Report</h2></div>
<div class="card"><div class="table-wrap"><table><thead><tr><th>Student</th><th>Section</th><th>Subject</th><th>Status</th><th>Grade</th><th>Submitted</th></tr></thead><tbody><?php foreach ($rows as $row): ?><tr><td><strong><?= h($row['full_name']) ?></strong><div class="muted small"><?= h($row['student_id']) ?></div></td><td><?= h($row['section_name']) ?></td><td><?= h($row['subject_name']) ?></td><td><?= h($row['status']) ?></td><td><?= h($row['grade']) ?></td><td><?= h($row['submitted_at']) ?></td></tr><?php endforeach; ?></tbody></table></div></div>
<script>window.print();</script>
<?php require_once __DIR__ . '/../backend/partials/footer.php'; ?>
