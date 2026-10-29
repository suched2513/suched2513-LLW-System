<?php
/**
 * room_booking/admin/export.php
 * ส่งออกรายงานการจองห้องประชุมเป็นไฟล์ CSV (UTF-8 BOM สำหรับ Excel)
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/functions.php';

requireRoomAdmin();

$pdo = getRoomBookingPdo();

$status = $_GET['status'] ?? 'all';
$sql = "
    SELECT b.*, r.room_name, r.building, r.floor
    FROM rb_bookings b
    JOIN rb_rooms r ON b.room_id = r.id
    WHERE 1=1
";
$params = [];
if ($status !== 'all') {
    $sql .= " AND b.status = ?";
    $params[] = $status;
}
$sql .= " ORDER BY b.booking_date DESC, b.start_time ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$filename = 'room_bookings_' . date('Ymd_His') . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

// Output UTF-8 BOM so Microsoft Excel renders Thai text properly
echo "\xEF\xBB\xBF";

$fp = fopen('php://output', 'w');

// Header row
fputcsv($fp, [
    'รหัสการจอง',
    'ชื่อห้องประชุม',
    'สถานที่',
    'วันที่ใช้งาน',
    'เวลาเริ่ม',
    'เวลาสิ้นสุด',
    'หัวข้อการประชุม',
    'กลุ่มสาระ/ฝ่ายงาน',
    'ผู้ขอใช้ห้อง',
    'เบอร์โทรศัพท์',
    'จำนวนคน',
    'สถานะ',
    'เหตุผลกรณีไม่อนุมัติ',
    'วันที่ส่งคำขอ'
]);

$statusMap = [
    'approved' => 'อนุมัติแล้ว',
    'pending'  => 'รออนุมัติ',
    'rejected' => 'ไม่อนุมัติ',
    'cancelled'=> 'ยกเลิก'
];

foreach ($rows as $r) {
    fputcsv($fp, [
        $r['booking_code'],
        $r['room_name'],
        trim("{$r['building']} {$r['floor']}"),
        $r['booking_date'],
        formatTime($r['start_time']),
        formatTime($r['end_time']),
        $r['title'],
        $r['department'],
        $r['requester_name'],
        $r['requester_phone'] ?: '-',
        $r['attendee_count'],
        $statusMap[$r['status']] ?? $r['status'],
        $r['rejection_reason'] ?: '-',
        $r['created_at']
    ]);
}

fclose($fp);
exit();
