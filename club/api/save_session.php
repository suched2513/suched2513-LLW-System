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

$id           = isset($input['id']) ? (int)$input['id'] : 0;
$isDelete     = !empty($input['_delete']);
$club_id      = isset($input['club_id']) ? (int)$input['club_id'] : 0;
$session_date = trim($input['session_date'] ?? '');
$period       = trim($input['period'] ?? '');
$topic        = trim($input['topic'] ?? '');
$description  = trim($input['description'] ?? '');
$status       = $input['status'] ?? 'planned';

if (!$isDelete && ($club_id <= 0 || $session_date === '')) {
    echo json_encode(['status' => 'error', 'message' => 'กรุณาระบุข้อมูลให้ครบ']);
    exit;
}
if (!in_array($status, ['planned', 'done', 'cancelled'])) {
    $status = 'planned';
}

try {
    $pdo = getPdo();

    // Verify teacher owns this club (att_teacher only)
    if ($_SESSION['llw_role'] === 'att_teacher') {
        $teacherId = (int)($_SESSION['teacher_id'] ?? 0);
        $stmt = $pdo->prepare("SELECT id FROM club_groups WHERE id = ? AND teacher_id = ?");
        $stmt->execute([$club_id, $teacherId]);
        if (!$stmt->fetch()) {
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'คุณไม่ใช่ครูที่ปรึกษาของชุมนุมนี้']);
            exit;
        }
    }

    $createdBy = (int)($_SESSION['teacher_id'] ?? 0);

    if ($isDelete && $id > 0) {
        $pdo->prepare("DELETE FROM club_attendance WHERE session_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM club_activity_logs WHERE session_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM club_sessions WHERE id = ?")->execute([$id]);
        echo json_encode(['status' => 'success', 'message' => 'ลบคาบสำเร็จ']);
        exit;
    }

    if ($id > 0) {
        $stmt = $pdo->prepare("UPDATE club_sessions SET club_id=?, session_date=?, period=?, topic=?, description=?, status=? WHERE id=?");
        $stmt->execute([$club_id, $session_date, $period, $topic, $description, $status, $id]);
        echo json_encode(['status' => 'success', 'message' => 'แก้ไขคาบสำเร็จ', 'id' => $id]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO club_sessions (club_id, session_date, period, topic, description, status, created_by) VALUES (?,?,?,?,?,?,?)");
        $stmt->execute([$club_id, $session_date, $period, $topic, $description, $status, $createdBy ?: null]);
        $newId = (int)$pdo->lastInsertId();
        echo json_encode(['status' => 'success', 'message' => 'สร้างคาบสำเร็จ', 'id' => $newId]);
    }
} catch (Exception $e) {
    error_log('[save_session] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาด']);
}
