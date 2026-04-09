<?php
// Migration: Add boss_pin column to wfh_system_settings
// DDL (ALTER TABLE) ใน MySQL auto-commit — ไม่ต้องใช้ transaction
return [
    'up' => function(PDO $pdo) {
        // ตรวจก่อนว่ามี column แล้วหรือยัง
        $cols = $pdo->query("SHOW COLUMNS FROM wfh_system_settings LIKE 'boss_pin'")->fetchAll();
        if (empty($cols)) {
            $pdo->exec("ALTER TABLE wfh_system_settings ADD COLUMN boss_pin VARCHAR(255) NULL COMMENT 'Hashed PIN สำหรับ ผอ.อนุมัติ'");
        }
        // Mark done without transaction
        return true;
    },
    'down' => function(PDO $pdo) {
        $cols = $pdo->query("SHOW COLUMNS FROM wfh_system_settings LIKE 'boss_pin'")->fetchAll();
        if (!empty($cols)) {
            $pdo->exec("ALTER TABLE wfh_system_settings DROP COLUMN boss_pin");
        }
        return true;
    }
];
