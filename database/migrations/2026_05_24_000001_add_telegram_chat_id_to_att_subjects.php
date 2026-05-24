<?php
return [
    'up' => function (PDO $pdo) {
        $col = $pdo->query("SHOW COLUMNS FROM `att_subjects` LIKE 'telegram_chat_id'")->fetchAll();
        if (empty($col)) {
            $pdo->exec("ALTER TABLE att_subjects ADD COLUMN telegram_chat_id VARCHAR(100) NULL DEFAULT NULL AFTER classroom");
        }
    },
    'down' => function (PDO $pdo) {
        $col = $pdo->query("SHOW COLUMNS FROM `att_subjects` LIKE 'telegram_chat_id'")->fetchAll();
        if (!empty($col)) {
            $pdo->exec("ALTER TABLE att_subjects DROP COLUMN telegram_chat_id");
        }
    },
];
