<?php
return [
    'up' => function (PDO $pdo) {
        $cols = $pdo->query("SHOW COLUMNS FROM `signatories`")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('step_key', $cols)) {
            $pdo->exec("ALTER TABLE `signatories`
                ADD COLUMN `step_key` VARCHAR(50) NULL COMMENT 'submitted|budget_approved|procurement_approved|finance_approved|deputy_approved|director_final' AFTER `is_active`,
                ADD COLUMN `linked_user_id` INT NULL COMMENT 'llw_users.user_id ที่ผูกกับขั้นตอนนี้' AFTER `step_key`
            ");
        }
    },
    'down' => function (PDO $pdo) {
        $cols = $pdo->query("SHOW COLUMNS FROM `signatories`")->fetchAll(PDO::FETCH_COLUMN);
        if (in_array('step_key', $cols)) {
            $pdo->exec("ALTER TABLE `signatories` DROP COLUMN `step_key`, DROP COLUMN `linked_user_id`");
        }
    },
];
