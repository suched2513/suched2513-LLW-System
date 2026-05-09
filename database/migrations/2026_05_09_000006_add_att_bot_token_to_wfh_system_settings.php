<?php
return [
    'up' => function (PDO $pdo) {
        $cols = $pdo->query("SHOW COLUMNS FROM wfh_system_settings LIKE 'att_bot_token'")->fetchAll();
        if (empty($cols)) {
            $pdo->exec("ALTER TABLE wfh_system_settings ADD COLUMN att_bot_token VARCHAR(200) NULL AFTER att_chat_id");
        }
    },
    'down' => function (PDO $pdo) {
        $cols = $pdo->query("SHOW COLUMNS FROM wfh_system_settings LIKE 'att_bot_token'")->fetchAll();
        if (!empty($cols)) {
            $pdo->exec("ALTER TABLE wfh_system_settings DROP COLUMN att_bot_token");
        }
    },
];
