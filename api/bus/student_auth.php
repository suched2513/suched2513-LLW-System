<?php
header('Content-Type: application/json; charset=utf-8');
session_start();
require_once __DIR__ . '/../../config/database.php';

$input = json_decode(file_get_contents('php://input'), true);
$sid       = trim($input['sid'] ?? '');
$citizenId = preg_replace('/\D/', '', $input['citizen_id'] ?? '');

if (!$sid || strlen($citizenId) !== 13) {
    echo json_encode(['status' => 'error', 'message' => 'กรุณากรอกรหัสนักเรียนและเลขบัตรประชาชน 13 หลัก']);
    exit;
}

if (preg_match('/^\d+$/', $sid)) {
    $sid = str_pad($sid, 5, '0', STR_PAD_LEFT);
}

try {
    $pdo  = getPdo();
    $stmt = $pdo->prepare("SELECT national_id_hash FROM att_students WHERE student_id = ? AND national_id_hash IS NOT NULL LIMIT 1");
    $stmt->execute([$sid]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$student || !password_verify($citizenId, $student['national_id_hash'])) {
        echo json_encode(['status' => 'error', 'message' => 'รหัสนักเรียนหรือเลขบัตรประชาชนไม่ถูกต้อง']);
        exit;
    }

    echo json_encode(['status' => 'success', 'message' => 'ยืนยันตัวตนสำเร็จ']);

} catch (Exception $e) {
    error_log('[bus/student_auth] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาดในการเชื่อมต่อ']);
}
