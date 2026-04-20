<?php
/**
 * API: Get Behavior Leaderboard
 * Calculates net scores: 100 (Initial) + Total Good - Total Bad
 */
header('Content-Type: application/json; charset=utf-8');
session_start();
ob_start();
require_once __DIR__ . '/../../config/database.php';

if (!isset($_SESSION['llw_role'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

try {
    $pdo = getPdo();
    
    /**
     * SQL calculating Net Score per student using att_students as Master
     * Uses Extreme Trimming to bridge IDs and correctly decomposes classroom
     */
    $sql = "
        SELECT 
            s.student_id, 
            s.name, 
            s.classroom,
            SUBSTRING_INDEX(s.classroom, '/', 1) as level,
            SUBSTRING_INDEX(s.classroom, '/', -1) as room,
            COALESCE(SUM(CASE WHEN r.type = 'ความดี' THEN r.score ELSE 0 END), 0) as total_good,
            COALESCE(SUM(CASE WHEN r.type = 'ความผิด' THEN r.score ELSE 0 END), 0) as total_bad,
            (100 + COALESCE(SUM(CASE WHEN r.type = 'ความดี' THEN r.score ELSE 0 END), 0) - COALESCE(SUM(CASE WHEN r.type = 'ความผิด' THEN r.score ELSE 0 END), 0)) as net_score
        FROM att_students s
        JOIN beh_records r ON (
            TRIM(LEADING '0' FROM TRIM(BOTH ' ' FROM s.student_id)) = 
            TRIM(LEADING '0' FROM TRIM(BOTH ' ' FROM r.student_id))
        )
        GROUP BY s.student_id
        ORDER BY net_score DESC, total_good DESC
        LIMIT 10
    ";
    
    $stmt = $pdo->query($sql);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (ob_get_length()) ob_clean();
    echo json_encode(['status' => 'success', 'data' => $data]);

} catch (Exception $e) {
    if (ob_get_length()) ob_clean();
    error_log('[behavior] get_leaderboard error: ' . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาดในการดึงข้อมูลอันดับ']);
}
