<?php
$completionRate = ($stats['total_tasks'] ?? 0) > 0
    ? (int) round(($stats['done'] / $stats['total_tasks']) * 100)
    : 0;
$hour = (int) date('G');
if ($hour < 12) {
    $greeting = 'Subax wanaagsan';
} elseif ($hour < 17) {
    $greeting = 'Galab wanaagsan';
} else {
    $greeting = 'Fiid wanaagsan';
}
?>

<div class="admin-hero">
    <div class="admin-hero-bg"></div>
    <div class="admin-hero-content">
        <div class="admin-hero-text">
            <span class="admin-hero-badge"><i class="fas fa-shield-alt"></i> Super Admin</span>
            <h2><?= $greeting ?>, <?= sanitize($user['full_name']) ?></h2>
            <p>Dhammaan isticmaaleyaasha, hawlaha, iyo warbixinnada nidaamka halkan ka maamul.</p>
            <div class="admin-hero-meta">
                <span><i class="fas fa-calendar-day"></i> <?= date('l, M d, Y') ?></span>
                <span><i class="fas fa-users"></i> <?= $stats['total_users'] ?? 0 ?> Users</span>
                <span><i class="fas fa-chart-line"></i> <?= $completionRate ?>% Completion</span>
            </div>
        </div>
        <div class="admin-quick-actions">
            <a href="<?= APP_URL ?>/admin.php" class="admin-action-btn">
                <i class="fas fa-user-plus"></i>
                <span>Manage Users</span>
            </a>
            <a href="<?= APP_URL ?>/board.php" class="admin-action-btn">
                <i class="fas fa-columns"></i>
                <span>Kanban Board</span>
            </a>
            <a href="<?= APP_URL ?>/reports.php" class="admin-action-btn">
                <i class="fas fa-chart-bar"></i>
                <span>Reports</span>
            </a>
            <a href="<?= APP_URL ?>/plans.php" class="admin-action-btn">
                <i class="fas fa-clipboard-list"></i>
                <span>All Plans</span>
            </a>
        </div>
    </div>
</div>

<div class="admin-kpi-grid">
    <div class="admin-kpi-card kpi-users">
        <div class="admin-kpi-top">
            <div class="admin-kpi-icon"><i class="fas fa-users"></i></div>
            <span class="admin-kpi-tag">System</span>
        </div>
        <div class="admin-kpi-value"><?= $stats['total_users'] ?? 0 ?></div>
        <div class="admin-kpi-label">Total Users</div>
    </div>
    <div class="admin-kpi-card kpi-tasks">
        <div class="admin-kpi-top">
            <div class="admin-kpi-icon"><i class="fas fa-tasks"></i></div>
            <span class="admin-kpi-tag">Active</span>
        </div>
        <div class="admin-kpi-value"><?= $stats['total_tasks'] ?? 0 ?></div>
        <div class="admin-kpi-label">Total Tasks</div>
    </div>
    <div class="admin-kpi-card kpi-completion">
        <div class="admin-kpi-top">
            <div class="admin-kpi-icon"><i class="fas fa-check-double"></i></div>
            <span class="admin-kpi-tag">Rate</span>
        </div>
        <div class="admin-kpi-value"><?= $completionRate ?>%</div>
        <div class="admin-kpi-label">Completion Rate</div>
        <div class="admin-kpi-progress">
            <div class="admin-kpi-progress-bar" style="width:<?= $completionRate ?>%"></div>
        </div>
    </div>
    <div class="admin-kpi-card kpi-plans">
        <div class="admin-kpi-top">
            <div class="admin-kpi-icon"><i class="fas fa-layer-group"></i></div>
            <span class="admin-kpi-tag">Plans</span>
        </div>
        <div class="admin-kpi-value"><?= $stats['total_plans'] ?? 0 ?></div>
        <div class="admin-kpi-label">Active Plans</div>
    </div>
</div>

<div class="admin-status-strip">
    <?php
    $statusItems = [
        'all' => ['icon' => 'fa-inbox', 'label' => 'All'],
        'weekly' => ['icon' => 'fa-calendar-week', 'label' => 'Weekly'],
        'daily' => ['icon' => 'fa-sun', 'label' => 'Daily'],
        'processing' => ['icon' => 'fa-spinner', 'label' => 'Processing'],
        'done' => ['icon' => 'fa-check-circle', 'label' => 'Done'],
        'failed' => ['icon' => 'fa-times-circle', 'label' => 'Failed'],
    ];
    foreach ($statusItems as $key => $item):
    ?>
    <div class="admin-status-pill status-<?= $key ?>">
        <i class="fas <?= $item['icon'] ?>"></i>
        <span class="admin-status-count"><?= $stats[$key] ?? 0 ?></span>
        <span class="admin-status-label"><?= $item['label'] ?></span>
    </div>
    <?php endforeach; ?>
</div>

<div class="admin-dashboard-layout">
    <div class="admin-panel-main">
        <div class="charts-grid">
            <div class="card admin-chart-card">
                <div class="card-header">
                    <h2><i class="fas fa-chart-pie"></i> Task Distribution</h2>
                </div>
                <div class="chart-container chart-container-lg">
                    <canvas id="overviewChart"></canvas>
                </div>
            </div>
            <div class="card admin-chart-card">
                <div class="card-header">
                    <h2><i class="fas fa-chart-line"></i> Weekly Activity</h2>
                </div>
                <div class="chart-container chart-container-lg">
                    <canvas id="trendChart"></canvas>
                </div>
            </div>
        </div>

        <div class="card admin-table-card">
            <div class="card-header">
                <h2><i class="fas fa-clock"></i> Recent Tasks</h2>
                <a href="<?= APP_URL ?>/board.php" class="btn btn-sm btn-primary">View Board</a>
            </div>
            <?php if (empty($recentTasks)): ?>
                <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <p>No tasks yet across the system.</p>
                </div>
            <?php else: ?>
                <div class="table-wrapper">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Task</th>
                                <th>User</th>
                                <th>Status</th>
                                <th>Priority</th>
                                <th>Plan</th>
                                <th>Updated</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentTasks as $task): ?>
                            <tr>
                                <td><strong><?= sanitize($task['title']) ?></strong></td>
                                <td>
                                    <span class="admin-table-user">
                                        <span class="admin-mini-avatar"><?= strtoupper(substr($task['user_name'] ?? 'U', 0, 1)) ?></span>
                                        <?= sanitize($task['user_name'] ?? '-') ?>
                                    </span>
                                </td>
                                <td><span class="badge badge-<?= $task['status'] ?>"><?= getStatusLabel($task['status']) ?></span></td>
                                <td><span class="badge badge-<?= $task['priority'] ?>"><?= getPriorityLabel($task['priority']) ?></span></td>
                                <td><?= sanitize($task['plan_title'] ?? '-') ?></td>
                                <td><?= formatDateTime($task['updated_at']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <aside class="admin-panel-side">
        <div class="card admin-users-card">
            <div class="card-header">
                <h2><i class="fas fa-user-friends"></i> Team Overview</h2>
                <a href="<?= APP_URL ?>/admin.php" class="btn btn-sm btn-secondary">All</a>
            </div>
            <?php if (empty($userSummaries)): ?>
                <div class="empty-state" style="padding:24px;">
                    <p>No users found.</p>
                </div>
            <?php else: ?>
                <div class="admin-user-list">
                    <?php foreach ($userSummaries as $u):
                        $userRate = ($u['task_count'] ?? 0) > 0
                            ? (int) round(($u['done_count'] / $u['task_count']) * 100)
                            : 0;
                    ?>
                    <div class="admin-user-card">
                        <div class="admin-user-card-head">
                            <div class="admin-user-avatar"><?= strtoupper(substr($u['full_name'], 0, 1)) ?></div>
                            <div>
                                <div class="admin-user-name"><?= sanitize($u['full_name']) ?></div>
                                <div class="admin-user-meta">@<?= sanitize($u['username']) ?></div>
                            </div>
                            <?php if ($u['role'] === 'super_admin'): ?>
                                <span class="badge badge-urgent">Admin</span>
                            <?php endif; ?>
                        </div>
                        <div class="admin-user-stats">
                            <span><strong><?= (int) $u['task_count'] ?></strong> tasks</span>
                            <span><strong><?= (int) $u['plan_count'] ?></strong> plans</span>
                            <span><strong><?= (int) $u['processing_count'] ?></strong> active</span>
                        </div>
                        <div class="admin-user-progress">
                            <div class="admin-user-progress-label">
                                <span>Done</span>
                                <span><?= $userRate ?>%</span>
                            </div>
                            <div class="admin-user-progress-track">
                                <div class="admin-user-progress-fill" style="width:<?= $userRate ?>%"></div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="card admin-activity-card">
            <div class="card-header">
                <h2><i class="fas fa-bolt"></i> Live Activity</h2>
            </div>
            <?php if (empty($activityLogs)): ?>
                <div class="empty-state" style="padding:24px;">
                    <p>No recent activity.</p>
                </div>
            <?php else: ?>
                <div class="admin-activity-feed">
                    <?php foreach ($activityLogs as $log): ?>
                    <div class="admin-activity-item">
                        <div class="admin-activity-icon">
                            <i class="fas <?= getActivityActionIcon($log['action']) ?>"></i>
                        </div>
                        <div class="admin-activity-body">
                            <div class="admin-activity-title"><?= sanitize($log['full_name']) ?></div>
                            <div class="admin-activity-detail"><?= sanitize($log['details'] ?: ucfirst($log['action'])) ?></div>
                            <div class="admin-activity-time"><?= formatDateTime($log['created_at']) ?></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <?php if (($stats['deleted_tasks'] ?? 0) > 0): ?>
        <div class="card admin-alert-card">
            <div class="admin-alert-icon"><i class="fas fa-trash-restore"></i></div>
            <div>
                <strong><?= (int) $stats['deleted_tasks'] ?></strong> deleted tasks
                <a href="<?= APP_URL ?>/admin.php">Review in Admin Panel</a>
            </div>
        </div>
        <?php endif; ?>
    </aside>
</div>
