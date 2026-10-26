<?php
return [
    'up' => function (PDO $pdo) {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS learning_media (
                id           INT AUTO_INCREMENT PRIMARY KEY,
                user_id      INT NULL,
                media_date   DATE NOT NULL,
                author       VARCHAR(150) NOT NULL,
                grade        VARCHAR(50) NOT NULL,
                subject      VARCHAR(100) NOT NULL,
                media_name   VARCHAR(255) NOT NULL,
                media_link   TEXT NOT NULL,
                cover_url    TEXT NULL,
                views        INT NOT NULL DEFAULT 0,
                status       ENUM('active','inactive') NOT NULL DEFAULT 'active',
                created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_media_date (media_date),
                INDEX idx_grade (grade),
                INDEX idx_subject (subject),
                INDEX idx_user_id (user_id),
                INDEX idx_views (views)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    },
    'down' => function (PDO $pdo) {
        $pdo->exec("DROP TABLE IF EXISTS learning_media");
    },
];
