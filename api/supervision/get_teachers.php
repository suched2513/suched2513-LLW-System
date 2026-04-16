<?php
// api/supervision/get_teachers.php
header('Content-Type: application/json; charset=utf-8');
session_start();
require_once '../../config/database.php';

if (!isset($_SESSION['llw_role'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'กรุณาเข้าสู่ระบบ']);
    exit;
}

try {
    $pdo = getPdo();

    // Check if the current user is an evaluator
    $stmtC = $pdo->prepare("SELECT role, is_evaluator FROM llw_users WHERE user_id = ?");
    $stmtC->execute([$_SESSION['user_id']]);
    $currUser = $stmtC->fetch();

    $isAdmin = in_array($currUser['role'] ?? '', ['super_admin', 'wfh_admin']);
    $isEval  = ($currUser['is_evaluator'] ?? 0) == 1;

    $teachers = [];
    if ($isAdmin || $isEval) {
        // Admins and Evaluators see everyone
        $stmt = $pdo->prepare("
            SELECT 
                user_id as id,
                CONCAT(firstname, ' ', lastname) as name,
                position,
                academic_status,
                subject_group,
                is_evaluator
            FROM llw_users
            WHERE status = 'active'
              AND role IN ('att_teacher', 'super_admin', 'wfh_admin', 'wfh_staff')
            ORDER BY firstname ASC
        ");
        $stmt->execute();
        $teachers = $stmt->fetchAll();
    } else {
        // Standard teachers see ONLY themselves
        $stmt = $pdo->prepare("
            SELECT 
                user_id as id,
                CONCAT(firstname, ' ', lastname) as name,
                position,
                academic_status,
                subject_group,
                is_evaluator
            FROM llw_users
            WHERE user_id = ?
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $teachers = $stmt->fetchAll();
    }


    echo json_encode(['status' => 'success', 'data' => $teachers]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาดในการดึงข้อมูลครู']);
    error_log($e->getMessage());
}
