<?php
return [
    'up' => function (PDO $pdo) {
        $cols = $pdo->query("SHOW COLUMNS FROM lms_pre_questions LIKE 'question_type'")->fetchAll();
        if (empty($cols)) {
            $pdo->exec("ALTER TABLE lms_pre_questions ADD COLUMN question_type ENUM('choice','text') NOT NULL DEFAULT 'choice' AFTER question_text");
        }
        $cols = $pdo->query("SHOW COLUMNS FROM lms_post_questions LIKE 'question_type'")->fetchAll();
        if (empty($cols)) {
            $pdo->exec("ALTER TABLE lms_post_questions ADD COLUMN question_type ENUM('choice','text') NOT NULL DEFAULT 'choice' AFTER question_text");
        }
    },
    'down' => function (PDO $pdo) {
        $cols = $pdo->query("SHOW COLUMNS FROM lms_pre_questions LIKE 'question_type'")->fetchAll();
        if (!empty($cols)) {
            $pdo->exec("ALTER TABLE lms_pre_questions DROP COLUMN question_type");
        }
        $cols = $pdo->query("SHOW COLUMNS FROM lms_post_questions LIKE 'question_type'")->fetchAll();
        if (!empty($cols)) {
            $pdo->exec("ALTER TABLE lms_post_questions DROP COLUMN question_type");
        }
    },
];
