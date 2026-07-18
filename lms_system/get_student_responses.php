<?php
header('Content-Type: application/json; charset=utf-8');
session_start();
require_once __DIR__ . '/../config.php';

// Auth Guard
if (!isset($_SESSION['llw_role'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'กรุณาเข้าสู่ระบบ']);
    exit;
}
if (!in_array($_SESSION['llw_role'], ['super_admin', 'att_teacher'])) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'คุณไม่มีสิทธิ์เข้าถึงส่วนนี้']);
    exit;
}

$pdo = getPdo();
$quiz_id = (int)($_GET['quiz_id'] ?? 0);
$student_id = (int)($_GET['student_id'] ?? 0);

if (!$quiz_id || !$student_id) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'พารามิเตอร์ไม่ครบถ้วน']);
    exit;
}

try {
    // 1. Fetch latest attempt of the student
    $stmt = $pdo->prepare("
        SELECT id FROM lms_quiz_attempts 
        WHERE quiz_id = ? AND student_id = ? 
        ORDER BY id DESC LIMIT 1
    ");
    $stmt->execute([$quiz_id, $student_id]);
    $attempt_id = $stmt->fetchColumn();

    // 2. Fetch all questions for this quiz
    $stmt = $pdo->prepare("
        SELECT id, question_text, points 
        FROM lms_questions 
        WHERE quiz_id = ? 
        ORDER BY id ASC
    ");
    $stmt->execute([$quiz_id]);
    $questions = $stmt->fetchAll();

    $data = [];
    foreach ($questions as $q) {
        // Fetch choices for each question
        $c_stmt = $pdo->prepare("
            SELECT id, choice_text, is_correct 
            FROM lms_choices 
            WHERE question_id = ? 
            ORDER BY id ASC
        ");
        $c_stmt->execute([$q['id']]);
        $choices = $c_stmt->fetchAll();

        // Get student's answer for this question
        $selected_choice_id = null;
        $is_correct = false;
        if ($attempt_id) {
            $a_stmt = $pdo->prepare("
                SELECT choice_id, is_correct 
                FROM lms_quiz_answers 
                WHERE attempt_id = ? AND question_id = ? 
                LIMIT 1
            ");
            $a_stmt->execute([$attempt_id, $q['id']]);
            $ans = $a_stmt->fetch();
            if ($ans) {
                $selected_choice_id = $ans['choice_id'];
                $is_correct = (bool)$ans['is_correct'];
            }
        }

        $data[] = [
            'id' => $q['id'],
            'question_text' => $q['question_text'],
            'points' => $q['points'],
            'choices' => $choices,
            'selected_choice_id' => $selected_choice_id,
            'is_correct' => $is_correct
        ];
    }

    echo json_encode(['status' => 'success', 'data' => $data]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาดในการโหลดผลสอบ']);
    error_log($e->getMessage());
}
