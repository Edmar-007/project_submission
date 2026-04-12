<?php
if (defined('FILE_BACKEND_PARTIALS_HEADER_PHP_LOADED')) { return; }
define('FILE_BACKEND_PARTIALS_HEADER_PHP_LOADED', true);

$flashes = get_flashes();
$role = current_role();
$user = current_user();
$currentPath = $_SERVER['PHP_SELF'] ?? '';
$nav = [
    'admin' => [
        'Dashboard' => 'admin/dashboard.php',
        'Sections' => 'admin/sections.php',
        'Academic' => 'admin/academic.php',
        'Students' => 'admin/students.php',
        'Teachers' => 'admin/teachers.php',
        'Subjects' => 'admin/subjects.php',
        'Submissions' => 'admin/submissions.php',
        'Requests' => 'admin/requests.php',
        'Reports' => 'admin/reports.php',
        'Notifications' => 'admin/notifications.php',
        'Bulk Move' => 'admin/bulk_move.php',
        'Audit Logs' => 'admin/audit_logs.php',
        'System Tools' => 'admin/system_tools.php',
        'Settings' => 'admin/settings.php',
    ],
    'teacher' => [
        'Dashboard' => 'teacher/dashboard.php',
        'Students' => 'teacher/students.php',
        'Subjects' => 'teacher/subjects.php',
        'Submissions' => 'teacher/submissions.php',
        'Notifications' => 'teacher/notifications.php',
        'Profile' => 'teacher/profile.php',
    ],
    'student' => [
        'Dashboard' => 'student/dashboard.php',
        'Subjects' => 'student/subjects.php',
        'Submit' => 'student/submit.php',
        'My Submissions' => 'student/my_submissions.php',
        'Notifications' => 'student/notifications.php',
        'Profile' => 'student/profile.php',
    ],
];
$navIcons = [
    'Dashboard' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 4h7v7H4zM13 4h7v4h-7zM13 10h7v10h-7zM4 13h7v7H4z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/></svg>',
    'Sections' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 5h14v14H5zM9 5v14M5 9h14" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>',
    'Academic' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 9l9-5 9 5-9 5-9-5zm3 2.5v4.5c0 1.7 2.7 3 6 3s6-1.3 6-3v-4.5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>',
    'Students' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8zm13 10v-2a4 4 0 0 0-3-3.87M16 3.13A4 4 0 0 1 16 11" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>',
    'Teachers' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3 1 9l11 6 9-4.91V17h2V9L12 3zm0 13L5 12.18V17l7 4 7-4v-4.82L12 16z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>',
    'Subjects' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 4.5A2.5 2.5 0 0 1 8.5 2H20v18H8.5A2.5 2.5 0 0 0 6 22V4.5zM6 4.5V20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/><path d="M10 6h6M10 10h6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>',
    'Submissions' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16l4-3 4 3 4-3 4 3V8z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/><path d="M9 8h4M9 12h6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>',
    'Submit' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M7 10l5-5 5 5M12 5v12" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>',
    'My Submissions' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 3H5a2 2 0 0 0-2 2v14l4-2 4 2 4-2 4 2V9l-6-6H9z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/><path d="M13 3v6h6" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>',
    'Notifications' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M15 17h5l-1.4-1.4A2 2 0 0 1 18 14.2V11a6 6 0 0 0-5-5.9V4a1 1 0 1 0-2 0v1.1A6 6 0 0 0 6 11v3.2a2 2 0 0 1-.6 1.4L4 17h5m6 0a3 3 0 1 1-6 0" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>',
    'Profile' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20 21a8 8 0 1 0-16 0M12 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>',
    'Requests' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>',
    'Reports' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 19h16M7 16V8M12 16V4M17 16v-5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>',
    'Bulk Move' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 7H4v5M15 17h5v-5M20 12a8 8 0 0 0-13.66-5.66L4 8M4 12a8 8 0 0 0 13.66 5.66L20 16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>',
    'Audit Logs' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 8v5l3 3M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>',
    'System Tools' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 15.5A3.5 3.5 0 1 0 12 8.5a3.5 3.5 0 0 0 0 7zm7.4-3.5a7.4 7.4 0 0 0-.1-1l2.1-1.6-2-3.4-2.5 1a7.8 7.8 0 0 0-1.7-1l-.4-2.6H9.2l-.4 2.6a7.8 7.8 0 0 0-1.7 1l-2.5-1-2 3.4 2.1 1.6a7.4 7.4 0 0 0 0 2l-2.1 1.6 2 3.4 2.5-1a7.8 7.8 0 0 0 1.7 1l.4 2.6h5.6l.4-2.6a7.8 7.8 0 0 0 1.7-1l2.5 1 2-3.4-2.1-1.6c.1-.3.1-.7.1-1z" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>',
    'Settings' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 15.5A3.5 3.5 0 1 0 12 8.5a3.5 3.5 0 0 0 0 7zm8-3.5-2.1-1.2.2-2.4-2.4-1-1.4-1.9-2.3.8-2.3-.8-1.4 1.9-2.4 1 .2 2.4L4 12l1.2 2.1-.2 2.4 2.4 1 1.4 1.9 2.3-.8 2.3.8 1.4-1.9 2.4-1-.2-2.4z" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>',
];
$avatarInitial = strtoupper(substr(trim((string) ($user['full_name'] ?? $user['username'] ?? 'U')), 0, 1));
$avatarPath = trim((string) ($user['avatar_path'] ?? ''));
$avatarUrl = $avatarPath !== '' ? url(ltrim($avatarPath, '/')) : '';
$avatarStyle = $avatarUrl !== '' ? ' style="background-image:url(' . h($avatarUrl) . ')"' : '';
$avatarClasses = 'student-avatar';
$avatarSmallClasses = 'student-avatar student-avatar-small';
if ($avatarUrl !== '') {
    $avatarClasses .= ' has-image';
    $avatarSmallClasses .= ' has-image';
}
$unreadCount = 0;
$recentNotifications = [];
if ($role && $user) {
    $unreadCount = count_unread_notifications($role, (int) $user['id']);
    $recentNotifications = fetch_notifications($role, (int) $user['id'], 5);
}
$notificationPage = $role ? url($role . '/notifications.php') : '#';
$logoutPage = $role ? url($role . '/logout.php') : '#';
$dashboardPage = $role ? url($role . '/dashboard.php') : '#';
$profilePage = match ($role) {
    'admin' => url('admin/settings.php'),
    'teacher' => url('teacher/profile.php'),
    'student' => url('student/profile.php'),
    default => '#',
};
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= h(page_title($title ?? 'Dashboard')) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Figtree:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link rel="stylesheet" href="<?= h(asset_url('app.css')) ?>">
  <?php if ($role && $user): ?>
  <meta name="csrf-token" content="<?= h(csrf_token()) ?>">
  <meta name="ajax-mark-notif-read" content="<?= h(url('backend/ajax/mark_notification_read.php')) ?>">
  <?php endif; ?>
</head>
<body class="role-<?= h($role ?: 'guest') ?>">
<div class="app-shell">
<?php if ($role): ?>
  <aside class="sidebar sidebar-pro <?= $role === 'student' ? 'student-sidebar-shell' : '' ?>" data-sidebar>
    <div class="brand-block student-brand-block">
      <div class="brand-mark">PS</div>
      <div>
        <strong><?= h(APP_NAME) ?></strong>
        <div class="muted small sidebar-muted"><?= h(ucfirst($role)) ?> Portal</div>
      </div>
    </div>
    <nav class="side-nav <?= $role === 'student' ? 'student-side-nav-modern' : '' ?>">
      <?php foreach (($nav[$role] ?? []) as $label => $path): ?>
        <?php $isActive = str_contains($currentPath, basename($path)); ?>
        <a class="<?= $isActive ? 'active' : '' ?>" href="<?= h(url($path)) ?>" data-nav-label="<?= h($label) ?>" <?= $isActive ? 'aria-current="page"' : '' ?>>
          <span class="nav-icon"><?= $navIcons[$label] ?? $navIcons['Settings'] ?></span>
          <span><?= h($label) ?></span>
        </a>
      <?php endforeach; ?>
    </nav>
    <div class="sidebar-footer <?= $role === 'student' ? 'student-sidebar-profile' : '' ?>">
      <div class="student-sidebar-user">
        <div class="<?= h($avatarSmallClasses) ?>" data-student-avatar data-avatar-initial="<?= h($avatarInitial) ?>"<?= $avatarStyle ?>><?= h($avatarInitial) ?></div>
        <div>
          <div class="muted small sidebar-muted">Signed in as</div>
          <strong><?= h($user['full_name'] ?? $user['username'] ?? 'User') ?></strong>
        </div>
      </div>
      <div class="muted small sidebar-muted">Use the top-right menu to sign out.</div>
    </div>
  </aside>
<?php endif; ?>
<?php if ($role): ?><button class="sidebar-overlay" type="button" data-sidebar-overlay aria-label="Close navigation"></button><?php endif; ?>
<main class="main-content <?= $role ? '' : 'no-sidebar' ?>">
  <header class="topbar modern-topbar <?= $role === 'student' ? 'student-topbar-shell' : '' ?>">
    <div class="topbar-heading-wrap">
      <?php if ($role): ?>
        <button type="button" class="icon-btn sidebar-toggle-btn" data-sidebar-toggle aria-label="Toggle navigation" aria-expanded="true" title="Toggle navigation">
          <span class="sidebar-toggle-bars" aria-hidden="true"><span></span><span></span><span></span></span>
        </button>
      <?php endif; ?>
      <div>
      <h1><?= h($title ?? 'Dashboard') ?></h1>
      <?php if (!empty($subtitle)): ?><p class="muted"><?= h($subtitle) ?></p><?php endif; ?>
      </div>
    </div>
    <?php if ($role && $user): ?>
      <div class="topbar-meta modern-meta <?= $role === 'student' ? 'student-topbar-meta' : '' ?>">
        <div class="page-actions" data-page-actions></div>
        <div class="notification-menu">
          <button type="button" class="icon-btn topbar-alert-btn <?= str_contains($currentPath, 'notifications.php') ? 'is-current' : '' ?>" data-toggle-menu data-notification-toggle aria-expanded="false" aria-haspopup="true" aria-controls="topbar-notification-dropdown" aria-label="Toggle notifications<?= $unreadCount > 0 ? ' (' . (int) $unreadCount . ' unread)' : '' ?>" title="Notifications">
            <span class="topbar-alert-icon" aria-hidden="true">🔔</span>
            <?php if ($unreadCount > 0): ?><span class="badge-count"><?= (int) $unreadCount ?></span><?php endif; ?>
          </button>
          <div class="notification-dropdown" id="topbar-notification-dropdown" data-menu>
            <div class="dropdown-head notification-dropdown-head">
              <div>
                <strong>Notifications</strong>
                <div class="muted small"><?= $unreadCount > 0 ? (int) $unreadCount . ' unread' : 'Everything caught up' ?></div>
              </div>
              <a class="notification-view-all" href="<?= h($notificationPage) ?>">View all</a>
            </div>
            <?php if ($recentNotifications): ?>
              <div class="notification-preview-list">
              <?php foreach ($recentNotifications as $note): ?>
                <a class="notification-item notification-item-link <?= (int) $note['is_read'] === 0 ? 'unread' : '' ?>"
                   href="<?= h($notificationPage . '#notification-' . (int) $note['id']) ?>"
                   data-notif-id="<?= (int) $note['id'] ?>"
                   data-notif-read="<?= (int) $note['is_read'] ?>">
                  <div class="notification-title-row">
                    <strong><?= h($note['title']) ?></strong>
                    <?= status_badge($note['type']) ?>
                  </div>
                  <p><?= h($note['message']) ?></p>
                  <span class="muted small"><?= h($note['created_at']) ?></span>
                </a>
              <?php endforeach; ?>
              </div>
            <?php else: ?>
              <div class="notification-empty muted">No notifications yet.</div>
            <?php endif; ?>
          </div>
        </div>
        <div class="profile-menu dropdown-shell <?= $role === 'student' ? 'student-topbar-usercard' : '' ?>" data-dropdown-shell>
          <button type="button" class="profile-toggle" data-dropdown-toggle aria-expanded="false" aria-label="Open profile menu" title="Profile menu">
            <div class="profile-toggle-copy">
              <div class="pill soft"><?= h(ucfirst($role)) ?></div>
              <strong><?= h($user['username'] ?? $user['full_name'] ?? 'User') ?></strong>
            </div>
            <div class="<?= h($avatarClasses) ?>" data-student-avatar data-avatar-initial="<?= h($avatarInitial) ?>"<?= $avatarStyle ?>><?= h($avatarInitial) ?></div>
            <span class="profile-caret" aria-hidden="true">▾</span>
          </button>
          <div class="profile-dropdown" data-dropdown-menu>
            <div class="dropdown-head">
              <strong><?= h($user['full_name'] ?? $user['username'] ?? 'User') ?></strong>
              <span class="muted small"><?= h(ucfirst($role)) ?> portal</span>
            </div>
            <a href="<?= h($dashboardPage) ?>">Dashboard</a>
            <a href="<?= h($profilePage) ?>"><?= h($role === 'admin' ? 'Settings' : 'Profile') ?></a>
            <a href="<?= h($notificationPage) ?>">Notifications</a>
            <a class="profile-logout-link" href="<?= h($logoutPage) ?>">Logout</a>
          </div>
        </div>
      </div>
    <?php endif; ?>
  </header>

  <?php foreach ($flashes as $flash): ?>
    <div class="flash <?= h($flash['type']) ?>"><?= h($flash['message']) ?></div>
  <?php endforeach; ?>
