<?php
// database/migrations/2026_04_11_000011_add_observer_details_to_sup_records.php

return [
    'up' => function (PDO $pdo) {
        // Add observer_id and observer_position to sup_records
        // Note: The previous migration (000010) created the table 'sup_records'
        // Let's ensure the table exists before altering
        $pdo->exec("
            ALTER TABLE sup_records 
            ADD COLUMN observer_id INT NULL AFTER observer_name,
            ADD COLUMN observer_position VARCHAR(200) NULL AFTER observer_id
        ");
        
        // Add optional index for observer_id for faster lookups
        $pdo->exec("CREATE INDEX idx_observer_id ON sup_records(observer_id)");
    },

    'down' => function (PDO $pdo) {
        $pdo->exec("DROP INDEX idx_observer_id ON sup_records");
        $pdo->exec("ALTER TABLE sup_records DROP COLUMN observer_id, DROP COLUMN observer_position");
    },
];
