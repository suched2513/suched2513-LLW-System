<?php
/**
 * assembly/api/get_admin_detailed.php
 * GET ?month=&level=&classroom= — รายงานเชิงลึกรายวัน
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
$level     = trim($_GET['level']     ?? 'all');
$classroom = trim($_GET['classroom'] ?? 'all');

try {
    $pdo = getPdo();

    $where  = ['1=1'];
    $params = [];

    if ($month !== 'all') {
        $where[]  = "DATE_FORMAT(date, '%m') = ?";
        $params[] = $month;
    }
    if ($level !== 'all') {
        // level เช่น "ม.1" → classroom ขึ้นต้นด้วย "ม.1/"
        $where[]  = "classroom LIKE ?";
        $params[] = $level . '/%';
    }
    if ($classroom !== 'all') {
        $where[]  = "classroom = ?";
        $params[] = $classroom;
    }

    $whereStr = implode(' AND ', $where);

    $stmt = $pdo->prepare("
        SELECT
            date,
            classroom,
            SUM(status = 'ม') AS present,
            SUM(status = 'ข') AS absent,
            SUM(status = 'ล') AS `leave`,
            SUM(status = 'ด') AS skip,
            ROUND(AVG(
                (nail = 'ถูก') + (hair = 'ถูก') + (shirt = 'ถูก') +
                (pants= 'ถูก') + (socks= 'ถูก') + (shoes= 'ถูก')
            ) / 6 * 100) AS uniform_pct,
            GROUP_CONCAT(DISTINCT NULLIF(note,'') SEPARATOR '; ') AS notes
        FROM assembly_attendance
        WHERE $whereStr
        GROUP BY date, classroom
        ORDER BY date DESC, classroom
    ");
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    $result = array_map(fn($r) => [
        'date'       => $r['date'],
        'classroom'  => $r['classroom'],
        'present'    => (int)$r['present'],
        'absent'     => (int)$r['absent'],
        'leave'      => (int)$r['leave'],
        'skip'       => (int)$r['skip'],
        'uniformPct' => (int)($r['uniform_pct'] ?? 0),
        'note'       => $r['notes'] ?? '',
    ], $rows);

    echo json_encode(['status' => 'success', 'data' => $result]);
} catch (Exception $e) {
    error_log('[Assembly] get_admin_detailed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาด']);
}
