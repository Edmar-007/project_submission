<?php
if (defined('FILE_ADMIN_REPORTS_PHP_LOADED')) { return; }
define('FILE_ADMIN_REPORTS_PHP_LOADED', true);

require_once __DIR__ . '/../backend/config/app.php';
require_role('admin');
$pdo = pdo();
$filterSection = (int) ($_GET['section_id'] ?? 0);
$filterSubject = (int) ($_GET['subject_id'] ?? 0);
$filterStatus = trim($_GET['status'] ?? '');
$sections = all_sections();
$subjects = all_subjects();
$where = [];
$params = [];
if ($filterSection) { $where[] = 'sub.section_id = ?'; $params[] = $filterSection; }
if ($filterSubject) { $where[] = 'sub.subject_id = ?'; $params[] = $filterSubject; }
if ($filterStatus !== '') { $where[] = 'sub.status = ?'; $params[] = $filterStatus; }
$sqlWhere = $where ? ' WHERE ' . implode(' AND ', $where) : '';
$totals = [
    'students' => (int) $pdo->query('SELECT COUNT(*) FROM students')->fetchColumn(),
    'teachers' => (int) $pdo->query('SELECT COUNT(*) FROM teachers')->fetchColumn(),
    'subjects' => (int) $pdo->query('SELECT COUNT(*) FROM subjects')->fetchColumn(),
    'submissions' => (int) $pdo->query('SELECT COUNT(*) FROM submissions')->fetchColumn(),
];
$gradeRows = $pdo->query('SELECT subj.subject_name, COUNT(sub.id) AS total_submissions, SUM(CASE WHEN sub.status = "graded" THEN 1 ELSE 0 END) AS graded_count FROM subjects subj LEFT JOIN submissions sub ON sub.subject_id = subj.id GROUP BY subj.id ORDER BY subj.subject_name')->fetchAll();
$summaryStmt = $pdo->prepare('SELECT COUNT(*) AS filtered_total, SUM(CASE WHEN sub.status = "graded" THEN 1 ELSE 0 END) AS graded_total, SUM(CASE WHEN sub.status = "pending" THEN 1 ELSE 0 END) AS pending_total FROM submissions sub' . $sqlWhere);
$summaryStmt->execute($params);
$filteredSummary = $summaryStmt->fetch();
$statusTotal = max(1, (int)($filteredSummary['filtered_total'] ?? 0));
$gradedTotal = (int)($filteredSummary['graded_total'] ?? 0);
$pendingTotal = (int)($filteredSummary['pending_total'] ?? 0);
$reviewedOnly = max(0, $statusTotal - $gradedTotal - $pendingTotal);
$title = 'Reports';
$subtitle = 'Export actions are now merged into the report table area instead of living in a separate side panel.';
require_once __DIR__ . '/../backend/partials/header.php';
?>
<div class="kpi-grid">
  <div class="kpi-card"><span class="label">Students</span><strong><?= number_format($totals['students']) ?></strong><span class="muted small">Registered student records</span></div>
  <div class="kpi-card"><span class="label">Teachers</span><strong><?= number_format($totals['teachers']) ?></strong><span class="muted small">Faculty accounts</span></div>
  <div class="kpi-card"><span class="label">Subjects</span><strong><?= number_format($totals['subjects']) ?></strong><span class="muted small">Tracked subjects</span></div>
  <div class="kpi-card"><span class="label">Submissions</span><strong><?= number_format($totals['submissions']) ?></strong><span class="muted small">Projects on file</span></div>
</div>
<section class="table-card table-bootstrap-shell">
  <div class="module-header">
    <div>
      <div class="eyebrow">Reports</div>
      <h3 class="mb-1">Operational Summary Table</h3>
      <p class="muted mb-0">Use the filters below, then export directly from this same section.</p>
    </div>
    <div class="module-actions">
      <a class="btn" href="<?= h(url('admin/export_report.php?' . http_build_query(['type' => 'submissions', 'format' => 'xlsx', 'section_id' => $filterSection, 'subject_id' => $filterSubject, 'status' => $filterStatus]))) ?>"><i class="bi bi-file-earmark-spreadsheet"></i> Export Excel</a>
      <a class="btn btn-secondary" href="<?= h(url('admin/export_report.php?' . http_build_query(['type' => 'submissions', 'format' => 'csv', 'section_id' => $filterSection, 'subject_id' => $filterSubject, 'status' => $filterStatus]))) ?>"><i class="bi bi-download"></i> Export CSV</a>
      <a class="btn btn-outline" href="<?= h(url('admin/export_report.php?type=students&format=xlsx')) ?>"><i class="bi bi-person-lines-fill"></i> Students</a>
      <a class="btn btn-outline" href="<?= h(url('admin/export_report.php?type=teams&format=xlsx')) ?>"><i class="bi bi-people"></i> Teams</a>
    </div>
  </div>
  <div class="table-toolbar">
    <form method="get" class="filters">
      <select class="form-select" name="section_id"><option value="0">All sections</option><?php foreach ($sections as $section): ?><option value="<?= (int) $section['id'] ?>" <?= $filterSection === (int) $section['id'] ? 'selected' : '' ?>><?= h($section['section_name']) ?></option><?php endforeach; ?></select>
      <select class="form-select" name="subject_id"><option value="0">All subjects</option><?php foreach ($subjects as $subject): ?><option value="<?= (int) $subject['id'] ?>" <?= $filterSubject === (int) $subject['id'] ? 'selected' : '' ?>><?= h($subject['subject_name']) ?></option><?php endforeach; ?></select>
      <select class="form-select" name="status"><option value="">All statuses</option><option value="pending" <?= selected($filterStatus, 'pending') ?>>Pending</option><option value="reviewed" <?= selected($filterStatus, 'reviewed') ?>>Reviewed</option><option value="graded" <?= selected($filterStatus, 'graded') ?>>Graded</option></select>
      <button class="btn" type="submit"><i class="bi bi-funnel"></i> Apply Filters</button>
    </form>
    <span class="badge-soft"><i class="bi bi-table"></i> Matching submissions <?= number_format((int) ($filteredSummary['filtered_total'] ?? 0)) ?></span>
  </div>
  <div class="row g-3 mb-3">
    <div class="col-12 col-md-4"><div class="callout h-100"><strong>Pending</strong><div class="display-6 mb-1"><?= number_format($pendingTotal) ?></div><div class="muted small">Submissions still waiting for review.</div></div></div>
    <div class="col-12 col-md-4"><div class="callout h-100"><strong>Reviewed</strong><div class="display-6 mb-1"><?= number_format($reviewedOnly) ?></div><div class="muted small">Opened but not fully graded yet.</div></div></div>
    <div class="col-12 col-md-4"><div class="callout h-100"><strong>Graded</strong><div class="display-6 mb-1"><?= number_format($gradedTotal) ?></div><div class="muted small">Final grading already released.</div></div></div>
  </div>
  <div class="table-responsive">
    <table class="table table-hover align-middle">
      <thead><tr><th>Subject</th><th>Total submissions</th><th>Graded</th><th>Progress</th></tr></thead>
      <tbody>
      <?php foreach ($gradeRows as $row): $subjectTotal = (int) $row['total_submissions']; $gradedCount = (int) $row['graded_count']; $progress = $subjectTotal > 0 ? (int) round(($gradedCount / $subjectTotal) * 100) : 0; ?>
        <tr>
          <td><strong><?= h($row['subject_name']) ?></strong><div class="muted small">Grading performance overview</div></td>
          <td><?= number_format($subjectTotal) ?></td>
          <td><?= number_format($gradedCount) ?></td>
          <td style="min-width:190px;"><div class="progress" role="progressbar" aria-valuenow="<?= $progress ?>" aria-valuemin="0" aria-valuemax="100"><div class="progress-bar" style="width: <?= $progress ?>%"></div></div><div class="muted small mt-1"><?= $progress ?>%</div></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$gradeRows): ?><tr><td colspan="4" class="table-empty">No grading data available yet.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</section>
<?php require_once __DIR__ . '/../backend/partials/footer.php'; ?>
