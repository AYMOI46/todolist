<?php
require_once __DIR__ . '/../config/app.php';
requireLogin();

$pageTitle = $pageTitle ?? 'Dashboard';
$currentPage = $currentPage ?? 'dashboard';
$user = currentUser();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= sanitize($pageTitle) ?> - <?= APP_NAME ?></title>
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <div class="app-layout">
        <aside class="sidebar">
            <div class="sidebar-header">
                <div class="logo">
                    <i class="fas fa-layer-group"></i>
                    <span><?= APP_NAME ?></span>
                </div>
            </div>
            <nav class="sidebar-nav">
                <a href="<?= APP_URL ?>/index.php" class="nav-item <?= $currentPage === 'dashboard' ? 'active' : '' ?>">
                    <i class="fas fa-chart-pie"></i> Dashboard
                </a>
                <a href="<?= APP_URL ?>/board.php" class="nav-item <?= $currentPage === 'board' ? 'active' : '' ?>">
                    <i class="fas fa-columns"></i> Kanban Board
                </a>
                <a href="<?= APP_URL ?>/plans.php" class="nav-item <?= $currentPage === 'plans' ? 'active' : '' ?>">
                    <i class="fas fa-clipboard-list"></i> Plans
                </a>
                <a href="<?= APP_URL ?>/reports.php" class="nav-item <?= $currentPage === 'reports' ? 'active' : '' ?>">
                    <i class="fas fa-chart-bar"></i> Reports
                </a>
                <?php if (isSuperAdmin()): ?>
                <a href="<?= APP_URL ?>/admin.php" class="nav-item <?= $currentPage === 'admin' ? 'active' : '' ?>">
                    <i class="fas fa-shield-alt"></i> Admin Panel
                </a>
                <?php endif; ?>
            </nav>
            <div class="sidebar-footer">
                <div class="user-info">
                    <div class="user-avatar"><?= strtoupper(substr($user['full_name'], 0, 1)) ?></div>
                    <div class="user-details">
                        <span class="user-name"><?= sanitize($user['full_name']) ?></span>
                        <span class="user-role"><?= isSuperAdmin() ? 'Super Admin' : 'User' ?></span>
                    </div>
                </div>
                <a href="<?= APP_URL ?>/logout.php" class="logout-btn" title="Logout">
                    <i class="fas fa-sign-out-alt"></i>
                </a>
            </div>
        </aside>
        <main class="main-content">
            <header class="top-bar">
                <h1><?= sanitize($pageTitle) ?></h1>
                <div class="top-bar-actions">
                    <?php if (isset($headerActions)) echo $headerActions; ?>
                </div>
            </header>
            <div class="content-area">
