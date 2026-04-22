<?php
/**
 * Migration: Create enhanced homeroom tracking tables
 * ระบบบันทึกโฮมรูม (Activity Logs & Photos)
 */
return [
    'up' => function (PDO $pdo) {
        // 1. homeroom_logs — บันทึกหัวข้อโฮมรูมรายวัน
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `homeroom_logs` (
                `id`          INT AUTO_INCREMENT PRIMARY KEY,
                `classroom`   VARCHAR(50) NOT NULL COMMENT 'ม.X/Y',
                `log_date`    DATE NOT NULL,
                `topic`       TEXT COMMENT 'หัวข้อ/เรื่องที่แจ้งนักเรียน',
                `user_id`     INT NOT NULL COMMENT 'ครูผู้บันทึก',
                `created_at`  DATETIME DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY `uq_class_date` (`classroom`, `log_date`),
                INDEX `idx_classroom` (`classroom`),
                INDEX `idx_log_date` (`log_date`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // 2. homeroom_photos — บันทึกรูปภาพกิจกรรม
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `homeroom_photos` (
                `id`          INT AUTO_INCREMENT PRIMARY KEY,
                `classroom`   VARCHAR(50) NOT NULL,
                `log_date`    DATE NOT NULL,
                `image_path`  VARCHAR(500) NOT NULL,
                `caption`     VARCHAR(255) DEFAULT NULL,
                `user_id`     INT NOT NULL,
                `created_at`  DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX `idx_class_date` (`classroom`, `log_date`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    },

    'down' => function (PDO $pdo) {
        $pdo->exec("DROP TABLE IF EXISTS `homeroom_photos` ");
        $pdo->exec("DROP TABLE IF EXISTS `homeroom_logs` ");
    },
];
