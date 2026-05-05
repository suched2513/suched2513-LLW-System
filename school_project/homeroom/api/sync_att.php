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

// 1. หานักเรียนในห้อง — ลอง exact match ก่อน แล้ว fallback LIKE
$students = [];
try {
    $stmt = $db->prepare("SELECT student_id, name FROM att_students WHERE classroom = ? ORDER BY student_id");
    $stmt->execute([$classroom]);
    $students = $stmt->fetchAll();

    // fallback: trim whitespace และลอง LIKE ถ้าไม่เจอ
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
} catch (Exception $e) {
    echo json_encode(['ok' => false, 'msg' => 'att_students error: ' . $e->getMessage()]); exit;
}

if (empty($students)) {
    echo json_encode(['ok' => false, 'msg' => 'ไม่พบนักเรียนในห้อง ' . $classroom, 'count' => 0]); exit;
}

// 2. ดึงสถานะจาก att_attendance ทุก period ของวันนั้น เอา period แรกสุดต่อคน
$sidList = array_column($students, 'student_id');
$ph      = implode(',', array_fill(0, count($sidList), '?'));
$attMap  = [];

try {
    $stmt = $db->prepare("
        SELECT student_id, status, period, time_in
        FROM att_attendance
        WHERE date = ? AND student_id IN ($ph)
        ORDER BY period ASC
    ");
    $stmt->execute(array_merge([$date], $sidList));
    foreach ($stmt->fetchAll() as $r) {
        if (!isset($attMap[$r['student_id']])) {
            $attMap[$r['student_id']] = [
                'status' => $r['status'],
                'period' => (int)$r['period'],
                'time'   => $r['time_in'] ? substr($r['time_in'], 0, 5) : '',
            ];
        }
    }
} catch (Exception $e) {
    echo json_encode(['ok' => false, 'msg' => 'att_attendance error: ' . $e->getMessage()]); exit;
}

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
        'status'     => $st,   // null = ไม่มีข้อมูล
        'period'     => $att ? $att['period'] : null,
        'time'       => $att ? $att['time'] : '',
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
]);
