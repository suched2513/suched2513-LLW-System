<?php
/**
 * API: Approve or Reject a student good deed
 * POST JSON: { recordId, action: 'approve'|'reject', note }
 */
header('Content-Type: application/json; charset=utf-8');
session_start();
require_once __DIR__ . '/../../config/database.php';

if (!isset($_SESSION['llw_role'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'กรุณาเข้าสู่ระบบ']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$recordId = (int)($input['recordId'] ?? 0);
$action   = $input['action'] ?? ''; // 'approve' or 'reject'
$note     = trim($input['note'] ?? '');

if ($recordId === 0 || !in_array($action, ['approve', 'reject'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'ข้อมูลไม่ถูกต้อง']);
    exit;
}

try {
    $pdo = getPdo();
    $teacherName = $_SESSION['fullname'];
    $teacherId   = $_SESSION['user_id'];
    $isSuper     = ($_SESSION['llw_role'] === 'super_admin');

    // Verify record exists and is pending
    $stmt = $pdo->prepare("SELECT * FROM beh_records WHERE id = ? AND status = 'pending'");
    $stmt->execute([$recordId]);
    $record = $stmt->fetch();

    if (!$record) {
        echo json_encode(['status' => 'error', 'message' => 'ไม่พบรายการที่รอการตรวจสอบ']);
        exit;
    }

    // Permission check: Must be advisor or super admin
    if (!$isSuper) {
        $stmtCheck = $pdo->prepare("
            SELECT a.id 
            FROM beh_advisors a
            JOIN beh_students s ON a.level = s.level AND a.room = s.room
            WHERE a.user_id = ? AND s.student_id = ?
        ");
        $stmtCheck->execute([$teacherId, $record['student_id']]);
        if (!$stmtCheck->fetch()) {
            echo json_encode(['status' => 'error', 'message' => 'คุณไม่มีสิทธิ์อนุมัติให้นักเรียนคนนี้']);
            exit;
        }
    }

    $newStatus = ($action === 'approve') ? 'approved' : 'rejected';
    $activity = $record['activity'];
    if ($action === 'reject') {
        $activity .= " (ปฏิเสธ: $note)";
    }

    $stmt = $pdo->prepare("
        UPDATE beh_records 
        SET status = ?, 
            teacher_name = ?, 
            teacher_user_id = ?, 
            activity = ?
        WHERE id = ?
    ");
    $stmt->execute([$newStatus, $teacherName, $teacherId, $activity, $recordId]);

    $msg = ($action === 'approve') ? 'อนุมัติความดีเรียบร้อยแล้ว' : 'ปฏิเสธคำขอเรียบร้อยแล้ว';
    echo json_encode(['status' => 'success', 'message' => $msg]);

} catch (Exception $e) {
    error_log('[behavior] review_deed error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาด']);
}
