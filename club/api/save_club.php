<?php
header('Content-Type: application/json; charset=utf-8');
session_start();
require_once __DIR__ . '/../../config/database.php';

if (!isset($_SESSION['llw_role'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'กรุณาเข้าสู่ระบบ']);
    exit;
}
if ($_SESSION['llw_role'] !== 'super_admin') {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'คุณไม่มีสิทธิ์']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

$id            = isset($input['id']) ? (int)$input['id'] : 0;
$name          = trim($input['name'] ?? '');
$description   = trim($input['description'] ?? '');
$objectives    = trim($input['objectives'] ?? '');
$teacher_id    = isset($input['teacher_id']) && $input['teacher_id'] !== '' ? (int)$input['teacher_id'] : null;
$room          = trim($input['room'] ?? '');
$max_capacity  = isset($input['max_capacity']) ? (int)$input['max_capacity'] : 30;
$semester      = isset($input['semester']) ? (int)$input['semester'] : 1;
$year          = isset($input['year']) ? (int)$input['year'] : (int)date('Y');
$status        = $input['status'] ?? 'draft';
$pass_threshold = isset($input['pass_threshold']) ? (int)$input['pass_threshold'] : 80;

if ($name === '') {
    echo json_encode(['status' => 'error', 'message' => 'กรุณากรอกชื่อชุมนุม']);
    exit;
}
if (!in_array($status, ['draft', 'open', 'closed', 'archived'])) {
    echo json_encode(['status' => 'error', 'message' => 'สถานะไม่ถูกต้อง']);
    exit;
}

try {
    $pdo = getPdo();

    if ($id > 0) {
        $stmt = $pdo->prepare("UPDATE club_groups SET name=?, description=?, objectives=?, teacher_id=?, room=?, max_capacity=?, semester=?, year=?, status=?, pass_threshold=? WHERE id=?");
        $stmt->execute([$name, $description, $objectives, $teacher_id, $room, $max_capacity, $semester, $year, $status, $pass_threshold, $id]);
        echo json_encode(['status' => 'success', 'message' => 'แก้ไขข้อมูลชุมนุมสำเร็จ', 'id' => $id]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO club_groups (name, description, objectives, teacher_id, room, max_capacity, semester, year, status, pass_threshold) VALUES (?,?,?,?,?,?,?,?,?,?)");
        $stmt->execute([$name, $description, $objectives, $teacher_id, $room, $max_capacity, $semester, $year, $status, $pass_threshold]);
        $newId = (int)$pdo->lastInsertId();
        echo json_encode(['status' => 'success', 'message' => 'สร้างชุมนุมสำเร็จ', 'id' => $newId]);
    }
} catch (Exception $e) {
    error_log('[save_club] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาด']);
}
