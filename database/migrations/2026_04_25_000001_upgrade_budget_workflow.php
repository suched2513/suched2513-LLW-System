<?php
return [
    'up' => function (PDO $pdo) {
        // Add workflow columns to budget_disbursements
        $sql = "ALTER TABLE budget_disbursements 
                ADD COLUMN doc_no VARCHAR(50) NULL AFTER disbursement_id,
                ADD COLUMN current_step VARCHAR(50) DEFAULT 'pending_project' AFTER status,
                
                -- Step 2: Project Head
                ADD COLUMN project_head_id INT NULL,
                ADD COLUMN project_head_signed_at DATETIME NULL,
                
                -- Step 3: Plan Head
                ADD COLUMN plan_head_id INT NULL,
                ADD COLUMN plan_head_signed_at DATETIME NULL,
                ADD COLUMN plan_budget_total DECIMAL(15,2) NULL,
                ADD COLUMN plan_budget_used DECIMAL(15,2) NULL,
                ADD COLUMN plan_budget_remain DECIMAL(15,2) NULL,
                ADD COLUMN plan_is_in_plan TINYINT(1) DEFAULT 1,
                
                -- Step 4: Procurement Head
                ADD COLUMN procurement_head_id INT NULL,
                ADD COLUMN procurement_head_signed_at DATETIME NULL,
                ADD COLUMN procurement_result ENUM('can_buy', 'cannot_buy') NULL,
                
                -- Step 5: Finance Head
                ADD COLUMN finance_head_id INT NULL,
                ADD COLUMN finance_head_signed_at DATETIME NULL,
                
                -- Step 6: Deputy Director
                ADD COLUMN deputy_id INT NULL,
                ADD COLUMN deputy_signed_at DATETIME NULL,
                ADD COLUMN deputy_comment TEXT NULL,
                ADD COLUMN deputy_result ENUM('approved', 'rejected') NULL,
                
                -- Step 7: Director
                ADD COLUMN director_id INT NULL,
                ADD COLUMN director_signed_at DATETIME NULL,
                ADD COLUMN director_result ENUM('approved', 'rejected') NULL,
                
                ADD INDEX (current_step),
                ADD INDEX (status)";
        
        $pdo->exec($sql);
    },
    'down' => function (PDO $pdo) {
        $sql = "ALTER TABLE budget_disbursements 
                DROP COLUMN doc_no, DROP COLUMN current_step,
                DROP COLUMN project_head_id, DROP COLUMN project_head_signed_at,
                DROP COLUMN plan_head_id, DROP COLUMN plan_head_signed_at,
                DROP COLUMN plan_budget_total, DROP COLUMN plan_budget_used, DROP COLUMN plan_budget_remain, DROP COLUMN plan_is_in_plan,
                DROP COLUMN procurement_head_id, DROP COLUMN procurement_head_signed_at, DROP COLUMN procurement_result,
                DROP COLUMN finance_head_id, DROP COLUMN finance_head_signed_at,
                DROP COLUMN deputy_id, DROP COLUMN deputy_signed_at, DROP COLUMN deputy_comment, DROP COLUMN deputy_result,
                DROP COLUMN director_id, DROP COLUMN director_signed_at, DROP COLUMN director_result";
        $pdo->exec($sql);
    }
];
