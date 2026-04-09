<?php
// Migration: Add boss_name column to wfh_system_settings
return [
    'up' => function(PDO $pdo) {
        $cols = $pdo->query("SHOW COLUMNS FROM wfh_system_settings LIKE 'boss_name'")->fetchAll();
        if (empty($cols)) {
            $pdo->exec("ALTER TABLE wfh_system_settings ADD COLUMN boss_name VARCHAR(150) NULL COMMENT 'ชื่อผู้อำนวยการโรงเรียน'");
        }
        return true;
    },
    'down' => function(PDO $pdo) {
        $cols = $pdo->query("SHOW COLUMNS FROM wfh_system_settings LIKE 'boss_name'")->fetchAll();
        if (!empty($cols)) {
            $pdo->exec("ALTER TABLE wfh_system_settings DROP COLUMN boss_name");
        }
        return true;
    }
];
