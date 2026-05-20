<?php
return [
    'up' => function (PDO $pdo) {
        $chk = $pdo->query("SHOW COLUMNS FROM att_students LIKE 'status'")->fetch();
        if (!$chk) {
            $pdo->exec("ALTER TABLE att_students ADD COLUMN status ENUM('active','moved','resigned','suspended') DEFAULT 'active' AFTER classroom");
            $pdo->exec("ALTER TABLE att_students ADD COLUMN status_note VARCHAR(255) NULL AFTER status");
            $pdo->exec("CREATE INDEX idx_student_status ON att_students(status)");
        }
    },
    'down' => function (PDO $pdo) {
        $cols = $pdo->query("SHOW COLUMNS FROM att_students LIKE 'status'")->fetch();
        if ($cols) {
            $pdo->exec("ALTER TABLE att_students DROP COLUMN status_note");
            $pdo->exec("ALTER TABLE att_students DROP COLUMN status");
        }
    },
];
