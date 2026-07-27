USE trello_system;

ALTER TABLE plans
    ADD COLUMN IF NOT EXISTS parent_id INT NULL AFTER user_id,
    ADD COLUMN IF NOT EXISTS status ENUM('weekly', 'daily', 'processing', 'done', 'failed') DEFAULT 'weekly' AFTER end_date,
    ADD COLUMN IF NOT EXISTS priority ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium' AFTER status,
    ADD COLUMN IF NOT EXISTS failure_reason TEXT NULL AFTER priority,
    ADD COLUMN IF NOT EXISTS completed_at TIMESTAMP NULL AFTER failure_reason;

-- MySQL 8 doesn't support IF NOT EXISTS on ADD COLUMN in all versions, use migrate.php instead
