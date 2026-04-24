<?php
require_once 'config.php';
$db = connectDB();

try {
    $sql = "ALTER TABLE budget_disbursements 
            ADD COLUMN doc_no VARCHAR(50) NULL AFTER disbursement_id,
            ADD COLUMN current_step VARCHAR(50) DEFAULT 'pending_project' AFTER status,
            
            ADD COLUMN project_head_id INT NULL,
            ADD COLUMN project_head_signed_at DATETIME NULL,
            
            ADD COLUMN plan_head_id INT NULL,
            ADD COLUMN plan_head_signed_at DATETIME NULL,
            ADD COLUMN plan_budget_total DECIMAL(15,2) NULL,
            ADD COLUMN plan_budget_used DECIMAL(15,2) NULL,
            ADD COLUMN plan_budget_remain DECIMAL(15,2) NULL,
            ADD COLUMN plan_is_in_plan TINYINT(1) DEFAULT 1,
            
            ADD COLUMN procurement_head_id INT NULL,
            ADD COLUMN procurement_head_signed_at DATETIME NULL,
            ADD COLUMN procurement_result ENUM('can_buy', 'cannot_buy') NULL,
            
            ADD COLUMN finance_head_id INT NULL,
            ADD COLUMN finance_head_signed_at DATETIME NULL,
            
            ADD COLUMN deputy_id INT NULL,
            ADD COLUMN deputy_signed_at DATETIME NULL,
            ADD COLUMN deputy_comment TEXT NULL,
            ADD COLUMN deputy_result ENUM('approved', 'rejected') NULL,
            
            ADD COLUMN director_id INT NULL,
            ADD COLUMN director_signed_at DATETIME NULL,
            ADD COLUMN director_result ENUM('approved', 'rejected') NULL";
    
    $db->exec($sql);
    echo "<h1>Database Upgraded to Phase 3!</h1>";
    echo "<p>Workflow columns added to budget_disbursements table.</p>";
} catch (Exception $e) {
    echo "<h1>Error</h1>";
    echo "<p>" . $e->getMessage() . "</p>";
}
?>
