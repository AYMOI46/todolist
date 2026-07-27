<?php
require_once __DIR__ . '/../config/app.php';
requireLogin();

header('Content-Type: application/json');

try {
    handlePlansApi();
} catch (Throwable $e) {
    jsonResponse(['success' => false, 'message' => 'Server error: ' . $e->getMessage()], 500);
}

function handlePlansApi(): void {
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
$db = getDB();
$user = currentUser();

function planBaseQuery(): string {
    return 'SELECT p.*, u.full_name as user_name,
            pp.title as parent_title,
            (SELECT COUNT(*) FROM tasks WHERE plan_id = p.id AND is_deleted = 0) as task_count,
            (SELECT COUNT(*) FROM plans sp WHERE sp.parent_id = p.id AND sp.is_deleted = 0) as subplan_count,
            (SELECT COUNT(*) FROM plan_files WHERE plan_id = p.id) as file_count
            FROM plans p
            LEFT JOIN users u ON p.user_id = u.id
            LEFT JOIN plans pp ON p.parent_id = pp.id';
}

function applyPlanFilters(string &$sql, array &$params, bool $showDeleted, ?string $parentFilter = null): void {
    if (!$showDeleted) {
        $sql .= ' AND p.is_deleted = 0';
    } elseif (isSuperAdmin()) {
        $sql .= ' AND p.is_deleted = 1';
    }

    if (!isSuperAdmin()) {
        $sql .= ' AND p.user_id = ?';
        $params[] = currentUser()['id'];
    }

    if ($parentFilter === 'main') {
        $sql .= ' AND p.parent_id IS NULL';
    } elseif ($parentFilter === 'sub') {
        $sql .= ' AND p.parent_id IS NOT NULL';
    }
}

switch ($action) {
    case 'list':
        $showDeleted = isSuperAdmin() && isset($_GET['show_deleted']);
        $type = $_GET['type'] ?? 'main';

        $sql = planBaseQuery() . ' WHERE 1=1';
        $params = [];
        applyPlanFilters($sql, $params, $showDeleted, $type === 'sub' ? 'sub' : ($type === 'all' ? null : 'main'));
        $sql .= ' ORDER BY p.parent_id IS NULL DESC, p.updated_at DESC';

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $plans = $stmt->fetchAll();

        if ($type === 'main' || $type === 'hierarchy') {
            $subSql = planBaseQuery() . ' WHERE p.parent_id IS NOT NULL';
            $subParams = [];
            applyPlanFilters($subSql, $subParams, false, null);
            $subSql .= ' ORDER BY p.updated_at DESC';
            $subStmt = $db->prepare($subSql);
            $subStmt->execute($subParams);
            $subPlans = $subStmt->fetchAll();

            $grouped = [];
            foreach ($subPlans as $sub) {
                $grouped[$sub['parent_id']][] = $sub;
            }
            foreach ($plans as &$plan) {
                $plan['sub_plans'] = $grouped[$plan['id']] ?? [];
            }
        }

        jsonResponse(['success' => true, 'plans' => $plans]);
        break;

    case 'board':
        $sql = planBaseQuery() . ' WHERE p.parent_id IS NOT NULL';
        $params = [];
        applyPlanFilters($sql, $params, false, null);

        $parentId = $_GET['parent_id'] ?? '';
        if ($parentId) {
            $sql .= ' AND p.parent_id = ?';
            $params[] = $parentId;
        }

        $status = $_GET['status'] ?? '';
        if ($status) {
            $sql .= ' AND p.status = ?';
            $params[] = $status;
        }

        if (!isset($_GET['include_all'])) {
            $sql .= dailyBoardFilter('p');
        }

        $sql .= ' ORDER BY p.updated_at DESC';
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        jsonResponse(['success' => true, 'sub_plans' => $stmt->fetchAll()]);
        break;

    case 'main_board':
        $sql = planBaseQuery() . ' WHERE p.parent_id IS NULL';
        $params = [];
        applyPlanFilters($sql, $params, false, 'main');
        $sql .= ' ORDER BY p.updated_at DESC';
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        jsonResponse(['success' => true, 'plans' => $stmt->fetchAll()]);
        break;

    case 'get':
        $id = (int) ($_GET['id'] ?? 0);
        if (!canAccessPlan($id)) jsonResponse(['success' => false, 'message' => 'Access denied'], 403);

        $stmt = $db->prepare(planBaseQuery() . ' WHERE p.id = ?');
        $stmt->execute([$id]);
        $plan = $stmt->fetch();
        if (!$plan) jsonResponse(['success' => false, 'message' => 'Plan not found'], 404);

        if (!$plan['parent_id']) {
            $subStmt = $db->prepare(planBaseQuery() . ' WHERE p.parent_id = ? AND p.is_deleted = 0 ORDER BY p.updated_at DESC');
            $subStmt->execute([$id]);
            $plan['sub_plans'] = $subStmt->fetchAll();
        }

        jsonResponse(['success' => true, 'plan' => $plan]);
        break;

    case 'create':
        if ($method !== 'POST') jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
        $data = json_decode(file_get_contents('php://input'), true);
        if (!is_array($data)) jsonResponse(['success' => false, 'message' => 'Invalid request data'], 400);

        $title = trim($data['title'] ?? '');
        if (!$title) jsonResponse(['success' => false, 'message' => 'Title is required']);

        $parentId = !empty($data['parent_id']) ? (int) $data['parent_id'] : null;
        if ($parentId && !canAccessPlan($parentId)) {
            jsonResponse(['success' => false, 'message' => 'Invalid parent plan'], 403);
        }

        $stmt = $db->prepare('INSERT INTO plans (user_id, parent_id, title, description, start_date, end_date, status, priority, task_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, CURDATE())');
        $stmt->execute([
            $user['id'],
            $parentId,
            $title,
            $data['description'] ?? '',
            !empty($data['start_date']) ? $data['start_date'] : null,
            !empty($data['end_date']) ? $data['end_date'] : null,
            $data['status'] ?? 'all',
            $data['priority'] ?? 'medium',
        ]);
        $planId = $db->lastInsertId();
        $type = $parentId ? 'sub-plan' : 'plan';
        logActivity($user['id'], 'create', 'plan', $planId, "Created $type: $title");
        jsonResponse(['success' => true, 'message' => ucfirst($type) . ' created', 'id' => $planId]);
        break;

    case 'update':
        if ($method !== 'POST') jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
        $data = json_decode(file_get_contents('php://input'), true);
        if (!is_array($data)) jsonResponse(['success' => false, 'message' => 'Invalid request data'], 400);
        $id = (int) ($data['id'] ?? 0);
        if (!canAccessPlan($id)) jsonResponse(['success' => false, 'message' => 'Access denied'], 403);

        $check = $db->prepare('SELECT parent_id FROM plans WHERE id = ?');
        $check->execute([$id]);
        $existing = $check->fetch();

        if ($existing && $existing['parent_id']) {
            if (isset($data['status']) && $data['status'] === 'done') {
                jsonResponse(['success' => false, 'message' => 'Upload file to mark as done', 'requires_upload' => true]);
            }
            $stmt = $db->prepare('UPDATE plans SET title = ?, description = ?, status = ?, priority = ?, failure_reason = ?, task_date = CURDATE() WHERE id = ?');
            $stmt->execute([
                $data['title'],
                $data['description'] ?? '',
                $data['status'] ?? 'all',
                $data['priority'] ?? 'medium',
                $data['failure_reason'] ?? null,
                $id,
            ]);
        } else {
            $stmt = $db->prepare('UPDATE plans SET title = ?, description = ?, start_date = ?, end_date = ? WHERE id = ?');
            $stmt->execute([
                $data['title'],
                $data['description'] ?? '',
                !empty($data['start_date']) ? $data['start_date'] : null,
                !empty($data['end_date']) ? $data['end_date'] : null,
                $id,
            ]);
        }

        logActivity($user['id'], 'update', 'plan', $id, 'Updated plan');
        jsonResponse(['success' => true, 'message' => 'Plan updated']);
        break;

    case 'move':
        if ($method !== 'POST') jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
        $data = json_decode(file_get_contents('php://input'), true);
        if (!is_array($data)) jsonResponse(['success' => false, 'message' => 'Invalid request'], 400);
        $id = (int) ($data['id'] ?? 0);
        if (!canAccessPlan($id)) jsonResponse(['success' => false, 'message' => 'Access denied'], 403);

        $stmt = $db->prepare('SELECT * FROM plans WHERE id = ? AND is_deleted = 0');
        $stmt->execute([$id]);
        $item = $stmt->fetch();
        if (!$item || !$item['parent_id']) {
            jsonResponse(['success' => false, 'message' => 'Only sub-plans can be moved on the board']);
        }

        $newStatus = $data['status'] ?? '';
        $validStatuses = ['all', 'weekly', 'daily', 'processing', 'done', 'failed'];
        if (!in_array($newStatus, $validStatuses)) {
            jsonResponse(['success' => false, 'message' => 'Invalid status']);
        }

        if ($item['status'] === $newStatus) {
            jsonResponse(['success' => true, 'message' => 'Already in this status']);
        }

        if ($newStatus === 'failed' && empty($data['failure_reason'])) {
            jsonResponse(['success' => false, 'message' => 'Failure reason is required']);
        }

        if ($newStatus === 'done') {
            jsonResponse(['success' => false, 'message' => 'File upload required to mark as done', 'requires_upload' => true]);
        }

        // Failed → duplicate, asalka Failed ha joogo
        if ($item['status'] === 'failed' && $newStatus !== 'failed') {
            $stmt = $db->prepare('INSERT INTO plans (user_id, parent_id, title, description, status, priority, task_date, duplicated_from_id) VALUES (?, ?, ?, ?, ?, ?, CURDATE(), ?)');
            $stmt->execute([
                $item['user_id'], $item['parent_id'], $item['title'], $item['description'],
                $newStatus, $item['priority'], $id,
            ]);
            $newId = (int) $db->lastInsertId();
            logActivity($user['id'], 'duplicate', 'plan', $newId, "Duplicated failed sub-plan #$id to $newStatus");
            jsonResponse(['success' => true, 'message' => 'Copy created — original stays in Failed', 'id' => $newId, 'duplicated' => true]);
        }

        $failureReason = $newStatus === 'failed' ? ', failure_reason = ?, task_date = CURDATE()' : ', failure_reason = NULL, task_date = CURDATE()';
        $params = [$newStatus];
        if ($newStatus === 'failed') $params[] = $data['failure_reason'];
        $params[] = $id;

        $stmt = $db->prepare("UPDATE plans SET status = ? {$failureReason} WHERE id = ?");
        $stmt->execute($params);
        logActivity($user['id'], 'move', 'plan', $id, "Moved sub-plan to $newStatus");
        jsonResponse(['success' => true, 'message' => 'Sub-plan moved']);
        break;

    case 'duplicate':
        if ($method !== 'POST') jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
        $data = json_decode(file_get_contents('php://input'), true);
        $id = (int) ($data['id'] ?? 0);
        $targetStatus = $data['status'] ?? 'weekly';
        if (!canAccessPlan($id)) jsonResponse(['success' => false, 'message' => 'Access denied'], 403);

        $stmt = $db->prepare('SELECT * FROM plans WHERE id = ? AND is_deleted = 0 AND parent_id IS NOT NULL');
        $stmt->execute([$id]);
        $item = $stmt->fetch();
        if (!$item || $item['status'] !== 'failed') {
            jsonResponse(['success' => false, 'message' => 'Only failed sub-plans can be retried']);
        }

        $stmt = $db->prepare('INSERT INTO plans (user_id, parent_id, title, description, status, priority, task_date, duplicated_from_id) VALUES (?, ?, ?, ?, ?, ?, CURDATE(), ?)');
        $stmt->execute([
            $item['user_id'], $item['parent_id'], $item['title'], $item['description'],
            $targetStatus, $item['priority'], $id,
        ]);
        $newId = (int) $db->lastInsertId();
        logActivity($user['id'], 'duplicate', 'plan', $newId, "Retried failed sub-plan #$id");
        jsonResponse(['success' => true, 'message' => 'New copy created in ' . $targetStatus, 'id' => $newId]);
        break;

    case 'complete':
        if ($method !== 'POST') jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
        $id = (int) ($_POST['plan_id'] ?? 0);
        if (!canAccessPlan($id)) jsonResponse(['success' => false, 'message' => 'Access denied'], 403);
        if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            jsonResponse(['success' => false, 'message' => 'File is required to complete sub-plan']);
        }
        $upload = saveUploadedFile($_FILES['file']);
        if (!$upload['success']) jsonResponse($upload, 400);

        $db->beginTransaction();
        try {
            $stmt = $db->prepare('INSERT INTO plan_files (plan_id, user_id, original_name, file_path, file_size, mime_type) VALUES (?, ?, ?, ?, ?, ?)');
            $stmt->execute([$id, $user['id'], $_FILES['file']['name'], $upload['filename'], $_FILES['file']['size'], $upload['mime_type']]);
            $stmt = $db->prepare('UPDATE plans SET status = ?, completed_at = NOW(), task_date = CURDATE() WHERE id = ?');
            $stmt->execute(['done', $id]);
            $db->commit();
            logActivity($user['id'], 'complete', 'plan', $id, 'Sub-plan completed with file');
            jsonResponse(['success' => true, 'message' => 'Sub-plan completed']);
        } catch (Throwable $e) {
            $db->rollBack();
            jsonResponse(['success' => false, 'message' => 'Failed to complete sub-plan'], 500);
        }
        break;

    case 'delete':
        if ($method !== 'POST') jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
        $data = json_decode(file_get_contents('php://input'), true);
        $id = (int) ($data['id'] ?? 0);
        if (!canAccessPlan($id)) jsonResponse(['success' => false, 'message' => 'Access denied'], 403);

        $stmt = $db->prepare('UPDATE plans SET is_deleted = 1 WHERE id = ?');
        $stmt->execute([$id]);
        logActivity($user['id'], 'delete', 'plan', $id, 'Soft deleted plan');
        jsonResponse(['success' => true, 'message' => 'Plan deleted']);
        break;

    case 'restore':
        if (!isSuperAdmin()) jsonResponse(['success' => false, 'message' => 'Access denied'], 403);
        if ($method !== 'POST') jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
        $data = json_decode(file_get_contents('php://input'), true);
        $id = (int) ($data['id'] ?? 0);

        $stmt = $db->prepare('UPDATE plans SET is_deleted = 0 WHERE id = ?');
        $stmt->execute([$id]);
        logActivity($user['id'], 'restore', 'plan', $id, 'Restored plan');
        jsonResponse(['success' => true, 'message' => 'Plan restored']);
        break;

    default:
        jsonResponse(['success' => false, 'message' => 'Invalid action'], 400);
}
}
