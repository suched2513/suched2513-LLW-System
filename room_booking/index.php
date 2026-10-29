<?php
/**
 * room_booking/index.php
 * หน้าหลักระบบจองห้องประชุม (ปฏิทินรายเดือนและตารางการใช้ห้อง)
 */

declare(strict_types=1);

$pageTitle = 'ปฏิทินการใช้ห้องประชุม';
require_once __DIR__ . '/includes/header.php';

$pdo = getRoomBookingPdo();
syncSchoolRooms($pdo);

// ดึงรายการห้องประชุมที่เปิดใช้งาน
$roomsStmt = $pdo->query("SELECT * FROM rb_rooms WHERE is_active = 1 ORDER BY id ASC");
$rooms = $roomsStmt->fetchAll();

// สถิติด่วนประจำวัน
$today = date('Y-m-d');
$todayCountStmt = $pdo->prepare("SELECT COUNT(*) FROM rb_bookings WHERE booking_date = ? AND status IN ('pending', 'approved')");
$todayCountStmt->execute([$today]);
$todayBookings = (int)$todayCountStmt->fetchColumn();

$pendingCountStmt = $pdo->query("SELECT COUNT(*) FROM rb_bookings WHERE status = 'pending'");
$pendingBookings = (int)$pendingCountStmt->fetchColumn();

$currentMonth = date('Y-m');
?>

<!-- Hero Banner & Stats -->
<div class="mb-8">
    <div class="bg-gradient-to-r from-blue-700 via-indigo-700 to-blue-900 rounded-3xl p-6 sm:p-8 text-white shadow-xl relative overflow-hidden">
        <div class="absolute -right-10 -bottom-10 opacity-10 text-9xl">
            <i class="bi bi-door-open-fill"></i>
        </div>
        <div class="relative z-10 flex flex-col md:flex-row md:items-center justify-between gap-6">
            <div>
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-medium bg-blue-500/30 text-blue-100 border border-blue-400/30 mb-3">
                    <i class="bi bi-calendar-event"></i> ตารางการใช้ห้องประชุมแบบ Real-time
                </span>
                <h1 class="text-2xl sm:text-3xl font-extrabold tracking-tight">ระบบจองห้องประชุมและห้องพิเศษ</h1>
                <p class="mt-2 text-sm text-blue-100 max-w-xl">
                    ตรวจสอบตารางเวลาว่าง จองห้องประชุมออนไลน์ พร้อมระบบป้องกันเวลาชนกันอัตโนมัติ สำหรับบุคลากรโรงเรียนละลมวิทยา
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <a href="book.php" class="inline-flex items-center gap-2 px-6 py-3.5 rounded-2xl bg-white text-blue-900 hover:bg-blue-50 font-bold text-sm shadow-lg shadow-black/10 transition transform active:scale-95">
                    <i class="bi bi-calendar-plus text-lg text-blue-600"></i>
                    <span>ส่งคำขอจองห้อง</span>
                </a>
            </div>
        </div>

        <!-- Quick Stats Cards Inside Hero -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 sm:gap-4 mt-6 pt-6 border-t border-white/10">
            <div class="bg-white/10 backdrop-blur-md rounded-2xl p-3.5 border border-white/10">
                <div class="text-[11px] text-blue-200">ห้องทั้งหมด</div>
                <div class="text-2xl font-bold mt-1"><?= count($rooms) ?> <span class="text-xs font-normal text-blue-200">ห้อง</span></div>
            </div>
            <div class="bg-white/10 backdrop-blur-md rounded-2xl p-3.5 border border-white/10">
                <div class="text-[11px] text-blue-200">การใช้งานวันนี้</div>
                <div class="text-2xl font-bold mt-1 text-emerald-300"><?= $todayBookings ?> <span class="text-xs font-normal text-blue-200">รายการ</span></div>
            </div>
            <div class="bg-white/10 backdrop-blur-md rounded-2xl p-3.5 border border-white/10">
                <div class="text-[11px] text-blue-200">รออนุมัติ</div>
                <div class="text-2xl font-bold mt-1 text-amber-300"><?= $pendingBookings ?> <span class="text-xs font-normal text-blue-200">รายการ</span></div>
            </div>
            <div class="bg-white/10 backdrop-blur-md rounded-2xl p-3.5 border border-white/10">
                <div class="text-[11px] text-blue-200">วันที่ปัจจุบัน</div>
                <div class="text-sm font-semibold mt-2 text-white truncate"><?= formatThaiDate($today, true) ?></div>
            </div>
        </div>
    </div>
</div>

<!-- Controls & Filters Bar -->
<div class="bg-white rounded-2xl p-4 border border-slate-200 shadow-sm mb-6 flex flex-col sm:flex-row items-center justify-between gap-4">
    <!-- Month Navigation -->
    <div class="flex items-center gap-2 w-full sm:w-auto">
        <button id="prevMonthBtn" class="p-2 rounded-xl border border-slate-200 hover:bg-slate-100 text-slate-700 transition" title="เดือนก่อนหน้า">
            <i class="bi bi-chevron-left"></i>
        </button>
        <div id="currentMonthLabel" class="text-base font-bold text-slate-900 min-w-[180px] text-center">
            กำลังโหลด...
        </div>
        <button id="nextMonthBtn" class="p-2 rounded-xl border border-slate-200 hover:bg-slate-100 text-slate-700 transition" title="เดือนถัดไป">
            <i class="bi bi-chevron-right"></i>
        </button>
        <button id="todayBtn" class="px-3 py-1.5 rounded-xl border border-blue-200 text-blue-700 hover:bg-blue-50 text-xs font-medium transition ml-1">
            วันนี้
        </button>
    </div>

    <!-- Room Filter Dropdown -->
    <div class="flex items-center gap-3 w-full sm:w-auto">
        <label for="roomFilter" class="text-xs font-medium text-slate-600 whitespace-nowrap">เลือกห้อง:</label>
        <select id="roomFilter" class="w-full sm:w-64 px-3.5 py-2 rounded-xl border border-slate-200 bg-slate-50 text-slate-800 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            <option value="">ทุกห้องประชุม</option>
            <?php foreach ($rooms as $r): ?>
                <option value="<?= $r['id'] ?>"><?= htmlspecialchars($r['room_name']) ?> (ความจุ <?= $r['capacity'] ?> คน)</option>
            <?php endforeach; ?>
        </select>
    </div>
</div>

<!-- Interactive Calendar Container -->
<div class="bg-white rounded-3xl border border-slate-200 shadow-sm overflow-hidden mb-8">
    <!-- Days Header (อาทิตย์ - เสาร์) -->
    <div class="grid grid-cols-7 border-b border-slate-200 bg-slate-50 text-center text-xs font-semibold text-slate-600 py-3">
        <div class="text-rose-600">อาทิตย์</div>
        <div>จันทร์</div>
        <div>อังคาร</div>
        <div>พุธ</div>
        <div>พฤหัสบดี</div>
        <div>ศุกร์</div>
        <div class="text-blue-600">เสาร์</div>
    </div>

    <!-- Calendar Grid Days (Dynamically Rendered via JS) -->
    <div id="calendarGrid" class="grid grid-cols-7 divide-x divide-y divide-slate-100 min-h-[500px]">
        <!-- Rendered by JS -->
    </div>
</div>

<!-- Event Detail Modal -->
<div id="eventModal" class="fixed inset-0 z-50 hidden bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl max-w-lg w-full p-6 shadow-2xl border border-slate-100 transform transition-all">
        <div class="flex items-start justify-between pb-4 border-b border-slate-100">
            <div>
                <span id="modalStatusBadge"></span>
                <h3 id="modalTitle" class="text-lg font-bold text-slate-900 mt-2"></h3>
                <p id="modalRoomName" class="text-xs text-blue-600 font-medium"></p>
            </div>
            <button onclick="closeModal()" class="text-slate-400 hover:text-slate-600 p-1.5 rounded-lg hover:bg-slate-100">
                <i class="bi bi-x-lg text-lg"></i>
            </button>
        </div>

        <div class="py-4 space-y-3 text-sm">
            <div class="flex items-center gap-2.5 text-slate-700">
                <i class="bi bi-calendar-event text-blue-600 text-base"></i>
                <span class="font-medium text-slate-500 w-24">วันที่:</span>
                <span id="modalDate" class="font-semibold text-slate-800"></span>
            </div>
            <div class="flex items-center gap-2.5 text-slate-700">
                <i class="bi bi-clock text-blue-600 text-base"></i>
                <span class="font-medium text-slate-500 w-24">เวลา:</span>
                <span id="modalTime" class="font-semibold text-slate-800"></span>
            </div>
            <div class="flex items-center gap-2.5 text-slate-700">
                <i class="bi bi-person text-blue-600 text-base"></i>
                <span class="font-medium text-slate-500 w-24">ผู้ขอใช้:</span>
                <span id="modalRequester" class="text-slate-800"></span>
            </div>
            <div class="flex items-center gap-2.5 text-slate-700">
                <i class="bi bi-building text-blue-600 text-base"></i>
                <span class="font-medium text-slate-500 w-24">กลุ่มสาระ/ฝ่าย:</span>
                <span id="modalDept" class="text-slate-800"></span>
            </div>
            <div class="flex items-center gap-2.5 text-slate-700">
                <i class="bi bi-people text-blue-600 text-base"></i>
                <span class="font-medium text-slate-500 w-24">จำนวนคน:</span>
                <span id="modalAttendees" class="text-slate-800"></span>
            </div>
        </div>

        <div class="pt-4 border-t border-slate-100 flex items-center justify-between gap-3">
            <a id="modalIcsBtn" href="#" class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl text-xs font-semibold bg-slate-100 hover:bg-slate-200 text-slate-700 transition">
                <i class="bi bi-calendar-plus text-blue-600"></i> เพิ่มลงปฏิทิน (.ics)
            </a>
            <button onclick="closeModal()" class="px-5 py-2 rounded-xl text-xs font-semibold bg-blue-600 hover:bg-blue-700 text-white transition">
                ปิด
            </button>
        </div>
    </div>
</div>

<script>
let currentYear = <?= (int)date('Y') ?>;
let currentMonth = <?= (int)date('n') ?>; // 1-12
let cachedEvents = [];

const thaiMonths = ['', 'มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน', 'พฤษภาคม', 'มิถุนายน', 'กรกฎาคม', 'สิงหาคม', 'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม'];

async function fetchEvents() {
    const monthStr = `${currentYear}-${String(currentMonth).padStart(2, '0')}`;
    const roomId = document.getElementById('roomFilter').value;
    const url = `api/calendar.php?month=${monthStr}&room_id=${encodeURIComponent(roomId)}`;

    try {
        const res = await fetch(url);
        const data = await res.json();
        if (data.success) {
            cachedEvents = data.events || [];
            renderCalendar();
        }
    } catch (err) {
        console.error('Error fetching calendar events:', err);
    }
}

function renderCalendar() {
    document.getElementById('currentMonthLabel').textContent = `${thaiMonths[currentMonth]} ${currentYear + 543}`;

    const grid = document.getElementById('calendarGrid');
    grid.innerHTML = '';

    const firstDayIndex = new Date(currentYear, currentMonth - 1, 1).getDay(); // 0 = Sun
    const totalDays = new Date(currentYear, currentMonth, 0).getDate();
    const prevMonthDays = new Date(currentYear, currentMonth - 1, 0).getDate();

    const todayStr = '<?= $today ?>';

    // 1. Previous month trailing days
    for (let i = firstDayIndex - 1; i >= 0; i--) {
        const dayNum = prevMonthDays - i;
        const cell = document.createElement('div');
        cell.className = 'p-2 min-h-[100px] bg-slate-50/50 text-slate-300 text-xs font-medium';
        cell.innerHTML = `<span>${dayNum}</span>`;
        grid.appendChild(cell);
    }

    // 2. Current month days
    for (let day = 1; day <= totalDays; day++) {
        const dateStr = `${currentYear}-${String(currentMonth).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
        const isToday = (dateStr === todayStr);

        const dayEvents = cachedEvents.filter(e => e.date === dateStr);

        const cell = document.createElement('div');
        cell.className = `p-2 min-h-[110px] sm:min-h-[120px] transition hover:bg-blue-50/30 flex flex-col ${isToday ? 'bg-blue-50/40' : 'bg-white'}`;

        let headerHtml = `<div class="flex items-center justify-between mb-1.5">
            <span class="text-xs font-bold ${isToday ? 'w-6 h-6 rounded-full bg-blue-600 text-white flex items-center justify-center -ml-1' : 'text-slate-700'}">${day}</span>
            <a href="book.php?date=${dateStr}" class="text-[10px] text-slate-400 hover:text-blue-600 hover:bg-blue-100 p-0.5 rounded transition opacity-0 sm:group-hover:opacity-100" title="จองวันนี้">
                <i class="bi bi-plus"></i>
            </a>
        </div>`;

        let eventsHtml = '<div class="space-y-1 flex-1 overflow-y-auto max-h-[85px]">';
        dayEvents.forEach(evt => {
            const isPending = (evt.status === 'pending');
            const bgClass = isPending ? 'bg-amber-100 text-amber-900 border-amber-300' : 'bg-blue-100 text-blue-900 border-blue-300';
            eventsHtml += `
                <div onclick="openModal(${evt.id})" class="cursor-pointer text-[11px] p-1 rounded-md border truncate font-medium transition hover:shadow-sm ${bgClass}" title="${evt.raw_title} (${evt.time_range})">
                    <span class="font-bold">${evt.time_range.split(' ')[0]}</span> ${evt.raw_title}
                </div>
            `;
        });
        eventsHtml += '</div>';

        cell.innerHTML = headerHtml + eventsHtml;
        grid.appendChild(cell);
    }

    // 3. Next month leading days
    const totalCells = firstDayIndex + totalDays;
    const remainingCells = (Math.ceil(totalCells / 7) * 7) - totalCells;
    for (let day = 1; day <= remainingCells; day++) {
        const cell = document.createElement('div');
        cell.className = 'p-2 min-h-[100px] bg-slate-50/50 text-slate-300 text-xs font-medium';
        cell.innerHTML = `<span>${day}</span>`;
        grid.appendChild(cell);
    }
}

function openModal(eventId) {
    const evt = cachedEvents.find(e => e.id === eventId);
    if (!evt) return;

    document.getElementById('modalTitle').textContent = evt.raw_title;
    document.getElementById('modalRoomName').textContent = `ห้อง: ${evt.room_name}`;
    document.getElementById('modalDate').textContent = evt.date_thai;
    document.getElementById('modalTime').textContent = evt.time_range;
    document.getElementById('modalRequester').textContent = evt.requester_name;
    document.getElementById('modalDept').textContent = evt.department;
    document.getElementById('modalAttendees').textContent = `${evt.attendee_count} คน`;

    const badge = document.getElementById('modalStatusBadge');
    if (evt.status === 'approved') {
        badge.className = 'inline-block px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-emerald-100 text-emerald-800 border border-emerald-200';
        badge.textContent = '✓ อนุมัติแล้ว';
    } else {
        badge.className = 'inline-block px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-amber-100 text-amber-800 border border-amber-200';
        badge.textContent = '⏳ รออนุมัติ';
    }

    document.getElementById('modalIcsBtn').href = `api/export_ics.php?id=${evt.id}`;
    document.getElementById('eventModal').classList.remove('hidden');
}

function closeModal() {
    document.getElementById('eventModal').classList.add('hidden');
}

// Navigation event listeners
document.getElementById('prevMonthBtn').addEventListener('click', () => {
    currentMonth--;
    if (currentMonth < 1) { currentMonth = 12; currentYear--; }
    fetchEvents();
});

document.getElementById('nextMonthBtn').addEventListener('click', () => {
    currentMonth++;
    if (currentMonth > 12) { currentMonth = 1; currentYear++; }
    fetchEvents();
});

document.getElementById('todayBtn').addEventListener('click', () => {
    currentYear = <?= (int)date('Y') ?>;
    currentMonth = <?= (int)date('n') ?>;
    fetchEvents();
});

document.getElementById('roomFilter').addEventListener('change', () => {
    fetchEvents();
});

// Initial load
fetchEvents();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
