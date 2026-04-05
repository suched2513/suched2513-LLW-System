<?php
/**
 * Migration: สร้างตารางทั้งหมดของระบบ LLW (initial schema)
 * Created: 2026-04-05
 *
 * ตาราง: llw_users, wfh_*, cb_*, att_*, _migrations
 */
return [
    'up' => function (PDO $pdo) {

        // ── 1. llw_users (Central Auth) ───────────────────────
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS llw_users (
                user_id     INT AUTO_INCREMENT PRIMARY KEY,
                username    VARCHAR(100) NOT NULL UNIQUE,
                password    VARCHAR(255) NOT NULL,
                firstname   VARCHAR(100) NOT NULL DEFAULT '',
                lastname    VARCHAR(100) NOT NULL DEFAULT '',
                role        ENUM('super_admin','wfh_admin','wfh_staff','cb_admin','att_teacher') NOT NULL DEFAULT 'wfh_staff',
                status      ENUM('active','inactive') NOT NULL DEFAULT 'active',
                last_login  DATETIME NULL,
                created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // ── 2. WFH Module ──────────────────────────────��──────
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS wfh_departments (
                dept_id   INT AUTO_INCREMENT PRIMARY KEY,
                dept_name VARCHAR(200) NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS wfh_users (
                user_id   INT AUTO_INCREMENT PRIMARY KEY,
                username  VARCHAR(100) NOT NULL UNIQUE,
                password  VARCHAR(255) NOT NULL,
                firstname VARCHAR(100) NOT NULL DEFAULT '',
                lastname  VARCHAR(100) NOT NULL DEFAULT '',
                position  VARCHAR(200) DEFAULT '',
                dept_id   INT DEFAULT 0,
                role      ENUM('admin','user') NOT NULL DEFAULT 'user',
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS wfh_timelogs (
                log_id          INT AUTO_INCREMENT PRIMARY KEY,
                user_id         INT NOT NULL,
                log_date        DATE NOT NULL,
                check_in_time   TIME NULL,
                check_out_time  TIME NULL,
                check_in_status VARCHAR(50) DEFAULT 'ปกติ',
                check_in_lat    DECIMAL(10,7) NULL,
                check_in_lng    DECIMAL(10,7) NULL,
                check_in_photo  VARCHAR(500) NULL,
                check_out_lat   DECIMAL(10,7) NULL,
                check_out_lng   DECIMAL(10,7) NULL,
                check_out_photo VARCHAR(500) NULL,
                created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS wfh_system_settings (
                setting_id      INT AUTO_INCREMENT PRIMARY KEY,
                regular_time_in TIME NOT NULL DEFAULT '08:00:00',
                late_time       TIME NOT NULL DEFAULT '08:30:00',
                school_lat      DECIMAL(10,7) NOT NULL DEFAULT 0,
                school_lng      DECIMAL(10,7) NOT NULL DEFAULT 0,
                geofence_radius INT NOT NULL DEFAULT 200,
                telegram_token  VARCHAR(255) DEFAULT '',
                admin_chat_id   VARCHAR(100) DEFAULT ''
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // ── 3. Chromebook Module ──────────────────────────────
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS cb_chromebooks (
                chromebook_id INT AUTO_INCREMENT PRIMARY KEY,
                model         VARCHAR(200) DEFAULT '',
                serial_number VARCHAR(200) NOT NULL UNIQUE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS cb_teachers (
                teacher_id INT AUTO_INCREMENT PRIMARY KEY,
                name       VARCHAR(200) NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS cb_students (
                student_id INT AUTO_INCREMENT PRIMARY KEY,
                name       VARCHAR(200) NOT NULL,
                class_name VARCHAR(100) DEFAULT ''
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS cb_borrow_logs (
                entry_id          INT AUTO_INCREMENT PRIMARY KEY,
                borrower_type     VARCHAR(50) NOT NULL DEFAULT 'teacher',
                borrower_id       INT NOT NULL,
                class_name        VARCHAR(100) DEFAULT '',
                chromebook_id     INT NULL,
                chromebook_serial VARCHAR(200) DEFAULT '',
                images            VARCHAR(1000) DEFAULT '',
                status            ENUM('Borrowed','Returned') NOT NULL DEFAULT 'Borrowed',
                date_borrowed     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                date_returned     DATETIME NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS cb_inspections (
                id               INT AUTO_INCREMENT PRIMARY KEY,
                borrow_log_id    INT NOT NULL,
                condition_status VARCHAR(100) DEFAULT '',
                notes            VARCHAR(500) DEFAULT '',
                images           VARCHAR(1000) DEFAULT '',
                inspected_date   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // ── 4. Attendance Module ──────────────────────────────
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS att_teachers (
                id          INT AUTO_INCREMENT PRIMARY KEY,
                name        VARCHAR(200) NOT NULL,
                username    VARCHAR(100) DEFAULT '',
                password    VARCHAR(255) DEFAULT '',
                llw_user_id INT DEFAULT 0
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS att_students (
                id         INT AUTO_INCREMENT PRIMARY KEY,
                student_id VARCHAR(50) NOT NULL,
                name       VARCHAR(200) NOT NULL,
                classroom  VARCHAR(100) DEFAULT ''
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS att_subjects (
                id           INT AUTO_INCREMENT PRIMARY KEY,
                subject_code VARCHAR(50) NOT NULL,
                subject_name VARCHAR(200) NOT NULL,
                classroom    VARCHAR(100) DEFAULT '',
                teacher_id   INT NOT NULL DEFAULT 0
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS att_attendance (
                id         INT AUTO_INCREMENT PRIMARY KEY,
                date       DATE NOT NULL,
                period     INT NOT NULL DEFAULT 1,
                subject_id INT NOT NULL DEFAULT 0,
                teacher_id INT NOT NULL DEFAULT 0,
                student_id VARCHAR(50) NOT NULL,
                status     VARCHAR(20) NOT NULL DEFAULT 'มา',
                time_in    TIME NULL,
                note       VARCHAR(500) DEFAULT '',
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // ── 5. Leave Requests ─────────────────────────────────
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS leave_requests (
                r_id         INT AUTO_INCREMENT PRIMARY KEY,
                teacher_id   INT NOT NULL,
                reason       VARCHAR(500) NOT NULL,
                time_start   TIME NOT NULL,
                time_end     TIME NOT NULL,
                has_class    TINYINT(1) NOT NULL DEFAULT 0,
                status_boss1 TINYINT NOT NULL DEFAULT 0,
                created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS leave_request_details (
                id              INT AUTO_INCREMENT PRIMARY KEY,
                r_id            INT NOT NULL,
                period          VARCHAR(50) NOT NULL,
                subject         VARCHAR(200) NOT NULL,
                class_level     VARCHAR(100) NOT NULL,
                sub_teacher_id  INT NOT NULL,
                created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    },

    'down' => function (PDO $pdo) {
        $tables = [
            'leave_request_details', 'leave_requests',
            'att_attendance', 'att_subjects', 'att_students', 'att_teachers',
            'cb_inspections', 'cb_borrow_logs', 'cb_students', 'cb_teachers', 'cb_chromebooks',
            'wfh_system_settings', 'wfh_timelogs', 'wfh_users', 'wfh_departments',
            'llw_users',
        ];
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
        foreach ($tables as $t) {
            $pdo->exec("DROP TABLE IF EXISTS `$t`");
        }
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    },
];
