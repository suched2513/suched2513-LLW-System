<?php
header('Content-Type: application/json; charset=utf-8');
session_start();
require_once __DIR__ . '/../../config/database.php';

if (!isset($_SESSION['llw_role'])) { http_response_code(401); echo json_encode(['status'=>'error','message'=>'กรุณาเข้าสู่ระบบ']); exit; }
if ($_SESSION['llw_role'] !== 'super_admin') { http_response_code(403); echo json_encode(['status'=>'error','message'=>'ไม่มีสิทธิ์']); exit; }

$input = json_decode(file_get_contents('php://input'), true);

$title       = trim($input['title']       ?? '');
$subject     = trim($input['subject']     ?? '');
$description = trim($input['description'] ?? '');
$due_date    = trim($input['due_date']    ?? '');
$max_score   = max(1, min(100, (int)($input['max_score'] ?? 10)));
$classrooms  = $input['classrooms'] ?? [];
$teacher_id  = (int)($_SESSION['user_id'] ?? 0);

if ($title === '' || empty($classrooms) || $due_date === '') {
    echo json_encode(['status'=>'error','message'=>'กรอกข้อมูลไม่ครบ']); exit;
}

try {
    $pdo = getPdo();
    $stmt = $pdo->prepare("
        INSERT INTO hw_assignments (title, subject, description, classroom, due_date, max_score, teacher_id, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, 'published')
    ");

    $count = 0;
    foreach ($classrooms as $cls) {
        $cls = trim($cls);
        if ($cls === '') continue;
        $stmt->execute([$title, $subject, $description, $cls, $due_date, $max_score, $teacher_id]);
        $count++;
    }

    echo json_encode(['status'=>'success','count'=>$count]);
} catch (Exception $e) {
    error_log('[homework] save_assignment: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status'=>'error','message'=>'เกิดข้อผิดพลาด']);
}
