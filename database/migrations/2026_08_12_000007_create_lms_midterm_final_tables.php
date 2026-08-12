<?php
return [
    'up' => function (PDO $pdo) {
        foreach (['lms_midterm_questions', 'lms_final_questions'] as $tbl) {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS `{$tbl}` (
                    id             INT AUTO_INCREMENT PRIMARY KEY,
                    question_text  TEXT NOT NULL,
                    question_type  ENUM('choice','multi_choice','true_false','fill_blank','matching','ordering','text','upload')
                                       NOT NULL DEFAULT 'choice',
                    question_img   VARCHAR(255) DEFAULT NULL,
                    choice1        TEXT NOT NULL,
                    choice1_img    VARCHAR(255) DEFAULT NULL,
                    choice2        TEXT NOT NULL,
                    choice2_img    VARCHAR(255) DEFAULT NULL,
                    choice3        TEXT NOT NULL,
                    choice3_img    VARCHAR(255) DEFAULT NULL,
                    choice4        TEXT NOT NULL,
                    choice4_img    VARCHAR(255) DEFAULT NULL,
                    correct_answer TINYINT NULL DEFAULT NULL COMMENT '1-4, ใช้เฉพาะ choice/true_false',
                    options_json   TEXT NULL DEFAULT NULL,
                    correct_json   TEXT NULL DEFAULT NULL,
                    subject_id     INT NOT NULL,
                    created_at     DATETIME DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_subject (subject_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        }

        foreach (['lms_student_midterm_exam', 'lms_student_final_exam'] as $tbl) {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS `{$tbl}` (
                    id          INT AUTO_INCREMENT PRIMARY KEY,
                    student_uid INT NOT NULL,
                    subject_id  INT NOT NULL,
                    score       INT NOT NULL DEFAULT 0,
                    total       INT NOT NULL DEFAULT 0,
                    passed      TINYINT(1) NOT NULL DEFAULT 0,
                    attempt_no  INT NOT NULL DEFAULT 1,
                    taken_at    DATETIME DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_student (student_uid),
                    INDEX idx_subject (subject_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        }
    },
    'down' => function (PDO $pdo) {
        foreach ([
            'lms_student_final_exam', 'lms_student_midterm_exam',
            'lms_final_questions', 'lms_midterm_questions',
        ] as $tbl) {
            $pdo->exec("DROP TABLE IF EXISTS `{$tbl}`");
        }
    },
];
