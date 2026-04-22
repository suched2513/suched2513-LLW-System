<?php
/**
 * Migration: create_sbms_procurements
 * สร้างตารางสำหรับระบบจัดซื้อจัดจ้าง (PR/PO)
 */
return [
    'up' => function (PDO $pdo) {
        $pdo->exec("CREATE TABLE IF NOT EXISTS sbms_procurements (
            id INT AUTO_INCREMENT PRIMARY KEY,
            pr_no VARCHAR(30) UNIQUE,
            po_no VARCHAR(30) UNIQUE,
            project_id INT,
            vendor_id INT,
            title VARCHAR(255) NOT NULL,
            estimated_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
            actual_amount DECIMAL(15,2) DEFAULT 0,
            status ENUM('pr_draft', 'pr_pending', 'pr_approved', 'po_issued', 'received', 'cancelled') DEFAULT 'pr_draft',
            requested_by INT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (project_id) REFERENCES sbms_projects(id) ON DELETE SET NULL,
            FOREIGN KEY (vendor_id) REFERENCES sbms_vendors(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    },
    'down' => function (PDO $pdo) {
        $pdo->exec("DROP TABLE IF EXISTS sbms_procurements;");
    },
];
