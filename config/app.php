<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

define('APP_NAME', 'TaskFlow Pro');
require_once __DIR__ . '/paths.php';
define('APP_URL', detectAppUrl());
define('UPLOAD_DIR', __DIR__ . '/../uploads/');
define('MAX_FILE_SIZE', 10 * 1024 * 1024); // 10MB

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

ensureSchema();

if (!is_dir(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0755, true);
}

// Suppress HTML error output on API requests (errors still logged)
if (str_contains($_SERVER['REQUEST_URI'] ?? '', '/api/')) {
    ini_set('display_errors', '0');
    ob_start();
}
