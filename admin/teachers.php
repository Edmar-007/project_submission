<?php
require_once __DIR__ . '/../backend/helpers/query.php';
require_role('admin');
$pdo = pdo();
$admin = current_user();
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
$teachers = $pdo->query('SELECT t.*, COUNT(s.id) AS total_subjects, COUNT(DISTINCT ss.section_id) AS total_sections FROM teachers t LEFT JOIN subjects s ON s.teacher_id = t.id LEFT JOIN section_subjects ss ON ss.subject_id = s.id GROUP BY t.id ORDER BY t.created_at DESC')->fetchAll();
$title = 'Teachers';
$subtitle = 'Create, edit, and manage teacher accounts for subject ownership';
require_once __DIR__ . '/../backend/partials/header.php';
?>
<div class="grid cols-2">
  <div class="card">
    <h3>Add teacher</h3>
    <form method="post" class="form-grid">
      <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
      <input type="hidden" name="action" value="create">
      <div><label>Teacher ID</label><input name="teacher_id" required></div>
      <div><label>Full name</label><input name="full_name" required></div>
      <div><label>Email</label><input type="email" name="email" required></div>
      <div><label>Username</label><input name="username" required></div>
      <div class="full"><label>Password</label><input type="password" name="password" required></div>
      <div class="full form-actions"><button class="btn" type="submit">Create teacher</button></div>
    </form>
  </div>
  <div class="card highlight-card">
    <h3>Teaching design</h3>
    <p class="muted">Teachers own subjects. Subjects are assigned to sections. Students inherit subject access through their section automatically.</p>
    <div class="analytics-bar" style="margin-top:16px;">
      <?php foreach (array_slice($teachers, 0, 4) as $teacher): ?>
        <div class="analytics-row"><div class="notification-title-row"><strong><?= h($teacher['full_name']) ?></strong><span class="muted small"><?= (int) $teacher['total_subjects'] ?> subject<?= (int) $teacher['total_subjects'] === 1 ? '' : 's' ?></span></div><div class="analytics-track"><div class="analytics-fill" style="width: <?= max(8, min(100, (int) $teacher['total_sections'] * 18)) ?>%"></div></div></div>
      <?php endforeach; ?>
    </div>
  </div>
</div>
<div class="card" style="margin-top:18px;">
  <div class="table-wrap"><table><thead><tr><th>Teacher</th><th>Coverage</th><th>Status</th><th>Actions</th></tr></thead><tbody><?php foreach ($teachers as $row): ?><tr><td><strong><?= h($row['full_name']) ?></strong><div class="muted small"><?= h($row['teacher_id']) ?> · <?= h($row['email']) ?></div></td><td><?= (int) $row['total_subjects'] ?> subjects · <?= (int) $row['total_sections'] ?> sections</td><td><?= status_badge($row['status']) ?></td><td><div class="table-actions"><a class="btn btn-secondary" href="<?= h(url('admin/teacher_view.php?id=' . (int) $row['id'])) ?>">View</a><details><summary class="btn btn-outline">Quick edit</summary><form method="post" class="grid" style="margin-top:12px; min-width:260px;"><input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>"><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int) $row['id'] ?>"><input name="full_name" value="<?= h($row['full_name']) ?>"><input type="email" name="email" value="<?= h($row['email']) ?>"><input name="username" value="<?= h($row['username']) ?>"><select name="status"><option value="active" <?= selected($row['status'],'active') ?>>Active</option><option value="inactive" <?= selected($row['status'],'inactive') ?>>Inactive</option></select><input type="password" name="password" placeholder="New password (optional)"><button class="btn btn-secondary" type="submit">Save</button></form></details></div></td></tr><?php endforeach; ?></tbody></table></div>
</div>
<?php require_once __DIR__ . '/../backend/partials/footer.php'; ?>
