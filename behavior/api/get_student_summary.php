<?php
/**
 * API: Get student summary (all students with cumulative scores)
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
        SELECT 
            s.student_id, s.name, s.level, s.room, s.homeroom,
            COALESCE(SUM(CASE WHEN r.type = 'ความดี' THEN r.score ELSE 0 END), 0) AS good,
            COALESCE(SUM(CASE WHEN r.type = 'ความผิด' THEN r.score ELSE 0 END), 0) AS bad
        FROM beh_students s
        LEFT JOIN beh_records r ON s.student_id = r.student_id
        WHERE s.status = 'active'
        GROUP BY s.student_id, s.name, s.level, s.room, s.homeroom
        ORDER BY s.level, s.room, s.name
    ");

    $list = [];
    foreach ($stmt->fetchAll() as $row) {
        $list[] = [
            'studentId' => $row['student_id'],
            'name'      => $row['name'],
            'level'     => $row['level'] ?? '',
            'room'      => $row['room'] ?? '',
            'homeroom'  => $row['homeroom'] ?? '',
            'good'      => (int)$row['good'],
            'bad'       => (int)$row['bad'],
        ];
    }

    echo json_encode($list);

} catch (Exception $e) {
    error_log('[behavior] get_student_summary error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาด']);
}
