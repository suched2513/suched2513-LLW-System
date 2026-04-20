<?php
require_once __DIR__ . '/config/database.php';
header('Content-Type: text/plain; charset=utf-8');

try {
    $pdo = getPdo();
    
    echo "--- LAST 10 BEHAVIOR RECORDS ---\n";
    $s1 = $pdo->query("SELECT student_id, student_name, record_date, activity FROM beh_records ORDER BY id DESC LIMIT 10");
    print_r($s1->fetchAll());

    echo "\n--- LAST 10 SUBJECT ATTENDANCE ---\n";
    $s2 = $pdo->query("SELECT student_id, date, period, status FROM att_attendance ORDER BY id DESC LIMIT 10");
    print_r($s2->fetchAll());

    echo "\n--- LAST 10 ASSEMBLY ATTENDANCE ---\n";
    $s3 = $pdo->query("SELECT student_id, date, status FROM assembly_attendance ORDER BY id DESC LIMIT 10");
    print_r($s3->fetchAll());

    echo "\n--- SYSTEM CHECK ---\n";
    echo "PHP Version: " . PHP_VERSION . "\n";
    echo "Default Charset: " . ini_get('default_charset') . "\n";

} catch (Exception $e) { echo $e->getMessage(); }
