<?php
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
    echo json_encode(['status' => 'error', 'message' => 'คุณไม่มีสิทธิ์']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$id = isset($input['id']) ? (int)$input['id'] : 0;

if ($id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'ID ไม่ถูกต้อง']);
    exit;
}

try {
    $pdo = getPdo();

    // Check if there are registrations
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM club_registrations WHERE club_id = ?");
    $stmt->execute([$id]);
    $regCount = (int)$stmt->fetchColumn();

    if ($regCount > 0) {
        echo json_encode(['status' => 'error', 'message' => "ไม่สามารถลบได้ เนื่องจากมีนักเรียนลงทะเบียนแล้ว $regCount คน"]);
        exit;
    }

    $stmt = $pdo->prepare("DELETE FROM club_groups WHERE id = ?");
    $stmt->execute([$id]);

    echo json_encode(['status' => 'success', 'message' => 'ลบชุมนุมสำเร็จ']);
} catch (Exception $e) {
    error_log('[delete_club] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาด']);
}
