<?php
require_once __DIR__ . '/../backend/helpers/query.php';
require_role('admin');
$pdo = pdo();
$admin = current_user();
$statusFilter = trim($_GET['status'] ?? '');
$search = trim($_GET['q'] ?? '');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? 'create';
    if ($action === 'create') {
        $teacherId = trim($_POST['teacher_id'] ?? '');
        $fullName = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        if ($teacherId && $fullName && $email && $username && $password) {
            try {
                $stmt = $pdo->prepare('INSERT INTO teachers (teacher_id, full_name, email, username, password_hash, status) VALUES (?, ?, ?, ?, ?, "active")');
                $stmt->execute([$teacherId, $fullName, $email, $username, password_hash($password, PASSWORD_DEFAULT)]);
                log_action('admin', (int) $admin['id'], 'create_teacher', 'teacher', (int) $pdo->lastInsertId(), $fullName);
                set_flash('Teacher account created.', 'success');
            } catch (Throwable $e) {
                set_flash('Unable to create teacher account.', 'error');
            }
        }
    }
    if ($action === 'save') {
        $id = (int) ($_POST['id'] ?? 0);
        $fullName = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $status = $_POST['status'] ?? 'active';
        if ($id && $fullName && $email && $username) {
            $pdo->prepare('UPDATE teachers SET full_name = ?, email = ?, username = ?, status = ? WHERE id = ?')->execute([$fullName, $email, $username, $status, $id]);
            if (!empty($_POST['password'])) {
                $pdo->prepare('UPDATE teachers SET password_hash = ? WHERE id = ?')->execute([password_hash($_POST['password'], PASSWORD_DEFAULT), $id]);
            }
            set_flash('Teacher updated successfully.', 'success');
        }
    }
    redirect_to('admin/teachers.php');
}
$sql = 'SELECT t.*, COUNT(DISTINCT s.id) AS total_subjects, COUNT(DISTINCT ss.section_id) AS total_sections FROM teachers t LEFT JOIN subjects s ON s.teacher_id = t.id LEFT JOIN section_subjects ss ON ss.subject_id = s.id WHERE 1=1';
$params = [];
if ($search !== '') {
    $sql .= ' AND (t.teacher_id LIKE ? OR t.full_name LIKE ? OR t.email LIKE ? OR t.username LIKE ?)';
    $like = "%{$search}%";
    array_push($params, $like, $like, $like, $like);
}
if ($statusFilter !== '') { $sql .= ' AND t.status = ?'; $params[] = $statusFilter; }
$sql .= ' GROUP BY t.id ORDER BY FIELD(t.status, "active","inactive"), t.created_at DESC';
$stmt = $pdo->prepare($sql); $stmt->execute($params); $teachers = $stmt->fetchAll();
$stats = [
    'total' => (int) $pdo->query('SELECT COUNT(*) FROM teachers')->fetchColumn(),
    'active' => (int) $pdo->query("SELECT COUNT(*) FROM teachers WHERE status = 'active'")->fetchColumn(),
    'coverage' => (int) $pdo->query('SELECT COUNT(DISTINCT teacher_id) FROM subjects WHERE teacher_id IS NOT NULL')->fetchColumn(),
    'subjects' => (int) $pdo->query('SELECT COUNT(*) FROM subjects')->fetchColumn(),
];
$title = 'Teachers';
$subtitle = 'Faculty registry redesigned into one main table plus modal actions.';
require_once __DIR__ . '/../backend/partials/header.php';
?>
<div class="kpi-grid">
  <div class="kpi-card"><span class="label">Faculty</span><strong><?= number_format($stats['total']) ?></strong><span class="muted small">Teacher records</span></div>
  <div class="kpi-card"><span class="label">Active</span><strong><?= number_format($stats['active']) ?></strong><span class="muted small">Can manage classes</span></div>
  <div class="kpi-card"><span class="label">Coverage</span><strong><?= number_format($stats['coverage']) ?></strong><span class="muted small">Own at least one subject</span></div>
  <div class="kpi-card"><span class="label">Subjects</span><strong><?= number_format($stats['subjects']) ?></strong><span class="muted small">Total subjects in system</span></div>
</div>
<section class="table-card table-bootstrap-shell">
  <div class="module-header"><div><div class="eyebrow">Teacher Registry</div><h3 class="mb-1">Faculty Coverage Table</h3><p class="muted mb-0">Create from the Add button, then edit row details with icon actions.</p></div><div class="module-actions"><button class="btn" type="button" data-open-modal="admin-add-teacher"><i class="bi bi-plus-lg"></i> Add Teacher</button><span class="badge-soft"><i class="bi bi-table"></i> <?= count($teachers) ?> rows</span></div></div>
  <div class="table-toolbar"><form method="get" class="filters"><input class="form-control" name="q" placeholder="Search teacher ID, name, email, username" value="<?= h($search) ?>"><select class="form-select" name="status"><option value="">All statuses</option><option value="active" <?= selected($statusFilter, 'active') ?>>Active</option><option value="inactive" <?= selected($statusFilter, 'inactive') ?>>Inactive</option></select><button class="btn" type="submit"><i class="bi bi-funnel"></i> Apply Filters</button></form></div>
  <div class="table-responsive"><table class="table table-hover align-middle"><thead><tr><th>Teacher</th><th>Coverage</th><th>Status</th><th class="text-end">Actions</th></tr></thead><tbody><?php foreach ($teachers as $row): ?><tr><td><strong><?= h($row['full_name']) ?></strong><div class="muted small"><?= h($row['teacher_id']) ?> · <?= h($row['username']) ?></div><div class="muted small"><?= h($row['email']) ?></div></td><td><div class="d-flex gap-2 flex-wrap"><span class="badge-soft"><i class="bi bi-book"></i> <?= (int) $row['total_subjects'] ?> subjects</span><span class="badge-soft"><i class="bi bi-diagram-3"></i> <?= (int) $row['total_sections'] ?> sections</span></div></td><td><?= status_badge($row['status']) ?></td><td class="text-end"><div class="icon-action-group justify-content-end"><a class="icon-action" href="<?= h(url('admin/teacher_preview.php?id=' . (int) $row['id'])) ?>" data-ajax-modal="1" data-modal-title="Teacher overview" title="View teacher"><i class="bi bi-eye"></i></a><button class="icon-action" type="button" data-open-modal="edit-teacher-<?= (int) $row['id'] ?>" title="Edit teacher"><i class="bi bi-pencil"></i></button></div></td></tr><?php endforeach; ?><?php if (!$teachers): ?><tr><td colspan="4" class="table-empty">No teacher records matched your filters.</td></tr><?php endif; ?></tbody></table></div>
</section>
<div class="modal-backdrop" data-modal="admin-add-teacher" aria-hidden="true"><div class="modal-card" role="dialog" aria-modal="true"><div class="modal-head"><div><span class="badge-soft"><i class="bi bi-person-plus"></i> Teacher</span><h3>Add Teacher</h3></div><button type="button" class="icon-btn modal-close" data-close-modal aria-label="Close">✕</button></div><form method="post" class="form-modal-grid"><input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>"><input type="hidden" name="action" value="create"><div><label>Teacher ID</label><input class="form-control" name="teacher_id" required></div><div><label>Full name</label><input class="form-control" name="full_name" required></div><div><label>Email</label><input class="form-control" type="email" name="email" required></div><div><label>Username</label><input class="form-control" name="username" required></div><div class="full"><label>Password</label><input class="form-control" type="password" name="password" required></div><div class="full d-flex justify-content-end gap-2"><button class="btn btn-outline" type="button" data-close-modal>Cancel</button><button class="btn" type="submit">Create teacher</button></div></form></div></div>
<?php foreach ($teachers as $row): ?><div class="modal-backdrop" data-modal="edit-teacher-<?= (int) $row['id'] ?>" aria-hidden="true"><div class="modal-card" role="dialog" aria-modal="true"><div class="modal-head"><div><span class="badge-soft"><i class="bi bi-pencil"></i> Teacher</span><h3>Edit <?= h($row['full_name']) ?></h3></div><button type="button" class="icon-btn modal-close" data-close-modal aria-label="Close">✕</button></div><form method="post" class="form-modal-grid"><input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>"><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int) $row['id'] ?>"><div><label>Full name</label><input class="form-control" name="full_name" value="<?= h($row['full_name']) ?>" required></div><div><label>Email</label><input class="form-control" type="email" name="email" value="<?= h($row['email']) ?>" required></div><div><label>Username</label><input class="form-control" name="username" value="<?= h($row['username']) ?>" required></div><div><label>Status</label><select class="form-select" name="status"><option value="active" <?= selected($row['status'],'active') ?>>Active</option><option value="inactive" <?= selected($row['status'],'inactive') ?>>Inactive</option></select></div><div class="full"><label>Reset password</label><input class="form-control" type="password" name="password" placeholder="Leave blank to keep current password"></div><div class="full d-flex justify-content-end gap-2"><button class="btn btn-outline" type="button" data-close-modal>Cancel</button><button class="btn" type="submit">Save changes</button></div></form></div></div><?php endforeach; ?>
<?php require_once __DIR__ . '/../backend/partials/footer.php'; ?>
