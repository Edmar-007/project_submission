<?php
require_once __DIR__ . '/../backend/helpers/query.php';
require_role('admin');
$pdo = pdo();
$admin = current_user();
$filterStatus = trim($_GET['status'] ?? '');
$filterSection = (int) ($_GET['section_id'] ?? 0);
$filterSubject = (int) ($_GET['subject_id'] ?? 0);
$includeArchived = isset($_GET['archived']) ? 1 : 0;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $submissionId = (int) ($_POST['submission_id'] ?? 0);
    $status = $_POST['status'] ?? 'pending';
    $grade = trim($_POST['grade'] ?? '');
    $feedback = trim($_POST['teacher_feedback'] ?? '');
    $reviewNotes = trim($_POST['review_notes'] ?? '');
    if ($submissionId && (($_POST['action'] ?? 'review') === 'archive')) {
        $pdo->prepare('UPDATE submissions SET status = "archived" WHERE id = ?')->execute([$submissionId]);
        log_action('admin', (int)$admin['id'], 'archive_submission', 'submission', $submissionId, 'Archived submission');
        set_flash('success', 'Submission archived successfully.');
        redirect_to('admin/submissions.php');
    }
    if ($submissionId) {
        $stmt = $pdo->prepare('UPDATE submissions SET status = ?, grade = ?, teacher_feedback = ?, review_notes = ? WHERE id = ?');
        $stmt->execute([$status, $grade ?: null, $feedback ?: null, $reviewNotes ?: null, $submissionId]);
        $lookup = $pdo->prepare('SELECT student_id FROM submissions WHERE id = ?');
        $lookup->execute([$submissionId]);
        $studentId = (int) $lookup->fetchColumn();
        if ($studentId) {
            create_notification('student', $studentId, 'Submission updated', 'Your submission review status has been updated to ' . $status . ($grade ? ' with grade ' . $grade : '') . '.', $status === 'graded' ? 'success' : 'info');
        }
        log_action('admin', (int)$admin['id'], 'review_submission', 'submission', $submissionId, $status);
        set_flash('success', 'Submission updated successfully.');
    }
    redirect_to('admin/submissions.php');
}
$sections = all_sections();
$subjects = all_subjects();
$sql = 'SELECT sub.*, st.full_name, st.student_id AS student_code, sec.section_name, subj.subject_name, teams.team_name FROM submissions sub JOIN students st ON st.id = sub.student_id JOIN sections sec ON sec.id = sub.section_id JOIN subjects subj ON subj.id = sub.subject_id LEFT JOIN teams ON teams.id = sub.team_id WHERE 1=1';
if (!$includeArchived) { $sql .= ' AND sub.status <> "archived"'; }
$params = [];
if ($filterStatus !== '') { $sql .= ' AND sub.status = ?'; $params[] = $filterStatus; }
if ($filterSection) { $sql .= ' AND sub.section_id = ?'; $params[] = $filterSection; }
if ($filterSubject) { $sql .= ' AND sub.subject_id = ?'; $params[] = $filterSubject; }
$sql .= ' ORDER BY sub.submitted_at DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();
$title = 'Submissions';
$subtitle = 'Review, grade, and update project submissions';
require_once __DIR__ . '/../backend/partials/header.php';
?>
<div class="card highlight-card">
  <form method="get" class="filter-row">
    <select name="status"><option value="">All statuses</option><option value="pending" <?= selected($filterStatus,'pending') ?>>Pending</option><option value="reviewed" <?= selected($filterStatus,'reviewed') ?>>Reviewed</option><option value="graded" <?= selected($filterStatus,'graded') ?>>Graded</option></select>
    <select name="section_id"><option value="0">All sections</option><?php foreach ($sections as $section): ?><option value="<?= (int) $section['id'] ?>" <?= $filterSection === (int) $section['id'] ? 'selected' : '' ?>><?= h($section['section_name']) ?></option><?php endforeach; ?></select>
    <select name="subject_id"><option value="0">All subjects</option><?php foreach ($subjects as $subject): ?><option value="<?= (int) $subject['id'] ?>" <?= $filterSubject === (int) $subject['id'] ? 'selected' : '' ?>><?= h($subject['subject_name']) ?></option><?php endforeach; ?></select>
    <label><input type="checkbox" name="archived" value="1" <?= $includeArchived ? 'checked' : '' ?>> Include archived</label>
    <button class="btn" type="submit">Apply filters</button>
  </form>
</div>
<div class="card" style="margin-top:18px;">
  <div class="table-wrap"><table><thead><tr><th>Student</th><th>Subject</th><th>Project</th><th>Status</th><th>Actions</th></tr></thead><tbody><?php foreach ($rows as $row): ?><tr><td><strong><?= h($row['full_name']) ?><?php if (!empty($row['team_name'])): ?><div class="muted small"><?= h($row['team_name']) ?></div><?php endif; ?></strong><div class="muted small"><?= h($row['student_code']) ?> · <?= h($row['section_name']) ?></div></td><td><?= h($row['subject_name']) ?></td><td><strong><?= h($row['assigned_system']) ?></strong><div class="muted small"><?= h($row['company_name']) ?></div><div class="muted small"><a href="<?= h($row['project_url']) ?>" target="_blank">Project</a> · <a href="<?= h($row['video_url']) ?>" target="_blank">Video</a></div></td><td><?= status_badge($row['status']) ?><div class="muted small">Grade: <?= h($row['grade'] ?: '—') ?></div></td><td><div class="table-actions"><a class="btn btn-secondary" href="<?= h(url('admin/submission_view.php?id=' . (int) $row['id'])) ?>">Open</a><?php if ($row['status'] !== 'archived'): ?><form method="post" class="inline"><input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>"><input type="hidden" name="action" value="archive"><input type="hidden" name="submission_id" value="<?= (int) $row['id'] ?>"><button class="btn btn-outline" type="submit">Archive</button></form><?php endif; ?><details><summary class="btn btn-outline">Quick review</summary><form method="post" class="grid" style="margin-top:12px; min-width:280px;"><input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>"><input type="hidden" name="submission_id" value="<?= (int) $row['id'] ?>"><select name="status"><?php foreach (['pending','reviewed','graded'] as $s): ?><option value="<?= h($s) ?>" <?= $row['status']===$s?'selected':'' ?>><?= h($s) ?></option><?php endforeach; ?></select><input name="grade" value="<?= h($row['grade']) ?>" placeholder="Grade"><textarea name="teacher_feedback" placeholder="Feedback"><?= h($row['teacher_feedback']) ?></textarea><textarea name="review_notes" placeholder="Internal review notes"><?= h($row['review_notes']) ?></textarea><button class="btn btn-secondary" type="submit">Save review</button></form></details></div></td></tr><?php endforeach; ?><?php if (!$rows): ?><tr><td colspan="5" class="empty-state">No submissions matched your filters.</td></tr><?php endif; ?></tbody></table></div>
</div>
<?php require_once __DIR__ . '/../backend/partials/footer.php'; ?>
