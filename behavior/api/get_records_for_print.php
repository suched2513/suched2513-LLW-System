<?php
/**
 * API: Get records for discipline print form
 * GET ?dateFrom=YYYY-MM-DD&dateTo=YYYY-MM-DD&classText=ม.2/1
 */
header('Content-Type: application/json; charset=utf-8');
session_start();
require_once __DIR__ . '/../../config/database.php';

if (!isset($_SESSION['llw_role'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'กรุณาเข้าสู่ระบบ']);
    exit;
}

$dateFrom  = $_GET['dateFrom'] ?? '';
$dateTo    = $_GET['dateTo'] ?? '';
$classText = trim($_GET['classText'] ?? '');

try {
    $pdo = getPdo();

    $sql = "
        SELECT r.*, s.level, s.room, s.homeroom
        FROM beh_records r
        LEFT JOIN beh_students s ON r.student_id = s.student_id
        WHERE 1=1
    ";
    $params = [];

    if ($dateFrom !== '') {
        $sql .= " AND r.record_date >= ?";
        $params[] = $dateFrom;
    }
    if ($dateTo !== '') {
        $sql .= " AND r.record_date <= ?";
        $params[] = $dateTo;
    }
    if ($classText !== '') {
        // Parse classText like "ม.2/1" → level = "ม.2", room = "1"
        $parts = explode('/', $classText, 2);
        if (count($parts) === 2) {
            $sql .= " AND s.level = ? AND s.room = ?";
            $params[] = $parts[0];
            $params[] = $parts[1];
        } else {
            $sql .= " AND (s.level = ? OR s.room = ?)";
            $params[] = $classText;
            $params[] = $classText;
        }
    }

    $sql .= " ORDER BY r.record_date ASC, r.student_id ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $records = $stmt->fetchAll();

    $data = [];
    foreach ($records as $r) {
        $data[] = [
            'date'        => $r['record_date'],
            'studentName' => $r['student_name'] ?? '',
            'studentId'   => $r['student_id'],
            'level'       => $r['level'] ?? '',
            'room'        => $r['room'] ?? '',
            'classInfo'   => trim(($r['level'] ?? '') . '/' . ($r['room'] ?? ''), '/'),
            'type'        => $r['type'],
            'activity'    => $r['activity'],
            'score'       => (int)$r['score'],
            'teacher'     => $r['teacher_name'] ?? '',
            'homeroom'    => $r['homeroom'] ?? '',
        ];
    }

    echo json_encode($data);

} catch (Exception $e) {
    error_log('[behavior] get_records_for_print error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาด']);
}
