<?php
return [
    'up' => function (PDO $pdo) {
        // Extend role ENUM in llw_users to support bus roles
        try {
            $pdo->exec("ALTER TABLE llw_users MODIFY COLUMN role
                ENUM('super_admin','wfh_admin','wfh_staff','cb_admin','att_teacher','bus_admin','bus_finance')
                NOT NULL DEFAULT 'wfh_staff'");
        } catch (PDOException $e) {
            error_log('[bus migration] role ENUM alter: ' . $e->getMessage());
        }

        $pdo->exec("CREATE TABLE IF NOT EXISTS bus_students (
            id                 INT AUTO_INCREMENT PRIMARY KEY,
            student_id         VARCHAR(20)  NOT NULL,
            fullname           VARCHAR(200) NOT NULL,
            classroom          VARCHAR(20)  NOT NULL DEFAULT '',
            national_id_hash   VARCHAR(255) NOT NULL COMMENT 'bcrypt of 13-digit national ID',
            national_id_masked VARCHAR(20)  NOT NULL COMMENT 'e.g. 1-2345-xxxxx-xx-9',
            phone              VARCHAR(20)  NULL,
            parent_phone       VARCHAR(20)  NULL,
            is_active          TINYINT(1)   NOT NULL DEFAULT 1,
            created_at         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uk_student_id (student_id),
            INDEX idx_classroom (classroom)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS bus_routes (
            id           INT AUTO_INCREMENT PRIMARY KEY,
            route_code   VARCHAR(20)  NOT NULL,
            route_name   VARCHAR(200) NOT NULL,
            description  TEXT         NULL,
            price        DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            seats        INT          NOT NULL DEFAULT 0 COMMENT '0 = unlimited',
            driver_name  VARCHAR(200) NULL,
            driver_phone VARCHAR(20)  NULL,
            is_active    TINYINT(1)   NOT NULL DEFAULT 1,
            created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uk_route_code (route_code)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS bus_registrations (
            id            INT AUTO_INCREMENT PRIMARY KEY,
            student_id    INT          NOT NULL,
            route_id      INT          NOT NULL,
            semester      VARCHAR(10)  NOT NULL,
            status        ENUM('active','cancelled','pending_cancel') NOT NULL DEFAULT 'active',
            registered_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            cancelled_at  DATETIME     NULL,
            notes         TEXT         NULL,
            UNIQUE KEY uk_student_semester (student_id, semester),
            INDEX idx_route_id (route_id),
            INDEX idx_status (status),
            INDEX idx_semester (semester)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // bus_payments: columns must match PHP code usage
        // (paid_at, note, recorded_by — NOT payment_date/received_by)
        $pdo->exec("CREATE TABLE IF NOT EXISTS bus_payments (
            id              INT AUTO_INCREMENT PRIMARY KEY,
            registration_id INT          NOT NULL,
            amount          DECIMAL(10,2) NOT NULL,
            paid_at         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            note            TEXT         NULL,
            recorded_by     INT          NULL COMMENT 'llw_users.user_id',
            INDEX idx_registration_id (registration_id),
            INDEX idx_paid_at (paid_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // bus_cancel_requests: columns must match PHP code usage
        // (created_at, admin_note, reviewed_by, reviewed_at — NOT requested_at/process_note)
        $pdo->exec("CREATE TABLE IF NOT EXISTS bus_cancel_requests (
            id              INT AUTO_INCREMENT PRIMARY KEY,
            registration_id INT          NOT NULL,
            reason          TEXT         NULL,
            status          ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
            admin_note      TEXT         NULL,
            reviewed_by     INT          NULL COMMENT 'llw_users.user_id',
            reviewed_at     DATETIME     NULL,
            created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_registration_id (registration_id),
            INDEX idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    },
    'down' => function (PDO $pdo) {
        $pdo->exec("DROP TABLE IF EXISTS bus_cancel_requests");
        $pdo->exec("DROP TABLE IF EXISTS bus_payments");
        $pdo->exec("DROP TABLE IF EXISTS bus_registrations");
        $pdo->exec("DROP TABLE IF EXISTS bus_routes");
        $pdo->exec("DROP TABLE IF EXISTS bus_students");
    },
];
