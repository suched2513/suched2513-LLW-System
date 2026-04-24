<?php
header('Content-Type: application/json; charset=utf-8');
session_start();
require_once __DIR__ . '/../../config/database.php';

if (!isset($_SESSION['llw_role'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$id = $_GET['id'] ?? null;
if (!$id) {
    echo json_encode(['status' => 'error', 'message' => 'Missing ID']);
    exit;
}

try {
    $pdo = getPdo();
    
    // Check ownership or admin status
    $stmt = $pdo->prepare("SELECT recorded_by_user_id FROM clean_scores WHERE id = ?");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    
    if (!$row) {
        throw new Exception("Record not found");
    }

    if (!in_array($_SESSION['llw_role'], ['super_admin']) && $_SESSION['user_id'] != $row['recorded_by_user_id']) {
        throw new Exception("คุณไม่มีสิทธิ์ลบรายการนี้");
    }

    $stmtDelete = $pdo->prepare("DELETE FROM clean_scores WHERE id = ?");
    $stmtDelete->execute([$id]);

    echo json_encode(['status' => 'success']);

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
