<?php
$pageTitle = 'Dashboard';
$currentPage = 'dashboard';
require_once __DIR__ . '/includes/header.php';

$stats = getDashboardStats();
$recentTasks = getRecentTasks(8);
$isSuperAdminDash = isSuperAdmin();

if ($isSuperAdminDash) {
    $userSummaries = getUserSummaries(8);
    $activityLogs = getActivityLogs(10);
    $weeklyTrend = getWeeklyTaskTrend();
}
?>

<?php if ($isSuperAdminDash): ?>
    <?php require __DIR__ . '/includes/dashboard-super-admin.php'; ?>
<?php else: ?>
    <?php require __DIR__ . '/includes/dashboard-user.php'; ?>
<?php endif; ?>

<?php
if ($isSuperAdminDash) {
    $pageScript = "
    const stats = " . json_encode($stats) . ";
    const weeklyTrend = " . json_encode($weeklyTrend) . ";

    Chart.defaults.color = '#94a3b8';
    Chart.defaults.borderColor = 'rgba(148,163,184,0.15)';

    new Chart(document.getElementById('overviewChart'), {
        type: 'doughnut',
        data: {
            labels: ['All', 'Weekly', 'Daily', 'Processing', 'Done', 'Failed'],
            datasets: [{
                data: [stats.all || 0, stats.weekly, stats.daily, stats.processing, stats.done, stats.failed],
                backgroundColor: ['#64748b', '#6366f1', '#8b5cf6', '#f59e0b', '#10b981', '#ef4444'],
                borderWidth: 0,
                hoverOffset: 8,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '68%',
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: { color: '#94a3b8', padding: 14, usePointStyle: true, pointStyle: 'circle' }
                }
            }
        }
    });

    new Chart(document.getElementById('trendChart'), {
        type: 'line',
        data: {
            labels: weeklyTrend.map(d => d.label),
            datasets: [{
                label: 'New Tasks',
                data: weeklyTrend.map(d => d.count),
                borderColor: '#818cf8',
                backgroundColor: 'rgba(99,102,241,0.15)',
                fill: true,
                tension: 0.4,
                pointBackgroundColor: '#6366f1',
                pointRadius: 5,
                pointHoverRadius: 7,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { stepSize: 1, color: '#94a3b8' },
                    grid: { color: 'rgba(148,163,184,0.08)' }
                },
                x: {
                    ticks: { color: '#94a3b8' },
                    grid: { display: false }
                }
            }
        }
    });
    ";
} else {
    $pageScript = "
    const stats = " . json_encode($stats) . ";
    new Chart(document.getElementById('overviewChart'), {
        type: 'doughnut',
        data: {
            labels: ['All', 'Weekly', 'Daily', 'Processing', 'Done', 'Failed'],
            datasets: [{
                data: [stats.all || 0, stats.weekly, stats.daily, stats.processing, stats.done, stats.failed],
                backgroundColor: ['#64748b', '#6366f1', '#8b5cf6', '#f59e0b', '#10b981', '#ef4444'],
                borderWidth: 0,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom', labels: { color: '#94a3b8', padding: 16 } }
            }
        }
    });
    ";
}
require_once __DIR__ . '/includes/footer.php';
?>
