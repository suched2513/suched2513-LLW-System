<?php
// api/get_teachers.php — ดึงรายชื่อครูจาก llw_db สำหรับ Leave System
header('Content-Type: application/json; charset=utf-8');
session_start();

require_once '../config/database.php';

try {
    $pdo = getPdo();
    // ดึงครูจาก llw_users ที่มีสิทธิ์ att_teacher หรือ super_admin (คนที่สอนได้)
    // รวมถึง wfh_staff ที่เป็นครูด้วย
    $stmt = $pdo->query("
        SELECT u.user_id AS t_id, 
               CONCAT(u.firstname,' ',u.lastname) AS t_name
        FROM llw_users u 
        WHERE u.status = 'active'
          AND u.role IN ('att_teacher','super_admin','wfh_staff','wfh_admin')
        ORDER BY u.firstname ASC
    ");
    $teachers = $stmt->fetchAll();
    echo json_encode(['status' => 'success', 'data' => $teachers]);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
