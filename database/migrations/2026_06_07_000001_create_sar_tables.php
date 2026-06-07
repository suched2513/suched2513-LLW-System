<?php
return [
    'up' => function (PDO $pdo) {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS sar_reports (
                id           INT AUTO_INCREMENT PRIMARY KEY,
                teacher_id   INT NOT NULL,
                teacher_name VARCHAR(200) NOT NULL DEFAULT '',
                year         VARCHAR(10)  NOT NULL DEFAULT '',
                semester     TINYINT      NOT NULL DEFAULT 1,
                form_data    LONGTEXT     NOT NULL DEFAULT '{}',
                status       ENUM('draft','submitted') NOT NULL DEFAULT 'draft',
                created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_teacher (teacher_id),
                INDEX idx_year_sem (year, semester)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    },
    'down' => function (PDO $pdo) {
        $pdo->exec("DROP TABLE IF EXISTS sar_reports");
    },
];
