<?php
header('Content-Type: application/json; charset=utf-8');
session_start();
require_once __DIR__ . '/../../config/database.php';

if (!isset($_SESSION['llw_role'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'กรุณาเข้าสู่ระบบ']);
    exit;
}

try {
    $pdo = getPdo();

    $semester = isset($_GET['semester']) ? (int)$_GET['semester'] : 0;
    $year     = isset($_GET['year'])     ? (int)$_GET['year']     : 0;
    $status   = $_GET['status'] ?? '';

    // If no filter, use active semester settings
    if ($semester === 0 || $year === 0) {
        $settRow = $pdo->query("SELECT semester, year FROM club_settings WHERE is_active = 1 LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        if ($settRow) {
            if ($semester === 0) $semester = (int)$settRow['semester'];
            if ($year === 0) $year = (int)$settRow['year'];
        }
    }

    $where = [];
    $params = [];

    if ($semester > 0) { $where[] = 'cg.semester = ?'; $params[] = $semester; }
    if ($year > 0)     { $where[] = 'cg.year = ?';     $params[] = $year; }
    if ($status !== '') { $where[] = 'cg.status = ?';  $params[] = $status; }

    // att_teacher sees only clubs where they are one of the 3 advisors
    if ($_SESSION['llw_role'] === 'att_teacher' && isset($_SESSION['teacher_id'])) {
        $tid = (int)$_SESSION['teacher_id'];
        $where[] = '(cg.teacher_id = ? OR cg.teacher_id_2 = ? OR cg.teacher_id_3 = ?)';
        $params[] = $tid;
        $params[] = $tid;
        $params[] = $tid;
    }

    $whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $sql = "SELECT cg.id, cg.name, cg.description, cg.objectives,
                   cg.teacher_id, cg.teacher_id_2, cg.teacher_id_3,
                   COALESCE(t1.name,'') AS teacher_name,
                   COALESCE(t2.name,'') AS teacher_name_2,
                   COALESCE(t3.name,'') AS teacher_name_3,
                   cg.room, cg.max_capacity, cg.semester, cg.year,
                   cg.status, cg.pass_threshold, cg.created_at,
                   (SELECT COUNT(*) FROM club_registrations cr
                    JOIN att_students s ON s.student_id = cr.student_id
                    WHERE cr.club_id = cg.id AND cr.semester = cg.semester AND cr.year = cg.year AND s.status = 'active') AS registered_count
            FROM club_groups cg
            LEFT JOIN att_teachers t1 ON t1.id = cg.teacher_id
            LEFT JOIN att_teachers t2 ON t2.id = cg.teacher_id_2
            LEFT JOIN att_teachers t3 ON t3.id = cg.teacher_id_3
            $whereSQL
            ORDER BY cg.year DESC, cg.semester DESC, cg.name ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $clubs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['status' => 'success', 'data' => $clubs]);
} catch (Exception $e) {
    error_log('[clubs_list] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาด']);
}
