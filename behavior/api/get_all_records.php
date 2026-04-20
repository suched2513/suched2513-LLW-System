<?php
/**
 * API: Get all behavior records for database view
 * GET
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

    /** 
     * CANONICAL IDENTITY JOIN (v2)
     * Extremely lenient matching to handle:
     * - '04715' (Padded String)
     * - '4715' (Unpadded String)
     * - 4715 (Integer)
     * - ' 04715 ' (Spaces)
     */
    $stmt = $pdo->query("
        SELECT 
            r.*, 
            s.name as master_name, 
            s.classroom as master_classroom
        FROM beh_records r
        LEFT JOIN att_students s ON (
            TRIM(LEADING '0' FROM TRIM(BOTH ' ' FROM r.student_id)) = 
            TRIM(LEADING '0' FROM TRIM(BOTH ' ' FROM s.student_id))
        )
        ORDER BY r.record_date DESC, r.created_at DESC
        LIMIT 2000
    ");
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $data = [];
    foreach ($records as $r) {
        $displayClass = $r['master_classroom'] ?: '-';
        
        $data[] = [
            'id'          => (int)$r['id'],
            'date'        => $r['record_date'],
            'studentId'   => $r['student_id'],
            'studentName' => $r['master_name'] ?: ($r['student_name'] ?: 'ไม่ทราบชื่อ'),
            'classInfo'   => $displayClass,
            'type'        => $r['type'],
            'activity'    => $r['activity'],
            'score'       => (int)$r['score'],
            'teacher'     => $r['teacher_name'] ?? '',
            'status'      => $r['status'] ?? 'approved',
        ];
    }

    if (ob_get_length()) ob_clean();
    echo json_encode($data);

} catch (Exception $e) {
    if (ob_get_length()) ob_clean();
    error_log('[behavior] get_all_records error: ' . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาดในการดึงข้อมูล']);
}
