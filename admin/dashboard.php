<?php
if (defined('FILE_ADMIN_DASHBOARD_PHP_LOADED')) { return; }
define('FILE_ADMIN_DASHBOARD_PHP_LOADED', true);

require_once __DIR__ . '/../backend/config/app.php';
require_role('admin');
$user = current_user();
$pdo = pdo();
$metrics = [
    'students' => (int) $pdo->query('SELECT COUNT(*) FROM students')->fetchColumn(),
    'teachers' => (int) $pdo->query('SELECT COUNT(*) FROM teachers WHERE status = "active"')->fetchColumn(),
    'subjects' => (int) $pdo->query('SELECT COUNT(*) FROM subjects WHERE status = "active"')->fetchColumn(),
    'submissions' => (int) $pdo->query('SELECT COUNT(*) FROM submissions')->fetchColumn(),
    'pending' => (int) $pdo->query('SELECT COUNT(*) FROM submissions WHERE status = "pending"')->fetchColumn(),
    'requests' => (int) $pdo->query('SELECT COUNT(*) FROM reactivation_requests WHERE status = "pending"')->fetchColumn(),
];
$latestSubmissions = $pdo->query('SELECT sub.id, st.full_name, st.student_id, sec.section_name, subj.subject_name, sub.status, sub.grade, sub.submitted_at FROM submissions sub JOIN students st ON st.id = sub.student_id JOIN sections sec ON sec.id = sub.section_id JOIN subjects subj ON subj.id = sub.subject_id ORDER BY sub.submitted_at DESC LIMIT 8')->fetchAll();
$latestRequests = $pdo->query('SELECT rr.id, st.full_name, st.student_id, rr.status, rr.created_at FROM reactivation_requests rr JOIN students st ON st.id = rr.student_id ORDER BY rr.created_at DESC LIMIT 5')->fetchAll();
$sectionHealth = $pdo->query('SELECT s.section_name, s.status, COUNT(st.id) AS total_students FROM sections s LEFT JOIN students st ON st.section_id = s.id GROUP BY s.id ORDER BY s.section_name LIMIT 6')->fetchAll();

$sectionDistribution = fetch_section_distribution();
$subjectDistribution = fetch_subject_submission_distribution();
$maxSection = max(array_map(fn($r) => (int)$r['total_students'], $sectionDistribution ?: [['total_students'=>1]]));
$maxSubject = max(array_map(fn($r) => (int)$r['total_submissions'], $subjectDistribution ?: [['total_submissions'=>1]]));
$title = 'Admin Dashboard';
$subtitle = 'Modern control center for sections, subjects, student access, and review operations';
require_once __DIR__ . '/../backend/partials/header.php';
?>
<div class="banner">
  <div>
    <div class="eyebrow">Operations Overview</div>
    <h2 style="margin:0;">Welcome back, <?= h($user['full_name']) ?></h2>
    <p>Manage the academic year, restrict whole sections in one click, and keep repeaters moving with individual reactivation.</p>
  </div>
  <div class="quick-grid">
    <a class="btn" href="<?= h(url('admin/sections.php')) ?>">Manage Sections</a>
    <a class="btn btn-secondary" href="<?= h(url('admin/requests.php')) ?>">Review Requests</a>
    <a class="btn btn-secondary" href="<?= h(url('admin/reports.php')) ?>">Open Reports</a>
  </div>
</div>

<div class="grid cols-4" style="margin-top:18px;">
  <div class="card metric metric-card"><span class="metric-label">Students</span><strong><?= $metrics['students'] ?></strong><span class="metric-trend">Full student registry</span></div>
  <div class="card metric metric-card"><span class="metric-label">Teachers</span><strong><?= $metrics['teachers'] ?></strong><span class="metric-trend">Active faculty accounts</span></div>
  <div class="card metric metric-card"><span class="metric-label">Subjects</span><strong><?= $metrics['subjects'] ?></strong><span class="metric-trend">Live offerings</span></div>
  <div class="card metric metric-card"><span class="metric-label">Submissions</span><strong><?= $metrics['submissions'] ?></strong><span class="metric-trend">Total stored records</span></div>
</div>

<div class="chart-grid" style="margin-top:18px;">
  <div class="card chart-card">
    <div class="split-header"><div><h3 class="section-title">Students by section</h3><div class="muted small">Quick visual enrollment distribution</div></div><a class="btn btn-outline" href="<?= h(url('admin/bulk_move.php')) ?>">Bulk move</a></div>
    <div class="bar-chart"><?php foreach ($sectionDistribution as $row): $width = $maxSection ? round(((int)$row['total_students'] / $maxSection) * 100) : 0; ?><div class="bar-item"><div class="bar-top"><strong><?= h($row['section_name']) ?></strong><span><?= (int)$row['total_students'] ?></span></div><div class="bar-line"><span style="width: <?= $width ?>%"></span></div></div><?php endforeach; ?></div>
  </div>
  <div class="card chart-card">
    <div class="split-header"><div><h3 class="section-title">Submissions by subject</h3><div class="muted small">Where activity is highest right now</div></div><a class="btn btn-outline" href="<?= h(url('admin/export_report.php?type=submissions&format=xlsx')) ?>">Export Excel</a></div>
    <div class="bar-chart"><?php foreach ($subjectDistribution as $row): $width = $maxSubject ? round(((int)$row['total_submissions'] / $maxSubject) * 100) : 0; ?><div class="bar-item"><div class="bar-top"><strong><?= h($row['subject_name']) ?></strong><span><?= (int)$row['total_submissions'] ?></span></div><div class="bar-line"><span style="width: <?= $width ?>%"></span></div></div><?php endforeach; ?></div>
  </div>
</div>


<div class="card" style="margin-top:18px;">
  <div class="split-header"><div><h3 class="section-title">Portal links</h3><div class="muted small">Separate entry pages you can open or share</div></div></div>
  <div class="portal-link-stack">
    <div class="portal-link-box"><div><strong>Student landing</strong><br><code><?= h(url('student/')) ?></code></div><a class="btn btn-secondary" href="<?= h(url('student/')) ?>" target="_blank">Open</a></div>
    <div class="portal-link-box"><div><strong>Teacher landing</strong><br><code><?= h(url('teacher/')) ?></code></div><a class="btn btn-secondary" href="<?= h(url('teacher/')) ?>" target="_blank">Open</a></div>
    <div class="portal-link-box"><div><strong>Super admin landing</strong><br><code><?= h(url('admin/')) ?></code></div><a class="btn btn-secondary" href="<?= h(url('admin/')) ?>" target="_blank">Open</a></div>
  </div>
</div>

<div class="grid cols-2" style="margin-top:18px;">
  <div class="card">
    <div class="split-header"><div><h3 class="section-title">Review pipeline</h3><div class="muted small">What needs action today</div></div></div>
    <div class="insight-list">
      <div class="insight-row"><span>Pending submissions</span><strong><?= $metrics['pending'] ?></strong></div>
      <div class="insight-row"><span>Pending reactivation requests</span><strong><?= $metrics['requests'] ?></strong></div>
      <div class="insight-row"><span>Unread admin notifications</span><strong><?= count_unread_notifications('admin', (int) $user['id']) ?></strong></div>
    </div>
  </div>
  <div class="card">
    <div class="split-header"><div><h3 class="section-title">Section health</h3><div class="muted small">Current section availability</div></div></div>
    <div class="timeline-list">
      <?php foreach ($sectionHealth as $row): ?>
        <div class="timeline-item">
          <div class="notification-title-row">
            <strong><?= h($row['section_name']) ?></strong>
            <?= status_badge($row['status']) ?>
          </div>
          <p><?= (int) $row['total_students'] ?> enrolled student<?= (int) $row['total_students'] === 1 ? '' : 's' ?>.</p>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>
<div class="grid cols-2" style="margin-top:18px;">
  <div class="card">
    <div class="split-header"><div><h3 class="section-title">Recent submissions</h3><div class="muted small">Latest activity across all subjects</div></div></div>
    <div class="table-wrap"><table><thead><tr><th>Student</th><th>Subject</th><th>Status</th><th>Submitted</th></tr></thead><tbody><?php foreach ($latestSubmissions as $row): ?><tr><td><strong><?= h($row['full_name']) ?></strong><div class="muted small"><?= h($row['student_id']) ?> · <?= h($row['section_name']) ?></div></td><td><?= h($row['subject_name']) ?></td><td><?= status_badge($row['status']) ?></td><td><?= h($row['submitted_at']) ?></td></tr><?php endforeach; ?></tbody></table></div>
  </div>
  <div class="card">
    <div class="split-header"><div><h3 class="section-title">Recent reactivation requests</h3><div class="muted small">Repeaters and restored access requests</div></div></div>
    <div class="table-wrap"><table><thead><tr><th>Student</th><th>Status</th><th>Date</th></tr></thead><tbody><?php foreach ($latestRequests as $row): ?><tr><td><strong><?= h($row['full_name']) ?></strong><div class="muted small"><?= h($row['student_id']) ?></div></td><td><?= status_badge($row['status']) ?></td><td><?= h($row['created_at']) ?></td></tr><?php endforeach; ?></tbody></table></div>
  </div>
</div>
<?php require_once __DIR__ . '/../backend/partials/footer.php'; ?>
