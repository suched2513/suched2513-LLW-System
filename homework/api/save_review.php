<?php
header('Content-Type: application/json; charset=utf-8');
session_start();
require_once __DIR__ . '/../../config/database.php';

if (!isset($_SESSION['llw_role'])) { http_response_code(401); echo json_encode(['status'=>'error','message'=>'กรุณาเข้าสู่ระบบ']); exit; }
if ($_SESSION['llw_role'] !== 'super_admin') { http_response_code(403); echo json_encode(['status'=>'error','message'=>'ไม่มีสิทธิ์']); exit; }

$input   = json_decode(file_get_contents('php://input'), true);
$sub_id  = (int)($input['sub_id']  ?? 0);
$score   = (int)($input['score']   ?? 0);
$comment = trim($input['comment']  ?? '');

if (!$sub_id) { echo json_encode(['status'=>'error','message'=>'ข้อมูลไม่ถูกต้อง']); exit; }

try {
    $pdo = getPdo();

    // Validate score against assignment max
    $max = (int)$pdo->prepare("SELECT a.max_score FROM hw_submissions s JOIN hw_assignments a ON a.id=s.assignment_id WHERE s.id=? LIMIT 1")
               ->execute([$sub_id]) ? $pdo->prepare("SELECT a.max_score FROM hw_submissions s JOIN hw_assignments a ON a.id=s.assignment_id WHERE s.id=? LIMIT 1") : null;

    $stmt = $pdo->prepare("SELECT a.max_score FROM hw_submissions s JOIN hw_assignments a ON a.id=s.assignment_id WHERE s.id=? LIMIT 1");
    $stmt->execute([$sub_id]);
    $row = $stmt->fetch();
    if (!$row) { echo json_encode(['status'=>'error','message'=>'ไม่พบข้อมูล']); exit; }

    $score = max(0, min((int)$row['max_score'], $score));

    $pdo->prepare("UPDATE hw_submissions SET score=?, teacher_comment=?, reviewed_at=NOW() WHERE id=?")
        ->execute([$score, $comment ?: null, $sub_id]);

    echo json_encode(['status'=>'success']);
} catch (Exception $e) {
    error_log('[homework] save_review: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status'=>'error','message'=>'เกิดข้อผิดพลาด']);
}
