<?php
require_once __DIR__ . '/../backend/helpers/query.php';
require_role('teacher');
$teacher = current_user();
$pdo = pdo();
$subjectId = (int) ($_GET['id'] ?? 0);
$subject = fetch_subject_detail($subjectId);
if (!$subject || (int) $subject['teacher_id'] !== (int) $teacher['id']) {
    set_flash('error', 'Subject not found.');
    redirect_to('teacher/subjects.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? 'save_deadline';
    if ($action === 'save_deadline') {
        $deadline = trim($_POST['submission_deadline'] ?? '') ?: null;
        $warningHours = max(1, (int) ($_POST['deadline_warning_hours'] ?? 72));
        $pdo->prepare('UPDATE subjects SET submission_deadline = ?, deadline_warning_hours = ?, deadline_warning_sent_at = NULL, deadline_locked_notice_sent_at = NULL WHERE id = ? AND teacher_id = ?')->execute([$deadline, $warningHours, $subjectId, $teacher['id']]);
        set_flash('success', 'Submission deadline settings saved.');
    }
    if ($action === 'toggle_late') {
        $allowLate = isset($_POST['allow_late_submissions']) ? 1 : 0;
        $lateUntil = trim($_POST['late_submission_until'] ?? '') ?: null;
        $pdo->prepare('UPDATE subjects SET allow_late_submissions = ?, late_submission_until = ? WHERE id = ? AND teacher_id = ?')->execute([$allowLate, $lateUntil, $subjectId, $teacher['id']]);
        $subject = fetch_subject_detail($subjectId);
        $studentStmt = $pdo->prepare('SELECT DISTINCT st.id, st.full_name, st.email FROM section_subjects ss JOIN students st ON st.section_id = ss.section_id WHERE ss.subject_id = ? AND st.account_status <> "archived"');
        $studentStmt->execute([$subjectId]);
        $students = $studentStmt->fetchAll();
        $title = $allowLate ? 'Submission reopened: ' . $subject['subject_name'] : 'Late access ended: ' . $subject['subject_name'];
        $message = $allowLate
            ? 'Your teacher reopened submissions for ' . $subject['subject_name'] . ($lateUntil ? ' until ' . date('M d, Y h:i A', strtotime($lateUntil)) . '.' : '.')
            : 'Late submission access for ' . $subject['subject_name'] . ' has been removed. The deadline lock now applies again.';
        require_once __DIR__ . '/../backend/helpers/mailer.php';
        foreach ($students as $studentRow) {
            create_notification_once('student', (int) $studentRow['id'], $title, $message, $allowLate ? 'info' : 'warning');
            if (!empty($studentRow['email']) && filter_var($studentRow['email'], FILTER_VALIDATE_EMAIL)) {
                send_system_mail(
                    (string) $studentRow['email'],
                    $title,
                    "Hello {$studentRow['full_name']},

{$message}

Regards,
" . APP_NAME
                );
            }
        }
        create_notification_once('teacher', (int) $teacher['id'], $title, $allowLate
            ? 'Late submission access is now enabled for ' . $subject['subject_name'] . ($lateUntil ? ' until ' . date('M d, Y h:i A', strtotime($lateUntil)) . '.' : '.')
            : 'Late submission access is now disabled for ' . $subject['subject_name'] . '.', $allowLate ? 'info' : 'warning');
        if (!empty($subject['teacher_email']) && filter_var($subject['teacher_email'], FILTER_VALIDATE_EMAIL)) {
            send_system_mail(
                (string) $subject['teacher_email'],
                $title,
                "Hello {$teacher['full_name']},

{$message}

Regards,
" . APP_NAME
            );
        }
        set_flash('success', $allowLate ? 'Late submission override enabled.' : 'Late submission override removed.');
    }
    redirect_to('teacher/subject_view.php?id=' . $subjectId);
}

$subject = fetch_subject_detail($subjectId);
$sectionsStmt = pdo()->prepare('SELECT sec.section_name, sec.status, COUNT(st.id) AS total_students FROM section_subjects ss JOIN sections sec ON sec.id = ss.section_id LEFT JOIN students st ON st.section_id = sec.id WHERE ss.subject_id = ? GROUP BY sec.id ORDER BY sec.section_name');
$sectionsStmt->execute([$subjectId]);
$sections = $sectionsStmt->fetchAll();
$submissionsStmt = pdo()->prepare('SELECT sub.id, st.full_name, sec.section_name, sub.status, sub.grade, sub.submitted_at FROM submissions sub JOIN students st ON st.id = sub.student_id JOIN sections sec ON sec.id = sub.section_id WHERE sub.subject_id = ? ORDER BY sub.submitted_at DESC LIMIT 8');
$submissionsStmt->execute([$subjectId]);
$submissions = $submissionsStmt->fetchAll();
$title = 'Teacher Subject Detail';
$subtitle = 'Section coverage, deadline control, and latest student work for this subject';
require_once __DIR__ . '/../backend/partials/header.php';
?>
<div class="detail-grid">
  <div class="detail-section">
    <div class="card"><div class="split-header"><div><h3 class="section-title"><?= h($subject['subject_name']) ?></h3><div class="muted small"><?= h($subject['subject_code']) ?></div></div><?= status_badge($subject['status']) ?></div><p class="muted"><?= h($subject['description'] ?: 'No description provided.') ?></p><div style="margin-top:12px;"><?= deadline_badge_html($subject) ?></div></div>
    <div class="card">
      <div class="split-header"><div><h3 class="section-title">Deadline control</h3><div class="muted small">Warn students when the deadline is near, then lock submissions automatically at the cutoff.</div></div></div>
      <form method="post" class="form-grid" style="margin-bottom:18px;">
        <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
        <input type="hidden" name="action" value="save_deadline">
        <div><label>Submission deadline</label><input type="datetime-local" name="submission_deadline" value="<?= h(format_deadline_for_input($subject['submission_deadline'] ?? null)) ?>"></div>
        <div><label>Warning hours before deadline</label><input type="number" name="deadline_warning_hours" min="1" value="<?= (int) ($subject['deadline_warning_hours'] ?? 72) ?>"></div>
        <div class="full form-actions"><button class="btn" type="submit">Save deadline settings</button></div>
      </form>
      <form method="post" class="form-grid">
        <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
        <input type="hidden" name="action" value="toggle_late">
        <div><label><input type="checkbox" name="allow_late_submissions" value="1" <?= (int) ($subject['allow_late_submissions'] ?? 0) === 1 ? 'checked' : '' ?>> Allow submissions after deadline</label></div>
        <div><label>Allow until (optional)</label><input type="datetime-local" name="late_submission_until" value="<?= h(format_deadline_for_input($subject['late_submission_until'] ?? null)) ?>"></div>
        <div class="full form-actions"><button class="btn btn-secondary" type="submit">Update late submission access</button></div>
      </form>
    </div>
    <div class="card"><h3 class="section-title">Assigned sections</h3><div class="table-wrap"><table><thead><tr><th>Section</th><th>Status</th><th>Students</th></tr></thead><tbody><?php foreach ($sections as $section): ?><tr><td><?= h($section['section_name']) ?></td><td><?= status_badge($section['status']) ?></td><td><?= (int) $section['total_students'] ?></td></tr><?php endforeach; ?></tbody></table></div></div>
  </div>
  <div class="detail-section">
    <div class="card"><h3 class="section-title">Recent submissions</h3><div class="timeline-list"><?php foreach ($submissions as $submission): ?><div class="timeline-item"><div class="notification-title-row"><strong><?= h($submission['full_name']) ?></strong><?= status_badge($submission['status']) ?></div><p><?= h($submission['section_name']) ?></p><div class="muted small">Grade: <?= h($submission['grade'] ?: '—') ?> · <?= h($submission['submitted_at']) ?></div><div style="margin-top:10px;"><a class="muted-link" href="<?= h(url('teacher/submission_view.php?id=' . (int) $submission['id'])) ?>">Open submission</a></div></div><?php endforeach; ?><?php if (!$submissions): ?><div class="empty-state">No submissions yet.</div><?php endif; ?></div></div>
  </div>
</div>
<?php require_once __DIR__ . '/../backend/partials/footer.php'; ?>
