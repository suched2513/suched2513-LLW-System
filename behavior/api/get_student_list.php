<?php
/**
 * API: Get student list for modal
 * GET
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

    $stmt = $pdo->query("
        SELECT student_id, name, level, room, homeroom
        FROM beh_students
        WHERE status = 'active'
        ORDER BY level, room, name
    ");

    $list = [];
    foreach ($stmt->fetchAll() as $s) {
        $classText = trim(($s['level'] ?? '') . '/' . ($s['room'] ?? ''), '/');
        $list[] = [
            'studentId' => $s['student_id'],
            'name'      => $s['name'],
            'classText' => $classText,
            'homeroom'  => $s['homeroom'] ?? '',
        ];
    }

    echo json_encode($list);

} catch (Exception $e) {
    error_log('[behavior] get_student_list error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาด']);
}
