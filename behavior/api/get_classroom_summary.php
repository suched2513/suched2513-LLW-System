<?php
/**
 * API: Get Classroom Behavior Summary
 * คืนค่าสรุปคะแนนสะสมของนักเรียนทุกคนในห้องที่เลือก
 */
header('Content-Type: application/json; charset=utf-8');
session_start();
ob_start();
require_once __DIR__ . '/../../config/database.php';

if (!isset($_SESSION['llw_role'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'กรุณาเข้าสู่ระบบ']);
    exit;
}

$room = $_GET['room'] ?? ''; // e.g. "4/1"

try {
    $pdo = getPdo();
    
    // 1. ดึงรายชื่อนักเรียนในห้องจาก att_students (Master)
    $query = "SELECT student_id, name, classroom FROM att_students";
    $params = [];
    if (!empty($room)) {
        $query .= " WHERE classroom = ?";
        $params = [$room];
    }
    $query .= " ORDER BY student_id ASC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($students)) {
        if (ob_get_length()) ob_clean();
        echo json_encode(['status' => 'success', 'data' => []]);
        exit;
    }

    // 2. ดึงสรุปคะแนนจาก beh_records
    // ต้อง Group โดยใช้ LPAD เพื่อรวมคะแนนของ 04715 และ 4715 เข้าด้วยกัน
    $scoreStmt = $pdo->prepare("
        SELECT LPAD(student_id, 5, '0') as sid_norm, type, SUM(score) as total 
        FROM beh_records 
        GROUP BY sid_norm, type
    ");
    $scoreStmt->execute();
    $rawScores = $scoreStmt->fetchAll(PDO::FETCH_ASSOC);

    // Map scores to normalized student_id
    $scoreMap = [];
    foreach ($rawScores as $rs) {
        $sid = $rs['sid_norm'];
        if (!isset($scoreMap[$sid])) $scoreMap[$sid] = ['good' => 0, 'bad' => 0];
        if ($rs['type'] === 'ความดี') $scoreMap[$sid]['good'] = (int)$rs['total'];
        if ($rs['type'] === 'ความผิด') $scoreMap[$sid]['bad'] = (int)$rs['total'];
    }

    // 3. รวมข้อมูล
    $result = [];
    $baseScore = 100; // ค่าเริ่มต้นคะแนนความประพฤติ
    foreach ($students as $s) {
        $sidRaw = $s['student_id'];
        $sidNorm = str_pad($sidRaw, 5, '0', STR_PAD_LEFT);
        
        $sGood = $scoreMap[$sidNorm]['good'] ?? 0;
        $sBad = $scoreMap[$sidNorm]['bad'] ?? 0;
        
        $result[] = [
            'studentId' => $sidRaw,
            'name' => $s['name'],
            'classText' => $s['classroom'],
            'good' => $sGood,
            'bad' => $sBad,
            'net' => max(0, $baseScore - $sBad)
        ];
    }

    if (ob_get_length()) ob_clean();
    echo json_encode(['status' => 'success', 'data' => $result]);

} catch (Exception $e) {
    if (ob_get_length()) ob_clean();
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาดในการดึงข้อมูล']);
    error_log('[behavior] get_classroom_summary error: ' . $e->getMessage());
}
