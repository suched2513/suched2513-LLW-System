<?php
/**
 * Migration: add_obstacles_to_club_groups
 * Created: 2026-10-01 12:17:01
 */
return [
    'up' => function (PDO $pdo) {
        $cols = $pdo->query("SHOW COLUMNS FROM club_groups LIKE 'obstacles'")->fetchAll();
        if (empty($cols)) {
            $pdo->exec("ALTER TABLE club_groups ADD COLUMN obstacles TEXT NULL AFTER objectives");
        }
    },

    'down' => function (PDO $pdo) {
        $cols = $pdo->query("SHOW COLUMNS FROM club_groups LIKE 'obstacles'")->fetchAll();
        if (!empty($cols)) {
            $pdo->exec("ALTER TABLE club_groups DROP COLUMN obstacles");
        }
    },
];