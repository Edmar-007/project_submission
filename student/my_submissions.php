<?php
require_once __DIR__ . '/../backend/helpers/query.php';
require_role('student');
$student = current_user();
$rows = student_team_submissions((int) $student['id']);
$title = 'My Team Projects';
$subtitle = 'Shared project records for the whole team, now shown in a cleaner student table.';
require_once __DIR__ . '/../backend/partials/header.php';
?>
<section class="table-card table-bootstrap-shell student-table-card">
  <div class="module-header">
    <div><div class="eyebrow">Shared Workspace</div><h3 class="mb-1">My Team Project Table</h3><p class="muted mb-0">Every member sees the same status, grade, links, and teacher feedback here.</p></div>
    <div class="module-actions"><span class="badge-soft"><i class="bi bi-people"></i> <?= count($rows) ?> shared project<?= count($rows) === 1 ? '' : 's' ?></span></div>
  </div>
  <div class="table-responsive">
    <table class="table table-hover align-middle">
      <thead><tr><th>Subject</th><th>Team</th><th>Status</th><th>Grade</th><th>Feedback</th><th class="text-end">Links</th></tr></thead>
      <tbody>
      <?php foreach ($rows as $row): ?>
        <tr>
          <td><strong><?= h($row['subject_name']) ?></strong><div class="muted small"><?= h($row['subject_code']) ?></div><div class="muted small">Submitted <?= h($row['submitted_at']) ?></div></td>
          <td><strong><?= h($row['team_name']) ?></strong><div class="muted small">Leader: <?= h($row['leader_name']) ?></div><div class="muted small">Role: <?= h(ucfirst($row['member_role'])) ?></div></td>
          <td><?= status_badge($row['status']) ?></td>
          <td><strong><?= h($row['grade'] ?: '—') ?></strong></td>
          <td><div class="muted small" style="max-width:280px;"><?= h($row['teacher_feedback'] ?: 'No feedback yet.') ?></div></td>
          <td class="text-end"><div class="icon-action-group justify-content-end"><?php if (!empty($row['project_url'])): ?><a class="icon-action" target="_blank" href="<?= h($row['project_url']) ?>" title="Open project"><i class="bi bi-box-arrow-up-right"></i></a><?php endif; ?><?php if (!empty($row['video_url'])): ?><a class="icon-action" target="_blank" href="<?= h($row['video_url']) ?>" title="Open video"><i class="bi bi-play-btn"></i></a><?php endif; ?><?php if (!empty($row['attachment_path'])): ?><a class="icon-action" target="_blank" href="<?= url_for($row['attachment_path']) ?>" title="Open attachment"><i class="bi bi-paperclip"></i></a><?php endif; ?></div></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$rows): ?><tr><td colspan="6" class="table-empty">No team project is visible yet. Once your leader submits, the shared project will appear here.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</section>
<?php require_once __DIR__ . '/../backend/partials/footer.php'; ?>
