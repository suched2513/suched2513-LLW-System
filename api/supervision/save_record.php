<?php
// api/supervision/save_record.php
header('Content-Type: application/json; charset=utf-8');
session_start();
require_once '../../config/database.php';

if (!isset($_SESSION['llw_role'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'กรุณาเข้าสู่ระบบ']);
    exit;
}

// Role guard: Everyone can record, but restricted by logic below
if (!in_array($_SESSION['llw_role'], ['super_admin', 'wfh_admin', 'att_teacher', 'wfh_staff'])) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'คุณไม่มีสิทธิ์บันทึกการนิเทศ']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || empty($input['teacher_id']) || !isset($input['scores'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'ข้อมูลไม่ครบถ้วน']);
    exit;
}

try {
    $pdo = getPdo();

    // Check Evaluator Status
    $stmtC = $pdo->prepare("SELECT role, is_evaluator FROM llw_users WHERE user_id = ?");
    $stmtC->execute([$_SESSION['user_id']]);
    $currUser = $stmtC->fetch();

    $isAdmin = in_array($currUser['role'] ?? '', ['super_admin', 'wfh_admin']);
    $isEval  = ($currUser['is_evaluator'] ?? 0) == 1;

    // Security guard: If not admin AND not evaluator, teacher_id MUST be their own ID
    if (!$isAdmin && !$isEval && (int)$input['teacher_id'] !== (int)$_SESSION['user_id']) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'คุณไม่มีสิทธิ์บันทึกผลการนิเทศให้ผู้อื่น']);
        exit;
    }

    $pdo->beginTransaction();

    // Calculate score
    $scores = $input['scores'];
    $total = array_sum($scores);
    $avg = count($scores) > 0 ? $total / count($scores) : 0;
    
    $interpretation = "";
    if ($avg >= 4.50) $interpretation = "ดีเยี่ยม";
    elseif ($avg >= 3.50) $interpretation = "ดีมาก";
    elseif ($avg >= 2.50) $interpretation = "ดี";
    elseif ($avg >= 1.50) $interpretation = "พอใช้";
    else $interpretation = "ปรับปรุง";

    // Insert Record
    $stmt = $pdo->prepare("
        INSERT INTO sup_records (
            teacher_id, course_name, course_code, class_level, 
            observation_date, total_score, average_score, interpretation,
            findings, impressions, improvements, observer_name,
            observer_id, observer_position
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $input['teacher_id'],
        $input['course_name'],
        $input['course_code'],
        $input['class_level'],
        $input['observation_date'],
        $total,
        $avg,
        $interpretation,
        $input['findings'] ?? '',
        $input['impressions'] ?? '',
        $input['improvements'] ?? '',
        $input['observer_name'] ?? $_SESSION['fullname'],
        $_SESSION['user_id'],
        $input['observer_position'] ?? ''
    ]);
    
    $record_id = $pdo->lastInsertId();

    // Insert Scores
    $stmtScore = $pdo->prepare("INSERT INTO sup_scores (record_id, item_idx, score) VALUES (?, ?, ?)");
    foreach ($scores as $idx => $score) {
        $stmtScore->execute([$record_id, $idx, $score]);
    }

    $pdo->commit();
    echo json_encode(['status' => 'success', 'message' => 'บันทึกข้อมูลสำเร็จ', 'id' => $record_id]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาดในการบันทึกข้อมูล']);
    error_log($e->getMessage());
}
