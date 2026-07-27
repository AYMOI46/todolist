<?php
require_once __DIR__ . '/../config/app.php';
requireLogin();

header('Content-Type: application/json');
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
$user = currentUser();

if ($action === 'upload' && $method === 'POST') {
    $taskId = (int) ($_POST['task_id'] ?? 0);
    if (!canAccessTask($taskId)) jsonResponse(['success' => false, 'message' => 'Access denied'], 403);

    $stmt = getDB()->prepare('SELECT status FROM tasks WHERE id = ? AND is_deleted = 0');
    $stmt->execute([$taskId]);
    $task = $stmt->fetch();
    if (!$task) jsonResponse(['success' => false, 'message' => 'Task not found'], 404);

    if ($task['status'] !== 'done') {
        jsonResponse(['success' => false, 'message' => 'Files can only be uploaded to completed tasks']);
    }

    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        jsonResponse(['success' => false, 'message' => 'No file uploaded or upload error']);
    }

    $file = $_FILES['file'];
    if ($file['size'] > MAX_FILE_SIZE) {
        jsonResponse(['success' => false, 'message' => 'File too large (max 10MB)']);
    }

    $allowedTypes = [
        'image/jpeg', 'image/png', 'image/gif', 'image/webp',
        'application/pdf', 'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'text/plain', 'application/zip',
    ];

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mimeType, $allowedTypes)) {
        jsonResponse(['success' => false, 'message' => 'File type not allowed']);
    }

    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid('task_') . '_' . time() . '.' . $ext;
    $filepath = UPLOAD_DIR . $filename;

    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        jsonResponse(['success' => false, 'message' => 'Failed to save file']);
    }

    $stmt = getDB()->prepare('INSERT INTO task_files (task_id, user_id, original_name, file_path, file_size, mime_type) VALUES (?, ?, ?, ?, ?, ?)');
    $stmt->execute([$taskId, $user['id'], $file['name'], $filename, $file['size'], $mimeType]);
    $fileId = getDB()->lastInsertId();

    logActivity($user['id'], 'upload', 'task_file', $fileId, "Uploaded file: {$file['name']}");
    jsonResponse(['success' => true, 'message' => 'File uploaded', 'file_id' => $fileId]);
}

if ($action === 'delete' && $method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $fileId = (int) ($data['file_id'] ?? 0);

    $stmt = getDB()->prepare('SELECT tf.*, t.user_id as task_user_id FROM task_files tf JOIN tasks t ON tf.task_id = t.id WHERE tf.id = ?');
    $stmt->execute([$fileId]);
    $file = $stmt->fetch();

    if (!$file) jsonResponse(['success' => false, 'message' => 'File not found'], 404);
    if (!isSuperAdmin() && $file['task_user_id'] != $user['id']) {
        jsonResponse(['success' => false, 'message' => 'Access denied'], 403);
    }

    $fullPath = UPLOAD_DIR . $file['file_path'];
    if (file_exists($fullPath)) unlink($fullPath);

    $stmt = getDB()->prepare('DELETE FROM task_files WHERE id = ?');
    $stmt->execute([$fileId]);
    logActivity($user['id'], 'delete', 'task_file', $fileId, 'Deleted file');
    jsonResponse(['success' => true, 'message' => 'File deleted']);
}

if ($action === 'list') {
    $taskId = (int) ($_GET['task_id'] ?? 0);
    if (!canAccessTask($taskId)) jsonResponse(['success' => false, 'message' => 'Access denied'], 403);
    jsonResponse(['success' => true, 'files' => getTaskFiles($taskId)]);
}

jsonResponse(['success' => false, 'message' => 'Invalid action'], 400);
