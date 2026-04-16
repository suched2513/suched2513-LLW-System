<?php
/**
 * teacher_leave/api/get_admin_stats.php
 * Summary stats for administrators
 */
header('Content-Type: application/json; charset=utf-8');
session_start();
require_once '../../config/database.php';

if (!isset($_SESSION['llw_role']) || !in_array($_SESSION['llw_role'], ['super_admin', 'wfh_admin'])) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'คุณไม่มีสิทธิ์เข้าถึงข้อมูลนี้']);
    exit;
}

try {
    $pdo = getPdo();
    $today = date('Y-m-d');

    // 1. รอเจ้าหน้าที่ตรวจสอบ (Lv.1)
    $stmt1 = $pdo->query("SELECT COUNT(*) FROM tl_requests WHERE status = 'pending' AND level_at = 1");
    $waiting_staff = (int)$stmt1->fetchColumn();

    // 2. รอ ผอ./รองฯ อนุมัติ (Lv.2)
    $stmt2 = $pdo->query("SELECT COUNT(*) FROM tl_requests WHERE status = 'pending' AND level_at = 2");
    $waiting_director = (int)$stmt2->fetchColumn();

    // 3. ลาวันนี้ (Approved และวันที่ปัจจุบันอยู่ระหว่าง Start - End)
    $stmt3 = $pdo->prepare("
        SELECT COUNT(*) 
        FROM tl_requests 
        WHERE status = 'approved' 
        AND ? BETWEEN date_start AND date_end
    ");
    $stmt3->execute([$today]);
    $on_leave_today = (int)$stmt3->fetchColumn();

    echo json_encode([
        'status' => 'success',
        'data' => [
            'waiting_staff'    => $waiting_staff,
            'waiting_director' => $waiting_director,
            'on_leave_today'   => $on_leave_today
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
