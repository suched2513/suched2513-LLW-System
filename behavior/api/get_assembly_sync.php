<?php
/**
 * API: Get Assembly Sync Data
 * ดึงข้อมูลการเข้าแถวและระเบียบวินัยจากระบบ Assembly
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
    if (ob_get_length()) ob_clean();
    echo json_encode(['status' => 'success', 'data' => []]);
    exit;
}

// Poly-Identity Resolver
$sidPadded = $studentId;
if (preg_match('/^\d+$/', $studentId)) {
    $sidPadded = str_pad($studentId, 5, '0', STR_PAD_LEFT);
}
$sidUnpadded = ltrim($sidPadded, '0');

try {
    $pdo = getPdo();
    $stmt = $pdo->prepare("
        SELECT date, status, nail, hair, shirt, pants, socks, shoes, note 
        FROM assembly_attendance 
        WHERE student_id = ? OR student_id = ?
        ORDER BY date DESC 
        LIMIT 30
    ");
    $stmt->execute([$sidPadded, $sidUnpadded]);
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (ob_get_length()) ob_clean();
    echo json_encode([
        'status' => 'success', 
        'data' => $records,
        'debug' => ['sidPadded' => $sidPadded, 'sidUnpadded' => $sidUnpadded],
        'summary' => [
            'total' => count($records),
            'absent' => count(array_filter($records, fn($r) => $r['status'] === 'ข')),
            'late' => count(array_filter($records, fn($r) => $r['status'] === 'ส')),
            'dress_violation' => count(array_filter($records, fn($r) => 
                ($r['nail'] ?? '') === 'ผิด' || ($r['hair'] ?? '') === 'ผิด' || ($r['shirt'] ?? '') === 'ผิด' || 
                ($r['pants'] ?? '') === 'ผิด' || ($r['socks'] ?? '') === 'ผิด' || ($r['shoes'] ?? '') === 'ผิด'
            ))
        ]
    ]);

} catch (Exception $e) {
    if (ob_get_length()) ob_clean();
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาดในการเชื่อมโยงข้อมูล']);
    error_log($e->getMessage());
}
