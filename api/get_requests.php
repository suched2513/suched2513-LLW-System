<?php
// api/get_requests.php — ดึงข้อมูลคำขอออกนอกบริเวณจาก llw_db
header('Content-Type: application/json; charset=utf-8');
session_start();

require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['data' => []]);
    exit;
}

try {
    $pdo = getPdo();
    $user_id  = $_SESSION['user_id'];
    $userRole = $_SESSION['llw_role'] ?? 'wfh_staff';

    // super_admin และ wfh_admin ดูได้ทุกรายการ
    if (in_array($userRole, ['super_admin', 'wfh_admin'])) {
        $sql = "SELECT r.r_id, r.teacher_id, r.req_date, r.reason, r.detail, 
                       r.time_start, r.time_end, r.total_hr, r.has_class, 
                       r.status_boss1, r.created_at,
                       CONCAT(u.firstname,' ',u.lastname) AS t_name
                FROM leave_requests r
                JOIN llw_users u ON r.teacher_id = u.user_id
                ORDER BY r.created_at DESC";
        $stmt = $pdo->query($sql);
    } else {
        $sql = "SELECT r.r_id, r.teacher_id, r.req_date, r.reason, r.detail, 
                       r.time_start, r.time_end, r.total_hr, r.has_class, 
                       r.status_boss1, r.created_at,
                       CONCAT(u.firstname,' ',u.lastname) AS t_name
                FROM leave_requests r
                JOIN llw_users u ON r.teacher_id = u.user_id
                WHERE r.teacher_id = ?
                ORDER BY r.created_at DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$user_id]);
    }

    $requests = $stmt->fetchAll();
    echo json_encode(['data' => $requests]);

} catch (Exception $e) {
    echo json_encode(['data' => [], 'error' => $e->getMessage()]);
}
?>
