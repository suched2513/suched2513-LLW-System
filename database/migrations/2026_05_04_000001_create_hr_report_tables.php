<?php
/**
 * Migration: Create homeroom report tables
 * สร้างตารางรายงานโฮมรูมรายสัปดาห์/เดือน พร้อมกิจกรรมและภาพถ่าย
 */
return [
    'up' => function (PDO $pdo) {

        // 1. รายงานหลัก (สัปดาห์ / เดือน)
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `hr_reports` (
                `id`                    INT AUTO_INCREMENT PRIMARY KEY,
                `classroom`             VARCHAR(50)  NOT NULL COMMENT 'เช่น ม.3/2',
                `teacher_id`            INT          NOT NULL COMMENT 'llw_users.user_id',
                `report_type`           ENUM('weekly','monthly') NOT NULL DEFAULT 'weekly',
                `period_start`          DATE         NOT NULL,
                `period_end`            DATE         NOT NULL,
                `academic_year`         SMALLINT     NOT NULL DEFAULT 2568,
                `semester`              TINYINT      NOT NULL DEFAULT 1,
                -- สถิติการเข้าเรียน (ครูกรอกเอง หรือ auto จาก attendance)
                `total_students`        INT          NOT NULL DEFAULT 0,
                `school_days`           INT          NOT NULL DEFAULT 0,
                `present_count`         INT          NOT NULL DEFAULT 0,
                `absent_count`          INT          NOT NULL DEFAULT 0,
                `late_count`            INT          NOT NULL DEFAULT 0,
                -- ส่วนเนื้อหา (ครูกรอก)
                `activities_summary`    TEXT         NULL COMMENT 'สรุปกิจกรรมโดยรวม',
                `problems_noted`        TEXT         NULL COMMENT 'ปัญหาที่พบ',
                `suggestions`           TEXT         NULL COMMENT 'ข้อเสนอแนะ',
                `next_period_plan`      TEXT         NULL COMMENT 'แผนงานงวดถัดไป',
                -- Workflow
                `status`                ENUM('draft','submitted','approved','revision') NOT NULL DEFAULT 'draft',
                `submitted_at`          DATETIME     NULL,
                `reviewed_by`           INT          NULL COMMENT 'llw_users.user_id',
                `reviewed_at`           DATETIME     NULL,
                `review_note`           TEXT         NULL,
                `created_at`            DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at`            DATETIME     NULL ON UPDATE CURRENT_TIMESTAMP,
                INDEX `idx_classroom`   (`classroom`),
                INDEX `idx_teacher`     (`teacher_id`),
                INDEX `idx_period`      (`period_start`, `period_end`),
                INDEX `idx_status`      (`status`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // 2. กิจกรรมภายในรายงาน (1 รายงาน : หลายกิจกรรม)
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `hr_report_activities` (
                `id`                    INT AUTO_INCREMENT PRIMARY KEY,
                `report_id`             INT          NOT NULL,
                `activity_date`         DATE         NULL,
                `activity_name`         VARCHAR(200) NOT NULL,
                `description`           TEXT         NULL,
                `participants_count`    INT          NULL,
                `outcome`               TEXT         NULL COMMENT 'ผลที่ได้',
                `order_no`              INT          NOT NULL DEFAULT 1,
                INDEX `idx_report`      (`report_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // 3. ภาพกิจกรรม (1 รายงาน : หลายภาพ)
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `hr_report_photos` (
                `id`                    INT AUTO_INCREMENT PRIMARY KEY,
                `report_id`             INT          NOT NULL,
                `activity_id`           INT          NULL COMMENT 'hr_report_activities.id (optional)',
                `file_path`             VARCHAR(500) NOT NULL,
                `original_name`         VARCHAR(255) NULL,
                `caption`               VARCHAR(500) NULL,
                `file_size_kb`          INT          NULL,
                `order_no`              INT          NOT NULL DEFAULT 1,
                `uploaded_at`           DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX `idx_report`      (`report_id`),
                INDEX `idx_activity`    (`activity_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    },

    'down' => function (PDO $pdo) {
        $pdo->exec("DROP TABLE IF EXISTS `hr_report_photos`");
        $pdo->exec("DROP TABLE IF EXISTS `hr_report_activities`");
        $pdo->exec("DROP TABLE IF EXISTS `hr_reports`");
    },
];
