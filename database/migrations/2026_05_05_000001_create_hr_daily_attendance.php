<?php
return [
    'up' => function (PDO $pdo) {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS hr_daily_attendance (
                id           INT AUTO_INCREMENT PRIMARY KEY,
                log_id       INT NOT NULL,
                student_id   VARCHAR(50) NOT NULL,
                student_name VARCHAR(200) NOT NULL,
                status       ENUM('มา','ขาด','สาย','ลา','โดด') NOT NULL DEFAULT 'มา',
                note         VARCHAR(255) NULL,
                created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_log_student (log_id, student_id),
                INDEX idx_log_id (log_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    },
    'down' => function (PDO $pdo) {
        $pdo->exec("DROP TABLE IF EXISTS hr_daily_attendance");
    },
];
