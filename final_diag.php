<?php
require_once __DIR__ . '/config/database.php';
header('Content-Type: text/plain; charset=utf-8');
session_start();

try {
    $pdo = getPdo();
    $iid = 40; // ธนพฐ์
    $uid = $_SESSION['user_id'] ?? 0;
    
    echo "--- Attendance for ID 40 ---\n";
    $s1 = $pdo->prepare("SELECT * FROM att_attendance WHERE student_id = ? LIMIT 10");
    $s1->execute([$iid]);
    print_r($s1->fetchAll());

    echo "\n--- Advisor Mapping for User ID $uid ---\n";
    $s2 = $pdo->prepare("SELECT * FROM beh_advisor_mapping WHERE user_id = ?");
    $s2->execute([$uid]);
    print_r($s2->fetchAll());

} catch (Exception $e) { echo $e->getMessage(); }
