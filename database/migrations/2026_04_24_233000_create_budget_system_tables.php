<?php
/**
 * Migration: create_budget_system_tables
 * สร้างตารางสำหรับระบบบริหารงบประมาณ (Budget Management System)
 */
return [
    'up' => function (PDO $pdo) {
        // 1. Create budget_projects table (renamed from projects)
        $pdo->exec("CREATE TABLE IF NOT EXISTS `budget_projects` (
            `project_id` int(11) NOT NULL AUTO_INCREMENT,
            `project_name` varchar(200) NOT NULL,
            `description` text DEFAULT NULL,
            `total_budget` decimal(15,2) NOT NULL,
            `start_date` date NOT NULL,
            `end_date` date NOT NULL,
            `status` enum('active','completed','cancelled') DEFAULT 'active',
            `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
            `created_by` int(11) NOT NULL,
            PRIMARY KEY (`project_id`),
            KEY `idx_project_status` (`status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // 2. Create budget_transactions table
        $pdo->exec("CREATE TABLE IF NOT EXISTS `budget_transactions` (
            `transaction_id` int(11) NOT NULL AUTO_INCREMENT,
            `project_id` int(11) NOT NULL,
            `amount` decimal(15,2) NOT NULL,
            `transaction_type` enum('income','expense') NOT NULL,
            `description` text DEFAULT NULL,
            `transaction_date` date NOT NULL,
            `created_by` int(11) NOT NULL,
            `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
            PRIMARY KEY (`transaction_id`),
            KEY `idx_transaction_date` (`transaction_date`),
            KEY `idx_transaction_project` (`project_id`),
            CONSTRAINT `budget_transactions_ibfk_1` FOREIGN KEY (`project_id`) REFERENCES `budget_projects` (`project_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // Seed sample data if table is empty
        $count = $pdo->query("SELECT COUNT(*) FROM budget_projects")->fetchColumn();
        if ($count == 0) {
            // Get a default admin ID from llw_users
            $adminId = $pdo->query("SELECT user_id FROM llw_users WHERE role = 'super_admin' LIMIT 1")->fetchColumn();
            if (!$adminId) $adminId = 1;

            $pdo->exec("INSERT INTO `budget_projects` (`project_id`, `project_name`, `description`, `total_budget`, `start_date`, `end_date`, `status`, `created_by`) VALUES
                (1, 'โครงการพัฒนาระบบ E-Office', 'พัฒนาระบบสำนักงานอิเล็กทรอนิกส์เพื่อเพิ่มประสิทธิภาพการทำงาน', 500000.00, '2024-01-01', '2024-12-31', 'active', $adminId),
                (2, 'โครงการอบรมบุคลากร', 'จัดอบรมพัฒนาศักยภาพบุคลากรด้านเทคโนโลยีสารสนเทศ', 200000.00, '2024-03-01', '2024-08-31', 'active', $adminId),
                (3, 'โครงการจัดซื้อครุภัณฑ์', 'จัดซื้อคอมพิวเตอร์และอุปกรณ์สำนักงาน', 300000.00, '2024-02-01', '2024-06-30', 'active', $adminId)");

            $pdo->exec("INSERT INTO `budget_transactions` (`transaction_id`, `project_id`, `amount`, `transaction_type`, `description`, `transaction_date`, `created_by`) VALUES
                (1, 1, 150000.00, 'expense', 'ค่าจ้างที่ปรึกษาพัฒนาระบบ งวดที่ 1', '2024-02-15', $adminId),
                (2, 2, 45000.00, 'expense', 'ค่าวิทยากรและเอกสารประกอบการอบรม', '2024-03-10', $adminId),
                (3, 3, 120000.00, 'expense', 'จัดซื้อคอมพิวเตอร์ จำนวน 4 เครื่อง', '2024-03-15', $adminId)");
        }
    },
    'down' => function (PDO $pdo) {
        $pdo->exec("DROP TABLE IF EXISTS `budget_transactions` ");
        $pdo->exec("DROP TABLE IF EXISTS `budget_projects` ");
    },
];
