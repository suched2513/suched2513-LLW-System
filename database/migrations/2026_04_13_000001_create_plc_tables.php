<?php
/**
 * Migration: Create PLC Module Tables
 * Created: 2026-04-13
 */
return [
    'up' => function (PDO $pdo) {
        // 1. plc_groups
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS plc_groups (
                id            INT AUTO_INCREMENT PRIMARY KEY,
                group_name    VARCHAR(255) NOT NULL,
                academic_year VARCHAR(20) DEFAULT '',
                semester      VARCHAR(10) DEFAULT '',
                target_group  VARCHAR(255) DEFAULT '', -- e.g. Students in Math Grade 7
                status        ENUM('active', 'completed', 'archived') NOT NULL DEFAULT 'active',
                created_by    INT NOT NULL,
                created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // 2. plc_members
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS plc_members (
                id         INT AUTO_INCREMENT PRIMARY KEY,
                group_id   INT NOT NULL,
                user_id    INT NOT NULL,
                role       ENUM('model_teacher', 'mentor', 'expert', 'member') NOT NULL DEFAULT 'member',
                joined_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY unique_group_user (group_id, user_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // 3. plc_logs (PDCA Records)
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS plc_logs (
                id            INT AUTO_INCREMENT PRIMARY KEY,
                group_id      INT NOT NULL,
                user_id       INT NOT NULL,
                phase         ENUM('Plan', 'Do', 'Check', 'Act') NOT NULL,
                topic         VARCHAR(255) NOT NULL,
                details       TEXT NULL,
                reflection    TEXT NULL,
                evidence_path TEXT NULL,
                log_date      DATE NOT NULL,
                created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    },

    'down' => function (PDO $pdo) {
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
        $pdo->exec("DROP TABLE IF EXISTS plc_logs");
        $pdo->exec("DROP TABLE IF EXISTS plc_members");
        $pdo->exec("DROP TABLE IF EXISTS plc_groups");
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    },
];
