<?php
return [
    'up' => function (PDO $pdo) {

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS lms_units (
                id         INT AUTO_INCREMENT PRIMARY KEY,
                order_no   INT NOT NULL DEFAULT 1,
                unit_name  VARCHAR(255) NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_order (order_no)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS lms_unit_exercises (
                id             INT AUTO_INCREMENT PRIMARY KEY,
                unit_id        INT NOT NULL,
                exercise_title VARCHAR(255) NOT NULL,
                INDEX idx_unit (unit_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS lms_topics (
                id         INT AUTO_INCREMENT PRIMARY KEY,
                unit_id    INT NOT NULL,
                order_no   INT NOT NULL DEFAULT 1,
                topic_name VARCHAR(255) NOT NULL,
                content    TEXT DEFAULT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_unit (unit_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS lms_topic_links (
                id          INT AUTO_INCREMENT PRIMARY KEY,
                topic_id    INT NOT NULL,
                url         TEXT NOT NULL,
                link_label  VARCHAR(255) DEFAULT NULL,
                INDEX idx_topic (topic_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS lms_topic_youtube (
                id          INT AUTO_INCREMENT PRIMARY KEY,
                topic_id    INT NOT NULL,
                url         TEXT NOT NULL,
                video_label VARCHAR(255) DEFAULT NULL,
                INDEX idx_topic (topic_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS lms_topic_files (
                id            INT AUTO_INCREMENT PRIMARY KEY,
                topic_id      INT NOT NULL,
                filename      VARCHAR(255) NOT NULL,
                original_name VARCHAR(255) NOT NULL,
                created_at    DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_topic (topic_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS lms_pre_questions (
                id             INT AUTO_INCREMENT PRIMARY KEY,
                question_text  TEXT NOT NULL,
                question_img   VARCHAR(255) DEFAULT NULL,
                choice1        TEXT NOT NULL,
                choice1_img    VARCHAR(255) DEFAULT NULL,
                choice2        TEXT NOT NULL,
                choice2_img    VARCHAR(255) DEFAULT NULL,
                choice3        TEXT NOT NULL,
                choice3_img    VARCHAR(255) DEFAULT NULL,
                choice4        TEXT NOT NULL,
                choice4_img    VARCHAR(255) DEFAULT NULL,
                correct_answer TINYINT NOT NULL COMMENT '1-4',
                created_at     DATETIME DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS lms_post_questions (
                id             INT AUTO_INCREMENT PRIMARY KEY,
                question_text  TEXT NOT NULL,
                question_img   VARCHAR(255) DEFAULT NULL,
                choice1        TEXT NOT NULL,
                choice1_img    VARCHAR(255) DEFAULT NULL,
                choice2        TEXT NOT NULL,
                choice2_img    VARCHAR(255) DEFAULT NULL,
                choice3        TEXT NOT NULL,
                choice3_img    VARCHAR(255) DEFAULT NULL,
                choice4        TEXT NOT NULL,
                choice4_img    VARCHAR(255) DEFAULT NULL,
                correct_answer TINYINT NOT NULL COMMENT '1-4',
                created_at     DATETIME DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS lms_exam_settings (
                id                INT AUTO_INCREMENT PRIMARY KEY,
                pre_pass_score    INT NOT NULL DEFAULT 6,
                post_pass_score   INT NOT NULL DEFAULT 6,
                post_max_attempts INT NOT NULL DEFAULT 3,
                updated_at        DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $pdo->exec("INSERT IGNORE INTO lms_exam_settings (id, pre_pass_score, post_pass_score, post_max_attempts) VALUES (1, 6, 6, 3)");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS lms_student_pre_exam (
                id          INT AUTO_INCREMENT PRIMARY KEY,
                student_uid INT NOT NULL,
                score       INT NOT NULL DEFAULT 0,
                total       INT NOT NULL DEFAULT 0,
                passed      TINYINT(1) NOT NULL DEFAULT 0,
                attempt_no  INT NOT NULL DEFAULT 1,
                taken_at    DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_student (student_uid)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS lms_student_post_exam (
                id          INT AUTO_INCREMENT PRIMARY KEY,
                student_uid INT NOT NULL,
                score       INT NOT NULL DEFAULT 0,
                total       INT NOT NULL DEFAULT 0,
                passed      TINYINT(1) NOT NULL DEFAULT 0,
                attempt_no  INT NOT NULL DEFAULT 1,
                taken_at    DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_student (student_uid)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS lms_student_exercises (
                id          INT AUTO_INCREMENT PRIMARY KEY,
                student_uid INT NOT NULL,
                unit_id     INT NOT NULL,
                exercise_id INT NOT NULL,
                answer_text TEXT DEFAULT NULL,
                submitted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_student_exercise (student_uid, exercise_id),
                INDEX idx_student (student_uid)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    },

    'down' => function (PDO $pdo) {
        foreach ([
            'lms_student_exercises','lms_student_post_exam','lms_student_pre_exam',
            'lms_exam_settings','lms_post_questions','lms_pre_questions',
            'lms_topic_files','lms_topic_youtube','lms_topic_links',
            'lms_topics','lms_unit_exercises','lms_units',
        ] as $t) {
            $pdo->exec("DROP TABLE IF EXISTS `$t`");
        }
    },
];
