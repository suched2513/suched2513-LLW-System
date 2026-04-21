<?php
/**
 * Migration: Standardize all student IDs & Sync Missing Data
 * Created: 2026-04-20
 * Updated: 2026-04-21 — handle duplicate IDs gracefully (safe subquery approach)
 */

return [
    'up' => function (PDO $pdo) {

        // ─── 0. Pre-clean: delete duplicate student_ids before LPAD ──────
        // Some tables may have both '4684' and '04684' which would collide after LPAD
        // Strategy: for each table, find IDs that LPAD would duplicate
        // and keep only the row with the ALREADY-padded ID (starts with '0' prefix)
        $masterTables = ['att_students', 'assembly_students', 'beh_students'];
        foreach ($masterTables as $table) {
            if (!$pdo->query("SHOW TABLES LIKE '$table'")->fetch()) continue;

            // Find student_ids that when padded would duplicate another padded id
            // Delete the shorter/unpadded duplicate (e.g., '4684' when '04684' exists)
            $pdo->exec("
                DELETE FROM `$table`
                WHERE student_id REGEXP '^[0-9]+$'
                AND LENGTH(student_id) < 5
                AND LPAD(student_id, 5, '0') IN (
                    SELECT padded FROM (
                        SELECT LPAD(student_id, 5, '0') AS padded
                        FROM `$table`
                        WHERE LENGTH(student_id) = 5 AND student_id REGEXP '^[0-9]+$'
                    ) AS already_padded
                )
            ");
        }

        // ─── 1. Sync Teachers to Chromebook System ────────────────────────
        if ($pdo->query("SHOW TABLES LIKE 'cb_teachers'")->fetch()) {
            $pdo->exec("INSERT INTO cb_teachers (teacher_id, name) 
                        SELECT id, name FROM att_teachers
                        ON DUPLICATE KEY UPDATE name = VALUES(name)");
        }

        // ─── 2. Fix Chromebook Students Sync ─────────────────────────────
        if ($pdo->query("SHOW TABLES LIKE 'cb_students'")->fetch()) {
            $pdo->exec("TRUNCATE TABLE cb_students");
            $pdo->exec("INSERT INTO cb_students (student_id, name, class_name) 
                        SELECT student_id, name, classroom FROM att_students 
                        WHERE student_id IS NOT NULL AND student_id != ''");
        }

        // ─── 3. Standardize Master Tables (LPAD to 5 digits) ─────────────
        $masters = [
            'att_students'      => 'student_id',
            'assembly_students' => 'student_id',
            'beh_students'      => 'student_id',
            'cb_students'       => 'student_id'
        ];

        foreach ($masters as $table => $column) {
            if ($pdo->query("SHOW TABLES LIKE '$table'")->fetch()) {
                $pdo->exec("UPDATE `$table` SET `$column` = LPAD(`$column`, 5, '0') WHERE `$column` REGEXP '^[0-9]+$'");
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
                $pdo->exec("UPDATE `$table` SET `$column` = LPAD(`$column`, 5, '0') WHERE `$column` REGEXP '^[0-9]+$'");
            }
        }

        // ─── 5. Chromebook Borrow Logs ────────────────────────────────────
        if ($pdo->query("SHOW TABLES LIKE 'cb_borrow_logs'")->fetch()) {
            $pdo->exec("UPDATE cb_borrow_logs SET borrower_id = LPAD(borrower_id, 5, '0') 
                        WHERE borrower_type = 'Student' AND borrower_id REGEXP '^[0-9]+$'");
        }
    },

    'down' => function (PDO $pdo) {
        // Rollback not implemented — data transformation is irreversible
    },
];
