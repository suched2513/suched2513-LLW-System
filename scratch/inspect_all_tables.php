<?php
require_once 'config/database.php';
try {
    $pdo = getPdo();
    $tables = ['att_attendance', 'att_students', 'att_subjects', 'assembly_attendance', 'assembly_students', 'beh_students', 'beh_records'];
    foreach ($tables as $t) {
        $stmt = $pdo->query("DESCRIBE $t");
        echo "\n--- Table: $t ---\n";
        print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
