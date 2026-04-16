<?php
/**
 * teacher_leave/api/get_holidays.php
 * Fetch upcoming holidays from the database
 */
header('Content-Type: application/json; charset=utf-8');
session_start();
require_once '../../config/database.php';

if (!isset($_SESSION['llw_role'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'กรุณาเข้าสู่ระบบ']);
    exit;
}

$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;

try {
    $pdo = getPdo();
    $today = date('Y-m-d');
    
    $stmt = $pdo->prepare("
        SELECT holiday_date, name 
        FROM tl_holidays 
        WHERE holiday_date >= ? 
        ORDER BY holiday_date ASC 
        LIMIT ?
    ");
    $stmt->execute([$today, $limit]);
    $holidays = $stmt->fetchAll();

    $data = [];
    foreach ($holidays as $h) {
        $date = new DateTime($h['holiday_date']);
        $h['formatted_date'] = $date->format('d/m/Y');
        $data[] = $h;
    }

    echo json_encode(['status' => 'success', 'data' => $data]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
