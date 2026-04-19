<?php
/**
 * API: Get pending good deeds for advisor's students
 */
header('Content-Type: application/json; charset=utf-8');
session_start();
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
        $sql = "SELECT r.*, s.level, s.room, s.img_url as student_img
                FROM beh_records r
                JOIN beh_students s ON r.student_id = s.student_id
                WHERE r.status = 'pending'
                ORDER BY r.created_at DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
    } else {
        // Advisor sees only their students based on beh_advisors mapping
        $sql = "SELECT r.*, s.level, s.room, s.img_url as student_img
                FROM beh_records r
                JOIN beh_students s ON r.student_id = s.student_id
                JOIN beh_advisors a ON s.level = a.level AND s.room = a.room
                WHERE r.status = 'pending' AND a.user_id = ?
                ORDER BY r.created_at DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$userId]);
    }
    
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['status' => 'success', 'data' => $records]);

} catch (Exception $e) {
    error_log('[behavior] get_pending_deeds error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาด']);
}
