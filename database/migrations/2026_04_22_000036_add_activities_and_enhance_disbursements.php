<?php
/**
 * Migration: add_activities_and_enhance_disbursements
 * เพิ่มตารางกิจกรรมและปรับปรุงตารางการเบิกจ่ายสำหรับระบบขออนุญาตดำเนินงาน
 */
return [
    'up' => function (PDO $pdo) {
        // 1. ตารางกิจกรรม (Activities)
        $pdo->exec("CREATE TABLE IF NOT EXISTS sbms_activities (
            id INT AUTO_INCREMENT PRIMARY KEY,
            project_id INT NOT NULL,
            activity_name VARCHAR(255) NOT NULL,
            budget_allocated DECIMAL(15,2) DEFAULT 0,
            budget_used DECIMAL(15,2) DEFAULT 0,
            responsible_name VARCHAR(255),
            responsible_position VARCHAR(255),
            status ENUM('pending', 'in_progress', 'completed') DEFAULT 'pending',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (project_id) REFERENCES sbms_projects(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

        // 2. ปรับปรุงตารางการเบิกจ่าย (Disbursements) เพื่อรองรับข้อมูลจาก GAS
        // เช็คก่อนว่ามี column หรือยัง เพื่อป้องกัน error ถ้า run ซ้ำ
        $columns = $pdo->query("SHOW COLUMNS FROM sbms_disbursements")->fetchAll(PDO::FETCH_COLUMN);
        
        if (!in_array('activity_id', $columns)) {
            $pdo->exec("ALTER TABLE sbms_disbursements ADD COLUMN activity_id INT AFTER project_id");
            $pdo->exec("ALTER TABLE sbms_disbursements ADD FOREIGN KEY (activity_id) REFERENCES sbms_activities(id) ON DELETE SET NULL");
        }
        
        if (!in_array('book_no', $columns)) {
            $pdo->exec("ALTER TABLE sbms_disbursements ADD COLUMN book_no VARCHAR(50) AFTER doc_no");
        }
        
        if (!in_array('book_date', $columns)) {
            $pdo->exec("ALTER TABLE sbms_disbursements ADD COLUMN book_date DATE AFTER book_no");
        }
        
        if (!in_array('requester_name', $columns)) {
            $pdo->exec("ALTER TABLE sbms_disbursements ADD COLUMN requester_name VARCHAR(255)");
        }
        
        if (!in_array('requester_position', $columns)) {
            $pdo->exec("ALTER TABLE sbms_disbursements ADD COLUMN requester_position VARCHAR(255)");
        }
        
        if (!in_array('signature_data', $columns)) {
            $pdo->exec("ALTER TABLE sbms_disbursements ADD COLUMN signature_data LONGTEXT");
        }
        
        if (!in_array('pdf_path', $columns)) {
            $pdo->exec("ALTER TABLE sbms_disbursements ADD COLUMN pdf_path VARCHAR(255)");
        }
    },
    'down' => function (PDO $pdo) {
        $pdo->exec("ALTER TABLE sbms_disbursements DROP FOREIGN KEY sbms_disbursements_ibfk_2"); // อาจจะต้องเช็คชื่อ FK จริงๆ
        $pdo->exec("ALTER TABLE sbms_disbursements DROP COLUMN activity_id, DROP COLUMN book_no, DROP COLUMN book_date, DROP COLUMN requester_name, DROP COLUMN requester_position, DROP COLUMN signature_data, DROP COLUMN pdf_path;");
        $pdo->exec("DROP TABLE IF EXISTS sbms_activities;");
    },
];
