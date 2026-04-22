<?php
/**
 * assembly/dashboard.php — ระบบเช็คชื่อเข้าแถวและแต่งกายนักเรียน
 * Roles: att_teacher, super_admin, wfh_admin
 */
session_start();
require_once __DIR__ . '/../config.php';

if (!isset($_SESSION['llw_role'])) {
    header('Location: ' . $base_path . '/login.php?redirect=' . urlencode($_SERVER['REQUEST_URI'])); exit();
}
if (!in_array($_SESSION['llw_role'], ['att_teacher', 'super_admin', 'wfh_admin'])) {
    header('Location: ' . $base_path . '/login.php'); exit();
}

$pageTitle    = 'เช็คชื่อเข้าแถว';
$pageSubtitle = 'ระบบเช็คชื่อเข้าแถวและแต่งกายนักเรียน';
$activeSystem = 'assembly';

require_once __DIR__ . '/../components/layout_start.php';
?>

<!-- ─── Tab Navigation ─── -->
<div class="mb-6">
    <div class="bg-white rounded-2xl shadow-xl shadow-amber-100/50 p-1.5 inline-flex flex-wrap gap-1 border border-amber-100/50">
        <button id="tab-btn-attendance" onclick="showTab('attendance')"
            class="tab-btn tab-active px-5 py-2.5 rounded-xl text-sm font-bold transition-all flex items-center gap-2">
            <i class="bi bi-check2-all"></i> เช็คชื่อเข้าแถว
        </button>
        <button id="tab-btn-checkout" onclick="showTab('checkout')"
            class="tab-btn tab-inactive px-5 py-2.5 rounded-xl text-sm font-bold transition-all flex items-center gap-2">
            <i class="bi bi-house-door"></i> กลับบ้าน
        </button>
        <button id="tab-btn-overview" onclick="showTab('overview')"
            class="tab-btn tab-inactive px-5 py-2.5 rounded-xl text-sm font-bold transition-all flex items-center gap-2">
            <i class="bi bi-bar-chart-fill"></i> สรุปภาพรวม
        </button>
        <button id="tab-btn-individual" onclick="showTab('individual')"
            class="tab-btn tab-inactive px-5 py-2.5 rounded-xl text-sm font-bold transition-all flex items-center gap-2">
            <i class="bi bi-person-lines-fill"></i> รายบุคคล
        </button>
    </div>
</div>

<!-- ─── TAB: ATTENDANCE ─── -->
<div id="tab-attendance" class="tab-content">
    <div class="bg-white rounded-2xl shadow-xl shadow-amber-100/50 p-6 border border-amber-100/30">
        <div class="flex items-center gap-3 mb-6">
            <div class="w-10 h-10 bg-gradient-to-br from-amber-500 to-orange-500 rounded-xl flex items-center justify-center shadow-lg shadow-amber-200/50">
                <i class="bi bi-check2-all text-white text-lg"></i>
            </div>
            <div>
                <h2 class="text-lg font-black text-slate-800">เช็คชื่อและเครื่องแต่งกาย</h2>
                <p class="text-xs text-slate-400">บันทึกการเข้าแถวประจำวัน</p>
            </div>
            <div class="ml-auto">
                <span id="current-datetime" class="text-sm text-slate-500 font-medium"></span>
            </div>
        </div>

        <!-- Filters -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-5">
            <div>
                <label class="text-[10px] font-black text-slate-400 uppercase tracking-wider mb-1.5 block">ห้องเรียน</label>
                <select id="att-classroom" class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-4 py-3 text-sm focus:ring-2 focus:ring-amber-400 outline-none transition-all">
                    <option value="">เลือกห้องเรียน...</option>
                </select>
            </div>
            <div>
                <label class="text-[10px] font-black text-slate-400 uppercase tracking-wider mb-1.5 block">วันที่</label>
                <input id="att-date" type="date" class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-4 py-3 text-sm focus:ring-2 focus:ring-amber-400 outline-none transition-all">
            </div>
            <div class="flex items-end gap-2">
                <button onclick="loadStudents()" class="flex-1 bg-gradient-to-r from-amber-500 to-orange-500 text-white px-4 py-3 rounded-2xl font-bold text-sm shadow-lg shadow-amber-200/50 hover:shadow-amber-300/50 hover:scale-[1.02] transition-all flex items-center justify-center gap-2">
                    <i class="bi bi-arrow-clockwise"></i> โหลดรายชื่อ
                </button>
            </div>
        </div>

        <!-- Action Buttons -->
        <div id="att-actions" class="hidden flex flex-wrap gap-3 mb-5">
            <button onclick="markAllPresent()" class="bg-emerald-500 text-white px-4 py-2.5 rounded-2xl text-sm font-bold shadow-lg shadow-emerald-200/50 hover:bg-emerald-600 hover:scale-[1.02] transition-all flex items-center gap-2">
                <i class="bi bi-check-all"></i> มาทั้งห้อง
            </button>
            <button onclick="saveAllAttendance()" class="bg-gradient-to-r from-blue-600 to-indigo-600 text-white px-4 py-2.5 rounded-2xl text-sm font-bold shadow-lg shadow-blue-200/50 hover:scale-[1.02] transition-all flex items-center gap-2">
                <i class="bi bi-cloud-upload-fill"></i> บันทึกทั้งห้อง
            </button>
            <button onclick="printDailyReport()" class="bg-cyan-500 text-white px-4 py-2.5 rounded-2xl text-sm font-bold shadow-lg shadow-cyan-200/50 hover:bg-cyan-600 hover:scale-[1.02] transition-all flex items-center gap-2">
                <i class="bi bi-printer"></i> ปริ้นรายงาน
            </button>
            <div id="advisor-badge" class="hidden px-4 py-2.5 bg-amber-50 text-amber-700 rounded-2xl text-sm font-bold border border-amber-200">
                <i class="bi bi-person-fill"></i> <span id="advisor-name"></span>
            </div>
        </div>

        <!-- Table -->
        <div class="rounded-2xl border border-slate-100 overflow-hidden">
            <div class="max-h-[500px] overflow-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 sticky top-0 z-10">
                        <tr>
                            <th class="px-3 py-3 text-[10px] font-black text-slate-400 uppercase tracking-wider text-left border-b border-slate-100">รหัส</th>
                            <th class="px-3 py-3 text-[10px] font-black text-slate-400 uppercase tracking-wider text-left border-b border-slate-100">ชื่อ-สกุล</th>
                            <th class="px-3 py-3 text-[10px] font-black text-slate-400 uppercase tracking-wider border-b border-slate-100">สถานะ</th>
                            <th class="px-3 py-3 text-[10px] font-black text-slate-400 uppercase tracking-wider border-b border-slate-100">เล็บ</th>
                            <th class="px-3 py-3 text-[10px] font-black text-slate-400 uppercase tracking-wider border-b border-slate-100">ผม</th>
                            <th class="px-3 py-3 text-[10px] font-black text-slate-400 uppercase tracking-wider border-b border-slate-100">เสื้อ</th>
                            <th class="px-3 py-3 text-[10px] font-black text-slate-400 uppercase tracking-wider border-b border-slate-100">กางเกง</th>
                            <th class="px-3 py-3 text-[10px] font-black text-slate-400 uppercase tracking-wider border-b border-slate-100">ถุงเท้า</th>
                            <th class="px-3 py-3 text-[10px] font-black text-slate-400 uppercase tracking-wider border-b border-slate-100">รองเท้า</th>
                            <th class="px-3 py-3 text-[10px] font-black text-slate-400 uppercase tracking-wider text-left border-b border-slate-100">หมายเหตุ</th>
                        </tr>
                    </thead>
                    <tbody id="att-student-table">
                        <tr>
                            <td colspan="10" class="px-4 py-12 text-center text-slate-400">
                                <i class="bi bi-people text-4xl block mb-2 opacity-30"></i>
                                เลือกห้องเรียนและวันที่ แล้วกด "โหลดรายชื่อ"
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- ─── TAB: CHECKOUT ─── -->
<div id="tab-checkout" class="tab-content hidden">
    <div class="bg-white rounded-2xl shadow-xl shadow-purple-100/50 p-6 border border-purple-100/30">
        <div class="flex items-center gap-3 mb-6">
            <div class="w-10 h-10 bg-gradient-to-br from-purple-500 to-violet-600 rounded-xl flex items-center justify-center shadow-lg shadow-purple-200/50">
                <i class="bi bi-house-door-fill text-white text-lg"></i>
            </div>
            <div>
                <h2 class="text-lg font-black text-slate-800">เช็คเวลากลับบ้าน</h2>
                <p class="text-xs text-slate-400">บันทึกการกลับบ้านตอนเย็น</p>
            </div>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-5">
            <div>
                <label class="text-[10px] font-black text-slate-400 uppercase tracking-wider mb-1.5 block">ห้องเรียน</label>
                <select id="co-classroom" class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-4 py-3 text-sm focus:ring-2 focus:ring-purple-400 outline-none transition-all">
                    <option value="">เลือกห้องเรียน...</option>
                </select>
            </div>
            <div>
                <label class="text-[10px] font-black text-slate-400 uppercase tracking-wider mb-1.5 block">วันที่</label>
                <input id="co-date" type="date" class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-4 py-3 text-sm focus:ring-2 focus:ring-purple-400 outline-none transition-all">
            </div>
            <div class="flex items-end gap-2">
                <button onclick="loadCheckout()" class="flex-1 bg-gradient-to-r from-purple-500 to-violet-600 text-white px-4 py-3 rounded-2xl font-bold text-sm shadow-lg shadow-purple-200/50 hover:scale-[1.02] transition-all flex items-center justify-center gap-2">
                    <i class="bi bi-arrow-clockwise"></i> โหลดรายชื่อ
                </button>
                <button onclick="saveCheckout()" class="flex-1 bg-gradient-to-r from-blue-600 to-indigo-600 text-white px-4 py-3 rounded-2xl font-bold text-sm shadow-lg shadow-blue-200/50 hover:scale-[1.02] transition-all flex items-center justify-center gap-2">
                    <i class="bi bi-cloud-upload-fill"></i> บันทึก
                </button>
            </div>
        </div>
        <div class="rounded-2xl border border-slate-100 overflow-hidden">
            <div class="max-h-[500px] overflow-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 sticky top-0">
                        <tr>
                            <th class="px-3 py-3 text-[10px] font-black text-slate-400 uppercase tracking-wider text-left border-b border-slate-100">รหัส</th>
                            <th class="px-3 py-3 text-[10px] font-black text-slate-400 uppercase tracking-wider text-left border-b border-slate-100">ชื่อ-สกุล</th>
                            <th class="px-3 py-3 text-[10px] font-black text-slate-400 uppercase tracking-wider text-center border-b border-slate-100">ตอนเช้า</th>
                            <th class="px-3 py-3 text-[10px] font-black text-slate-400 uppercase tracking-wider border-b border-slate-100">สถานะเย็น</th>
                            <th class="px-3 py-3 text-[10px] font-black text-slate-400 uppercase tracking-wider text-left border-b border-slate-100">หมายเหตุ</th>
                        </tr>
                    </thead>
                    <tbody id="co-table">
                        <tr><td colspan="4" class="px-4 py-12 text-center text-slate-400"><i class="bi bi-house-door text-4xl block mb-2 opacity-30"></i>เลือกห้องเรียนและวันที่</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- ─── TAB: OVERVIEW ─── -->
<div id="tab-overview" class="tab-content hidden">
    <div class="bg-white rounded-2xl shadow-xl shadow-teal-100/50 p-6 border border-teal-100/30">
        <div class="flex items-center gap-3 mb-6">
            <div class="w-10 h-10 bg-gradient-to-br from-teal-500 to-emerald-600 rounded-xl flex items-center justify-center shadow-lg shadow-teal-200/50">
                <i class="bi bi-bar-chart-fill text-white text-lg"></i>
            </div>
            <div>
                <h2 class="text-lg font-black text-slate-800">สรุปภาพรวม</h2>
                <p class="text-xs text-slate-400">สถิติการเข้าแถวและแต่งกายรายห้อง</p>
            </div>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-5">
            <div>
                <label class="text-[10px] font-black text-slate-400 uppercase tracking-wider mb-1.5 block">ห้องเรียน</label>
                <select id="ov-classroom" class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-4 py-3 text-sm focus:ring-2 focus:ring-teal-400 outline-none transition-all">
                    <option value="">เลือกห้องเรียน...</option>
                </select>
            </div>
            <div>
                <label class="text-[10px] font-black text-slate-400 uppercase tracking-wider mb-1.5 block">เดือน</label>
                <select id="ov-month" class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-4 py-3 text-sm focus:ring-2 focus:ring-teal-400 outline-none transition-all">
                    <?php echo renderMonthOptions(true); ?>
                </select>
            </div>
            <div class="flex items-end">
                <button onclick="loadOverview()" class="w-full bg-gradient-to-r from-teal-500 to-emerald-600 text-white px-4 py-3 rounded-2xl font-bold text-sm shadow-lg shadow-teal-200/50 hover:scale-[1.02] transition-all flex items-center justify-center gap-2">
                    <i class="bi bi-bar-chart"></i> แสดงข้อมูล
                </button>
            </div>
        </div>

        <div id="ov-content" class="hidden">
            <!-- Charts -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                <div class="bg-slate-50 rounded-2xl p-5">
                    <h3 class="text-sm font-black text-slate-600 mb-3">สถิติการเข้าแถว</h3>
                    <canvas id="ov-att-chart" height="200"></canvas>
                </div>
                <div class="bg-slate-50 rounded-2xl p-5">
                    <h3 class="text-sm font-black text-slate-600 mb-3">สถิติการแต่งกาย (%)</h3>
                    <canvas id="ov-uni-chart" height="200"></canvas>
                </div>
            </div>

            <!-- Summary Table -->
            <div class="rounded-2xl border border-slate-100 overflow-hidden mb-4">
                <div class="overflow-auto max-h-96">
                    <table class="w-full text-sm">
                        <thead class="bg-slate-50 sticky top-0">
                            <tr>
                                <th class="px-3 py-3 text-[10px] font-black text-slate-400 uppercase tracking-wider text-left border-b">ชื่อ</th>
                                <th class="px-3 py-3 text-[10px] font-black text-slate-400 uppercase tracking-wider border-b">วันที่เช็ค</th>
                                <th class="px-3 py-3 text-[10px] font-black text-slate-400 uppercase tracking-wider border-b text-green-600">มา</th>
                                <th class="px-3 py-3 text-[10px] font-black text-slate-400 uppercase tracking-wider border-b text-red-500">ขาด</th>
                                <th class="px-3 py-3 text-[10px] font-black text-slate-400 uppercase tracking-wider border-b">เล็บ</th>
                                <th class="px-3 py-3 text-[10px] font-black text-slate-400 uppercase tracking-wider border-b">ผม</th>
                                <th class="px-3 py-3 text-[10px] font-black text-slate-400 uppercase tracking-wider border-b">เสื้อ</th>
                                <th class="px-3 py-3 text-[10px] font-black text-slate-400 uppercase tracking-wider border-b">กางเกง</th>
                                <th class="px-3 py-3 text-[10px] font-black text-slate-400 uppercase tracking-wider border-b">ถุงเท้า</th>
                                <th class="px-3 py-3 text-[10px] font-black text-slate-400 uppercase tracking-wider border-b">รองเท้า</th>
                                <th class="px-3 py-3 text-[10px] font-black text-slate-400 uppercase tracking-wider text-left border-b">หมายเหตุ</th>
                            </tr>
                        </thead>
                        <tbody id="ov-table"></tbody>
                    </table>
                </div>
            </div>

            <div class="flex justify-end gap-3">
                <button onclick="printDailyReportFromOverview()" class="bg-cyan-600 text-white px-5 py-2.5 rounded-2xl font-bold text-sm shadow-lg shadow-cyan-200/50 hover:bg-cyan-700 transition-all flex items-center gap-2">
                    <i class="bi bi-printer"></i> ปริ้นรายงานรายวัน
                </button>
                <a id="ov-export-btn" href="#" class="bg-emerald-600 text-white px-5 py-2.5 rounded-2xl font-bold text-sm shadow-lg shadow-emerald-200/50 hover:bg-emerald-700 transition-all flex items-center gap-2">
                    <i class="bi bi-download"></i> โหลด CSV
                </a>
            </div>
        </div>
    </div>
</div>

<!-- ─── TAB: INDIVIDUAL ─── -->
<div id="tab-individual" class="tab-content hidden">
    <div class="bg-white rounded-2xl shadow-xl shadow-indigo-100/50 p-6 border border-indigo-100/30">
        <div class="flex items-center gap-3 mb-6">
            <div class="w-10 h-10 bg-gradient-to-br from-indigo-500 to-purple-600 rounded-xl flex items-center justify-center shadow-lg shadow-indigo-200/50">
                <i class="bi bi-person-lines-fill text-white text-lg"></i>
            </div>
            <div>
                <h2 class="text-lg font-black text-slate-800">รายงานรายบุคคล</h2>
                <p class="text-xs text-slate-400">ดูประวัติการเข้าแถวของนักเรียนแต่ละคน</p>
            </div>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-5">
            <div>
                <label class="text-[10px] font-black text-slate-400 uppercase tracking-wider mb-1.5 block">รหัสนักเรียน</label>
                <input id="ind-student-id" type="text" placeholder="กรอกรหัสนักเรียน..."
                    class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-4 py-3 text-sm focus:ring-2 focus:ring-indigo-400 outline-none transition-all">
            </div>
            <div>
                <label class="text-[10px] font-black text-slate-400 uppercase tracking-wider mb-1.5 block">เดือน</label>
                <select id="ind-month" class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-4 py-3 text-sm focus:ring-2 focus:ring-indigo-400 outline-none transition-all">
                    <?php echo renderMonthOptions(true); ?>
                </select>
            </div>
            <div class="flex items-end">
                <button onclick="loadIndividual()" class="w-full bg-gradient-to-r from-indigo-500 to-purple-600 text-white px-4 py-3 rounded-2xl font-bold text-sm shadow-lg shadow-indigo-200/50 hover:scale-[1.02] transition-all flex items-center justify-center gap-2">
                    <i class="bi bi-search"></i> ค้นหา
                </button>
            </div>
        </div>

        <div id="ind-content" class="hidden">
            <!-- Info card -->
            <div class="bg-gradient-to-br from-indigo-50 to-purple-50 rounded-2xl p-5 mb-5 border border-indigo-100">
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div><p class="text-[10px] font-black text-slate-400 uppercase tracking-wider">รหัส</p><p id="ind-info-id" class="font-black text-slate-700 mt-1"></p></div>
                    <div><p class="text-[10px] font-black text-slate-400 uppercase tracking-wider">ชื่อ</p><p id="ind-info-name" class="font-black text-slate-700 mt-1"></p></div>
                    <div><p class="text-[10px] font-black text-slate-400 uppercase tracking-wider">ห้อง</p><p id="ind-info-class" class="font-black text-slate-700 mt-1"></p></div>
                    <div><p class="text-[10px] font-black text-slate-400 uppercase tracking-wider">ครูที่ปรึกษา</p><p id="ind-info-teacher" class="font-black text-slate-700 mt-1"></p></div>
                </div>
            </div>
            <!-- KPI -->
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-5">
                <div class="bg-gradient-to-br from-emerald-500 to-teal-600 text-white rounded-2xl p-4 shadow-lg shadow-emerald-200/50">
                    <p class="text-[10px] font-black uppercase tracking-wider opacity-80">วันมา</p>
                    <p id="ind-present" class="text-3xl font-black mt-1">0</p>
                </div>
                <div class="bg-gradient-to-br from-rose-500 to-pink-600 text-white rounded-2xl p-4 shadow-lg shadow-rose-200/50">
                    <p class="text-[10px] font-black uppercase tracking-wider opacity-80">วันขาด</p>
                    <p id="ind-absent" class="text-3xl font-black mt-1">0</p>
                </div>
                <div class="bg-gradient-to-br from-amber-500 to-orange-500 text-white rounded-2xl p-4 shadow-lg shadow-amber-200/50">
                    <p class="text-[10px] font-black uppercase tracking-wider opacity-80">วันลา</p>
                    <p id="ind-leave" class="text-3xl font-black mt-1">0</p>
                </div>
                <div class="bg-gradient-to-br from-indigo-500 to-purple-600 text-white rounded-2xl p-4 shadow-lg shadow-indigo-200/50">
                    <p class="text-[10px] font-black uppercase tracking-wider opacity-80">คะแนนแต่งกาย</p>
                    <p id="ind-uniform" class="text-3xl font-black mt-1">0%</p>
                </div>
            </div>
            <!-- History table -->
            <div class="rounded-2xl border border-slate-100 overflow-hidden">
                <div class="overflow-auto max-h-96">
                    <table class="w-full text-sm">
                        <thead class="bg-slate-50 sticky top-0">
                            <tr>
                                <th class="px-3 py-3 text-[10px] font-black text-slate-400 uppercase tracking-wider text-left border-b">วันที่</th>
                                <th class="px-3 py-3 text-[10px] font-black text-slate-400 uppercase tracking-wider border-b">สถานะ</th>
                                <th class="px-3 py-3 text-[10px] font-black text-slate-400 uppercase tracking-wider border-b">เล็บ</th>
                                <th class="px-3 py-3 text-[10px] font-black text-slate-400 uppercase tracking-wider border-b">ผม</th>
                                <th class="px-3 py-3 text-[10px] font-black text-slate-400 uppercase tracking-wider border-b">เสื้อ</th>
                                <th class="px-3 py-3 text-[10px] font-black text-slate-400 uppercase tracking-wider border-b">กางเกง</th>
                                <th class="px-3 py-3 text-[10px] font-black text-slate-400 uppercase tracking-wider border-b">ถุงเท้า</th>
                                <th class="px-3 py-3 text-[10px] font-black text-slate-400 uppercase tracking-wider border-b">รองเท้า</th>
                                <th class="px-3 py-3 text-[10px] font-black text-slate-400 uppercase tracking-wider text-left border-b">หมายเหตุ</th>
                            </tr>
                        </thead>
                        <tbody id="ind-history"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.tab-active   { background: linear-gradient(135deg, #f59e0b, #f97316); color: white; box-shadow: 0 4px 15px rgba(245,158,11,0.3); }
.tab-inactive { background: transparent; color: #64748b; }
.tab-inactive:hover { background: #f8fafc; color: #1e293b; }
.uniform-chk { width: 1.1rem; height: 1.1rem; accent-color: #f59e0b; cursor: pointer; }
.status-sel  { border: 1px solid #e2e8f0; border-radius: 0.75rem; padding: 0.25rem 0.5rem; font-size: 0.8rem; font-weight: 700; background: #f8fafc; cursor: pointer; }
</style>

<script>
// ─── Global Configuration ───
window.BASE = '<?= rtrim(str_replace("/assembly", "", dirname($_SERVER["SCRIPT_NAME"])), "/") ?>';
window.api  = async path => {
    try {
        const r = await fetch(window.BASE + path);
        if(!r.ok) throw new Error('HTTP ' + r.status);
        return await r.json();
    } catch(e) {
        console.error('API Error:', path, e);
        return {status:'error', message: e.message};
    }
};
window.post = async (path, data) => {
    try {
        const r = await fetch(window.BASE + path, {method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify(data)});
        if(!r.ok) throw new Error('HTTP ' + r.status);
        return await r.json();
    } catch(e) {
        console.error('POST Error:', path, e);
        return {status:'error', message: e.message};
    }
};

// ─── Document Ready ───
document.addEventListener('DOMContentLoaded', () => {
    console.log("Assembly Dashboard Ready. BASE:", window.BASE);
    
    // Init Chart.js
    try {
        if (typeof Chart !== 'undefined' && typeof ChartDataLabels !== 'undefined') {
            Chart.register(ChartDataLabels);
            console.log("ChartDataLabels registered.");
        }
    } catch (e) {
        console.warn("Chart registration error:", e);
    }

    // Load initial data
    initRooms().then(() => {
        const urlParams = new URLSearchParams(window.location.search);
        const getRoom = urlParams.get('classroom');
        if (getRoom) {
            const sel = document.getElementById('att-classroom');
            if (sel) {
                sel.value = getRoom;
                loadStudents();
            }
        }
    });

    // Set dates
    const todayStr = new Date().toISOString().split('T')[0];
    ['att-date', 'co-date'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.value = todayStr;
    });

    // Start clock
    setInterval(() => {
        const el = document.getElementById('current-datetime');
        if (el) el.textContent = new Date().toLocaleString('th-TH', {dateStyle:'medium', timeStyle:'medium'});
    }, 1000);
});

// ─── Functions ───
function showTab(name) {
    document.querySelectorAll('.tab-content').forEach(t => t.classList.add('hidden'));
    document.querySelectorAll('.tab-btn').forEach(b => { 
        b.classList.remove('tab-active'); 
        b.classList.add('tab-inactive'); 
    });
    const content = document.getElementById('tab-' + name);
    const btn     = document.getElementById('tab-btn-' + name);
    if(content) content.classList.remove('hidden');
    if(btn) { btn.classList.remove('tab-inactive'); btn.classList.add('tab-active'); }
}

async function initRooms() {
    console.log("Loading rooms...");
    const res = await window.api('/assembly/api/get_rooms.php');
    if (res.status !== 'success') {
        console.error("Room load failed:", res.message);
        return;
    }
    console.log("Rooms loaded:", res.classrooms.length);
    ['att-classroom', 'co-classroom', 'ov-classroom'].forEach(id => {
        const el = document.getElementById(id);
        if (!el) return;
        el.innerHTML = '<option value="">เลือกห้องเรียน...</option>' +
            res.classrooms.map(c => `<option value="${c}">${c}</option>`).join('');
    });
}

// ─── ATTENDANCE ───
async function loadStudents() {
    const classroom = document.getElementById('att-classroom').value;
    const date      = document.getElementById('att-date').value;
    if (!classroom || !date) { Swal.fire('กรุณาเลือกห้องเรียนและวันที่','','warning'); return; }

    Swal.fire({ title: 'กำลังโหลด...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
    const res = await api(`/assembly/api/get_students.php?classroom=${encodeURIComponent(classroom)}&date=${date}`);
    Swal.close();

    if (res.status !== 'success') { Swal.fire('เกิดข้อผิดพลาด', res.message, 'error'); return; }

    const tbody = document.getElementById('att-student-table');
    if (!res.students.length) {
        tbody.innerHTML = `<tr><td colspan="10" class="px-4 py-8 text-center text-slate-400">ไม่พบนักเรียนในห้องนี้</td></tr>`;
        return;
    }

    tbody.innerHTML = res.students.map(s => {
        const hasRecord = s.status !== null;
        const status = s.status || 'ม';
        const checked = (field) => (s[field] ?? 'ถูก') === 'ถูก';

        return `<tr class="border-b border-slate-50 hover:bg-amber-50/30 transition-colors">
            <td class="px-3 py-2.5 text-xs font-bold text-slate-400">${esc(s.student_id)}</td>
            <td class="px-3 py-2.5 text-sm font-medium text-slate-700">${esc(s.name)}</td>
            <td class="px-3 py-2.5 text-center">
                <select class="status-sel" data-sid="${esc(s.student_id)}" onchange="onStatusChange(this)">
                    <option value="ม" ${status==='ม'?'selected':''}>ม</option>
                    <option value="ข" ${status==='ข'?'selected':''}>ข</option>
                    <option value="ล" ${status==='ล'?'selected':''}>ล</option>
                    <option value="ด" ${status==='ด'?'selected':''}>ด</option>
                </select>
            </td>
            ${['nail','hair','shirt','pants','socks','shoes'].map(f =>
                `<td class="px-3 py-2.5 text-center"><input type="checkbox" class="uniform-chk" data-field="${f}" ${checked(f)?'checked':''}></td>`
            ).join('')}
            <td class="px-3 py-2.5 min-w-[120px]">
                <input type="text" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-2 py-1 text-xs focus:ring-1 focus:ring-amber-400 outline-none" value="${esc(s.note||'')}" placeholder="หมายเหตุ">
            </td>
        </tr>`;
    }).join('');

    document.getElementById('att-actions').classList.remove('hidden');
    if (res.teacher) {
        document.getElementById('advisor-name').textContent = 'ครูที่ปรึกษา: ' + res.teacher;
        document.getElementById('advisor-badge').classList.remove('hidden');
    }

    Swal.fire({ icon:'success', title:'โหลดสำเร็จ', text:`พบนักเรียน ${res.students.length} คน`, timer:1500, showConfirmButton:false });
}

function onStatusChange(sel) {
    const row   = sel.closest('tr');
    const val   = sel.value;
    const chks  = row.querySelectorAll('.uniform-chk');
    const isAbsent = ['ข','ล','ด'].includes(val);
    chks.forEach(c => c.checked = !isAbsent);
}

function markAllPresent() {
    const rows = document.querySelectorAll('#att-student-table tr');
    rows.forEach(row => {
        const sel = row.querySelector('.status-sel');
        if (sel) { sel.value = 'ม'; row.querySelectorAll('.uniform-chk').forEach(c => c.checked = true); }
    });
    Swal.fire('สำเร็จ','ตั้งค่า "มา" ให้ทุกคนแล้ว ตรวจสอบและแก้ไขได้ทันที','success');
}

async function saveAllAttendance() {
    const classroom = document.getElementById('att-classroom').value;
    const date      = document.getElementById('att-date').value;
    if (!classroom || !date) { Swal.fire('กรุณาเลือกห้องเรียนและวันที่','','warning'); return; }

    const rows    = document.querySelectorAll('#att-student-table tr');
    const records = [];
    rows.forEach(row => {
        const sid  = row.querySelector('.status-sel')?.dataset.sid;
        if (!sid) return;
        const chks = row.querySelectorAll('.uniform-chk');
        records.push({
            student_id: sid,
            status: row.querySelector('.status-sel').value,
            nail:  chks[0]?.checked ? 'ถูก' : 'ผิด',
            hair:  chks[1]?.checked ? 'ถูก' : 'ผิด',
            shirt: chks[2]?.checked ? 'ถูก' : 'ผิด',
            pants: chks[3]?.checked ? 'ถูก' : 'ผิด',
            socks: chks[4]?.checked ? 'ถูก' : 'ผิด',
            shoes: chks[5]?.checked ? 'ถูก' : 'ผิด',
            note:  row.querySelector('input[type=text]')?.value || '',
        });
    });

    if (!records.length) { Swal.fire('ไม่พบข้อมูลในตาราง','','warning'); return; }

    const confirm = await Swal.fire({
        title: 'ยืนยันการบันทึก?',
        text: `บันทึกข้อมูล ${records.length} รายการ`,
        icon: 'question', showCancelButton: true,
        confirmButtonText: 'บันทึกทั้งหมด', cancelButtonText: 'ยกเลิก',
        confirmButtonColor: '#f59e0b'
    });
    if (!confirm.isConfirmed) return;

    Swal.fire({ title: 'กำลังบันทึก...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
    const res = await post('/assembly/api/save_all.php', { records, date, classroom });
    Swal.close();

    if (res.status === 'success') {
        Swal.fire('สำเร็จ!', res.message, 'success');
    } else {
        Swal.fire('ผิดพลาด', res.message, 'error');
    }
}

// ─── CHECKOUT ───
async function loadCheckout() {
    const classroom = document.getElementById('co-classroom').value;
    const date      = document.getElementById('co-date').value;
    if (!classroom || !date) { Swal.fire('กรุณาเลือกห้องเรียนและวันที่','','warning'); return; }

    Swal.fire({ title: 'กำลังโหลด...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
    const res = await api(`/assembly/api/get_students.php?classroom=${encodeURIComponent(classroom)}&date=${date}`);
    Swal.close();

    if (res.status !== 'success') { Swal.fire('ผิดพลาด', res.message, 'error'); return; }

    document.getElementById('co-table').innerHTML = res.students.map(s => {
        const mStatus = s.morning_status || '-';
        const eStatus = s.evening_status || 'มา';
        const mClass  = { 'ม':'bg-emerald-50 text-emerald-600', 'ข':'bg-rose-50 text-rose-600', 'ล':'bg-amber-50 text-amber-600', 'ด':'bg-purple-50 text-purple-600' }[mStatus] || 'bg-slate-50 text-slate-400';
        
        return `
            <tr class="border-b border-slate-50 hover:bg-purple-50/30 transition-colors">
                <td class="px-3 py-2.5 text-xs font-bold text-slate-400">${esc(s.student_id)}</td>
                <td class="px-3 py-2.5 text-sm font-medium text-slate-700">${esc(s.name)}</td>
                <td class="px-3 py-2.5 text-center">
                    <span class="px-2 py-1 rounded-lg text-[10px] font-black ${mClass}">${mStatus}</span>
                </td>
                <td class="px-3 py-2.5 text-center">
                    <select class="status-sel" data-sid="${esc(s.student_id)}" style="background:#f3f4f6">
                        <option value="มา" ${eStatus==='มา'?'selected':''}>มา</option>
                        <option value="ไม่มา" ${eStatus==='ไม่มา'?'selected':''}>ไม่มา</option>
                        <option value="โดด" ${eStatus==='โดด'?'selected':''}>โดด</option>
                    </select>
                </td>
                <td class="px-3 py-2.5">
                    <input type="text" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-2 py-1 text-xs outline-none" 
                        value="${esc(s.evening_note || '')}" placeholder="หมายเหตุ">
                </td>
            </tr>
        `;
    }).join('') || `<tr><td colspan="5" class="px-4 py-8 text-center text-slate-400">ไม่พบนักเรียน</td></tr>`;
}

async function saveCheckout() {
    const classroom = document.getElementById('co-classroom').value;
    const date      = document.getElementById('co-date').value;
    if (!classroom || !date) { Swal.fire('กรุณาเลือกห้องเรียนและวันที่','','warning'); return; }

    const rows = document.querySelectorAll('#co-table tr');
    const records = [];
    rows.forEach(row => {
        const sid = row.querySelector('.status-sel')?.dataset.sid;
        if (!sid) return;
        records.push({
            student_id: sid,
            name:       row.querySelectorAll('td')[1].textContent.trim(),
            status:     row.querySelector('select')?.value || 'มา',
            note:       row.querySelector('input')?.value  || '',
        });
    });

    Swal.fire({ title: 'กำลังบันทึก...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
    const res = await post('/assembly/api/save_checkout.php', { records, date, classroom });
    Swal.close();
    res.status === 'success' ? Swal.fire('สำเร็จ','บันทึกสำเร็จ','success') : Swal.fire('ผิดพลาด', res.message, 'error');
}

// ─── OVERVIEW ───
let ovAttChart = null, ovUniChart = null;

async function loadOverview() {
    const classroom = document.getElementById('ov-classroom').value;
    const month     = document.getElementById('ov-month').value || 'all';
    if (!classroom) { Swal.fire('กรุณาเลือกห้องเรียน','','warning'); return; }

    Swal.fire({ title: 'กำลังโหลด...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
    const res = await api(`/assembly/api/get_overview.php?classroom=${encodeURIComponent(classroom)}&month=${month}`);
    Swal.close();
    if (res.status !== 'success') { Swal.fire('ผิดพลาด', res.message, 'error'); return; }

    document.getElementById('ov-content').classList.remove('hidden');

    // Attendance Chart
    const attCtx = document.getElementById('ov-att-chart').getContext('2d');
    if (ovAttChart) ovAttChart.destroy();
    const s = res.attendanceStats;
    ovAttChart = new Chart(attCtx, {
        type: 'doughnut',
        data: { labels: ['มา','ขาด','ลา','โดด'], datasets: [{ data: [s.present, s.absent, s.leave, s.skip], backgroundColor: ['#10b981','#f43f5e','#f59e0b','#8b5cf6'], borderWidth: 0 }] },
        options: { plugins: { legend: { position: 'right' }, datalabels: { formatter: v => v > 0 ? v + '%' : '', color: '#fff', font: { weight: 'bold' } } } }
    });

    // Uniform Chart
    const uniCtx = document.getElementById('ov-uni-chart').getContext('2d');
    if (ovUniChart) ovUniChart.destroy();
    const u = res.uniformStats;
    ovUniChart = new Chart(uniCtx, {
        type: 'bar',
        data: { labels: ['เล็บ','ทรงผม','เสื้อ','กางเกง','ถุงเท้า','รองเท้า'], datasets: [{ label: '%', data: [u.nail, u.hair, u.shirt, u.pants, u.socks, u.shoes], backgroundColor: '#f59e0b', borderRadius: 8 }] },
        options: { scales: { y: { beginAtZero: true, max: 100 } }, plugins: { legend: { display: false }, datalabels: { anchor: 'end', align: 'top', formatter: v => v + '%', font: { weight: 'bold', size: 11 } } } }
    });

    // Table
    document.getElementById('ov-table').innerHTML = res.studentsSummary.map(s => `
        <tr class="border-b border-slate-50 hover:bg-teal-50/30 transition-colors">
            <td class="px-3 py-2.5 text-sm font-medium text-slate-700">${esc(s.name)}</td>
            <td class="px-3 py-2.5 text-center text-sm font-bold text-slate-500">${s.totalDays}</td>
            <td class="px-3 py-2.5 text-center text-sm font-bold text-emerald-600">${s.present}</td>
            <td class="px-3 py-2.5 text-center text-sm font-bold text-rose-500">${s.absent}</td>
            ${[s.nailCorrect, s.hairCorrect, s.shirtCorrect, s.pantsCorrect, s.socksCorrect, s.shoesCorrect].map(v => `<td class="px-3 py-2.5 text-center text-xs text-slate-500">${v}</td>`).join('')}
            <td class="px-3 py-2.5 text-xs text-slate-400">${esc(s.notes || '')}</td>
        </tr>
    `).join('');

    // CSV link
    document.getElementById('ov-export-btn').href = BASE + `/assembly/api/export_csv.php?classroom=${encodeURIComponent(classroom)}&month=${month}`;
}

// ─── INDIVIDUAL ───
async function loadIndividual() {
    const id    = document.getElementById('ind-student-id').value.trim();
    const month = document.getElementById('ind-month').value || 'all';
    if (!id) { Swal.fire('กรุณากรอกรหัสนักเรียน','','warning'); return; }

    Swal.fire({ title: 'กำลังโหลด...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
    const res = await api(`/assembly/api/get_student_report.php?student_id=${encodeURIComponent(id)}&month=${month}`);
    Swal.close();
    if (res.status !== 'success') { Swal.fire('ผิดพลาด', res.message || 'ไม่พบข้อมูล', 'error'); return; }

    document.getElementById('ind-content').classList.remove('hidden');
    document.getElementById('ind-info-id').textContent      = res.info.id;
    document.getElementById('ind-info-name').textContent    = res.info.name;
    document.getElementById('ind-info-class').textContent   = res.info.class;
    document.getElementById('ind-info-teacher').textContent = res.info.teacher || '-';
    document.getElementById('ind-present').textContent      = res.summary.present;
    document.getElementById('ind-absent').textContent       = res.summary.absent;
    document.getElementById('ind-leave').textContent        = res.summary.leave;
    document.getElementById('ind-uniform').textContent      = res.summary.uniformScore + '%';

    const statusBadge = st => ({
        'ม': '<span class="px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700 text-xs font-bold">ม</span>',
        'ข': '<span class="px-2 py-0.5 rounded-full bg-rose-50 text-rose-700 text-xs font-bold">ข</span>',
        'ล': '<span class="px-2 py-0.5 rounded-full bg-amber-50 text-amber-700 text-xs font-bold">ล</span>',
        'ด': '<span class="px-2 py-0.5 rounded-full bg-purple-50 text-purple-700 text-xs font-bold">ด</span>',
    }[st] || st);

    const icon = ok => ok === 'ถูก' ? '<span class="text-emerald-500 font-bold">✓</span>' : '<span class="text-rose-400">✗</span>';

    document.getElementById('ind-history').innerHTML = res.history.map(r => `
        <tr class="border-b border-slate-50 hover:bg-indigo-50/30 transition-colors">
            <td class="px-3 py-2.5 text-xs text-slate-500">${r.date}</td>
            <td class="px-3 py-2.5 text-center">${statusBadge(r.status)}</td>
            ${['nail','hair','shirt','pants','socks','shoes'].map(f => `<td class="px-3 py-2.5 text-center">${icon(r[f])}</td>`).join('')}
            <td class="px-3 py-2.5 text-xs text-slate-400">${esc(r.note || '')}</td>
        </tr>
    `).join('') || `<tr><td colspan="9" class="px-4 py-6 text-center text-slate-400">ไม่มีประวัติ</td></tr>`;
}

// ─── Printing ───
function printDailyReport() {
    const classroom = document.getElementById('att-classroom').value;
    const date      = document.getElementById('att-date').value;
    if (!classroom || !date) { Swal.fire('กรุณาเลือกห้องเรียนและวันที่','','warning'); return; }
    window.open(window.BASE + `/assembly/report_print.php?classroom=${encodeURIComponent(classroom)}&date=${date}`, '_blank');
}

function printDailyReportFromOverview() {
    const classroom = document.getElementById('ov-classroom').value;
    // For overview, we might want to default to today or the last active date if we have it.
    // However, usually we print for a specific day. Let's use today as default if no date selected elsewhere.
    const date = document.getElementById('att-date').value || new Date().toISOString().split('T')[0];
    if (!classroom) { Swal.fire('กรุณาเลือกห้องเรียน','','warning'); return; }
    window.open(window.BASE + `/assembly/report_print.php?classroom=${encodeURIComponent(classroom)}&date=${date}`, '_blank');
}

// ─── Escape HTML ───
function esc(str) {
    const d = document.createElement('div');
    d.textContent = String(str);
    return d.innerHTML;
}
</script>

<?php
require_once __DIR__ . '/../components/layout_end.php';

// ─── Helper: month options ───
function renderMonthOptions(bool $includeAll = false): string {
    $months = ['01'=>'มกราคม','02'=>'กุมภาพันธ์','03'=>'มีนาคม','04'=>'เมษายน',
               '05'=>'พฤษภาคม','06'=>'มิถุนายน','07'=>'กรกฎาคม','08'=>'สิงหาคม',
               '09'=>'กันยายน','10'=>'ตุลาคม','11'=>'พฤศจิกายน','12'=>'ธันวาคม'];
    $html = $includeAll ? '<option value="all">ทุกเดือน</option>' : '<option value="">เลือกเดือน</option>';
    $current = date('m');
    foreach ($months as $k => $v) {
        $sel = $k === $current ? 'selected' : '';
        $html .= "<option value=\"$k\" $sel>$v</option>";
    }
    return $html;
}
?>
