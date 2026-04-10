<?php
require_once __DIR__ . '/../backend/helpers/query.php';
require_role('teacher');
$teacher = current_user();
$pdo = pdo();
$stmt = $pdo->prepare('SELECT subj.*, GROUP_CONCAT(sec.section_name ORDER BY sec.section_name SEPARATOR ", ") AS sections, COUNT(sub.id) AS total_submissions FROM subjects subj LEFT JOIN section_subjects ss ON ss.subject_id = subj.id LEFT JOIN sections sec ON sec.id = ss.section_id LEFT JOIN submissions sub ON sub.subject_id = subj.id WHERE subj.teacher_id = ? GROUP BY subj.id ORDER BY subj.subject_name');
$stmt->execute([(int)$teacher['id']]);
$subjects = $stmt->fetchAll();
$title = 'My Subjects';
$subtitle = 'Subjects you own and their linked sections';
require_once __DIR__ . '/../backend/partials/header.php';
?>
<div class="card">
  <div class="table-wrap"><table><thead><tr><th>Subject</th><th>Description</th><th>Sections</th><th>Status</th><th></th></tr></thead><tbody><?php foreach ($subjects as $row): ?><tr><td><strong><?= h($row['subject_name']) ?></strong><div class="muted small"><?= h($row['subject_code']) ?></div></td><td><?= h($row['description']) ?></td><td><?= h($row['sections'] ?: 'No sections') ?><div class="muted small"><?= (int) $row['total_submissions'] ?> submissions</div></td><td><?= status_badge($row['status']) ?></td><td><a class="btn btn-secondary" href="<?= h(url('teacher/subject_view.php?id=' . (int) $row['id'])) ?>">Open</a></td></tr><?php endforeach; ?></tbody></table></div>
</div>
<?php require_once __DIR__ . '/../backend/partials/footer.php'; ?>
