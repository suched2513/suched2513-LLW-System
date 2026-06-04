<?php
return [
    'up' => function (PDO $pdo) {
        $cols = $pdo->query("SHOW COLUMNS FROM duty_teachers LIKE 'photo'")->fetchAll();
        if (empty($cols)) {
            $pdo->exec("ALTER TABLE duty_teachers ADD COLUMN photo VARCHAR(255) NULL DEFAULT NULL AFTER full_name");
        }
    },
    'down' => function (PDO $pdo) {
        $cols = $pdo->query("SHOW COLUMNS FROM duty_teachers LIKE 'photo'")->fetchAll();
        if (!empty($cols)) {
            $pdo->exec("ALTER TABLE duty_teachers DROP COLUMN photo");
        }
    },
];
