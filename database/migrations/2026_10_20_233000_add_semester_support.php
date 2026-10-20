<?php
/**
 * Migration: add_semester_support
 * Created: 2026-10-20
 *
 * 1. สร้างตาราง llw_academic_terms เก็บวันเปิด-ปิดภาคเรียน 1 และ 2 ของแต่ละปีการศึกษา
 * 2. เพิ่มคอลัมน์ academic_year และ semester ในตาราง att_subjects (ข้อมูลเดิมเป็น 2569 เทอม 1)
 */
return [
    'up' => function (PDO $pdo) {
        // 1. ตารางตั้งค่าภาคเรียนส่วนกลาง
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `llw_academic_terms` (
                `id`            INT AUTO_INCREMENT PRIMARY KEY,
                `academic_year` INT          NOT NULL COMMENT 'ปีการศึกษา พ.ศ. เช่น 2569',
                `semester`      TINYINT      NOT NULL COMMENT '1 หรือ 2',
                `start_date`    DATE         NOT NULL COMMENT 'วันเปิดภาคเรียน',
                `end_date`      DATE         NOT NULL COMMENT 'วันปิดภาคเรียน',
                `is_active`     TINYINT(1)   NOT NULL DEFAULT 0 COMMENT '1=บังคับเป็นเทอมปัจจุบัน, 0=คำนวณตามวันที่อัตโนมัติ',
                `updated_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY `uq_llw_term_year_sem` (`academic_year`, `semester`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // ค่าเริ่มต้นสำหรับปีการศึกษา 2569
        $pdo->exec("
            INSERT IGNORE INTO `llw_academic_terms` (`academic_year`, `semester`, `start_date`, `end_date`, `is_active`) VALUES
            (2569, 1, '2026-05-16', '2026-10-31', 0),
            (2569, 2, '2026-11-01', '2027-03-31', 0)
        ");

        // 2. เพิ่มคอลัมน์ academic_year และ semester ใน att_subjects
        $cols = $pdo->query("SHOW COLUMNS FROM `att_subjects`")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('academic_year', $cols, true)) {
            $pdo->exec("ALTER TABLE `att_subjects` ADD COLUMN `academic_year` INT NOT NULL DEFAULT 2569 AFTER `teacher_id`");
        }
        if (!in_array('semester', $cols, true)) {
            $pdo->exec("ALTER TABLE `att_subjects` ADD COLUMN `semester` TINYINT NOT NULL DEFAULT 1 AFTER `academic_year`");
        }

        // Index สำหรับค้นหาวิชาตามปีการศึกษาและเทอม
        $idx = $pdo->query("SHOW INDEX FROM `att_subjects` WHERE Key_name = 'idx_att_subj_year_sem'")->fetchAll();
        if (empty($idx)) {
            $pdo->exec("ALTER TABLE `att_subjects` ADD INDEX `idx_att_subj_year_sem` (`academic_year`, `semester`)");
        }
    },

    'down' => function (PDO $pdo) {
        $cols = $pdo->query("SHOW COLUMNS FROM `att_subjects`")->fetchAll(PDO::FETCH_COLUMN);
        if (in_array('semester', $cols, true)) {
            $pdo->exec("ALTER TABLE `att_subjects` DROP COLUMN `semester`");
        }
        if (in_array('academic_year', $cols, true)) {
            $pdo->exec("ALTER TABLE `att_subjects` DROP COLUMN `academic_year`");
        }
        $pdo->exec("DROP TABLE IF EXISTS `llw_academic_terms`");
    },
];
