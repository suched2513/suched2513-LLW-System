<?php
header('Content-Type: application/json; charset=utf-8');
session_start();
require_once __DIR__ . '/../../config/database.php';

if (!isset($_SESSION['llw_role'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'กรุณาเข้าสู่ระบบ']);
    exit;
}
$allowedRoles = ['super_admin', 'club_admin', 'att_teacher', 'wfh_admin', 'wfh_staff', 'finance_head', 'procurement_head', 'deputy_director', 'director'];
if (!in_array($userRole, $allowedRoles, true)) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'คุณไม่มีสิทธิ์']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

$id            = isset($input['id']) ? (int)$input['id'] : 0;
$name          = trim($input['name'] ?? '');
$description   = trim($input['description'] ?? '');
$objectives    = trim($input['objectives'] ?? '');
$teacher_id    = isset($input['teacher_id'])   && $input['teacher_id']   !== '' ? (int)$input['teacher_id']   : null;
$teacher_id_2  = isset($input['teacher_id_2']) && $input['teacher_id_2'] !== '' ? (int)$input['teacher_id_2'] : null;
$teacher_id_3  = isset($input['teacher_id_3']) && $input['teacher_id_3'] !== '' ? (int)$input['teacher_id_3'] : null;

// att_teacher: force slot 1 to their own teacher_id and verify edit permission
if ($userRole === 'att_teacher') {
    $myTeacherId = isset($_SESSION['teacher_id']) ? (int)$_SESSION['teacher_id'] : 0;
    $teacher_id  = $myTeacherId ?: $teacher_id;
    if ($id > 0 && $myTeacherId > 0) {
        // Verify they are an advisor of this club
        try {
            $pdo = getPdo();
            $chk = $pdo->prepare("SELECT teacher_id, teacher_id_2, teacher_id_3 FROM club_groups WHERE id = ?");
            $chk->execute([$id]);
            $row = $chk->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                http_response_code(404);
                echo json_encode(['status' => 'error', 'message' => 'ไม่พบชุมนุม']);
                exit;
            }
            $advisorIds = array_filter([(int)($row['teacher_id'] ?? 0), (int)($row['teacher_id_2'] ?? 0), (int)($row['teacher_id_3'] ?? 0)]);
            if (!in_array($myTeacherId, $advisorIds, true)) {
                http_response_code(403);
                echo json_encode(['status' => 'error', 'message' => 'คุณไม่มีสิทธิ์แก้ไขชุมนุมนี้']);
                exit;
            }
        } catch (Exception $e) {
            error_log('[save_club permission] ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาด']);
            exit;
        }
    }
}

$room          = trim($input['room'] ?? '');
$max_capacity  = isset($input['max_capacity']) ? (int)$input['max_capacity'] : 30;
$semester      = isset($input['semester']) ? (int)$input['semester'] : 1;
$year          = isset($input['year']) ? (int)$input['year'] : (int)date('Y');
$status        = $input['status'] ?? 'draft';
$pass_threshold = isset($input['pass_threshold']) ? (int)$input['pass_threshold'] : 80;

// ─── TEACHER RESTRICTIONS ───
if ($userRole === 'att_teacher') {
    // 1. Block creation
    if ($id === 0) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'คุณไม่มีสิทธิ์สร้างชุมนุมใหม่']);
        exit;
    }
    
    // 2. Lock protected fields by fetching current values
    try {
        $pdo = getPdo();
        $stmt = $pdo->prepare("SELECT * FROM club_groups WHERE id = ?");
        $stmt->execute([$id]);
        $curr = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($curr) {
            $name           = $curr['name'];
            $room           = $curr['room'];
            $max_capacity   = $curr['max_capacity'];
            $semester       = $curr['semester'];
            $year           = $curr['year'];
            $status         = $curr['status'];
            $pass_threshold = $curr['pass_threshold'];
            $teacher_id_2   = $curr['teacher_id_2'];
            $teacher_id_3   = $curr['teacher_id_3'];
        }
    } catch (Exception $e) { /* Fallback to input if error */ }
}

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
        $stmt = $pdo->prepare("UPDATE club_groups SET name=?, description=?, objectives=?, teacher_id=?, teacher_id_2=?, teacher_id_3=?, room=?, max_capacity=?, semester=?, year=?, status=?, pass_threshold=? WHERE id=?");
        $stmt->execute([$name, $description, $objectives, $teacher_id, $teacher_id_2, $teacher_id_3, $room, $max_capacity, $semester, $year, $status, $pass_threshold, $id]);
        echo json_encode(['status' => 'success', 'message' => 'แก้ไขข้อมูลชุมนุมสำเร็จ', 'id' => $id]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO club_groups (name, description, objectives, teacher_id, teacher_id_2, teacher_id_3, room, max_capacity, semester, year, status, pass_threshold) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)");
        $stmt->execute([$name, $description, $objectives, $teacher_id, $teacher_id_2, $teacher_id_3, $room, $max_capacity, $semester, $year, $status, $pass_threshold]);
        $newId = (int)$pdo->lastInsertId();
        echo json_encode(['status' => 'success', 'message' => 'สร้างชุมนุมสำเร็จ', 'id' => $newId]);
    }
} catch (Exception $e) {
    error_log('[save_club] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาด']);
}
