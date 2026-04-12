<?php
require_once __DIR__ . '/../backend/helpers/query.php';
require_role('teacher');
$teacher = current_user();
$pdo = pdo();
$teacherId = (int) $teacher['id'];
$stmt = $pdo->prepare('SELECT COUNT(*) FROM subjects WHERE teacher_id = ? AND status = "active"');
$stmt->execute([$teacherId]);
$subjectCount = (int) $stmt->fetchColumn();
$stmt = $pdo->prepare('SELECT COUNT(DISTINCT ss.section_id) FROM section_subjects ss JOIN subjects subj ON subj.id = ss.subject_id WHERE subj.teacher_id = ?');
$stmt->execute([$teacherId]);
$sectionCount = (int) $stmt->fetchColumn();
$stmt = $pdo->prepare('SELECT COUNT(*) FROM submissions sub JOIN subjects subj ON subj.id = sub.subject_id WHERE subj.teacher_id = ? AND sub.status = "pending"');
$stmt->execute([$teacherId]);
$pending = (int) $stmt->fetchColumn();
$stmt = $pdo->prepare('SELECT COUNT(*) FROM submissions sub JOIN subjects subj ON subj.id = sub.subject_id WHERE subj.teacher_id = ? AND sub.status = "graded"');
$stmt->execute([$teacherId]);
$graded = (int) $stmt->fetchColumn();
$stmt = $pdo->prepare('SELECT subj.subject_name, subj.subject_code, COUNT(ss.id) AS total_sections FROM subjects subj LEFT JOIN section_subjects ss ON ss.subject_id = subj.id WHERE subj.teacher_id = ? GROUP BY subj.id ORDER BY subj.subject_name');
$stmt->execute([$teacherId]);
$subjects = $stmt->fetchAll();
$stmt = $pdo->prepare('SELECT sub.id, st.full_name, sec.section_name, subj.subject_name, sub.status, sub.submitted_at FROM submissions sub JOIN students st ON st.id = sub.student_id JOIN sections sec ON sec.id = sub.section_id JOIN subjects subj ON subj.id = sub.subject_id WHERE subj.teacher_id = ? ORDER BY sub.submitted_at DESC LIMIT 6');
$stmt->execute([$teacherId]);
$recent = $stmt->fetchAll();

$subjectPerformance = $pdo->prepare('SELECT subj.subject_name, COUNT(sub.id) AS total_submissions FROM subjects subj LEFT JOIN submissions sub ON sub.subject_id = subj.id WHERE subj.teacher_id = ? GROUP BY subj.id ORDER BY total_submissions DESC, subj.subject_name ASC LIMIT 6');
$subjectPerformance->execute([$teacherId]);
$subjectPerformanceRows = $subjectPerformance->fetchAll();
$maxSubj = max(array_map(fn($r) => (int)$r['total_submissions'], $subjectPerformanceRows ?: [['total_submissions'=>1]]));
$title = 'Teacher Dashboard';
$subtitle = 'Track your subjects, assigned sections, pending reviews, and grading progress';
require_once __DIR__ . '/../backend/partials/header.php';
?>
<div class="banner">
  <div>
    <div class="eyebrow">Teaching Workspace</div>
    <h2 style="margin:0;">Hello, <?= h($teacher['full_name']) ?></h2>
    <p>Everything you need to review submissions, manage your subjects, and keep your section workload organized.</p>
  </div>
  <div class="quick-grid">
    <a class="btn" href="<?= h(url('teacher/submissions.php')) ?>">Review Submissions</a>
    <a class="btn btn-secondary" href="<?= h(url('teacher/subjects.php')) ?>">My Subjects</a>
    <a class="btn btn-secondary" href="<?= h(url('teacher/students.php')) ?>">Invite students</a>
    <a class="btn btn-outline" href="<?= h(url('student/')) ?>" target="_blank">Open student landing</a>
  </div>
</div>
<div class="grid cols-4" style="margin-top:18px;">
  <div class="card metric metric-card"><span class="metric-label">Subjects</span><strong><?= $subjectCount ?></strong><span class="metric-trend">Active teaching load</span></div>
  <div class="card metric metric-card"><span class="metric-label">Sections</span><strong><?= $sectionCount ?></strong><span class="metric-trend">Assigned section reach</span></div>
  <div class="card metric metric-card"><span class="metric-label">Pending</span><strong><?= $pending ?></strong><span class="metric-trend">Needs review</span></div>
  <div class="card metric metric-card"><span class="metric-label">Graded</span><strong><?= $graded ?></strong><span class="metric-trend">Completed decisions</span></div>
</div>

<div class="card chart-card" style="margin-top:18px;"><div class="split-header"><div><h3 class="section-title">Subject workload chart</h3><div class="muted small">Submission volume by subject</div></div><a class="btn btn-outline" href="<?= h(url('teacher/profile.php')) ?>">Open profile</a></div><div class="bar-chart"><?php foreach ($subjectPerformanceRows as $row): $width = $maxSubj ? round(((int)$row['total_submissions'] / $maxSubj) * 100) : 0; ?><div class="bar-item"><div class="bar-top"><strong><?= h($row['subject_name']) ?></strong><span><?= (int)$row['total_submissions'] ?></span></div><div class="bar-line"><span style="width: <?= $width ?>%"></span></div></div><?php endforeach; ?></div></div>


<div class="card" style="margin-top:18px;">
  <div class="split-header"><div><h3 class="section-title">Portal links</h3><div class="muted small">Use these dedicated landing pages when sharing access</div></div></div>
  <div class="portal-link-stack">
    <div class="portal-link-box"><div><strong>Student landing</strong><br><code><?= h(url('student/')) ?></code></div><a class="btn btn-secondary" href="<?= h(url('student/')) ?>" target="_blank">Open</a></div>
    <div class="portal-link-box"><div><strong>Teacher landing</strong><br><code><?= h(url('teacher/')) ?></code></div><a class="btn btn-secondary" href="<?= h(url('teacher/')) ?>" target="_blank">Open</a></div>
    <div class="portal-link-box"><div><strong>Invite students securely</strong><br><code><?= h(url('teacher/students.php')) ?></code></div><a class="btn btn-secondary" href="<?= h(url('teacher/students.php')) ?>">Manage</a></div>
  </div>
</div>

<div class="grid cols-2" style="margin-top:18px;">
  <div class="card">
    <div class="split-header"><div><h3 class="section-title">My subjects</h3><div class="muted small">Section coverage by subject</div></div></div>
    <div class="timeline-list">
      <?php foreach ($subjects as $row): ?>
        <div class="timeline-item">
          <div class="notification-title-row"><strong><?= h($row['subject_name']) ?></strong><span class="pill"><?= h($row['subject_code']) ?></span></div>
          <p><?= (int) $row['total_sections'] ?> assigned section<?= (int) $row['total_sections'] === 1 ? '' : 's' ?>.</p>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
  <div class="card">
    <div class="split-header"><div><h3 class="section-title">Recent submissions</h3><div class="muted small">Latest work waiting in your pipeline</div></div></div>
    <div class="table-wrap"><table><thead><tr><th>Student</th><th>Subject</th><th>Status</th><th>Submitted</th></tr></thead><tbody><?php foreach ($recent as $row): ?><tr><td><strong><?= h($row['full_name']) ?></strong><div class="muted small"><?= h($row['section_name']) ?></div></td><td><?= h($row['subject_name']) ?></td><td><?= status_badge($row['status']) ?></td><td><?= h($row['submitted_at']) ?></td></tr><?php endforeach; ?></tbody></table></div>
  </div>
</div>
<?php require_once __DIR__ . '/../backend/partials/footer.php'; ?>
