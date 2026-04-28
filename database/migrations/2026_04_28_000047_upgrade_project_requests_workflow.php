<?php
/**
 * Migration: upgrade_project_requests_workflow
 * Adds multi-step approval columns to project_requests table.
 */
return [
    'up' => function (PDO $pdo) {
        $cols = $pdo->query("SHOW COLUMNS FROM `project_requests`")->fetchAll(PDO::FETCH_COLUMN);

        $sql = "ALTER TABLE `project_requests` ";
        $add = [];

        if (!in_array('current_step', $cols)) {
            $add[] = "ADD COLUMN `current_step` ENUM('budget','procurement','finance','deputy','director','completed') NOT NULL DEFAULT 'budget' AFTER `status`";
        }
        
        // Budget Step
        if (!in_array('budget_ok_at', $cols)) $add[] = "ADD COLUMN `budget_ok_at` DATETIME NULL";
        if (!in_array('budget_user_id', $cols)) $add[] = "ADD COLUMN `budget_user_id` INT NULL";
        if (!in_array('budget_note', $cols)) $add[] = "ADD COLUMN `budget_note` TEXT NULL";

        // Procurement Step
        if (!in_array('proc_ok_at', $cols)) $add[] = "ADD COLUMN `proc_ok_at` DATETIME NULL";
        if (!in_array('proc_user_id', $cols)) $add[] = "ADD COLUMN `proc_user_id` INT NULL";
        if (!in_array('proc_note', $cols)) $add[] = "ADD COLUMN `proc_note` TEXT NULL";

        // Finance Step
        if (!in_array('fin_ok_at', $cols)) $add[] = "ADD COLUMN `fin_ok_at` DATETIME NULL";
        if (!in_array('fin_user_id', $cols)) $add[] = "ADD COLUMN `fin_user_id` INT NULL";
        if (!in_array('fin_note', $cols)) $add[] = "ADD COLUMN `fin_note` TEXT NULL";

        // Deputy Step
        if (!in_array('deputy_ok_at', $cols)) $add[] = "ADD COLUMN `deputy_ok_at` DATETIME NULL";
        if (!in_array('deputy_user_id', $cols)) $add[] = "ADD COLUMN `deputy_user_id` INT NULL";
        if (!in_array('deputy_note', $cols)) $add[] = "ADD COLUMN `deputy_note` TEXT NULL";

        if (!empty($add)) {
            $pdo->exec($sql . implode(", ", $add));
        }

        // Update existing 'submitted' requests to 'budget' step if they have no step yet
        $pdo->exec("UPDATE `project_requests` SET `current_step` = 'budget' WHERE `status` = 'submitted' AND `current_step` = 'budget'");
        // If it was already approved, mark as completed
        $pdo->exec("UPDATE `project_requests` SET `current_step` = 'completed' WHERE `status` = 'approved'");
    },

    'down' => function (PDO $pdo) {
        $pdo->exec("ALTER TABLE `project_requests` 
            DROP COLUMN `current_step`,
            DROP COLUMN `budget_ok_at`, DROP COLUMN `budget_user_id`, DROP COLUMN `budget_note`,
            DROP COLUMN `proc_ok_at`, DROP COLUMN `proc_user_id`, DROP COLUMN `proc_note`,
            DROP COLUMN `fin_ok_at`, DROP COLUMN `fin_user_id`, DROP COLUMN `fin_note`,
            DROP COLUMN `deputy_ok_at`, DROP COLUMN `deputy_user_id`, DROP COLUMN `deputy_note`
        ");
    },
];
