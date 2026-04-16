<?php
/**
 * assembly/admin.php — Admin Dashboard (ผู้บริหาร)
 * Roles: super_admin, wfh_admin
 */
session_start();
require_once __DIR__ . '/../config.php';

if (!isset($_SESSION['llw_role'])) {
    header('Location: ' . $base_path . '/login.php?redirect=' . urlencode($_SERVER['REQUEST_URI'])); exit();
}
if (!in_array($_SESSION['llw_role'], ['super_admin', 'wfh_admin'])) {
    header('Location: ' . $base_path . '/assembly/dashboard.php'); exit();
}

$pageTitle    = 'รายงานผู้บริหาร';
$pageSubtitle = 'สรุปการเข้าแถวและแต่งกายทั้งโรงเรียน';
$activeSystem = 'assembly';

require_once __DIR__ . '/../components/layout_start.php';
?>

<!-- ─── Tab Navigation ─── -->
<div class="mb-6">
    <div class="bg-white rounded-2xl shadow-xl shadow-rose-100/50 p-1.5 inline-flex flex-wrap gap-1 border border-rose-100/50">
        <button id="adm-tab-btn-overview" onclick="admShowTab('overview')"
            class="adm-tab-btn adm-tab-active px-5 py-2.5 rounded-xl text-sm font-bold transition-all flex items-center gap-2">
            <i class="bi bi-speedometer2"></i> ภาพรวมทั้งโรงเรียน
        </button>
        <button id="adm-tab-btn-detailed" onclick="admShowTab('detailed')"
            class="adm-tab-btn adm-tab-inactive px-5 py-2.5 rounded-xl text-sm font-bold transition-all flex items-center gap-2">
            <i class="bi bi-table"></i> รายงานเชิงลึก
        </button>
        <button id="adm-tab-btn-daily" onclick="admShowTab('daily')"
            class="adm-tab-btn adm-tab-inactive px-5 py-2.5 rounded-xl text-sm font-bold transition-all flex items-center gap-2">
            <i class="bi bi-calendar-day"></i> รายงานรายวัน
        </button>
    </div>
</div>

<!-- ─── TAB: OVERVIEW ─── -->
<div id="adm-tab-overview" class="adm-tab-content">
    <div class="bg-white rounded-2xl shadow-xl shadow-amber-100/50 p-6 mb-6 border border-amber-100/30">
        <div class="flex items-center gap-3 mb-5">
            <div class="w-10 h-10 bg-gradient-to-br from-amber-500 to-orange-500 rounded-xl flex items-center justify-center shadow-lg shadow-amber-200/50">
                <i class="bi bi-speedometer2 text-white text-lg"></i>
            </div>
            <h2 class="text-lg font-black text-slate-800">ภาพรวมทั้งโรงเรียน</h2>
        </div>

        <!-- Filters -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-5">
            <div>
                <label class="text-[10px] font-black text-slate-400 uppercase tracking-wider mb-1.5 block">เดือน</label>
                <select id="adm-month" class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-4 py-3 text-sm focus:ring-2 focus:ring-amber-400 outline-none transition-all">
                    <?php echo renderMonthOptions(true); ?>
                </select>
            </div>
            <div>
                <label class="text-[10px] font-black text-slate-400 uppercase tracking-wider mb-1.5 block">ชั้น</label>
                <select id="adm-grade" class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-4 py-3 text-sm focus:ring-2 focus:ring-amber-400 outline-none transition-all">
                    <option value="all">ทุกชั้น</option>
                </select>
            </div>
            <div>
                <label class="text-[10px] font-black text-slate-400 uppercase tracking-wider mb-1.5 block">ห้อง</label>
                <select id="adm-classroom" class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-4 py-3 text-sm focus:ring-2 focus:ring-amber-400 outline-none transition-all">
                    <option value="all">ทุกห้อง</option>
                </select>
            </div>
            <div class="flex items-end">
                <button onclick="loadAdminOverview()" class="w-full bg-gradient-to-r from-amber-500 to-orange-500 text-white px-4 py-3 rounded-2xl font-bold text-sm shadow-lg shadow-amber-200/50 hover:scale-[1.02] transition-all flex items-center justify-center gap-2">
                    <i class="bi bi-bar-chart"></i> แสดงข้อมูล
                </button>
            </div>
        </div>

        <!-- KPI Cards -->
        <div id="adm-kpi" class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6 hidden">
            <div class="bg-gradient-to-br from-emerald-500 to-teal-600 text-white rounded-2xl p-5 shadow-lg shadow-emerald-200/50">
                <p class="text-[10px] font-black uppercase tracking-wider opacity-80">การมาเฉลี่ย</p>
                <p id="kpi-present" class="text-4xl font-black mt-1">- %</p>
            </div>
            <div class="bg-gradient-to-br from-blue-500 to-indigo-600 text-white rounded-2xl p-5 shadow-lg shadow-blue-200/50">
                <p class="text-[10px] font-black uppercase tracking-wider opacity-80">แต่งกายถูกเฉลี่ย</p>
                <p id="kpi-uniform" class="text-4xl font-black mt-1">- %</p>
            </div>
            <div class="bg-gradient-to-br from-rose-500 to-pink-600 text-white rounded-2xl p-5 shadow-lg shadow-rose-200/50">
                <p class="text-[10px] font-black uppercase tracking-wider opacity-80">หมายเหตุทั้งหมด</p>
                <p id="kpi-notes" class="text-4xl font-black mt-1">-</p>
            </div>
        </div>

        <!-- Charts -->
        <div id="adm-charts" class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6 hidden">
            <div class="bg-slate-50 rounded-2xl p-5">
                <h4 class="text-sm font-black text-slate-600 mb-3">ร้อยละการมาเรียน (รายห้อง)</h4>
                <canvas id="adm-bar-chart" height="220"></canvas>
            </div>
            <div class="bg-slate-50 rounded-2xl p-5">
                <h4 class="text-sm font-black text-slate-600 mb-3">ค่าเฉลี่ยแต่งกายถูก % (รายห้อง)</h4>
                <canvas id="adm-uni-chart" height="220"></canvas>
            </div>
        </div>

        <!-- Table -->
        <div id="adm-table-wrap" class="hidden">
            <div class="rounded-2xl border border-slate-100 overflow-hidden mb-4">
                <div class="overflow-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-4 py-3 text-[10px] font-black text-slate-400 uppercase tracking-wider text-left border-b">ห้อง</th>
                                <th class="px-4 py-3 text-[10px] font-black text-slate-400 uppercase tracking-wider text-left border-b">ครูที่ปรึกษา</th>
                                <th class="px-4 py-3 text-[10px] font-black text-slate-400 uppercase tracking-wider border-b text-green-600">การมา %</th>
                                <th class="px-4 py-3 text-[10px] font-black text-slate-400 uppercase tracking-wider border-b text-blue-500">แต่งกายถูก %</th>
                                <th class="px-4 py-3 text-[10px] font-black text-slate-400 uppercase tracking-wider border-b text-rose-500">หมายเหตุ</th>
                            </tr>
                        </thead>
                        <tbody id="adm-table"></tbody>
                    </table>
                </div>
            </div>
            <div class="flex justify-end gap-3">
                <button onclick="highlightLow()" class="bg-rose-500 text-white px-5 py-2.5 rounded-2xl font-bold text-sm shadow-lg shadow-rose-200/50 hover:bg-rose-600 transition-all flex items-center gap-2">
                    <i class="bi bi-exclamation-triangle-fill"></i> ห้องคะแนนต่ำสุด
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ─── TAB: DETAILED ─── -->
<div id="adm-tab-detailed" class="adm-tab-content hidden">
    <div class="bg-white rounded-2xl shadow-xl shadow-indigo-100/50 p-6 mb-6 border border-indigo-100/30">
        <div class="flex items-center gap-3 mb-5">
            <div class="w-10 h-10 bg-gradient-to-br from-indigo-500 to-purple-600 rounded-xl flex items-center justify-center shadow-lg shadow-indigo-200/50">
                <i class="bi bi-table text-white text-lg"></i>
            </div>
            <h2 class="text-lg font-black text-slate-800">รายงานเชิงลึก (รายวัน)</h2>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-5">
            <div>
                <label class="text-[10px] font-black text-slate-400 uppercase tracking-wider mb-1.5 block">เดือน</label>
                <select id="det-month" class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-4 py-3 text-sm focus:ring-2 focus:ring-indigo-400 outline-none transition-all">
                    <?php echo renderMonthOptions(true); ?>
                </select>
            </div>
            <div>
                <label class="text-[10px] font-black text-slate-400 uppercase tracking-wider mb-1.5 block">ชั้น</label>
                <select id="det-level" class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-4 py-3 text-sm focus:ring-2 focus:ring-indigo-400 outline-none transition-all">
                    <option value="all">ทุกชั้น</option>
                    <option value="ม.1">ม.1</option>
                    <option value="ม.2">ม.2</option>
                    <option value="ม.3">ม.3</option>
                    <option value="ม.4">ม.4</option>
                    <option value="ม.5">ม.5</option>
                    <option value="ม.6">ม.6</option>
                </select>
            </div>
            <div>
                <label class="text-[10px] font-black text-slate-400 uppercase tracking-wider mb-1.5 block">ห้อง</label>
                <select id="det-classroom" class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-4 py-3 text-sm focus:ring-2 focus:ring-indigo-400 outline-none transition-all">
                    <option value="all">ทุกห้อง</option>
                </select>
            </div>
            <div class="flex items-end">
                <button onclick="loadDetailed()" class="w-full bg-gradient-to-r from-indigo-500 to-purple-600 text-white px-4 py-3 rounded-2xl font-bold text-sm shadow-lg shadow-indigo-200/50 hover:scale-[1.02] transition-all flex items-center justify-center gap-2">
                    <i class="bi bi-search"></i> แสดงข้อมูล
                </button>
            </div>
        </div>
        <div id="det-table-wrap" class="rounded-2xl border border-slate-100 overflow-hidden">
            <div class="overflow-auto max-h-[500px]">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 sticky top-0">
                        <tr>
                            <th class="px-3 py-3 text-[10px] font-black text-slate-400 uppercase tracking-wider text-left border-b">วันที่</th>
                            <th class="px-3 py-3 text-[10px] font-black text-slate-400 uppercase tracking-wider text-left border-b">ห้อง</th>
                            <th class="px-3 py-3 text-[10px] font-black text-slate-400 uppercase tracking-wider border-b text-emerald-600">มา</th>
                            <th class="px-3 py-3 text-[10px] font-black text-slate-400 uppercase tracking-wider border-b text-rose-500">ขาด</th>
                            <th class="px-3 py-3 text-[10px] font-black text-slate-400 uppercase tracking-wider border-b text-amber-500">ลา</th>
                            <th class="px-3 py-3 text-[10px] font-black text-slate-400 uppercase tracking-wider border-b text-purple-500">โดด</th>
                            <th class="px-3 py-3 text-[10px] font-black text-slate-400 uppercase tracking-wider border-b text-blue-500">แต่งกาย %</th>
                            <th class="px-3 py-3 text-[10px] font-black text-slate-400 uppercase tracking-wider text-left border-b">หมายเหตุ</th>
                            <th class="px-3 py-3 text-[10px] font-black text-slate-400 uppercase tracking-wider border-b text-center">ปริ้น</th>
                        </tr>
                    </thead>
                    <tbody id="det-table">
                        <tr><td colspan="8" class="px-4 py-10 text-center text-slate-400">กรอกตัวกรองและกด "แสดงข้อมูล"</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- ─── TAB: DAILY ─── -->
<div id="adm-tab-daily" class="adm-tab-content hidden">
    <div class="bg-white rounded-2xl shadow-xl shadow-cyan-100/50 p-6 mb-6 border border-cyan-100/30">
        <div class="flex items-center gap-3 mb-5">
            <div class="w-10 h-10 bg-gradient-to-br from-cyan-500 to-blue-600 rounded-xl flex items-center justify-center shadow-lg shadow-cyan-200/50">
                <i class="bi bi-calendar-day text-white text-lg"></i>
            </div>
            <h2 class="text-lg font-black text-slate-800">รายงานการมาเข้าแถวและกลับบ้าน (รายวัน)</h2>
        </div>
        <div class="flex items-end gap-4 mb-5">
            <div>
                <label class="text-[10px] font-black text-slate-400 uppercase tracking-wider mb-1.5 block">เลือกวันที่</label>
                <input id="daily-date" type="date" value="<?= date('Y-m-d') ?>" class="bg-slate-50 border border-slate-200 rounded-2xl px-4 py-3 text-sm focus:ring-2 focus:ring-cyan-400 outline-none transition-all">
            </div>
            <button onclick="loadDailySummary()" class="bg-gradient-to-r from-cyan-500 to-blue-600 text-white px-5 py-3 rounded-2xl font-bold text-sm shadow-lg shadow-cyan-200/50 hover:scale-[1.02] transition-all flex items-center gap-2">
                <i class="bi bi-arrow-clockwise"></i> โหลดข้อมูล
            </button>
        </div>

        <!-- KPI totals -->
        <div id="daily-kpis" class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-5 hidden">
            <div class="bg-gradient-to-br from-emerald-500 to-teal-600 text-white rounded-2xl p-4 text-center shadow-lg shadow-emerald-200/50">
                <p class="text-[10px] font-black uppercase tracking-wider opacity-80">เช้า: มา</p>
                <p id="daily-m-present" class="text-3xl font-black mt-1">0</p>
            </div>
            <div class="bg-gradient-to-br from-rose-500 to-pink-600 text-white rounded-2xl p-4 text-center shadow-lg shadow-rose-200/50">
                <p class="text-[10px] font-black uppercase tracking-wider opacity-80">เช้า: ขาด</p>
                <p id="daily-m-absent" class="text-3xl font-black mt-1">0</p>
            </div>
            <div class="bg-gradient-to-br from-blue-500 to-indigo-600 text-white rounded-2xl p-4 text-center shadow-lg shadow-blue-200/50">
                <p class="text-[10px] font-black uppercase tracking-wider opacity-80">เย็น: กลับบ้าน</p>
                <p id="daily-e-present" class="text-3xl font-black mt-1">0</p>
            </div>
            <div class="bg-gradient-to-br from-amber-500 to-orange-500 text-white rounded-2xl p-4 text-center shadow-lg shadow-amber-200/50">
                <p class="text-[10px] font-black uppercase tracking-wider opacity-80">เย็น: ไม่กลับ</p>
                <p id="daily-e-absent" class="text-3xl font-black mt-1">0</p>
            </div>
        </div>

        <div class="rounded-2xl border border-slate-100 overflow-hidden mb-6">
            <div class="overflow-auto max-h-96">
                <table class="w-full text-sm text-center">
                    <thead class="bg-slate-50 sticky top-0">
                        <tr>
                            <th class="px-3 py-3 text-[10px] font-black text-slate-400 uppercase tracking-wider text-left border-b">ห้อง</th>
                            <th class="px-3 py-3 text-[10px] font-black text-slate-400 uppercase tracking-wider text-left border-b">ครูที่ปรึกษา</th>
                            <th class="px-3 py-3 text-[10px] font-black text-slate-400 uppercase tracking-wider border-b text-emerald-600">เช้า: มา</th>
                            <th class="px-3 py-3 text-[10px] font-black text-slate-400 uppercase tracking-wider border-b text-rose-500">เช้า: ขาด</th>
                            <th class="px-3 py-3 text-[10px] font-black text-slate-400 uppercase tracking-wider border-b text-blue-500">เย็น: กลับ</th>
                            <th class="px-3 py-3 text-[10px] font-black text-slate-400 uppercase tracking-wider border-b text-amber-500">เย็น: ไม่กลับ</th>
                            <th class="px-3 py-3 text-[10px] font-black text-slate-400 uppercase tracking-wider border-b text-slate-400 text-center">ปริ้น</th>
                        </tr>
                    </thead>
                    <tbody id="daily-table">
                        <tr><td colspan="6" class="px-4 py-10 text-center text-slate-400"><i class="bi bi-calendar-day text-3xl block mb-2 opacity-30"></i>เลือกวันที่และกด "โหลดข้อมูล"</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="bg-slate-50 rounded-2xl p-5">
            <h4 class="text-sm font-black text-slate-600 mb-3">กราฟเปรียบเทียบรายห้อง</h4>
            <canvas id="daily-chart" height="120"></canvas>
        </div>
    </div>
</div>

<style>
.adm-tab-active   { background: linear-gradient(135deg, #f59e0b, #f97316); color: white; box-shadow: 0 4px 15px rgba(245,158,11,0.3); }
.adm-tab-inactive { background: transparent; color: #64748b; }
.adm-tab-inactive:hover { background: #f8fafc; color: #1e293b; }
</style>

<script>
// ─── Global Configuration ───
window.BASE = '<?= $base_path ?>';
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

document.addEventListener('DOMContentLoaded', () => {
    console.log("Assembly Admin Ready. BASE:", window.BASE);
    try {
        if (typeof Chart !== 'undefined' && typeof ChartDataLabels !== 'undefined') {
            Chart.register(ChartDataLabels);
        }
    } catch (e) {
        console.warn("Chart registration error:", e);
    }
    initRooms();
});

function admShowTab(name) {
    document.querySelectorAll('.adm-tab-content').forEach(t => t.classList.add('hidden'));
    document.querySelectorAll('.adm-tab-btn').forEach(b => { b.classList.remove('adm-tab-active'); b.classList.add('adm-tab-inactive'); });
    document.getElementById('adm-tab-' + name).classList.remove('hidden');
    document.getElementById('adm-tab-btn-' + name).classList.remove('adm-tab-inactive');
    document.getElementById('adm-tab-btn-' + name).classList.add('adm-tab-active');
}

// ─── Init rooms ───
async function initRooms() {
    const res = await api('/assembly/api/get_rooms.php');
    if (res.status !== 'success') return;
    const admSelector = document.getElementById('adm-classroom');
    const detSelector = document.getElementById('det-classroom');
    res.classrooms.forEach(c => {
        admSelector.innerHTML += `<option value="${c}">${c}</option>`;
        detSelector.innerHTML += `<option value="${c}">${c}</option>`;
    });
    const gradeSelector = document.getElementById('adm-grade');
    res.grades.forEach(g => { gradeSelector.innerHTML += `<option value="${g}">${g}</option>`; });
}
// ─── OVERVIEW ───
let admBarChart = null, admUniChart = null;

async function loadAdminOverview() {
    const month     = document.getElementById('adm-month').value || 'all';
    const grade     = document.getElementById('adm-grade').value || 'all';
    const classroom = document.getElementById('adm-classroom').value || 'all';

    Swal.fire({ title: 'กำลังโหลด...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
    const res = await api(`/assembly/api/get_admin_summary.php?month=${month}&grade=${encodeURIComponent(grade)}&classroom=${encodeURIComponent(classroom)}`);
    Swal.close();
    if (res.status !== 'success') { Swal.fire('ผิดพลาด', res.message, 'error'); return; }

    document.getElementById('adm-kpi').classList.remove('hidden');
    document.getElementById('adm-charts').classList.remove('hidden');
    document.getElementById('adm-table-wrap').classList.remove('hidden');

    document.getElementById('kpi-present').textContent = (res.totals.presentPct ?? 0) + ' %';
    document.getElementById('kpi-uniform').textContent = (res.totals.uniformPct ?? 0) + ' %';
    document.getElementById('kpi-notes').textContent   = res.totals.noteCount ?? 0;

    const labels     = res.rooms.map(r => r.classroom);
    const presentData= res.rooms.map(r => r.presentPct);
    const uniformData= res.rooms.map(r => r.uniformPct);

    const barCtx = document.getElementById('adm-bar-chart').getContext('2d');
    if (admBarChart) admBarChart.destroy();
    admBarChart = new Chart(barCtx, {
        type: 'bar',
        data: { labels, datasets: [{ label: 'การมา %', data: presentData, backgroundColor: '#10b981', borderRadius: 8 }] },
        options: { scales: { y: { beginAtZero: true, max: 100 } }, plugins: { legend: { display: false }, datalabels: { anchor: 'end', align: 'top', formatter: v => v + '%', font: { weight: 'bold', size: 10 } } } }
    });

    const uniCtx = document.getElementById('adm-uni-chart').getContext('2d');
    if (admUniChart) admUniChart.destroy();
    admUniChart = new Chart(uniCtx, {
        type: 'bar',
        data: { labels, datasets: [{ label: 'แต่งกายถูก %', data: uniformData, backgroundColor: '#3b82f6', borderRadius: 8 }] },
        options: { scales: { y: { beginAtZero: true, max: 100 } }, plugins: { legend: { display: false }, datalabels: { anchor: 'end', align: 'top', formatter: v => v + '%', font: { weight: 'bold', size: 10 } } } }
    });

    document.getElementById('adm-table').innerHTML = res.rooms.map(r => `
        <tr class="border-b border-slate-50 hover:bg-amber-50/30 transition-colors">
            <td class="px-4 py-3 font-bold text-slate-700">${esc(r.classroom)}</td>
            <td class="px-4 py-3 text-slate-500">${esc(r.teacher || '-')}</td>
            <td class="px-4 py-3 text-center font-bold ${r.presentPct >= 80 ? 'text-emerald-600' : 'text-rose-500'}">${r.presentPct}%</td>
            <td class="px-4 py-3 text-center font-bold ${r.uniformPct >= 80 ? 'text-blue-600' : 'text-amber-500'}">${r.uniformPct}%</td>
            <td class="px-4 py-3 text-center text-rose-500 font-bold">${r.noteCount}</td>
        </tr>
    `).join('') || `<tr><td colspan="5" class="px-4 py-8 text-center text-slate-400">ไม่พบข้อมูล</td></tr>`;
}

function highlightLow() {
    const rows = Array.from(document.querySelectorAll('#adm-table tr'));
    if (!rows.length) { Swal.fire('ยังไม่มีข้อมูล','','info'); return; }
    let minVal = Infinity, minIdx = -1;
    rows.forEach((tr, i) => {
        const v = parseFloat(tr.children[2]?.textContent);
        if (!isNaN(v) && v < minVal) { minVal = v; minIdx = i; }
    });
    rows.forEach((tr, i) => tr.style.background = i === minIdx ? '#fff1f2' : '');
    if (minIdx >= 0) {
        Swal.fire('ห้องคะแนนต่ำสุด', `ห้อง ${rows[minIdx].children[0].textContent} — การมา ${rows[minIdx].children[2].textContent}`, 'warning');
    }
}

// ─── DETAILED ───
async function loadDetailed() {
    const month     = document.getElementById('det-month').value || 'all';
    const level     = document.getElementById('det-level').value || 'all';
    const classroom = document.getElementById('det-classroom').value || 'all';

    Swal.fire({ title: 'กำลังโหลด...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
    const res = await api(`/assembly/api/get_admin_detailed.php?month=${month}&level=${encodeURIComponent(level)}&classroom=${encodeURIComponent(classroom)}`);
    Swal.close();
    if (res.status !== 'success') { Swal.fire('ผิดพลาด', res.message, 'error'); return; }

    document.getElementById('det-table').innerHTML = res.data.map(r => `
        <tr class="border-b border-slate-50 hover:bg-indigo-50/30 transition-colors">
            <td class="px-3 py-2.5 text-xs text-slate-500 text-left">${r.date}</td>
            <td class="px-3 py-2.5 font-bold text-slate-700 text-left">${esc(r.classroom)}</td>
            <td class="px-3 py-2.5 text-center font-bold text-emerald-600">${r.present}</td>
            <td class="px-3 py-2.5 text-center font-bold text-rose-500">${r.absent}</td>
            <td class="px-3 py-2.5 text-center font-bold text-amber-500">${r.leave}</td>
            <td class="px-3 py-2.5 text-center font-bold text-purple-500">${r.skip}</td>
            <td class="px-3 py-2.5 text-center font-bold ${r.uniformPct >= 80 ? 'text-blue-600' : 'text-amber-500'}">${r.uniformPct}%</td>
            <td class="px-3 py-2.5 text-xs text-slate-400 text-left">${esc(r.note || '')}</td>
            <td class="px-3 py-2.5 text-center">
                <button onclick="printRowReport('${esc(r.classroom)}', '${r.date}')" class="text-cyan-500 hover:text-cyan-700 transition-all">
                    <i class="bi bi-printer"></i>
                </button>
            </td>
        </tr>
    `).join('') || `<tr><td colspan="8" class="px-4 py-8 text-center text-slate-400">ไม่พบข้อมูล</td></tr>`;
}

// ─── DAILY ───
let dailyChart = null;

async function loadDailySummary() {
    const date = document.getElementById('daily-date').value;
    if (!date) { Swal.fire('กรุณาเลือกวันที่','','warning'); return; }

    Swal.fire({ title: 'กำลังโหลด...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
    const res = await api(`/assembly/api/get_daily_summary.php?date=${date}`);
    Swal.close();
    if (res.status !== 'success') { Swal.fire('ผิดพลาด', res.message, 'error'); return; }

    const data = res.data;
    document.getElementById('daily-kpis').classList.remove('hidden');

    let mPresent = 0, mAbsent = 0, ePresent = 0, eAbsent = 0;
    data.forEach(r => {
        mPresent += r.morning.present;
        mAbsent  += r.morning.absent + r.morning.skip;
        ePresent += r.evening.present;
        eAbsent  += r.evening.absent + r.evening.skip;
    });
    document.getElementById('daily-m-present').textContent = mPresent;
    document.getElementById('daily-m-absent').textContent  = mAbsent;
    document.getElementById('daily-e-present').textContent = ePresent;
    document.getElementById('daily-e-absent').textContent  = eAbsent;

    document.getElementById('daily-table').innerHTML = data.map(r => `
        <tr class="border-b border-slate-50 hover:bg-cyan-50/30 transition-colors">
            <td class="px-3 py-2.5 font-bold text-slate-700 text-left">${esc(r.room)}</td>
            <td class="px-3 py-2.5 text-slate-500 text-left text-xs">${esc(r.advisor)}</td>
            <td class="px-3 py-2.5 font-bold text-emerald-600">${r.morning.present}</td>
            <td class="px-3 py-2.5 font-bold text-rose-500">${r.morning.absent + r.morning.skip}</td>
            <td class="px-3 py-2.5 font-bold text-blue-600">${r.evening.present}</td>
            <td class="px-3 py-2.5 font-bold text-amber-500">${r.evening.absent + r.evening.skip}</td>
            <td class="px-3 py-2.5">
                <button onclick="printRoomReport('${esc(r.room)}')" class="text-cyan-500 hover:text-cyan-700 transition-all">
                    <i class="bi bi-printer-fill"></i>
                </button>
            </td>
        </tr>
    `).join('') || `<tr><td colspan="6" class="px-4 py-8 text-center text-slate-400">ไม่พบข้อมูลวันนี้</td></tr>`;

    // Chart
    const ctx = document.getElementById('daily-chart').getContext('2d');
    if (dailyChart) dailyChart.destroy();
    dailyChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: data.map(r => r.room),
            datasets: [
                { label: 'เช้า: มาเข้าแถว', data: data.map(r => r.morning.present), backgroundColor: '#10b981', borderRadius: 6 },
                { label: 'เย็น: กลับบ้าน',  data: data.map(r => r.evening.present), backgroundColor: '#3b82f6', borderRadius: 6 }
            ]
        },
        options: { responsive: true, plugins: { legend: { position: 'bottom' }, datalabels: { display: false } }, scales: { y: { beginAtZero: true } } }
    });
}

function printRoomReport(room) {
    const date = document.getElementById('daily-date').value;
    if (!date) { Swal.fire('กรุณาเลือกวันที่','','warning'); return; }
    window.open(window.BASE + `/assembly/report_print.php?classroom=${encodeURIComponent(room)}&date=${date}`, '_blank');
}

function printRowReport(room, date) {
    window.open(window.BASE + `/assembly/report_print.php?classroom=${encodeURIComponent(room)}&date=${date}`, '_blank');
}

function esc(str) {
    const d = document.createElement('div'); d.textContent = String(str); return d.innerHTML;
}
</script>

<?php
require_once __DIR__ . '/../components/layout_end.php';

function renderMonthOptions(bool $includeAll = false): string {
    $months = ['01'=>'มกราคม','02'=>'กุมภาพันธ์','03'=>'มีนาคม','04'=>'เมษายน',
               '05'=>'พฤษภาคม','06'=>'มิถุนายน','07'=>'กรกฎาคม','08'=>'สิงหาคม',
               '09'=>'กันยายน','10'=>'ตุลาคม','11'=>'พฤศจิกายน','12'=>'ธันวาคม'];
    $html    = $includeAll ? '<option value="all">ทุกเดือน</option>' : '<option value="">เลือกเดือน</option>';
    $current = date('m');
    foreach ($months as $k => $v) {
        $html .= "<option value=\"$k\" " . ($k === $current ? 'selected' : '') . ">$v</option>";
    }
    return $html;
}
?>
