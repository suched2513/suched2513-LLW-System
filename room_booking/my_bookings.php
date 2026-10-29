<?php
/**
 * room_booking/my_bookings.php
 * รายการคำขอจองห้องของผู้ใช้งาน
 */

declare(strict_types=1);

$pageTitle = 'รายการจองของฉัน';
require_once __DIR__ . '/includes/header.php';

if (!$isLoggedIn) {
    header('Location: /login.php');
    exit();
}

$pdo = getRoomBookingPdo();
$userId = (int)$_SESSION['user_id'];
$userName = $_SESSION['fullname'] ?? '';

// ดึงรายการจองของผู้ใช้นี้
$stmt = $pdo->prepare("
    SELECT b.*, r.room_name, r.color_code, r.building, r.floor
    FROM rb_bookings b
    JOIN rb_rooms r ON b.room_id = r.id
    WHERE b.requester_user_id = ? OR b.requester_name = ?
    ORDER BY b.id DESC
");
$stmt->execute([$userId, $userName]);
$myBookings = $stmt->fetchAll();
?>

<div class="max-w-5xl mx-auto">
    <!-- Page Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">รายการจองห้องประชุมของฉัน</h1>
            <p class="text-sm text-slate-500">ติดตามสถานะคำขอ และดาวน์โหลดไฟล์ปฏิทินสำหรับการประชุมที่ได้รับการอนุมัติ</p>
        </div>
        <a href="book.php" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-blue-600 hover:bg-blue-700 text-white text-sm font-bold shadow-md shadow-blue-500/20 transition self-start sm:self-auto">
            <i class="bi bi-plus-lg"></i> จองห้องใหม่
        </a>
    </div>

    <!-- Bookings Table / Cards -->
    <div class="bg-white rounded-3xl border border-slate-200 shadow-sm overflow-hidden">
        <?php if (empty($myBookings)): ?>
            <div class="text-center py-16 px-4">
                <div class="w-16 h-16 rounded-2xl bg-blue-50 text-blue-500 flex items-center justify-center mx-auto text-2xl mb-3">
                    <i class="bi bi-calendar-x"></i>
                </div>
                <h3 class="text-base font-bold text-slate-800">ยังไม่มีประวัติการส่งคำขอจองห้อง</h3>
                <p class="text-xs text-slate-400 mt-1">คุณสามารถตรวจสอบวันเวลาว่างและกดส่งคำขอจองห้องได้ทันที</p>
                <a href="book.php" class="inline-flex items-center gap-1.5 mt-4 px-4 py-2 rounded-xl bg-blue-600 text-white text-xs font-semibold hover:bg-blue-700 transition">
                    <i class="bi bi-plus-circle"></i> เริ่มจองห้องประชุม
                </a>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-sm">
                    <thead>
                        <tr class="bg-slate-50/80 border-b border-slate-200 text-slate-600 text-xs">
                            <th class="py-3.5 px-4 font-semibold">รหัสการจอง</th>
                            <th class="py-3.5 px-4 font-semibold">ห้องประชุม</th>
                            <th class="py-3.5 px-4 font-semibold">วันที่ใช้งาน</th>
                            <th class="py-3.5 px-4 font-semibold">ช่วงเวลา</th>
                            <th class="py-3.5 px-4 font-semibold">หัวข้อการประชุม</th>
                            <th class="py-3.5 px-4 font-semibold text-center">สถานะ</th>
                            <th class="py-3.5 px-4 font-semibold text-right">ปฏิทิน</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach ($myBookings as $b): ?>
                            <tr class="hover:bg-slate-50/60 transition">
                                <td class="py-3.5 px-4 font-mono text-xs font-bold text-blue-700">
                                    <?= htmlspecialchars($b['booking_code']) ?>
                                </td>
                                <td class="py-3.5 px-4">
                                    <div class="font-semibold text-slate-800"><?= htmlspecialchars($b['room_name']) ?></div>
                                    <div class="text-[11px] text-slate-400"><?= htmlspecialchars("{$b['building']} {$b['floor']}") ?></div>
                                </td>
                                <td class="py-3.5 px-4 whitespace-nowrap text-slate-700">
                                    <?= formatThaiDate($b['booking_date'], true) ?>
                                </td>
                                <td class="py-3.5 px-4 whitespace-nowrap font-medium text-slate-700">
                                    <?= formatTime($b['start_time']) ?> - <?= formatTime($b['end_time']) ?> น.
                                </td>
                                <td class="py-3.5 px-4 max-w-xs">
                                    <div class="font-medium text-slate-800 truncate" title="<?= htmlspecialchars($b['title']) ?>"><?= htmlspecialchars($b['title']) ?></div>
                                    <?php if ($b['status'] === 'rejected' && !empty($b['rejection_reason'])): ?>
                                        <div class="text-[11px] text-rose-600 mt-0.5">เหตุผล: <?= htmlspecialchars($b['rejection_reason']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3.5 px-4 text-center whitespace-nowrap">
                                    <?= renderStatusBadge($b['status']) ?>
                                </td>
                                <td class="py-3.5 px-4 text-right whitespace-nowrap">
                                    <?php if ($b['status'] === 'approved'): ?>
                                        <a href="api/export_ics.php?id=<?= $b['id'] ?>" class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg text-xs font-semibold bg-blue-50 text-blue-700 hover:bg-blue-100 border border-blue-200 transition" title="ดาวน์โหลดไฟล์ .ics เพิ่มลง Google Calendar">
                                            <i class="bi bi-calendar-plus"></i> .ics
                                        </a>
                                    <?php else: ?>
                                        <span class="text-xs text-slate-300">-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
