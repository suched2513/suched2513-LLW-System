<?php
/**
 * Manual Table Initialization for Homeroom Enhanced System
 */
header('Content-Type: text/plain; charset=utf-8');
session_start();
require_once __DIR__ . '/../../config/database.php';

if (!isset($_SESSION['llw_role']) || $_SESSION['llw_role'] !== 'super_admin') {
    die("Access Denied: Super Admin Only");
}

try {
    $pdo = getPdo();
    
    echo "Creating homeroom_logs table...\n";
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

    echo "Creating homeroom_photos table...\n";
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

    echo "ALL DONE!";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage();
}
