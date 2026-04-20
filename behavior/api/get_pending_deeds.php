<?php
/**
 * API: Get pending good deeds for advisor's students
 */
header('Content-Type: application/json; charset=utf-8');
session_start();
ob_start();
require_once __DIR__ . '/../../config/database.php';

if (!isset($_SESSION['llw_role'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'กรุณาเข้าสู่ระบบ']);
    exit;
}

try {
    $pdo = getPdo();
    $userId = $_SESSION['user_id'];
    $isSuper = ($_SESSION['llw_role'] === 'super_admin');

    if ($isSuper) {
        // Super Admin sees all pending
        $sql = "SELECT r.*, s.name as student_name, s.classroom, b.img_url as student_img
                FROM beh_records r
                LEFT JOIN att_students s ON (TRIM(LEADING '0' FROM r.student_id) = TRIM(LEADING '0' FROM s.student_id))
                LEFT JOIN beh_students b ON (TRIM(LEADING '0' FROM r.student_id) = TRIM(LEADING '0' FROM b.student_id))
                WHERE r.status = 'pending'
                ORDER BY r.created_at DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
    } else {
        // Advisor sees only their students based on beh_advisors mapping
        $sql = "SELECT r.*, s.name as student_name, s.classroom, b.img_url as student_img
                FROM beh_records r
                JOIN att_students s ON (TRIM(LEADING '0' FROM r.student_id) = TRIM(LEADING '0' FROM s.student_id))
                JOIN beh_advisors a ON (
                    SUBSTRING_INDEX(s.classroom, '/', 1) = a.level AND 
                    SUBSTRING_INDEX(s.classroom, '/', -1) = a.room
                )
                LEFT JOIN beh_students b ON (TRIM(LEADING '0' FROM r.student_id) = TRIM(LEADING '0' FROM b.student_id))
                WHERE r.status = 'pending' AND a.user_id = ?
                ORDER BY r.created_at DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$userId]);
    }
    
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (ob_get_length()) ob_clean();
    echo json_encode(['status' => 'success', 'data' => $records]);

} catch (Exception $e) {
    if (ob_get_length()) ob_clean();
    error_log('[behavior] get_pending_deeds error: ' . $e->getMessage());
    http_response_code(200);
    echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()]);
}
