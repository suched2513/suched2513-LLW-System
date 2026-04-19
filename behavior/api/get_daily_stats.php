<?php
/**
 * API: Get daily statistics
 * GET ?date=YYYY-MM-DD
 */
header('Content-Type: application/json; charset=utf-8');
session_start();
require_once __DIR__ . '/../../config/database.php';

if (!isset($_SESSION['llw_role'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'กรุณาเข้าสู่ระบบ']);
    exit;
}

$date = $_GET['date'] ?? date('Y-m-d');

try {
    $pdo = getPdo();

    // จำนวนนักเรียนที่ทำความดี (unique students)
    $stmtGood = $pdo->prepare("
        SELECT COUNT(DISTINCT student_id) FROM beh_records 
        WHERE record_date = ? AND type = 'ความดี'
    ");
    $stmtGood->execute([$date]);
    $personGood = (int)$stmtGood->fetchColumn();

    // จำนวนนักเรียนที่ทำความผิด (unique students)
    $stmtBad = $pdo->prepare("
        SELECT COUNT(DISTINCT student_id) FROM beh_records 
        WHERE record_date = ? AND type = 'ความผิด'
    ");
    $stmtBad->execute([$date]);
    $personBad = (int)$stmtBad->fetchColumn();

    // จำนวนรายการทั้งหมด
    $stmtTotal = $pdo->prepare("
        SELECT COUNT(*) FROM beh_records WHERE record_date = ?
    ");
    $stmtTotal->execute([$date]);
    $dailyRecords = (int)$stmtTotal->fetchColumn();

    echo json_encode([
        'status'       => 'success',
        'personGood'   => $personGood,
        'personBad'    => $personBad,
        'dailyRecords' => $dailyRecords,
    ]);

} catch (Exception $e) {
    error_log('[behavior] get_daily_stats error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาด']);
}
