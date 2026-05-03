<?php
header('Content-Type: application/json; charset=utf-8');
session_start();
require_once __DIR__ . '/../../config/database.php';

if (!isset($_SESSION['llw_role'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'กรุณาเข้าสู่ระบบ']);
    exit;
}
if (!in_array($_SESSION['llw_role'], ['super_admin', 'att_teacher'])) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'คุณไม่มีสิทธิ์']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$session_id = isset($input['session_id']) ? (int)$input['session_id'] : 0;
$records    = $input['records'] ?? [];

if ($session_id <= 0 || !is_array($records)) {
    echo json_encode(['status' => 'error', 'message' => 'ข้อมูลไม่ถูกต้อง']);
    exit;
}

try {
    $pdo = getPdo();

    // Get session info
    $stmt = $pdo->prepare("SELECT cs.*, cg.teacher_id FROM club_sessions cs JOIN club_groups cg ON cg.id = cs.club_id WHERE cs.id = ?");
    $stmt->execute([$session_id]);
    $session = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$session) {
        echo json_encode(['status' => 'error', 'message' => 'ไม่พบคาบนี้']);
        exit;
    }

    // Verify teacher owns this club
    if ($_SESSION['llw_role'] === 'att_teacher') {
        $teacherId = (int)($_SESSION['teacher_id'] ?? 0);
        if ((int)$session['teacher_id'] !== $teacherId) {
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'คุณไม่ใช่ครูที่ปรึกษาของชุมนุมนี้']);
            exit;
        }
    }

    $checkedBy = (int)($_SESSION['teacher_id'] ?? 0);
    $count = 0;

    $pdo->beginTransaction();

    $upsert = $pdo->prepare("INSERT INTO club_attendance (session_id, student_id, status, note, checked_by, checked_at)
        VALUES (?, ?, ?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE status=VALUES(status), note=VALUES(note), checked_by=VALUES(checked_by), checked_at=NOW()");

    foreach ($records as $rec) {
        $sid    = trim($rec['student_id'] ?? '');
        $st     = $rec['status'] ?? 'absent';
        $note   = trim($rec['note'] ?? '');
        if ($sid === '') continue;
        if (!in_array($st, ['present', 'absent', 'late', 'leave'])) $st = 'absent';
        $upsert->execute([$session_id, $sid, $st, $note, $checkedBy ?: null]);
        $count++;
    }

    // Update session status to done if it was planned
    if ($session['status'] === 'planned') {
        $upd = $pdo->prepare("UPDATE club_sessions SET status='done' WHERE id=?");
        $upd->execute([$session_id]);
    }

    $pdo->commit();

    echo json_encode(['status' => 'success', 'message' => "บันทึกการเช็คชื่อสำเร็จ $count รายการ", 'count' => $count]);
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    error_log('[save_attendance] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาด']);
}
