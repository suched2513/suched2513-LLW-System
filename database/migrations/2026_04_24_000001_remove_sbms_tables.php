<?php
/**
 * Migration: remove_sbms_tables
 * ลบตารางที่เกี่ยวข้องกับระบบงบประมาณ (SBMS) ทั้งหมด
 */
return [
    'up' => function (PDO $pdo) {
        // ปิด Foreign Key Check เพื่อให้ลบได้ทุกลำดับ
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");

        $tables = [
            'sbms_summaries',
            'sbms_procurements',
            'sbms_disbursements',
            'sbms_activities',
            'sbms_vendors',
            'sbms_projects',
            'sbms_budgets',
            'sbms_fiscal_years'
        ];

        foreach ($tables as $table) {
            $pdo->exec("DROP TABLE IF EXISTS `$table`;");
        }

        $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");
    },
    'down' => function (PDO $pdo) {
        // การลบระบบแบบสมบูรณ์มักไม่มีการ Rollback กลับมาแบบเดิม 
        // แต่ในเชิงโครงสร้าง migration เราจะเว้นไว้เป็น No-op
    },
];
