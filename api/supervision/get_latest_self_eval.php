<?php
// api/supervision/get_latest_self_eval.php
header('Content-Type: application/json; charset=utf-8');
session_start();
require_once '../../config/database.php';

if (!isset($_SESSION['llw_role'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'กรุณาเข้าสู่ระบบ']);
    exit;
}

$teacher_id = $_GET['teacher_id'] ?? null;
if (!$teacher_id) {
    echo json_encode(['status' => 'error', 'message' => 'ไม่ระบุรหัสครู']);
    exit;
}

try {
    $pdo = getPdo();
    
    // 1. Get Latest Self-Evaluation Metadata
    $stmt = $pdo->prepare("
        SELECT id, course_name, course_code, class_level, observation_date, total_score, average_score
        FROM sup_records
        WHERE teacher_id = ? 
          AND observer_position = 'ประเมินตนเอง'
        ORDER BY created_at DESC
        LIMIT 1
    ");
    $stmt->execute([$teacher_id]);
    $record = $stmt->fetch();
    
    if (!$record) {
        echo json_encode(['status' => 'success', 'data' => null]);
        exit;
    }

    // 2. Get Scores
    $stmtS = $pdo->prepare("SELECT item_idx, score FROM sup_scores WHERE record_id = ? ORDER BY item_idx ASC");
    $stmtS->execute([$record['id']]);
    $scores = $stmtS->fetchAll();

    echo json_encode([
        'status' => 'success',
        'data' => [
            'metadata' => $record,
            'scores' => $scores
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาดในการดึงข้อมูล']);
    error_log($e->getMessage());
}
