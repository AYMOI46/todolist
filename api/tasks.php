<?php
require_once __DIR__ . '/../config/app.php';
requireLogin();

header('Content-Type: application/json');
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
$db = getDB();
$user = currentUser();

switch ($action) {
    case 'list':
        $status = $_GET['status'] ?? '';
        $planId = $_GET['plan_id'] ?? '';
        $showDeleted = isSuperAdmin() && isset($_GET['show_deleted']);

        $sql = "SELECT t.*, u.full_name as user_name, p.title as plan_title,
                (SELECT COUNT(*) FROM task_files WHERE task_id = t.id) as file_count
                FROM tasks t
                LEFT JOIN users u ON t.user_id = u.id
                LEFT JOIN plans p ON t.plan_id = p.id
                WHERE 1=1";
        $params = [];

        if (!$showDeleted) {
            $sql .= ' AND t.is_deleted = 0';
        } elseif (isSuperAdmin()) {
            $sql .= ' AND t.is_deleted = 1';
        }

        if (!isSuperAdmin()) {
            $sql .= ' AND t.user_id = ?';
            $params[] = $user['id'];
        }

        if ($status) {
            $sql .= ' AND t.status = ?';
            $params[] = $status;
        }
        if ($planId) {
            $sql .= ' AND t.plan_id = ?';
            $params[] = $planId;
        }

        // Daily: board-ka kaliya maanta, reports-ka dhammaan
        if (!isset($_GET['include_all'])) {
            $sql .= dailyBoardFilter('t');
        }

        $sql .= ' ORDER BY t.updated_at DESC';
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        jsonResponse(['success' => true, 'tasks' => $stmt->fetchAll()]);
        break;

    case 'get':
        $id = (int) ($_GET['id'] ?? 0);
        if (!canAccessTask($id)) jsonResponse(['success' => false, 'message' => 'Access denied'], 403);

        $stmt = $db->prepare("
            SELECT t.*, u.full_name as user_name, p.title as plan_title
            FROM tasks t
            LEFT JOIN users u ON t.user_id = u.id
            LEFT JOIN plans p ON t.plan_id = p.id
            WHERE t.id = ?
        ");
        $stmt->execute([$id]);
        $task = $stmt->fetch();
        if (!$task) jsonResponse(['success' => false, 'message' => 'Task not found'], 404);

        $task['files'] = getTaskFiles($id);
        jsonResponse(['success' => true, 'task' => $task]);
        break;

    case 'create':
        if ($method !== 'POST') jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
        $data = json_decode(file_get_contents('php://input'), true);

        $title = trim($data['title'] ?? '');
        if (!$title) jsonResponse(['success' => false, 'message' => 'Title is required']);

        $stmt = $db->prepare('INSERT INTO tasks (plan_id, user_id, title, description, status, priority, week_number, task_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([
            !empty($data['plan_id']) ? (int) $data['plan_id'] : null,
            $user['id'],
            $title,
            $data['description'] ?? '',
            $data['status'] ?? 'all',
            $data['priority'] ?? 'medium',
            $data['week_number'] ?? getWeekNumber(),
            $data['task_date'] ?? date('Y-m-d'),
        ]);
        $taskId = $db->lastInsertId();
        logActivity($user['id'], 'create', 'task', $taskId, "Created task: $title");
        jsonResponse(['success' => true, 'message' => 'Task created', 'id' => $taskId]);
        break;

    case 'update':
        if ($method !== 'POST') jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
        $data = json_decode(file_get_contents('php://input'), true);
        $id = (int) ($data['id'] ?? 0);
        if (!canAccessTask($id)) jsonResponse(['success' => false, 'message' => 'Access denied'], 403);

        $fields = [];
        $params = [];
        $allowed = ['title', 'description', 'status', 'priority', 'week_number', 'task_date', 'plan_id', 'failure_reason'];

        foreach ($allowed as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "$field = ?";
                $params[] = $data[$field];
            }
        }

        if (isset($data['status']) && $data['status'] === 'done') {
            jsonResponse(['success' => false, 'message' => 'Upload file to mark as done', 'requires_upload' => true]);
        }
        if (isset($data['status']) && $data['status'] === 'failed' && empty($data['failure_reason'])) {
            jsonResponse(['success' => false, 'message' => 'Failure reason is required']);
        }

        if (isset($data['status'])) {
            $fields[] = 'task_date = CURDATE()';
        }

        if (empty($fields)) jsonResponse(['success' => false, 'message' => 'No fields to update']);

        $params[] = $id;
        $stmt = $db->prepare('UPDATE tasks SET ' . implode(', ', $fields) . ' WHERE id = ?');
        $stmt->execute($params);
        logActivity($user['id'], 'update', 'task', $id, 'Updated task');
        jsonResponse(['success' => true, 'message' => 'Task updated']);
        break;

    case 'move':
        if ($method !== 'POST') jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
        $data = json_decode(file_get_contents('php://input'), true);
        if (!is_array($data)) jsonResponse(['success' => false, 'message' => 'Invalid request'], 400);
        $id = (int) ($data['id'] ?? 0);
        $newStatus = $data['status'] ?? '';
        if (!canAccessTask($id)) jsonResponse(['success' => false, 'message' => 'Access denied'], 403);

        $validStatuses = ['all', 'weekly', 'daily', 'processing', 'done', 'failed'];
        if (!in_array($newStatus, $validStatuses)) {
            jsonResponse(['success' => false, 'message' => 'Invalid status']);
        }

        $stmt = $db->prepare('SELECT * FROM tasks WHERE id = ? AND is_deleted = 0');
        $stmt->execute([$id]);
        $item = $stmt->fetch();
        if (!$item) jsonResponse(['success' => false, 'message' => 'Task not found'], 404);

        if ($item['status'] === $newStatus) {
            jsonResponse(['success' => true, 'message' => 'Already in this status']);
        }

        if ($newStatus === 'failed' && empty($data['failure_reason'])) {
            jsonResponse(['success' => false, 'message' => 'Failure reason is required']);
        }

        if ($newStatus === 'done') {
            jsonResponse(['success' => false, 'message' => 'File upload required to mark as done', 'requires_upload' => true]);
        }

        // Failed → meel kale: duplicate, asalka Failed ha joogo
        if ($item['status'] === 'failed' && $newStatus !== 'failed') {
            $stmt = $db->prepare('INSERT INTO tasks (plan_id, user_id, title, description, status, priority, week_number, task_date, duplicated_from_id) VALUES (?, ?, ?, ?, ?, ?, ?, CURDATE(), ?)');
            $stmt->execute([
                $item['plan_id'], $item['user_id'], $item['title'], $item['description'],
                $newStatus, $item['priority'], getWeekNumber(), $id,
            ]);
            $newId = (int) $db->lastInsertId();
            logActivity($user['id'], 'duplicate', 'task', $newId, "Duplicated from failed task #$id to $newStatus");
            jsonResponse(['success' => true, 'message' => 'Copy created — original stays in Failed', 'id' => $newId, 'duplicated' => true]);
        }

        $failureReason = $newStatus === 'failed' ? ', failure_reason = ?, task_date = CURDATE()' : ', failure_reason = NULL, task_date = CURDATE()';
        $params = [$newStatus];
        if ($newStatus === 'failed') $params[] = $data['failure_reason'];
        $params[] = $id;

        $stmt = $db->prepare("UPDATE tasks SET status = ? {$failureReason} WHERE id = ?");
        $stmt->execute($params);
        logActivity($user['id'], 'move', 'task', $id, "Moved task to $newStatus");
        jsonResponse(['success' => true, 'message' => 'Task moved']);
        break;

    case 'duplicate':
        if ($method !== 'POST') jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
        $data = json_decode(file_get_contents('php://input'), true);
        $id = (int) ($data['id'] ?? 0);
        $targetStatus = $data['status'] ?? 'weekly';
        if (!canAccessTask($id)) jsonResponse(['success' => false, 'message' => 'Access denied'], 403);

        $stmt = $db->prepare('SELECT * FROM tasks WHERE id = ? AND is_deleted = 0');
        $stmt->execute([$id]);
        $item = $stmt->fetch();
        if (!$item || $item['status'] !== 'failed') {
            jsonResponse(['success' => false, 'message' => 'Only failed tasks can be retried']);
        }

        $stmt = $db->prepare('INSERT INTO tasks (plan_id, user_id, title, description, status, priority, week_number, task_date, duplicated_from_id) VALUES (?, ?, ?, ?, ?, ?, ?, CURDATE(), ?)');
        $stmt->execute([
            $item['plan_id'], $item['user_id'], $item['title'], $item['description'],
            $targetStatus, $item['priority'], getWeekNumber(), $id,
        ]);
        $newId = (int) $db->lastInsertId();
        logActivity($user['id'], 'duplicate', 'task', $newId, "Retried failed task #$id");
        jsonResponse(['success' => true, 'message' => 'New copy created in ' . $targetStatus, 'id' => $newId]);
        break;

    case 'complete':
        if ($method !== 'POST') jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
        $id = (int) ($_POST['task_id'] ?? 0);
        if (!canAccessTask($id)) jsonResponse(['success' => false, 'message' => 'Access denied'], 403);
        if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            jsonResponse(['success' => false, 'message' => 'File is required to complete task']);
        }
        $upload = saveUploadedFile($_FILES['file']);
        if (!$upload['success']) jsonResponse($upload, 400);

        $db->beginTransaction();
        try {
            $stmt = $db->prepare('INSERT INTO task_files (task_id, user_id, original_name, file_path, file_size, mime_type) VALUES (?, ?, ?, ?, ?, ?)');
            $stmt->execute([$id, $user['id'], $_FILES['file']['name'], $upload['filename'], $_FILES['file']['size'], $upload['mime_type']]);
            $stmt = $db->prepare('UPDATE tasks SET status = ?, completed_at = NOW(), task_date = CURDATE() WHERE id = ?');
            $stmt->execute(['done', $id]);
            $db->commit();
            logActivity($user['id'], 'complete', 'task', $id, 'Task completed with file');
            jsonResponse(['success' => true, 'message' => 'Task completed']);
        } catch (Throwable $e) {
            $db->rollBack();
            jsonResponse(['success' => false, 'message' => 'Failed to complete task'], 500);
        }
        break;

    case 'delete':
        if ($method !== 'POST') jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
        $data = json_decode(file_get_contents('php://input'), true);
        $id = (int) ($data['id'] ?? 0);
        if (!canAccessTask($id)) jsonResponse(['success' => false, 'message' => 'Access denied'], 403);

        $stmt = $db->prepare('UPDATE tasks SET is_deleted = 1 WHERE id = ?');
        $stmt->execute([$id]);
        logActivity($user['id'], 'delete', 'task', $id, 'Soft deleted task');
        jsonResponse(['success' => true, 'message' => 'Task deleted']);
        break;

    case 'restore':
        if (!isSuperAdmin()) jsonResponse(['success' => false, 'message' => 'Access denied'], 403);
        if ($method !== 'POST') jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
        $data = json_decode(file_get_contents('php://input'), true);
        $id = (int) ($data['id'] ?? 0);

        $stmt = $db->prepare('UPDATE tasks SET is_deleted = 0 WHERE id = ?');
        $stmt->execute([$id]);
        logActivity($user['id'], 'restore', 'task', $id, 'Restored task');
        jsonResponse(['success' => true, 'message' => 'Task restored']);
        break;

    default:
        jsonResponse(['success' => false, 'message' => 'Invalid action'], 400);
}
