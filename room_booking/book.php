<?php
/**
 * room_booking/book.php
 * แบบฟอร์มการขอจองห้องประชุม พร้อมระบบตรวจสอบเวลาว่างแบบ Real-time
 */

declare(strict_types=1);

$pageTitle = 'แบบฟอร์มขอจองห้องประชุม';
require_once __DIR__ . '/includes/header.php';

$pdo = getRoomBookingPdo();
syncSchoolRooms($pdo);

// ดึงรายการห้องประชุมทั้งหมด
$rooms = $pdo->query("SELECT * FROM rb_rooms WHERE is_active = 1 ORDER BY id ASC")->fetchAll();

$prefillDate = $_GET['date'] ?? date('Y-m-d');
$prefillRoom = isset($_GET['room_id']) ? (int)$_GET['room_id'] : (int)($rooms[0]['id'] ?? 1);

// ดึงข้อมูลผู้ใช้งานที่ล็อกอิน
$userName = $_SESSION['fullname'] ?? '';
$userPhone = $_SESSION['phone'] ?? '';
$userDept = $_SESSION['department'] ?? '';
?>

<div class="max-w-4xl mx-auto">
    <!-- Breadcrumb & Page Title -->
    <div class="mb-6">
        <a href="index.php" class="inline-flex items-center gap-1.5 text-xs text-slate-500 hover:text-blue-600 mb-2 transition">
            <i class="bi bi-chevron-left"></i> กลับไปยังปฏิทิน
        </a>
        <h1 class="text-2xl font-bold text-slate-900">แบบฟอร์มขอจองห้องประชุม</h1>
        <p class="text-sm text-slate-500">กรอกรายละเอียดเพื่อส่งคำขออนุมัติใช้งานห้องประชุมและอุปกรณ์</p>
    </div>

    <!-- Booking Form Card -->
    <div class="bg-white rounded-3xl border border-slate-200 shadow-sm p-6 sm:p-8">
        <form id="bookingForm" onsubmit="handleFormSubmit(event)">
            <?= csrf_field() ?>

            <!-- 1. ข้อมูลห้องและวันเวลา -->
            <div class="border-b border-slate-100 pb-6 mb-6">
                <h2 class="text-base font-bold text-slate-900 mb-4 flex items-center gap-2">
                    <span class="w-7 h-7 rounded-lg bg-blue-100 text-blue-700 flex items-center justify-center text-xs font-extrabold">1</span>
                    เลือกห้องประชุมและวันเวลาที่ต้องการ
                </h2>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 sm:gap-6">
                    <!-- ห้องประชุม -->
                    <div>
                        <label for="room_id" class="block text-xs font-semibold text-slate-700 mb-1.5">ห้องประชุม <span class="text-rose-500">*</span></label>
                        <select id="room_id" name="room_id" required onchange="triggerAvailabilityCheck()" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 bg-slate-50 text-slate-800 text-sm focus:ring-2 focus:ring-blue-500">
                            <?php foreach ($rooms as $r): ?>
                                <option value="<?= $r['id'] ?>" <?= $r['id'] === $prefillRoom ? 'selected' : '' ?> data-capacity="<?= $r['capacity'] ?>" data-facilities="<?= htmlspecialchars($r['facilities'] ?? '') ?>" data-location="<?= htmlspecialchars("{$r['building']} {$r['floor']}") ?>">
                                    <?= htmlspecialchars($r['room_name']) ?> (ความจุ <?= $r['capacity'] ?> คน)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div id="roomDetailsNotice" class="mt-2 text-xs text-slate-500 bg-blue-50/60 p-2.5 rounded-xl border border-blue-100">
                            <!-- Populated by JS -->
                        </div>
                    </div>

                    <!-- วันที่ใช้งาน -->
                    <div>
                        <label for="booking_date" class="block text-xs font-semibold text-slate-700 mb-1.5">วันที่ต้องการใช้ <span class="text-rose-500">*</span></label>
                        <input type="date" id="booking_date" name="booking_date" value="<?= htmlspecialchars($prefillDate) ?>" min="<?= date('Y-m-d') ?>" required onchange="triggerAvailabilityCheck()" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 bg-slate-50 text-slate-800 text-sm focus:ring-2 focus:ring-blue-500">
                        <div class="text-[11px] text-slate-400 mt-1">ไม่อนุญาตให้เลือกวันที่ย้อนหลังในอดีต</div>
                    </div>

                    <!-- เวลาเริ่มต้น -->
                    <div>
                        <label for="start_time" class="block text-xs font-semibold text-slate-700 mb-1.5">เวลาเริ่มต้น <span class="text-rose-500">*</span></label>
                        <input type="time" id="start_time" name="start_time" value="09:00" required onchange="triggerAvailabilityCheck()" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 bg-slate-50 text-slate-800 text-sm focus:ring-2 focus:ring-blue-500">
                    </div>

                    <!-- เวลาสิ้นสุด -->
                    <div>
                        <label for="end_time" class="block text-xs font-semibold text-slate-700 mb-1.5">เวลาสิ้นสุด <span class="text-rose-500">*</span></label>
                        <input type="time" id="end_time" name="end_time" value="12:00" required onchange="triggerAvailabilityCheck()" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 bg-slate-50 text-slate-800 text-sm focus:ring-2 focus:ring-blue-500">
                    </div>
                </div>

                <!-- Live Availability Badge -->
                <div class="mt-4">
                    <div id="availabilityStatus" class="p-3.5 rounded-2xl text-xs font-medium flex items-center gap-2 bg-slate-100 text-slate-600 border border-slate-200">
                        <i class="bi bi-info-circle"></i> กำลังตรวจสอบความพร้อมของห้อง...
                    </div>
                </div>
            </div>

            <!-- 2. ข้อมูลการประชุมและกิจกรรม -->
            <div class="border-b border-slate-100 pb-6 mb-6">
                <h2 class="text-base font-bold text-slate-900 mb-4 flex items-center gap-2">
                    <span class="w-7 h-7 rounded-lg bg-blue-100 text-blue-700 flex items-center justify-center text-xs font-extrabold">2</span>
                    รายละเอียดการประชุม / กิจกรรม
                </h2>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 sm:gap-6">
                    <div class="sm:col-span-2">
                        <label for="title" class="block text-xs font-semibold text-slate-700 mb-1.5">หัวข้อการประชุม / ชื่อกิจกรรม <span class="text-rose-500">*</span></label>
                        <input type="text" id="title" name="title" placeholder="เช่น ประชุมคณะกรรมการสถานศึกษาขั้นพื้นฐาน ครั้งที่ 1/2570" required class="w-full px-4 py-2.5 rounded-xl border border-slate-200 bg-slate-50 text-slate-800 text-sm focus:ring-2 focus:ring-blue-500">
                    </div>

                    <div>
                        <label for="department" class="block text-xs font-semibold text-slate-700 mb-1.5">กลุ่มสาระการเรียนรู้ / ฝ่ายงาน <span class="text-rose-500">*</span></label>
                        <input type="text" id="department" name="department" value="<?= htmlspecialchars($userDept) ?>" placeholder="เช่น กลุ่มบริหารวิชาการ / กลุ่มสาระฯ วิทยาศาสตร์" required class="w-full px-4 py-2.5 rounded-xl border border-slate-200 bg-slate-50 text-slate-800 text-sm focus:ring-2 focus:ring-blue-500">
                    </div>

                    <div>
                        <label for="attendee_count" class="block text-xs font-semibold text-slate-700 mb-1.5">จำนวนผู้เข้าร่วม (คน) <span class="text-rose-500">*</span></label>
                        <input type="number" id="attendee_count" name="attendee_count" value="20" min="1" max="500" required class="w-full px-4 py-2.5 rounded-xl border border-slate-200 bg-slate-50 text-slate-800 text-sm focus:ring-2 focus:ring-blue-500">
                    </div>

                    <div class="sm:col-span-2">
                        <label for="note" class="block text-xs font-semibold text-slate-700 mb-1.5">อุปกรณ์หรือความต้องการเพิ่มเติม (ถ้ามี)</label>
                        <textarea id="note" name="note" rows="2" placeholder="เช่น ขอเปิดแอร์ล่วงหน้า 15 นาที, ต้องการไมโครโฟนไร้สาย 4 ตัว, ปลั๊กพ่วง" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 bg-slate-50 text-slate-800 text-sm focus:ring-2 focus:ring-blue-500"></textarea>
                    </div>
                </div>
            </div>

            <!-- 3. ข้อมูลผู้ขอใช้ห้อง -->
            <div class="mb-8">
                <h2 class="text-base font-bold text-slate-900 mb-4 flex items-center gap-2">
                    <span class="w-7 h-7 rounded-lg bg-blue-100 text-blue-700 flex items-center justify-center text-xs font-extrabold">3</span>
                    ข้อมูลผู้ประสานงาน / ผู้ขอใช้ห้อง
                </h2>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 sm:gap-6">
                    <div>
                        <label for="requester_name" class="block text-xs font-semibold text-slate-700 mb-1.5">ชื่อ-นามสกุล ผู้ขอใช้ห้อง <span class="text-rose-500">*</span></label>
                        <input type="text" id="requester_name" name="requester_name" value="<?= htmlspecialchars($userName) ?>" placeholder="ระบุชื่อและนามสกุล" required class="w-full px-4 py-2.5 rounded-xl border border-slate-200 bg-slate-50 text-slate-800 text-sm focus:ring-2 focus:ring-blue-500">
                    </div>

                    <div>
                        <label for="requester_phone" class="block text-xs font-semibold text-slate-700 mb-1.5">เบอร์โทรศัพท์ติดต่อ <span class="text-rose-500">*</span></label>
                        <input type="tel" id="requester_phone" name="requester_phone" value="<?= htmlspecialchars($userPhone) ?>" placeholder="เช่น 081-234-5678" required class="w-full px-4 py-2.5 rounded-xl border border-slate-200 bg-slate-50 text-slate-800 text-sm focus:ring-2 focus:ring-blue-500">
                    </div>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="flex items-center justify-end gap-3 pt-6 border-t border-slate-100">
                <a href="index.php" class="px-5 py-2.5 rounded-xl border border-slate-200 hover:bg-slate-50 text-slate-700 text-sm font-semibold transition">
                    ยกเลิก
                </a>
                <button type="submit" id="submitBtn" class="inline-flex items-center gap-2 px-7 py-2.5 rounded-xl bg-blue-600 hover:bg-blue-700 text-white text-sm font-bold shadow-md shadow-blue-500/20 transition active:scale-95">
                    <i class="bi bi-check2-circle text-lg"></i>
                    <span>ยืนยันส่งคำขอจอง</span>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function updateRoomNotice() {
    const sel = document.getElementById('room_id');
    const opt = sel.options[sel.selectedIndex];
    const loc = opt.getAttribute('data-location') || '-';
    const fac = opt.getAttribute('data-facilities') || '-';

    document.getElementById('roomDetailsNotice').innerHTML = `
        <div class="flex items-start gap-2">
            <i class="bi bi-geo-alt-fill text-blue-600 mt-0.5"></i>
            <div>
                <strong>สถานที่:</strong> ${loc} <br>
                <strong>สิ่งอำนวยความสะดวก:</strong> ${fac}
            </div>
        </div>
    `;
}

let checkTimeout = null;
function triggerAvailabilityCheck() {
    clearTimeout(checkTimeout);
    checkTimeout = setTimeout(performAvailabilityCheck, 300);
}

async function performAvailabilityCheck() {
    const roomId = document.getElementById('room_id').value;
    const date = document.getElementById('booking_date').value;
    const startTime = document.getElementById('start_time').value;
    const endTime = document.getElementById('end_time').value;

    const statusEl = document.getElementById('availabilityStatus');
    const submitBtn = document.getElementById('submitBtn');

    updateRoomNotice();

    if (!roomId || !date || !startTime || !endTime) return;

    if (startTime >= endTime) {
        statusEl.className = 'p-3.5 rounded-2xl text-xs font-medium flex items-center gap-2 bg-rose-50 text-rose-800 border border-rose-200';
        statusEl.innerHTML = '<i class="bi bi-exclamation-triangle-fill text-rose-600 text-sm"></i> เวลาเริ่มต้นต้องน้อยกว่าเวลาสิ้นสุด';
        submitBtn.disabled = true;
        return;
    }

    statusEl.className = 'p-3.5 rounded-2xl text-xs font-medium flex items-center gap-2 bg-slate-50 text-slate-600 border border-slate-200';
    statusEl.innerHTML = '<i class="bi bi-arrow-repeat animate-spin text-sm"></i> กำลังตรวจสอบเวลาว่าง...';

    try {
        const formData = new FormData();
        formData.append('room_id', roomId);
        formData.append('date', date);
        formData.append('start_time', startTime);
        formData.append('end_time', endTime);

        const res = await fetch('api/check_availability.php', {
            method: 'POST',
            body: formData
        });
        const data = await res.json();

        if (data.available) {
            statusEl.className = 'p-3.5 rounded-2xl text-xs font-medium flex items-center gap-2 bg-emerald-50 text-emerald-800 border border-emerald-200';
            statusEl.innerHTML = '<i class="bi bi-check-circle-fill text-emerald-600 text-sm"></i> ห้องว่าง สามารถจองช่วงเวลานี้ได้';
            submitBtn.disabled = false;
        } else {
            statusEl.className = 'p-3.5 rounded-2xl text-xs font-medium flex items-center gap-2 bg-rose-50 text-rose-800 border border-rose-200';
            statusEl.innerHTML = `<i class="bi bi-x-circle-fill text-rose-600 text-sm"></i> ${data.message}`;
            submitBtn.disabled = true;
        }
    } catch (err) {
        console.error(err);
    }
}

async function handleFormSubmit(e) {
    e.preventDefault();
    const submitBtn = document.getElementById('submitBtn');
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="bi bi-arrow-repeat animate-spin"></i> กำลังส่งข้อมูล...';

    const form = document.getElementById('bookingForm');
    const formData = new FormData(form);

    try {
        const res = await fetch('api/submit_booking.php', {
            method: 'POST',
            body: formData
        });
        const data = await res.json();

        if (res.ok && data.success) {
            Swal.fire({
                icon: 'success',
                title: 'ส่งคำขอจองสำเร็จ!',
                text: `รหัสการจอง: ${data.booking_code} (สถานะ: รออนุมัติ)`,
                confirmButtonText: 'ตกลง',
                confirmButtonColor: '#2563eb'
            }).then(() => {
                window.location.href = 'index.php';
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'ไม่สามารถส่งคำขอได้',
                text: data.message || 'เกิดข้อผิดพลาดในการบันทึกข้อมูล',
                confirmButtonText: 'ตกลง',
                confirmButtonColor: '#e11d48'
            });
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="bi bi-check2-circle text-lg"></i> ยืนยันส่งคำขอจอง';
        }
    } catch (err) {
        Swal.fire({
            icon: 'error',
            title: 'เกิดข้อผิดพลาด',
            text: 'ไม่สามารถเชื่อมต่อกับเซิร์ฟเวอร์ได้',
            confirmButtonText: 'ตกลง'
        });
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="bi bi-check2-circle text-lg"></i> ยืนยันส่งคำขอจอง';
    }
}

// Initial check
updateRoomNotice();
performAvailabilityCheck();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
