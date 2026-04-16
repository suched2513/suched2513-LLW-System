<?php
/**
 * assembly/api/get_daily_summary.php
 * GET ?date= — รายงานเช้า-เย็นรายวัน (all rooms)
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

$date = trim($_GET['date'] ?? date('Y-m-d'));

try {
    $pdo = getPdo();

    // ดึงทุกห้อง
    $roomStmt = $pdo->query("
        SELECT c.classroom, c.teacher_name
        FROM assembly_classrooms c
        ORDER BY c.classroom
    ");
    $rooms = $roomStmt->fetchAll();
    $roomMap = [];
    foreach ($rooms as $r) {
        $roomMap[$r['classroom']] = ['advisor' => $r['teacher_name'] ?? '-'];
    }

    // เช้า
    $morningStmt = $pdo->prepare("
        SELECT classroom,
               SUM(status = 'ม') AS present,
               SUM(status = 'ข') AS absent,
               SUM(status = 'ด') AS skip
        FROM assembly_attendance
        WHERE date = ?
        GROUP BY classroom
    ");
    $morningStmt->execute([$date]);
    $morningData = [];
    foreach ($morningStmt->fetchAll() as $r) {
        $morningData[$r['classroom']] = [
            'present' => (int)$r['present'],
            'absent'  => (int)$r['absent'],
            'skip'    => (int)$r['skip'],
        ];
    }

    // เย็น
    $eveningStmt = $pdo->prepare("
        SELECT classroom,
               SUM(status = 'มา')   AS present,
               SUM(status = 'ไม่มา') AS absent,
               SUM(status = 'โดด')  AS skip
        FROM assembly_checkout
        WHERE date = ?
        GROUP BY classroom
    ");
    $eveningStmt->execute([$date]);
    $eveningData = [];
    foreach ($eveningStmt->fetchAll() as $r) {
        $eveningData[$r['classroom']] = [
            'present' => (int)$r['present'],
            'absent'  => (int)$r['absent'],
            'skip'    => (int)$r['skip'],
        ];
    }

    $result = [];
    foreach ($roomMap as $classroom => $info) {
        $result[] = [
            'room'    => $classroom,
            'advisor' => $info['advisor'],
            'morning' => $morningData[$classroom] ?? ['present' => 0, 'absent' => 0, 'skip' => 0],
            'evening' => $eveningData[$classroom] ?? ['present' => 0, 'absent' => 0, 'skip' => 0],
        ];
    }

    // ห้องที่เช็คแล้วแต่ไม่อยู่ใน classrooms table
    $checkedRooms = array_unique(array_merge(array_keys($morningData), array_keys($eveningData)));
    foreach ($checkedRooms as $cr) {
        if (!isset($roomMap[$cr])) {
            $result[] = [
                'room'    => $cr,
                'advisor' => '-',
                'morning' => $morningData[$cr] ?? ['present' => 0, 'absent' => 0, 'skip' => 0],
                'evening' => $eveningData[$cr] ?? ['present' => 0, 'absent' => 0, 'skip' => 0],
            ];
        }
    }

    usort($result, fn($a, $b) => strnatcmp($a['room'], $b['room']));

    echo json_encode(['status' => 'success', 'data' => $result]);
} catch (Exception $e) {
    error_log('[Assembly] get_daily_summary: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาด']);
}
