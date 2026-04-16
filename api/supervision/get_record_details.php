<?php
// api/supervision/get_record_details.php
header('Content-Type: application/json; charset=utf-8');
session_start();
require_once '../../config/database.php';

if (!isset($_SESSION['llw_role'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'กรุณาเข้าสู่ระบบ']);
    exit;
}

$id = $_GET['id'] ?? null;
if (!$id) {
    echo json_encode(['status' => 'error', 'message' => 'ไม่ระบุรหัสรายการ']);
    exit;
}

try {
    $pdo = getPdo();
    
    // 1. Get Metadata
    $stmt = $pdo->prepare("
        SELECT r.*, CONCAT(u.firstname, ' ', u.lastname) as teacher_name
        FROM sup_records r
        JOIN llw_users u ON r.teacher_id = u.user_id
        WHERE r.id = ?
    ");
    $stmt->execute([$id]);
    $record = $stmt->fetch();
    
    if (!$record) {
        echo json_encode(['status' => 'error', 'message' => 'ไม่พบข้อมูล']);
        exit;
    }

    // Security Check: If not admin/evaluator, can only see own record
    // First fetch evaluator status again to be safe
    $stmtC = $pdo->prepare("SELECT role, is_evaluator FROM llw_users WHERE user_id = ?");
    $stmtC->execute([$_SESSION['user_id']]);
    $currUser = $stmtC->fetch();
    $isAdmin = in_array($currUser['role'] ?? '', ['super_admin', 'wfh_admin']);
    $isEval = ($currUser['is_evaluator'] ?? 0) == 1;

    if (!$isAdmin && !$isEval && (int)$record['teacher_id'] !== (int)$_SESSION['user_id']) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'คุณไม่มีสิทธิ์เข้าถึงข้อมูลนี้']);
        exit;
    }

    // 2. Get Scores
    $stmtS = $pdo->prepare("SELECT item_idx, score FROM sup_scores WHERE record_id = ? ORDER BY item_idx ASC");
    $stmtS->execute([$id]);
    $scores = $stmtS->fetchAll();

    echo json_encode([
        'status' => 'success',
        'record' => $record,
        'scores' => $scores
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาดในการดึงข้อมูล']);
    error_log($e->getMessage());
}
