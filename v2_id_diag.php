<?php
require_once __DIR__ . '/config/database.php';
header('Content-Type: text/plain; charset=utf-8');

try {
    $pdo = getPdo();
    $id_to_check = '04715';
    $unpadded = '4715';
    
    echo "Searching for '$id_to_check' in att_students:\n";
    $s1 = $pdo->prepare("SELECT id, student_id, name FROM att_students WHERE student_id = ?");
    $s1->execute([$id_to_check]);
    print_r($s1->fetchAll());

    echo "\nSearching for '$unpadded' in att_students:\n";
    $s2 = $pdo->prepare("SELECT id, student_id, name FROM att_students WHERE student_id = ?");
    $s2->execute([$unpadded]);
    print_r($s2->fetchAll());

} catch (Exception $e) { echo $e->getMessage(); }
