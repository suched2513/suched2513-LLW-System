<?php
return [
    'up' => function (PDO $pdo) {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `budget_amendments` (
                `id`              INT(11) NOT NULL AUTO_INCREMENT,
                `type`            ENUM('increase','transfer') NOT NULL DEFAULT 'increase',
                `to_project_id`   INT(11) NOT NULL,
                `from_project_id` INT(11) NULL,
                `amount`          DECIMAL(15,2) NOT NULL,
                `reason`          TEXT NOT NULL,
                `linked_request_id` INT(11) NULL,
                `status`          ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
                `requested_by`    INT(11) NOT NULL,
                `reviewed_by`     INT(11) NULL,
                `review_note`     TEXT NULL,
                `reviewed_at`     DATETIME NULL,
                `created_at`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_ba_status` (`status`),
                KEY `idx_ba_to_project` (`to_project_id`),
                KEY `idx_ba_created` (`created_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    },
    'down' => function (PDO $pdo) {
        $pdo->exec("DROP TABLE IF EXISTS `budget_amendments`");
    },
];
