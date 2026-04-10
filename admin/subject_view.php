<?php
require_once __DIR__ . '/../backend/helpers/query.php';
require_role('admin');
$subjectId = (int) ($_GET['id'] ?? 0);
$subject = $subjectId ? fetch_subject_detail($subjectId) : null;
if (!$subject) {
    set_flash('error', 'Subject not found.');
    redirect_to('admin/subjects.php');
}
$sectionsStmt = pdo()->prepare('SELECT sec.*, COUNT(st.id) AS total_students FROM section_subjects ss JOIN sections sec ON sec.id = ss.section_id LEFT JOIN students st ON st.section_id = sec.id WHERE ss.subject_id = ? GROUP BY sec.id ORDER BY sec.section_name');
$sectionsStmt->execute([$subjectId]);
$sections = $sectionsStmt->fetchAll();
$submissionsStmt = pdo()->prepare('SELECT sub.id, st.full_name, st.student_id AS student_code, sec.section_name, sub.status, sub.grade, sub.submitted_at FROM submissions sub JOIN students st ON st.id = sub.student_id JOIN sections sec ON sec.id = sub.section_id WHERE sub.subject_id = ? ORDER BY sub.submitted_at DESC LIMIT 10');
$submissionsStmt->execute([$subjectId]);
$submissions = $submissionsStmt->fetchAll();
$totalStudents = array_sum(array_map(fn($row) => (int) $row['total_students'], $sections));
$title = 'Subject Detail';
$subtitle = 'Teacher ownership, assigned sections, student reach, and submission analytics';
require_once __DIR__ . '/../backend/partials/header.php';
?>
<div class="detail-grid">
  <div class="detail-section">
    <div class="card">
      <div class="split-header"><div><h3 class="section-title"><?= h($subject['subject_name']) ?></h3><div class="muted small"><?= h($subject['subject_code']) ?> · <?= h($subject['school_year']) ?> · <?= h($subject['semester']) ?></div></div><?= status_badge($subject['status']) ?></div>
      <p class="muted"><?= h($subject['description'] ?: 'No description provided.') ?></p>
      <div class="grid cols-3">
        <div class="card stat-mini"><span class="metric-label">Sections</span><strong><?= count($sections) ?></strong></div>
        <div class="card stat-mini"><span class="metric-label">Students reached</span><strong><?= $totalStudents ?></strong></div>
        <div class="card stat-mini"><span class="metric-label">Submissions</span><strong><?= count($submissions) ?></strong></div>
      </div>
    </div>
    <div class="card">
      <h3 class="section-title">Assigned sections</h3>
      <div class="table-wrap"><table><thead><tr><th>Section</th><th>Status</th><th>Students</th></tr></thead><tbody><?php foreach ($sections as $section): ?><tr><td><?= h($section['section_name']) ?></td><td><?= status_badge($section['status']) ?></td><td><?= (int) $section['total_students'] ?></td></tr><?php endforeach; ?><?php if (!$sections): ?><tr><td colspan="3" class="empty-state">No sections assigned yet.</td></tr><?php endif; ?></tbody></table></div>
    </div>
  </div>
  <div class="detail-section">
    <div class="card">
      <h3 class="section-title">Owner</h3>
      <div class="info-list"><div class="row"><span>Teacher</span><strong><?= h($subject['teacher_name']) ?></strong></div><div class="row"><span>Email</span><strong><?= h($subject['teacher_email']) ?></strong></div></div>
    </div>
    <div class="card">
      <h3 class="section-title">Recent submissions</h3>
      <div class="timeline-list"><?php foreach ($submissions as $submission): ?><div class="timeline-item"><div class="notification-title-row"><strong><?= h($submission['full_name']) ?></strong><?= status_badge($submission['status']) ?></div><p><?= h($submission['student_code']) ?> · <?= h($submission['section_name']) ?></p><div class="muted small">Grade: <?= h($submission['grade'] ?: '—') ?> · <?= h($submission['submitted_at']) ?></div><div style="margin-top:10px;"><a class="muted-link" href="<?= h(url('admin/submission_view.php?id=' . (int) $submission['id'])) ?>">Open submission</a></div></div><?php endforeach; ?><?php if (!$submissions): ?><div class="empty-state">No submissions in this subject yet.</div><?php endif; ?></div>
    </div>
  </div>
</div>
<?php require_once __DIR__ . '/../backend/partials/footer.php'; ?>
