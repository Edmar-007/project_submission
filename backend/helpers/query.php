<?php
if (defined('BACKEND_HELPERS_QUERY_PHP_LOADED')) { return; }
define('BACKEND_HELPERS_QUERY_PHP_LOADED', true);
require_once __DIR__ . '/../config/auth.php';

function ensure_submission_activity_schema(): void {
    static $ready = false;
    if ($ready) { return; }
    $pdo = pdo();
    $pdo->exec("CREATE TABLE IF NOT EXISTS submission_activities (
        id INT AUTO_INCREMENT PRIMARY KEY,
        subject_id INT NOT NULL,
        title VARCHAR(180) NOT NULL,
        description TEXT NULL,
        status ENUM('draft','published','closed','archived') NOT NULL DEFAULT 'draft',
        opens_at DATETIME NULL,
        deadline_at DATETIME NULL,
        allow_late TINYINT(1) NOT NULL DEFAULT 0,
        late_until DATETIME NULL,
        is_locked TINYINT(1) NOT NULL DEFAULT 0,
        lock_note TEXT NULL,
        submission_mode ENUM('individual','team') NOT NULL DEFAULT 'team',
        min_members INT NOT NULL DEFAULT 1,
        max_members INT NOT NULL DEFAULT 5,
        allow_resubmission TINYINT(1) NOT NULL DEFAULT 0,
        max_resubmissions INT NOT NULL DEFAULT 1,
        require_file TINYINT(1) NOT NULL DEFAULT 0,
        require_repository TINYINT(1) NOT NULL DEFAULT 1,
        require_live_url TINYINT(1) NOT NULL DEFAULT 1,
        require_demo_access TINYINT(1) NOT NULL DEFAULT 0,
        require_notes TINYINT(1) NOT NULL DEFAULT 0,
        created_by_teacher_id INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_submission_activities_subject (subject_id, status, deadline_at),
        INDEX idx_submission_activities_teacher (created_by_teacher_id, status),
        CONSTRAINT fk_submission_activities_subject FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE ON UPDATE CASCADE,
        CONSTRAINT fk_submission_activities_teacher FOREIGN KEY (created_by_teacher_id) REFERENCES teachers(id) ON DELETE CASCADE ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("CREATE TABLE IF NOT EXISTS submission_activity_sections (
        id INT AUTO_INCREMENT PRIMARY KEY,
        activity_id INT NOT NULL,
        section_id INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_activity_section (activity_id, section_id),
        INDEX idx_activity_section (section_id),
        CONSTRAINT fk_submission_activity_sections_activity FOREIGN KEY (activity_id) REFERENCES submission_activities(id) ON DELETE CASCADE ON UPDATE CASCADE,
        CONSTRAINT fk_submission_activity_sections_section FOREIGN KEY (section_id) REFERENCES sections(id) ON DELETE CASCADE ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $tables = [
        'submission_activities' => [
            'status' => "ALTER TABLE submission_activities ADD COLUMN status ENUM('draft','published','closed','archived') NOT NULL DEFAULT 'draft' AFTER description",
            'opens_at' => "ALTER TABLE submission_activities ADD COLUMN opens_at DATETIME NULL AFTER status"
        ],
        'teams' => ['activity_id' => "ALTER TABLE teams ADD COLUMN activity_id INT NULL AFTER subject_id"],
        'submissions' => [
            'activity_id' => "ALTER TABLE submissions ADD COLUMN activity_id INT NULL AFTER subject_id",
            'attempt_no' => "ALTER TABLE submissions ADD COLUMN attempt_no INT NOT NULL DEFAULT 1 AFTER activity_id"
        ],
        'submission_members' => [
            'student_id' => "ALTER TABLE submission_members ADD COLUMN student_id INT NULL AFTER submission_id",
            'student_id_snapshot' => "ALTER TABLE submission_members ADD COLUMN student_id_snapshot VARCHAR(50) NULL AFTER member_name"
        ],
    ];
    $checkTable = static function(string $tableName) use ($pdo): bool {
        $res = $pdo->query("SHOW TABLES LIKE " . $pdo->quote($tableName));
        if ($res === false) return false;
        return (bool) $res->fetch();
    };
    foreach ($tables as $table => $columns) {
        if (!$checkTable($table)) {
            // table does not exist yet; skip column migrations for it
            continue;
        }
        $existing = [];
        foreach ($pdo->query('SHOW COLUMNS FROM ' . $table)->fetchAll() as $col) { $existing[$col['Field']] = true; }
        foreach ($columns as $column => $ddl) {
            if (!isset($existing[$column])) { $pdo->exec($ddl); }
        }
    }

    $teamIndexes = [];
    if ($checkTable('teams')) {
        foreach ($pdo->query('SHOW INDEX FROM teams')->fetchAll() as $idx) { $teamIndexes[$idx['Key_name']] = true; }
        if (isset($teamIndexes['uniq_leader_subject_team'])) {
            try { $pdo->exec('ALTER TABLE teams DROP INDEX uniq_leader_subject_team'); } catch (Throwable $e) {}
        }
        if (!isset($teamIndexes['uniq_leader_activity_team'])) {
            try { $pdo->exec('ALTER TABLE teams ADD UNIQUE KEY uniq_leader_activity_team (leader_student_id, activity_id)'); } catch (Throwable $e) {}
        }
        try { $pdo->exec('ALTER TABLE teams ADD CONSTRAINT fk_teams_activity FOREIGN KEY (activity_id) REFERENCES submission_activities(id) ON DELETE CASCADE ON UPDATE CASCADE'); } catch (Throwable $e) {}
    }
    $submissionIndexes = [];
    if ($checkTable('submissions')) {
        foreach ($pdo->query('SHOW INDEX FROM submissions')->fetchAll() as $idx) { $submissionIndexes[$idx['Key_name']] = true; }
        if (isset($submissionIndexes['uniq_team_subject'])) {
            try { $pdo->exec('ALTER TABLE submissions DROP INDEX uniq_team_subject'); } catch (Throwable $e) {}
        }
        if (isset($submissionIndexes['uniq_team_activity'])) {
            try { $pdo->exec('ALTER TABLE submissions DROP INDEX uniq_team_activity'); } catch (Throwable $e) {}
        }
        if (!isset($submissionIndexes['idx_team_activity_attempt'])) {
            try { $pdo->exec('ALTER TABLE submissions ADD INDEX idx_team_activity_attempt (team_id, activity_id, attempt_no)'); } catch (Throwable $e) {}
        }
        if (!isset($submissionIndexes['idx_student_activity_attempt'])) {
            try { $pdo->exec('ALTER TABLE submissions ADD INDEX idx_student_activity_attempt (student_id, activity_id, attempt_no)'); } catch (Throwable $e) {}
        }
        try { $pdo->exec('ALTER TABLE submissions ADD CONSTRAINT fk_submissions_activity FOREIGN KEY (activity_id) REFERENCES submission_activities(id) ON DELETE SET NULL ON UPDATE CASCADE'); } catch (Throwable $e) {}
    }
    // submission_members constraints/indexes
    if ($checkTable('submission_members')) {
        try { $pdo->exec('ALTER TABLE submission_members ADD CONSTRAINT fk_submission_members_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE SET NULL ON UPDATE CASCADE'); } catch (Throwable $e) {}
        try { $pdo->exec('ALTER TABLE submission_members ADD INDEX idx_submission_members_student (student_id)'); } catch (Throwable $e) {}
    }

    $ready = true;
}
ensure_submission_activity_schema();

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
    $stmt = pdo()->prepare('SELECT subj.*, t.full_name AS teacher_name, ss.submission_deadline AS section_submission_deadline, ss.deadline_warning_hours AS section_deadline_warning_hours, (
        SELECT MIN(act.deadline_at)
        FROM submission_activities act
        LEFT JOIN submission_activity_sections sas ON sas.activity_id = act.id
        WHERE act.subject_id = subj.id
          AND act.status = "published"
          AND act.deadline_at IS NOT NULL
          AND (sas.section_id IS NULL OR sas.section_id = ss.section_id)
    ) AS next_activity_deadline FROM section_subjects ss JOIN subjects subj ON subj.id = ss.subject_id JOIN teachers t ON t.id = subj.teacher_id WHERE ss.section_id = ? AND subj.status = "active" ORDER BY subj.subject_name');
    $stmt->execute([$sectionId]);
    $rows = $stmt->fetchAll();
    foreach ($rows as &$row) {
        if (!empty($row['section_submission_deadline'])) {
            $row['submission_deadline'] = $row['section_submission_deadline'];
        }
        if (!empty($row['section_deadline_warning_hours'])) {
            $row['deadline_warning_hours'] = (int) $row['section_deadline_warning_hours'];
        }
        $row['deadline_window'] = subject_deadline_window_with_activity($row);
        $row['submission_locked'] = student_subject_locked($row);
    }
    unset($row);
    return $rows;
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

if (!function_exists('status_badge')) {
    function status_badge(string $status): string {
        $raw = strtolower(trim($status));
        $classMap = [
            'published' => 'open',
            'open' => 'open',
            'pending' => 'submitted',
            'submitted' => 'submitted',
            'late' => 'late',
            'reviewed' => 'reviewed',
            'graded' => 'graded',
            'needs_revision' => 'needs-revision',
            'archived' => 'archived',
            'closed' => 'closed',
            'locked' => 'closed',
            'warning' => 'closing-soon',
            'reopened' => 'open',
            'draft' => 'draft',
        ];
        $labelMap = [
            'published' => 'Open',
            'pending' => 'Submitted',
            'late' => 'Late',
            'reviewed' => 'Reviewed',
            'graded' => 'Graded',
            'needs_revision' => 'Needs revision',
            'warning' => 'Closing soon',
        ];
        $class = $classMap[$raw] ?? ($raw !== '' ? $raw : 'neutral');
        $label = $labelMap[$raw] ?? ucwords(str_replace('_', ' ', ($raw !== '' ? $raw : 'status')));
        return '<span class="status ' . h($class) . '">' . h($label) . '</span>';
    }
}

function subject_deadline_window_with_activity(array $subject): array {
    if ((int) ($subject['teacher_submission_locked'] ?? 0) === 1) {
        return subject_deadline_window($subject);
    }

    $activityDeadlineRaw = trim((string) ($subject['next_activity_deadline'] ?? ''));
    if ($activityDeadlineRaw !== '') {
        try {
            $now = new DateTimeImmutable('now');
            $deadline = new DateTimeImmutable($activityDeadlineRaw);
            $warningHours = max(1, (int) ($subject['deadline_warning_hours'] ?? 72));
            $warningStart = $deadline->sub(new DateInterval('PT' . $warningHours . 'H'));
            if ($now > $deadline) {
                return ['has_deadline' => true, 'state' => 'closed', 'label' => 'Next due passed: ' . $deadline->format('M d, Y h:i A'), 'deadline' => $deadline];
            }
            if ($now >= $warningStart) {
                return ['has_deadline' => true, 'state' => 'warning', 'label' => 'Next due soon: ' . $deadline->format('M d, Y h:i A'), 'deadline' => $deadline];
            }
            return ['has_deadline' => true, 'state' => 'open', 'label' => 'Next due: ' . $deadline->format('M d, Y h:i A'), 'deadline' => $deadline];
        } catch (Throwable $e) {
        }
    }

    $window = subject_deadline_window($subject);
    if (($window['label'] ?? '') === 'No deadline set') {
        $window['label'] = 'No active deadlines';
    }
    return $window;
}

function fetch_student_detail(int $studentId): ?array {
    $stmt = pdo()->prepare('SELECT st.*, sec.section_name, sec.status AS section_status, sy.label AS school_year, sem.name AS semester FROM students st JOIN sections sec ON sec.id = st.section_id JOIN school_years sy ON sy.id = sec.school_year_id JOIN semesters sem ON sem.id = sec.semester_id WHERE st.id = ? LIMIT 1');
    $stmt->execute([$studentId]);
    $student = $stmt->fetch();
    return $student ?: null;
}

function fetch_subject_detail(int $subjectId): ?array {
    $stmt = pdo()->prepare('SELECT subj.*, t.full_name AS teacher_name, t.email AS teacher_email, sy.label AS school_year, sem.name AS semester, (
        SELECT MIN(act.deadline_at) FROM submission_activities act WHERE act.subject_id = subj.id AND act.status = "published" AND act.deadline_at IS NOT NULL
    ) AS next_activity_deadline FROM subjects subj JOIN teachers t ON t.id = subj.teacher_id JOIN school_years sy ON sy.id = subj.school_year_id JOIN semesters sem ON sem.id = subj.semester_id WHERE subj.id = ? LIMIT 1');
    $stmt->execute([$subjectId]);
    $subject = $stmt->fetch();
    if ($subject) {
        $subject['deadline_window'] = subject_deadline_window_with_activity($subject);
        $subject['submission_locked'] = student_subject_locked($subject);
    }
    return $subject ?: null;
}

function fetch_submission_detail(int $submissionId): ?array {
    $stmt = pdo()->prepare('SELECT sub.*, st.full_name, st.student_id AS student_code, st.email AS student_email, sec.section_name, subj.subject_name, subj.subject_code, t.full_name AS teacher_name, t.id AS teacher_id, act.title AS activity_title, act.status AS activity_status FROM submissions sub JOIN students st ON st.id = sub.student_id JOIN sections sec ON sec.id = sub.section_id JOIN subjects subj ON subj.id = sub.subject_id JOIN teachers t ON t.id = subj.teacher_id LEFT JOIN submission_activities act ON act.id = sub.activity_id WHERE sub.id = ? LIMIT 1');
    $stmt->execute([$submissionId]);
    $submission = $stmt->fetch();
    return $submission ?: null;
}

function fetch_submission_members(int $submissionId): array {
    $stmt = pdo()->prepare('SELECT sm.*, st.full_name AS linked_student_name, st.student_id AS linked_student_code FROM submission_members sm LEFT JOIN students st ON st.id = sm.student_id WHERE sm.submission_id = ? ORDER BY sm.id ASC');
    $stmt->execute([$submissionId]);
    return $stmt->fetchAll();
}

function teacher_can_access_submission(int $teacherId, int $submissionId): bool {
    $stmt = pdo()->prepare('SELECT COUNT(*) FROM submissions sub JOIN subjects subj ON subj.id = sub.subject_id WHERE sub.id = ? AND subj.teacher_id = ?');
    $stmt->execute([$submissionId, $teacherId]);
    return (int) $stmt->fetchColumn() > 0;
}

function has_demo_access(array $submission): bool {
    return trim((string) ($submission['admin_username'] ?? '')) !== ''
        || trim((string) ($submission['admin_password'] ?? '')) !== ''
        || trim((string) ($submission['user_username'] ?? '')) !== ''
        || trim((string) ($submission['user_password'] ?? '')) !== '';
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


function student_submission_row_for_manage(int $studentId, int $submissionId): ?array {
    $stmt = pdo()->prepare('
        SELECT
            sub.*,
            subj.subject_name,
            subj.subject_code,
            subj.teacher_id,
            act.title AS activity_title,
            t.team_name,
            t.leader_student_id,
            leader.full_name AS leader_name,
            tm.role AS member_role,
            submitter.full_name AS submitted_by_name
        FROM team_members tm
        JOIN teams t ON t.id = tm.team_id
        JOIN submissions sub ON sub.team_id = t.id
        JOIN subjects subj ON subj.id = sub.subject_id
        LEFT JOIN submission_activities act ON act.id = sub.activity_id
        JOIN students leader ON leader.id = t.leader_student_id
        LEFT JOIN students submitter ON submitter.id = sub.submitted_by_student_id
        WHERE tm.student_id = ?
          AND sub.id = ?
          AND sub.status <> "archived"
        LIMIT 1
    ');
    $stmt->execute([$studentId, $submissionId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function student_can_edit_submission(int $studentId, int $submissionId): bool {
    $row = student_submission_row_for_manage($studentId, $submissionId);
    if (!$row) {
        return false;
    }
    return ($row['member_role'] ?? '') === 'leader' && !in_array($row['status'] ?? '', ['graded', 'archived'], true);
}

function team_member_rows(int $teamId): array {
    $stmt = pdo()->prepare('SELECT tm.role, st.id, st.student_id, st.full_name, st.email FROM team_members tm JOIN students st ON st.id = tm.student_id WHERE tm.team_id = ? ORDER BY CASE WHEN tm.role = "leader" THEN 0 ELSE 1 END, st.full_name ASC');
    $stmt->execute([$teamId]);
    return $stmt->fetchAll();
}

function archive_submission_record(int $submissionId): void {
    $stmt = pdo()->prepare('UPDATE submissions SET status = "archived", updated_at = CURRENT_TIMESTAMP WHERE id = ?');
    $stmt->execute([$submissionId]);
}

function delete_submission_record(int $submissionId): void {
    archive_submission_record($submissionId);
}

function student_team_submissions(int $studentId, bool $includeArchived = false): array {
    $sql = '
        SELECT DISTINCT
            sub.*,
            subj.subject_name,
            subj.subject_code,
            act.title AS activity_title,
            act.status AS activity_status,
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
        LEFT JOIN submission_activities act ON act.id = sub.activity_id
        JOIN students leader ON leader.id = t.leader_student_id
        WHERE tm.student_id = ?
    ';
    if (!$includeArchived) {
        $sql .= ' AND sub.status <> "archived"';
    }
    $sql .= ' ORDER BY COALESCE(sub.updated_at, sub.submitted_at) DESC, sub.id DESC';
    $stmt = pdo()->prepare($sql);
    $stmt->execute([$studentId]);
    $rows = $stmt->fetchAll();
    foreach ($rows as $row) {
        ensure_submission_history_seed((int) $row['id']);
    }
    return $rows;
}

function student_team_for_subject(int $studentId, int $subjectId): ?array {
    $stmt = pdo()->prepare('
        SELECT t.*, tm.role, leader.full_name AS leader_name, leader.student_id AS leader_student_code
        FROM team_members tm
        JOIN teams t ON t.id = tm.team_id
        JOIN students leader ON leader.id = t.leader_student_id
        WHERE tm.student_id = ?
          AND t.subject_id = ?
          AND t.activity_id IS NULL
          AND t.status <> "archived"
        LIMIT 1
    ');
    $stmt->execute([$studentId, $subjectId]);
    return $stmt->fetch() ?: null;
}

function subject_team_members(int $teamId): array {
    $stmt = pdo()->prepare('SELECT tm.role, st.id, st.student_id, st.full_name, st.email FROM team_members tm JOIN students st ON st.id = tm.student_id WHERE tm.team_id = ? ORDER BY CASE WHEN tm.role = "leader" THEN 0 ELSE 1 END, st.full_name ASC');
    $stmt->execute([$teamId]);
    return $stmt->fetchAll();
}

function subject_team_for_leader(int $leaderStudentId, int $subjectId): ?array {
    $stmt = pdo()->prepare('SELECT * FROM teams WHERE leader_student_id = ? AND subject_id = ? AND activity_id IS NULL AND status <> "archived" LIMIT 1');
    $stmt->execute([$leaderStudentId, $subjectId]);
    return $stmt->fetch() ?: null;
}

function activity_subject_team_for_student(int $studentId, int $subjectId): ?array {
    $stmt = pdo()->prepare('
        SELECT t.*, tm.role, leader.full_name AS leader_name, leader.student_id AS leader_student_code
        FROM team_members tm
        JOIN teams t ON t.id = tm.team_id
        JOIN students leader ON leader.id = t.leader_student_id
        WHERE tm.student_id = ?
          AND t.subject_id = ?
          AND t.activity_id IS NULL
          AND t.status = "active"
        LIMIT 1
    ');
    $stmt->execute([$studentId, $subjectId]);
    return $stmt->fetch() ?: null;
}

function searchable_students_for_subject_team(int $subjectId, int $leaderStudentId, string $query, array $excludeIds = []): array {
    $query = trim($query);
    if ($query === '') {
        return [];
    }

    $subject = fetch_subject_detail($subjectId);
    if (!$subject) {
        return [];
    }

    $stmt = pdo()->prepare('SELECT section_id FROM section_subjects WHERE subject_id = ?');
    $stmt->execute([$subjectId]);
    $sectionIds = array_values(array_unique(array_map('intval', array_column($stmt->fetchAll(), 'section_id'))));
    if (!$sectionIds) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($sectionIds), '?'));
    $params = array_merge([$leaderStudentId], $sectionIds, ['%' . $query . '%', '%' . $query . '%']);
    $sql = "SELECT st.id, st.student_id, st.full_name, st.email, sec.section_name
            FROM students st
            JOIN sections sec ON sec.id = st.section_id
            WHERE st.id <> ?
              AND st.account_status IN ('active','view_only')
              AND st.section_id IN ($placeholders)
              AND (st.student_id LIKE ? OR st.full_name LIKE ?)";

    if ($excludeIds) {
        $excludeIds = array_values(array_filter(array_map('intval', $excludeIds)));
        if ($excludeIds) {
            $excludePlaceholders = implode(',', array_fill(0, count($excludeIds), '?'));
            $sql .= " AND st.id NOT IN ($excludePlaceholders)";
            $params = array_merge($params, $excludeIds);
        }
    }

    $sql .= ' ORDER BY st.full_name ASC LIMIT 8';
    $stmt = pdo()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function students_for_subject_team_ids(int $subjectId, array $studentIds): array {
    $studentIds = array_values(array_filter(array_map('intval', $studentIds)));
    if (!$studentIds) {
        return [];
    }
    $sectionStmt = pdo()->prepare('SELECT section_id FROM section_subjects WHERE subject_id = ?');
    $sectionStmt->execute([$subjectId]);
    $sectionIds = array_values(array_unique(array_map('intval', array_column($sectionStmt->fetchAll(), 'section_id'))));
    if (!$sectionIds) {
        return [];
    }
    $studentPlaceholders = implode(',', array_fill(0, count($studentIds), '?'));
    $sectionPlaceholders = implode(',', array_fill(0, count($sectionIds), '?'));
    $params = array_merge($studentIds, $sectionIds);
    $stmt = pdo()->prepare("SELECT st.*, sec.section_name FROM students st JOIN sections sec ON sec.id = st.section_id WHERE st.id IN ($studentPlaceholders) AND st.section_id IN ($sectionPlaceholders) AND st.account_status <> 'archived'");
    $stmt->execute($params);
    return $stmt->fetchAll();
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

function all_subjects_for_teacher(int $teacherId): array {
    $stmt = pdo()->prepare('SELECT id, subject_code, subject_name, status, submission_deadline, updated_at FROM subjects WHERE teacher_id = ? AND status <> "archived" ORDER BY subject_name ASC');
    $stmt->execute([$teacherId]);
    return $stmt->fetchAll();
}

function available_subject_sections(): array {
    return all_active_sections();
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


function student_subject_locked(array $subject): bool {
    $window = $subject['deadline_window'] ?? subject_deadline_window_with_activity($subject);
    return in_array(($window['state'] ?? 'open'), ['locked', 'closed'], true);
}

if (!function_exists('format_deadline_for_input')) {
    function format_deadline_for_input(?string $value): string {
        if (!$value) {
            return '';
        }
        try {
            return (new DateTimeImmutable($value))->format('Y-m-d\TH:i');
        } catch (Throwable $e) {
            return '';
        }
    }
}

function deadline_badge_html(array $subject): string {
    $window = $subject['deadline_window'] ?? subject_deadline_window_with_activity($subject);
    $state = $window['state'] ?? 'open';
    $classMap = ['open' => 'success', 'warning' => 'warning', 'locked' => 'danger', 'closed' => 'danger', 'reopened' => 'info'];
    $class = $classMap[$state] ?? 'neutral';
    return '<span class="status ' . h($class) . '">' . h($window['label'] ?? 'No active deadlines') . '</span>';
}


function subject_sections_with_deadlines(int $subjectId): array {
    $stmt = pdo()->prepare('SELECT ss.id AS mapping_id, sec.id AS section_id, sec.section_name, sec.status, ss.submission_deadline, ss.deadline_warning_hours, COUNT(st.id) AS total_students FROM section_subjects ss JOIN sections sec ON sec.id = ss.section_id LEFT JOIN students st ON st.section_id = sec.id WHERE ss.subject_id = ? GROUP BY ss.id, sec.id ORDER BY sec.section_name');
    $stmt->execute([$subjectId]);
    return $stmt->fetchAll();
}

function subject_resources_for_role(int $subjectId, bool $studentVisibleOnly = false): array {
    $sql = 'SELECT sr.*, t.full_name AS teacher_name FROM subject_resources sr JOIN teachers t ON t.id = sr.created_by_teacher_id WHERE sr.subject_id = ?';
    if ($studentVisibleOnly) {
        $sql .= ' AND sr.is_visible_to_students = 1';
    }
    $sql .= ' ORDER BY sr.created_at DESC, sr.id DESC';
    $stmt = pdo()->prepare($sql);
    $stmt->execute([$subjectId]);
    return $stmt->fetchAll();
}

function student_visible_subject_resources(int $subjectId): array {
    return subject_resources_for_role($subjectId, true);
}


function ensure_submission_history_table(): void {
    static $ready = false;
    if ($ready) {
        return;
    }
    pdo()->exec('CREATE TABLE IF NOT EXISTS submission_history (
        id INT AUTO_INCREMENT PRIMARY KEY,
        submission_id INT NOT NULL,
        team_id INT NOT NULL,
        subject_id INT NOT NULL,
        section_id INT NOT NULL,
        version_no INT NOT NULL,
        action_type ENUM("created", "edited", "reviewed", "graded", "deleted", "restored") NOT NULL DEFAULT "created",
        actor_user_id INT NULL,
        actor_role ENUM("student", "teacher", "admin", "system") NOT NULL DEFAULT "system",
        actor_name VARCHAR(150) NULL,
        status VARCHAR(50) NOT NULL,
        grade VARCHAR(50) NULL,
        assigned_system VARCHAR(255) NOT NULL,
        company_name VARCHAR(255) NOT NULL,
        project_url TEXT NULL,
        video_url TEXT NULL,
        contact_email VARCHAR(150) NULL,
        attachment_path VARCHAR(255) NULL,
        teacher_feedback TEXT NULL,
        review_notes TEXT NULL,
        snapshot_payload LONGTEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_submission_history_submission (submission_id, version_no),
        INDEX idx_submission_history_team (team_id),
        INDEX idx_submission_history_subject (subject_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    $ready = true;
}

function next_submission_history_version(int $submissionId): int {
    ensure_submission_history_table();
    $stmt = pdo()->prepare('SELECT COALESCE(MAX(version_no), 0) + 1 FROM submission_history WHERE submission_id = ?');
    $stmt->execute([$submissionId]);
    return (int) $stmt->fetchColumn();
}

function snapshot_submission_history(int $submissionId, string $actionType, string $actorRole = 'system', ?int $actorUserId = null, ?string $actorName = null): void {
    ensure_submission_history_table();
    $submission = fetch_submission_detail($submissionId);
    if (!$submission) {
        return;
    }
    $version = next_submission_history_version($submissionId);
    $memberRows = fetch_submission_members($submissionId);
    $payload = [
        'submission' => [
            'id' => (int) $submission['id'],
            'team_id' => (int) $submission['team_id'],
            'subject_id' => (int) $submission['subject_id'],
            'section_id' => (int) $submission['section_id'],
            'assigned_system' => $submission['assigned_system'],
            'company_name' => $submission['company_name'],
            'project_url' => $submission['project_url'],
            'video_url' => $submission['video_url'],
            'contact_email' => $submission['contact_email'],
            'status' => $submission['status'],
            'grade' => $submission['grade'],
            'teacher_feedback' => $submission['teacher_feedback'],
            'review_notes' => $submission['review_notes'],
            'attachment_path' => $submission['attachment_path'],
            'submitted_at' => $submission['submitted_at'],
            'updated_at' => $submission['updated_at'],
        ],
        'members' => array_map(static fn(array $row): array => [
            'member_name' => $row['member_name'] ?? '',
        ], $memberRows),
    ];
    $stmt = pdo()->prepare('INSERT INTO submission_history (
        submission_id, team_id, subject_id, section_id, version_no, action_type,
        actor_user_id, actor_role, actor_name, status, grade, assigned_system,
        company_name, project_url, video_url, contact_email, attachment_path,
        teacher_feedback, review_notes, snapshot_payload
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([
        $submission['id'],
        $submission['team_id'],
        $submission['subject_id'],
        $submission['section_id'],
        $version,
        $actionType,
        $actorUserId,
        $actorRole,
        $actorName,
        $submission['status'],
        $submission['grade'],
        $submission['assigned_system'],
        $submission['company_name'],
        $submission['project_url'],
        $submission['video_url'],
        $submission['contact_email'],
        $submission['attachment_path'],
        $submission['teacher_feedback'],
        $submission['review_notes'],
        json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ]);
}

function ensure_submission_history_seed(int $submissionId): void {
    ensure_submission_history_table();
    $stmt = pdo()->prepare('SELECT COUNT(*) FROM submission_history WHERE submission_id = ?');
    $stmt->execute([$submissionId]);
    if ((int) $stmt->fetchColumn() === 0 && fetch_submission_detail($submissionId)) {
        snapshot_submission_history($submissionId, 'created', 'system', null, 'System backfill');
    }
}

function fetch_submission_history(int $submissionId): array {
    ensure_submission_history_seed($submissionId);
    $stmt = pdo()->prepare('SELECT * FROM submission_history WHERE submission_id = ? ORDER BY version_no DESC, id DESC');
    $stmt->execute([$submissionId]);
    return $stmt->fetchAll();
}

function fetch_submission_history_map(array $submissionIds): array {
    ensure_submission_history_table();
    $submissionIds = array_values(array_filter(array_map('intval', $submissionIds)));
    if (!$submissionIds) {
        return [];
    }
    foreach ($submissionIds as $submissionId) {
        ensure_submission_history_seed((int) $submissionId);
    }
    $placeholders = implode(',', array_fill(0, count($submissionIds), '?'));
    $stmt = pdo()->prepare("SELECT * FROM submission_history WHERE submission_id IN ($placeholders) ORDER BY submission_id ASC, version_no DESC, id DESC");
    $stmt->execute($submissionIds);
    $map = [];
    foreach ($stmt->fetchAll() as $row) {
        $map[(int) $row['submission_id']][] = $row;
    }
    return $map;
}

function action_badge(string $action): string {
    $classMap = [
        'created' => 'info',
        'edited' => 'warning',
        'reviewed' => 'info',
        'graded' => 'success',
        'deleted' => 'danger',
        'restored' => 'success',
    ];
    $class = $classMap[$action] ?? 'neutral';
    return '<span class="status ' . h($class) . '">' . h(ucwords(str_replace('_', ' ', $action))) . '</span>';
}

ensure_submission_history_table();


function subject_activities_for_teacher(int $subjectId, ?int $teacherId = null): array {
    $params = [$subjectId];
    $sql = 'SELECT act.*, (SELECT COUNT(*) FROM submission_activity_sections sas WHERE sas.activity_id = act.id) AS total_sections, (SELECT COUNT(*) FROM submissions sub WHERE sub.activity_id = act.id AND sub.status <> "archived") AS total_submissions FROM submission_activities act JOIN subjects subj ON subj.id = act.subject_id WHERE act.subject_id = ?';
    if ($teacherId !== null) {
        $sql .= ' AND subj.teacher_id = ?';
        $params[] = $teacherId;
    }
    $sql .= ' ORDER BY FIELD(act.status, "published", "draft", "closed", "archived"), COALESCE(act.deadline_at, act.created_at) ASC, act.id DESC';
    $stmt = pdo()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function activity_section_ids(int $activityId): array {
    $stmt = pdo()->prepare('SELECT section_id FROM submission_activity_sections WHERE activity_id = ? ORDER BY section_id');
    $stmt->execute([$activityId]);
    return array_map('intval', array_column($stmt->fetchAll(), 'section_id'));
}

function activity_sections_label(int $activityId): string {
    $stmt = pdo()->prepare('SELECT GROUP_CONCAT(sec.section_name ORDER BY sec.section_name SEPARATOR ", ") FROM submission_activity_sections sas JOIN sections sec ON sec.id = sas.section_id WHERE sas.activity_id = ?');
    $stmt->execute([$activityId]);
    return (string) ($stmt->fetchColumn() ?: 'All assigned sections');
}

function teacher_activity_detail(int $activityId, int $teacherId): ?array {
    $stmt = pdo()->prepare('SELECT act.*, subj.subject_name, subj.subject_code, subj.teacher_id FROM submission_activities act JOIN subjects subj ON subj.id = act.subject_id WHERE act.id = ? AND subj.teacher_id = ? LIMIT 1');
    $stmt->execute([$activityId, $teacherId]);
    return $stmt->fetch() ?: null;
}

function activity_window(array $activity): array {
    $now = new DateTimeImmutable('now');
    $opensAt = !empty($activity['opens_at']) ? new DateTimeImmutable((string) $activity['opens_at']) : null;
    $deadline = !empty($activity['deadline_at']) ? new DateTimeImmutable((string) $activity['deadline_at']) : null;
    $lateUntil = !empty($activity['late_until']) ? new DateTimeImmutable((string) $activity['late_until']) : null;
    if (($activity['status'] ?? '') !== 'published') {
        return ['state' => strtolower((string) ($activity['status'] ?? 'draft')), 'label' => ucfirst((string) ($activity['status'] ?? 'draft'))];
    }
    if (!empty($activity['is_locked'])) {
        return ['state' => 'locked', 'label' => 'Locked by teacher'];
    }
    if ($opensAt && $now < $opensAt) {
        return ['state' => 'upcoming', 'label' => 'Opens ' . $opensAt->format('M d, Y g:i A')];
    }
    if ($deadline && $now > $deadline) {
        if (!empty($activity['allow_late']) && $lateUntil && $now <= $lateUntil) {
            return ['state' => 'late', 'label' => 'Late until ' . $lateUntil->format('M d, Y g:i A')];
        }
        return ['state' => 'locked', 'label' => 'Closed'];
    }
    return ['state' => 'open', 'label' => $deadline ? ('Due ' . $deadline->format('M d, Y g:i A')) : 'Open'];
}

function student_activity_locked(array $activity): bool {
    $window = activity_window($activity);
    return in_array($window['state'], ['locked', 'draft', 'upcoming', 'archived', 'closed'], true);
}

function student_visible_activities(int $studentId, int $sectionId, ?int $subjectId = null): array {
    $params = [$sectionId, $sectionId];
    $sql = 'SELECT DISTINCT act.*, subj.subject_name, subj.subject_code, subj.description AS subject_description, subj.teacher_id, t.full_name AS teacher_name FROM submission_activities act JOIN subjects subj ON subj.id = act.subject_id JOIN teachers t ON t.id = subj.teacher_id JOIN section_subjects ss ON ss.subject_id = subj.id LEFT JOIN submission_activity_sections sas ON sas.activity_id = act.id WHERE ss.section_id = ? AND subj.status = "active" AND act.status IN ("published", "closed") AND (sas.section_id IS NULL OR sas.section_id = ?)';
    if ($subjectId !== null) {
        $sql .= ' AND subj.id = ?';
        $params[] = $subjectId;
    }
    $sql .= ' ORDER BY subj.subject_name ASC, CASE WHEN act.deadline_at IS NULL THEN 1 ELSE 0 END ASC, act.deadline_at ASC, act.created_at ASC, act.title ASC';
    $stmt = pdo()->prepare($sql);
    $stmt->execute($params);
    $rows = [];
    $seen = [];
    foreach ($stmt->fetchAll() as $row) {
        $id = (int) $row['id'];
        if (isset($seen[$id])) {
            continue;
        }
        $seen[$id] = true;
        $row['activity_window'] = activity_window($row);
        $row['submission_locked'] = student_activity_locked($row);
        $rows[] = $row;
    }
    return $rows;
}

function student_activity_detail(int $activityId, int $studentId, int $sectionId): ?array {
    foreach (student_visible_activities($studentId, $sectionId) as $activity) {
        if ((int) $activity['id'] === $activityId) {
            return $activity;
        }
    }
    return null;
}

function student_team_for_activity(int $studentId, int $activityId): ?array {
    $stmt = pdo()->prepare('SELECT t.*, tm.role FROM team_members tm JOIN teams t ON t.id = tm.team_id WHERE tm.student_id = ? AND t.activity_id = ? AND t.status <> "archived" LIMIT 1');
    $stmt->execute([$studentId, $activityId]);
    return $stmt->fetch() ?: null;
}

function activity_eligible_section_ids(int $activityId): array {
    $ids = activity_section_ids($activityId);
    if ($ids) {
        return $ids;
    }
    $stmt = pdo()->prepare('SELECT ss.section_id FROM submission_activities act JOIN section_subjects ss ON ss.subject_id = act.subject_id WHERE act.id = ?');
    $stmt->execute([$activityId]);
    return array_map('intval', array_column($stmt->fetchAll(), 'section_id'));
}

function searchable_students_for_activity(int $activityId, int $leaderStudentId, string $query, array $excludeIds = []): array {
    $query = trim($query);
    if ($query === '') {
        return [];
    }
    $sectionIds = activity_eligible_section_ids($activityId);
    if (!$sectionIds) {
        return [];
    }
    $placeholders = implode(',', array_fill(0, count($sectionIds), '?'));
    $params = array_merge([$leaderStudentId], $sectionIds, ['%' . $query . '%', '%' . $query . '%', '%' . $query . '%']);
    $sql = "SELECT st.id, st.student_id, st.full_name, st.email, sec.section_name FROM students st JOIN sections sec ON sec.id = st.section_id WHERE st.id <> ? AND st.account_status IN ('active','view_only','pending') AND st.section_id IN ($placeholders) AND (st.student_id LIKE ? OR st.full_name LIKE ? OR st.email LIKE ?)";
    if ($excludeIds) {
        $excludeIds = array_values(array_filter(array_map('intval', $excludeIds)));
        if ($excludeIds) {
            $excludePlaceholders = implode(',', array_fill(0, count($excludeIds), '?'));
            $sql .= " AND st.id NOT IN ($excludePlaceholders)";
            $params = array_merge($params, $excludeIds);
        }
    }
    $sql .= ' ORDER BY st.full_name ASC LIMIT 8';
    $stmt = pdo()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function students_for_activity_ids(int $activityId, array $studentIds): array {
    $studentIds = array_values(array_filter(array_map('intval', $studentIds)));
    if (!$studentIds) {
        return [];
    }
    $sectionIds = activity_eligible_section_ids($activityId);
    if (!$sectionIds) {
        return [];
    }
    $studentPlaceholders = implode(',', array_fill(0, count($studentIds), '?'));
    $sectionPlaceholders = implode(',', array_fill(0, count($sectionIds), '?'));
    $params = array_merge($studentIds, $sectionIds);
    $stmt = pdo()->prepare("SELECT st.*, sec.section_name FROM students st JOIN sections sec ON sec.id = st.section_id WHERE st.id IN ($studentPlaceholders) AND st.section_id IN ($sectionPlaceholders) AND st.account_status <> 'archived'");
    $stmt->execute($params);
    return $stmt->fetchAll();
}
