<?php

function isLoggedIn(): bool {
    return isset($_SESSION['user_id']);
}

function currentUser(): ?array {
    if (!isLoggedIn()) return null;
    static $user = null;
    if ($user === null) {
        $stmt = getDB()->prepare('SELECT id, username, email, full_name, role FROM users WHERE id = ? AND is_deleted = 0');
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch() ?: null;
    }
    return $user;
}

function isSuperAdmin(): bool {
    $user = currentUser();
    return $user && $user['role'] === 'super_admin';
}

function requireLogin(): void {
    if (!isLoggedIn()) {
        header('Location: ' . APP_URL . '/login.php');
        exit;
    }
}

function requireSuperAdmin(): void {
    requireLogin();
    if (!isSuperAdmin()) {
        header('Location: ' . APP_URL . '/index.php');
        exit;
    }
}

function login(string $username, string $password): bool {
    $stmt = getDB()->prepare('SELECT * FROM users WHERE (username = ? OR email = ?) AND is_deleted = 0');
    $stmt->execute([$username, $username]);
    $user = $stmt->fetch();
    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        logActivity($user['id'], 'login', 'user', $user['id'], 'User logged in');
        return true;
    }
    return false;
}

function register(string $username, string $email, string $password, string $fullName): array {
    $result = createUserByAdmin($username, $email, $password, $fullName, 'user');
    if ($result['success']) {
        logActivity($result['id'], 'register', 'user', $result['id'], 'New user registered');
        return ['success' => true, 'message' => 'Registration successful'];
    }
    return $result;
}

function createUserByAdmin(string $username, string $email, string $password, string $fullName, string $role = 'user'): array {
    $username = trim($username);
    $email = trim($email);
    $fullName = trim($fullName);

    if (!$username || !$email || !$fullName || !$password) {
        return ['success' => false, 'message' => 'All fields are required'];
    }
    if (strlen($password) < 6) {
        return ['success' => false, 'message' => 'Password must be at least 6 characters'];
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'message' => 'Invalid email address'];
    }
    if (!in_array($role, ['user', 'super_admin'], true)) {
        return ['success' => false, 'message' => 'Invalid role'];
    }

    $db = getDB();
    $check = $db->prepare('SELECT id FROM users WHERE (username = ? OR email = ?) AND is_deleted = 0');
    $check->execute([$username, $email]);
    if ($check->fetch()) {
        return ['success' => false, 'message' => 'Username or email already exists'];
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $db->prepare('INSERT INTO users (username, email, password, full_name, role) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute([$username, $email, $hash, $fullName, $role]);
    $userId = (int) $db->lastInsertId();
    return ['success' => true, 'message' => 'User created', 'id' => $userId];
}

function logout(): void {
    if (isLoggedIn()) {
        logActivity($_SESSION['user_id'], 'logout', 'user', $_SESSION['user_id'], 'User logged out');
    }
    session_destroy();
}

function canAccessTask(int $taskId): bool {
    $user = currentUser();
    if (!$user) return false;
    if (isSuperAdmin()) return true;
    $stmt = getDB()->prepare('SELECT user_id FROM tasks WHERE id = ?');
    $stmt->execute([$taskId]);
    $task = $stmt->fetch();
    return $task && $task['user_id'] == $user['id'];
}

function canAccessPlan(int $planId): bool {
    $user = currentUser();
    if (!$user) return false;
    if (isSuperAdmin()) return true;
    $stmt = getDB()->prepare('SELECT user_id FROM plans WHERE id = ?');
    $stmt->execute([$planId]);
    $plan = $stmt->fetch();
    return $plan && $plan['user_id'] == $user['id'];
}

function getDeletedFilter(): string {
    return isSuperAdmin() ? '' : ' AND is_deleted = 0';
}

function getUserFilter(string $alias = ''): array {
    $prefix = $alias ? "$alias." : '';
    if (isSuperAdmin()) {
        return ['sql' => '', 'params' => []];
    }
    return ['sql' => " AND {$prefix}user_id = ?", 'params' => [currentUser()['id']]];
}
