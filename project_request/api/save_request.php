<?php
/**
 * api/save_request.php — Save project request (Draft/Submit)
 */
header('Content-Type: application/json; charset=utf-8');
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/constants.php';

// Auth guard
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'กรุณาเข้าสู่ระบบ']);
    exit;
}

try {
    $pdo = getPdo();
    $pdo->beginTransaction();

    // 1. Save main request
    $sql = "INSERT INTO project_requests (budget_project_id, user_id, request_date, proc_type, reason, amount_requested, status) 
            VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $_POST['budget_project_id'],
        $_SESSION['user_id'],
        $_POST['request_date'],
        $_POST['proc_type'],
        $_POST['reason'],
        $_POST['amount_requested'],
        $_POST['status'] // 'draft' or 'submitted'
    ]);
    $requestId = $pdo->lastInsertId();

    // 2. Save Items
    if (!empty($_POST['items'])) {
        $sql = "INSERT INTO request_items (request_id, item_order, item_name, quantity, unit, unit_price, total_price) 
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        foreach ($_POST['items'] as $index => $item) {
            if (empty($item['name'])) continue;
            $stmt->execute([
                $requestId,
                $index,
                $item['name'],
                $item['qty'],
                $item['unit'],
                $item['price'],
                $item['qty'] * $item['price']
            ]);
        }
    }

    // 3. Save Committee
    if (!empty($_POST['comm'])) {
        $sql = "INSERT INTO request_committee (request_id, member_order, member_name, position, role) 
                VALUES (?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        foreach ($_POST['comm'] as $index => $c) {
            if (empty($c['name'])) continue;
            $stmt->execute([
                $requestId,
                $index,
                $c['name'],
                $c['pos'],
                $c['role']
            ]);
        }
    }

    $pdo->commit();
    
    // Redirect back to request list or success page
    header('Location: ' . BASE_URL . '/teacher/request_list.php?success=1');
    exit();

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log($e->getMessage());
    die("เกิดข้อผิดพลาด: " . $e->getMessage());
}
