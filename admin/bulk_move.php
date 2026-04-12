<?php
if (defined('FILE_ADMIN_BULK_MOVE_PHP_LOADED')) { return; }
define('FILE_ADMIN_BULK_MOVE_PHP_LOADED', true);

require_once __DIR__ . '/../backend/config/app.php';
require_role('admin');
$pdo = pdo();
$admin = current_user();
$sections = all_sections();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $from = (int)($_POST['from_section_id'] ?? 0);
    $to = (int)($_POST['to_section_id'] ?? 0);
    $restore = isset($_POST['restore_submit']) ? 1 : 0;
    if ($from && $to && $from !== $to) {
        $stmt = $pdo->prepare('UPDATE students SET section_id = ?, account_status = CASE WHEN ? = 1 THEN "active" ELSE account_status END, can_submit = CASE WHEN ? = 1 THEN 1 ELSE can_submit END WHERE section_id = ?');
        $stmt->execute([$to, $restore, $restore, $from]);
        $moved = $stmt->rowCount();
        $studentIds = $pdo->prepare('SELECT id FROM students WHERE section_id = ?');
        $studentIds->execute([$to]);
        foreach ($studentIds->fetchAll(PDO::FETCH_COLUMN) as $sid) {
            create_notification('student', (int)$sid, 'Section updated', 'Your section assignment has been updated by the administrator.', 'info');
        }
        log_action('admin', (int)$admin['id'], 'bulk_move_students', 'section', $from, 'Moved students to section ' . $to . ' count=' . $moved);
        set_flash('success', 'Students moved successfully.');
    } else {
        set_flash('error', 'Choose two different sections.');
    }
    redirect_to('admin/bulk_move.php');
}
$sectionCounts = pdo()->query('SELECT sec.id, sec.section_name, sec.status, COUNT(st.id) AS total_students FROM sections sec LEFT JOIN students st ON st.section_id = sec.id GROUP BY sec.id ORDER BY sec.section_name')->fetchAll();
$title = 'Bulk Move Students';
$subtitle = 'Move an entire section cohort into another section in one operation';
require_once __DIR__ . '/../backend/partials/header.php';
?>
<div class="grid cols-2">
  <div class="card">
    <h3 class="section-title">Bulk section reassignment</h3>
    <form method="post" class="form-grid">
      <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
      <div><label>From section</label><select name="from_section_id" required><?php foreach ($sections as $row): ?><option value="<?= (int)$row['id'] ?>"><?= h($row['section_name']) ?> · <?= h($row['school_year']) ?></option><?php endforeach; ?></select></div>
      <div><label>To section</label><select name="to_section_id" required><?php foreach ($sections as $row): ?><option value="<?= (int)$row['id'] ?>"><?= h($row['section_name']) ?> · <?= h($row['school_year']) ?></option><?php endforeach; ?></select></div>
      <div class="full"><label><input type="checkbox" name="restore_submit" value="1"> Restore active + submit access after move</label></div>
      <div class="full form-actions"><button class="btn" type="submit">Move students now</button></div>
    </form>
  </div>
  <div class="card">
    <h3 class="section-title">Current section counts</h3>
    <div class="table-wrap"><table><thead><tr><th>Section</th><th>Status</th><th>Students</th></tr></thead><tbody><?php foreach ($sectionCounts as $row): ?><tr><td><?= h($row['section_name']) ?></td><td><?= status_badge($row['status']) ?></td><td><?= (int)$row['total_students'] ?></td></tr><?php endforeach; ?></tbody></table></div>
  </div>
</div>
<?php require_once __DIR__ . '/../backend/partials/footer.php'; ?>
