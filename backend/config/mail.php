<?php
if (defined('BACKEND_CONFIG_MAIL_PHP_LOADED')) { return; }
define('BACKEND_CONFIG_MAIL_PHP_LOADED', true);

define('MAIL_ENABLED', getenv('MAIL_ENABLED') ?: '0');
define('MAIL_HOST', getenv('MAIL_HOST') ?: 'smtp.gmail.com');
define('MAIL_PORT', getenv('MAIL_PORT') ?: '587');
define('MAIL_USERNAME', getenv('MAIL_USERNAME') ?: '');
define('MAIL_PASSWORD', getenv('MAIL_PASSWORD') ?: '');
define('MAIL_FROM', getenv('MAIL_FROM') ?: 'no-reply@example.com');
define('MAIL_FROM_NAME', getenv('MAIL_FROM_NAME') ?: (defined('APP_NAME') ? APP_NAME : 'Project Submission Management System'));
define('MAIL_SECURE', getenv('MAIL_SECURE') ?: 'tls');
