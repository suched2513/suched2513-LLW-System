<?php
/**
 * assembly/api/get_admin_summary.php
 * GET ?month=&grade=&classroom= — Admin dashboard summary
 */
header('Content-Type: application/json; charset=utf-8');
session_start();
require_once __DIR__ . '/../../config/database.php';

if (!isset($_SESSION['llw_role'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'กรุณาเข้าสู่ระบบ']);
    exit;
}
if (!in_array($_SESSION['llw_role'], ['super_admin', 'wfh_admin'])) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'คุณไม่มีสิทธิ์']);
    exit;
}

$month     = trim($_GET['month']     ?? 'all');
$grade     = trim($_GET['grade']     ?? 'all');
$classroom = trim($_GET['classroom'] ?? 'all');

try {
    $pdo = getPdo();

    $where  = ['1=1'];
    $params = [];

    if ($month !== 'all') {
        $where[]  = "DATE_FORMAT(a.date, '%m') = ?";
        $params[] = $month;
    }
    if ($grade !== 'all') {
        $where[] = "a.classroom REGEXP ?";
        $params[] = '^' . preg_quote($grade) . '/';
    }
    if ($classroom !== 'all') {
        $where[]  = "a.classroom = ?";
        $params[] = $classroom;
    }

    $whereStr = implode(' AND ', $where);

    $stmt = $pdo->prepare("
        SELECT
            a.classroom,
            c.teacher_name,
            COUNT(*) AS total_checks,
            SUM(a.status = 'ม') AS present_count,
            SUM(a.nail  = 'ถูก') AS nail_ok,
            SUM(a.hair  = 'ถูก') AS hair_ok,
            SUM(a.shirt = 'ถูก') AS shirt_ok,
            SUM(a.pants = 'ถูก') AS pants_ok,
            SUM(a.socks = 'ถูก') AS socks_ok,
            SUM(a.shoes = 'ถูก') AS shoes_ok,
            SUM(CASE WHEN a.note IS NOT NULL AND a.note != '' THEN 1 ELSE 0 END) AS note_count
        FROM assembly_attendance a
        LEFT JOIN assembly_classrooms c ON c.classroom = a.classroom
        WHERE $whereStr
        GROUP BY a.classroom, c.teacher_name
        ORDER BY a.classroom
    ");
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    $rooms = [];
    foreach ($rows as $r) {
        $tc          = (int)$r['total_checks'];
        $presentPct  = $tc > 0 ? round($r['present_count'] / $tc * 100) : 0;
        $uniformSum  = $r['nail_ok'] + $r['hair_ok'] + $r['shirt_ok'] + $r['pants_ok'] + $r['socks_ok'] + $r['shoes_ok'];
        $uniformChecks = $tc * 6;
        $uniformPct  = $uniformChecks > 0 ? round($uniformSum / $uniformChecks * 100) : 0;
        $rooms[] = [
            'classroom'  => $r['classroom'],
            'teacher'    => $r['teacher_name'] ?? '-',
            'presentPct' => $presentPct,
            'uniformPct' => $uniformPct,
            'noteCount'  => (int)$r['note_count'],
        ];
    }

    $totals = ['presentPct' => 0, 'uniformPct' => 0, 'noteCount' => 0, 'roomCount' => count($rooms)];
    if (count($rooms) > 0) {
        $totals['presentPct'] = round(array_sum(array_column($rooms, 'presentPct')) / count($rooms));
        $totals['uniformPct'] = round(array_sum(array_column($rooms, 'uniformPct')) / count($rooms));
        $totals['noteCount']  = array_sum(array_column($rooms, 'noteCount'));
    }

    echo json_encode(['status' => 'success', 'rooms' => $rooms, 'totals' => $totals]);
} catch (Exception $e) {
    error_log('[Assembly] get_admin_summary: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาด']);
}
