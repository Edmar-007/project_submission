<?php
require_once __DIR__ . '/../backend/helpers/query.php';
require_role('admin');
$pdo = pdo();
$admin = current_user();
$teachers = all_teachers();
$sections = all_sections();
$statusFilter = trim($_GET['status'] ?? '');
$search = trim($_GET['q'] ?? '');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? 'create';
    if ($action === 'create') {
        $subjectCode = trim($_POST['subject_code'] ?? '');
        $subjectName = trim($_POST['subject_name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $teacherId = (int) ($_POST['teacher_id'] ?? 0);
        $schoolYearId = active_school_year_id();
        $semesterId = active_semester_id($schoolYearId);
        $selectedSections = array_map('intval', $_POST['section_ids'] ?? []);
        $submissionDeadline = trim($_POST['submission_deadline'] ?? '') ?: null;
        $deadlineWarningHours = max(1, (int) ($_POST['deadline_warning_hours'] ?? 72));
        if ($subjectCode && $subjectName && $teacherId && $schoolYearId && $semesterId) {
            try {
                $stmt = $pdo->prepare('INSERT INTO subjects (subject_code, subject_name, description, teacher_id, school_year_id, semester_id, status, submission_deadline, deadline_warning_hours) VALUES (?, ?, ?, ?, ?, ?, "active", ?, ?)');
                $stmt->execute([$subjectCode, $subjectName, $description, $teacherId, $schoolYearId, $semesterId, $submissionDeadline, $deadlineWarningHours]);
                $subjectId = (int) $pdo->lastInsertId();
                foreach ($selectedSections as $sectionId) {
                    $pdo->prepare('INSERT IGNORE INTO section_subjects (section_id, subject_id) VALUES (?, ?)')->execute([$sectionId, $subjectId]);
                }
                log_action('admin', (int) $admin['id'], 'create_subject', 'subject', $subjectId, $subjectName);
                set_flash('Subject created and assigned to selected sections.', 'success');
            } catch (Throwable $e) {
                set_flash('Unable to create subject.', 'error');
            }
        }
    }
    if ($action === 'save') {
        $subjectId = (int) ($_POST['subject_id'] ?? 0);
        $subjectCode = trim($_POST['subject_code'] ?? '');
        $subjectName = trim($_POST['subject_name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $teacherId = (int) ($_POST['teacher_id'] ?? 0);
        $status = $_POST['status'] ?? 'active';
        $selectedSections = array_map('intval', $_POST['section_ids'] ?? []);
        $submissionDeadline = trim($_POST['submission_deadline'] ?? '') ?: null;
        $deadlineWarningHours = max(1, (int) ($_POST['deadline_warning_hours'] ?? 72));
        if ($subjectId && $subjectCode && $subjectName && $teacherId) {
            $pdo->prepare('UPDATE subjects SET subject_code = ?, subject_name = ?, description = ?, teacher_id = ?, status = ?, submission_deadline = ?, deadline_warning_hours = ? WHERE id = ?')->execute([$subjectCode, $subjectName, $description, $teacherId, $status, $submissionDeadline, $deadlineWarningHours, $subjectId]);
            $pdo->prepare('DELETE FROM section_subjects WHERE subject_id = ?')->execute([$subjectId]);
            foreach ($selectedSections as $sectionId) {
                $pdo->prepare('INSERT IGNORE INTO section_subjects (section_id, subject_id) VALUES (?, ?)')->execute([$sectionId, $subjectId]);
            }
            set_flash('Subject updated successfully.', 'success');
        }
    }
    if ($action === 'archive') {
        $subjectId = (int) ($_POST['subject_id'] ?? 0);
        if ($subjectId) {
            $pdo->prepare('UPDATE subjects SET status = "archived" WHERE id = ?')->execute([$subjectId]);
            log_action('admin', (int) $admin['id'], 'archive_subject', 'subject', $subjectId, 'Archived subject');
            set_flash('Subject archived successfully.', 'success');
        }
    }
    redirect_to('admin/subjects.php');
}
$sql = 'SELECT subj.*, t.full_name AS teacher_name, GROUP_CONCAT(DISTINCT sec.section_name ORDER BY sec.section_name SEPARATOR ", ") AS sections, COUNT(DISTINCT sub.id) AS total_submissions FROM subjects subj JOIN teachers t ON t.id = subj.teacher_id LEFT JOIN section_subjects ss ON ss.subject_id = subj.id LEFT JOIN sections sec ON sec.id = ss.section_id LEFT JOIN submissions sub ON sub.subject_id = subj.id WHERE 1=1';
$params=[];
if ($search !== '') { $sql .= ' AND (subj.subject_code LIKE ? OR subj.subject_name LIKE ? OR t.full_name LIKE ?)'; $like = "%{$search}%"; array_push($params, $like, $like, $like); }
if ($statusFilter !== '') { $sql .= ' AND subj.status = ?'; $params[]=$statusFilter; }
$sql .= ' GROUP BY subj.id ORDER BY FIELD(subj.status, "active","inactive","archived"), subj.created_at DESC';
$stmt = $pdo->prepare($sql); $stmt->execute($params); $subjects = $stmt->fetchAll();
$assignedMap = [];
foreach ($pdo->query('SELECT * FROM section_subjects')->fetchAll() as $link) { $assignedMap[(int) $link['subject_id']][] = (int) $link['section_id']; }
$stats = ['total' => (int) $pdo->query('SELECT COUNT(*) FROM subjects')->fetchColumn(),'active' => (int) $pdo->query("SELECT COUNT(*) FROM subjects WHERE status = 'active'")->fetchColumn(),'submissions' => (int) $pdo->query('SELECT COUNT(*) FROM submissions')->fetchColumn(),'sections' => (int) $pdo->query('SELECT COUNT(DISTINCT section_id) FROM section_subjects')->fetchColumn()];
$title = 'Subjects';
$subtitle = 'Subjects are now table-first, with add/edit moved into modals and actions reduced to icons.';
require_once __DIR__ . '/../backend/partials/header.php';
?>
<div class="kpi-grid"><div class="kpi-card"><span class="label">Subjects</span><strong><?= number_format($stats['total']) ?></strong><span class="muted small">Configured records</span></div><div class="kpi-card"><span class="label">Active</span><strong><?= number_format($stats['active']) ?></strong><span class="muted small">Currently available</span></div><div class="kpi-card"><span class="label">Coverage</span><strong><?= number_format($stats['sections']) ?></strong><span class="muted small">Distinct assigned sections</span></div><div class="kpi-card"><span class="label">Submissions</span><strong><?= number_format($stats['submissions']) ?></strong><span class="muted small">Projects on file</span></div></div>
<section class="table-card table-bootstrap-shell"><div class="module-header"><div><div class="eyebrow">Subject Registry</div><h3 class="mb-1">Ownership and Section Mapping</h3><p class="muted mb-0">Deadline, section mapping, and lifecycle controls live directly inside this table flow now.</p></div><div class="module-actions"><button class="btn" type="button" data-open-modal="admin-add-subject"><i class="bi bi-plus-lg"></i> Add Subject</button><span class="badge-soft"><i class="bi bi-table"></i> <?= count($subjects) ?> rows</span></div></div><div class="table-toolbar"><form method="get" class="filters"><input class="form-control" name="q" placeholder="Search code, subject name, teacher" value="<?= h($search) ?>"><select class="form-select" name="status"><option value="">All statuses</option><option value="active" <?= selected($statusFilter,'active') ?>>Active</option><option value="inactive" <?= selected($statusFilter,'inactive') ?>>Inactive</option><option value="archived" <?= selected($statusFilter,'archived') ?>>Archived</option></select><button class="btn" type="submit"><i class="bi bi-funnel"></i> Apply Filters</button></form></div><div class="table-responsive"><table class="table table-hover align-middle"><thead><tr><th>Subject</th><th>Teacher</th><th>Sections</th><th>Deadline</th><th>Submissions</th><th class="text-end">Actions</th></tr></thead><tbody><?php foreach ($subjects as $row): ?><tr><td><strong><?= h($row['subject_name']) ?></strong><div class="muted small"><?= h($row['subject_code']) ?></div><div class="muted small"><?= h($row['description'] ?: 'No description yet.') ?></div><?= status_badge($row['status']) ?></td><td><?= h($row['teacher_name']) ?></td><td><?php foreach (array_filter(array_map('trim', explode(',', (string) ($row['sections'] ?? '')))) as $sectionName): ?><span class="badge-soft me-1 mb-1"><i class="bi bi-diagram-3"></i> <?= h($sectionName) ?></span><?php endforeach; ?><?php if (trim((string) ($row['sections'] ?? '')) === ''): ?><div class="muted small">No sections yet</div><?php endif; ?></td><td><?= deadline_badge_html($row) ?><div class="muted small mt-1"><?= h(($row['submission_deadline'] ?? '') ?: 'No deadline set') ?></div></td><td><?= (int) $row['total_submissions'] ?></td><td class="text-end"><div class="icon-action-group justify-content-end"><a class="icon-action" href="<?= h(url('admin/subject_preview.php?id=' . (int) $row['id'])) ?>" data-ajax-modal="1" data-modal-title="Subject overview" title="View subject"><i class="bi bi-eye"></i></a><button class="icon-action" type="button" data-open-modal="edit-subject-<?= (int) $row['id'] ?>" title="Edit subject"><i class="bi bi-pencil"></i></button><form method="post" class="d-inline"><input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>"><input type="hidden" name="action" value="archive"><input type="hidden" name="subject_id" value="<?= (int) $row['id'] ?>"><button class="icon-action danger" type="submit" title="Archive subject" data-confirm-title="Archive subject?" data-confirm-message="This subject will move to archived status."><i class="bi bi-archive"></i></button></form></div></td></tr><?php endforeach; ?><?php if (!$subjects): ?><tr><td colspan="6" class="table-empty">No subject records matched your filters.</td></tr><?php endif; ?></tbody></table></div></section>
<div class="modal-backdrop" data-modal="admin-add-subject" aria-hidden="true"><div class="modal-card modal-lg" role="dialog" aria-modal="true"><div class="modal-head"><div><span class="badge-soft"><i class="bi bi-book"></i> Subject</span><h3>Add Subject</h3></div><button type="button" class="icon-btn modal-close" data-close-modal aria-label="Close">✕</button></div><form method="post" class="form-modal-grid"><input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>"><input type="hidden" name="action" value="create"><div><label>Subject code</label><input class="form-control" name="subject_code" required></div><div><label>Subject name</label><input class="form-control" name="subject_name" required></div><div><label>Teacher</label><select class="form-select" name="teacher_id" required><?php foreach ($teachers as $teacher): ?><option value="<?= (int) $teacher['id'] ?>"><?= h($teacher['full_name']) ?></option><?php endforeach; ?></select></div><div><label>Deadline warning hours</label><input class="form-control" type="number" min="1" name="deadline_warning_hours" value="72"></div><div><label>Submission deadline</label><input class="form-control" type="datetime-local" name="submission_deadline"></div><div class="full"><label>Description</label><textarea class="form-control" name="description" rows="3"></textarea></div><div class="full"><label>Assign sections</label><div class="row g-2"><?php foreach ($sections as $section): ?><div class="col-12 col-md-6"><label class="form-check"><input class="form-check-input" type="checkbox" name="section_ids[]" value="<?= (int) $section['id'] ?>"> <span class="form-check-label"><?= h($section['section_name']) ?></span></label></div><?php endforeach; ?></div></div><div class="full d-flex justify-content-end gap-2"><button class="btn btn-outline" type="button" data-close-modal>Cancel</button><button class="btn" type="submit">Create subject</button></div></form></div></div>
<?php foreach ($subjects as $row): ?><div class="modal-backdrop" data-modal="edit-subject-<?= (int) $row['id'] ?>" aria-hidden="true"><div class="modal-card modal-lg" role="dialog" aria-modal="true"><div class="modal-head"><div><span class="badge-soft"><i class="bi bi-pencil"></i> Subject</span><h3>Edit <?= h($row['subject_name']) ?></h3></div><button type="button" class="icon-btn modal-close" data-close-modal aria-label="Close">✕</button></div><form method="post" class="form-modal-grid"><input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>"><input type="hidden" name="action" value="save"><input type="hidden" name="subject_id" value="<?= (int) $row['id'] ?>"><div><label>Subject code</label><input class="form-control" name="subject_code" value="<?= h($row['subject_code']) ?>" required></div><div><label>Subject name</label><input class="form-control" name="subject_name" value="<?= h($row['subject_name']) ?>" required></div><div><label>Teacher</label><select class="form-select" name="teacher_id" required><?php foreach ($teachers as $teacher): ?><option value="<?= (int) $teacher['id'] ?>" <?= (int)$teacher['id']===(int)$row['teacher_id'] ? 'selected' : '' ?>><?= h($teacher['full_name']) ?></option><?php endforeach; ?></select></div><div><label>Status</label><select class="form-select" name="status"><option value="active" <?= selected($row['status'],'active') ?>>Active</option><option value="inactive" <?= selected($row['status'],'inactive') ?>>Inactive</option><option value="archived" <?= selected($row['status'],'archived') ?>>Archived</option></select></div><div><label>Submission deadline</label><input class="form-control" type="datetime-local" name="submission_deadline" value="<?= h(!empty($row['submission_deadline']) ? date('Y-m-d\TH:i', strtotime((string) $row['submission_deadline'])) : '') ?>"></div><div><label>Deadline warning hours</label><input class="form-control" type="number" min="1" name="deadline_warning_hours" value="<?= (int)($row['deadline_warning_hours'] ?? 72) ?>"></div><div class="full"><label>Description</label><textarea class="form-control" name="description" rows="3"><?= h($row['description']) ?></textarea></div><div class="full"><label>Assign sections</label><div class="row g-2"><?php foreach ($sections as $section): ?><div class="col-12 col-md-6"><label class="form-check"><input class="form-check-input" type="checkbox" name="section_ids[]" value="<?= (int) $section['id'] ?>" <?= in_array((int) $section['id'], $assignedMap[(int)$row['id']] ?? [], true) ? 'checked' : '' ?>> <span class="form-check-label"><?= h($section['section_name']) ?></span></label></div><?php endforeach; ?></div></div><div class="full d-flex justify-content-end gap-2"><button class="btn btn-outline" type="button" data-close-modal>Cancel</button><button class="btn" type="submit">Save changes</button></div></form></div></div><?php endforeach; ?>
<?php require_once __DIR__ . '/../backend/partials/footer.php'; ?>
