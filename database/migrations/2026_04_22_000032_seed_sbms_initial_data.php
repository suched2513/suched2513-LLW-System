<?php
/**
 * Migration: seed_sbms_initial_data
 * เพิ่มข้อมูลเริ่มต้นสำหรับระบบงบประมาณ (SBMS)
 */
return [
    'up' => function (PDO $pdo) {
        // 1. เพิ่มปีงบประมาณปัจจุบัน (2568)
        $pdo->exec("INSERT INTO sbms_fiscal_years (year_name, start_date, end_date, is_active) 
                   VALUES ('2568', '2024-10-01', '2025-09-30', 1)");
        $yearId = $pdo->lastInsertId();

        // 2. เพิ่มหมวดงบประมาณเริ่มต้น
        $pdo->exec("INSERT INTO sbms_budgets (fiscal_year_id, budget_type, plan_name, total_amount, status) 
                   VALUES ($yearId, 'government', 'เงินอุดหนุนรายหัว', 1500000.00, 'active')");
        $pdo->exec("INSERT INTO sbms_budgets (fiscal_year_id, budget_type, plan_name, total_amount, status) 
                   VALUES ($yearId, 'subsidy', 'เงินเรียนฟรี 15 ปี (วัสดุการสอน)', 500000.00, 'active')");
        $pdo->exec("INSERT INTO sbms_budgets (fiscal_year_id, budget_type, plan_name, total_amount, status) 
                   VALUES ($yearId, 'revenue', 'เงินระดมทรัพยากร', 200000.00, 'active')");
    },
    'down' => function (PDO $pdo) {
        $pdo->exec("DELETE FROM sbms_fiscal_years WHERE year_name = '2568'");
    },
];
