<?php
require_once __DIR__ . '/../backend/helpers/query.php';
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

if (isset($_GET['export'])) {
    $export = $_GET['export'];
    if ($export === 'students') {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="students_report.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['Student ID','Full Name','Email','Section','Status','Can Submit']);
        foreach ($pdo->query('SELECT st.student_id, st.full_name, st.email, sec.section_name, st.account_status, st.can_submit FROM students st JOIN sections sec ON sec.id = st.section_id ORDER BY st.full_name') as $row) {
            fputcsv($out, $row);
        }
        fclose($out);
        exit;
    }
    if ($export === 'submissions') {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="submissions_report.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['Student','Student ID','Section','Subject','System','Status','Grade','Submitted At']);
        $stmt = $pdo->prepare('SELECT st.full_name, st.student_id, sec.section_name, subj.subject_name, sub.assigned_system, sub.status, sub.grade, sub.submitted_at FROM submissions sub JOIN students st ON st.id = sub.student_id JOIN sections sec ON sec.id = sub.section_id JOIN subjects subj ON subj.id = sub.subject_id' . $sqlWhere . ' ORDER BY sub.submitted_at DESC');
        $stmt->execute($params);
        foreach ($stmt->fetchAll() as $row) { fputcsv($out, $row); }
        fclose($out);
        exit;
    }
}
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
$title = 'Reports';
$subtitle = 'Filter operational data, export CSVs, and monitor grading progress by subject and section';
require_once __DIR__ . '/../backend/partials/header.php';
?>
<div class="grid cols-4">
  <div class="card metric metric-card"><span class="metric-label">Students</span><strong><?= $totals['students'] ?></strong><span class="metric-trend">Registered students</span></div>
  <div class="card metric metric-card"><span class="metric-label">Teachers</span><strong><?= $totals['teachers'] ?></strong><span class="metric-trend">Faculty accounts</span></div>
  <div class="card metric metric-card"><span class="metric-label">Subjects</span><strong><?= $totals['subjects'] ?></strong><span class="metric-trend">Configured offerings</span></div>
  <div class="card metric metric-card"><span class="metric-label">Submissions</span><strong><?= $totals['submissions'] ?></strong><span class="metric-trend">All stored records</span></div>
</div>
<div class="grid cols-2" style="margin-top:18px;">
  <div class="card">
    <h3 class="section-title">Filter submission data</h3>
    <form method="get" class="filter-row">
      <select name="section_id"><option value="0">All sections</option><?php foreach ($sections as $section): ?><option value="<?= (int) $section['id'] ?>" <?= $filterSection === (int) $section['id'] ? 'selected' : '' ?>><?= h($section['section_name']) ?></option><?php endforeach; ?></select>
      <select name="subject_id"><option value="0">All subjects</option><?php foreach ($subjects as $subject): ?><option value="<?= (int) $subject['id'] ?>" <?= $filterSubject === (int) $subject['id'] ? 'selected' : '' ?>><?= h($subject['subject_name']) ?></option><?php endforeach; ?></select>
      <select name="status"><option value="">All statuses</option><option value="pending" <?= selected($filterStatus, 'pending') ?>>Pending</option><option value="reviewed" <?= selected($filterStatus, 'reviewed') ?>>Reviewed</option><option value="graded" <?= selected($filterStatus, 'graded') ?>>Graded</option></select>
      <button class="btn btn-secondary" type="submit">Apply filters</button>
    </form>
    <div class="insight-list" style="margin-top:14px;">
      <div class="insight-row"><span>Filtered submissions</span><strong><?= (int) ($filteredSummary['filtered_total'] ?? 0) ?></strong></div>
      <div class="insight-row"><span>Graded in filtered view</span><strong><?= (int) ($filteredSummary['graded_total'] ?? 0) ?></strong></div>
      <div class="insight-row"><span>Pending in filtered view</span><strong><?= (int) ($filteredSummary['pending_total'] ?? 0) ?></strong></div>
    </div>
  </div>
  <div class="card">
    <h3 class="section-title">Export data</h3>
    <p class="muted">Export the full registry or your filtered submission dataset for external reporting.</p>
    <div class="callout" style="margin-bottom:14px;">
      <strong>Recommended:</strong> use the export modal so admins can quickly review what will be downloaded before starting the CSV export.
    </div>
    <div class="form-actions">
      <button class="btn" type="button" data-open-modal="students-export-modal">Export Students CSV</button>
      <button class="btn btn-secondary" type="button" data-open-modal="submissions-export-modal">Export Filtered Submissions</button>
    </div>
  </div>
</div>
<?php
$maxTotal = 1;
foreach ($gradeRows as $row) {
    $maxTotal = max($maxTotal, (int)$row['total_submissions']);
}
$statusTotal = max(1, (int)($filteredSummary['filtered_total'] ?? 0));
$gradedPct = $statusTotal ? round(((int)($filteredSummary['graded_total'] ?? 0) / $statusTotal) * 100) : 0;
$pendingPct = $statusTotal ? round(((int)($filteredSummary['pending_total'] ?? 0) / $statusTotal) * 100) : 0;
$reviewedOnly = max(0, $statusTotal - (int)($filteredSummary['graded_total'] ?? 0) - (int)($filteredSummary['pending_total'] ?? 0));
$reviewedPct = $statusTotal ? max(0, 100 - $gradedPct - $pendingPct) : 0;
$sectionRows = $pdo->query('SELECT sec.section_name, COUNT(sub.id) AS total_submissions FROM sections sec LEFT JOIN submissions sub ON sub.section_id = sec.id GROUP BY sec.id ORDER BY total_submissions DESC, sec.section_name ASC LIMIT 6')->fetchAll();
$maxSection = 1;
foreach ($sectionRows as $row) { $maxSection = max($maxSection, (int)$row['total_submissions']); }
?>
<div class="grid cols-2" style="margin-top:18px;">
  <div class="card chart-card">
    <div class="split-header"><div><h3 class="section-title">Subject submission graph</h3><div class="muted small">Submission volume and grading progress by subject</div></div><span class="pill">Graph</span></div>
    <div class="bar-chart">
      <?php foreach ($gradeRows as $row): $width = $maxTotal ? round(((int)$row['total_submissions'] / $maxTotal) * 100) : 0; $gradedWidth = (int)$row['total_submissions'] > 0 ? round(((int)$row['graded_count'] / (int)$row['total_submissions']) * 100) : 0; ?>
        <div class="bar-item">
          <div class="bar-top"><strong><?= h($row['subject_name']) ?></strong><span><?= (int)$row['graded_count'] ?>/<?= (int)$row['total_submissions'] ?> graded</span></div>
          <div class="bar-line"><span style="width: <?= $width ?>%"></span></div>
          <div class="analytics-track" style="height:8px; margin-top:6px;"><div class="analytics-fill" style="width: <?= $gradedWidth ?>%; background: linear-gradient(90deg, #15803d, #22c55e);"></div></div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
  <div class="card chart-card">
    <div class="split-header"><div><h3 class="section-title">Filtered status breakdown</h3><div class="muted small">Live graph based on your current report filters</div></div><span class="pill">Breakdown</span></div>
    <div class="report-donut-wrap">
      <div class="report-donut" style="--graded: <?= $gradedPct ?>; --pending: <?= $pendingPct ?>; --reviewed: <?= $reviewedPct ?>;">
        <div>
          <strong><?= $statusTotal ?></strong>
          <span>Total</span>
        </div>
      </div>
      <div class="legend-list">
        <div class="legend-item"><span class="legend-swatch graded"></span><span>Graded</span><strong><?= (int)($filteredSummary['graded_total'] ?? 0) ?></strong></div>
        <div class="legend-item"><span class="legend-swatch pending"></span><span>Pending</span><strong><?= (int)($filteredSummary['pending_total'] ?? 0) ?></strong></div>
        <div class="legend-item"><span class="legend-swatch reviewed"></span><span>Reviewed</span><strong><?= $reviewedOnly ?></strong></div>
      </div>
    </div>
  </div>
</div>
<div class="card chart-card" style="margin-top:18px;">
  <div class="split-header"><div><h3 class="section-title">Section activity graph</h3><div class="muted small">Compare where submissions are concentrated</div></div></div>
  <div class="bar-chart">
    <?php foreach ($sectionRows as $row): $width = $maxSection ? round(((int)$row['total_submissions'] / $maxSection) * 100) : 0; ?>
      <div class="bar-item">
        <div class="bar-top"><strong><?= h($row['section_name']) ?></strong><span><?= (int)$row['total_submissions'] ?> submissions</span></div>
        <div class="bar-line"><span style="width: <?= $width ?>%; background: linear-gradient(90deg, #7c3aed, #a78bfa);"></span></div>
      </div>
    <?php endforeach; ?>
  </div>
</div>
<div class="card" style="margin-top:18px;">
  <h3 class="section-title">Subject grading progress table</h3>
  <div class="table-wrap"><table><thead><tr><th>Subject</th><th>Total submissions</th><th>Graded</th></tr></thead><tbody><?php foreach ($gradeRows as $row): ?><tr><td><?= h($row['subject_name']) ?></td><td><?= (int) $row['total_submissions'] ?></td><td><?= (int) $row['graded_count'] ?></td></tr><?php endforeach; ?></tbody></table></div>
</div>
<div class="modal-backdrop" data-modal="students-export-modal" aria-hidden="true">
  <div class="modal-card" role="dialog" aria-modal="true" aria-labelledby="students-export-title">
    <div class="modal-head">
      <div>
        <span class="pill">CSV Export</span>
        <h3 id="students-export-title">Export student registry</h3>
      </div>
      <button type="button" class="icon-btn modal-close" data-close-modal aria-label="Close export dialog">✕</button>
    </div>
    <p class="muted">This downloads the full student registry, including account status and current submission permission.</p>
    <div class="modal-summary-grid">
      <div class="portal-split-card">
        <span class="muted small">Included rows</span>
        <strong><?= $totals['students'] ?></strong>
      </div>
      <div class="portal-split-card">
        <span class="muted small">File format</span>
        <strong>CSV</strong>
      </div>
    </div>
    <div class="kv" style="margin-top:16px;">
      <div><span>Columns</span><strong>Student ID, Name, Email, Section, Status, Can Submit</strong></div>
      <div><span>Scope</span><strong>All registered students</strong></div>
    </div>
    <div class="form-actions">
      <a class="btn" href="<?= h(url('admin/reports.php?export=students')) ?>">Download students CSV</a>
      <button class="btn btn-ghost" type="button" data-close-modal>Cancel</button>
    </div>
  </div>
</div>

<div class="modal-backdrop" data-modal="submissions-export-modal" aria-hidden="true">
  <div class="modal-card" role="dialog" aria-modal="true" aria-labelledby="submissions-export-title">
    <div class="modal-head">
      <div>
        <span class="pill">Filtered Export</span>
        <h3 id="submissions-export-title">Export filtered submissions</h3>
      </div>
      <button type="button" class="icon-btn modal-close" data-close-modal aria-label="Close export dialog">✕</button>
    </div>
    <p class="muted">This export follows the filters currently applied on the reports page.</p>
    <div class="modal-summary-grid">
      <div class="portal-split-card">
        <span class="muted small">Matching submissions</span>
        <strong><?= (int) ($filteredSummary['filtered_total'] ?? 0) ?></strong>
      </div>
      <div class="portal-split-card">
        <span class="muted small">Status filter</span>
        <strong><?= h($filterStatus !== '' ? ucfirst($filterStatus) : 'All statuses') ?></strong>
      </div>
      <div class="portal-split-card">
        <span class="muted small">Section filter</span>
        <strong><?= h($filterSection ? array_values(array_filter($sections, fn($section) => (int) $section['id'] === $filterSection))[0]['section_name'] ?? 'Selected section' : 'All sections') ?></strong>
      </div>
      <div class="portal-split-card">
        <span class="muted small">Subject filter</span>
        <strong><?= h($filterSubject ? array_values(array_filter($subjects, fn($subject) => (int) $subject['id'] === $filterSubject))[0]['subject_name'] ?? 'Selected subject' : 'All subjects') ?></strong>
      </div>
    </div>
    <div class="kv" style="margin-top:16px;">
      <div><span>Columns</span><strong>Student, Student ID, Section, Subject, System, Status, Grade, Submitted At</strong></div>
      <div><span>Tip</span><strong>Adjust filters first if you want a narrower report.</strong></div>
    </div>
    <div class="form-actions">
      <a class="btn btn-secondary" href="<?= h(url('admin/reports.php?' . http_build_query(['export' => 'submissions', 'section_id' => $filterSection, 'subject_id' => $filterSubject, 'status' => $filterStatus]))) ?>">Download filtered submissions</a>
      <button class="btn btn-ghost" type="button" data-close-modal>Cancel</button>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../backend/partials/footer.php'; ?>
