<?php
return [
    'up' => function (PDO $pdo) {
        $cols = $pdo->query("SHOW COLUMNS FROM wfh_system_settings LIKE 'leave_chat_id'")->fetchAll();
        if (empty($cols)) {
            $pdo->exec("ALTER TABLE wfh_system_settings ADD COLUMN leave_chat_id VARCHAR(100) DEFAULT '' AFTER admin_chat_id");
        }
    },
    'down' => function (PDO $pdo) {
        $cols = $pdo->query("SHOW COLUMNS FROM wfh_system_settings LIKE 'leave_chat_id'")->fetchAll();
        if (!empty($cols)) {
            $pdo->exec("ALTER TABLE wfh_system_settings DROP COLUMN leave_chat_id");
        }
    },
];
