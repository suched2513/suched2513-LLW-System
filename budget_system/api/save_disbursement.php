<?php
header('Content-Type: application/json; charset=utf-8');
session_start();
require_once __DIR__ . '/../../config/database.php';

// Auth guard
if (!isset($_SESSION['llw_role'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'กรุณาเข้าสู่ระบบ']);
    exit;
}

try {
    $pdo = getPdo();
    
    $projectId = $_POST['project_id'] ?: null;
    $budgetId = $_POST['budget_id'] ?: null;
    $type = $_POST['disbursement_type'] ?? 'other';
    $amount = $_POST['amount'] ?? 0;
    $method = $_POST['payment_method'] ?? 'transfer';
    $docNo = $_POST['doc_no'] ?? '';
    
    if ($amount <= 0) {
        throw new Exception('จำนวนเงินต้องมากกว่า 0');
    }

    if (!$projectId && !$budgetId) {
        throw new Exception('กรุณาเลือกโครงการหรือหมวดงบประมาณ');
    }
    
    $pdo->beginTransaction();

    // 1. Create Disbursement Record
    $stmt = $pdo->prepare("
        INSERT INTO sbms_disbursements (doc_no, disbursement_type, project_id, amount, payment_method, status, created_by)
        VALUES (?, ?, ?, ?, ?, 'paid', ?)
    ");
    $stmt->execute([$docNo, $type, $projectId, $amount, $method, $_SESSION['user_id'] ?? null]);

    // 2. Handle Project and Budget Updates
    if ($projectId) {
        // Update Project Balance
        $stmt = $pdo->prepare("UPDATE sbms_projects SET used_amount = used_amount + ? WHERE id = ?");
        $stmt->execute([$amount, $projectId]);
        
        // Find budget_id for this project to update the main budget plan
        $stmt = $pdo->prepare("SELECT budget_id FROM sbms_projects WHERE id = ?");
        $stmt->execute([$projectId]);
        $linkedBudgetId = $stmt->fetchColumn();
        
        if ($linkedBudgetId) {
            $stmt = $pdo->prepare("UPDATE sbms_budgets SET used_amount = used_amount + ? WHERE id = ?");
            $stmt->execute([$amount, $linkedBudgetId]);
        }
    } else if ($budgetId) {
        // Update Budget Balance directly (General Disbursement)
        $stmt = $pdo->prepare("UPDATE sbms_budgets SET used_amount = used_amount + ? WHERE id = ?");
        $stmt->execute([$amount, $budgetId]);
    }

    $pdo->commit();

    header('Location: ../disbursements.php?success=1');
    exit;

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    error_log($e->getMessage());
    header('Location: ../disbursements.php?error=' . urlencode($e->getMessage()));
}
