<?php
/**
 * teacher_leave/api/get_admin_stats.php
 * Summary stats for administrators
 */
header('Content-Type: application/json; charset=utf-8');
session_start();
require_once '../../config.php';

$isAdmin = function_exists('llw_has_any_role')
    ? llw_has_any_role(['super_admin', 'wfh_admin', 'director', 'deputy_director'])
    : in_array($_SESSION['llw_role'] ?? '', ['super_admin', 'wfh_admin', 'director', 'deputy_director']);

$userId = (int)($_SESSION['user_id'] ?? 0);

if (!$isAdmin && $userId > 0) {
    try {
        $stmtRole = getPdo()->prepare("SELECT 1 FROM llw_user_roles WHERE user_id = ? AND role IN ('super_admin', 'wfh_admin', 'director', 'deputy_director') LIMIT 1");
        $stmtRole->execute([$userId]);
        if ($stmtRole->fetchColumn()) {
            $isAdmin = true;
        }
    } catch (\Throwable $ignore) {}
}

if (!$isAdmin) {
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

    // รวมรออนุมัติทั้งหมด
    $waiting_total = $waiting_staff + $waiting_director;

    // 3. ลาวันนี้ (Approved และวันที่ปัจจุบันอยู่ระหว่าง Start - End)
    $stmt3 = $pdo->prepare("
        SELECT COUNT(*) 
        FROM tl_requests 
        WHERE status = 'approved' 
        AND ? BETWEEN date_start AND date_end
    ");
    $stmt3->execute([$today]);
    $on_leave_today = (int)$stmt3->fetchColumn();

    // 4. ลาวันนี้ (ที่ยังรออนุมัติอยู่)
    $stmt4 = $pdo->prepare("
        SELECT COUNT(*) 
        FROM tl_requests 
        WHERE status = 'pending' 
        AND ? BETWEEN date_start AND date_end
    ");
    $stmt4->execute([$today]);
    $pending_today = (int)$stmt4->fetchColumn();

    echo json_encode([
        'status' => 'success',
        'data' => [
            'waiting_staff'    => $waiting_staff,
            'waiting_director' => $waiting_director,
            'waiting_total'    => $waiting_total,
            'on_leave_today'   => $on_leave_today,
            'pending_today'    => $pending_today
        ]
    ]);

} catch (Exception $e) {
    error_log('[LLW] get_admin_stats error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาด']);
}
