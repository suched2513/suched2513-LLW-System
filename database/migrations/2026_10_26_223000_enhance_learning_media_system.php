<?php
return [
    'up' => function (PDO $pdo) {
        // 1. เพิ่มคอลัมน์ในตาราง learning_media
        $cols = $pdo->query("SHOW COLUMNS FROM learning_media")->fetchAll(PDO::FETCH_COLUMN);

        if (!in_array('media_type', $cols, true)) {
            $pdo->exec("ALTER TABLE learning_media ADD COLUMN media_type VARCHAR(50) NOT NULL DEFAULT 'slide' AFTER subject");
        }
        if (!in_array('objective', $cols, true)) {
            $pdo->exec("ALTER TABLE learning_media ADD COLUMN objective TEXT NULL AFTER media_link");
        }
        if (!in_array('usage_results', $cols, true)) {
            $pdo->exec("ALTER TABLE learning_media ADD COLUMN usage_results TEXT NULL AFTER objective");
        }
        if (!in_array('rating_avg', $cols, true)) {
            $pdo->exec("ALTER TABLE learning_media ADD COLUMN rating_avg DECIMAL(3,2) NOT NULL DEFAULT 0.00 AFTER views");
        }
        if (!in_array('rating_count', $cols, true)) {
            $pdo->exec("ALTER TABLE learning_media ADD COLUMN rating_count INT NOT NULL DEFAULT 0 AFTER rating_avg");
        }

        // 2. สร้างตารางบันทึกการประเมินความพึงพอใจ (Ratings)
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS learning_media_ratings (
                id             INT AUTO_INCREMENT PRIMARY KEY,
                media_id       INT NOT NULL,
                rating         TINYINT NOT NULL DEFAULT 5,
                feedback       TEXT NULL,
                evaluator_name VARCHAR(100) NULL,
                evaluator_role VARCHAR(50) NOT NULL DEFAULT 'student',
                created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_lmr_media (media_id),
                INDEX idx_lmr_created (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    },
    'down' => function (PDO $pdo) {
        $pdo->exec("DROP TABLE IF EXISTS learning_media_ratings");
    },
];
