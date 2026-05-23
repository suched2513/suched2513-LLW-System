<?php
return [
    'up' => function (PDO $pdo) {
        $addCol = function (string $table, string $col, string $def) use ($pdo): void {
            $stmt = $pdo->query("SHOW COLUMNS FROM `{$table}` LIKE '{$col}'");
            if (!$stmt->fetch()) {
                $pdo->exec("ALTER TABLE `{$table}` ADD COLUMN `{$col}` {$def}");
            }
        };
        $addCol('lms_unit_exercises',    'description',    'TEXT NULL DEFAULT NULL AFTER exercise_title');
        $addCol('lms_unit_exercises',    'max_score',      'SMALLINT UNSIGNED NULL DEFAULT NULL AFTER description');
        $addCol('lms_unit_exercises',    'due_date',       'DATETIME NULL DEFAULT NULL AFTER max_score');
        $addCol('lms_unit_exercises',    'allow_resubmit', 'TINYINT(1) NOT NULL DEFAULT 1 AFTER due_date');
        $addCol('lms_student_exercises', 'file_path',      'VARCHAR(255) NULL DEFAULT NULL AFTER answer_text');
    },
    'down' => function (PDO $pdo) {
        $dropCol = function (string $table, string $col) use ($pdo): void {
            $stmt = $pdo->query("SHOW COLUMNS FROM `{$table}` LIKE '{$col}'");
            if ($stmt->fetch()) {
                $pdo->exec("ALTER TABLE `{$table}` DROP COLUMN `{$col}`");
            }
        };
        $dropCol('lms_unit_exercises',    'allow_resubmit');
        $dropCol('lms_unit_exercises',    'due_date');
        $dropCol('lms_unit_exercises',    'max_score');
        $dropCol('lms_unit_exercises',    'description');
        $dropCol('lms_student_exercises', 'file_path');
    },
];
