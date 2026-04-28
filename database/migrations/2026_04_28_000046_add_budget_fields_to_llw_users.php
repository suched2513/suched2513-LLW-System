<?php
/**
 * Migration: add_budget_fields_to_llw_users
 * เพิ่ม column department_id และ owner_name ให้ตาราง llw_users
 * เพื่อรองรับการทำงานของระบบงบประมาณ (school_project)
 */
return [
    'up' => function (PDO $pdo) {
        // เพิ่ม department_id ถ้ายังไม่มี
        $cols = $pdo->query("SHOW COLUMNS FROM llw_users LIKE 'department_id'")->fetchAll();
        if (empty($cols)) {
            $pdo->exec("ALTER TABLE llw_users ADD COLUMN `department_id` INT NULL DEFAULT NULL COMMENT 'FK → departments.id (ระบบงบประมาณ)'");
        }

        // เพิ่ม owner_name ถ้ายังไม่มี
        $cols2 = $pdo->query("SHOW COLUMNS FROM llw_users LIKE 'owner_name'")->fetchAll();
        if (empty($cols2)) {
            $pdo->exec("ALTER TABLE llw_users ADD COLUMN `owner_name` VARCHAR(200) NULL DEFAULT NULL COMMENT 'ชื่อผู้รับผิดชอบโครงการ (ต้องตรงกับ budget_projects.owner_name)'");
        }
    },
    'down' => function (PDO $pdo) {
        $pdo->exec("ALTER TABLE llw_users DROP COLUMN IF EXISTS `owner_name`");
        $pdo->exec("ALTER TABLE llw_users DROP COLUMN IF EXISTS `department_id`");
    },
];
