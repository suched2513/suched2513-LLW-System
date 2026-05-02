<?php
return [
    'up' => function (PDO $pdo) {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS stl_requests (
                id            INT AUTO_INCREMENT PRIMARY KEY,
                student_id    VARCHAR(20) NOT NULL,
                leave_type    ENUM('sick','personal','other') NOT NULL,
                date_from     DATE NOT NULL,
                date_to       DATE NOT NULL,
                days          INT NOT NULL DEFAULT 1,
                reason        TEXT NOT NULL,
                parent_name   VARCHAR(100) NULL,
                parent_phone  VARCHAR(20) NULL,
                status        ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
                teacher_id    INT NULL,
                teacher_note  VARCHAR(500) NULL,
                approved_at   DATETIME NULL,
                created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_student (student_id),
                INDEX idx_status (status),
                INDEX idx_date (date_from)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS stl_attachments (
                id          INT AUTO_INCREMENT PRIMARY KEY,
                request_id  INT NOT NULL,
                file_path   VARCHAR(255) NOT NULL,
                file_name   VARCHAR(255) NOT NULL,
                file_size   BIGINT NOT NULL,
                created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_request (request_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    },
    'down' => function (PDO $pdo) {
        $pdo->exec("DROP TABLE IF EXISTS stl_attachments");
        $pdo->exec("DROP TABLE IF EXISTS stl_requests");
    },
];
