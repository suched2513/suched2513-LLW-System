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
        SELECT s.student_id, s.name, s.classroom, b.homeroom
        FROM att_students s
        LEFT JOIN beh_students b ON s.student_id = b.student_id
        ORDER BY s.classroom, s.name
    ");

    $list = [];
    foreach ($stmt->fetchAll() as $s) {
        $sid = $s['student_id'];
        // Ensure 5-digit padding for IDs that are numbers
        if (preg_match('/^\d+$/', $sid)) {
            $sid = str_pad($sid, 5, '0', STR_PAD_LEFT);
        }

        $list[] = [
            'studentId' => $sid,
            'name'      => $s['name'],
            'classText' => $s['classroom'],
            'homeroom'  => $s['homeroom'] ?? '',
        ];
    }

    echo json_encode($list);

} catch (Exception $e) {
    error_log('[behavior] get_student_list error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาด']);
}
