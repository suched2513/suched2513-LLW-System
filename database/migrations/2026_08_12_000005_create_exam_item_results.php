<?php
return [
    'up' => function (PDO $pdo) {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS lms_exam_item_results (
                id             INT AUTO_INCREMENT PRIMARY KEY,
                student_uid    INT NOT NULL,
                subject_id     INT NOT NULL,
                exam_type      ENUM('pre','post') NOT NULL,
                exam_record_id INT NOT NULL,
                question_id    INT NOT NULL,
                is_correct     TINYINT(1) NOT NULL,
                created_at     DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_subject_exam (subject_id, exam_type),
                INDEX idx_question (question_id),
                INDEX idx_student (student_uid)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    },
    'down' => function (PDO $pdo) {
        $pdo->exec("DROP TABLE IF EXISTS lms_exam_item_results");
    },
];
