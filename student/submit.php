<?php
if (defined('FILE_STUDENT_SUBMIT_PHP_LOADED')) { return; }
define('FILE_STUDENT_SUBMIT_PHP_LOADED', true);

require_once __DIR__ . '/../backend/config/app.php';
require_once __DIR__ . '/../backend/helpers/uploads.php';
if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__));
}
require_role('student');
$student = current_user();
$allowed = $student['account_status'] === 'active' && (int) $student['can_submit'] === 1;
$subjectIdFilter = (int) ($_GET['subject_id'] ?? 0);
$activities = student_visible_activities((int) $student['id'], (int) $student['section_id'], $subjectIdFilter > 0 ? $subjectIdFilter : null);
$activitiesById = [];
$resourceMap = [];
foreach ($activities as $activity) {
    $activitiesById[(int) $activity['id']] = $activity;
    $resourceMap[(int) $activity['id']] = student_visible_subject_resources((int) $activity['subject_id']);
}
$historyRows = student_team_submissions((int) $student['id']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if (!$allowed) {
        set_flash('error', 'Your account is not allowed to submit right now.');
        redirect_to('student/submit.php');
    }

    $activityId = (int) ($_POST['activity_id'] ?? 0);
    $projectUrl = normalize_public_url($_POST['project_url'] ?? '');
    $videoUrl = normalize_public_url($_POST['video_url'] ?? '');
    $assignedSystem = trim($_POST['assigned_system'] ?? '');
    $companyName = trim($_POST['company_name'] ?? '');
    $demoUsername = trim($_POST['demo_username'] ?? '');
    $demoPassword = trim($_POST['demo_password'] ?? '');
    $contactEmail = trim($_POST['contact_email'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    $attachmentPath = null;
    $uploadedAbsolutePath = null;

    $selectedActivity = student_activity_detail($activityId, (int) $student['id'], (int) $student['section_id']);
    if (!$selectedActivity) {
        set_flash('error', 'Select a valid published activity.');
        redirect_to('student/submit.php');
    }
    if (student_activity_locked($selectedActivity) || !empty($selectedActivity['teacher_submission_locked'])) {
        set_flash('error', 'This activity is not open for new submissions right now.');
        redirect_to('student/submit.php?subject_id=' . (int) $selectedActivity['subject_id']);
    }

    if (!filter_var($contactEmail, FILTER_VALIDATE_EMAIL)) {
        set_flash('error', 'Enter a valid contact email address.');
        redirect_to('student/submit.php?subject_id=' . (int) $selectedActivity['subject_id']);
    }
    if (!empty($selectedActivity['require_repository']) && !$projectUrl) {
        set_flash('error', 'Enter a valid repository or project URL that starts with http:// or https://.');
        redirect_to('student/submit.php?subject_id=' . (int) $selectedActivity['subject_id']);
    }
    if (trim((string) ($_POST['video_url'] ?? '')) !== '' && !$videoUrl) {
        set_flash('error', 'Enter a valid live/demo URL that starts with http:// or https://, or leave it blank.');
        redirect_to('student/submit.php?subject_id=' . (int) $selectedActivity['subject_id']);
    }
    if (!empty($selectedActivity['require_live_url']) && !$videoUrl) {
        set_flash('error', 'This activity requires a live/demo URL.');
        redirect_to('student/submit.php?subject_id=' . (int) $selectedActivity['subject_id']);
    }
    if (!empty($selectedActivity['require_notes']) && $notes === '') {
        set_flash('error', 'Add notes/details for this activity.');
        redirect_to('student/submit.php?subject_id=' . (int) $selectedActivity['subject_id']);
    }
    if (!empty($selectedActivity['require_demo_access']) && ($demoUsername === '' || $demoPassword === '')) {
        set_flash('error', 'This activity requires demo access credentials.');
        redirect_to('student/submit.php?subject_id=' . (int) $selectedActivity['subject_id']);
    }

    $subjectTeam = null;
    $subjectTeamMembers = [];
    if (($selectedActivity['submission_mode'] ?? 'team') === 'team') {
        $subjectTeam = activity_subject_team_for_student((int) $student['id'], (int) $selectedActivity['subject_id']);
        if (!$subjectTeam) {
            set_flash('error', 'This activity requires a subject team first. Create or join your team from My Subjects.');
            redirect_to('student/subjects.php');
        }
        if (($subjectTeam['role'] ?? '') !== 'leader') {
            set_flash('error', 'Only the team leader can submit team-based activities.');
            redirect_to('student/my_submissions.php');
        }
        $subjectTeamMembers = subject_team_members((int) $subjectTeam['id']);
        $teamCount = count($subjectTeamMembers);
        if ($teamCount < max(1, (int) ($selectedActivity['min_members'] ?? 1))) {
            set_flash('error', 'Your subject team does not meet the minimum member requirement for this activity.');
            redirect_to('student/subjects.php');
        }
        if ($teamCount > max(1, (int) ($selectedActivity['max_members'] ?? 1))) {
            set_flash('error', 'Your subject team exceeds the maximum allowed team members for this activity.');
            redirect_to('student/subjects.php');
        }
    }

    $studentAttemptStmt = pdo()->prepare('SELECT COUNT(*) FROM submissions WHERE activity_id = ? AND student_id = ? AND status <> "archived"');
    $studentAttemptStmt->execute([$activityId, (int) $student['id']]);
    $studentAttemptCount = (int) $studentAttemptStmt->fetchColumn();
    $maxResubmissions = max(0, (int) ($selectedActivity['max_resubmissions'] ?? 0));
    $totalAllowedAttempts = 1 + $maxResubmissions;
    if ($studentAttemptCount > 0 && empty($selectedActivity['allow_resubmission'])) {
        set_flash('error', 'You already submitted this activity.');
        redirect_to('student/my_submissions.php');
    }
    if ($studentAttemptCount >= $totalAllowedAttempts) {
        set_flash('error', 'You already reached the maximum allowed attempts for this activity.');
        redirect_to('student/my_submissions.php');
    }
    $teamAttempts = 0;
    if ($subjectTeam) {
        $stmt = pdo()->prepare('SELECT COUNT(*) FROM submissions WHERE activity_id = ? AND team_id = ? AND status <> "archived"');
        $stmt->execute([$activityId, (int) $subjectTeam['id']]);
        $teamAttempts = (int) $stmt->fetchColumn();
        if ($teamAttempts > 0 && empty($selectedActivity['allow_resubmission'])) {
            set_flash('error', 'Your team already submitted this activity.');
            redirect_to('student/my_submissions.php');
        }
        if ($teamAttempts >= $totalAllowedAttempts) {
            set_flash('error', 'Your team already reached the maximum allowed attempts for this activity.');
            redirect_to('student/my_submissions.php');
        }
    }
    $attemptNo = $subjectTeam ? ($teamAttempts + 1) : ($studentAttemptCount + 1);

    $attachment = $_FILES['attachment'] ?? null;
    if (is_array($attachment) && (int) ($attachment['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        $upload = store_uploaded_file(
            $attachment,
            'uploads/submissions',
            $student['student_id'] . '-' . $selectedActivity['subject_code'] . '-activity-' . $activityId,
            ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'],
            5 * 1024 * 1024,
            ['pdf', 'jpg', 'jpeg', 'png', 'webp']
        );
        if (!$upload['ok']) {
            set_flash('error', 'Attachment must be a PDF or image file (JPG, PNG, or WEBP) and 5 MB or smaller.');
            redirect_to('student/submit.php?subject_id=' . (int) $selectedActivity['subject_id']);
        }
        $attachmentPath = $upload['path'];
        $uploadedAbsolutePath = APP_ROOT . '/' . ltrim((string) $attachmentPath, '/');
    } elseif (!empty($selectedActivity['require_file'])) {
        set_flash('error', 'This activity requires a file upload.');
        redirect_to('student/submit.php?subject_id=' . (int) $selectedActivity['subject_id']);
    }

    try {
        pdo()->beginTransaction();
        $teamId = 0;
        if ($subjectTeam) {
            $teamId = (int) $subjectTeam['id'];
        } else {
            $teamName = $selectedActivity['subject_code'] . ' · ' . $selectedActivity['title'] . ' · ' . $student['student_id'];
            $teamStmt = pdo()->prepare('INSERT INTO teams (subject_id, activity_id, section_id, leader_student_id, team_name, status) VALUES (?, ?, ?, ?, ?, "active")');
            $teamStmt->execute([(int) $selectedActivity['subject_id'], $activityId, (int) $student['section_id'], (int) $student['id'], $teamName]);
            $teamId = (int) pdo()->lastInsertId();
            $teamMemberStmt = pdo()->prepare('INSERT INTO team_members (team_id, student_id, role) VALUES (?, ?, ?)');
            $teamMemberStmt->execute([$teamId, (int) $student['id'], 'leader']);
        }

        $submissionStmt = pdo()->prepare('INSERT INTO submissions (team_id, student_id, submitted_by_student_id, section_id, subject_id, activity_id, attempt_no, assigned_system, company_name, project_url, video_url, admin_username, admin_password, user_username, user_password, contact_email, attachment_path, review_notes, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, "pending")');
        $submissionStmt->execute([
            $teamId,
            (int) $student['id'],
            (int) $student['id'],
            (int) $student['section_id'],
            (int) $selectedActivity['subject_id'],
            $activityId,
            $attemptNo,
            $assignedSystem !== '' ? $assignedSystem : $selectedActivity['title'],
            $companyName,
            $projectUrl,
            $videoUrl,
            $demoUsername !== '' ? demo_encrypt($demoUsername) : null,
            $demoPassword !== '' ? demo_encrypt($demoPassword) : null,
            null,
            null,
            $contactEmail,
            $attachmentPath,
            $notes !== '' ? $notes : null,
        ]);
        $submissionId = (int) pdo()->lastInsertId();

        $memberStmt = pdo()->prepare('INSERT INTO submission_members (submission_id, student_id, member_name, student_id_snapshot) VALUES (?, ?, ?, ?)');
        if ($subjectTeam) {
            foreach ($subjectTeamMembers as $memberRow) {
                $memberId = (int) $memberRow['id'];
                $memberStmt->execute([$submissionId, $memberId, (string) $memberRow['full_name'], (string) $memberRow['student_id']]);
                if ($memberId !== (int) $student['id']) {
                    create_notification('student', $memberId, 'Team submission shared', $student['full_name'] . ' submitted for your subject team in ' . $selectedActivity['subject_name'] . '.', 'info');
                    if (!empty($memberRow['email']) && filter_var($memberRow['email'], FILTER_VALIDATE_EMAIL)) {
                        require_once __DIR__ . '/../backend/helpers/mailer.php';
                        $mailSubject = 'Team submission update — ' . ($selectedActivity['title'] ?? 'Project');
                        $mailBody = "Hello " . ($memberRow['full_name'] ?? 'Student') . ",\n\n" . $student['full_name'] . " submitted your team project for '" . ($selectedActivity['title'] ?? '') . "' in " . ($selectedActivity['subject_name'] ?? '') . ".\n\nYou can view the submission in your student portal: " . url('student/my_submissions.php') . "\n\nRegards,\n" . MAIL_FROM_NAME;
                        @send_system_mail($memberRow['email'], $mailSubject, $mailBody);
                    }
                }
            }
        } else {
            $memberStmt->execute([$submissionId, (int) $student['id'], (string) $student['full_name'], (string) $student['student_id']]);
        }
        create_notification('teacher', (int) $selectedActivity['teacher_id'], 'New activity submission', 'A team submitted ' . $selectedActivity['title'] . ' for ' . $selectedActivity['subject_name'] . '.', 'info');
        // Notify teacher by email
        try {
          $teacherStmt = pdo()->prepare('SELECT email, full_name FROM teachers WHERE id = ? LIMIT 1');
          $teacherStmt->execute([(int) $selectedActivity['teacher_id']]);
          $teacherRow = $teacherStmt->fetch();
          if (!empty($teacherRow['email']) && filter_var($teacherRow['email'], FILTER_VALIDATE_EMAIL)) {
            require_once __DIR__ . '/../backend/helpers/mailer.php';
            $mailSubject = 'New project submission: ' . ($selectedActivity['title'] ?? 'Activity');
            $mailBody = "Hello " . ($teacherRow['full_name'] ?? 'Instructor') . ",\n\nA team has submitted a project for '" . ($selectedActivity['title'] ?? '') . "' in " . ($selectedActivity['subject_name'] ?? '') . ".\n\nView it here: " . url('teacher/submissions.php') . "\n\nRegards,\n" . MAIL_FROM_NAME;
            @send_system_mail($teacherRow['email'], $mailSubject, $mailBody);
          }
        } catch (Throwable $e) { }

        create_notification('student', (int) $student['id'], 'Submission received', 'Your submission for ' . $selectedActivity['title'] . ' has been received.', 'success');
        // Notify submitting student by email
        if (!empty($student['email']) && filter_var($student['email'], FILTER_VALIDATE_EMAIL)) {
          require_once __DIR__ . '/../backend/helpers/mailer.php';
          $mailSubject = 'Submission received — ' . ($selectedActivity['title'] ?? 'Project');
          $mailBody = "Hello " . ($student['full_name'] ?? 'Student') . ",\n\nYour submission for '" . ($selectedActivity['title'] ?? '') . "' has been received successfully. You can view your submission here: " . url('student/my_submissions.php') . "\n\nRegards,\n" . MAIL_FROM_NAME;
          @send_system_mail($student['email'], $mailSubject, $mailBody);
        }
        snapshot_submission_history($submissionId, 'created', 'student', (int) $student['id'], (string) $student['full_name']);
        pdo()->commit();
        set_flash('success', 'Submission created successfully. Team members can now see the shared record.');
        redirect_to('student/my_submissions.php');
    } catch (Throwable $e) {
        if (pdo()->inTransaction()) { pdo()->rollBack(); }
        if ($uploadedAbsolutePath && is_file($uploadedAbsolutePath)) { @unlink($uploadedAbsolutePath); }
        set_flash('error', 'Unable to create the submission right now. Please try again.');
        redirect_to('student/submit.php?subject_id=' . (int) $selectedActivity['subject_id']);
    }
}

$selectedActivityId = (int) ($_GET['activity_id'] ?? 0);
if ($selectedActivityId > 0 && !isset($activitiesById[$selectedActivityId])) {
    $selectedActivityId = 0;
}
$selectedActivity = $selectedActivityId > 0 ? ($activitiesById[$selectedActivityId] ?? null) : null;
$selectedResources = $selectedActivity ? ($resourceMap[(int) $selectedActivity['id']] ?? []) : [];
$selectedSubjectTeam = null;
$selectedSubjectTeamMembers = [];
if ($selectedActivity && (($selectedActivity['submission_mode'] ?? 'team') === 'team')) {
    $selectedSubjectTeam = activity_subject_team_for_student((int) $student['id'], (int) $selectedActivity['subject_id']);
    if ($selectedSubjectTeam) {
        $selectedSubjectTeamMembers = subject_team_members((int) $selectedSubjectTeam['id']);
    }
}

$submissionCountByActivity = [];
$latestSubmissionByActivity = [];
foreach ($historyRows as $row) {
    $activityId = (int) ($row['activity_id'] ?? 0);
    if ($activityId <= 0 || ($row['status'] ?? '') === 'archived') {
        continue;
    }
    $submissionCountByActivity[$activityId] = ($submissionCountByActivity[$activityId] ?? 0) + 1;
    if (!isset($latestSubmissionByActivity[$activityId])) {
        $latestSubmissionByActivity[$activityId] = $row;
        continue;
    }
    $currentTs = strtotime((string) ($row['updated_at'] ?? $row['submitted_at'] ?? '')) ?: 0;
    $latestTs = strtotime((string) ($latestSubmissionByActivity[$activityId]['updated_at'] ?? $latestSubmissionByActivity[$activityId]['submitted_at'] ?? '')) ?: 0;
    if ($currentTs > $latestTs) {
        $latestSubmissionByActivity[$activityId] = $row;
    }
}

$title = 'Submit Project';
$subtitle = 'Choose an activity, review restrictions, and submit from one clear workspace';
require_once __DIR__ . '/../backend/partials/header.php';
?>
<section class="workspace-shell student-history-shell ui-section" data-student-submit-shell>
  <div class="workspace-head">
    <div>
      <div class="eyebrow">Student portal</div>
      <h2>Submission workspace</h2>
      <p class="muted">Review available activities, filter by state or mode, then submit inline without leaving this page.</p>
    </div>
    <div class="student-history-actions ui-action-row">
      <a class="btn btn-secondary ui-btn ui-btn--secondary" href="<?= h(url('student/my_submissions.php')) ?>">Open history</a>
      <a class="btn btn-outline ui-btn ui-btn--ghost" href="<?= h(url('student/subjects.php')) ?>">Go to Subjects</a>
      <a class="btn btn-outline ui-btn ui-btn--ghost" href="<?= h(url('student/submit.php' . ($subjectIdFilter > 0 ? '?subject_id=' . $subjectIdFilter : ''))) ?>">Refresh</a>
    </div>
  </div>

  <div class="card submit-loading-state ui-panel-card" data-submit-loading>
    <strong>Loading submission workspace...</strong>
    <div class="muted small">Preparing activity states and availability.</div>
  </div>

  <?php if (!$allowed): ?>
    <div class="card empty-state submit-empty-state ui-empty-state">
      <strong>Submissions are currently unavailable for your account.</strong>
      <div class="muted small">This usually means your section or account is in view-only mode. Contact your teacher or administrator, then refresh this page.</div>
      <div class="form-actions ui-action-row">
        <a class="btn btn-outline ui-btn ui-btn--ghost" href="<?= h(url('student/subjects.php')) ?>">Go to Subjects</a>
        <a class="btn ui-btn ui-btn--primary" href="<?= h(url('student/submit.php')) ?>">Refresh</a>
      </div>
    </div>
  <?php elseif (!$activities): ?>
    <div class="card empty-state submit-empty-state ui-empty-state">
      <strong>No submission activities are visible yet.</strong>
      <div class="muted small">Your teacher may not have published activities for your section, or the current activities are still closed/upcoming.</div>
      <div class="form-actions ui-action-row">
        <a class="btn btn-outline ui-btn ui-btn--ghost" href="<?= h(url('student/subjects.php')) ?>">Go to Subjects</a>
        <a class="btn ui-btn ui-btn--primary" href="<?= h(url('student/submit.php' . ($subjectIdFilter > 0 ? '?subject_id=' . $subjectIdFilter : ''))) ?>">Refresh</a>
      </div>
    </div>
  <?php else: ?>
    <article class="card submit-list-shell ui-panel-card">
      <div class="split-header">
        <div>
          <h3 class="section-title">Available activities</h3>
          <div class="muted small">Select an activity to open the inline detail and submit panel below.</div>
        </div>
        <span class="pill soft"><?= count($activities) ?> total</span>
      </div>

      <div class="submit-filter-row ui-filter-group" data-submit-filters>
        <button type="button" class="btn btn-outline ui-filter is-active" data-submit-filter="all">All</button>
        <button type="button" class="btn btn-outline ui-filter" data-submit-filter="open">Open</button>
        <button type="button" class="btn btn-outline ui-filter" data-submit-filter="closing-soon">Closing soon</button>
        <button type="button" class="btn btn-outline ui-filter" data-submit-filter="team">Team</button>
        <button type="button" class="btn btn-outline ui-filter" data-submit-filter="individual">Individual</button>
      </div>

      <div class="review-card-grid" data-submit-activity-grid>
        <?php
        $nowTs = time();
        $soonThreshold = $nowTs + (72 * 3600);
        foreach ($activities as $activity):
            $activityId = (int) $activity['id'];
            $window = $activity['activity_window'] ?? ['state' => 'open', 'label' => 'Open'];
            $windowState = (string) ($window['state'] ?? 'open');
            $deadlineTs = !empty($activity['deadline_at']) ? strtotime((string) $activity['deadline_at']) : false;
            $isClosingSoon = $deadlineTs && $deadlineTs > $nowTs && $deadlineTs <= $soonThreshold && in_array($windowState, ['open', 'late'], true);
            $submissionCount = (int) ($submissionCountByActivity[$activityId] ?? 0);
            $hasSubmitted = $submissionCount > 0;
            $maxResubmissions = max(0, (int) ($activity['max_resubmissions'] ?? 0));
            $totalAllowedAttempts = 1 + $maxResubmissions;
            $allowResubmit = !empty($activity['allow_resubmission']) && $submissionCount < $totalAllowedAttempts;
            $isLocked = !empty($activity['submission_locked']);
            $canSubmitNow = !$isLocked && (!$hasSubmitted || $allowResubmit);
            $restrictionItems = [];
            if (!empty($activity['require_repository'])) { $restrictionItems[] = 'Repo required'; }
            if (!empty($activity['require_live_url'])) { $restrictionItems[] = 'Live URL required'; }
            if (!empty($activity['require_file'])) { $restrictionItems[] = 'File required'; }
            if (!empty($activity['require_demo_access'])) { $restrictionItems[] = 'Demo access required'; }
            if (!empty($activity['require_notes'])) { $restrictionItems[] = 'Notes required'; }
            $restrictionText = $restrictionItems ? implode(', ', $restrictionItems) : 'No extra requirements';
            $cardFilters = ['all', strtolower((string) ($activity['submission_mode'] ?? 'team'))];
            if ($canSubmitNow) { $cardFilters[] = 'open'; }
            if ($isClosingSoon) { $cardFilters[] = 'closing-soon'; }
            $isSelected = $selectedActivityId === $activityId;
        ?>
        <article class="card review-queue-card submit-activity-card ui-activity-card<?= $isSelected ? ' is-selected' : '' ?>" data-submit-card data-filter-tags="<?= h(implode(' ', array_unique($cardFilters))) ?>">
          <div class="split-header">
            <div>
              <h3 class="section-title"><?= h($activity['activity_title'] ?? $activity['title'] ?? 'Untitled activity') ?></h3>
              <div class="muted small"><?= h($activity['subject_code']) ?> · <?= h($activity['subject_name']) ?></div>
            </div>
            <?= status_badge($windowState) ?>
          </div>
          <div class="muted small"><?= h($activity['teacher_name']) ?></div>
          <div class="submit-chip-row ui-chip-row">
            <span class="pill soft ui-chip"><?= h($window['label'] ?? 'Open') ?></span>
            <span class="pill soft ui-chip"><?= h(ucfirst((string) $activity['submission_mode'])) ?></span>
            <?php foreach ($restrictionItems as $restriction): ?>
              <span class="pill ui-chip"><?= h($restriction) ?></span>
            <?php endforeach; ?>
            <?php if (!$restrictionItems): ?>
              <span class="pill ui-chip">Flexible requirements</span>
            <?php endif; ?>
          </div>
          <?php if ($hasSubmitted): ?>
            <div class="callout">
              <strong>Submitted</strong>
              <div class="muted small">
                <?= $allowResubmit ? 'Resubmission is allowed for this activity.' : 'Resubmission is not available for this activity.' ?>
              </div>
            </div>
          <?php endif; ?>
          <div class="form-actions ui-action-row" style="margin-top:12px;">
            <a class="btn ui-btn <?= $canSubmitNow ? 'ui-btn--primary' : 'ui-btn--secondary' ?> <?= $canSubmitNow ? '' : 'btn-secondary' ?>" href="<?= h(url('student/submit.php?activity_id=' . $activityId . ($subjectIdFilter > 0 ? '&subject_id=' . $subjectIdFilter : ''))) ?>">
              <?= $canSubmitNow ? ($hasSubmitted ? 'View / Resubmit' : 'Submit') : 'View details' ?>
            </a>
          </div>
        </article>
        <?php endforeach; ?>
      </div>
      <div class="card empty-state submission-empty-state ui-empty-state is-hidden" data-submit-filter-empty>
        No activities match the current filter.
      </div>
    </article>

    <?php if ($selectedActivity): ?>
      <?php
      $selectedId = (int) $selectedActivity['id'];
      $selectedCount = (int) ($submissionCountByActivity[$selectedId] ?? 0);
      $selectedSubmitted = $selectedCount > 0;
      $selectedMaxResub = max(0, (int) ($selectedActivity['max_resubmissions'] ?? 0));
      $selectedTotalAllowed = 1 + $selectedMaxResub;
      $selectedCanResubmit = !empty($selectedActivity['allow_resubmission']) && $selectedCount < $selectedTotalAllowed;
      $selectedCanSubmit = empty($selectedActivity['submission_locked']) && (!$selectedSubmitted || $selectedCanResubmit);
      ?>
      <article class="card submit-detail-shell ui-panel-card">
        <div class="split-header">
          <div>
            <h3 class="section-title">Selected activity details</h3>
            <div class="muted small"><?= h($selectedActivity['subject_code']) ?> · <?= h($selectedActivity['subject_name']) ?> · <?= h($selectedActivity['title']) ?></div>
          </div>
          <?= status_badge((string) (($selectedActivity['activity_window']['state'] ?? 'open'))) ?>
        </div>
        <div class="callout" style="margin-bottom:14px;">
          <strong><?= h($selectedActivity['activity_window']['label'] ?? 'Open') ?></strong>
          <div class="muted small">Teacher: <?= h($selectedActivity['teacher_name']) ?> · Mode: <?= h(ucfirst((string) $selectedActivity['submission_mode'])) ?> · Members <?= (int) $selectedActivity['min_members'] ?>-<?= (int) $selectedActivity['max_members'] ?></div>
        </div>
        <section class="card submit-requirements ui-panel-card">
          <h4 class="section-title">Requirements</h4>
          <div class="submit-chip-row ui-chip-row">
            <span class="pill ui-chip <?= !empty($selectedActivity['require_repository']) ? '' : 'soft' ?>">Repository <?= !empty($selectedActivity['require_repository']) ? 'required' : 'optional' ?></span>
            <span class="pill ui-chip <?= !empty($selectedActivity['require_live_url']) ? '' : 'soft' ?>">Live URL <?= !empty($selectedActivity['require_live_url']) ? 'required' : 'optional' ?></span>
            <span class="pill ui-chip <?= !empty($selectedActivity['require_file']) ? '' : 'soft' ?>">Attachment <?= !empty($selectedActivity['require_file']) ? 'required' : 'optional' ?></span>
            <span class="pill ui-chip <?= !empty($selectedActivity['allow_late']) ? '' : 'soft' ?>">Late <?= !empty($selectedActivity['allow_late']) ? 'allowed' : 'blocked' ?></span>
            <span class="pill ui-chip <?= !empty($selectedActivity['allow_resubmission']) ? '' : 'soft' ?>">Resubmission <?= !empty($selectedActivity['allow_resubmission']) ? 'allowed' : 'blocked' ?></span>
          </div>
        </section>

        <?php
          $teamMode = (($selectedActivity['submission_mode'] ?? 'team') === 'team');
          $teamReady = true;
          $teamReadOnlyNotice = '';
          if ($teamMode) {
              if (!$selectedSubjectTeam) {
                  $teamReady = false;
                  $teamReadOnlyNotice = 'A subject team is required before you can submit this team-based activity.';
              } elseif (($selectedSubjectTeam['role'] ?? '') !== 'leader') {
                  $teamReady = false;
                  $teamReadOnlyNotice = 'Only your team leader can submit for this activity.';
              } else {
                  $memberCount = count($selectedSubjectTeamMembers);
                  $minMembers = max(1, (int) ($selectedActivity['min_members'] ?? 1));
                  $maxMembers = max(1, (int) ($selectedActivity['max_members'] ?? 1));
                  if ($memberCount < $minMembers || $memberCount > $maxMembers) {
                      $teamReady = false;
                      $teamReadOnlyNotice = 'Your subject team size must be between ' . $minMembers . ' and ' . $maxMembers . ' members for this activity.';
                  }
              }
          }
          $canSubmitSelected = $selectedCanSubmit && (!$teamMode || $teamReady);
        ?>

        <?php if ($teamMode): ?>
          <section class="card ui-panel-card" style="margin-bottom:14px;">
            <div class="split-header">
              <div>
                <h4 class="section-title">Team review</h4>
                <div class="muted small">Your saved subject team will be used for this submission.</div>
              </div>
              <?php if ($selectedSubjectTeam): ?>
                <span class="pill ui-chip"><?= h(ucfirst((string) ($selectedSubjectTeam['role'] ?? 'member'))) ?></span>
              <?php endif; ?>
            </div>
            <?php if (!$selectedSubjectTeam): ?>
              <div class="empty-state ui-empty-state">
                <strong>Team required first</strong>
                <div class="muted small">Create your subject team in the subject workspace, then return here to submit.</div>
                <div class="form-actions ui-action-row"><a class="btn btn-outline ui-btn ui-btn--ghost" href="<?= h(url('student/subjects.php')) ?>">Manage team in Subjects</a></div>
              </div>
            <?php else: ?>
              <div class="info-list" style="margin-top:8px;">
                <div class="row"><span>Leader</span><strong><?= h($selectedSubjectTeam['leader_name'] ?? '—') ?></strong></div>
                <div class="row"><span>Team count</span><strong><?= count($selectedSubjectTeamMembers) ?></strong></div>
              </div>
              <div class="timeline-list" style="margin-top:10px;">
                <?php foreach ($selectedSubjectTeamMembers as $teamMember): ?>
                  <div class="timeline-item">
                    <strong><?= h($teamMember['full_name']) ?></strong>
                    <p><?= h($teamMember['student_id']) ?> · <?= h(ucfirst((string) $teamMember['role'])) ?></p>
                  </div>
                <?php endforeach; ?>
              </div>
              <?php if (!$teamReady): ?>
                <div class="callout" style="margin-top:12px;"><strong>Submission unavailable</strong><div class="muted small"><?= h($teamReadOnlyNotice) ?></div></div>
              <?php endif; ?>
              <div class="form-actions ui-action-row" style="margin-top:12px;"><a class="btn btn-outline ui-btn ui-btn--ghost" href="<?= h(url('student/subjects.php')) ?>">Manage Team</a></div>
            <?php endif; ?>
          </section>
        <?php endif; ?>

        <?php if ($selectedSubmitted && !$selectedCanResubmit): ?>
          <div class="empty-state ui-empty-state">
            <strong>Already submitted</strong>
            <div class="muted small">You already submitted this activity and resubmission is not allowed.</div>
          </div>
        <?php elseif (!$canSubmitSelected): ?>
          <div class="empty-state ui-empty-state">
            <strong>Submission is currently unavailable.</strong>
            <div class="muted small"><?= h($teamReadOnlyNotice ?: 'This activity is locked or currently outside the open window.') ?></div>
          </div>
        <?php else: ?>
        <form method="post" enctype="multipart/form-data" class="form-grid submit-form-shell ui-form-shell ui-form-grid" id="activity-submit-form">
          <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
          <input type="hidden" name="activity_id" id="activity-id-fixed" value="<?= $selectedId ?>">

          <div class="full submit-form-section">
            <h4 class="section-title">Required submission details</h4>
            <div class="form-grid submit-two-col">
              <?php if (!empty($selectedActivity['require_repository'])): ?>
              <div><label>Project / repository URL</label><input class="ui-input" name="project_url" placeholder="https://github.com/your-team/project" required></div>
              <?php endif; ?>
              <?php if (!empty($selectedActivity['require_live_url'])): ?>
              <div><label>Live / demo URL</label><input class="ui-input" name="video_url" placeholder="https://your-demo.example.com" required></div>
              <?php endif; ?>
              <div><label>Contact email</label><input class="ui-input" name="contact_email" type="email" required placeholder="leader@example.com"></div>
              <?php if (!empty($selectedActivity['require_file'])): ?>
              <div><label>Attachment</label><input class="ui-input" type="file" name="attachment" accept=".pdf,.jpg,.jpeg,.png,.webp" required></div>
              <?php endif; ?>
            </div>
          </div>

          <div class="full submit-form-section">
            <h4 class="section-title">Optional project information</h4>
            <div class="form-grid submit-two-col">
              <?php if (empty($selectedActivity['require_repository'])): ?>
              <div><label>Project / repository URL</label><input class="ui-input" name="project_url" placeholder="https://github.com/your-team/project"></div>
              <?php endif; ?>
              <?php if (empty($selectedActivity['require_live_url'])): ?>
              <div><label>Live / demo URL</label><input class="ui-input" name="video_url" placeholder="https://your-demo.example.com"></div>
              <?php endif; ?>
              <?php if (empty($selectedActivity['require_file'])): ?>
              <div><label>Attachment</label><input class="ui-input" type="file" name="attachment" accept=".pdf,.jpg,.jpeg,.png,.webp"></div>
              <?php endif; ?>
              <div><label>Submission title</label><input class="ui-input" name="assigned_system" placeholder="Final web application build" value="<?= h($selectedActivity['title']) ?>"></div>
              <div><label>Client / company name</label><input class="ui-input" name="company_name" placeholder="Optional client or company"></div>
            </div>
            <div class="full"><label>Notes</label><textarea class="ui-textarea" name="notes" id="notes-input" rows="4" placeholder="Optional summary, setup notes, or special instructions" <?= !empty($selectedActivity['require_notes']) ? 'required' : '' ?>></textarea></div>
          </div>

          <div class="full form-grid<?= !empty($selectedActivity['require_demo_access']) ? '' : ' is-hidden' ?>" id="demo-block" style="grid-template-columns:1fr 1fr;">
            <div><label>Demo username</label><input class="ui-input" name="demo_username" placeholder="admin-demo"></div>
            <div id="demo-password-block"><label>Demo password</label><input class="ui-input" name="demo_password" placeholder="temporary password"></div>
          </div>

          <div class="full form-actions ui-action-row">
            <button class="btn ui-btn ui-btn--primary" type="submit"><?= $selectedSubmitted ? 'Submit resubmission' : 'Submit project' ?></button>
            <a class="btn btn-outline ui-btn ui-btn--ghost" href="<?= h(url('student/submit.php' . ($subjectIdFilter > 0 ? '?subject_id=' . $subjectIdFilter : ''))) ?>">Cancel</a>
          </div>
        </form>
        <?php endif; ?>

        <?php if ($selectedResources): ?>
          <div class="card ui-table-card" style="margin-top:16px;">
            <h4 class="section-title">Activity resources</h4>
            <div class="timeline-list">
              <?php foreach ($selectedResources as $resource): ?>
                <div class="timeline-item">
                  <strong><?= h($resource['title']) ?></strong>
                  <div class="muted small"><?= h(basename((string) $resource['file_path'])) ?></div>
                  <div class="form-actions ui-action-row"><a class="btn btn-outline ui-btn ui-btn--ghost" href="<?= h(url($resource['file_path'])) ?>" target="_blank" rel="noopener">Open file</a></div>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endif; ?>
      </article>
    <?php endif; ?>
  <?php endif; ?>
</section>
<script>
(() => {
  const loadingState = document.querySelector('[data-submit-loading]');
  if (loadingState) loadingState.classList.add('is-hidden');

  const grid = document.querySelector('[data-submit-activity-grid]');
  const emptyFiltered = document.querySelector('[data-submit-filter-empty]');
  const filterButtons = Array.from(document.querySelectorAll('[data-submit-filter]'));
  const cards = Array.from(document.querySelectorAll('[data-submit-card]'));
  if (grid && filterButtons.length && cards.length) {
    const applyFilter = (filter) => {
      let visible = 0;
      const emptyMessages = {
        all: 'No activities available right now.',
        open: 'No open activities right now.',
        'closing-soon': 'Nothing is closing soon.',
        team: 'No team activities available.',
        individual: 'No individual activities available.'
      };
      cards.forEach((card) => {
        const tags = (card.getAttribute('data-filter-tags') || '').split(/\s+/).filter(Boolean);
        const show = filter === 'all' || tags.includes(filter);
        card.classList.toggle('is-hidden', !show);
        if (show) visible += 1;
      });
      emptyFiltered?.classList.toggle('is-hidden', visible > 0);
      if (emptyFiltered) {
        emptyFiltered.textContent = emptyMessages[filter] || emptyMessages.all;
      }
      filterButtons.forEach((button) => button.classList.toggle('is-active', button.getAttribute('data-submit-filter') === filter));
    };
    filterButtons.forEach((button) => {
      button.addEventListener('click', () => applyFilter(button.getAttribute('data-submit-filter') || 'all'));
    });
    applyFilter('all');
  }

})();
</script>
<?php require_once __DIR__ . '/../backend/partials/footer.php'; ?>
