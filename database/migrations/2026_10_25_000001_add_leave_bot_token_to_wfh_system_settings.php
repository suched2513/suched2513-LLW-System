<?php
return [
    'up' => function (PDO $pdo) {
        $cols = $pdo->query("SHOW COLUMNS FROM wfh_system_settings LIKE 'leave_bot_token'")->fetchAll();
        if (empty($cols)) {
            $pdo->exec("ALTER TABLE wfh_system_settings ADD COLUMN leave_bot_token VARCHAR(200) NULL AFTER leave_chat_id");
        }
    },
    'down' => function (PDO $pdo) {
        $cols = $pdo->query("SHOW COLUMNS FROM wfh_system_settings LIKE 'leave_bot_token'")->fetchAll();
        if (!empty($cols)) {
            $pdo->exec("ALTER TABLE wfh_system_settings DROP COLUMN leave_bot_token");
        }
    },
];
