<?php
session_start();
require_once __DIR__ . '/../../config/database.php';

// Auth guard - Super Admin only
if (!isset($_SESSION['llw_role']) || $_SESSION['llw_role'] !== 'super_admin') {
    http_response_code(403);
    exit('Forbidden');
}

$action = $_GET['action'] ?? '';

try {
    $pdo = getPdo();
    $pdo->beginTransaction();

    if ($action === 'add_year') {
        $yearName = $_POST['year_name'] ?? '';
        $startDate = $_POST['start_date'] ?? '';
        $endDate = $_POST['end_date'] ?? '';
        
        if (!$yearName || !$startDate || !$endDate) {
            throw new Exception('กรุณากรอกข้อมูลให้ครบถ้วน');
        }

        $stmt = $pdo->prepare("INSERT INTO sbms_fiscal_years (year_name, start_date, end_date, is_active) VALUES (?, ?, ?, 0)");
        $stmt->execute([$yearName, $startDate, $endDate]);
    }

    $pdo->commit();
    header('Location: ../settings.php?success=1');
    exit;

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    error_log($e->getMessage());
    header('Location: ../settings.php?error=' . urlencode($e->getMessage()));
}
