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
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();
$summary = ['total' => count($rows), 'pending' => 0, 'reviewed' => 0, 'graded' => 0];
foreach ($rows as $row) {
    if (isset($summary[$row['status']])) { $summary[$row['status']]++; }
}
$title = 'Teacher Submissions';
$subtitle = 'Open, review, and grade only the submissions that belong to your assigned subjects';
require_once __DIR__ . '/../backend/partials/header.php';
?>
<div class="grid cols-4" style="margin-bottom:18px;">
  <div class="card metric-card"><span class="metric-label">Visible to you</span><div class="metric"><strong><?= (int) $summary['total'] ?></strong><span class="metric-trend">Assigned submissions only</span></div></div>
  <div class="card"><span class="metric-label">Pending</span><div class="stat-mini"><strong><?= (int) $summary['pending'] ?></strong><span class="muted">Needs first review</span></div></div>
  <div class="card"><span class="metric-label">Reviewed</span><div class="stat-mini"><strong><?= (int) $summary['reviewed'] ?></strong><span class="muted">Seen but not finalized</span></div></div>
  <div class="card"><span class="metric-label">Graded</span><div class="stat-mini"><strong><?= (int) $summary['graded'] ?></strong><span class="muted">Ready for student view</span></div></div>
</div>
<div class="card">
  <div class="callout" style="margin-bottom:16px;">You only see submissions for <strong>subjects directly assigned to your teacher account</strong>. Other teachers cannot open these rows even if they teach a similar subject.</div>
  <div class="teacher-submission-toolbar">
    <div>
      <h3 style="margin:0 0 4px;">Submission review workspace</h3>
      <p class="muted" style="margin:0;">Open links, check demo access, and leave a quick review without losing the row context.</p>
    </div>
  </div>
  <form method="get" class="filter-row teacher-submission-filters">
    <select name="subject_id"><option value="0">All assigned subjects</option><?php foreach ($subjectOptions as $subject): ?><option value="<?= (int) $subject['id'] ?>" <?= $filterSubject === (int)$subject['id'] ? 'selected' : '' ?>><?= h($subject['subject_name']) ?> · <?= h($subject['subject_code']) ?></option><?php endforeach; ?></select>
    <select name="status"><option value="">All statuses</option><option value="pending" <?= selected($filterStatus, 'pending') ?>>Pending</option><option value="reviewed" <?= selected($filterStatus, 'reviewed') ?>>Reviewed</option><option value="graded" <?= selected($filterStatus, 'graded') ?>>Graded</option></select>
    <button class="btn btn-secondary" type="submit">Apply filters</button>
  </form>
  <div class="table-wrap teacher-submission-wrap">
    <table class="teacher-submission-table">
      <thead><tr class="submission-row-card"><th>Student / Project</th><th>Links</th><th>Demo access</th><th>Review</th><th>Actions</th></tr></thead>
      <tbody>
      <?php foreach ($rows as $row): ?>
        <?php $passwordId = 'demo-pass-' . (int) $row['id']; ?>
        <tr class="submission-row-card">
          <td data-label="Student / Project">
            <div class="submission-student-cell">
              <div class="submission-student-head">
                <strong><?= h($row['full_name']) ?></strong>
                <?= status_badge($row['status']) ?>
              </div>
              <div class="submission-meta-stack">
                <div class="muted small"><?= h($row['student_code']) ?> · <?= h($row['section_name']) ?></div>
                <?php if (!empty($row['team_name'])): ?><div class="muted small">Team: <?= h($row['team_name']) ?></div><?php endif; ?>
                <div class="submission-project-chip">
                  <strong><?= h($row['assigned_system']) ?></strong>
                  <span><?= h($row['subject_name']) ?> · <?= h($row['subject_code']) ?></span>
                </div>
              </div>
            </div>
          </td>
          <td data-label="Links">
            <div class="submission-link-stack">
              <a class="submission-link-card" href="<?= h($row['project_url']) ?>" target="_blank" rel="noopener">
                <span class="submission-link-label">Project URL</span>
                <strong>Open live project</strong>
                <span class="muted small"><?= h($row['project_url']) ?></span>
              </a>
              <?php if (!empty($row['video_url'])): ?>
                <a class="submission-link-card soft" href="<?= h($row['video_url']) ?>" target="_blank" rel="noopener">
                  <span class="submission-link-label">Video demo</span>
                  <strong>Open walkthrough</strong>
                  <span class="muted small"><?= h($row['video_url']) ?></span>
                </a>
              <?php else: ?>
                <div class="table-note">No video walkthrough was provided for this submission.</div>
              <?php endif; ?>
            </div>
          </td>
          <td data-label="Demo access">
            <?php if (has_demo_access($row)): ?>
              <div class="access-card">
                <div class="access-card-head">
                  <strong>Demo login</strong>
                  <span class="access-badge">Teacher-only</span>
                </div>
                <div class="credential-row">
                  <span class="credential-label">Username</span>
                  <code class="credential-value" data-copy-source><?= h($row['admin_username'] ?: '—') ?></code>
                  <?php if (!empty($row['admin_username'])): ?>
                    <button type="button" class="btn btn-outline credential-btn" data-copy-text="<?= h($row['admin_username']) ?>">Copy</button>
                  <?php endif; ?>
                </div>
                <div class="credential-row">
                  <span class="credential-label">Password</span>
                  <code class="credential-value credential-secret<?= empty($row['admin_password']) ? ' code-muted' : '' ?>" id="<?= h($passwordId) ?>" data-secret="<?= h($row['admin_password']) ?>"><?= !empty($row['admin_password']) ? '••••••••' : '—' ?></code>
                  <?php if (!empty($row['admin_password'])): ?>
                    <button type="button" class="btn btn-outline credential-btn" data-toggle-secret="<?= h($passwordId) ?>">Show</button>
                    <button type="button" class="btn btn-outline credential-btn" data-copy-text="<?= h($row['admin_password']) ?>">Copy</button>
                  <?php endif; ?>
                </div>
              </div>
            <?php else: ?>
              <div class="table-note">No demo credentials were provided. Open the project link directly.</div>
            <?php endif; ?>
          </td>
          <td data-label="Review">
            <div class="review-card review-<?= h($row['status']) ?>">
              <div class="review-card-head">
                <strong><?= h(ucfirst($row['status'])) ?></strong>
                <span class="review-grade">Grade: <?= h($row['grade'] ?: '—') ?></span>
              </div>
              <div class="review-card-copy muted small">
                <?= $row['teacher_feedback'] ? h(mb_strimwidth($row['teacher_feedback'], 0, 96, '…')) : 'No feedback written yet.' ?>
              </div>
              <details class="quick-review" style="margin-top:10px;">
                <summary class="btn btn-outline">Quick review</summary>
                <form method="post" class="grid quick-review-form">
                  <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
                  <input type="hidden" name="submission_id" value="<?= (int)$row['id'] ?>">
                  <select name="status"><?php foreach (['pending','reviewed','graded'] as $s): ?><option value="<?= h($s) ?>" <?= $row['status']===$s?'selected':'' ?>><?= h(ucfirst($s)) ?></option><?php endforeach; ?></select>
                  <input name="grade" value="<?= h($row['grade']) ?>" placeholder="Grade">
                  <textarea name="teacher_feedback" placeholder="Feedback for the team"><?= h($row['teacher_feedback']) ?></textarea>
                  <button class="btn btn-secondary" type="submit">Save review</button>
                </form>
              </details>
            </div>
          </td>
          <td data-label="Actions">
            <div class="table-actions submission-action-stack">
              <a class="btn" href="<?= h(url('teacher/submission_view.php?id=' . (int) $row['id'])) ?>">Open workspace</a>
              <a class="btn btn-outline" href="<?= h(url('teacher/print_submission.php?id=' . (int) $row['id'])) ?>" target="_blank">Print</a>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$rows): ?><tr class="submission-row-card"><td colspan="5" class="empty-state">No submissions matched your current filters.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php require_once __DIR__ . '/../backend/partials/footer.php'; ?>
