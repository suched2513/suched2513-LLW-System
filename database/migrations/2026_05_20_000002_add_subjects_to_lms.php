<?php
return [
    'up' => function (PDO $pdo) {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS lms_subjects (
                id           INT AUTO_INCREMENT PRIMARY KEY,
                subject_name VARCHAR(255) NOT NULL,
                subject_code VARCHAR(50)  DEFAULT NULL,
                created_at   DATETIME DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS lms_subject_classrooms (
                id         INT AUTO_INCREMENT PRIMARY KEY,
                subject_id INT NOT NULL,
                classroom  VARCHAR(100) NOT NULL,
                UNIQUE KEY uq_subj_class (subject_id, classroom),
                INDEX idx_subject (subject_id),
                INDEX idx_classroom (classroom)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        foreach (['lms_units','lms_pre_questions','lms_post_questions',
                  'lms_student_pre_exam','lms_student_post_exam','lms_student_exercises'] as $tbl) {
            $col = $pdo->query("SHOW COLUMNS FROM `{$tbl}` LIKE 'subject_id'")->fetch();
            if (!$col) {
                $pdo->exec("ALTER TABLE `{$tbl}` ADD COLUMN subject_id INT NOT NULL DEFAULT 0, ADD INDEX idx_subj (subject_id)");
            }
        }

        $col = $pdo->query("SHOW COLUMNS FROM lms_exam_settings LIKE 'subject_id'")->fetch();
        if (!$col) {
            $pdo->exec("ALTER TABLE lms_exam_settings ADD COLUMN subject_id INT NOT NULL DEFAULT 0, ADD UNIQUE KEY uq_subj_settings (subject_id)");
        }
    },
    'down' => function (PDO $pdo) {
        $pdo->exec("DROP TABLE IF EXISTS lms_subject_classrooms");
        $pdo->exec("DROP TABLE IF EXISTS lms_subjects");
        foreach (['lms_units','lms_pre_questions','lms_post_questions',
                  'lms_student_pre_exam','lms_student_post_exam','lms_student_exercises'] as $tbl) {
            try { $pdo->exec("ALTER TABLE `{$tbl}` DROP INDEX idx_subj, DROP COLUMN subject_id"); } catch (Exception $e) {}
        }
        try { $pdo->exec("ALTER TABLE lms_exam_settings DROP INDEX uq_subj_settings, DROP COLUMN subject_id"); } catch (Exception $e) {}
    },
];
