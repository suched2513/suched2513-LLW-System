<?php
/**
 * API: Manage Advisor Mappings
 * GET ?action=list
 * POST { action: 'save'|'delete', level, room, mappingId }
 */
header('Content-Type: application/json; charset=utf-8');
session_start();
require_once __DIR__ . '/../../config/database.php';

if (!isset($_SESSION['llw_role'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'กรุณาเข้าสู่ระบบ']);
    exit;
}

$action = $_GET['action'] ?? '';
$input  = json_decode(file_get_contents('php://input'), true);
if (!$action) $action = $input['action'] ?? '';

try {
    $pdo = getPdo();
    $userId = $_SESSION['user_id'];

    if ($action === 'list') {
        $stmt = $pdo->prepare("SELECT * FROM beh_advisors WHERE user_id = ? ORDER BY level, room");
        $stmt->execute([$userId]);
        echo json_encode(['status' => 'success', 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        exit;
    }

    if ($action === 'save') {
        $level = trim($input['level'] ?? '');
        $room  = trim($input['room'] ?? '');
        if ($level === '' || $room === '') {
            echo json_encode(['status' => 'error', 'message' => 'กรุณากรอกข้อมูลให้ครบ'});
            exit;
        }

        // Check if mapping already exists for this user
        $stmt = $pdo->prepare("SELECT id FROM beh_advisors WHERE user_id = ? AND level = ? AND room = ?");
        $stmt->execute([$userId, $level, $room]);
        if ($stmt->fetch()) {
            echo json_encode(['status' => 'error', 'message' => 'ระบุห้องนี้ไปแล้ว']);
            exit;
        }

        $stmt = $pdo->prepare("INSERT INTO beh_advisors (user_id, level, room) VALUES (?, ?, ?)");
        $stmt->execute([$userId, $level, $room]);
        echo json_encode(['status' => 'success', 'message' => 'เพิ่มห้องที่ดูแลแล้ว']);
        exit;
    }

    if ($action === 'delete') {
        $mid = (int)($input['mappingId'] ?? 0);
        $stmt = $pdo->prepare("DELETE FROM beh_advisors WHERE id = ? AND user_id = ?");
        $stmt->execute([$mid, $userId]);
        echo json_encode(['status' => 'success', 'message' => 'ลบข้อมูลแล้ว']);
        exit;
    }

    echo json_encode(['status' => 'error', 'message' => 'Action not found']);

} catch (Exception $e) {
    error_log('[behavior] manage_advisors error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาด']);
}
