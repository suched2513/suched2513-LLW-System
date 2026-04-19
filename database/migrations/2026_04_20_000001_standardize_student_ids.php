<?php
/**
 * Migration: Standardize all student IDs & Sync Missing Data
 * Created: 2026-04-20
 */

return new class {
    public function up(PDO $pdo)
    {
        // 1. Sync Teachers to Chromebook System
        if ($pdo->query("SHOW TABLES LIKE 'cb_teachers'")->fetch()) {
            $pdo->exec("INSERT INTO cb_teachers (teacher_id, name) 
                        SELECT id, name FROM att_teachers
                        ON DUPLICATE KEY UPDATE name = VALUES(name)");
        }

        // 2. Fix Chromebook Students Sync (Add missing student_id, skip empty)
        if ($pdo->query("SHOW TABLES LIKE 'cb_students'")->fetch()) {
            $pdo->exec("TRUNCATE TABLE cb_students");
            $pdo->exec("INSERT INTO cb_students (student_id, name, class_name) 
                        SELECT student_id, name, classroom FROM att_students 
                        WHERE student_id IS NOT NULL AND student_id != ''");
        }

        // 3. Standardize Master Tables (LPAD to 5 digits)
        $masters = [
            'att_students'      => 'student_id',
            'assembly_students' => 'student_id',
            'beh_students'      => 'student_id',
            'cb_students'       => 'student_id'
        ];

        foreach ($masters as $table => $column) {
            $checkTable = $pdo->query("SHOW TABLES LIKE '$table'")->fetch();
            if ($checkTable) {
                $pdo->exec("UPDATE $table SET $column = LPAD($column, 5, '0') WHERE $column REGEXP '^[0-9]+$'");
            }
        }

        // 4. Update Log Tables (Foreign Keys/References)
        $logs = [
            'att_attendance'      => 'student_id',
            'beh_records'         => 'student_id',
            'assembly_attendance' => 'student_id',
            'assembly_checkout'   => 'student_id'
        ];

        foreach ($logs as $table => $column) {
            $checkTable = $pdo->query("SHOW TABLES LIKE '$table'")->fetch();
            if ($checkTable) {
                $pdo->exec("UPDATE $table SET $column = LPAD($column, 5, '0') WHERE $column REGEXP '^[0-9]+$'");
            }
        }

        // Chromebook Borrow Logs (Special check for type='Student')
        if ($pdo->query("SHOW TABLES LIKE 'cb_borrow_logs'")->fetch()) {
            $pdo->exec("UPDATE cb_borrow_logs SET borrower_id = LPAD(borrower_id, 5, '0') 
                        WHERE borrower_type = 'Student' AND borrower_id REGEXP '^[0-9]+$'");
        }
        
        return "Standardized Student IDs & Synced Teacher/Chromebook data successfully.";
    }

    public function down(PDO $pdo)
    {
        return "Rollback not implemented.";
    }
};
