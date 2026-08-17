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
if ($_SESSION['llw_role'] !== 'super_admin') {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'คุณไม่มีสิทธิ์']);
    exit;
}

$monthFrom = trim($_GET['month_from'] ?? '');
$monthTo   = trim($_GET['month_to']   ?? '');
$month     = trim($_GET['month']      ?? 'all'); // legacy single-month param, still supported
$grade     = trim($_GET['grade']      ?? 'all');
$classroom = trim($_GET['classroom']  ?? 'all');

try {
    $pdo = getPdo();

    $where  = ['1=1'];
    $params = [];

    if ($monthFrom !== '' && $monthTo !== '') {
        $where[]  = "DATE_FORMAT(a.date, '%m') BETWEEN ? AND ?";
        $params[] = $monthFrom;
        $params[] = $monthTo;
    } elseif ($month !== 'all') {
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
            (SELECT GROUP_CONCAT(CONCAT(u2.firstname, ' ', u2.lastname) ORDER BY ca2.id SEPARATOR ' / ')
             FROM llw_class_advisors ca2
             JOIN llw_users u2 ON u2.user_id = ca2.user_id
             WHERE ca2.classroom = a.classroom) AS teacher_name,
            COUNT(*) AS total_checks,
            SUM(a.status = 'ม') AS present_count,
            SUM(a.nail  = 'ถูก') AS nail_ok,
            SUM(a.hair  = 'ถูก') AS hair_ok,
            SUM(a.shirt = 'ถูก') AS shirt_ok,
            SUM(a.pants = 'ถูก') AS pants_ok,
            SUM(a.socks = 'ถูก') AS socks_ok,
            SUM(a.shoes = 'ถูก') AS shoes_ok,
            SUM(a.status = 'ด') AS skip_count,
            SUM(CASE WHEN a.note IS NOT NULL AND a.note != '' THEN 1 ELSE 0 END) AS note_count
        FROM assembly_attendance a
        WHERE $whereStr
        GROUP BY a.classroom
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
            'skipCount'  => (int)$r['skip_count'],
            'noteCount'  => (int)$r['note_count'],
        ];
    }

    $totals = ['presentPct' => 0, 'uniformPct' => 0, 'skipCount' => 0, 'noteCount' => 0, 'roomCount' => count($rooms)];
    if (count($rooms) > 0) {
        $totals['presentPct'] = round(array_sum(array_column($rooms, 'presentPct')) / count($rooms));
        $totals['uniformPct'] = round(array_sum(array_column($rooms, 'uniformPct')) / count($rooms));
        $totals['skipCount']  = array_sum(array_column($rooms, 'skipCount'));
        $totals['noteCount']  = array_sum(array_column($rooms, 'noteCount'));
    }

    // Per-student breakdown — only when one specific classroom is picked, to
    // avoid dumping every student in the school onto one response.
    $students = [];
    if ($classroom !== 'all') {
        $stmt2 = $pdo->prepare("
            SELECT
                a.student_id,
                COALESCE(s.name, a.student_id) AS name,
                SUM(a.status = 'ม') AS present_count,
                SUM(a.status = 'ข') AS absent_count,
                SUM(a.status = 'ล') AS leave_count,
                SUM(a.status = 'ด') AS skip_count
            FROM assembly_attendance a
            LEFT JOIN assembly_students s ON s.student_id = a.student_id
            WHERE $whereStr
            GROUP BY a.student_id, s.name
            ORDER BY a.student_id
        ");
        $stmt2->execute($params);
        foreach ($stmt2->fetchAll() as $r) {
            $students[] = [
                'studentId' => $r['student_id'],
                'name'      => $r['name'],
                'present'   => (int)$r['present_count'],
                'absent'    => (int)$r['absent_count'],
                'leave'     => (int)$r['leave_count'],
                'skip'      => (int)$r['skip_count'],
            ];
        }
    }

    echo json_encode(['status' => 'success', 'rooms' => $rooms, 'totals' => $totals, 'students' => $students]);
} catch (Exception $e) {
    error_log('[Assembly] get_admin_summary: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาด']);
}
