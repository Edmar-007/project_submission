<?php
require_once __DIR__ . '/../backend/helpers/query.php';
require_role('admin');
$pdo = pdo();
$admin = current_user();
$search = trim($_GET['q'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $studentId = (int) ($_POST['student_pk'] ?? 0);
    $action = $_POST['action'] ?? '';
    if ($studentId && $action === 'update_status') {
        $newStatus = $_POST['account_status'] ?? 'active';
        $canSubmit = isset($_POST['can_submit']) ? 1 : 0;
        $sectionId = (int) ($_POST['section_id'] ?? 0);
        $stmt = $pdo->prepare('UPDATE students SET account_status = ?, can_submit = ?, section_id = ? WHERE id = ?');
        $stmt->execute([$newStatus, $canSubmit, $sectionId, $studentId]);
        create_notification('student', $studentId, 'Account updated', 'Your account access or section assignment has been updated by the administrator.', 'info');
        log_action('admin', (int)$admin['id'], 'update_student', 'student', $studentId, 'Status or section updated');
        set_flash('success', 'Student record updated.');
        redirect_to('admin/students.php');
    }
    if ($studentId && $action === 'archive') {
        $pdo->prepare('UPDATE students SET account_status = "archived", can_submit = 0 WHERE id = ?')->execute([$studentId]);
        create_notification('student', $studentId, 'Account archived', 'Your account is archived and remains available only for historical viewing by administrators.', 'warning');
        log_action('admin', (int)$admin['id'], 'archive_student', 'student', $studentId, 'Archived student account');
        set_flash('success', 'Student archived.');
        redirect_to('admin/students.php');
    }
}
$sections = $pdo->query('SELECT id, section_name FROM sections WHERE status <> "archived" ORDER BY section_name')->fetchAll();
$sql = 'SELECT st.*, sec.section_name, (SELECT COUNT(*) FROM submissions sub WHERE sub.student_id = st.id AND sub.status <> "archived") AS total_submissions FROM students st JOIN sections sec ON sec.id = st.section_id WHERE 1=1';
$params = [];
if ($search !== '') {
    $sql .= ' AND (st.student_id LIKE ? OR st.full_name LIKE ? OR st.email LIKE ?)';
    $like = "%{$search}%";
    $params = array_merge($params, [$like, $like, $like]);
}
if ($statusFilter !== '') {
    $sql .= ' AND st.account_status = ?';
    $params[] = $statusFilter;
}
$sql .= ' ORDER BY FIELD(st.account_status, "active","view_only","inactive","archived"), st.created_at DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$students = $stmt->fetchAll();
$title = 'Students';
$subtitle = 'Search by student ID, name, or email and manage access rights';
require_once __DIR__ . '/../backend/partials/header.php';
?>
<div class="grid cols-2">
<div class="card highlight-card">
  <form method="get" class="filter-row">
    <input name="q" placeholder="Search student ID, full name, email" value="<?= h($search) ?>">
    <select name="status">
      <option value="">All statuses</option>
      <option value="active" <?= selected($statusFilter, 'active') ?>>Active</option>
      <option value="view_only" <?= selected($statusFilter, 'view_only') ?>>View only</option>
      <option value="inactive" <?= selected($statusFilter, 'inactive') ?>>Inactive</option>
      <option value="archived" <?= selected($statusFilter, 'archived') ?>>Archived</option>
    </select>
    <button class="btn" type="submit">Search</button>
  </form>
  <div class="table-note">Tip: search by student ID to reactivate repeaters quickly after a section-wide restriction.</div>
</div>
<div class="card">
  <h3 class="section-title">Secure onboarding recommendation</h3>
  <p class="muted">Use teacher invitations instead of open registration. Students receive an activation link and create their own password after verifying their official student ID and email address.</p>
  <div class="timeline-list">
    <div class="timeline-item"><strong>Pending</strong><p>Invited but not yet activated.</p></div>
    <div class="timeline-item"><strong>Active</strong><p>Can sign in with student ID or email and submit when allowed.</p></div>
  </div>
</div>
</div>
<div class="card" style="margin-top:18px;">
  <div class="table-wrap"><table><thead><tr><th>Student</th><th>Section</th><th>Status</th><th>Access</th><th>Submissions</th><th>Actions</th></tr></thead><tbody><?php foreach ($students as $row): ?><tr><td><strong><?= h($row['full_name']) ?></strong><div class="muted small"><?= h($row['student_id']) ?> · <?= h($row['email']) ?></div></td><td><?= h($row['section_name']) ?></td><td><?= status_badge($row['account_status']) ?></td><td><?= $row['can_submit'] ? 'Can submit' : 'View only' ?></td><td><?= (int) $row['total_submissions'] ?></td><td><div class="table-actions"><a class="btn btn-secondary" href="<?= h(url('admin/student_view.php?id=' . (int) $row['id'])) ?>">View</a><?php if ($row['account_status'] !== 'archived'): ?><form method="post" class="inline"><input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>"><input type="hidden" name="action" value="archive"><input type="hidden" name="student_pk" value="<?= (int) $row['id'] ?>"><button class="btn btn-outline" type="submit">Archive</button></form><?php endif; ?><details><summary class="btn btn-outline">Quick edit</summary><form method="post" class="form-grid" style="margin-top:12px; min-width:260px;"><input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>"><input type="hidden" name="action" value="update_status"><input type="hidden" name="student_pk" value="<?= (int) $row['id'] ?>"><div><select name="account_status"><?php foreach (['active','view_only','inactive','archived'] as $s): ?><option value="<?= h($s) ?>" <?= $row['account_status']===$s?'selected':'' ?>><?= h($s) ?></option><?php endforeach; ?></select></div><div><select name="section_id"><?php foreach ($sections as $sec): ?><option value="<?= (int) $sec['id'] ?>" <?= (int)$row['section_id']===(int)$sec['id']?'selected':'' ?>><?= h($sec['section_name']) ?></option><?php endforeach; ?></select></div><div><label><input type="checkbox" name="can_submit" value="1" <?= $row['can_submit'] ? 'checked' : '' ?>> Allow submit</label></div><div><button class="btn btn-secondary" type="submit">Save</button></div></form></details></div></td></tr><?php endforeach; ?><?php if (!$students): ?><tr><td colspan="6" class="empty-state">No students matched your filters.</td></tr><?php endif; ?></tbody></table></div>
</div>
<?php require_once __DIR__ . '/../backend/partials/footer.php'; ?>
