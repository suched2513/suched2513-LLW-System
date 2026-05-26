<?php
return [
    'up' => function (PDO $pdo) {
        $cols = $pdo->query("SHOW COLUMNS FROM cb_repairs LIKE 'images'")->fetchAll();
        if (empty($cols)) {
            $pdo->exec("ALTER TABLE cb_repairs ADD COLUMN images TEXT NULL AFTER description");
        }
    },
    'down' => function (PDO $pdo) {
        $cols = $pdo->query("SHOW COLUMNS FROM cb_repairs LIKE 'images'")->fetchAll();
        if (!empty($cols)) {
            $pdo->exec("ALTER TABLE cb_repairs DROP COLUMN images");
        }
    },
];
