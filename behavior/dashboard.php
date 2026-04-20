<?php
/**
 * behavior/dashboard.php — ระบบบันทึกพฤติกรรมนักเรียน (Teacher Dashboard)
 * Tabs: บันทึกพฤติกรรม | ประวัติรายบุคคล | ฐานข้อมูลรวม
 */
session_start();
require_once __DIR__ . '/../config.php';

if (!isset($_SESSION['llw_role'])) {
    header('Location: ' . $base_path . '/login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit();
}


$pageTitle    = 'บันทึกพฤติกรรม';
$pageSubtitle = 'ระบบบันทึกพฤติกรรมนักเรียน';
$activeSystem = 'behavior';

require_once __DIR__ . '/../components/layout_start.php';
?>

<!-- ─── Tab Navigation ─── -->
<div class="mb-6">
    <div class="bg-white rounded-2xl shadow-xl shadow-violet-100/50 p-1.5 inline-flex flex-wrap gap-1 border border-violet-100/50">
        <button id="tab-btn-input" onclick="showTab('input')"
            class="tab-btn tab-active px-5 py-2.5 rounded-xl text-sm font-bold transition-all flex items-center gap-2">
            <i class="bi bi-pencil-square"></i> บันทึกพฤติกรรม
        </button>
        <button id="tab-btn-history" onclick="showTab('history')"
            class="tab-btn tab-inactive px-5 py-2.5 rounded-xl text-sm font-bold transition-all flex items-center gap-2">
            <i class="bi bi-clock-history"></i> ประวัติรายบุคคล
        </button>
        <button id="tab-btn-database" onclick="showTab('database')"
            class="tab-btn tab-inactive px-5 py-2.5 rounded-xl text-sm font-bold transition-all flex items-center gap-2">
            <i class="bi bi-database-fill"></i> ฐานข้อมูลรวม
        </button>
        <button id="tab-btn-approval" onclick="showTab('approval')"
            class="tab-btn tab-inactive px-5 py-2.5 rounded-xl text-sm font-bold transition-all flex items-center gap-2 relative border border-transparent">
            <i class="bi bi-patch-check-fill"></i> อนุมัติความดี
            <span id="pendingBadge" class="hidden absolute -top-1 -right-1 flex h-4 w-4">
                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-rose-400 opacity-75"></span>
                <span class="relative inline-flex rounded-full h-4 w-4 bg-rose-500 text-[8px] text-white items-center justify-center font-black">!</span>
            </span>
        </button>
        <button id="tab-btn-leaderboard" onclick="showTab('leaderboard')"
            class="tab-btn tab-inactive px-5 py-2.5 rounded-xl text-sm font-bold transition-all flex items-center gap-2">
            <i class="bi bi-trophy-fill text-amber-500"></i> Hall of Fame
        </button>
        <button id="tab-btn-analytics" onclick="showTab('analytics')"
            class="tab-btn tab-inactive px-5 py-2.5 rounded-xl text-sm font-bold transition-all flex items-center gap-2">
            <i class="bi bi-bar-chart-line-fill text-indigo-500"></i> Analytics
        </button>
    </div>
</div>

<!-- ═══════ TAB: INPUT (บันทึกพฤติกรรม) ═══════ -->
<div id="tab-input" class="tab-content">
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">

        <!-- LEFT: Search + Student Card -->
        <div class="lg:col-span-4 space-y-4">
            <!-- Pending Task Alert (New) -->
            <div id="pendingTaskAlert" class="bg-gradient-to-br from-indigo-600 to-violet-700 rounded-2xl p-5 text-white shadow-xl shadow-indigo-200/50 hidden cursor-pointer transition-all hover:scale-[1.02]" onclick="showTab('approval')">
                <div class="flex justify-between items-start">
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-widest opacity-80">มีรายการรอยืนยัน</p>
                        <p class="text-3xl font-black mt-1" id="pendingCountText">0</p>
                    </div>
                    <div class="w-10 h-10 bg-white/20 rounded-xl flex items-center justify-center text-xl">
                        <i class="bi bi-patch-check"></i>
                    </div>
                </div>
                <p class="text-[10px] mt-3 font-bold opacity-80 border-t border-white/20 pt-2 flex items-center gap-2">
                    <i class="bi bi-arrow-right-circle"></i> คลิกเพื่อไปตรวจสอบโครงการ
                </p>
            </div>

            <!-- Search -->
            <div class="bg-white rounded-2xl shadow-xl shadow-violet-100/50 p-5 border border-violet-100/30">
                <label class="text-[10px] font-black text-slate-400 uppercase tracking-wider mb-2 block">
                    <i class="bi bi-search me-1"></i> ค้นหานักเรียน
                </label>
                <div class="flex gap-2 mb-3">
                    <input type="text" id="teacherStudentIdInput"
                        class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-4 py-3 text-sm focus:ring-2 focus:ring-violet-500 outline-none transition-all"
                        placeholder="รหัสนักเรียน...">
                    <button id="btnLoadStudent"
                        class="bg-gradient-to-r from-violet-600 to-indigo-600 text-white px-4 rounded-2xl font-bold shadow-lg shadow-violet-200/50 hover:scale-[1.02] transition-all flex-shrink-0">
                        <i class="bi bi-arrow-right"></i>
                    </button>
                </div>
                <button onclick="openStudentModal()"
                    class="w-full text-sm text-slate-500 bg-slate-50 hover:bg-slate-100 border border-dashed border-slate-300 rounded-xl py-2 transition-all">
                    <i class="bi bi-list-ul me-1"></i> เลือกจากรายชื่อทั้งหมด
                </button>

                <div id="teacherSpinner" class="text-center py-6 hidden">
                    <div class="animate-spin w-8 h-8 border-4 border-violet-200 border-t-violet-600 rounded-full mx-auto mb-2"></div>
                    <div class="text-xs text-slate-400">กำลังโหลด...</div>
                </div>
            </div>

            <!-- Student Profile Card -->
            <div id="teacherStudentBox" class="bg-white rounded-2xl shadow-xl shadow-violet-100/50 p-6 border border-violet-100/30 hidden">
                <div class="h-20 bg-gradient-to-r from-cyan-500 to-blue-600 rounded-xl -mx-6 -mt-6 mb-0 relative">
                    <div class="absolute -bottom-10 left-1/2 -translate-x-1/2 w-20 h-20 rounded-full border-4 border-white bg-white shadow-lg overflow-hidden">
                        <img id="sbpStudentPhoto" src="" alt="Student" class="w-full h-full object-cover">
                    </div>
                </div>

                <div class="text-center mt-12">
                    <h4 class="text-lg font-black text-slate-800" id="sbpStudentName">-</h4>
                    <p class="text-xs text-slate-400 mt-1" id="sbpStudentMeta">-</p>
                    <span class="inline-block mt-2 px-3 py-1 bg-violet-50 text-violet-600 rounded-full text-[10px] font-bold border border-violet-100">
                        <i class="bi bi-person-badge me-1"></i><span id="sbpAdvisorName">-</span>
                    </span>

                    <div class="grid grid-cols-3 gap-2 mt-4">
                        <div class="bg-emerald-50 rounded-xl p-3 border border-emerald-100">
                            <div class="text-2xl font-black text-emerald-600" id="sbpScoreGood">0</div>
                            <div class="text-[9px] font-bold text-emerald-400 uppercase">ความดี</div>
                        </div>
                        <div class="bg-rose-50 rounded-xl p-3 border border-rose-100">
                            <div class="text-2xl font-black text-rose-600" id="sbpScoreBad">0</div>
                            <div class="text-[9px] font-bold text-rose-400 uppercase">ความผิด</div>
                        </div>
                        <div class="bg-sky-50 rounded-xl p-3 border border-sky-100">
                            <div class="text-2xl font-black text-sky-600" id="sbpScoreNet">100</div>
                            <div class="text-[9px] font-bold text-sky-400 uppercase">สุทธิ</div>
                        </div>
                    </div>

                    <div class="mt-5 no-print">
                        <a id="sbpCertBtn" href="#" target="_blank" class="block w-full py-2 bg-gradient-to-r from-amber-500 to-orange-500 text-white rounded-xl text-[10px] font-black uppercase tracking-widest shadow-lg shadow-amber-200 hover:scale-[1.02] transition-all">
                            <i class="bi bi-patch-check-fill me-1"></i> Print Certificate
                        </a>
                    </div>
                </div>

                        <div id="sectionAssemblySync" class="hidden mt-6 animate-fade-in text-left">
                            <div class="bg-gradient-to-br from-indigo-50 to-blue-50 rounded-2xl p-4 border border-indigo-100/50">
                                <h6 class="text-[10px] font-black text-indigo-600 uppercase tracking-widest mb-3 flex items-center gap-2">
                                    <i class="bi bi-shield-check"></i> การเข้าแถว & ระเบียบวินัย
                                </h6>
                                <div id="assemblyHistoryList" class="space-y-2 max-h-[150px] overflow-y-auto pr-2 custom-scrollbar text-[9px]">
                                    <!-- Assembly items -->
                                </div>
                            </div>
                        </div>

                        <!-- NEW: Subject Attendance Panel -->
                        <div id="sectionAttendanceSync" class="hidden mt-4 animate-fade-in text-left">
                            <div class="bg-gradient-to-br from-emerald-50 to-teal-50 rounded-2xl p-4 border border-emerald-100/50">
                                <h6 class="text-[10px] font-black text-emerald-600 uppercase tracking-widest mb-3 flex items-center gap-2">
                                    <i class="bi bi-calendar-check-fill"></i> การเข้าเรียนรายวิชา (คาบ 1-8)
                                </h6>
                                <div id="attendanceHistoryList" class="space-y-3 max-h-[250px] overflow-y-auto pr-2 custom-scrollbar text-[9px]">
                                    <!-- Attendance items -->
                                </div>
                            </div>
                    </div>
                </div>
            </div>

        <!-- RIGHT: Behavior Form -->
        <div class="lg:col-span-8">
            <div class="bg-white rounded-2xl shadow-xl shadow-violet-100/50 p-6 border border-violet-100/30 h-full">
                <h5 class="text-lg font-black text-slate-800 mb-5 pb-3 border-b border-slate-100 flex items-center gap-2">
                    <div class="w-8 h-8 bg-gradient-to-br from-violet-500 to-indigo-600 rounded-lg flex items-center justify-center">
                        <i class="bi bi-file-earmark-plus-fill text-white"></i>
                    </div>
                    แบบฟอร์มบันทึก
                </h5>
                <form id="behaviorForm">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="text-[10px] font-black text-slate-400 uppercase tracking-wider mb-1.5 block">วันที่</label>
                            <input type="date" id="behDate" required
                                class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-4 py-3 text-sm focus:ring-2 focus:ring-violet-500 outline-none transition-all">
                        </div>
                        <div>
                            <label class="text-[10px] font-black text-slate-400 uppercase tracking-wider mb-1.5 block">ผู้บันทึก</label>
                            <input type="text" id="behTeacher" readonly
                                class="w-full bg-slate-100 border border-slate-200 rounded-2xl px-4 py-3 text-sm text-slate-500">
                        </div>
                        <div>
                            <label class="text-[10px] font-black text-slate-400 uppercase tracking-wider mb-1.5 block">ประเภท</label>
                            <select id="behType" required
                                class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-4 py-3 text-sm focus:ring-2 focus:ring-violet-500 outline-none transition-all">
                                <option value="">-- เลือกประเภท --</option>
                                <option value="ความดี">💚 ความดี (+)</option>
                                <option value="ความผิด">🛑 ความผิด (-)</option>
                            </select>
                        </div>
                        <div>
                            <label class="text-[10px] font-black text-slate-400 uppercase tracking-wider mb-1.5 block">แม่แบบ (Quick Select)</label>
                            <select id="behTemplateSelect"
                                class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-4 py-3 text-sm focus:ring-2 focus:ring-violet-500 outline-none transition-all">
                                <option value="">(กำหนดเอง)</option>
                            </select>
                        </div>
                        <div class="md:col-span-2">
                            <label class="text-[10px] font-black text-slate-400 uppercase tracking-wider mb-1.5 block">รายละเอียด / กิจกรรม</label>
                            <textarea id="behActivity" rows="3" required placeholder="ระบุสิ่งที่นักเรียนทำ..."
                                class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-4 py-3 text-sm focus:ring-2 focus:ring-violet-500 outline-none transition-all resize-none"></textarea>
                        </div>
                        <div>
                            <label class="text-[10px] font-black text-slate-400 uppercase tracking-wider mb-1.5 block">คะแนน</label>
                            <input type="number" id="behScore" min="1" placeholder="0" required
                                class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-4 py-3 text-sm focus:ring-2 focus:ring-violet-500 outline-none transition-all">
                        </div>
                        <div>
                            <label class="text-[10px] font-black text-slate-400 uppercase tracking-wider mb-1.5 block">รูปภาพ (หลักฐาน)</label>
                            <input type="file" id="behFile" accept="image/*"
                                class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-4 py-2.5 text-sm file:mr-3 file:bg-violet-50 file:text-violet-600 file:border-0 file:rounded-lg file:px-3 file:py-1 file:font-bold file:text-xs">
                        </div>
                        <div class="md:col-span-2 mt-2">
                            <button type="submit"
                                class="w-full bg-gradient-to-r from-violet-600 to-indigo-600 text-white py-3.5 rounded-2xl font-black shadow-lg shadow-violet-200/50 hover:shadow-violet-300/50 hover:scale-[1.01] transition-all flex items-center justify-center gap-2 text-sm">
                                <i class="bi bi-send-fill"></i> บันทึกข้อมูลเข้าระบบ
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- ═══════ TAB: HISTORY (ประวัติรายบุคคล) ═══════ -->
<div id="tab-history" class="tab-content hidden">
    <div class="bg-white rounded-2xl shadow-xl shadow-violet-100/50 p-6 border border-violet-100/30">
        <div class="flex flex-wrap justify-between items-center gap-3 mb-5">
            <h5 class="text-lg font-black text-slate-800 flex items-center gap-2">
                <div class="w-8 h-8 bg-gradient-to-br from-amber-500 to-orange-500 rounded-lg flex items-center justify-center">
                    <i class="bi bi-clock-history text-white"></i>
                </div>
                ประวัติพฤติกรรม
            </h5>
        </div>

        <div class="bg-violet-50 border border-violet-200/50 rounded-xl px-4 py-3 flex items-center gap-2 text-violet-600 text-sm mb-5">
            <i class="bi bi-info-circle-fill"></i>
            กำลังแสดงข้อมูลของ: <strong id="historyStudentName">-- กรุณาเลือกนักเรียนจากแท็บ "บันทึกพฤติกรรม" --</strong>
        </div>

        <div id="activityListTeacher">
            <div class="text-center py-12 text-slate-400 opacity-50">
                <i class="bi bi-inbox text-5xl block mb-3"></i>
                <p>ยังไม่มีข้อมูล</p>
            </div>
        </div>
    </div>
</div>

<!-- ═══════ TAB: DATABASE (ฐานข้อมูลรวม) ═══════ -->
<div id="tab-database" class="tab-content hidden">
    <div class="bg-white rounded-2xl shadow-xl shadow-violet-100/50 p-6 border border-violet-100/30">
        <div class="flex flex-wrap justify-between items-center gap-3 mb-5">
            <h5 class="text-lg font-black text-slate-800 flex items-center gap-2">
                <div class="w-8 h-8 bg-gradient-to-br from-emerald-500 to-teal-500 rounded-lg flex items-center justify-center">
                    <i class="bi bi-database-fill text-white"></i>
                </div>
                ฐานข้อมูลรวม
            </h5>
                <div class="flex items-center gap-2">
                    <button onclick="syncAssemblyData()" 
                        class="bg-indigo-50 text-indigo-600 px-4 py-2 rounded-xl font-bold text-xs hover:bg-indigo-100 transition-all flex items-center gap-2 border border-indigo-200">
                        <i class="bi bi-arrow-repeat"></i> ดึงข้อมูลจากระบบเข้าแถว
                    </button>
                    <button onclick="exportTableToCSV()" class="bg-emerald-50 text-emerald-600 px-4 py-2 rounded-xl font-bold text-xs hover:bg-emerald-100 transition-all flex items-center gap-2 border border-emerald-200">
                        <i class="bi bi-file-earmark-excel"></i> Export CSV
                    </button>
                </div>
            </div>

        <div class="grid grid-cols-1 md:grid-cols-12 gap-3 mb-4 bg-slate-50 rounded-xl p-3 border border-slate-100">
            <div class="md:col-span-4">
                <input type="text" id="dbSearch" placeholder="🔍 ค้นหาชื่อ, กิจกรรม..."
                    class="w-full bg-white border border-slate-200 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-violet-500 outline-none transition-all">
            </div>
            <div class="md:col-span-2">
                <select id="dbFilterClass"
                    class="w-full bg-white border border-slate-200 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-violet-500 outline-none transition-all">
                    <option value="">ทุกห้อง</option>
                </select>
            </div>
            <div class="md:col-span-3">
                <select id="dbFilterType"
                    class="w-full bg-white border border-slate-200 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-violet-500 outline-none transition-all">
                    <option value="">ทุกประเภท</option>
                    <option value="ความดี">ความดี</option>
                    <option value="ความผิด">ความผิด</option>
                </select>
            </div>
            <div class="md:col-span-3 flex justify-end gap-2">
                <button onclick="loadDatabaseRecords()"
                    class="bg-violet-600 text-white px-4 py-2.5 rounded-xl font-bold text-sm shadow-lg shadow-violet-200/50 hover:bg-violet-700 transition-all flex items-center gap-2">
                    <i class="bi bi-arrow-clockwise"></i> โหลดข้อมูล
                </button>
            </div>
        </div>

        <div class="space-y-6">
            <!-- Table 1: Records Log -->
            <div>
                <h6 class="text-xs font-black text-slate-400 uppercase tracking-widest mb-3 flex items-center gap-2">
                    <i class="bi bi-clock-history"></i> รายการพฤติกรรมล่าสุด
                </h6>
                <div class="rounded-xl border border-slate-100 overflow-hidden bg-white">
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-slate-50">
                                <tr>
                                    <th class="px-4 py-3 text-[10px] font-black text-slate-400 uppercase tracking-wider text-left border-b border-slate-100">วันที่</th>
                                    <th class="px-4 py-3 text-[10px] font-black text-slate-400 uppercase tracking-wider text-left border-b border-slate-100">ชื่อ-สกุล</th>
                                    <th class="px-4 py-3 text-[10px] font-black text-slate-400 uppercase tracking-wider border-b border-slate-100">ชั้น</th>
                                    <th class="px-4 py-3 text-[10px] font-black text-slate-400 uppercase tracking-wider border-b border-slate-100">ประเภท</th>
                                    <th class="px-4 py-3 text-[10px] font-black text-slate-400 uppercase tracking-wider text-left border-b border-slate-100">กิจกรรม</th>
                                    <th class="px-4 py-3 text-[10px] font-black text-slate-400 uppercase tracking-wider border-b border-slate-100">คะแนน</th>
                                    <th class="px-4 py-3 text-[10px] font-black text-slate-400 uppercase tracking-wider text-left border-b border-slate-100">ผู้บันทึก</th>
                                </tr>
                            </thead>
                            <tbody id="databaseBody">
                                <tr><td colspan="7" class="px-4 py-12 text-center text-slate-400">กดปุ่ม "โหลดข้อมูล"</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Table 2: Score Summary (Active Monitoring) -->
            <div id="sectionClassroomSummary" class="hidden">
                <h6 class="text-xs font-black text-slate-400 uppercase tracking-widest mb-3 flex items-center gap-2">
                    <i class="bi bi-person-check-fill"></i> สรุปคะแนนสะสมรายห้อง (Active Monitoring)
                </h6>
                <div class="rounded-xl border border-slate-100 overflow-hidden bg-white shadow-sm">
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-slate-50">
                                <tr>
                                    <th class="px-4 py-3 text-[10px] font-black text-slate-400 uppercase tracking-wider text-left border-b border-slate-100">ชื่อ-สกุล</th>
                                    <th class="px-4 py-3 text-[10px] font-black text-emerald-500 uppercase tracking-wider border-b border-slate-100 text-center">ความดี (+)</th>
                                    <th class="px-4 py-3 text-[10px] font-black text-rose-500 uppercase tracking-wider border-b border-slate-100 text-center">ความผิด (-)</th>
                                    <th class="px-4 py-3 text-[10px] font-black text-slate-400 uppercase tracking-wider border-b border-slate-100 text-center">คะแนนสุทธิ</th>
                                    <th class="px-4 py-3 text-[10px] font-black text-slate-400 uppercase tracking-wider border-b border-slate-100 text-center">สถานะความเสี่ยง</th>
                                </tr>
                            </thead>
                            <tbody id="classroomSummaryBody">
                                <tr><td colspan="5" class="px-4 py-12 text-center text-slate-400 italic">กรุณาเลือกห้องเรียนเพื่อดูสรุป</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ═══════ TAB: APPROVAL (อนุมัติความดี) ═══════ -->
<div id="tab-approval" class="tab-content hidden">
    <div class="bg-white rounded-2xl shadow-xl shadow-violet-100/50 p-6 border border-violet-100/30">
        <div class="flex justify-between items-center mb-6">
            <h5 class="text-lg font-black text-slate-800 flex items-center gap-2">
                <div class="w-8 h-8 bg-gradient-to-br from-violet-600 to-indigo-600 rounded-lg flex items-center justify-center">
                    <i class="bi bi-patch-check-fill text-white"></i>
                </div>
                รายการรอยืนยัน
            </h5>
            <button onclick="loadPendingDeeds()" class="text-xs font-bold text-violet-600 bg-violet-50 px-4 py-2 rounded-xl border border-violet-100 transition-all hover:bg-violet-600 hover:text-white">
                <i class="bi bi-arrow-clockwise"></i> รีเฟรช
            </button>
        </div>

        <div id="pendingDeedsList" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <!-- Pending items -->
            <div class="col-span-full py-20 text-center text-slate-400 opacity-50">
                <i class="bi bi-inbox text-5xl block mb-3"></i>
                <p>ไม่มีรายการรอยืนยันในขณะนี้</p>
            </div>
        </div>
    </div>

    <!-- Configuration for Advisors (Visible to Super Admin or relevant teachers) -->
    <?php if (in_array($_SESSION['llw_role'], ['super_admin', 'att_teacher'])): ?>
    <div class="mt-6 bg-white rounded-2xl shadow-xl shadow-violet-100/50 p-6 border border-violet-100/30">
        <div class="flex justify-between items-center mb-4">
            <h6 class="text-sm font-black text-slate-800">จัดการสิทธิ์ครูที่ปรึกษา</h6>
            <button onclick="openAdvisorMappingModal()" class="text-[10px] font-black bg-slate-800 text-white px-3 py-1.5 rounded-lg hover:bg-slate-700 transition-all">
                <i class="bi bi-gear-fill me-1"></i> ตั้งค่าห้องเรียนที่คุณดูแล
            </button>
        </div>
        <p class="text-xs text-slate-400">ระบุห้องเรียนที่คุณรับผิดชอบเพื่อรับการแจ้งเตือนและอนุมัติความดีให้แก่นักเรียนในห้องครับ</p>
    </div>
    <?php endif; ?>
</div>

<!-- ═══════ TAB: LEADERBOARD (Hall of Fame) ═══════ -->
<div id="tab-leaderboard" class="tab-content hidden">
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-10 items-start">
        <!-- Decoration / Trophy -->
        <div class="lg:col-span-4 flex flex-col items-center text-center py-10 bg-gradient-to-br from-amber-400 to-orange-500 rounded-[48px] text-white shadow-2xl shadow-amber-200/50">
            <i class="bi bi-trophy-fill text-8xl mb-6 drop-shadow-xl animate-bounce"></i>
            <h4 class="text-3xl font-black uppercase tracking-tighter">Hall of Fame</h4>
            <p class="text-xs font-bold opacity-80 uppercase tracking-widest mt-2">นักเรียนแบบอย่าง ประจำปีการศึกษา</p>
            
            <div class="mt-10 w-full px-8 space-y-4">
                <div class="bg-white/20 backdrop-blur-md rounded-2xl p-6 border border-white/30">
                    <p class="text-[10px] font-black uppercase tracking-widest opacity-70 mb-1">Top Performer</p>
                    <h5 id="leaderboardTopName" class="text-xl font-black">-</h5>
                </div>
                <button onclick="loadLeaderboard()" class="w-full bg-white text-orange-600 py-3 rounded-2xl font-black text-xs uppercase tracking-widest shadow-xl shadow-orange-700/20 hover:scale-[1.02] transition-all">
                    <i class="bi bi-arrow-clockwise"></i> Refresh Standings
                </button>
            </div>
        </div>

        <!-- Leaderboard List -->
        <div class="lg:col-span-8 bg-white rounded-[48px] p-10 shadow-sm border border-slate-100 flex flex-col gap-10">
            <div class="flex items-center justify-between">
                <h5 class="text-lg font-black text-slate-800 flex items-center gap-3">
                    <i class="bi bi-stars text-amber-500"></i> อันดับนักเรียนคะแนนความดีสูงสุด
                </h5>
                <span class="text-[9px] font-black text-slate-400 uppercase tracking-widest bg-slate-50 px-3 py-1 rounded-full border border-slate-100">Updated Real-time</span>
            </div>

            <div id="leaderboardContainer" class="space-y-4">
                <!-- Items -->
            </div>
        </div>
    </div>
</div>

<!-- ═══════ TAB: ANALYTICS (Analytics) ═══════ -->
<div id="tab-analytics" class="tab-content hidden">
    <div class="flex flex-col gap-10">
        <!-- Top Stats Row -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-10">
            <div class="bg-white rounded-[48px] p-10 shadow-sm border border-slate-100 flex flex-col gap-10">
                <div>
                    <h5 class="text-lg font-black text-slate-800 flex items-center gap-3">
                        <i class="bi bi-bar-chart-fill text-indigo-600"></i> แผนภูมิคะแนนเฉลี่ยรายห้อง
                    </h5>
                    <p class="text-[10px] text-slate-400 font-bold uppercase mt-1">Average Behavior Score per Class</p>
                </div>
                <div class="h-[400px]">
                    <canvas id="roomStatsChart"></canvas>
                </div>
            </div>

            <div class="bg-white rounded-[48px] p-10 shadow-sm border border-slate-100 flex flex-col gap-10">
                <div>
                    <h5 class="text-lg font-black text-slate-800 flex items-center gap-3">
                        <i class="bi bi-pie-chart-fill text-rose-500"></i> สัดส่วนพฤติกรรมในระบบ
                    </h5>
                    <p class="text-[10px] text-slate-400 font-bold uppercase mt-1">Good Deeds vs Violations Distribution</p>
                </div>
                <div class="h-[400px] flex items-center justify-center">
                    <canvas id="typeRatioChart"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ═══════ STUDENT MODAL (เลือกนักเรียน) ═══════ -->
<div id="studentModal" class="fixed inset-0 z-50 hidden">
    <div class="absolute inset-0 bg-slate-900/40 backdrop-blur-sm" onclick="closeStudentModal()"></div>
    <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-full max-w-lg max-h-[85vh] bg-white rounded-3xl shadow-2xl flex flex-col overflow-hidden">
        <div class="p-5 border-b border-slate-100 flex items-center justify-between flex-shrink-0">
            <h5 class="text-lg font-black text-slate-800">รายชื่อนักเรียน</h5>
            <button onclick="closeStudentModal()" class="w-8 h-8 rounded-lg hover:bg-slate-100 flex items-center justify-center text-slate-400 transition-all">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
        <div class="p-4 border-b border-slate-50 flex-shrink-0">
            <div class="grid grid-cols-12 gap-2">
                <div class="col-span-8">
                    <input type="text" id="studentSearchInput" placeholder="พิมพ์ชื่อหรือรหัสนักเรียน..."
                        class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-violet-500 outline-none transition-all">
                </div>
                <div class="col-span-4">
                    <select id="studentLevelFilter"
                        class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-violet-500 outline-none transition-all">
                        <option value="">ทุกห้อง</option>
                    </select>
                </div>
            </div>
        </div>
        <div class="flex-1 overflow-y-auto p-4" id="studentListContainer">
            <div class="text-center py-8 text-slate-400">กำลังโหลด...</div>
        </div>
        <div class="p-3 border-t border-slate-100 flex items-center justify-between text-xs text-slate-400 flex-shrink-0">
            <span id="studentPageInfo">Page 1</span>
            <div class="flex gap-1">
                <button id="studentPrevPage" class="px-3 py-1.5 bg-slate-100 rounded-lg hover:bg-slate-200 transition-all"><i class="bi bi-chevron-left"></i></button>
                <button id="studentNextPage" class="px-3 py-1.5 bg-slate-100 rounded-lg hover:bg-slate-200 transition-all"><i class="bi bi-chevron-right"></i></button>
            </div>
        </div>
    </div>
</div>

<style>
.tab-active   { background: linear-gradient(135deg, #7c3aed, #6366f1); color: white; box-shadow: 0 4px 15px rgba(124,58,237,0.3); }
.tab-inactive { background: transparent; color: #64748b; }
.tab-inactive:hover { background: #f8fafc; color: #1e293b; }
</style>

<script>
// ─── Config ───
window.BASE = '<?= rtrim(str_replace("/behavior", "", dirname($_SERVER["SCRIPT_NAME"])), "/") ?>';
const BASE_BEHAVIOR_SCORE = 100;
const STUDENT_PAGE_SIZE = 20;

// ─── State ───
let currentStudentId = '';
let currentStudentName = '';
let currentAdvisorName = '';
let cachedBehaviorTemplates = { goods: [], bads: [] };
let cachedStudentList = [];
let cachedAllRecordForDB = [];
let studentFilteredList = [];
let studentCurrentPage = 1;
let studentSearchText = '';
let studentLevelFilter = '';
let studentListLoaded = false;
let studentListLoading = false;

// ─── Utility ───
const esc = s => { const d = document.createElement('div'); d.textContent = String(s); return d.innerHTML; };
const toNum = (v, fb = 0) => { const n = Number(String(v ?? '').replace(/,/g, '').trim()); return Number.isFinite(n) ? n : fb; };
const clamp = (n, min, max) => Math.min(max, Math.max(min, toNum(n, 0)));
const normId = sid => { sid = String(sid || '').trim(); if (/^\d+$/.test(sid)) sid = sid.padStart(5, '0'); return sid; };

// ─── API helpers ───
async function api(url) {
    try {
        const r = await fetch(window.BASE + url);
        if (!r.ok) throw new Error('HTTP ' + r.status);
        return await r.json();
    } catch (e) { console.error('API Error:', url, e); return { status: 'error', message: e.message }; }
}
async function post(url, data) {
    try {
        const r = await fetch(window.BASE + url, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data) });
        if (!r.ok) throw new Error('HTTP ' + r.status);
        return await r.json();
    } catch (e) { console.error('POST Error:', url, e); return { status: 'error', message: e.message }; }
}

// ─── Tab Switching ───
function showTab(name) {
    document.querySelectorAll('.tab-content').forEach(t => t.classList.add('hidden'));
    document.querySelectorAll('.tab-btn').forEach(b => { b.classList.remove('tab-active'); b.classList.add('tab-inactive'); });
    const c = document.getElementById('tab-' + name);
    const b = document.getElementById('tab-btn-' + name);
    if (c) c.classList.remove('hidden');
    if (b) { b.classList.remove('tab-inactive'); b.classList.add('tab-active'); }

    if (name === 'database' && cachedAllRecordForDB.length === 0) loadDatabaseRecords();
    if (name === 'leaderboard') loadLeaderboard();
    if (name === 'analytics') loadAnalytics();
}

/** Leaderboard Logic **/
async function loadLeaderboard() {
    const container = document.getElementById('leaderboardContainer');
    container.innerHTML = '<div class="py-20 text-center"><div class="animate-spin w-10 h-10 border-4 border-amber-200 border-t-amber-500 rounded-full mx-auto"></div></div>';
    
    const res = await api('/behavior/api/get_leaderboard.php');
    if (res.status === 'success' && res.data.length > 0) {
        container.innerHTML = '';
        document.getElementById('leaderboardTopName').innerText = res.data[0].name;
        
        res.data.forEach((st, idx) => {
            const rank = idx + 1;
            let rankColor = 'bg-slate-50 text-slate-400';
            let rankIcon = '';
            if (rank === 1) { rankColor = 'bg-amber-100 text-amber-600'; rankIcon = '🥇'; }
            if (rank === 2) { rankColor = 'bg-slate-200 text-slate-600'; rankIcon = '🥈'; }
            if (rank === 3) { rankColor = 'bg-orange-100 text-orange-600'; rankIcon = '🥉'; }
            
            const card = document.createElement('div');
            card.className = 'flex items-center justify-between p-5 bg-white rounded-3xl border border-slate-100 hover:shadow-lg hover:border-amber-100 transition-all group';
            card.innerHTML = `
                <div class="flex items-center gap-5">
                    <div class="w-12 h-12 rounded-2xl ${rankColor} flex items-center justify-center font-black text-lg group-hover:scale-110 transition-all">
                        ${rankIcon || rank}
                    </div>
                    <div>
                        <p class="text-sm font-black text-slate-800">${esc(st.name)}</p>
                        <p class="text-[9px] text-slate-400 font-bold uppercase tracking-widest">${esc(st.level)}/${esc(st.room)} | ID: ${esc(st.student_id)}</p>
                    </div>
                </div>
                <div class="text-right">
                    <div class="text-xl font-black text-amber-600">${st.net_score}</div>
                    <div class="text-[8px] font-black text-slate-300 uppercase tracking-widest">Net Points</div>
                </div>
            `;
            container.appendChild(card);
        });
    } else {
        container.innerHTML = '<div class="py-20 text-center text-slate-400 italic">ยังไม่มีข้อมูลลำดับ</div>';
    }
}

/** Analytics Logic **/
let roomChart = null;
let ratioChart = null;

async function loadAnalytics() {
    const res = await api('/behavior/api/get_room_stats.php');
    if (res.status === 'success') {
        renderRoomStats(res.data);
        renderRatioStats(res.data);
    }
}

function renderRoomStats(data) {
    const ctx = document.getElementById('roomStatsChart').getContext('2d');
    if (roomChart) roomChart.destroy();
    
    roomChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: data.map(i => 'ห้อง ' + i.classroom),
            datasets: [{
                label: 'คะแนนเฉลี่ย',
                data: data.map(i => parseFloat(i.avg_score).toFixed(1)),
                backgroundColor: 'rgba(99, 102, 241, 0.8)',
                borderRadius: 15,
                barThickness: 30
            }]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                x: { min: 80, max: 120, grid: { borderDash: [5, 5] } },
                y: { grid: { display: false } }
            },
            plugins: {
                legend: { display: false }
            }
        }
    });
}

function renderRatioStats(data) {
    const ctx = document.getElementById('typeRatioChart').getContext('2d');
    if (ratioChart) ratioChart.destroy();
    
    const totalGood = data.reduce((a, b) => a + parseInt(b.total_good_points), 0);
    const totalBad = data.reduce((a, b) => a + parseInt(b.total_bad_points), 0);
    
    ratioChart = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['ความดี (+)', 'ความผิด (-)'],
            datasets: [{
                data: [totalGood, totalBad],
                backgroundColor: ['#10b981', '#f43f5e'],
                borderWidth: 0,
                cutout: '80%'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom', labels: { font: { family: 'Prompt', weight: 'bold' } } }
            }
        }
    });
}

// ─── Init ───
document.addEventListener('DOMContentLoaded', () => {
    const today = new Date().toISOString().split('T')[0];
    const behDate = document.getElementById('behDate');
    if (behDate) behDate.value = today;

    // Set teacher name
    const behTeacher = document.getElementById('behTeacher');
    if (behTeacher) behTeacher.value = '<?= htmlspecialchars(($_SESSION['firstname'] ?? '') . ' ' . ($_SESSION['lastname'] ?? ''), ENT_QUOTES, 'UTF-8') ?>';

    // Search button
    document.getElementById('btnLoadStudent').onclick = () => {
        const v = document.getElementById('teacherStudentIdInput').value.trim();
        if (v) loadStudentFocus(v); else Swal.fire('กรุณาระบุรหัสนักเรียน', '', 'warning');
    };

    // Enter key on search
    document.getElementById('teacherStudentIdInput').addEventListener('keypress', e => {
        if (e.key === 'Enter') { e.preventDefault(); document.getElementById('btnLoadStudent').click(); }
    });

    // Type change → refresh templates
    document.getElementById('behType').onchange = refreshTemplates;

    // Template select → fill activity & score
    document.getElementById('behTemplateSelect').onchange = function() {
        if (this.value) {
            document.getElementById('behActivity').value = this.value;
            if (this.options[this.selectedIndex].dataset.score)
                document.getElementById('behScore').value = this.options[this.selectedIndex].dataset.score;
        }
    };

    // Form submit
    document.getElementById('behaviorForm').onsubmit = e => { e.preventDefault(); submitBehavior(); };

    // DB filters
    document.getElementById('dbSearch').oninput = filterDatabaseTable;
    document.getElementById('dbFilterType').onchange = filterDatabaseTable;
    document.getElementById('dbFilterClass').onchange = () => {
        filterDatabaseTable();
        loadClassroomSummary();
    };

    // Student modal events
    document.getElementById('studentSearchInput').oninput = e => { studentSearchText = e.target.value; studentCurrentPage = 1; applyStudentFilterAndRender(); };
    document.getElementById('studentLevelFilter').onchange = e => { studentLevelFilter = e.target.value; studentCurrentPage = 1; applyStudentFilterAndRender(); };
    document.getElementById('studentPrevPage').onclick = () => { if (studentCurrentPage > 1) { studentCurrentPage--; renderStudentModalPage(); } };
    document.getElementById('studentNextPage').onclick = () => {
        const total = Math.ceil((studentFilteredList || []).length / STUDENT_PAGE_SIZE);
        if (studentCurrentPage < total) { studentCurrentPage++; renderStudentModalPage(); }
    };

    // Load templates and student list (for filters)
    loadTemplates();
    fetchStudentList();
    loadPendingDeeds(); // Initial load for badge
    setInterval(loadPendingDeeds, 60000); // Check every minute
});

// ─── Load Student Focus ───
async function loadStudentFocus(sid) {
    sid = normId(sid);
    currentStudentId = sid;

    document.getElementById('teacherSpinner').classList.remove('hidden');
    document.getElementById('teacherStudentBox').classList.add('hidden');

    const data = await api('/behavior/api/get_student_focus.php?sid=' + encodeURIComponent(sid));

    document.getElementById('teacherSpinner').classList.add('hidden');

    if (!data || !data.st) {
        Swal.fire({ icon: 'error', title: 'ไม่พบข้อมูล', text: 'ไม่พบรหัสนักเรียนนี้' });
        return;
    }

    const st = data.st;
    currentStudentName = st.name || '';
    currentAdvisorName = st.homeroom || '';

    document.getElementById('sbpStudentName').innerText = st.name || '-';
    document.getElementById('sbpStudentMeta').innerText = `รหัส: ${sid} | ชั้น: ${st.classText || '-'}`;
    document.getElementById('sbpAdvisorName').innerText = st.homeroom || 'ไม่ระบุ';
    document.getElementById('sbpStudentPhoto').src = st.img || 'https://via.placeholder.com/100x120?text=No+Img';

    const scores = data.scores || {};
    const sGood = clamp(scores.good, 0, 999999);
    const sBad = clamp(scores.bad, 0, BASE_BEHAVIOR_SCORE);
    const sNet = clamp(BASE_BEHAVIOR_SCORE - sBad, 0, 999999);

    document.getElementById('sbpScoreGood').innerText = sGood.toLocaleString();
    document.getElementById('sbpScoreBad').innerText = sBad.toLocaleString();
    document.getElementById('sbpScoreNet').innerText = sNet.toLocaleString();

    // Update Certificate Link
    const certBtn = document.getElementById('sbpCertBtn');
    if (certBtn) certBtn.href = window.BASE + '/behavior/export_certificate.php?student_id=' + sid;

    document.getElementById('teacherStudentBox').classList.remove('hidden');

    // History
    document.getElementById('activityListTeacher').innerHTML = data.html || '<div class="text-center py-12 text-slate-400">ไม่มีประวัติ</div>';
    document.getElementById('historyStudentName').innerText = st.name || '-';

    loadAssemblySync(sid);
    loadAttendanceSync(sid);
}

async function loadAttendanceSync(sid) {
    const list = document.getElementById('attendanceHistoryList');
    const section = document.getElementById('sectionAttendanceSync');
    
    if (!list || !section) return;

    list.innerHTML = '<div class="text-center py-4"><div class="animate-spin w-4 h-4 border-2 border-emerald-200 border-t-emerald-600 rounded-full mx-auto"></div></div>';
    section.classList.remove('hidden');

    const res = await api('/behavior/api/get_attendance_records.php?student_id=' + sid);
    if (res.status === 'success' && res.data && res.data.length > 0) {
        list.innerHTML = '';
        res.data.forEach(day => {
            const dateStr = new Date(day.date).toLocaleDateString('th-TH', { day:'numeric', month:'short' });
            
            const card = document.createElement('div');
            card.className = 'bg-white/70 rounded-xl p-2 border border-emerald-100 shadow-sm';
            
            let pHtml = '';
            for(let i=1; i<=8; i++) {
                const p = day.periods[i];
                const status = p ? p.status : '-';
                let bgColor = 'bg-slate-100 text-slate-400';
                if (status === 'มา') bgColor = 'bg-emerald-500 text-white';
                if (status === 'ขาด' || status === 'โดด') bgColor = 'bg-rose-500 text-white';
                if (status === 'ลา') bgColor = 'bg-amber-500 text-white';
                if (status === 'สาย') bgColor = 'bg-orange-400 text-white';

                pHtml += `<div class="flex flex-col items-center gap-0.5" title="${p ? esc(p.subject) : 'ไม่พบข้อมูล'}">
                    <span class="text-[8px] font-black text-slate-400">คาบ ${i}</span>
                    <div class="w-5 h-5 rounded-md flex items-center justify-center font-black ${bgColor}">${status[0]}</div>
                </div>`;
            }

            card.innerHTML = `
                <div class="flex items-center justify-between mb-2">
                    <span class="font-black text-slate-700">${dateStr}</span>
                </div>
                <div class="flex justify-between gap-1">${pHtml}</div>
            `;
            list.appendChild(card);
        });
    } else {
        list.innerHTML = '<div class="text-center py-6 text-slate-400 italic">ไม่พบประวัติเข้าเรียน</div>';
    }
}

async function loadAssemblySync(sid) {
    const list = document.getElementById('assemblyHistoryList');
    const section = document.getElementById('sectionAssemblySync');
    
    if (!list || !section) return;

    list.innerHTML = '<div class="text-center py-4"><div class="animate-spin w-5 h-5 border-2 border-indigo-200 border-t-indigo-600 rounded-full mx-auto"></div></div>';
    section.classList.remove('hidden');

    const res = await api('/behavior/api/get_assembly_sync.php?student_id=' + sid);
    if (res.status === 'success' && res.data && res.data.length > 0) {
        list.innerHTML = '';
        res.data.forEach(r => {
            const dateStr = new Date(r.date).toLocaleDateString('th-TH', { day:'numeric', month:'short', year:'2-digit' });
            
            const dressVio = [];
            if (r.nail === 'ผิด') dressVio.push('เล็บ');
            if (r.hair === 'ผิด') dressVio.push('ทรงผม');
            if (r.shirt === 'ผิด') dressVio.push('เสื้อ');
            if (r.pants === 'ผิด') dressVio.push('กางเกง/กระโปรง');
            if (r.socks === 'ผิด') dressVio.push('ถุงเท้า');
            if (r.shoes === 'ผิด') dressVio.push('รองเท้า');
            
            const colorClass = r.status === 'ข' ? 'bg-rose-100/50 border-rose-200 text-rose-700' : 'bg-white border-indigo-100/30 text-slate-600';
            const statusMap = { 'ม': 'มาเข้าแถว', 'ข': 'ขาดแถว', 'ล': 'ลา', 'ด': 'โดดสาย' };
            const statusText = statusMap[r.status] || r.status;

            const item = document.createElement('div');
            item.className = `p-3 rounded-xl border shadow-sm ${colorClass} transition-all hover:scale-[1.01]`;
            item.innerHTML = `
                <div class="flex justify-between items-start">
                    <div>
                        <span class="font-bold text-slate-800">${dateStr}</span>
                        <span class="ml-2 px-2 py-0.5 rounded-full font-bold bg-white/50 text-[9px] uppercase">${statusText}</span>
                    </div>
                </div>
                ${dressVio.length > 0 ? `<div class="mt-2 font-bold text-rose-600 flex flex-wrap gap-1">
                    <i class="bi bi-exclamation-triangle-fill"></i> ผิดระเบียบ: ${dressVio.join(', ')}
                </div>` : ''}
                ${r.note ? `<div class="mt-1 opacity-70 italic text-slate-500">หมายเหตุ: ${esc(r.note)}</div>` : ''}
            `;
            list.appendChild(item);
        });
    } else {
        list.innerHTML = '<div class="text-center py-8 text-slate-400 italic">ไม่พบประวัติการเข้าแถวในช่วงนี้</div>';
    }
}

// ─── Submit Behavior ───
async function submitBehavior() {
    if (!currentStudentId) { Swal.fire('กรุณาเลือกนักเรียนก่อนบันทึก', '', 'warning'); return; }

    const type = document.getElementById('behType').value.trim();
    if (!type) { Swal.fire('กรุณาเลือกประเภท', '', 'warning'); return; }

    const scoreNum = parseInt(document.getElementById('behScore').value, 10);
    if (!Number.isFinite(scoreNum) || scoreNum <= 0) { Swal.fire('กรุณากรอกคะแนนเป็นตัวเลข > 0', '', 'warning'); return; }

    const payload = {
        date: document.getElementById('behDate').value,
        teacher: document.getElementById('behTeacher').value.trim(),
        type: type,
        activity: document.getElementById('behActivity').value.trim(),
        score: scoreNum,
        studentId: normId(currentStudentId),
        studentName: currentStudentName,
    };

    if (!payload.date || !payload.activity) { Swal.fire('กรุณากรอกวันที่และรายละเอียด', '', 'warning'); return; }

    Swal.fire({ title: 'กำลังบันทึก...', didOpen: () => Swal.showLoading(), allowOutsideClick: false });

    const fileIn = document.getElementById('behFile');
    if (fileIn && fileIn.files && fileIn.files.length > 0) {
        const f = fileIn.files[0];
        const reader = new FileReader();
        reader.onload = async (e) => {
            payload.imageBase64 = String(e.target.result || '').split(',')[1] || null;
            payload.imageName = f.name;
            payload.imageType = f.type;
            await doSave(payload);
        };
        reader.readAsDataURL(f);
    } else {
        await doSave(payload);
    }
}

async function doSave(payload) {
    const res = await post('/behavior/api/save_record.php', payload);
    if (res.status === 'success') {
        Swal.fire({ icon: 'success', title: 'บันทึกเรียบร้อย', timer: 1500, showConfirmButton: false });
        document.getElementById('behActivity').value = '';
        document.getElementById('behScore').value = '';
        document.getElementById('behFile').value = '';
        document.getElementById('behType').value = '';
        document.getElementById('behTemplateSelect').innerHTML = '<option value="">(กำหนดเอง)</option>';
        loadStudentFocus(currentStudentId);
        cachedAllRecordForDB = [];
    } else {
        Swal.fire({ icon: 'error', title: 'บันทึกไม่สำเร็จ', text: res.message || 'โปรดลองใหม่' });
    }
}

// ─── Templates ───
async function loadTemplates() {
    const res = await api('/behavior/api/get_templates.php');
    if (res && res.goods) cachedBehaviorTemplates = { goods: res.goods || [], bads: res.bads || [] };
}

function refreshTemplates() {
    const type = document.getElementById('behType').value;
    const sel = document.getElementById('behTemplateSelect');
    sel.innerHTML = '<option value="">(กำหนดเอง)</option>';
    const list = type === 'ความดี' ? cachedBehaviorTemplates.goods : type === 'ความผิด' ? cachedBehaviorTemplates.bads : [];
    (list || []).forEach(i => {
        const op = document.createElement('option');
        op.value = i.name; op.innerText = `${i.name} (${i.score} คะแนน)`; op.dataset.score = i.score;
        sel.appendChild(op);
    });
}

// ─── Delete Activity ───
async function deleteActivity(recordId) {
    const result = await Swal.fire({
        title: 'ยืนยันลบ?', text: 'ลบถาวร', icon: 'warning',
        showCancelButton: true, confirmButtonColor: '#ef4444', confirmButtonText: 'ลบ', cancelButtonText: 'ยกเลิก'
    });
    if (result.isConfirmed) {
        const res = await post('/behavior/api/delete_record.php', { id: recordId });
        if (res.status === 'success') {
            if (currentStudentId) loadStudentFocus(currentStudentId);
            cachedAllRecordForDB = [];
        }
    }
}
window.deleteActivity = deleteActivity;

// ─── Database Table ───
async function loadDatabaseRecords() {
    const body = document.getElementById('databaseBody');
    body.innerHTML = '<tr><td colspan="7" class="px-4 py-12 text-center"><div class="animate-spin w-8 h-8 border-4 border-violet-200 border-t-violet-600 rounded-full mx-auto"></div></td></tr>';
    const data = await api('/behavior/api/get_all_records.php');
    cachedAllRecordForDB = Array.isArray(data) ? data : [];
    renderDatabaseTable(cachedAllRecordForDB);
}

async function syncAssemblyData() {
    const { value: date } = await Swal.fire({
        title: 'ดึงข้อมูลจากระบบเข้าแถว',
        html: '<p class="text-sm text-slate-500 mb-3">ระบบจะดึงรายชื่อคนที่ "ขาดแถว" และ "แต่งกายผิดระเบียบ" มาบันทึกในระบบพฤติกรรมอัตโนมัติ</p>',
        input: 'date',
        inputValue: new Date().toISOString().split('T')[0],
        showCancelButton: true,
        confirmButtonText: 'เริ่มดึงข้อมูล',
        cancelButtonText: 'ยกเลิก',
        confirmButtonColor: '#4f46e5'
    });

    if (date) {
        Swal.fire({ title: 'กำลังดำเนินการ...', didOpen: () => Swal.showLoading(), allowOutsideClick: false });
        const res = await post('/behavior/api/sync_attendance_to_behavior.php', { date: date });
        if (res.status === 'success') {
            await Swal.fire({ icon: 'success', title: 'สำเร็จ', text: res.message });
            loadDatabaseRecords(); // Refresh table
        } else {
            Swal.fire({ icon: 'error', title: 'ล้มเหลว', text: res.message });
        }
    }
}

function renderDatabaseTable(data) {
    const body = document.getElementById('databaseBody');
    body.innerHTML = '';
    if (!data || !data.length) {
        body.innerHTML = '<tr><td colspan="7" class="px-4 py-12 text-center text-slate-400">ไม่พบข้อมูล</td></tr>';
        return;
    }
    data.slice(0, 500).forEach(row => {
        const isGood = row.type === 'ความดี';
        const tr = document.createElement('tr');
        tr.className = 'border-b border-slate-50 hover:bg-violet-50/30 transition-colors';
        tr.innerHTML = `
            <td class="px-4 py-3 text-xs text-slate-500">${new Date(row.date).toLocaleDateString('th-TH')}</td>
            <td class="px-4 py-3 text-sm font-bold text-slate-700">${esc(row.studentName)}</td>
            <td class="px-4 py-3 text-center"><span class="px-2 py-1 bg-slate-100 text-slate-600 rounded-lg text-[10px] font-bold">${esc(row.classInfo || '-')}</span></td>
            <td class="px-4 py-3 text-center"><span class="px-2.5 py-1 rounded-full text-[10px] font-black ${isGood ? 'bg-emerald-50 text-emerald-600' : 'bg-rose-50 text-rose-600'}">${esc(row.type)}</span></td>
            <td class="px-4 py-3 text-sm text-slate-600">${esc(row.activity)}</td>
            <td class="px-4 py-3 text-center font-black ${isGood ? 'text-emerald-600' : 'text-rose-600'}">${row.score}</td>
            <td class="px-4 py-3 text-xs text-slate-400">${esc(row.teacher || '-')}</td>
        `;
        body.appendChild(tr);
    });
}

function filterDatabaseTable() {
    const txt = document.getElementById('dbSearch').value.toLowerCase();
    const type = document.getElementById('dbFilterType').value;
    const cls = document.getElementById('dbFilterClass').value.toLowerCase();
    const filtered = cachedAllRecordForDB.filter(item => {
        const matchTxt = (item.studentName + item.activity + item.studentId).toLowerCase().includes(txt);
        const matchType = type ? item.type === type : true;
        const matchClass = !cls || (item.classInfo || '').toLowerCase() === cls;
        return matchTxt && matchType && matchClass;
    });
    renderDatabaseTable(filtered);
}

async function loadClassroomSummary() {
    const room = document.getElementById('dbFilterClass').value;
    const section = document.getElementById('sectionClassroomSummary');
    const body = document.getElementById('classroomSummaryBody');

    if (!room) {
        section.classList.add('hidden');
        return;
    }

    section.classList.remove('hidden');
    body.innerHTML = '<tr><td colspan="5" class="px-4 py-12 text-center text-slate-400"><div class="animate-spin w-6 h-6 border-2 border-violet-200 border-t-violet-600 rounded-full mx-auto"></div></td></tr>';

    const res = await api('/behavior/api/get_classroom_summary.php?room=' + encodeURIComponent(room));
    if (res.status === 'success') {
        renderClassroomSummary(res.data);
    } else {
        body.innerHTML = '<tr><td colspan="5" class="px-4 py-12 text-center text-rose-400">เกิดข้อผิดพลาด</td></tr>';
    }
}

async function loadPendingDeeds() {
    const res = await api('/behavior/api/get_pending_deeds.php');
    const container = document.getElementById('pendingDeedsList');
    const badge = document.getElementById('pendingBadge');

    if (res.status === 'success' && res.data) {
        const count = res.data.length;
        if (count > 0) {
            badge.classList.remove('hidden');
            const alertBox = document.getElementById('pendingTaskAlert');
            const countText = document.getElementById('pendingCountText');
            if (alertBox && countText) {
                alertBox.classList.remove('hidden');
                countText.innerText = count.toLocaleString();
            }
        } else {
            badge.classList.add('hidden');
            const alertBox = document.getElementById('pendingTaskAlert');
            if (alertBox) alertBox.classList.add('hidden');
        }

        if (!container) return;

        if (count === 0) {
            container.innerHTML = '<div class="col-span-full py-20 text-center text-slate-400 opacity-50"><i class="bi bi-inbox text-5xl block mb-3"></i><p>ไม่มีรายการรอยืนยันในขณะนี้</p></div>';
            return;
        }

        let html = '';
        res.data.forEach(r => {
            const dateStr = new Date(r.record_date).toLocaleDateString('th-TH', { day:'numeric', month:'short' });
            const proofImgHtml = r.image_path ? `<div class="mt-3 cursor-pointer group relative overflow-hidden rounded-xl border border-slate-100" onclick="window.open('${window.BASE}${r.image_path}', '_blank')">
                <img src="${window.BASE}${r.image_path}" class="w-full h-24 object-cover transition-transform group-hover:scale-110">
                <div class="absolute inset-0 bg-black/20 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity"><i class="bi bi-zoom-in text-white"></i></div>
            </div>` : '';
            const stuImg = r.student_img || 'https://via.placeholder.com/100x120?text=?';
            
            html += `
            <div class="bg-white rounded-3xl border border-slate-100 p-5 shadow-xl shadow-slate-200/20 transition-all hover:shadow-indigo-100/50 flex flex-col justify-between">
                <div>
                    <div class="flex gap-3 items-center mb-4">
                        <div class="w-12 h-12 rounded-2xl overflow-hidden shadow-md flex-shrink-0 border-2 border-white">
                            <img src="${stuImg}" class="w-full h-full object-cover">
                        </div>
                        <div class="flex-1">
                            <div class="font-black text-slate-800 text-sm leading-tight">${esc(r.student_name)}</div>
                            <div class="flex items-center gap-2 mt-1">
                                <span class="px-2 py-0.5 bg-indigo-50 text-indigo-600 rounded-lg text-[9px] font-black uppercase tracking-wider">ม.${esc(r.level || '')}/${esc(r.room || '')}</span>
                                <span class="text-[9px] text-slate-400 font-bold"><i class="bi bi-calendar-event me-1"></i>${dateStr}</span>
                            </div>
                        </div>
                    </div>
                    <div class="bg-slate-50/50 rounded-2xl p-3 border border-slate-100/50">
                        <p class="text-xs text-slate-600 leading-relaxed italic">"${esc(r.activity)}"</p>
                    </div>
                    ${proofImgHtml}
                </div>
                <div class="grid grid-cols-2 gap-2 mt-5">
                    <button onclick="reviewDeed(${r.id}, 'reject')" class="bg-slate-50 text-slate-400 py-2.5 rounded-xl text-xs font-black hover:bg-rose-50 hover:text-rose-600 border border-transparent hover:border-rose-100 transition-all">ปฏิเสธ</button>
                    <button onclick="reviewDeed(${r.id}, 'approve')" class="bg-emerald-50 text-emerald-600 py-2.5 rounded-xl text-xs font-black border border-emerald-100 shadow-md shadow-emerald-100/50 hover:bg-emerald-600 hover:text-white transition-all transform hover:-translate-y-0.5 active:translate-y-0">อนุมัติ (+${r.score})</button>
                </div>
            </div>`;
        });
        container.innerHTML = html;
    }
}

async function reviewDeed(recordId, action) {
    let note = '';
    if (action === 'reject') {
        const { value: text } = await Swal.fire({
            title: 'เหตุผลการปฏิเสธ',
            input: 'text',
            inputPlaceholder: 'ระบุเหตุผล (ถ้ามี)',
            showCancelButton: true,
            confirmButtonText: 'ยืนยันปฏิเสธ',
            cancelButtonText: 'ยกเลิก'
        });
        if (text === undefined) return;
        note = text;
    } else {
        const confirm = await Swal.fire({
            title: 'ยืนยันการอนุมัติ?',
            text: 'คะแนนพฤติกรรมจะถูกเพิ่มให้แก่นักเรียนทันที',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'อนุมัติ',
            cancelButtonColor: '#10b981'
        });
        if (!confirm.isConfirmed) return;
    }

    Swal.fire({ title: 'กำลังบันทึก...', didOpen: () => Swal.showLoading(), allowOutsideClick: false });

    const res = await post('/behavior/api/review_deed.php', { recordId, action, note });
    if (res.status === 'success') {
        Swal.fire({ icon: 'success', title: 'ดำเนินการแล้ว', timer: 1500, showConfirmButton: false });
        loadPendingDeeds();
        cachedAllRecordForDB = []; // Clear cache to refresh database tab
    } else {
        Swal.fire('ล้มเหลว', res.message, 'error');
    }
}

async function openAdvisorMappingModal() {
    // Basic implementation: Show current mappings and allow adding/deleting
    Swal.fire({
        title: 'ตั้งค่าห้องเรียนที่ดูแล',
        html: `<p class="text-xs text-slate-400 mb-4">ระบบกำลังดึงข้อมูล...</p>`,
        didOpen: async () => {
            Swal.showLoading();
            const res = await api('/behavior/api/manage_advisors.php?action=list');
            let listHtml = '';
            if (res.data && res.data.length > 0) {
                res.data.forEach(m => {
                    listHtml += `<div class="flex justify-between items-center bg-slate-50 p-2 rounded-lg mb-2">
                        <span class="text-sm font-bold">ม.${esc(m.level)}/${esc(m.room)}</span>
                        <button onclick="deleteAdvisorMapping(${m.id})" class="text-rose-500 hover:text-rose-700"><i class="bi bi-trash"></i></button>
                    </div>`;
                });
            } else {
                listHtml = '<div class="text-sm text-slate-400 italic py-4">ยังไม่ได้ระบุห้องเรียน</div>';
            }

            Swal.update({
                html: `
                <div class="text-left">
                    <div class="mb-4">
                        <label class="text-[10px] font-black text-slate-400 uppercase mb-1 block">เพิ่มห้องที่ดูแล</label>
                        <div class="flex gap-2">
                            <input type="text" id="map-level" placeholder="ชั้น (เช่น 4)" class="w-1/2 bg-slate-50 border border-slate-200 rounded-lg px-3 py-2 text-sm">
                            <input type="text" id="map-room" placeholder="ห้อง (เช่น 1)" class="w-1/2 bg-slate-50 border border-slate-200 rounded-lg px-3 py-2 text-sm">
                            <button onclick="addAdvisorMapping()" class="bg-slate-800 text-white px-3 rounded-lg"><i class="bi bi-plus-lg"></i></button>
                        </div>
                    </div>
                    <label class="text-[10px] font-black text-slate-400 uppercase mb-1 block">รายการห้องปัจจุบัน</label>
                    <div id="advisorMappingList" class="max-h-48 overflow-y-auto">${listHtml}</div>
                </div>`
            });
            Swal.hideLoading();
        },
        showConfirmButton: true,
        confirmButtonText: 'ปิด'
    });
}

// These functions will need to be global for Swal HTML
window.addAdvisorMapping = async () => {
    const level = document.getElementById('map-level').value.trim();
    const room = document.getElementById('map-room').value.trim();
    if (!level || !room) return;
    const res = await post('/behavior/api/manage_advisors.php', { action: 'save', level, room });
    if (res.status === 'success') openAdvisorMappingModal(); else Swal.fire('Error', res.message, 'error');
};
window.deleteAdvisorMapping = async (id) => {
    const res = await post('/behavior/api/manage_advisors.php', { action: 'delete', mappingId: id });
    if (res.status === 'success') openAdvisorMappingModal(); else Swal.fire('Error', res.message, 'error');
};

function renderClassroomSummary(data) {
    const body = document.getElementById('classroomSummaryBody');
    body.innerHTML = '';

    if (!data || !data.length) {
        body.innerHTML = '<tr><td colspan="5" class="px-4 py-12 text-center text-slate-400">ไม่มีข้อมูลนักเรียนในห้องนี้</td></tr>';
        return;
    }

    data.forEach(s => {
        const score = s.net;
        let riskLabel = 'ปกติ';
        let riskColor = 'bg-emerald-50 text-emerald-600';
        
        if (score < 60) {
            riskLabel = 'วิกฤต (ควรพบผู้ปกครอง)';
            riskColor = 'bg-rose-50 text-rose-600';
        } else if (score < 80) {
            riskLabel = 'เฝ้าระวัง';
            riskColor = 'bg-amber-50 text-amber-600';
        }

        const tr = document.createElement('tr');
        tr.className = 'border-b border-slate-50 hover:bg-slate-50 transition-colors';
        tr.innerHTML = `
            <td class="px-4 py-3 font-bold text-slate-700">${esc(s.name)}<br><span class="text-[10px] text-slate-400 font-mono">${esc(s.studentId)}</span></td>
            <td class="px-4 py-3 text-center font-black text-emerald-600">${s.good}</td>
            <td class="px-4 py-3 text-center font-black text-rose-600">${s.bad}</td>
            <td class="px-4 py-3 text-center font-black text-slate-800 text-lg">${s.net}</td>
            <td class="px-4 py-3 text-center"><span class="px-3 py-1 rounded-full text-[10px] font-black ${riskColor}">${riskLabel}</span></td>
        `;
        body.appendChild(tr);
    });
}

function exportTableToCSV() {
    if (!cachedAllRecordForDB.length) return Swal.fire('ไม่มีข้อมูล', '', 'warning');
    let rows = [["วันที่", "ชื่อ", "ระดับชั้น", "ประเภท", "กิจกรรม", "คะแนน", "ผู้บันทึก"].join(",")];
    cachedAllRecordForDB.forEach(r => {
        const d = new Date(r.date).toLocaleDateString('th-TH');
        rows.push([d, r.studentName, r.classInfo, r.type, r.activity, r.score, r.teacher].map(v => '"' + String(v || '').replace(/"/g, '""') + '"').join(','));
    });
    const blob = new Blob(["\uFEFF" + rows.join("\n")], { type: "text/csv;charset=utf-8;" });
    const link = document.createElement("a");
    link.href = URL.createObjectURL(blob);
    link.download = "Behavior_Export_" + new Date().toISOString().slice(0, 10) + ".csv";
    document.body.appendChild(link); link.click(); document.body.removeChild(link);
}

// ─── Student Modal ───
function openStudentModal() {
    document.getElementById('studentModal').classList.remove('hidden');
    if (!studentListLoaded) fetchStudentList();
}
function closeStudentModal() { document.getElementById('studentModal').classList.add('hidden'); }

async function fetchStudentList() {
    if (studentListLoading) return;
    studentListLoading = true;
    document.getElementById('studentListContainer').innerHTML = '<div class="text-center py-8 text-slate-400"><div class="animate-spin w-8 h-8 border-4 border-violet-200 border-t-violet-600 rounded-full mx-auto mb-2"></div>กำลังโหลด...</div>';

    const data = await api('/behavior/api/get_student_list.php');
    studentListLoading = false;
    studentListLoaded = true;
    cachedStudentList = Array.isArray(data) ? data : [];
    studentCurrentPage = 1;
    populateLevelFilterOptions();
    applyStudentFilterAndRender();
}

function populateLevelFilterOptions() {
    const sel = document.getElementById('studentLevelFilter');
    const dbSel = document.getElementById('dbFilterClass'); // New for Database tab
    
    while (sel.options.length > 1) sel.remove(1);
    if (dbSel) { while (dbSel.options.length > 1) dbSel.remove(1); }

    const classSet = new Set();
    cachedStudentList.forEach(s => { if (s.classText) classSet.add(s.classText); });
    const classList = Array.from(classSet).sort((a, b) => a.localeCompare(b, 'th'));
    
    classList.forEach(cls => {
        const opt = document.createElement('option'); opt.value = cls; opt.textContent = cls;
        sel.appendChild(opt);
        if (dbSel) {
            const opt2 = document.createElement('option'); opt2.value = cls; opt2.textContent = cls;
            dbSel.appendChild(opt2);
        }
    });
}

function applyStudentFilterAndRender() {
    const txt = (studentSearchText || '').toLowerCase();
    const cls = (studentLevelFilter || '').toLowerCase();
    studentFilteredList = cachedStudentList.filter(s => {
        const t = (s.studentId + ' ' + s.name).toLowerCase();
        const matchText = !txt || t.includes(txt);
        const matchClass = !cls || (s.classText || '').toLowerCase() === cls;
        return matchText && matchClass;
    });
    const totalPages = Math.max(1, Math.ceil(studentFilteredList.length / STUDENT_PAGE_SIZE));
    if (studentCurrentPage > totalPages) studentCurrentPage = totalPages;
    renderStudentModalPage();
}

function renderStudentModalPage() {
    const container = document.getElementById('studentListContainer');
    const pageInfo = document.getElementById('studentPageInfo');
    container.innerHTML = '';
    if (!studentFilteredList.length) {
        container.innerHTML = '<div class="text-center py-8 text-slate-400">ไม่พบข้อมูล</div>';
        if (pageInfo) pageInfo.textContent = 'Page 0 / 0';
        return;
    }
    const totalPages = Math.max(1, Math.ceil(studentFilteredList.length / STUDENT_PAGE_SIZE));
    const start = (studentCurrentPage - 1) * STUDENT_PAGE_SIZE;
    const items = studentFilteredList.slice(start, start + STUDENT_PAGE_SIZE);

    items.forEach(s => {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'w-full flex justify-between items-center px-4 py-3 rounded-xl hover:bg-violet-50 text-left transition-all border-b border-slate-50';
        btn.innerHTML = `<span class="text-sm font-medium text-slate-700">${esc(s.studentId)} - ${esc(s.name)}</span><span class="px-2 py-0.5 bg-slate-100 text-slate-500 rounded-lg text-[10px] font-bold">${esc(s.classText || '')}</span>`;
        btn.onclick = () => {
            document.getElementById('teacherStudentIdInput').value = s.studentId;
            closeStudentModal();
            loadStudentFocus(s.studentId);
        };
        container.appendChild(btn);
    });
    if (pageInfo) pageInfo.textContent = `Page ${studentCurrentPage} / ${totalPages}`;
}
</script>

<?php require_once __DIR__ . '/../components/layout_end.php'; ?>
