<?php
/**
 * assembly/api/save_checkout.php
 * POST JSON — บันทึกข้อมูลกลับบ้าน (evening checkout)
 */
header('Content-Type: application/json; charset=utf-8');
session_start();
require_once __DIR__ . '/../../config/database.php';

if (!isset($_SESSION['llw_role'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'กรุณาเข้าสู่ระบบ']);
    exit;
}

$input     = json_decode(file_get_contents('php://input'), true);
$records   = $input['records']   ?? [];
$date      = $input['date']      ?? date('Y-m-d');
$classroom = $input['classroom'] ?? '';

if (empty($classroom) || empty($records)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'ข้อมูลไม่ครบถ้วน']);
    exit;
}

try {
    $pdo       = getPdo();
    $createdBy = $_SESSION['user_id'] ?? null;

    $stmt = $pdo->prepare("
        INSERT INTO assembly_checkout (date, classroom, student_id, name, status, note, created_by)
        VALUES (:date, :classroom, :student_id, :name, :status, :note, :created_by)
        ON DUPLICATE KEY UPDATE
            status     = VALUES(status),
            note       = VALUES(note)
    ");

    // เพิ่ม unique index ถ้ายังไม่มี (defensive)
    $count = 0;
    foreach ($records as $r) {
        $studentId = trim($r['student_id'] ?? $r['id'] ?? '');
        if ($studentId === '') continue;

        // Normalize: pad to 5 digits
        if (preg_match('/^\d+$/', $studentId)) {
            $studentId = str_pad($studentId, 5, '0', STR_PAD_LEFT);
        }
        $stmt->execute([
            ':date'       => $date,
            ':classroom'  => $classroom,
            ':student_id' => $studentId,
            ':name'       => $r['name']   ?? '',
            ':status'     => $r['status'] ?? 'มา',
            ':note'       => $r['note']   ?? '',
            ':created_by' => $createdBy,
        ]);
        $count++;
    }

    echo json_encode(['status' => 'success', 'saved' => $count]);
} catch (Exception $e) {
    error_log('[Assembly] save_checkout: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาด']);
}
