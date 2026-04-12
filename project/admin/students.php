<?php
require_once __DIR__ . '/../backend/helpers/query.php';
require_role('admin');
$pdo = pdo();
$admin = current_user();
$search = trim($_GET['q'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');
$sectionFilter = (int) ($_GET['section_id'] ?? 0);
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
if ($sectionFilter > 0) {
    $sql .= ' AND st.section_id = ?';
    $params[] = $sectionFilter;
}
$sql .= ' ORDER BY FIELD(st.account_status, "active","view_only","inactive","archived"), st.created_at DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$students = $stmt->fetchAll();
$stats = [
  'total' => (int) $pdo->query('SELECT COUNT(*) FROM students')->fetchColumn(),
  'active' => (int) $pdo->query("SELECT COUNT(*) FROM students WHERE account_status = 'active'")->fetchColumn(),
  'restricted' => (int) $pdo->query('SELECT COUNT(*) FROM students WHERE can_submit = 0')->fetchColumn(),
  'archived' => (int) $pdo->query("SELECT COUNT(*) FROM students WHERE account_status = 'archived'")->fetchColumn(),
];
$title = 'Students';
$subtitle = 'Structured roster control for sections, access rights, and account status';
require_once __DIR__ . '/../backend/partials/header.php';
?>

<div class="admin-students-shell">
  <section class="admin-stats-grid compact-stats-grid">
    <article class="card metric-card compact-metric-card">
      <span class="eyebrow">Roster</span>
      <h3><?= number_format($stats['total']) ?></h3>
      <p class="muted">Total student records across all sections.</p>
    </article>
    <article class="card metric-card compact-metric-card">
      <span class="eyebrow">Active</span>
      <h3><?= number_format($stats['active']) ?></h3>
      <p class="muted">Currently allowed to access the portal.</p>
    </article>
    <article class="card metric-card compact-metric-card">
      <span class="eyebrow">Restricted</span>
      <h3><?= number_format($stats['restricted']) ?></h3>
      <p class="muted">Accounts in view-only or blocked submission state.</p>
    </article>
    <article class="card metric-card compact-metric-card">
      <span class="eyebrow">Archived</span>
      <h3><?= number_format($stats['archived']) ?></h3>
      <p class="muted">Historical records kept for reporting only.</p>
    </article>
  </section>

  <section class="admin-students-layout">
    <aside class="card admin-students-panel">
      <div class="panel-head">
        <div>
          <p class="eyebrow">Control panel</p>
          <h3>Student operations</h3>
        </div>
      </div>
      <div class="control-stack">
        <div class="control-item">
          <strong>Search and filter</strong>
          <p>Use section and status filters to find records quickly before applying updates.</p>
        </div>
        <div class="control-item">
          <strong>Access governance</strong>
          <p>Move students to view-only when submission windows close or when review is pending.</p>
        </div>
        <div class="control-item">
          <strong>Archive safely</strong>
          <p>Archive old records instead of deleting them to preserve submissions and audit history.</p>
        </div>
      </div>
      <div class="panel-note soft-note">
        Use the row actions for day-to-day updates. Keep major identity changes tied to official school records.
      </div>
    </aside>

    <div class="card admin-students-main">
      <div class="admin-table-header">
        <div>
          <p class="eyebrow">Student registry</p>
          <h3>Section-based roster</h3>
          <p class="muted">Compact operational view for superadmin management.</p>
        </div>
        <div class="table-head-actions">
          <span class="pill soft">Filtered rows <?= count($students) ?></span>
        </div>
      </div>

      <form method="get" class="filter-row admin-filter-row admin-students-filters">
        <input name="q" placeholder="Search student ID, full name, email" value="<?= h($search) ?>">
        <select name="section_id">
          <option value="0">All sections</option>
          <?php foreach ($sections as $sec): ?>
            <option value="<?= (int) $sec['id'] ?>" <?= $sectionFilter === (int) $sec['id'] ? 'selected' : '' ?>><?= h($sec['section_name']) ?></option>
          <?php endforeach; ?>
        </select>
        <select name="status">
          <option value="">All statuses</option>
          <option value="active" <?= selected($statusFilter, 'active') ?>>Active</option>
          <option value="view_only" <?= selected($statusFilter, 'view_only') ?>>View only</option>
          <option value="inactive" <?= selected($statusFilter, 'inactive') ?>>Inactive</option>
          <option value="archived" <?= selected($statusFilter, 'archived') ?>>Archived</option>
        </select>
        <button class="btn" type="submit">Apply filters</button>
      </form>

      <div class="table-wrap admin-compact-table-wrap">
        <table class="table-redesign">
          <thead>
            <tr>
              <th>Student</th>
              <th>Section</th>
              <th>Status</th>
              <th>Access</th>
              <th>Submissions</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($students as $row): ?>
            <tr>
              <td data-label="Student">
                <div class="admin-person-cell">
                  <div class="admin-avatar"><?= strtoupper(substr(trim((string) $row['full_name']), 0, 1)) ?></div>
                  <div>
                    <strong><?= h($row['full_name']) ?></strong>
                    <div class="muted small"><?= h($row['student_id']) ?></div>
                    <div class="muted small"><?= h($row['email']) ?></div>
                  </div>
                </div>
              </td>
              <td data-label="Section"><span class="pill neutral"><?= h($row['section_name']) ?></span></td>
              <td data-label="Status"><?= status_badge($row['account_status']) ?></td>
              <td data-label="Access">
                <span class="admin-access-badge <?= $row['can_submit'] ? 'allow' : 'restrict' ?>">
                  <?= $row['can_submit'] ? 'Can submit' : 'Restricted' ?>
                </span>
              </td>
              <td data-label="Submissions"><strong><?= (int) $row['total_submissions'] ?></strong></td>
              <td data-label="Actions" class="text-end">
                <div class="icon-action-group justify-content-end">
                  <button class="icon-action" type="button" data-open-modal="student-view-<?= (int) $row['id'] ?>" title="view student" aria-label="view student">
                    <i class="bi bi-eye"></i>
                  </button>
                  <button class="icon-action" type="button" data-open-modal="student-edit-<?= (int) $row['id'] ?>" title="edit student" aria-label="edit student">
                    <i class="bi bi-pencil"></i>
                  </button>
                  <?php if ($row['account_status'] !== 'archived'): ?>
                    <form method="post" class="inline-icon-form">
                      <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
                      <input type="hidden" name="action" value="archive">
                      <input type="hidden" name="student_pk" value="<?= (int) $row['id'] ?>">
                      <button class="icon-action danger" type="submit" title="archive student" aria-label="archive student">
                        <i class="bi bi-archive"></i>
                      </button>
                    </form>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$students): ?>
            <tr><td colspan="6" class="empty-state">No students matched your filters.</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </section>
</div>
</div>
<?php foreach ($students as $row): ?>
<div class="modal-backdrop" data-modal="student-view-<?= (int) $row['id'] ?>" aria-hidden="true">
  <div class="modal-card modal-lg" role="dialog" aria-modal="true">
    <div class="modal-head">
      <div><span class="badge-soft"><i class="bi bi-person-vcard"></i> student</span><h3><?= h($row['full_name']) ?></h3><p class="muted mb-0"><?= h($row['student_id']) ?> · <?= h($row['email']) ?></p></div>
      <button type="button" class="icon-btn modal-close" data-close-modal aria-label="Close">✕</button>
    </div>
    <div class="quick-view-grid">
      <div class="quick-view-card"><span class="label">section</span><strong><?= h($row['section_name']) ?></strong></div>
      <div class="quick-view-card"><span class="label">status</span><?= status_badge($row['account_status']) ?></div>
      <div class="quick-view-card"><span class="label">access</span><span class="admin-access-badge <?= $row['can_submit'] ? 'allow' : 'restrict' ?>"><?= $row['can_submit'] ? 'Can submit' : 'Restricted' ?></span></div>
      <div class="quick-view-card"><span class="label">submissions</span><strong><?= (int) $row['total_submissions'] ?></strong></div>
    </div>
    <div class="d-flex justify-content-end gap-2 mt-3">
      <button class="btn btn-outline" type="button" data-close-modal>close</button>
      <button class="btn" type="button" data-close-modal data-open-modal="student-edit-<?= (int) $row['id'] ?>">edit</button>
    </div>
  </div>
</div>
<div class="modal-backdrop" data-modal="student-edit-<?= (int) $row['id'] ?>" aria-hidden="true">
  <div class="modal-card modal-lg" role="dialog" aria-modal="true">
    <div class="modal-head">
      <div><span class="badge-soft"><i class="bi bi-pencil"></i> student</span><h3>edit <?= h($row['full_name']) ?></h3></div>
      <button type="button" class="icon-btn modal-close" data-close-modal aria-label="Close">✕</button>
    </div>
    <form method="post" class="form-modal-grid">
      <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
      <input type="hidden" name="action" value="update_status">
      <input type="hidden" name="student_pk" value="<?= (int) $row['id'] ?>">
      <div>
        <label>account status</label>
        <select class="form-select" name="account_status">
          <?php foreach (['active','view_only','inactive','archived'] as $s): ?>
            <option value="<?= h($s) ?>" <?= $row['account_status']===$s?'selected':'' ?>><?= h(ucwords(str_replace('_',' ', $s))) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label>section</label>
        <select class="form-select" name="section_id">
          <?php foreach ($sections as $sec): ?>
            <option value="<?= (int) $sec['id'] ?>" <?= (int)$row['section_id']===(int)$sec['id']?'selected':'' ?>><?= h($sec['section_name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="full"><label class="checkbox-line"><input type="checkbox" name="can_submit" value="1" <?= $row['can_submit'] ? 'checked' : '' ?>> allow submissions</label></div>
      <div class="full d-flex justify-content-between gap-2 flex-wrap">
        <div class="ms-auto d-flex gap-2">
          <?php if ($row['account_status'] !== 'archived'): ?>
          <button class="btn btn-outline" name="action" value="archive" type="submit" data-confirm-title="archive student?" data-confirm-message="This student will move to archived status and become read only.">archive</button>
          <?php endif; ?>
          <button class="btn btn-outline" type="button" data-close-modal>close</button>
          <button class="btn" type="submit">save</button>
        </div>
      </div>
    </form>
  </div>
</div>
<?php endforeach; ?>
<?php require_once __DIR__ . '/../backend/partials/footer.php'; ?>
