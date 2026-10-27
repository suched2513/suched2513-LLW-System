<?php
/**
 * teacher_leave/api/get_requests.php
 * Fetch leave requests (filtered by user if not admin)
 */
header('Content-Type: application/json; charset=utf-8');
session_start();
require_once '../../config.php';

if (!isset($_SESSION['llw_role'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'กรุณาเข้าสู่ระบบ']);
    exit;
}

$isAdmin = function_exists('llw_has_any_role')
    ? llw_has_any_role(['super_admin', 'wfh_admin', 'director', 'deputy_director'])
    : in_array($_SESSION['llw_role'] ?? '', ['super_admin', 'wfh_admin', 'director', 'deputy_director']);

$userId = (int)($_SESSION['user_id'] ?? 0);

try {
    $pdo = getPdo();

    // ตรวจสอบสิทธิ์เพิ่มเติมจาก llw_user_roles เผื่อกรณี session ยังไม่อัปเดต
    if (!$isAdmin && $userId > 0) {
        try {
            $stmtRole = $pdo->prepare("SELECT 1 FROM llw_user_roles WHERE user_id = ? AND role IN ('super_admin', 'wfh_admin', 'director', 'deputy_director') LIMIT 1");
            $stmtRole->execute([$userId]);
            if ($stmtRole->fetchColumn()) {
                $isAdmin = true;
            }
        } catch (\Throwable $ignore) {}
    }
    
    // Logic: If Admin, show all. If normal user, show own requests.
    if ($isAdmin) {
        $stmt = $pdo->query("
            SELECT r.*, 
                   COALESCE(u.firstname, '') AS firstname, 
                   COALESCE(u.lastname, '') AS lastname,
                   COALESCE(u.position, '') AS position
            FROM tl_requests r
            LEFT JOIN llw_users u ON r.user_id = u.user_id
            ORDER BY FIELD(r.status,'pending','approved','rejected'), r.created_at DESC
        ");
    } else {
        $stmt = $pdo->prepare("
            SELECT r.*, 
                   COALESCE(u.firstname, '') AS firstname, 
                   COALESCE(u.lastname, '') AS lastname,
                   COALESCE(u.position, '') AS position
            FROM tl_requests r 
            LEFT JOIN llw_users u ON r.user_id = u.user_id 
            WHERE r.user_id = ? 
            ORDER BY r.created_at DESC
        ");
        $stmt->execute([$userId]);
    }
    
    $rows = $stmt->fetchAll();
    
    $typeMap = [
        'sick'      => 'ลาป่วย',
        'personal'  => 'ลากิจส่วนตัว',
        'vacation'  => 'ลาพักผ่อน',
        'maternity' => 'ลาคลอดบุตร',
        'other'     => 'ลาอื่นๆ'
    ];

    $data = [];
    foreach ($rows as $r) {
        $r['leave_type_text'] = $typeMap[$r['leave_type']] ?? 'ลาอื่นๆ';
        $name = trim(($r['firstname'] ?? '') . ' ' . ($r['lastname'] ?? ''));
        $r['t_name'] = $name !== '' ? $name : ('ผู้ใช้งาน #' . $r['user_id']);
        $data[] = $r;
    }

    echo json_encode(['status' => 'success', 'data' => $data]);

} catch (Exception $e) {
    error_log('[LLW] get_requests error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาด']);
}
