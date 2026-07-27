<?php
/**
 * Database Installation Script
 * Run once: http://localhost/todolist/install.php
 */
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/paths.php';
define('APP_NAME', 'TaskFlow Pro');
define('APP_URL', detectAppUrl());

$messages = [];
$success = false;

try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    $sql = file_get_contents(__DIR__ . '/database/schema.sql');
    $statements = array_filter(array_map('trim', explode(';', $sql)));

    foreach ($statements as $statement) {
        if (!empty($statement)) {
            $pdo->exec($statement);
        }
    }

    $success = true;
    $messages[] = 'Database created successfully!';
    $messages[] = 'Default admin account: admin / password';
} catch (PDOException $e) {
    $messages[] = 'Error: ' . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Install - TaskFlow Pro</title>
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body>
    <div class="auth-page">
        <div class="auth-container">
            <div class="auth-card">
                <div class="auth-logo">
                    <i class="fas fa-<?= $success ? 'check-circle' : 'exclamation-triangle' ?>" style="color:<?= $success ? '#10b981' : '#ef4444' ?>"></i>
                    <h1>Installation <?= $success ? 'Complete' : 'Failed' ?></h1>
                </div>
                <?php foreach ($messages as $msg): ?>
                    <p style="padding:8px 0;color:var(--text-muted);"><?= htmlspecialchars($msg) ?></p>
                <?php endforeach; ?>
                <?php if ($success): ?>
                    <a href="<?= APP_URL ?>/login.php" class="btn btn-primary" style="width:100%;justify-content:center;margin-top:16px;">
                        <i class="fas fa-sign-in-alt"></i> Go to Login
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
