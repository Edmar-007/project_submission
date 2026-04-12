<?php
require_once __DIR__ . '/../backend/config/app.php';
try {
    $pdo = pdo();
    $pdo->beginTransaction();
    $teamId = 1;
    $studentId = 1;
    $sectionId = 1;
    $subjectId = 1;
    $activityId = null;
    $attemptNo = 1;
    $assignedSystem = 'CLI Test Insert';
    $companyName = 'CLI Corp';
    $projectUrl = 'https://example.com/cli-project';
    $videoUrl = '';
    $adminUsername = demo_encrypt('cli_admin');
    $adminPassword = demo_encrypt('cli_pass');
    $contactEmail = 'juan@example.com';
    $attachmentPath = null;
    $notes = 'Inserted via CLI test script';

    $stmt = $pdo->prepare('INSERT INTO submissions (team_id, student_id, submitted_by_student_id, section_id, subject_id, activity_id, attempt_no, assigned_system, company_name, project_url, video_url, admin_username, admin_password, user_username, user_password, contact_email, attachment_path, review_notes, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, "pending")');
    $stmt->execute([
        $teamId,
        $studentId,
        $studentId,
        $sectionId,
        $subjectId,
        $activityId,
        $attemptNo,
        $assignedSystem,
        $companyName,
        $projectUrl,
        $videoUrl,
        $adminUsername,
        $adminPassword,
        null,
        null,
        $contactEmail,
        $attachmentPath,
        $notes,
    ]);
    $submissionId = (int) $pdo->lastInsertId();
    $mstmt = $pdo->prepare('INSERT INTO submission_members (submission_id, student_id, member_name, student_id_snapshot) VALUES (?, ?, ?, ?)');
    $mstmt->execute([$submissionId, $studentId, 'Juan Dela Cruz', '2025-0001']);
    $pdo->commit();
    echo "Inserted submission id: $submissionId\n";
} catch (Throwable $e) {
    if ($pdo && $pdo->inTransaction()) { $pdo->rollBack(); }
    echo 'Error: ' . $e->getMessage() . "\n";
}
