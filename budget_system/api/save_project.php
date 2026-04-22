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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method Not Allowed']);
    exit;
}

try {
    $pdo = getPdo();
    
    $projectName = $_POST['project_name'] ?? '';
    $fiscalYearId = $_POST['fiscal_year_id'] ?? null;
    $budgetId = $_POST['budget_id'] ?? null;
    $requestedAmount = $_POST['requested_amount'] ?? 0;
    
    if (empty($projectName) || empty($fiscalYearId)) {
        throw new Exception('กรุณากรอกข้อมูลให้ครบถ้วน');
    }

    // Generate Project Code (Optional)
    $projectCode = 'PROJ-' . date('Y') . '-' . rand(1000, 9999);

    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
        INSERT INTO sbms_projects (project_code, project_name, fiscal_year_id, budget_id, requested_amount, approved_amount, status, owner_id)
        VALUES (?, ?, ?, ?, ?, ?, 'approved', ?)
    ");
    
    // For simplicity in this demo, we auto-approve the requested amount
    $stmt->execute([
        $projectCode,
        $projectName,
        $fiscalYearId,
        $budgetId,
        $requestedAmount,
        $requestedAmount,
        $_SESSION['user_id'] ?? null
    ]);

    $pdo->commit();

    // Redirect back to projects page
    header('Location: ../projects.php?success=1');
    exit;

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    error_log($e->getMessage());
    header('Location: ../projects.php?error=' . urlencode($e->getMessage()));
}
