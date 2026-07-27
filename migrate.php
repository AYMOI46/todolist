<?php
/**
 * Run once to add sub-plans support: http://localhost/todolist/migrate.php
 */
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/paths.php';
define('APP_NAME', 'TaskFlow Pro');
define('APP_URL', detectAppUrl());

$messages = [];
$success = false;

try {
    $pdo = getDB();

    $columns = [
        'parent_id' => "ADD COLUMN parent_id INT NULL AFTER user_id",
        'status' => "ADD COLUMN status ENUM('weekly', 'daily', 'processing', 'done', 'failed') DEFAULT 'weekly' AFTER end_date",
        'priority' => "ADD COLUMN priority ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium' AFTER status",
        'failure_reason' => "ADD COLUMN failure_reason TEXT NULL AFTER priority",
        'completed_at' => "ADD COLUMN completed_at TIMESTAMP NULL AFTER failure_reason",
    ];

    $existing = $pdo->query("SHOW COLUMNS FROM plans")->fetchAll(PDO::FETCH_COLUMN);

    foreach ($columns as $col => $sql) {
        if (!in_array($col, $existing)) {
            $pdo->exec("ALTER TABLE plans $sql");
            $messages[] = "Added column: $col";
        } else {
            $messages[] = "Column already exists: $col";
        }
    }

    // Add foreign key if missing
    $fks = $pdo->query("
        SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'plans' AND COLUMN_NAME = 'parent_id' AND REFERENCED_TABLE_NAME IS NOT NULL
    ")->fetchAll();

    if (empty($fks)) {
        try {
            $pdo->exec('ALTER TABLE plans ADD CONSTRAINT fk_plans_parent FOREIGN KEY (parent_id) REFERENCES plans(id) ON DELETE CASCADE');
            $messages[] = 'Added foreign key for parent_id';
        } catch (PDOException $e) {
            $messages[] = 'FK note: ' . $e->getMessage();
        }
    }

    $success = true;
    $messages[] = 'Migration completed successfully!';
} catch (PDOException $e) {
    $messages[] = 'Error: ' . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Migration - TaskFlow Pro</title>
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css">
</head>
<body>
    <div class="auth-page">
        <div class="auth-container">
            <div class="auth-card">
                <h1 style="margin-bottom:16px;">Database Migration</h1>
                <?php foreach ($messages as $msg): ?>
                    <p style="padding:4px 0;color:var(--text-muted);"><?= htmlspecialchars($msg) ?></p>
                <?php endforeach; ?>
                <?php if ($success): ?>
                    <a href="<?= APP_URL ?>/board.php" class="btn btn-primary" style="width:100%;justify-content:center;margin-top:16px;">Go to Board</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
