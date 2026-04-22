<?php
header('Content-Type: application/json; charset=utf-8');
session_start();
require_once __DIR__ . '/../../config/database.php';

if (!isset($_SESSION['llw_role'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'กรุณาเข้าสู่ระบบ']);
    exit;
}

$input = $_POST; // Using POST from form

try {
    $pdo = getPdo();
    $pdo->beginTransaction();

    $projectId = $input['project_id'];
    $activityId = $input['activity_id'];
    $amount = (float)$input['amount'];
    
    // 1. Generate Doc No
    $docNo = 'REQ-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -4));

    // 2. Insert into sbms_disbursements
    $stmt = $pdo->prepare("
        INSERT INTO sbms_disbursements 
        (doc_no, book_no, book_date, disbursement_type, project_id, activity_id, amount, requester_name, requester_position, signature_data, status, created_by)
        VALUES (?, ?, ?, 'project', ?, ?, ?, ?, ?, ?, 'pending', ?)
    ");
    $stmt->execute([
        $docNo,
        $input['book_no'],
        $input['book_date'],
        $projectId,
        $activityId,
        $amount,
        $input['requester_name'],
        $input['requester_position'],
        $input['signature'],
        $_SESSION['user_id'] ?? 0
    ]);

    // 3. Update Activity used budget
    $stmt = $pdo->prepare("UPDATE sbms_activities SET budget_used = budget_used + ? WHERE id = ?");
    $stmt->execute([$amount, $activityId]);

    // 4. Update Project used budget
    $stmt = $pdo->prepare("UPDATE sbms_projects SET used_amount = used_amount + ? WHERE id = ?");
    $stmt->execute([$amount, $projectId]);

    // 5. Update Budget (Source) used amount
    // Need to find which budget this project is using
    $stmt = $pdo->prepare("SELECT budget_id FROM sbms_projects WHERE id = ?");
    $stmt->execute([$projectId]);
    $budgetId = $stmt->fetchColumn();
    
    if ($budgetId) {
        $stmt = $pdo->prepare("UPDATE sbms_budgets SET used_amount = used_amount + ? WHERE id = ?");
        $stmt->execute([$amount, $budgetId]);
    }

    $pdo->commit();
    
    // Redirect back with success
    header('Location: ../index.php?success=1');
    exit;

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log($e->getMessage());
    header('Location: ../request_form.php?error=' . urlencode($e->getMessage()));
    exit;
}
