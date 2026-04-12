<?php
if (defined('FILE_ADMIN_EXPORT_REPORT_PHP_LOADED')) { return; }
define('FILE_ADMIN_EXPORT_REPORT_PHP_LOADED', true);

require_once __DIR__ . '/../backend/config/app.php';
require_once __DIR__ . '/../backend/helpers/export.php';
require_role('admin');

$pdo = pdo();
$type = trim((string) ($_GET['type'] ?? 'submissions'));
$format = trim((string) ($_GET['format'] ?? 'xlsx'));
$sectionId = (int) ($_GET['section_id'] ?? 0);
$subjectId = (int) ($_GET['subject_id'] ?? 0);
$status = trim((string) ($_GET['status'] ?? ''));
$submissionId = (int) ($_GET['submission_id'] ?? 0);

if ($type === 'students') {
    $rows = $pdo->query('SELECT st.student_id AS student_id_code, st.full_name AS student_name, st.email, sec.section_name, st.account_status, st.can_submit FROM students st JOIN sections sec ON sec.id = st.section_id ORDER BY st.full_name')->fetchAll();
    export_table(
        'admin_students_report',
        'Students',
        'Student Directory',
        'All students',
        [
            'student_id_code' => 'Student ID',
            'student_name' => 'Student Name',
            'email' => 'Email',
            'section_name' => 'Section',
            'account_status' => 'Status',
            'can_submit' => 'Can Submit',
        ],
        $rows,
        $format
    );
}

if ($type === 'teams') {
    $stmt = $pdo->prepare('SELECT subj.subject_name, t.team_name, leader.full_name AS leader_name, m.full_name AS member_name, m.student_id AS student_id_code, m.email, tm.role, t.status AS team_status FROM teams t JOIN subjects subj ON subj.id = t.subject_id JOIN students leader ON leader.id = t.leader_student_id JOIN team_members tm ON tm.team_id = t.id JOIN students m ON m.id = tm.student_id ORDER BY subj.subject_name, t.team_name, CASE tm.role WHEN "leader" THEN 0 ELSE 1 END, m.full_name');
    $stmt->execute();
    $rows = $stmt->fetchAll();
    export_table(
        'admin_team_roster',
        'Teams',
        'Team Roster',
        'All teams',
        [
            'subject_name' => 'Subject',
            'team_name' => 'Team Name',
            'leader_name' => 'Leader',
            'member_name' => 'Member Name',
            'student_id_code' => 'Student ID',
            'email' => 'Email',
            'role' => 'Role',
            'team_status' => 'Team Status',
        ],
        $rows,
        $format
    );
}

$where = [];
$params = [];
$filters = [];
if ($sectionId > 0) {
    $where[] = 'sub.section_id = ?';
    $params[] = $sectionId;
    $filters[] = 'section=' . $sectionId;
}
if ($subjectId > 0) {
    $where[] = 'sub.subject_id = ?';
    $params[] = $subjectId;
    $filters[] = 'subject=' . $subjectId;
}
if ($status !== '') {
    $where[] = 'sub.status = ?';
    $params[] = $status;
    $filters[] = 'status=' . $status;
}
if ($submissionId > 0) {
    $where[] = 'sub.id = ?';
    $params[] = $submissionId;
    $filters[] = 'submission=' . $submissionId;
}
$sqlWhere = $where ? (' WHERE ' . implode(' AND ', $where)) : '';
$filterSummary = $filters ? implode(', ', $filters) : 'All records';

$stmt = $pdo->prepare('SELECT sub.id AS submission_id, COALESCE(act.title, "General submission") AS activity_title, subj.subject_name, sec.section_name, st.full_name AS student_name, st.student_id AS student_id_code, COALESCE(tm.team_name, "Individual") AS team_name, COALESCE(act.submission_mode, "team") AS submission_mode, sub.status, sub.attempt_no, COALESCE(sub.grade, "") AS grade, sub.submitted_at, COALESCE(act.deadline_at, subj.submission_deadline) AS deadline_at, CASE WHEN COALESCE(act.deadline_at, subj.submission_deadline) IS NOT NULL AND sub.submitted_at > COALESCE(act.deadline_at, subj.submission_deadline) THEN "Yes" ELSE "No" END AS late_submission, COALESCE(sub.project_url, "") AS repo_url, COALESCE(sub.video_url, "") AS live_url, COALESCE(sub.attachment_path, "") AS attachment, COALESCE(sub.teacher_feedback, "") AS teacher_feedback, COALESCE(sh.actor_name, "") AS reviewed_by, COALESCE(sh.created_at, "") AS reviewed_at FROM submissions sub JOIN students st ON st.id = sub.student_id JOIN sections sec ON sec.id = sub.section_id JOIN subjects subj ON subj.id = sub.subject_id LEFT JOIN teams tm ON tm.id = sub.team_id LEFT JOIN submission_activities act ON act.id = sub.activity_id LEFT JOIN (SELECT h.submission_id, h.actor_name, h.created_at FROM submission_history h INNER JOIN (SELECT submission_id, MAX(version_no) AS max_version FROM submission_history WHERE action_type IN ("reviewed","graded") GROUP BY submission_id) mx ON mx.submission_id = h.submission_id AND mx.max_version = h.version_no) sh ON sh.submission_id = sub.id' . $sqlWhere . ' ORDER BY sub.submitted_at DESC');
$stmt->execute($params);
$rows = $stmt->fetchAll();

export_table(
    'admin_submission_report',
    'Submissions',
    'Submission Report',
    $filterSummary,
    [
        'submission_id' => 'Submission ID',
        'activity_title' => 'Activity Title',
        'subject_name' => 'Subject',
        'section_name' => 'Section',
        'student_name' => 'Student Name',
        'student_id_code' => 'Student ID',
        'team_name' => 'Team Name',
        'submission_mode' => 'Submission Mode',
        'status' => 'Status',
        'attempt_no' => 'Attempt No',
        'grade' => 'Grade',
        'submitted_at' => 'Submitted At',
        'deadline_at' => 'Deadline',
        'late_submission' => 'Late Submission',
        'repo_url' => 'Repo URL',
        'live_url' => 'Live URL',
        'attachment' => 'Attachment',
        'teacher_feedback' => 'Teacher Feedback',
        'reviewed_by' => 'Reviewed By',
        'reviewed_at' => 'Reviewed At',
    ],
    $rows,
    $format
);
