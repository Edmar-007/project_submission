<?php
require_once __DIR__ . '/../backend/helpers/query.php';
require_once __DIR__ . '/../backend/helpers/uploads.php';
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
    $memberIds = array_values(array_unique(array_map('intval', $_POST['member_ids'] ?? [])));
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

    if ($selectedActivity['submission_mode'] === 'individual') {
        $memberIds = [];
    }

    $existingTeam = student_team_for_activity((int) $student['id'], $activityId);
    if ($existingTeam && (int) $existingTeam['leader_student_id'] !== (int) $student['id']) {
        set_flash('error', 'You are already part of a team in this activity. Only the leader can submit.');
        redirect_to('student/my_submissions.php');
    }
    if ($existingTeam) {
        $stmt = pdo()->prepare('SELECT COUNT(*) FROM submissions WHERE activity_id = ? AND team_id = ? AND status <> "archived"');
        $stmt->execute([$activityId, (int) $existingTeam['id']]);
        if ((int) $stmt->fetchColumn() > 0 && empty($selectedActivity['allow_resubmission'])) {
            set_flash('error', 'Your team already has a submission for this activity.');
            redirect_to('student/my_submissions.php');
        }
    }

    $selectedMembers = students_for_activity_ids($activityId, $memberIds);
    $selectedMembersById = [];
    foreach ($selectedMembers as $memberRow) {
        $selectedMembersById[(int) $memberRow['id']] = $memberRow;
    }
    if ($selectedActivity['submission_mode'] === 'team') {
        $membersForSubmission = $selectedMembers;
        $membersForSubmission[(int) $student['id']] = $student;
        $uniqueCount = count(array_unique(array_merge([(int) $student['id']], array_keys($selectedMembersById))));
        if ($uniqueCount < max(1, (int) $selectedActivity['min_members'])) {
            set_flash('error', 'Add more members to meet the minimum member requirement.');
            redirect_to('student/submit.php?subject_id=' . (int) $selectedActivity['subject_id']);
        }
        if ($uniqueCount > max(1, (int) $selectedActivity['max_members'])) {
            set_flash('error', 'Too many members selected for this activity.');
            redirect_to('student/submit.php?subject_id=' . (int) $selectedActivity['subject_id']);
        }
        foreach (array_keys($selectedMembersById) as $memberId) {
            $teamCheck = student_team_for_activity($memberId, $activityId);
            if ($teamCheck) {
                set_flash('error', 'One or more selected members already belong to another team for this activity.');
                redirect_to('student/submit.php?subject_id=' . (int) $selectedActivity['subject_id']);
            }
        }
    }

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
        if ($existingTeam) {
            $teamId = (int) $existingTeam['id'];
        } else {
            $teamName = $selectedActivity['subject_code'] . ' · ' . $selectedActivity['title'] . ' · ' . $student['student_id'];
            $teamStmt = pdo()->prepare('INSERT INTO teams (subject_id, activity_id, section_id, leader_student_id, team_name, status) VALUES (?, ?, ?, ?, ?, "active")');
            $teamStmt->execute([(int) $selectedActivity['subject_id'], $activityId, (int) $student['section_id'], (int) $student['id'], $teamName]);
            $teamId = (int) pdo()->lastInsertId();
            $teamMemberStmt = pdo()->prepare('INSERT INTO team_members (team_id, student_id, role) VALUES (?, ?, ?)');
            $teamMemberStmt->execute([$teamId, (int) $student['id'], 'leader']);
            foreach ($selectedMembersById as $memberId => $memberRow) {
                $teamMemberStmt->execute([$teamId, $memberId, 'member']);
            }
        }

        $submissionStmt = pdo()->prepare('INSERT INTO submissions (team_id, student_id, submitted_by_student_id, section_id, subject_id, activity_id, assigned_system, company_name, project_url, video_url, admin_username, admin_password, user_username, user_password, contact_email, attachment_path, review_notes, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, "pending")');
        $submissionStmt->execute([
            $teamId,
            (int) $student['id'],
            (int) $student['id'],
            (int) $student['section_id'],
            (int) $selectedActivity['subject_id'],
            $activityId,
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
        $memberStmt->execute([$submissionId, (int) $student['id'], (string) $student['full_name'], (string) $student['student_id']]);
        foreach ($selectedMembersById as $memberId => $memberRow) {
            $memberStmt->execute([$submissionId, $memberId, (string) $memberRow['full_name'], (string) $memberRow['student_id']]);
            create_notification('student', $memberId, 'Team submission shared', $student['full_name'] . ' added you to the team submission for ' . $selectedActivity['title'] . ' in ' . $selectedActivity['subject_name'] . '.', 'info');
        }
        create_notification('teacher', (int) $selectedActivity['teacher_id'], 'New activity submission', 'A team submitted ' . $selectedActivity['title'] . ' for ' . $selectedActivity['subject_name'] . '.', 'info');
        create_notification('student', (int) $student['id'], 'Submission received', 'Your submission for ' . $selectedActivity['title'] . ' has been received.', 'success');
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

$title = 'Submit Project';
$subtitle = 'Activity-based submission flow with searchable team members';
require_once __DIR__ . '/../backend/partials/header.php';
?>
<section class="workspace-shell student-history-shell">
  <div class="workspace-head">
    <div>
      <div class="eyebrow">Student portal</div>
      <h2>Activity-based submission</h2>
      <p class="muted">Teachers now publish submission activities inside each subject. Choose an activity, review its restrictions, search real classmates by ID or name, and submit once for the whole team.</p>
    </div>
    <div class="student-history-actions"><a class="btn btn-secondary" href="<?= h(url('student/my_submissions.php')) ?>">Open history</a></div>
  </div>

  <?php if (!$allowed): ?>
    <div class="card empty-state">Your account is not currently allowed to submit. Contact your teacher or administrator.</div>
  <?php elseif (!$activities): ?>
    <div class="card empty-state">No published submission activity is visible for your assigned subjects yet.</div>
  <?php else: ?>
  <div class="grid cols-2">
    <article class="card">
      <form method="post" enctype="multipart/form-data" class="form-grid" id="activity-submit-form">
        <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
        <div class="full">
          <label>Submission activity</label>
          <select name="activity_id" id="activity-select" required>
            <option value="">Choose an activity</option>
            <?php foreach ($activities as $activity): ?>
              <option value="<?= (int) $activity['id'] ?>"
                      data-mode="<?= h($activity['submission_mode']) ?>"
                      data-min-members="<?= (int) $activity['min_members'] ?>"
                      data-max-members="<?= (int) $activity['max_members'] ?>"
                      data-require-file="<?= !empty($activity['require_file']) ? '1' : '0' ?>"
                      data-require-demo="<?= !empty($activity['require_demo_access']) ? '1' : '0' ?>"
                      data-require-notes="<?= !empty($activity['require_notes']) ? '1' : '0' ?>">
                <?= h($activity['subject_code'] . ' · ' . $activity['title'] . ' · ' . ($activity['activity_window']['label'] ?? 'Open')) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label>Project / repository URL</label>
          <input name="project_url" placeholder="https://github.com/your-team/project">
        </div>
        <div>
          <label>Live / demo URL</label>
          <input name="video_url" placeholder="https://your-demo.example.com">
        </div>
        <div>
          <label>Submission title</label>
          <input name="assigned_system" placeholder="Final web application build">
        </div>
        <div>
          <label>Client / company name</label>
          <input name="company_name" placeholder="Optional client or company">
        </div>
        <div class="full">
          <label>Contact email</label>
          <input name="contact_email" type="email" required placeholder="leader@example.com">
        </div>
        <div class="full" id="member-search-block">
          <div class="split-header"><label style="margin:0;">Members</label><div class="muted small" id="member-help">Search by student ID, name, or email.</div></div>
          <div class="form-grid" style="grid-template-columns:1fr auto; align-items:start;">
            <div style="position:relative;">
              <input type="text" id="member-search-input" placeholder="Search teammates by student ID or name">
              <div class="table-card" id="member-results" hidden style="position:absolute; z-index:12; inset:auto 0 0 0; transform:translateY(100%); max-height:260px; overflow:auto;"></div>
            </div>
            <button class="btn btn-secondary" type="button" id="clear-members-btn">Clear</button>
          </div>
          <div id="member-chip-list" class="timeline-list" style="margin-top:12px;"></div>
        </div>
        <div class="full">
          <label>Notes / details</label>
          <textarea name="notes" id="notes-input" placeholder="Add scope, credentials note, branch name, known limitations, or revision summary."></textarea>
        </div>
        <div id="demo-block">
          <label>Demo username</label>
          <input name="demo_username" placeholder="admin-demo">
        </div>
        <div id="demo-password-block">
          <label>Demo password</label>
          <input name="demo_password" placeholder="temporary password">
        </div>
        <div class="full">
          <label>Attachment</label>
          <input type="file" name="attachment" accept=".pdf,.jpg,.jpeg,.png,.webp">
        </div>
        <div class="full form-actions"><button class="btn" type="submit">Create submission</button></div>
      </form>
    </article>

    <article class="card">
      <h3 class="section-title">Activity guide</h3>
      <div class="timeline-list">
        <?php foreach ($activities as $activity): ?>
          <div class="timeline-item">
            <strong><?= h($activity['title']) ?></strong>
            <p><?= h($activity['subject_name']) ?> · <?= h($activity['activity_window']['label'] ?? 'Open') ?></p>
            <div class="muted small"><?= h(ucfirst((string) $activity['submission_mode'])) ?> · Members <?= (int) $activity['min_members'] ?>–<?= (int) $activity['max_members'] ?></div>
          </div>
        <?php endforeach; ?>
      </div>
      <?php if ($historyRows): ?>
        <div class="callout" style="margin-top:16px;">
          <strong>Shared team records</strong>
          <div class="muted small"><?= count($historyRows) ?> projects are already visible in your account.</div>
        </div>
      <?php endif; ?>
    </article>
  </div>
  <?php endif; ?>
</section>
<script>
(() => {
  const form = document.getElementById('activity-submit-form');
  if (!form) return;
  const activitySelect = document.getElementById('activity-select');
  const searchInput = document.getElementById('member-search-input');
  const resultsBox = document.getElementById('member-results');
  const chipList = document.getElementById('member-chip-list');
  const clearBtn = document.getElementById('clear-members-btn');
  const memberBlock = document.getElementById('member-search-block');
  const memberHelp = document.getElementById('member-help');
  const notesInput = document.getElementById('notes-input');
  const demoBlock = document.getElementById('demo-block');
  const demoPasswordBlock = document.getElementById('demo-password-block');
  const selected = new Map();
  let lastController = null;

  const syncHiddenInputs = () => {
    form.querySelectorAll('input[name="member_ids[]"]').forEach((el) => el.remove());
    selected.forEach((item) => {
      const input = document.createElement('input');
      input.type = 'hidden';
      input.name = 'member_ids[]';
      input.value = item.id;
      form.appendChild(input);
    });
  };

  const renderChips = () => {
    chipList.innerHTML = '';
    if (!selected.size) {
      chipList.innerHTML = '<div class="empty-state">No additional members selected yet. Search and click a student to add them.</div>';
      syncHiddenInputs();
      return;
    }
    selected.forEach((item) => {
      const row = document.createElement('div');
      row.className = 'timeline-item';
      row.innerHTML = `<div class="split-header"><div><strong>${item.full_name}</strong><div class="muted small">${item.student_id} · ${item.section_name}</div></div><button type="button" class="btn btn-danger btn-sm">Remove</button></div>`;
      row.querySelector('button').addEventListener('click', () => {
        selected.delete(String(item.id));
        renderChips();
      });
      chipList.appendChild(row);
    });
    syncHiddenInputs();
  };

  const applyActivityRules = () => {
    const option = activitySelect.selectedOptions[0];
    const mode = option?.dataset.mode || 'team';
    memberBlock.hidden = mode !== 'team';
    memberHelp.textContent = mode === 'team'
      ? `Search teammates. This activity allows ${option?.dataset.minMembers || 1} to ${option?.dataset.maxMembers || 5} members.`
      : 'This activity is individual. No team members are required.';
    demoBlock.hidden = option?.dataset.requireDemo !== '1';
    demoPasswordBlock.hidden = option?.dataset.requireDemo !== '1';
    notesInput.required = option?.dataset.requireNotes === '1';
    if (mode !== 'team') {
      selected.clear();
      renderChips();
    }
  };

  const showResults = (items) => {
    resultsBox.innerHTML = '';
    if (!items.length) {
      resultsBox.innerHTML = '<div class="empty-state" style="padding:12px;">No matching students found.</div>';
      resultsBox.hidden = false;
      return;
    }
    items.forEach((item) => {
      const button = document.createElement('button');
      button.type = 'button';
      button.className = 'btn btn-ghost';
      button.style.width = '100%';
      button.style.justifyContent = 'flex-start';
      button.style.borderRadius = '0';
      button.innerHTML = `<div><strong>${item.full_name}</strong><div class="muted small">${item.student_id} · ${item.section_name}${item.email ? ' · ' + item.email : ''}</div></div>`;
      button.addEventListener('click', () => {
        selected.set(String(item.id), item);
        searchInput.value = '';
        resultsBox.hidden = true;
        renderChips();
      });
      resultsBox.appendChild(button);
    });
    resultsBox.hidden = false;
  };

  const searchMembers = async () => {
    const activityId = activitySelect.value;
    const query = searchInput.value.trim();
    if (!activityId || query.length < 2) {
      resultsBox.hidden = true;
      return;
    }
    if (lastController) lastController.abort();
    lastController = new AbortController();
    const exclude = Array.from(selected.keys()).join(',');
    const response = await fetch(`member_search.php?activity_id=${encodeURIComponent(activityId)}&q=${encodeURIComponent(query)}&exclude=${encodeURIComponent(exclude)}`, {signal: lastController.signal});
    if (!response.ok) {
      resultsBox.hidden = true;
      return;
    }
    const data = await response.json();
    showResults(Array.isArray(data.items) ? data.items : []);
  };

  activitySelect.addEventListener('change', applyActivityRules);
  searchInput.addEventListener('input', () => {
    window.clearTimeout(searchInput._timer);
    searchInput._timer = window.setTimeout(() => { searchMembers().catch(() => {}); }, 220);
  });
  clearBtn.addEventListener('click', () => {
    selected.clear();
    renderChips();
    resultsBox.hidden = true;
  });
  document.addEventListener('click', (event) => {
    if (!resultsBox.contains(event.target) && event.target !== searchInput) {
      resultsBox.hidden = true;
    }
  });
  renderChips();
  applyActivityRules();
})();
</script>
<?php require_once __DIR__ . '/../backend/partials/footer.php'; ?>
