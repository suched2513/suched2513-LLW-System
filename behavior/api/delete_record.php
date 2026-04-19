<?php
/**
 * API: Delete behavior record
 * POST JSON: { id: int }
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
$id = (int)($input['id'] ?? 0);

if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'ไม่พบ ID ที่ต้องการลบ']);
    exit;
}

try {
    $pdo = getPdo();

    // Get record to delete associated image
    $stmt = $pdo->prepare("SELECT image_path FROM beh_records WHERE id = ?");
    $stmt->execute([$id]);
    $record = $stmt->fetch();

    if (!$record) {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'ไม่พบรายการนี้']);
        exit;
    }

    // Delete the record
    $stmtDel = $pdo->prepare("DELETE FROM beh_records WHERE id = ?");
    $stmtDel->execute([$id]);

    // Delete associated image file
    if (!empty($record['image_path'])) {
        $filePath = __DIR__ . '/../../' . ltrim($record['image_path'], '/');
        if (file_exists($filePath)) {
            @unlink($filePath);
        }
    }

    echo json_encode(['status' => 'success', 'message' => 'ลบรายการเรียบร้อย']);

} catch (Exception $e) {
    error_log('[behavior] delete_record error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาด']);
}
