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
    $sample = $pdo->query("SELECT student_id FROM att_attendance LIMIT 1")->fetchColumn();
    if ($sample) {
        echo "Testing lookup for att_attendance.student_id=$sample...\n";
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

echo "\n--- 4. Simulate attendance.php for student uid=116 (04707) ---\n";
try {
    $uid  = 116;
    $dateFrom = '2026-05-01';
    $dateTo   = '2026-10-31';
    $stmt = $pdo->prepare("
        SELECT s.subject_name, COUNT(*) AS total, SUM(a.status IN ('มา','สาย')) AS present
        FROM att_attendance a
        JOIN att_subjects s ON s.id = a.subject_id
        WHERE a.student_id = ? AND a.date BETWEEN ? AND ?
        GROUP BY s.id, s.subject_name
    ");
    $stmt->execute([$uid, $dateFrom, $dateTo]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "Subjects found: " . count($rows) . "\n";
    foreach ($rows as $r) {
        echo "  - " . $r['subject_name'] . ": " . $r['present'] . "/" . $r['total'] . "\n";
    }
} catch (Exception $e) { echo "Error sim: " . $e->getMessage() . "\n"; }

echo "\n--- 5. Session state ---\n";
echo "student_uid  = " . ($_SESSION['student_uid']  ?? 'NOT SET') . "\n";
echo "student_code = " . ($_SESSION['student_code'] ?? 'NOT SET') . "\n";
echo "student_name = " . ($_SESSION['student_name'] ?? 'NOT SET') . "\n";
