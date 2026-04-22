<?php
session_start();
require_once __DIR__ . '/../../config/database.php';

// Auth guard
if (!isset($_SESSION['llw_role']) || $_SESSION['llw_role'] !== 'super_admin') {
    http_response_code(403);
    exit;
}

$id = $_GET['id'] ?? null;

if ($id) {
    try {
        $pdo = getPdo();
        $pdo->beginTransaction();

        // Deactivate all
        $pdo->exec("UPDATE sbms_fiscal_years SET is_active = 0");
        
        // Activate selected
        $stmt = $pdo->prepare("UPDATE sbms_fiscal_years SET is_active = 1 WHERE id = ?");
        $stmt->execute([$id]);

        $pdo->commit();
        header('Location: ../settings.php?success=1');
        exit;
    } catch (Exception $e) {
        if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
        header('Location: ../settings.php?error=' . urlencode($e->getMessage()));
    }
}
