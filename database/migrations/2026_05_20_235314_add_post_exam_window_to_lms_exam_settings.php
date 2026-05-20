<?php
return [
    'up' => function (PDO $pdo) {
        $col = $pdo->query("SHOW COLUMNS FROM lms_exam_settings LIKE 'post_exam_open_at'")->fetchAll();
        if (empty($col)) {
            $pdo->exec("ALTER TABLE lms_exam_settings
                ADD COLUMN post_exam_open_at  DATETIME NULL DEFAULT NULL AFTER post_max_attempts,
                ADD COLUMN post_exam_close_at DATETIME NULL DEFAULT NULL AFTER post_exam_open_at");
        }
    },
    'down' => function (PDO $pdo) {
        $col = $pdo->query("SHOW COLUMNS FROM lms_exam_settings LIKE 'post_exam_open_at'")->fetchAll();
        if (!empty($col)) {
            $pdo->exec("ALTER TABLE lms_exam_settings
                DROP COLUMN post_exam_open_at,
                DROP COLUMN post_exam_close_at");
        }
    },
];
