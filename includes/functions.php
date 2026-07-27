<?php

function ensureSchema(): void {
    static $done = false;
    if ($done) return;

    try {
        $pdo = getDB();
        $existing = $pdo->query('SHOW COLUMNS FROM plans')->fetchAll(PDO::FETCH_COLUMN);

        $columns = [
            'parent_id' => "ALTER TABLE plans ADD COLUMN parent_id INT NULL AFTER user_id",
            'status' => "ALTER TABLE plans ADD COLUMN status ENUM('weekly', 'daily', 'processing', 'done', 'failed') DEFAULT 'weekly' AFTER end_date",
            'priority' => "ALTER TABLE plans ADD COLUMN priority ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium' AFTER status",
            'failure_reason' => "ALTER TABLE plans ADD COLUMN failure_reason TEXT NULL AFTER priority",
            'completed_at' => "ALTER TABLE plans ADD COLUMN completed_at TIMESTAMP NULL AFTER failure_reason",
        ];

        foreach ($columns as $col => $sql) {
            if (!in_array($col, $existing, true)) {
                $pdo->exec($sql);
            }
        }

        // Add 'all' status to ENUM
        $pdo->exec("ALTER TABLE tasks MODIFY status ENUM('all', 'weekly', 'daily', 'processing', 'done', 'failed') NOT NULL DEFAULT 'all'");
        $pdo->exec("ALTER TABLE plans MODIFY status ENUM('all', 'weekly', 'daily', 'processing', 'done', 'failed') NOT NULL DEFAULT 'all'");

        $taskCols = $pdo->query('SHOW COLUMNS FROM tasks')->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('duplicated_from_id', $taskCols, true)) {
            $pdo->exec('ALTER TABLE tasks ADD COLUMN duplicated_from_id INT NULL AFTER failure_reason');
        }

        $planCols = $pdo->query('SHOW COLUMNS FROM plans')->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('task_date', $planCols, true)) {
            $pdo->exec('ALTER TABLE plans ADD COLUMN task_date DATE NULL AFTER completed_at');
        }
        if (!in_array('duplicated_from_id', $planCols, true)) {
            $pdo->exec('ALTER TABLE plans ADD COLUMN duplicated_from_id INT NULL AFTER task_date');
        }

        $pdo->exec("CREATE TABLE IF NOT EXISTS plan_files (
            id INT AUTO_INCREMENT PRIMARY KEY,
            plan_id INT NOT NULL,
            user_id INT NOT NULL,
            original_name VARCHAR(255) NOT NULL,
            file_path VARCHAR(500) NOT NULL,
            file_size INT,
            mime_type VARCHAR(100),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (plan_id) REFERENCES plans(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id)
        )");

        $done = true;
    } catch (Throwable $e) {
        // Database may not exist yet — install.php handles initial setup
    }
}

function logActivity(int $userId, string $action, string $entityType, ?int $entityId, string $details = ''): void {
    $stmt = getDB()->prepare(
        'INSERT INTO activity_logs (user_id, action, entity_type, entity_id, details, ip_address) VALUES (?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([$userId, $action, $entityType, $entityId, $details, $_SERVER['REMOTE_ADDR'] ?? '']);
}

function jsonResponse(array $data, int $code = 200): void {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}

function sanitize(string $input): string {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

function getStatuses(): array {
    return ['all', 'weekly', 'daily', 'processing', 'done', 'failed'];
}

function getStatusLabel(string $status): string {
    $labels = [
        'all' => 'All',
        'weekly' => 'Weekly',
        'daily' => 'Daily',
        'processing' => 'Processing',
        'done' => 'Done',
        'failed' => 'Failed',
    ];
    return $labels[$status] ?? $status;
}

function getStatusColor(string $status): string {
    $colors = [
        'all' => '#64748b',
        'weekly' => '#6366f1',
        'daily' => '#8b5cf6',
        'processing' => '#f59e0b',
        'done' => '#10b981',
        'failed' => '#ef4444',
    ];
    return $colors[$status] ?? '#64748b';
}

function getPriorityLabel(string $priority): string {
    return ucfirst($priority);
}

function formatDate(?string $date): string {
    if (!$date) return '-';
    return date('M d, Y', strtotime($date));
}

function formatDateTime(?string $datetime): string {
    if (!$datetime) return '-';
    return date('M d, Y H:i', strtotime($datetime));
}

function getWeekNumber(?string $date = null): int {
    $date = $date ? strtotime($date) : time();
    return (int) date('W', $date);
}

function getDashboardStats(?int $userId = null): array {
    $db = getDB();
    $deletedFilter = ' AND is_deleted = 0';
    $userFilter = '';
    $params = [];

    if ($userId && !isSuperAdmin()) {
        $userFilter = ' AND user_id = ?';
        $params[] = $userId;
    } elseif (!isSuperAdmin()) {
        $userFilter = ' AND user_id = ?';
        $params[] = currentUser()['id'];
    }

    $statuses = getStatuses();
    $stats = [];

    foreach ($statuses as $status) {
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM tasks WHERE status = ? {$deletedFilter} {$userFilter}");
        $stmt->execute(array_merge([$status], $params));
        $stats[$status] = (int) $stmt->fetch()['count'];
    }

    $stmt = $db->prepare("SELECT COUNT(*) as count FROM plans WHERE 1=1 {$deletedFilter} {$userFilter}");
    $stmt->execute($params);
    $stats['total_plans'] = (int) $stmt->fetch()['count'];

    $stmt = $db->prepare("SELECT COUNT(*) as count FROM tasks WHERE 1=1 {$deletedFilter} {$userFilter}");
    $stmt->execute($params);
    $stats['total_tasks'] = (int) $stmt->fetch()['count'];

    if (isSuperAdmin()) {
        $stmt = $db->query("SELECT COUNT(*) as count FROM users WHERE is_deleted = 0");
        $stats['total_users'] = (int) $stmt->fetch()['count'];

        $stmt = $db->query("SELECT COUNT(*) as count FROM tasks WHERE is_deleted = 1");
        $stats['deleted_tasks'] = (int) $stmt->fetch()['count'];
    }

    return $stats;
}

function getRecentTasks(int $limit = 10, ?int $userId = null): array {
    $db = getDB();
    $deletedFilter = ' AND t.is_deleted = 0';
    $userFilter = '';
    $params = [];

    if ($userId && !isSuperAdmin()) {
        $userFilter = ' AND t.user_id = ?';
        $params[] = $userId;
    } elseif (!isSuperAdmin()) {
        $userFilter = ' AND t.user_id = ?';
        $params[] = currentUser()['id'];
    }

    $params[] = $limit;
    $stmt = $db->prepare("
        SELECT t.*, u.full_name as user_name, p.title as plan_title
        FROM tasks t
        LEFT JOIN users u ON t.user_id = u.id
        LEFT JOIN plans p ON t.plan_id = p.id
        WHERE 1=1 {$deletedFilter} {$userFilter}
        ORDER BY t.updated_at DESC
        LIMIT ?
    ");
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function getTaskFiles(int $taskId): array {
    $stmt = getDB()->prepare('SELECT * FROM task_files WHERE task_id = ? ORDER BY created_at DESC');
    $stmt->execute([$taskId]);
    return $stmt->fetchAll();
}

function getPlanFiles(int $planId): array {
    $stmt = getDB()->prepare('SELECT * FROM plan_files WHERE plan_id = ? ORDER BY created_at DESC');
    $stmt->execute([$planId]);
    return $stmt->fetchAll();
}

/** Daily items only show on board for today's date */
function dailyBoardFilter(string $alias): string {
    return " AND ({$alias}.status != 'daily' OR {$alias}.task_date = CURDATE())";
}

function fileDownloadUrl(string $type, int $fileId): string {
    return APP_URL . '/download.php?type=' . urlencode($type) . '&id=' . $fileId;
}

function saveUploadedFile(array $file): array {
    if ($file['size'] > MAX_FILE_SIZE) {
        return ['success' => false, 'message' => 'File too large (max 10MB)'];
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
        return ['success' => false, 'message' => 'File type not allowed'];
    }
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid('file_') . '_' . time() . '.' . $ext;
    $filepath = UPLOAD_DIR . $filename;
    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        return ['success' => false, 'message' => 'Failed to save file'];
    }
    return ['success' => true, 'filename' => $filename, 'mime_type' => $mimeType];
}

function getReportDateFilter(string $period, string $dateColumn = 'created_at'): string {
    switch ($period) {
        case 'day':
            return " AND DATE({$dateColumn}) = CURDATE()";
        case 'month':
            return " AND MONTH({$dateColumn}) = MONTH(CURDATE()) AND YEAR({$dateColumn}) = YEAR(CURDATE())";
        case 'week':
            return " AND YEARWEEK({$dateColumn}, 1) = YEARWEEK(CURDATE(), 1)";
        default:
            return '';
    }
}

function getReportUserFilter(string $alias, array &$params, ?int $userId = null): string {
    if (isSuperAdmin() && $userId === null) {
        return '';
    }
    $params[] = $userId ?? currentUser()['id'];
    return " AND {$alias}.user_id = ?";
}

function getReportData(string $period = 'all', ?string $statusFilter = null, ?string $typeFilter = null, ?int $userId = null): array {
    if (!isSuperAdmin()) {
        $userId = currentUser()['id'];
    }

    $db = getDB();
    $deletedFilter = ' AND is_deleted = 0';
    $params = [];
    $userFilterT = getReportUserFilter('t', $params, $userId);
    $paramsSp = [];
    $userFilterSp = getReportUserFilter('sp', $paramsSp, $userId);
    $paramsP = [];
    $userFilterP = getReportUserFilter('p', $paramsP, $userId);

    $dateFilterT = getReportDateFilter($period, 't.created_at');
    $dateFilterSp = getReportDateFilter($period, 'sp.created_at');
    $statusSqlT = $statusFilter ? ' AND t.status = ?' : '';
    $statusSqlSp = $statusFilter ? ' AND sp.status = ?' : '';
    $statusParam = $statusFilter ? [$statusFilter] : [];

    // Combined status counts (tasks + sub-plans)
    $statusCounts = [];
    foreach (getStatuses() as $st) {
        $statusCounts[$st] = 0;
    }

    $qParams = array_merge($params, $statusParam);
    $stmt = $db->prepare("SELECT status, COUNT(*) as cnt FROM tasks t WHERE 1=1 {$deletedFilter} {$userFilterT} {$dateFilterT} {$statusSqlT} GROUP BY status");
    $stmt->execute($qParams);
    foreach ($stmt->fetchAll() as $row) {
        $statusCounts[$row['status']] = ($statusCounts[$row['status']] ?? 0) + (int) $row['cnt'];
    }

    $qParamsSp = array_merge($paramsSp, $statusParam);
    $stmt = $db->prepare("SELECT status, COUNT(*) as cnt FROM plans sp WHERE sp.parent_id IS NOT NULL {$deletedFilter} {$userFilterSp} {$dateFilterSp} {$statusSqlSp} GROUP BY status");
    $stmt->execute($qParamsSp);
    foreach ($stmt->fetchAll() as $row) {
        $statusCounts[$row['status']] = ($statusCounts[$row['status']] ?? 0) + (int) $row['cnt'];
    }

    $byStatus = [];
    foreach ($statusCounts as $st => $cnt) {
        if ($cnt > 0) $byStatus[] = ['status' => $st, 'count' => $cnt];
    }

    // Priority counts combined
    $priMap = [];
    $stmt = $db->prepare("SELECT priority, COUNT(*) as count FROM tasks t WHERE 1=1 {$deletedFilter} {$userFilterT} {$dateFilterT} {$statusSqlT} GROUP BY priority");
    $stmt->execute($qParams);
    foreach ($stmt->fetchAll() as $row) {
        $priMap[$row['priority']] = ($priMap[$row['priority']] ?? 0) + (int) $row['count'];
    }
    $stmt = $db->prepare("SELECT priority, COUNT(*) as count FROM plans sp WHERE sp.parent_id IS NOT NULL {$deletedFilter} {$userFilterSp} {$dateFilterSp} {$statusSqlSp} GROUP BY priority");
    $stmt->execute($qParamsSp);
    foreach ($stmt->fetchAll() as $row) {
        $priMap[$row['priority']] = ($priMap[$row['priority']] ?? 0) + (int) $row['count'];
    }
    $byPriority = [];
    foreach ($priMap as $p => $c) {
        $byPriority[] = ['priority' => $p, 'count' => $c];
    }

    // Timeline combined
    $dateMap = [];
    $stmt = $db->prepare("SELECT DATE(t.created_at) as date, COUNT(*) as count FROM tasks t WHERE 1=1 {$deletedFilter} {$userFilterT} {$dateFilterT} {$statusSqlT} GROUP BY DATE(t.created_at)");
    $stmt->execute($qParams);
    foreach ($stmt->fetchAll() as $row) {
        $dateMap[$row['date']] = ($dateMap[$row['date']] ?? 0) + (int) $row['count'];
    }
    $stmt = $db->prepare("SELECT DATE(sp.created_at) as date, COUNT(*) as count FROM plans sp WHERE sp.parent_id IS NOT NULL {$deletedFilter} {$userFilterSp} {$dateFilterSp} {$statusSqlSp} GROUP BY DATE(sp.created_at)");
    $stmt->execute($qParamsSp);
    foreach ($stmt->fetchAll() as $row) {
        $dateMap[$row['date']] = ($dateMap[$row['date']] ?? 0) + (int) $row['count'];
    }
    ksort($dateMap);
    $byDate = [];
    foreach ($dateMap as $d => $c) {
        $byDate[] = ['date' => $d, 'count' => $c];
    }

    // All items unified list
    $allItems = [];
    if (!$typeFilter || $typeFilter === 'task') {
        $stmt = $db->prepare("
            SELECT t.id, t.title, t.description, t.status, t.priority, t.task_date, t.failure_reason,
                   t.completed_at, t.created_at, t.updated_at, 'task' as item_type,
                   u.full_name as user_name, pl.title as plan_title,
                   (SELECT COUNT(*) FROM task_files WHERE task_id = t.id) as file_count
            FROM tasks t
            LEFT JOIN users u ON t.user_id = u.id
            LEFT JOIN plans pl ON t.plan_id = pl.id
            WHERE 1=1 {$deletedFilter} {$userFilterT} {$dateFilterT} {$statusSqlT}
            ORDER BY t.updated_at DESC
        ");
        $stmt->execute($qParams);
        $allItems = array_merge($allItems, $stmt->fetchAll());
    }

    if (!$typeFilter || $typeFilter === 'subplan') {
        $stmt = $db->prepare("
            SELECT sp.id, sp.title, sp.description, sp.status, sp.priority, sp.task_date, sp.failure_reason,
                   sp.completed_at, sp.created_at, sp.updated_at, 'subplan' as item_type,
                   u.full_name as user_name, pp.title as plan_title,
                   (SELECT COUNT(*) FROM plan_files WHERE plan_id = sp.id) as file_count
            FROM plans sp
            LEFT JOIN users u ON sp.user_id = u.id
            LEFT JOIN plans pp ON sp.parent_id = pp.id
            WHERE sp.parent_id IS NOT NULL {$deletedFilter} {$userFilterSp} {$dateFilterSp} {$statusSqlSp}
            ORDER BY sp.updated_at DESC
        ");
        $stmt->execute($qParamsSp);
        $allItems = array_merge($allItems, $stmt->fetchAll());
    }

    usort($allItems, fn($a, $b) => strtotime($b['updated_at']) - strtotime($a['updated_at']));

    // Main plans
    $mainPlans = [];
    if (!$typeFilter || $typeFilter === 'plan') {
        $dateFilterP = getReportDateFilter($period, 'p.created_at');
        $stmt = $db->prepare("
            SELECT p.*, u.full_name as user_name,
                   (SELECT COUNT(*) FROM plans sp WHERE sp.parent_id = p.id AND sp.is_deleted = 0) as subplan_count,
                   (SELECT COUNT(*) FROM tasks WHERE plan_id = p.id AND is_deleted = 0) as task_count
            FROM plans p
            LEFT JOIN users u ON p.user_id = u.id
            WHERE p.parent_id IS NULL {$deletedFilter} {$userFilterP} {$dateFilterP}
            ORDER BY p.updated_at DESC
        ");
        $stmt->execute($paramsP);
        $mainPlans = $stmt->fetchAll();
    }

    // Failed with reasons
    $failedItems = array_values(array_filter($allItems, fn($i) => $i['status'] === 'failed'));

    // Done with files
    $doneItems = array_values(array_filter($allItems, fn($i) => $i['status'] === 'done'));

    // All uploaded files
    if (isSuperAdmin() && $userId === null) {
        $fileParams = [];
        $fileUserT = '';
        $fileUserP = '';
    } else {
        $targetUserId = $userId ?? currentUser()['id'];
        $fileParams = [$targetUserId, $targetUserId];
        $fileUserT = ' AND tf.user_id = ?';
        $fileUserP = ' AND pf.user_id = ?';
    }
    $stmt = $db->prepare("
        SELECT tf.id, tf.original_name, tf.file_path, tf.file_size, tf.mime_type, tf.created_at, 'task' as source_type,
               t.title as item_title, t.id as item_id, u.full_name as user_name
        FROM task_files tf
        JOIN tasks t ON tf.task_id = t.id
        JOIN users u ON tf.user_id = u.id
        WHERE t.is_deleted = 0 {$fileUserT}
        UNION ALL
        SELECT pf.id, pf.original_name, pf.file_path, pf.file_size, pf.mime_type, pf.created_at, 'subplan' as source_type,
               sp.title as item_title, sp.id as item_id, u.full_name as user_name
        FROM plan_files pf
        JOIN plans sp ON pf.plan_id = sp.id
        JOIN users u ON pf.user_id = u.id
        WHERE sp.is_deleted = 0 {$fileUserP}
        ORDER BY created_at DESC
    ");
    $stmt->execute($fileParams);
    $allFiles = $stmt->fetchAll();

    $summary = [
        'total_items' => count($allItems),
        'total_tasks' => count(array_filter($allItems, fn($i) => $i['item_type'] === 'task')),
        'total_subplans' => count(array_filter($allItems, fn($i) => $i['item_type'] === 'subplan')),
        'total_plans' => count($mainPlans),
        'total_done' => count($doneItems),
        'total_failed' => count($failedItems),
        'total_files' => count($allFiles),
    ];

    return [
        'by_status' => $byStatus,
        'by_date' => $byDate,
        'by_priority' => $byPriority,
        'daily_history' => getDailyHistory($userId),
        'all_items' => $allItems,
        'main_plans' => $mainPlans,
        'failed_items' => $failedItems,
        'done_items' => $doneItems,
        'all_files' => $allFiles,
        'summary' => $summary,
    ];
}

function getDailyHistory(?int $userId = null): array {
    $db = getDB();
    if (!isSuperAdmin()) {
        $userId = currentUser()['id'];
    }

    $deletedFilter = ' AND is_deleted = 0';
    $pastFilter = " AND COALESCE(task_date, DATE(created_at)) < CURDATE()";
    $userFilter = '';
    $params = [];

    if ($userId !== null) {
        $userFilter = ' AND user_id = ?';
        $params = [$userId, $userId];
    }

    $stmt = $db->prepare("
        SELECT id, title, status, task_date, 'task' as item_type FROM tasks
        WHERE status = 'daily' {$deletedFilter} {$pastFilter} {$userFilter}
        UNION ALL
        SELECT id, title, status, task_date, 'subplan' as item_type FROM plans
        WHERE status = 'daily' AND parent_id IS NOT NULL {$deletedFilter} {$pastFilter} {$userFilter}
        ORDER BY task_date DESC, id DESC
        LIMIT 100
    ");
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function getReportUsers(): array {
    if (!isSuperAdmin()) return [];
    $stmt = getDB()->query('SELECT id, full_name, username FROM users WHERE is_deleted = 0 ORDER BY full_name ASC');
    return $stmt->fetchAll();
}

function getActivityLogs(int $limit = 50): array {
    if (!isSuperAdmin()) return [];
    $stmt = getDB()->prepare("
        SELECT al.*, u.full_name, u.username
        FROM activity_logs al
        JOIN users u ON al.user_id = u.id
        ORDER BY al.created_at DESC
        LIMIT ?
    ");
    $stmt->execute([$limit]);
    return $stmt->fetchAll();
}

function getUserSummaries(int $limit = 12): array {
    if (!isSuperAdmin()) return [];
    $db = getDB();
    $stmt = $db->prepare("
        SELECT u.id, u.full_name, u.username, u.role, u.created_at,
            (SELECT COUNT(*) FROM tasks t WHERE t.user_id = u.id AND t.is_deleted = 0) AS task_count,
            (SELECT COUNT(*) FROM tasks t WHERE t.user_id = u.id AND t.status = 'done' AND t.is_deleted = 0) AS done_count,
            (SELECT COUNT(*) FROM tasks t WHERE t.user_id = u.id AND t.status = 'processing' AND t.is_deleted = 0) AS processing_count,
            (SELECT COUNT(*) FROM plans p WHERE p.user_id = u.id AND p.is_deleted = 0) AS plan_count
        FROM users u
        WHERE u.is_deleted = 0
        ORDER BY task_count DESC, u.full_name ASC
        LIMIT ?
    ");
    $stmt->execute([$limit]);
    return $stmt->fetchAll();
}

function getWeeklyTaskTrend(): array {
    if (!isSuperAdmin()) return [];
    $db = getDB();
    $stmt = $db->query("
        SELECT DATE(created_at) AS day, COUNT(*) AS count
        FROM tasks
        WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
          AND is_deleted = 0
        GROUP BY DATE(created_at)
    ");
    $map = [];
    foreach ($stmt->fetchAll() as $row) {
        $map[$row['day']] = (int) $row['count'];
    }

    $trend = [];
    for ($i = 6; $i >= 0; $i--) {
        $day = date('Y-m-d', strtotime("-{$i} days"));
        $trend[] = [
            'label' => date('D', strtotime($day)),
            'date' => $day,
            'count' => $map[$day] ?? 0,
        ];
    }
    return $trend;
}

function getActivityActionIcon(string $action): string {
    $icons = [
        'login' => 'fa-sign-in-alt',
        'logout' => 'fa-sign-out-alt',
        'create' => 'fa-plus-circle',
        'update' => 'fa-edit',
        'delete' => 'fa-trash',
        'register' => 'fa-user-plus',
        'move' => 'fa-arrows-alt',
        'complete' => 'fa-check-circle',
        'restore' => 'fa-undo',
    ];
    return $icons[$action] ?? 'fa-circle';
}
