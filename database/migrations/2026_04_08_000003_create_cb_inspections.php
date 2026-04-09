<?php
/**
 * Migration: Create cb_inspections table
 * ย้ายจาก chromebook/create_inspection_table.sql → migration system
 *
 * ตาราง: cb_inspections
 * ใช้บันทึกการตรวจสภาพ Chromebook หลังคืน
 */
return [
    'up' => function (PDO $pdo): void {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS cb_inspections (
                id               INT AUTO_INCREMENT PRIMARY KEY,
                borrow_log_id    INT NOT NULL,
                condition_status ENUM('Normal','Damaged','Lost') NOT NULL DEFAULT 'Normal',
                notes            TEXT,
                images           TEXT,
                inspected_date   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (borrow_log_id)
                    REFERENCES cb_borrow_logs(entry_id)
                    ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    },

    'down' => function (PDO $pdo): void {
        $pdo->exec("DROP TABLE IF EXISTS cb_inspections");
    },
];
