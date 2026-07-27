<?php
require_once __DIR__ . '/../config/app.php';
requireLogin();

header('Content-Type: application/json');
$action = $_GET['action'] ?? '';

switch ($action) {
    case 'dashboard':
        jsonResponse(['success' => true, 'stats' => getDashboardStats()]);
        break;

    case 'report':
        $period = $_GET['period'] ?? 'week';
        $userId = null;
        if (isSuperAdmin() && !empty($_GET['user_id'])) {
            $userId = (int) $_GET['user_id'];
        }
        jsonResponse(['success' => true, 'data' => getReportData($period, null, null, $userId)]);
        break;

    case 'activity':
        if (!isSuperAdmin()) jsonResponse(['success' => false, 'message' => 'Access denied'], 403);
        jsonResponse(['success' => true, 'logs' => getActivityLogs()]);
        break;

    case 'users':
        if (!isSuperAdmin()) jsonResponse(['success' => false, 'message' => 'Access denied'], 403);
        $stmt = getDB()->query('SELECT id, username, email, full_name, role, created_at FROM users WHERE is_deleted = 0 ORDER BY created_at DESC');
        jsonResponse(['success' => true, 'users' => $stmt->fetchAll()]);
        break;

    default:
        jsonResponse(['success' => false, 'message' => 'Invalid action'], 400);
}
