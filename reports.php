<?php
require_once __DIR__ . '/config/app.php';
requireLogin();
$pageTitle = isSuperAdmin() ? 'Reports' : 'My Report';
$currentPage = 'reports';
require_once __DIR__ . '/includes/header.php';

$period = $_GET['period'] ?? 'all';
$statusFilter = $_GET['status'] ?? '';
$typeFilter = $_GET['type'] ?? '';
$isAdmin = isSuperAdmin();
$reportUserId = null;

if ($isAdmin && !empty($_GET['user_id'])) {
    $reportUserId = (int) $_GET['user_id'];
}

$reportData = getReportData($period, $statusFilter ?: null, $typeFilter ?: null, $reportUserId);
$summary = $reportData['summary'];
$reportUsers = getReportUsers();
$reportOwner = $user;

if ($reportUserId) {
    foreach ($reportUsers as $ru) {
        if ((int) $ru['id'] === $reportUserId) {
            $reportOwner = $ru;
            break;
        }
    }
}

$rate = ($summary['total_done'] + $summary['total_failed']) > 0
    ? round(($summary['total_done'] / ($summary['total_done'] + $summary['total_failed'])) * 100) : 0;
?>

<?php if (!$isAdmin): ?>
<div class="my-report-hero">
    <div class="my-report-hero-content">
        <div>
            <span class="my-report-badge"><i class="fas fa-chart-bar"></i> Warbixintayda</span>
            <h2>My Report — <?= sanitize($user['full_name']) ?></h2>
            <p>Halkan ka arag hawlahaaga, plans-kaaga, files-kaaga, iyo taariikhda daily-gaaga.</p>
        </div>
        <div class="my-report-quick-stats">
            <div><strong><?= $summary['total_items'] ?></strong><span>Items</span></div>
            <div><strong><?= $summary['total_done'] ?></strong><span>Done</span></div>
            <div><strong><?= $rate ?>%</strong><span>Success</span></div>
        </div>
    </div>
</div>
<?php else: ?>
<div class="filter-bar reports-filter" style="margin-bottom:16px;">
    <label style="color:var(--text-muted);font-size:0.875rem;">User:</label>
    <select onchange="applyReportFilter('user_id', this.value)">
        <option value="">All Users</option>
        <?php foreach ($reportUsers as $ru): ?>
        <option value="<?= (int) $ru['id'] ?>" <?= $reportUserId === (int) $ru['id'] ? 'selected' : '' ?>>
            <?= sanitize($ru['full_name']) ?> (@<?= sanitize($ru['username']) ?>)
        </option>
        <?php endforeach; ?>
    </select>
    <?php if ($reportUserId): ?>
        <span class="badge badge-medium">Showing report for: <?= sanitize($reportOwner['full_name'] ?? 'User') ?></span>
    <?php endif; ?>
</div>
<?php endif; ?>

<div class="filter-bar reports-filter">    <label style="color:var(--text-muted);font-size:0.875rem;">Period:</label>
    <select onchange="applyReportFilter('period', this.value)">
        <option value="all" <?= $period === 'all' ? 'selected' : '' ?>>All Time / Dhammaan</option>
        <option value="day" <?= $period === 'day' ? 'selected' : '' ?>>Today</option>
        <option value="week" <?= $period === 'week' ? 'selected' : '' ?>>This Week</option>
        <option value="month" <?= $period === 'month' ? 'selected' : '' ?>>This Month</option>
    </select>

    <label style="color:var(--text-muted);font-size:0.875rem;">Status:</label>
    <select onchange="applyReportFilter('status', this.value)">
        <option value="">All Statuses</option>
        <?php foreach (getStatuses() as $st): ?>
        <option value="<?= $st ?>" <?= $statusFilter === $st ? 'selected' : '' ?>><?= getStatusLabel($st) ?></option>
        <?php endforeach; ?>
    </select>

    <label style="color:var(--text-muted);font-size:0.875rem;">Type:</label>
    <select onchange="applyReportFilter('type', this.value)">
        <option value="">All Types</option>
        <option value="task" <?= $typeFilter === 'task' ? 'selected' : '' ?>>Tasks</option>
        <option value="subplan" <?= $typeFilter === 'subplan' ? 'selected' : '' ?>>Sub-Plans</option>
        <option value="plan" <?= $typeFilter === 'plan' ? 'selected' : '' ?>>Plans</option>
    </select>
</div>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon" style="background:rgba(99,102,241,0.2);color:#818cf8;"><i class="fas fa-list"></i></div>
        <div class="stat-value"><?= $summary['total_items'] ?></div>
        <div class="stat-label">Total Items</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:rgba(59,130,246,0.2);color:#60a5fa;"><i class="fas fa-tasks"></i></div>
        <div class="stat-value"><?= $summary['total_tasks'] ?></div>
        <div class="stat-label">Tasks</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:rgba(139,92,246,0.2);color:#a78bfa;"><i class="fas fa-layer-group"></i></div>
        <div class="stat-value"><?= $summary['total_subplans'] ?></div>
        <div class="stat-label">Sub-Plans</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:rgba(16,185,129,0.2);color:#34d399;"><i class="fas fa-check-circle"></i></div>
        <div class="stat-value"><?= $summary['total_done'] ?></div>
        <div class="stat-label">Done</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:rgba(239,68,68,0.2);color:#f87171;"><i class="fas fa-times-circle"></i></div>
        <div class="stat-value"><?= $summary['total_failed'] ?></div>
        <div class="stat-label">Failed</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:rgba(245,158,11,0.2);color:#fbbf24;"><i class="fas fa-folder"></i></div>
        <div class="stat-value"><?= $summary['total_plans'] ?></div>
        <div class="stat-label">Plans</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:rgba(100,116,139,0.2);color:#94a3b8;"><i class="fas fa-paperclip"></i></div>
        <div class="stat-value"><?= $summary['total_files'] ?></div>
        <div class="stat-label">Files</div>
    </div>
    <div class="stat-card">
        <div class="stat-value"><?= $rate ?>%</div>
        <div class="stat-label">Success Rate</div>
    </div>
</div>

<div class="charts-grid">
    <div class="card">
        <div class="card-header"><h2>By Status (Tasks + Sub-Plans)</h2></div>
        <div class="chart-container"><canvas id="statusChart"></canvas></div>
    </div>
    <div class="card">
        <div class="card-header"><h2>By Priority</h2></div>
        <div class="chart-container"><canvas id="priorityChart"></canvas></div>
    </div>
</div>

<div class="card" style="margin-top:24px;">
    <div class="card-header">
        <h2><i class="fas fa-table"></i> <?= $isAdmin ? 'Dhammaan — All Items' : 'Hawlahayga — My Items' ?> (<?= count($reportData['all_items']) ?>)</h2>
    </div>
    <?php if (empty($reportData['all_items'])): ?>
        <div class="empty-state"><p><?= $isAdmin ? 'No items found' : 'Weli ma haysatid hawlo. Ku bilow board-ka!' ?></p></div>
    <?php else: ?>
        <div class="table-wrapper" style="max-height:500px;overflow-y:auto;">
            <table>
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th>Priority</th>
                        <th>Plan</th>
                        <th>Work Date</th>
                        <?php if ($isAdmin): ?><th>User</th><?php endif; ?>
                        <th>Files</th>
                        <th>Updated</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($reportData['all_items'] as $item): ?>
                    <tr>
                        <td><strong><?= sanitize($item['title']) ?></strong>
                            <?php if ($item['failure_reason']): ?>
                                <br><small style="color:#f87171;"><?= sanitize($item['failure_reason']) ?></small>
                            <?php endif; ?>
                        </td>
                        <td><span class="badge badge-medium"><?= $item['item_type'] === 'subplan' ? 'Sub-Plan' : 'Task' ?></span></td>
                        <td><span class="badge badge-<?= $item['status'] ?>"><?= getStatusLabel($item['status']) ?></span></td>
                        <td><span class="badge badge-<?= $item['priority'] ?>"><?= getPriorityLabel($item['priority']) ?></span></td>
                        <td><?= sanitize($item['plan_title'] ?? '-') ?></td>
                        <td><?= formatDate($item['task_date']) ?></td>
                        <?php if ($isAdmin): ?><td><?= sanitize($item['user_name']) ?></td><?php endif; ?>
                        <td>
                            <?php if ((int)$item['file_count'] > 0 && $item['status'] === 'done'): ?>
                                <a href="<?= APP_URL ?>/files.php?type=<?= $item['item_type'] === 'subplan' ? 'plan' : 'task' ?>&item_id=<?= (int)$item['id'] ?>" class="btn btn-sm btn-secondary" title="View files">
                                    <i class="fas fa-download"></i> <?= (int)$item['file_count'] ?>
                                </a>
                            <?php else: ?>
                                <?= (int)$item['file_count'] ?>
                            <?php endif; ?>
                        </td>
                        <td style="font-size:0.8rem;"><?= formatDateTime($item['updated_at']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php if (!empty($reportData['main_plans'])): ?>
<div class="card" style="margin-top:24px;">
    <div class="card-header"><h2><i class="fas fa-folder"></i> Plans (<?= count($reportData['main_plans']) ?>)</h2></div>
    <div class="table-wrapper">
        <table>
            <thead><tr><th>Title</th><th>Sub-Plans</th><th>Tasks</th><th>Start</th><th>End</th><?php if ($isAdmin): ?><th>User</th><?php endif; ?></tr></thead>
            <tbody>
                <?php foreach ($reportData['main_plans'] as $plan): ?>
                <tr>
                    <td><strong><?= sanitize($plan['title']) ?></strong></td>
                    <td><?= (int)$plan['subplan_count'] ?></td>
                    <td><?= (int)$plan['task_count'] ?></td>
                    <td><?= formatDate($plan['start_date']) ?></td>
                    <td><?= formatDate($plan['end_date']) ?></td>
                    <?php if ($isAdmin): ?><td><?= sanitize($plan['user_name']) ?></td><?php endif; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php if (!empty($reportData['failed_items'])): ?>
<div class="card" style="margin-top:24px;">
    <div class="card-header"><h2><i class="fas fa-exclamation-triangle" style="color:#f87171;"></i> Failed (<?= count($reportData['failed_items']) ?>)</h2></div>
    <div class="table-wrapper">
        <table>
            <thead><tr><th>Title</th><th>Type</th><th>Reason</th><th>Date</th><?php if ($isAdmin): ?><th>User</th><?php endif; ?></tr></thead>
            <tbody>
                <?php foreach ($reportData['failed_items'] as $item): ?>
                <tr class="deleted-item">
                    <td><?= sanitize($item['title']) ?></td>
                    <td><?= $item['item_type'] === 'subplan' ? 'Sub-Plan' : 'Task' ?></td>
                    <td style="color:#f87171;"><?= sanitize($item['failure_reason'] ?? '-') ?></td>
                    <td><?= formatDate($item['task_date']) ?></td>
                    <?php if ($isAdmin): ?><td><?= sanitize($item['user_name']) ?></td><?php endif; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php if (!empty($reportData['all_files'])): ?>
<div class="card" style="margin-top:24px;">
    <div class="card-header"><h2><i class="fas fa-paperclip"></i> Uploaded Files (<?= count($reportData['all_files']) ?>)</h2></div>
    <div class="table-wrapper">
        <table>
            <thead><tr><th>File</th><th>Item</th><th>Type</th><th>Size</th><th>Uploaded</th><?php if ($isAdmin): ?><th>User</th><?php endif; ?><th>Download</th></tr></thead>
            <tbody>
                <?php foreach ($reportData['all_files'] as $file): ?>
                <?php $dlType = $file['source_type'] === 'subplan' ? 'plan' : 'task'; ?>
                <tr>
                    <td><i class="fas fa-file"></i> <?= sanitize($file['original_name']) ?></td>
                    <td><?= sanitize($file['item_title']) ?></td>
                    <td><?= $file['source_type'] === 'subplan' ? 'Sub-Plan' : 'Task' ?></td>
                    <td><?= round($file['file_size'] / 1024, 1) ?> KB</td>
                    <td><?= formatDateTime($file['created_at']) ?></td>
                    <?php if ($isAdmin): ?><td><?= sanitize($file['user_name']) ?></td><?php endif; ?>
                    <td>
                        <a href="<?= fileDownloadUrl($dlType, (int)$file['id']) ?>" class="btn btn-sm btn-primary" title="Download">
                            <i class="fas fa-download"></i> Download
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<div class="card" style="margin-top:24px;">
    <div class="card-header"><h2><i class="fas fa-sun"></i> Daily History — Maalmihii Hore</h2></div>
    <?php if (empty($reportData['daily_history'])): ?>
        <div class="empty-state"><p>No daily history</p></div>
    <?php else: ?>
        <div class="table-wrapper">
            <table>
                <thead><tr><th>Date</th><th>Title</th><th>Type</th><th>Status</th></tr></thead>
                <tbody>
                    <?php foreach ($reportData['daily_history'] as $row): ?>
                    <tr>
                        <td><?= formatDate($row['task_date']) ?></td>
                        <td><?= sanitize($row['title']) ?></td>
                        <td><?= $row['item_type'] === 'subplan' ? 'Sub-Plan' : 'Task' ?></td>
                        <td><span class="badge badge-<?= $row['status'] ?>"><?= getStatusLabel($row['status']) ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<div class="card" style="margin-top:24px;">
    <div class="card-header"><h2>Activity Over Time</h2></div>
    <div class="chart-container"><canvas id="timelineChart"></canvas></div>
</div>

<?php
$pageScript = "
function applyReportFilter(key, val) {
    const params = new URLSearchParams(window.location.search);
    if (val) params.set(key, val); else params.delete(key);
    window.location = '?' + params.toString();
}

const reportData = " . json_encode($reportData) . ";

const statusColors = {'all':'#64748b','weekly':'#6366f1','daily':'#8b5cf6','processing':'#f59e0b','done':'#10b981','failed':'#ef4444'};
const statusLabels = reportData.by_status.map(s => s.status.charAt(0).toUpperCase() + s.status.slice(1));
const statusCounts = reportData.by_status.map(s => parseInt(s.count));

new Chart(document.getElementById('statusChart'), {
    type: 'bar',
    data: {
        labels: statusLabels.length ? statusLabels : ['No Data'],
        datasets: [{ data: statusCounts.length ? statusCounts : [0], backgroundColor: reportData.by_status.map(s => statusColors[s.status] || '#64748b'), borderRadius: 8 }]
    },
    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { color: '#94a3b8' }, grid: { color: '#334155' } }, x: { ticks: { color: '#94a3b8' }, grid: { display: false } } } }
});

const priLabels = reportData.by_priority.map(p => p.priority.charAt(0).toUpperCase() + p.priority.slice(1));
const priCounts = reportData.by_priority.map(p => parseInt(p.count));
const priColors = {'low':'#64748b','medium':'#3b82f6','high':'#f59e0b','urgent':'#ef4444'};

new Chart(document.getElementById('priorityChart'), {
    type: 'doughnut',
    data: {
        labels: priLabels.length ? priLabels : ['No Data'],
        datasets: [{ data: priCounts.length ? priCounts : [0], backgroundColor: reportData.by_priority.map(p => priColors[p.priority] || '#64748b'), borderWidth: 0 }]
    },
    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom', labels: { color: '#94a3b8' } } } }
});

const dateLabels = reportData.by_date.map(d => d.date);
const dateCounts = reportData.by_date.map(d => parseInt(d.count));

new Chart(document.getElementById('timelineChart'), {
    type: 'line',
    data: {
        labels: dateLabels.length ? dateLabels : ['No Data'],
        datasets: [{ label: 'Items Created', data: dateCounts.length ? dateCounts : [0], borderColor: '#6366f1', backgroundColor: 'rgba(99,102,241,0.1)', fill: true, tension: 0.4 }]
    },
    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { labels: { color: '#94a3b8' } } }, scales: { y: { beginAtZero: true, ticks: { color: '#94a3b8' }, grid: { color: '#334155' } }, x: { ticks: { color: '#94a3b8' }, grid: { color: '#334155' } } } }
});
";
require_once __DIR__ . '/includes/footer.php';
?>
