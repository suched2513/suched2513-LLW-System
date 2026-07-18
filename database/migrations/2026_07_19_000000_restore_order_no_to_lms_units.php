<?php
/**
 * Migration: restore_order_no_to_lms_units
 * Created: 2026-07-19 00:00:00
 */
return [
    'up' => function (PDO $pdo) {
        // Check if order_no already exists in lms_units
        $stmt = $pdo->query("SHOW COLUMNS FROM lms_units LIKE 'order_no'");
        $exists = $stmt->fetch();
        if (!$exists) {
            $pdo->exec("ALTER TABLE lms_units ADD COLUMN order_no INT NOT NULL DEFAULT 1");
            $pdo->exec("UPDATE lms_units SET order_no = unit_number WHERE unit_number IS NOT NULL");
        }
    },

    'down' => function (PDO $pdo) {
        $stmt = $pdo->query("SHOW COLUMNS FROM lms_units LIKE 'order_no'");
        $exists = $stmt->fetch();
        if ($exists) {
            $pdo->exec("ALTER TABLE lms_units DROP COLUMN order_no");
        }
    },
];
