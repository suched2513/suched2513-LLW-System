<?php
/**
 * assembly/report_print.php — หน้าปริ้นรายงานการเข้าแถวรายห้อง
 * GET ?classroom=...&date=...
 */
session_start();
require_once __DIR__ . '/../config.php';

if (!isset($_SESSION['llw_role'])) {
    header('Location: ' . $base_path . '/login.php'); exit();
}

$room = $_GET['classroom'] ?? '';
$date = $_GET['date'] ?? date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รายงานประจำวัน - <?= htmlspecialchars($room) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;600;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body { font-family: 'Prompt', sans-serif; background: #f1f5f9; color: #1e293b; }
        @media print {
            body { background: white; padding: 0; margin: 0; }
            .no-print { display: none !important; }
            .print-area { padding: 0 !important; margin: 0 !important; width: 100% !important; max-width: none !important; border: none !important; box-shadow: none !important; }
            .page-break { page-break-after: always; }
        }
        .report-header { background: linear-gradient(135deg, #0ea5e9, #0284c7); }
        .sidebar { background: #06b6d4; }
        .card-teal { background: #ecfeff; border-left: 4px solid #06b6d4; }
    </style>
</head>
<body class="p-4 md:p-8">

    <!-- ─── Print Controls ─── -->
    <div class="max-w-5xl mx-auto mb-6 no-print flex justify-between items-center">
        <a href="javascript:window.close()" class="text-slate-500 font-bold flex items-center gap-2 hover:text-slate-800 transition-all">
            <i class="bi bi-arrow-left"></i> กลับ
        </a>
        <button onclick="window.print()" class="bg-blue-600 text-white px-6 py-3 rounded-2xl font-bold shadow-lg hover:bg-blue-700 hover:scale-[1.02] transition-all flex items-center gap-2">
            <i class="bi bi-printer-fill"></i> ปริ้นรายงาน
        </button>
    </div>

    <!-- ─── Report Content ─── -->
    <div id="report-container" class="max-w-5xl mx-auto bg-white rounded-[2rem] shadow-2xl overflow-hidden border border-slate-100 print-area flex flex-col md:flex-row min-h-[1000px]">
        
        <!-- Left Sidebar (Summary) -->
        <aside class="w-full md:w-72 sidebar text-white p-8 flex flex-col">
            <div class="mb-8">
                <h1 id="room-name" class="text-4xl font-black text-center mb-2">...</h1>
                <div class="w-16 h-1 bg-white/30 mx-auto rounded-full"></div>
            </div>

            <!-- Stats Block -->
            <div class="space-y-4 mb-10">
                <div class="bg-white/10 backdrop-blur-md rounded-2xl p-4 flex justify-between items-center border border-white/20">
                    <span class="text-xs font-black uppercase tracking-wider opacity-80">ทั้งหมด</span>
                    <span id="stat-total" class="text-2xl font-black">0</span>
                </div>
                <div class="bg-white/10 backdrop-blur-md rounded-2xl p-4 flex justify-between items-center border border-white/20">
                    <span class="text-xs font-black uppercase tracking-wider opacity-80 text-rose-100">ขาด</span>
                    <span id="stat-absent" class="text-2xl font-black">0</span>
                </div>
                <div class="bg-white/10 backdrop-blur-md rounded-2xl p-4 flex justify-between items-center border border-white/20">
                    <span class="text-xs font-black uppercase tracking-wider opacity-80 text-amber-100">ลา</span>
                    <span id="stat-leave" class="text-2xl font-black">0</span>
                </div>
                <div class="bg-white/10 backdrop-blur-md rounded-2xl p-4 flex justify-between items-center border border-white/20">
                    <span class="text-xs font-black uppercase tracking-wider opacity-80 text-purple-100">โดด</span>
                    <span id="stat-skip" class="text-2xl font-black">0</span>
                </div>
                <div class="bg-emerald-400 rounded-2xl p-4 flex justify-between items-center shadow-lg shadow-emerald-600/20">
                    <span class="text-xs font-black uppercase tracking-wider text-emerald-900 leading-tight">มาเรียน</span>
                    <span id="stat-present" class="text-2xl font-black text-emerald-900">0</span>
                </div>
            </div>

            <!-- Advisor Section -->
            <div class="mt-auto pt-10 border-t border-white/10 flex flex-col items-center">
                <p class="text-xs font-black uppercase tracking-widest opacity-60 mb-4">ครูที่ปรึกษา</p>
                <div class="w-24 h-24 rounded-full bg-white/20 border-4 border-white/30 flex items-center justify-center mb-4 text-3xl">
                    <i class="bi bi-person-fill"></i>
                </div>
                <p id="advisor-name" class="font-black text-center leading-tight">...</p>
            </div>

            <!-- Rules Section -->
            <div class="mt-10 bg-black/10 rounded-2xl p-4 border border-white/5">
                <h3 class="text-xs font-black uppercase tracking-widest opacity-60 mb-2">ข้อตกลงของห้อง</h3>
                <ol class="text-xs font-medium leading-relaxed opacity-80 list-decimal pl-4">
                    <li>เข้าแถวให้ตรงเวลาและเป็นระเบียบ</li>
                    <li>แต่งกายให้ถูกต้องตามระเบียบโรงเรียน</li>
                    <li>รักษาความสะอาดและความสงบเรียบร้อย</li>
                    <li>ให้ความเคารพครูและบุคลากร</li>
                </ol>
            </div>
        </aside>

        <!-- Main Area -->
        <main class="flex-1 p-8 md:p-12">
            <!-- Header -->
            <header class="flex justify-between items-start mb-12 border-b-2 border-slate-100 pb-8">
                <div>
                    <div class="inline-flex items-center gap-2 bg-cyan-50 text-cyan-700 px-4 py-1.5 rounded-full text-xs font-black uppercase tracking-widest mb-4">
                        <i class="bi bi-file-earmark-text-fill"></i> รายงานการมาเรียนประจำวัน
                    </div>
                    <h2 id="school-name" class="text-3xl font-black text-slate-800">...</h2>
                </div>
                <div class="text-right">
                    <p class="text-xs font-black text-slate-400 uppercase tracking-widest mb-1">ประจำวันที่</p>
                    <p id="report-date" class="text-3xl font-black text-cyan-600 tracking-tighter">...</p>
                </div>
            </header>

            <!-- Student Table -->
            <div class="overflow-hidden bg-white">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left border-b-2 border-slate-100">
                            <th class="py-4 text-xs font-black text-slate-400 uppercase tracking-widest w-12">เลขที่</th>
                            <th class="py-4 text-xs font-black text-slate-400 uppercase tracking-widest">ชื่อ-สกุล</th>
                            <th class="py-4 text-xs font-black text-slate-400 uppercase tracking-widest text-center bg-slate-50/50">วันนี้</th>
                            <th colspan="4" class="py-4 text-xs font-black text-slate-400 uppercase tracking-widest text-center border-l border-slate-50">สถิติรวม (ครั้ง)</th>
                        </tr>
                        <tr class="text-xs font-black text-slate-400 border-b border-slate-50 uppercase text-center">
                            <th></th><th></th><th></th>
                            <th class="py-2 border-l border-slate-50 text-emerald-600">มา</th>
                            <th class="py-2 text-rose-500">ขาด</th>
                            <th class="py-2 text-amber-500">ลา</th>
                            <th class="py-2 text-purple-500">โดด</th>
                        </tr>
                    </thead>
                    <tbody id="student-list" class="divide-y divide-slate-50">
                        <!-- Loaded via JS -->
                        <tr><td colspan="7" class="py-20 text-center text-slate-300 font-bold italic">กำลังโหลดข้อมูล...</td></tr>
                    </tbody>
                </table>
            </div>

            <!-- Footer -->
            <footer class="mt-20 pt-10 border-t border-slate-100 flex justify-between gap-10">
                <div class="w-48 text-center">
                    <div class="border-b border-slate-300 h-10 mb-2"></div>
                    <p class="text-xs font-black text-slate-400 uppercase">ครูที่ปรึกษา</p>
                </div>
                <div class="w-48 text-center">
                    <div class="border-b border-slate-300 h-10 mb-2"></div>
                    <p class="text-xs font-black text-slate-400 uppercase">ผู้ช่วยฝ่ายปกครอง</p>
                </div>
                <div class="w-48 text-center">
                    <div class="border-b border-slate-300 h-10 mb-2"></div>
                    <p class="text-xs font-black text-slate-400 uppercase">ฝ่ายบริหาร</p>
                </div>
            </footer>
        </main>
    </div>

    <script>
    const ROOM = '<?= htmlspecialchars($room) ?>';
    const DATE = '<?= htmlspecialchars($date) ?>';
    const BASE = '<?= $base_path ?>';

    async function fetchData() {
        if (!ROOM) {
            Swal.fire('ข้อผิดพลาด', 'ไม่ระบุห้องเรียน', 'error');
            return;
        }

        try {
            const res = await fetch(`${BASE}/assembly/api/get_classroom_report.php?classroom=${encodeURIComponent(ROOM)}&date=${DATE}`).then(r => r.json());
            
            if (res.status === 'success') {
                renderReport(res);
            } else {
                Swal.fire('ผิดพลาด', res.message, 'error');
            }
        } catch (e) {
            console.error(e);
            Swal.fire('ผิดพลาด', 'ไม่สามารถเชื่อมต่อเครื่องแม่ข่ายได้', 'error');
        }
    }

    function renderReport(data) {
        document.getElementById('school-name').textContent = data.school;
        document.getElementById('room-name').textContent   = data.classroom.classroom;
        document.getElementById('advisor-name').textContent= data.classroom.teacher_name || '-';
        
        // Date formatting: DD/MM/YYYY (Thai style)
        const d = new Date(data.date);
        const day = String(d.getDate()).padStart(2, '0');
        const month = String(d.getMonth() + 1).padStart(2, '0');
        const year = d.getFullYear() + 543;
        document.getElementById('report-date').textContent = `${day}/${month}/${year}`;

        // Summary
        document.getElementById('stat-total').textContent   = data.summary.total;
        document.getElementById('stat-present').textContent = data.summary.present;
        document.getElementById('stat-absent').textContent  = data.summary.absent;
        document.getElementById('stat-leave').textContent   = data.summary.leave;
        document.getElementById('stat-skip').textContent    = data.summary.skip;

        // Table
        const statusMap = {
            'ม': '<span class="text-emerald-500 font-black"><i class="bi bi-check-circle-fill"></i></span>',
            'ข': '<span class="text-rose-500 font-black"><i class="bi bi-x-circle-fill"></i></span>',
            'ล': '<span class="text-amber-500 font-black"><i class="bi bi-clock-fill"></i></span>',
            'ด': '<span class="text-purple-500 font-black"><i class="bi bi-slash-circle-fill"></i></span>',
            null: '<span class="text-slate-200">-</span>'
        };

        const tbody = document.getElementById('student-list');
        tbody.innerHTML = data.students.map((s, i) => `
            <tr class="hover:bg-slate-50/50 transition-colors">
                <td class="py-3 text-sm font-bold text-slate-400">${i + 1}</td>
                <td class="py-3 font-bold text-slate-700">${s.name}</td>
                <td class="py-3 text-center bg-slate-50/30">${statusMap[s.today] || '-'}</td>
                <td class="py-3 text-center border-l border-slate-50 font-bold text-emerald-600">${s.stats.m}</td>
                <td class="py-3 text-center font-bold text-rose-500">${s.stats.k}</td>
                <td class="py-3 text-center font-bold text-amber-500">${s.stats.l}</td>
                <td class="py-3 text-center font-bold text-purple-500">${s.stats.d}</td>
            </tr>
        `).join('');
    }

    window.onload = fetchData;
    </script>
</body>
</html>
