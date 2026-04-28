<?php
require_once 'config/db.php';
$db = getDB();
try {
    $sql = "ALTER TABLE project_requests 
            ADD COLUMN IF NOT EXISTS current_step VARCHAR(50) DEFAULT 'submitted' AFTER status,
            ADD COLUMN IF NOT EXISTS budget_ok_at DATETIME NULL,
            ADD COLUMN IF NOT EXISTS budget_user_id INT NULL,
            ADD COLUMN IF NOT EXISTS budget_note TEXT NULL,
            ADD COLUMN IF NOT EXISTS proc_ok_at DATETIME NULL,
            ADD COLUMN IF NOT EXISTS proc_user_id INT NULL,
            ADD COLUMN IF NOT EXISTS proc_note TEXT NULL,
            ADD COLUMN IF NOT EXISTS fin_ok_at DATETIME NULL,
            ADD COLUMN IF NOT EXISTS fin_user_id INT NULL,
            ADD COLUMN IF NOT EXISTS fin_note TEXT NULL,
            ADD COLUMN IF NOT EXISTS deputy_ok_at DATETIME NULL,
            ADD COLUMN IF NOT EXISTS deputy_user_id INT NULL,
            ADD COLUMN IF NOT EXISTS deputy_note TEXT NULL";
    $db->exec($sql);
    echo "Database updated successfully!";
} catch (Exception $e) {
    echo "Error updating database: " . $e->getMessage();
}
