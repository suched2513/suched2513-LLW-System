<?php
return [
    'up' => function (PDO $pdo) {
        foreach (['lms_pre_questions', 'lms_post_questions'] as $tbl) {
            $pdo->exec("
                ALTER TABLE `{$tbl}`
                MODIFY COLUMN question_type ENUM('choice','multi_choice','true_false','fill_blank','matching','ordering','text','upload')
                    NOT NULL DEFAULT 'choice'
            ");
            $col = $pdo->query("SHOW COLUMNS FROM `{$tbl}` LIKE 'options_json'")->fetch();
            if (!$col) {
                $pdo->exec("ALTER TABLE `{$tbl}` ADD COLUMN options_json TEXT NULL DEFAULT NULL AFTER correct_answer");
            }
            $col = $pdo->query("SHOW COLUMNS FROM `{$tbl}` LIKE 'correct_json'")->fetch();
            if (!$col) {
                $pdo->exec("ALTER TABLE `{$tbl}` ADD COLUMN correct_json TEXT NULL DEFAULT NULL AFTER options_json");
            }
        }

        $col = $pdo->query("SHOW COLUMNS FROM lms_student_exam_answers LIKE 'file_path'")->fetch();
        if (!$col) {
            $pdo->exec("ALTER TABLE lms_student_exam_answers ADD COLUMN file_path VARCHAR(255) NULL DEFAULT NULL AFTER answer_text");
        }
    },
    'down' => function (PDO $pdo) {
        foreach (['lms_pre_questions', 'lms_post_questions'] as $tbl) {
            $col = $pdo->query("SHOW COLUMNS FROM `{$tbl}` LIKE 'correct_json'")->fetch();
            if ($col) $pdo->exec("ALTER TABLE `{$tbl}` DROP COLUMN correct_json");
            $col = $pdo->query("SHOW COLUMNS FROM `{$tbl}` LIKE 'options_json'")->fetch();
            if ($col) $pdo->exec("ALTER TABLE `{$tbl}` DROP COLUMN options_json");
            $pdo->exec("ALTER TABLE `{$tbl}` MODIFY COLUMN question_type ENUM('choice','text') NOT NULL DEFAULT 'choice'");
        }
        $col = $pdo->query("SHOW COLUMNS FROM lms_student_exam_answers LIKE 'file_path'")->fetch();
        if ($col) $pdo->exec("ALTER TABLE lms_student_exam_answers DROP COLUMN file_path");
    },
];
