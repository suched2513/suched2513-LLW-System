<?php
/**
 * assembly/api/get_overview.php
 * GET ?classroom=&month= — สรุปภาพรวมห้อง
 */
header('Content-Type: application/json; charset=utf-8');
session_start();
require_once __DIR__ . '/../../config/database.php';

if (!isset($_SESSION['llw_role'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'กรุณาเข้าสู่ระบบ']);
    exit;
}

$classroom = trim($_GET['classroom'] ?? '');
$month     = trim($_GET['month']     ?? 'all');

if ($classroom === '') {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'กรุณาระบุห้องเรียน']);
    exit;
}

try {
    $pdo = getPdo();

    // ดึงนักเรียนทั้งหมดในห้อง
    $sStmt = $pdo->prepare("SELECT student_id, name FROM assembly_students WHERE classroom = ? ORDER BY student_id");
    $sStmt->execute([$classroom]);
    $students = $sStmt->fetchAll();

    // สร้าง condition สำหรับ month
    $monthCond = '';
    $params    = [$classroom];
    if ($month !== 'all') {
        $monthCond = "AND DATE_FORMAT(a.date, '%m') = ?";
        $params[]  = $month;
    }

    // ดึง attendance ทั้งหมด
    $aStmt = $pdo->prepare("
        SELECT a.student_id, a.status, a.nail, a.hair, a.shirt, a.pants, a.socks, a.shoes, a.note
        FROM assembly_attendance a
        WHERE a.classroom = ? $monthCond
    ");
    $aStmt->execute($params);
    $allAtt = $aStmt->fetchAll();

    // Index by student_id
    $attByStudent = [];
    foreach ($allAtt as $row) {
        $attByStudent[$row['student_id']][] = $row;
    }

    $attendanceStats = ['present' => 0, 'absent' => 0, 'leave' => 0, 'skip' => 0];
    $uniformTotals   = ['nail' => 0, 'hair' => 0, 'shirt' => 0, 'pants' => 0, 'socks' => 0, 'shoes' => 0, 'count' => 0];
    $studentsSummary = [];

    foreach ($students as $s) {
        $recs      = $attByStudent[$s['student_id']] ?? [];
        $totalDays = count($recs);
        $present = $absent = $leave = $skip = 0;
        $nail = $hair = $shirt = $pants = $socks = $shoes = 0;
        $notes = [];

        foreach ($recs as $r) {
            match($r['status']) {
                'ม' => $present++, 'ข' => $absent++, 'ล' => $leave++, 'ด' => $skip++, default => null
            };
            if ($r['nail']  === 'ถูก') $nail++;
            if ($r['hair']  === 'ถูก') $hair++;
            if ($r['shirt'] === 'ถูก') $shirt++;
            if ($r['pants'] === 'ถูก') $pants++;
            if ($r['socks'] === 'ถูก') $socks++;
            if ($r['shoes'] === 'ถูก') $shoes++;
            if (!empty($r['note'])) $notes[] = $r['note'];
        }

        $attendanceStats['present'] += $present;
        $attendanceStats['absent']  += $absent;
        $attendanceStats['leave']   += $leave;
        $attendanceStats['skip']    += $skip;
        $uniformTotals['nail']      += $nail;
        $uniformTotals['hair']      += $hair;
        $uniformTotals['shirt']     += $shirt;
        $uniformTotals['pants']     += $pants;
        $uniformTotals['socks']     += $socks;
        $uniformTotals['shoes']     += $shoes;
        $uniformTotals['count']     += $totalDays;

        $studentsSummary[] = [
            'name'        => $s['name'],
            'totalDays'   => $totalDays,
            'present'     => $present,
            'absent'      => $absent + $skip,
            'leave'       => $leave,
            'nailCorrect' => $nail,
            'hairCorrect' => $hair,
            'shirtCorrect'=> $shirt,
            'pantsCorrect'=> $pants,
            'socksCorrect'=> $socks,
            'shoesCorrect'=> $shoes,
            'notes'       => implode('; ', $notes),
        ];
    }

    // คำนวณ % uniform
    $uc = $uniformTotals['count'];
    $uniformStats = [
        'nail'  => $uc > 0 ? round($uniformTotals['nail']  / $uc * 100) : 0,
        'hair'  => $uc > 0 ? round($uniformTotals['hair']  / $uc * 100) : 0,
        'shirt' => $uc > 0 ? round($uniformTotals['shirt'] / $uc * 100) : 0,
        'pants' => $uc > 0 ? round($uniformTotals['pants'] / $uc * 100) : 0,
        'socks' => $uc > 0 ? round($uniformTotals['socks'] / $uc * 100) : 0,
        'shoes' => $uc > 0 ? round($uniformTotals['shoes'] / $uc * 100) : 0,
    ];

    // คำนวณ % attendance
    $total = array_sum($attendanceStats);
    if ($total > 0) {
        foreach ($attendanceStats as $k => $v) {
            $attendanceStats[$k] = round($v / $total * 100);
        }
    }

    echo json_encode([
        'status'          => 'success',
        'attendanceStats' => $attendanceStats,
        'uniformStats'    => $uniformStats,
        'studentsSummary' => $studentsSummary,
    ]);
} catch (Exception $e) {
    error_log('[Assembly] get_overview: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาด']);
}
