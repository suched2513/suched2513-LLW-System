<?php
/**
 * teacher_leave/api/calculate_days.php
 * API for calculating leave days excluding weekends and holidays
 */
header('Content-Type: application/json; charset=utf-8');
session_start();
require_once '../../config/database.php';
require_once '../includes/functions.php';

if (!isset($_SESSION['llw_role'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'กรุณาเข้าสู่ระบบ']);
    exit;
}

$start = $_GET['start'] ?? '';
$end = $_GET['end'] ?? '';

if (!$start || !$end) {
    echo json_encode(['status' => 'success', 'days' => 0]);
    exit;
}

try {
    $pdo = getPdo();
    $days = calculateLeaveDays($start, $end, $pdo);

    echo json_encode([
        'status' => 'success',
        'days' => $days
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
