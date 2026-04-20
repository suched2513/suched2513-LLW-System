<?php
/**
 * API: Get Behavior Leaderboard
 * Calculates net scores: 100 (Initial) + Total Good - Total Bad
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
    
    // SQL calculating Net Score per student using att_students as Master
    $sql = "
        SELECT 
            s.student_id, 
            s.name, 
            s.classroom,
            COALESCE(SUM(CASE WHEN r.type = 'ความดี' THEN r.score ELSE 0 END), 0) as total_good,
            COALESCE(SUM(CASE WHEN r.type = 'ความผิด' THEN r.score ELSE 0 END), 0) as total_bad,
            (100 + COALESCE(SUM(CASE WHEN r.type = 'ความดี' THEN r.score ELSE 0 END), 0) - COALESCE(SUM(CASE WHEN r.type = 'ความผิด' THEN r.score ELSE 0 END), 0)) as net_score
        FROM att_students s
        JOIN beh_records r ON s.student_id = r.student_id
        GROUP BY s.student_id
        ORDER BY net_score DESC, total_good DESC
        LIMIT 10
    ";
    
    $stmt = $pdo->query($sql);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['status' => 'success', 'data' => $data]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
