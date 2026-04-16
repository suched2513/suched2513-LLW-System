<?php
/**
 * Migration: create_assembly_tables
 * Created: 2026-04-10
 *
 * ตาราง: assembly_classrooms, assembly_students, assembly_attendance, assembly_checkout
 */
return [
    'up' => function (PDO $pdo) {

        // ─── 1. assembly_classrooms ───────────────────────────────────
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `assembly_classrooms` (
                `id`           INT AUTO_INCREMENT PRIMARY KEY,
                `classroom`    VARCHAR(20)  NOT NULL UNIQUE COMMENT 'เช่น ม.1/1, ม.2/3',
                `teacher_name` VARCHAR(100) NULL,
                `llw_user_id`  INT          NULL COMMENT 'FK → llw_users.user_id',
                `created_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX `idx_asmc_llw_user` (`llw_user_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // ─── 2. assembly_students ─────────────────────────────────────
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `assembly_students` (
                `id`         INT AUTO_INCREMENT PRIMARY KEY,
                `student_id` VARCHAR(20)  NOT NULL UNIQUE COMMENT 'รหัสประจำตัวนักเรียน',
                `name`       VARCHAR(100) NOT NULL,
                `classroom`  VARCHAR(20)  NOT NULL,
                `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX `idx_asms_classroom` (`classroom`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // ─── 3. assembly_attendance ───────────────────────────────────
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `assembly_attendance` (
                `id`         INT AUTO_INCREMENT PRIMARY KEY,
                `date`       DATE         NOT NULL,
                `classroom`  VARCHAR(20)  NOT NULL,
                `student_id` VARCHAR(20)  NOT NULL,
                `status`     ENUM('ม','ข','ล','ด') NOT NULL DEFAULT 'ม',
                `nail`       ENUM('ถูก','ผิด')     NOT NULL DEFAULT 'ถูก',
                `hair`       ENUM('ถูก','ผิด')     NOT NULL DEFAULT 'ถูก',
                `shirt`      ENUM('ถูก','ผิด')     NOT NULL DEFAULT 'ถูก',
                `pants`      ENUM('ถูก','ผิด')     NOT NULL DEFAULT 'ถูก',
                `socks`      ENUM('ถูก','ผิด')     NOT NULL DEFAULT 'ถูก',
                `shoes`      ENUM('ถูก','ผิด')     NOT NULL DEFAULT 'ถูก',
                `note`       TEXT         NULL,
                `created_by` INT          NULL,
                `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY `uq_asma_date_student`  (`date`, `student_id`),
                INDEX       `idx_asma_date`         (`date`),
                INDEX       `idx_asma_classroom`    (`classroom`),
                INDEX       `idx_asma_student`      (`student_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // ─── 4. assembly_checkout ─────────────────────────────────────
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `assembly_checkout` (
                `id`         INT AUTO_INCREMENT PRIMARY KEY,
                `date`       DATE         NOT NULL,
                `classroom`  VARCHAR(20)  NOT NULL,
                `student_id` VARCHAR(20)  NOT NULL,
                `name`       VARCHAR(100) NULL,
                `status`     ENUM('มา','ไม่มา','โดด') NOT NULL DEFAULT 'มา',
                `note`       TEXT         NULL,
                `created_by` INT          NULL,
                `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX `idx_asmco_date`      (`date`),
                INDEX `idx_asmco_classroom` (`classroom`),
                INDEX `idx_asmco_student`   (`student_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    },

    'down' => function (PDO $pdo) {
        $pdo->exec("DROP TABLE IF EXISTS `assembly_checkout`");
        $pdo->exec("DROP TABLE IF EXISTS `assembly_attendance`");
        $pdo->exec("DROP TABLE IF EXISTS `assembly_students`");
        $pdo->exec("DROP TABLE IF EXISTS `assembly_classrooms`");
    },
];
