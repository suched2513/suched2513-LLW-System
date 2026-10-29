<?php
/**
 * room_booking/admin/index.php
 * แผงควบคุมผู้ดูแลระบบจองห้องประชุม (Dashboard & Reports)
 */

declare(strict_types=1);

$pageTitle = 'แผงควบคุมผู้ดูแลระบบ';
require_once __DIR__ . '/../includes/functions.php';

requireRoomAdmin();

require_once __DIR__ . '/../includes/header.php';

$pdo = getRoomBookingPdo();

// สถิติรวม
$statStmt = $pdo->query("
    SELECT 
        COUNT(*) as total_bookings,
        COUNT(CASE WHEN status = 'pending' THEN 1 END) as total_pending,
        COUNT(CASE WHEN status = 'approved' THEN 1 END) as total_approved,
        COUNT(CASE WHEN status = 'rejected' THEN 1 END) as total_rejected
    FROM rb_bookings
");
$stats = $statStmt->fetch();

// สถิติการใช้งานแต่ละห้อง
$roomStatsStmt = $pdo->query("
    SELECT r.room_name, COUNT(b.id) as booking_count
    FROM rb_rooms r
    LEFT JOIN rb_bookings b ON r.id = b.room_id AND b.status = 'approved'
    GROUP BY r.id
    ORDER BY booking_count DESC
");
$roomStats = $roomStatsStmt->fetchAll();

// รายการรออนุมัติล่าสุด 5 รายการ
$pendingStmt = $pdo->query("
    SELECT b.*, r.room_name, r.building, r.floor
    FROM rb_bookings b
    JOIN rb_rooms r ON b.room_id = r.id
    WHERE b.status = 'pending'
    ORDER BY b.booking_date ASC, b.start_time ASC
    LIMIT 5
");
$recentPending = $pendingStmt->fetchAll();
?>

<div class="space-y-6">
    <!-- Top Action Bar -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">แผงควบคุมระบบจองห้องประชุม</h1>
            <p class="text-sm text-slate-500">ภาพรวมสถิติ คำขอที่รอดำเนินการ และการจัดการห้องประชุม</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="requests.php" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold transition shadow-sm">
                <i class="bi bi-list-check text-sm"></i> จัดการคำขอทั้งหมด
            </a>
            <a href="rooms.php" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-slate-800 hover:bg-slate-900 text-white text-xs font-bold transition shadow-sm">
                <i class="bi bi-door-closed text-sm"></i> จัดการห้องประชุม
            </a>
        </div>
    </div>

    <!-- Stat Cards Grid -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
        <div class="bg-white p-5 rounded-3xl border border-slate-200 shadow-sm">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold text-slate-500">คำขอทั้งหมด</span>
                <div class="w-8 h-8 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center text-sm">
                    <i class="bi bi-calendar3"></i>
                </div>
            </div>
            <div class="text-2xl font-extrabold text-slate-900 mt-2"><?= number_format((int)$stats['total_bookings']) ?></div>
            <div class="text-[11px] text-slate-400 mt-1">รายการตั้งแต่เริ่มต้น</div>
        </div>

        <div class="bg-white p-5 rounded-3xl border border-amber-200 shadow-sm bg-gradient-to-br from-white to-amber-50/40">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold text-amber-700">รออนุมัติ</span>
                <div class="w-8 h-8 rounded-lg bg-amber-100 text-amber-700 flex items-center justify-center text-sm font-bold">
                    <i class="bi bi-hourglass-split"></i>
                </div>
            </div>
            <div class="text-2xl font-extrabold text-amber-900 mt-2"><?= number_format((int)$stats['total_pending']) ?></div>
            <div class="text-[11px] text-amber-600 mt-1">ต้องพิจารณาเร่งด่วน</div>
        </div>

        <div class="bg-white p-5 rounded-3xl border border-emerald-200 shadow-sm bg-gradient-to-br from-white to-emerald-50/40">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold text-emerald-700">อนุมัติแล้ว</span>
                <div class="w-8 h-8 rounded-lg bg-emerald-100 text-emerald-700 flex items-center justify-center text-sm">
                    <i class="bi bi-check2-circle"></i>
                </div>
            </div>
            <div class="text-2xl font-extrabold text-emerald-900 mt-2"><?= number_format((int)$stats['total_approved']) ?></div>
            <div class="text-[11px] text-emerald-600 mt-1">ใช้งานตามกำหนด</div>
        </div>

        <div class="bg-white p-5 rounded-3xl border border-rose-200 shadow-sm bg-gradient-to-br from-white to-rose-50/40">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold text-rose-700">ไม่อนุมัติ</span>
                <div class="w-8 h-8 rounded-lg bg-rose-100 text-rose-700 flex items-center justify-center text-sm">
                    <i class="bi bi-x-circle"></i>
                </div>
            </div>
            <div class="text-2xl font-extrabold text-rose-900 mt-2"><?= number_format((int)$stats['total_rejected']) ?></div>
            <div class="text-[11px] text-rose-600 mt-1">ปฏิเสธหรือยกเลิก</div>
        </div>
    </div>

    <!-- Main Section: Chart & Pending List -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Chart: Room Usage -->
        <div class="lg:col-span-1 bg-white p-6 rounded-3xl border border-slate-200 shadow-sm flex flex-col">
            <h2 class="text-base font-bold text-slate-900 mb-2">สถิติการใช้งานแต่ละห้อง</h2>
            <p class="text-xs text-slate-500 mb-4">จำนวนครั้งที่มีการอนุมัติการใช้ห้องจริง</p>
            <div class="relative flex-1 flex items-center justify-center min-h-[240px]">
                <canvas id="roomUsageChart"></canvas>
            </div>
        </div>

        <!-- Pending Approvals Action Card -->
        <div class="lg:col-span-2 bg-white p-6 rounded-3xl border border-slate-200 shadow-sm flex flex-col">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h2 class="text-base font-bold text-slate-900">คำขอที่รอการอนุมัติ</h2>
                    <p class="text-xs text-slate-500">พิจารณาและอนุมัติการใช้ห้องประชุมเพื่อกันเวลา</p>
                </div>
                <a href="requests.php?status=pending" class="text-xs font-semibold text-blue-600 hover:text-blue-700">
                    ดูทั้งหมด (<?= (int)$stats['total_pending'] ?>) <i class="bi bi-arrow-right"></i>
                </a>
            </div>

            <?php if (empty($recentPending)): ?>
                <div class="flex-1 flex flex-col items-center justify-center py-12 text-slate-400">
                    <i class="bi bi-check2-all text-4xl text-emerald-500 mb-2"></i>
                    <p class="text-sm font-semibold text-slate-700">ไม่มีคำขอที่ค้างรออนุมัติ</p>
                    <p class="text-xs text-slate-400 mt-0.5">การพิจารณาคำขอทั้งหมดเรียบร้อยสมบูรณ์แล้ว</p>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto divide-y divide-slate-100 flex-1">
                    <?php foreach ($recentPending as $req): ?>
                        <div class="py-3.5 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                            <div class="space-y-1">
                                <div class="flex items-center gap-2">
                                    <span class="text-xs font-mono font-bold text-blue-600"><?= htmlspecialchars($req['booking_code']) ?></span>
                                    <span class="text-xs font-semibold text-slate-800"><?= htmlspecialchars($req['room_name']) ?></span>
                                </div>
                                <div class="text-sm font-medium text-slate-900"><?= htmlspecialchars($req['title']) ?></div>
                                <div class="text-xs text-slate-500 flex flex-wrap items-center gap-x-3 gap-y-1">
                                    <span><i class="bi bi-calendar-event mr-1 text-slate-400"></i><?= formatThaiDate($req['booking_date'], true) ?></span>
                                    <span><i class="bi bi-clock mr-1 text-slate-400"></i><?= formatTime($req['start_time']) ?> - <?= formatTime($req['end_time']) ?> น.</span>
                                    <span><i class="bi bi-person mr-1 text-slate-400"></i><?= htmlspecialchars($req['requester_name']) ?> (<?= htmlspecialchars($req['department']) ?>)</span>
                                </div>
                            </div>
                            <div class="flex items-center gap-2 self-end sm:self-center shrink-0">
                                <button onclick="quickApprove(<?= $req['id'] ?>)" class="px-3.5 py-1.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold transition shadow-sm">
                                    อนุมัติ
                                </button>
                                <button onclick="quickReject(<?= $req['id'] ?>)" class="px-3.5 py-1.5 rounded-xl border border-rose-200 text-rose-700 hover:bg-rose-50 text-xs font-semibold transition">
                                    ไม่อนุมัติ
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Chart.js Script -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const roomLabels = <?= json_encode(array_column($roomStats, 'room_name'), JSON_UNESCAPED_UNICODE) ?>;
const roomData = <?= json_encode(array_column($roomStats, 'booking_count')) ?>;

const ctx = document.getElementById('roomUsageChart').getContext('2d');
new Chart(ctx, {
    type: 'doughnut',
    data: {
        labels: roomLabels,
        datasets: [{
            data: roomData,
            backgroundColor: ['#3b82f6', '#10b981', '#8b5cf6', '#f59e0b', '#06b6d4', '#ec4899'],
            borderWidth: 2,
            borderColor: '#ffffff'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom',
                labels: { boxWidth: 12, font: { family: "'Prompt', sans-serif", size: 11 } }
            }
        }
    }
});

async function quickApprove(bookingId) {
    const confirm = await Swal.fire({
        title: 'ยืนยันการอนุมัติ?',
        text: 'ระบบจะทำการกันเวลาห้องประชุมนี้อย่างเป็นทางการ',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'ยืนยันอนุมัติ',
        cancelButtonText: 'ยกเลิก',
        confirmButtonColor: '#10b981'
    });
    if (!confirm.isConfirmed) return;

    try {
        const formData = new FormData();
        formData.append('booking_id', bookingId);
        formData.append('status', 'approved');

        const res = await fetch('../api/update_status.php', { method: 'POST', body: formData });
        const data = await res.json();

        if (res.ok && data.success) {
            Swal.fire({ icon: 'success', title: 'อนุมัติเรียบร้อย!', timer: 1500, showConfirmButton: false }).then(() => {
                location.reload();
            });
        } else {
            Swal.fire({ icon: 'error', title: 'ไม่สามารถอนุมัติได้', text: data.message });
        }
    } catch (e) {
        Swal.fire({ icon: 'error', title: 'เกิดข้อผิดพลาด', text: 'ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์' });
    }
}

async function quickReject(bookingId) {
    const { value: reason } = await Swal.fire({
        title: 'ระบุเหตุผลที่ไม่อนุมัติ',
        input: 'text',
        inputPlaceholder: 'เช่น ห้องปิดปรับปรุง หรือ ติดภารกิจราชการด่วน',
        showCancelButton: true,
        confirmButtonText: 'ยืนยันปฏิเสธ',
        cancelButtonText: 'ยกเลิก',
        confirmButtonColor: '#e11d48',
        inputValidator: (val) => {
            if (!val) return 'กรุณาระบุเหตุผล!';
        }
    });

    if (!reason) return;

    try {
        const formData = new FormData();
        formData.append('booking_id', bookingId);
        formData.append('status', 'rejected');
        formData.append('rejection_reason', reason);

        const res = await fetch('../api/update_status.php', { method: 'POST', body: formData });
        const data = await res.json();

        if (res.ok && data.success) {
            Swal.fire({ icon: 'success', title: 'บันทึกการไม่อนุมัติเรียบร้อย', timer: 1500, showConfirmButton: false }).then(() => {
                location.reload();
            });
        } else {
            Swal.fire({ icon: 'error', title: 'เกิดข้อผิดพลาด', text: data.message });
        }
    } catch (e) {
        Swal.fire({ icon: 'error', title: 'เกิดข้อผิดพลาด', text: 'ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์' });
    }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
