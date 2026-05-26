<?php
return [
    'up' => function (PDO $pdo) {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS cb_repairs (
                id                INT AUTO_INCREMENT PRIMARY KEY,
                borrow_log_id     INT NOT NULL,
                chromebook_id     VARCHAR(50) NOT NULL DEFAULT '',
                chromebook_serial VARCHAR(100) NOT NULL DEFAULT '',
                description       TEXT,
                status            ENUM('รับแจ้ง','ส่งซ่อม','ซ่อมเสร็จ','รับกลับ') NOT NULL DEFAULT 'รับแจ้ง',
                repair_notes      TEXT,
                reported_by       VARCHAR(100) NOT NULL DEFAULT '',
                created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_borrow_log (borrow_log_id),
                INDEX idx_chromebook (chromebook_id),
                INDEX idx_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    },
    'down' => function (PDO $pdo) {
        $pdo->exec("DROP TABLE IF EXISTS cb_repairs");
    },
];
