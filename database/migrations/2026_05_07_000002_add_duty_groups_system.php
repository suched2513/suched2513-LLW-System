<?php
/**
 * Migration: add_duty_groups_system
 * Created: 2026-05-07
 * เพิ่มตาราง duty_groups, duty_group_members, duty_day_groups
 */
return [
    'up' => function (PDO $pdo) {

        // 1. กลุ่มเวร
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS duty_groups (
                id          INT AUTO_INCREMENT PRIMARY KEY,
                name        VARCHAR(100) NOT NULL COMMENT 'ชื่อกลุ่ม เช่น กลุ่ม ก',
                color       VARCHAR(7)   NOT NULL DEFAULT '#3B82F6' COMMENT 'hex color',
                description VARCHAR(255) NULL,
                sort_order  TINYINT      NOT NULL DEFAULT 0,
                status      ENUM('active','inactive') NOT NULL DEFAULT 'active',
                created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // 2. สมาชิกกลุ่ม
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS duty_group_members (
                id          INT AUTO_INCREMENT PRIMARY KEY,
                group_id    INT NOT NULL,
                teacher_id  INT NOT NULL,
                created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uk_gm (group_id, teacher_id),
                INDEX idx_group_id (group_id),
                INDEX idx_teacher_id (teacher_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // 3. กลุ่มที่รับเวรแต่ละวัน+กะ
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS duty_day_groups (
                id          INT AUTO_INCREMENT PRIMARY KEY,
                duty_date   DATE NOT NULL,
                shift       ENUM('day','night') NOT NULL,
                group_id    INT NULL,
                created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uk_date_shift (duty_date, shift),
                INDEX idx_duty_date (duty_date)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // 4. เพิ่ม group_id ใน duty_schedule (MySQL 5.7 compatible)
        $has = $pdo->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='duty_schedule' AND COLUMN_NAME='group_id'")->fetchColumn();
        if (!$has) {
            $pdo->exec("ALTER TABLE duty_schedule ADD COLUMN group_id INT NULL COMMENT 'FK duty_groups.id' AFTER shift");
        }
    },

    'down' => function (PDO $pdo) {
        $pdo->exec("SET FOREIGN_KEY_CHECKS=0");
        $pdo->exec("DROP TABLE IF EXISTS duty_day_groups");
        $pdo->exec("DROP TABLE IF EXISTS duty_group_members");
        $pdo->exec("DROP TABLE IF EXISTS duty_groups");
        $pdo->exec("SET FOREIGN_KEY_CHECKS=1");
    },
];
