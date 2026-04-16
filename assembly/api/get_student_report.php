<?php
/**
 * assembly/api/get_student_report.php
 * GET ?student_id=&month= — รายงานรายบุคคล
 */
header('Content-Type: application/json; charset=utf-8');
session_start();
require_once __DIR__ . '/../../config/database.php';

if (!isset($_SESSION['llw_role'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'กรุณาเข้าสู่ระบบ']);
    exit;
}

$studentId = trim($_GET['student_id'] ?? '');
$month     = trim($_GET['month']      ?? 'all');

if ($studentId === '') {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'กรุณาระบุรหัสนักเรียน']);
    exit;
}

try {
    $pdo = getPdo();

    // ข้อมูลนักเรียน
    $sStmt = $pdo->prepare("
        SELECT s.student_id, s.name, s.classroom, c.teacher_name
        FROM assembly_students s
        LEFT JOIN assembly_classrooms c ON c.classroom = s.classroom
        WHERE s.student_id = ?
    ");
    $sStmt->execute([$studentId]);
    $student = $sStmt->fetch();

    if (!$student) {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'ไม่พบนักเรียน']);
        exit;
    }

    // ประวัติการเข้าแถว
    $monthCond = '';
    $params    = [$studentId];
    if ($month !== 'all') {
        $monthCond = "AND DATE_FORMAT(date, '%m') = ?";
        $params[]  = $month;
    }
    $aStmt = $pdo->prepare("
        SELECT date, status, nail, hair, shirt, pants, socks, shoes, note
        FROM assembly_attendance
        WHERE student_id = ? $monthCond
        ORDER BY date DESC
    ");
    $aStmt->execute($params);
    $history = $aStmt->fetchAll();

    // คำนวณสรุป
    $present = $absent = $leave = $uniformCorrect = $uniformTotal = 0;
    foreach ($history as $r) {
        match($r['status']) {
            'ม' => $present++, 'ข' => $absent++, 'ล' => $leave++, 'ด' => $absent++, default => null
        };
        if ($r['status'] === 'ม') {
            $uniformTotal += 6;
            foreach (['nail','hair','shirt','pants','socks','shoes'] as $f) {
                if ($r[$f] === 'ถูก') $uniformCorrect++;
            }
        }
    }
    $uniformScore = $uniformTotal > 0 ? round($uniformCorrect / $uniformTotal * 100) : 0;

    // ข้อมูล checkout (evening)
    $cParams = [$studentId];
    $cMonthCond = '';
    if ($month !== 'all') { $cMonthCond = "AND DATE_FORMAT(date,'%m') = ?"; $cParams[] = $month; }
    $cStmt = $pdo->prepare("SELECT status FROM assembly_checkout WHERE student_id = ? $cMonthCond");
    $cStmt->execute($cParams);
    $checkouts = $cStmt->fetchAll(PDO::FETCH_COLUMN);
    $eveningPresent = count(array_filter($checkouts, fn($s) => $s === 'มา'));
    $eveningAbsent  = count($checkouts) - $eveningPresent;

    echo json_encode([
        'status'  => 'success',
        'info'    => [
            'id'      => $student['student_id'],
            'name'    => $student['name'],
            'class'   => $student['classroom'],
            'teacher' => $student['teacher_name'] ?? '',
        ],
        'summary' => [
            'present'      => $present,
            'absent'       => $absent,
            'leave'        => $leave,
            'uniformScore' => $uniformScore,
            'evening'      => ['present' => $eveningPresent, 'absent' => $eveningAbsent, 'skip' => 0],
        ],
        'history' => $history,
    ]);
} catch (Exception $e) {
    error_log('[Assembly] get_student_report: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาด']);
}
