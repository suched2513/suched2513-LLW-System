<?php
/**
 * assembly/api/assign_teacher.php
 * POST JSON — ผูกครูกับห้องเรียน
 */
header('Content-Type: application/json; charset=utf-8');
session_start();
require_once __DIR__ . '/../../config/database.php';

if (!isset($_SESSION['llw_role'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'กรุณาเข้าสู่ระบบ']);
    exit;
}
if ($_SESSION['llw_role'] !== 'super_admin') {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'เฉพาะ Super Admin เท่านั้น']);
    exit;
}

$input     = json_decode(file_get_contents('php://input'), true);
$classroom = trim($input['classroom']   ?? '');
$userId    = $input['llw_user_id'] !== '' && $input['llw_user_id'] !== null ? (int)$input['llw_user_id'] : null;

if ($classroom === '') {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'กรุณาระบุห้องเรียน']);
    exit;
}

try {
    $pdo = getPdo();
    $stmt = $pdo->prepare("
        INSERT INTO assembly_classrooms (classroom, llw_user_id)
        VALUES (:classroom, :user_id)
        ON DUPLICATE KEY UPDATE llw_user_id = VALUES(llw_user_id)
    ");
    $stmt->execute([':classroom' => $classroom, ':user_id' => $userId]);
    echo json_encode(['status' => 'success']);
} catch (Exception $e) {
    error_log('[Assembly] assign_teacher: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาด']);
}
