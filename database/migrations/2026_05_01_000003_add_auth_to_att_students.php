<?php
return [
    'up' => function (PDO $pdo) {
        $existing = array_column($pdo->query("SHOW COLUMNS FROM att_students")->fetchAll(PDO::FETCH_ASSOC), 'Field');
        if (!in_array('national_id_hash', $existing)) {
            $pdo->exec("ALTER TABLE att_students ADD COLUMN national_id_hash VARCHAR(255) NULL AFTER classroom");
        }
        if (!in_array('national_id_masked', $existing)) {
            $pdo->exec("ALTER TABLE att_students ADD COLUMN national_id_masked VARCHAR(20) NULL AFTER national_id_hash");
        }
        if (!in_array('last_login', $existing)) {
            $pdo->exec("ALTER TABLE att_students ADD COLUMN last_login DATETIME NULL AFTER national_id_masked");
        }
    },
    'down' => function (PDO $pdo) {
        foreach (['last_login', 'national_id_masked', 'national_id_hash'] as $col) {
            $rows = $pdo->query("SHOW COLUMNS FROM att_students LIKE '$col'")->fetchAll();
            if ($rows) $pdo->exec("ALTER TABLE att_students DROP COLUMN $col");
        }
    },
];
