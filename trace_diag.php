<?php
require_once __DIR__ . '/config/database.php';
header('Content-Type: text/plain; charset=utf-8');

try {
    $pdo = getPdo();
    $name = 'ธนพฐ์';
    
    echo "--- Master Identity (att_students) ---\n";
    $s1 = $pdo->prepare("SELECT * FROM att_students WHERE name LIKE ?");
    $s1->execute(["%$name%"]);
    $st = $s1->fetch();
    print_r($st);
    
    if ($st) {
        $sid = $st['student_id'];
        $iid = $st['id'];
        
        echo "\n--- Assembly Records ---\n";
        $s2 = $pdo->prepare("SELECT student_id, date, status FROM assembly_attendance WHERE student_id LIKE ? OR student_id = ?");
        $s2->execute(["%$sid%", $sid]);
        print_r($s2->fetchAll());

        echo "\n--- Attendance Records ---\n";
        $s3 = $pdo->prepare("SELECT student_id, date, period, status FROM att_attendance WHERE student_id = ?");
        $s3->execute([$iid]);
        print_r($s3->fetchAll());
        
        echo "\n--- Identity Resolution Test ---\n";
        $padded = str_pad($sid, 5, '0', STR_PAD_LEFT);
        $unpadded = ltrim($padded, '0');
        echo "Padded: [$padded], Unpadded: [$unpadded]\n";
    }

} catch (Exception $e) { echo $e->getMessage(); }
