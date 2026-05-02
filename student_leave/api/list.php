<?php
header('Content-Type: application/json; charset=utf-8');
session_start();
require_once __DIR__ . '/../../config/database.php';

$context = $_GET['context'] ?? 'student';
$isStudent = isset($_SESSION['is_student']) && $_SESSION['is_student'] === true;
$isStaff   = isset($_SESSION['llw_role']);

// Auth check
if ($context === 'student') {
    if (!$isStudent) {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'กรุณาเข้าสู่ระบบ']);
        exit;
    }
} else {
    // teacher context
    if (!$isStaff) {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'กรุณาเข้าสู่ระบบ']);
        exit;
    }
    $allowed_roles = ['att_teacher', 'super_admin', 'wfh_admin'];
    if (!in_array($_SESSION['llw_role'], $allowed_roles, true)) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'คุณไม่มีสิทธิ์']);
        exit;
    }
}

// Whitelist status filter
$status_filter = $_GET['status'] ?? 'all';
$allowed_statuses = ['all', 'pending', 'approved', 'rejected'];
if (!in_array($status_filter, $allowed_statuses, true)) {
    $status_filter = 'all';
}

try {
    $pdo = getPdo();

    if ($context === 'student') {
        $student_code = $_SESSION['student_code'] ?? '';

        $sql = "
            SELECT r.id, r.student_id, r.leave_type, r.date_from, r.date_to, r.days,
                   r.reason, r.parent_name, r.parent_phone, r.status,
                   r.teacher_note, r.approved_at, r.created_at,
                   s.name AS student_name, s.classroom
            FROM stl_requests r
            LEFT JOIN att_students s ON s.student_id = r.student_id
            WHERE r.student_id = ?
        ";
        $params = [$student_code];

        if ($status_filter !== 'all') {
            $sql .= " AND r.status = ?";
            $params[] = $status_filter;
        }

        $sql .= " ORDER BY r.created_at DESC LIMIT 100";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    } else {
        // Teacher context
        $userRole = $_SESSION['llw_role'];
        $teacher_id = isset($_SESSION['teacher_id']) ? (int)$_SESSION['teacher_id'] : 0;

        if (in_array($userRole, ['super_admin', 'wfh_admin'], true)) {
            // All requests
            $sql = "
                SELECT r.id, r.student_id, r.leave_type, r.date_from, r.date_to, r.days,
                       r.reason, r.parent_name, r.parent_phone, r.status,
                       r.teacher_note, r.approved_at, r.created_at,
                       s.name AS student_name, s.classroom
                FROM stl_requests r
                LEFT JOIN att_students s ON s.student_id = r.student_id
                WHERE 1=1
            ";
            $params = [];
        } else {
            // att_teacher: only classrooms where this teacher teaches
            $sql = "
                SELECT r.id, r.student_id, r.leave_type, r.date_from, r.date_to, r.days,
                       r.reason, r.parent_name, r.parent_phone, r.status,
                       r.teacher_note, r.approved_at, r.created_at,
                       s.name AS student_name, s.classroom
                FROM stl_requests r
                LEFT JOIN att_students s ON s.student_id = r.student_id
                WHERE s.classroom IN (
                    SELECT DISTINCT classroom
                    FROM att_subjects
                    WHERE teacher_id = ?
                )
            ";
            $params = [$teacher_id];
        }

        // Classroom filter (optional)
        $classroom_filter = trim($_GET['classroom'] ?? '');
        if ($classroom_filter !== '') {
            $sql .= " AND s.classroom = ?";
            $params[] = $classroom_filter;
        }

        if ($status_filter !== 'all') {
            $sql .= " AND r.status = ?";
            $params[] = $status_filter;
        }

        $sql .= " ORDER BY r.created_at DESC LIMIT 500";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    echo json_encode(['status' => 'success', 'data' => $rows]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาด']);
    error_log('[stl list] ' . $e->getMessage());
}
