<?php
require_once __DIR__ . '/../backend/helpers/query.php';
require_role('student');
$student = current_user();
$subjects = student_subjects((int)$student['section_id']);
$allowed = $student['account_status'] === 'active' && (int)$student['can_submit'] === 1;
$openSubjects = array_values(array_filter($subjects, fn($row) => empty($row['submission_locked'])));
$canSubmitNow = $allowed && count($openSubjects) > 0;
$historyRows = student_team_submissions((int) $student['id']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if (!$allowed) {
        set_flash('error', 'Your account is not allowed to submit right now.');
        redirect_to('student/submit.php');
    }
    if (!$openSubjects) {
        set_flash('error', 'All assigned subjects are currently locked by deadline. Wait for your teacher to reopen a submission window.');
        redirect_to('student/submit.php');
    }

    $subjectId = (int) ($_POST['subject_id'] ?? 0);
    $projectUrl = trim($_POST['project_url'] ?? '');
    $videoUrl = trim($_POST['video_url'] ?? '');
    $assignedSystem = trim($_POST['assigned_system'] ?? '');
    $companyName = trim($_POST['company_name'] ?? '');
    $demoUsername = trim($_POST['demo_username'] ?? '');
    $demoPassword = trim($_POST['demo_password'] ?? '');
    $contactEmail = trim($_POST['contact_email'] ?? '');
    $memberIdsRaw = trim($_POST['member_student_ids'] ?? '');
    $attachmentPath = null;
    $uploadedAbsolutePath = null;

    $subjectsById = [];
    foreach ($subjects as $subjectRow) {
        $subjectsById[(int) $subjectRow['id']] = $subjectRow;
    }
    $validSubjectIds = array_keys($subjectsById);
    if (!in_array($subjectId, $validSubjectIds, true)) {
        set_flash('error', 'Invalid subject selection.');
        redirect_to('student/submit.php');
    }

    $selectedSubject = $subjectsById[$subjectId];
    if (student_subject_locked($selectedSubject)) {
        set_flash('error', 'The deadline for this subject has already been reached. You can submit again only if your teacher reopens the submission window.');
        redirect_to('student/submit.php');
    }

    $existingTeam = student_team_for_subject((int) $student['id'], $subjectId);
    if ($existingTeam && (int) $existingTeam['leader_student_id'] !== (int) $student['id']) {
        set_flash('error', 'You are already a member of a team in this subject. Only the leader can send the project.');
        redirect_to('student/my_submissions.php');
    }
    if ($existingTeam) {
        set_flash('error', 'Your team already has a submission for this subject. Open My Submissions to view it.');
        redirect_to('student/my_submissions.php');
    }

    $memberStudentIds = preg_split('/\r\n|\r|\n/', $memberIdsRaw);
    $memberStudentIds = array_values(array_unique(array_filter(array_map('trim', $memberStudentIds))));
    if (!in_array($student['student_id'], $memberStudentIds, true)) {
        array_unshift($memberStudentIds, $student['student_id']);
    }

    $members = find_students_by_student_ids($memberStudentIds, (int) $student['section_id']);
    if (count($members) !== count($memberStudentIds)) {
        set_flash('error', 'Every member must have an existing account in the same section. Add members using their student ID, one per line.');
        redirect_to('student/submit.php');
    }

    $memberMap = [];
    foreach ($members as $member) {
        $memberMap[$member['student_id']] = $member;
    }

    foreach ($memberStudentIds as $memberStudentId) {
        $member = $memberMap[$memberStudentId];
        $teamCheck = student_team_for_subject((int) $member['id'], $subjectId);
        if ($teamCheck) {
            set_flash('error', 'Student ID ' . $memberStudentId . ' is already assigned to a team in this subject.');
            redirect_to('student/submit.php');
        }
    }

    $subjectInfoStmt = pdo()->prepare('SELECT subject_code, subject_name, teacher_id FROM subjects WHERE id = ?');
    $subjectInfoStmt->execute([$subjectId]);
    $subject = $subjectInfoStmt->fetch();
    if (!$subject) {
        set_flash('error', 'Subject not found.');
        redirect_to('student/submit.php');
    }

    $attachment = $_FILES['attachment'] ?? null;
    if (is_array($attachment) && (int) ($attachment['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        if ((int) $attachment['error'] !== UPLOAD_ERR_OK) {
            set_flash('error', 'The attachment could not be uploaded. Please try again.');
            redirect_to('student/submit.php');
        }
        if ((int) ($attachment['size'] ?? 0) > 5 * 1024 * 1024) {
            set_flash('error', 'Attachment must be 5 MB or smaller.');
            redirect_to('student/submit.php');
        }

        $originalName = (string) ($attachment['name'] ?? '');
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $allowedExtensions = ['pdf', 'jpg', 'jpeg', 'png', 'webp'];
        if (!in_array($extension, $allowedExtensions, true)) {
            set_flash('error', 'Attachment must be a PDF or image file (JPG, PNG, or WEBP).');
            redirect_to('student/submit.php');
        }

        $uploadDir = APP_ROOT . '/uploads/submissions';
        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
            set_flash('error', 'Upload folder is not writable right now. Please contact your administrator.');
            redirect_to('student/submit.php');
        }

        $safeBase = preg_replace('/[^a-zA-Z0-9_-]+/', '-', $student['student_id'] . '-' . $subject['subject_code']);
        $filename = strtolower(trim($safeBase, '-')) . '-' . date('YmdHis') . '-' . bin2hex(random_bytes(4)) . '.' . $extension;
        $uploadedAbsolutePath = $uploadDir . '/' . $filename;
        if (!move_uploaded_file($attachment['tmp_name'], $uploadedAbsolutePath)) {
            set_flash('error', 'Attachment could not be saved. Please try again.');
            redirect_to('student/submit.php');
        }

        $attachmentPath = 'uploads/submissions/' . $filename;
    }

    try {
        pdo()->beginTransaction();

        $teamName = $subject['subject_code'] . ' Team ' . $student['student_id'];
        $teamStmt = pdo()->prepare('INSERT INTO teams (subject_id, section_id, leader_student_id, team_name, status) VALUES (?, ?, ?, ?, "active")');
        $teamStmt->execute([$subjectId, $student['section_id'], $student['id'], $teamName]);
        $teamId = (int) pdo()->lastInsertId();

        $teamMemberStmt = pdo()->prepare('INSERT INTO team_members (team_id, student_id, role) VALUES (?, ?, ?)');
        $memberNameStmt = pdo()->prepare('INSERT INTO submission_members (submission_id, member_name) VALUES (?, ?)');
        foreach ($memberStudentIds as $memberStudentId) {
            $member = $memberMap[$memberStudentId];
            $role = ((int) $member['id'] === (int) $student['id']) ? 'leader' : 'member';
            $teamMemberStmt->execute([$teamId, $member['id'], $role]);
        }

        $submissionStmt = pdo()->prepare('
            INSERT INTO submissions (
                team_id, student_id, submitted_by_student_id, section_id, subject_id,
                assigned_system, company_name, project_url, video_url,
                admin_username, admin_password, user_username, user_password,
                contact_email, attachment_path, status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, "pending")
        ');
        $submissionStmt->execute([
            $teamId,
            $student['id'],
            $student['id'],
            $student['section_id'],
            $subjectId,
            $assignedSystem,
            $companyName,
            $projectUrl,
            $videoUrl,
            $demoUsername ?: null,
            $demoPassword ?: null,
            null,
            null,
            $contactEmail,
            $attachmentPath
        ]);
        $submissionId = (int) pdo()->lastInsertId();

        foreach ($memberStudentIds as $memberStudentId) {
            $member = $memberMap[$memberStudentId];
            $memberNameStmt->execute([$submissionId, $member['full_name']]);
            if ((int) $member['id'] !== (int) $student['id']) {
                create_notification('student', (int) $member['id'], 'Team project submitted', $student['full_name'] . ' submitted the project for your team in ' . $subject['subject_name'] . '. You can now view it from My Submissions.', 'info');
            }
        }

        if (!empty($subject['teacher_id'])) {
            create_notification('teacher', (int) $subject['teacher_id'], 'New team submission received', 'A leader submitted a project for ' . $subject['subject_name'] . '.', 'info');
        }

        create_notification('student', (int) $student['id'], 'Submission received', 'Your team project has been submitted successfully and is awaiting review.', 'success');

        pdo()->commit();

        require_once __DIR__ . '/../backend/helpers/mailer.php';
        send_system_mail($contactEmail, 'Project submission received', "Hello {$student['full_name']},

Your team submission for {$subject['subject_name']} has been received successfully. Team members can now view it from their accounts.

Regards,
" . APP_NAME);

        set_flash('success', 'Project submitted successfully. Your members can now view the shared project in their accounts.');
        redirect_to('student/my_submissions.php');
    } catch (Throwable $e) {
        if (pdo()->inTransaction()) {
            pdo()->rollBack();
        }
        if ($uploadedAbsolutePath && is_file($uploadedAbsolutePath)) {
            @unlink($uploadedAbsolutePath);
        }
        set_flash('error', 'Unable to submit the team project right now. Please verify member student IDs and try again.');
        redirect_to('student/submit.php');
    }
}

$title = 'Submit Project';
$subtitle = 'Leader-based team submission. Members see the same project after you send it.';
require_once __DIR__ . '/../backend/partials/header.php';
?>
<section class="student-page-shell">
  <div class="student-page-card">
    <?php if (!$allowed): ?>
      <div class="flash error">Your account is currently restricted. You may still log in and view your records, but you cannot submit new projects.</div>
    <?php endif; ?>
    <?php if ($allowed && !$openSubjects): ?>
      <div class="flash warning">All of your assigned subjects are currently locked by deadline. Your teacher must reopen a submission window before you can submit again.</div>
    <?php endif; ?>
    <?php $lockedSubjectCount = count(array_filter($subjects, fn($row) => !empty($row['submission_locked']))); ?>
    <?php if ($lockedSubjectCount > 0): ?>
      <div class="flash warning"><?= $lockedSubjectCount ?> subject<?= $lockedSubjectCount === 1 ? '' : 's' ?> already reached the submission deadline. Choose only an open or reopened subject below.</div>
    <?php endif; ?>

    <div class="student-submit-hero">
      <div class="student-submit-banner">
        <strong>Only the team leader should submit this form.</strong>
        <span>Add your group members using their student ID, one per line. After submission, every member can log in and see the same project, links, status, grade, and teacher feedback.</span>
      </div>
    </div>

    <div class="student-submit-layout">
      <div class="card student-submit-card">
        <form method="post" enctype="multipart/form-data" data-multistep class="multi-step student-submit-form">
          <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
          <div class="stepper student-stepper">
            <div>Subject</div><div>Links</div><div>Details</div><div>Members</div><div>Demo Access</div>
          </div>

          <section class="step-panel active">
            <div class="form-grid">
              <div class="full"><label>Subject</label><select name="subject_id" required <?= $canSubmitNow ? '' : 'disabled' ?>><?php if (!$canSubmitNow): ?><option value="" selected>No open subject available</option><?php endif; ?><?php foreach ($subjects as $subject): ?><option value="<?= (int) $subject['id'] ?>" <?= ((int) ($_GET['subject_id'] ?? 0) === (int) $subject['id']) ? 'selected' : '' ?> <?= !empty($subject['submission_locked']) ? 'disabled' : '' ?>><?= h($subject['subject_name']) ?> · <?= h($subject['subject_code']) ?><?= !empty($subject['submission_locked']) ? ' — deadline reached' : '' ?></option><?php endforeach; ?></select></div><div class="full muted small">Each subject follows its own submission window. Locked subjects stay visible here so you can see why they cannot be submitted.</div>
              <div class="full"><label>Contact email</label><input type="email" name="contact_email" required placeholder="group@gmail.com"></div>
            </div>
            <div class="step-actions"><span></span><button class="btn" type="button" data-next <?= $canSubmitNow ? '' : 'disabled' ?>>Continue</button></div>
          </section>

          <section class="step-panel">
            <div class="form-grid">
              <div><label>Project URL</label><input type="url" name="project_url" required placeholder="https://..."></div>
              <div><label>Video URL</label><input type="url" name="video_url" placeholder="https://..."></div>
            </div>
            <div class="step-actions"><button class="btn btn-secondary" type="button" data-prev>Back</button><button class="btn" type="button" data-next>Continue</button></div>
          </section>

          <section class="step-panel">
            <div class="form-grid">
              <div><label>Assigned system</label><input name="assigned_system" required placeholder="Library Management System"></div>
              <div><label>Company / Brand</label><input name="company_name" required placeholder="ABC Corporation"></div>
              <div class="full"><label>Optional attachment</label><input type="file" name="attachment" accept=".pdf,image/*"><div class="muted small">Upload one PDF or image up to 5 MB.</div></div>
            </div>
            <div class="step-actions"><button class="btn btn-secondary" type="button" data-prev>Back</button><button class="btn" type="button" data-next>Continue</button></div>
          </section>

          <section class="step-panel">
            <div class="form-grid">
              <div class="full"><label>Member student IDs (one per line)</label><textarea name="member_student_ids" required placeholder="<?= h($student['student_id']) ?>&#10;2025-0002"></textarea></div>
            </div>
            <div class="callout">Use registered student IDs only. Members must already have accounts and belong to the same section.</div>
            <div class="step-actions"><button class="btn btn-secondary" type="button" data-prev>Back</button><button class="btn" type="button" data-next>Continue</button></div>
          </section>

          <section class="step-panel">
            <div class="callout" style="margin-bottom:16px;"><strong>Optional.</strong> Add a demo login only if your project needs a sign-in before the teacher can test it.</div>
            <div class="form-grid">
              <div><label>Demo username</label><input name="demo_username" placeholder="testaccount"></div>
              <div><label>Demo password</label><input name="demo_password" placeholder="demo123"></div>
            </div>
            <div class="step-actions"><button class="btn btn-secondary" type="button" data-prev>Back</button><button class="btn" type="submit" <?= $canSubmitNow ? '' : 'disabled' ?>>Submit Project</button></div>
          </section>
        </form>
      </div>

      <aside class="student-submit-sidepanel">
        <div class="student-side-stat">
          <span>Submission mode</span>
          <strong><?= $canSubmitNow ? 'Enabled' : 'View only' ?></strong>
          <small><?= $canSubmitNow ? 'You can submit a new team project.' : 'New submissions are temporarily disabled.' ?></small>
        </div>
        <div class="student-side-stat">
          <span>Shared team records</span>
          <strong><?= count($historyRows) ?></strong>
          <small>Projects already visible in your account.</small>
        </div>
        <div class="student-side-note">
          <strong>Demo Access</strong>
          <p>Only fill the last step if your teacher needs credentials to log into your actual project website or system.</p>
        </div>
      </aside>
    </div>

    <div class="student-history-card card">
      <div class="split-header">
        <div>
          <h3 class="section-title">All Team Submissions</h3>
          <div class="muted small">Projects already shared with your account</div>
        </div>
        <a class="btn btn-ghost btn-sm" href="<?= h(url('student/my_submissions.php')) ?>">Open full view</a>
      </div>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Project Title</th>
              <th>Subject</th>
              <th>Links</th>
              <th>Status</th>
              <th>Grade</th>
              <th>Last Updated</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($historyRows as $row): ?>
              <tr>
                <td data-label="Project Title"><strong><?= h($row['assigned_system'] ?: 'Untitled Project') ?></strong></td>
                <td data-label="Subject"><?= h($row['subject_name']) ?> · <?= h($row['subject_code']) ?></td>
                <td data-label="Links">
                  <div class="table-actions">
                    <?php if (!empty($row['project_url'])): ?><a class="btn btn-secondary btn-sm" target="_blank" href="<?= h($row['project_url']) ?>">Project</a><?php endif; ?>
                    <?php if (!empty($row['video_url'])): ?><a class="btn btn-ghost btn-sm" target="_blank" href="<?= h($row['video_url']) ?>">Video</a><?php endif; ?>
                  </div>
                </td>
                <td data-label="Status"><?= status_badge($row['status']) ?></td>
                <td data-label="Grade"><?= h($row['grade'] ?: 'N/A') ?></td>
                <td data-label="Last Updated"><?= h($row['submitted_at']) ?></td>
              </tr>
            <?php endforeach; ?>
            <?php if (!$historyRows): ?>
              <tr><td colspan="6" class="empty-state">You do not have any team submissions yet.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</section>
<?php require_once __DIR__ . '/../backend/partials/footer.php'; ?>
