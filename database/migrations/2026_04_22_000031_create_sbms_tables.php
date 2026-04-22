<?php
/**
 * Migration: create_sbms_tables
 * สร้างตารางสำหรับระบบบริหารจัดการงบประมาณ (SBMS)
 */
return [
    'up' => function (PDO $pdo) {
        // 1. ปีงบประมาณ (Fiscal Years)
        $pdo->exec("CREATE TABLE IF NOT EXISTS sbms_fiscal_years (
            id INT AUTO_INCREMENT PRIMARY KEY,
            year_name VARCHAR(20) NOT NULL,
            start_date DATE NOT NULL,
            end_date DATE NOT NULL,
            is_active TINYINT(1) DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

        // 2. งบประมาณหลัก (Budgets)
        $pdo->exec("CREATE TABLE IF NOT EXISTS sbms_budgets (
            id INT AUTO_INCREMENT PRIMARY KEY,
            fiscal_year_id INT NOT NULL,
            budget_type ENUM('government','subsidy','revenue') NOT NULL,
            plan_name VARCHAR(200) NOT NULL,
            total_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
            used_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
            status ENUM('draft','active','closed') DEFAULT 'draft',
            created_by INT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (fiscal_year_id) REFERENCES sbms_fiscal_years(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

        // 3. โครงการ (Projects)
        $pdo->exec("CREATE TABLE IF NOT EXISTS sbms_projects (
            id INT AUTO_INCREMENT PRIMARY KEY,
            project_code VARCHAR(30) UNIQUE,
            project_name VARCHAR(200) NOT NULL,
            fiscal_year_id INT NOT NULL,
            budget_id INT,
            owner_id INT,
            requested_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
            approved_amount DECIMAL(15,2) DEFAULT 0,
            used_amount DECIMAL(15,2) DEFAULT 0,
            status ENUM('draft','pending','approved','rejected','in_progress','completed','cancelled') DEFAULT 'draft',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (fiscal_year_id) REFERENCES sbms_fiscal_years(id) ON DELETE CASCADE,
            FOREIGN KEY (budget_id) REFERENCES sbms_budgets(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

        // 4. ผู้ขาย (Vendors)
        $pdo->exec("CREATE TABLE IF NOT EXISTS sbms_vendors (
            id INT AUTO_INCREMENT PRIMARY KEY,
            vendor_name VARCHAR(200) NOT NULL,
            tax_id VARCHAR(20),
            address TEXT,
            phone VARCHAR(50),
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

        // 5. การเบิกจ่าย (Disbursements)
        $pdo->exec("CREATE TABLE IF NOT EXISTS sbms_disbursements (
            id INT AUTO_INCREMENT PRIMARY KEY,
            doc_no VARCHAR(30) UNIQUE,
            disbursement_type ENUM('project','utility','training','travel','salary','other') NOT NULL,
            project_id INT,
            amount DECIMAL(15,2) NOT NULL,
            payment_method ENUM('cash','transfer','cheque') DEFAULT 'transfer',
            status ENUM('draft','pending','approved','paid','cancelled') DEFAULT 'draft',
            created_by INT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (project_id) REFERENCES sbms_projects(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    },
    'down' => function (PDO $pdo) {
        $pdo->exec("DROP TABLE IF EXISTS sbms_disbursements;");
        $pdo->exec("DROP TABLE IF EXISTS sbms_vendors;");
        $pdo->exec("DROP TABLE IF EXISTS sbms_projects;");
        $pdo->exec("DROP TABLE IF EXISTS sbms_budgets;");
        $pdo->exec("DROP TABLE IF EXISTS sbms_fiscal_years;");
    },
];
