<?php
/**
 * API: Get all behavior records for database view
 * GET
 */
header('Content-Type: application/json; charset=utf-8');
session_start();
require_once __DIR__ . '/../../config/database.php';

if (!isset($_SESSION['llw_role'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'กรุณาเข้าสู่ระบบ']);
    exit;
}

try {
    $pdo = getPdo();

    $stmt = $pdo->query("
        SELECT r.*, s.level, s.room, s.homeroom
        FROM beh_records r
        LEFT JOIN beh_students s ON r.student_id = s.student_id
        ORDER BY r.record_date DESC, r.created_at DESC
        LIMIT 2000
    ");
    $records = $stmt->fetchAll();

    $data = [];
    foreach ($records as $r) {
        $classInfo = trim(($r['level'] ?? '') . '/' . ($r['room'] ?? ''), '/');
        $data[] = [
            'id'          => $r['id'],
            'date'        => $r['record_date'],
            'studentId'   => $r['student_id'],
            'studentName' => $r['student_name'] ?? '',
            'classInfo'   => $classInfo ?: '-',
            'type'        => $r['type'],
            'activity'    => $r['activity'],
            'score'       => (int)$r['score'],
            'teacher'     => $r['teacher_name'] ?? '',
            'homeroom'    => $r['homeroom'] ?? '',
        ];
    }

    echo json_encode($data);

} catch (Exception $e) {
    error_log('[behavior] get_all_records error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาด']);
}
