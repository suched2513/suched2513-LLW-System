<?php
/**
 * Migration: Add status to beh_records and create beh_advisors table
 */
require_once __DIR__ . '/../../config/database.php';

function up() {
    $pdo = getPdo();
    
    // 1. Add status to beh_records
    $pdo->exec("ALTER TABLE beh_records ADD COLUMN status ENUM('pending', 'approved', 'rejected') DEFAULT 'approved' AFTER image_path");
    
    // 2. Create beh_advisors
    $pdo->exec("CREATE TABLE IF NOT EXISTS beh_advisors (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        level VARCHAR(20) NOT NULL,
        room VARCHAR(20) NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX (user_id),
        INDEX (level, room)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

function down() {
    $pdo = getPdo();
    $pdo->exec("DROP TABLE IF EXISTS beh_advisors");
    $pdo->exec("ALTER TABLE beh_records DROP COLUMN status");
}

// Check for command line arguments
if (isset($argv[1])) {
    if ($argv[1] === 'up') {
        try {
            up();
            echo "Migration completed successfully (Up).\n";
        } catch (Exception $e) {
            echo "Migration failed: " . $e->getMessage() . "\n";
        }
    } elseif ($argv[1] === 'down') {
        try {
            down();
            echo "Migration rolled back successfully (Down).\n";
        } catch (Exception $e) {
            echo "Migration rollback failed: " . $e->getMessage() . "\n";
        }
    }
}
