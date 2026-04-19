<?php
/**
 * Migration: Create behavior module tables (beh_*)
 * ระบบบันทึกพฤติกรรมนักเรียน (Behavior Scorebook System)
 */
return [
    'up' => function (PDO $pdo) {

        // ── beh_students — นักเรียนในระบบพฤติกรรม ──
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `beh_students` (
                `id`              INT AUTO_INCREMENT PRIMARY KEY,
                `student_id`      VARCHAR(20)  NOT NULL,
                `name`            VARCHAR(200) NOT NULL,
                `level`           VARCHAR(20)  DEFAULT NULL COMMENT 'ระดับชั้น เช่น ม.2',
                `room`            VARCHAR(20)  DEFAULT NULL COMMENT 'ห้อง เช่น 1',
                `homeroom`        VARCHAR(200) DEFAULT NULL COMMENT 'ครูที่ปรึกษา',
                `img_url`         VARCHAR(500) DEFAULT NULL COMMENT 'URL รูปภาพนักเรียน',
                `status`          ENUM('active','inactive') DEFAULT 'active',
                `created_at`      DATETIME DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY `uq_beh_student_id` (`student_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // ── beh_templates — แม่แบบพฤติกรรม (Quick Select) ──
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `beh_templates` (
                `id`              INT AUTO_INCREMENT PRIMARY KEY,
                `type`            ENUM('ความดี','ความผิด') NOT NULL,
                `name`            VARCHAR(300) NOT NULL,
                `score`           INT NOT NULL DEFAULT 1,
                `status`          ENUM('active','inactive') DEFAULT 'active',
                `created_at`      DATETIME DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // ── beh_records — บันทึกพฤติกรรมรายครั้ง ──
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `beh_records` (
                `id`              INT AUTO_INCREMENT PRIMARY KEY,
                `student_id`      VARCHAR(20)  NOT NULL COMMENT 'FK → beh_students.student_id',
                `student_name`    VARCHAR(200) DEFAULT NULL COMMENT 'snapshot ชื่อตอนบันทึก',
                `record_date`     DATE         NOT NULL,
                `type`            ENUM('ความดี','ความผิด') NOT NULL,
                `activity`        TEXT         NOT NULL,
                `score`           INT          NOT NULL DEFAULT 1,
                `teacher_name`    VARCHAR(200) DEFAULT NULL COMMENT 'ครูผู้บันทึก',
                `teacher_user_id` INT          DEFAULT NULL COMMENT 'FK → llw_users.user_id',
                `image_path`      VARCHAR(500) DEFAULT NULL COMMENT 'path รูปหลักฐาน',
                `created_at`      DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX `idx_beh_student_id`  (`student_id`),
                INDEX `idx_beh_record_date` (`record_date`),
                INDEX `idx_beh_type`        (`type`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // ── beh_system_users — ผู้ใช้ระบบพฤติกรรม (เสริม) ──
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `beh_system_users` (
                `id`              INT AUTO_INCREMENT PRIMARY KEY,
                `llw_user_id`     INT          DEFAULT NULL COMMENT 'FK → llw_users.user_id',
                `username`        VARCHAR(100) NOT NULL,
                `password_hash`   VARCHAR(255) NOT NULL,
                `name`            VARCHAR(200) NOT NULL,
                `role`            ENUM('admin','teacher','homeroom') DEFAULT 'teacher',
                `active`          TINYINT(1) DEFAULT 1,
                `created_at`      DATETIME DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY `uq_beh_username` (`username`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    },

    'down' => function (PDO $pdo) {
        $pdo->exec("DROP TABLE IF EXISTS `beh_records`");
        $pdo->exec("DROP TABLE IF EXISTS `beh_templates`");
        $pdo->exec("DROP TABLE IF EXISTS `beh_system_users`");
        $pdo->exec("DROP TABLE IF EXISTS `beh_students`");
    },
];
