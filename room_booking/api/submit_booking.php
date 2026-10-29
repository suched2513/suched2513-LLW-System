<?php
/**
 * room_booking/api/submit_booking.php
 * API บันทึกการส่งคำขอจองห้องประชุม (พร้อมระบบ Concurrency Lock & Transaction)
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
    exit();
}

try {
    $pdo = getRoomBookingPdo();

    // รับข้อมูล
    $roomId = (int)($_POST['room_id'] ?? 0);
    $bookingDate = trim((string)($_POST['booking_date'] ?? ''));
    $startTime = trim((string)($_POST['start_time'] ?? ''));
    $endTime = trim((string)($_POST['end_time'] ?? ''));
    $title = trim((string)($_POST['title'] ?? ''));
    $department = trim((string)($_POST['department'] ?? ''));
    $requesterName = trim((string)($_POST['requester_name'] ?? ''));
    $requesterPhone = trim((string)($_POST['requester_phone'] ?? ''));
    $attendeeCount = (int)($_POST['attendee_count'] ?? 1);
    $note = trim((string)($_POST['note'] ?? ''));

    // ตรวจสอบค่าว่างเบื้องต้น
    if ($roomId <= 0 || empty($bookingDate) || empty($startTime) || empty($endTime) || empty($title) || empty($requesterName)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'กรุณากรอกข้อมูลที่จำเป็นให้ครบถ้วน']);
        exit();
    }

    if (strlen($startTime) === 5) $startTime .= ':00';
    if (strlen($endTime) === 5) $endTime .= ':00';

    if ($startTime >= $endTime) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'เวลาเริ่มต้นต้องน้อยกว่าเวลาสิ้นสุด']);
        exit();
    }

    // ตรวจสอบห้องว่าเปิดใช้งานอยู่หรือไม่
    $roomStmt = $pdo->prepare("SELECT * FROM rb_rooms WHERE id = ? AND is_active = 1");
    $roomStmt->execute([$roomId]);
    $room = $roomStmt->fetch();
    if (!$room) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ไม่พบห้องประชุมที่ระบุ หรือห้องนี้ถูกปิดใช้งาน']);
        exit();
    }

    // นโยบาย: ไม่อนุญาตให้ผู้ใช้ทั่วไปจองย้อนหลัง (วันที่ในอดีต)
    $today = date('Y-m-d');
    if ($bookingDate < $today && !isRoomAdmin()) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ไม่สามารถส่งคำขอจองห้องย้อนหลังในอดีตได้']);
        exit();
    }

    // ดึง User ID หากเข้าสู่ระบบไว้
    $userId = $_SESSION['user_id'] ?? null;
    if (empty($requesterName) && !empty($_SESSION['fullname'])) {
        $requesterName = $_SESSION['fullname'];
    }

    // --- เริ่มต้น Concurrency Transaction & Row-Level Lock ---
    $pdo->beginTransaction();

    // 1. ตรวจสอบการชนของเวลาพร้อมล็อก Record ด้วย FOR UPDATE
    $conflict = checkBookingConflict($pdo, $roomId, $bookingDate, $startTime, $endTime, null, true);

    if ($conflict) {
        $pdo->rollBack();
        http_response_code(409); // 409 Conflict
        $conflictMsg = sprintf(
            'ขออภัย ห้อง "%s" ถูกจองในช่วงเวลา %s - %s น. โดย %s ไปแล้ว กรุณาเลือกช่วงเวลาอื่น',
            $room['room_name'],
            formatTime($conflict['start_time']),
            formatTime($conflict['end_time']),
            $conflict['requester_name']
        );
        echo json_encode([
            'success'   => false,
            'message'   => $conflictMsg,
            'is_conflict' => true
        ], JSON_UNESCAPED_UNICODE);
        exit();
    }

    // 2. สร้างรหัสการจอง
    $bookingCode = generateBookingCode($pdo);

    // 3. ทำการบันทึกคำขอ (สถานะเริ่มต้นคือ pending)
    $stmt = $pdo->prepare("
        INSERT INTO rb_bookings (
            booking_code, room_id, booking_date, start_time, end_time,
            title, department, requester_name, requester_phone, requester_user_id,
            attendee_count, note, status
        ) VALUES (
            ?, ?, ?, ?, ?,
            ?, ?, ?, ?, ?,
            ?, ?, 'pending'
        )
    ");
    $stmt->execute([
        $bookingCode, $roomId, $bookingDate, $startTime, $endTime,
        $title, $department, $requesterName, $requesterPhone, $userId,
        $attendeeCount, $note
    ]);
    $bookingId = (int)$pdo->lastInsertId();

    // 4. บันทึก Audit Log
    logRoomBookingAudit(
        $pdo,
        'create',
        'rb_bookings',
        $bookingId,
        "สร้างคำขอจองห้อง {$room['room_name']} ({$bookingCode}) วันที่ {$bookingDate} เวลา {$startTime}-{$endTime}"
    );

    $pdo->commit();

    http_response_code(201); // 201 Created
    echo json_encode([
        'success'      => true,
        'message'      => 'ส่งคำขอจองห้องประชุมเรียบร้อยแล้ว รอผู้ดูแลระบบตรวจสอบอนุมัติ',
        'booking_id'   => $bookingId,
        'booking_code' => $bookingCode
    ], JSON_UNESCAPED_UNICODE);

} catch (\Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'เกิดข้อผิดพลาดในการบันทึก: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
