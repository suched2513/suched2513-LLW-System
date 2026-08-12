<?php
return [
    'up' => function (PDO $pdo) {
        $newCols = [
            'midterm_pass_score'   => "INT NOT NULL DEFAULT 6",
            'midterm_max_attempts' => "INT NOT NULL DEFAULT 1",
            'midterm_open_at'      => "DATETIME NULL DEFAULT NULL",
            'midterm_close_at'     => "DATETIME NULL DEFAULT NULL",
            'final_pass_score'     => "INT NOT NULL DEFAULT 6",
            'final_max_attempts'   => "INT NOT NULL DEFAULT 1",
            'final_open_at'        => "DATETIME NULL DEFAULT NULL",
            'final_close_at'       => "DATETIME NULL DEFAULT NULL",
        ];
        foreach ($newCols as $col => $def) {
            $exists = $pdo->query("SHOW COLUMNS FROM lms_subject_settings LIKE '{$col}'")->fetch();
            if (!$exists) {
                $pdo->exec("ALTER TABLE lms_subject_settings ADD COLUMN `{$col}` {$def}");
            }
        }

        // Widen exam_type to also cover subject-wide midterm/final exams
        $pdo->exec("ALTER TABLE lms_exam_item_results MODIFY COLUMN exam_type ENUM('pre','post','midterm','final') NOT NULL");
        $pdo->exec("ALTER TABLE lms_student_exam_answers MODIFY COLUMN exam_type ENUM('pre','post','midterm','final') NOT NULL");
    },
    'down' => function (PDO $pdo) {
        // Only safe to narrow the ENUMs back if no midterm/final rows exist
        $hasMidtermFinalItems = (int)$pdo->query("SELECT COUNT(*) FROM lms_exam_item_results WHERE exam_type IN ('midterm','final')")->fetchColumn();
        if ($hasMidtermFinalItems === 0) {
            $pdo->exec("ALTER TABLE lms_exam_item_results MODIFY COLUMN exam_type ENUM('pre','post') NOT NULL");
        }
        $hasMidtermFinalAnswers = (int)$pdo->query("SELECT COUNT(*) FROM lms_student_exam_answers WHERE exam_type IN ('midterm','final')")->fetchColumn();
        if ($hasMidtermFinalAnswers === 0) {
            $pdo->exec("ALTER TABLE lms_student_exam_answers MODIFY COLUMN exam_type ENUM('pre','post') NOT NULL");
        }

        foreach ([
            'midterm_pass_score', 'midterm_max_attempts', 'midterm_open_at', 'midterm_close_at',
            'final_pass_score', 'final_max_attempts', 'final_open_at', 'final_close_at',
        ] as $col) {
            $exists = $pdo->query("SHOW COLUMNS FROM lms_subject_settings LIKE '{$col}'")->fetch();
            if ($exists) {
                $pdo->exec("ALTER TABLE lms_subject_settings DROP COLUMN `{$col}`");
            }
        }
    },
];
