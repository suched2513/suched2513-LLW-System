<?php
return [
    'up' => function (PDO $pdo) {
        foreach (['lms_pre_questions', 'lms_post_questions', 'lms_midterm_questions', 'lms_final_questions'] as $tbl) {
            $col = $pdo->query("SHOW COLUMNS FROM `{$tbl}` LIKE 'choice5'")->fetch();
            if (!$col) {
                $pdo->exec("ALTER TABLE `{$tbl}` ADD COLUMN choice5 TEXT NULL DEFAULT NULL AFTER choice4_img");
            }
        }
    },
    'down' => function (PDO $pdo) {
        foreach (['lms_pre_questions', 'lms_post_questions', 'lms_midterm_questions', 'lms_final_questions'] as $tbl) {
            $col = $pdo->query("SHOW COLUMNS FROM `{$tbl}` LIKE 'choice5'")->fetch();
            if ($col) {
                $pdo->exec("ALTER TABLE `{$tbl}` DROP COLUMN choice5");
            }
        }
    },
];
