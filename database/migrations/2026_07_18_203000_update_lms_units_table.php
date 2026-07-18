<?php
/**
 * Migration: Update lms_units table schema to align with new manage_units features
 * Created: 2026-07-18
 */
return [
    'up' => function (PDO $pdo) {
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");

        $has_order_no = false;
        $has_unit_number = false;
        $has_description = false;

        $stmt = $pdo->query("SHOW COLUMNS FROM lms_units");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if ($row['Field'] === 'order_no') $has_order_no = true;
            if ($row['Field'] === 'unit_number') $has_unit_number = true;
            if ($row['Field'] === 'description') $has_description = true;
        }

        if ($has_order_no && !$has_unit_number) {
            $pdo->exec("ALTER TABLE lms_units CHANGE COLUMN order_no unit_number INT NOT NULL");
        } elseif (!$has_unit_number) {
            $pdo->exec("ALTER TABLE lms_units ADD COLUMN unit_number INT NOT NULL AFTER subject_id");
        }

        if (!$has_description) {
            $pdo->exec("ALTER TABLE lms_units ADD COLUMN description TEXT NULL AFTER unit_name");
        }

        $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    },

    'down' => function (PDO $pdo) {
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");

        $has_order_no = false;
        $has_unit_number = false;
        $has_description = false;

        $stmt = $pdo->query("SHOW COLUMNS FROM lms_units");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if ($row['Field'] === 'order_no') $has_order_no = true;
            if ($row['Field'] === 'unit_number') $has_unit_number = true;
            if ($row['Field'] === 'description') $has_description = true;
        }

        if ($has_unit_number && !$has_order_no) {
            $pdo->exec("ALTER TABLE lms_units CHANGE COLUMN unit_number order_no INT NOT NULL");
        }

        if ($has_description) {
            $pdo->exec("ALTER TABLE lms_units DROP COLUMN description");
        }

        $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    },
];
