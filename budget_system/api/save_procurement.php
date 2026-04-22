<?php
header('Content-Type: application/json; charset=utf-8');
session_start();
require_once __DIR__ . '/../../config/database.php';

if (!isset($_SESSION['llw_role'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'กรุณาเข้าสู่ระบบ']);
    exit;
}

$input = $_POST;

try {
    $pdo = getPdo();
    $pdo->beginTransaction();

    // Generate PR Number (PR-YYYYMM-XXXX)
    $prefix = 'PR-' . date('Ym') . '-';
    $stmt = $pdo->prepare("SELECT pr_no FROM sbms_procurements WHERE pr_no LIKE ? ORDER BY pr_no DESC LIMIT 1");
    $stmt->execute([$prefix . '%']);
    $lastNo = $stmt->fetchColumn();
    
    $nextId = 1;
    if ($lastNo) {
        $nextId = (int)substr($lastNo, -4) + 1;
    }
    $prNo = $prefix . str_pad($nextId, 4, '0', STR_PAD_LEFT);

    $stmt = $pdo->prepare("
        INSERT INTO sbms_procurements (
            pr_no, title, project_id, estimated_amount, status, requested_by
        ) VALUES (?, ?, ?, ?, 'pr_pending', ?)
    ");

    $stmt->execute([
        $prNo,
        $input['title'],
        $input['project_id'] ?: null,
        $input['estimated_amount'],
        $_SESSION['user_id'] ?? 0
    ]);

    $pdo->commit();
    header('Location: ../procurement.php?success=1');
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log($e->getMessage());
    header('Location: ../procurement.php?error=' . urlencode('เกิดข้อผิดพลาด: ' . $e->getMessage()));
}
