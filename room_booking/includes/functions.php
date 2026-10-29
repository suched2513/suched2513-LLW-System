<?php
/**
 * room_booking/includes/functions.php
 * ฟังก์ชันผู้ช่วยหลักสำหรับระบบจองห้องประชุม
 */

declare(strict_types=1);

require_once __DIR__ . '/db.php';

/**
 * ตรวจสอบสิทธิ์ผู้ดูแลระบบจองห้องประชุม
 */
function isRoomAdmin(): bool {
    if (session_status() === PHP_SESSION_NONE && php_sapi_name() !== 'cli') @session_start();
    if (empty($_SESSION['user_id'])) return false;
    
    $role = $_SESSION['llw_role'] ?? '';
    $roles = $_SESSION['llw_roles'] ?? [];
    
    $adminRoles = ['super_admin', 'wfh_admin', 'room_admin', 'director', 'deputy_director'];
    if (in_array($role, $adminRoles, true)) return true;
    foreach ($roles as $r) {
        if (in_array($r, $adminRoles, true)) return true;
    }
    return false;
}

/**
 * บังคับสิทธิ์ผู้ดูแลระบบ หากไม่ใช่ให้ Redirect หรือส่ง 403
 */
function requireRoomAdmin(bool $isApi = false): void {
    if (!isRoomAdmin()) {
        if ($isApi) {
            http_response_code(403);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'message' => 'คุณไม่มีสิทธิ์ดำเนินการในส่วนนี้']);
            exit();
        }
        header('Location: ../login.php?error=unauthorized');
        exit();
    }
}

/**
 * ฟังก์ชันตรวจสอบช่วงเวลาชนกัน (Time-Overlap Check)
 * กติกา:
 * 1. สถานะ pending และ approved ถือว่ากันเวลาห้อง
 * 2. rejected และ cancelled ไม่กันเวลาห้อง
 * 3. จองต่อกันพอดี เช่น 09:00-10:00 และ 10:00-11:00 ไม่ถือว่าชน
 * 
 * สูตร: NewStart < ExistingEnd AND NewEnd > ExistingStart
 */
function checkBookingConflict(
    PDO $pdo,
    int $roomId,
    string $bookingDate,
    string $startTime,
    string $endTime,
    ?int $excludeBookingId = null,
    bool $forUpdate = false
): ?array {
    $sql = "
        SELECT b.*, r.room_name 
        FROM rb_bookings b
        JOIN rb_rooms r ON b.room_id = r.id
        WHERE b.room_id = ?
          AND b.booking_date = ?
          AND b.status IN ('pending', 'approved')
          AND (b.start_time < ? AND b.end_time > ?)
    ";
    $params = [$roomId, $bookingDate, $endTime, $startTime];

    if ($excludeBookingId !== null && $excludeBookingId > 0) {
        $sql .= " AND b.id != ?";
        $params[] = $excludeBookingId;
    }

    if ($forUpdate) {
        $sql .= " FOR UPDATE";
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $conflict = $stmt->fetch();

    return $conflict ?: null;
}

/**
 * สร้างรหัสการจอง เช่น BK-202610-0001
 */
function generateBookingCode(PDO $pdo): string {
    $prefix = 'BK-' . date('Ym') . '-';
    $stmt = $pdo->prepare("SELECT booking_code FROM rb_bookings WHERE booking_code LIKE ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$prefix . '%']);
    $last = $stmt->fetchColumn();

    if ($last) {
        $num = (int)substr($last, -4);
        $next = $num + 1;
    } else {
        $next = 1;
    }
    return $prefix . str_pad((string)$next, 4, '0', STR_PAD_LEFT);
}

/**
 * บันทึก Audit Log
 */
function logRoomBookingAudit(
    PDO $pdo,
    string $action,
    string $tableName,
    ?int $recordId = null,
    ?string $details = null
): void {
    try {
        if (session_status() === PHP_SESSION_NONE && php_sapi_name() !== 'cli') @session_start();
        $userId = $_SESSION['user_id'] ?? null;
        $username = $_SESSION['username'] ?? 'guest';
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

        $stmt = $pdo->prepare("
            INSERT INTO rb_audit_logs (user_id, username, action, table_name, record_id, details, ip_address)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$userId, $username, $action, $tableName, $recordId, $details, $ip]);
    } catch (\Throwable $e) {
        error_log('[RB Audit Log Error] ' . $e->getMessage());
    }
}

/**
 * จัดรูปแบบวันที่ภาษาไทย (พ.ศ.)
 */
function formatThaiDate(?string $dateStr, bool $short = false): string {
    if (!$dateStr) return '-';
    $timestamp = strtotime($dateStr);
    if (!$timestamp) return $dateStr;

    $thaiMonthsFull = [
        1 => 'มกราคม', 2 => 'กุมภาพันธ์', 3 => 'มีนาคม', 4 => 'เมษายน',
        5 => 'พฤษภาคม', 6 => 'มิถุนายน', 7 => 'กรกฎาคม', 8 => 'สิงหาคม',
        9 => 'กันยายน', 10 => 'ตุลาคม', 11 => 'พฤศจิกายน', 12 => 'ธันวาคม'
    ];
    $thaiMonthsShort = [
        1 => 'ม.ค.', 2 => 'ก.พ.', 3 => 'มี.ค.', 4 => 'เม.ย.',
        5 => 'พ.ค.', 6 => 'มิ.ย.', 7 => 'ก.ค.', 8 => 'ส.ค.',
        9 => 'ก.ย.', 10 => 'ต.ค.', 11 => 'พ.ย.', 12 => 'ธ.ค.'
    ];

    $d = (int)date('j', $timestamp);
    $m = (int)date('n', $timestamp);
    $y = (int)date('Y', $timestamp) + 543;

    $month = $short ? $thaiMonthsShort[$m] : $thaiMonthsFull[$m];
    return "{$d} {$month} {$y}";
}

/**
 * ตัดวินาทีออกจากเวลา (HH:MM:SS -> HH:MM)
 */
function formatTime(?string $timeStr): string {
    if (!$timeStr) return '';
    return substr($timeStr, 0, 5);
}

/**
 * สร้าง Badge สถานะ HTML
 */
function renderStatusBadge(string $status): string {
    switch ($status) {
        case 'approved':
            return '<span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800 border border-emerald-200"><span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>อนุมัติแล้ว</span>';
        case 'pending':
            return '<span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-amber-100 text-amber-800 border border-amber-200"><span class="w-1.5 h-1.5 rounded-full bg-amber-500 animate-pulse"></span>รออนุมัติ</span>';
        case 'rejected':
            return '<span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-rose-100 text-rose-800 border border-rose-200"><span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span>ไม่อนุมัติ</span>';
        case 'cancelled':
            return '<span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-slate-100 text-slate-700 border border-slate-200"><span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span>ยกเลิกแล้ว</span>';
        default:
            return '<span class="px-2.5 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-700">' . htmlspecialchars($status) . '</span>';
    }
}
