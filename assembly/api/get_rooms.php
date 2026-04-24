<?php
/**
 * assembly/api/get_rooms.php
 * GET — ดึงรายการห้องเรียนและชั้น
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
    $pdo  = getPdo();
    $role = $_SESSION['llw_role'];

    // att_teacher เห็นเฉพาะห้องของตัวเอง (จาก Central Table: llw_class_advisors)
    if ($role === 'att_teacher') {
        $userId = $_SESSION['user_id'] ?? 0;
        $stmt = $pdo->prepare("
            SELECT DISTINCT classroom
            FROM llw_class_advisors
            WHERE user_id = ?
            ORDER BY classroom
        ");
        $stmt->execute([$userId]);
    } else {
        // Admin เห็นทุกห้องที่มีนักเรียนในปีปัจจุบัน (2569)
        $stmt = $pdo->query("
            SELECT DISTINCT classroom 
            FROM att_students 
            WHERE academic_year = 2569 
            ORDER BY classroom
        ");
    }

    $classrooms = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // สร้าง grades จาก classroom
    $grades = [];
    foreach ($classrooms as $c) {
        if (preg_match('/ม\.(\d+)/', $c, $m)) {
            $grades[] = 'ม.' . $m[1];
        }
    }
    $grades = array_values(array_unique($grades));
    sort($grades);

    echo json_encode([
        'status'     => 'success',
        'classrooms' => $classrooms,
        'grades'     => $grades,
    ]);
} catch (Exception $e) {
    error_log('[Assembly] get_rooms: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาด']);
}
