<?php
require_once __DIR__ . '/config/app.php';
requireLogin();

$type = $_GET['type'] ?? 'task';
$fileId = (int) ($_GET['id'] ?? 0);

if (!$fileId) {
    http_response_code(400);
    exit('Invalid file');
}

$user = currentUser();
$db = getDB();

if ($type === 'plan') {
    $stmt = $db->prepare('
        SELECT pf.*, sp.user_id as owner_id
        FROM plan_files pf
        JOIN plans sp ON pf.plan_id = sp.id
        WHERE pf.id = ? AND sp.is_deleted = 0
    ');
} else {
    $stmt = $db->prepare('
        SELECT tf.*, t.user_id as owner_id
        FROM task_files tf
        JOIN tasks t ON tf.task_id = t.id
        WHERE tf.id = ? AND t.is_deleted = 0
    ');
}

$stmt->execute([$fileId]);
$file = $stmt->fetch();

if (!$file) {
    http_response_code(404);
    exit('File not found');
}

if (!isSuperAdmin() && (int) $file['owner_id'] !== (int) $user['id']) {
    http_response_code(403);
    exit('Access denied');
}

$fullPath = UPLOAD_DIR . $file['file_path'];

if (!file_exists($fullPath) || !is_readable($fullPath)) {
    http_response_code(404);
    exit('File not found on server');
}

$mime = $file['mime_type'] ?: 'application/octet-stream';
$safeName = preg_replace('/[^\w\s.\-()]/u', '_', $file['original_name']);

header('Content-Type: ' . $mime);
header('Content-Disposition: attachment; filename="' . $safeName . '"');
header('Content-Length: ' . filesize($fullPath));
header('Cache-Control: private, no-cache');

readfile($fullPath);
exit;
