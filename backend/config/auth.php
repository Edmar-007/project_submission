<?php
require_once __DIR__ . '/db.php';

function current_portal_role(): ?string {
    static $portalRole = null;
    static $resolved = false;
    if ($resolved) {
        return $portalRole;
    }

    $scriptPath = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    $segments = array_values(array_filter(explode('/', trim($scriptPath, '/'))));
    $portalRole = $segments[1] ?? null;
    if (!in_array($portalRole, ['admin', 'teacher', 'student'], true)) {
        $portalRole = null;
    }

    $resolved = true;
    return $portalRole;
}

function session_auth_store(): array {
    $auth = $_SESSION['auth'] ?? [];

    if (isset($auth['role'], $auth['user']) && is_array($auth['user'])) {
        $legacyRole = $auth['role'];
        if (in_array($legacyRole, ['admin', 'teacher', 'student'], true)) {
            $auth = [
                $legacyRole => [
                    'role' => $legacyRole,
                    'user' => $auth['user'],
                ],
            ];
            $_SESSION['auth'] = $auth;
        }
    }

    return is_array($auth) ? $auth : [];
}


function logged_in_roles(): array {
    $auth = session_auth_store();
    $roles = [];
    foreach (['admin', 'teacher', 'student'] as $role) {
        if (isset($auth[$role]['user']) && is_array($auth[$role]['user'])) {
            $roles[] = $role;
        }
    }
    return $roles;
}

function first_logged_in_role(array $preferredOrder = ['admin', 'teacher', 'student']): ?string {
    $roles = logged_in_roles();
    foreach ($preferredOrder as $role) {
        if (in_array($role, $roles, true)) {
            return $role;
        }
    }
    return $roles[0] ?? null;
}

function dashboard_url_for_role(string $role): string {
    $map = [
        'admin' => 'admin/dashboard.php',
        'teacher' => 'teacher/dashboard.php',
        'student' => 'student/dashboard.php',
    ];
    return $map[$role] ?? '';
}

function current_role(): ?string {
    $portalRole = current_portal_role();
    $auth = session_auth_store();
    if ($portalRole && isset($auth[$portalRole]['user'])) {
        return $portalRole;
    }
    return null;
}

function current_user(): ?array {
    $portalRole = current_portal_role();
    $auth = session_auth_store();
    if ($portalRole && isset($auth[$portalRole]['user']) && is_array($auth[$portalRole]['user'])) {
        return $auth[$portalRole]['user'];
    }
    return null;
}

function login_user(string $role, array $user): void {
    $auth = session_auth_store();
    $auth[$role] = [
        'role' => $role,
        'user' => $user,
    ];
    $_SESSION['auth'] = $auth;
    session_regenerate_id(true);
}


function set_current_user_session(array $user, ?string $role = null): void {
    $role = $role ?: current_portal_role();
    if (!$role) {
        return;
    }

    $auth = session_auth_store();
    $auth[$role] = [
        'role' => $role,
        'user' => $user,
    ];
    $_SESSION['auth'] = $auth;
}

function logout_user(?string $role = null): void {
    $role = $role ?: current_portal_role();
    if (!$role) {
        unset($_SESSION['auth']);
        return;
    }

    $auth = session_auth_store();
    unset($auth[$role]);

    if ($auth) {
        $_SESSION['auth'] = $auth;
    } else {
        unset($_SESSION['auth']);
    }

    session_regenerate_id(true);
}

function require_role(string $role): void {
    $current = current_role();
    if ($current === $role) {
        return;
    }

    $otherRole = first_logged_in_role([$role === 'admin' ? 'teacher' : 'admin', 'student', 'teacher']);
    if ($otherRole && $otherRole !== $role) {
        set_flash('info', 'You are already signed in to the ' . ucfirst($otherRole) . ' portal. Please use that workspace or sign out before switching roles.');
        redirect_to(dashboard_url_for_role($otherRole));
    }

    set_flash('error', 'Please log in first.');
    redirect_to($role . '/login.php');
}

function authenticate_table(string $table, string $identity, string $password): ?array {
    $sql = '';
    if ($table === 'students') {
        $sql = 'SELECT * FROM students WHERE (username = ? OR student_id = ? OR email = ?) LIMIT 1';
    } elseif ($table === 'teachers') {
        $sql = 'SELECT * FROM teachers WHERE (username = ? OR teacher_id = ? OR email = ?) LIMIT 1';
    } else {
        $sql = 'SELECT * FROM ' . $table . ' WHERE username = ? LIMIT 1';
    }

    $stmt = pdo()->prepare($sql);
    if ($table === 'admins') {
        $stmt->execute([$identity]);
    } else {
        $stmt->execute([$identity, $identity, $identity]);
    }

    $user = $stmt->fetch();
    if (!$user) {
        return null;
    }

    $status = $user['status'] ?? $user['account_status'] ?? 'active';
    if (in_array($status, ['inactive', 'archived', 'pending'], true)) {
        return null;
    }
    if (!password_verify($password, $user['password_hash'])) {
        return null;
    }
    return $user;
}

function create_notification(string $userType, int $userId, string $title, string $message, string $type = 'info'): void {
    $stmt = pdo()->prepare('INSERT INTO notifications (user_type, user_id, title, message, type) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute([$userType, $userId, $title, $message, $type]);
}


function notification_exists(string $userType, int $userId, string $title, string $message): bool {
    $stmt = pdo()->prepare('SELECT COUNT(*) FROM notifications WHERE user_type = ? AND user_id = ? AND title = ? AND message = ?');
    $stmt->execute([$userType, $userId, $title, $message]);
    return (int) $stmt->fetchColumn() > 0;
}

function create_notification_once(string $userType, int $userId, string $title, string $message, string $type = 'info'): void {
    if (notification_exists($userType, $userId, $title, $message)) {
        return;
    }
    create_notification($userType, $userId, $title, $message, $type);
}

function ensure_deadline_schema(): void {
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    try {
        $pdo = pdo();
        $columns = [];
        foreach ($pdo->query('SHOW COLUMNS FROM subjects')->fetchAll() as $column) {
            $columns[$column['Field']] = true;
        }
        $ddl = [];
        if (!isset($columns['submission_deadline'])) { $ddl[] = 'ADD COLUMN submission_deadline DATETIME NULL AFTER status'; }
        if (!isset($columns['deadline_warning_hours'])) { $ddl[] = 'ADD COLUMN deadline_warning_hours INT NOT NULL DEFAULT 72 AFTER submission_deadline'; }
        if (!isset($columns['deadline_warning_sent_at'])) { $ddl[] = 'ADD COLUMN deadline_warning_sent_at DATETIME NULL AFTER deadline_warning_hours'; }
        if (!isset($columns['deadline_locked_notice_sent_at'])) { $ddl[] = 'ADD COLUMN deadline_locked_notice_sent_at DATETIME NULL AFTER deadline_warning_sent_at'; }
        if (!isset($columns['allow_late_submissions'])) { $ddl[] = 'ADD COLUMN allow_late_submissions TINYINT(1) NOT NULL DEFAULT 0 AFTER deadline_locked_notice_sent_at'; }
        if (!isset($columns['late_submission_until'])) { $ddl[] = 'ADD COLUMN late_submission_until DATETIME NULL AFTER allow_late_submissions'; }
        if ($ddl) {
            $pdo->exec('ALTER TABLE subjects ' . implode(', ', $ddl));
        }
    } catch (Throwable $e) {
    }
}

function send_deadline_mail_if_possible(string $email, string $subject, string $body): void {
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return;
    }
    require_once __DIR__ . '/../helpers/mailer.php';
    send_system_mail($email, $subject, $body);
}

function subject_deadline_window(array $subject): array {
    $deadlineRaw = trim((string) ($subject['submission_deadline'] ?? ''));
    if ($deadlineRaw === '') {
        return ['has_deadline' => false, 'state' => 'open', 'label' => 'No deadline set'];
    }
    try {
        $now = new DateTimeImmutable('now');
        $deadline = new DateTimeImmutable($deadlineRaw);
    } catch (Throwable $e) {
        return ['has_deadline' => false, 'state' => 'open', 'label' => 'Invalid deadline'];
    }
    $warningHours = max(1, (int) ($subject['deadline_warning_hours'] ?? 72));
    $warningStart = $deadline->sub(new DateInterval('PT' . $warningHours . 'H'));
    $allowLate = (int) ($subject['allow_late_submissions'] ?? 0) === 1;
    $lateUntilRaw = trim((string) ($subject['late_submission_until'] ?? ''));
    $lateUntil = null;
    if ($lateUntilRaw !== '') {
        try { $lateUntil = new DateTimeImmutable($lateUntilRaw); } catch (Throwable $e) { $lateUntil = null; }
    }
    $lateOverrideActive = $allowLate && ($lateUntil === null || $now <= $lateUntil);
    if ($now >= $deadline) {
        if ($lateOverrideActive) {
            $label = 'Deadline reached — teacher reopened submissions';
            if ($lateUntil) { $label .= ' until ' . $lateUntil->format('M d, Y h:i A'); }
            return ['has_deadline' => true, 'state' => 'reopened', 'label' => $label, 'deadline' => $deadline, 'late_override_active' => true, 'late_until' => $lateUntil];
        }
        return ['has_deadline' => true, 'state' => 'locked', 'label' => 'Deadline reached on ' . $deadline->format('M d, Y h:i A'), 'deadline' => $deadline, 'late_override_active' => false, 'late_until' => $lateUntil];
    }
    if ($now >= $warningStart) {
        return ['has_deadline' => true, 'state' => 'warning', 'label' => 'Deadline near: ' . $deadline->format('M d, Y h:i A'), 'deadline' => $deadline, 'late_override_active' => false, 'late_until' => $lateUntil];
    }
    return ['has_deadline' => true, 'state' => 'open', 'label' => 'Open until ' . $deadline->format('M d, Y h:i A'), 'deadline' => $deadline, 'late_override_active' => false, 'late_until' => $lateUntil];
}

function process_subject_deadlines(): void {
    static $processed = false;
    if ($processed) {
        return;
    }
    $processed = true;
    ensure_deadline_schema();
    try {
        $pdo = pdo();
        $subjects = $pdo->query('SELECT subj.*, t.full_name AS teacher_name, t.email AS teacher_email FROM subjects subj JOIN teachers t ON t.id = subj.teacher_id WHERE subj.status IN ("active", "inactive") AND subj.submission_deadline IS NOT NULL')->fetchAll();
        foreach ($subjects as $subject) {
            $window = subject_deadline_window($subject);
            if (empty($window['has_deadline'])) {
                continue;
            }
            $studentStmt = $pdo->prepare('SELECT DISTINCT st.id, st.full_name, st.email FROM section_subjects ss JOIN students st ON st.section_id = ss.section_id WHERE ss.subject_id = ? AND st.account_status <> "archived"');
            $studentStmt->execute([(int) $subject['id']]);
            $students = $studentStmt->fetchAll();
            if ($window['state'] === 'warning' && empty($subject['deadline_warning_sent_at'])) {
                $title = 'Deadline approaching: ' . $subject['subject_name'];
                $message = 'The submission deadline for ' . $subject['subject_name'] . ' is near. Final deadline: ' . $window['deadline']->format('M d, Y h:i A') . '. Submit before the cutoff to avoid being locked out.';
                foreach ($students as $student) {
                    create_notification_once('student', (int) $student['id'], $title, $message, 'warning');
                    send_deadline_mail_if_possible((string) $student['email'], $title, "Hello {$student['full_name']},

{$message}

Regards,
" . APP_NAME);
                }
                create_notification_once('teacher', (int) $subject['teacher_id'], $title, 'Your students have been warned that the deadline for ' . $subject['subject_name'] . ' is approaching.', 'warning');
                send_deadline_mail_if_possible((string) $subject['teacher_email'], $title, "Hello {$subject['teacher_name']},

Your students have been warned that the deadline for {$subject['subject_name']} is approaching.

Regards,
" . APP_NAME);
                $pdo->prepare('UPDATE subjects SET deadline_warning_sent_at = NOW() WHERE id = ?')->execute([(int) $subject['id']]);
            }
            if ($window['state'] === 'locked' && empty($subject['deadline_locked_notice_sent_at'])) {
                $title = 'Deadline reached: ' . $subject['subject_name'];
                $message = 'The deadline for ' . $subject['subject_name'] . ' has been reached. New submissions are now blocked unless your teacher reopens the submission window.';
                foreach ($students as $student) {
                    create_notification_once('student', (int) $student['id'], $title, $message, 'warning');
                    send_deadline_mail_if_possible((string) $student['email'], $title, "Hello {$student['full_name']},

{$message}

Regards,
" . APP_NAME);
                }
                create_notification_once('teacher', (int) $subject['teacher_id'], $title, 'The deadline for ' . $subject['subject_name'] . ' has been reached. Students are now blocked from new submissions unless you reopen them.', 'warning');
                send_deadline_mail_if_possible((string) $subject['teacher_email'], $title, "Hello {$subject['teacher_name']},

The deadline for {$subject['subject_name']} has been reached. Students are now blocked from new submissions unless you reopen them.

Regards,
" . APP_NAME);
                $pdo->prepare('UPDATE subjects SET deadline_locked_notice_sent_at = NOW() WHERE id = ?')->execute([(int) $subject['id']]);
            }
        }
    } catch (Throwable $e) {
    }
}

function log_action(string $actorType, int $actorId, string $action, string $targetType, int $targetId, string $description = ''): void {
    $stmt = pdo()->prepare('INSERT INTO audit_logs (actor_type, actor_id, action, target_type, target_id, description) VALUES (?, ?, ?, ?, ?, ?)');
    $stmt->execute([$actorType, $actorId, $action, $targetType, $targetId, $description]);
}

function active_school_year_id(): ?int {
    $id = pdo()->query('SELECT id FROM school_years WHERE is_active = 1 ORDER BY id DESC LIMIT 1')->fetchColumn();
    return $id ? (int) $id : null;
}

function active_semester_id(?int $schoolYearId = null): ?int {
    if (!$schoolYearId) {
        $schoolYearId = active_school_year_id();
    }
    if (!$schoolYearId) {
        return null;
    }
    $stmt = pdo()->prepare('SELECT id FROM semesters WHERE school_year_id = ? AND is_active = 1 ORDER BY id ASC LIMIT 1');
    $stmt->execute([$schoolYearId]);
    $id = $stmt->fetchColumn();
    return $id ? (int) $id : null;
}

function count_unread_notifications(string $userType, int $userId): int {
    $stmt = pdo()->prepare('SELECT COUNT(*) FROM notifications WHERE user_type = ? AND user_id = ? AND is_read = 0');
    $stmt->execute([$userType, $userId]);
    return (int) $stmt->fetchColumn();
}

ensure_deadline_schema();
process_subject_deadlines();
