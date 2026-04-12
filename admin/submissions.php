<?php
if (defined('FILE_ADMIN_SUBMISSIONS_PHP_LOADED')) { return; }
define('FILE_ADMIN_SUBMISSIONS_PHP_LOADED', true);

require_once __DIR__ . '/../backend/config/app.php';
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
    if (!in_array($status, ['pending', 'reviewed', 'graded'], true)) {
        set_flash('error', 'Invalid review status.');
        redirect_to('admin/submissions.php');
    }
    $grade = trim($_POST['grade'] ?? '');
    $feedback = trim($_POST['teacher_feedback'] ?? '');
    $reviewNotes = trim($_POST['review_notes'] ?? '');
    if ($submissionId && (($_POST['action'] ?? 'review') === 'archive')) {
        $submission = fetch_submission_detail($submissionId);
        if ($submission) {
            $pdo->prepare('UPDATE submissions SET status = "archived", updated_at = CURRENT_TIMESTAMP WHERE id = ?')->execute([$submissionId]);
            snapshot_submission_history($submissionId, 'deleted', 'admin', (int) $admin['id'], (string) $admin['full_name']);
            foreach (team_member_rows((int) $submission['team_id']) as $member) {
                create_notification('student', (int) $member['id'], 'Submission archived', 'An administrator removed the submission for ' . $submission['subject_name'] . ' from the active workflow.', 'warning');
            }
        }
        log_action('admin', (int)$admin['id'], 'archive_submission', 'submission', $submissionId, 'Archived submission');
        set_flash('success', 'Submission archived successfully.');
        redirect_to('admin/submissions.php');
    }
    if ($submissionId) {
        $stmt = $pdo->prepare('UPDATE submissions SET status = ?, grade = ?, teacher_feedback = ?, review_notes = ? WHERE id = ?');
        $stmt->execute([$status, $grade ?: null, $feedback ?: null, $reviewNotes ?: null, $submissionId]);
        $submission = fetch_submission_detail($submissionId);
        snapshot_submission_history($submissionId, $status === 'graded' ? 'graded' : 'reviewed', 'admin', (int) $admin['id'], (string) $admin['full_name']);
        if ($submission) {
            foreach (team_member_rows((int) $submission['team_id']) as $member) {
                create_notification('student', (int) $member['id'], 'Submission updated', 'Your submission review status has been updated to ' . $status . ($grade ? ' with grade ' . $grade : '') . '.', $status === 'graded' ? 'success' : 'info');
            }
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
$stats = ['visible' => count($rows), 'pending' => 0, 'reviewed' => 0, 'graded' => 0];
foreach ($rows as $row) { if (isset($stats[$row['status']])) { $stats[$row['status']]++; } }
$title = 'Submissions';
$subtitle = 'Guide content is now moved behind a small help trigger so the registry can breathe.';
require_once __DIR__ . '/../backend/partials/header.php';
?>
<div class="kpi-grid">
  <div class="kpi-card"><span class="label">Visible</span><strong><?= number_format($stats['visible']) ?></strong><span class="muted small">Rows in the current view</span></div>
  <div class="kpi-card"><span class="label">Pending</span><strong><?= number_format($stats['pending']) ?></strong><span class="muted small">Need first review</span></div>
  <div class="kpi-card"><span class="label">Reviewed</span><strong><?= number_format($stats['reviewed']) ?></strong><span class="muted small">Opened but not finalized</span></div>
  <div class="kpi-card"><span class="label">Graded</span><strong><?= number_format($stats['graded']) ?></strong><span class="muted small">Completed grading</span></div>
</div>
<section class="table-card table-bootstrap-shell">
  <div class="module-header">
    <div>
      <div class="eyebrow">Submission Registry</div>
      <h3 class="mb-1">Cross-section Review Table</h3>
      <p class="muted mb-0">Compact supervision view for project links, grades, and archive control.</p>
    </div>
    <div class="module-actions">
      <a href="#" class="help-trigger" data-open-modal="submission-guide"><i class="bi bi-info-circle"></i> Guide</a>
      <span class="badge-soft"><i class="bi bi-table"></i> Filtered rows <?= number_format(count($rows)) ?></span>
    </div>
  </div>
  <div class="table-toolbar">
    <form method="get" class="filters">
      <select class="form-select" name="status"><option value="">All statuses</option><option value="pending" <?= selected($filterStatus,'pending') ?>>Pending</option><option value="reviewed" <?= selected($filterStatus,'reviewed') ?>>Reviewed</option><option value="graded" <?= selected($filterStatus,'graded') ?>>Graded</option></select>
      <select class="form-select" name="section_id"><option value="0">All sections</option><?php foreach ($sections as $section): ?><option value="<?= (int) $section['id'] ?>" <?= $filterSection === (int) $section['id'] ? 'selected' : '' ?>><?= h($section['section_name']) ?></option><?php endforeach; ?></select>
      <select class="form-select" name="subject_id"><option value="0">All subjects</option><?php foreach ($subjects as $subject): ?><option value="<?= (int) $subject['id'] ?>" <?= $filterSubject === (int) $subject['id'] ? 'selected' : '' ?>><?= h($subject['subject_name']) ?></option><?php endforeach; ?></select>
      <div class="form-check"><input class="form-check-input" type="checkbox" name="archived" value="1" id="archived" <?= $includeArchived ? 'checked' : '' ?>><label class="form-check-label" for="archived">Include archived</label></div>
      <button class="btn" type="submit"><i class="bi bi-funnel"></i> Apply Filters</button>
    </form>
  </div>
  <div class="table-responsive">
    <table class="table table-hover align-middle">
      <thead><tr><th>Student</th><th>Subject</th><th>Project</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
      <tbody>
      <?php foreach ($rows as $row): ?>
        <tr>
          <td><strong><?= h($row['full_name']) ?></strong><div class="muted small"><?= h($row['student_code']) ?> · <?= h($row['section_name']) ?></div><?php if (!empty($row['team_name'])): ?><div class="muted small"><?= h($row['team_name']) ?></div><?php endif; ?></td>
          <td><strong><?= h($row['subject_name']) ?></strong><div class="muted small">Submitted <?= h(date('M j, Y', strtotime((string) $row['submitted_at']))) ?></div></td>
          <td><strong><?= h($row['assigned_system']) ?></strong><div class="muted small"><?= h($row['company_name'] ?: 'No company label') ?></div><div class="mt-2 d-flex gap-2 flex-wrap"><?php $projectHref = safe_public_url($row['project_url'] ?? null); $videoHref = safe_public_url($row['video_url'] ?? null); ?><?php if ($projectHref): ?><a class="icon-action" href="<?= h($projectHref) ?>" target="_blank" rel="noopener" title="open project" aria-label="open project"><i class="bi bi-link-45deg"></i></a><?php endif; ?><?php if ($videoHref): ?><a class="icon-action" href="<?= h($videoHref) ?>" target="_blank" rel="noopener" title="open video" aria-label="open video"><i class="bi bi-play-circle"></i></a><?php endif; ?></div></td>
          <td><?= status_badge($row['status']) ?><div class="muted small mt-1">Grade <?= h($row['grade'] !== null && $row['grade'] !== '' ? $row['grade'] : '—') ?></div></td>
          <td class="text-end">
            <div class="icon-action-group justify-content-end">
              <a class="icon-action" href="<?= h(url('admin/submission_preview.php?id=' . (int) $row['id'])) ?>" data-ajax-modal="1" data-modal-title="Submission overview" title="Open submission"><i class="bi bi-eye"></i></a>
              <button class="icon-action" type="button" data-open-modal="review-<?= (int) $row['id'] ?>" title="Quick review"><i class="bi bi-pencil-square"></i></button>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$rows): ?><tr><td colspan="5" class="table-empty">No submissions matched your current filters.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</section>

<div class="modal-backdrop" data-modal="submission-guide" aria-hidden="true">
  <div class="modal-overlay"></div>
  <div class="modal-container">
    <div class="modal-card" role="dialog" aria-modal="true">
      <div class="modal-head"><div><span class="badge-soft"><i class="bi bi-info-circle"></i> Submission Guide</span><h3>How to use this registry</h3></div><button type="button" class="icon-btn modal-close" data-close-modal aria-label="Close">✕</button></div>
      <div class="modal-body"><div class="stack"><div class="callout"><strong>Filter by accountability</strong><div class="muted small">Use subject, section, and status before opening quick review.</div></div><div class="callout"><strong>Archive historical records only</strong><div class="muted small">Keep active submissions visible until grading is completed.</div></div><div class="callout"><strong>Use quick review for small changes</strong><div class="muted small">Open the full page only when you need the richer submission context.</div></div></div></div>
      <div class="modal-footer"><div class="modal-actions"><button class="btn btn-outline" type="button" data-close-modal>Close</button></div></div>
    </div>
  </div>
</div>
<?php foreach ($rows as $row): ?>
<div class="modal-backdrop" data-modal="review-<?= (int) $row['id'] ?>" aria-hidden="true">
  <div class="modal-overlay"></div>
  <div class="modal-container">
  <div class="modal-card modal-lg" role="dialog" aria-modal="true">
    <div class="modal-head"><div><span class="badge-soft"><i class="bi bi-pencil-square"></i> Quick Review</span><h3><?= h($row['assigned_system']) ?></h3><p class="muted mb-0"><?= h($row['full_name']) ?> · <?= h($row['subject_name']) ?></p></div><button type="button" class="icon-btn modal-close" data-close-modal aria-label="Close">✕</button></div>
    <form id="quick-review-form-<?= (int) $row['id'] ?>" method="post" class="form-modal-grid modal-body">
      <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
      <input type="hidden" name="submission_id" value="<?= (int) $row['id'] ?>">
      <div><label>Status</label><select class="form-select" name="status"><?php foreach (['pending','reviewed','graded'] as $s): ?><option value="<?= h($s) ?>" <?= $row['status']===$s?'selected':'' ?>><?= h(ucfirst($s)) ?></option><?php endforeach; ?></select></div>
      <div><label>Grade</label><input class="form-control" name="grade" value="<?= h((string)$row['grade']) ?>" placeholder="Grade"></div>
      <div class="full"><label>Feedback</label><textarea class="form-control" name="teacher_feedback" rows="4" placeholder="Feedback for the student"><?= h((string)$row['teacher_feedback']) ?></textarea></div>
      <div class="full"><label>Internal notes</label><textarea class="form-control" name="review_notes" rows="3" placeholder="Internal review notes"><?= h((string)$row['review_notes']) ?></textarea></div>
    </form>
    <div class="modal-footer"><div class="modal-actions"><button class="btn btn-outline" type="button" data-close-modal>Cancel</button><button class="btn btn-danger" type="submit" form="quick-review-form-<?= (int) $row['id'] ?>" name="action" value="archive" data-confirm-title="Archive submission?" data-confirm-message="This submission will be removed from the active registry."><i class="bi bi-archive"></i> Archive</button><button class="btn" type="submit" form="quick-review-form-<?= (int) $row['id'] ?>"><i class="bi bi-save"></i> Save Review</button></div></div>
  </div>
  </div>
</div>
<?php endforeach; ?>
<?php require_once __DIR__ . '/../backend/partials/footer.php'; ?>
