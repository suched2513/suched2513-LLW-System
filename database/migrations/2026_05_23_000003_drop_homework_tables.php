<?php
return [
    'up' => function (PDO $pdo) {
        $pdo->exec("DROP TABLE IF EXISTS hw_submissions");
        $pdo->exec("DROP TABLE IF EXISTS hw_assignments");
        $pdo->exec("DROP TABLE IF EXISTS hw_subjects");
    },
    'down' => function (PDO $pdo) {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS hw_subjects (
                id         INT AUTO_INCREMENT PRIMARY KEY,
                name       VARCHAR(200) NOT NULL,
                teacher_id INT NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_teacher (teacher_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS hw_assignments (
                id           INT AUTO_INCREMENT PRIMARY KEY,
                teacher_id   INT NOT NULL,
                subject_id   INT NULL,
                title        VARCHAR(500) NOT NULL,
                description  TEXT NULL,
                classrooms   TEXT NULL,
                due_date     DATETIME NULL,
                max_score    SMALLINT UNSIGNED NULL,
                status       ENUM('active','closed') NOT NULL DEFAULT 'active',
                created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_teacher (teacher_id),
                INDEX idx_subject (subject_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS hw_submissions (
                id               INT AUTO_INCREMENT PRIMARY KEY,
                assignment_id    INT NOT NULL,
                student_uid      INT NOT NULL,
                student_code     VARCHAR(50) NULL,
                student_name     VARCHAR(200) NULL,
                classroom        VARCHAR(50) NULL,
                content          TEXT NULL,
                file_paths       JSON NULL,
                status           ENUM('submitted','late') NOT NULL DEFAULT 'submitted',
                score            SMALLINT UNSIGNED NULL,
                teacher_comment  TEXT NULL,
                reviewed_at      DATETIME NULL,
                submitted_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_assignment (assignment_id),
                INDEX idx_student (student_uid)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    },
];
