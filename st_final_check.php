<?php
require_once __DIR__ . '/config/database.php';
header('Content-Type: text/plain; charset=utf-8');

try {
    $pdo = getPdo();
    
    echo "Check 04715 in att_students (Master):\n";
    $s1 = $pdo->prepare('SELECT * FROM att_students WHERE student_id = ?');
    $s1->execute(['04715']);
    print_r($s1->fetchAll());

    echo "\nCheck 04715 in beh_students (Module Meta):\n";
    $s2 = $pdo->prepare('SELECT * FROM beh_students WHERE student_id = ?');
    $s2->execute(['04715']);
    print_r($s2->fetchAll());

} catch (Exception $e) { echo $e->getMessage(); }
