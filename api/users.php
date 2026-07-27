<?php
require_once __DIR__ . '/../config/app.php';
requireSuperAdmin();

header('Content-Type: application/json');

try {
    handleUsersApi();
} catch (Throwable $e) {
    jsonResponse(['success' => false, 'message' => 'Server error: ' . $e->getMessage()], 500);
}

function handleUsersApi(): void {
    $method = $_SERVER['REQUEST_METHOD'];
    $action = $_GET['action'] ?? '';
    $db = getDB();
    $admin = currentUser();

    switch ($action) {
        case 'list':
            $stmt = $db->query('SELECT id, username, email, full_name, role, created_at, updated_at FROM users WHERE is_deleted = 0 ORDER BY created_at DESC');
            jsonResponse(['success' => true, 'users' => $stmt->fetchAll()]);
            break;

        case 'get':
            $id = (int) ($_GET['id'] ?? 0);
            $stmt = $db->prepare('SELECT id, username, email, full_name, role, created_at FROM users WHERE id = ? AND is_deleted = 0');
            $stmt->execute([$id]);
            $user = $stmt->fetch();
            if (!$user) jsonResponse(['success' => false, 'message' => 'User not found'], 404);
            jsonResponse(['success' => true, 'user' => $user]);
            break;

        case 'create':
            if ($method !== 'POST') jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
            $data = json_decode(file_get_contents('php://input'), true);
            if (!is_array($data)) jsonResponse(['success' => false, 'message' => 'Invalid request'], 400);

            $result = createUserByAdmin(
                trim($data['username'] ?? ''),
                trim($data['email'] ?? ''),
                $data['password'] ?? '',
                trim($data['full_name'] ?? ''),
                $data['role'] ?? 'user'
            );
            if (!$result['success']) jsonResponse($result, 400);

            logActivity($admin['id'], 'create', 'user', $result['id'], 'Admin created user: ' . ($data['username'] ?? ''));
            jsonResponse(['success' => true, 'message' => 'User created successfully', 'id' => $result['id']]);
            break;

        case 'update':
            if ($method !== 'POST') jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
            $data = json_decode(file_get_contents('php://input'), true);
            if (!is_array($data)) jsonResponse(['success' => false, 'message' => 'Invalid request'], 400);

            $id = (int) ($data['id'] ?? 0);
            if (!$id) jsonResponse(['success' => false, 'message' => 'Invalid user'], 400);

            $stmt = $db->prepare('SELECT id FROM users WHERE id = ? AND is_deleted = 0');
            $stmt->execute([$id]);
            if (!$stmt->fetch()) jsonResponse(['success' => false, 'message' => 'User not found'], 404);

            $username = trim($data['username'] ?? '');
            $email = trim($data['email'] ?? '');
            $fullName = trim($data['full_name'] ?? '');
            $role = $data['role'] ?? 'user';

            if (!$username || !$email || !$fullName) {
                jsonResponse(['success' => false, 'message' => 'All fields are required']);
            }
            if (!in_array($role, ['user', 'super_admin'], true)) {
                jsonResponse(['success' => false, 'message' => 'Invalid role']);
            }

            $check = $db->prepare('SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ? AND is_deleted = 0');
            $check->execute([$username, $email, $id]);
            if ($check->fetch()) {
                jsonResponse(['success' => false, 'message' => 'Username or email already exists']);
            }

            if (!empty($data['password'])) {
                if (strlen($data['password']) < 6) {
                    jsonResponse(['success' => false, 'message' => 'Password must be at least 6 characters']);
                }
                $hash = password_hash($data['password'], PASSWORD_DEFAULT);
                $stmt = $db->prepare('UPDATE users SET username = ?, email = ?, full_name = ?, role = ?, password = ? WHERE id = ?');
                $stmt->execute([$username, $email, $fullName, $role, $hash, $id]);
            } else {
                $stmt = $db->prepare('UPDATE users SET username = ?, email = ?, full_name = ?, role = ? WHERE id = ?');
                $stmt->execute([$username, $email, $fullName, $role, $id]);
            }

            logActivity($admin['id'], 'update', 'user', $id, 'Admin updated user');
            jsonResponse(['success' => true, 'message' => 'User updated']);
            break;

        case 'delete':
            if ($method !== 'POST') jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
            $data = json_decode(file_get_contents('php://input'), true);
            $id = (int) ($data['id'] ?? 0);

            if ($id === (int) $admin['id']) {
                jsonResponse(['success' => false, 'message' => 'You cannot delete your own account']);
            }

            $stmt = $db->prepare('UPDATE users SET is_deleted = 1 WHERE id = ?');
            $stmt->execute([$id]);
            logActivity($admin['id'], 'delete', 'user', $id, 'Admin deleted user');
            jsonResponse(['success' => true, 'message' => 'User deleted']);
            break;

        default:
            jsonResponse(['success' => false, 'message' => 'Invalid action'], 400);
    }
}
