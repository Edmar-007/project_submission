<?php
require_once __DIR__ . '/../backend/helpers/query.php';
require_role('student');
$student = current_user();
$rows = student_team_submissions((int) $student['id']);
$title = 'My Team Projects';
$subtitle = 'View the shared project your leader submitted, including links, grade, and feedback';
require_once __DIR__ . '/../backend/partials/header.php';
?>
<section class="student-page-shell">
  <div class="student-page-card">
    <div class="student-page-toolbar student-simple-toolbar">
      <div>
        <div class="eyebrow">Shared Workspace</div>
        <h2>My Team Projects</h2>
        <p>Every member added by student ID sees the same project links, status, grade, and teacher feedback here.</p>
      </div>
      <div class="student-summary-pills">
        <span class="student-soft-badge info"><?= count($rows) ?> shared project<?= count($rows) === 1 ? '' : 's' ?></span>
      </div>
    </div>

    <div class="student-project-card-grid">
      <?php foreach ($rows as $row): ?>
        <article class="student-project-card-modern">
          <div class="student-project-card-head">
            <div>
              <h3><?= h($row['subject_name']) ?></h3>
              <div class="student-project-submeta"><?= h($row['subject_code']) ?> · <?= h($row['team_name']) ?></div>
            </div>
            <?= status_badge($row['status']) ?>
          </div>

          <div class="student-project-facts">
            <div><span>Leader</span><strong><?= h($row['leader_name']) ?></strong></div>
            <div><span>Your role</span><strong><?= h(ucfirst($row['member_role'])) ?></strong></div>
            <div><span>Project</span><strong><?= h($row['assigned_system'] ?: '—') ?></strong></div>
            <div><span>Grade</span><strong><?= h($row['grade'] ?: '—') ?></strong></div>
            <div><span>Submitted</span><strong><?= h($row['submitted_at']) ?></strong></div>
            <div><span>Demo access</span><strong><?= !empty($row['admin_username']) ? 'Available' : 'Not required' ?></strong></div>
          </div>

          <div class="student-project-feedback-box">
            <strong>Team members</strong>
            <p><?= h($row['team_members_list'] ?: $row['leader_name']) ?></p>
            <strong>Teacher feedback</strong>
            <p><?= h($row['teacher_feedback'] ?: 'No feedback yet. Once the assigned teacher reviews this project, their notes will appear here.') ?></p>
          </div>

          <div class="student-project-linkrow">
            <?php if (!empty($row['project_url'])): ?><a class="btn btn-secondary" target="_blank" href="<?= h($row['project_url']) ?>">Project</a><?php endif; ?>
            <?php if (!empty($row['video_url'])): ?><a class="btn btn-ghost" target="_blank" href="<?= h($row['video_url']) ?>">Video</a><?php endif; ?>
            <?php if (!empty($row['attachment_path'])): ?><a class="btn btn-ghost" target="_blank" href="<?= url_for($row['attachment_path']) ?>">Attachment</a><?php endif; ?>
          </div>
        </article>
      <?php endforeach; ?>

      <?php if (!$rows): ?>
        <div class="card empty-state">No team project is visible yet. Once your leader submits, every member added by student ID will see the same project here.</div>
      <?php endif; ?>
    </div>
  </div>
</section>
<?php require_once __DIR__ . '/../backend/partials/footer.php'; ?>
