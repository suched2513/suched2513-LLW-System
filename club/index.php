<?php
session_start();
require_once __DIR__ . '/../config.php';

if (!isset($_SESSION['llw_role'])) {
    header('Location: ' . $base_path . '/login.php?redirect=' . urlencode($_SERVER['REQUEST_URI'])); exit();
}
$allowed = ['att_teacher', 'super_admin', 'club_admin', 'wfh_admin', 'wfh_staff', 'finance_head', 'procurement_head', 'deputy_director', 'director', 'cb_admin', 'bus_admin', 'bus_finance'];
if (!in_array($_SESSION['llw_role'], $allowed, true)) {
    header('Location: ' . $base_path . '/login.php'); exit();
}

$pageTitle    = 'ระบบบริหารจัดการกิจกรรมชุมนุม';
$pageSubtitle = 'ศูนย์สั่งการและข้อมูลสถิติกิจกรรมชุมนุมนักเรียน';
$activeSystem = 'club';
$userRole     = $_SESSION['llw_role'];
$teacherId    = $_SESSION['teacher_id'] ?? 0;

$pdo = getPdo();
$activeSetting = $pdo->query("SELECT semester, year FROM club_settings WHERE is_active = 1 LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$currentSem  = $activeSetting ? (int)$activeSetting['semester'] : (function_exists('llw_current_semester') ? llw_current_semester() : 2);
$currentYear = $activeSetting ? (int)$activeSetting['year']     : (function_exists('llw_current_academic_year') ? llw_current_academic_year() : 2569);

require_once __DIR__ . '/../components/layout_start.php';
?>

<style>
/* Custom Dashboard Enhancements */
.kpi-card {
    transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    overflow: hidden;
}
.kpi-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 12px 24px -6px rgba(0,0,0,0.18);
}
.kpi-icon-bubble {
    width: 44px;
    height: 44px;
    border-radius: 14px;
    background: rgba(255,255,255,0.2);
    display: flex;
    align-items: center;
    justify-content: center;
    backdrop-filter: blur(4px);
}
.chart-card {
    background: #ffffff;
    border-radius: 1.25rem;
    box-shadow: 0 4px 18px 0 rgba(226, 232, 240, 0.6);
    border: 1px solid #f1f5f9;
}
.quick-btn {
    transition: all 0.2s ease;
}
.quick-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 16px -4px rgba(0,0,0,0.1);
}
.donut-center-text {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    text-align: center;
    pointer-events: none;
}
</style>

<div class="space-y-6">

    <!-- 1. Top Header & Real-time Clock Bar -->
    <div class="bg-white rounded-2xl p-4 sm:p-5 shadow-sm border border-slate-100 flex flex-col lg:flex-row items-start lg:items-center justify-between gap-4">
        <div class="flex items-center gap-3">
            <div class="w-12 h-12 rounded-2xl bg-gradient-to-tr from-blue-600 to-indigo-600 text-white flex items-center justify-center shadow-md shadow-blue-500/20 shrink-0">
                <i class="bi bi-buildings-fill text-2xl"></i>
            </div>
            <div>
                <div class="flex items-center gap-2 flex-wrap">
                    <h1 class="text-xl sm:text-2xl font-black text-slate-800 tracking-tight">ระบบบริหารจัดการกิจกรรมชุมนุม</h1>
                    <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-blue-50 text-blue-700 border border-blue-200">
                        โรงเรียนละลมวิทยา
                    </span>
                </div>
                <p class="text-xs sm:text-sm text-slate-500 font-medium">ภาพรวมการลงทะเบียน สถิติกิจกรรม และการประเมินผล ปพ. กิจกรรมพัฒนาผู้เรียน</p>
            </div>
        </div>

        <div class="flex items-center gap-2 sm:gap-3 flex-wrap w-full lg:w-auto justify-between lg:justify-end">
            <!-- Live Date & Clock -->
            <div class="flex items-center gap-2 bg-slate-50 text-slate-700 px-3.5 py-2 rounded-xl border border-slate-200 shadow-sm text-xs font-semibold">
                <i class="bi bi-calendar-event text-blue-600 text-sm"></i>
                <span id="live_date" class="hidden sm:inline">กำลังโหลด...</span>
                <span class="w-1.5 h-1.5 rounded-full bg-slate-300"></span>
                <i class="bi bi-clock-fill text-blue-600 text-sm"></i>
                <span id="live_clock" class="font-mono text-xs sm:text-sm font-black text-slate-800">--:--:--</span>
            </div>

            <!-- Fullscreen Button -->
            <button type="button" onclick="toggleFullScreen()" title="เต็มจอ" class="w-9 h-9 rounded-xl bg-slate-50 hover:bg-slate-100 text-slate-600 border border-slate-200 flex items-center justify-center transition-all">
                <i class="bi bi-fullscreen text-sm"></i>
            </button>
        </div>
    </div>

    <!-- 2. Controls & Term Bar -->
    <div class="bg-white rounded-2xl p-4 shadow-sm border border-slate-100 flex flex-col md:flex-row items-center justify-between gap-3">
        <div class="flex items-center gap-2.5 flex-wrap w-full md:w-auto">
            <span class="text-xs font-black uppercase tracking-wider text-slate-400 flex items-center gap-1.5">
                <i class="bi bi-funnel-fill text-indigo-500"></i> เลือกภาคเรียน:
            </span>
            <select id="term_selector" onchange="switchTerm(this.value)" class="bg-slate-50 border border-slate-200 text-slate-800 text-sm font-bold rounded-xl px-3.5 py-2 outline-none focus:ring-2 focus:ring-blue-500 cursor-pointer shadow-sm">
                <option value="2-2569" <?= ($currentSem == 2 && $currentYear == 2569) ? 'selected' : '' ?>>ภาคเรียนที่ 2/2569 <?= ($currentSem == 2 && $currentYear == 2569) ? '★ (ปัจจุบัน)' : '' ?></option>
                <option value="1-2569" <?= ($currentSem == 1 && $currentYear == 2569) ? 'selected' : '' ?>>ภาคเรียนที่ 1/2569 <?= ($currentSem == 1 && $currentYear == 2569) ? '★ (ปัจจุบัน)' : '' ?></option>
            </select>
            <span id="term_active_badge" class="px-2.5 py-1 rounded-full text-xs font-bold bg-emerald-50 text-emerald-700 border border-emerald-200 flex items-center gap-1">
                <i class="bi bi-check-circle-fill"></i> ภาคเรียนปัจจุบัน
            </span>
        </div>

        <div class="flex items-center gap-2 flex-wrap w-full md:w-auto justify-end">
            <?php if (in_array($userRole, ['super_admin', 'club_admin', 'att_teacher'])): ?>
            <button type="button" onclick="confirmCloneClubs()" class="px-3.5 py-2 rounded-xl text-xs font-bold bg-indigo-50 hover:bg-indigo-100 text-indigo-700 border border-indigo-200 transition-all flex items-center gap-1.5 shadow-sm">
                <i class="bi bi-copy"></i> คัดลอกชุมนุมจากเทอม 1
            </button>
            <?php endif; ?>

            <button type="button" onclick="openPrintModal()" class="px-3.5 py-2 rounded-xl text-xs font-bold bg-slate-50 hover:bg-slate-100 text-slate-700 border border-slate-200 transition-all flex items-center gap-1.5 shadow-sm">
                <i class="bi bi-printer-fill text-slate-500"></i> พิมพ์เอกสาร
            </button>

            <?php if (in_array($userRole, ['super_admin', 'club_admin'])): ?>
            <a href="/club/settings.php" class="px-3.5 py-2 rounded-xl text-xs font-bold bg-slate-50 hover:bg-slate-100 text-slate-700 border border-slate-200 transition-all flex items-center gap-1.5 shadow-sm">
                <i class="bi bi-gear-fill text-slate-500"></i> ตั้งค่าเทอม
            </a>
            <a href="/club/manage.php" class="px-4 py-2 rounded-xl text-xs font-bold bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white shadow-md shadow-blue-500/20 transition-all flex items-center gap-1.5">
                <i class="bi bi-plus-circle-fill"></i> สร้างชุมนุมใหม่
            </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- 3. KPI Stat Cards (6 Vibrant Gradient Cards exactly like the image) -->
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3 sm:gap-4" id="kpi_row">
        <!-- 1. Total Clubs -->
        <div class="kpi-card bg-gradient-to-br from-blue-600 to-indigo-700 rounded-2xl p-4 text-white shadow-lg shadow-blue-600/20">
            <div class="flex items-center justify-between">
                <div class="kpi-icon-bubble">
                    <i class="bi bi-collection-fill text-xl"></i>
                </div>
                <span class="text-[10px] font-bold uppercase tracking-wider bg-white/20 px-2 py-0.5 rounded-full">ชุมนุม</span>
            </div>
            <div class="mt-3">
                <p class="text-xs font-bold opacity-80">ชุมนุมทั้งหมด</p>
                <p class="text-2xl sm:text-3xl font-black tracking-tight mt-0.5" id="kpi_clubs">—</p>
            </div>
            <div class="mt-3 pt-2 border-t border-white/10 flex items-center justify-between text-[11px]">
                <span class="opacity-75">เปิดรับกิจกรรม</span>
                <span class="font-bold">100%</span>
            </div>
            <div class="w-full bg-black/20 h-1.5 rounded-full mt-1.5 overflow-hidden">
                <div class="bg-white h-full rounded-full" style="width: 100%"></div>
            </div>
        </div>

        <!-- 2. Registered Students -->
        <div class="kpi-card bg-gradient-to-br from-emerald-500 to-teal-700 rounded-2xl p-4 text-white shadow-lg shadow-emerald-500/20">
            <div class="flex items-center justify-between">
                <div class="kpi-icon-bubble">
                    <i class="bi bi-person-check-fill text-xl"></i>
                </div>
                <span class="text-[10px] font-bold uppercase tracking-wider bg-white/20 px-2 py-0.5 rounded-full" id="kpi_reg_badge">สำเร็จ</span>
            </div>
            <div class="mt-3">
                <p class="text-xs font-bold opacity-80">ลงทะเบียนแล้ว</p>
                <p class="text-2xl sm:text-3xl font-black tracking-tight mt-0.5" id="kpi_registered">—</p>
            </div>
            <div class="mt-3 pt-2 border-t border-white/10 flex items-center justify-between text-[11px]">
                <span class="opacity-75">เป้าหมาย 548 คน</span>
                <span class="font-bold" id="kpi_reg_pct">0%</span>
            </div>
            <div class="w-full bg-black/20 h-1.5 rounded-full mt-1.5 overflow-hidden">
                <div id="kpi_reg_bar" class="bg-white h-full rounded-full transition-all duration-500" style="width: 0%"></div>
            </div>
        </div>

        <!-- 3. Unregistered Students (Clickable to view modal) -->
        <div onclick="openUnregModal()" class="kpi-card bg-gradient-to-br from-amber-500 to-orange-600 rounded-2xl p-4 text-white shadow-lg shadow-amber-500/20 cursor-pointer group" title="คลิกเพื่อดูรายชื่อนักเรียนที่ยังไม่ลงทะเบียน">
            <div class="flex items-center justify-between">
                <div class="kpi-icon-bubble group-hover:bg-white/30 transition-all">
                    <i class="bi bi-person-exclamation text-xl"></i>
                </div>
                <span class="text-[10px] font-bold uppercase tracking-wider bg-white/20 group-hover:bg-white/30 px-2 py-0.5 rounded-full transition-all">คลิกดูชื่อ <i class="bi bi-arrow-right"></i></span>
            </div>
            <div class="mt-3">
                <p class="text-xs font-bold opacity-80">ยังไม่ลงทะเบียน</p>
                <p class="text-2xl sm:text-3xl font-black tracking-tight mt-0.5" id="kpi_unreg">—</p>
            </div>
            <div class="mt-3 pt-2 border-t border-white/10 flex items-center justify-between text-[11px]">
                <span class="opacity-75">ตกหล่น</span>
                <span class="font-bold" id="kpi_unreg_pct">0%</span>
            </div>
            <div class="w-full bg-black/20 h-1.5 rounded-full mt-1.5 overflow-hidden">
                <div id="kpi_unreg_bar" class="bg-white h-full rounded-full transition-all duration-500" style="width: 0%"></div>
            </div>
        </div>

        <!-- 4. Total Sessions Conducted -->
        <div class="kpi-card bg-gradient-to-br from-purple-600 to-indigo-800 rounded-2xl p-4 text-white shadow-lg shadow-purple-600/20">
            <div class="flex items-center justify-between">
                <div class="kpi-icon-bubble">
                    <i class="bi bi-calendar-check-fill text-xl"></i>
                </div>
                <span class="text-[10px] font-bold uppercase tracking-wider bg-white/20 px-2 py-0.5 rounded-full">คาบรวม</span>
            </div>
            <div class="mt-3">
                <p class="text-xs font-bold opacity-80">คาบที่จัดแล้ว</p>
                <p class="text-2xl sm:text-3xl font-black tracking-tight mt-0.5" id="kpi_sessions">—</p>
            </div>
            <div class="mt-3 pt-2 border-t border-white/10 flex items-center justify-between text-[11px]">
                <span class="opacity-75">บันทึกกิจกรรม</span>
                <span class="font-bold" id="kpi_avg_sessions">— คาบ/ชุมนุม</span>
            </div>
            <div class="w-full bg-black/20 h-1.5 rounded-full mt-1.5 overflow-hidden">
                <div class="bg-white h-full rounded-full" style="width: 60%"></div>
            </div>
        </div>

        <!-- 5. Full Capacity Clubs -->
        <div class="kpi-card bg-gradient-to-br from-cyan-600 to-blue-700 rounded-2xl p-4 text-white shadow-lg shadow-cyan-600/20">
            <div class="flex items-center justify-between">
                <div class="kpi-icon-bubble">
                    <i class="bi bi-award-fill text-xl"></i>
                </div>
                <span class="text-[10px] font-bold uppercase tracking-wider bg-white/20 px-2 py-0.5 rounded-full">ยอดนิยม</span>
            </div>
            <div class="mt-3">
                <p class="text-xs font-bold opacity-80">ชุมนุมที่เต็มแล้ว</p>
                <p class="text-2xl sm:text-3xl font-black tracking-tight mt-0.5" id="kpi_full">—</p>
            </div>
            <div class="mt-3 pt-2 border-t border-white/10 flex items-center justify-between text-[11px]">
                <span class="opacity-75">สัดส่วนชุมนุม</span>
                <span class="font-bold" id="kpi_full_pct">0%</span>
            </div>
            <div class="w-full bg-black/20 h-1.5 rounded-full mt-1.5 overflow-hidden">
                <div id="kpi_full_bar" class="bg-white h-full rounded-full transition-all duration-500" style="width: 0%"></div>
            </div>
        </div>

        <!-- 6. Inactive / No Session Alert -->
        <div class="kpi-card bg-gradient-to-br from-rose-500 to-pink-700 rounded-2xl p-4 text-white shadow-lg shadow-rose-600/20">
            <div class="flex items-center justify-between">
                <div class="kpi-icon-bubble">
                    <i class="bi bi-exclamation-triangle-fill text-xl"></i>
                </div>
                <span class="text-[10px] font-bold uppercase tracking-wider bg-white/20 px-2 py-0.5 rounded-full">เร่งด่วน</span>
            </div>
            <div class="mt-3">
                <p class="text-xs font-bold opacity-80">ยังไม่จัดกิจกรรม</p>
                <p class="text-2xl sm:text-3xl font-black tracking-tight mt-0.5" id="kpi_no_session">—</p>
            </div>
            <div class="mt-3 pt-2 border-t border-white/10 flex items-center justify-between text-[11px]">
                <span class="opacity-75">ต้องติดตาม</span>
                <span class="font-bold" id="kpi_no_session_pct">0%</span>
            </div>
            <div class="w-full bg-black/20 h-1.5 rounded-full mt-1.5 overflow-hidden">
                <div id="kpi_no_session_bar" class="bg-white h-full rounded-full transition-all duration-500" style="width: 0%"></div>
            </div>
        </div>
    </div>

    <!-- 4. Interactive Charts Section (Chart.js Bar & Donuts) -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-5">
        <!-- Chart 1: Registration by Class Grade (ม.1 - ม.6) -->
        <div class="lg:col-span-6 chart-card p-5">
            <div class="flex items-center justify-between mb-3">
                <div class="flex items-center gap-2">
                    <div class="w-8 h-8 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center font-bold">
                        <i class="bi bi-bar-chart-fill"></i>
                    </div>
                    <div>
                        <h3 class="text-sm font-black text-slate-800">สถิติการลงทะเบียนรายระดับชั้น</h3>
                        <p class="text-xs text-slate-400">เปรียบเทียบจำนวน นร. ที่ลงทะเบียนแล้ว vs ยังไม่ลงทะเบียน</p>
                    </div>
                </div>
                <div class="flex items-center gap-1 bg-slate-100 p-1 rounded-xl text-xs font-bold text-slate-600">
                    <button type="button" id="btn_view_grade" onclick="toggleChartView('grade')" class="px-2.5 py-1 rounded-lg bg-white shadow-sm text-blue-600">ระดับชั้น</button>
                    <button type="button" id="btn_view_room" onclick="toggleChartView('room')" class="px-2.5 py-1 rounded-lg text-slate-500 hover:text-slate-800">รายห้อง</button>
                </div>
            </div>
            <div class="relative" style="height: 220px;">
                <canvas id="chartByGrade"></canvas>
            </div>
        </div>

        <!-- Chart 2: Registration Donut -->
        <div class="lg:col-span-3 chart-card p-5 flex flex-col justify-between">
            <div class="flex items-center gap-2 mb-2">
                <div class="w-8 h-8 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center font-bold">
                    <i class="bi bi-pie-chart-fill"></i>
                </div>
                <div>
                    <h3 class="text-sm font-black text-slate-800">สัดส่วนการลงทะเบียน</h3>
                    <p class="text-xs text-slate-400">จากนักเรียนทั้งหมด 548 คน</p>
                </div>
            </div>
            <div class="relative flex items-center justify-center my-auto" style="height: 180px;">
                <canvas id="chartRegDonut"></canvas>
                <div class="donut-center-text">
                    <div class="text-xl font-black text-slate-800" id="donut_reg_center_pct">0%</div>
                    <div class="text-[10px] font-bold text-slate-400 uppercase">ลงทะเบียนแล้ว</div>
                </div>
            </div>
            <div class="grid grid-cols-2 gap-2 pt-2 border-t border-slate-100 text-center text-xs">
                <div class="bg-emerald-50 text-emerald-700 py-1.5 rounded-lg font-bold">
                    <span class="inline-block w-2 h-2 rounded-full bg-emerald-500 me-1"></span>
                    <span id="donut_reg_count">0</span> คน
                </div>
                <div class="bg-amber-50 text-amber-700 py-1.5 rounded-lg font-bold cursor-pointer" onclick="openUnregModal()">
                    <span class="inline-block w-2 h-2 rounded-full bg-amber-500 me-1"></span>
                    <span id="donut_unreg_count">0</span> คน
                </div>
            </div>
        </div>

        <!-- Chart 3: Capacity Donut -->
        <div class="lg:col-span-3 chart-card p-5 flex flex-col justify-between">
            <div class="flex items-center gap-2 mb-2">
                <div class="w-8 h-8 rounded-xl bg-cyan-50 text-cyan-600 flex items-center justify-center font-bold">
                    <i class="bi bi-diagram-3-fill"></i>
                </div>
                <div>
                    <h3 class="text-sm font-black text-slate-800">สถานะความจุชุมนุม</h3>
                    <p class="text-xs text-slate-400">ความพร้อมในการรับสมาชิก</p>
                </div>
            </div>
            <div class="relative flex items-center justify-center my-auto" style="height: 180px;">
                <canvas id="chartCapacityDonut"></canvas>
                <div class="donut-center-text">
                    <div class="text-xl font-black text-slate-800" id="donut_cap_center_count">0</div>
                    <div class="text-[10px] font-bold text-slate-400 uppercase">ชุมนุม</div>
                </div>
            </div>
            <div class="grid grid-cols-2 gap-2 pt-2 border-t border-slate-100 text-center text-xs">
                <div class="bg-cyan-50 text-cyan-700 py-1.5 rounded-lg font-bold">
                    <span class="inline-block w-2 h-2 rounded-full bg-cyan-500 me-1"></span>
                    เต็มแล้ว: <span id="donut_cap_full">0</span>
                </div>
                <div class="bg-indigo-50 text-indigo-700 py-1.5 rounded-lg font-bold">
                    <span class="inline-block w-2 h-2 rounded-full bg-indigo-500 me-1"></span>
                    ยังเปิดรับ: <span id="donut_cap_open">0</span>
                </div>
            </div>
        </div>
    </div>

    <!-- 5. Main Content: Club Table (Left 8) + Activity Progress Ranking (Right 4) -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-5">
        <!-- Left: Club Table -->
        <div class="lg:col-span-8 chart-card p-5">
            <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 mb-4">
                <div class="flex items-center gap-2">
                    <div class="w-8 h-8 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center font-bold">
                        <i class="bi bi-trophy-fill"></i>
                    </div>
                    <div>
                        <h3 class="text-base font-black text-slate-800">รายชื่อชุมนุมและสถานะล่าสุด</h3>
                        <p class="text-xs text-slate-400" id="table_term_badge">ภาคเรียนที่ <?= $currentSem ?>/<?= $currentYear ?></p>
                    </div>
                </div>

                <div class="flex items-center gap-2 w-full sm:w-auto">
                    <!-- Search Input -->
                    <div class="relative w-full sm:w-56">
                        <i class="bi bi-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                        <input type="text" id="club_search" oninput="filterClubsTable()" placeholder="ค้นหาชื่อชุมนุม, ครู..." class="w-full pl-8 pr-3 py-1.5 text-xs bg-slate-50 border border-slate-200 rounded-xl outline-none focus:ring-2 focus:ring-blue-500 transition-all font-medium">
                    </div>
                </div>
            </div>

            <!-- Filter Pills -->
            <div class="flex items-center gap-1.5 overflow-x-auto pb-2 mb-3 text-xs font-bold">
                <button type="button" onclick="setFilterStatus('all')" id="pill_all" class="px-3 py-1.5 rounded-xl bg-blue-600 text-white shadow-sm shadow-blue-500/20 whitespace-nowrap">ทั้งหมด (<span id="count_all">0</span>)</button>
                <button type="button" onclick="setFilterStatus('open')" id="pill_open" class="px-3 py-1.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-600 whitespace-nowrap">กำลังเปิดรับ (<span id="count_open">0</span>)</button>
                <button type="button" onclick="setFilterStatus('full')" id="pill_full" class="px-3 py-1.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-600 whitespace-nowrap">สมาชิกเต็ม (<span id="count_full">0</span>)</button>
                <button type="button" onclick="setFilterStatus('no_session')" id="pill_no_session" class="px-3 py-1.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-600 whitespace-nowrap text-rose-600">ยังไม่จัดกิจกรรม (<span id="count_no_session">0</span>)</button>
            </div>

            <!-- Loading & Empty States -->
            <div id="table_loading" class="text-center py-10 text-slate-400 text-sm">
                <i class="bi bi-arrow-repeat animate-spin text-2xl mb-2 inline-block"></i>
                <p>กำลังโหลดข้อมูลชุมนุม...</p>
            </div>

            <div id="table_empty" class="text-center py-10 px-4 text-slate-400 hidden">
                <i class="bi bi-inbox text-4xl text-slate-300 mb-2 inline-block"></i>
                <h4 class="text-sm font-bold text-slate-700">ไม่พบข้อมูลชุมนุมในภาคเรียนนี้</h4>
                <p class="text-xs text-slate-400 mt-1 mb-3">สามารถคัดลอกชุมนุมจากภาคเรียนที่ 1 หรือสร้างใหม่ได้ทันที</p>
                <?php if (in_array($userRole, ['super_admin', 'club_admin', 'att_teacher'])): ?>
                <button type="button" onclick="confirmCloneClubs()" class="px-3.5 py-2 rounded-xl text-xs font-bold bg-indigo-600 hover:bg-indigo-700 text-white shadow-md transition-all">
                    <i class="bi bi-copy me-1"></i> คัดลอกชุมนุมสู่เทอม 2
                </button>
                <?php endif; ?>
            </div>

            <!-- Table -->
            <div class="overflow-x-auto" id="table_wrapper" style="display:none">
                <table class="w-full text-left text-xs border-collapse">
                    <thead>
                        <tr class="border-b border-slate-100 text-slate-400 font-bold uppercase text-[10px] tracking-wider bg-slate-50/70">
                            <th class="py-3 px-3 rounded-l-xl">ชื่อชุมนุม</th>
                            <th class="py-3 px-3">ครูที่ปรึกษา</th>
                            <th class="py-3 px-3">ห้อง</th>
                            <th class="py-3 px-3 text-center">สมาชิก / ความจุ</th>
                            <th class="py-3 px-3 text-center">จัดแล้ว</th>
                            <th class="py-3 px-3 text-center">สถานะ</th>
                            <th class="py-3 px-3 text-center rounded-r-xl">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody id="clubs_tbody" class="divide-y divide-slate-100"></tbody>
                </table>
            </div>
        </div>

        <!-- Right: Activity Progress Ranking (Like 'ผลงานช่างประจำเดือน' in reference image) -->
        <div class="lg:col-span-4 chart-card p-5 flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between mb-3">
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 rounded-xl bg-purple-50 text-purple-600 flex items-center justify-center font-bold">
                            <i class="bi bi-activity"></i>
                        </div>
                        <div>
                            <h3 class="text-sm font-black text-slate-800">ความคืบหน้ากิจกรรมรายชุมนุม</h3>
                            <p class="text-xs text-slate-400">เป้าหมายตามเกณฑ์ 20 คาบ/เทอม</p>
                        </div>
                    </div>
                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-slate-100 text-slate-600">อันดับ</span>
                </div>

                <div id="ranking_list" class="space-y-3.5 my-2">
                    <!-- Populated dynamically via JS -->
                    <div class="text-center py-6 text-xs text-slate-400">กำลังประมวลผลอันดับ...</div>
                </div>
            </div>

            <div class="pt-3 border-t border-slate-100 mt-4 flex items-center justify-between text-xs">
                <span class="text-slate-400 font-medium">บันทึกผลการประเมิน ปพ.</span>
                <a href="/club/report.php" class="font-bold text-blue-600 hover:text-blue-800 flex items-center gap-1">
                    ดูรายงานฉบับเต็ม <i class="bi bi-chevron-right text-[10px]"></i>
                </a>
            </div>
        </div>
    </div>

    <!-- 6. Bottom Quick Action Menu (⚡ เมนูด่วน) -->
    <div class="bg-white rounded-2xl p-4 sm:p-5 shadow-sm border border-slate-100">
        <div class="flex items-center gap-2 mb-3">
            <span class="px-3 py-1 rounded-xl bg-blue-600 text-white text-xs font-black flex items-center gap-1.5 shadow-sm shadow-blue-500/20">
                <i class="bi bi-lightning-charge-fill"></i> เมนูด่วน
            </span>
            <span class="text-xs text-slate-400 font-medium">เข้าถึงฟังก์ชันสำคัญในคลิกเดียว</span>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
            <!-- 1. Create Club -->
            <a href="/club/manage.php" class="quick-btn p-3.5 rounded-2xl bg-rose-50/80 hover:bg-rose-100/90 text-rose-800 border border-rose-200/60 flex flex-col items-center text-center gap-2">
                <div class="w-10 h-10 rounded-xl bg-rose-500 text-white flex items-center justify-center shadow-md shadow-rose-500/20">
                    <i class="bi bi-plus-circle text-lg"></i>
                </div>
                <div>
                    <div class="text-xs font-bold text-slate-800">สร้างชุมนุมใหม่</div>
                    <div class="text-[10px] text-slate-500">เปิดรับสมัคร</div>
                </div>
            </a>

            <!-- 2. Students by Classroom -->
            <a href="/club/class_students.php" class="quick-btn p-3.5 rounded-2xl bg-blue-50/80 hover:bg-blue-100/90 text-blue-800 border border-blue-200/60 flex flex-col items-center text-center gap-2">
                <div class="w-10 h-10 rounded-xl bg-blue-500 text-white flex items-center justify-center shadow-md shadow-blue-500/20">
                    <i class="bi bi-people-fill text-lg"></i>
                </div>
                <div>
                    <div class="text-xs font-bold text-slate-800">นักเรียนรายห้อง</div>
                    <div class="text-[10px] text-slate-500">ม.1/1 - ม.6/2</div>
                </div>
            </a>

            <!-- 3. Unregistered Students Modal -->
            <div onclick="openUnregModal()" class="quick-btn p-3.5 rounded-2xl bg-amber-50/80 hover:bg-amber-100/90 text-amber-800 border border-amber-200/60 flex flex-col items-center text-center gap-2 cursor-pointer">
                <div class="w-10 h-10 rounded-xl bg-amber-500 text-white flex items-center justify-center shadow-md shadow-amber-500/20">
                    <i class="bi bi-person-x-fill text-lg"></i>
                </div>
                <div>
                    <div class="text-xs font-bold text-slate-800">ยังไม่ลงทะเบียน</div>
                    <div class="text-[10px] text-slate-500" id="quick_unreg_count">ตรวจชื่อตกหล่น</div>
                </div>
            </div>

            <!-- 4. Reports & Analytics -->
            <a href="/club/report.php" class="quick-btn p-3.5 rounded-2xl bg-purple-50/80 hover:bg-purple-100/90 text-purple-800 border border-purple-200/60 flex flex-col items-center text-center gap-2">
                <div class="w-10 h-10 rounded-xl bg-purple-500 text-white flex items-center justify-center shadow-md shadow-purple-500/20">
                    <i class="bi bi-file-earmark-bar-graph-fill text-lg"></i>
                </div>
                <div>
                    <div class="text-xs font-bold text-slate-800">รายงานสรุปผล</div>
                    <div class="text-[10px] text-slate-500">สถิติ & กราฟ</div>
                </div>
            </a>

            <!-- 5. Print Modal -->
            <div onclick="openPrintModal()" class="quick-btn p-3.5 rounded-2xl bg-emerald-50/80 hover:bg-emerald-100/90 text-emerald-800 border border-emerald-200/60 flex flex-col items-center text-center gap-2 cursor-pointer">
                <div class="w-10 h-10 rounded-xl bg-emerald-500 text-white flex items-center justify-center shadow-md shadow-emerald-500/20">
                    <i class="bi bi-printer-fill text-lg"></i>
                </div>
                <div>
                    <div class="text-xs font-bold text-slate-800">พิมพ์เอกสาร/ปพ.</div>
                    <div class="text-[10px] text-slate-500">สมุด & ใบเช็คชื่อ</div>
                </div>
            </div>

            <!-- 6. Settings -->
            <a href="/club/settings.php" class="quick-btn p-3.5 rounded-2xl bg-cyan-50/80 hover:bg-cyan-100/90 text-cyan-800 border border-cyan-200/60 flex flex-col items-center text-center gap-2">
                <div class="w-10 h-10 rounded-xl bg-cyan-600 text-white flex items-center justify-center shadow-md shadow-cyan-600/20">
                    <i class="bi bi-sliders text-lg"></i>
                </div>
                <div>
                    <div class="text-xs font-bold text-slate-800">ตั้งค่าระบบ</div>
                    <div class="text-[10px] text-slate-500">เปิด-ปิดรับสมัคร</div>
                </div>
            </a>
        </div>
    </div>

</div>

<!-- Unregistered Students Modal (SweetAlert2 or Custom Popup) -->
<div id="unregModal" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl shadow-2xl max-w-2xl w-full max-h-[85vh] flex flex-col overflow-hidden animate__animated animate__fadeInUp animate__faster">
        <div class="p-5 border-b border-slate-100 flex items-center justify-between bg-amber-50/60">
            <div class="flex items-center gap-2.5">
                <div class="w-10 h-10 rounded-2xl bg-amber-500 text-white flex items-center justify-center shadow-sm">
                    <i class="bi bi-person-x-fill text-xl"></i>
                </div>
                <div>
                    <h3 class="text-base font-black text-slate-800">รายชื่อนักเรียนที่ยังไม่ลงทะเบียน</h3>
                    <p class="text-xs text-amber-700 font-semibold" id="modal_unreg_subtitle">มีนักเรียนค้าง 0 คน</p>
                </div>
            </div>
            <button type="button" onclick="closeUnregModal()" class="w-8 h-8 rounded-full bg-slate-200/60 hover:bg-slate-200 text-slate-500 flex items-center justify-center transition-all">
                <i class="bi bi-x-lg text-sm"></i>
            </button>
        </div>

        <div class="p-4 border-b border-slate-100 flex items-center gap-2">
            <div class="relative flex-1">
                <i class="bi bi-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                <input type="text" id="modal_unreg_search" oninput="filterUnregModal()" placeholder="พิมพ์ชื่อ, รหัสนักเรียน, หรือห้อง..." class="w-full pl-8 pr-3 py-2 text-xs bg-slate-50 border border-slate-200 rounded-xl outline-none focus:ring-2 focus:ring-amber-500 font-medium">
            </div>
            <a id="btn_print_unreg_modal" href="#" target="_blank" class="px-3.5 py-2 rounded-xl text-xs font-bold bg-amber-500 hover:bg-amber-600 text-white shadow-sm flex items-center gap-1.5 whitespace-nowrap">
                <i class="bi bi-printer-fill"></i> พิมพ์ใบรายชื่อ
            </a>
        </div>

        <div class="p-4 overflow-y-auto flex-1">
            <table class="w-full text-left text-xs border-collapse">
                <thead>
                    <tr class="border-b border-slate-100 text-slate-400 font-bold uppercase text-[10px]">
                        <th class="py-2 px-3">#</th>
                        <th class="py-2 px-3">รหัสนักเรียน</th>
                        <th class="py-2 px-3">ชื่อ - นามสกุล</th>
                        <th class="py-2 px-3">ห้องเรียน</th>
                    </tr>
                </thead>
                <tbody id="unreg_modal_tbody" class="divide-y divide-slate-100"></tbody>
            </table>
        </div>

        <div class="p-4 border-t border-slate-100 bg-slate-50 flex items-center justify-between text-xs">
            <span class="text-slate-400 font-medium" id="modal_unreg_filter_count">แสดงทั้งหมด</span>
            <button type="button" onclick="closeUnregModal()" class="px-4 py-1.5 rounded-xl bg-slate-200 hover:bg-slate-300 text-slate-700 font-bold transition-all">
                ปิดหน้าต่าง
            </button>
        </div>
    </div>
</div>

<!-- Print Options Modal -->
<div id="printModal" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl shadow-2xl max-w-md w-full overflow-hidden animate__animated animate__fadeInUp animate__faster p-6">
        <div class="flex items-center justify-between mb-4">
            <div class="flex items-center gap-2.5">
                <div class="w-10 h-10 rounded-2xl bg-blue-50 text-blue-600 flex items-center justify-center">
                    <i class="bi bi-printer-fill text-xl"></i>
                </div>
                <div>
                    <h3 class="text-base font-black text-slate-800">พิมพ์เอกสารชุมนุม</h3>
                    <p class="text-xs text-slate-400">เลือกแบบฟอร์มที่ต้องการพิมพ์</p>
                </div>
            </div>
            <button type="button" onclick="closePrintModal()" class="w-8 h-8 rounded-full bg-slate-100 hover:bg-slate-200 text-slate-500 flex items-center justify-center transition-all">
                <i class="bi bi-x-lg text-sm"></i>
            </button>
        </div>

        <div class="space-y-2.5 my-4">
            <a id="link_print_summary" href="#" target="_blank" class="flex items-center justify-between p-3 rounded-2xl border border-slate-100 hover:border-blue-200 hover:bg-blue-50/50 transition-all text-xs font-bold text-slate-700">
                <span class="flex items-center gap-2.5">
                    <i class="bi bi-file-earmark-text-fill text-blue-600 text-base"></i>
                    <span>พิมพ์สรุปภาพรวมชุมนุม (Summary)</span>
                </span>
                <i class="bi bi-arrow-right text-slate-400"></i>
            </a>

            <a id="link_print_unreg" href="#" target="_blank" class="flex items-center justify-between p-3 rounded-2xl border border-slate-100 hover:border-amber-200 hover:bg-amber-50/50 transition-all text-xs font-bold text-slate-700">
                <span class="flex items-center gap-2.5">
                    <i class="bi bi-person-x-fill text-amber-600 text-base"></i>
                    <span>พิมพ์รายชื่อนักเรียนที่ยังไม่ลงทะเบียน</span>
                </span>
                <i class="bi bi-arrow-right text-slate-400"></i>
            </a>

            <a href="/club/class_students.php" class="flex items-center justify-between p-3 rounded-2xl border border-slate-100 hover:border-emerald-200 hover:bg-emerald-50/50 transition-all text-xs font-bold text-slate-700">
                <span class="flex items-center gap-2.5">
                    <i class="bi bi-people-fill text-emerald-600 text-base"></i>
                    <span>พิมพ์รายชื่อนักเรียนแยกตามห้องเรียน</span>
                </span>
                <i class="bi bi-arrow-right text-slate-400"></i>
            </a>

            <a href="/club/report.php" class="flex items-center justify-between p-3 rounded-2xl border border-slate-100 hover:border-purple-200 hover:bg-purple-50/50 transition-all text-xs font-bold text-slate-700">
                <span class="flex items-center gap-2.5">
                    <i class="bi bi-bar-chart-fill text-purple-600 text-base"></i>
                    <span>พิมพ์รายงานสรุปผลการประเมิน ปพ.</span>
                </span>
                <i class="bi bi-arrow-right text-slate-400"></i>
            </a>
        </div>

        <button type="button" onclick="closePrintModal()" class="w-full py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-600 font-bold text-xs transition-all">
            ปิด
        </button>
    </div>
</div>

<script>
const userRole = <?= json_encode($userRole) ?>;
const teacherId = <?= json_encode($teacherId) ?>;
let activeSystemSem = <?= (int)$currentSem ?>;
let activeSystemYear = <?= (int)$currentYear ?>;
let selectedSem = <?= (int)$currentSem ?>;
let selectedYear = <?= (int)$currentYear ?>;

let allClubs = [];
let allByClass = [];
let allUnregistered = [];
let currentFilterStatus = 'all';
let chartGradeViewMode = 'grade'; // 'grade' or 'room'

// Chart.js instances
let chartByGradeInstance = null;
let chartRegDonutInstance = null;
let chartCapacityDonutInstance = null;

const STATUS_LABEL = { draft:'ร่าง', open:'เปิดรับ', closed:'ปิด', archived:'เก็บถาวร' };
const STATUS_CLS   = {
    draft: 'bg-slate-100 text-slate-600 border border-slate-200',
    open: 'bg-emerald-50 text-emerald-700 border border-emerald-200',
    closed: 'bg-rose-50 text-rose-700 border border-rose-200',
    archived: 'bg-slate-200 text-slate-700 border border-slate-300'
};

// --- Live Real-time Clock ---
function startLiveClock() {
    const update = () => {
        const now = new Date();
        const dateEl = document.getElementById('live_date');
        const clockEl = document.getElementById('live_clock');
        if (dateEl) {
            dateEl.textContent = now.toLocaleDateString('th-TH', {
                weekday: 'long',
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            });
        }
        if (clockEl) {
            const h = String(now.getHours()).padStart(2, '0');
            const m = String(now.getMinutes()).padStart(2, '0');
            const s = String(now.getSeconds()).padStart(2, '0');
            clockEl.textContent = `${h}:${m}:${s} น.`;
        }
    };
    update();
    setInterval(update, 1000);
}

function toggleFullScreen() {
    if (!document.fullscreenElement) {
        document.documentElement.requestFullscreen().catch(() => {});
    } else {
        if (document.exitFullscreen) {
            document.exitFullscreen();
        }
    }
}

// --- Term Switching ---
function switchTerm(val) {
    const parts = val.split('-');
    if (parts.length === 2) {
        selectedSem = parseInt(parts[0]);
        selectedYear = parseInt(parts[1]);
        updateTermUI();
        loadClubs();
    }
}

function updateTermUI() {
    const isCurrent = (selectedSem === activeSystemSem && selectedYear === activeSystemYear);
    const badge = document.getElementById('term_active_badge');
    if (badge) {
        badge.className = isCurrent 
            ? 'px-2.5 py-1 rounded-full text-xs font-bold bg-emerald-50 text-emerald-700 border border-emerald-200 flex items-center gap-1' 
            : 'px-2.5 py-1 rounded-full text-xs font-bold bg-slate-100 text-slate-600 border border-slate-200 flex items-center gap-1';
        badge.innerHTML = isCurrent 
            ? '<i class="bi bi-check-circle-fill"></i> ภาคเรียนปัจจุบัน' 
            : '<i class="bi bi-clock-history"></i> ข้อมูลย้อนหลัง';
    }

    const tBadge = document.getElementById('table_term_badge');
    if (tBadge) {
        tBadge.textContent = `ภาคเรียนที่ ${selectedSem}/${selectedYear}`;
    }

    const printSummaryLink = document.getElementById('link_print_summary');
    if (printSummaryLink) {
        printSummaryLink.href = `/club/print_summary.php?semester=${selectedSem}&year=${selectedYear}`;
    }
    const printUnregLink = document.getElementById('link_print_unreg');
    if (printUnregLink) {
        printUnregLink.href = `/club/print_unreg.php?semester=${selectedSem}&year=${selectedYear}`;
    }
    const modalPrintUnreg = document.getElementById('btn_print_unreg_modal');
    if (modalPrintUnreg) {
        modalPrintUnreg.href = `/club/print_unreg.php?semester=${selectedSem}&year=${selectedYear}`;
    }
}

// --- Data Fetching ---
async function loadClubs() {
    document.getElementById('table_loading').classList.remove('hidden');
    document.getElementById('table_empty').classList.add('hidden');
    document.getElementById('table_wrapper').style.display = 'none';

    try {
        const res = await fetch(`/club/api/clubs_list.php?semester=${selectedSem}&year=${selectedYear}`);
        const data = await res.json();
        document.getElementById('table_loading').classList.add('hidden');

        if (data.active_semester && data.active_year) {
            activeSystemSem = parseInt(data.active_semester);
            activeSystemYear = parseInt(data.active_year);
            updateTermUI();
        }

        // Dropdown terms populate
        if (data.terms && Array.isArray(data.terms)) {
            const sel = document.getElementById('term_selector');
            const currentVal = `${selectedSem}-${selectedYear}`;
            let html = '';
            data.terms.forEach(t => {
                const val = `${t.semester}-${t.year}`;
                const isCur = (t.semester == activeSystemSem && t.year == activeSystemYear);
                const label = `ภาคเรียนที่ ${t.semester}/${t.year}` + (isCur ? ' ★ (ปัจจุบัน)' : '');
                html += `<option value="${val}" ${val === currentVal ? 'selected' : ''}>${label}</option>`;
            });
            if (html) sel.innerHTML = html;
        }

        allClubs = data.data || [];
        allByClass = data.by_class || [];
        allUnregistered = data.unregistered_list || [];
        const totalStudents = data.total_students || 548;

        if (!allClubs.length) {
            document.getElementById('table_empty').classList.remove('hidden');
            renderKpis(0, 0, totalStudents, 0, 0, 0);
            renderCharts([], 0, 0, 0);
            renderRanking([]);
            return;
        }

        document.getElementById('table_wrapper').style.display = '';

        // Calculate Stats
        let totalReg = 0, totalSess = 0, fullClubs = 0, noSessClubs = 0;
        allClubs.forEach(c => {
            const reg = parseInt(c.registered_count) || 0;
            const cap = parseInt(c.max_capacity) || 0;
            const sess = parseInt(c.session_count) || 0;
            totalReg += reg;
            totalSess += sess;
            if (cap > 0 && reg >= cap) fullClubs++;
            if (sess === 0) noSessClubs++;
        });

        const unregCount = Math.max(0, totalStudents - totalReg);

        renderKpis(allClubs.length, totalReg, unregCount, totalSess, fullClubs, noSessClubs, totalStudents);
        renderTable();
        renderRanking(allClubs);
        renderCharts(allByClass, totalReg, unregCount, totalStudents);
        updateFilterCounts();

    } catch (e) {
        console.error(e);
        document.getElementById('table_loading').innerHTML = '<div class="text-rose-500 py-6 text-center font-bold">เกิดข้อผิดพลาดในการโหลดข้อมูล</div>';
    }
}

// --- Render KPI Cards ---
function renderKpis(clubCount, registered, unregistered, sessions, fullClubs, noSessClubs, totalStudents = 548) {
    document.getElementById('kpi_clubs').textContent = clubCount;
    document.getElementById('kpi_registered').textContent = registered;
    document.getElementById('kpi_unreg').textContent = unregistered;
    document.getElementById('kpi_sessions').textContent = sessions;
    document.getElementById('kpi_full').textContent = fullClubs;
    document.getElementById('kpi_no_session').textContent = noSessClubs;

    const regPct = totalStudents > 0 ? Math.round((registered / totalStudents) * 100) : 0;
    const unregPct = totalStudents > 0 ? Math.round((unregistered / totalStudents) * 100) : 0;
    const fullPct = clubCount > 0 ? Math.round((fullClubs / clubCount) * 100) : 0;
    const noSessPct = clubCount > 0 ? Math.round((noSessClubs / clubCount) * 100) : 0;
    const avgSess = clubCount > 0 ? (sessions / clubCount).toFixed(1) : 0;

    document.getElementById('kpi_reg_pct').textContent = `${regPct}%`;
    document.getElementById('kpi_unreg_pct').textContent = `${unregPct}%`;
    document.getElementById('kpi_full_pct').textContent = `${fullPct}%`;
    document.getElementById('kpi_no_session_pct').textContent = `${noSessPct}%`;
    document.getElementById('kpi_avg_sessions').textContent = `${avgSess} คาบ/ชุมนุม`;

    document.getElementById('kpi_reg_bar').style.width = `${regPct}%`;
    document.getElementById('kpi_unreg_bar').style.width = `${unregPct}%`;
    document.getElementById('kpi_full_bar').style.width = `${fullPct}%`;
    document.getElementById('kpi_no_session_bar').style.width = `${noSessPct}%`;

    const qCount = document.getElementById('quick_unreg_count');
    if (qCount) qCount.textContent = `ค้าง ${unregistered} คน`;
}

// --- Render Main Table with Live Filter & Search ---
function renderTable() {
    const tbody = document.getElementById('clubs_tbody');
    tbody.innerHTML = '';

    const query = (document.getElementById('club_search').value || '').trim().toLowerCase();

    const filtered = allClubs.filter(c => {
        // Status filter
        const reg = parseInt(c.registered_count) || 0;
        const cap = parseInt(c.max_capacity) || 0;
        const sess = parseInt(c.session_count) || 0;

        if (currentFilterStatus === 'open' && (cap > 0 && reg >= cap)) return false;
        if (currentFilterStatus === 'full' && (cap === 0 || reg < cap)) return false;
        if (currentFilterStatus === 'no_session' && sess > 0) return false;

        // Search text
        if (query) {
            const matchName = (c.name || '').toLowerCase().includes(query);
            const matchRoom = (c.room || '').toLowerCase().includes(query);
            const matchTeachers = `${c.teacher_name} ${c.teacher_name_2} ${c.teacher_name_3}`.toLowerCase().includes(query);
            if (!matchName && !matchRoom && !matchTeachers) return false;
        }

        return true;
    });

    if (!filtered.length) {
        tbody.innerHTML = `<tr><td colspan="7" class="text-center py-8 text-slate-400 font-medium">ไม่พบชุมนุมตามเงื่อนไขที่ค้นหา</td></tr>`;
        return;
    }

    filtered.forEach(c => {
        const reg = parseInt(c.registered_count) || 0;
        const cap = parseInt(c.max_capacity) || 0;
        const sess = parseInt(c.session_count) || 0;
        const pct = cap > 0 ? Math.round((reg / cap) * 100) : 0;

        const isFull = (cap > 0 && reg >= cap);
        const barColor = isFull ? 'bg-rose-500' : pct >= 75 ? 'bg-amber-500' : 'bg-emerald-500';

        // Actions
        let btns = `
            <a href="/club/sessions.php?club_id=${c.id}" class="w-7 h-7 rounded-lg bg-blue-50 hover:bg-blue-100 text-blue-600 inline-flex items-center justify-center transition-all me-1" title="คาบกิจกรรม">
                <i class="bi bi-calendar-check"></i>
            </a>
            <a href="/club/members.php?club_id=${c.id}" class="w-7 h-7 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 inline-flex items-center justify-center transition-all me-1" title="รายชื่อสมาชิก">
                <i class="bi bi-people"></i>
            </a>
            <a href="/club/results.php?club_id=${c.id}" class="w-7 h-7 rounded-lg bg-purple-50 hover:bg-purple-100 text-purple-600 inline-flex items-center justify-center transition-all me-1" title="ผลประเมิน ปพ.">
                <i class="bi bi-award"></i>
            </a>
        `;

        if (userRole === 'super_admin' || userRole === 'club_admin') {
            btns += `
                <a href="/club/manage.php?id=${c.id}" class="w-7 h-7 rounded-lg bg-amber-50 hover:bg-amber-100 text-amber-600 inline-flex items-center justify-center transition-all me-1" title="แก้ไข">
                    <i class="bi bi-pencil-square"></i>
                </a>
                <button onclick="deleteClub(${c.id}, '${escHtml(c.name)}')" class="w-7 h-7 rounded-lg bg-rose-50 hover:bg-rose-100 text-rose-600 inline-flex items-center justify-center transition-all" title="ลบ">
                    <i class="bi bi-trash"></i>
                </button>
            `;
        } else if (userRole === 'att_teacher') {
            const myId = parseInt(teacherId) || 0;
            const advisors = [parseInt(c.teacher_id)||0, parseInt(c.teacher_id_2)||0, parseInt(c.teacher_id_3)||0];
            if (myId > 0 && advisors.includes(myId)) {
                btns += `
                    <a href="/club/manage.php?id=${c.id}" class="w-7 h-7 rounded-lg bg-amber-50 hover:bg-amber-100 text-amber-600 inline-flex items-center justify-center transition-all" title="แก้ไข">
                        <i class="bi bi-pencil-square"></i>
                    </a>
                `;
            }
        }

        const teachers = [c.teacher_name, c.teacher_name_2, c.teacher_name_3].filter(Boolean).join('<br>');

        const tr = document.createElement('tr');
        tr.className = 'hover:bg-slate-50/80 transition-colors';
        tr.innerHTML = `
            <td class="py-3 px-3">
                <div class="font-bold text-slate-800">${escHtml(c.name)}</div>
                ${c.objectives ? `<div class="text-slate-400 text-[10px] truncate max-w-xs">${escHtml(c.objectives)}</div>` : ''}
            </td>
            <td class="py-3 px-3 text-slate-600 font-medium">${teachers || '-'}</td>
            <td class="py-3 px-3">
                <span class="px-2 py-0.5 rounded-lg bg-slate-100 text-slate-600 font-semibold text-[11px]">${escHtml(c.room || '-')}</span>
            </td>
            <td class="py-3 px-3 text-center">
                <div class="font-bold text-slate-700">${reg}/${cap} <span class="text-[10px] text-slate-400">(${pct}%)</span></div>
                <div class="w-20 bg-slate-200 h-1.5 rounded-full mx-auto mt-1 overflow-hidden">
                    <div class="${barColor} h-full rounded-full" style="width: ${Math.min(100, pct)}%"></div>
                </div>
            </td>
            <td class="py-3 px-3 text-center">
                ${sess > 0 
                    ? `<span class="px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">${sess} คาบ</span>` 
                    : `<span class="px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-rose-50 text-rose-700 border border-rose-200"><i class="bi bi-exclamation-circle me-1"></i>0 คาบ</span>`}
            </td>
            <td class="py-3 px-3 text-center">
                <span class="px-2.5 py-0.5 rounded-full text-[11px] font-bold ${STATUS_CLS[c.status] || 'bg-slate-100 text-slate-600'}">
                    ${isFull ? 'เต็มแล้ว' : (STATUS_LABEL[c.status] || c.status)}
                </span>
            </td>
            <td class="py-3 px-3 text-center whitespace-nowrap">${btns}</td>
        `;
        tbody.appendChild(tr);
    });
}

function updateFilterCounts() {
    let openCount = 0, fullCount = 0, noSessCount = 0;
    allClubs.forEach(c => {
        const reg = parseInt(c.registered_count) || 0;
        const cap = parseInt(c.max_capacity) || 0;
        const sess = parseInt(c.session_count) || 0;
        if (cap > 0 && reg >= cap) fullCount++;
        else openCount++;
        if (sess === 0) noSessCount++;
    });

    document.getElementById('count_all').textContent = allClubs.length;
    document.getElementById('count_open').textContent = openCount;
    document.getElementById('count_full').textContent = fullCount;
    document.getElementById('count_no_session').textContent = noSessCount;
}

function setFilterStatus(status) {
    currentFilterStatus = status;
    ['all', 'open', 'full', 'no_session'].forEach(s => {
        const btn = document.getElementById(`pill_${s}`);
        if (!btn) return;
        if (s === status) {
            btn.className = 'px-3 py-1.5 rounded-xl bg-blue-600 text-white shadow-sm shadow-blue-500/20 whitespace-nowrap';
        } else {
            btn.className = 'px-3 py-1.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-600 whitespace-nowrap';
        }
    });
    renderTable();
}

function filterClubsTable() {
    renderTable();
}

// --- Render Activity Progress Ranking (Right Column) ---
function renderRanking(clubs) {
    const list = document.getElementById('ranking_list');
    if (!list) return;

    if (!clubs || !clubs.length) {
        list.innerHTML = `<div class="text-center py-6 text-xs text-slate-400">ยังไม่มีข้อมูลกิจกรรม</div>`;
        return;
    }

    // Sort by session count descending
    const sorted = [...clubs].sort((a, b) => (parseInt(b.session_count) || 0) - (parseInt(a.session_count) || 0)).slice(0, 5);

    const colors = [
        { bg: 'bg-emerald-500', text: 'text-emerald-700' },
        { bg: 'bg-blue-500',    text: 'text-blue-700' },
        { bg: 'bg-indigo-500',  text: 'text-indigo-700' },
        { bg: 'bg-amber-500',   text: 'text-amber-700' },
        { bg: 'bg-cyan-500',    text: 'text-cyan-700' }
    ];

    list.innerHTML = sorted.map((c, idx) => {
        const sess = parseInt(c.session_count) || 0;
        // Assume target is 20 sessions per semester
        const target = 20;
        const pct = Math.min(100, Math.round((sess / target) * 100));
        const col = colors[idx % colors.length];

        return `
            <div>
                <div class="flex items-center justify-between text-xs font-bold mb-1">
                    <span class="text-slate-700 truncate max-w-[180px]">${escHtml(c.name)}</span>
                    <span class="${col.text}">${pct}% (${sess}/${target})</span>
                </div>
                <div class="w-full bg-slate-100 h-2 rounded-full overflow-hidden">
                    <div class="${col.bg} h-full rounded-full transition-all duration-700" style="width: ${pct}%"></div>
                </div>
            </div>
        `;
    }).join('');
}

// --- Interactive Charts via Chart.js ---
function renderCharts(byClass, registered, unregistered, totalStudents) {
    // 1. Chart By Grade / Class
    renderGradeBarChart(byClass);

    // 2. Chart Registration Donut
    renderRegistrationDonut(registered, unregistered, totalStudents);

    // 3. Chart Capacity Donut
    renderCapacityDonut();
}

function renderGradeBarChart(byClass) {
    const ctx = document.getElementById('chartByGrade');
    if (!ctx) return;

    if (chartByGradeInstance) {
        chartByGradeInstance.destroy();
    }

    let labels = [], regData = [], unregData = [];

    if (chartGradeViewMode === 'grade') {
        // Group by grade: ม.1, ม.2, ม.3, ม.4, ม.5, ม.6
        const gradeMap = { 'ม.1': { reg: 0, tot: 0 }, 'ม.2': { reg: 0, tot: 0 }, 'ม.3': { reg: 0, tot: 0 }, 'ม.4': { reg: 0, tot: 0 }, 'ม.5': { reg: 0, tot: 0 }, 'ม.6': { reg: 0, tot: 0 } };

        byClass.forEach(row => {
            const m = row.classroom.match(/^(ม\.[1-6])/);
            if (m && gradeMap[m[1]]) {
                gradeMap[m[1]].tot += parseInt(row.total) || 0;
                gradeMap[m[1]].reg += parseInt(row.registered) || 0;
            }
        });

        labels = Object.keys(gradeMap);
        regData = labels.map(g => gradeMap[g].reg);
        unregData = labels.map(g => Math.max(0, gradeMap[g].tot - gradeMap[g].reg));
    } else {
        // By room
        labels = byClass.map(r => r.classroom);
        regData = byClass.map(r => parseInt(r.registered) || 0);
        unregData = byClass.map(r => Math.max(0, (parseInt(r.total) || 0) - (parseInt(r.registered) || 0)));
    }

    chartByGradeInstance = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [
                {
                    label: 'ลงทะเบียนแล้ว',
                    data: regData,
                    backgroundColor: '#10b981',
                    borderRadius: 6,
                    barPercentage: 0.65
                },
                {
                    label: 'ยังไม่ลงทะเบียน',
                    data: unregData,
                    backgroundColor: '#f59e0b',
                    borderRadius: 6,
                    barPercentage: 0.65
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top',
                    labels: { boxWidth: 12, font: { family: 'Prompt', size: 11, weight: 'bold' } }
                },
                tooltip: {
                    titleFont: { family: 'Prompt' },
                    bodyFont: { family: 'Prompt' }
                }
            },
            scales: {
                x: {
                    stacked: true,
                    grid: { display: false },
                    ticks: { font: { family: 'Prompt', size: 10 } }
                },
                y: {
                    stacked: true,
                    grid: { color: '#f1f5f9' },
                    ticks: { font: { family: 'Prompt', size: 10 } }
                }
            }
        }
    });
}

function toggleChartView(mode) {
    chartGradeViewMode = mode;
    const btnGrade = document.getElementById('btn_view_grade');
    const btnRoom = document.getElementById('btn_view_room');
    if (mode === 'grade') {
        btnGrade.className = 'px-2.5 py-1 rounded-lg bg-white shadow-sm text-blue-600';
        btnRoom.className = 'px-2.5 py-1 rounded-lg text-slate-500 hover:text-slate-800';
    } else {
        btnRoom.className = 'px-2.5 py-1 rounded-lg bg-white shadow-sm text-blue-600';
        btnGrade.className = 'px-2.5 py-1 rounded-lg text-slate-500 hover:text-slate-800';
    }
    renderGradeBarChart(allByClass);
}

function renderRegistrationDonut(registered, unregistered, totalStudents) {
    const ctx = document.getElementById('chartRegDonut');
    if (!ctx) return;

    if (chartRegDonutInstance) {
        chartRegDonutInstance.destroy();
    }

    const pct = totalStudents > 0 ? Math.round((registered / totalStudents) * 100) : 0;
    document.getElementById('donut_reg_center_pct').textContent = `${pct}%`;
    document.getElementById('donut_reg_count').textContent = registered;
    document.getElementById('donut_unreg_count').textContent = unregistered;

    chartRegDonutInstance = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['ลงทะเบียนแล้ว', 'ยังไม่ลงทะเบียน'],
            datasets: [{
                data: [registered, unregistered],
                backgroundColor: ['#10b981', '#f59e0b'],
                borderWidth: 0,
                hoverOffset: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '72%',
            plugins: {
                legend: { display: false },
                tooltip: {
                    titleFont: { family: 'Prompt' },
                    bodyFont: { family: 'Prompt' }
                }
            }
        }
    });
}

function renderCapacityDonut() {
    const ctx = document.getElementById('chartCapacityDonut');
    if (!ctx) return;

    if (chartCapacityDonutInstance) {
        chartCapacityDonutInstance.destroy();
    }

    let fullCount = 0, openCount = 0;
    allClubs.forEach(c => {
        const reg = parseInt(c.registered_count) || 0;
        const cap = parseInt(c.max_capacity) || 0;
        if (cap > 0 && reg >= cap) fullCount++;
        else openCount++;
    });

    document.getElementById('donut_cap_center_count').textContent = allClubs.length;
    document.getElementById('donut_cap_full').textContent = fullCount;
    document.getElementById('donut_cap_open').textContent = openCount;

    chartCapacityDonutInstance = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['เต็มแล้ว', 'ยังเปิดรับ'],
            datasets: [{
                data: [fullCount, openCount],
                backgroundColor: ['#06b6d4', '#6366f1'],
                borderWidth: 0,
                hoverOffset: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '72%',
            plugins: {
                legend: { display: false },
                tooltip: {
                    titleFont: { family: 'Prompt' },
                    bodyFont: { family: 'Prompt' }
                }
            }
        }
    });
}

// --- Unregistered Students Modal Logic ---
function openUnregModal() {
    const modal = document.getElementById('unregModal');
    if (!modal) return;
    modal.classList.remove('hidden');
    renderUnregModalTable();
}

function closeUnregModal() {
    const modal = document.getElementById('unregModal');
    if (modal) modal.classList.add('hidden');
}

function renderUnregModalTable() {
    const tbody = document.getElementById('unreg_modal_tbody');
    const query = (document.getElementById('modal_unreg_search').value || '').trim().toLowerCase();
    tbody.innerHTML = '';

    const filtered = allUnregistered.filter(s => {
        if (!query) return true;
        return (s.student_id || '').toLowerCase().includes(query) ||
               (s.name || '').toLowerCase().includes(query) ||
               (s.classroom || '').toLowerCase().includes(query);
    });

    document.getElementById('modal_unreg_subtitle').textContent = `มีนักเรียนค้าง ${allUnregistered.length} คน (ม.1 - ม.6)`;
    document.getElementById('modal_unreg_filter_count').textContent = `แสดง ${filtered.length} จาก ${allUnregistered.length} คน`;

    if (!filtered.length) {
        tbody.innerHTML = `<tr><td colspan="4" class="text-center py-6 text-slate-400">ไม่พบรายชื่อตามที่ค้นหา</td></tr>`;
        return;
    }

    filtered.forEach((s, idx) => {
        const tr = document.createElement('tr');
        tr.className = 'hover:bg-slate-50 transition-colors';
        tr.innerHTML = `
            <td class="py-2.5 px-3 text-slate-400 font-bold">${idx + 1}</td>
            <td class="py-2.5 px-3 font-mono font-bold text-slate-700">${escHtml(s.student_id)}</td>
            <td class="py-2.5 px-3 font-semibold text-slate-800">${escHtml(s.name)}</td>
            <td class="py-2.5 px-3">
                <span class="px-2 py-0.5 rounded-md bg-amber-50 text-amber-700 font-bold text-[11px] border border-amber-200">
                    ${escHtml(s.classroom)}
                </span>
            </td>
        `;
        tbody.appendChild(tr);
    });
}

function filterUnregModal() {
    renderUnregModalTable();
}

// --- Print Modal Logic ---
function openPrintModal() {
    const modal = document.getElementById('printModal');
    if (modal) modal.classList.remove('hidden');
}

function closePrintModal() {
    const modal = document.getElementById('printModal');
    if (modal) modal.classList.add('hidden');
}

// --- Actions (Clone / Delete) ---
async function confirmCloneClubs() {
    const { isConfirmed } = await Swal.fire({
        icon: 'question',
        title: 'คัดลอกชุมนุมสู่ภาคเรียนที่ 2?',
        html: `ต้องการคัดลอกรายชื่อชุมนุมเดิมจาก <b>ภาคเรียนที่ 1/2569</b> มายัง <b>ภาคเรียนที่ 2/2569</b> หรือไม่?<br><small class="text-slate-400">ระบบจะคัดลอกชื่อชุมนุม ครูที่ปรึกษา และห้อง โดยเปิดสถานะเป็น 'เปิดรับ' และเริ่มรับนักเรียนใหม่</small>`,
        showCancelButton: true,
        confirmButtonText: '<i class="bi bi-copy me-1"></i> ยืนยันคัดลอก',
        cancelButtonText: 'ยกเลิก',
        confirmButtonColor: '#4f46e5'
    });
    if (!isConfirmed) return;

    Swal.fire({ title: 'กำลังคัดลอกข้อมูล...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

    try {
        const res = await fetch('/club/api/clone_clubs.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ from_semester: 1, from_year: 2569, to_semester: 2, to_year: 2569 })
        });
        const data = await res.json();
        if (data.status === 'success') {
            await Swal.fire({
                icon: 'success',
                title: 'คัดลอกเรียบร้อย',
                text: data.message,
                confirmButtonColor: '#4f46e5'
            });
            selectedSem = 2;
            selectedYear = 2569;
            const sel = document.getElementById('term_selector');
            if (sel) sel.value = '2-2569';
            updateTermUI();
            loadClubs();
        } else {
            Swal.fire({ icon: 'warning', title: 'แจ้งเตือน', text: data.message, confirmButtonColor: '#4f46e5' });
        }
    } catch (e) {
        Swal.fire({ icon: 'error', title: 'เกิดข้อผิดพลาด', text: e.message, confirmButtonColor: '#4f46e5' });
    }
}

async function deleteClub(id, name) {
    const { isConfirmed } = await Swal.fire({
        icon: 'warning',
        title: 'ลบชุมนุม?',
        text: `"${name}" จะถูกลบถาวร`,
        showCancelButton: true,
        confirmButtonText: 'ลบข้อมูล',
        cancelButtonText: 'ยกเลิก',
        confirmButtonColor: '#e11d48'
    });
    if (!isConfirmed) return;

    const res = await fetch('/club/api/delete_club.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id })
    });
    const data = await res.json();
    if (data.status === 'success') {
        Swal.fire({ icon: 'success', title: 'ลบสำเร็จ', timer: 1200, showConfirmButton: false });
        loadClubs();
    } else {
        Swal.fire({ icon: 'error', title: 'เกิดข้อผิดพลาด', text: data.message, confirmButtonColor: '#4f46e5' });
    }
}

function escHtml(s) {
    return String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

document.addEventListener('DOMContentLoaded', () => {
    startLiveClock();
    updateTermUI();
    loadClubs();
});
</script>

<?php require_once __DIR__ . '/../components/layout_end.php'; ?>
