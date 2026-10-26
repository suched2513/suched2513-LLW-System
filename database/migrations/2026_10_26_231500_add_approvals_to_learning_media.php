<?php
return [
    'up' => function (PDO $pdo) {
        $cols = $pdo->query("SHOW COLUMNS FROM learning_media")->fetchAll(PDO::FETCH_COLUMN);

        if (!in_array('academic_head_approved', $cols, true)) {
            $pdo->exec("ALTER TABLE learning_media ADD COLUMN academic_head_approved TINYINT(1) NOT NULL DEFAULT 0 AFTER status");
        }
        if (!in_array('academic_head_name', $cols, true)) {
            $pdo->exec("ALTER TABLE learning_media ADD COLUMN academic_head_name VARCHAR(100) NOT NULL DEFAULT 'นายเฉลิมชัย ศรีชัย' AFTER academic_head_approved");
        }
        if (!in_array('academic_head_comment', $cols, true)) {
            $pdo->exec("ALTER TABLE learning_media ADD COLUMN academic_head_comment VARCHAR(255) NULL DEFAULT 'ทราบ และเห็นควรอนุมัติ' AFTER academic_head_name");
        }
        if (!in_array('academic_head_at', $cols, true)) {
            $pdo->exec("ALTER TABLE learning_media ADD COLUMN academic_head_at DATETIME NULL AFTER academic_head_comment");
        }
        if (!in_array('academic_head_signature', $cols, true)) {
            $pdo->exec("ALTER TABLE learning_media ADD COLUMN academic_head_signature LONGTEXT NULL AFTER academic_head_at");
        }

        if (!in_array('director_approved', $cols, true)) {
            $pdo->exec("ALTER TABLE learning_media ADD COLUMN director_approved TINYINT(1) NOT NULL DEFAULT 0 AFTER academic_head_signature");
        }
        if (!in_array('director_name', $cols, true)) {
            $pdo->exec("ALTER TABLE learning_media ADD COLUMN director_name VARCHAR(100) NOT NULL DEFAULT 'นายสถาน ปรางมาศ' AFTER director_approved");
        }
        if (!in_array('director_comment', $cols, true)) {
            $pdo->exec("ALTER TABLE learning_media ADD COLUMN director_comment VARCHAR(255) NULL DEFAULT 'ทราบ และอนุมัติ' AFTER director_name");
        }
        if (!in_array('director_at', $cols, true)) {
            $pdo->exec("ALTER TABLE learning_media ADD COLUMN director_at DATETIME NULL AFTER director_comment");
        }
        if (!in_array('director_signature', $cols, true)) {
            $pdo->exec("ALTER TABLE learning_media ADD COLUMN director_signature LONGTEXT NULL AFTER director_at");
        }
    },
    'down' => function (PDO $pdo) {
        // Reversible if needed
    },
];
