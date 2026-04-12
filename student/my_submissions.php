<?php
if (defined('FILE_STUDENT_MY_SUBMISSIONS_PHP_LOADED')) { return; }
define('FILE_STUDENT_MY_SUBMISSIONS_PHP_LOADED', true);
require_once __DIR__ . '/../backend/config/app.php';
require_role('student');
$student = current_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    $submissionId = (int) ($_POST['submission_id'] ?? 0);

    if ($action === 'delete' && $submissionId > 0) {
        $submission = student_submission_row_for_manage((int) $student['id'], $submissionId);
        if (!$submission) {
            set_flash('error', 'Submission not found.');
            redirect_to('student/my_submissions.php');
        }
        if (($submission['member_role'] ?? '') !== 'leader') {
            set_flash('error', 'Only the team leader can delete a submission.');
            redirect_to('student/my_submissions.php');
        }
        if (in_array($submission['status'], ['graded', 'archived'], true)) {
            set_flash('error', 'Graded or archived submissions can no longer be deleted.');
            redirect_to('student/my_submissions.php');
        }

        $memberRows = team_member_rows((int) $submission['team_id']);
        snapshot_submission_history($submissionId, 'deleted', 'student', (int) $student['id'], (string) $student['full_name']);
        archive_submission_record($submissionId);
        foreach ($memberRows as $member) {
            create_notification('student', (int) $member['id'], 'Submission deleted', 'The team submission for ' . $submission['subject_name'] . ' was deleted by the team leader.', 'warning');
        }
        create_notification('teacher', (int) $submission['teacher_id'], 'Submission removed', 'A student team deleted the submission for ' . $submission['subject_name'] . '.', 'warning');
        set_flash('success', 'Submission removed from the active workflow. Its history was preserved.');
        redirect_to('student/my_submissions.php');
    }
}

$rows = student_team_submissions((int) $student['id'], true);
$historyMap = fetch_submission_history_map(array_column($rows, 'id'));
$gradedCount = 0;
$editableCount = 0;
$totalGrade = 0.0;
$numericGradeCount = 0;
$historyVersionCount = 0;
foreach ($rows as $row) {
    if (($row['status'] ?? '') === 'graded') {
        $gradedCount++;
    }
    if (($row['member_role'] ?? '') === 'leader' && !in_array(($row['status'] ?? ''), ['graded', 'archived'], true)) {
        $editableCount++;
    }
    $gradeValue = trim((string) ($row['grade'] ?? ''));
    if ($gradeValue !== '' && is_numeric($gradeValue)) {
        $totalGrade += (float) $gradeValue;
        $numericGradeCount++;
    }
    $historyVersionCount += count($historyMap[(int) $row['id']] ?? []);
}
$averageGrade = $numericGradeCount > 0 ? number_format($totalGrade / $numericGradeCount, 2) : '—';

$title = 'My Submission History';
$subtitle = 'Live team submission hub with current status, grades, and version history';
require_once __DIR__ . '/../backend/partials/header.php';
?>
<section class="workspace-shell student-history-shell ui-section">
  <div class="workspace-head student-history-head">
    <div>
      <div class="eyebrow">Student portal</div>
      <h2>Submission history</h2>
      <p class="muted">This is now a real history view. Each submission shows the current state on top and every recorded version underneath, including teacher reviews, grades, and student edits.</p>
    </div>
    <div class="student-history-actions ui-action-row">
      <a class="btn ui-btn ui-btn--primary" href="<?= h(url('student/submit.php')) ?>">New submission</a>
      <a class="btn btn-secondary ui-btn ui-btn--secondary" href="<?= h(url('student/export_my_submissions.php?format=xlsx')) ?>">Export Excel</a>
      <a class="btn btn-outline ui-btn ui-btn--ghost" href="<?= h(url('student/export_my_submissions.php?format=csv')) ?>">Export CSV</a>
    </div>
  </div>

  <div class="workspace-stat-grid compact-grid student-history-kpis ui-stat-grid">
    <div class="segment ui-stat-card"><span class="muted small">Active submissions</span><strong><?= count($rows) ?></strong></div>
    <div class="segment ui-stat-card"><span class="muted small">Graded</span><strong><?= $gradedCount ?></strong></div>
    <div class="segment ui-stat-card"><span class="muted small">Average grade</span><strong><?= h($averageGrade) ?></strong></div>
    <div class="segment ui-stat-card"><span class="muted small">History versions</span><strong><?= $historyVersionCount ?></strong></div>
  </div>

  <?php foreach ($rows as $row): ?>
    <?php
      $canManage = ($row['member_role'] ?? '') === 'leader' && !in_array(($row['status'] ?? ''), ['graded', 'archived'], true);
      $historyRows = $historyMap[(int) $row['id']] ?? [];
      $latestHistory = $historyRows[0] ?? null;
    ?>
    <article class="card submission-history-record ui-panel-card">
      <div class="split-header submission-history-summary">
        <div>
          <div class="eyebrow"><?= h($row['subject_code']) ?> · <?= h($row['activity_title'] ?? $row['title'] ?? 'General submission') ?> · <?= h($row['team_name']) ?></div>
          <h3 class="section-title"><?= h($row['assigned_system'] ?: 'Untitled Project') ?></h3>
          <div class="muted small"><?= h($row['subject_name']) ?> · <?= h($row['activity_title'] ?? $row['title'] ?? 'General submission') ?> · Attempt <?= (int) ($row['attempt_no'] ?? 1) ?> · Leader: <?= h($row['leader_name']) ?> · Your role: <?= h(ucfirst((string) $row['member_role'])) ?></div>
        </div>
        <div class="submission-history-badges">
          <?= status_badge((string) $row['status']) ?>
          <?php if ($latestHistory): ?><span class="pill">v<?= (int) $latestHistory['version_no'] ?></span><?php endif; ?>
        </div>
      </div>

      <div class="submission-meta-grid">
        <div class="metric-chip"><span>Grade</span><strong><?= h($row['grade'] ?: '—') ?></strong></div>
        <div class="metric-chip"><span>Submitted</span><strong><?= h(date('M d, Y', strtotime((string) $row['submitted_at']))) ?></strong></div>
        <div class="metric-chip"><span>Last updated</span><strong><?= h(date('M d, Y g:i A', strtotime((string) $row['updated_at']))) ?></strong></div>
        <div class="metric-chip"><span>History entries</span><strong><?= count($historyRows) ?></strong></div>
      </div>

      <div class="history-feedback-panel">
        <div>
          <div class="muted small">Teacher feedback</div>
          <div class="history-feedback-copy"><?= nl2br(h($row['teacher_feedback'] ?: 'No feedback yet. Once your teacher reviews this project, feedback will appear here.')) ?></div>
        </div>
        <div class="submission-primary-actions">
          <?php $projectHref = safe_public_url($row['project_url'] ?? null); $videoHref = safe_public_url($row['video_url'] ?? null); ?>
           <?php if ($projectHref): ?><a class="btn btn-secondary ui-btn ui-btn--secondary" target="_blank" rel="noopener" href="<?= h($projectHref) ?>">Open project</a><?php endif; ?>
           <?php if ($videoHref): ?><a class="btn btn-outline ui-btn ui-btn--ghost" target="_blank" rel="noopener" href="<?= h($videoHref) ?>">Open video</a><?php endif; ?>
           <?php if (!empty($row['attachment_path'])): ?><a class="btn btn-ghost ui-btn ui-btn--ghost" target="_blank" href="<?= h(url($row['attachment_path'])) ?>">Attachment</a><?php endif; ?>
           <?php if ($canManage): ?>
             <a class="btn ui-btn ui-btn--primary" href="<?= h(url('student/submission_edit.php?id=' . (int) $row['id'])) ?>">Edit latest version</a>
             <form method="post" onsubmit="return confirm('Delete this submission? Its history log will stay available for audit.');" style="display:inline;">
               <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
               <input type="hidden" name="action" value="delete">
               <input type="hidden" name="submission_id" value="<?= (int) $row['id'] ?>">
               <button class="btn btn-danger ui-btn ui-btn--danger" type="submit">Delete</button>
             </form>
          <?php else: ?>
            <span class="muted small"><?= ($row['status'] ?? '') === 'archived' ? 'Removed from active workflow' : 'Locked after grading' ?></span>
          <?php endif; ?>
        </div>
      </div>

      <section class="history-ui-block">
        <div class="history-ui-head">
          <div>
            <div class="eyebrow">Submission history</div>
            <h4>Version log</h4>
            <p class="muted">Every important change is captured here, including student edits, teacher review actions, grading, and archive events.</p>
          </div>
          <button
            class="btn btn-secondary history-toggle-btn ui-btn ui-btn--secondary"
            type="button"
            data-history-toggle
            data-target="history-panel-<?= (int) $row['id'] ?>"
            aria-expanded="true"
          >
            Hide history
          </button>
        </div>

        <div class="history-version-strip">
          <?php foreach (array_slice($historyRows, 0, 4) as $history): ?>
            <article class="history-version-card">
              <div class="history-version-top">
                <strong>v<?= (int) $history['version_no'] ?></strong>
                <?= status_badge((string) $history['status']) ?>
              </div>
              <div class="muted small"><?= h(date('M d, Y g:i A', strtotime((string) $history['created_at']))) ?></div>
              <div><?= action_badge((string) $history['action_type']) ?></div>
              <div class="muted small">By <?= h($history['actor_name'] ?: ucfirst((string) $history['actor_role'])) ?></div>
            </article>
          <?php endforeach; ?>
          <?php if (!$historyRows): ?>
            <div class="history-version-card empty">No history versions have been captured yet for this submission.</div>
          <?php endif; ?>
        </div>

        <div class="history-panel is-open" id="history-panel-<?= (int) $row['id'] ?>">
          <div class="history-timeline">
            <?php foreach ($historyRows as $history): ?>
              <article class="history-timeline-item">
                <div class="history-timeline-marker">v<?= (int) $history['version_no'] ?></div>
                <div class="history-timeline-body">
                  <div class="history-timeline-head">
                    <div class="history-timeline-meta">
                      <?= action_badge((string) $history['action_type']) ?>
                      <?= status_badge((string) $history['status']) ?>
                    </div>
                    <div class="muted small"><?= h(date('M d, Y g:i A', strtotime((string) $history['created_at']))) ?></div>
                  </div>
                  <div class="history-timeline-grid">
                    <div><span class="muted small">Actor</span><strong><?= h($history['actor_name'] ?: ucfirst((string) $history['actor_role'])) ?></strong></div>
                    <div><span class="muted small">Grade snapshot</span><strong><?= h($history['grade'] ?: '—') ?></strong></div>
                    <div><span class="muted small">Project link</span><strong><?= !empty($history['project_url']) ? 'Captured' : '—' ?></strong></div>
                    <div><span class="muted small">Video link</span><strong><?= !empty($history['video_url']) ? 'Captured' : '—' ?></strong></div>
                  </div>
                  <div class="history-feedback-snapshot">
                    <span class="muted small">Feedback snapshot</span>
                    <div><?= nl2br(h($history['teacher_feedback'] ?: 'No feedback in this version.')) ?></div>
                  </div>
                </div>
              </article>
            <?php endforeach; ?>
          </div>

          <div class="table-wrap history-table-wrap">
            <table class="table-redesign student-history-table compact-table">
              <thead>
                <tr>
                  <th>Version</th>
                  <th>Action</th>
                  <th>Actor</th>
                  <th>Status</th>
                  <th>Grade</th>
                  <th>Feedback snapshot</th>
                  <th>Captured</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($historyRows as $history): ?>
                  <tr>
                    <td data-label="Version"><strong>v<?= (int) $history['version_no'] ?></strong></td>
                    <td data-label="Action"><?= action_badge((string) $history['action_type']) ?></td>
                    <td data-label="Actor"><?= h($history['actor_name'] ?: ucfirst((string) $history['actor_role'])) ?></td>
                    <td data-label="Status"><?= status_badge((string) $history['status']) ?></td>
                    <td data-label="Grade"><strong><?= h($history['grade'] ?: '—') ?></strong></td>
                    <td data-label="Feedback snapshot"><div class="line-clamp-sm"><?= h($history['teacher_feedback'] ?: 'No feedback in this version.') ?></div></td>
                    <td data-label="Captured"><strong><?= h(date('M d, Y', strtotime((string) $history['created_at']))) ?></strong><div class="muted small"><?= h(date('g:i A', strtotime((string) $history['created_at']))) ?></div></td>
                  </tr>
                <?php endforeach; ?>
                <?php if (!$historyRows): ?>
                  <tr><td colspan="7" class="empty-state">No history versions have been captured yet for this submission.</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </section>
    </article>
  <?php endforeach; ?>

  <?php if (!$rows): ?>
    <div class="card empty-state ui-empty-state" style="padding:32px;">No team submission is visible yet. Once a leader submits a project, this page will show the current record and its full version history.</div>
  <?php endif; ?>
</section>
<?php require_once __DIR__ . '/../backend/partials/footer.php'; ?>
