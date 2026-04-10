<?php
require_once __DIR__ . '/../config/auth.php';

function all_active_sections(): array {
    return pdo()->query('SELECT s.*, sy.label AS school_year, sem.name AS semester FROM sections s JOIN school_years sy ON sy.id = s.school_year_id JOIN semesters sem ON sem.id = s.semester_id WHERE s.status = "active" ORDER BY s.section_name')->fetchAll();
}

function all_sections(): array {
    return pdo()->query('SELECT s.*, sy.label AS school_year, sem.name AS semester FROM sections s JOIN school_years sy ON sy.id = s.school_year_id JOIN semesters sem ON sem.id = s.semester_id ORDER BY s.section_name')->fetchAll();
}

function all_teachers(): array {
    return pdo()->query('SELECT * FROM teachers WHERE status = "active" ORDER BY full_name')->fetchAll();
}

function all_subjects(): array {
    return pdo()->query('SELECT subj.*, t.full_name AS teacher_name FROM subjects subj JOIN teachers t ON t.id = subj.teacher_id ORDER BY subj.subject_name')->fetchAll();
}

function student_subjects(int $sectionId): array {
    $stmt = pdo()->prepare('SELECT subj.*, t.full_name AS teacher_name FROM section_subjects ss JOIN subjects subj ON subj.id = ss.subject_id JOIN teachers t ON t.id = subj.teacher_id WHERE ss.section_id = ? AND subj.status = "active" ORDER BY subj.subject_name');
    $stmt->execute([$sectionId]);
    return $stmt->fetchAll();
}

function fetch_notifications(string $userType, int $userId, int $limit = 5): array {
    $stmt = pdo()->prepare('SELECT * FROM notifications WHERE user_type = ? AND user_id = ? ORDER BY created_at DESC LIMIT ?');
    $stmt->bindValue(1, $userType, PDO::PARAM_STR);
    $stmt->bindValue(2, $userId, PDO::PARAM_INT);
    $stmt->bindValue(3, $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function mark_notifications_read(string $userType, int $userId): void {
    $stmt = pdo()->prepare('UPDATE notifications SET is_read = 1 WHERE user_type = ? AND user_id = ? AND is_read = 0');
    $stmt->execute([$userType, $userId]);
}


function mark_notification_read(string $userType, int $userId, int $notificationId): void {
    $stmt = pdo()->prepare('UPDATE notifications SET is_read = 1 WHERE id = ? AND user_type = ? AND user_id = ?');
    $stmt->execute([$notificationId, $userType, $userId]);
}

function fetch_notification_filters(string $userType, int $userId, string $state = '', string $type = ''): array {
    $sql = 'SELECT * FROM notifications WHERE user_type = ? AND user_id = ?';
    $params = [$userType, $userId];
    if ($state === 'unread') {
        $sql .= ' AND is_read = 0';
    } elseif ($state === 'read') {
        $sql .= ' AND is_read = 1';
    }
    if ($type !== '') {
        $sql .= ' AND type = ?';
        $params[] = $type;
    }
    $sql .= ' ORDER BY created_at DESC';
    $stmt = pdo()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function status_badge(string $status): string {
    return '<span class="status ' . h($status) . '">' . h(ucwords(str_replace('_', ' ', $status))) . '</span>';
}

function fetch_student_detail(int $studentId): ?array {
    $stmt = pdo()->prepare('SELECT st.*, sec.section_name, sec.status AS section_status, sy.label AS school_year, sem.name AS semester FROM students st JOIN sections sec ON sec.id = st.section_id JOIN school_years sy ON sy.id = sec.school_year_id JOIN semesters sem ON sem.id = sec.semester_id WHERE st.id = ? LIMIT 1');
    $stmt->execute([$studentId]);
    $student = $stmt->fetch();
    return $student ?: null;
}

function fetch_subject_detail(int $subjectId): ?array {
    $stmt = pdo()->prepare('SELECT subj.*, t.full_name AS teacher_name, t.email AS teacher_email, sy.label AS school_year, sem.name AS semester FROM subjects subj JOIN teachers t ON t.id = subj.teacher_id JOIN school_years sy ON sy.id = subj.school_year_id JOIN semesters sem ON sem.id = subj.semester_id WHERE subj.id = ? LIMIT 1');
    $stmt->execute([$subjectId]);
    $subject = $stmt->fetch();
    return $subject ?: null;
}

function fetch_submission_detail(int $submissionId): ?array {
    $stmt = pdo()->prepare('SELECT sub.*, st.full_name, st.student_id AS student_code, st.email AS student_email, sec.section_name, subj.subject_name, subj.subject_code, t.full_name AS teacher_name, t.id AS teacher_id FROM submissions sub JOIN students st ON st.id = sub.student_id JOIN sections sec ON sec.id = sub.section_id JOIN subjects subj ON subj.id = sub.subject_id JOIN teachers t ON t.id = subj.teacher_id WHERE sub.id = ? LIMIT 1');
    $stmt->execute([$submissionId]);
    $submission = $stmt->fetch();
    return $submission ?: null;
}

function fetch_submission_members(int $submissionId): array {
    $stmt = pdo()->prepare('SELECT * FROM submission_members WHERE submission_id = ? ORDER BY id ASC');
    $stmt->execute([$submissionId]);
    return $stmt->fetchAll();
}

function teacher_can_access_submission(int $teacherId, int $submissionId): bool {
    $stmt = pdo()->prepare('SELECT COUNT(*) FROM submissions sub JOIN subjects subj ON subj.id = sub.subject_id WHERE sub.id = ? AND subj.teacher_id = ?');
    $stmt->execute([$submissionId, $teacherId]);
    return (int) $stmt->fetchColumn() > 0;
}

function has_demo_access(array $submission): bool {
    return trim((string) ($submission['admin_username'] ?? '')) !== '' || trim((string) ($submission['admin_password'] ?? '')) !== '';
}

function count_for_query(string $sql, array $params = []): int {
    $stmt = pdo()->prepare($sql);
    $stmt->execute($params);
    return (int) $stmt->fetchColumn();
}


function all_school_years(): array {
    return pdo()->query('SELECT * FROM school_years ORDER BY id DESC')->fetchAll();
}

function all_semesters(): array {
    return pdo()->query('SELECT sem.*, sy.label AS school_year FROM semesters sem JOIN school_years sy ON sy.id = sem.school_year_id ORDER BY sem.id DESC')->fetchAll();
}

function fetch_section_distribution(): array {
    return pdo()->query('SELECT sec.section_name, COUNT(st.id) AS total_students FROM sections sec LEFT JOIN students st ON st.section_id = sec.id GROUP BY sec.id ORDER BY total_students DESC, sec.section_name ASC LIMIT 8')->fetchAll();
}

function fetch_subject_submission_distribution(): array {
    return pdo()->query('SELECT subj.subject_name, COUNT(sub.id) AS total_submissions FROM subjects subj LEFT JOIN submissions sub ON sub.subject_id = subj.id GROUP BY subj.id ORDER BY total_submissions DESC, subj.subject_name ASC LIMIT 8')->fetchAll();
}


function student_team_submissions(int $studentId): array {
    $stmt = pdo()->prepare('
        SELECT DISTINCT
            sub.*,
            subj.subject_name,
            subj.subject_code,
            tm.role AS member_role,
            t.team_name,
            leader.full_name AS leader_name,
            (SELECT GROUP_CONCAT(st2.full_name ORDER BY st2.full_name SEPARATOR ", ")
             FROM team_members tm2
             JOIN students st2 ON st2.id = tm2.student_id
             WHERE tm2.team_id = t.id) AS team_members_list
        FROM team_members tm
        JOIN teams t ON t.id = tm.team_id
        JOIN submissions sub ON sub.team_id = t.id
        JOIN subjects subj ON subj.id = sub.subject_id
        JOIN students leader ON leader.id = t.leader_student_id
        WHERE tm.student_id = ?
          AND sub.status <> "archived"
        ORDER BY sub.submitted_at DESC
    ');
    $stmt->execute([$studentId]);
    return $stmt->fetchAll();
}

function student_team_for_subject(int $studentId, int $subjectId): ?array {
    $stmt = pdo()->prepare('
        SELECT t.*, tm.role
        FROM team_members tm
        JOIN teams t ON t.id = tm.team_id
        WHERE tm.student_id = ?
          AND t.subject_id = ?
          AND t.status <> "archived"
        LIMIT 1
    ');
    $stmt->execute([$studentId, $subjectId]);
    return $stmt->fetch() ?: null;
}

function find_students_by_student_ids(array $studentIds, int $sectionId): array {
    if (!$studentIds) { return []; }
    $placeholders = implode(',', array_fill(0, count($studentIds), '?'));
    $params = $studentIds;
    $params[] = $sectionId;
    $stmt = pdo()->prepare("SELECT * FROM students WHERE student_id IN ($placeholders) AND section_id = ? AND account_status <> 'archived'");
    $stmt->execute($params);
    return $stmt->fetchAll();
}



function teacher_sections(int $teacherId): array {
    $stmt = pdo()->prepare('SELECT DISTINCT sec.id, sec.section_name FROM sections sec JOIN section_subjects ss ON ss.section_id = sec.id JOIN subjects subj ON subj.id = ss.subject_id WHERE subj.teacher_id = ? AND sec.status = "active" ORDER BY sec.section_name');
    $stmt->execute([$teacherId]);
    return $stmt->fetchAll();
}

function teacher_students(int $teacherId): array {
    $stmt = pdo()->prepare('SELECT DISTINCT st.*, sec.section_name FROM students st JOIN sections sec ON sec.id = st.section_id JOIN section_subjects ss ON ss.section_id = sec.id JOIN subjects subj ON subj.id = ss.subject_id WHERE subj.teacher_id = ? ORDER BY st.full_name');
    $stmt->execute([$teacherId]);
    return $stmt->fetchAll();
}

function send_student_activation_invite(array $student, string $token, string $inviterName): void {
    require_once __DIR__ . '/mailer.php';
    $link = url('student/activate.php?token=' . urlencode($token));
    $body = "Hello {$student['full_name']},

" .
        "{$inviterName} created your student portal access.
" .
        "Student ID: {$student['student_id']}

" .
        "Complete your account setup here:
{$link}

" .
        "This link expires in 72 hours. After activation, sign in using your Student ID or email address.

" .
        APP_NAME;
    send_system_mail($student['email'], 'Activate your student portal account', $body);
}
