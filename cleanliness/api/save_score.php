<?php
header('Content-Type: application/json; charset=utf-8');
session_start();
require_once __DIR__ . '/../../config/database.php';

// Auth guard
if (!isset($_SESSION['llw_role'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'กรุณาเข้าสู่ระบบ']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    echo json_encode(['status' => 'error', 'message' => 'ข้อมูลไม่ถูกต้อง']);
    exit;
}

try {
    $pdo = getPdo();
    $pdo->beginTransaction();

    $area_id = (int)$input['area_id'];
    $score_date = $input['score_date'];
    $cleanliness_score = (int)$input['cleanliness_score'];
    $orderliness_score = (int)$input['orderliness_score'];
    $class_name = $input['class_name'];
    $notes = $input['notes'] ?? '';
    $user_id = $_SESSION['user_id'];

    // Calculate total score (100 base)
    // (1-5) + (1-5) => max 10. (Sum * 10) = 100
    $total_score = ($cleanliness_score + $orderliness_score) * 10;

    $stmt = $pdo->prepare("INSERT INTO clean_scores 
        (score_date, cleanliness_score, orderliness_score, area_id, class_name, score, notes, recorded_by_user_id) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    
    $stmt->execute([
        $score_date,
        $cleanliness_score,
        $orderliness_score,
        $area_id,
        $class_name,
        $total_score,
        $notes,
        $user_id
    ]);

    $pdo->commit();
    echo json_encode(['status' => 'success', 'message' => 'บันทึกคะแนนเรียบร้อยแล้ว']);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()]);
    error_log($e->getMessage());
}
