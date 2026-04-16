<?php
// api/supervision/update_profile.php
header('Content-Type: application/json; charset=utf-8');
session_start();
require_once '../../config/database.php';

if (!isset($_SESSION['llw_role'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'กรุณาเข้าสู่ระบบ']);
    exit;
}

// Role guard: Only super_admin can update profiles here
if ($_SESSION['llw_role'] !== 'super_admin') {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'คุณไม่มีสิทธิ์จัดการข้อมูลครู']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || empty($input['id'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'ข้อมูลไม่ครบถ้วน']);
    exit;
}

try {
    $pdo = getPdo();

    // ── Toggle is_evaluator only (from toggle switch) ────────────────
    if (array_key_exists('is_evaluator', $input) && count($input) === 2) {
        $isEval = (int)$input['is_evaluator'];
        if (!in_array($isEval, [0, 1])) throw new Exception('ค่าไม่ถูกต้อง');

        $stmt = $pdo->prepare("UPDATE llw_users SET is_evaluator = ? WHERE user_id = ?");
        $stmt->execute([$isEval, $input['id']]);

        echo json_encode(['status' => 'success', 'message' => $isEval ? 'กำหนดเป็นผู้นิเทศสำเร็จ' : 'ยกเลิกสิทธิ์สำเร็จ']);
        exit;
    }

    // ── Full profile update (position, academic_status, subject_group) ───
    $stmt = $pdo->prepare("
        UPDATE llw_users 
        SET position = ?, academic_status = ?, subject_group = ? 
        WHERE user_id = ?
    ");
    $stmt->execute([
        $input['position'] ?? '',
        $input['academic_status'] ?? '',
        $input['subject_group'] ?? '',
        $input['id']
    ]);

    echo json_encode(['status' => 'success', 'message' => 'อัปเดตข้อมูลสำเร็จ']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาดในการอัปเดตข้อมูล']);
    error_log($e->getMessage());
}
