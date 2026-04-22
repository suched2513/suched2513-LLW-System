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
$classroom = $input['classroom'] ?? null;
$log_date = $input['log_date'] ?? null;
$topic = $input['topic'] ?? '';
$user_id = $_SESSION['user_id'];

if (!$classroom || !$log_date) {
    echo json_encode(['status' => 'error', 'message' => 'ข้อมูลไม่ครบถ้วน']);
    exit;
}

try {
    $pdo = getPdo();
    // INSERT IGNORE then UPDATE or UPSERT
    $stmt = $pdo->prepare("
        INSERT INTO homeroom_logs (classroom, log_date, topic, user_id)
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE topic = VALUES(topic), user_id = VALUES(user_id)
    ");
    $stmt->execute([$classroom, $log_date, $topic, $user_id]);

    echo json_encode(['status' => 'success', 'message' => 'บันทึกสำเร็จ']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()]);
    error_log($e->getMessage());
}
