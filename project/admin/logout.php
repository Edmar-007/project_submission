<?php
require_once __DIR__ . '/../backend/config/auth.php';
$user = current_user();
if ($user) { log_action('admin', (int) $user['id'], 'logout', 'admin', (int) $user['id'], 'User logged out'); }
logout_user();
set_flash('success', 'Logged out successfully.');
redirect_to('admin/login.php');
