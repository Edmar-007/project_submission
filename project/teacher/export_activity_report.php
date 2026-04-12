<?php
if (defined('FILE_TEACHER_EXPORT_ACTIVITY_REPORT_PHP_LOADED')) { return; }
define('FILE_TEACHER_EXPORT_ACTIVITY_REPORT_PHP_LOADED', true);

require_once __DIR__ . '/../backend/config/app.php';
require_once __DIR__ . '/../backend/helpers/export.php';
require_role('teacher');

$teacher = current_user();
$pdo = pdo();
$format = trim((string) ($_GET['format'] ?? 'xlsx'));
$subjectId = (int) ($_GET['subject_id'] ?? 0);

$where = ['subj.teacher_id = ?'];
$params = [(int) $teacher['id']];
if ($subjectId > 0) {
    $where[] = 'subj.id = ?';
    $params[] = $subjectId;
}

$sql = 'SELECT act.title AS activity_title, subj.subject_name, act.deadline_at, act.submission_mode AS mode, act.min_members, act.max_members, CASE WHEN act.allow_late = 1 THEN "Yes" ELSE "No" END AS late_allowed, CASE WHEN act.allow_resubmission = 1 THEN "Yes" ELSE "No" END AS resubmissions_allowed, CASE WHEN act.require_repository = 1 THEN "Yes" ELSE "No" END AS repo_required, CASE WHEN act.require_live_url = 1 THEN "Yes" ELSE "No" END AS live_url_required, CASE WHEN act.require_file = 1 THEN "Yes" ELSE "No" END AS file_upload_required, COUNT(sub.id) AS total_submissions, SUM(CASE WHEN sub.status = "pending" THEN 1 ELSE 0 END) AS pending_count, SUM(CASE WHEN sub.status = "reviewed" THEN 1 ELSE 0 END) AS reviewed_count, SUM(CASE WHEN sub.status = "graded" THEN 1 ELSE 0 END) AS graded_count FROM submission_activities act JOIN subjects subj ON subj.id = act.subject_id LEFT JOIN submissions sub ON sub.activity_id = act.id WHERE ' . implode(' AND ', $where) . ' GROUP BY act.id ORDER BY subj.subject_name, act.deadline_at IS NULL, act.deadline_at, act.title';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

export_table(
    'teacher_activity_summary',
    'Activities',
    'Teacher Activity Summary',
    $subjectId > 0 ? ('subject=' . $subjectId) : 'All assigned subjects',
    [
        'activity_title' => 'Activity Title',
        'subject_name' => 'Subject',
        'deadline_at' => 'Deadline',
        'mode' => 'Mode',
        'min_members' => 'Min Members',
        'max_members' => 'Max Members',
        'late_allowed' => 'Late Allowed',
        'resubmissions_allowed' => 'Resubmissions Allowed',
        'repo_required' => 'Repo Required',
        'live_url_required' => 'Live URL Required',
        'file_upload_required' => 'File Upload Required',
        'total_submissions' => 'Total Submissions',
        'pending_count' => 'Pending Count',
        'reviewed_count' => 'Reviewed Count',
        'graded_count' => 'Graded Count',
    ],
    $rows,
    $format
);
