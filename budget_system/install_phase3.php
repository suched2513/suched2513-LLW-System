<?php
require_once 'config.php';
$db = connectDB();

try {
    $columns = [
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

    echo "<h1>Database Upgrade Status</h1><ul>";
    foreach ($columns as $col) {
        try {
            $db->exec("ALTER TABLE budget_disbursements ADD COLUMN $col");
            echo "<li style='color: green;'>Added column: " . explode(' ', $col)[0] . " - SUCCESS</li>";
        } catch (Exception $e) {
            if (strpos($e->getMessage(), 'Duplicate column') !== false) {
                echo "<li style='color: orange;'>Column: " . explode(' ', $col)[0] . " - ALREADY EXISTS (Skipped)</li>";
            } else {
                echo "<li style='color: red;'>Error adding " . explode(' ', $col)[0] . ": " . $e->getMessage() . "</li>";
            }
        }
    }
    echo "</ul><p><b>Everything is ready for Phase 3 Digital Workflow!</b></p>";
    echo "<a href='index.php'>Go to Dashboard</a>";
} catch (Exception $e) {
    echo "<h1>Error</h1>";
    echo "<p>" . $e->getMessage() . "</p>";
}
?>
