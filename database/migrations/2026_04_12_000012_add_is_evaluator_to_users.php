<?php
// database/migrations/2026_04_12_000012_add_is_evaluator_to_users.php

return [
    'up' => function (PDO $pdo) {
        $pdo->exec("ALTER TABLE llw_users ADD COLUMN is_evaluator TINYINT(1) DEFAULT 0 AFTER subject_group");
    },

    'down' => function (PDO $pdo) {
        $pdo->exec("ALTER TABLE llw_users DROP COLUMN is_evaluator");
    },
];
