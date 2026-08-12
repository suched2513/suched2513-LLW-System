<?php
return [
    'up' => function (PDO $pdo) {
        $col = $pdo->query("SHOW COLUMNS FROM lms_exam_settings LIKE 'unlock_mode'")->fetch();
        if (!$col) {
            $pdo->exec("ALTER TABLE lms_exam_settings ADD COLUMN unlock_mode ENUM('open_all','sequential') NOT NULL DEFAULT 'open_all' AFTER post_exam_close_at");
        }

        $col = $pdo->query("SHOW COLUMNS FROM lms_unit_exercises LIKE 'is_remedial'")->fetch();
        if (!$col) {
            $pdo->exec("ALTER TABLE lms_unit_exercises ADD COLUMN is_remedial TINYINT(1) NOT NULL DEFAULT 0 AFTER allow_resubmit");
        }

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS lms_student_unit_unlocks (
                id           INT AUTO_INCREMENT PRIMARY KEY,
                student_uid  INT NOT NULL,
                unit_id      INT NOT NULL,
                unlocked_by  INT DEFAULT NULL COMMENT 'llw_users.user_id ของครูที่ปลดล็อก',
                created_at   DATETIME DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_student_unit (student_uid, unit_id),
                INDEX idx_unit (unit_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    },
    'down' => function (PDO $pdo) {
        $pdo->exec("DROP TABLE IF EXISTS lms_student_unit_unlocks");
        $col = $pdo->query("SHOW COLUMNS FROM lms_unit_exercises LIKE 'is_remedial'")->fetch();
        if ($col) $pdo->exec("ALTER TABLE lms_unit_exercises DROP COLUMN is_remedial");
        $col = $pdo->query("SHOW COLUMNS FROM lms_exam_settings LIKE 'unlock_mode'")->fetch();
        if ($col) $pdo->exec("ALTER TABLE lms_exam_settings DROP COLUMN unlock_mode");
    },
];
