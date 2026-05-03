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

    // att_teacher sees only own clubs
    if ($_SESSION['llw_role'] === 'att_teacher' && isset($_SESSION['teacher_id'])) {
        $where[] = 'cg.teacher_id = ?';
        $params[] = (int)$_SESSION['teacher_id'];
    }

    $whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $sql = "SELECT cg.id, cg.name, cg.description, cg.objectives,
                   cg.teacher_id, COALESCE(t.name,'') AS teacher_name,
                   cg.room, cg.max_capacity, cg.semester, cg.year,
                   cg.status, cg.pass_threshold, cg.created_at,
                   (SELECT COUNT(*) FROM club_registrations cr
                    WHERE cr.club_id = cg.id AND cr.semester = cg.semester AND cr.year = cg.year) AS registered_count
            FROM club_groups cg
            LEFT JOIN att_teachers t ON t.id = cg.teacher_id
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
