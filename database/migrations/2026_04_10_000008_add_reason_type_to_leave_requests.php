<?php
// Migration: Add reason_type column to leave_requests
return [
    'up' => function(PDO $pdo) {
        $cols = $pdo->query("SHOW COLUMNS FROM leave_requests LIKE 'reason_type'")->fetchAll();
        if (empty($cols)) {
            $pdo->exec("ALTER TABLE leave_requests ADD COLUMN reason_type VARCHAR(60) NOT NULL DEFAULT '\u0e2d\u0e37\u0e48\u0e19\u0e46' AFTER reason");
        }
        return true;
    },
    'down' => function(PDO $pdo) {
        $cols = $pdo->query("SHOW COLUMNS FROM leave_requests LIKE 'reason_type'")->fetchAll();
        if (!empty($cols)) {
            $pdo->exec("ALTER TABLE leave_requests DROP COLUMN reason_type");
        }
        return true;
    }
];
