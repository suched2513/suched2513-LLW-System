<?php
return [
    'up' => function (PDO $pdo) {
        foreach (['lms_units', 'lms_topics', 'lms_unit_exercises'] as $tbl) {
            $col = $pdo->query("SHOW COLUMNS FROM `{$tbl}` LIKE 'status'")->fetch();
            if (!$col) {
                $pdo->exec("ALTER TABLE `{$tbl}` ADD COLUMN status ENUM('draft','published') NOT NULL DEFAULT 'published'");
            }
            $col = $pdo->query("SHOW COLUMNS FROM `{$tbl}` LIKE 'deleted_at'")->fetch();
            if (!$col) {
                $pdo->exec("ALTER TABLE `{$tbl}` ADD COLUMN deleted_at DATETIME NULL DEFAULT NULL, ADD INDEX idx_deleted_at (deleted_at)");
            }
        }
    },
    'down' => function (PDO $pdo) {
        foreach (['lms_units', 'lms_topics', 'lms_unit_exercises'] as $tbl) {
            $col = $pdo->query("SHOW COLUMNS FROM `{$tbl}` LIKE 'deleted_at'")->fetch();
            if ($col) {
                $pdo->exec("ALTER TABLE `{$tbl}` DROP INDEX idx_deleted_at, DROP COLUMN deleted_at");
            }
            $col = $pdo->query("SHOW COLUMNS FROM `{$tbl}` LIKE 'status'")->fetch();
            if ($col) {
                $pdo->exec("ALTER TABLE `{$tbl}` DROP COLUMN status");
            }
        }
    },
];
