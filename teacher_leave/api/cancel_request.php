<?php
/**
 * teacher_leave/api/cancel_request.php
 * API for users to cancel their own pending leave requests
 */
header('Content-Type: application/json; charset=utf-8');
session_start();
require_once '../../config/database.php';

if (!isset($_SESSION['llw_role'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'กรุณาเข้าสู่ระบบ']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$requestId = $input['id'] ?? null;
$userId = $_SESSION['user_id'];

if (!$requestId) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'ไม่พบรหัสคำขอ']);
    exit;
}

try {
    $pdo = getPdo();
    $pdo->beginTransaction();

    // 1. ตรวจสอบความเป็นเจ้าของและสถานะปัจจุบัน
    $stmt = $pdo->prepare("SELECT id, status FROM tl_requests WHERE id = ? AND user_id = ?");
    $stmt->execute([$requestId, $userId]);
    $request = $stmt->fetch();

    if (!$request) {
        throw new Exception('ไม่พบข้อมูลคำขอ หรือคุณไม่มีสิทธิ์ยกเลิกรายการนี้');
    }

    if ($request['status'] !== 'pending') {
        throw new Exception('ไม่สามารถยกเลิกได้ เนื่องจากรายการนี้ได้รับการพิจารณาไปแล้ว');
    }

    // 2. อัปเดตสถานะเป็น rejected (ใช้คอมเมนต์ระบุว่ายกเลิกโดยผู้ใช้)
    $stmtUpdate = $pdo->prepare("UPDATE tl_requests SET status = 'rejected' WHERE id = ?");
    $stmtUpdate->execute([$requestId]);

    // 3. บันทึกใน Log การอนุมัติ (ถ้ามีระดับปัจจุบัน)
    $stmtLog = $pdo->prepare("
        UPDATE tl_approvals 
        SET status = 2, comment = 'ยกเลิกโดยผู้ใช้', approved_at = NOW() 
        WHERE request_id = ? AND status = 0
    ");
    $stmtLog->execute([$requestId]);

    $pdo->commit();

    echo json_encode([
        'status' => 'success',
        'message' => 'ยกเลิกใบลาเรียบร้อยแล้ว'
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('[LLW] cancel_request error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาด']);
}
