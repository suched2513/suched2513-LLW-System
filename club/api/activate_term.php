<?php
header('Content-Type: application/json; charset=utf-8');
session_start();
require_once __DIR__ . '/../../config/database.php';

if (!isset($_SESSION['llw_role']) || !in_array($_SESSION['llw_role'], ['super_admin', 'club_admin'], true)) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'คุณไม่มีสิทธิ์ดำเนินการนี้']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$semester = isset($input['semester']) ? (int)$input['semester'] : 2;
$year     = isset($input['year'])     ? (int)$input['year']     : 2569;

if ($semester < 1 || $semester > 2 || $year < 2500) {
    echo json_encode(['status' => 'error', 'message' => 'ข้อมูลภาคเรียนไม่ถูกต้อง']);
    exit;
}

try {
    $pdo = getPdo();
    $pdo->beginTransaction();

    // Deactivate all
    $pdo->exec("UPDATE club_settings SET is_active = 0");

    // Upsert target term as active
    $stmt = $pdo->prepare("
        INSERT INTO club_settings (semester, year, reg_open, reg_close, allow_change, is_active)
        VALUES (?, ?, NULL, NULL, 1, 1)
        ON DUPLICATE KEY UPDATE is_active = 1
    ");
    $stmt->execute([$semester, $year]);

    $pdo->commit();
    echo json_encode([
        'status' => 'success',
        'message' => "เปิดใช้งานภาคเรียนที่ $semester/$year เรียบร้อยแล้ว",
        'semester' => $semester,
        'year' => $year
    ]);
} catch (\Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('[activate_term] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()]);
}
