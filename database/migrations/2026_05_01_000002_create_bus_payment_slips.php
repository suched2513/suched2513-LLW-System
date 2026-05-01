<?php
return [
    'up' => function (PDO $pdo) {
        $pdo->exec("CREATE TABLE IF NOT EXISTS bus_payment_slips (
            id              INT AUTO_INCREMENT PRIMARY KEY,
            registration_id INT           NOT NULL,
            amount          DECIMAL(10,2) NOT NULL,
            slip_image      VARCHAR(255)  NOT NULL,
            transfer_date   DATE          NULL,
            note            TEXT          NULL,
            status          ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
            admin_note      VARCHAR(500)  NULL,
            reviewed_by     INT           NULL COMMENT 'llw_users.user_id',
            reviewed_at     DATETIME      NULL,
            created_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_registration_id (registration_id),
            INDEX idx_status (status),
            INDEX idx_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    },
    'down' => function (PDO $pdo) {
        $pdo->exec("DROP TABLE IF EXISTS bus_payment_slips");
    },
];
