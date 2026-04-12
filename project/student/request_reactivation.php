<?php
require_once __DIR__ . '/../backend/helpers/query.php';
require_role('student');
$student = current_user();
$sections = all_active_sections();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $reason = trim($_POST['reason'] ?? '');
    $requestedSectionId = (int) ($_POST['requested_section_id'] ?? 0);
    if ($reason) {
        pdo()->prepare('INSERT INTO reactivation_requests (student_id, current_section_id, requested_section_id, reason, status) VALUES (?, ?, ?, ?, "pending")')->execute([$student['id'], $student['section_id'], $requestedSectionId ?: null, $reason]);
        create_notification('admin', 1, 'New reactivation request', $student['full_name'] . ' submitted a reactivation request.', 'info');
        set_flash('success', 'Reactivation request sent successfully.');
        redirect_to('student/request_reactivation.php');
    }
}
$stmt = pdo()->prepare('SELECT * FROM reactivation_requests WHERE student_id = ? ORDER BY created_at DESC');
$stmt->execute([$student['id']]);
$rows = $stmt->fetchAll();
$title = 'Request Reactivation';
$subtitle = 'Use this when you need submission access restored after your section was restricted';
require_once __DIR__ . '/../backend/partials/header.php';
?>
<div class="grid cols-2">
  <div class="card">
    <h3 class="section-title">Submit request</h3>
    <p class="muted">Ideal for repeaters or students who need to be moved into a new active section.</p>
    <form method="post" class="grid">
      <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
      <div><label>Requested section (optional)</label><select name="requested_section_id"><option value="">Keep for admin review</option><?php foreach ($sections as $section): ?><option value="<?= (int)$section['id'] ?>"><?= h($section['section_name']) ?></option><?php endforeach; ?></select></div>
      <div><label>Reason</label><textarea name="reason" required placeholder="I am repeating the subject/year and need my account restored."></textarea></div>
      <div class="form-actions"><button class="btn" type="submit">Send request</button></div>
    </form>
  </div>
  <div class="card">
    <h3 class="section-title">My request history</h3>
    <div class="timeline-list">
      <?php foreach ($rows as $row): ?>
        <div class="timeline-item">
          <div class="notification-title-row"><?= status_badge($row['status']) ?><span class="muted small"><?= h($row['created_at']) ?></span></div>
          <p><strong>Reason:</strong> <?= h($row['reason']) ?></p>
          <p><strong>Admin note:</strong> <?= h($row['admin_note'] ?: 'No note yet.') ?></p>
        </div>
      <?php endforeach; ?>
      <?php if (!$rows): ?><div class="empty-state">No reactivation requests yet.</div><?php endif; ?>
    </div>
  </div>
</div>
<?php require_once __DIR__ . '/../backend/partials/footer.php'; ?>
