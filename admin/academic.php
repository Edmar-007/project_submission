<?php
if (defined('FILE_ADMIN_ACADEMIC_PHP_LOADED')) { return; }
define('FILE_ADMIN_ACADEMIC_PHP_LOADED', true);

require_once __DIR__ . '/../backend/config/app.php';
require_role('admin');
$pdo = pdo();
$admin = current_user();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'add_year') {
        $label = trim($_POST['label'] ?? '');
        if ($label !== '') {
            $pdo->prepare('INSERT INTO school_years (label, is_active) VALUES (?, 0)')->execute([$label]);
            log_action('admin', (int)$admin['id'], 'create_school_year', 'school_year', (int)$pdo->lastInsertId(), 'Added school year ' . $label);
            set_flash('success', 'School year added.');
        }
    }
    if ($action === 'toggle_year') {
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare('UPDATE school_years SET is_active = IF(is_active = 1, 0, 1) WHERE id = ?')->execute([$id]);
        log_action('admin', (int)$admin['id'], 'toggle_school_year', 'school_year', $id, 'Toggled active state');
        set_flash('success', 'School year status updated.');
    }
    if ($action === 'add_semester') {
        $schoolYearId = (int)($_POST['school_year_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        if ($schoolYearId && $name !== '') {
            $pdo->prepare('INSERT INTO semesters (school_year_id, name, is_active) VALUES (?, ?, 0)')->execute([$schoolYearId, $name]);
            log_action('admin', (int)$admin['id'], 'create_semester', 'semester', (int)$pdo->lastInsertId(), 'Added semester ' . $name);
            set_flash('success', 'Semester added.');
        }
    }
    if ($action === 'toggle_semester') {
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare('UPDATE semesters SET is_active = IF(is_active = 1, 0, 1) WHERE id = ?')->execute([$id]);
        log_action('admin', (int)$admin['id'], 'toggle_semester', 'semester', $id, 'Toggled active state');
        set_flash('success', 'Semester status updated.');
    }
    redirect_to('admin/academic.php');
}
$years = all_school_years();
$semesters = all_semesters();
$title = 'Academic Setup';
$subtitle = 'Split academic setup into focused tables so the page stays open and readable.';
require_once __DIR__ . '/../backend/partials/header.php';
?>
<div class="kpi-grid">
  <div class="kpi-card"><span class="label">School Years</span><strong><?= count($years) ?></strong><span class="muted small">Configured academic cycles</span></div>
  <div class="kpi-card"><span class="label">Active Year</span><strong><?= count(array_filter($years, fn($r) => (int)$r['is_active'] === 1)) ?></strong><span class="muted small">Currently enabled school years</span></div>
  <div class="kpi-card"><span class="label">Semesters</span><strong><?= count($semesters) ?></strong><span class="muted small">Semester records available</span></div>
  <div class="kpi-card"><span class="label">Active Semester</span><strong><?= count(array_filter($semesters, fn($r) => (int)$r['is_active'] === 1)) ?></strong><span class="muted small">Semesters open for use</span></div>
</div>

<div class="row g-4">
  <div class="col-12 col-xl-6">
    <section class="table-card table-bootstrap-shell">
      <div class="module-header">
        <div>
          <div class="eyebrow">Academic</div>
          <h3 class="mb-1">School Years</h3>
          <p class="muted mb-0">One table, one action area. Add a year in a modal, then activate it from the row.</p>
        </div>
        <div class="module-actions">
          <button class="btn" type="button" data-open-modal="add-year"><i class="bi bi-plus-lg"></i> Add School Year</button>
        </div>
      </div>
      <div class="table-responsive">
        <table class="table table-hover align-middle">
          <thead><tr><th>Label</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
          <tbody>
          <?php foreach ($years as $row): ?>
            <tr>
              <td><strong><?= h($row['label']) ?></strong></td>
              <td><?= $row['is_active'] ? status_badge('active') : status_badge('inactive') ?></td>
              <td class="text-end">
                <form method="post" class="d-inline">
                  <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
                  <input type="hidden" name="action" value="toggle_year">
                  <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                  <button class="icon-action <?= $row['is_active'] ? 'danger' : 'success' ?>" type="submit" title="<?= $row['is_active'] ? 'Deactivate' : 'Activate' ?> school year">
                    <i class="bi <?= $row['is_active'] ? 'bi-pause-circle' : 'bi-play-circle' ?>"></i>
                  </button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$years): ?><tr><td colspan="3" class="table-empty">No school years yet.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </section>
  </div>
  <div class="col-12 col-xl-6">
    <section class="table-card table-bootstrap-shell">
      <div class="module-header">
        <div>
          <div class="eyebrow">Academic</div>
          <h3 class="mb-1">Semesters</h3>
          <p class="muted mb-0">Keep semesters in their own table so the page stays less crowded.</p>
        </div>
        <div class="module-actions">
          <button class="btn" type="button" data-open-modal="add-semester"><i class="bi bi-plus-lg"></i> Add Semester</button>
        </div>
      </div>
      <div class="table-responsive">
        <table class="table table-hover align-middle">
          <thead><tr><th>Semester</th><th>School Year</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
          <tbody>
          <?php foreach ($semesters as $row): ?>
            <tr>
              <td><strong><?= h($row['name']) ?></strong></td>
              <td><?= h($row['school_year']) ?></td>
              <td><?= $row['is_active'] ? status_badge('active') : status_badge('inactive') ?></td>
              <td class="text-end">
                <form method="post" class="d-inline">
                  <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
                  <input type="hidden" name="action" value="toggle_semester">
                  <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                  <button class="icon-action <?= $row['is_active'] ? 'danger' : 'success' ?>" type="submit" title="<?= $row['is_active'] ? 'Deactivate' : 'Activate' ?> semester">
                    <i class="bi <?= $row['is_active'] ? 'bi-pause-circle' : 'bi-play-circle' ?>"></i>
                  </button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$semesters): ?><tr><td colspan="4" class="table-empty">No semesters yet.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </section>
  </div>
</div>

<div class="modal-backdrop" data-modal="add-year" aria-hidden="true">
  <div class="modal-overlay"></div>
  <div class="modal-container">
  <div class="modal-card" role="dialog" aria-modal="true">
    <div class="modal-head"><div><span class="badge-soft"><i class="bi bi-calendar3"></i> Academic Setup</span><h3>Add School Year</h3></div><button type="button" class="icon-btn modal-close" data-close-modal aria-label="Close">✕</button></div>
    <form id="add-year-form" method="post" class="form-modal-grid modal-body">
      <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
      <input type="hidden" name="action" value="add_year">
      <div class="full"><label>Label</label><input class="form-control" name="label" placeholder="2026-2027" required></div>
    </form>
    <div class="modal-footer"><div class="modal-actions"><button class="btn btn-outline" type="button" data-close-modal>Cancel</button><button class="btn" type="submit" form="add-year-form">Save school year</button></div></div>
  </div>
  </div>
</div>

<div class="modal-backdrop" data-modal="add-semester" aria-hidden="true">
  <div class="modal-overlay"></div>
  <div class="modal-container">
  <div class="modal-card" role="dialog" aria-modal="true">
    <div class="modal-head"><div><span class="badge-soft"><i class="bi bi-calendar2-week"></i> Academic Setup</span><h3>Add Semester</h3></div><button type="button" class="icon-btn modal-close" data-close-modal aria-label="Close">✕</button></div>
    <form id="add-semester-form" method="post" class="form-modal-grid modal-body">
      <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
      <input type="hidden" name="action" value="add_semester">
      <div><label>School year</label><select class="form-select" name="school_year_id" required><?php foreach ($years as $row): ?><option value="<?= (int)$row['id'] ?>"><?= h($row['label']) ?></option><?php endforeach; ?></select></div>
      <div><label>Name</label><input class="form-control" name="name" placeholder="1st Semester" required></div>
    </form>
    <div class="modal-footer"><div class="modal-actions"><button class="btn btn-outline" type="button" data-close-modal>Cancel</button><button class="btn" type="submit" form="add-semester-form">Save semester</button></div></div>
  </div>
  </div>
</div>
<?php require_once __DIR__ . '/../backend/partials/footer.php'; ?>
