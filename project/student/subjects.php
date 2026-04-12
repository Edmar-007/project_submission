<?php
require_once __DIR__ . '/../backend/helpers/query.php';
require_role('student');
$student = current_user();
$subjects = student_subjects((int) $student['section_id']);
$activities = student_visible_activities((int) $student['id'], (int) $student['section_id']);
$activityMap = [];
foreach ($activities as $activity) {
    $activityMap[(int) $activity['subject_id']][] = $activity;
}
$title = 'My Subjects';
$subtitle = 'Browse subject containers and the submission activities published inside them';
require_once __DIR__ . '/../backend/partials/header.php';
?>
<section class="workspace-shell student-history-shell">
  <div class="workspace-head">
    <div>
      <div class="eyebrow">Student subjects</div>
      <h2>Assigned subject workspace</h2>
      <p class="muted">Subjects are now containers. Your teacher can publish multiple submission activities inside each subject with different sections, deadlines, and restrictions.</p>
    </div>
    <div class="student-history-actions"><a class="btn" href="<?= h(url('student/submit.php')) ?>">Open submit flow</a></div>
  </div>

  <div class="review-card-grid">
    <?php foreach ($subjects as $subject): ?>
      <?php $subjectActivities = $activityMap[(int) $subject['id']] ?? []; ?>
      <article class="card review-queue-card">
        <div class="split-header"><div><h3 class="section-title"><?= h($subject['subject_name']) ?></h3><div class="muted small"><?= h($subject['subject_code']) ?> · <?= h($subject['teacher_name']) ?></div></div><?= status_badge($subject['status']) ?></div>
        <p class="muted"><?= h($subject['description'] ?: 'Assigned through your section.') ?></p>
        <div class="metric-chip" style="margin-bottom:12px;"><span>Activities</span><strong><?= count($subjectActivities) ?></strong></div>
        <div class="timeline-list">
          <?php foreach (array_slice($subjectActivities, 0, 4) as $activity): ?>
            <div class="timeline-item">
              <strong><?= h($activity['title']) ?></strong>
              <p><?= h($activity['activity_window']['label'] ?? 'Open') ?></p>
              <div class="muted small"><?= h(ucfirst((string) $activity['submission_mode'])) ?> submission</div>
            </div>
          <?php endforeach; ?>
          <?php if (!$subjectActivities): ?><div class="empty-state">No activity is published for this subject yet.</div><?php endif; ?>
        </div>
        <div class="form-actions" style="margin-top:12px;">
          <a class="btn btn-secondary" href="<?= h(url('student/subject_preview.php?subject_id=' . (int) $subject['id'])) ?>" data-ajax-modal="1" data-modal-title="Subject overview">Overview</a>
          <?php if ($subjectActivities): ?><a class="btn" href="<?= h(url('student/submit.php?subject_id=' . (int) $subject['id'])) ?>">Choose activity</a><?php endif; ?>
        </div>
      </article>
    <?php endforeach; ?>
    <?php if (!$subjects): ?><div class="card empty-state">No active subjects are assigned to your section yet.</div><?php endif; ?>
  </div>
</section>
<?php require_once __DIR__ . '/../backend/partials/footer.php'; ?>
