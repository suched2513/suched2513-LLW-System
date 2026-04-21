<?php
/**
 * Migration: Standardize all student IDs & Sync Missing Data
 * Created: 2026-04-20
 * Updated: 2026-04-21 — use UPDATE IGNORE to skip duplicate conflicts
 */

return [
    'up' => function (PDO $pdo) {

        // ─── 1. Sync Teachers to Chromebook System ────────────────────────
        if ($pdo->query("SHOW TABLES LIKE 'cb_teachers'")->fetch()) {
            $pdo->exec("INSERT IGNORE INTO cb_teachers (teacher_id, name) 
                        SELECT id, name FROM att_teachers");
        }

        // ─── 2. Fix Chromebook Students Sync ─────────────────────────────
        if ($pdo->query("SHOW TABLES LIKE 'cb_students'")->fetch()) {
            $pdo->exec("TRUNCATE TABLE cb_students");
            $pdo->exec("INSERT IGNORE INTO cb_students (student_id, name, class_name) 
                        SELECT student_id, name, classroom FROM att_students 
                        WHERE student_id IS NOT NULL AND student_id != ''");
        }

        // ─── 3. Standardize Master Tables ────────────────────────────────
        // Use UPDATE IGNORE to skip rows where padded value would cause UNIQUE conflict
        $masters = [
            'att_students'      => 'student_id',
            'assembly_students' => 'student_id',
            'beh_students'      => 'student_id',
            'cb_students'       => 'student_id'
        ];

        foreach ($masters as $table => $column) {
            if ($pdo->query("SHOW TABLES LIKE '$table'")->fetch()) {
                // UPDATE IGNORE silently skips rows that would cause duplicate key error
                $pdo->exec("UPDATE IGNORE `$table` SET `$column` = LPAD(`$column`, 5, '0') WHERE `$column` REGEXP '^[0-9]+$'");
            }
        }

        // ─── 4. Update Log Tables ─────────────────────────────────────────
        $logs = [
            'att_attendance'      => 'student_id',
            'beh_records'         => 'student_id',
            'assembly_attendance' => 'student_id',
            'assembly_checkout'   => 'student_id'
        ];

        foreach ($logs as $table => $column) {
            if ($pdo->query("SHOW TABLES LIKE '$table'")->fetch()) {
                $pdo->exec("UPDATE IGNORE `$table` SET `$column` = LPAD(`$column`, 5, '0') WHERE `$column` REGEXP '^[0-9]+$'");
            }
        }

        // ─── 5. Chromebook Borrow Logs ────────────────────────────────────
        if ($pdo->query("SHOW TABLES LIKE 'cb_borrow_logs'")->fetch()) {
            $pdo->exec("UPDATE IGNORE cb_borrow_logs SET borrower_id = LPAD(borrower_id, 5, '0') 
                        WHERE borrower_type = 'Student' AND borrower_id REGEXP '^[0-9]+$'");
        }
    },

    'down' => function (PDO $pdo) {
        // Rollback not implemented
    },
];
