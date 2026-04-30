<?php
return [
    'up' => function (PDO $pdo) {
        $cols = $pdo->query("SHOW COLUMNS FROM bus_students LIKE 'village'")->fetchAll();
        if (empty($cols)) {
            $pdo->exec("ALTER TABLE bus_students ADD COLUMN village VARCHAR(200) NULL DEFAULT NULL AFTER classroom");
        }
    },
    'down' => function (PDO $pdo) {
        $cols = $pdo->query("SHOW COLUMNS FROM bus_students LIKE 'village'")->fetchAll();
        if (!empty($cols)) {
            $pdo->exec("ALTER TABLE bus_students DROP COLUMN village");
        }
    },
];
