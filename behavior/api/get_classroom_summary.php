<?php
/**
 * API: Get Classroom Behavior Summary
 * คืนค่าสรุปคะแนนสะสมของนักเรียนทุกคนในห้องที่เลือก
 */
header('Content-Type: application/json; charset=utf-8');
session_start();
require_once __DIR__ . '/../../config/database.php';

if (!isset($_SESSION['llw_role'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'กรุณาเข้าสู่ระบบ']);
    exit;
}

$room = $_GET['room'] ?? ''; // e.g. "ม.6/1"

try {
    $pdo = getPdo();
    
    // 1. ดึงรายชื่อนักเรียนในห้องนั้นจาก beh_students
    $query = "SELECT student_id, name, level, room FROM beh_students";
    $params = [];
    if (!empty($room)) {
        $parts = explode('/', $room);
        $lvl = $parts[0] ?? '';
        $rm = $parts[1] ?? '';
        $query .= " WHERE level = ? AND room = ?";
        $params = [$lvl, $rm];
    }
    $query .= " ORDER BY student_id ASC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($students)) {
        echo json_encode(['status' => 'success', 'data' => []]);
        exit;
    }

    // 2. ดึงสรุปคะแนนจาก beh_records
    $scoreStmt = $pdo->prepare("
        SELECT student_id, type, SUM(score) as total 
        FROM beh_records 
        GROUP BY student_id, type
    ");
    $scoreStmt->execute();
    $rawScores = $scoreStmt->fetchAll(PDO::FETCH_ASSOC);

    // Map scores to student_id
    $scoreMap = [];
    foreach ($rawScores as $rs) {
        $sid = $rs['student_id'];
        if (!isset($scoreMap[$sid])) $scoreMap[$sid] = ['good' => 0, 'bad' => 0];
        if ($rs['type'] === 'ความดี') $scoreMap[$sid]['good'] = (int)$rs['total'];
        if ($rs['type'] === 'ความผิด') $scoreMap[$sid]['bad'] = (int)$rs['total'];
    }

    // 3. รวมข้อมูล
    $result = [];
    $baseScore = 100; // ค่าเริ่มต้นคะแนนความประพฤติ
    foreach ($students as $s) {
        $sid = $s['student_id'];
        $sGood = $scoreMap[$sid]['good'] ?? 0;
        $sBad = $scoreMap[$sid]['bad'] ?? 0;
        $result[] = [
            'studentId' => $sid,
            'name' => $s['name'],
            'classText' => $s['level'] . '/' . $s['room'],
            'good' => $sGood,
            'bad' => $sBad,
            'net' => max(0, $baseScore - $sBad)
        ];
    }

    echo json_encode(['status' => 'success', 'data' => $result]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาดในการดึงข้อมูล']);
    error_log($e->getMessage());
}
