<?php
require_once __DIR__ . '/../backend/helpers/query.php';
require_role('student');
$student = current_user();
$pdo = pdo();
$stmt = $pdo->prepare('SELECT sec.section_name, sec.status AS section_status, sy.label AS school_year FROM students st JOIN sections sec ON sec.id = st.section_id JOIN school_years sy ON sy.id = sec.school_year_id WHERE st.id = ?');
$stmt->execute([$student['id']]);
$profile = $stmt->fetch();
$subjects = student_subjects((int) $student['section_id']);
$sharedSubmissions = student_team_submissions((int) $student['id']);
$totalSubmissions = count($sharedSubmissions);
$stmt = $pdo->prepare('SELECT COUNT(*) FROM reactivation_requests WHERE student_id = ? AND status = "pending"');
$stmt->execute([$student['id']]);
$pendingRequests = (int) $stmt->fetchColumn();
$recentSubmissions = array_slice($sharedSubmissions, 0, 3);
$viewOnly = $student['account_status'] !== 'active' || (int) $student['can_submit'] !== 1 || (($profile['section_status'] ?? '') !== 'active');
$pendingCount = 0;
$reviewedCount = 0;
$gradedCount = 0;
$feedbackCount = 0;
foreach ($sharedSubmissions as $submission) {
    if (($submission['status'] ?? '') === 'pending') { $pendingCount++; }
    if (($submission['status'] ?? '') === 'reviewed') { $reviewedCount++; }
    if (($submission['status'] ?? '') === 'graded') { $gradedCount++; }
    if (!empty($submission['teacher_feedback']) || !empty($submission['review_notes'])) { $feedbackCount++; }
}
$unreadNotifications = count_unread_notifications('student', (int) $student['id']);
$title = 'Student Dashboard';
$subtitle = 'Safe, shared access for team projects, grades, notifications, and submission tracking';
require_once __DIR__ . '/../backend/partials/header.php';
?>
<?php if ($viewOnly): ?>
  <div class="flash warning">You are currently in <strong>view-only mode</strong>. You can still log in and view your records, but new submissions are disabled until an administrator reactivates your access.</div>
<?php endif; ?>

<section class="student-workspace-shell">
  <div class="student-workspace-card">
    <div class="student-workspace-head">
      <div>
        <div class="eyebrow">Student Workspace</div>
        <h2>Welcome, <?= h(strtoupper($student['full_name'])) ?>!</h2>
        <p>Your student-only dashboard gives you shared team access, progress visibility, and quick review of your active project records.</p>
      </div>
      <div class="student-hero-actions">
        <a class="btn" href="<?= url_for('student/my_submissions.php') ?>">Open My Team Projects</a>
        <a class="btn btn-secondary" href="<?= url_for('student/subjects.php') ?>">Browse Subjects</a>
      </div>
    </div>

    <div class="student-hero-strip">
      <div class="student-chip-card">
        <span class="student-chip-label">Section</span>
        <strong><?= h($profile['section_name'] ?? '—') ?></strong>
        <small><?= h($profile['school_year'] ?? '') ?></small>
      </div>
      <div class="student-chip-card">
        <span class="student-chip-label">Assigned subjects</span>
        <strong><?= count($subjects) ?></strong>
        <small>Loaded from your section</small>
      </div>
      <div class="student-chip-card">
        <span class="student-chip-label">Unread alerts</span>
        <strong><?= $unreadNotifications ?></strong>
        <small>Notifications waiting</small>
      </div>
      <div class="student-chip-card">
        <span class="student-chip-label">Pending requests</span>
        <strong><?= $pendingRequests ?></strong>
        <small>Reactivation in progress</small>
      </div>
    </div>

    <div class="student-panel-grid">
      <article class="student-panel-card">
        <div class="student-panel-top">
          <h3>Track grades &amp; feedback</h3>
          <span class="student-panel-tag success"><?= $gradedCount ?> graded</span>
        </div>
        <div class="student-panel-stack">
          <div class="student-panel-metric-row">
            <div>
              <span class="student-mini-label">Comments</span>
              <strong><?= $feedbackCount ?></strong>
            </div>
            <span class="student-soft-badge success">Active</span>
          </div>
          <div class="student-panel-metric-row">
            <div>
              <span class="student-mini-label">Review progress</span>
              <strong><?= $reviewedCount ?></strong>
            </div>
            <span class="student-soft-badge warning">In review</span>
          </div>
          <div class="student-panel-metric-row">
            <div>
              <span class="student-mini-label">Ready to check</span>
              <strong><?= $gradedCount > 0 ? 'Yes' : 'Soon' ?></strong>
            </div>
            <a class="btn btn-ghost btn-sm" href="<?= url_for('student/my_submissions.php') ?>">Check records</a>
          </div>
        </div>
      </article>

      <article class="student-panel-card">
        <div class="student-panel-top">
          <h3>Manage submissions</h3>
          <span class="student-panel-tag"><?= $totalSubmissions ?> total</span>
        </div>
        <div class="student-panel-stack">
          <div class="student-panel-metric-row compact">
            <div>
              <span class="student-mini-label">Current status</span>
              <strong><?= $viewOnly ? 'View only' : 'Can submit' ?></strong>
            </div>
            <span class="student-soft-badge <?= $viewOnly ? 'danger' : 'success' ?>"><?= $viewOnly ? 'Restricted' : 'Enabled' ?></span>
          </div>
          <div class="student-panel-metric-row compact">
            <div>
              <span class="student-mini-label">Pending projects</span>
              <strong><?= $pendingCount ?></strong>
            </div>
            <span class="student-soft-badge warning">Pending</span>
          </div>
          <div class="student-panel-metric-row compact">
            <div>
              <span class="student-mini-label">Reviewed projects</span>
              <strong><?= $reviewedCount ?></strong>
            </div>
            <a class="btn btn-sm" href="<?= url_for('student/submit.php') ?>">New submit</a>
          </div>
        </div>
      </article>

      <article class="student-panel-card student-panel-card-accent">
        <div class="student-panel-top">
          <h3>Team projects</h3>
          <span class="student-panel-tag info"><?= $totalSubmissions ?> shared</span>
        </div>
        <div class="student-project-list">
          <?php foreach ($recentSubmissions as $row): ?>
            <div class="student-project-item">
              <div>
                <strong><?= h($row['subject_name']) ?></strong>
                <div class="muted small"><?= h($row['team_name']) ?> · Leader: <?= h($row['leader_name']) ?></div>
              </div>
              <?= status_badge($row['status']) ?>
            </div>
          <?php endforeach; ?>
          <?php if (!$recentSubmissions): ?>
            <div class="student-empty-panel">
              <strong>No team project yet</strong>
              <p>Once your leader submits, your shared project, links, and teacher review will appear here automatically.</p>
            </div>
          <?php endif; ?>
        </div>
      </article>
    </div>

    <div class="student-lower-grid">
      <article class="card student-support-card">
        <div class="split-header">
          <h3 class="section-title">My Subjects</h3>
          <a class="link" href="<?= url_for('student/subjects.php') ?>">View all</a>
        </div>
        <div class="list student-subject-list">
          <?php foreach ($subjects as $subject): ?>
            <?php $team = student_team_for_subject((int) $student['id'], (int) $subject['id']); ?>
            <div class="list-item">
              <div>
                <strong><?= h($subject['subject_name']) ?></strong>
                <div class="muted small"><?= h($subject['subject_code']) ?> · <?= h($subject['teacher_name']) ?></div>
              </div>
              <?php if ($team): ?>
                <span class="badge success"><?= h($team['role'] === 'leader' ? 'Team leader' : 'Team member') ?></span>
              <?php else: ?>
                <span class="badge warning">No team yet</span>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
          <?php if (!$subjects): ?><div class="empty-state">No active subjects are assigned to your section yet.</div><?php endif; ?>
        </div>
      </article>

      <article class="card student-support-card">
        <div class="split-header">
          <h3 class="section-title">Quick access</h3>
          <span class="table-note">Student-only tools</span>
        </div>
        <div class="student-quick-links">
          <a class="student-quick-link" href="<?= url_for('student/my_submissions.php') ?>">
            <strong>My submissions</strong>
            <span>Open your shared project records</span>
          </a>
          <a class="student-quick-link" href="<?= url_for('student/notifications.php') ?>">
            <strong>Notifications</strong>
            <span>Read updates and teacher notices</span>
          </a>
          <a class="student-quick-link" href="<?= url_for('student/profile.php') ?>">
            <strong>Profile</strong>
            <span>Manage account details and access</span>
          </a>
          <a class="student-quick-link" href="<?= url_for('student/request_reactivation.php') ?>">
            <strong>Request reactivation</strong>
            <span>Send approval requests when needed</span>
          </a>
        </div>
      </article>
    </div>
  </div>
</section>

<?php require_once __DIR__ . '/../backend/partials/footer.php'; ?>
