<?php
// api/verify_pin.php — ตรวจสอบ PIN ผอ. ก่อนอนุมัติคำขออกนอกบริเวณ
header('Content-Type: application/json; charset=utf-8');
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['llw_role'])) {
    http_response_code(401);
    echo json_encode(['valid' => false, 'message' => 'กรุณาเข้าสู่ระบบก่อน']);
    exit;
}

if (!in_array($_SESSION['llw_role'], ['super_admin', 'wfh_admin'])) {
    http_response_code(403);
    echo json_encode(['valid' => false, 'message' => 'คุณไม่มีสิทธิ์']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input || !isset($input['pin'])) {
    echo json_encode(['valid' => false, 'message' => 'กรุณากรอก PIN']);
    exit;
}

$pin = trim($input['pin']);

try {
    $pdo = getPdo();
    $stmt = $pdo->query("SELECT boss_pin FROM wfh_system_settings LIMIT 1");
    $row  = $stmt->fetch();

    if (!$row || empty($row['boss_pin'])) {
        // ยังไม่ได้ตั้ง PIN — อนุญาตผ่านได้เลย (เพื่อ backward compat)
        echo json_encode(['valid' => true, 'message' => 'ยังไม่ได้ตั้ง PIN — ผ่านอัตโนมัติ']);
        exit;
    }

    if (password_verify($pin, $row['boss_pin'])) {
        echo json_encode(['valid' => true, 'message' => 'PIN ถูกต้อง']);
    } else {
        echo json_encode(['valid' => false, 'message' => 'PIN ไม่ถูกต้อง']);
    }

} catch (Exception $e) {
    error_log('[LLW] verify_pin error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['valid' => false, 'message' => 'เกิดข้อผิดพลาดในระบบ']);
}
