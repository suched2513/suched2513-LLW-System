<?php
require_once __DIR__ . '/config/database.php';
header('Content-Type: text/plain; charset=utf-8');

try {
    $pdo = getPdo();
    
    echo "--- Database Diagnosis ---\n";
    
    // Check att_students
    $attCount = $pdo->query("SELECT COUNT(*) FROM att_students")->fetchColumn();
    echo "Total students in att_students: $attCount\n";
    
    $attSample = $pdo->query("SELECT student_id, name, classroom FROM att_students LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
    echo "Sample att_students:\n";
    print_r($attSample);
    
    // Check beh_students
    $behCount = $pdo->query("SELECT COUNT(*) FROM beh_students")->fetchColumn();
    echo "Total students in beh_students: $behCount\n";
    
    $behSample = $pdo->query("SELECT student_id, name FROM beh_students LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
    echo "Sample beh_students:\n";
    print_r($behSample);
    
    // Check if they are in the same DB (they should be)
    $dbName = $pdo->query("SELECT DATABASE()")->fetchColumn();
    echo "Current Database: $dbName\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
