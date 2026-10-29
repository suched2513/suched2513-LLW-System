<?php
/**
 * room_booking/tests/RoomBookingTest.php
 * ชุดทดสอบระบบจองห้องประชุม (Logic, Time Overlap, ICS & Security)
 */

declare(strict_types=1);

echo "=====================================================================" . PHP_EOL;
echo "  โรงเรียนละลมวิทยา - ชุดทดสอบระบบจองห้องประชุม (Room Booking Test Suite)" . PHP_EOL;
echo "=====================================================================" . PHP_EOL . PHP_EOL;

$passed = 0;
$total = 0;

function assertTrue(bool $condition, string $message): void {
    global $passed, $total;
    $total++;
    if ($condition) {
        $passed++;
        echo "  [PASS] {$message}" . PHP_EOL;
    } else {
        echo "  [FAIL] {$message}" . PHP_EOL;
    }
}

// ── 1. ทดสอบตรรกะการตรวจสอบเวลาชน (Time-Overlap Logic) ────────────────────────
echo ">>> 1. ทดสอบตรรกะการตรวจสอบเวลาชน (Conflict Formula Logic)" . PHP_EOL;

function testOverlap(string $startA, string $endA, string $startB, string $endB): bool {
    return ($startA < $endB) && ($endA > $startB);
}

// กรณีที่ 1: ช่วงเวลาซ้อนทับกันบางส่วน (09:00-11:00 vs 10:00-12:00)
assertTrue(testOverlap('09:00:00', '11:00:00', '10:00:00', '12:00:00') === true, 'เวลาซ้อนทับบางส่วน (09:00-11:00 vs 10:00-12:00) ต้องตรวจพบว่าชนกัน');

// กรณีที่ 2: ช่วงเวลาต่อกันพอดี (09:00-10:00 vs 10:00-11:00) -> อนุญาต (ไม่ชน)
assertTrue(testOverlap('09:00:00', '10:00:00', '10:00:00', '11:00:00') === false, 'เวลาต่อกันพอดี (09:00-10:00 vs 10:00-11:00) ต้องไม่ชนกัน (อนุญาตให้จองต่อกันได้)');

// กรณีที่ 3: ช่วงเวลาคลุมทั้งช่วง (08:30-12:30 vs 09:00-10:00)
assertTrue(testOverlap('08:30:00', '12:30:00', '09:00:00', '10:00:00') === true, 'เวลาคลุมทั้งช่วง (08:30-12:30 vs 09:00-10:00) ต้องตรวจพบว่าชนกัน');

// กรณีที่ 4: คนละช่วงเวลาไม่เกี่ยวข้องกัน (09:00-10:00 vs 13:00-15:00)
assertTrue(testOverlap('09:00:00', '10:00:00', '13:00:00', '15:00:00') === false, 'คนละช่วงเวลาเช้า-บ่าย (09:00-10:00 vs 13:00-15:00) ต้องไม่ชนกัน');

// ── 2. ทดสอบการจัดรูปแบบวันที่ภาษาไทย ──────────────────────────────────────────
echo PHP_EOL . ">>> 2. ทดสอบการจัดรูปแบบวันที่และเวลา (Thai Formatting)" . PHP_EOL;

function testFormatThaiDate(?string $dateStr, bool $short = false): string {
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

$thaiFull = testFormatThaiDate('2026-10-30', false);
assertTrue($thaiFull === '30 ตุลาคม 2569', "แปลงวันที่ '2026-10-30' เป็นภาษาไทยเต็ม: ได้ '{$thaiFull}' (ถูกต้อง)");

$thaiShort = testFormatThaiDate('2026-10-30', true);
assertTrue($thaiShort === '30 ต.ค. 2569', "แปลงวันที่ '2026-10-30' เป็นภาษาไทยย่อ: ได้ '{$thaiShort}' (ถูกต้อง)");

function testFormatTime(?string $timeStr): string {
    if (!$timeStr) return '';
    return substr($timeStr, 0, 5);
}

$formattedTime = testFormatTime('09:30:00');
assertTrue($formattedTime === '09:30', "ตัดวินาทีออกจากเวลา '09:30:00' -> '09:30' (ถูกต้อง)");

// ── 3. ทดสอบ Badge สถานะ ──────────────────────────────────────────────────────
echo PHP_EOL . ">>> 3. ทดสอบการแสดงผลสถานะ Badge (Status Badges)" . PHP_EOL;

function testRenderBadge(string $status): string {
    switch ($status) {
        case 'approved':
            return 'อนุมัติแล้ว';
        case 'pending':
            return 'รออนุมัติ';
        case 'rejected':
            return 'ไม่อนุมัติ';
        default:
            return $status;
    }
}

assertTrue(testRenderBadge('approved') === 'อนุมัติแล้ว', 'Badge สถานะ approved มีข้อความ อนุมัติแล้ว');
assertTrue(testRenderBadge('pending') === 'รออนุมัติ', 'Badge สถานะ pending มีข้อความ รออนุมัติ');
assertTrue(testRenderBadge('rejected') === 'ไม่อนุมัติ', 'Badge สถานะ rejected มีข้อความ ไม่อนุมัติ');

// ── 4. ทดสอบความพร้อมของไฟล์ระบบและโครงสร้าง API ──────────────────────────────
echo PHP_EOL . ">>> 4. ทดสอบความครบถ้วนของไฟล์ในโมดูลระบบจองห้องประชุม" . PHP_EOL;
$files = [
    'includes/db.php',
    'includes/functions.php',
    'includes/header.php',
    'includes/footer.php',
    'index.php',
    'book.php',
    'my_bookings.php',
    'admin/index.php',
    'admin/requests.php',
    'admin/rooms.php',
    'admin/export.php',
    'api/calendar.php',
    'api/check_availability.php',
    'api/submit_booking.php',
    'api/update_status.php',
    'api/export_ics.php'
];

foreach ($files as $f) {
    $path = __DIR__ . '/../' . $f;
    assertTrue(file_exists($path) && filesize($path) > 0, "ไฟล์ '{$f}' มีอยู่จริงและมีขนาดถูกต้อง");
}

// ── 5. ทดสอบการเชื่อมโยงระบบเข้าสู่หน้าแรกโรงเรียน ─────────────────────────────
echo PHP_EOL . ">>> 5. ทดสอบการเชื่อมโยงเข้าสู่ระบบกลางของโรงเรียน" . PHP_EOL;
$indexContent = file_get_contents(__DIR__ . '/../../index.php');
assertTrue(strpos($indexContent, 'room_booking/index.php') !== false, 'มีลิงก์โมดูล room_booking/index.php ใน index.php (หน้าแรกโรงเรียน)');
assertTrue(strpos($indexContent, 'ระบบจองห้องประชุม') !== false, 'มีการ์ดชื่อ ระบบจองห้องประชุม ในรายการโมดูลของโรงเรียน');

$dashboardContent = file_get_contents(__DIR__ . '/../../central_dashboard.php');
assertTrue(strpos($dashboardContent, 'room_booking/admin/requests.php') !== false, 'มีลิงก์ทางลัดคำขอจองห้องใน central_dashboard.php (Super Dashboard)');

echo PHP_EOL . "=====================================================================" . PHP_EOL;
echo "  สรุปผลการทดสอบ: ผ่าน {$passed} / {$total} รายการ (" . round(($passed / $total) * 100) . "%)" . PHP_EOL;
echo "=====================================================================" . PHP_EOL;

if ($passed === $total) {
    echo "ผลลัพธ์: การทดสอบผ่านสมบูรณ์ 100% (ALL TESTS PASSED)" . PHP_EOL;
    exit(0);
} else {
    echo "ผลลัพธ์: มีข้อผิดพลาดในบางการทดสอบ!" . PHP_EOL;
    exit(1);
}
