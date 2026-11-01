<?php
/**
 * Migration: 2026_11_01_000001_create_pp5_online_tables
 * Created: 2026-11-01
 *
 * สร้างโครงสร้างฐานข้อมูลทั้งหมดสำหรับ "ระบบ ปพ.5 ออนไลน์" (แบบบันทึกผลการพัฒนาคุณภาพผู้เรียน)
 * ตามหลักสูตรแกนกลางการศึกษาขั้นพื้นฐาน พ.ศ. 2551 เชื่อมต่อกับระบบ LLW System (llw_db)
 *
 * เหตุผลการออกแบบและปรับปรุงโครงสร้างฐานข้อมูล:
 * 1. ใช้ prefix `pp5_` สำหรับตารางเฉพาะของระบบ ปพ.5 เพื่อแยกขอบเขตชัดเจนตามมาตรฐาน LLW System
 *    และเชื่อมโยง (Foreign Key / Mapping) กับ `llw_users`, `llw_academic_terms`, `att_students`, `att_subjects`
 * 2. เพิ่มคอลัมน์ `academic_year` และ `semester` ใน `pp5_classrooms` และ `pp5_courses`
 *    เพื่อรองรับการเลื่อนชั้นและเก็บประวัติ ปพ.5 ย้อนหลังทุกภาคเรียนโดยไม่ทับข้อมูลเดิม
 * 3. เพิ่ม `citizen_id_encrypted` (เข้ารหัส AES-256-CBC ตามมาตรฐาน PDPA) ใน `att_students`
 *    ควบคู่กับ `national_id_masked` เพื่อให้แสดงผลเฉพาะผู้มีสิทธิ์
 * 4. เพิ่มตาราง `pp5_activity_logs` และ `pp5_notifications` เพื่อบันทึก Audit Trail ทุกครั้งที่มีการแก้คะแนน/เกรด/ปลดล็อก
 *    และแจ้งเตือนในระบบเมื่อมีงานรออนุมัติหรือถูกตีกลับโดยไม่ต้องพึ่งพา Redis
 * 5. กำหนด Unique Constraint และ Composite Index ในทุกตารางเพื่อป้องกันข้อมูลซ้ำและรองรับการค้นหาความเร็วสูง
 */
return [
    'up' => function (PDO $pdo) {
        // ─── 0. อัปเกรดตารางแกนกลาง llw_users และ att_students ให้รองรับข้อมูล ปพ.5 ───
        try {
            $uCols = $pdo->query("SHOW COLUMNS FROM `llw_users`")->fetchAll(PDO::FETCH_COLUMN);
            if (!in_array('prefix', $uCols, true)) {
                $pdo->exec("ALTER TABLE `llw_users` ADD COLUMN `prefix` VARCHAR(50) NOT NULL DEFAULT '' AFTER `password`");
            }
            if (!in_array('department_id', $uCols, true)) {
                $pdo->exec("ALTER TABLE `llw_users` ADD COLUMN `department_id` INT NULL DEFAULT NULL AFTER `lastname`");
            }
            if (!in_array('email', $uCols, true)) {
                $pdo->exec("ALTER TABLE `llw_users` ADD COLUMN `email` VARCHAR(150) NULL DEFAULT NULL AFTER `department_id`");
            }
            if (!in_array('force_password_change', $uCols, true)) {
                $pdo->exec("ALTER TABLE `llw_users` ADD COLUMN `force_password_change` TINYINT(1) NOT NULL DEFAULT 0");
            }
        } catch (Throwable $e) {
            error_log('[PP5 Migration] ALTER llw_users: ' . $e->getMessage());
        }

        try {
            $sCols = $pdo->query("SHOW COLUMNS FROM `att_students`")->fetchAll(PDO::FETCH_COLUMN);
            if (!in_array('prefix', $sCols, true)) {
                $pdo->exec("ALTER TABLE `att_students` ADD COLUMN `prefix` VARCHAR(50) NOT NULL DEFAULT '' AFTER `student_id`");
            }
            if (!in_array('first_name', $sCols, true)) {
                $pdo->exec("ALTER TABLE `att_students` ADD COLUMN `first_name` VARCHAR(100) NOT NULL DEFAULT '' AFTER `prefix`");
            }
            if (!in_array('last_name', $sCols, true)) {
                $pdo->exec("ALTER TABLE `att_students` ADD COLUMN `last_name` VARCHAR(100) NOT NULL DEFAULT '' AFTER `first_name`");
            }
            if (!in_array('gender', $sCols, true)) {
                $pdo->exec("ALTER TABLE `att_students` ADD COLUMN `gender` ENUM('male','female','unspecified') NOT NULL DEFAULT 'unspecified' AFTER `name`");
            }
            if (!in_array('student_number', $sCols, true)) {
                $pdo->exec("ALTER TABLE `att_students` ADD COLUMN `student_number` SMALLINT UNSIGNED NULL DEFAULT NULL COMMENT 'เลขที่ในห้องเรียน' AFTER `classroom`");
            }
            if (!in_array('citizen_id_encrypted', $sCols, true)) {
                $pdo->exec("ALTER TABLE `att_students` ADD COLUMN `citizen_id_encrypted` TEXT NULL COMMENT 'เลขบัตรประชาชนเข้ารหัส AES-256-CBC ตาม PDPA'");
            }
            if (!in_array('national_id_hash', $sCols, true)) {
                $pdo->exec("ALTER TABLE `att_students` ADD COLUMN `national_id_hash` VARCHAR(255) NULL");
            }
            if (!in_array('national_id_masked', $sCols, true)) {
                $pdo->exec("ALTER TABLE `att_students` ADD COLUMN `national_id_masked` VARCHAR(30) NULL");
            }
            if (!in_array('status', $sCols, true)) {
                $pdo->exec("ALTER TABLE `att_students` ADD COLUMN `status` VARCHAR(30) NOT NULL DEFAULT 'active'");
            } else {
                $pdo->exec("ALTER TABLE `att_students` MODIFY COLUMN `status` VARCHAR(30) NOT NULL DEFAULT 'active' COMMENT 'active/moved/graduated/dropped/inactive'");
            }
        } catch (Throwable $e) {
            error_log('[PP5 Migration] ALTER att_students: ' . $e->getMessage());
        }

        // ─── 1. ตารางตั้งค่าโรงเรียนและเกณฑ์การประเมิน (pp5_school_settings) ───
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `pp5_school_settings` (
                `id`                       INT AUTO_INCREMENT PRIMARY KEY,
                `school_name`              VARCHAR(200) NOT NULL DEFAULT 'โรงเรียนละลมวิทยา',
                `school_code`              VARCHAR(50)  NOT NULL DEFAULT '1033720344',
                `affiliation`              VARCHAR(200) NOT NULL DEFAULT 'สำนักงานเขตพื้นที่การศึกษามัธยมศึกษาศรีสะเกษ ยโสธร',
                `province`                 VARCHAR(100) NOT NULL DEFAULT 'ศรีสะเกษ',
                `logo_path`                VARCHAR(255) NOT NULL DEFAULT '/assets/icons/icon-192x192.png',
                `director_name`            VARCHAR(150) NOT NULL DEFAULT 'ผู้อำนวยการโรงเรียนละลมวิทยา',
                `director_position`        VARCHAR(150) NOT NULL DEFAULT 'ผู้อำนวยการโรงเรียนละลมวิทยา',
                `deputy_academic_name`     VARCHAR(150) NOT NULL DEFAULT 'รองผู้อำนวยการกลุ่มบริหารวิชาการ',
                `deputy_academic_position` VARCHAR(150) NOT NULL DEFAULT 'รองผู้อำนวยการกลุ่มบริหารวิชาการ',
                `measurement_head_name`    VARCHAR(150) NOT NULL DEFAULT 'หัวหน้างานวัดและประเมินผล',
                `min_attendance_percent`   DECIMAL(5,2) NOT NULL DEFAULT 80.00 COMMENT 'เกณฑ์เวลาเรียนขั้นต่ำ (%) ไม่ติด มส',
                `count_late_as_present`    TINYINT(1)   NOT NULL DEFAULT 1 COMMENT '1=นับมาสายเป็นเวลาเรียน, 0=ไม่นับ',
                `count_leave_as_present`   TINYINT(1)   NOT NULL DEFAULT 0 COMMENT '1=นับลากิจเป็นเวลาเรียน, 0=ไม่นับ',
                `count_sick_as_present`    TINYINT(1)   NOT NULL DEFAULT 0 COMMENT '1=นับลาป่วยเป็นเวลาเรียน, 0=ไม่นับ',
                `assessment_summary_mode`  ENUM('mode','mean') NOT NULL DEFAULT 'mode' COMMENT 'วิธีสรุปผลคุณลักษณะ/อ่านคิดเขียน/สมรรถนะ: mode=ฐานนิยม, mean=ค่าเฉลี่ย',
                `zero_item_forces_fail`    TINYINT(1)   NOT NULL DEFAULT 1 COMMENT '1=ถ้ามีข้อย่อยได้ 0 ให้สรุปผลรวมเป็น 0 ทันที',
                `mean_min_excellent`       DECIMAL(4,2) NOT NULL DEFAULT 2.50 COMMENT 'เกณฑ์ค่าเฉลี่ยระดับ 3 (ดีเยี่ยม)',
                `mean_min_good`            DECIMAL(4,2) NOT NULL DEFAULT 1.50 COMMENT 'เกณฑ์ค่าเฉลี่ยระดับ 2 (ดี)',
                `mean_min_pass`            DECIMAL(4,2) NOT NULL DEFAULT 1.00 COMMENT 'เกณฑ์ค่าเฉลี่ยระดับ 1 (ผ่าน)',
                `updated_at`               DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // ─── 2. ตารางเกณฑ์การตัดเกรด (pp5_grade_criteria) ───
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `pp5_grade_criteria` (
                `id`          INT AUTO_INCREMENT PRIMARY KEY,
                `min_score`   INT          NOT NULL COMMENT 'คะแนนต่ำสุดของเกรดนี้ (0-100)',
                `max_score`   INT          NOT NULL COMMENT 'คะแนนสูงสุดของเกรดนี้ (0-100)',
                `grade`       VARCHAR(10)  NOT NULL COMMENT 'เช่น 4, 3.5, 3, 2.5, 2, 1.5, 1, 0',
                `meaning`     VARCHAR(100) NOT NULL DEFAULT '' COMMENT 'ความหมายผลการเรียน',
                `sort_order`  INT          NOT NULL DEFAULT 0,
                `updated_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY `uq_pp5_grade_label` (`grade`),
                INDEX `idx_pp5_grade_min_score` (`min_score` DESC)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // ─── 3. ตารางกลุ่มสาระการเรียนรู้ 8 กลุ่มสาระ + กิจกรรมพัฒนาผู้เรียน (pp5_departments) ───
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `pp5_departments` (
                `id`           INT AUTO_INCREMENT PRIMARY KEY,
                `code`         VARCHAR(20)  NOT NULL COMMENT 'รหัสย่อ เช่น TH, MA, SC, SO, HP, AR, OC, FL, ACT',
                `name`         VARCHAR(150) NOT NULL COMMENT 'ชื่อกลุ่มสาระการเรียนรู้',
                `head_user_id` INT          NULL DEFAULT NULL COMMENT 'FK -> llw_users.user_id (หัวหน้ากลุ่มสาระ)',
                `is_activity`  TINYINT(1)   NOT NULL DEFAULT 0 COMMENT '1=กิจกรรมพัฒนาผู้เรียน',
                `sort_order`   INT          NOT NULL DEFAULT 0,
                `created_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY `uq_pp5_dept_code` (`code`),
                INDEX `idx_pp5_dept_head` (`head_user_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // ─── 4. ตารางห้องเรียนประจำภาคเรียน (pp5_classrooms) ───
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `pp5_classrooms` (
                `id`                 INT AUTO_INCREMENT PRIMARY KEY,
                `academic_year`      INT          NOT NULL COMMENT 'ปีการศึกษา พ.ศ. เช่น 2568, 2569',
                `semester`           TINYINT      NOT NULL COMMENT 'ภาคเรียน 1 หรือ 2',
                `grade_level`        TINYINT      NOT NULL COMMENT 'ระดับชั้น ม.1-ม.6 (1-6)',
                `room_no`            TINYINT      NOT NULL COMMENT 'ห้องที่ เช่น 1, 2, 3, 4',
                `classroom_name`     VARCHAR(30)  NOT NULL COMMENT 'ชื่อห้อง เช่น ม.1/1',
                `study_plan`         VARCHAR(100) NOT NULL DEFAULT 'ทั่วไป' COMMENT 'แผนการเรียน',
                `advisor_user_id`    INT          NULL DEFAULT NULL COMMENT 'ครูที่ปรึกษาคนที่ 1 (llw_users.user_id)',
                `co_advisor_user_id` INT          NULL DEFAULT NULL COMMENT 'ครูที่ปรึกษาคนที่ 2 (llw_users.user_id)',
                `created_at`         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at`         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY `uq_pp5_cls_term_name` (`academic_year`, `semester`, `classroom_name`),
                INDEX `idx_pp5_cls_grade` (`academic_year`, `semester`, `grade_level`, `room_no`),
                INDEX `idx_pp5_cls_advisor` (`advisor_user_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // ─── 5. ตารางนักเรียนประจำห้องเรียนในแต่ละภาคเรียน (pp5_classroom_students) ───
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `pp5_classroom_students` (
                `id`             INT AUTO_INCREMENT PRIMARY KEY,
                `classroom_id`   INT          NOT NULL COMMENT 'FK -> pp5_classrooms.id',
                `student_id`     INT          NOT NULL COMMENT 'FK -> att_students.id',
                `student_code`   VARCHAR(20)  NOT NULL COMMENT 'รหัสประจำตัวนักเรียน 5 หลัก',
                `student_number` SMALLINT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'เลขที่ในห้องเรียน',
                `status`         ENUM('active','moved','graduated','dropped') NOT NULL DEFAULT 'active',
                `created_at`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY `uq_pp5_cs_room_stu` (`classroom_id`, `student_id`),
                INDEX `idx_pp5_cs_room_num` (`classroom_id`, `student_number`),
                INDEX `idx_pp5_cs_student` (`student_id`),
                CONSTRAINT `fk_pp5_cs_classroom` FOREIGN KEY (`classroom_id`) REFERENCES `pp5_classrooms` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // ─── 6. ตารางรายวิชามาตรฐานตามหลักสูตร (pp5_subjects) ───
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `pp5_subjects` (
                `id`            INT AUTO_INCREMENT PRIMARY KEY,
                `code`          VARCHAR(30)   NOT NULL COMMENT 'รหัสวิชา เช่น ท21101, ค21101, ก21901',
                `name`          VARCHAR(200)  NOT NULL COMMENT 'ชื่อรายวิชา',
                `department_id` INT           NOT NULL COMMENT 'FK -> pp5_departments.id',
                `type`          ENUM('basic','additional','activity') NOT NULL DEFAULT 'basic' COMMENT 'พื้นฐาน/เพิ่มเติม/กิจกรรมพัฒนาผู้เรียน',
                `credit`        DECIMAL(3,1)  NOT NULL DEFAULT 1.5 COMMENT 'หน่วยกิต (กิจกรรม = 0.0)',
                `hours`         SMALLINT UNSIGNED NOT NULL DEFAULT 60 COMMENT 'จำนวนชั่วโมงต่อภาคเรียน (เช่น 20, 40, 60, 80)',
                `grade_level`   TINYINT       NOT NULL DEFAULT 1 COMMENT 'ระดับชั้น ม.1-ม.6 (1-6)',
                `default_semester` TINYINT    NOT NULL DEFAULT 1 COMMENT 'ภาคเรียนมาตรฐาน 1 หรือ 2',
                `is_active`     TINYINT(1)    NOT NULL DEFAULT 1,
                `created_at`    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at`    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY `uq_pp5_subject_code` (`code`),
                INDEX `idx_pp5_subj_dept` (`department_id`),
                INDEX `idx_pp5_subj_grade_type` (`grade_level`, `type`),
                CONSTRAINT `fk_pp5_subj_dept` FOREIGN KEY (`department_id`) REFERENCES `pp5_departments` (`id`) ON DELETE RESTRICT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // ─── 7. ตารางตัวชี้วัด / ผลการเรียนรู้ (pp5_indicators) ───
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `pp5_indicators` (
                `id`          INT AUTO_INCREMENT PRIMARY KEY,
                `subject_id`  INT          NOT NULL COMMENT 'FK -> pp5_subjects.id',
                `code`        VARCHAR(50)  NOT NULL COMMENT 'รหัสตัวชี้วัด เช่น ค 1.1 ม.1/1 หรือ ผลการเรียนรู้ข้อที่ 1',
                `description` TEXT         NOT NULL COMMENT 'คำอธิบายตัวชี้วัดหรือผลการเรียนรู้',
                `sort_order`  INT          NOT NULL DEFAULT 1,
                `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY `uq_pp5_ind_subj_code` (`subject_id`, `code`),
                INDEX `idx_pp5_ind_subj_sort` (`subject_id`, `sort_order`),
                CONSTRAINT `fk_pp5_ind_subject` FOREIGN KEY (`subject_id`) REFERENCES `pp5_subjects` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // ─── 8. ตารางรายวิชาที่เปิดสอนประจำภาคเรียน: 1 วิชา × 1 ห้อง = 1 ปพ.5 (pp5_courses) ───
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `pp5_courses` (
                `id`               INT AUTO_INCREMENT PRIMARY KEY,
                `academic_year`    INT          NOT NULL COMMENT 'ปีการศึกษา พ.ศ. เช่น 2568, 2569',
                `semester`         TINYINT      NOT NULL COMMENT 'ภาคเรียนที่ 1 หรือ 2',
                `subject_id`       INT          NOT NULL COMMENT 'FK -> pp5_subjects.id',
                `classroom_id`     INT          NOT NULL COMMENT 'FK -> pp5_classrooms.id',
                `teacher_user_id`  INT          NOT NULL COMMENT 'ครูผู้สอนหลัก (llw_users.user_id)',
                `att_subject_id`   INT          NULL DEFAULT NULL COMMENT 'เชื่อมกับ att_subjects.id ของระบบเช็คชื่อ',
                `ratio_collect`    TINYINT UNSIGNED NOT NULL DEFAULT 70 COMMENT 'สัดส่วนคะแนนเก็บ (เช่น 70 หรือ 80)',
                `ratio_final`      TINYINT UNSIGNED NOT NULL DEFAULT 30 COMMENT 'สัดส่วนคะแนนปลายภาค (เช่น 30 หรือ 20)',
                `status`           ENUM('draft','submitted','head_approved','deputy_approved','approved','locked') NOT NULL DEFAULT 'draft',
                `rejected_reason`  TEXT         NULL DEFAULT NULL COMMENT 'เหตุผลกรณีถูกตีกลับ',
                `rejected_by`      INT          NULL DEFAULT NULL,
                `submitted_at`     DATETIME     NULL DEFAULT NULL,
                `head_approved_at` DATETIME     NULL DEFAULT NULL,
                `deputy_approved_at` DATETIME   NULL DEFAULT NULL,
                `approved_at`      DATETIME     NULL DEFAULT NULL,
                `locked_at`        DATETIME     NULL DEFAULT NULL,
                `created_at`       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at`       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY `uq_pp5_course_term_subj_cls` (`academic_year`, `semester`, `subject_id`, `classroom_id`),
                INDEX `idx_pp5_course_teacher` (`teacher_user_id`, `academic_year`, `semester`),
                INDEX `idx_pp5_course_status` (`academic_year`, `semester`, `status`),
                INDEX `idx_pp5_course_classroom` (`classroom_id`),
                CONSTRAINT `fk_pp5_course_subject` FOREIGN KEY (`subject_id`) REFERENCES `pp5_subjects` (`id`) ON DELETE RESTRICT,
                CONSTRAINT `fk_pp5_course_classroom` FOREIGN KEY (`classroom_id`) REFERENCES `pp5_classrooms` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // ─── 9. ตารางครูสอนร่วม (pp5_course_teachers) ───
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `pp5_course_teachers` (
                `id`         INT AUTO_INCREMENT PRIMARY KEY,
                `course_id`  INT         NOT NULL COMMENT 'FK -> pp5_courses.id',
                `user_id`    INT         NOT NULL COMMENT 'FK -> llw_users.user_id',
                `role`       ENUM('primary','co_teacher') NOT NULL DEFAULT 'co_teacher',
                `created_at` DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY `uq_pp5_ct_course_user` (`course_id`, `user_id`),
                INDEX `idx_pp5_ct_user` (`user_id`),
                CONSTRAINT `fk_pp5_ct_course` FOREIGN KEY (`course_id`) REFERENCES `pp5_courses` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // ─── 10. ตารางรายการคะแนน / ชิ้นงาน (pp5_score_items) ───
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `pp5_score_items` (
                `id`           INT AUTO_INCREMENT PRIMARY KEY,
                `course_id`    INT           NOT NULL COMMENT 'FK -> pp5_courses.id',
                `phase`        ENUM('before_mid','midterm','after_mid','final') NOT NULL DEFAULT 'before_mid',
                `name`         VARCHAR(150)  NOT NULL COMMENT 'ชื่อชิ้นงาน/การทดสอบ',
                `max_score`    DECIMAL(6,2)  NOT NULL DEFAULT 10.00 COMMENT 'คะแนนเต็มของรายการนี้',
                `indicator_id` INT           NULL DEFAULT NULL COMMENT 'FK -> pp5_indicators.id',
                `sort_order`   INT           NOT NULL DEFAULT 1,
                `created_at`   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at`   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX `idx_pp5_si_course_phase_sort` (`course_id`, `phase`, `sort_order`),
                CONSTRAINT `fk_pp5_si_course` FOREIGN KEY (`course_id`) REFERENCES `pp5_courses` (`id`) ON DELETE CASCADE,
                CONSTRAINT `fk_pp5_si_indicator` FOREIGN KEY (`indicator_id`) REFERENCES `pp5_indicators` (`id`) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // ─── 11. ตารางคะแนนรายบุคคล (pp5_scores) ───
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `pp5_scores` (
                `id`            BIGINT AUTO_INCREMENT PRIMARY KEY,
                `score_item_id` INT          NOT NULL COMMENT 'FK -> pp5_score_items.id',
                `student_id`    INT          NOT NULL COMMENT 'FK -> att_students.id',
                `score`         DECIMAL(6,2) NULL DEFAULT NULL COMMENT 'NULL = ยังไม่ส่งงานหรือขาดสอบ (ติด ร)',
                `updated_by`    INT          NULL DEFAULT NULL,
                `updated_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY `uq_pp5_score_item_student` (`score_item_id`, `student_id`),
                INDEX `idx_pp5_score_student` (`student_id`),
                CONSTRAINT `fk_pp5_score_item` FOREIGN KEY (`score_item_id`) REFERENCES `pp5_score_items` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // ─── 12. ตารางคาบเรียนสำหรับบันทึกเวลาเรียนใน ปพ.5 (pp5_attendance_sessions) ───
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `pp5_attendance_sessions` (
                `id`           INT AUTO_INCREMENT PRIMARY KEY,
                `course_id`    INT          NOT NULL COMMENT 'FK -> pp5_courses.id',
                `session_date` DATE         NOT NULL COMMENT 'วันที่สอน',
                `period`       TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'คาบที่ (1-8)',
                `hours`        DECIMAL(3,1) NOT NULL DEFAULT 1.0 COMMENT 'จำนวนชั่วโมงของคาบนี้',
                `topic`        VARCHAR(255) NULL DEFAULT NULL COMMENT 'หัวข้อการสอน/กิจกรรม',
                `created_by`   INT          NULL DEFAULT NULL,
                `created_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY `uq_pp5_att_sess_course_date_period` (`course_id`, `session_date`, `period`),
                INDEX `idx_pp5_att_sess_date` (`course_id`, `session_date`),
                CONSTRAINT `fk_pp5_att_sess_course` FOREIGN KEY (`course_id`) REFERENCES `pp5_courses` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // ─── 13. ตารางบันทึกการเข้าเรียนรายบุคคลใน ปพ.5 (pp5_attendances) ───
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `pp5_attendances` (
                `id`         BIGINT AUTO_INCREMENT PRIMARY KEY,
                `session_id` INT          NOT NULL COMMENT 'FK -> pp5_attendance_sessions.id',
                `student_id` INT          NOT NULL COMMENT 'FK -> att_students.id',
                `status`     ENUM('present','late','leave','sick','absent') NOT NULL DEFAULT 'present',
                `remark`     VARCHAR(255) NULL DEFAULT NULL,
                `updated_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY `uq_pp5_att_session_student` (`session_id`, `student_id`),
                INDEX `idx_pp5_att_student` (`student_id`),
                CONSTRAINT `fk_pp5_att_session` FOREIGN KEY (`session_id`) REFERENCES `pp5_attendance_sessions` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // ─── 14. ตารางสรุปคะแนนและผลการเรียนประจำรายวิชา (pp5_grades) ───
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `pp5_grades` (
                `id`                 BIGINT AUTO_INCREMENT PRIMARY KEY,
                `course_id`          INT          NOT NULL COMMENT 'FK -> pp5_courses.id',
                `student_id`         INT          NOT NULL COMMENT 'FK -> att_students.id',
                `score_before_mid`   DECIMAL(6,2) NULL DEFAULT 0.00,
                `score_midterm`      DECIMAL(6,2) NULL DEFAULT 0.00,
                `score_after_mid`    DECIMAL(6,2) NULL DEFAULT 0.00,
                `score_collect`      DECIMAL(6,2) NULL DEFAULT 0.00 COMMENT 'รวมคะแนนเก็บ (ก่อนกลางภาค+กลางภาค+หลังกลางภาค)',
                `score_final`        DECIMAL(6,2) NULL DEFAULT 0.00 COMMENT 'คะแนนปลายภาค',
                `total`              INT          NULL DEFAULT 0 COMMENT 'คะแนนรวมสุทธิปัดเป็นจำนวนเต็ม (0-100)',
                `attended_hours`     DECIMAL(5,1) NOT NULL DEFAULT 0.0,
                `total_hours`        DECIMAL(5,1) NOT NULL DEFAULT 0.0,
                `attendance_percent` DECIMAL(5,2) NOT NULL DEFAULT 100.00,
                `has_missing_score`  TINYINT(1)   NOT NULL DEFAULT 0 COMMENT '1 = มีรายการคะแนนเป็น NULL (ติด ร)',
                `grade`              VARCHAR(10)  NOT NULL DEFAULT '' COMMENT '4, 3.5, 3, 2.5, 2, 1.5, 1, 0, ร, มส, ผ, มผ',
                `original_grade`     VARCHAR(10)  NULL DEFAULT NULL COMMENT 'เกรดเดิมก่อนแก้ 0 / ร / มส',
                `is_remedial`        TINYINT(1)   NOT NULL DEFAULT 0 COMMENT '1 = ผ่านการแก้ตัวแล้ว',
                `remedial_date`      DATE         NULL DEFAULT NULL COMMENT 'วันที่บันทึกผลแก้ตัว',
                `characteristic_level` TINYINT    NULL DEFAULT 3 COMMENT 'สรุปคุณลักษณะอันพึงประสงค์ (0-3)',
                `rtw_level`          TINYINT      NULL DEFAULT 3 COMMENT 'สรุปอ่าน คิดวิเคราะห์ และเขียน (0-3)',
                `competency_level`   TINYINT      NULL DEFAULT 3 COMMENT 'สรุปสมรรถนะสำคัญของผู้เรียน (0-3)',
                `remark`             VARCHAR(255) NULL DEFAULT NULL,
                `updated_at`         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY `uq_pp5_grade_course_student` (`course_id`, `student_id`),
                INDEX `idx_pp5_grade_student` (`student_id`),
                INDEX `idx_pp5_grade_val` (`course_id`, `grade`),
                CONSTRAINT `fk_pp5_grade_course` FOREIGN KEY (`course_id`) REFERENCES `pp5_courses` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // ─── 15. ตารางประเมินคุณลักษณะอันพึงประสงค์ 8 ข้อ (pp5_characteristic_assessments) ───
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `pp5_characteristic_assessments` (
                `id`         BIGINT AUTO_INCREMENT PRIMARY KEY,
                `course_id`  INT     NOT NULL COMMENT 'FK -> pp5_courses.id',
                `student_id` INT     NOT NULL COMMENT 'FK -> att_students.id',
                `item_no`    TINYINT UNSIGNED NOT NULL COMMENT 'ข้อที่ 1-8',
                `level`      TINYINT UNSIGNED NOT NULL DEFAULT 3 COMMENT '3=ดีเยี่ยม, 2=ดี, 1=ผ่าน, 0=ไม่ผ่าน',
                `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY `uq_pp5_char_course_stu_item` (`course_id`, `student_id`, `item_no`),
                CONSTRAINT `fk_pp5_char_course` FOREIGN KEY (`course_id`) REFERENCES `pp5_courses` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // ─── 16. ตารางประเมินการอ่าน คิดวิเคราะห์ และเขียน 5 ข้อ (pp5_rtw_assessments) ───
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `pp5_rtw_assessments` (
                `id`         BIGINT AUTO_INCREMENT PRIMARY KEY,
                `course_id`  INT     NOT NULL COMMENT 'FK -> pp5_courses.id',
                `student_id` INT     NOT NULL COMMENT 'FK -> att_students.id',
                `item_no`    TINYINT UNSIGNED NOT NULL COMMENT 'ข้อที่ 1-5',
                `level`      TINYINT UNSIGNED NOT NULL DEFAULT 3 COMMENT '3=ดีเยี่ยม, 2=ดี, 1=ผ่าน, 0=ไม่ผ่าน',
                `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY `uq_pp5_rtw_course_stu_item` (`course_id`, `student_id`, `item_no`),
                CONSTRAINT `fk_pp5_rtw_course` FOREIGN KEY (`course_id`) REFERENCES `pp5_courses` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // ─── 17. ตารางประเมินสมรรถนะสำคัญของผู้เรียน 5 ด้าน (pp5_competency_assessments) ───
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `pp5_competency_assessments` (
                `id`         BIGINT AUTO_INCREMENT PRIMARY KEY,
                `course_id`  INT     NOT NULL COMMENT 'FK -> pp5_courses.id',
                `student_id` INT     NOT NULL COMMENT 'FK -> att_students.id',
                `item_no`    TINYINT UNSIGNED NOT NULL COMMENT 'ด้านที่ 1-5',
                `level`      TINYINT UNSIGNED NOT NULL DEFAULT 3 COMMENT '3=ดีเยี่ยม, 2=ดี, 1=ผ่าน, 0=ไม่ผ่าน',
                `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY `uq_pp5_comp_course_stu_item` (`course_id`, `student_id`, `item_no`),
                CONSTRAINT `fk_pp5_comp_course` FOREIGN KEY (`course_id`) REFERENCES `pp5_courses` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // ─── 18. ตารางประวัติการอนุมัติ / ตีกลับ / ปลดล็อก ปพ.5 (pp5_approvals) ───
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `pp5_approvals` (
                `id`          INT AUTO_INCREMENT PRIMARY KEY,
                `course_id`   INT          NOT NULL COMMENT 'FK -> pp5_courses.id',
                `step`        VARCHAR(50)  NOT NULL COMMENT 'teacher_submit, head_review, deputy_review, director_review, admin_unlock',
                `approver_id` INT          NOT NULL COMMENT 'FK -> llw_users.user_id',
                `action`      ENUM('submit','approve','reject','unlock','lock') NOT NULL,
                `comment`     TEXT         NULL DEFAULT NULL,
                `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX `idx_pp5_app_course` (`course_id`, `created_at`),
                INDEX `idx_pp5_app_approver` (`approver_id`),
                CONSTRAINT `fk_pp5_app_course` FOREIGN KEY (`course_id`) REFERENCES `pp5_courses` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // ─── 19. ตารางบันทึกประวัติการแก้ไขข้อมูล คะแนน และเกรด (pp5_activity_logs) ───
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `pp5_activity_logs` (
                `id`           BIGINT AUTO_INCREMENT PRIMARY KEY,
                `user_id`      INT          NULL DEFAULT NULL COMMENT 'ผู้กระทำ (llw_users.user_id)',
                `user_name`    VARCHAR(150) NOT NULL DEFAULT '',
                `action`       VARCHAR(100) NOT NULL COMMENT 'เช่น score.update, grade.remedial, course.unlock, excel.import',
                `target_type`  VARCHAR(100) NOT NULL DEFAULT '' COMMENT 'เช่น pp5_scores, pp5_grades, pp5_courses',
                `target_id`    INT          NULL DEFAULT NULL,
                `course_id`    INT          NULL DEFAULT NULL,
                `description`  VARCHAR(500) NOT NULL DEFAULT '',
                `old_values`   TEXT         NULL DEFAULT NULL COMMENT 'JSON ค่าเดิม',
                `new_values`   TEXT         NULL DEFAULT NULL COMMENT 'JSON ค่าใหม่',
                `ip_address`   VARCHAR(45)  NOT NULL DEFAULT '',
                `created_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX `idx_pp5_log_course` (`course_id`, `created_at` DESC),
                INDEX `idx_pp5_log_user` (`user_id`, `created_at` DESC),
                INDEX `idx_pp5_log_action` (`action`, `created_at` DESC)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // ─── 20. ตารางแจ้งเตือนในระบบ (pp5_notifications) ───
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `pp5_notifications` (
                `id`         BIGINT AUTO_INCREMENT PRIMARY KEY,
                `user_id`    INT          NOT NULL COMMENT 'ผู้รับแจ้งเตือน (llw_users.user_id)',
                `course_id`  INT          NULL DEFAULT NULL,
                `title`      VARCHAR(200) NOT NULL,
                `message`    TEXT         NOT NULL,
                `type`       ENUM('info','success','warning','danger') NOT NULL DEFAULT 'info',
                `link`       VARCHAR(255) NOT NULL DEFAULT '/pp5/index.php',
                `is_read`    TINYINT(1)   NOT NULL DEFAULT 0,
                `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX `idx_pp5_notif_user_read` (`user_id`, `is_read`, `created_at` DESC)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    },

    'down' => function (PDO $pdo) {
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
        $tables = [
            'pp5_notifications',
            'pp5_activity_logs',
            'pp5_approvals',
            'pp5_competency_assessments',
            'pp5_rtw_assessments',
            'pp5_characteristic_assessments',
            'pp5_grades',
            'pp5_attendances',
            'pp5_attendance_sessions',
            'pp5_scores',
            'pp5_score_items',
            'pp5_course_teachers',
            'pp5_courses',
            'pp5_indicators',
            'pp5_subjects',
            'pp5_classroom_students',
            'pp5_classrooms',
            'pp5_departments',
            'pp5_grade_criteria',
            'pp5_school_settings',
        ];
        foreach ($tables as $t) {
            $pdo->exec("DROP TABLE IF EXISTS `{$t}`");
        }
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    },
];
