<?php
header('Content-Type: application/json; charset=utf-8');
session_start();
require_once __DIR__ . '/../../../config.php';
require_once __DIR__ . '/../../config.php';

busRequireStaff(['bus_admin', 'bus_finance', 'super_admin', 'wfh_admin']);

$regId = (int)($_GET['reg_id'] ?? 0);
if ($regId <= 0) {
    echo json_encode(['payments' => []]);
    exit;
}

try {
    $pdo  = getPdo();
    $stmt = $pdo->prepare("
        SELECT p.id, p.amount, p.note,
               DATE_FORMAT(p.paid_at, '%d/%m/%Y %H:%i') AS paid_at,
               u.firstname AS staff_name
        FROM bus_payments p
        LEFT JOIN llw_users u ON u.user_id = p.recorded_by
        WHERE p.registration_id = ?
        ORDER BY p.paid_at DESC
    ");
    $stmt->execute([$regId]);
    echo json_encode(['payments' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
} catch (Exception $e) {
    error_log($e->getMessage());
    echo json_encode(['payments' => []]);
}
