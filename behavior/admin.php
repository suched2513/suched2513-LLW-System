<?php
/**
 * behavior/admin.php — Admin Dashboard (สถิติ + พิมพ์รายงาน + สรุปคะแนน)
 * Roles: super_admin, wfh_admin
 */
session_start();
require_once __DIR__ . '/../config.php';

if (!isset($_SESSION['llw_role'])) {
    header('Location: ' . $base_path . '/login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit();
}
if (!in_array($_SESSION['llw_role'], ['super_admin', 'wfh_admin'])) {
    header('Location: ' . $base_path . '/login.php');
    exit();
}

$pageTitle    = 'Admin Dashboard — พฤติกรรม';
$pageSubtitle = 'แผงควบคุมผู้บริหาร';
$activeSystem = 'behavior';

require_once __DIR__ . '/../components/layout_start.php';
?>

<!-- ─── Date Selector ─── -->
<div class="flex flex-wrap justify-between items-center mb-6 gap-4">
    <h2 class="text-xl font-black text-slate-800">Administrator Dashboard</h2>
    <div class="flex items-center gap-2">
        <span class="text-sm font-bold text-slate-400">เลือกวันที่</span>
        <input type="date" id="adminStatDate"
            class="bg-white border border-slate-200 rounded-xl px-4 py-2 text-sm shadow-sm focus:ring-2 focus:ring-violet-500 outline-none transition-all cursor-pointer">
    </div>
</div>

<!-- ─── KPI Cards ─── -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
    <div class="bg-gradient-to-br from-emerald-500 to-teal-600 rounded-2xl p-6 text-white shadow-xl shadow-emerald-200/50 relative overflow-hidden">
        <p class="text-xs font-black uppercase tracking-wider opacity-75">นักเรียนทำความดี</p>
        <p class="text-4xl font-black mt-2" id="statPersonGood">0</p>
        <p class="text-xs opacity-75">คน (วันที่เลือก)</p>
        <i class="bi bi-emoji-smile-fill absolute -right-3 -bottom-5 text-6xl opacity-20 rotate-[-15deg]"></i>
    </div>
    <div class="bg-gradient-to-br from-rose-500 to-pink-600 rounded-2xl p-6 text-white shadow-xl shadow-rose-200/50 relative overflow-hidden">
        <p class="text-xs font-black uppercase tracking-wider opacity-75">นักเรียนทำความผิด</p>
        <p class="text-4xl font-black mt-2" id="statPersonBad">0</p>
        <p class="text-xs opacity-75">คน (วันที่เลือก)</p>
        <i class="bi bi-emoji-frown-fill absolute -right-3 -bottom-5 text-6xl opacity-20 rotate-[-15deg]"></i>
    </div>
    <div class="bg-gradient-to-br from-violet-600 to-indigo-700 rounded-2xl p-6 text-white shadow-xl shadow-violet-200/50 relative overflow-hidden">
        <p class="text-xs font-black uppercase tracking-wider opacity-75">รายการบันทึกรวม</p>
        <p class="text-4xl font-black mt-2" id="statDailyRecords">0</p>
        <p class="text-xs opacity-75">รายการ (วันที่เลือก)</p>
        <i class="bi bi-file-earmark-text-fill absolute -right-3 -bottom-5 text-6xl opacity-20 rotate-[-15deg]"></i>
    </div>
</div>

<!-- ─── Print Section ─── -->
<div class="bg-gradient-to-r from-slate-800 to-slate-700 rounded-2xl p-6 text-white shadow-xl mb-6">
    <div class="flex items-center gap-3 mb-4">
        <div class="w-10 h-10 bg-white/10 rounded-xl flex items-center justify-center">
            <i class="bi bi-printer-fill text-xl"></i>
        </div>
        <div>
            <h5 class="font-black">พิมพ์รายงาน / หนังสือแจ้ง</h5>
            <p class="text-xs text-white/50">สร้างเอกสารสำหรับครูที่ปรึกษาและผู้ปกครอง</p>
        </div>
    </div>
    <div class="grid grid-cols-1 md:grid-cols-6 gap-3 items-end">
        <div>
            <label class="text-xs font-bold text-white/50 uppercase tracking-wider mb-1 block">จากวันที่</label>
            <input type="date" id="printFromDate"
                class="w-full bg-white/10 border border-white/20 rounded-xl px-3 py-2 text-sm text-white focus:ring-2 focus:ring-violet-500 outline-none">
        </div>
        <div>
            <label class="text-xs font-bold text-white/50 uppercase tracking-wider mb-1 block">ถึงวันที่</label>
            <input type="date" id="printToDate"
                class="w-full bg-white/10 border border-white/20 rounded-xl px-3 py-2 text-sm text-white focus:ring-2 focus:ring-violet-500 outline-none">
        </div>
        <div>
            <label class="text-xs font-bold text-white/50 uppercase tracking-wider mb-1 block">ห้อง (เช่น ม.1/1)</label>
            <input type="text" id="printClass" placeholder="ระบุห้อง"
                class="w-full bg-white/10 border border-white/20 rounded-xl px-3 py-2 text-sm text-white placeholder-white/30 focus:ring-2 focus:ring-violet-500 outline-none">
        </div>
        <div>
            <label class="text-xs font-bold text-white/50 uppercase tracking-wider mb-1 block">ครูที่ปรึกษา</label>
            <input type="text" id="printHomeroom" placeholder="ชื่อครู"
                class="w-full bg-white/10 border border-white/20 rounded-xl px-3 py-2 text-sm text-white placeholder-white/30 focus:ring-2 focus:ring-violet-500 outline-none">
        </div>
        <div>
            <button onclick="handlePrintNormal()" class="w-full bg-violet-600 hover:bg-violet-700 text-white px-3 py-2 rounded-xl font-bold text-xs transition-all mb-1.5">
                <i class="bi bi-printer me-1"></i> พิมพ์ (ปกติ)
            </button>
            <button onclick="handlePrintWithSign()" class="w-full bg-white/10 hover:bg-white/20 border border-white/20 text-white px-3 py-2 rounded-xl font-bold text-xs transition-all">
                <i class="bi bi-pen me-1"></i> พิมพ์ (มีช่องเซ็น)
            </button>
        </div>
    </div>
</div>

<!-- ─── Summary Table ─── -->
<div class="bg-white rounded-2xl shadow-xl shadow-violet-100/50 p-6 border border-violet-100/30">
    <div class="flex flex-wrap justify-between items-center gap-3 mb-5">
        <h5 class="text-lg font-black text-slate-800 flex items-center gap-2">
            <i class="bi bi-bar-chart-fill text-violet-600"></i> สรุปคะแนนนักเรียนสะสม
        </h5>
        <div class="flex items-center gap-2">
            <select id="adminLevelFilter"
                class="bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500 outline-none transition-all">
                <option value="">ทุกระดับชั้น</option>
                <option value="ป.1">ป.1</option><option value="ป.2">ป.2</option><option value="ป.3">ป.3</option>
                <option value="ป.4">ป.4</option><option value="ป.5">ป.5</option><option value="ป.6">ป.6</option>
                <option value="ม.1">ม.1</option><option value="ม.2">ม.2</option><option value="ม.3">ม.3</option>
                <option value="ม.4">ม.4</option><option value="ม.5">ม.5</option><option value="ม.6">ม.6</option>
            </select>
            <button onclick="loadAdminSummary()" class="w-9 h-9 bg-slate-100 hover:bg-slate-200 rounded-xl flex items-center justify-center transition-all">
                <i class="bi bi-arrow-clockwise text-slate-500"></i>
            </button>
        </div>
    </div>

    <div class="rounded-xl border border-slate-100 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-4 py-3 text-xs font-black text-slate-400 uppercase tracking-wider text-left border-b border-slate-100">รหัส</th>
                        <th class="px-4 py-3 text-xs font-black text-slate-400 uppercase tracking-wider text-left border-b border-slate-100">ชื่อ-สกุล</th>
                        <th class="px-4 py-3 text-xs font-black text-slate-400 uppercase tracking-wider border-b border-slate-100">ห้อง</th>
                        <th class="px-4 py-3 text-xs font-black text-emerald-500 uppercase tracking-wider border-b border-slate-100 text-right">ดี</th>
                        <th class="px-4 py-3 text-xs font-black text-rose-500 uppercase tracking-wider border-b border-slate-100 text-right">ลบ</th>
                        <th class="px-4 py-3 text-xs font-black text-slate-400 uppercase tracking-wider border-b border-slate-100 text-right">สุทธิ</th>
                        <th class="px-4 py-3 text-xs font-black text-slate-400 uppercase tracking-wider border-b border-slate-100 text-center">ดู</th>
                    </tr>
                </thead>
                <tbody id="adminSummaryBody">
                    <tr><td colspan="7" class="px-4 py-12 text-center text-slate-400">Loading...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
window.BASE = '<?= rtrim(str_replace("/behavior", "", dirname($_SERVER["SCRIPT_NAME"])), "/") ?>';
const BASE_BEHAVIOR_SCORE = 100;
const THAI_MONTHS_FULL = ['มกราคม','กุมภาพันธ์','มีนาคม','เมษายน','พฤษภาคม','มิถุนายน','กรกฎาคม','สิงหาคม','กันยายน','ตุลาคม','พฤศจิกายน','ธันวาคม'];

const esc = s => { const d = document.createElement('div'); d.textContent = String(s); return d.innerHTML; };
const clamp = (n, min, max) => Math.min(max, Math.max(min, Number(n) || 0));

async function api(url) {
    const r = await fetch(window.BASE + url); return await r.json();
}
async function post(url, data) {
    const r = await fetch(window.BASE + url, { method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify(data) }); return await r.json();
}

document.addEventListener('DOMContentLoaded', () => {
    const today = new Date().toISOString().split('T')[0];
    document.getElementById('adminStatDate').value = today;
    document.getElementById('adminStatDate').onchange = loadDashboardStats;
    document.getElementById('adminLevelFilter').onchange = filterAdminTable;

    loadDashboardStats();
    loadAdminSummary();
});

async function loadDashboardStats() {
    const date = document.getElementById('adminStatDate').value;
    const res = await api('/behavior/api/get_daily_stats.php?date=' + date);
    if (res.status === 'success') {
        document.getElementById('statPersonGood').innerText = res.personGood.toLocaleString();
        document.getElementById('statPersonBad').innerText = res.personBad.toLocaleString();
        document.getElementById('statDailyRecords').innerText = res.dailyRecords.toLocaleString();
    }
}

async function loadAdminSummary() {
    const body = document.getElementById('adminSummaryBody');
    body.innerHTML = '<tr><td colspan="7" class="px-4 py-12 text-center"><div class="animate-spin w-8 h-8 border-4 border-violet-200 border-t-violet-600 rounded-full mx-auto"></div></td></tr>';

    const list = await api('/behavior/api/get_student_summary.php');
    body.innerHTML = '';

    if (!Array.isArray(list) || !list.length) {
        body.innerHTML = '<tr><td colspan="7" class="px-4 py-12 text-center text-slate-400">ไม่พบข้อมูล</td></tr>';
        return;
    }

    list.forEach(item => {
        const good = clamp(item.good, 0, 999999);
        const bad = clamp(item.bad, 0, BASE_BEHAVIOR_SCORE);
        const net = clamp(BASE_BEHAVIOR_SCORE - bad, 0, 999999);

        const tr = document.createElement('tr');
        tr.className = 'border-b border-slate-50 hover:bg-violet-50/30 transition-colors';
        tr.dataset.level = (item.level || '').toLowerCase();
        tr.innerHTML = `
            <td class="px-4 py-3 text-xs text-slate-400 font-mono">${esc(item.studentId)}</td>
            <td class="px-4 py-3 text-sm font-bold text-slate-700">${esc(item.name)}</td>
            <td class="px-4 py-3 text-center"><span class="px-2 py-0.5 bg-slate-100 rounded-lg text-xs font-bold">${esc(item.level)}/${esc(item.room)}</span></td>
            <td class="px-4 py-3 text-right font-black text-emerald-600">${good}</td>
            <td class="px-4 py-3 text-right font-black text-rose-600">${bad}</td>
            <td class="px-4 py-3 text-right font-black ${net < BASE_BEHAVIOR_SCORE ? 'text-rose-600' : 'text-emerald-600'}">${net}</td>
            <td class="px-4 py-3 text-center">
                <a href="${window.BASE}/behavior/dashboard.php?sid=${item.studentId}" class="w-8 h-8 bg-slate-100 hover:bg-violet-100 rounded-lg inline-flex items-center justify-center transition-all">
                    <i class="bi bi-eye text-slate-500"></i>
                </a>
            </td>
        `;
        body.appendChild(tr);
    });
}

function filterAdminTable() {
    const lvl = document.getElementById('adminLevelFilter').value.toLowerCase();
    document.querySelectorAll('#adminSummaryBody tr').forEach(tr => {
        const l = tr.dataset.level || '';
        tr.style.display = (!lvl || l === lvl) ? '' : 'none';
    });
}

// ─── Print Functions ───
function buildPrintHtml(records, meta, scoreMap, variant) {
    const today = new Date();
    const printDate = `${today.getDate()} ${THAI_MONTHS_FULL[today.getMonth()]} พ.ศ. ${today.getFullYear() + 543}`;

    let dateHeader = '';
    if (meta.from) {
        const d = new Date(meta.from);
        dateHeader = `ประจำเดือน ${THAI_MONTHS_FULL[d.getMonth()]} พ.ศ. ${d.getFullYear() + 543}`;
        if (meta.to) {
            const d2 = new Date(meta.to);
            if (d.getTime() !== d2.getTime()) {
                dateHeader = `ระหว่างวันที่ ${d.getDate()} ${THAI_MONTHS_FULL[d.getMonth()]} ${(d.getFullYear()+543).toString().slice(-2)} ถึง ${d2.getDate()} ${THAI_MONTHS_FULL[d2.getMonth()]} ${(d2.getFullYear()+543).toString().slice(-2)}`;
            }
        }
    }

    let rowsHtml = '', totalScore = 0, totalCount = 0;
    if (records && records.length) {
        records.forEach((r, i) => {
            const d = new Date(r.date);
            const ds = `${d.getDate()}/${d.getMonth()+1}/${(d.getFullYear()+543).toString().slice(-2)}`;
            const s = parseInt(r.score) || 0;
            totalScore += s; totalCount++;
            const key = `${r.studentName}||${r.level}||${r.room}`;
            const sum = scoreMap[key] || {};
            rowsHtml += `<tr>
                <td style="text-align:center">${i+1}</td>
                <td style="padding-left:4px;white-space:nowrap">${r.studentName||''}</td>
                <td style="text-align:center">${r.level||''}/${r.room||''}</td>
                <td style="text-align:center">${r.teacher||''}</td>
                <td style="text-align:center">${ds}</td>
                <td style="padding-left:4px;word-break:break-word">${r.activity||''}</td>
                <td style="text-align:center;font-weight:700">${s}</td>
                <td style="text-align:center">${sum.good??''}</td>
                <td style="text-align:center">${sum.bad??''}</td>
                <td style="text-align:center;font-weight:700">${sum.net??''}</td>
            </tr>`;
        });
    }
    for (let i = 0; i < Math.max(0, 18 - (records?.length||0)); i++) {
        rowsHtml += `<tr><td style="text-align:center;color:#ccc">-</td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td></tr>`;
    }
    rowsHtml += `<tr style="font-weight:700"><td colspan="6" style="text-align:right">รวม</td><td style="text-align:center">${totalScore}</td><td colspan="3" style="text-align:center">จำนวน ${totalCount} รายการ</td></tr>`;

    let signBlock = variant === 'multi' ? `
        <div style="display:flex;justify-content:space-between;margin-top:28px;font-size:11pt">
            <div style="width:32%;text-align:center">ผู้บันทึก<br><br><br>.............................................<br>(.............................................)<br>วันที่........../........../..........  </div>
            <div style="width:32%;text-align:center">หัวหน้างานระเบียบวินัย<br><br><br>.............................................<br>(.............................................)<br>วันที่........../........../..........  </div>
            <div style="width:32%;text-align:center">หัวหน้าฝ่ายบริหารกิจการนักเรียน<br><br><br>.............................................<br>(.............................................)<br>วันที่........../........../..........  </div>
        </div>
        <div style="display:flex;justify-content:center;margin-top:20px;font-size:11pt">
            <div style="width:45%;text-align:center">ผู้อำนวยการสถานศึกษา<br><br><br>.............................................<br>(.............................................)<br>วันที่........../........../..........  </div>
        </div>
    ` : `<div style="display:flex;justify-content:center;margin-top:28px;font-size:11pt"><div style="text-align:center">ครูที่ปรึกษา<br><br><br>.............................................<br>(${meta.homeroom || '.............................................'})<br>วันที่........../........../..........  </div></div>`;

    return `<!DOCTYPE html><html lang="th"><head><meta charset="UTF-8"><title>แบบบันทึกการตัดคะแนน</title>
<link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600;700&display=swap" rel="stylesheet">
<style>*{box-sizing:border-box}@page{size:A4 portrait;margin:10mm}body{font-family:"Sarabun",sans-serif;font-size:11pt;margin:0;color:#111}table{width:100%;border-collapse:collapse;margin-top:10px;font-size:10pt}th,td{border:.6pt solid #000;padding:2px 4px;vertical-align:top}th{text-align:center;font-weight:700}</style>
</head><body onload="window.print()"><div style="padding:6mm 8mm">
<div style="text-align:center"><div style="font-size:16pt;font-weight:700">แบบบันทึกการตัดคะแนนความประพฤติของนักเรียน</div>
<div style="font-size:11pt">ชั้น ${meta.cls || '-'} &nbsp; ครูที่ปรึกษา ${meta.homeroom || '-'}</div>
<div style="font-size:11pt;margin-top:2px">${dateHeader} &nbsp; (พิมพ์เมื่อ ${printDate})</div></div>
<table><thead><tr><th style="width:4%">ลำดับ</th><th style="width:18%">ชื่อ</th><th style="width:7%">ชั้น</th><th style="width:13%">ครูผู้บันทึก</th><th style="width:9%">วันที่</th><th>รายละเอียด</th><th style="width:6%">ครั้งนี้</th><th style="width:7%">ดีสะสม</th><th style="width:7%">ลบสะสม</th><th style="width:7%">สุทธิ</th></tr></thead><tbody>${rowsHtml}</tbody></table>
${signBlock}</div></body></html>`;
}

async function handlePrintNormal() { await doPrint('normal'); }
async function handlePrintWithSign() { await doPrint('multi'); }

async function doPrint(variant) {
    const from = document.getElementById('printFromDate').value;
    const to = document.getElementById('printToDate').value;
    const cls = document.getElementById('printClass').value.trim();
    const rm = document.getElementById('printHomeroom').value.trim();
    if (!from && !to) return Swal.fire('กรุณาระบุช่วงวันที่', '', 'warning');

    Swal.fire({ title: 'กำลังเตรียมข้อมูล...', didOpen: () => Swal.showLoading() });

    let url = '/behavior/api/get_records_for_print.php?';
    if (from) url += 'dateFrom=' + from + '&';
    if (to) url += 'dateTo=' + to + '&';
    if (cls) url += 'classText=' + encodeURIComponent(cls);

    const records = await api(url);
    const summaryList = await api('/behavior/api/get_student_summary.php');
    Swal.close();

    let finalClass = cls, finalRoom = rm;
    if (Array.isArray(records) && records.length) {
        if (!finalClass) finalClass = records[0].classInfo || `${records[0].level}/${records[0].room}`;
        if (!finalRoom) finalRoom = records[0].homeroom;
    }

    const scoreMap = {};
    (Array.isArray(summaryList) ? summaryList : []).forEach(i => {
        scoreMap[`${i.name}||${i.level}||${i.room}`] = {
            good: i.good, bad: i.bad, net: Math.max(0, BASE_BEHAVIOR_SCORE - i.bad)
        };
    });

    const html = buildPrintHtml(Array.isArray(records) ? records : [], { from, to, cls: finalClass, homeroom: finalRoom }, scoreMap, variant);
    const w = window.open('', '_blank'); w.document.open(); w.document.write(html); w.document.close();
}
</script>

<?php require_once __DIR__ . '/../components/layout_end.php'; ?>
