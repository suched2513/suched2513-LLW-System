<?php
return [
    'up' => function (PDO $pdo) {
        $cols = $pdo->query("SHOW COLUMNS FROM llw_users LIKE 'force_password_change'")->fetchAll();
        if (empty($cols)) {
            $pdo->exec("ALTER TABLE llw_users ADD COLUMN force_password_change TINYINT(1) NOT NULL DEFAULT 0 AFTER status");
        }
    },
    'down' => function (PDO $pdo) {
        $cols = $pdo->query("SHOW COLUMNS FROM llw_users LIKE 'force_password_change'")->fetchAll();
        if (!empty($cols)) {
            $pdo->exec("ALTER TABLE llw_users DROP COLUMN force_password_change");
        }
    },
];
