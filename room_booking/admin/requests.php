<?php
/**
 * room_booking/admin/requests.php
 * หน้าจัดการคำขอจองห้องประชุมทั้งหมด (กรอง ค้นหา อนุมัติ ไม่อนุมัติ)
 */

declare(strict_types=1);

$pageTitle = 'จัดการคำขอจองห้องประชุม';
require_once __DIR__ . '/../includes/functions.php';

requireRoomAdmin();

require_once __DIR__ . '/../includes/header.php';

$pdo = getRoomBookingPdo();

// รับค่าตัวกรอง
$filterStatus = $_GET['status'] ?? 'all';
$filterRoom   = isset($_GET['room_id']) && $_GET['room_id'] !== '' ? (int)$_GET['room_id'] : null;
$filterSearch = trim((string)($_GET['q'] ?? ''));

$sql = "
    SELECT b.*, r.room_name, r.building, r.floor, u.full_name as approved_by_name
    FROM rb_bookings b
    JOIN rb_rooms r ON b.room_id = r.id
    LEFT JOIN llw_users u ON b.approved_by = u.user_id
    WHERE 1=1
";
$params = [];

if ($filterStatus !== 'all') {
    $sql .= " AND b.status = ?";
    $params[] = $filterStatus;
}
if ($filterRoom !== null && $filterRoom > 0) {
    $sql .= " AND b.room_id = ?";
    $params[] = $filterRoom;
}
if ($filterSearch !== '') {
    $sql .= " AND (b.title LIKE ? OR b.requester_name LIKE ? OR b.booking_code LIKE ? OR b.department LIKE ?)";
    $like = "%{$filterSearch}%";
    $params = array_merge($params, [$like, $like, $like, $like]);
}

$sql .= " ORDER BY b.booking_date DESC, b.start_time DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$requests = $stmt->fetchAll();

// รายชื่อห้องสำหรับ Dropdown
$rooms = $pdo->query("SELECT id, room_name FROM rb_rooms ORDER BY id ASC")->fetchAll();
?>

<div class="space-y-6">
    <!-- Breadcrumb & Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-xs text-slate-500 mb-1">
                <a href="index.php" class="hover:text-blue-600">แผงควบคุม</a>
                <span>/</span>
                <span class="text-slate-800 font-medium">จัดการคำขอ</span>
            </div>
            <h1 class="text-2xl font-bold text-slate-900">จัดการคำขอจองห้องประชุม</h1>
        </div>
        <div class="flex items-center gap-2">
            <a href="export.php?status=<?= urlencode($filterStatus) ?>" class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl border border-slate-200 bg-white hover:bg-slate-50 text-slate-700 text-xs font-semibold shadow-sm transition">
                <i class="bi bi-file-earmark-excel text-emerald-600"></i> ส่งออก Excel
            </a>
            <a href="../book.php" class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold shadow-sm transition">
                <i class="bi bi-plus-lg"></i> จองในนามผู้ดูแล
            </a>
        </div>
    </div>

    <!-- Filters Bar -->
    <div class="bg-white p-4 rounded-3xl border border-slate-200 shadow-sm">
        <form method="GET" class="grid grid-cols-1 sm:grid-cols-4 gap-3">
            <div>
                <label class="block text-[11px] font-semibold text-slate-600 mb-1">สถานะคำขอ</label>
                <select name="status" class="w-full px-3 py-2 rounded-xl border border-slate-200 bg-slate-50 text-xs focus:ring-2 focus:ring-blue-500">
                    <option value="all" <?= $filterStatus === 'all' ? 'selected' : '' ?>>ทุกสถานะ</option>
                    <option value="pending" <?= $filterStatus === 'pending' ? 'selected' : '' ?>>⏳ รออนุมัติ</option>
                    <option value="approved" <?= $filterStatus === 'approved' ? 'selected' : '' ?>>✓ อนุมัติแล้ว</option>
                    <option value="rejected" <?= $filterStatus === 'rejected' ? 'selected' : '' ?>>✕ ไม่อนุมัติ</option>
                    <option value="cancelled" <?= $filterStatus === 'cancelled' ? 'selected' : '' ?>>ยกเลิก</option>
                </select>
            </div>

            <div>
                <label class="block text-[11px] font-semibold text-slate-600 mb-1">ห้องประชุม</label>
                <select name="room_id" class="w-full px-3 py-2 rounded-xl border border-slate-200 bg-slate-50 text-xs focus:ring-2 focus:ring-blue-500">
                    <option value="">ทุกห้องประชุม</option>
                    <?php foreach ($rooms as $r): ?>
                        <option value="<?= $r['id'] ?>" <?= $filterRoom === (int)$r['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($r['room_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="block text-[11px] font-semibold text-slate-600 mb-1">ค้นหาคำค้น</label>
                <input type="text" name="q" value="<?= htmlspecialchars($filterSearch) ?>" placeholder="รหัส, หัวข้อ, หรือชื่อผู้ขอ" class="w-full px-3 py-2 rounded-xl border border-slate-200 bg-slate-50 text-xs focus:ring-2 focus:ring-blue-500">
            </div>

            <div class="flex items-end gap-2">
                <button type="submit" class="w-full py-2 px-4 rounded-xl bg-slate-800 hover:bg-slate-900 text-white text-xs font-bold transition">
                    <i class="bi bi-search mr-1"></i> กรองข้อมูล
                </button>
                <a href="requests.php" class="py-2 px-3 rounded-xl border border-slate-200 hover:bg-slate-100 text-slate-600 text-xs transition" title="ล้างตัวกรอง">
                    <i class="bi bi-arrow-counterclockwise"></i>
                </a>
            </div>
        </form>
    </div>

    <!-- Requests Table -->
    <div class="bg-white rounded-3xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-sm">
                <thead>
                    <tr class="bg-slate-50/80 border-b border-slate-200 text-slate-600 text-xs">
                        <th class="py-3.5 px-4 font-semibold">รหัส / ห้อง</th>
                        <th class="py-3.5 px-4 font-semibold">วันที่ / เวลา</th>
                        <th class="py-3.5 px-4 font-semibold">หัวข้อการประชุม / วัตถุประสงค์</th>
                        <th class="py-3.5 px-4 font-semibold">ผู้ขอใช้ห้อง</th>
                        <th class="py-3.5 px-4 font-semibold text-center">สถานะ</th>
                        <th class="py-3.5 px-4 font-semibold text-right">การจัดการ</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (empty($requests)): ?>
                        <tr>
                            <td colspan="6" class="py-12 text-center text-slate-400">
                                <i class="bi bi-inbox text-3xl mb-2 block"></i>
                                ไม่พบรายการคำขอจองตามเงื่อนไขที่เลือก
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($requests as $r): ?>
                            <tr class="hover:bg-slate-50/70 transition">
                                <td class="py-3.5 px-4">
                                    <div class="font-mono text-xs font-bold text-blue-700"><?= htmlspecialchars($r['booking_code']) ?></div>
                                    <div class="font-medium text-slate-800 text-xs mt-0.5"><?= htmlspecialchars($r['room_name']) ?></div>
                                </td>
                                <td class="py-3.5 px-4 whitespace-nowrap">
                                    <div class="font-semibold text-slate-800 text-xs"><?= formatThaiDate($r['booking_date'], true) ?></div>
                                    <div class="text-[11px] text-slate-500 font-mono"><?= formatTime($r['start_time']) ?> - <?= formatTime($r['end_time']) ?> น.</div>
                                </td>
                                <td class="py-3.5 px-4 max-w-xs">
                                    <div class="font-semibold text-slate-900 text-xs truncate" title="<?= htmlspecialchars($r['title']) ?>">
                                        <?= htmlspecialchars($r['title']) ?>
                                    </div>
                                    <div class="text-[11px] text-slate-500 mt-0.5 flex items-center gap-2">
                                        <span><i class="bi bi-people"></i> <?= $r['attendee_count'] ?> คน</span>
                                        <?php if ($r['note']): ?>
                                            <span class="truncate max-w-[150px]" title="<?= htmlspecialchars($r['note']) ?>">
                                                <i class="bi bi-chat-left-dots"></i> <?= htmlspecialchars($r['note']) ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($r['status'] === 'rejected' && $r['rejection_reason']): ?>
                                        <div class="text-[11px] text-rose-600 mt-1">เหตุผล: <?= htmlspecialchars($r['rejection_reason']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3.5 px-4">
                                    <div class="text-xs font-medium text-slate-900"><?= htmlspecialchars($r['requester_name']) ?></div>
                                    <div class="text-[11px] text-slate-500"><?= htmlspecialchars($r['department'] ?: '-') ?></div>
                                    <div class="text-[10px] text-slate-400 font-mono"><?= htmlspecialchars($r['requester_phone'] ?: '-') ?></div>
                                </td>
                                <td class="py-3.5 px-4 text-center whitespace-nowrap">
                                    <?= renderStatusBadge($r['status']) ?>
                                </td>
                                <td class="py-3.5 px-4 text-right whitespace-nowrap">
                                    <div class="flex items-center justify-end gap-1.5">
                                        <?php if ($r['status'] === 'pending'): ?>
                                            <button onclick="changeStatus(<?= $r['id'] ?>, 'approved')" class="px-2.5 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold transition shadow-sm" title="อนุมัติ">
                                                <i class="bi bi-check-lg"></i> อนุมัติ
                                            </button>
                                            <button onclick="rejectWithReason(<?= $r['id'] ?>)" class="px-2.5 py-1.5 rounded-lg border border-rose-200 text-rose-700 hover:bg-rose-50 text-xs font-semibold transition" title="ไม่อนุมัติ">
                                                <i class="bi bi-x-lg"></i>
                                            </button>
                                        <?php elseif ($r['status'] === 'approved'): ?>
                                            <a href="../api/export_ics.php?id=<?= $r['id'] ?>" class="p-1.5 rounded-lg text-slate-600 hover:bg-slate-100 transition" title="ดาวน์โหลด .ics">
                                                <i class="bi bi-calendar-plus text-sm"></i>
                                            </a>
                                            <button onclick="rejectWithReason(<?= $r['id'] ?>)" class="px-2 py-1 rounded-lg border border-slate-200 text-rose-600 hover:bg-rose-50 text-xs transition" title="ยกเลิกการอนุมัติ">
                                                ยกเลิก
                                            </button>
                                        <?php elseif ($r['status'] === 'rejected'): ?>
                                            <button onclick="changeStatus(<?= $r['id'] ?>, 'approved')" class="px-2.5 py-1 rounded-lg border border-blue-200 text-blue-700 hover:bg-blue-50 text-xs font-semibold transition" title="นำกลับมาอนุมัติใหม่">
                                                <i class="bi bi-arrow-counterclockwise"></i> อนุมัติใหม่
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
async function changeStatus(bookingId, status) {
    const confirm = await Swal.fire({
        title: 'ยืนยันการเปลี่ยนสถานะ?',
        text: 'ระบบจะตรวจสอบการกันเวลาและบันทึกประวัติการกระทำ',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'ยืนยัน',
        cancelButtonText: 'ยกเลิก',
        confirmButtonColor: '#2563eb'
    });
    if (!confirm.isConfirmed) return;

    try {
        const formData = new FormData();
        formData.append('booking_id', bookingId);
        formData.append('status', status);

        const res = await fetch('../api/update_status.php', { method: 'POST', body: formData });
        const data = await res.json();

        if (res.ok && data.success) {
            Swal.fire({ icon: 'success', title: data.message, timer: 1500, showConfirmButton: false }).then(() => {
                location.reload();
            });
        } else {
            Swal.fire({ icon: 'error', title: 'ไม่สามารถดำเนินการได้', text: data.message });
        }
    } catch (e) {
        Swal.fire({ icon: 'error', title: 'เกิดข้อผิดพลาด', text: 'ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์' });
    }
}

async function rejectWithReason(bookingId) {
    const { value: reason } = await Swal.fire({
        title: 'ระบุเหตุผลที่ไม่อนุมัติ / ยกเลิก',
        input: 'text',
        inputPlaceholder: 'เช่น ห้องติดภารกิจด่วน หรือซ้ำซ้อนกับกิจกรรมอื่น',
        showCancelButton: true,
        confirmButtonText: 'ยืนยัน',
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
            Swal.fire({ icon: 'success', title: 'บันทึกเรียบร้อย', timer: 1500, showConfirmButton: false }).then(() => {
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
