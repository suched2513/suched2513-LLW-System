<?php
return [
    'up' => function (PDO $pdo) {
        // 1. เพิ่ม duty_chat_id ใน wfh_system_settings
        $col = $pdo->query("SHOW COLUMNS FROM wfh_system_settings LIKE 'duty_chat_id'")->fetchAll();
        if (empty($col)) {
            $pdo->exec("ALTER TABLE wfh_system_settings ADD COLUMN duty_chat_id VARCHAR(100) DEFAULT '' AFTER leave_chat_id");
        }

        // 2. ขยาย ENUM shift → เพิ่ม 'evening'
        foreach (['duty_schedule', 'duty_day_groups', 'duty_reports'] as $table) {
            $exists = $pdo->query("SHOW TABLES LIKE '$table'")->fetchAll();
            if (!empty($exists)) {
                $pdo->exec("ALTER TABLE `$table` MODIFY COLUMN shift ENUM('day','evening','night') NOT NULL");
            }
        }

        // 3. เพิ่ม notify_key ใน duty_settings (ใช้ protect endpoint)
        $key = bin2hex(random_bytes(16));
        $pdo->prepare("INSERT IGNORE INTO duty_settings (skey, svalue, description) VALUES (?, ?, ?)")
            ->execute(['duty_notify_key', $key, 'Secret key สำหรับ GitHub Actions เรียก notify endpoint']);
    },
    'down' => function (PDO $pdo) {
        $col = $pdo->query("SHOW COLUMNS FROM wfh_system_settings LIKE 'duty_chat_id'")->fetchAll();
        if (!empty($col)) {
            $pdo->exec("ALTER TABLE wfh_system_settings DROP COLUMN duty_chat_id");
        }
        foreach (['duty_schedule', 'duty_day_groups', 'duty_reports'] as $table) {
            $exists = $pdo->query("SHOW TABLES LIKE '$table'")->fetchAll();
            if (!empty($exists)) {
                $pdo->exec("ALTER TABLE `$table` MODIFY COLUMN shift ENUM('day','night') NOT NULL");
            }
        }
    },
];
