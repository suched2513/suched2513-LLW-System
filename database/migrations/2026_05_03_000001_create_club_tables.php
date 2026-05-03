<?php
/**
 * Migration: สร้างตารางระบบชุมนุม (Club Management System)
 * Created: 2026-05-03
 */
return [
    'up' => function (PDO $pdo) {
        // club_groups
        $pdo->exec("CREATE TABLE IF NOT EXISTS club_groups (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(200) NOT NULL,
            description TEXT,
            objectives TEXT,
            teacher_id INT NULL,
            room VARCHAR(100),
            max_capacity INT NOT NULL DEFAULT 30,
            semester TINYINT NOT NULL DEFAULT 1,
            year INT NOT NULL,
            status ENUM('draft','open','closed','archived') NOT NULL DEFAULT 'draft',
            pass_threshold TINYINT NOT NULL DEFAULT 80,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_teacher(teacher_id),
            INDEX idx_status(status),
            INDEX idx_year_sem(year,semester)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // club_registrations
        $pdo->exec("CREATE TABLE IF NOT EXISTS club_registrations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            student_id VARCHAR(20) NOT NULL,
            club_id INT NOT NULL,
            semester TINYINT NOT NULL,
            year INT NOT NULL,
            registered_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            changed_at DATETIME NULL,
            UNIQUE KEY uq_student_sem(student_id, semester, year),
            INDEX idx_club(club_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // club_sessions
        $pdo->exec("CREATE TABLE IF NOT EXISTS club_sessions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            club_id INT NOT NULL,
            session_date DATE NOT NULL,
            period VARCHAR(50),
            topic VARCHAR(200),
            description TEXT,
            status ENUM('planned','done','cancelled') NOT NULL DEFAULT 'planned',
            created_by INT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_club(club_id),
            INDEX idx_date(session_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // club_activity_logs
        $pdo->exec("CREATE TABLE IF NOT EXISTS club_activity_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            session_id INT NOT NULL,
            content TEXT,
            photo_paths TEXT,
            logged_by INT NULL,
            logged_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_session(session_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // club_attendance
        $pdo->exec("CREATE TABLE IF NOT EXISTS club_attendance (
            id INT AUTO_INCREMENT PRIMARY KEY,
            session_id INT NOT NULL,
            student_id VARCHAR(20) NOT NULL,
            status ENUM('present','absent','late','leave') NOT NULL DEFAULT 'absent',
            note VARCHAR(200),
            checked_by INT NULL,
            checked_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_att(session_id, student_id),
            INDEX idx_student(student_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // club_results
        $pdo->exec("CREATE TABLE IF NOT EXISTS club_results (
            id INT AUTO_INCREMENT PRIMARY KEY,
            student_id VARCHAR(20) NOT NULL,
            club_id INT NOT NULL,
            semester TINYINT NOT NULL,
            year INT NOT NULL,
            total_sessions INT NOT NULL DEFAULT 0,
            attended_sessions INT NOT NULL DEFAULT 0,
            attendance_pct DECIMAL(5,2) NOT NULL DEFAULT 0,
            result ENUM('pass','fail','pending') NOT NULL DEFAULT 'pending',
            teacher_comment VARCHAR(500),
            finalized_by INT NULL,
            finalized_at DATETIME NULL,
            UNIQUE KEY uq_result(student_id, club_id, semester, year),
            INDEX idx_club(club_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // club_settings
        $pdo->exec("CREATE TABLE IF NOT EXISTS club_settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            semester TINYINT NOT NULL,
            year INT NOT NULL,
            reg_open DATETIME NULL,
            reg_close DATETIME NULL,
            allow_change TINYINT(1) NOT NULL DEFAULT 1,
            is_active TINYINT(1) NOT NULL DEFAULT 0,
            UNIQUE KEY uq_sem_year(semester, year)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    },
    'down' => function (PDO $pdo) {
        foreach (['club_results', 'club_attendance', 'club_activity_logs', 'club_sessions', 'club_registrations', 'club_groups', 'club_settings'] as $t) {
            $pdo->exec("DROP TABLE IF EXISTS $t");
        }
    },
];
