<?php
header('Content-Type: application/json; charset=utf-8');
session_start();
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/constants.php';
requireLogin();

$db        = getDB();
$classroom = trim($_GET['classroom'] ?? '');
$date      = $_GET['date'] ?? date('Y-m-d');

if ($classroom === '') {
    echo json_encode(['ok' => false, 'msg' => 'classroom required']); exit;
}

// แปลง assembly status (ย่อ) → full word
$assemblyStatusMap = ['ม' => 'มา', 'ข' => 'ขาด', 'ล' => 'ลา', 'ด' => 'โดด', 'ส' => 'สาย'];

// 1. หานักเรียนในห้อง (ลอง att_students ก่อน ถ้าไม่เจอลอง assembly_students)
$students = [];
$source   = '';
try {
    $stmt = $db->prepare("SELECT student_id, name FROM att_students WHERE classroom = ? ORDER BY student_id");
    $stmt->execute([$classroom]);
    $students = $stmt->fetchAll();
    if (empty($students)) {
        $stmt = $db->prepare("SELECT student_id, name FROM att_students WHERE TRIM(classroom) = ? ORDER BY student_id");
        $stmt->execute([trim($classroom)]);
        $students = $stmt->fetchAll();
    }
    if (empty($students)) {
        $stmt = $db->prepare("SELECT student_id, name FROM att_students WHERE classroom LIKE ? ORDER BY student_id");
        $stmt->execute(['%' . trim($classroom) . '%']);
        $students = $stmt->fetchAll();
    }
    if (!empty($students)) $source = 'att_students';
} catch (Exception $e) {}

// fallback: assembly_students
if (empty($students)) {
    try {
        $stmt = $db->prepare("SELECT student_id, name FROM assembly_students WHERE classroom = ? ORDER BY student_id");
        $stmt->execute([$classroom]);
        $students = $stmt->fetchAll();
        if (empty($students)) {
            $stmt = $db->prepare("SELECT student_id, name FROM assembly_students WHERE classroom LIKE ? ORDER BY student_id");
            $stmt->execute(['%' . trim($classroom) . '%']);
            $students = $stmt->fetchAll();
        }
        if (!empty($students)) $source = 'assembly_students';
    } catch (Exception $e) {}
}

if (empty($students)) {
    echo json_encode(['ok' => false, 'msg' => 'ไม่พบนักเรียนในห้อง ' . $classroom]); exit;
}

$sidList = array_column($students, 'student_id');
$ph      = implode(',', array_fill(0, count($sidList), '?'));
$attMap  = [];

// 2a. ดึงจาก assembly_attendance (เช็คชื่อเข้าแถว) — ลำดับความสำคัญสูงสุด
try {
    $stmt = $db->prepare("
        SELECT student_id, status
        FROM assembly_attendance
        WHERE date = ? AND classroom = ?
    ");
    $stmt->execute([$date, $classroom]);
    foreach ($stmt->fetchAll() as $r) {
        $fullStatus = $assemblyStatusMap[$r['status']] ?? $r['status'];
        $attMap[$r['student_id']] = ['status' => $fullStatus, 'source' => 'เข้าแถว'];
    }
    // ถ้าไม่เจอด้วย classroom ตรง ลอง LIKE
    if (empty($attMap)) {
        $stmt = $db->prepare("
            SELECT student_id, status
            FROM assembly_attendance
            WHERE date = ? AND student_id IN ($ph)
        ");
        $stmt->execute(array_merge([$date], $sidList));
        foreach ($stmt->fetchAll() as $r) {
            $fullStatus = $assemblyStatusMap[$r['status']] ?? $r['status'];
            if (!isset($attMap[$r['student_id']])) {
                $attMap[$r['student_id']] = ['status' => $fullStatus, 'source' => 'เข้าแถว'];
            }
        }
    }
} catch (Exception $e) { /* assembly table อาจไม่มี */ }

// 2b. fallback: att_attendance (เช็คชื่อรายคาบ) สำหรับคนที่ไม่มีใน assembly
try {
    $stmt = $db->prepare("
        SELECT student_id, status, period
        FROM att_attendance
        WHERE date = ? AND student_id IN ($ph)
        ORDER BY period ASC
    ");
    $stmt->execute(array_merge([$date], $sidList));
    foreach ($stmt->fetchAll() as $r) {
        if (!isset($attMap[$r['student_id']])) {
            $attMap[$r['student_id']] = ['status' => $r['status'], 'source' => 'คาบ ' . $r['period']];
        }
    }
} catch (Exception $e) {}

// 3. สร้าง result
$result  = [];
$counts  = ['มา' => 0, 'ขาด' => 0, 'สาย' => 0, 'ลา' => 0, 'โดด' => 0];
$hasSome = false;

foreach ($students as $s) {
    $att = $attMap[$s['student_id']] ?? null;
    $st  = $att ? $att['status'] : null;
    if ($st && isset($counts[$st])) { $counts[$st]++; $hasSome = true; }
    $result[] = [
        'student_id' => $s['student_id'],
        'name'       => $s['name'],
        'status'     => $st,
        'source'     => $att ? $att['source'] : null,
    ];
}

echo json_encode([
    'ok'        => true,
    'has_data'  => $hasSome,
    'total'     => count($students),
    'counts'    => $counts,
    'students'  => $result,
    'date'      => $date,
    'classroom' => $classroom,
    'data_source' => $source,
]);
