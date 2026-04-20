<?php
/**
 * scratch/cleanup_ids.php
 * One-time script to pad existing student IDs in all tables to 5-digit format.
 * This fixes broken links in dashboards where students were recorded with unpadded IDs.
 */
require_once __DIR__ . '/../config/database.php';

try {
    $pdo = getPdo();
    $pdo->beginTransaction();

    $tables = [
        ['table' => 'att_attendance',     'col' => 'student_id'],
        ['table' => 'assembly_attendance', 'col' => 'student_id'],
        ['table' => 'assembly_checkout',   'col' => 'student_id'],
        ['table' => 'cb_borrow_logs',      'col' => 'borrower_id', 'condition' => "borrower_type = 'Student'"],
        ['table' => 'beh_records',         'col' => 'student_id'],
        ['table' => 'att_students',        'col' => 'student_id'],
        ['table' => 'assembly_students',   'col' => 'student_id'],
        ['table' => 'cb_students',         'col' => 'student_id'],
    ];

    echo "Starting Student ID Cleanup (Padding to 5 digits)...\n";

    foreach ($tables as $t) {
        $tableName = $t['table'];
        $colName   = $t['col'];
        $condition = $t['condition'] ?? '1=1';

        echo "Processing $tableName ($colName)... ";

        // Select all IDs that are purely numeric and length < 5
        $stmt = $pdo->prepare("SELECT DISTINCT $colName FROM $tableName WHERE $condition AND $colName REGEXP '^[0-9]+$' AND LENGTH($colName) < 5");
        $stmt->execute();
        $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $count = 0;
        if ($ids) {
            $update = $pdo->prepare("UPDATE $tableName SET $colName = LPAD($colName, 5, '0') WHERE $colName = ? AND $condition");
            foreach ($ids as $id) {
                // Double check it's numeric
                if (is_numeric($id)) {
                    $update->execute([$id]);
                    $count += $update->rowCount();
                }
            }
        }
        echo "Updated $count rows.\n";
    }

    $pdo->commit();
    echo "\n✅ Successfully standardized all Student IDs to 5-digit format.\n";

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    echo "\n❌ Error: " . $e->getMessage() . "\n";
}
