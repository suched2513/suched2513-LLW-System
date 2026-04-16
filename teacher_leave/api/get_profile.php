<?php
/**
 * teacher_leave/api/get_profile.php
 * API for fetching user profile for the leave form
 */
header('Content-Type: application/json; charset=utf-8');
session_start();
require_once '../../config/database.php';

if (!isset($_SESSION['llw_role'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'กรุณาเข้าสู่ระบบ']);
    exit;
}

$userId = $_SESSION['user_id'];

try {
    $pdo = getPdo();
    $stmt = $pdo->prepare("SELECT firstname, lastname, position, subject_group FROM llw_users WHERE user_id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    if (!$user) {
        throw new Exception('ไม่พบข้อมูลผู้ใช้');
    }

    echo json_encode([
        'status' => 'success',
        'data' => $user
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
