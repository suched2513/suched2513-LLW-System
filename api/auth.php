<?php
/**
 * api/auth.php — Teacher/Staff Login API (Unified llw_db)
 * ใช้สำหรับ Leave System และ module อื่นที่ต้อง login ผ่าน API
 */
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once '../config/database.php';

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['username']) || !isset($input['password'])) {
    echo json_encode(['status' => 'error', 'message' => 'กรุณากรอก username และ password']);
    exit;
}

try {
    $pdo = getPdo();
    $stmt = $pdo->prepare("SELECT * FROM llw_users WHERE username = ? AND status = 'active' LIMIT 1");
    $stmt->execute([$input['username']]);
    $user = $stmt->fetch();

    if ($user && password_verify($input['password'], $user['password'])) {
        // ตั้ง session ตามมาตรฐาน llw_role
        $_SESSION['user_id']   = $user['user_id'];
        $_SESSION['username']  = $user['username'];
        $_SESSION['firstname'] = $user['firstname'];
        $_SESSION['fullname']  = trim($user['firstname'] . ' ' . $user['lastname']);
        $_SESSION['llw_role']  = $user['role'];
        $_SESSION['role']      = in_array($user['role'], ['super_admin', 'wfh_admin']) ? 'admin' : 'user';

        // อัปเดต last_login
        $pdo->prepare("UPDATE llw_users SET last_login = NOW() WHERE user_id = ?")->execute([$user['user_id']]);

        echo json_encode(['status' => 'success', 'message' => 'เข้าสู่ระบบสำเร็จ']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง']);
    }
} catch (Exception $e) {
    error_log('[LLW] api/auth error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาด']);
}
