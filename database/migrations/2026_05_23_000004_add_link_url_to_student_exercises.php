<?php
return [
    'up' => function (PDO $pdo) {
        $stmt = $pdo->query("SHOW COLUMNS FROM `lms_student_exercises` LIKE 'link_url'");
        if (!$stmt->fetch()) {
            $pdo->exec("ALTER TABLE `lms_student_exercises` ADD COLUMN `link_url` VARCHAR(500) NULL DEFAULT NULL AFTER `file_paths`");
        }
    },
    'down' => function (PDO $pdo) {
        $stmt = $pdo->query("SHOW COLUMNS FROM `lms_student_exercises` LIKE 'link_url'");
        if ($stmt->fetch()) {
            $pdo->exec("ALTER TABLE `lms_student_exercises` DROP COLUMN `link_url`");
        }
    },
];
