<?php
header('Content-Type: application/json; charset=utf-8');
session_start();
require_once __DIR__ . '/../../config/database.php';

// Auth: student or teacher/admin
$isStudent = isset($_SESSION['is_student']) && $_SESSION['is_student'] === true;
$isStaff   = isset($_SESSION['llw_role']);

if (!$isStudent && !$isStaff) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'กรุณาเข้าสู่ระบบ']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'ข้อมูลไม่ถูกต้อง']);
    exit;
}

// Determine student_id
if ($isStudent) {
    $student_id = $_SESSION['student_code'] ?? '';
} else {
    // Teacher submitting for student: must supply student_id
    $student_id = trim($input['student_id'] ?? '');
}

if (empty($student_id)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'ไม่พบรหัสนักเรียน']);
    exit;
}

// Validate required fields
$leave_type  = $input['leave_type']  ?? '';
$date_from   = $input['date_from']   ?? '';
$date_to     = $input['date_to']     ?? '';
$reason      = trim($input['reason'] ?? '');
$parent_name  = trim($input['parent_name']  ?? '');
$parent_phone = trim($input['parent_phone'] ?? '');

$allowed_types = ['sick', 'personal', 'other'];
if (!in_array($leave_type, $allowed_types, true)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'ประเภทการลาไม่ถูกต้อง']);
    exit;
}

if (empty($date_from) || empty($date_to) || empty($reason)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'กรุณากรอกข้อมูลให้ครบถ้วน']);
    exit;
}

// Validate date format
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_from) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_to)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'รูปแบบวันที่ไม่ถูกต้อง']);
    exit;
}

if ($date_to < $date_from) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'วันที่สิ้นสุดต้องไม่ก่อนวันที่เริ่มต้น']);
    exit;
}

try {
    $pdo = getPdo();

    // Calculate days (weekdays only: DATEDIFF + 1 for inclusive)
    $stmt = $pdo->prepare("SELECT DATEDIFF(?, ?) + 1 AS days");
    $stmt->execute([$date_to, $date_from]);
    $days = (int)$stmt->fetchColumn();
    if ($days < 1) $days = 1;

    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
        INSERT INTO stl_requests
            (student_id, leave_type, date_from, date_to, days, reason, parent_name, parent_phone, status, created_at)
        VALUES
            (?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())
    ");
    $stmt->execute([
        $student_id,
        $leave_type,
        $date_from,
        $date_to,
        $days,
        $reason,
        $parent_name ?: null,
        $parent_phone ?: null,
    ]);

    $new_id = (int)$pdo->lastInsertId();

    $pdo->commit();

    echo json_encode(['status' => 'success', 'id' => $new_id, 'days' => $days]);

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาด กรุณาลองใหม่']);
    error_log('[stl submit] ' . $e->getMessage());
}
