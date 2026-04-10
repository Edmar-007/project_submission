<?php
require_once __DIR__ . '/../backend/helpers/query.php';
require_role('student');
$student = current_user();
$subjects = student_subjects((int)$student['section_id']);
$submissions = student_team_submissions((int) $student['id']);
$statusMap = [];
foreach ($submissions as $submission) { $statusMap[(int) $submission['subject_id']] = $submission; }
$statusFilter = trim($_GET['state'] ?? '');
$search = trim($_GET['search'] ?? '');
$title = 'My Subjects';
$subtitle = 'Student tables now use the same cleaner structure as admin and teacher pages.';
require_once __DIR__ . '/../backend/partials/header.php';
?>
<section class="table-card table-bootstrap-shell student-table-card">
  <div class="module-header">
    <div>
      <div class="eyebrow">Student Subjects</div>
      <h3 class="mb-1">Assigned Subject Table</h3>
      <p class="muted mb-0">Browse subjects, see the deadline state, and jump straight into the correct submission flow.</p>
    </div>
    <div class="module-actions"><span class="badge-soft"><i class="bi bi-journal-text"></i> <?= count($subjects) ?> subjects</span></div>
  </div>
  <div class="table-toolbar">
    <form method="get" class="filters">
      <input class="form-control" type="search" name="search" value="<?= h($search) ?>" placeholder="Search subject or teacher">
      <select class="form-select" name="state"><option value="">All status</option><option value="ready" <?= selected($statusFilter, 'ready') ?>>Ready to submit</option><option value="pending" <?= selected($statusFilter, 'pending') ?>>Pending review</option><option value="reviewed" <?= selected($statusFilter, 'reviewed') ?>>Reviewed</option><option value="graded" <?= selected($statusFilter, 'graded') ?>>Graded</option></select>
      <button class="btn" type="submit"><i class="bi bi-funnel"></i> Filter</button>
    </form>
  </div>
  <div class="table-responsive">
    <table class="table table-hover align-middle">
      <thead><tr><th>Subject</th><th>Teacher</th><th>Deadline</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
      <tbody>
      <?php foreach ($subjects as $subject): ?>
        <?php $submission = $statusMap[(int) $subject['id']] ?? null; $subjectStatus = $submission['status'] ?? 'ready'; $searchBlob = strtolower($subject['subject_name'] . ' ' . $subject['subject_code'] . ' ' . $subject['teacher_name']); if ($search && !str_contains($searchBlob, strtolower($search))) continue; if ($statusFilter !== '' && $subjectStatus !== $statusFilter) continue; ?>
        <tr>
          <td><strong><?= h($subject['subject_name']) ?></strong><div class="muted small"><?= h($subject['subject_code']) ?></div><div class="muted small"><?= h($subject['description'] ?: 'Assigned through your section.') ?></div></td>
          <td><?= h($subject['teacher_name']) ?></td>
          <td><?= deadline_badge_html($subject) ?><div class="muted small mt-1"><?= h(($subject['deadline_window']['label'] ?? 'No deadline set')) ?></div></td>
          <td><?= status_badge($subjectStatus === 'ready' ? 'active' : $subjectStatus) ?></td>
          <td class="text-end"><div class="icon-action-group justify-content-end"><?php if (!empty($subject['submission_locked'])): ?><span class="icon-action" title="Deadline reached"><i class="bi bi-lock"></i></span><?php else: ?><a class="icon-action" href="<?= h(url('student/submit.php?subject_id=' . (int)$subject['id'])) ?>" title="Submit for this subject"><i class="bi bi-upload"></i></a><?php endif; ?><a class="icon-action" href="<?= h(url('student/my_submissions.php')) ?>" title="Open team project"><i class="bi bi-eye"></i></a></div></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$subjects): ?><tr><td colspan="5" class="table-empty">No active subjects are assigned to your section yet.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</section>
<?php require_once __DIR__ . '/../backend/partials/footer.php'; ?>
