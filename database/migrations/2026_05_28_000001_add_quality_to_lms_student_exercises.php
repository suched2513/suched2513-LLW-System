<?php
return [
    'up' => function (PDO $pdo) {
        $cols = $pdo->query("SHOW COLUMNS FROM lms_student_exercises LIKE 'quality'")->fetchAll();
        if (empty($cols)) {
            $pdo->exec("ALTER TABLE lms_student_exercises ADD COLUMN quality VARCHAR(60) NULL DEFAULT NULL AFTER feedback");
        }
    },
    'down' => function (PDO $pdo) {
        $cols = $pdo->query("SHOW COLUMNS FROM lms_student_exercises LIKE 'quality'")->fetchAll();
        if (!empty($cols)) {
            $pdo->exec("ALTER TABLE lms_student_exercises DROP COLUMN quality");
        }
    },
];
