<?php
header('Content-Type: application/json; charset=utf-8');
session_start();
require_once __DIR__ . '/../../config/database.php';

if (empty($_SESSION['is_student'])) {
    http_response_code(401);
    echo json_encode(['status'=>'error','message'=>'กรุณาเข้าสู่ระบบ']);
    exit;
}

$input         = json_decode(file_get_contents('php://input'), true);
$assignment_id = (int)($input['assignment_id'] ?? 0);
$content       = trim($input['content']        ?? '');
$student_uid   = (int)$_SESSION['student_uid'];
$student_code  = $_SESSION['student_code']  ?? '';
$student_name  = $_SESSION['student_name']  ?? '';
$classroom     = $_SESSION['student_class'] ?? '';

if (!$assignment_id || $content === '') {
    echo json_encode(['status'=>'error','message'=>'กรุณากรอกเนื้อหางาน']); exit;
}

try {
    $pdo = getPdo();

    // Check assignment exists and student's classroom matches
    $aStmt = $pdo->prepare("SELECT * FROM hw_assignments WHERE id=? AND status='published' LIMIT 1");
    $aStmt->execute([$assignment_id]);
    $assignment = $aStmt->fetch();
    if (!$assignment) { echo json_encode(['status'=>'error','message'=>'ไม่พบงาน']); exit; }

    // Verify classroom
    $validClassrooms = array_map('trim', explode(',', $assignment['classroom']));
    if (!in_array($classroom, $validClassrooms)) {
        echo json_encode(['status'=>'error','message'=>'งานนี้ไม่ได้มอบหมายให้ห้องของคุณ']); exit;
    }

    // Determine late status
    $status = strtotime($assignment['due_date']) < time() ? 'late' : 'submitted';

    // Upsert submission
    $pdo->prepare("
        INSERT INTO hw_submissions (assignment_id, student_uid, student_code, student_name, classroom, content, submitted_at, status)
        VALUES (?, ?, ?, ?, ?, ?, NOW(), ?)
        ON DUPLICATE KEY UPDATE content=VALUES(content), submitted_at=NOW(), status=VALUES(status), score=NULL, teacher_comment=NULL, reviewed_at=NULL
    ")->execute([$assignment_id, $student_uid, $student_code, $student_name, $classroom, $content, $status]);

    echo json_encode(['status'=>'success','late'=>($status==='late')]);
} catch (Exception $e) {
    error_log('[homework] save_submission: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status'=>'error','message'=>'เกิดข้อผิดพลาด']);
}
