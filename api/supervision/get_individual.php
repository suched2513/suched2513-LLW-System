<?php
// api/supervision/get_individual.php
header('Content-Type: application/json; charset=utf-8');
session_start();
require_once '../../config/database.php';

if (!isset($_SESSION['llw_role'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'กรุณาเข้าสู่ระบบ']);
    exit;
}

$teacher_id = $_GET['teacher_id'] ?? null;

try {
    $pdo = getPdo();
    
    // Check Evaluator Status
    $stmtC = $pdo->prepare("SELECT role, is_evaluator FROM llw_users WHERE user_id = ?");
    $stmtC->execute([$_SESSION['user_id']]);
    $currUser = $stmtC->fetch();

    $isAdmin = in_array($currUser['role'] ?? '', ['super_admin', 'wfh_admin']);
    $isEval  = ($currUser['is_evaluator'] ?? 0) == 1;

    if (!$isAdmin && !$isEval) {
        // Standard user can ONLY see their own data
        $teacher_id = $_SESSION['user_id'];
    }

    if (!$teacher_id) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'ไม่พบรหัสครู']);
        exit;
    }

    // 1. Get Teacher Profile
    $stmtUser = $pdo->prepare("SELECT user_id, firstname, lastname, position, academic_status, subject_group FROM llw_users WHERE user_id = ?");
    $stmtUser->execute([$teacher_id]);
    $user = $stmtUser->fetch();
    
    if (!$user) {
        echo json_encode(['status' => 'error', 'message' => 'ไม่พบข้อมูลครู']);
        exit;
    }

    // 2. Get All Records for this teacher
    $stmtRecords = $pdo->prepare("SELECT * FROM sup_records WHERE teacher_id = ? ORDER BY observation_date DESC");
    $stmtRecords->execute([$teacher_id]);
    $records = $stmtRecords->fetchAll();

    // 3. Get Scores Distribution (Radar Data) - Average of all sessions for this teacher
    $stmtScores = $pdo->prepare("
        SELECT s.item_idx, AVG(s.score) as avg_score
        FROM sup_scores s
        JOIN sup_records r ON s.record_id = r.id
        WHERE r.teacher_id = ?
        GROUP BY s.item_idx
    ");
    $stmtScores->execute([$teacher_id]);
    $scoresRaw = $stmtScores->fetchAll();
    
    $itemScores = array_fill(0, 27, 0);
    foreach ($scoresRaw as $s) {
        $itemScores[$s['item_idx']] = (float)$s['avg_score'];
    }

    // Categories mapping (Indices)
    // Group 1: 0-12 (13 items)
    // Group 2: 13-17 (5 items)
    // Group 3: 18-22 (5 items)
    // Group 4: 23-26 (4 items)
    $radarData = [
        'group1' => count($itemScores) > 0 ? array_sum(array_slice($itemScores, 0, 13)) / 13 : 0,
        'group2' => count($itemScores) > 0 ? array_sum(array_slice($itemScores, 13, 5)) / 5 : 0,
        'group3' => count($itemScores) > 0 ? array_sum(array_slice($itemScores, 18, 5)) / 5 : 0,
        'group4' => count($itemScores) > 0 ? array_sum(array_slice($itemScores, 23, 4)) / 4 : 0,
    ];

    echo json_encode([
        'status' => 'success',
        'teacher' => $user,
        'records' => $records,
        'radar' => $radarData
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาดในการดึงข้อมูล']);
    error_log($e->getMessage());
}
