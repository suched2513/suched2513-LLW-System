<?php
return [
    'up' => function (PDO $pdo) {
        // Essay exam answers (pre/post)
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS lms_student_exam_answers (
                id               INT AUTO_INCREMENT PRIMARY KEY,
                student_uid      INT NOT NULL,
                subject_id       INT NOT NULL,
                exam_type        ENUM('pre','post') NOT NULL,
                exam_record_id   INT NOT NULL,
                question_id      INT NOT NULL,
                answer_text      TEXT NOT NULL,
                teacher_score    TINYINT  NULL DEFAULT NULL,
                teacher_feedback TEXT     NULL DEFAULT NULL,
                reviewed_at      DATETIME NULL DEFAULT NULL,
                created_at       DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_student_subj  (student_uid, subject_id),
                INDEX idx_exam_record   (exam_type, exam_record_id),
                INDEX idx_question      (question_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        // Grade + feedback on exercises
        $cols = $pdo->query("SHOW COLUMNS FROM lms_student_exercises LIKE 'grade'")->fetchAll();
        if (empty($cols)) {
            $pdo->exec("ALTER TABLE lms_student_exercises
                ADD COLUMN grade        TINYINT  NULL DEFAULT NULL AFTER answer_text,
                ADD COLUMN feedback     TEXT     NULL DEFAULT NULL AFTER grade,
                ADD COLUMN reviewed_at  DATETIME NULL DEFAULT NULL AFTER feedback
            ");
        }
    },
    'down' => function (PDO $pdo) {
        $pdo->exec("DROP TABLE IF EXISTS lms_student_exam_answers");
        $cols = $pdo->query("SHOW COLUMNS FROM lms_student_exercises LIKE 'grade'")->fetchAll();
        if (!empty($cols)) {
            $pdo->exec("ALTER TABLE lms_student_exercises
                DROP COLUMN grade,
                DROP COLUMN feedback,
                DROP COLUMN reviewed_at
            ");
        }
    },
];
