<?php
if (defined('FILE_TEACHER_SUBMISSIONS_PHP_LOADED')) { return; }
define('FILE_TEACHER_SUBMISSIONS_PHP_LOADED', true);

require_once __DIR__ . '/../backend/config/app.php';
require_role('teacher');

if (!function_exists('teacher_submission_initials')) {
    function teacher_submission_initials(string $name): string {
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        $initials = '';
        foreach ($parts as $part) {
            if ($part === '') { continue; }
            $initials .= strtoupper(substr($part, 0, 1));
            if (strlen($initials) >= 2) { break; }
        }
        return $initials !== '' ? $initials : 'S';
    }
}

$teacher = current_user();
$pdo = pdo();
$filterSubject = (int) ($_GET['subject_id'] ?? 0);
$filterStatus = trim($_GET['status'] ?? '');
$params = [(int) $teacher['id']];
$sql = 'SELECT sub.*, st.full_name, st.student_id AS student_code, sec.section_name, subj.subject_name, subj.subject_code, tm.team_name FROM submissions sub JOIN students st ON st.id = sub.student_id JOIN sections sec ON sec.id = sub.section_id JOIN subjects subj ON subj.id = sub.subject_id LEFT JOIN teams tm ON tm.id = sub.team_id WHERE subj.teacher_id = ?';
if ($filterSubject > 0) {
    $sql .= ' AND subj.id = ?';
    $params[] = $filterSubject;
}
if ($filterStatus !== '') {
    $sql .= ' AND sub.status = ?';
    $params[] = $filterStatus;
}
$sql .= ' ORDER BY FIELD(sub.status, "pending", "reviewed", "graded", "archived"), sub.submitted_at DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();
$subjectOptions = all_subjects_for_teacher((int) $teacher['id']);

$summary = ['pending' => 0, 'reviewed' => 0, 'graded' => 0];
$gradedCount = 0;
$gradeTotal = 0.0;
foreach ($rows as $row) {
    if (isset($summary[$row['status']])) {
        $summary[$row['status']]++;
    }
    if ($row['grade'] !== null && $row['grade'] !== '' && is_numeric($row['grade'])) {
        $gradedCount++;
        $gradeTotal += (float) $row['grade'];
    }
}
$averageGrade = $gradedCount > 0 ? round($gradeTotal / $gradedCount, 1) : null;
$reviewBadgeClass = static function (string $status): string {
    $key = strtolower(trim($status));
    if ($key === 'graded') return 'ui-badge--success';
    if ($key === 'pending') return 'ui-badge--warning';
    if ($key === 'reviewed') return 'ui-badge--open';
    return 'ui-badge--muted';
};

$title = 'Teacher Submissions';
$subtitle = 'Faster review queue with live search, compact table mode, and stronger action hierarchy';
require_once __DIR__ . '/../backend/partials/header.php';
?>
<div class="kpi-grid ui-stat-grid">
  <div class="kpi-card ui-stat-card"><span class="label ui-stat-card__label">Pending</span><strong class="ui-stat-card__value"><?= (int) $summary['pending'] ?></strong><span class="muted small ui-stat-card__hint">Waiting for review</span></div>
  <div class="kpi-card ui-stat-card"><span class="label ui-stat-card__label">Reviewed</span><strong class="ui-stat-card__value"><?= (int) $summary['reviewed'] ?></strong><span class="muted small ui-stat-card__hint">Checked but not finalized</span></div>
  <div class="kpi-card ui-stat-card"><span class="label ui-stat-card__label">Graded</span><strong class="ui-stat-card__value"><?= (int) $summary['graded'] ?></strong><span class="muted small ui-stat-card__hint">Ready for students</span></div>
  <div class="kpi-card ui-stat-card"><span class="label ui-stat-card__label">Average grade</span><strong class="ui-stat-card__value"><?= $averageGrade !== null ? h(number_format($averageGrade, 1)) : '—' ?></strong><span class="muted small ui-stat-card__hint">Across visible graded submissions</span></div>
</div>

<section class="workspace-shell submissions-workspace ui-section" data-submission-workspace>
  <div class="workspace-head submissions-workspace-head ui-section__head">
    <div>
      <div class="eyebrow ui-section__eyebrow">Teacher review queue</div>
      <h2 class="ui-section__title">Review faster with cards or a compact table</h2>
      <p class="muted ui-section__desc">Search instantly, switch views, and jump into grading with fewer clicks.</p>
      <div class="form-actions" style="margin-top:10px;">
        <a class="btn ui-btn ui-btn--primary" href="<?= h(url('teacher/export_submission.php?' . http_build_query(['format' => 'xlsx', 'subject_id' => $filterSubject, 'status' => $filterStatus]))) ?>">Export Excel</a>
        <a class="btn btn-secondary ui-btn ui-btn--secondary" href="<?= h(url('teacher/export_submission.php?' . http_build_query(['format' => 'csv', 'subject_id' => $filterSubject, 'status' => $filterStatus]))) ?>">Export CSV</a>
      </div>
    </div>
    <div class="submission-view-switch" role="tablist" aria-label="Submission layout">
      <button type="button" class="submission-view-btn active" data-submission-view-btn="cards" aria-pressed="true">Cards</button>
      <button type="button" class="submission-view-btn" data-submission-view-btn="table" aria-pressed="false">Compact table</button>
    </div>
  </div>

  <form method="get" class="filter-row submissions-toolbar ui-filter-group" data-submission-filter-form>
    <div class="submission-search-field">
      <label class="sr-only" for="submission-live-search">Search submissions</label>
      <input id="submission-live-search" type="search" placeholder="Search by student, ID, subject, section, or project" autocomplete="off" data-submission-search>
    </div>
    <select name="subject_id" data-submission-server-filter>
      <option value="0">All my subjects</option>
      <?php foreach ($subjectOptions as $subject): ?>
        <option value="<?= (int) $subject['id'] ?>" <?= $filterSubject === (int) $subject['id'] ? 'selected' : '' ?>><?= h($subject['subject_code']) ?> · <?= h($subject['subject_name']) ?></option>
      <?php endforeach; ?>
    </select>
    <select name="status" data-submission-server-filter>
      <option value="">All statuses</option>
      <option value="pending" <?= selected($filterStatus, 'pending') ?>>Pending</option>
      <option value="reviewed" <?= selected($filterStatus, 'reviewed') ?>>Reviewed</option>
      <option value="graded" <?= selected($filterStatus, 'graded') ?>>Graded</option>
    </select>
    <a class="btn btn-outline ui-btn ui-btn--ghost" href="<?= h(url('teacher/submissions.php')) ?>">Reset</a>
  </form>

  <div class="submission-quickbar">
    <div class="submission-quick-filters ui-filter-group" role="toolbar" aria-label="Quick submission filters">
      <button type="button" class="quick-filter-chip ui-filter active is-active" data-queue-filter="all">All</button>
      <button type="button" class="quick-filter-chip ui-filter" data-queue-filter="pending">Pending</button>
      <button type="button" class="quick-filter-chip ui-filter" data-queue-filter="reviewed">Reviewed</button>
      <button type="button" class="quick-filter-chip ui-filter" data-queue-filter="graded">Graded</button>
    </div>
    <div class="muted small submission-live-count"><span data-submission-live-count><?= count($rows) ?></span> submission<?= count($rows) === 1 ? '' : 's' ?> visible in this workspace</div>
  </div>

  <div class="review-card-grid submission-grid-view" data-submission-panel="cards">
    <?php foreach ($rows as $row):
      $searchText = strtolower(implode(' ', [
        (string) ($row['full_name'] ?? ''),
        (string) ($row['student_code'] ?? ''),
        (string) ($row['section_name'] ?? ''),
        (string) ($row['subject_name'] ?? ''),
        (string) ($row['subject_code'] ?? ''),
        (string) ($row['assigned_system'] ?? ''),
        (string) ($row['team_name'] ?? ''),
      ]));
      $submittedLabel = !empty($row['submitted_at']) ? date('M d, Y', strtotime($row['submitted_at'])) : '—';
      $gradeLabel = ($row['grade'] !== null && $row['grade'] !== '') ? (string) $row['grade'] : 'Not graded';
      $teamLabel = trim((string) ($row['team_name'] ?? '')) !== '' ? (string) $row['team_name'] : 'Solo or hidden team';
    ?>
      <article class="card submission-review-card" data-submission-item="cards" data-status="<?= h($row['status']) ?>" data-search="<?= h($searchText) ?>">
        <div class="submission-card-top">
          <div class="submission-student-block">
            <div class="student-avatar-badge submission-avatar-badge" aria-hidden="true"><?= h(teacher_submission_initials((string) $row['full_name'])) ?></div>
            <div class="submission-student-copy">
              <div class="submission-title-row">
                <h3 class="section-title"><?= h($row['full_name']) ?></h3>
                <span class="ui-badge <?= h($reviewBadgeClass((string) ($row['status'] ?? ''))) ?>"><?= h(ucfirst((string) $row['status'])) ?></span>
              </div>
              <div class="muted small">ID: <?= h($row['student_code']) ?> · <?= h($row['section_name']) ?></div>
            </div>
          </div>
          <div class="submission-card-actions">
            <a class="btn ui-btn ui-btn--primary" href="<?= h(url('teacher/submission_view.php?id=' . (int) $row['id'])) ?>">Review</a>
          </div>
        </div>

        <div class="submission-meta-line">
          <span class="pill soft"><?= h($row['subject_code']) ?></span>
          <span><?= h($row['subject_name']) ?></span>
        </div>

        <div class="submission-project-title"><?= h($row['assigned_system']) ?></div>

        <div class="submission-metrics-grid">
          <div class="segment"><span class="muted small">Team</span><strong><?= h($teamLabel) ?></strong></div>
          <div class="segment"><span class="muted small">Grade</span><strong><?= h($gradeLabel) ?></strong></div>
          <div class="segment"><span class="muted small">Submitted</span><strong><?= h($submittedLabel) ?></strong></div>
        </div>
      </article>
    <?php endforeach; ?>
    <div class="ui-empty-state submission-empty-state<?= $rows ? ' is-hidden' : '' ?>" data-submission-empty="cards"><div class="ui-empty-state__icon">○</div><h3 class="ui-empty-state__title">No pending submissions to review</h3><p class="ui-empty-state__text">No submissions matched the current search.</p></div>
  </div>

  <div class="submissions-table-shell is-hidden ui-table-card" data-submission-panel="table">
    <div class="submissions-table-wrap ui-table-wrap">
      <table class="table-redesign submission-compact-table ui-table">
        <thead>
          <tr>
            <th>Student</th>
            <th>Subject</th>
            <th>Project</th>
            <th>Team</th>
            <th>Status</th>
            <th>Grade</th>
            <th>Submitted</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $row):
            $searchText = strtolower(implode(' ', [
              (string) ($row['full_name'] ?? ''),
              (string) ($row['student_code'] ?? ''),
              (string) ($row['section_name'] ?? ''),
              (string) ($row['subject_name'] ?? ''),
              (string) ($row['subject_code'] ?? ''),
              (string) ($row['assigned_system'] ?? ''),
              (string) ($row['team_name'] ?? ''),
            ]));
            $submittedLabel = !empty($row['submitted_at']) ? date('M d, Y', strtotime($row['submitted_at'])) : '—';
            $gradeLabel = ($row['grade'] !== null && $row['grade'] !== '') ? (string) $row['grade'] : '—';
            $teamLabel = trim((string) ($row['team_name'] ?? '')) !== '' ? (string) $row['team_name'] : 'Solo';
          ?>
          <tr data-submission-item="table" data-status="<?= h($row['status']) ?>" data-search="<?= h($searchText) ?>">
            <td data-label="Student">
              <div class="submission-table-student">
                <div class="student-avatar-badge submission-avatar-badge is-small" aria-hidden="true"><?= h(teacher_submission_initials((string) $row['full_name'])) ?></div>
                <div>
                  <strong><?= h($row['full_name']) ?></strong>
                  <div class="muted small"><?= h($row['student_code']) ?> · <?= h($row['section_name']) ?></div>
                </div>
              </div>
            </td>
            <td data-label="Subject"><span class="pill soft"><?= h($row['subject_code']) ?></span><div class="muted small"><?= h($row['subject_name']) ?></div></td>
            <td data-label="Project"><strong><?= h($row['assigned_system']) ?></strong></td>
            <td data-label="Team"><?= h($teamLabel) ?></td>
            <td data-label="Status"><span class="ui-badge <?= h($reviewBadgeClass((string) ($row['status'] ?? ''))) ?>"><?= h(ucfirst((string) $row['status'])) ?></span></td>
            <td data-label="Grade"><strong><?= h($gradeLabel) ?></strong></td>
            <td data-label="Submitted"><?= h($submittedLabel) ?></td>
            <td data-label="Actions">
              <div class="table-actions">
                <a class="btn ui-btn ui-btn--primary" href="<?= h(url('teacher/submission_view.php?id=' . (int) $row['id'])) ?>">Review</a>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <div class="ui-empty-state submission-empty-state<?= $rows ? ' is-hidden' : '' ?>" data-submission-empty="table"><div class="ui-empty-state__icon">○</div><h3 class="ui-empty-state__title">No results found</h3><p class="ui-empty-state__text">No submissions matched the current search.</p></div>
  </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', function () {
  var workspace = document.querySelector('[data-submission-workspace]');
  if (!workspace) return;

  var searchInput = workspace.querySelector('[data-submission-search]');
  var liveCount = workspace.querySelector('[data-submission-live-count]');
  var panels = workspace.querySelectorAll('[data-submission-panel]');
  var viewButtons = workspace.querySelectorAll('[data-submission-view-btn]');
  var quickFilters = workspace.querySelectorAll('[data-queue-filter]');
  var serverFilters = workspace.querySelectorAll('[data-submission-server-filter]');
  var form = workspace.querySelector('[data-submission-filter-form]');
  var activeQuickFilter = 'all';
  var viewKey = 'teacher-submission-layout';
  var submitTimer = null;

  function getItems(type) {
    return Array.prototype.slice.call(workspace.querySelectorAll('[data-submission-item="' + type + '"]'));
  }

  function applyView(view) {
    panels.forEach(function (panel) {
      var match = panel.getAttribute('data-submission-panel') === view;
      panel.classList.toggle('is-hidden', !match);
    });
    viewButtons.forEach(function (button) {
      var active = button.getAttribute('data-submission-view-btn') === view;
      button.classList.toggle('active', active);
      button.setAttribute('aria-pressed', active ? 'true' : 'false');
    });
    try { localStorage.setItem(viewKey, view); } catch (e) {}
  }

  function setEmptyState(type, visibleCount) {
    var empty = workspace.querySelector('[data-submission-empty="' + type + '"]');
    if (!empty) return;
    empty.classList.toggle('is-hidden', visibleCount > 0);
  }

  function filterCollection(type, query, status) {
    var visible = 0;
    getItems(type).forEach(function (item) {
      var matchesQuery = !query || ((item.getAttribute('data-search') || '').indexOf(query) !== -1);
      var matchesStatus = status === 'all' || item.getAttribute('data-status') === status;
      var show = matchesQuery && matchesStatus;
      if (type === 'table') {
        item.style.display = show ? '' : 'none';
      } else {
        item.classList.toggle('is-hidden', !show);
      }
      if (show) visible += 1;
    });
    setEmptyState(type, visible);
    return visible;
  }

  function runFilters() {
    var query = (searchInput && searchInput.value ? searchInput.value : '').toLowerCase().trim();
    var cardsVisible = filterCollection('cards', query, activeQuickFilter);
    filterCollection('table', query, activeQuickFilter);
    if (liveCount) {
      liveCount.textContent = String(cardsVisible);
    }
  }

  quickFilters.forEach(function (button) {
    button.addEventListener('click', function () {
      activeQuickFilter = button.getAttribute('data-queue-filter') || 'all';
      quickFilters.forEach(function (chip) {
        chip.classList.toggle('active', chip === button);
        chip.classList.toggle('is-active', chip === button);
      });
      runFilters();
    });
  });

  if (searchInput) {
    searchInput.addEventListener('input', runFilters);
  }

  serverFilters.forEach(function (field) {
    field.addEventListener('change', function () {
      if (!form) return;
      window.clearTimeout(submitTimer);
      submitTimer = window.setTimeout(function () {
        form.submit();
      }, 120);
    });
  });

  viewButtons.forEach(function (button) {
    button.addEventListener('click', function () {
      applyView(button.getAttribute('data-submission-view-btn') || 'cards');
    });
  });

  var initialView = 'cards';
  try {
    initialView = localStorage.getItem(viewKey) || 'cards';
  } catch (e) {}
  applyView(initialView === 'table' ? 'table' : 'cards');
  runFilters();
});
</script>
<?php require_once __DIR__ . '/../backend/partials/footer.php'; ?>
