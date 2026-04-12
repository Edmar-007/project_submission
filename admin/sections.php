<?php
if (defined('FILE_ADMIN_SECTIONS_PHP_LOADED')) { return; }
define('FILE_ADMIN_SECTIONS_PHP_LOADED', true);

require_once __DIR__ . '/../backend/config/app.php';
require_role('admin');
$pdo = pdo();
$admin = current_user();
$statusFilter = trim($_GET['status'] ?? '');
$search = trim($_GET['search'] ?? '');
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
$sql = 'SELECT s.*, sy.label AS school_year, sem.name AS semester, COUNT(st.id) AS total_students, COUNT(DISTINCT ss.subject_id) AS total_subjects FROM sections s JOIN school_years sy ON sy.id = s.school_year_id JOIN semesters sem ON sem.id = s.semester_id LEFT JOIN students st ON st.section_id = s.id LEFT JOIN section_subjects ss ON ss.section_id = s.id WHERE 1=1';
$params = [];
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
$stats = [
  'total' => (int) $pdo->query('SELECT COUNT(*) FROM sections')->fetchColumn(),
  'active' => (int) $pdo->query("SELECT COUNT(*) FROM sections WHERE status = 'active'")->fetchColumn(),
  'inactive' => (int) $pdo->query("SELECT COUNT(*) FROM sections WHERE status = 'inactive'")->fetchColumn(),
  'students' => (int) $pdo->query('SELECT COUNT(*) FROM students')->fetchColumn(),
];
$title = 'Sections';
$subtitle = 'Govern section lifecycle, student access, and academic grouping from one compact page';
require_once __DIR__ . '/../backend/partials/header.php';
?>
<div class="admin-shell">
  <section class="admin-stats-grid compact-stats-grid">
    <article class="card metric-card compact-metric-card"><span class="eyebrow">Sections</span><h3><?= number_format($stats['total']) ?></h3><p class="muted">Section groups tracked across active and historical terms.</p></article>
    <article class="card metric-card compact-metric-card"><span class="eyebrow">Active</span><h3><?= number_format($stats['active']) ?></h3><p class="muted">Available for current student workflows and subject inheritance.</p></article>
    <article class="card metric-card compact-metric-card"><span class="eyebrow">Inactive</span><h3><?= number_format($stats['inactive']) ?></h3><p class="muted">Temporarily blocked and placing students into view-only mode.</p></article>
    <article class="card metric-card compact-metric-card"><span class="eyebrow">Students</span><h3><?= number_format($stats['students']) ?></h3><p class="muted">Student records currently attached to section structures.</p></article>
  </section>

  <section class="admin-control-layout">
    <aside class="card admin-control-panel">
      <div class="panel-head"><div><p class="eyebrow">Control panel</p><h3>Section operations</h3></div></div>
      <div class="control-stack">
        <div class="control-item"><strong>Restriction flow</strong><p>Deactivating a section automatically moves linked students into view-only mode and blocks submission.</p></div>
        <div class="control-item"><strong>Archive safely</strong><p>Archive only when the section is fully historical and no longer belongs in active operational filters.</p></div>
      </div>
    </aside>

    <div class="card admin-control-main">
      <div class="admin-table-header"><div><p class="eyebrow">Section registry</p><h3>Lifecycle and reach</h3><p class="muted">Compact superadmin table for section health, load, and lifecycle actions.</p></div><div class="table-head-actions"><button class="btn" type="button" data-open-modal="admin-add-section">Add section</button><span class="pill soft">Rows <?= count($sections) ?></span></div></div>
      <form method="get" class="filter-row admin-filter-row admin-students-filters">
        <input type="text" name="search" placeholder="Search section or note" value="<?= h($search) ?>">
        <select name="status"><option value="">All statuses</option><option value="active" <?= selected($statusFilter,'active') ?>>Active</option><option value="inactive" <?= selected($statusFilter,'inactive') ?>>Inactive</option><option value="archived" <?= selected($statusFilter,'archived') ?>>Archived</option></select>
        <button class="btn" type="submit">Apply filters</button>
      </form>
      <div class="table-wrap admin-compact-table-wrap">
        <table class="table-redesign">
          <thead><tr><th>Section</th><th>Term</th><th>Students</th><th>Status</th><th>Actions</th></tr></thead>
          <tbody>
          <?php foreach ($sections as $row): ?>
            <tr>
              <td data-label="Section">
                <div class="admin-cell-stack">
                  <strong><?= h($row['section_name']) ?></strong>
                  <div class="muted small"><?= h($row['notes'] ?: 'No section notes added.') ?></div>
                  <div class="admin-meta-list"><span class="pill neutral"><?= (int) $row['total_subjects'] ?> subjects</span></div>
                </div>
              </td>
              <td data-label="Term"><div class="admin-cell-stack"><strong><?= h($row['school_year']) ?></strong><div class="muted small"><?= h($row['semester']) ?></div></div></td>
              <td data-label="Students"><strong><?= (int) $row['total_students'] ?></strong></td>
              <td data-label="Status"><?= status_badge($row['status']) ?></td>
              <td data-label="Actions" class="text-end">
                <div class="icon-action-group justify-content-end">
                  <button class="icon-action" type="button" data-open-modal="section-edit-<?= (int) $row['id'] ?>" title="edit section" aria-label="edit section"><i class="bi bi-pencil"></i></button>
                  <?php if ($row['status'] !== 'archived'): ?>
                  <form method="post" class="inline-icon-form">
                    <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
                    <input type="hidden" name="action" value="toggle">
                    <input type="hidden" name="section_id" value="<?= (int) $row['id'] ?>">
                    <input type="hidden" name="current_status" value="<?= h($row['status']) ?>">
                    <button class="icon-action <?= $row['status'] === 'active' ? 'warning' : 'success' ?>" type="submit" title="<?= $row['status'] === 'active' ? 'deactivate section' : 'reactivate section' ?>" aria-label="<?= $row['status'] === 'active' ? 'deactivate section' : 'reactivate section' ?>"><i class="bi <?= $row['status'] === 'active' ? 'bi-pause-circle' : 'bi-play-circle' ?>"></i></button>
                  </form>
                  <form method="post" class="inline-icon-form">
                    <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
                    <input type="hidden" name="action" value="archive">
                    <input type="hidden" name="section_id" value="<?= (int) $row['id'] ?>">
                    <button class="icon-action danger" type="submit" title="archive section" aria-label="archive section"><i class="bi bi-archive"></i></button>
                  </form>
                  <?php else: ?><span class="muted small">archived</span><?php endif; ?>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$sections): ?><tr><td colspan="5" class="empty-state">No sections matched your filters.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </section>
</div>


<?php foreach ($sections as $row): ?>
<div class="modal-backdrop" data-modal="section-edit-<?= (int) $row['id'] ?>" aria-hidden="true">
  <div class="modal-overlay"></div>
  <div class="modal-container">
  <div class="modal-card" role="dialog" aria-modal="true">
    <div class="modal-head"><div><span class="badge-soft"><i class="bi bi-pencil"></i> section</span><h3>edit <?= h($row['section_name']) ?></h3></div><button type="button" class="icon-btn modal-close" data-close-modal aria-label="Close">✕</button></div>
    <form id="section-edit-form-<?= (int) $row['id'] ?>" method="post" class="form-modal-grid modal-body">
      <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="section_id" value="<?= (int) $row['id'] ?>">
      <div><label>section name</label><input class="form-control" name="section_name" value="<?= h($row['section_name']) ?>"></div>
      <div><label>notes</label><input class="form-control" name="notes" value="<?= h($row['notes'] ?? '') ?>" placeholder="Optional notes"></div>
    </form>
    <div class="modal-footer"><div class="modal-actions"><button class="btn btn-outline" type="button" data-close-modal>close</button><button class="btn" type="submit" form="section-edit-form-<?= (int) $row['id'] ?>">save</button></div></div>
  </div>
  </div>
</div>
<?php endforeach; ?>

<div class="modal-backdrop" data-modal="admin-add-section" aria-hidden="true">
  <div class="modal-overlay"></div>
  <div class="modal-container">
  <div class="modal-card" role="dialog" aria-modal="true" aria-labelledby="admin-add-section-title">
    <div class="modal-head">
      <div><span class="pill soft">Superadmin modal</span><h3 id="admin-add-section-title">Add section</h3><p class="muted">Create a new section from the same registry screen.</p></div>
      <button type="button" class="icon-btn modal-close" data-close-modal aria-label="Close dialog">✕</button>
    </div>
    <form id="admin-add-section-form" method="post" class="form-grid modal-form-grid modal-body">
      <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
      <input type="hidden" name="action" value="create">
      <div><label>Section name</label><input name="section_name" placeholder="BSIT 22012" required></div>
      <div><label>Notes</label><input name="notes" placeholder="Optional notes"></div>
    </form>
    <div class="modal-footer"><div class="modal-actions"><button class="btn btn-outline" type="button" data-close-modal>Cancel</button><button class="btn" type="submit" form="admin-add-section-form">Create section</button></div></div>
  </div>
  </div>
</div>
<?php require_once __DIR__ . '/../backend/partials/footer.php'; ?>
