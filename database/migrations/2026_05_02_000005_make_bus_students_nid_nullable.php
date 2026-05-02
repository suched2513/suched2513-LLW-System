<?php
// Remove NID from bus_students — att_students is now the single auth source
return [
    'up' => function (PDO $pdo) {
        $cols = array_column($pdo->query("SHOW COLUMNS FROM bus_students")->fetchAll(PDO::FETCH_ASSOC), 'Null', 'Field');

        if (isset($cols['national_id_hash']) && $cols['national_id_hash'] === 'NO') {
            $pdo->exec("ALTER TABLE bus_students MODIFY COLUMN national_id_hash VARCHAR(255) NULL");
        }
        if (isset($cols['national_id_masked']) && $cols['national_id_masked'] === 'NO') {
            $pdo->exec("ALTER TABLE bus_students MODIFY COLUMN national_id_masked VARCHAR(20) NULL");
        }
    },
    'down' => function (PDO $pdo) {
        // Restore NOT NULL with a placeholder so rollback doesn't break existing rows
        $pdo->exec("UPDATE bus_students SET national_id_hash='rolled_back', national_id_masked='x-xxxx-xxxxx-xx-x' WHERE national_id_hash IS NULL");
        $pdo->exec("ALTER TABLE bus_students MODIFY COLUMN national_id_hash VARCHAR(255) NOT NULL");
        $pdo->exec("ALTER TABLE bus_students MODIFY COLUMN national_id_masked VARCHAR(20) NOT NULL");
    },
];
