<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon" style="background:rgba(100,116,139,0.2);color:#94a3b8;">
            <i class="fas fa-inbox"></i>
        </div>
        <div class="stat-value"><?= $stats['all'] ?? 0 ?></div>
        <div class="stat-label">All</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:rgba(99,102,241,0.2);color:#818cf8;">
            <i class="fas fa-calendar-week"></i>
        </div>
        <div class="stat-value"><?= $stats['weekly'] ?></div>
        <div class="stat-label">Weekly Tasks</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:rgba(139,92,246,0.2);color:#a78bfa;">
            <i class="fas fa-sun"></i>
        </div>
        <div class="stat-value"><?= $stats['daily'] ?></div>
        <div class="stat-label">Daily Tasks</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:rgba(245,158,11,0.2);color:#fbbf24;">
            <i class="fas fa-spinner"></i>
        </div>
        <div class="stat-value"><?= $stats['processing'] ?></div>
        <div class="stat-label">Processing</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:rgba(16,185,129,0.2);color:#34d399;">
            <i class="fas fa-check-circle"></i>
        </div>
        <div class="stat-value"><?= $stats['done'] ?></div>
        <div class="stat-label">Completed</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:rgba(239,68,68,0.2);color:#f87171;">
            <i class="fas fa-times-circle"></i>
        </div>
        <div class="stat-value"><?= $stats['failed'] ?></div>
        <div class="stat-label">Failed</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:rgba(59,130,246,0.2);color:#60a5fa;">
            <i class="fas fa-clipboard-list"></i>
        </div>
        <div class="stat-value"><?= $stats['total_plans'] ?></div>
        <div class="stat-label">Total Plans</div>
    </div>
</div>

<div class="grid-2">
    <div class="card">
        <div class="card-header">
            <h2><i class="fas fa-chart-pie"></i> Task Overview</h2>
            <a href="<?= APP_URL ?>/reports.php" class="btn btn-sm btn-primary"><i class="fas fa-chart-bar"></i> My Report</a>
        </div>
        <div class="chart-container">
            <canvas id="overviewChart"></canvas>
        </div>
    </div>
    <div class="card">
        <div class="card-header">
            <h2><i class="fas fa-clock"></i> Recent Tasks</h2>
            <a href="<?= APP_URL ?>/board.php" class="btn btn-sm btn-primary">View Board</a>
        </div>
        <?php if (empty($recentTasks)): ?>
            <div class="empty-state">
                <i class="fas fa-inbox"></i>
                <p>No tasks yet. Create your first task!</p>
            </div>
        <?php else: ?>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Task</th>
                            <th>Status</th>
                            <th>Priority</th>
                            <th>Updated</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentTasks as $task): ?>
                        <tr>
                            <td><?= sanitize($task['title']) ?></td>
                            <td><span class="badge badge-<?= $task['status'] ?>"><?= getStatusLabel($task['status']) ?></span></td>
                            <td><span class="badge badge-<?= $task['priority'] ?>"><?= getPriorityLabel($task['priority']) ?></span></td>
                            <td><?= formatDateTime($task['updated_at']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
