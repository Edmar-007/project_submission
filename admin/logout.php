<?php
if (defined('FILE_ADMIN_LOGOUT_PHP_LOADED')) { return; }
define('FILE_ADMIN_LOGOUT_PHP_LOADED', true);

require_once __DIR__ . '/../backend/config/app.php';
$user = current_user();
if ($user) { log_action('admin', (int) $user['id'], 'logout', 'admin', (int) $user['id'], 'User logged out'); }
logout_user();
set_flash('success', 'Logged out successfully.');
redirect_to('admin/login.php');
