<?php
return [
    'up' => function (PDO $pdo) {
        foreach (['lms_pre_questions', 'lms_post_questions'] as $tbl) {
            $pdo->exec("ALTER TABLE `{$tbl}` MODIFY COLUMN correct_answer TINYINT NULL DEFAULT NULL COMMENT '1-4, ใช้เฉพาะ choice/true_false'");
        }
    },
    'down' => function (PDO $pdo) {
        foreach (['lms_pre_questions', 'lms_post_questions'] as $tbl) {
            $pdo->exec("UPDATE `{$tbl}` SET correct_answer=1 WHERE correct_answer IS NULL");
            $pdo->exec("ALTER TABLE `{$tbl}` MODIFY COLUMN correct_answer TINYINT NOT NULL COMMENT '1-4'");
        }
    },
];
