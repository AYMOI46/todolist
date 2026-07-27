<?php
require_once __DIR__ . '/config/app.php';
requireSuperAdmin();

$pageTitle = 'Admin Panel';
$currentPage = 'admin';
$headerActions = '<button class="btn btn-primary" onclick="openCreateUser()"><i class="fas fa-user-plus"></i> Create User</button>';
require_once __DIR__ . '/includes/header.php';

$stats = getDashboardStats();
$activityLogs = getActivityLogs(30);
$userStmt = getDB()->query('SELECT id, username, email, full_name, role, created_at FROM users WHERE is_deleted = 0 ORDER BY created_at DESC');
$allUsers = $userStmt->fetchAll();
$extraScripts = ['assets/js/admin.js'];
?>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon" style="background:rgba(59,130,246,0.2);color:#60a5fa;"><i class="fas fa-users"></i></div>
        <div class="stat-value"><?= $stats['total_users'] ?? 0 ?></div>
        <div class="stat-label">Total Users</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:rgba(16,185,129,0.2);color:#34d399;"><i class="fas fa-tasks"></i></div>
        <div class="stat-value"><?= $stats['total_tasks'] ?></div>
        <div class="stat-label">Total Tasks</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:rgba(239,68,68,0.2);color:#f87171;"><i class="fas fa-trash"></i></div>
        <div class="stat-value"><?= $stats['deleted_tasks'] ?? 0 ?></div>
        <div class="stat-label">Deleted Tasks</div>
    </div>
</div>

<div class="grid-2">
    <div class="card">
        <div class="card-header">
            <h2><i class="fas fa-users"></i> All Users</h2>
            <button class="btn btn-sm btn-primary" onclick="openCreateUser()"><i class="fas fa-user-plus"></i> New User</button>
        </div>
        <div id="usersContainer">
            <?php if (empty($allUsers)): ?>
                <div class="empty-state"><p>No users yet. Click "New User" to create one.</p></div>
            <?php else: ?>
                <div class="table-wrapper">
                    <table>
                        <thead><tr><th>Name</th><th>Username</th><th>Email</th><th>Role</th><th>Joined</th><th>Actions</th></tr></thead>
                        <tbody>
                            <?php foreach ($allUsers as $u): ?>
                            <tr>
                                <td><strong><?= sanitize($u['full_name']) ?></strong></td>
                                <td><?= sanitize($u['username']) ?></td>
                                <td><?= sanitize($u['email']) ?></td>
                                <td><span class="badge <?= $u['role'] === 'super_admin' ? 'badge-urgent' : 'badge-medium' ?>"><?= $u['role'] === 'super_admin' ? 'Super Admin' : 'User' ?></span></td>
                                <td><?= formatDate($u['created_at']) ?></td>
                                <td>
                                    <button class="btn btn-sm btn-secondary" onclick="editUser(<?= (int) $u['id'] ?>)"><i class="fas fa-edit"></i></button>
                                    <button class="btn btn-sm btn-danger" onclick="deleteUser(<?= (int) $u['id'] ?>)"><i class="fas fa-trash"></i></button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <div class="card">
        <div class="card-header">
            <h2><i class="fas fa-history"></i> Activity Log</h2>
        </div>
        <?php if (empty($activityLogs)): ?>
            <div class="empty-state"><p>No activity yet</p></div>
        <?php else: ?>
            <div class="table-wrapper" style="max-height:400px;overflow-y:auto;">
                <table>
                    <thead><tr><th>User</th><th>Action</th><th>Details</th><th>Time</th></tr></thead>
                    <tbody>
                        <?php foreach ($activityLogs as $log): ?>
                        <tr>
                            <td><?= sanitize($log['full_name']) ?></td>
                            <td><span class="badge badge-medium"><?= sanitize($log['action']) ?></span></td>
                            <td style="max-width:200px;font-size:0.8rem;"><?= sanitize($log['details']) ?></td>
                            <td style="font-size:0.8rem;"><?= formatDateTime($log['created_at']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h2><i class="fas fa-trash-restore"></i> Deleted Items</h2>
        <div>
            <button class="btn btn-sm btn-secondary" onclick="loadDeleted('tasks')">Deleted Tasks</button>
            <button class="btn btn-sm btn-secondary" onclick="loadDeleted('plans')">Deleted Plans</button>
        </div>
    </div>
    <div id="deletedContainer">
        <div class="empty-state"><p>Click a button above to view deleted items</p></div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
