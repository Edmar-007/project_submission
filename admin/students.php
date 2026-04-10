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
        <table>
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
              <td data-label="Actions">
                <div class="table-actions compact-table-actions">
                  <a class="btn btn-secondary" href="<?= h(url('admin/student_view.php?id=' . (int) $row['id'])) ?>">View</a>
                  <details class="inline-action-details">
                    <summary class="btn btn-outline" data-icon="more">More</summary>
                    <div class="action-popover compact-action-popover">
                      <form method="post" class="form-grid compact-inline-form">
                        <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
                        <input type="hidden" name="action" value="update_status">
                        <input type="hidden" name="student_pk" value="<?= (int) $row['id'] ?>">
                        <div>
                          <label class="small muted">Account status</label>
                          <select name="account_status">
                            <?php foreach (['active','view_only','inactive','archived'] as $s): ?>
                              <option value="<?= h($s) ?>" <?= $row['account_status']===$s?'selected':'' ?>><?= h(ucwords(str_replace('_',' ', $s))) ?></option>
                            <?php endforeach; ?>
                          </select>
                        </div>
                        <div>
                          <label class="small muted">Section</label>
                          <select name="section_id">
                            <?php foreach ($sections as $sec): ?>
                              <option value="<?= (int) $sec['id'] ?>" <?= (int)$row['section_id']===(int)$sec['id']?'selected':'' ?>><?= h($sec['section_name']) ?></option>
                            <?php endforeach; ?>
                          </select>
                        </div>
                        <label class="checkbox-line"><input type="checkbox" name="can_submit" value="1" <?= $row['can_submit'] ? 'checked' : '' ?>> Allow submissions</label>
                        <button class="btn btn-secondary" type="submit">Save updates</button>
                      </form>
                      <?php if ($row['account_status'] !== 'archived'): ?>
                        <form method="post" class="compact-danger-form">
                          <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
                          <input type="hidden" name="action" value="archive">
                          <input type="hidden" name="student_pk" value="<?= (int) $row['id'] ?>">
                          <button class="btn btn-outline" type="submit">Archive student</button>
                        </form>
                      <?php endif; ?>
                    </div>
                  </details>
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
<?php require_once __DIR__ . '/../backend/partials/footer.php'; ?>
