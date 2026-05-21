<?php
return [
    'up' => function (PDO $pdo) {
        $cols = [
            "doc_no VARCHAR(50) NULL AFTER disbursement_id",
            "current_step VARCHAR(50) DEFAULT 'pending_project' AFTER status",
            "project_head_id INT NULL",
            "project_head_signed_at DATETIME NULL",
            "plan_head_id INT NULL",
            "plan_head_signed_at DATETIME NULL",
            "plan_budget_total DECIMAL(15,2) NULL",
            "plan_budget_used DECIMAL(15,2) NULL",
            "plan_budget_remain DECIMAL(15,2) NULL",
            "plan_is_in_plan TINYINT(1) DEFAULT 1",
            "procurement_head_id INT NULL",
            "procurement_head_signed_at DATETIME NULL",
            "procurement_result ENUM('can_buy', 'cannot_buy') NULL",
            "finance_head_id INT NULL",
            "finance_head_signed_at DATETIME NULL",
            "deputy_id INT NULL",
            "deputy_signed_at DATETIME NULL",
            "deputy_comment TEXT NULL",
            "deputy_result ENUM('approved', 'rejected') NULL",
            "director_id INT NULL",
            "director_signed_at DATETIME NULL",
            "director_result ENUM('approved', 'rejected') NULL"
        ];

        foreach ($cols as $col) {
            try {
                $name = explode(' ', trim($col))[0];
                $pdo->exec("ALTER TABLE budget_disbursements ADD COLUMN $col");
            } catch (Exception $e) {
                // Ignore if column already exists
            }
        }

        try {
            $pdo->exec("ALTER TABLE budget_disbursements ADD INDEX (current_step)");
        } catch (Exception $e) {}
        try {
            $pdo->exec("ALTER TABLE budget_disbursements ADD INDEX (status)");
        } catch (Exception $e) {}
    },
    'down' => function (PDO $pdo) {
        $cols = [
            "doc_no", "current_step",
            "project_head_id", "project_head_signed_at",
            "plan_head_id", "plan_head_signed_at",
            "plan_budget_total", "plan_budget_used", "plan_budget_remain", "plan_is_in_plan",
            "procurement_head_id", "procurement_head_signed_at", "procurement_result",
            "finance_head_id", "finance_head_signed_at",
            "deputy_id", "deputy_signed_at", "deputy_comment", "deputy_result",
            "director_id", "director_signed_at", "director_result"
        ];

        foreach ($cols as $col) {
            try {
                $pdo->exec("ALTER TABLE budget_disbursements DROP COLUMN $col");
            } catch (Exception $e) {
                // Ignore if column doesn't exist
            }
        }
    }
];
