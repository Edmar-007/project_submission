<?php
if (defined('FILE_STUDENT_MEMBER_SEARCH_PHP_LOADED')) { return; }
define('FILE_STUDENT_MEMBER_SEARCH_PHP_LOADED', true);

require_once __DIR__ . '/../backend/config/app.php';
require_role('student');
header('Content-Type: application/json; charset=utf-8');
$student = current_user();
$activityId = (int) ($_GET['activity_id'] ?? 0);
$subjectId = (int) ($_GET['subject_id'] ?? 0);
$query = trim((string) ($_GET['q'] ?? ''));
$excludeRaw = trim((string) ($_GET['exclude'] ?? ''));
$excludeIds = array_values(array_filter(array_map('intval', array_filter(explode(',', $excludeRaw)))));
if (($activityId <= 0 && $subjectId <= 0) || $query === '') {
    echo json_encode(['items' => []]);
    exit;
}

if ($subjectId > 0) {
    $subjectIds = array_map(static fn(array $row): int => (int) $row['id'], student_subjects((int) $student['section_id']));
    if (!in_array($subjectId, $subjectIds, true)) {
        http_response_code(403);
        echo json_encode(['items' => []]);
        exit;
    }
    $items = searchable_students_for_subject_team($subjectId, (int) $student['id'], $query, $excludeIds);
    echo json_encode(['items' => $items], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$activity = student_activity_detail($activityId, (int) $student['id'], (int) $student['section_id']);
if (!$activity) {
    http_response_code(403);
    echo json_encode(['items' => []]);
    exit;
}

$items = searchable_students_for_activity($activityId, (int) $student['id'], $query, $excludeIds);
echo json_encode(['items' => $items], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
