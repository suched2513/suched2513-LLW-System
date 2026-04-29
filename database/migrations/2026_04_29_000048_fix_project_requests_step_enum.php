<?php
/**
 * Migration: fix_project_requests_step_enum
 * แก้ ENUM ของ current_step ใน project_requests ให้ตรงกับโค้ดใน approve.php
 * โค้ดเดิม (migration 47) ใช้: budget, procurement, finance, deputy, director, completed
 * โค้ดปัจจุบัน (approve.php): submitted, budget_approved, procurement_approved, finance_approved, deputy_approved, completed
 */
return [
    'up' => function (PDO $pdo) {
        // 1. แก้ ENUM ของ current_step
        $pdo->exec("ALTER TABLE `project_requests`
            MODIFY COLUMN `current_step`
            ENUM('submitted','budget_approved','procurement_approved','finance_approved','deputy_approved','completed')
            NOT NULL DEFAULT 'submitted'
        ");

        // 2. Migrate ข้อมูลเดิม: แปลงค่า step เก่า → ค่าใหม่
        $pdo->exec("UPDATE `project_requests` SET `current_step` = 'submitted'          WHERE `current_step` = 'budget'");
        $pdo->exec("UPDATE `project_requests` SET `current_step` = 'budget_approved'    WHERE `current_step` = 'procurement'");
        $pdo->exec("UPDATE `project_requests` SET `current_step` = 'procurement_approved' WHERE `current_step` = 'finance'");
        $pdo->exec("UPDATE `project_requests` SET `current_step` = 'finance_approved'   WHERE `current_step` = 'deputy'");
        $pdo->exec("UPDATE `project_requests` SET `current_step` = 'deputy_approved'    WHERE `current_step` = 'director'");

        // 3. แก้ค่า default สำหรับ records ที่ status = 'submitted' แต่ current_step ยังว่าง
        $pdo->exec("UPDATE `project_requests` SET `current_step` = 'submitted' WHERE `status` = 'submitted' AND `current_step` = 'submitted'");
        $pdo->exec("UPDATE `project_requests` SET `current_step` = 'completed' WHERE `status` = 'approved'");
    },

    'down' => function (PDO $pdo) {
        // คืน ENUM กลับเป็นค่าเดิม
        $pdo->exec("ALTER TABLE `project_requests`
            MODIFY COLUMN `current_step`
            ENUM('submitted','budget_approved','procurement_approved','finance_approved','deputy_approved','completed')
            NOT NULL DEFAULT 'submitted'
        ");
    },
];
