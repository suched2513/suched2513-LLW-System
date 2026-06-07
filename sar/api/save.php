<?php
header('Content-Type: application/json; charset=utf-8');
session_start();
require_once __DIR__ . '/../../config.php';

if (!isset($_SESSION['llw_role'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'กรุณาเข้าสู่ระบบ']);
    exit;
}
if (!in_array($_SESSION['llw_role'], ['super_admin', 'wfh_admin', 'wfh_staff', 'att_teacher'])) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'ไม่มีสิทธิ์']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'ข้อมูลไม่ถูกต้อง']);
    exit;
}

$sarId   = (int)($input['id'] ?? 0);
$submit  = !empty($input['submit']);
$data    = is_array($input['data'] ?? null) ? $input['data'] : [];
$userId  = (int)$_SESSION['user_id'];
$isAdmin = in_array($_SESSION['llw_role'], ['super_admin', 'wfh_admin']);

$fname       = trim($data['fname'] ?? '');
$lname       = trim($data['lname'] ?? '');
$teacherName = trim($fname . ' ' . $lname);
$year        = trim($data['syear'] ?? '');
$semester    = (int)($data['sem'] ?? 1);
$status      = $submit ? 'submitted' : 'draft';
$formJson    = json_encode($data, JSON_UNESCAPED_UNICODE);

try {
    $pdo = getPdo();
    $pdo->beginTransaction();

    if ($sarId) {
        $chk = $isAdmin
            ? $pdo->prepare("SELECT id FROM sar_reports WHERE id = ?")
            : $pdo->prepare("SELECT id FROM sar_reports WHERE id = ? AND teacher_id = ?");
        $isAdmin ? $chk->execute([$sarId]) : $chk->execute([$sarId, $userId]);
        if (!$chk->fetch()) {
            $pdo->rollBack();
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'ไม่พบรายงาน']);
            exit;
        }
        $pdo->prepare(
            "UPDATE sar_reports SET teacher_name=?, year=?, semester=?, form_data=?, status=? WHERE id=?"
        )->execute([$teacherName, $year, $semester, $formJson, $status, $sarId]);
        $pdo->commit();
        echo json_encode(['status' => 'success', 'id' => $sarId]);
    } else {
        $pdo->prepare(
            "INSERT INTO sar_reports (teacher_id, teacher_name, year, semester, form_data, status) VALUES (?,?,?,?,?,?)"
        )->execute([$userId, $teacherName, $year, $semester, $formJson, $status]);
        $newId = (int)$pdo->lastInsertId();
        $pdo->commit();
        echo json_encode(['status' => 'success', 'id' => $newId]);
    }
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    error_log($e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาด']);
}
