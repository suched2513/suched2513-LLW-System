<?php
/**
 * API: Get Subject Attendance Data
 * ดึงข้อมูลการเข้าเรียนรายวิชา (คาบ 1-8) จากระบบ Attendance
 */
header('Content-Type: application/json; charset=utf-8');
session_start();
ob_start();
require_once __DIR__ . '/../../config/database.php';

if (!isset($_SESSION['llw_role'])) {
    $mode = $_GET['mode'] ?? 'teacher';
    if ($mode !== 'student') {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'กรุณาเข้าสู่ระบบ']);
        exit;
    }
}

$studentId = $_GET['sid'] ?? $_GET['student_id'] ?? '';

if (empty($studentId)) {
    echo json_encode(['status' => 'success', 'data' => []]);
    exit;
}

// Normalize: pad to 5 digits
if (preg_match('/^\d+$/', $studentId)) {
    $studentId = str_pad($studentId, 5, '0', STR_PAD_LEFT);
}

try {
    $pdo = getPdo();
    
    // Poly-Identity Resolver
    $sidPadded = $studentId;
    if (preg_match('/^\d+$/', $studentId)) {
        $sidPadded = str_pad($studentId, 5, '0', STR_PAD_LEFT);
    }
    $sidUnpadded = ltrim($sidPadded, '0');

    // Search master table for BOTH padded and unpadded ID
    $stmtSt = $pdo->prepare("SELECT id FROM att_students WHERE student_id = ? OR student_id = ? LIMIT 1");
    $stmtSt->execute([$sidPadded, $sidUnpadded]);
    $st = $stmtSt->fetch();
    
    if (!$st) {
        if (ob_get_length()) ob_clean();
        echo json_encode(['status' => 'success', 'data' => [], 'debug_msg' => 'Student not found in master table']);
        exit;
    }
    
    $internalId = $st['id'];

    // Query using internal id
    $stmt = $pdo->prepare("
        SELECT 
            a.date, 
            a.period, 
            a.status, 
            s.subject_name,
            s.subject_code
        FROM att_attendance a
        LEFT JOIN att_subjects s ON a.subject_id = s.id
        WHERE a.student_id = ? 
        ORDER BY a.date DESC, a.period ASC
        LIMIT 100
    ");
    $stmt->execute([$internalId]);
    $raw = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // จัดกลุ่มตามวันที่
    $grouped = [];
    foreach ($raw as $r) {
        $date = $r['date'];
        if (!isset($grouped[$date])) {
            $grouped[$date] = [
                'date' => $date,
                'periods' => array_fill(1, 8, null)
            ];
        }
        $p = (int)$r['period'];
        if ($p >= 1 && $p <= 8) {
            $grouped[$date]['periods'][$p] = [
                'status' => $r['status'],
                'subject' => $r['subject_name'] ? ($r['subject_code'] . ' ' . $r['subject_name']) : 'วิชาทั่วไป'
            ];
        }
    }

    if (ob_get_length()) ob_clean();
    echo json_encode([
        'status' => 'success', 
        'data' => array_values($grouped),
        'internal_id' => $internalId
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาดในการดึงข้อมูลการเข้าเรียน']);
    error_log($e->getMessage());
}
