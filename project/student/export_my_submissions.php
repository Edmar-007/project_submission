<?php
if (defined('FILE_STUDENT_EXPORT_MY_SUBMISSIONS_PHP_LOADED')) { return; }
define('FILE_STUDENT_EXPORT_MY_SUBMISSIONS_PHP_LOADED', true);

require_once __DIR__ . '/../backend/config/app.php';
require_once __DIR__ . '/../backend/helpers/export.php';
require_role('student');

$student = current_user();
$pdo = pdo();
$format = trim((string) ($_GET['format'] ?? 'xlsx'));

$stmt = $pdo->prepare('SELECT COALESCE(act.title, "General submission") AS activity_title, subj.subject_name, sub.assigned_system AS submission_title, sub.status, sub.attempt_no, COALESCE(sub.grade, "") AS grade, sub.submitted_at, COALESCE(act.deadline_at, subj.submission_deadline) AS deadline_at, COALESCE(sub.project_url, "") AS repo_url, COALESCE(sub.video_url, "") AS live_url, COALESCE(sub.teacher_feedback, "") AS feedback_summary FROM submissions sub JOIN team_members tm ON tm.team_id = sub.team_id JOIN subjects subj ON subj.id = sub.subject_id LEFT JOIN submission_activities act ON act.id = sub.activity_id WHERE tm.student_id = ? ORDER BY sub.submitted_at DESC');
$stmt->execute([(int) $student['id']]);
$rows = $stmt->fetchAll();

export_table(
    'student_my_submissions',
    'Students',
    'My Submissions',
    'Current student scope',
    [
        'activity_title' => 'Activity',
        'subject_name' => 'Subject',
        'submission_title' => 'Submission Title',
        'status' => 'Status',
        'attempt_no' => 'Attempt No',
        'grade' => 'Grade',
        'submitted_at' => 'Submitted At',
        'deadline_at' => 'Deadline',
        'repo_url' => 'Repo URL',
        'live_url' => 'Live URL',
        'feedback_summary' => 'Feedback Summary',
    ],
    $rows,
    $format
);
