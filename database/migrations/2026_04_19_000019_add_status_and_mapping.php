<?php
/**
 * Migration: Add status to beh_records and create beh_advisors table
 */
return [
    'up' => function (PDO $pdo) {
        // 1. Add status to beh_records
        // ใช้ try-catch หรือตรวจสอบก่อนเพื่อความปลอดภัย
        try {
            $pdo->exec("ALTER TABLE beh_records ADD COLUMN status ENUM('pending', 'approved', 'rejected') DEFAULT 'approved' AFTER image_path");
        } catch (Exception $e) {
            // คอลัมน์อาจจะมีอยู่แล้ว ข้ามไป
        }
        
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
    },

    'down' => function (PDO $pdo) {
        $pdo->exec("DROP TABLE IF EXISTS beh_advisors");
        $pdo->exec("ALTER TABLE beh_records DROP COLUMN status");
    }
];
