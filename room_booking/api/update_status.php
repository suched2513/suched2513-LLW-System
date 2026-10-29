<?php
/**
 * room_booking/api/update_status.php
 * API อนุมัติ / ไม่อนุมัติ / ยกเลิกคำขอจอง (สำหรับผู้ดูแลระบบ)
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/functions.php';

requireRoomAdmin(true);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
    exit();
}

try {
    $pdo = getRoomBookingPdo();

    $bookingId = (int)($_POST['booking_id'] ?? 0);
    $newStatus = trim((string)($_POST['status'] ?? ''));
    $reason = trim((string)($_POST['rejection_reason'] ?? ''));

    if ($bookingId <= 0 || !in_array($newStatus, ['approved', 'rejected', 'pending', 'cancelled'], true)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ข้อมูลสถานะไม่ถูกต้อง']);
        exit();
    }

    $adminId = $_SESSION['user_id'] ?? null;

    $pdo->beginTransaction();

    // ดึงข้อมูลคำขอเดิม
    $stmt = $pdo->prepare("SELECT * FROM rb_bookings WHERE id = ? FOR UPDATE");
    $stmt->execute([$bookingId]);
    $booking = $stmt->fetch();

    if (!$booking) {
        $pdo->rollBack();
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'ไม่พบรายการจองที่ระบุ']);
        exit();
    }

    // กติกา: หากกำลังจะเปลี่ยนสถานะเป็น 'approved' หรือ 'pending' (เช่น นำรายการ rejected กลับมา)
    // ต้องตรวจสอบว่าช่วงเวลานี้ชนกับรายการอื่นหรือไม่!
    if (in_array($newStatus, ['approved', 'pending'], true)) {
        $conflict = checkBookingConflict(
            $pdo,
            (int)$booking['room_id'],
            $booking['booking_date'],
            $booking['start_time'],
            $booking['end_time'],
            $bookingId,
            true
        );

        if ($conflict) {
            $pdo->rollBack();
            http_response_code(409);
            echo json_encode([
                'success' => false,
                'message' => sprintf(
                    'ไม่สามารถอนุมัติได้ เนื่องจากช่วงเวลานี้มีรายการ "%s" (%s - %s น.) ถูกจองไปแล้ว',
                    $conflict['title'],
                    formatTime($conflict['start_time']),
                    formatTime($conflict['end_time'])
                )
            ], JSON_UNESCAPED_UNICODE);
            exit();
        }
    }

    // ทำการอัปเดตสถานะ
    $updateStmt = $pdo->prepare("
        UPDATE rb_bookings SET
            status = ?,
            rejection_reason = ?,
            approved_by = ?,
            approved_at = NOW(),
            updated_at = NOW()
        WHERE id = ?
    ");
    $updateStmt->execute([
        $newStatus,
        $newStatus === 'rejected' ? $reason : null,
        $adminId,
        $bookingId
    ]);

    // บันทึก Audit Log
    $statusTh = [
        'approved' => 'อนุมัติ',
        'rejected' => 'ไม่อนุมัติ',
        'pending'  => 'เปลี่ยนเป็นรออนุมัติ',
        'cancelled'=> 'ยกเลิก'
    ][$newStatus] ?? $newStatus;

    logRoomBookingAudit(
        $pdo,
        'update_status',
        'rb_bookings',
        $bookingId,
        "ผู้ดูแลเปลี่ยนสถานะคำขอ {$booking['booking_code']} เป็น '{$statusTh}'" . ($reason ? " (เหตุผล: {$reason})" : '')
    );

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => "ปรับปรุงสถานะคำขอเป็น '{$statusTh}' เรียบร้อยแล้ว",
        'status'  => $newStatus
    ], JSON_UNESCAPED_UNICODE);

} catch (\Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
