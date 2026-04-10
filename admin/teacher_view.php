<?php
require_once __DIR__ . '/../backend/helpers/query.php';
require_role('admin');
$teacherId = (int) ($_GET['id'] ?? 0);
$stmt = pdo()->prepare('SELECT * FROM teachers WHERE id = ? LIMIT 1');
$stmt->execute([$teacherId]);
$teacher = $stmt->fetch();
if (!$teacher) {
    set_flash('error', 'Teacher not found.');
    redirect_to('admin/teachers.php');
}
$subjectsStmt = pdo()->prepare('SELECT subj.*, COUNT(ss.id) AS total_sections, COUNT(sub.id) AS total_submissions FROM subjects subj LEFT JOIN section_subjects ss ON ss.subject_id = subj.id LEFT JOIN submissions sub ON sub.subject_id = subj.id WHERE subj.teacher_id = ? GROUP BY subj.id ORDER BY subj.subject_name');
$subjectsStmt->execute([$teacherId]);
$subjects = $subjectsStmt->fetchAll();
$notifications = pdo()->prepare('SELECT * FROM notifications WHERE user_type = "teacher" AND user_id = ? ORDER BY created_at DESC LIMIT 6');
$notifications->execute([$teacherId]);
$notifications = $notifications->fetchAll();
$title = 'Teacher Detail';
$subtitle = 'Subject ownership, section reach, and recent teacher-side activity';
require_once __DIR__ . '/../backend/partials/header.php';
?>
<div class="detail-grid">
  <div class="detail-section">
    <div class="card">
      <div class="split-header"><div><h3 class="section-title"><?= h($teacher['full_name']) ?></h3><div class="muted small"><?= h($teacher['teacher_id']) ?> · <?= h($teacher['email']) ?></div></div><?= status_badge($teacher['status']) ?></div>
      <div class="grid cols-3">
        <div class="card stat-mini"><span class="metric-label">Subjects</span><strong><?= count($subjects) ?></strong></div>
        <div class="card stat-mini"><span class="metric-label">Sections</span><strong><?= array_sum(array_map(fn($row) => (int) $row['total_sections'], $subjects)) ?></strong></div>
        <div class="card stat-mini"><span class="metric-label">Submissions</span><strong><?= array_sum(array_map(fn($row) => (int) $row['total_submissions'], $subjects)) ?></strong></div>
      </div>
    </div>
    <div class="card">
      <h3 class="section-title">Owned subjects</h3>
      <div class="table-wrap"><table><thead><tr><th>Subject</th><th>Sections</th><th>Submissions</th><th></th></tr></thead><tbody><?php foreach ($subjects as $row): ?><tr><td><strong><?= h($row['subject_name']) ?></strong><div class="muted small"><?= h($row['subject_code']) ?></div></td><td><?= (int) $row['total_sections'] ?></td><td><?= (int) $row['total_submissions'] ?></td><td><a class="muted-link" href="<?= h(url('admin/subject_view.php?id=' . (int) $row['id'])) ?>">Open subject</a></td></tr><?php endforeach; ?><?php if (!$subjects): ?><tr><td colspan="4" class="empty-state">No subjects assigned yet.</td></tr><?php endif; ?></tbody></table></div>
    </div>
  </div>
  <div class="detail-section">
    <div class="card">
      <h3 class="section-title">Recent teacher notifications</h3>
      <div class="timeline-list"><?php foreach ($notifications as $note): ?><div class="timeline-item"><div class="notification-title-row"><strong><?= h($note['title']) ?></strong><?= status_badge($note['type']) ?></div><p><?= h($note['message']) ?></p><div class="muted small"><?= h($note['created_at']) ?></div></div><?php endforeach; ?><?php if (!$notifications): ?><div class="empty-state">No teacher notifications recorded.</div><?php endif; ?></div>
    </div>
    <div class="card callout">
      Teacher accounts work best when each subject clearly maps to the right sections. This keeps dashboards and review pipelines clean for each instructor.
    </div>
  </div>
</div>
<?php require_once __DIR__ . '/../backend/partials/footer.php'; ?>
