<?php
/**
 * assembly/api/export_csv.php
 * GET ?classroom=&month= — Export CSV รายงานห้อง
 */
session_start();
require_once __DIR__ . '/../../config/database.php';

if (!isset($_SESSION['llw_role'])) {
    http_response_code(401);
    echo 'Unauthorized';
    exit;
}

$classroom = trim($_GET['classroom'] ?? '');
$month     = trim($_GET['month']     ?? 'all');

if ($classroom === '') {
    http_response_code(400);
    echo 'Missing classroom';
    exit;
}

try {
    $pdo = getPdo();

    $monthNames = ['01'=>'มกราคม','02'=>'กุมภาพันธ์','03'=>'มีนาคม','04'=>'เมษายน',
                   '05'=>'พฤษภาคม','06'=>'มิถุนายน','07'=>'กรกฎาคม','08'=>'สิงหาคม',
                   '09'=>'กันยายน','10'=>'ตุลาคม','11'=>'พฤศจิกายน','12'=>'ธันวาคม'];
    $monthLabel = ($month === 'all') ? 'ทุกเดือน' : ($monthNames[$month] ?? $month);

    $sStmt = $pdo->prepare("SELECT student_id, name FROM assembly_students WHERE classroom = ? ORDER BY student_id");
    $sStmt->execute([$classroom]);
    $students = $sStmt->fetchAll();

    $monthCond = '';
    $params    = [$classroom];
    if ($month !== 'all') {
        $monthCond = "AND DATE_FORMAT(date,'%m') = ?";
        $params[]  = $month;
    }
    $aStmt = $pdo->prepare("
        SELECT student_id, status, nail, hair, shirt, pants, socks, shoes, note, date
        FROM assembly_attendance
        WHERE classroom = ? $monthCond
        ORDER BY student_id, date
    ");
    $aStmt->execute($params);
    $allAtt = $aStmt->fetchAll();

    $attByStudent = [];
    foreach ($allAtt as $row) {
        $attByStudent[$row['student_id']][] = $row;
    }

    $filename = "assembly_{$classroom}_{$monthLabel}_" . date('Ymd') . ".csv";
    $filename  = preg_replace('/[^\w\-.]/', '_', $filename);

    header('Content-Type: text/csv; charset=utf-8');
    header("Content-Disposition: attachment; filename=\"$filename\"");
    // BOM for Excel Thai
    echo "\xEF\xBB\xBF";

    $out = fopen('php://output', 'w');
    fputcsv($out, ['รหัส','ชื่อ-สกุล','วันมา','วันขาด','วันลา','วันโดด','เล็บถูก','ทรงผมถูก','เสื้อถูก','กางเกงถูก','ถุงเท้าถูก','รองเท้าถูก','หมายเหตุ']);

    foreach ($students as $s) {
        $recs = $attByStudent[$s['student_id']] ?? [];
        $present = $absent = $leave = $skip = 0;
        $nail = $hair = $shirt = $pants = $socks = $shoes = 0;
        $notes = [];
        foreach ($recs as $r) {
            match($r['status']) { 'ม'=>$present++,'ข'=>$absent++,'ล'=>$leave++,'ด'=>$skip++, default=>null };
            if ($r['nail']  === 'ถูก') $nail++;
            if ($r['hair']  === 'ถูก') $hair++;
            if ($r['shirt'] === 'ถูก') $shirt++;
            if ($r['pants'] === 'ถูก') $pants++;
            if ($r['socks'] === 'ถูก') $socks++;
            if ($r['shoes'] === 'ถูก') $shoes++;
            if (!empty($r['note'])) $notes[] = $r['note'];
        }
        fputcsv($out, [
            $s['student_id'], $s['name'],
            $present, $absent, $leave, $skip,
            $nail, $hair, $shirt, $pants, $socks, $shoes,
            implode('; ', $notes),
        ]);
    }
    fclose($out);
} catch (Exception $e) {
    error_log('[Assembly] export_csv: ' . $e->getMessage());
    http_response_code(500);
    echo 'เกิดข้อผิดพลาด';
}
