<?php
return [
    'up' => function (PDO $pdo) {
        $col = $pdo->query("SHOW COLUMNS FROM wfh_system_settings LIKE 'duty_bot_token'")->fetchAll();
        if (empty($col)) {
            $pdo->exec("ALTER TABLE wfh_system_settings ADD COLUMN duty_bot_token VARCHAR(200) DEFAULT '' AFTER duty_chat_id");
        }
    },
    'down' => function (PDO $pdo) {
        $col = $pdo->query("SHOW COLUMNS FROM wfh_system_settings LIKE 'duty_bot_token'")->fetchAll();
        if (!empty($col)) {
            $pdo->exec("ALTER TABLE wfh_system_settings DROP COLUMN duty_bot_token");
        }
    },
];
