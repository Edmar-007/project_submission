<?php
require_once __DIR__ . '/../backend/helpers/query.php';
require_role('admin');
$pdo = pdo();
$admin = current_user();
$teachers = all_teachers();
$sections = all_sections();
$statusFilter = trim($_GET['status'] ?? '');
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
        if ($subjectCode && $subjectName && $teacherId && $schoolYearId && $semesterId) {
            try {
                $stmt = $pdo->prepare('INSERT INTO subjects (subject_code, subject_name, description, teacher_id, school_year_id, semester_id, status) VALUES (?, ?, ?, ?, ?, ?, "active")');
                $stmt->execute([$subjectCode, $subjectName, $description, $teacherId, $schoolYearId, $semesterId]);
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
        if ($subjectId && $subjectCode && $subjectName && $teacherId) {
            $pdo->prepare('UPDATE subjects SET subject_code = ?, subject_name = ?, description = ?, teacher_id = ?, status = ? WHERE id = ?')->execute([$subjectCode, $subjectName, $description, $teacherId, $status, $subjectId]);
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
$sql = 'SELECT subj.*, t.full_name AS teacher_name, GROUP_CONCAT(sec.section_name ORDER BY sec.section_name SEPARATOR ", ") AS sections, COUNT(DISTINCT sub.id) AS total_submissions FROM subjects subj JOIN teachers t ON t.id = subj.teacher_id LEFT JOIN section_subjects ss ON ss.subject_id = subj.id LEFT JOIN sections sec ON sec.id = ss.section_id LEFT JOIN submissions sub ON sub.subject_id = subj.id';
$params=[];
if ($statusFilter !== '') { $sql .= ' WHERE subj.status = ?'; $params[]=$statusFilter; }
$sql .= ' GROUP BY subj.id ORDER BY FIELD(subj.status, "active","inactive","archived"), subj.created_at DESC';
$stmt = $pdo->prepare($sql); $stmt->execute($params); $subjects = $stmt->fetchAll();
$assignedMap = [];
foreach ($pdo->query('SELECT * FROM section_subjects')->fetchAll() as $link) { $assignedMap[(int) $link['subject_id']][] = (int) $link['section_id']; }
$title = 'Subjects';
$subtitle = 'Create, edit, archive, and assign whole sections so students inherit access automatically';
require_once __DIR__ . '/../backend/partials/header.php';
?>
<div class="grid cols-2">
  <div class="card">
    <h3>Create subject</h3>
    <form method="post" class="form-grid">
      <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
      <input type="hidden" name="action" value="create">
      <div><label>Subject code</label><input name="subject_code" placeholder="IM101" required></div>
      <div><label>Subject name</label><input name="subject_name" placeholder="Information Management" required></div>
      <div class="full"><label>Description</label><textarea name="description"></textarea></div>
      <div><label>Teacher</label><select name="teacher_id" required><option value="">Select teacher</option><?php foreach ($teachers as $teacher): ?><option value="<?= (int) $teacher['id'] ?>"><?= h($teacher['full_name']) ?></option><?php endforeach; ?></select></div>
      <div><label>Assign sections</label><select name="section_ids[]" multiple size="6"><?php foreach ($sections as $section): ?><option value="<?= (int) $section['id'] ?>"><?= h($section['section_name']) ?> (<?= h($section['status']) ?>)</option><?php endforeach; ?></select></div>
      <div class="full form-actions"><button class="btn" type="submit">Create subject</button></div>
    </form>
  </div>
  <div class="card highlight-card">
    <h3>Subject assignment model</h3>
    <p class="muted">Assign the subject to one or more whole sections. Every student in those sections can then see and submit to the subject automatically.</p>
    <form method="get" class="filter-row" style="margin-top:18px;"><select name="status"><option value="">All statuses</option><option value="active" <?= selected($statusFilter,'active') ?>>Active</option><option value="inactive" <?= selected($statusFilter,'inactive') ?>>Inactive</option><option value="archived" <?= selected($statusFilter,'archived') ?>>Archived</option></select><button class="btn btn-secondary" type="submit">Filter</button></form>
  </div>
</div>
<div class="card" style="margin-top:18px;">
  <div class="table-wrap"><table><thead><tr><th>Subject</th><th>Teacher</th><th>Assigned sections</th><th>Submissions</th><th>Actions</th></tr></thead><tbody><?php foreach ($subjects as $row): ?><tr><td><strong><?= h($row['subject_name']) ?></strong><div class="muted small"><?= h($row['subject_code']) ?></div><?= status_badge($row['status']) ?></td><td><?= h($row['teacher_name']) ?></td><td><?= h($row['sections'] ?: 'No sections yet') ?></td><td><?= (int) $row['total_submissions'] ?></td><td><div class="table-actions"><a class="btn btn-secondary" href="<?= h(url('admin/subject_view.php?id=' . (int) $row['id'])) ?>">View</a><?php if ($row['status'] !== 'archived'): ?><form method="post" class="inline"><input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>"><input type="hidden" name="action" value="archive"><input type="hidden" name="subject_id" value="<?= (int) $row['id'] ?>"><button class="btn btn-outline" type="submit">Archive</button></form><?php endif; ?><details><summary class="btn btn-outline">Quick edit</summary><form method="post" class="grid" style="margin-top:12px; min-width:280px;"><input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>"><input type="hidden" name="action" value="save"><input type="hidden" name="subject_id" value="<?= (int) $row['id'] ?>"><input name="subject_code" value="<?= h($row['subject_code']) ?>"><input name="subject_name" value="<?= h($row['subject_name']) ?>"><textarea name="description"><?= h($row['description']) ?></textarea><select name="teacher_id"><?php foreach ($teachers as $teacher): ?><option value="<?= (int) $teacher['id'] ?>" <?= (int)$teacher['id']===(int)$row['teacher_id']?'selected':'' ?>><?= h($teacher['full_name']) ?></option><?php endforeach; ?></select><select name="status"><option value="active" <?= selected($row['status'],'active') ?>>Active</option><option value="inactive" <?= selected($row['status'],'inactive') ?>>Inactive</option><option value="archived" <?= selected($row['status'],'archived') ?>>Archived</option></select><select name="section_ids[]" multiple size="6"><?php foreach ($sections as $section): ?><option value="<?= (int) $section['id'] ?>" <?= in_array((int)$section['id'], $assignedMap[(int)$row['id']] ?? [], true) ? 'selected' : '' ?>><?= h($section['section_name']) ?></option><?php endforeach; ?></select><button class="btn btn-secondary" type="submit">Save</button></form></details></div></td></tr><?php endforeach; ?></tbody></table></div>
</div>
<?php require_once __DIR__ . '/../backend/partials/footer.php'; ?>
