<?php
if (defined('FILE_ADMIN_TEACHER_PREVIEW_PHP_LOADED')) { return; }
define('FILE_ADMIN_TEACHER_PREVIEW_PHP_LOADED', true);

require_once __DIR__ . '/../backend/config/app.php';
require_role('admin');
$teacherId = (int) ($_GET['id'] ?? 0);
$stmt = pdo()->prepare('SELECT t.*, COUNT(DISTINCT subj.id) AS total_subjects, COUNT(DISTINCT ss.section_id) AS total_sections, COUNT(DISTINCT sub.id) AS total_submissions FROM teachers t LEFT JOIN subjects subj ON subj.teacher_id = t.id LEFT JOIN section_subjects ss ON ss.subject_id = subj.id LEFT JOIN submissions sub ON sub.subject_id = subj.id WHERE t.id = ? GROUP BY t.id LIMIT 1');
$stmt->execute([$teacherId]);
$teacher = $stmt->fetch();
if (!$teacher) {
    http_response_code(404);
    echo '<div class="empty-state">Teacher not found.</div>';
    exit;
}
$subjectsStmt = pdo()->prepare('SELECT subject_code, subject_name, status FROM subjects WHERE teacher_id = ? ORDER BY subject_name LIMIT 6');
$subjectsStmt->execute([$teacherId]);
$subjects = $subjectsStmt->fetchAll();
?>
<div class="preview-shell detail-preview-shell">
  <div class="preview-hero">
    <div>
      <span class="pill soft"><?= h($teacher['teacher_id']) ?></span>
      <h3><?= h($teacher['full_name']) ?></h3>
      <p class="muted"><?= h($teacher['email']) ?> · <?= h($teacher['username']) ?></p>
    </div>
    <div class="preview-hero-actions">
      <?= status_badge((string) $teacher['status']) ?>
      <a class="btn" href="<?= h(url('admin/teacher_view.php?id=' . $teacherId)) ?>">Open full page</a>
    </div>
  </div>
  <div class="modal-summary-grid modal-summary-grid-3">
    <div class="segment"><span class="muted small">Subjects</span><strong><?= (int) $teacher['total_subjects'] ?></strong></div>
    <div class="segment"><span class="muted small">Sections</span><strong><?= (int) $teacher['total_sections'] ?></strong></div>
    <div class="segment"><span class="muted small">Submissions</span><strong><?= (int) $teacher['total_submissions'] ?></strong></div>
  </div>
  <section class="card preview-panel compact-panel" style="margin-top:16px;">
    <div class="split-header"><h4 class="section-title">Recent subject ownership</h4><span class="pill"><?= count($subjects) ?></span></div>
    <?php if ($subjects): ?><div class="preview-resource-list"><?php foreach ($subjects as $subject): ?><div class="portal-link-box"><div><strong><?= h($subject['subject_name']) ?></strong><div class="muted small"><?= h($subject['subject_code']) ?></div></div><?= status_badge((string) $subject['status']) ?></div><?php endforeach; ?></div><?php else: ?><div class="empty-state">No subjects assigned yet.</div><?php endif; ?>
  </section>
</div>
