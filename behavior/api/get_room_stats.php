<?php
/**
 * API: Get Behavior Stats by Room
 * Calculates average scores and counts per classroom
 */
header('Content-Type: application/json; charset=utf-8');
session_start();
require_once __DIR__ . '/../../config/database.php';

if (!isset($_SESSION['llw_role'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

try {
    $pdo = getPdo();
    
    // SQL calculating Averages per Room using Master att_students
    $sql = "
        SELECT 
            s.classroom,
            AVG(100 + COALESCE(good.total, 0) - COALESCE(bad.total, 0)) as avg_score,
            SUM(COALESCE(good.total, 0)) as total_good_points,
            SUM(COALESCE(bad.total, 0)) as total_bad_points
        FROM att_students s
        LEFT JOIN (
            SELECT student_id, SUM(score) as total FROM beh_records WHERE type = 'ความดี' GROUP BY student_id
        ) good ON s.student_id = good.student_id
        LEFT JOIN (
            SELECT student_id, SUM(score) as total FROM beh_records WHERE type = 'ความผิด' GROUP BY student_id
        ) bad ON s.student_id = bad.student_id
        WHERE s.classroom IS NOT NULL AND s.classroom != ''
        GROUP BY s.classroom
        ORDER BY s.classroom ASC
    ";
    
    $stmt = $pdo->query($sql);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['status' => 'success', 'data' => $data]);

} catch (Exception $e) {
    error_log('[LLW] get_room_stats error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาด']);
}
