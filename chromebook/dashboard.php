<?php
session_start();
// Auth: super_admin or cb_admin
if (!isset($_SESSION['llw_role']) || !in_array($_SESSION['llw_role'], ['super_admin', 'cb_admin'])) {
    header('Location: ../login.php'); exit();
}

$pageTitle = 'Dashboard Chromebook';
$pageSubtitle = 'รายงานสถิติและวิเคราะห์ข้อมูลการใช้งานอุปกรณ์';
$activeSystem = 'chromebook';

require_once '../components/layout_start.php';
?>

<div class="flex flex-col gap-8">
    
    <!-- Period Filter & Actions -->
    <div class="flex flex-wrap justify-between items-center gap-4 bg-white p-6 rounded-[2rem] border border-slate-100 shadow-sm no-print">
        <div class="flex items-center gap-4">
            <div class="w-12 h-12 bg-blue-50 text-blue-600 rounded-2xl flex items-center justify-center text-xl shadow-sm">
                <i class="bi bi-filter-right"></i>
            </div>
            <select id="period-filter" class="bg-slate-50 border border-slate-200 rounded-2xl px-6 py-3 text-sm font-bold text-slate-700 outline-none focus:ring-2 focus:ring-blue-400 transition-all cursor-pointer" onchange="applyFilter()">
                <option value="all">ทั้งหมด</option>
                <option value="today">วันนี้</option>
                <option value="week">สัปดาห์นี้</option>
                <option value="month" selected>เดือนนี้</option>
            </select>
        </div>
        <div class="flex gap-3">
            <button onclick="exportCSV()" class="bg-emerald-600 text-white px-8 py-3 rounded-2xl font-black text-sm shadow-xl shadow-emerald-100 hover:bg-emerald-700 hover:scale-105 transition-all flex items-center gap-2">
                <i class="bi bi-file-earmark-excel"></i> ส่งออก CSV
            </button>
            <button onclick="window.print()" class="bg-slate-100 text-slate-600 px-8 py-3 rounded-2xl font-black text-sm hover:bg-slate-200 transition-all flex items-center gap-2">
                <i class="bi bi-printer"></i> พิมพ์
            </button>
        </div>
    </div>

    <!-- Stats Grid -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-6">
        <div class="bg-white p-8 rounded-[2rem] shadow-sm border border-slate-100 relative overflow-hidden group">
            <div class="absolute top-0 right-0 p-4 opacity-5 group-hover:scale-110 transition-all"><i class="bi bi-laptop text-6xl"></i></div>
            <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">อุปกรณ์ทั้งหมด</p>
            <h3 class="text-4xl font-black text-slate-800 tracking-tight" id="stat-total">-</h3>
        </div>
        <div class="bg-white p-8 rounded-[2rem] shadow-sm border border-slate-100 relative overflow-hidden group">
            <div class="absolute top-0 right-0 p-4 opacity-5 group-hover:scale-110 transition-all text-amber-600"><i class="bi bi-hand-index-thumb-fill text-6xl"></i></div>
            <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">กำลังยืมอยู่</p>
            <h3 class="text-4xl font-black text-slate-800 tracking-tight" id="stat-borrowed">-</h3>
        </div>
        <div class="bg-white p-8 rounded-[2rem] shadow-sm border border-slate-100 relative overflow-hidden group">
            <div class="absolute top-0 right-0 p-4 opacity-5 group-hover:scale-110 transition-all text-emerald-600"><i class="bi bi-check-circle-fill text-6xl"></i></div>
            <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">ว่างพร้อมใช้</p>
            <h3 class="text-4xl font-black text-slate-800 tracking-tight" id="stat-avail">-</h3>
        </div>
        <div class="bg-white p-8 rounded-[2rem] shadow-sm border border-slate-100 relative overflow-hidden group">
            <div class="absolute top-0 right-0 p-4 opacity-5 group-hover:scale-110 transition-all text-rose-600"><i class="bi bi-exclamation-triangle-fill text-6xl"></i></div>
            <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">ค้างคืน &gt;2 วัน</p>
            <h3 class="text-4xl font-black text-slate-800 tracking-tight" id="stat-overdue">-</h3>
        </div>
    </div>

    <!-- Charts Section -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <div class="lg:col-span-2 bg-white p-10 rounded-[32px] shadow-sm border border-slate-100">
            <div class="flex justify-between items-center mb-8">
                <h3 class="font-black text-slate-800 flex items-center gap-3"><i class="bi bi-bar-chart-fill text-blue-600"></i> อัตราการยืมตามห้องเรียน</h3>
                <span class="text-[10px] font-black text-slate-400 bg-slate-50 px-4 py-1.5 rounded-full uppercase tracking-widest" id="chart-period-label">ทั้งหมด</span>
            </div>
            <div class="relative h-[300px]"><canvas id="classChart"></canvas></div>
        </div>
        <div class="bg-white p-10 rounded-[32px] shadow-sm border border-slate-100">
            <h3 class="font-black text-slate-800 mb-8 flex items-center gap-3"><i class="bi bi-pie-chart-fill text-indigo-600"></i> สถานะภาพรวม</h3>
            <div class="relative h-[300px] flex justify-center"><canvas id="statusChart"></canvas></div>
        </div>
    </div>

    <!-- Tables row -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        <!-- Overdue Table -->
        <div class="bg-white rounded-[32px] shadow-sm border border-rose-100 overflow-hidden">
            <div class="px-10 py-6 border-b border-rose-50 bg-rose-50/30 flex items-center justify-between">
                <h3 class="font-black text-rose-700 flex items-center gap-3"><i class="bi bi-exclamation-octagon-fill"></i> ค้างส่งเกินกำหนด</h3>
                <span class="px-3 py-1 rounded-full bg-rose-100 text-rose-700 text-[10px] font-black uppercase tracking-widest" id="overdue-count-badge">0 รายการ</span>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50/50 text-[10px] font-black text-slate-400 uppercase tracking-widest border-b border-slate-100">
                        <tr>
                            <th class="px-8 py-4 text-left">ผู้ยืม</th>
                            <th class="px-6 py-4 text-left">เครื่อง</th>
                            <th class="px-6 py-4 text-center">ล่าช้า</th>
                        </tr>
                    </thead>
                    <tbody id="overdue-table" class="divide-y divide-slate-50 text-slate-600">
                        <tr><td colspan="3" class="text-center py-10 italic text-slate-400 font-bold">ไม่มีข้อมูลค้างส่งปัจจุบัน</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Leaderboard -->
        <div class="bg-white p-10 rounded-[32px] shadow-sm border border-slate-100">
            <h3 class="font-black text-slate-800 mb-8 flex items-center gap-3"><i class="bi bi-trophy-fill text-amber-500"></i> ห้องเรียนที่ยืมบ่อยที่สุด</h3>
            <div class="space-y-6" id="top-classes">
                <p class="text-center py-10 italic text-slate-400 font-bold">กำลังรวบรวมข้อมูล...</p>
            </div>
        </div>
    </div>
</div>

<script>
let ALL_LOGS = [], ALL_CBS = [], ALL_INS = [], ALL_TEACHERS = [], ALL_STUDENTS = [];
let classChart = null, statusChart = null;
const OVERDUE_DAYS = 2;

async function fetchAll() {
    try {
        const post = (sn) => fetch('api.php?action=getData', {
            method: 'POST', headers: {'Content-Type':'application/json'},
            body: JSON.stringify({action:'getData', payload:{sheetName:sn}})
        }).then(r => r.json());
        
        const [log, cbs, ins, teachers, students] = await Promise.all([
            post('BorrowLog'), post('Chromebooks'), post('Inspections'), post('Teachers'), post('Students')
        ]);
        
        if(log.success) ALL_LOGS = log.data;
        if(cbs.success) ALL_CBS = cbs.data;
        if(ins.success) ALL_INS = ins.data;
        if(teachers.success) ALL_TEACHERS = teachers.data;
        if(students.success) ALL_STUDENTS = students.data;
        
        applyFilter();
    } catch(e) {
        Swal.fire('Error', 'ไม่สามารถโหลดข้อมูลสถิติได้: ' + e.message, 'error');
    }
}

function applyFilter() {
    const period = document.getElementById('period-filter').value;
    const now = new Date();
    const filterLabels = {all:'ทั้งหมด', today:'วันนี้', week:'สัปดาห์นี้', month:'เดือนนี้'};
    
    let filteredLogs = ALL_LOGS;
    if (period !== 'all') {
        filteredLogs = ALL_LOGS.filter(r => {
            const d = new Date(r[8]);
            if (period === 'today') return d.toDateString() === now.toDateString();
            if (period === 'week') {
                const startOfWeek = new Date(now); startOfWeek.setDate(now.getDate() - now.getDay());
                return d >= startOfWeek;
            }
            if (period === 'month') return d.getMonth() === now.getMonth() && d.getFullYear() === now.getFullYear();
            return true;
        });
    }
    
    document.getElementById('chart-period-label').textContent = filterLabels[period];
    renderDashboard(filteredLogs);
}

function getName(row) {
    if (row[1] === 'Teacher') {
        const t = ALL_TEACHERS.find(x => String(x[0]) == String(row[2]));
        return t ? t[1] : row[2];
    }
    const s = ALL_STUDENTS.find(x => String(x[0]) == String(row[2]));
    return s ? s[1] : row[2];
}

function renderDashboard(logs) {
    const totalCBs = ALL_CBS.length;
    const activeLogs = ALL_LOGS.filter(r => r[7] === 'Borrowed');
    const borrowed = activeLogs.length;
    const available = totalCBs - borrowed;
    
    const now = new Date();
    const overdueLogs = activeLogs.filter(r => {
        const d = new Date(r[8]);
        return (now - d) / (1000 * 60 * 60 * 24) > OVERDUE_DAYS;
    });
    
    document.getElementById('stat-total').textContent = totalCBs;
    document.getElementById('stat-borrowed').textContent = borrowed;
    document.getElementById('stat-avail').textContent = available;
    document.getElementById('stat-overdue').textContent = overdueLogs.length;
    document.getElementById('overdue-count-badge').textContent = `${overdueLogs.length} รายการ`;
    
    // Overdue table
    if (overdueLogs.length === 0) {
        document.getElementById('overdue-table').innerHTML = `<tr><td colspan="3" class="text-center py-10 text-slate-400 font-bold italic"><i class="bi bi-check2-circle text-emerald-500 mr-2"></i>ไม่มีอุปกรณ์ค้างส่งในขณะนี้</td></tr>`;
    } else {
        document.getElementById('overdue-table').innerHTML = overdueLogs.map(r => {
            const dayCount = Math.floor((now - new Date(r[8])) / (1000 * 60 * 60 * 24));
            const urgency = dayCount > 7 ? 'text-rose-600 bg-rose-50' : 'text-amber-600 bg-amber-50';
            return `<tr>
                <td class="px-8 py-5 font-bold text-slate-700">${getName(r)}<div class="text-[10px] text-slate-400 uppercase tracking-widest font-black">${r[3]||r[1]}</div></td>
                <td class="px-6 py-5 font-mono font-bold text-slate-500 text-xs">${r[4]}<div class="text-[9px] text-slate-300 font-black">${r[5]}</div></td>
                <td class="px-6 py-5 text-center"><span class="px-3 py-1 rounded-lg text-[10px] font-black uppercase tracking-widest ${urgency}">${dayCount} วัน</span></td>
            </tr>`;
        }).join('');
    }
    
    // Chart 1
    const classCounts = {'Staff': 0, 'M.4': 0, 'M.5': 0, 'M.6': 0};
    logs.filter(r => r[7] === 'Borrowed').forEach(r => {
        if (r[1] === 'Teacher') classCounts['Staff']++;
        else if (String(r[3]).startsWith('ม.4')) classCounts['M.4']++;
        else if (String(r[3]).startsWith('ม.5')) classCounts['M.5']++;
        else if (String(r[3]).startsWith('ม.6')) classCounts['M.6']++;
    });
    
    if (classChart) classChart.destroy();
    classChart = new Chart(document.getElementById('classChart'), {
        type: 'bar',
        data: {
            labels: Object.keys(classCounts),
            datasets: [{ label: 'จำนวนการยืม', data: Object.values(classCounts), backgroundColor: ['#8b5cf6','#3b82f6','#06b6d4','#10b981'], borderRadius: 12 }]
        },
        options: { maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, grid: { display: false }, border: { display: false } }, x: { grid: { display: false }, border: { display: false } } } }
    });
    
    // Chart 2
    const damageCount = ALL_INS.filter(i => i[2] !== 'Normal').length;
    if (statusChart) statusChart.destroy();
    statusChart = new Chart(document.getElementById('statusChart'), {
        type: 'doughnut',
        data: {
            labels: ['ว่างพร้อมใช้', 'กำลังยืม', 'เสียหาย/สูญหาย'],
            datasets: [{ data: [available, borrowed, damageCount], backgroundColor: ['#10b981','#f59e0b','#f43f5e'], borderWidth: 0, hoverOffset: 15 }]
        },
        options: { maintainAspectRatio: false, cutout: '75%', plugins: { legend: { position: 'bottom', labels: { usePointStyle: true, pointStyle: 'circle', font: { family: 'Prompt', weight: 'bold', size: 10 } } } } }
    });
    
    // Leaderboard
    const classHistory = {};
    ALL_LOGS.filter(r => r[1] !== 'Teacher').forEach(r => {
        const c = r[3] || 'Unknown';
        classHistory[c] = (classHistory[c] || 0) + 1;
    });
    const sorted = Object.entries(classHistory).sort((a,b)=>b[1]-a[1]).slice(0, 5);
    document.getElementById('top-classes').innerHTML = sorted.length === 0 
        ? '<p class="text-center text-slate-400 py-10 font-bold italic">ยังไม่มีประวัติการยืมของนักเรียน</p>'
        : sorted.map((item, idx) => {
            const pct = Math.max(10, Math.round((item[1] / sorted[0][1]) * 100));
            const colorClass = ['bg-amber-400', 'bg-slate-300', 'bg-orange-300', 'bg-blue-300', 'bg-indigo-300'][idx] || 'bg-slate-200';
            return `<div>
                <div class="flex justify-between items-center mb-2">
                    <span class="text-sm font-bold text-slate-700">ห้อง ${item[0]}</span>
                    <span class="text-xs font-black text-slate-400 uppercase tracking-widest">${item[1]} ครั้ง</span>
                </div>
                <div class="w-full bg-slate-50 rounded-full h-3 overflow-hidden border border-slate-100 shadow-inner">
                    <div class="${colorClass} h-full rounded-full transition-all duration-1000" style="width:${pct}%"></div>
                </div>
            </div>`;
        }).join('');
}

function exportCSV() {
    const BOM = '\uFEFF';
    const headers = ['ลำดับ','ประเภทผู้ยืม','รหัสผู้ยืม','ชื่อผู้ยืม','ชั้นเรียน','รหัส Chromebook','Serial Number','สถานะ','วันที่ยืม'];
    const rows = ALL_LOGS.map((r, idx) => [
        idx + 1, r[1] === 'Teacher' ? 'ครู' : 'นักเรียน', r[2], getName(r), r[3] || '-', r[4], r[5],
        r[7] === 'Borrowed' ? 'ยืมอยู่' : 'คืนแล้ว', r[8] ? new Date(r[8]).toLocaleString('th-TH') : '-'
    ]);
    const csvContent = BOM + [headers, ...rows].map(row => row.map(cell => `"${String(cell).replace(/"/g, '""')}"`).join(',')).join('\n');
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a'); a.href = url;
    a.download = `Report_Chromebook_${new Date().toISOString().slice(0,10)}.csv`;
    a.click();
}

document.addEventListener('DOMContentLoaded', fetchAll);
</script>

<?php require_once '../components/layout_end.php'; ?>
