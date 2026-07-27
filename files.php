<?php
$pageTitle = 'Files';
$currentPage = 'reports';
require_once __DIR__ . '/includes/header.php';

$type = $_GET['type'] ?? 'task';
$itemId = (int) ($_GET['item_id'] ?? 0);
$files = [];
$itemTitle = '';

if ($type === 'plan') {
    if (!canAccessPlan($itemId)) {
        header('Location: ' . APP_URL . '/reports.php');
        exit;
    }
    $stmt = getDB()->prepare('SELECT title FROM plans WHERE id = ?');
    $stmt->execute([$itemId]);
    $item = $stmt->fetch();
    $itemTitle = $item['title'] ?? 'Sub-Plan';
    $files = getPlanFiles($itemId);
} else {
    if (!canAccessTask($itemId)) {
        header('Location: ' . APP_URL . '/reports.php');
        exit;
    }
    $stmt = getDB()->prepare('SELECT title FROM tasks WHERE id = ?');
    $stmt->execute([$itemId]);
    $item = $stmt->fetch();
    $itemTitle = $item['title'] ?? 'Task';
    $files = getTaskFiles($itemId);
}

$dlType = $type === 'plan' ? 'plan' : 'task';
?>

<div class="card">
    <div class="card-header">
        <h2><i class="fas fa-paperclip"></i> Files — <?= sanitize($itemTitle) ?></h2>
        <a href="<?= APP_URL ?>/reports.php" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left"></i> Back to Reports</a>
    </div>

    <?php if (empty($files)): ?>
        <div class="empty-state"><p>No files uploaded</p></div>
    <?php else: ?>
        <div class="file-list" style="padding:8px 0;">
            <?php foreach ($files as $file): ?>
            <div class="file-item" style="margin-bottom:10px;">
                <span>
                    <i class="fas fa-file"></i>
                    <?= sanitize($file['original_name']) ?>
                    <small style="color:var(--text-muted);margin-left:8px;"><?= round($file['file_size'] / 1024, 1) ?> KB · <?= formatDateTime($file['created_at']) ?></small>
                </span>
                <a href="<?= fileDownloadUrl($dlType, (int)$file['id']) ?>" class="btn btn-sm btn-primary">
                    <i class="fas fa-download"></i> Download
                </a>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
