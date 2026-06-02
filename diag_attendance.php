<?php
session_start();
if (($_GET['k'] ?? '') !== 'llwdiag2026') { http_response_code(403); exit('403'); }
require_once 'config/database.php';
$pdo = getPdo();
header('Content-Type: text/plain; charset=UTF-8');

echo "--- 1. Check att_attendance structure vs data ---\n";
try {
    $stmt = $pdo->query("SELECT student_id, COUNT(*) as cnt FROM att_attendance GROUP BY student_id LIMIT 10");
    $attendance_pks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "Sample student_ids in att_attendance:\n";
    print_r($attendance_pks);
} catch (Exception $e) { echo "Error att_attendance: " . $e->getMessage() . "\n"; }

echo "\n--- 2. Check att_students mapping ---\n";
try {
    $stmt = $pdo->query("SELECT id, student_id, name FROM att_students LIMIT 10");
    echo "Sample rows in att_students:\n";
    print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
} catch (Exception $e) { echo "Error att_students: " . $e->getMessage() . "\n"; }

echo "\n--- 3. Direct Test query (Simulation of student/attendance.php) ---\n";
try {
    // Pick the first student from attendance to see if we can find them via our typical query
    $sample = $pdo->query("SELECT student_id FROM att_attendance LIMIT 1")->fetchColumn();
    if ($sample) {
        echo "Testing lookup for student_id=$sample...\n";
        // Check if $sample is an ID or CODE
        $st = $pdo->prepare("SELECT name FROM att_students WHERE id = ?");
        $st->execute([$sample]);
        $resId = $st->fetchColumn();
        
        $st2 = $pdo->prepare("SELECT name FROM att_students WHERE student_id = ?");
        $st2->execute([$sample]);
        $resCode = $st2->fetchColumn();
        
        echo "Lookup by ID result: " . ($resId ?: 'NOT FOUND') . "\n";
        echo "Lookup by CODE result: " . ($resCode ?: 'NOT FOUND') . "\n";
    }
} catch (Exception $e) { echo "Error test: " . $e->getMessage() . "\n"; }
