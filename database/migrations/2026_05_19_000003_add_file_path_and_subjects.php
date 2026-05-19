<?php
return [
    'up' => function (PDO $pdo) {
        // Add file_path to hw_submissions
        $cols = $pdo->query("SHOW COLUMNS FROM `hw_submissions` LIKE 'file_path'")->fetchAll();
        if (empty($cols)) {
            $pdo->exec("ALTER TABLE `hw_submissions` ADD COLUMN `file_path` TEXT DEFAULT NULL AFTER `content`");
        }

        // Create hw_subjects table
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `hw_subjects` (
                id         INT AUTO_INCREMENT PRIMARY KEY,
                name       VARCHAR(100) NOT NULL,
                teacher_id INT NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_name_teacher (name, teacher_id),
                INDEX idx_teacher (teacher_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    },
    'down' => function (PDO $pdo) {
        $cols = $pdo->query("SHOW COLUMNS FROM `hw_submissions` LIKE 'file_path'")->fetchAll();
        if (!empty($cols)) {
            $pdo->exec("ALTER TABLE `hw_submissions` DROP COLUMN `file_path`");
        }
        $pdo->exec("DROP TABLE IF EXISTS `hw_subjects`");
    },
];
