-- =====================================================================
-- ระบบบริหารการเงินและบัญชีสถานศึกษา - โรงเรียนละลมวิทยา
-- Complete Database Architecture Schema (Phase 1 Specification)
-- Character Set: UTF8MB4 | Collation: utf8mb4_unicode_ci | Engine: InnoDB
-- =====================================================================

SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- 1. หมวดความปลอดภัย ผู้ใช้งาน และสิทธิ์ (Users & RBAC)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `fin_users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(50) NOT NULL UNIQUE,
    `password_hash` VARCHAR(255) NOT NULL,
    `full_name` VARCHAR(150) NOT NULL,
    `email` VARCHAR(100) NULL,
    `phone` VARCHAR(30) NULL,
    `position` VARCHAR(100) NULL COMMENT 'ตำแหน่งราชการ',
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_user_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `fin_roles` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `role_code` VARCHAR(50) NOT NULL UNIQUE,
    `role_name` VARCHAR(100) NOT NULL,
    `description` VARCHAR(255) NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `fin_permissions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `module` VARCHAR(50) NOT NULL,
    `action` VARCHAR(50) NOT NULL,
    `description` VARCHAR(255) NULL,
    UNIQUE KEY `uq_module_action` (`module`, `action`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `fin_role_permissions` (
    `role_id` INT NOT NULL,
    `permission_id` INT NOT NULL,
    PRIMARY KEY (`role_id`, `permission_id`),
    FOREIGN KEY (`role_id`) REFERENCES `fin_roles`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`permission_id`) REFERENCES `fin_permissions`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `fin_user_roles` (
    `user_id` INT NOT NULL,
    `role_id` INT NOT NULL,
    PRIMARY KEY (`user_id`, `role_id`),
    FOREIGN KEY (`user_id`) REFERENCES `fin_users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`role_id`) REFERENCES `fin_roles`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 2. หมวดปีงบประมาณไทยและงวดบัญชี (Fiscal Years & Accounting Periods)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `fin_fiscal_years` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `year_be` INT NOT NULL UNIQUE COMMENT 'ปีงบประมาณ พ.ศ. เช่น 2569, 2570',
    `start_date` DATE NOT NULL COMMENT '1 ตุลาคม',
    `end_date` DATE NOT NULL COMMENT '30 กันยายน ของปีถัดไป',
    `is_current` TINYINT(1) NOT NULL DEFAULT 0,
    `is_closed` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `fin_accounting_periods` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `fiscal_year_id` INT NOT NULL,
    `period_no` TINYINT NOT NULL COMMENT 'งวดที่ 1 ถึง 12 (ต.ค. = 1, ก.ย. = 12)',
    `month_no` TINYINT NOT NULL COMMENT '1-12',
    `year_ce` INT NOT NULL COMMENT 'ปี ค.ศ.',
    `start_date` DATE NOT NULL,
    `end_date` DATE NOT NULL,
    `status` ENUM('open', 'closed', 'locked') NOT NULL DEFAULT 'open',
    `closed_at` DATETIME NULL,
    `closed_by` INT NULL,
    FOREIGN KEY (`fiscal_year_id`) REFERENCES `fin_fiscal_years`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`closed_by`) REFERENCES `fin_users`(`id`),
    UNIQUE KEY `uq_year_period` (`fiscal_year_id`, `period_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 3. หมวดแหล่งเงิน โครงการ และการควบคุมงบประมาณ (Budgets & Commitments)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `fin_funding_sources` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `source_code` VARCHAR(50) NOT NULL UNIQUE,
    `source_name` VARCHAR(150) NOT NULL,
    `source_type` ENUM('subsidy', 'revenue', 'donation', 'tuition_fee', 'other') NOT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `fin_projects` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `fiscal_year_id` INT NOT NULL,
    `project_code` VARCHAR(50) NOT NULL,
    `project_name` VARCHAR(255) NOT NULL,
    `department` VARCHAR(150) NULL,
    `responsible_user_id` INT NULL,
    `status` ENUM('draft', 'active', 'completed', 'cancelled') NOT NULL DEFAULT 'active',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`fiscal_year_id`) REFERENCES `fin_fiscal_years`(`id`),
    FOREIGN KEY (`responsible_user_id`) REFERENCES `fin_users`(`id`),
    UNIQUE KEY `uq_fy_project` (`fiscal_year_id`, `project_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `fin_budgets` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `fiscal_year_id` INT NOT NULL,
    `project_id` INT NOT NULL,
    `funding_source_id` INT NOT NULL,
    `budget_category` ENUM('personnel', 'operating', 'investment', 'subsidy', 'other') NOT NULL,
    `initial_amount` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    `transfer_in_amount` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    `transfer_out_amount` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    `net_amount` DECIMAL(14,2) GENERATED ALWAYS AS (initial_amount + transfer_in_amount - transfer_out_amount) STORED,
    `committed_amount` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    `paid_amount` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`fiscal_year_id`) REFERENCES `fin_fiscal_years`(`id`),
    FOREIGN KEY (`project_id`) REFERENCES `fin_projects`(`id`),
    FOREIGN KEY (`funding_source_id`) REFERENCES `fin_funding_sources`(`id`),
    UNIQUE KEY `uq_budget_line` (`fiscal_year_id`, `project_id`, `funding_source_id`, `budget_category`),
    INDEX `idx_budget_net` (`net_amount`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `fin_budget_commitments` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `budget_id` INT NOT NULL,
    `reference_type` ENUM('purchase_request', 'payment', 'advance') NOT NULL,
    `reference_id` INT NOT NULL,
    `amount` DECIMAL(14,2) NOT NULL,
    `status` ENUM('active', 'liquidated', 'cancelled') NOT NULL DEFAULT 'active',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`budget_id`) REFERENCES `fin_budgets`(`id`),
    INDEX `idx_commit_ref` (`reference_type`, `reference_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `fin_budget_transfers` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `transfer_no` VARCHAR(50) NOT NULL UNIQUE,
    `transfer_date` DATE NOT NULL,
    `from_budget_id` INT NOT NULL,
    `to_budget_id` INT NOT NULL,
    `amount` DECIMAL(14,2) NOT NULL,
    `reason` TEXT NOT NULL,
    `status` ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
    `created_by` INT NOT NULL,
    `approved_by` INT NULL,
    `approved_at` DATETIME NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`from_budget_id`) REFERENCES `fin_budgets`(`id`),
    FOREIGN KEY (`to_budget_id`) REFERENCES `fin_budgets`(`id`),
    FOREIGN KEY (`created_by`) REFERENCES `fin_users`(`id`),
    FOREIGN KEY (`approved_by`) REFERENCES `fin_users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 4. หมวดการเงิน รับเงิน เบิกจ่าย และเงินยืม (Receipts, Payments, Advances)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `fin_receipts` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `receipt_no` VARCHAR(50) NOT NULL UNIQUE,
    `receipt_date` DATE NOT NULL,
    `fiscal_year_id` INT NOT NULL,
    `funding_source_id` INT NOT NULL,
    `payer_name` VARCHAR(255) NOT NULL,
    `payer_tax_id` VARCHAR(30) NULL,
    `payment_method` ENUM('cash', 'transfer', 'cheque') NOT NULL,
    `bank_account_id` INT NULL,
    `bank_ref_no` VARCHAR(100) NULL,
    `total_amount` DECIMAL(14,2) NOT NULL,
    `status` ENUM('issued', 'deposited', 'cancelled') NOT NULL DEFAULT 'issued',
    `cancellation_reason` VARCHAR(255) NULL,
    `cancelled_by` INT NULL,
    `cancelled_at` DATETIME NULL,
    `created_by` INT NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`fiscal_year_id`) REFERENCES `fin_fiscal_years`(`id`),
    FOREIGN KEY (`funding_source_id`) REFERENCES `fin_funding_sources`(`id`),
    FOREIGN KEY (`created_by`) REFERENCES `fin_users`(`id`),
    INDEX `idx_receipt_date` (`receipt_date`),
    INDEX `idx_receipt_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `fin_receipt_items` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `receipt_id` INT NOT NULL,
    `item_description` VARCHAR(255) NOT NULL,
    `amount` DECIMAL(14,2) NOT NULL,
    `account_id` INT NULL,
    FOREIGN KEY (`receipt_id`) REFERENCES `fin_receipts`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `fin_payments` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `voucher_no` VARCHAR(50) NOT NULL UNIQUE COMMENT 'เลขที่ใบสำคัญจ่าย',
    `payment_date` DATE NOT NULL,
    `fiscal_year_id` INT NOT NULL,
    `budget_id` INT NOT NULL,
    `requester_id` INT NOT NULL,
    `payee_name` VARCHAR(255) NOT NULL,
    `payee_tax_id` VARCHAR(30) NULL,
    `payment_method` ENUM('transfer', 'cheque', 'cash') NOT NULL,
    `bank_account_id` INT NULL,
    `cheque_no` VARCHAR(50) NULL,
    `gross_amount` DECIMAL(14,2) NOT NULL,
    `withholding_tax_percent` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    `withholding_tax_amount` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    `net_amount` DECIMAL(14,2) NOT NULL,
    `workflow_status` ENUM('draft', 'proposed', 'audited', 'approved', 'disbursed', 'posted', 'rejected', 'cancelled') NOT NULL DEFAULT 'draft',
    `audited_by` INT NULL,
    `audited_at` DATETIME NULL,
    `approved_by` INT NULL,
    `approved_at` DATETIME NULL,
    `disbursed_by` INT NULL,
    `disbursed_at` DATETIME NULL,
    `posted_at` DATETIME NULL,
    `rejection_reason` VARCHAR(255) NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`fiscal_year_id`) REFERENCES `fin_fiscal_years`(`id`),
    FOREIGN KEY (`budget_id`) REFERENCES `fin_budgets`(`id`),
    FOREIGN KEY (`requester_id`) REFERENCES `fin_users`(`id`),
    FOREIGN KEY (`audited_by`) REFERENCES `fin_users`(`id`),
    FOREIGN KEY (`approved_by`) REFERENCES `fin_users`(`id`),
    FOREIGN KEY (`disbursed_by`) REFERENCES `fin_users`(`id`),
    INDEX `idx_pay_status` (`workflow_status`),
    INDEX `idx_pay_date` (`payment_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `fin_payment_items` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `payment_id` INT NOT NULL,
    `item_description` VARCHAR(255) NOT NULL,
    `quantity` DECIMAL(10,2) NOT NULL DEFAULT 1.00,
    `unit_price` DECIMAL(14,2) NOT NULL,
    `amount` DECIMAL(14,2) NOT NULL,
    `account_id` INT NULL,
    FOREIGN KEY (`payment_id`) REFERENCES `fin_payments`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `fin_advances` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `contract_no` VARCHAR(50) NOT NULL UNIQUE COMMENT 'สัญญายืมเงิน แบบ 8500',
    `contract_date` DATE NOT NULL,
    `fiscal_year_id` INT NOT NULL,
    `budget_id` INT NOT NULL,
    `borrower_id` INT NOT NULL,
    `objective` TEXT NOT NULL,
    `amount` DECIMAL(14,2) NOT NULL,
    `due_date` DATE NOT NULL,
    `payment_id` INT NULL COMMENT 'ใบสำคัญจ่ายตอนจ่ายเงินยืม',
    `status` ENUM('draft', 'approved', 'disbursed', 'clearing_pending', 'settled', 'overdue') NOT NULL DEFAULT 'draft',
    `cleared_amount` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`fiscal_year_id`) REFERENCES `fin_fiscal_years`(`id`),
    FOREIGN KEY (`budget_id`) REFERENCES `fin_budgets`(`id`),
    FOREIGN KEY (`borrower_id`) REFERENCES `fin_users`(`id`),
    FOREIGN KEY (`payment_id`) REFERENCES `fin_payments`(`id`),
    INDEX `idx_adv_status` (`status`),
    INDEX `idx_adv_due` (`due_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `fin_advance_clearings` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `clearing_no` VARCHAR(50) NOT NULL UNIQUE,
    `clearing_date` DATE NOT NULL,
    `advance_id` INT NOT NULL,
    `actual_expense_amount` DECIMAL(14,2) NOT NULL,
    `refund_amount` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    `reimburse_amount` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    `status` ENUM('submitted', 'verified', 'approved') NOT NULL DEFAULT 'submitted',
    `verified_by` INT NULL,
    `approved_by` INT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`advance_id`) REFERENCES `fin_advances`(`id`),
    FOREIGN KEY (`verified_by`) REFERENCES `fin_users`(`id`),
    FOREIGN KEY (`approved_by`) REFERENCES `fin_users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 5. หมวดระบบบัญชีคู่ (Double-Entry Chart of Accounts & Journals)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `fin_chart_of_accounts` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `account_code` VARCHAR(30) NOT NULL UNIQUE,
    `account_name` VARCHAR(150) NOT NULL,
    `account_category` ENUM('asset', 'liability', 'equity', 'revenue', 'expense') NOT NULL,
    `normal_balance` ENUM('debit', 'credit') NOT NULL,
    `parent_account_id` INT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    FOREIGN KEY (`parent_account_id`) REFERENCES `fin_chart_of_accounts`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `fin_journal_entries` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `entry_no` VARCHAR(50) NOT NULL UNIQUE,
    `entry_date` DATE NOT NULL,
    `fiscal_year_id` INT NOT NULL,
    `accounting_period_id` INT NOT NULL,
    `entry_type` ENUM('receipt', 'payment', 'advance', 'clearing', 'general', 'adjusting', 'closing') NOT NULL,
    `reference_type` VARCHAR(50) NULL,
    `reference_id` INT NULL,
    `description` TEXT NOT NULL,
    `total_debit` DECIMAL(14,2) NOT NULL,
    `total_credit` DECIMAL(14,2) NOT NULL,
    `is_posted` TINYINT(1) NOT NULL DEFAULT 1,
    `is_reversed` TINYINT(1) NOT NULL DEFAULT 0,
    `reversed_by_entry_id` INT NULL,
    `created_by` INT NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`fiscal_year_id`) REFERENCES `fin_fiscal_years`(`id`),
    FOREIGN KEY (`accounting_period_id`) REFERENCES `fin_accounting_periods`(`id`),
    FOREIGN KEY (`created_by`) REFERENCES `fin_users`(`id`),
    INDEX `idx_journal_date` (`entry_date`),
    INDEX `idx_journal_ref` (`reference_type`, `reference_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `fin_journal_lines` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `journal_entry_id` INT NOT NULL,
    `account_id` INT NOT NULL,
    `debit_amount` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    `credit_amount` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    `line_description` VARCHAR(255) NULL,
    FOREIGN KEY (`journal_entry_id`) REFERENCES `fin_journal_entries`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`account_id`) REFERENCES `fin_chart_of_accounts`(`id`),
    INDEX `idx_line_acc` (`account_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 6. หมวดบัญชีธนาคารและการกระทบยอด (Bank Accounts & Reconciliation)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `fin_bank_accounts` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `bank_name` VARCHAR(100) NOT NULL,
    `branch_name` VARCHAR(100) NULL,
    `account_no` VARCHAR(50) NOT NULL UNIQUE,
    `account_name` VARCHAR(150) NOT NULL,
    `account_type` ENUM('current', 'savings', 'fixed') NOT NULL DEFAULT 'savings',
    `current_balance` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    `funding_source_id` INT NULL,
    `gl_account_id` INT NULL COMMENT 'ผังบัญชีเงินฝากธนาคาร',
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    FOREIGN KEY (`funding_source_id`) REFERENCES `fin_funding_sources`(`id`),
    FOREIGN KEY (`gl_account_id`) REFERENCES `fin_chart_of_accounts`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `fin_bank_transactions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `bank_account_id` INT NOT NULL,
    `trans_date` DATE NOT NULL,
    `trans_type` ENUM('deposit', 'withdrawal', 'transfer_in', 'transfer_out', 'fee', 'interest') NOT NULL,
    `amount` DECIMAL(14,2) NOT NULL,
    `reference_type` VARCHAR(50) NULL,
    `reference_id` INT NULL,
    `paired_transaction_id` INT NULL COMMENT 'คู่โอนระหว่างบัญชี',
    `is_reconciled` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`bank_account_id`) REFERENCES `fin_bank_accounts`(`id`),
    INDEX `idx_bank_trans_date` (`trans_date`),
    INDEX `idx_bank_rec` (`is_reconciled`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `fin_statement_imports` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `bank_account_id` INT NOT NULL,
    `statement_month` VARCHAR(7) NOT NULL COMMENT 'YYYY-MM',
    `original_filename` VARCHAR(255) NOT NULL,
    `uploaded_by` INT NOT NULL,
    `uploaded_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`bank_account_id`) REFERENCES `fin_bank_accounts`(`id`),
    FOREIGN KEY (`uploaded_by`) REFERENCES `fin_users`(`id`),
    UNIQUE KEY `uq_bank_month` (`bank_account_id`, `statement_month`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `fin_statement_lines` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `statement_import_id` INT NOT NULL,
    `line_date` DATE NOT NULL,
    `description` VARCHAR(255) NULL,
    `withdrawal` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    `deposit` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    `balance` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    `matched_bank_transaction_id` INT NULL,
    `match_status` ENUM('unmatched', 'candidate', 'confirmed') NOT NULL DEFAULT 'unmatched',
    FOREIGN KEY (`statement_import_id`) REFERENCES `fin_statement_imports`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`matched_bank_transaction_id`) REFERENCES `fin_bank_transactions`(`id`),
    INDEX `idx_stmt_match` (`match_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `fin_reconciliations` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `bank_account_id` INT NOT NULL,
    `as_of_date` DATE NOT NULL,
    `book_balance` DECIMAL(14,2) NOT NULL,
    `bank_statement_balance` DECIMAL(14,2) NOT NULL,
    `reconciled_difference` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    `status` ENUM('draft', 'verified', 'closed') NOT NULL DEFAULT 'draft',
    `verified_by` INT NULL,
    `verified_at` DATETIME NULL,
    FOREIGN KEY (`bank_account_id`) REFERENCES `fin_bank_accounts`(`id`),
    FOREIGN KEY (`verified_by`) REFERENCES `fin_users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 7. หมวดห้องเรียนพิเศษและค่าบำรุงการศึกษา (Special Programs & Tuition)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `fin_special_programs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `program_code` VARCHAR(30) NOT NULL UNIQUE COMMENT 'EP, MEP, IEP, IP, Gifted, SMP, etc.',
    `program_name` VARCHAR(100) NOT NULL,
    `description` VARCHAR(255) NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `fin_fee_rates` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `academic_year_be` INT NOT NULL,
    `semester` TINYINT NOT NULL,
    `special_program_id` INT NOT NULL,
    `grade_level` VARCHAR(20) NOT NULL COMMENT 'ม.1 - ม.6',
    `fee_amount` DECIMAL(14,2) NOT NULL,
    FOREIGN KEY (`special_program_id`) REFERENCES `fin_special_programs`(`id`),
    UNIQUE KEY `uq_fee_rate` (`academic_year_be`, `semester`, `special_program_id`, `grade_level`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `fin_student_charges` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `student_id` VARCHAR(30) NOT NULL,
    `student_name` VARCHAR(150) NOT NULL,
    `classroom` VARCHAR(20) NOT NULL,
    `fee_rate_id` INT NOT NULL,
    `original_amount` DECIMAL(14,2) NOT NULL,
    `discount_amount` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    `waived_amount` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    `net_amount` DECIMAL(14,2) NOT NULL,
    `paid_amount` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    `status` ENUM('unpaid', 'partial', 'paid', 'waived') NOT NULL DEFAULT 'unpaid',
    `receipt_id` INT NULL,
    FOREIGN KEY (`fee_rate_id`) REFERENCES `fin_fee_rates`(`id`),
    FOREIGN KEY (`receipt_id`) REFERENCES `fin_receipts`(`id`),
    INDEX `idx_charge_student` (`student_id`),
    INDEX `idx_charge_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 8. หมวดจัดซื้อจัดจ้าง พัสดุ และครุภัณฑ์ (Procurement, Inventory, Assets)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `fin_suppliers` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `tax_id` VARCHAR(30) NOT NULL UNIQUE,
    `name` VARCHAR(255) NOT NULL,
    `contact_person` VARCHAR(100) NULL,
    `phone` VARCHAR(50) NULL,
    `address` TEXT NULL,
    `bank_name` VARCHAR(100) NULL,
    `bank_account_no` VARCHAR(50) NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `fin_purchase_requests` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `pr_no` VARCHAR(50) NOT NULL UNIQUE COMMENT 'ใบขอซื้อ/ขอจ้าง',
    `request_date` DATE NOT NULL,
    `fiscal_year_id` INT NOT NULL,
    `budget_id` INT NOT NULL,
    `procurement_method` ENUM('specific', 'bidding', 'selection', 'price_agreement') NOT NULL DEFAULT 'specific',
    `subject` VARCHAR(255) NOT NULL,
    `estimated_amount` DECIMAL(14,2) NOT NULL,
    `status` ENUM('draft', 'proposed', 'approved', 'ordered', 'cancelled') NOT NULL DEFAULT 'draft',
    `requester_id` INT NOT NULL,
    `approved_by` INT NULL,
    `approved_at` DATETIME NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`fiscal_year_id`) REFERENCES `fin_fiscal_years`(`id`),
    FOREIGN KEY (`budget_id`) REFERENCES `fin_budgets`(`id`),
    FOREIGN KEY (`requester_id`) REFERENCES `fin_users`(`id`),
    FOREIGN KEY (`approved_by`) REFERENCES `fin_users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `fin_purchase_orders` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `po_no` VARCHAR(50) NOT NULL UNIQUE COMMENT 'ใบสั่งซื้อ/สั่งจ้าง',
    `po_date` DATE NOT NULL,
    `pr_id` INT NOT NULL,
    `supplier_id` INT NOT NULL,
    `contract_no` VARCHAR(50) NULL,
    `total_amount` DECIMAL(14,2) NOT NULL,
    `delivery_due_date` DATE NULL,
    `status` ENUM('active', 'completed', 'cancelled') NOT NULL DEFAULT 'active',
    FOREIGN KEY (`pr_id`) REFERENCES `fin_purchase_requests`(`id`),
    FOREIGN KEY (`supplier_id`) REFERENCES `fin_suppliers`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `fin_inspections` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `inspection_no` VARCHAR(50) NOT NULL UNIQUE COMMENT 'รายงานตรวจรับพัสดุ',
    `inspection_date` DATE NOT NULL,
    `po_id` INT NOT NULL,
    `result` ENUM('accepted', 'rejected') NOT NULL DEFAULT 'accepted',
    `inspector_user_id` INT NOT NULL,
    `remarks` TEXT NULL,
    FOREIGN KEY (`po_id`) REFERENCES `fin_purchase_orders`(`id`),
    FOREIGN KEY (`inspector_user_id`) REFERENCES `fin_users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `fin_inventory_items` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `item_code` VARCHAR(50) NOT NULL UNIQUE,
    `item_name` VARCHAR(150) NOT NULL,
    `category` VARCHAR(100) NULL,
    `unit` VARCHAR(30) NOT NULL,
    `current_stock` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `minimum_stock` DECIMAL(10,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `fin_stock_movements` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `inventory_item_id` INT NOT NULL,
    `movement_date` DATE NOT NULL,
    `movement_type` ENUM('in', 'out', 'adjust') NOT NULL,
    `quantity` DECIMAL(10,2) NOT NULL,
    `unit_cost` DECIMAL(14,2) NOT NULL,
    `reference_doc` VARCHAR(100) NULL,
    `created_by` INT NOT NULL,
    FOREIGN KEY (`inventory_item_id`) REFERENCES `fin_inventory_items`(`id`),
    FOREIGN KEY (`created_by`) REFERENCES `fin_users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `fin_assets` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `asset_code` VARCHAR(50) NOT NULL UNIQUE COMMENT 'เลขรหัสครุภัณฑ์ราชการ',
    `asset_name` VARCHAR(200) NOT NULL,
    `category` VARCHAR(100) NULL,
    `acquired_date` DATE NOT NULL,
    `cost_amount` DECIMAL(14,2) NOT NULL,
    `useful_life_years` INT NOT NULL DEFAULT 5,
    `depreciation_rate` DECIMAL(5,2) NOT NULL DEFAULT 20.00,
    `current_location` VARCHAR(150) NULL,
    `custodian_user_id` INT NULL COMMENT 'ผู้รับผิดชอบ',
    `status` ENUM('in_use', 'damaged', 'under_repair', 'disposed', 'transferred') NOT NULL DEFAULT 'in_use',
    FOREIGN KEY (`custodian_user_id`) REFERENCES `fin_users`(`id`),
    INDEX `idx_asset_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `fin_asset_maintenances` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `asset_id` INT NOT NULL,
    `maintenance_date` DATE NOT NULL,
    `description` TEXT NOT NULL,
    `cost_amount` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    `technician_name` VARCHAR(150) NULL,
    FOREIGN KEY (`asset_id`) REFERENCES `fin_assets`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 9. หมวดแม่แบบ ลายเซ็น ไฟล์แนบ และประวัติการใช้งาน (Templates, Signatures & Audit Trail)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `fin_document_templates` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `template_code` VARCHAR(50) NOT NULL UNIQUE,
    `template_name` VARCHAR(150) NOT NULL,
    `form_code` VARCHAR(50) NULL COMMENT 'เช่น 8500, 8708, บก.111',
    `is_active` TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `fin_signatures` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `position_title` VARCHAR(150) NOT NULL,
    `signature_image_path` VARCHAR(255) NOT NULL,
    `start_date` DATE NOT NULL,
    `end_date` DATE NULL,
    `is_allowed` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `fin_users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `fin_attachments` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `entity_type` VARCHAR(50) NOT NULL COMMENT 'payment, receipt, advance, pr, po',
    `entity_id` INT NOT NULL,
    `file_path` VARCHAR(255) NOT NULL,
    `original_name` VARCHAR(255) NOT NULL,
    `mime_type` VARCHAR(100) NOT NULL,
    `file_size_bytes` BIGINT NOT NULL,
    `uploaded_by` INT NOT NULL,
    `uploaded_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`uploaded_by`) REFERENCES `fin_users`(`id`),
    INDEX `idx_attach_entity` (`entity_type`, `entity_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `fin_system_settings` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `setting_key` VARCHAR(100) NOT NULL UNIQUE,
    `setting_value` TEXT NULL,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `fin_audit_logs` (
    `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NULL,
    `action` VARCHAR(50) NOT NULL COMMENT 'LOGIN, CREATE, UPDATE, APPROVE, DISBURSE, POST, CANCEL, EXPORT, USE_SIGNATURE',
    `entity_type` VARCHAR(50) NOT NULL,
    `entity_id` VARCHAR(50) NULL,
    `details` TEXT NULL,
    `ip_address` VARCHAR(45) NULL,
    `user_agent` VARCHAR(255) NULL,
    `status` ENUM('success', 'failed', 'blocked') NOT NULL DEFAULT 'success',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_log_user` (`user_id`),
    INDEX `idx_log_action` (`action`),
    INDEX `idx_log_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
