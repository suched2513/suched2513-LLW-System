<?php
/**
 * room_booking/api/calendar.php
 * API ดึงข้อมูลรายการจองสำหรับปฏิทิน
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/functions.php';

try {
    $pdo = getRoomBookingPdo();

    $month = $_GET['month'] ?? date('Y-m');
    $roomId = isset($_GET['room_id']) && $_GET['room_id'] !== '' ? (int)$_GET['room_id'] : null;

    // ตรวจสอบรูปแบบ YYYY-MM
    if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
        $month = date('Y-m');
    }

    $startDate = "{$month}-01";
    $endDate = date('Y-m-t', strtotime($startDate));

    $sql = "
        SELECT 
            b.id,
            b.booking_code,
            b.room_id,
            b.booking_date,
            b.start_time,
            b.end_time,
            b.title,
            b.department,
            b.requester_name,
            b.attendee_count,
            b.status,
            r.room_name,
            r.color_code
        FROM rb_bookings b
        JOIN rb_rooms r ON b.room_id = r.id
        WHERE b.booking_date BETWEEN ? AND ?
          AND b.status IN ('approved', 'pending')
    ";
    $params = [$startDate, $endDate];

    if ($roomId !== null && $roomId > 0) {
        $sql .= " AND b.room_id = ?";
        $params[] = $roomId;
    }

    $sql .= " ORDER BY b.booking_date ASC, b.start_time ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    $events = [];
    foreach ($rows as $r) {
        $startTime = formatTime($r['start_time']);
        $endTime = formatTime($r['end_time']);
        $isPending = ($r['status'] === 'pending');

        $events[] = [
            'id'             => (int)$r['id'],
            'booking_code'   => $r['booking_code'],
            'title'          => ($isPending ? '[รออนุมัติ] ' : '') . $r['title'],
            'raw_title'      => $r['title'],
            'room_id'        => (int)$r['room_id'],
            'room_name'      => $r['room_name'],
            'start'          => "{$r['booking_date']}T{$r['start_time']}",
            'end'            => "{$r['booking_date']}T{$r['end_time']}",
            'date'           => $r['booking_date'],
            'date_thai'      => formatThaiDate($r['booking_date']),
            'time_range'     => "{$startTime} - {$endTime} น.",
            'department'     => $r['department'] ?: 'ไม่ระบุ',
            'requester_name' => $r['requester_name'],
            'attendee_count' => (int)$r['attendee_count'],
            'status'         => $r['status'],
            'color'          => $isPending ? '#f59e0b' : ($r['color_code'] ?: '#3b82f6'),
            'border_color'   => $isPending ? '#d97706' : ($r['color_code'] ?: '#2563eb'),
        ];
    }

    echo json_encode(['success' => true, 'events' => $events], JSON_UNESCAPED_UNICODE);
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'เกิดข้อผิดพลาดในการดึงข้อมูล: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
