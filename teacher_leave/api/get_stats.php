<?php
/**
 * teacher_leave/api/get_stats.php
 * API for fetching leave statistics for a user
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

$userId = $_GET['user_id'] ?? $_SESSION['user_id'];
$date = $_GET['date'] ?? date('Y-m-d');
$fiscalYear = getThaiFiscalYear($date);

try {
    $pdo = getPdo();
    $stats = getUserLeaveStats($userId, $fiscalYear, $pdo);

    echo json_encode([
        'status' => 'success',
        'data' => [
            'fiscal_year'    => $fiscalYear,
            'sick_taken'     => (float)$stats['sick_taken'],
            'personal_taken' => (float)$stats['personal_taken'],
            'vacation_taken' => (float)$stats['vacation_taken'],
            'vacation_quota' => (float)$stats['vacation_quota'],
            'other_taken'    => (float)$stats['other_taken'],
        ]
    ]);

} catch (Exception $e) {
    error_log('[LLW] get_stats error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาด']);
}
