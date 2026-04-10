<?php
require_once __DIR__ . '/../backend/helpers/query.php';
require_role('teacher');
$teacher = current_user();
$pdo = pdo();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $submissionId = (int) ($_POST['submission_id'] ?? 0);
    $status = $_POST['status'] ?? 'pending';
    $grade = trim($_POST['grade'] ?? '');
    $feedback = trim($_POST['teacher_feedback'] ?? '');
    if ($submissionId && teacher_can_access_submission((int) $teacher['id'], $submissionId)) {
        $check = $pdo->prepare('SELECT student_id FROM submissions WHERE id = ?');
        $check->execute([$submissionId]);
        $studentId = (int) $check->fetchColumn();
        $pdo->prepare('UPDATE submissions SET status = ?, grade = ?, teacher_feedback = ? WHERE id = ?')->execute([$status, $grade ?: null, $feedback ?: null, $submissionId]);
        if ($studentId) {
            create_notification('student', $studentId, 'Submission reviewed', 'Your submission has been updated to ' . $status . ($grade ? ' with grade ' . $grade : '') . '.', $status === 'graded' ? 'success' : 'info');
        }
        set_flash('Submission review saved.', 'success');
    } else {
        set_flash('You can only review submissions from subjects assigned to you.', 'error');
    }
    redirect_to('teacher/submissions.php');
}
$filterSubject = (int) ($_GET['subject_id'] ?? 0);
$filterStatus = trim($_GET['status'] ?? '');
$subjectOptionsStmt = $pdo->prepare('SELECT id, subject_name, subject_code FROM subjects WHERE teacher_id = ? ORDER BY subject_name');
$subjectOptionsStmt->execute([(int) $teacher['id']]);
$subjectOptions = $subjectOptionsStmt->fetchAll();
$sql = 'SELECT sub.*, st.full_name, st.student_id AS student_code, sec.section_name, subj.subject_name, subj.subject_code, teams.team_name FROM submissions sub JOIN students st ON st.id = sub.student_id JOIN sections sec ON sec.id = sub.section_id JOIN subjects subj ON subj.id = sub.subject_id LEFT JOIN teams ON teams.id = sub.team_id WHERE subj.teacher_id = ?';
$params = [(int) $teacher['id']];
if ($filterSubject) { $sql .= ' AND sub.subject_id = ?'; $params[] = $filterSubject; }
if ($filterStatus !== '') { $sql .= ' AND sub.status = ?'; $params[] = $filterStatus; }
$sql .= ' ORDER BY sub.submitted_at DESC';
$stmt = $pdo->prepare($sql); $stmt->execute($params); $rows = $stmt->fetchAll();
$summary = ['total' => count($rows), 'pending' => 0, 'reviewed' => 0, 'graded' => 0];
foreach ($rows as $row) { if (isset($summary[$row['status']])) { $summary[$row['status']]++; } }
$title = 'Teacher Submissions';
$subtitle = 'Teacher review space cleaned into one focused table with quick review modals.';
require_once __DIR__ . '/../backend/partials/header.php';
?>
<div class="kpi-grid"><div class="kpi-card"><span class="label">Visible</span><strong><?= (int) $summary['total'] ?></strong><span class="muted small">Assigned submissions only</span></div><div class="kpi-card"><span class="label">Pending</span><strong><?= (int) $summary['pending'] ?></strong><span class="muted small">Needs first review</span></div><div class="kpi-card"><span class="label">Reviewed</span><strong><?= (int) $summary['reviewed'] ?></strong><span class="muted small">Seen but not finalized</span></div><div class="kpi-card"><span class="label">Graded</span><strong><?= (int) $summary['graded'] ?></strong><span class="muted small">Ready for students</span></div></div>
<section class="table-card table-bootstrap-shell"><div class="module-header"><div><div class="eyebrow">Teacher Review</div><h3 class="mb-1">Submission Review Table</h3><p class="muted mb-0">Only submissions from subjects directly assigned to you are shown here.</p></div><div class="module-actions"><span class="badge-soft"><i class="bi bi-table"></i> <?= count($rows) ?> rows</span></div></div><div class="table-toolbar"><form method="get" class="filters"><select class="form-select" name="subject_id"><option value="0">All my subjects</option><?php foreach ($subjectOptions as $subject): ?><option value="<?= (int) $subject['id'] ?>" <?= $filterSubject === (int) $subject['id'] ? 'selected' : '' ?>><?= h($subject['subject_name']) ?></option><?php endforeach; ?></select><select class="form-select" name="status"><option value="">All statuses</option><option value="pending" <?= selected($filterStatus, 'pending') ?>>Pending</option><option value="reviewed" <?= selected($filterStatus, 'reviewed') ?>>Reviewed</option><option value="graded" <?= selected($filterStatus, 'graded') ?>>Graded</option></select><button class="btn" type="submit"><i class="bi bi-funnel"></i> Apply Filters</button></form></div><div class="table-responsive"><table class="table table-hover align-middle"><thead><tr><th>Student</th><th>Subject</th><th>Project</th><th>Status</th><th class="text-end">Actions</th></tr></thead><tbody><?php foreach ($rows as $row): ?><tr><td><strong><?= h($row['full_name']) ?></strong><div class="muted small"><?= h($row['student_code']) ?> · <?= h($row['section_name']) ?></div><?php if (!empty($row['team_name'])): ?><div class="muted small"><?= h($row['team_name']) ?></div><?php endif; ?></td><td><strong><?= h($row['subject_name']) ?></strong><div class="muted small"><?= h($row['subject_code']) ?></div></td><td><strong><?= h($row['assigned_system']) ?></strong><div class="muted small">Submitted <?= h($row['submitted_at']) ?></div></td><td><?= status_badge($row['status']) ?><div class="muted small mt-1">Grade <?= h($row['grade'] ?: '—') ?></div></td><td class="text-end"><div class="icon-action-group justify-content-end"><a class="icon-action" href="<?= h(url('teacher/submission_view.php?id=' . (int) $row['id'])) ?>" title="Open submission"><i class="bi bi-eye"></i></a><button class="icon-action" type="button" data-open-modal="teacher-review-<?= (int) $row['id'] ?>" title="Quick review"><i class="bi bi-pencil-square"></i></button></div></td></tr><?php endforeach; ?><?php if (!$rows): ?><tr><td colspan="5" class="table-empty">No submissions matched your current filters.</td></tr><?php endif; ?></tbody></table></div></section>
<?php foreach ($rows as $row): ?><div class="modal-backdrop" data-modal="teacher-review-<?= (int) $row['id'] ?>" aria-hidden="true"><div class="modal-card" role="dialog" aria-modal="true"><div class="modal-head"><div><span class="badge-soft"><i class="bi bi-pencil-square"></i> Quick Review</span><h3><?= h($row['assigned_system']) ?></h3><p class="muted mb-0"><?= h($row['full_name']) ?> · <?= h($row['subject_name']) ?></p></div><button type="button" class="icon-btn modal-close" data-close-modal aria-label="Close">✕</button></div><form method="post" class="form-modal-grid"><input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>"><input type="hidden" name="submission_id" value="<?= (int) $row['id'] ?>"><div><label>Status</label><select class="form-select" name="status"><?php foreach (['pending','reviewed','graded'] as $s): ?><option value="<?= h($s) ?>" <?= $row['status']===$s?'selected':'' ?>><?= h(ucfirst($s)) ?></option><?php endforeach; ?></select></div><div><label>Grade</label><input class="form-control" name="grade" value="<?= h((string)$row['grade']) ?>" placeholder="Grade"></div><div class="full"><label>Feedback</label><textarea class="form-control" name="teacher_feedback" rows="4" placeholder="Feedback for the team"><?= h((string)$row['teacher_feedback']) ?></textarea></div><div class="full d-flex justify-content-end gap-2"><button class="btn btn-outline" type="button" data-close-modal>Cancel</button><button class="btn" type="submit">Save review</button></div></form></div></div><?php endforeach; ?>
<?php require_once __DIR__ . '/../backend/partials/footer.php'; ?>
