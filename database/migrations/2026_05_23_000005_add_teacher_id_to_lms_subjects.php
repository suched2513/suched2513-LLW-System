<?php
return [
    'up' => function (PDO $pdo) {
        $col = $pdo->query("SHOW COLUMNS FROM `lms_subjects` LIKE 'teacher_id'")->fetch();
        if (!$col) {
            $pdo->exec("ALTER TABLE `lms_subjects` ADD COLUMN `teacher_id` INT NULL DEFAULT NULL AFTER `subject_code`, ADD INDEX `idx_teacher` (`teacher_id`)");
        }
    },
    'down' => function (PDO $pdo) {
        $col = $pdo->query("SHOW COLUMNS FROM `lms_subjects` LIKE 'teacher_id'")->fetch();
        if ($col) {
            $pdo->exec("ALTER TABLE `lms_subjects` DROP INDEX `idx_teacher`, DROP COLUMN `teacher_id`");
        }
    },
];
