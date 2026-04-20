<?php
/**
 * api/save_advisors.php
 * POST { mappings: { "ม.1/1": [u1, u2], ... } }
 */
header('Content-Type: application/json; charset=utf-8');
session_start();
require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['llw_role']) || $_SESSION['llw_role'] !== 'super_admin') {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$mappings = $input['mappings'] ?? [];

if (empty($mappings)) {
    echo json_encode(['status' => 'success', 'message' => 'ไม่มีการเปลี่ยนแปลง']);
    exit;
}

try {
    $pdo = getPdo();
    $pdo->beginTransaction();

    // 1. Clear ALL existing mappings
    $pdo->exec("DELETE FROM llw_class_advisors");

    // 2. Insert new mappings
    $stmt = $pdo->prepare("INSERT INTO llw_class_advisors (classroom, user_id, role_type) VALUES (?, ?, ?)");
    
    foreach ($mappings as $room => $userIds) {
        $uniqueUsers = array_unique($userIds);
        foreach ($uniqueUsers as $idx => $uid) {
            if (!$uid) continue;
            $roleType = ($idx === 0) ? 'primary' : 'secondary';
            $stmt->execute([$room, (int)$uid, $roleType]);
        }
    }

    $pdo->commit();
    echo json_encode(['status' => 'success', 'message' => 'บันทึกข้อมูลเรียบร้อยแล้ว']);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('[API] save_advisors error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาดในการบันทึกข้อมูล']);
}
