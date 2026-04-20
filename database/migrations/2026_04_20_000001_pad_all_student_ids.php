<?php
/**
 * Migration: pad_all_student_ids
 * Created: 2026-04-20
 *
 * Ensures all student_id fields in the database follow the 5-digit padding convention.
 */
return [
    'up' => function (PDO $pdo) {
        $tables = [
            'att_students'     => 'student_id',
            'beh_students'     => 'student_id',
            'beh_records'      => 'student_id',
            'beh_deeds'        => 'student_id',
            'att_attendance'   => 'student_id',
            'cb_borrow_logs'   => 'student_id',
            'cb_students'      => 'student_id',
            'assembly_students' => 'student_id'
        ];

        foreach ($tables as $table => $column) {
            echo "Standardizing $table.$column...\n";
            try {
                // Pad if it's purely numeric and less than 5 characters
                $sql = "UPDATE $table SET $column = LPAD($column, 5, '0') 
                        WHERE $column REGEXP '^[0-9]+$' AND LENGTH($column) > 0 AND LENGTH($column) < 5";
                $affected = $pdo->exec($sql);
                echo "  Affected rows in $table: $affected\n";
            } catch (Exception $e) {
                echo "  Error updating $table: " . $e->getMessage() . "\n";
            }
        }
        return true;
    },

    'down' => function (PDO $pdo) {
        // No reverse for padding
        return true;
    },
];
