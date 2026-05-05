<?php
return [
    'up' => function (PDO $pdo) {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS hr_daily_logs (
                id              INT AUTO_INCREMENT PRIMARY KEY,
                teacher_id      INT NOT NULL COMMENT 'llw_users.user_id',
                classroom       VARCHAR(50) NOT NULL COMMENT 'ม.1/1',
                log_date        DATE NOT NULL,
                academic_year   INT NOT NULL,
                semester        TINYINT NOT NULL DEFAULT 1,

                total_students  INT DEFAULT 0,
                present_count   INT DEFAULT 0,
                absent_count    INT DEFAULT 0,
                late_count      INT DEFAULT 0,
                leave_count     INT DEFAULT 0,
                att_synced      TINYINT DEFAULT 0 COMMENT '1=ดึงจาก att_attendance',

                activities      TEXT NULL COMMENT 'JSON array of {name,desc}',
                notes           TEXT NULL,

                status          ENUM('draft','submitted','approved','revision') NOT NULL DEFAULT 'draft',
                submitted_at    DATETIME NULL,
                reviewed_by     INT NULL,
                reviewed_at     DATETIME NULL,
                review_note     TEXT NULL,

                created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                UNIQUE  KEY uq_classroom_date  (classroom, log_date),
                INDEX       idx_teacher_date   (teacher_id, log_date),
                INDEX       idx_log_date       (log_date),
                INDEX       idx_status         (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS hr_daily_photos (
                id              INT AUTO_INCREMENT PRIMARY KEY,
                log_id          INT NOT NULL,
                filename        VARCHAR(255) NOT NULL,
                original_name   VARCHAR(255) NULL,
                caption         VARCHAR(255) NULL,
                order_no        INT DEFAULT 0,
                created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_log_id (log_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    },
    'down' => function (PDO $pdo) {
        $pdo->exec("DROP TABLE IF EXISTS hr_daily_photos");
        $pdo->exec("DROP TABLE IF EXISTS hr_daily_logs");
    },
];
