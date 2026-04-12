<?php
if (defined('FILE_ADMIN_REQUESTS_PHP_LOADED')) { return; }
define('FILE_ADMIN_REQUESTS_PHP_LOADED', true);

require_once __DIR__ . '/../backend/config/app.php';
require_role('admin');
$pdo = pdo();
$admin = current_user();
$sections = all_active_sections();
$search = trim($_GET['q'] ?? '');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $requestId = (int) ($_POST['request_id'] ?? 0);
    $action = $_POST['action'] ?? '';
    $adminNote = trim($_POST['admin_note'] ?? '');
    if ($requestId && in_array($action, ['approve', 'deny'], true)) {
        $lookup = $pdo->prepare('SELECT * FROM reactivation_requests WHERE id = ?');
        $lookup->execute([$requestId]);
        $request = $lookup->fetch();
        if ($request) {
            if ($action === 'approve') {
                $sectionId = (int) ($_POST['requested_section_id'] ?? $request['requested_section_id'] ?? 0);
                if ($sectionId) {
                    $pdo->prepare('UPDATE students SET section_id = ?, account_status = "active", can_submit = 1 WHERE id = ?')->execute([$sectionId, $request['student_id']]);
                } else {
                    $pdo->prepare('UPDATE students SET account_status = "active", can_submit = 1 WHERE id = ?')->execute([$request['student_id']]);
                }
                $pdo->prepare('UPDATE reactivation_requests SET status = "approved", admin_note = ?, reviewed_by = ?, reviewed_at = NOW() WHERE id = ?')->execute([$adminNote, $admin['id'], $requestId]);
                create_notification('student', (int)$request['student_id'], 'Reactivation approved', 'Your account has been reactivated. Please log in to continue your subject submissions.', 'success');
                set_flash('success', 'Request approved and student reactivated.');
            } else {
                $pdo->prepare('UPDATE reactivation_requests SET status = "denied", admin_note = ?, reviewed_by = ?, reviewed_at = NOW() WHERE id = ?')->execute([$adminNote, $admin['id'], $requestId]);
                create_notification('student', (int)$request['student_id'], 'Reactivation denied', 'Your reactivation request was denied. Please coordinate with the administrator for more details.', 'warning');
                set_flash('success', 'Request denied.');
            }
        }
        redirect_to('admin/requests.php');
    }
}
$sql = 'SELECT rr.*, st.full_name, st.student_id AS student_code, sec.section_name AS current_section FROM reactivation_requests rr JOIN students st ON st.id = rr.student_id JOIN sections sec ON sec.id = rr.current_section_id WHERE 1=1';
$params = [];
if ($search !== '') {
    $sql .= ' AND (st.student_id LIKE ? OR st.full_name LIKE ? OR st.email LIKE ?)';
    $like = "%{$search}%";
    $params = [$like, $like, $like];
}
$sql .= ' ORDER BY rr.created_at DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();
$title = 'Reactivation Requests';
$subtitle = 'Approve repeaters, reassign sections, and restore student submission rights';
require_once __DIR__ . '/../backend/partials/header.php';
?>
<div class="card highlight-card">
  <form method="get" class="filter-row">
    <input name="q" value="<?= h($search) ?>" placeholder="Search by student ID, name, or email">
    <button class="btn" type="submit">Search</button>
  </form>
</div>
<div class="card" style="margin-top:18px;">
  <div class="table-wrap"><table><thead><tr><th>Student</th><th>Current section</th><th>Reason</th><th>Status</th><th>Decision</th></tr></thead><tbody><?php foreach ($rows as $row): ?><tr><td><strong><?= h($row['full_name']) ?></strong><div class="muted small"><?= h($row['student_code']) ?></div><div style="margin-top:8px;"><a class="muted-link" href="<?= h(url('admin/student_view.php?id=' . (int) $row['student_id'])) ?>">Open student profile</a></div></td><td><?= h($row['current_section']) ?></td><td><?= h($row['reason']) ?></td><td><?= status_badge($row['status']) ?></td><td><?php if ($row['status'] === 'pending'): ?><form method="post" class="grid"><input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>"><input type="hidden" name="request_id" value="<?= (int) $row['id'] ?>"><select name="requested_section_id"><option value="">Keep / use requested</option><?php foreach ($sections as $section): ?><option value="<?= (int) $section['id'] ?>" <?= (int)$row['requested_section_id'] === (int)$section['id'] ? 'selected' : '' ?>><?= h($section['section_name']) ?></option><?php endforeach; ?></select><textarea name="admin_note" placeholder="Admin note"></textarea><div class="form-actions"><button class="btn" type="submit" name="action" value="approve">Approve</button><button class="btn btn-danger" type="submit" name="action" value="deny">Deny</button></div></form><?php else: ?><div class="muted small">Reviewed at <?= h($row['reviewed_at']) ?><br><?= h($row['admin_note']) ?></div><?php endif; ?></td></tr><?php endforeach; ?><?php if (!$rows): ?><tr><td colspan="5" class="empty-state">No requests matched your search.</td></tr><?php endif; ?></tbody></table></div>
</div>
<?php require_once __DIR__ . '/../backend/partials/footer.php'; ?>
