<?php
header('Content-Type: application/json; charset=utf-8');
session_start();
require_once __DIR__ . '/../../config/database.php';

// Auth: att_teacher, super_admin, wfh_admin only
if (!isset($_SESSION['llw_role'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'กรุณาเข้าสู่ระบบ']);
    exit;
}

$allowed_roles = ['att_teacher', 'super_admin', 'wfh_admin'];
if (!in_array($_SESSION['llw_role'], $allowed_roles, true)) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'คุณไม่มีสิทธิ์ดำเนินการนี้']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'ข้อมูลไม่ถูกต้อง']);
    exit;
}

$id     = (int)($input['id']     ?? 0);
$action = $input['action'] ?? '';
$note   = trim($input['note']   ?? '');

if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'ไม่พบหมายเลขคำขอ']);
    exit;
}

$allowed_actions = ['approved', 'rejected'];
if (!in_array($action, $allowed_actions, true)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'การดำเนินการไม่ถูกต้อง']);
    exit;
}

$teacher_id = isset($_SESSION['teacher_id']) ? (int)$_SESSION['teacher_id'] : null;

try {
    $pdo = getPdo();
    $pdo->beginTransaction();

    // Fetch the request first
    $stmt = $pdo->prepare("SELECT * FROM stl_requests WHERE id = ? FOR UPDATE");
    $stmt->execute([$id]);
    $req = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$req) {
        $pdo->rollBack();
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'ไม่พบคำขอลา']);
        exit;
    }

    if ($req['status'] !== 'pending') {
        $pdo->rollBack();
        http_response_code(409);
        echo json_encode(['status' => 'error', 'message' => 'คำขอนี้ถูกดำเนินการแล้ว']);
        exit;
    }

    // Update request status
    $stmt = $pdo->prepare("
        UPDATE stl_requests
        SET status = ?, teacher_id = ?, teacher_note = ?, approved_at = NOW(), updated_at = NOW()
        WHERE id = ?
    ");
    $stmt->execute([$action, $teacher_id, $note ?: null, $id]);

    // If approved: update att_attendance records 'ขาด' → 'ลา' for this student/date range
    if ($action === 'approved') {
        $stmt = $pdo->prepare("
            UPDATE att_attendance
            SET status = 'ลา'
            WHERE student_id = ?
              AND date BETWEEN ? AND ?
              AND status = 'ขาด'
        ");
        $stmt->execute([$req['student_id'], $req['date_from'], $req['date_to']]);
    }

    $pdo->commit();

    $actionLabel = $action === 'approved' ? 'อนุมัติ' : 'ปฏิเสธ';
    echo json_encode(['status' => 'success', 'message' => $actionLabel . 'คำขอเรียบร้อยแล้ว']);

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาด กรุณาลองใหม่']);
    error_log('[stl approve] ' . $e->getMessage());
}
