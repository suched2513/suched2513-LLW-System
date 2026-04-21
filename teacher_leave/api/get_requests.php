<?php
/**
 * teacher_leave/api/get_requests.php
 * Fetch leave requests (filtered by user if not admin)
 */
header('Content-Type: application/json; charset=utf-8');
session_start();
require_once '../../config/database.php';

if (!isset($_SESSION['llw_role'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'กรุณาเข้าสู่ระบบ']);
    exit;
}

$isAdmin = in_array($_SESSION['llw_role'], ['super_admin', 'wfh_admin']);
$userId = $_SESSION['user_id'];

try {
    $pdo = getPdo();
    
    // Logic: If Admin, show all pending or all? 
    // Usually admin wants to see pending first. But for history let's show all latest.
    if ($isAdmin) {
        $stmt = $pdo->query("
            SELECT r.*, u.firstname, u.lastname 
            FROM tl_requests r 
            JOIN llw_users u ON r.user_id = u.user_id 
            ORDER BY r.created_at DESC
        ");
    } else {
        $stmt = $pdo->prepare("
            SELECT r.*, u.firstname, u.lastname 
            FROM tl_requests r 
            JOIN llw_users u ON r.user_id = u.user_id 
            WHERE r.user_id = ? 
            ORDER BY r.created_at DESC
        ");
        $stmt->execute([$userId]);
    }
    
    $rows = $stmt->fetchAll();
    
    $typeMap = [
        'sick' => 'ลาป่วย',
        'personal' => 'ลากิจส่วนตัว',
        'vacation' => 'ลาพักผ่อน',
        'maternity' => 'ลาคลอดบุตร',
        'other' => 'ลาอื่นๆ'
    ];

    $data = [];
    foreach ($rows as $r) {
        $r['leave_type_text'] = $typeMap[$r['leave_type']] ?? 'ลาอื่นๆ';
        $r['t_name'] = $r['firstname'] . ' ' . $r['lastname'];
        $data[] = $r;
    }

    echo json_encode(['status' => 'success', 'data' => $data]);

} catch (Exception $e) {
    error_log('[LLW] get_requests error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาด']);
}
