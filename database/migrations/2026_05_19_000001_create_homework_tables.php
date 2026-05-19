<?php
return [
    'up' => function (PDO $pdo) {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS hw_assignments (
                id          INT AUTO_INCREMENT PRIMARY KEY,
                title       VARCHAR(255) NOT NULL,
                description TEXT,
                subject     VARCHAR(100) DEFAULT '',
                classroom   VARCHAR(255) DEFAULT '',
                due_date    DATETIME NOT NULL,
                max_score   TINYINT UNSIGNED NOT NULL DEFAULT 10,
                teacher_id  INT NOT NULL DEFAULT 0,
                status      ENUM('draft','published') NOT NULL DEFAULT 'published',
                created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_teacher (teacher_id),
                INDEX idx_status (status),
                INDEX idx_due (due_date)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS hw_submissions (
                id              INT AUTO_INCREMENT PRIMARY KEY,
                assignment_id   INT NOT NULL,
                student_uid     INT NOT NULL,
                student_code    VARCHAR(20) NOT NULL DEFAULT '',
                student_name    VARCHAR(200) NOT NULL DEFAULT '',
                classroom       VARCHAR(50) NOT NULL DEFAULT '',
                content         TEXT,
                submitted_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                status          ENUM('submitted','late') NOT NULL DEFAULT 'submitted',
                score           TINYINT UNSIGNED DEFAULT NULL,
                teacher_comment TEXT DEFAULT NULL,
                reviewed_at     DATETIME DEFAULT NULL,
                UNIQUE KEY uq_sub (assignment_id, student_uid),
                INDEX idx_assignment (assignment_id),
                INDEX idx_student (student_uid)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    },
    'down' => function (PDO $pdo) {
        $pdo->exec("DROP TABLE IF EXISTS hw_submissions");
        $pdo->exec("DROP TABLE IF EXISTS hw_assignments");
    },
];
