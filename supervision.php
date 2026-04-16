<?php
/**
 * supervision.php — ระบบนิเทศการจัดการเรียนรู้เชิงรุก
 */
session_start();
require_once __DIR__ . '/config.php';

// Auth guard
if (!isset($_SESSION['llw_role'])) {
    header('Location: /login.php');
    exit();
}

$pageTitle = 'นิเทศการจัดการเรียนรู้เชิงรุก';
$pageSubtitle = 'ระบบบันทึกและรายงานผลการนิเทศการจัดการเรียนรู้';
$activeSystem = 'supervision';

require_once __DIR__ . '/components/layout_start.php';
?>

<!-- Main Content -->
<div class="space-y-6 pb-20" id="supervisionApp">
    
    <!-- Explicit Mode Selector (Visible only for dual-role users) -->
    <div id="mode-selector-container" class="hidden no-print">
        <div class="bg-indigo-900 rounded-[2rem] p-4 shadow-2xl shadow-indigo-900/20 border border-indigo-800 flex flex-col md:flex-row items-center justify-between gap-4">
            <div class="flex items-center gap-4 pl-4">
                <div class="w-12 h-12 bg-white/10 rounded-2xl flex items-center justify-center text-indigo-300">
                    <i class="bi bi-shield-lock-fill text-2xl"></i>
                </div>
                <div>
                    <p class="text-[10px] font-black text-indigo-300 uppercase tracking-[0.2em] mb-0.5">สถานะการใช้งานระบบ</p>
                    <p class="text-white font-black text-sm">กรุณาระบุสถานะเพื่อเข้าถึงฟังก์ชันที่ต้องการ</p>
                </div>
            </div>
            
            <div class="flex bg-white/5 rounded-2xl p-1.5 w-full md:w-auto">
                <button onclick="switchMode('teacher')" id="mode-btn-teacher" class="flex-1 md:flex-none px-6 py-3 rounded-xl font-black text-xs transition-all flex items-center justify-center gap-2">
                    <i class="bi bi-person-fill"></i> ครูผู้รับการนิเทศ
                </button>
                <button onclick="switchMode('evaluator')" id="mode-btn-evaluator" class="flex-1 md:flex-none px-6 py-3 rounded-xl font-black text-xs transition-all flex items-center justify-center gap-2">
                    <i class="bi bi-person-badge-fill"></i> ผู้นิเทศ
                </button>
            </div>
        </div>
    </div>

    <!-- Navigation Tabs (Desktop & Mobile Scrollable) -->
    <div class="bg-white/70 backdrop-blur-xl rounded-[2.5rem] p-2 shadow-xl shadow-indigo-100/50 border border-white flex overflow-x-auto no-scrollbar gap-2 sticky top-4 z-30 no-print">
        <button onclick="switchTab('record')" id="tab-record" class="tab-btn whitespace-nowrap px-8 py-4 rounded-[2rem] font-black text-sm transition-all flex items-center gap-3">
            <i class="bi bi-pencil-square"></i> บันทึกการนิเทศ
        </button>
        <button onclick="switchTab('individual')" id="tab-individual" class="tab-btn whitespace-nowrap px-8 py-4 rounded-[2rem] font-black text-sm transition-all flex items-center gap-3">
            <i class="bi bi-person-badge"></i> รายงานรายบุคคล
        </button>
        <button onclick="switchTab('summary')" id="tab-summary" class="tab-btn whitespace-nowrap px-8 py-4 rounded-[2rem] font-black text-sm transition-all flex items-center gap-3 admin-only hidden">
            <i class="bi bi-pie-chart"></i> รายงานภาพรวม
        </button>
        <button onclick="switchTab('settings')" id="tab-settings" class="tab-btn whitespace-nowrap px-8 py-4 rounded-[2rem] font-black text-sm transition-all flex items-center gap-3 admin-only hidden">
            <i class="bi bi-person-gear"></i> ตั้งค่าข้อมูลครู
        </button>
    </div>

    <!-- 1. บันทึกการนิเทศ Part -->
    <div id="content-record" class="tab-content hidden space-y-6">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
            <!-- Left: Teacher Selection & Info -->
            <div class="lg:col-span-4 space-y-6">
                <div class="bg-white rounded-[2.5rem] shadow-xl shadow-slate-200/50 p-8 border border-slate-100 h-full">
                    <h3 class="text-xl font-black text-slate-800 flex items-center gap-3 mb-6">
                        <span class="w-10 h-10 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center">
                            <i class="bi bi-person-plus-fill"></i>
                        </span>
                        ข้อมูลผู้รับการนิเทศ
                    </h3>
                    
                    <div class="space-y-5">
                        <div class="group">
                            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest pl-1 mb-2 block">ชื่อ-นามสกุล ครูผู้สอน</label>
                            <select id="sel-teacher" onchange="onTeacherChange()" class="w-full bg-slate-50 border-2 border-slate-100 rounded-2xl px-5 py-4 text-sm font-bold focus:border-indigo-500 focus:bg-white outline-none transition-all appearance-none cursor-pointer">
                                <option value="">เลือกครูผู้สอน...</option>
                            </select>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest pl-1 mb-2 block">ตำแหน่ง</label>
                                <input type="text" id="info-position" readonly class="w-full bg-slate-100 border-none rounded-2xl px-5 py-4 text-sm font-bold text-slate-500 opacity-70">
                            </div>
                            <div>
                                <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest pl-1 mb-2 block">วิทยฐานะ</label>
                                <input type="text" id="info-academic" readonly class="w-full bg-slate-100 border-none rounded-2xl px-5 py-4 text-sm font-bold text-slate-500 opacity-70">
                            </div>
                        </div>

                        <div>
                            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest pl-1 mb-2 block">กลุ่มสาระการเรียนรู้</label>
                            <input type="text" id="info-group" readonly class="w-full bg-slate-100 border-none rounded-2xl px-5 py-4 text-sm font-bold text-slate-500 opacity-70">
                        </div>

                        <hr class="border-slate-100 my-2">

                        <div class="grid grid-cols-2 gap-4">
                            <div class="col-span-2">
                                <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest pl-1 mb-2 block">ชื่อรายวิชา <span class="text-rose-500 font-black">*</span></label>
                                <input type="text" id="course-name" placeholder="เช่น วิทยาการคำนวณ 1" class="w-full bg-slate-50 border-2 border-slate-100 rounded-2xl px-5 py-4 text-sm font-bold focus:border-indigo-500 focus:bg-white outline-none transition-all">
                            </div>
                            <div>
                                <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest pl-1 mb-2 block">รหัสวิชา <span class="text-rose-500 font-black">*</span></label>
                                <input type="text" id="course-code" placeholder="เช่น ว21103" class="w-full bg-slate-50 border-2 border-slate-100 rounded-2xl px-5 py-4 text-sm font-bold focus:border-indigo-500 focus:bg-white outline-none transition-all">
                            </div>
                            <div>
                                <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest pl-1 mb-2 block">ระดับชั้น <span class="text-rose-500 font-black">*</span></label>
                                <input type="text" id="class-level" placeholder="เช่น ม.1" class="w-full bg-slate-50 border-2 border-slate-100 rounded-2xl px-5 py-4 text-sm font-bold focus:border-indigo-500 focus:bg-white outline-none transition-all">
                            </div>
                        </div>

                        <div>
                            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest pl-1 mb-2 block">วันที่นิเทศ</label>
                            <input type="date" id="obs-date" value="<?= date('Y-m-d') ?>" class="w-full bg-slate-50 border-2 border-slate-100 rounded-2xl px-5 py-4 text-sm font-bold focus:border-indigo-500 focus:bg-white outline-none transition-all">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right: Scoring Items -->
            <div class="lg:col-span-8">
                <div class="bg-white rounded-[2.5rem] shadow-xl shadow-slate-200/50 border border-slate-100 overflow-hidden flex flex-col h-full">
                    <div class="p-8 border-b border-slate-100 bg-slate-50/50 flex items-center justify-between">
                        <h3 class="text-xl font-black text-slate-800 flex items-center gap-3">
                            <span class="w-10 h-10 rounded-xl bg-indigo-600 text-white flex items-center justify-center shadow-lg shadow-indigo-200">
                                <i class="bi bi-clipboard2-check"></i>
                            </span>
                            แบบนิเทศการจัดการเรียนรู้
                        </h3>
                        <div class="px-4 py-2 bg-white rounded-xl border border-slate-100 shadow-sm flex items-center gap-3">
                            <span class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Score Avg</span>
                            <span id="live-avg" class="text-xl font-black text-indigo-600">0.00</span>
                        </div>
                    </div>

                    <div class="p-0 flex-1 overflow-y-auto max-h-[700px] custom-scrollbar">
                        <div id="scoring-items" class="divide-y divide-slate-100">
                            <!-- Items generated by JS -->
                        </div>
                        
                        <div class="p-8 bg-slate-50/50 space-y-6">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest pl-1 mb-2 block">ผลการวิเคราะห์/สิ่งที่ค้นพบ</label>
                                    <textarea id="findings" rows="4" class="w-full bg-white border-2 border-slate-100 rounded-2xl px-5 py-4 text-sm font-medium focus:border-indigo-500 outline-none transition-all placeholder:text-slate-300" placeholder="ระบุสิ่งที่พบจากการนิเทศ..."></textarea>
                                </div>
                                <div>
                                    <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest pl-1 mb-2 block">จุดเด่น/สิ่งที่ประทับใจ</label>
                                    <textarea id="impressions" rows="4" class="w-full bg-white border-2 border-slate-100 rounded-2xl px-5 py-4 text-sm font-medium focus:border-indigo-500 outline-none transition-all placeholder:text-slate-300" placeholder="ระบุจุดเด่นหรือสิ่งที่ควรชื่นชม..."></textarea>
                                </div>
                                <div class="md:col-span-2">
                                    <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest pl-1 mb-2 block">ข้อขวัญ/สิ่งที่ควรพัฒนา</label>
                                    <textarea id="improvements" rows="4" class="w-full bg-white border-2 border-slate-100 rounded-2xl px-5 py-4 text-sm font-medium focus:border-indigo-500 outline-none transition-all placeholder:text-slate-300" placeholder="ระบุข้อเสนอแนะในการพัฒนา..."></textarea>
                                </div>
                            </div>

                            <div class="flex flex-col md:flex-row items-end gap-6 pt-4">
                                <div class="flex-1">
                                    <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest pl-1 mb-2 block">ผู้นิเทศ</label>
                                    <input type="text" id="observer-name" value="<?= htmlspecialchars($_SESSION['fullname']) ?>" class="w-full bg-white border-2 border-slate-100 rounded-2xl px-5 py-4 text-sm font-bold focus:border-indigo-500 outline-none transition-all">
                                </div>
                                <div class="flex-1">
                                    <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest pl-1 mb-2 block">ตำแหน่งผู้นิเทศ</label>
                                    <select id="observer-position" class="w-full bg-white border-2 border-slate-100 rounded-2xl px-5 py-4 text-sm font-bold focus:border-indigo-500 outline-none transition-all appearance-none cursor-pointer">
                                        <option value="">เลือกตำแหน่ง...</option>
                                        <option value="ผู้อำนวยการโรงเรียน">ผู้อำนวยการโรงเรียน</option>
                                        <option value="รองผู้อำนวยการโรงเรียน">รองผู้อำนวยการโรงเรียน</option>
                                        <option value="หัวหน้าฝ่าย">หัวหน้าฝ่าย</option>
                                        <option value="หัวหน้าสาระ">หัวหน้าสาระ</option>
                                        <option value="ผู้ที่ได้รับมอบหมาย">ผู้ที่ได้รับมอบหมาย</option>
                                        <option value="ประเมินตนเอง">ประเมินตนเอง</option>
                                    </select>
                                </div>
                                <button onclick="saveRecord()" class="h-[60px] px-12 bg-gradient-to-r from-indigo-600 to-indigo-700 text-white rounded-2xl font-black text-lg shadow-xl shadow-indigo-200 hover:scale-[1.02] active:scale-95 transition-all flex items-center justify-center gap-3">
                                    <i class="bi bi-cloud-arrow-up-fill"></i>
                                    บันทึกข้อมูล
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 2. รายงานรายบุคคล Part -->
    <div id="content-individual" class="tab-content hidden space-y-6">
        <div id="teacher-selection" class="bg-white rounded-[2.5rem] shadow-xl shadow-slate-200/50 p-8 border border-white flex flex-col md:flex-row items-center gap-6">
            <div class="flex-1 w-full">
                <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 block">เลือกครูเพื่อดูรายงาน</label>
                <select id="sel-report-teacher" onchange="loadIndividualReport()" class="w-full bg-slate-50 border-2 border-slate-100 rounded-2xl px-5 py-4 text-sm font-bold focus:border-indigo-500 outline-none transition-all appearance-none cursor-pointer">
                    <option value="">เลือกครูผู้สอน...</option>
                </select>
            </div>
            <button onclick="printPage()" class="px-8 py-4 bg-white border-2 border-slate-100 rounded-2xl font-black text-slate-700 shadow-sm hover:bg-slate-50 transition-all flex items-center gap-2">
                <i class="bi bi-printer"></i> พิมพ์รายงาน
            </button>
        </div>

        <div id="report-view-area" class="hidden space-y-6 print:m-0 print:p-0">
            <!-- Stats Grid -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                <div class="bg-gradient-to-br from-indigo-600 to-indigo-800 rounded-[2.5rem] p-8 text-white shadow-xl shadow-indigo-200">
                    <p class="text-[10px] font-black uppercase tracking-widest opacity-70">จำนวนครั้งที่รับการนิเทศ</p>
                    <p id="stat-count" class="text-5xl font-black mt-3 italic">0</p>
                </div>
                <div class="bg-white rounded-[2.5rem] p-8 shadow-xl shadow-slate-100 border border-slate-50">
                    <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">คะแนนเฉลี่ยรวม</p>
                    <p id="stat-avg" class="text-5xl font-black mt-3 text-slate-800 italic">0.00</p>
                </div>
                <div class="bg-white rounded-[2.5rem] p-8 shadow-xl shadow-slate-100 border border-slate-50">
                    <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">การแปลผล</p>
                    <div class="mt-3 flex items-center gap-3">
                        <span id="stat-inter" class="text-2xl font-black text-indigo-600">รอดำเนินการ</span>
                    </div>
                </div>
                <div class="bg-white rounded-[2.5rem] p-8 shadow-xl shadow-slate-100 border border-slate-50 flex items-center justify-center">
                    <div class="text-center">
                        <div id="profile-initial" class="w-16 h-16 rounded-2xl bg-indigo-50 text-indigo-600 flex items-center justify-center text-3xl font-black mx-auto mb-2 italic">T</div>
                        <p id="profile-name" class="text-sm font-black text-slate-800">ชื่อนามสกุล</p>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 items-start">
                <!-- Radar Chart -->
                <div class="bg-white rounded-[2.5rem] p-10 shadow-xl shadow-slate-200/50 border border-slate-100">
                    <h4 class="text-lg font-black text-slate-800 mb-8 flex items-center gap-3">
                        <span class="w-2 h-8 bg-indigo-500 rounded-full"></span>
                        สมรรถนะรายด้าน (Radar Chart)
                    </h4>
                    <div class="relative group h-[400px]">
                        <canvas id="radarChart"></canvas>
                    </div>
                </div>

                <!-- History Table -->
                <div class="bg-white rounded-[2.5rem] shadow-xl shadow-slate-200/50 border border-slate-100 overflow-hidden">
                    <div class="p-8 border-b border-slate-50 flex items-center justify-between">
                        <h4 class="text-lg font-black text-slate-800">ประวัติการรับนิเทศ</h4>
                        <button id="btn-triple-report" onclick="loadTripleSummary()" class="hidden px-4 py-2 bg-indigo-50 text-indigo-600 rounded-xl text-[10px] font-black hover:bg-indigo-600 hover:text-white transition-all">
                            <i class="bi bi-people-fill mr-1"></i> พิมพ์สรุป 3 กรรมการ
                        </button>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left">
                            <thead class="bg-slate-50">
                                <tr>
                                    <th class="px-8 py-5 text-[10px] font-black text-slate-400 uppercase tracking-widest">วันที่</th>
                                    <th class="px-8 py-5 text-[10px] font-black text-slate-400 uppercase tracking-widest">วิชา/ชั้น</th>
                                    <th class="px-8 py-5 text-[10px] font-black text-slate-400 uppercase tracking-widest">ผู้นิเทศ</th>
                                    <th class="px-8 py-5 text-[10px] font-black text-slate-400 uppercase tracking-widest text-center">คะแนนเฉลี่ย</th>
                                    <th class="px-8 py-5 text-[10px] font-black text-slate-400 uppercase tracking-widest">ผลประเมิน</th>
                                </tr>
                            </thead>
                            <tbody id="history-rows" class="divide-y divide-slate-50">
                                <!-- Data rows -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 3. รายงานภาพรวม Part -->
    <div id="content-summary" class="tab-content hidden space-y-6">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-6">
            <div class="bg-gradient-to-br from-indigo-600 to-indigo-800 rounded-[2rem] p-6 text-white shadow-xl shadow-indigo-200">
                 <p class="text-[9px] font-black uppercase tracking-widest opacity-70">ครูทั้งหมด (ไม่รวม ผอ./รอง)</p>
                 <p id="sum-kpi-total" class="text-4xl font-black mt-2 italic">0</p>
            </div>
            <div class="bg-white rounded-[2rem] p-6 shadow-xl shadow-slate-100 border border-slate-100">
                <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest">ประเมินตนเองแล้ว</p>
                <div class="flex items-baseline gap-2">
                    <p id="sum-kpi-self" class="text-4xl font-black mt-2 text-slate-800 italic">0</p>
                    <p class="text-[10px] font-bold text-slate-400">คน</p>
                </div>
            </div>
            <div class="bg-white rounded-[2rem] p-6 shadow-xl shadow-slate-100 border border-slate-100">
                <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest">ประเมินครบ 3 ท่านแล้ว</p>
                <div class="flex items-baseline gap-2">
                    <p id="sum-kpi-peer3" class="text-4xl font-black mt-2 text-indigo-600 italic">0</p>
                    <p class="text-[10px] font-bold text-slate-400">คน</p>
                </div>
            </div>
            <div class="bg-white rounded-[2rem] p-6 shadow-xl shadow-slate-100 border border-slate-100">
                <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest">จำนวนครั้งการนิเทศรวม</p>
                <p id="sum-total-obs" class="text-4xl font-black mt-2 text-slate-800 italic">0</p>
            </div>
            <div class="bg-white rounded-[2rem] p-6 shadow-xl shadow-slate-100 border border-slate-100">
                <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest">ครูที่รับนิเทศแล้ว (คน)</p>
                <p id="sum-total-teachers" class="text-4xl font-black mt-2 text-slate-800 italic">0</p>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Pie Chart (Interpretation) -->
            <div class="bg-white rounded-[2.5rem] p-10 shadow-xl shadow-slate-200/50 border border-slate-100">
                <h4 class="text-lg font-black text-slate-800 mb-8 flex items-center gap-3">
                    <span class="w-2 h-8 bg-pink-500 rounded-full"></span>
                    สัดส่วนผลประเมินรายคุณภาพ
                </h4>
                <div class="relative h-[300px]">
                    <canvas id="summaryPieChart"></canvas>
                </div>
            </div>

            <!-- Global Radar -->
            <div class="lg:col-span-2 bg-white rounded-[2.5rem] p-10 shadow-xl shadow-slate-200/50 border border-slate-100">
                <h4 class="text-lg font-black text-slate-800 mb-8 flex items-center gap-3">
                    <span class="w-2 h-8 bg-blue-500 rounded-full"></span>
                    ค่าเฉลี่ยสมรรถนะภาพรวมโรงเรียน
                </h4>
                <div class="relative h-[400px]">
                    <canvas id="summaryRadarChart"></canvas>
                </div>
            </div>

            <!-- Recent Record Cards -->
            <div class="lg:col-span-3 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6" id="recent-cards">
                <!-- Record Cards -->
            </div>
        </div>
    </div>

    <!-- 4. Settings (Teacher Profiling) -->
    <div id="content-settings" class="tab-content hidden">
        <div class="bg-white rounded-[2.5rem] shadow-xl shadow-slate-200/50 p-10 border border-slate-100">
            <h3 class="text-2xl font-black text-slate-800 mb-8 flex items-center gap-4">
                <span class="w-12 h-12 rounded-2xl bg-slate-100 flex items-center justify-center text-slate-600">
                    <i class="bi bi-person-gear"></i>
                </span>
                จัดการข้อมูลโปรไฟล์ครู
            </h3>

            <div class="overflow-x-auto">
                <table class="w-full text-left">
                     <thead>
                        <tr class="bg-slate-50">
                            <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest rounded-l-2xl">ชื่อครู</th>
                            <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">ตำแหน่ง</th>
                            <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">วิทยฐานะ</th>
                            <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">กลุ่มสาระฯ</th>
                            <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest text-center">สิทธิ์ผู้นิเทศ</th>
                            <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest rounded-r-2xl text-center">จัดการ</th>
                        </tr>
                     </thead>
                     <tbody id="settings-teacher-list" class="divide-y divide-slate-50">
                         <!-- Teacher profiling rows -->
                     </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

<!-- Scoring Item Template (Hidden) -->
<template id="item-template">
    <div class="item-row group p-6 sm:p-8 hover:bg-slate-50 transition-all flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div class="flex-1">
            <div class="flex items-center gap-3">
                <span class="item-number w-8 h-8 rounded-lg bg-slate-100 text-slate-400 text-xs font-black flex items-center justify-center group-hover:bg-indigo-600 group-hover:text-white transition-colors italic">01</span>
                <p class="item-text text-sm font-black text-slate-700 leading-relaxed"></p>
            </div>
        </div>
        <div class="flex items-center gap-1.5 bg-white p-1.5 rounded-2xl border border-slate-100 shadow-sm self-start md:self-auto">
            <button onclick="setScore(this, 1)" class="score-btn w-9 h-9 rounded-xl font-black text-xs transition-all hover:scale-110">1</button>
            <button onclick="setScore(this, 2)" class="score-btn w-9 h-9 rounded-xl font-black text-xs transition-all hover:scale-110">2</button>
            <button onclick="setScore(this, 3)" class="score-btn w-9 h-9 rounded-xl font-black text-xs transition-all hover:scale-110">3</button>
            <button onclick="setScore(this, 4)" class="score-btn w-9 h-9 rounded-xl font-black text-xs transition-all hover:scale-110">4</button>
            <button onclick="setScore(this, 5)" class="score-btn w-9 h-9 rounded-xl font-black text-xs transition-all hover:scale-110">5</button>
        </div>
    </div>
</template>

<style>
    .tab-btn { color: #94a3b8; background: transparent; }
    .tab-btn:hover { background: rgba(79, 70, 229, 0.05); color: #4f46e5; }
    .tab-btn.active { background: #4f46e5; color: white; box-shadow: 0 10px 25px -5px rgba(79, 70, 229, 0.4); }
    
    .score-btn { background: #f8fafc; color: #94a3b8; border: 1px solid #f1f5f9; }
    .score-btn.active-1 { background: #ef4444; color: white; border-color: #ef4444; box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3); }
    .score-btn.active-2 { background: #f97316; color: white; border-color: #f97316; box-shadow: 0 4px 12px rgba(249, 115, 22, 0.3); }
    .score-btn.active-3 { background: #eab308; color: white; border-color: #eab308; box-shadow: 0 4px 12px rgba(234, 179, 8, 0.3); }
    .score-btn.active-4 { background: #22c55e; color: white; border-color: #22c55e; box-shadow: 0 4px 12px rgba(34, 197, 94, 0.3); }
    .score-btn.active-5 { background: #06b6d4; color: white; border-color: #06b6d4; box-shadow: 0 4px 12px rgba(6, 182, 212, 0.3); }

    .custom-scrollbar::-webkit-scrollbar { width: 6px; }
    .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
    .custom-scrollbar::-webkit-scrollbar-thumb { background: #e2e8f0; border-radius: 10px; }
    .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #cbd5e1; }

    @media print {
        .no-print { display: none !important; }
        .sticky { position: static !important; }
        .shadow-xl, .shadow-2xl { box-shadow: none !important; }
        .rounded-[2.5rem] { border-radius: 1rem !important; }
        .tab-content { display: block !important; }
        #tab-record, #tab-settings { display: none !important; }
        .h-[400px], .h-[300px] { height: auto !important; min-height: 400px; }
    }
</style>

<!-- Detailed Modal -->
<div id="modal-details" class="fixed inset-0 z-[1000] hidden overflow-y-auto overflow-x-hidden p-4 md:p-8 outline-none no-print">
    <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity" onclick="hideRecordDetails()"></div>
    <div class="relative mx-auto w-full max-w-4xl bg-white rounded-[2.5rem] shadow-2xl shadow-indigo-900/40 transform transition-all">
        <!-- Header -->
        <div class="p-8 border-b border-slate-100 flex items-center justify-between">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 bg-indigo-600 rounded-2xl flex items-center justify-center text-white shadow-lg shadow-indigo-200">
                    <i class="bi bi-journal-text text-2xl"></i>
                </div>
                <div>
                    <h3 id="md-title" class="text-xl font-black text-slate-800">รายละเอียดการนิเทศ</h3>
                    <p id="md-subtitle" class="text-xs font-bold text-slate-400 uppercase tracking-widest">Active Learning Supervision Result</p>
                </div>
            </div>
            <button onclick="hideRecordDetails()" class="w-10 h-10 rounded-xl bg-slate-50 text-slate-400 hover:bg-rose-50 hover:text-rose-500 transition-all">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>

        <div class="p-8 space-y-8 max-h-[75vh] overflow-y-auto custom-scrollbar" id="md-body">
            <!-- Summary Info -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div class="bg-slate-50 p-4 rounded-2xl border border-slate-100">
                    <p class="text-[9px] font-black text-slate-400 uppercase tracking-wider">รหัสวิชา</p>
                    <p id="md-code" class="text-sm font-bold text-slate-700 mt-1">-</p>
                </div>
                <div class="bg-slate-50 p-4 rounded-2xl border border-slate-100">
                    <p class="text-[9px] font-black text-slate-400 uppercase tracking-wider">ระดับชั้น</p>
                    <p id="md-class" class="text-sm font-bold text-slate-700 mt-1">-</p>
                </div>
                <div class="bg-slate-50 p-4 rounded-2xl border border-slate-100">
                    <p class="text-[9px] font-black text-slate-400 uppercase tracking-wider">วันที่นิเทศ</p>
                    <p id="md-date" class="text-sm font-bold text-slate-700 mt-1">-</p>
                </div>
                <div class="bg-indigo-600 p-4 rounded-2xl text-white shadow-xl shadow-indigo-200">
                    <p class="text-[9px] font-black opacity-80 uppercase tracking-wider">คะแนนเฉลี่ย</p>
                    <p id="md-avg" class="text-2xl font-black italic mt-1">-</p>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <!-- Left: Text fields -->
                <div class="md:col-span-2 space-y-6">
                    <div>
                        <h4 class="text-sm font-black text-slate-800 flex items-center gap-2 mb-3">
                            <i class="bi bi-search text-indigo-500"></i> สิ่งที่พบจากการนิเทศ
                        </h4>
                        <div id="md-findings" class="p-5 bg-white border border-slate-100 rounded-2xl text-sm text-slate-600 leading-relaxed min-h-[80px]"></div>
                    </div>
                    <div>
                        <h4 class="text-sm font-black text-slate-800 flex items-center gap-2 mb-3">
                            <i class="bi bi-star-fill text-amber-500"></i> จุดเด่นที่ควรชื่นชม
                        </h4>
                        <div id="md-impressions" class="p-5 bg-white border border-slate-100 rounded-2xl text-sm text-slate-600 leading-relaxed min-h-[80px]"></div>
                    </div>
                    <div>
                        <h4 class="text-sm font-black text-slate-800 flex items-center gap-2 mb-3">
                            <i class="bi bi-arrow-up-circle-fill text-emerald-500"></i> สิ่งที่ควรพัฒนา
                        </h4>
                        <div id="md-improvements" class="p-5 bg-white border border-slate-100 rounded-2xl text-sm text-slate-600 leading-relaxed min-h-[80px]"></div>
                    </div>
                </div>

                <!-- Right: Metadata & Scores List -->
                <div class="space-y-6">
                    <div class="bg-slate-900 rounded-2xl p-6 text-white overflow-hidden relative">
                        <div class="relative z-10">
                            <p class="text-[9px] font-black text-slate-500 uppercase tracking-widest mb-4">ข้อมูลผู้นิเทศ</p>
                            <p id="md-obs-name" class="font-bold text-sm">-</p>
                            <p id="md-obs-pos" class="text-xs text-indigo-400 mt-1">-</p>
                        </div>
                        <i class="bi bi-person-check absolute -right-4 -bottom-4 text-7xl opacity-10"></i>
                    </div>

                    <div class="space-y-3">
                        <h4 class="text-[10px] font-black text-slate-400 uppercase tracking-widest pl-1">รายการคะแนน (27 หัวข้อ)</h4>
                        <div id="md-score-list" class="space-y-2 pr-2">
                            <!-- Populated by JS -->
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="p-8 bg-slate-50 rounded-b-[2.5rem] border-t border-slate-100 flex justify-between items-center">
            <p class="text-[10px] font-black text-slate-300 uppercase italic">Active Learning Supervision | LLW System</p>
            <div class="flex items-center gap-3">
                <button onclick="printPage()" class="px-6 py-3 bg-white border border-slate-200 text-slate-600 rounded-xl font-black text-xs hover:bg-slate-100 transition-all flex items-center gap-2">
                    <i class="bi bi-printer"></i> พิมพ์รายงาน
                </button>
            </div>
        </div>
    </div>
</div>

<script>
const BASE_URL = '<?= $base_path ?>/api/supervision/';
const APP_ROLE = '<?= $_SESSION['llw_role'] ?>';
const APP_USER_ID = '<?= $_SESSION['user_id'] ?>';
const ITEMS_DESC = [
    "การจัดทำแผนการจัดการเรียนรู้ที่เน้นผู้เรียนเป็นสำคัญ",
    "การวิเคราะห์ผู้เรียนรายบุคคล",
    "การกำหนดเป้าหมายการเรียนรู้ที่ครอบคลุม K S A",
    "การออกแบบกิจกรรมการเรียนรู้แบบเชิงรุก (Active Learning)",
    "การเตรียมสื่อและแหล่งเรียนรู้ที่หลากหลาย",
    "การนำเข้าสู่บทเรียนที่กระตุ้นความสนใจ",
    "ลำดับขั้นตอนการสอนที่ต่อเนื่องชัดเจน",
    "การใช้คำถามกระตุ้นการคิดระดับสูง",
    "ครูเป็นผู้อำนวยความสะดวกในห้องเรียน",
    "การสรุปองค์ความรู้ร่วมกับผู้เรียน",
    "การบริหารจัดการเวลาได้อย่างเหมาะสม",
    "การแก้ปัญหาเฉพาะหน้าในระหว่างการสอน",
    "การเชื่องโยงบทเรียนกับชีวิตประจำวัน",
    "การใช้สื่อ ICT ในการจัดการเรียนรู้",
    "การใช้แหล่งเรียนรู้นอกห้องเรียนหรือออนไลน์",
    "การผลิตสื่อ/นวัตกรรมใหม่ๆ",
    "ผู้เรียนใช้เทคโนโลยีในการสร้างสรรค์งาน",
    "ความเหมาะสมของสื่อกับเนื้อหาวิชา",
    "การวัดผลที่หลากหลาย (เน้นประเมินตามสภาพจริง)",
    "การแจ้งเกณฑ์การประเมินที่ชัดเจน",
    "การให้ข้อมูลสะท้อนกลับ (Feedback) แก่ผู้เรียน",
    "การใช้เครื่องมือวัดผลที่ทันสมัย",
    "การนำผลไปใช้พัฒนาผู้เรียน (CAR)",
    "บรรยากาศในห้องเรียนที่ส่งเสริมการเรียนรู้",
    "การสร้างแรงจูงใจและปฏิสัมพันธ์ที่ดี",
    "ห้องเรียนสะอาด เป็นระเบียบเรียบร้อย",
    "การดูแลทั่วถึงและช่วยเหลือผู้เรียน"
];

let teachers = [];
let scores = arrayToNulls(27);
let radarChart, summaryPieChart, summaryRadarChart;
let CURRENT_MODE = null; 
let USER_IS_EVALUATOR = false;

function arrayToNulls(len) { return Array(len).fill(0); }

function printPage() {
    window.print();
}

document.addEventListener('DOMContentLoaded', () => {
    initUI();
    loadTeachers();
});

function initUI() {
    // Generate Scoring Items
    const container = document.getElementById('scoring-items');
    const template = document.getElementById('item-template');
    
    ITEMS_DESC.forEach((desc, i) => {
        const clone = template.content.cloneNode(true);
        clone.querySelector('.item-number').textContent = (i + 1).toString().padStart(2, '0');
        clone.querySelector('.item-text').textContent = desc;
        
        // Add data attributes
        const buttons = clone.querySelectorAll('.score-btn');
        buttons.forEach(btn => btn.setAttribute('data-idx', i));
        
        container.appendChild(clone);
    });
}

function switchMode(mode) {
    if (!USER_IS_EVALUATOR && mode === 'evaluator') return;
    CURRENT_MODE = mode;
    
    // UI Update for buttons
    const btnTeacher = document.getElementById('mode-btn-teacher');
    const btnEvaluator = document.getElementById('mode-btn-evaluator');
    
    if (mode === 'teacher') {
        btnTeacher.className = "flex-1 md:flex-none px-6 py-3 rounded-xl font-black text-xs transition-all flex items-center justify-center gap-2 bg-white text-indigo-900 shadow-lg shadow-indigo-200/50";
        btnEvaluator.className = "flex-1 md:flex-none px-6 py-3 rounded-xl font-black text-xs transition-all flex items-center justify-center gap-2 text-indigo-300 hover:bg-white/5";
        
        document.querySelectorAll('.admin-only').forEach(el => el.classList.add('hidden'));
        document.getElementById('teacher-selection').classList.add('hidden');
        
        const selTeacher = document.getElementById('sel-teacher');
        selTeacher.value = APP_USER_ID;
        selTeacher.disabled = true;

        const selReport = document.getElementById('sel-report-teacher');
        if (selReport) selReport.value = APP_USER_ID;

        document.getElementById('observer-position').value = 'ประเมินตนเอง';
        
        onTeacherChange();
        loadIndividualReport();
        switchTab('individual');
    } else {
        btnEvaluator.className = "flex-1 md:flex-none px-6 py-3 rounded-xl font-black text-xs transition-all flex items-center justify-center gap-2 bg-white text-indigo-900 shadow-lg shadow-indigo-200/50";
        btnTeacher.className = "flex-1 md:flex-none px-6 py-3 rounded-xl font-black text-xs transition-all flex items-center justify-center gap-2 text-indigo-300 hover:bg-white/5";
        
        document.querySelectorAll('.admin-only').forEach(el => el.classList.remove('hidden'));
        document.getElementById('teacher-selection').classList.remove('hidden');
        document.getElementById('sel-teacher').disabled = false;
        
        switchTab('record');
    }
}

async function loadTeachers() {
    try {
        const res = await fetch(BASE_URL + 'get_teachers.php');
        const json = await res.json();
        if (json.status === 'success') {
            teachers = json.data;
            const selRecord = document.getElementById('sel-teacher');
            const selReport = document.getElementById('sel-report-teacher');
            
            // Populate Dropdowns
            teachers.forEach(t => {
                const opt = `<option value="${t.id}">${t.name}</option>`;
                selRecord.insertAdjacentHTML('beforeend', opt);
                selReport.insertAdjacentHTML('beforeend', opt);
            });

            renderSettingsList();

            // Set Evaluator Status
            const self = teachers.find(t => t.id == APP_USER_ID);
            USER_IS_EVALUATOR = ['super_admin', 'wfh_admin'].includes(APP_ROLE) || (self && self.is_evaluator == 1);
            
            if (USER_IS_EVALUATOR) {
                document.getElementById('mode-selector-container').classList.remove('hidden');
            }
            
            // Initial Mode
            switchMode('teacher');
            
            // Check URL for specific tab
            const urlParams = new URLSearchParams(window.location.search);
            const tab = urlParams.get('tab');
            if (tab) switchTab(tab);
        }
    } catch (e) { console.error('Load teachers failed', e); }
}

async function onTeacherChange() {
    const id = document.getElementById('sel-teacher').value;
    const t = teachers.find(x => x.id == id);
    if (!t) return;
    
    document.getElementById('info-position').value = t.position || 'ยังไม่ระบุ';
    document.getElementById('info-academic').value = t.academic_status || 'ยังไม่ระบุ';
    document.getElementById('info-group').value = t.subject_group || 'ยังไม่ระบุ';

    if (CURRENT_MODE === 'evaluator' && id) {
        try {
            const res = await fetch(BASE_URL + 'get_latest_self_eval.php?teacher_id=' + id);
            const json = await res.json();
            if (json.status === 'success' && json.data) {
                const meta = json.data.metadata;
                const selfScores = json.data.scores;

                document.getElementById('course-name').value = meta.course_name;
                document.getElementById('course-code').value = meta.course_code;
                document.getElementById('class-level').value = meta.class_level;

                if (selfScores && selfScores.length > 0) {
                    selfScores.forEach(s => {
                        const idx = s.item_idx;
                        const val = s.score;
                        scores[idx] = val;
                        
                        // Find button in DOM and activate it
                        const btn = document.querySelector(`.score-btn[data-idx="${idx}"][onclick*="${val})"]`);
                        if (btn) {
                            const row = btn.closest('.item-row');
                            row.querySelectorAll('.score-btn').forEach(b => {
                                b.className = 'score-btn w-9 h-9 rounded-xl font-black text-xs transition-all hover:scale-110';
                            });
                            btn.classList.add(`active-${val}`);
                        }
                    });
                    calculateAvg();
                }

                const Toast = Swal.mixin({
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 3000,
                    timerProgressBar: true
                });
                Toast.fire({
                    icon: 'success',
                    title: 'โหลดข้อมูลการประเมินตนเองแล้ว'
                });
            } else {
                // Clear course fields if no self-eval found
                document.getElementById('course-name').value = '';
                document.getElementById('course-code').value = '';
                document.getElementById('class-level').value = '';
                scores.fill(0);
                document.querySelectorAll('.score-btn').forEach(b => {
                    b.className = 'score-btn w-9 h-9 rounded-xl font-black text-xs transition-all hover:scale-110';
                });
                calculateAvg();
            }
        } catch (e) { console.error('Failed to load self-eval', e); }
    }
}

function setScore(btn, val) {
    const idx = btn.getAttribute('data-idx');
    const row = btn.closest('.item-row');
    
    // Clear siblings
    row.querySelectorAll('.score-btn').forEach(b => {
        b.className = 'score-btn w-9 h-9 rounded-xl font-black text-xs transition-all hover:scale-110';
    });
    
    // Set active
    btn.classList.add(`active-${val}`);
    scores[idx] = val;
    
    calculateAvg();
}

function calculateAvg() {
    const count = scores.filter(x => x > 0).length;
    if (count === 0) return;
    const sum = scores.reduce((a, b) => a + b, 0);
    const avg = sum / count;
    document.getElementById('live-avg').textContent = avg.toFixed(2);
}

async function saveRecord() {
    const teacher_id = document.getElementById('sel-teacher').value;
    if (!teacher_id) return Swal.fire('แจ้งเตือน', 'กรุณาเลือกครูผู้รับการนิเทศ', 'warning');
    
    // Validation
    const courseName = document.getElementById('course-name').value;
    const courseCode = document.getElementById('course-code').value;
    const classLevel = document.getElementById('class-level').value;
    
    if (!courseName || !courseCode || !classLevel) {
        return Swal.fire('ข้อมูลไม่ครบถ้วน', 'กรุณาระบุ ชื่อวิชา, รหัสวิชา และระดับชั้นให้ครบถ้วน', 'warning');
    }

    if (scores.includes(0)) return Swal.fire('แจ้งเตือน', 'กรุณาให้คะแนนให้ครบทุกข้อ (27 ข้อ)', 'warning');

    const data = {
        teacher_id,
        course_name: courseName,
        course_code: courseCode,
        class_level: classLevel,
        observation_date: document.getElementById('obs-date').value,
        findings: document.getElementById('findings').value,
        impressions: document.getElementById('impressions').value,
        improvements: document.getElementById('improvements').value,
        observer_name: document.getElementById('observer-name').value,
        observer_position: document.getElementById('observer-position').value,
        scores
    };

    try {
        const res = await fetch(BASE_URL + 'save_record.php', {
            method: 'POST',
            body: JSON.stringify(data)
        });
        const json = await res.json();
        if (json.status === 'success') {
            Swal.fire('สำเร็จ', 'บันทึกการนิเทศเรียบร้อยแล้ว', 'success').then(() => {
                location.reload();
            });
        } else {
            Swal.fire('เกิดข้อผิดพลาด', json.message, 'error');
        }
    } catch (e) { Swal.fire('ผิดพลาด', 'ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้', 'error'); }
}

function switchTab(tab) {
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.querySelectorAll('.tab-content').forEach(c => c.classList.add('hidden'));
    
    document.getElementById(`tab-${tab}`).classList.add('active');
    document.getElementById(`content-${tab}`).classList.remove('hidden');

    if (tab === 'summary') loadSummaryReport();
    
    // Update URL without refresh
    window.history.replaceState(null, null, `?tab=${tab}`);
}

// ── Reports & Charts ────────────────

async function loadIndividualReport() {
    const id = document.getElementById('sel-report-teacher').value;
    if (!id) return;

    try {
        const res = await fetch(BASE_URL + 'get_individual.php?teacher_id=' + id);
        const json = await res.json();
        if (json.status === 'success') {
            document.getElementById('report-view-area').classList.remove('hidden');
            
            // Stats
            document.getElementById('profile-name').textContent = json.teacher.firstname + ' ' + json.teacher.lastname;
            document.getElementById('profile-initial').textContent = json.teacher.firstname[0];
            document.getElementById('stat-count').textContent = json.records.length;
            
            const totalAvg = json.records.reduce((a, b) => a + parseFloat(b.average_score), 0) / json.records.length;
            document.getElementById('stat-avg').textContent = totalAvg.toFixed(2);
            document.getElementById('stat-inter').textContent = getInterpretation(totalAvg);

            // Triple Report Button Visibility
            const peerEvals = json.records.filter(r => r.observer_position !== 'ประเมินตนเอง').length;
            if (peerEvals >= 1) {
                document.getElementById('btn-triple-report').classList.remove('hidden');
            } else {
                document.getElementById('btn-triple-report').classList.add('hidden');
            }

            // Table
            const tbody = document.getElementById('history-rows');
            tbody.innerHTML = json.records.map(r => `
                <tr class="hover:bg-indigo-50 cursor-pointer transition-all border-b border-slate-50" onclick="showRecordDetails(${r.id})">
                    <td class="px-8 py-5 text-sm font-bold text-slate-700">${new Date(r.observation_date).toLocaleDateString('th-TH')}</td>
                    <td class="px-8 py-5">
                        <p class="text-sm font-black text-slate-800">${r.course_name}</p>
                        <p class="text-[10px] text-indigo-500 font-bold uppercase tracking-wider">${r.course_code} | ${r.class_level}</p>
                    </td>
                    <td class="px-8 py-5">
                        <div class="flex items-center gap-2">
                             <span class="w-8 h-8 rounded-full bg-slate-100 flex items-center justify-center text-[10px] font-black text-slate-500">${r.observer_name ? r.observer_name[0] : '?'}</span>
                             <div>
                                <p class="text-xs font-bold text-slate-700">${r.observer_name || 'ไม่ระบุ'}</p>
                                <p class="text-[9px] font-bold text-slate-400 uppercase tracking-tight">${r.observer_position || '-'}</p>
                             </div>
                        </div>
                    </td>
                    <td class="px-8 py-5 text-center">
                        <span class="inline-block px-4 py-1.5 rounded-xl bg-indigo-50 text-indigo-600 text-xs font-black italic">
                            ${parseFloat(r.average_score).toFixed(2)}
                        </span>
                    </td>
                    <td class="px-8 py-5">
                        <div class="flex items-center justify-between gap-2">
                            <span class="px-3 py-1.5 rounded-full ${getInterColor(r.average_score)} text-[10px] font-black uppercase tracking-wider">
                                ${r.interpretation}
                            </span>
                            <i class="bi bi-chevron-right text-slate-300"></i>
                        </div>
                    </td>
                </tr>
            `).join('');

            renderRadar(json.radar);
        }
    } catch (e) {  }
}

async function loadSummaryReport() {
    try {
        const res = await fetch(BASE_URL + 'get_summary.php');
        const json = await res.json();
        if (json.status === 'success') {
            document.getElementById('sum-total-teachers').textContent = json.stats.total_teachers_supervised;
            document.getElementById('sum-total-obs').textContent = json.stats.total_records;
            
            // New KPIs
            document.getElementById('sum-kpi-total').textContent = json.stats.kpi_total_staff;
            document.getElementById('sum-kpi-self').textContent = json.stats.kpi_self_done;
            document.getElementById('sum-kpi-peer3').textContent = json.stats.kpi_peer_done;

            // Pie Chart
            renderSummaryPie(json.pie);
            // Global Radar
            renderGlobalRadar(json.radar);
            
            // Recent Cards
            document.getElementById('recent-cards').innerHTML = json.recent.map(r => `
                <div class="bg-white rounded-[2rem] p-6 shadow-xl shadow-slate-200/50 border border-slate-50 hover:scale-[1.02] cursor-pointer transition-all" onclick="showRecordDetails(${r.id})">
                    <div class="flex items-center gap-3 mb-4">
                        <div class="w-10 h-10 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center font-black">${r.teacher_name[0]}</div>
                        <div>
                            <p class="text-xs font-bold text-slate-800">${r.teacher_name}</p>
                            <p class="text-[9px] font-bold text-slate-400 uppercase">${new Date(r.observation_date).toLocaleDateString('th-TH')}</p>
                        </div>
                        <div class="ml-auto px-3 py-1 rounded-lg ${getInterColor(r.average_score)} text-[8px] font-black uppercase">${r.interpretation}</div>
                    </div>
                    <p class="text-xs font-black text-slate-700 line-clamp-1">${r.course_name}</p>
                    <p class="text-[9px] font-bold text-indigo-500 uppercase mt-1">${r.course_code} | ${r.class_level}</p>
                    <div class="mt-4 flex items-center justify-between">
                        <span class="text-xl font-black italic text-indigo-600">${r.average_score}</span>
                        <p class="text-[10px] font-bold text-slate-300">นิเทศโดย: ${r.observer_name}</p>
                    </div>
                </div>
            `).join('');
        }
    } catch (e) { }
}

function renderRadar(data) {
    const ctx = document.getElementById('radarChart').getContext('2d');
    if (radarChart) radarChart.destroy();
    
    radarChart = new Chart(ctx, {
        type: 'radar',
        data: {
            labels: ['การจัดการเรียนรู้', 'นวัตกรรม/ICT', 'วัดและประเมินผล', 'บรรยากาศชั้นเรียน'],
            datasets: [{
                label: 'สมรรถนะครู',
                data: [data.group1, data.group2, data.group3, data.group4],
                backgroundColor: 'rgba(79, 70, 229, 0.2)',
                borderColor: 'rgba(79, 70, 229, 1)',
                pointBackgroundColor: 'rgba(79, 70, 229, 1)',
                pointBorderColor: '#fff'
            }]
        },
        options: {
            maintainAspectRatio: false,
            scales: { r: { min: 0, max: 5, ticks: { stepSize: 1 } } },
            plugins: { legend: { display: false } }
        }
    });
}

function renderSummaryPie(data) {
    const ctx = document.getElementById('summaryPieChart').getContext('2d');
    if (summaryPieChart) summaryPieChart.destroy();
    
    summaryPieChart = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: data.map(x => x.interpretation),
            datasets: [{
                data: data.map(x => x.count),
                backgroundColor: ['#22c55e', '#06b6d4', '#4f46e5', '#f97316', '#ef4444']
            }]
        },
        options: {
            maintainAspectRatio: false,
            plugins: { legend: { position: 'bottom', labels: { font: { family: 'Prompt', weight: 'bold' } } } }
        }
    });
}

function renderGlobalRadar(data) {
    const ctx = document.getElementById('summaryRadarChart').getContext('2d');
    if (summaryRadarChart) summaryRadarChart.destroy();
    
    summaryRadarChart = new Chart(ctx, {
        type: 'radar',
        data: {
            labels: ['การจัดการเรียนรู้', 'นวัตกรรม/ICT', 'วัดและประเมินผล', 'บรรยากาศชั้นเรียน'],
            datasets: [{
                label: 'ค่าเฉลี่ยทั้งโรงเรียน',
                data: [data.group1, data.group2, data.group3, data.group4],
                backgroundColor: 'rgba(56, 189, 248, 0.2)',
                borderColor: '#0ea5e9',
                pointBackgroundColor: '#0ea5e9'
            }]
        },
        options: { maintainAspectRatio: false, scales: { r: { min: 0, max: 5 } } }
    });
}

function renderSettingsList() {
    const tbody = document.getElementById('settings-teacher-list');
    tbody.innerHTML = teachers.map(t => `
        <tr class="hover:bg-slate-50 transition-colors group">
            <td class="px-6 py-5">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center font-black text-sm">${t.name.charAt(0)}</div>
                    <span class="font-black text-slate-700 text-sm">${t.name}</span>
                </div>
            </td>
            <td class="px-6 py-5">
                <input type="text" id="set-pos-${t.id}" value="${t.position || ''}" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2 text-xs font-bold outline-none focus:ring-2 focus:ring-slate-300">
            </td>
            <td class="px-6 py-5">
                <input type="text" id="set-aca-${t.id}" value="${t.academic_status || ''}" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2 text-xs font-bold outline-none focus:ring-2 focus:ring-slate-300">
            </td>
            <td class="px-6 py-5">
                <input type="text" id="set-grp-${t.id}" value="${t.subject_group || ''}" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2 text-xs font-bold outline-none focus:ring-2 focus:ring-slate-300">
            </td>
            <td class="px-6 py-5 text-center">
                <label class="relative inline-flex items-center cursor-pointer gap-2" title="สิทธิ์นิเทศครูคนอื่นได้">
                    <input type="checkbox" id="set-eval-${t.id}" class="sr-only peer" ${t.is_evaluator == 1 ? 'checked' : ''}
                           onchange="toggleEvaluator(${t.id}, this.checked)">
                    <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-600 relative"></div>
                    <span id="eval-label-${t.id}" class="text-[10px] font-black uppercase tracking-widest ${t.is_evaluator == 1 ? 'text-indigo-600' : 'text-slate-300'}">${t.is_evaluator == 1 ? 'ผู้นิเทศ' : 'ครู'}</span>
                </label>
            </td>
            <td class="px-6 py-5 text-center">
                <button onclick="updateProfile(${t.id})" class="text-indigo-600 hover:text-indigo-800 transition-colors font-black text-[10px] uppercase tracking-widest">
                    <i class="bi bi-save-fill"></i> Save
                </button>
            </td>
        </tr>
    `).join('');
}

async function toggleEvaluator(id, checked) {
    const label = document.getElementById(`eval-label-${id}`);
    const checkbox = document.getElementById(`set-eval-${id}`);
    checkbox.disabled = true;

    try {
        const res = await fetch(BASE_URL + 'update_profile.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id, is_evaluator: checked ? 1 : 0 })
        });
        const json = await res.json();
        if (json.status === 'success') {
            // Update local data
            const t = teachers.find(x => x.id == id);
            t.is_evaluator = checked ? 1 : 0;
            label.textContent = checked ? 'ผู้นิเทศ' : 'ครู';
            label.className = `text-[10px] font-black uppercase tracking-widest ${checked ? 'text-indigo-600' : 'text-slate-300'}`;

            const Toast = Swal.mixin({ toast: true, position: 'top-end', showConfirmButton: false, timer: 2000 });
            Toast.fire({ icon: 'success', title: checked ? 'กำหนดเป็นผู้นิเทศแล้ว' : 'ยกเลิกสิทธิ์ผู้นิเทศแล้ว' });
        } else {
            // Revert checkbox on failure
            checkbox.checked = !checked;
            Swal.fire({ icon: 'error', title: 'เกิดข้อผิดพลาด', text: json.message });
        }
    } catch (e) {
        checkbox.checked = !checked;
    } finally {
        checkbox.disabled = false;
    }
}

async function updateProfile(id) {
    const pos = document.getElementById(`set-pos-${id}`).value;
    const aca = document.getElementById(`set-aca-${id}`).value;
    const grp = document.getElementById(`set-grp-${id}`).value;

    const btn = event.currentTarget;
    btn.innerHTML = '<i class="bi bi-hourglass-split"></i> Saving...';
    btn.disabled = true;

    try {
        const res = await fetch(BASE_URL + 'update_profile.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id, position: pos, academic_status: aca, subject_group: grp })
        });
        const json = await res.json();
        if (json.status === 'success') {
            // Update local copy
            const t = teachers.find(x => x.id == id);
            t.position = pos; t.academic_status = aca; t.subject_group = grp;
            
            btn.innerHTML = '<i class="bi bi-check-lg"></i> Saved!';
            setTimeout(() => {
                btn.innerHTML = '<i class="bi bi-save-fill"></i> Save';
                btn.disabled = false;
            }, 2000);
        }
    } catch (e) { btn.disabled = false; }
}

function getInterpretation(avg) {
    if (avg >= 4.50) return "ดีเยี่ยม";
    if (avg >= 3.50) return "ดีมาก";
    if (avg >= 2.50) return "ดี";
    if (avg >= 1.50) return "พอใช้";
    return "ปรับปรุง";
}

function getInterColor(avg) {
    if (avg >= 4.50) return "bg-emerald-50 text-emerald-600";
    if (avg >= 3.50) return "bg-blue-50 text-blue-600";
    if (avg >= 2.50) return "bg-amber-50 text-amber-600";
    if (avg >= 1.50) return "bg-orange-50 text-orange-600";
    return "bg-rose-50 text-rose-600";
}

async function showRecordDetails(id) {
    Swal.fire({ title: 'กำลังโหลด...', didOpen: () => Swal.showLoading(), allowOutsideClick: false });
    
    try {
        const res = await fetch(BASE_URL + 'get_record_details.php?id=' + id);
        const json = await res.json();
        Swal.close();
        
        if (json.status === 'success') {
            const r = json.record;
            document.getElementById('md-title').textContent = r.course_name;
            document.getElementById('md-code').textContent = r.course_code;
            document.getElementById('md-class').textContent = r.class_level;
            document.getElementById('md-date').textContent = new Date(r.observation_date).toLocaleDateString('th-TH');
            document.getElementById('md-avg').textContent = parseFloat(r.average_score).toFixed(2);
            document.getElementById('md-findings').textContent = r.findings || '-';
            document.getElementById('md-impressions').textContent = r.impressions || '-';
            document.getElementById('md-improvements').textContent = r.improvements || '-';
            document.getElementById('md-obs-name').textContent = r.observer_name;
            document.getElementById('md-obs-pos').textContent = r.observer_position || 'ไม่ระบุตำแหน่ง';
            
            // Render Scores list
            const scoreList = document.getElementById('md-score-list');
            scoreList.innerHTML = json.scores.map(s => `
                <div class="flex items-center justify-between p-3 bg-white border border-slate-100 rounded-xl shadow-sm">
                    <div class="flex items-center gap-3">
                        <span class="w-6 h-6 rounded-lg bg-slate-50 text-slate-400 text-[9px] font-black flex items-center justify-center italic">${(s.item_idx + 1).toString().padStart(2, '0')}</span>
                        <p class="text-[11px] font-bold text-slate-600 line-clamp-1">${ITEMS_DESC[s.item_idx]}</p>
                    </div>
                    <span class="w-8 h-8 rounded-lg flex items-center justify-center text-xs font-black ${getScoreColor(s.score)}">${s.score}</span>
                </div>
            `).join('');
            
            document.getElementById('modal-details').classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        } else {
            Swal.fire('ผิดพลาด', json.message, 'error');
        }
    } catch (e) { Swal.close(); Swal.fire('ผิดพลาด', 'ไม่สามารถดึงข้อมูลได้', 'error'); }
}

function hideRecordDetails() {
    document.getElementById('modal-details').classList.add('hidden');
    document.body.style.overflow = 'auto';
}

function getScoreColor(score) {
    if (score >= 5) return "bg-cyan-50 text-cyan-600";
    if (score >= 4) return "bg-emerald-50 text-emerald-600";
    if (score >= 3) return "bg-amber-50 text-amber-600";
    if (score >= 2) return "bg-orange-50 text-orange-600";
    return "bg-rose-50 text-rose-600";
}

async function loadTripleSummary() {
    const teacherId = document.getElementById('sel-teacher').value;
    if (!teacherId) {
        Swal.fire('แจ้งเตือน', 'กรุณาเลือกรายชื่อครูก่อน', 'warning');
        return;
    }

    Swal.fire({ title: 'กำลังสร้างรายงาน...', didOpen: () => Swal.showLoading(), allowOutsideClick: false });

    try {
        const res = await fetch(BASE_URL + 'get_triple_summary.php?teacher_id=' + teacherId);
        const json = await res.json();
        Swal.close();

        if (json.status === 'success') {
            const t = json.teacher;
            const evals = json.evaluations;
            
            // Create a print window or temporary element
            let html = `
                <div class="p-8 max-w-4xl mx-auto bg-white font-[Prompt]">
                    <div class="text-center mb-6">
                        <h1 class="text-base font-black leading-snug">สรุปรายงานผลการนิเทศการจัดการเรียนรู้เชิงรุก (Active Learning)</h1>
                        <h2 class="text-xs font-bold text-slate-600 mt-1">โรงเรียนละลมวิทยา สำนักงานเขตพื้นที่การศึกษามัธยมศึกษาศรีสะเกษ ยโสธร</h2>
                        <div class="mt-4 flex flex-col items-center gap-1">
                             <p class="text-sm font-bold">ข้อมูลผู้รับการนิเทศ: <span class="text-indigo-600">${t.firstname} ${t.lastname}</span></p>
                             <div class="flex gap-6 text-xs">
                                <span>ตำแหน่ง: ${t.position || '-'}</span>
                                <span>วิทยฐานะ: ${t.academic_status || '-'}</span>
                                <span>กลุ่มสาระฯ: ${t.subject_group || '-'}</span>
                             </div>
                        </div>
                    </div>

                    <div class="mb-4 flex justify-between items-end border-b-2 border-slate-900 pb-2">
                        <p class="text-[10px] font-bold uppercase tracking-widest text-slate-500">Supervision Itemized Comparison</p>
                        <p class="text-[10px] font-bold text-slate-500 italic">Page 1 of 1</p>
                    </div>

                    <table class="w-full border-collapse border border-slate-400 text-[11px] text-center">
                        <thead>
                            <tr class="bg-slate-100">
                                <th class="border border-slate-400 p-2 w-10">ข้อ</th>
                                <th class="border border-slate-400 p-2 text-left">รายการนิเทศ (Active Learning)</th>
                                ${evals.map((e, i) => `<th class="border border-slate-400 p-2">กก.${i+1}<br><span class="text-[8px] font-normal leading-tight">${e.observer_name.split(' ')[0]}</span></th>`).join('')}
                                <th class="border border-slate-400 p-2 bg-slate-200 font-bold">ค่าเฉลี่ย</th>
                            </tr>
                        </thead>
                        <tbody>
            `;

            for (let i = 0; i < 27; i++) {
                const s1 = evals[0]?.scores.find(s => s.item_idx == i)?.score || 0;
                const s2 = evals[1]?.scores.find(s => s.item_idx == i)?.score || 0;
                const s3 = evals[2]?.scores.find(s => s.item_idx == i)?.score || 0;
                const rowAvg = ((s1 + s2 + s3) / (evals.length)).toFixed(2);

                html += `
                    <tr>
                        <td class="border border-slate-300 p-2">${i+1}</td>
                        <td class="border border-slate-300 p-2 text-left">${ITEMS_DESC[i]}</td>
                        <td class="border border-slate-300 p-2 ${s1 == 0 ? 'text-slate-300':''}">${s1 || '-'}</td>
                        ${evals.length >= 2 ? `<td class="border border-slate-300 p-2 ${s2 == 0 ? 'text-slate-300':''}">${s2 || '-'}</td>` : ''}
                        ${evals.length >= 3 ? `<td class="border border-slate-300 p-2 ${s3 == 0 ? 'text-slate-300':''}">${s3 || '-'}</td>` : ''}
                        <td class="border border-slate-300 p-2 bg-slate-50 font-bold">${rowAvg}</td>
                    </tr>
                `;
            }

            const totalAvg = evals.reduce((acc, e) => acc + parseFloat(e.average_score), 0) / evals.length;

            html += `
                        </tbody>
                        <tfoot>
                            <tr class="bg-indigo-600 text-white font-black">
                                <td colspan="${evals.length + 2}" class="p-3 text-right text-sm">คะแนนเฉลี่ยสรุปรวมทั้ง 3 ท่าน</td>
                                <td class="p-3 text-lg italic">${totalAvg.toFixed(2)}</td>
                            </tr>
                        </tfoot>
                    </table>

                    <div class="mt-12 grid grid-cols-2 gap-y-12 gap-x-8 text-sm">
                        <div class="text-center pt-8">
                            <p class="mb-12">(ลงชื่อ)...........................................................</p>
                            <p class="font-bold">(${t.firstname} ${t.lastname})</p>
                            <p class="text-xs text-slate-500 mt-1">ผู้รับการนิเทศ</p>
                        </div>
                        <div class="text-center pt-8">
                            <p class="mb-12">(ลงชื่อ)...........................................................</p>
                            <p class="font-bold">(${evals[0]?.observer_name || '...........................................'})</p>
                            <p class="text-xs text-slate-500 mt-1">ผู้นิเทศคนที่ 1</p>
                        </div>
                        <div class="text-center pt-8">
                            <p class="mb-12">(ลงชื่อ)...........................................................</p>
                            <p class="font-bold">(${evals[1]?.observer_name || '...........................................'})</p>
                            <p class="text-xs text-slate-500 mt-1">ผู้นิเทศคนที่ 2</p>
                        </div>
                        <div class="text-center pt-8">
                            <p class="mb-12">(ลงชื่อ)...........................................................</p>
                            <p class="font-bold">(${evals[2]?.observer_name || '...........................................'})</p>
                            <p class="text-xs text-slate-500 mt-1">ผู้นิเทศคนที่ 3</p>
                        </div>
                    </div>
                </div>
            `;

            const printWin = window.open('', '_blank');
            if (!printWin) {
                Swal.fire('ตรวจพบการปิดกั้นหน้าต่าง', 'กรุณาอนุญาตให้แสดงหน้าต่างป๊อปอัพ (Popup) เพื่อพิมพ์รายงาน', 'warning');
                return;
            }

            printWin.document.write(`
                <html>
                <head>
                    <title>Report - Triple Summary</title>
                    <script src="https://cdn.tailwindcss.com"></` + `script>
                    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700;900&display=swap" rel="stylesheet">
                    <style>
                        body { font-family: 'Prompt', sans-serif; }
                        @media print { .no-print { display: none; } }
                    </style>
                </head>
                <body class="bg-white">
                    ${html}
                </body>
                </html>
            `);
            printWin.document.close();

            // Trigger print from parent context after a short delay
            setTimeout(() => {
                if (printWin) {
                    printWin.focus();
                    printWin.print();
                }
            }, 1000);

        } else {
            Swal.fire('ไม่พบข้อมูล', json.message, 'info');
        }
    } catch (e) {
        Swal.close();
        Swal.fire('ผิดพลาด', 'เกิดข้อผิดพลาดในการสร้างรายงาน', 'error');
        console.error(e);
    }
}
</script>

<?php require_once __DIR__ . '/components/layout_end.php'; ?>
