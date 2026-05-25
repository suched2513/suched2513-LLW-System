<?php
return [
    'up' => function (PDO $pdo) {
        $cols = $pdo->query("SHOW COLUMNS FROM att_students")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('birthdate', $cols)) {
            $pdo->exec("ALTER TABLE att_students ADD COLUMN birthdate DATE NULL DEFAULT NULL AFTER gender");
        }
    },
    'down' => function (PDO $pdo) {
        $cols = $pdo->query("SHOW COLUMNS FROM att_students")->fetchAll(PDO::FETCH_COLUMN);
        if (in_array('birthdate', $cols)) {
            $pdo->exec("ALTER TABLE att_students DROP COLUMN birthdate");
        }
    },
];
