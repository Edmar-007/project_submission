<?php
require_once __DIR__ . '/../backend/helpers/query.php';
require_role('admin');
$pdo = pdo();
$admin = current_user();
$statusFilter = trim($_GET['status'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $sectionName = trim($_POST['section_name'] ?? '');
        $notes = trim($_POST['notes'] ?? '');
        $schoolYearId = active_school_year_id();
        $semesterId = active_semester_id($schoolYearId);
        if ($sectionName && $schoolYearId && $semesterId) {
            try {
                $stmt = $pdo->prepare('INSERT INTO sections (section_name, school_year_id, semester_id, status, notes) VALUES (?, ?, ?, "active", ?)');
                $stmt->execute([$sectionName, $schoolYearId, $semesterId, $notes]);
                log_action('admin', (int) $admin['id'], 'create_section', 'section', (int) $pdo->lastInsertId(), $sectionName);
                set_flash('Section added successfully.', 'success');
            } catch (Throwable $e) {
                set_flash('Unable to add section. It may already exist this term.', 'error');
            }
        }
    }

    if ($action === 'save') {
        $sectionId = (int) ($_POST['section_id'] ?? 0);
        $sectionName = trim($_POST['section_name'] ?? '');
        $notes = trim($_POST['notes'] ?? '');
        if ($sectionId && $sectionName) {
            $pdo->prepare('UPDATE sections SET section_name = ?, notes = ? WHERE id = ?')->execute([$sectionName, $notes, $sectionId]);
            set_flash('Section updated successfully.', 'success');
        }
    }

    if ($action === 'toggle') {
        $sectionId = (int) ($_POST['section_id'] ?? 0);
        $currentStatus = $_POST['current_status'] ?? 'active';
        if ($sectionId) {
            if ($currentStatus === 'active') {
                $pdo->prepare('UPDATE sections SET status = "inactive" WHERE id = ?')->execute([$sectionId]);
                $pdo->prepare('UPDATE students SET account_status = "view_only", can_submit = 0 WHERE section_id = ? AND account_status <> "archived"')->execute([$sectionId]);
                $students = $pdo->prepare('SELECT id FROM students WHERE section_id = ? AND account_status <> "archived"');
                $students->execute([$sectionId]);
                foreach ($students->fetchAll() as $student) {
                    create_notification('student', (int) $student['id'], 'Section restricted', 'Your section has been marked inactive. Your account is now view-only. You can still log in and view your records.', 'warning');
                }
                set_flash('Section deactivated and students were moved to view-only mode.', 'success');
            } else {
                $pdo->prepare('UPDATE sections SET status = "active" WHERE id = ?')->execute([$sectionId]);
                set_flash('Section reactivated. Students stay unchanged until individually reactivated if needed.', 'success');
            }
        }
    }

    if ($action === 'archive') {
        $sectionId = (int) ($_POST['section_id'] ?? 0);
        if ($sectionId) {
            $pdo->prepare('UPDATE sections SET status = "archived" WHERE id = ?')->execute([$sectionId]);
            $pdo->prepare('UPDATE students SET account_status = "archived", can_submit = 0 WHERE section_id = ?')->execute([$sectionId]);
            log_action('admin', (int) $admin['id'], 'archive_section', 'section', $sectionId, 'Archived section and linked students');
            set_flash('Section archived. Linked students were archived from active workflows.', 'success');
        }
    }

    redirect_to('admin/sections.php');
}

$sql = 'SELECT s.*, sy.label AS school_year, sem.name AS semester, COUNT(st.id) AS total_students FROM sections s JOIN school_years sy ON sy.id = s.school_year_id JOIN semesters sem ON sem.id = s.semester_id LEFT JOIN students st ON st.section_id = s.id WHERE 1=1';
$params = [];
$search = trim($_GET['search'] ?? '');
if ($search !== '') {
    $sql .= ' AND (s.section_name LIKE ? OR s.notes LIKE ?)';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}
if ($statusFilter !== '') {
    $sql .= ' AND s.status = ?';
    $params[] = $statusFilter;
}
$sql .= ' GROUP BY s.id ORDER BY FIELD(s.status, "active","inactive","archived"), s.created_at DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$sections = $stmt->fetchAll();
$title = 'Sections';
$subtitle = 'Activate, deactivate, archive, and bulk-restrict students by section';
require_once __DIR__ . '/../backend/partials/header.php';
?>
<div class="grid cols-2">
  <div class="card">
    <h3>Add section</h3>
    <form method="post">
      <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
      <input type="hidden" name="action" value="create">
      <div class="grid">
        <div><label>Section name</label><input name="section_name" placeholder="BSIT 22012" required></div>
        <div><label>Notes</label><input name="notes" placeholder="Optional notes"></div>
      </div>
      <div class="form-actions"><button class="btn" type="submit">Add section</button></div>
    </form>
  </div>
  <div class="card">
    <h3>Lifecycle rule</h3>
    <p class="muted">Deactivate to keep history visible while restricting students. Archive when a section is fully historical and should drop out of active workflows.</p>
    <form method="get" class="filter-row" style="margin-top:18px;">
      <input type="text" name="search" placeholder="Search section or note" value="<?= h($search) ?>">
      <select name="status"><option value="">All statuses</option><option value="active" <?= selected($statusFilter,'active') ?>>Active</option><option value="inactive" <?= selected($statusFilter,'inactive') ?>>Inactive</option><option value="archived" <?= selected($statusFilter,'archived') ?>>Archived</option></select>
      <button class="btn btn-secondary" type="submit">Filter</button>
    </form>
  </div>
</div>
<div class="card" style="margin-top:18px;">
  <div class="table-wrap">
    <table>
      <thead><tr><th>Section</th><th>Term</th><th>Students</th><th>Status</th><th>Action</th></tr></thead>
      <tbody>
        <?php foreach ($sections as $row): ?>
          <tr>
            <td>
              <form method="post" class="grid" style="min-width:240px;">
                <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="section_id" value="<?= (int) $row['id'] ?>">
                <input name="section_name" value="<?= h($row['section_name']) ?>">
                <input name="notes" value="<?= h($row['notes'] ?? '') ?>" placeholder="Optional notes">
                <button class="btn btn-secondary" type="submit">Save</button>
              </form>
            </td>
            <td><?= h($row['school_year']) ?> · <?= h($row['semester']) ?></td>
            <td><?= (int) $row['total_students'] ?></td>
            <td><?= status_badge($row['status']) ?></td>
            <td>
              <div class="table-actions">
                <?php if ($row['status'] !== 'archived'): ?>
                <form method="post" class="inline">
                  <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
                  <input type="hidden" name="action" value="toggle">
                  <input type="hidden" name="section_id" value="<?= (int) $row['id'] ?>">
                  <input type="hidden" name="current_status" value="<?= h($row['status']) ?>">
                  <button class="btn <?= $row['status'] === 'active' ? 'btn-danger' : 'btn-secondary' ?>" type="submit"><?= $row['status'] === 'active' ? 'Deactivate + Restrict' : 'Reactivate section' ?></button>
                </form>
                <form method="post" class="inline">
                  <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
                  <input type="hidden" name="action" value="archive">
                  <input type="hidden" name="section_id" value="<?= (int) $row['id'] ?>">
                  <button class="btn btn-outline" type="submit">Archive</button>
                </form>
                <?php else: ?>
                  <span class="muted small">Archived section</span>
                <?php endif; ?>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php require_once __DIR__ . '/../backend/partials/footer.php'; ?>
