<?php
/**
 * room_booking/api/export_ics.php
 * ดาวน์โหลดไฟล์ปฏิทินมาตรฐาน .ics สำหรับนำเข้า Google Calendar, Outlook, iPhone
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/functions.php';

$bookingId = (int)($_GET['id'] ?? 0);

if ($bookingId <= 0) {
    die('Invalid Booking ID');
}

$pdo = getRoomBookingPdo();
$stmt = $pdo->prepare("
    SELECT b.*, r.room_name, r.building, r.floor
    FROM rb_bookings b
    JOIN rb_rooms r ON b.room_id = r.id
    WHERE b.id = ?
");
$stmt->execute([$bookingId]);
$b = $stmt->fetch();

if (!$b) {
    die('Booking not found');
}

$dtStart = date('Ymd\THis', strtotime("{$b['booking_date']} {$b['start_time']}"));
$dtEnd   = date('Ymd\THis', strtotime("{$b['booking_date']} {$b['end_time']}"));
$created = date('Ymd\THis\Z', strtotime($b['created_at']));
$uid     = "booking-{$b['id']}@krusuched.com";
$summary = "จองห้อง: {$b['room_name']} - {$b['title']}";
$location = "{$b['room_name']} ({$b['building']} {$b['floor']}) โรงเรียนละลมวิทยา";
$description = "รหัสการจอง: {$b['booking_code']}\\nผู้ขอใช้: {$b['requester_name']}\\nหน่วยงาน: {$b['department']}\\nจำนวนผู้เข้าร่วม: {$b['attendee_count']} คน\\nสถานะ: {$b['status']}\\nหมายเหตุ: " . addcslashes($b['note'] ?: '-', "\n\r");

header('Content-Type: text/calendar; charset=utf-8');
header('Content-Disposition: attachment; filename="booking_' . $b['booking_code'] . '.ics"');

echo "BEGIN:VCALENDAR\r\n";
echo "VERSION:2.0\r\n";
echo "PRODID:-//โรงเรียนละลมวิทยา//ระบบจองห้องประชุม//TH\r\n";
echo "CALSCALE:GREGORIAN\r\n";
echo "METHOD:PUBLISH\r\n";
echo "BEGIN:VEVENT\r\n";
echo "UID:{$uid}\r\n";
echo "DTSTAMP:{$created}\r\n";
echo "DTSTART:{$dtStart}\r\n";
echo "DTEND:{$dtEnd}\r\n";
echo "SUMMARY:{$summary}\r\n";
echo "DESCRIPTION:{$description}\r\n";
echo "LOCATION:{$location}\r\n";
echo "STATUS:" . ($b['status'] === 'approved' ? 'CONFIRMED' : 'TENTATIVE') . "\r\n";
echo "END:VEVENT\r\n";
echo "END:VCALENDAR\r\n";
exit();
