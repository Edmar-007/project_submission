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
