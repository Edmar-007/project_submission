<?php
require_once __DIR__ . '/../backend/helpers/query.php';
require_role('teacher');
$teacher = current_user();
$pdo = pdo();
$sections = available_subject_sections();
$allowedSectionIds = array_map(static fn(array $section): int => (int) $section['id'], $sections);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'save_subject') {
        $subjectId = (int) ($_POST['subject_id'] ?? 0);
        $code = trim($_POST['subject_code'] ?? '');
        $name = trim($_POST['subject_name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $status = trim($_POST['status'] ?? 'active');
        if (!in_array($status, ['active', 'inactive', 'archived'], true)) {
            $status = 'active';
        }
        $rawSectionIds = is_array($_POST['section_ids'] ?? null) ? $_POST['section_ids'] : [];
        $sectionIds = array_values(array_unique(array_filter(array_map('intval', $rawSectionIds), static fn(int $id): bool => $id > 0 && in_array($id, $allowedSectionIds, true))));
        $schoolYearId = active_school_year_id();
        $semesterId = active_semester_id($schoolYearId);

        if ($code === '' || $name === '' || !$schoolYearId || !$semesterId) {
            set_flash('error', 'Subject code, subject name, and an active academic term are required.');
            redirect_to('teacher/subjects.php');
        }
        if (!$sectionIds) {
            set_flash('error', 'Select at least one section for this subject.');
            redirect_to('teacher/subjects.php');
        }

        try {
            $pdo->beginTransaction();
            if ($subjectId > 0) {
                $ownershipStmt = $pdo->prepare('SELECT id FROM subjects WHERE id = ? AND teacher_id = ? LIMIT 1');
                $ownershipStmt->execute([$subjectId, (int) $teacher['id']]);
                if (!$ownershipStmt->fetchColumn()) {
                    throw new RuntimeException('Subject not found.');
                }

                $stmt = $pdo->prepare('UPDATE subjects SET subject_code = ?, subject_name = ?, description = ?, status = ? WHERE id = ? AND teacher_id = ?');
                $stmt->execute([$code, $name, $description ?: null, $status, $subjectId, (int) $teacher['id']]);
                $pdo->prepare('DELETE FROM section_subjects WHERE subject_id = ?')->execute([$subjectId]);
                $message = 'Subject updated successfully.';
            } else {
                $stmt = $pdo->prepare('INSERT INTO subjects (subject_code, subject_name, description, teacher_id, school_year_id, semester_id, status) VALUES (?, ?, ?, ?, ?, ?, ?)');
                $stmt->execute([$code, $name, $description ?: null, (int) $teacher['id'], $schoolYearId, $semesterId, $status]);
                $subjectId = (int) $pdo->lastInsertId();
                $message = 'Subject created successfully.';
            }

            $mapStmt = $pdo->prepare('INSERT INTO section_subjects (section_id, subject_id) VALUES (?, ?)');
            foreach ($sectionIds as $sectionId) {
                if ($sectionId > 0) {
                    $mapStmt->execute([$sectionId, $subjectId]);
                }
            }
            log_action('teacher', (int) $teacher['id'], $action === 'save_subject' && (int) ($_POST['subject_id'] ?? 0) > 0 ? 'subject_update' : 'subject_create', 'subject', $subjectId, $name);
            $pdo->commit();
            set_flash('success', $message);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            $message = 'Unable to save subject right now.';
            if ($e instanceof PDOException && (string) $e->getCode() === '23000') {
                $message = 'That subject code is already in use for the current term. Please use a different code.';
            }
            set_flash('error', $message);
        }
        redirect_to('teacher/subjects.php');
    }

    if ($action === 'archive_subject') {
        $subjectId = (int) ($_POST['subject_id'] ?? 0);
        $stmt = $pdo->prepare('UPDATE subjects SET status = "archived" WHERE id = ? AND teacher_id = ?');
        $stmt->execute([$subjectId, (int) $teacher['id']]);
        log_action('teacher', (int) $teacher['id'], 'subject_archive', 'subject', $subjectId, 'Archived subject from teacher workspace');
        set_flash('success', 'Subject archived.');
        redirect_to('teacher/subjects.php');
    }
}

$stmt = $pdo->prepare('SELECT subj.*, GROUP_CONCAT(sec.section_name ORDER BY sec.section_name SEPARATOR ", ") AS sections, COUNT(DISTINCT ss.section_id) AS total_sections, COUNT(DISTINCT sub.id) AS total_submissions, COUNT(DISTINCT sr.id) AS total_resources, COUNT(DISTINCT act.id) AS total_activities FROM subjects subj LEFT JOIN section_subjects ss ON ss.subject_id = subj.id LEFT JOIN sections sec ON sec.id = ss.section_id LEFT JOIN submissions sub ON sub.subject_id = subj.id LEFT JOIN subject_resources sr ON sr.subject_id = subj.id LEFT JOIN submission_activities act ON act.subject_id = subj.id WHERE subj.teacher_id = ? GROUP BY subj.id ORDER BY FIELD(subj.status, "active", "inactive", "archived"), subj.subject_name');
$stmt->execute([(int) $teacher['id']]);
$subjects = $stmt->fetchAll();

$sectionMapStmt = $pdo->prepare('SELECT subject_id, section_id FROM section_subjects WHERE subject_id IN (SELECT id FROM subjects WHERE teacher_id = ?)');
$sectionMapStmt->execute([(int) $teacher['id']]);
$sectionMap = [];
foreach ($sectionMapStmt->fetchAll() as $row) {
    $sectionMap[(int) $row['subject_id']][] = (int) $row['section_id'];
}

$activeCount = count(array_filter($subjects, fn($row) => ($row['status'] ?? '') === 'active'));
$archivedCount = count(array_filter($subjects, fn($row) => ($row['status'] ?? '') === 'archived'));
$submissionTotal = array_sum(array_map(fn($row) => (int) $row['total_submissions'], $subjects));
$title = 'Teacher Subjects';
$subtitle = 'Create, assign, update, and archive multi-section subjects from one workspace';
require_once __DIR__ . '/../backend/partials/header.php';
?>
<div class="kpi-grid">
  <div class="kpi-card"><span class="label">Subjects</span><strong><?= count($subjects) ?></strong><span class="muted small">Owned by your account</span></div>
  <div class="kpi-card"><span class="label">Active</span><strong><?= $activeCount ?></strong><span class="muted small">Currently visible to students</span></div>
  <div class="kpi-card"><span class="label">Archived</span><strong><?= $archivedCount ?></strong><span class="muted small">Hidden from the main flow</span></div>
  <div class="kpi-card"><span class="label">Submissions</span><strong><?= $submissionTotal ?></strong><span class="muted small">Across all your subjects</span></div>
</div>

<section class="workspace-shell">
  <div class="workspace-head">
    <div>
      <div class="eyebrow">Teacher Subject Workspace</div>
      <h2>Manage subject cards instead of a table-first flow</h2>
      <p class="muted">Create a subject, assign one or many sections, reopen it later for edits, or archive it when the term is complete.</p>
    </div>
    <div class="form-actions"><button class="btn" type="button" data-open-modal="subject-create-modal">Create subject</button></div>
  </div>

  <div class="subject-workspace-grid">
    <?php foreach ($subjects as $subject): ?>
      <?php $locked = !empty($subject['teacher_submission_locked']); ?>
      <article class="card subject-workspace-card">
        <div class="split-header">
          <div>
            <h3 class="section-title"><?= h($subject['subject_name']) ?></h3>
            <div class="muted small"><?= h($subject['subject_code']) ?> · <?= h($subject['sections'] ?: 'No sections assigned') ?></div>
          </div>
          <?= status_badge($subject['status']) ?>
        </div>
        <p class="muted"><?= h($subject['description'] ?: 'No description provided yet.') ?></p>
        <div class="workspace-stat-grid">
          <div class="segment"><span class="muted small">Sections</span><strong><?= (int) $subject['total_sections'] ?></strong></div>
          <div class="segment"><span class="muted small">Submissions</span><strong><?= (int) $subject['total_submissions'] ?></strong></div>
          <div class="segment"><span class="muted small">Activities</span><strong><?= (int) $subject['total_activities'] ?></strong></div><div class="segment"><span class="muted small">Resources</span><strong><?= (int) $subject['total_resources'] ?></strong></div>
        </div>
        <div style="margin-top:12px;"><?= deadline_badge_html($subject) ?></div>
        <?php if ($locked): ?><div class="callout" style="margin-top:12px;"><strong>Manual lock active.</strong><div class="muted small"><?= h($subject['teacher_submission_lock_note'] ?: 'Teacher closed submissions for this subject.') ?></div></div><?php endif; ?>
        <div class="form-actions" style="margin-top:16px;">
          <a class="btn btn-secondary" href="<?= h(url('teacher/subject_view.php?id=' . (int) $subject['id'])) ?>">Open workspace</a>
          <button class="btn btn-outline" type="button" data-open-modal="subject-edit-<?= (int) $subject['id'] ?>">Edit</button>
          <?php if (($subject['status'] ?? '') !== 'archived'): ?>
            <form method="post" class="inline">
              <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
              <input type="hidden" name="action" value="archive_subject">
              <input type="hidden" name="subject_id" value="<?= (int) $subject['id'] ?>">
              <button class="btn btn-ghost" type="submit" data-confirm-title="Archive subject?" data-confirm-message="This hides the subject from normal teacher and student workflows, but keeps its history.">Archive</button>
            </form>
          <?php endif; ?>
        </div>
      </article>
    <?php endforeach; ?>
    <?php if (!$subjects): ?><div class="card empty-state">No subject yet. Create your first subject to start assigning sections and collecting submissions.</div><?php endif; ?>
  </div>
</section>

<div class="modal-backdrop" data-modal="subject-create-modal" aria-hidden="true">
  <div class="modal-card modal-lg subject-modal-card" role="dialog" aria-modal="true" aria-labelledby="subject-create-title">
    <div class="modal-head">
      <div>
        <span class="pill soft">Create subject</span>
        <h3 id="subject-create-title">New subject</h3>
        <p class="muted">Create the subject, set its visibility, and assign one or many sections in one clean flow.</p>
      </div>
      <button type="button" class="icon-btn modal-close" data-close-modal aria-label="Close create subject modal">✕</button>
    </div>
    <div class="modal-body">
      <form id="subject-create-form" method="post" class="form-grid" data-modal-form="subject">
        <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
        <input type="hidden" name="action" value="save_subject">
        <div class="full modal-section-card modal-section-intro">
          <div>
            <strong>Subject setup</strong>
            <p class="muted small">Use a short subject code, a clear title, and pick every section that should see this subject.</p>
          </div>
          <span class="pill soft"><?= count($sections) ?> section<?= count($sections) === 1 ? '' : 's' ?> available</span>
        </div>
        <div><label>Subject code</label><input name="subject_code" placeholder="Example: IM101" required></div>
        <div><label>Subject name</label><input name="subject_name" placeholder="Example: Information Management" required></div>
        <div class="full"><label>Description</label><textarea name="description" rows="4" placeholder="Describe the subject goals, expected outputs, or class requirements"></textarea></div>
        <div><label>Status</label><select name="status"><option value="active">Active</option><option value="inactive">Inactive</option></select></div>
        <div class="full modal-section-card">
          <div class="modal-section-heading">
            <div>
              <strong>Assigned sections</strong>
              <p class="muted small">Students only see this subject if their section is selected here.</p>
            </div>
          </div>
          <div class="checkbox-grid"><?php foreach ($sections as $section): ?><label class="checkbox-card"><input type="checkbox" name="section_ids[]" value="<?= (int) $section['id'] ?>"> <span><strong><?= h($section['section_name']) ?></strong><small><?= h(($section['school_year'] ?? '') . ' · ' . ($section['semester'] ?? '')) ?></small></span></label><?php endforeach; ?></div>
        </div>
      </form>
    </div>
    <div class="modal-footer">
      <button class="btn" type="submit" form="subject-create-form">Save subject</button>
      <button class="btn btn-outline" type="button" data-close-modal>Cancel</button>
    </div>
  </div>
</div>

<?php foreach ($subjects as $subject): ?>
<div class="modal-backdrop" data-modal="subject-edit-<?= (int) $subject['id'] ?>" aria-hidden="true">
  <div class="modal-card modal-lg subject-modal-card" role="dialog" aria-modal="true" aria-labelledby="subject-edit-title-<?= (int) $subject['id'] ?>">
    <div class="modal-head">
      <div>
        <span class="pill soft">Edit subject</span>
        <h3 id="subject-edit-title-<?= (int) $subject['id'] ?>"><?= h($subject['subject_name']) ?></h3>
        <p class="muted">Update details, visibility, and section mapping without leaving the workspace.</p>
      </div>
      <button type="button" class="icon-btn modal-close" data-close-modal aria-label="Close edit subject modal">✕</button>
    </div>
    <div class="modal-body">
      <form id="subject-edit-form-<?= (int) $subject['id'] ?>" method="post" class="form-grid" data-modal-form="subject">
        <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
        <input type="hidden" name="action" value="save_subject">
        <input type="hidden" name="subject_id" value="<?= (int) $subject['id'] ?>">
        <div class="full modal-section-card modal-section-intro">
          <div>
            <strong>Subject status</strong>
            <p class="muted small">Keep the subject active for students, mark it inactive temporarily, or archive it when the term is finished.</p>
          </div>
          <?= status_badge($subject['status']) ?>
        </div>
        <div><label>Subject code</label><input name="subject_code" required value="<?= h($subject['subject_code']) ?>"></div>
        <div><label>Subject name</label><input name="subject_name" required value="<?= h($subject['subject_name']) ?>"></div>
        <div class="full"><label>Description</label><textarea name="description" rows="4"><?= h($subject['description']) ?></textarea></div>
        <div><label>Status</label><select name="status"><option value="active" <?= selected($subject['status'], 'active') ?>>Active</option><option value="inactive" <?= selected($subject['status'], 'inactive') ?>>Inactive</option><option value="archived" <?= selected($subject['status'], 'archived') ?>>Archived</option></select></div>
        <div class="full modal-section-card">
          <div class="modal-section-heading">
            <div>
              <strong>Assigned sections</strong>
              <p class="muted small">Update the section list any time. Only checked sections can access this subject.</p>
            </div>
          </div>
          <div class="checkbox-grid"><?php foreach ($sections as $section): ?><label class="checkbox-card"><input type="checkbox" name="section_ids[]" value="<?= (int) $section['id'] ?>" <?= in_array((int) $section['id'], $sectionMap[(int) $subject['id']] ?? [], true) ? 'checked' : '' ?>> <span><strong><?= h($section['section_name']) ?></strong><small><?= h(($section['school_year'] ?? '') . ' · ' . ($section['semester'] ?? '')) ?></small></span></label><?php endforeach; ?></div>
        </div>
      </form>
    </div>
    <div class="modal-footer">
      <button class="btn" type="submit" form="subject-edit-form-<?= (int) $subject['id'] ?>">Update subject</button>
      <button class="btn btn-outline" type="button" data-close-modal>Cancel</button>
    </div>
  </div>
</div>
<?php endforeach; ?>
<?php require_once __DIR__ . '/../backend/partials/footer.php'; ?>
