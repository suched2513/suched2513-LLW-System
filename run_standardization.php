<?php
require_once __DIR__ . '/config/database.php';
header('Content-Type: text/plain; charset=utf-8');

try {
    $pdo = getPdo();
    echo "Running Student ID Standardization...\n";

    $tables = [
        'att_students'      => 'student_id',
        'beh_students'      => 'student_id',
        'beh_records'       => 'student_id',
        'beh_deeds'         => 'student_id',
        'att_attendance'    => 'student_id',
        'cb_borrow_logs'    => 'student_id',
        'cb_students'       => 'student_id',
        'assembly_students' => 'student_id'
    ];

    foreach ($tables as $table => $column) {
        // SQL padding logic
        $sql = "UPDATE $table SET $column = LPAD($column, 5, '0') 
                WHERE $column REGEXP '^[0-9]+$' AND LENGTH($column) > 0 AND LENGTH($column) < 5";
        $affected = $pdo->exec($sql);
        echo "Table $table: $affected rows padded.\n";
    }

    echo "Standardization complete.\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
