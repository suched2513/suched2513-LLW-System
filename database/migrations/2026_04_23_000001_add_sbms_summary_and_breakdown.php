<?php
use Illuminate\Database\Capsule\Manager as Capsule;

/**
 * Migration: 2026_04_23_000001_add_sbms_summary_and_breakdown
 * Adds summary evaluation fields and budget breakdown columns.
 */

require_once __DIR__ . '/../../config/database.php';

return [
    'up' => function (PDO $pdo) {
        // ตรวจว่ามีตาราง sbms_disbursements หรือไม่ — หากไม่มี (เช่นถูก remove_sbms_tables
        // migration ลบไปแล้ว หรือ production ยังไม่เคยสร้าง) ให้ข้ามทั้งหมด
        $exists = $pdo->query("SHOW TABLES LIKE 'sbms_disbursements'")->fetchColumn();
        if (!$exists) {
            return;
        }

        // 1. Add Breakdown columns to sbms_disbursements (supporting input_11 to input_1015 style)
        // For simplicity and flexibility, we'll add a JSON column for itemized expenses
        // but also specific columns for common summary fields
        $pdo->exec("ALTER TABLE sbms_disbursements
            ADD COLUMN IF NOT EXISTS expense_items JSON NULL AFTER amount,
            ADD COLUMN IF NOT EXISTS total_spent_before DECIMAL(15,2) DEFAULT 0 AFTER expense_items,
            ADD COLUMN IF NOT EXISTS balance_remaining DECIMAL(15,2) DEFAULT 0 AFTER total_spent_before
        ");

        // 2. Create sbms_summaries table
        $pdo->exec("CREATE TABLE IF NOT EXISTS sbms_summaries (
            id INT AUTO_INCREMENT PRIMARY KEY,
            disbursement_id INT NOT NULL,
            project_id INT NOT NULL,
            activity_id INT NOT NULL,
            project_type ENUM('โครงการใหม่', 'โครงการต่อเนื่อง', 'โครงการพิเศษ / เฉพาะกิจ') NULL,
            objectives TEXT NULL,
            eval_objective ENUM('มากที่สุด', 'มาก', 'ปานกลาง', 'น้อย', 'น้อยที่สุด') NULL,
            eval_cooperation ENUM('มากที่สุด', 'มาก', 'ปานกลาง', 'น้อย', 'น้อยที่สุด') NULL,
            eval_interest ENUM('มากที่สุด', 'มาก', 'ปานกลาง', 'น้อย', 'น้อยที่สุด') NULL,
            eval_benefit ENUM('มากที่สุด', 'มาก', 'ปานกลาง', 'น้อย', 'น้อยที่สุด') NULL,
            eval_success ENUM('มากที่สุด', 'มาก', 'ปานกลาง', 'น้อย', 'น้อยที่สุด') NULL,
            problems TEXT NULL,
            suggestions TEXT NULL,
            conclusion TEXT NULL,
            image1_path VARCHAR(255) NULL,
            image2_path VARCHAR(255) NULL,
            image3_path VARCHAR(255) NULL,
            image4_path VARCHAR(255) NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (disbursement_id) REFERENCES sbms_disbursements(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
    },

    'down' => function (PDO $pdo) {
        $pdo->exec("DROP TABLE IF EXISTS sbms_summaries");
        $pdo->exec("ALTER TABLE sbms_disbursements 
            DROP COLUMN IF EXISTS expense_items,
            DROP COLUMN IF EXISTS total_spent_before,
            DROP COLUMN IF EXISTS balance_remaining
        ");
    },
];

