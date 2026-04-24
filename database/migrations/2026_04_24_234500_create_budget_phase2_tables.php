<?php
/**
 * Migration: create_budget_phase2_tables
 * เพิ่มความสามารถในการขออนุมัติเบิกจ่ายและแหล่งเงินทุน (Phase 2)
 */
return [
    'up' => function (PDO $pdo) {
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");

        // 1. ตารางแหล่งเงินทุน (Fund Sources)
        $pdo->exec("CREATE TABLE IF NOT EXISTS `budget_fund_sources` (
            `source_id` int(11) NOT NULL AUTO_INCREMENT,
            `source_name` varchar(100) NOT NULL,
            `description` varchar(255) DEFAULT NULL,
            PRIMARY KEY (`source_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // Seed แหล่งเงินทุนพื้นฐาน
        $pdo->exec("INSERT IGNORE INTO `budget_fund_sources` (`source_id`, `source_name`) VALUES 
            (1, 'เงินอุดหนุน'),
            (2, 'พัฒนาคุณภาพผู้เรียน'),
            (3, 'เงินรายได้สถานศึกษา'),
            (4, 'เงินสำรองจ่าย'),
            (5, 'เงินอื่นๆ')");

        // 2. ปรับปรุง budget_projects ให้มีฝ่าย/กลุ่มสาระ และปีงบประมาณ
        // ตรวจสอบว่ามี column หรือยัง
        $cols = $pdo->query("DESCRIBE `budget_projects`")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('department_id', $cols)) {
            $pdo->exec("ALTER TABLE `budget_projects` ADD COLUMN `department_id` int(11) DEFAULT NULL AFTER `project_name` ");
        }
        if (!in_array('fiscal_year', $cols)) {
            $pdo->exec("ALTER TABLE `budget_projects` ADD COLUMN `fiscal_year` varchar(4) DEFAULT NULL AFTER `department_id` ");
        }

        // 3. ตารางการขออนุมัติเบิกจ่าย (Disbursements)
        $pdo->exec("CREATE TABLE IF NOT EXISTS `budget_disbursements` (
            `disbursement_id` int(11) NOT NULL AUTO_INCREMENT,
            `project_id` int(11) NOT NULL,
            `doc_no` varchar(50) DEFAULT NULL, -- เลขที่โครงการ/เลขที่เอกสาร
            `activity_name` varchar(255) NOT NULL, -- กิจกรรม
            `reason` text DEFAULT NULL, -- เหตุผลที่ขอใช้
            `fund_source_id` int(11) DEFAULT NULL,
            `total_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
            `status` enum('pending','approved','rejected') DEFAULT 'pending',
            `request_date` date NOT NULL,
            `requested_by` int(11) NOT NULL, -- ผู้ขอใช้ (llw_users.user_id)
            `approved_by` int(11) DEFAULT NULL,
            `approved_at` timestamp NULL DEFAULT NULL,
            `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
            PRIMARY KEY (`disbursement_id`),
            KEY `idx_disbursement_project` (`project_id`),
            CONSTRAINT `budget_disbursements_ibfk_1` FOREIGN KEY (`project_id`) REFERENCES `budget_projects` (`project_id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // 4. ตารางรายการสิ่งของ (Disbursement Items)
        $pdo->exec("CREATE TABLE IF NOT EXISTS `budget_disbursement_items` (
            `item_id` int(11) NOT NULL AUTO_INCREMENT,
            `disbursement_id` int(11) NOT NULL,
            `item_name` varchar(255) NOT NULL,
            `quantity` decimal(10,2) NOT NULL DEFAULT 1.00,
            `unit` varchar(50) DEFAULT NULL,
            `price_per_unit` decimal(15,2) NOT NULL DEFAULT 0.00,
            `total_price` decimal(15,2) NOT NULL DEFAULT 0.00,
            PRIMARY KEY (`item_id`),
            KEY `idx_item_disbursement` (`disbursement_id`),
            CONSTRAINT `budget_disbursement_items_ibfk_1` FOREIGN KEY (`disbursement_id`) REFERENCES `budget_disbursements` (`disbursement_id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");
    },
    'down' => function (PDO $pdo) {
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
        $pdo->exec("DROP TABLE IF EXISTS `budget_disbursement_items` ");
        $pdo->exec("DROP TABLE IF EXISTS `budget_disbursements` ");
        $pdo->exec("DROP TABLE IF EXISTS `budget_fund_sources` ");
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");
    },
];
