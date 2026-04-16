<?php
/**
 * assembly/api/delete_student.php
 * POST JSON — ลบนักเรียนออกจากระบบ
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
$studentId = trim($input['student_id'] ?? '');

if ($studentId === '') {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'กรุณาระบุรหัสนักเรียน']);
    exit;
}

try {
    $pdo  = getPdo();
    $stmt = $pdo->prepare("DELETE FROM assembly_students WHERE student_id = ?");
    $stmt->execute([$studentId]);
    echo json_encode(['status' => 'success', 'deleted' => $stmt->rowCount()]);
} catch (Exception $e) {
    error_log('[Assembly] delete_student: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาด']);
}
