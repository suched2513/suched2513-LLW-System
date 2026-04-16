<?php
/**
 * Migration: Create Teacher Leave System Tables
 * Created: 2026-04-14
 */
return [
    'up' => function (PDO $pdo) {
        // 1. Leave Requests
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS tl_requests (
                id              INT AUTO_INCREMENT PRIMARY KEY,
                user_id         INT NOT NULL,
                leave_type      VARCHAR(50) NOT NULL COMMENT 'sick, personal, vacation, other',
                reason          TEXT,
                date_start      DATE NOT NULL,
                date_end        DATE NOT NULL,
                days_count      DECIMAL(4,1) NOT NULL DEFAULT 0.0,
                contact_info    TEXT,
                signature_path  VARCHAR(500) NULL,
                status          ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
                fiscal_year     INT NOT NULL,
                level_at        TINYINT NOT NULL DEFAULT 1 COMMENT 'Current approval level (1, 2, 3)',
                created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX (user_id),
                INDEX (fiscal_year),
                INDEX (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // 2. Leave Stats (Per Fiscal Year)
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS tl_stats (
                id              INT AUTO_INCREMENT PRIMARY KEY,
                user_id         INT NOT NULL,
                fiscal_year     INT NOT NULL,
                sick_taken      DECIMAL(4,1) NOT NULL DEFAULT 0.0,
                personal_taken  DECIMAL(4,1) NOT NULL DEFAULT 0.0,
                vacation_taken  DECIMAL(4,1) NOT NULL DEFAULT 0.0,
                other_taken     DECIMAL(4,1) NOT NULL DEFAULT 0.0,
                vacation_quota  DECIMAL(4,1) NOT NULL DEFAULT 10.0,
                UNIQUE KEY user_fy (user_id, fiscal_year)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // 3. Approval Logs
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS tl_approvals (
                id              INT AUTO_INCREMENT PRIMARY KEY,
                request_id      INT NOT NULL,
                level           TINYINT NOT NULL COMMENT '1=Staff, 2=Dept Head, 3=Director',
                approver_id     INT NOT NULL,
                status          TINYINT NOT NULL DEFAULT 0 COMMENT '0=Pending, 1=Approved, 2=Rejected',
                comment         TEXT,
                approved_at     DATETIME NULL,
                created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX (request_id),
                INDEX (approver_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // 4. Holidays
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS tl_holidays (
                id              INT AUTO_INCREMENT PRIMARY KEY,
                holiday_date    DATE NOT NULL UNIQUE,
                name            VARCHAR(255) NOT NULL,
                created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // 5. Seed initial holidays for 2026 (Example)
        $holidays = [
            ['2026-01-01', 'วันขึ้นปีใหม่'],
            ['2026-04-13', 'วันสงกรานต์'],
            ['2026-04-14', 'วันสงกรานต์'],
            ['2026-04-15', 'วันสงกรานต์'],
            ['2026-05-01', 'วันแรงงานแห่งชาติ'],
            ['2026-07-28', 'วันเฉลิมพระชนมพรรษา ร.10'],
            ['2026-08-12', 'วันแม่แห่งชาติ'],
            ['2026-10-13', 'วันคล้ายวันสวรรคต ร.9'],
            ['2026-10-23', 'วันปิยมหาราช'],
            ['2026-12-05', 'วันพ่อแห่งชาติ'],
            ['2026-12-10', 'วันรัฐธรรมนูญ'],
            ['2026-12-31', 'วันสิ้นปี']
        ];
        $stmt = $pdo->prepare("INSERT IGNORE INTO tl_holidays (holiday_date, name) VALUES (?, ?)");
        foreach ($holidays as $h) {
            $stmt->execute($h);
        }
    },

    'down' => function (PDO $pdo) {
        $pdo->exec("DROP TABLE IF EXISTS tl_approvals");
        $pdo->exec("DROP TABLE IF EXISTS tl_stats");
        $pdo->exec("DROP TABLE IF EXISTS tl_requests");
        $pdo->exec("DROP TABLE IF EXISTS tl_holidays");
    },
];
