<?php
if (defined('FILE_TEACHER_SUBJECT_VIEW_PHP_LOADED')) { return; }
define('FILE_TEACHER_SUBJECT_VIEW_PHP_LOADED', true);

require_once __DIR__ . '/../backend/config/app.php';
require_once __DIR__ . '/../backend/helpers/uploads.php';
require_role('teacher');
$teacher = current_user();
$pdo = pdo();
$subjectId = (int) ($_GET['id'] ?? 0);
$subject = fetch_subject_detail($subjectId);
if (!$subject || (int) $subject['teacher_id'] !== (int) $teacher['id']) {
    set_flash('error', 'Subject not found.');
    redirect_to('teacher/subjects.php');
}
$allSections = available_subject_sections();
$allowedSectionIds = array_map(static fn(array $row): int => (int) $row['id'], $allSections);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'save_deadline') {
        $deadline = trim($_POST['submission_deadline'] ?? '') ?: null;
        $warningHours = max(1, (int) ($_POST['deadline_warning_hours'] ?? 72));
        $pdo->prepare('UPDATE subjects SET submission_deadline = ?, deadline_warning_hours = ?, deadline_warning_sent_at = NULL, deadline_locked_notice_sent_at = NULL WHERE id = ? AND teacher_id = ?')->execute([$deadline, $warningHours, $subjectId, (int) $teacher['id']]);
        set_flash('success', 'Default deadline settings saved.');
    }

    if ($action === 'toggle_lock') {
        $locked = isset($_POST['teacher_submission_locked']) ? 1 : 0;
        $note = trim($_POST['teacher_submission_lock_note'] ?? '') ?: null;
        $pdo->prepare('UPDATE subjects SET teacher_submission_locked = ?, teacher_submission_lock_note = ? WHERE id = ? AND teacher_id = ?')->execute([$locked, $note, $subjectId, (int) $teacher['id']]);
        set_flash('success', $locked ? 'Manual subject lock enabled.' : 'Manual subject lock removed.');
    }

    if ($action === 'save_section_deadline') {
        $mappingId = (int) ($_POST['mapping_id'] ?? 0);
        $deadline = trim($_POST['section_submission_deadline'] ?? '') ?: null;
        $warningHours = max(1, (int) ($_POST['section_deadline_warning_hours'] ?? 72));
        $pdo->prepare('UPDATE section_subjects ss JOIN subjects subj ON subj.id = ss.subject_id SET ss.submission_deadline = ?, ss.deadline_warning_hours = ?, ss.deadline_warning_sent_at = NULL, ss.deadline_locked_notice_sent_at = NULL WHERE ss.id = ? AND subj.teacher_id = ?')->execute([$deadline, $warningHours, $mappingId, (int) $teacher['id']]);
        set_flash('success', 'Section deadline override saved.');
    }

    if ($action === 'save_section_mapping') {
        $sectionIds = array_values(array_unique(array_map('intval', $_POST['section_ids'] ?? [])));
        $sectionIds = array_values(array_filter($sectionIds, static fn(int $id): bool => in_array($id, $allowedSectionIds, true)));
        if (!$sectionIds) {
            set_flash('error', 'Select at least one valid active section.');
            redirect_to('teacher/subject_view.php?id=' . $subjectId);
        }
        $pdo->beginTransaction();
        try {
            $current = $pdo->prepare('SELECT section_id, submission_deadline, deadline_warning_hours, deadline_warning_sent_at, deadline_locked_notice_sent_at FROM section_subjects WHERE subject_id = ?');
            $current->execute([$subjectId]);
            $existing = [];
            foreach ($current->fetchAll() as $row) {
                $existing[(int) $row['section_id']] = $row;
            }
            $pdo->prepare('DELETE FROM section_subjects WHERE subject_id = ?')->execute([$subjectId]);
            $ins = $pdo->prepare('INSERT INTO section_subjects (section_id, subject_id, submission_deadline, deadline_warning_hours, deadline_warning_sent_at, deadline_locked_notice_sent_at) VALUES (?, ?, ?, ?, ?, ?)');
            foreach ($sectionIds as $mappedSectionId) {
                $row = $existing[$mappedSectionId] ?? ['submission_deadline' => null, 'deadline_warning_hours' => 72, 'deadline_warning_sent_at' => null, 'deadline_locked_notice_sent_at' => null];
                $ins->execute([$mappedSectionId, $subjectId, $row['submission_deadline'], (int) ($row['deadline_warning_hours'] ?? 72), $row['deadline_warning_sent_at'], $row['deadline_locked_notice_sent_at']]);
            }
            $pdo->commit();
            set_flash('success', 'Section mapping updated.');
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            set_flash('error', 'Unable to update sections right now.');
        }
    }

    if ($action === 'upload_resource') {
        $title = trim($_POST['resource_title'] ?? '');
        $visible = isset($_POST['is_visible_to_students']) ? 1 : 0;
        if ($title === '' || empty($_FILES['resource_file']['name'])) {
            set_flash('error', 'Provide a title and choose a file.');
            redirect_to('teacher/subject_view.php?id=' . $subjectId . '#resources-tab');
        }
        $upload = store_uploaded_file($_FILES['resource_file'], 'uploads/subject_resources', 'subject-' . $subjectId, [
            'application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx'
        ], 10 * 1024 * 1024, ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'docx', 'pptx', 'xlsx']);
        if (!$upload['ok']) {
            set_flash('error', 'Upload failed. Use PDF, image, DOCX, PPTX, or XLSX up to 10 MB.');
            redirect_to('teacher/subject_view.php?id=' . $subjectId . '#resources-tab');
        }
        $pdo->prepare('INSERT INTO subject_resources (subject_id, title, file_path, is_visible_to_students, created_by_teacher_id) VALUES (?, ?, ?, ?, ?)')->execute([$subjectId, $title, $upload['path'], $visible, (int) $teacher['id']]);
        set_flash('success', 'Resource uploaded.');
    }

    if ($action === 'delete_resource') {
        $resourceId = (int) ($_POST['resource_id'] ?? 0);
        $stmt = $pdo->prepare('SELECT * FROM subject_resources WHERE id = ? AND subject_id = ? LIMIT 1');
        $stmt->execute([$resourceId, $subjectId]);
        $resource = $stmt->fetch();
        if ($resource) {
            @unlink(APP_ROOT . '/' . ltrim((string) $resource['file_path'], '/'));
            $pdo->prepare('DELETE FROM subject_resources WHERE id = ?')->execute([$resourceId]);
        }
        set_flash('success', 'Resource removed.');
    }

    if ($action === 'save_activity') {
        $activityId = (int) ($_POST['activity_id'] ?? 0);
        $titleValue = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '') ?: null;
        $status = in_array(($_POST['status'] ?? 'draft'), ['draft', 'published', 'closed', 'archived'], true) ? $_POST['status'] : 'draft';
        $opensAt = trim($_POST['opens_at'] ?? '') ?: null;
        $deadlineAt = trim($_POST['deadline_at'] ?? '') ?: null;
        $allowLate = isset($_POST['allow_late']) ? 1 : 0;
        $lateUntil = trim($_POST['late_until'] ?? '') ?: null;
        $submissionMode = (($_POST['submission_mode'] ?? 'team') === 'individual') ? 'individual' : 'team';
        $minMembers = max(1, (int) ($_POST['min_members'] ?? 1));
        $maxMembers = max($minMembers, (int) ($_POST['max_members'] ?? max(3, $minMembers)));
        $allowResubmission = isset($_POST['allow_resubmission']) ? 1 : 0;
        $maxResubmissions = max(1, (int) ($_POST['max_resubmissions'] ?? 1));
        $requireRepository = isset($_POST['require_repository']) ? 1 : 0;
        $requireLiveUrl = isset($_POST['require_live_url']) ? 1 : 0;
        $requireFile = isset($_POST['require_file']) ? 1 : 0;
        $requireDemoAccess = isset($_POST['require_demo_access']) ? 1 : 0;
        $requireNotes = isset($_POST['require_notes']) ? 1 : 0;
        $activitySectionIds = array_values(array_unique(array_map('intval', $_POST['activity_section_ids'] ?? [])));
        $activitySectionIds = array_values(array_filter($activitySectionIds, static fn(int $id): bool => in_array($id, $allowedSectionIds, true)));
        if ($titleValue === '') {
            set_flash('error', 'Activity title is required.');
            redirect_to('teacher/subject_view.php?id=' . $subjectId . '#activities-tab');
        }
        $assignedSectionIds = array_map(static fn(array $row): int => (int) $row['section_id'], subject_sections_with_deadlines($subjectId));
        if ($activitySectionIds) {
            foreach ($activitySectionIds as $sid) {
                if (!in_array($sid, $assignedSectionIds, true)) {
                    set_flash('error', 'Only sections already assigned to this subject can be selected for an activity.');
                    redirect_to('teacher/subject_view.php?id=' . $subjectId . '#activities-tab');
                }
            }
        }
        $pdo->beginTransaction();
        try {
            if ($activityId > 0) {
                $currentActivity = teacher_activity_detail($activityId, (int) $teacher['id']);
                if (!$currentActivity || (int) $currentActivity['subject_id'] !== $subjectId) {
                    throw new RuntimeException('Activity not found.');
                }
                $pdo->prepare('UPDATE submission_activities SET title = ?, description = ?, status = ?, opens_at = ?, deadline_at = ?, allow_late = ?, late_until = ?, submission_mode = ?, min_members = ?, max_members = ?, allow_resubmission = ?, max_resubmissions = ?, require_file = ?, require_repository = ?, require_live_url = ?, require_demo_access = ?, require_notes = ? WHERE id = ?')->execute([
                    $titleValue, $description, $status, $opensAt, $deadlineAt, $allowLate, $lateUntil, $submissionMode, $minMembers, $maxMembers, $allowResubmission, $maxResubmissions, $requireFile, $requireRepository, $requireLiveUrl, $requireDemoAccess, $requireNotes, $activityId
                ]);
                $pdo->prepare('DELETE FROM submission_activity_sections WHERE activity_id = ?')->execute([$activityId]);
            } else {
                $pdo->prepare('INSERT INTO submission_activities (subject_id, title, description, status, opens_at, deadline_at, allow_late, late_until, submission_mode, min_members, max_members, allow_resubmission, max_resubmissions, require_file, require_repository, require_live_url, require_demo_access, require_notes, created_by_teacher_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')->execute([
                    $subjectId, $titleValue, $description, $status, $opensAt, $deadlineAt, $allowLate, $lateUntil, $submissionMode, $minMembers, $maxMembers, $allowResubmission, $maxResubmissions, $requireFile, $requireRepository, $requireLiveUrl, $requireDemoAccess, $requireNotes, (int) $teacher['id']
                ]);
                $activityId = (int) $pdo->lastInsertId();
            }
            if ($activitySectionIds) {
                $mapStmt = $pdo->prepare('INSERT INTO submission_activity_sections (activity_id, section_id) VALUES (?, ?)');
                foreach ($activitySectionIds as $mappedSectionId) {
                    $mapStmt->execute([$activityId, $mappedSectionId]);
                }
            }
            $pdo->commit();
            set_flash('success', 'Submission activity saved.');
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            set_flash('error', 'Unable to save the activity right now.');
        }
    }

    if ($action === 'activity_state') {
        $activityId = (int) ($_POST['activity_id'] ?? 0);
        $state = $_POST['state'] ?? '';
        $activity = teacher_activity_detail($activityId, (int) $teacher['id']);
        if ($activity && (int) $activity['subject_id'] === $subjectId) {
            if ($state === 'toggle_lock') {
                $next = empty($activity['is_locked']) ? 1 : 0;
                $pdo->prepare('UPDATE submission_activities SET is_locked = ?, lock_note = ? WHERE id = ?')->execute([$next, $next ? (trim($_POST['lock_note'] ?? '') ?: 'Locked by teacher.') : null, $activityId]);
                set_flash('success', $next ? 'Activity locked.' : 'Activity unlocked.');
            } elseif (in_array($state, ['draft', 'published', 'closed', 'archived'], true)) {
                $pdo->prepare('UPDATE submission_activities SET status = ? WHERE id = ?')->execute([$state, $activityId]);
                set_flash('success', 'Activity status updated.');
            }
        }
    }

    redirect_to('teacher/subject_view.php?id=' . $subjectId);
}

$subject = fetch_subject_detail($subjectId);
$sections = subject_sections_with_deadlines($subjectId);
$resources = subject_resources_for_role($subjectId, false);
$activities = subject_activities_for_teacher($subjectId, (int) $teacher['id']);
$submissionStmt = $pdo->prepare('SELECT sub.id, st.full_name, sec.section_name, sub.status, sub.grade, sub.submitted_at, sub.assigned_system, COALESCE(act.title, "General submission") AS activity_title FROM submissions sub JOIN students st ON st.id = sub.student_id JOIN sections sec ON sec.id = sub.section_id LEFT JOIN submission_activities act ON act.id = sub.activity_id WHERE sub.subject_id = ? ORDER BY sub.submitted_at DESC LIMIT 12');
$submissionStmt->execute([$subjectId]);
$submissions = $submissionStmt->fetchAll();
$totalStudents = array_sum(array_map(static fn(array $row): int => (int) ($row['total_students'] ?? 0), $sections));
$assignedSectionIds = array_map(static fn(array $row): int => (int) $row['section_id'], $sections);
$title = 'Teacher Subject Workspace';
$subtitle = 'Manage subject restrictions, resources, and unlimited submission activities';
require_once __DIR__ . '/../backend/partials/header.php';
?>
<section class="workspace-shell">
  <div class="workspace-head">
    <div>
      <div class="eyebrow">Teacher subject workspace</div>
      <h2><?= h($subject['subject_name']) ?></h2>
      <p class="muted"><?= h($subject['subject_code']) ?> · <?= h($subject['description'] ?: 'No description yet.') ?></p>
    </div>
    <div class="form-actions"><a class="btn btn-secondary" href="<?= h(url('teacher/subjects.php')) ?>">Back to subjects</a></div>
  </div>

  <div class="workspace-stat-grid" style="margin-bottom:18px;">
    <div class="segment"><span class="muted small">Assigned sections</span><strong><?= count($sections) ?></strong></div>
    <div class="segment"><span class="muted small">Students covered</span><strong><?= $totalStudents ?></strong></div>
    <div class="segment"><span class="muted small">Resources</span><strong><?= count($resources) ?></strong></div>
    <div class="segment"><span class="muted small">Activities</span><strong><?= count($activities) ?></strong></div>
  </div>

  <div class="workspace-tabs" data-workspace-tabs>
    <div class="workspace-tabbar" role="tablist">
      <button type="button" class="workspace-tab is-active" data-workspace-target="overview-tab">Overview</button>
      <button type="button" class="workspace-tab" data-workspace-target="activities-tab">Submission activities</button>
      <button type="button" class="workspace-tab" data-workspace-target="sections-tab">Sections &amp; deadlines</button>
      <button type="button" class="workspace-tab" data-workspace-target="resources-tab">Resources</button>
      <button type="button" class="workspace-tab" data-workspace-target="submissions-tab">Submissions</button>
      <button type="button" class="workspace-tab" data-workspace-target="settings-tab">Settings</button>
    </div>

    <section id="overview-tab" class="workspace-panel is-active">
      <div class="grid cols-2">
        <article class="card">
          <div class="split-header"><h3 class="section-title">Current status</h3><?= status_badge($subject['status']) ?></div>
          <div style="margin-bottom:12px;"><?= deadline_badge_html($subject) ?></div>
          <?php if (!empty($subject['teacher_submission_locked'])): ?><div class="callout"><strong>Manual lock enabled.</strong><div class="muted small"><?= h($subject['teacher_submission_lock_note'] ?: 'Submissions are manually closed for this subject.') ?></div></div><?php endif; ?>
          <div class="info-list" style="margin-top:12px;">
            <div class="row"><span>Teacher</span><strong><?= h($subject['teacher_name']) ?></strong></div>
            <div class="row"><span>School year</span><strong><?= h($subject['school_year']) ?></strong></div>
            <div class="row"><span>Semester</span><strong><?= h($subject['semester']) ?></strong></div>
          </div>
        </article>
        <article class="card">
          <h3 class="section-title">Teacher workflow</h3>
          <div class="timeline-list">
            <div class="timeline-item"><strong>1. Assign sections</strong><p>Choose which classes belong to this subject.</p></div>
            <div class="timeline-item"><strong>2. Create activities</strong><p>Publish unlimited checkpoints like Proposal, Demo, Final Upload, or Revision.</p></div>
            <div class="timeline-item"><strong>3. Apply restrictions</strong><p>Set deadlines, section limits, team rules, and lock/reopen behavior per activity.</p></div>
            <div class="timeline-item"><strong>4. Review submissions</strong><p>Track progress activity by activity instead of mixing everything into one subject slot.</p></div>
          </div>
        </article>
      </div>
    </section>

    <section id="activities-tab" class="workspace-panel" hidden>
      <div class="grid cols-2">
        <article class="card">
          <h3 class="section-title">Create submission activity</h3>
          <form method="post" class="form-grid">
            <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
            <input type="hidden" name="action" value="save_activity">
            <div><label>Title</label><input name="title" placeholder="Proposal submission" required></div>
            <div><label>Status</label><select name="status"><option value="draft">Draft</option><option value="published">Published</option><option value="closed">Closed</option></select></div>
            <div class="full"><label>Description</label><textarea name="description" placeholder="Explain what students should submit, the format, and the restrictions."></textarea></div>
            <div><label>Opens at</label><input type="datetime-local" name="opens_at"></div>
            <div><label>Deadline</label><input type="datetime-local" name="deadline_at"></div>
            <div><label>Submission mode</label><select name="submission_mode"><option value="team">Team</option><option value="individual">Individual</option></select></div>
            <div><label>Max members</label><input type="number" name="max_members" min="1" value="5"></div>
            <div><label>Min members</label><input type="number" name="min_members" min="1" value="1"></div>
            <div><label>Max resubmissions</label><input type="number" name="max_resubmissions" min="1" value="1"></div>
            <div class="full checkbox-grid">
              <?php foreach ($allSections as $section): ?>
                <?php if (!in_array((int) $section['id'], $assignedSectionIds, true)) { continue; } ?>
                <label class="checkbox-card"><input type="checkbox" name="activity_section_ids[]" value="<?= (int) $section['id'] ?>"> <span><?= h($section['section_name']) ?></span></label>
              <?php endforeach; ?>
            </div>
            <div class="full muted small">Leave all section boxes empty to allow every section already assigned to this subject.</div>
            <div class="full form-grid" style="grid-template-columns:repeat(auto-fit, minmax(170px, 1fr));">
              <label><input type="checkbox" name="allow_late" value="1"> Allow late submission window</label>
              <label><input type="checkbox" name="allow_resubmission" value="1"> Allow resubmission</label>
              <label><input type="checkbox" name="require_repository" value="1" checked> Require repository/project link</label>
              <label><input type="checkbox" name="require_live_url" value="1" checked> Require live/demo URL</label>
              <label><input type="checkbox" name="require_file" value="1"> Require file upload</label>
              <label><input type="checkbox" name="require_demo_access" value="1"> Require demo credentials</label>
              <label><input type="checkbox" name="require_notes" value="1"> Require notes/details</label>
            </div>
            <div><label>Late until</label><input type="datetime-local" name="late_until"></div>
            <div class="full form-actions"><button class="btn" type="submit">Save activity</button></div>
          </form>
        </article>
        <article class="card">
          <div class="split-header">
            <h3 class="section-title">Activity list</h3>
            <div class="form-actions">
              <a class="btn btn-outline" href="<?= h(url('teacher/export_activity_report.php?' . http_build_query(['subject_id' => (int) $subjectId, 'format' => 'xlsx']))) ?>">Export Excel</a>
              <a class="btn btn-secondary" href="<?= h(url('teacher/export_activity_report.php?' . http_build_query(['subject_id' => (int) $subjectId, 'format' => 'csv']))) ?>">Export CSV</a>
            </div>
          </div>
          <div class="timeline-list">
            <?php foreach ($activities as $activity): ?>
              <?php $window = activity_window($activity); ?>
              <div class="timeline-item">
                <div class="split-header" style="gap:12px; align-items:flex-start;">
                  <div>
                    <strong><?= h($activity['title']) ?></strong>
                    <div class="muted small"><?= h($activity['description'] ?: 'No instructions yet.') ?></div>
                    <div class="muted small"><?= h(activity_sections_label((int) $activity['id'])) ?></div>
                  </div>
                  <div><?= status_badge((string) $activity['status']) ?></div>
                </div>
                <div class="muted small" style="margin:8px 0;"><?= h($window['label']) ?> · <?= (int) $activity['total_submissions'] ?> submissions · <?= h(ucfirst((string) $activity['submission_mode'])) ?></div>
                <div class="form-actions">
                  <form method="post" class="inline-icon-form">
                    <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
                    <input type="hidden" name="action" value="activity_state">
                    <input type="hidden" name="activity_id" value="<?= (int) $activity['id'] ?>">
                    <input type="hidden" name="state" value="<?= $activity['status'] === 'published' ? 'closed' : 'published' ?>">
                    <button class="btn btn-secondary" type="submit"><?= $activity['status'] === 'published' ? 'Close' : 'Publish' ?></button>
                  </form>
                  <form method="post" class="inline-icon-form">
                    <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
                    <input type="hidden" name="action" value="activity_state">
                    <input type="hidden" name="activity_id" value="<?= (int) $activity['id'] ?>">
                    <input type="hidden" name="state" value="toggle_lock">
                    <input type="hidden" name="lock_note" value="Temporarily locked by teacher.">
                    <button class="btn btn-outline" type="submit"><?= !empty($activity['is_locked']) ? 'Unlock' : 'Lock' ?></button>
                  </form>
                  <form method="post" class="inline-icon-form">
                    <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
                    <input type="hidden" name="action" value="activity_state">
                    <input type="hidden" name="activity_id" value="<?= (int) $activity['id'] ?>">
                    <input type="hidden" name="state" value="archived">
                    <button class="btn btn-danger" type="submit">Archive</button>
                  </form>
                </div>
              </div>
            <?php endforeach; ?>
            <?php if (!$activities): ?><div class="empty-state">No activities yet. Create one so students can submit by checkpoint instead of directly on the subject.</div><?php endif; ?>
          </div>
        </article>
      </div>
    </section>

    <section id="sections-tab" class="workspace-panel" hidden>
      <div class="grid cols-2">
        <article class="card">
          <h3 class="section-title">Assigned sections</h3>
          <form method="post">
            <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
            <input type="hidden" name="action" value="save_section_mapping">
            <div class="checkbox-grid">
              <?php foreach ($allSections as $section): ?>
                <label class="checkbox-card"><input type="checkbox" name="section_ids[]" value="<?= (int) $section['id'] ?>" <?= in_array((int) $section['id'], $assignedSectionIds, true) ? 'checked' : '' ?>> <span><?= h($section['section_name']) ?></span></label>
              <?php endforeach; ?>
            </div>
            <div class="form-actions"><button class="btn" type="submit">Update sections</button></div>
          </form>
        </article>
        <article class="card">
          <h3 class="section-title">Default deadline</h3>
          <form method="post" class="form-grid">
            <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
            <input type="hidden" name="action" value="save_deadline">
            <div><label>Submission deadline</label><input type="datetime-local" name="submission_deadline" value="<?= h(format_deadline_for_input($subject['submission_deadline'] ?? null)) ?>"></div>
            <div><label>Warning hours</label><input type="number" name="deadline_warning_hours" min="1" value="<?= (int) ($subject['deadline_warning_hours'] ?? 72) ?>"></div>
            <div class="full form-actions"><button class="btn" type="submit">Save default deadline</button></div>
          </form>
        </article>
      </div>
      <div class="card" style="margin-top:18px;">
        <h3 class="section-title">Section-specific overrides</h3>
        <div class="table-wrap"><table class="table-redesign"><thead><tr><th>Section</th><th>Students</th><th>Override</th><th>Action</th></tr></thead><tbody>
          <?php foreach ($sections as $section): ?>
            <tr>
              <td><strong><?= h($section['section_name']) ?></strong><div class="muted small"><?= h($section['status']) ?></div></td>
              <td><?= (int) $section['total_students'] ?></td>
              <td><?= h($section['submission_deadline'] ?: 'Using default deadline') ?><div class="muted small">Warning <?= (int) ($section['deadline_warning_hours'] ?? 72) ?>h</div></td>
              <td>
                <form method="post" class="form-grid" style="grid-template-columns:1fr 120px auto; align-items:end;">
                  <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
                  <input type="hidden" name="action" value="save_section_deadline">
                  <input type="hidden" name="mapping_id" value="<?= (int) $section['mapping_id'] ?>">
                  <div><label>Override deadline</label><input type="datetime-local" name="section_submission_deadline" value="<?= h(format_deadline_for_input($section['submission_deadline'] ?? null)) ?>"></div>
                  <div><label>Warning hours</label><input type="number" name="section_deadline_warning_hours" min="1" value="<?= (int) ($section['deadline_warning_hours'] ?? 72) ?>"></div>
                  <div><button class="icon-action" type="submit" title="save override" aria-label="save override"><i class="bi bi-check2"></i></button></div>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$sections): ?><tr><td colspan="4" class="empty-state">No section assigned yet.</td></tr><?php endif; ?>
        </tbody></table></div>
      </div>
    </section>

    <section id="resources-tab" class="workspace-panel" hidden>
      <div class="grid cols-2">
        <article class="card">
          <h3 class="section-title">Upload resource</h3>
          <form method="post" enctype="multipart/form-data" class="form-grid">
            <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
            <input type="hidden" name="action" value="upload_resource">
            <div class="full"><label>Title</label><input name="resource_title" required></div>
            <div class="full"><label>File</label><input type="file" name="resource_file" required></div>
            <div class="full"><label><input type="checkbox" name="is_visible_to_students" value="1" checked> Visible to students</label></div>
            <div class="full form-actions"><button class="btn" type="submit">Upload resource</button></div>
          </form>
        </article>
        <article class="card">
          <h3 class="section-title">Resource notes</h3>
          <div class="callout">Use this area for project briefs, rubrics, datasets, templates, and other files your students should review before submitting.</div>
        </article>
      </div>
      <div class="card" style="margin-top:18px;">
        <h3 class="section-title">Uploaded resources</h3>
        <div class="table-wrap"><table class="table-redesign"><thead><tr><th>Title</th><th>Visibility</th><th>File</th><th>Action</th></tr></thead><tbody>
          <?php foreach ($resources as $resource): ?>
            <tr>
              <td><strong><?= h($resource['title']) ?></strong><div class="muted small"><?= h($resource['created_at']) ?></div></td>
              <td><?= $resource['is_visible_to_students'] ? '<span class="status success">Visible to students</span>' : '<span class="status warning">Teacher only</span>' ?></td>
              <td><a class="muted-link" href="<?= h(url($resource['file_path'])) ?>" target="_blank"><?= h(basename((string) $resource['file_path'])) ?></a></td>
              <td class="text-end"><form method="post" class="inline-icon-form"><input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>"><input type="hidden" name="action" value="delete_resource"><input type="hidden" name="resource_id" value="<?= (int) $resource['id'] ?>"><button class="icon-action danger" type="submit" title="delete resource" aria-label="delete resource"><i class="bi bi-trash3"></i></button></form></td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$resources): ?><tr><td colspan="4" class="empty-state">No resource uploaded yet.</td></tr><?php endif; ?>
        </tbody></table></div>
      </div>
    </section>

    <section id="submissions-tab" class="workspace-panel" hidden>
      <div class="review-card-grid">
        <?php foreach ($submissions as $submission): ?>
          <article class="card review-queue-card">
            <div class="split-header"><div><h3 class="section-title"><?= h($submission['full_name']) ?></h3><div class="muted small"><?= h($submission['activity_title'] ?? $submission['title'] ?? 'General submission') ?> · <?= h($submission['section_name']) ?></div></div><?= status_badge($submission['status']) ?></div>
            <p class="muted"><?= h($submission['assigned_system']) ?></p>
            <div class="muted small">Submitted <?= h($submission['submitted_at']) ?> · Grade <?= h($submission['grade'] ?: '—') ?></div>
            <div class="form-actions"><a class="btn btn-secondary" href="<?= h(url('teacher/submission_view.php?id=' . (int) $submission['id'])) ?>">Open review</a></div>
          </article>
        <?php endforeach; ?>
        <?php if (!$submissions): ?><div class="card empty-state">No submission yet for this subject.</div><?php endif; ?>
      </div>
    </section>

    <section id="settings-tab" class="workspace-panel" hidden>
      <div class="grid cols-2">
        <article class="card">
          <h3 class="section-title">Manual subject lock</h3>
          <form method="post" class="form-grid">
            <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
            <input type="hidden" name="action" value="toggle_lock">
            <div class="full"><label><input type="checkbox" name="teacher_submission_locked" value="1" <?= !empty($subject['teacher_submission_locked']) ? 'checked' : '' ?>> Manually lock submissions for this subject</label></div>
            <div class="full"><label>Lock reason</label><textarea name="teacher_submission_lock_note" placeholder="Explain why the subject is currently locked."><?= h($subject['teacher_submission_lock_note']) ?></textarea></div>
            <div class="full form-actions"><button class="btn" type="submit">Save manual lock</button></div>
          </form>
        </article>
        <article class="card">
          <h3 class="section-title">What this controls</h3>
          <div class="callout">Subject-level lock hides all activity submission actions. Activity-level lock lets you pause only one checkpoint without affecting the rest of the subject.</div>
        </article>
      </div>
    </section>
  </div>
</section>
<?php require_once __DIR__ . '/../backend/partials/footer.php'; ?>
