<?php
require_once __DIR__ . '/../backend/helpers/query.php';
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
$subtitle = 'Manage school years and semesters before sections and subjects are assigned';
require_once __DIR__ . '/../backend/partials/header.php';
?>
<div class="grid cols-2">
  <div class="card">
    <div class="split-header"><div><h3 class="section-title">School years</h3><div class="muted small">Activate the year you want current users to work in.</div></div></div>
    <form method="post" class="form-grid" style="margin-bottom:16px;">
      <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
      <input type="hidden" name="action" value="add_year">
      <div><label>Label</label><input name="label" placeholder="2026-2027" required></div>
      <div class="form-actions" style="align-items:end;"><button class="btn" type="submit">Add school year</button></div>
    </form>
    <div class="table-wrap"><table><thead><tr><th>Label</th><th>Status</th><th>Action</th></tr></thead><tbody><?php foreach ($years as $row): ?><tr><td><strong><?= h($row['label']) ?></strong></td><td><?= $row['is_active'] ? status_badge('active') : status_badge('inactive') ?></td><td><form method="post" class="inline"><input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>"><input type="hidden" name="action" value="toggle_year"><input type="hidden" name="id" value="<?= (int)$row['id'] ?>"><button class="btn btn-secondary" type="submit"><?= $row['is_active'] ? 'Deactivate' : 'Activate' ?></button></form></td></tr><?php endforeach; ?></tbody></table></div>
  </div>
  <div class="card">
    <div class="split-header"><div><h3 class="section-title">Semesters</h3><div class="muted small">Use multiple semesters per school year if needed.</div></div></div>
    <form method="post" class="form-grid" style="margin-bottom:16px;">
      <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
      <input type="hidden" name="action" value="add_semester">
      <div><label>School year</label><select name="school_year_id" required><?php foreach ($years as $row): ?><option value="<?= (int)$row['id'] ?>"><?= h($row['label']) ?></option><?php endforeach; ?></select></div>
      <div><label>Name</label><input name="name" placeholder="1st Semester" required></div>
      <div class="full form-actions"><button class="btn" type="submit">Add semester</button></div>
    </form>
    <div class="table-wrap"><table><thead><tr><th>Semester</th><th>School year</th><th>Status</th><th>Action</th></tr></thead><tbody><?php foreach ($semesters as $row): ?><tr><td><strong><?= h($row['name']) ?></strong></td><td><?= h($row['school_year']) ?></td><td><?= $row['is_active'] ? status_badge('active') : status_badge('inactive') ?></td><td><form method="post" class="inline"><input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>"><input type="hidden" name="action" value="toggle_semester"><input type="hidden" name="id" value="<?= (int)$row['id'] ?>"><button class="btn btn-secondary" type="submit"><?= $row['is_active'] ? 'Deactivate' : 'Activate' ?></button></form></td></tr><?php endforeach; ?></tbody></table></div>
  </div>
</div>
<?php require_once __DIR__ . '/../backend/partials/footer.php'; ?>
