<?php
require_once __DIR__ . '/../backend/helpers/query.php';
require_role('student');
$student = current_user();
$subjects = student_subjects((int)$student['section_id']);
$submissions = student_team_submissions((int) $student['id']);
$statusMap = [];
foreach ($submissions as $submission) {
    $statusMap[(int) $submission['subject_id']] = $submission;
}
$statusFilter = trim($_GET['state'] ?? '');
$search = trim($_GET['search'] ?? '');
$title = 'My Subjects';
$subtitle = 'Subjects assigned automatically through your section';
require_once __DIR__ . '/../backend/partials/header.php';
?>
<section class="student-page-shell">
  <div class="student-page-card">
    <div class="student-page-toolbar">
      <div>
        <div class="eyebrow">Student Subjects</div>
        <h2>My Subjects</h2>
        <p>Review your assigned subjects, track team submission progress, and jump directly into the correct project form.</p>
      </div>
      <form method="get" class="student-toolbar-filters subject-toolbar-filters" data-student-subject-filters>
        <label class="student-search-box">
          <span>🔎</span>
          <input type="search" name="search" value="<?= h($search) ?>" placeholder="Search subject or teacher" data-student-search>
        </label>
        <select name="state" data-student-state>
          <option value="">All status</option>
          <option value="ready" <?= selected($statusFilter, 'ready') ?>>Ready to submit</option>
          <option value="pending" <?= selected($statusFilter, 'pending') ?>>Pending review</option>
          <option value="reviewed" <?= selected($statusFilter, 'reviewed') ?>>Reviewed</option>
          <option value="graded" <?= selected($statusFilter, 'graded') ?>>Graded</option>
        </select>
        <button class="btn btn-secondary" type="submit">Filter</button>
      </form>
    </div>

    <div class="student-subject-grid" data-student-subject-grid>
      <?php foreach ($subjects as $subject): ?>
        <?php
          $submission = $statusMap[(int) $subject['id']] ?? null;
          $subjectStatus = $submission['status'] ?? 'ready';
          $progress = 18;
          if ($subjectStatus === 'pending') $progress = 58;
          if ($subjectStatus === 'reviewed') $progress = 82;
          if ($subjectStatus === 'graded') $progress = 100;
          $searchBlob = strtolower($subject['subject_name'] . ' ' . $subject['subject_code'] . ' ' . $subject['teacher_name']);
        ?>
        <article class="student-subject-card" data-status="<?= h($subjectStatus) ?>" data-search="<?= h($searchBlob) ?>">
          <div class="student-subject-head">
            <div>
              <h3><?= h($subject['subject_name']) ?></h3>
              <div class="student-subject-meta"><?= h($subject['subject_code']) ?> · <?= h($subject['teacher_name']) ?></div>
            </div>
            <?= status_badge($subjectStatus === 'ready' ? 'active' : $subjectStatus) ?>
          </div>

          <div class="student-progress-wrap">
            <div class="student-progress-bar"><span style="width: <?= (int) $progress ?>%"></span></div>
            <div class="student-progress-copy">
              <strong><?= $progress ?>%</strong>
              <span>
                <?php if ($subjectStatus === 'ready'): ?>Start your team submission<?php elseif ($subjectStatus === 'pending'): ?>Waiting for teacher review<?php elseif ($subjectStatus === 'reviewed'): ?>Review posted · update if needed<?php else: ?>Grade available<?php endif; ?>
              </span>
            </div>
          </div>

          <p class="student-subject-copy"><?= h($subject['description'] ?: 'This subject is assigned through your current section and is ready for your team project workflow.') ?></p>

          <div class="student-subject-footer">
            <a class="btn btn-secondary" href="<?= h(url('student/submit.php?subject_id=' . (int)$subject['id'])) ?>">Submit for this subject</a>
            <a class="btn btn-ghost" href="<?= h(url('student/my_submissions.php')) ?>">Open team project</a>
          </div>
        </article>
      <?php endforeach; ?>
      <?php if (!$subjects): ?>
        <div class="card empty-state">No active subjects are assigned to your section yet.</div>
      <?php endif; ?>
    </div>
  </div>
</section>
<?php require_once __DIR__ . '/../backend/partials/footer.php'; ?>
