<?php
/**
 * teacher_leave/index.php
 * Leave System Dashboard
 */
session_start();
require_once '../config.php';

if (!isset($_SESSION['llw_role'])) {
    header('Location: /login.php'); exit();
}

$pageTitle = 'ระบบใบลาออนไลน์';
$pageSubtitle = 'ติดตามสถานะการลาและสถิติการลาสะสม';
$activeSystem = 'leave';

$isAdmin = in_array($_SESSION['llw_role'], ['super_admin', 'wfh_admin']);

require_once '../components/layout_start.php';
?>

<div class="flex flex-col gap-8">
    <!-- Action Header -->
    <div class="flex flex-wrap justify-between items-center gap-6 bg-white p-8 rounded-[2.5rem] border border-slate-100 shadow-sm">
        <div class="flex items-center gap-4">
            <div class="w-14 h-14 bg-rose-50 text-rose-600 rounded-2xl flex items-center justify-center text-2xl shadow-sm">
                <i class="bi bi-calendar-check"></i>
            </div>
            <div>
                <h3 class="font-black text-slate-800 tracking-tight">ระบบใบลาออนไลน์</h3>
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mt-1 italic">จัดการการลาตามระเบียบโรงเรียน</p>
            </div>
        </div>
        <div class="flex items-center gap-3">
            <a href="form.php" class="px-8 py-4 bg-rose-600 text-white rounded-2xl font-black text-sm shadow-xl shadow-rose-100 hover:scale-[1.02] active:scale-95 transition-all flex items-center gap-3">
                <i class="bi bi-plus-lg text-lg"></i> ยื่นใบลาใหม่
            </a>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Left: Stats Card -->
        <div class="lg:col-span-1 space-y-6">
            <div class="bg-white rounded-[2.5rem] p-8 border border-slate-100 shadow-sm">
                <h3 class="font-black text-slate-800 mb-6 flex items-center gap-2">
                    <i class="bi bi-pie-chart text-rose-600"></i> สถิติปีปัจจุบัน
                </h3>
                <div class="mb-8 p-6 bg-slate-50/50 rounded-3xl border border-slate-100 flex items-center justify-center">
                    <div style="width: 180px; height: 180px;">
                        <canvas id="statsChart"></canvas>
                    </div>
                </div>
                <div class="space-y-4" id="stats-container">
                    <!-- Stats list will be loaded via JS -->
                    <div class="animate-pulse flex flex-col gap-4">
                        <div class="h-16 bg-slate-50 rounded-2xl"></div>
                        <div class="h-16 bg-slate-50 rounded-2xl"></div>
                        <div class="h-16 bg-slate-50 rounded-2xl"></div>
                    </div>
                </div>
            </div>
            
            <div class="bg-gradient-to-br from-indigo-600 to-blue-700 rounded-[2.5rem] p-8 text-white shadow-xl shadow-indigo-200/50">
                <h4 class="font-black text-lg mb-2">วันหยุดราชการถัดไป</h4>
                <div id="next-holiday" class="mt-4">
                    <p class="text-xs opacity-70 uppercase tracking-widest font-bold">รอการตรวจสอบ...</p>
                </div>
            </div>
        </div>

        <!-- Right: Request History Table -->
        <div class="lg:col-span-2 space-y-8">
            <!-- Admin KPI Section -->
            <?php if ($isAdmin): ?>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6" id="admin-stats-row">
                <!-- รอเจ้าหน้าที่ -->
                <div class="bg-white rounded-3xl p-6 border border-slate-100 shadow-sm flex items-center gap-5 group hover:shadow-md transition-all">
                    <div class="w-12 h-12 bg-amber-50 text-amber-500 rounded-2xl flex items-center justify-center text-2xl group-hover:scale-110 transition-all">
                        <i class="bi bi-person-workspace"></i>
                    </div>
                    <div>
                        <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest leading-none mb-1">รอการตรวจสอบ</p>
                        <p class="text-2xl font-black text-slate-800" id="stat-waiting-verify">0</p>
                    </div>
                </div>
                <!-- ลาวันนี้ -->
                <div class="bg-white rounded-3xl p-6 border border-slate-100 shadow-sm flex items-center gap-5 group hover:shadow-md transition-all">
                    <div class="w-12 h-12 bg-emerald-50 text-emerald-500 rounded-2xl flex items-center justify-center text-2xl group-hover:scale-110 transition-all">
                        <i class="bi bi-calendar-event"></i>
                    </div>
                    <div>
                        <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest leading-none mb-1">ลางานวันนี้</p>
                        <p class="text-2xl font-black text-slate-800" id="stat-on-leave-today">0</p>
                    </div>
                </div>
                <!-- รอ ผอ. -->
                <div class="bg-white rounded-3xl p-6 border border-slate-100 shadow-sm flex items-center gap-5 group hover:shadow-md transition-all">
                    <div class="w-12 h-12 bg-blue-50 text-blue-500 rounded-2xl flex items-center justify-center text-2xl group-hover:scale-110 transition-all">
                        <i class="bi bi-mortarboard"></i>
                    </div>
                    <div>
                        <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest leading-none mb-1">รอ ผอ./รองฯ</p>
                        <p class="text-2xl font-black text-slate-800" id="stat-waiting-director">0</p>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <div class="bg-white rounded-[2.5rem] shadow-sm border border-slate-100 overflow-hidden">
                <div class="px-10 py-8 border-b border-slate-50 flex items-center justify-between bg-slate-50/30">
                    <h3 class="font-black text-slate-800 flex items-center gap-3">
                        <i class="bi bi-clock-history text-blue-600"></i> 
                        <?= $isAdmin ? 'รายการรอดำเนินการ (Admin)' : 'ประวัติการลาของฉัน' ?>
                    </h3>
                </div>
                
                <div class="p-8">
                    <div class="overflow-x-auto">
                        <table id="leaveTable" class="min-w-full text-sm">
                            <thead class="bg-slate-50/50 text-[10px] font-black text-slate-400 uppercase tracking-widest border-b border-slate-100">
                                <tr>
                                    <th class="px-6 py-5">ชื่อผู้ลา / ประเภทใบลา</th>
                                    <th class="px-6 py-5">จำนวน</th>
                                    <th class="px-6 py-5">สถานะ</th>
                                    <th class="px-6 py-5 text-right"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-50 text-slate-600">
                                <!-- Loaded via DataTables -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Approve with Director Signature -->
<div id="approvalModal" class="fixed inset-0 z-50 hidden flex items-center justify-center p-4" style="background:rgba(0,0,0,0.5);backdrop-filter:blur(4px)">
    <div class="bg-white rounded-[2.5rem] shadow-2xl w-full max-w-lg p-8 flex flex-col gap-6">
        <div class="flex items-center gap-4">
            <div class="w-12 h-12 bg-emerald-50 text-emerald-600 rounded-2xl flex items-center justify-center text-2xl">
                <i class="bi bi-pen"></i>
            </div>
            <div>
                <h3 class="font-black text-slate-800 text-lg" id="modalTitle">อนุมัติใบลา</h3>
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest" id="modalSubtitle">กรุณาลงนามและยืนยัน</p>
            </div>
        </div>

        <!-- Comment -->
        <div>
            <label class="text-[10px] font-black text-slate-500 uppercase tracking-widest block mb-2">ความคิดเห็น (ไม่บังคับ)</label>
            <textarea id="approveComment" rows="2"
                class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-4 py-3 text-sm focus:ring-2 focus:ring-emerald-500 outline-none transition-all resize-none"
                placeholder="ใส่เหตุผลหรือข้อความเพิ่มเติม..."></textarea>
        </div>

        <!-- Signature Canvas -->
        <div>
            <div class="flex items-center justify-between mb-2">
                <label class="text-[10px] font-black text-slate-500 uppercase tracking-widest" id="sigLabel">ลายเซ็นผู้อำนวยการ <span class="text-rose-500">*</span></label>
                <button onclick="clearDirectorSig()" class="text-[10px] font-black text-slate-400 hover:text-rose-500 transition-all uppercase tracking-widest flex items-center gap-1">
                    <i class="bi bi-arrow-counterclockwise"></i> ล้าง
                </button>
            </div>
            <canvas id="directorSigCanvas"
                class="w-full border-2 border-dashed border-slate-200 rounded-2xl bg-slate-50 cursor-crosshair touch-none"
                style="height:140px"></canvas>
            <p class="text-[9px] text-slate-400 mt-1 font-bold">วาดลายเซ็นในกรอบด้านบน</p>
        </div>

        <!-- Attachment (If any) -->
        <div id="attachment-view-section" class="hidden">
            <div class="p-4 bg-rose-50 rounded-2xl border border-rose-100 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-white rounded-xl flex items-center justify-center text-xl text-rose-500 shadow-sm">
                        <i class="bi bi-file-earmark-medical"></i>
                    </div>
                    <div>
                        <p class="text-[10px] font-black text-slate-800 uppercase tracking-wider leading-none mb-1">ใบรับรองแพทย์</p>
                        <p class="text-[9px] font-bold text-slate-400">คลิกเพื่อดูเอกสารหลักฐาน</p>
                    </div>
                </div>
                <a id="btn-view-att" href="#" target="_blank" class="px-4 py-2 bg-rose-500 text-white text-[10px] font-black uppercase tracking-widest rounded-xl hover:bg-rose-600 transition-all shadow-md shadow-rose-100">ดูไฟล์</a>
            </div>
        </div>

        <!-- Buttons -->
        <div class="grid grid-cols-3 gap-3 mt-2">
            <button onclick="closeApprovalModal()" class="col-span-1 py-3 bg-slate-100 text-slate-600 rounded-2xl font-black text-sm hover:bg-slate-200 transition-all">
                ยกเลิก
            </button>
            <button onclick="submitReject()" class="col-span-1 py-3 bg-rose-500 text-white rounded-2xl font-black text-sm shadow-lg shadow-rose-100 hover:bg-rose-600 transition-all">
                <i class="bi bi-x-lg"></i> ไม่อนุมัติ
            </button>
            <button onclick="submitApprove()" class="col-span-1 py-3 bg-emerald-600 text-white rounded-2xl font-black text-sm shadow-lg shadow-emerald-100 hover:bg-emerald-700 transition-all">
                <i class="bi bi-check-lg"></i> อนุมัติ
            </button>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>

<script>
const BASE_PATH = '<?= $base_path ?>';
$(document).ready(function() {
    const table = $('#leaveTable').DataTable({
        ajax: { url: 'api/get_requests.php', dataSrc: 'data' },
        dom: 'rtip',
        columns: [
            { 
                data: 'date_start',
                render: (d, t, r) => `
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-slate-100 flex flex-col items-center justify-center font-black leading-none shrink-0">
                            <span class="text-[8px] text-slate-400 uppercase">${new Date(d).toLocaleString('th-TH', {month:'short'})}</span>
                            <span class="text-lg text-slate-700">${new Date(d).getDate()}</span>
                        </div>
                        <div>
                            <?php if ($isAdmin): ?>
                            <div class="text-[10px] font-black text-rose-500 uppercase tracking-widest mb-0.5">${r.t_name}</div>
                            <?php endif; ?>
                            <div class="font-bold text-slate-800">${r.leave_type_text}</div>
                            <div class="text-[10px] font-black text-slate-400 uppercase tracking-widest">${d} - ${r.date_end}</div>
                        </div>
                    </div>
                `
            },
            { 
                data: 'days_count',
                render: (d) => `<div class="font-black text-slate-700 text-lg">${d} <span class="text-[10px] text-slate-400 uppercase">วัน</span></div>`
            },
            { 
                data: 'status',
                render: (data, t, r) => {
                    if (data === 'approved') {
                        return `<span class="inline-flex items-center gap-1 px-3 py-1.5 rounded-full bg-emerald-50 text-emerald-600 text-[10px] font-black tracking-widest whitespace-nowrap">
                            <i class="bi bi-check-circle-fill"></i> อนุมัติแล้ว
                        </span>`;
                    }
                    if (data === 'rejected') {
                        return `<span class="inline-flex items-center gap-1 px-3 py-1.5 rounded-full bg-rose-50 text-rose-600 text-[10px] font-black tracking-widest whitespace-nowrap">
                            <i class="bi bi-x-circle-fill"></i> ไม่อนุมัติ
                        </span>`;
                    }
                    // pending — แสดงตาม level
                    const lvl = r.level_at;
                    if (lvl <= 1) {
                        return `<span class="inline-flex items-center gap-1 px-3 py-1.5 rounded-full bg-amber-50 text-amber-600 text-[10px] font-black tracking-widest whitespace-nowrap">
                            <i class="bi bi-hourglass-split"></i> รอตรวจสอบจากเจ้าหน้าที่
                        </span>`;
                    }
                    if (lvl === 2) {
                        return `<span class="inline-flex items-center gap-1 px-3 py-1.5 rounded-full bg-blue-50 text-blue-600 text-[10px] font-black tracking-widest whitespace-nowrap">
                            <i class="bi bi-person-check"></i> รออนุมัติ ผอ./รองฯ
                        </span>`;
                    }
                    return `<span class="inline-flex items-center gap-1 px-3 py-1.5 rounded-full bg-slate-50 text-slate-500 text-[10px] font-black tracking-widest whitespace-nowrap">
                        <i class="bi bi-clock"></i> รอดำเนินการ
                    </span>`;
                }
            },
            {
                data: 'id',
                className: 'text-right',
                render: (id, t, r) => {
                    let html = `<div class="flex items-center justify-end gap-2">`;
                    html += `<a href="print_leave.php?id=${id}" target="_blank" class="w-8 h-8 rounded-lg bg-slate-100 text-slate-500 flex items-center justify-center hover:bg-slate-200 transition-all shadow-sm" title="พิมพ์ใบลา"><i class="bi bi-printer-fill"></i></a>`;
                    
                    // ปุ่มยกเลิก (สำหรับเจ้าของใบลา และยังไม่ได้อนุมัติ)
                    if (r.status === 'pending' && r.user_id == <?= $_SESSION['user_id'] ?>) {
                        html += `<button onclick="cancelRequest(${id})" class="w-8 h-8 rounded-lg bg-rose-50 text-rose-500 flex items-center justify-center hover:bg-rose-100 transition-all shadow-sm" title="ยกเลิกใบลา"><i class="bi bi-trash-fill"></i></button>`;
                    }

                    <?php if ($isAdmin): ?>
                    if (r.status === 'pending') {
                        html += `<button onclick='openApproval(${JSON.stringify(r)})' class="px-4 py-2 bg-blue-600 text-white rounded-xl text-[10px] font-black uppercase tracking-widest hover:scale-105 transition-all shadow-lg shadow-blue-100">อนุมัติ</button>`;
                    }
                    <?php endif; ?>
                    
                    html += `</div>`;
                    return html;
                }
            }
        ],
        language: { emptyTable: "ไม่พบข้อมูลการลา" }
    });

    // Load Stats
    $.get('api/get_stats.php', function(res) {
        if(res.status === 'success') {
            const s = res.data;
            
            // Render Chart
            renderStatsChart(s);

            let html = `
                <div class="p-4 bg-blue-50/50 rounded-2xl border border-blue-100 flex justify-between items-center group hover:bg-blue-50 transition-all">
                    <div><p class="text-[10px] font-black text-blue-400 uppercase tracking-widest mb-1">ลาป่วยสะสม</p><p class="text-xl font-black text-blue-700">${s.sick_taken} วัน</p></div>
                    <div class="w-10 h-10 bg-blue-600 text-white rounded-xl flex items-center justify-center text-xl shadow-lg shadow-blue-200 group-hover:scale-110 transition-all"><i class="bi bi-heart-pulse"></i></div>
                </div>
                <div class="p-4 bg-emerald-50/50 rounded-2xl border border-emerald-100 flex justify-between items-center group hover:bg-emerald-50 transition-all">
                    <div><p class="text-[10px] font-black text-emerald-400 uppercase tracking-widest mb-1">ลากิจสะสม</p><p class="text-xl font-black text-emerald-700">${s.personal_taken} วัน</p></div>
                    <div class="w-10 h-10 bg-emerald-500 text-white rounded-xl flex items-center justify-center text-xl shadow-lg shadow-emerald-200 group-hover:scale-110 transition-all"><i class="bi bi-briefcase"></i></div>
                </div>
                <div class="p-4 bg-indigo-50/50 rounded-2xl border border-indigo-100 flex justify-between items-center group hover:bg-indigo-50 transition-all">
                    <div><p class="text-[10px] font-black text-indigo-400 uppercase tracking-widest mb-1">ลาพักผ่อนคงเหลือ</p><p class="text-xl font-black text-indigo-700">${s.vacation_quota - s.vacation_taken} / ${s.vacation_quota}</p></div>
                    <div class="w-10 h-10 bg-indigo-600 text-white rounded-xl flex items-center justify-center text-xl shadow-lg shadow-indigo-200 group-hover:scale-110 transition-all"><i class="bi bi-sun"></i></div>
                </div>
            `;
            $('#stats-container').html(html);
        }
    });

    function renderStatsChart(s) {
        const ctx = document.getElementById('statsChart').getContext('2d');
        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['ลาป่วย', 'ลากิจ', 'ลาพักผ่อนที่ใช้', 'ลาพักผ่อนคงเหลือ'],
                datasets: [{
                    data: [s.sick_taken, s.personal_taken, s.vacation_taken, s.vacation_quota - s.vacation_taken],
                    backgroundColor: ['#2563eb', '#10b981', '#6366f1', '#e2e8f0'],
                    borderWidth: 0,
                    hoverOffset: 4
                }]
            },
            options: {
                cutout: '75%',
                plugins: {
                    legend: { display: false }
                },
                maintainAspectRatio: false
            }
        });
    }

    // Load Next Holiday
    $.get('api/get_holidays.php?limit=1', function(res) {
        if(res.status === 'success' && res.data.length > 0) {
            const h = res.data[0];
            let html = `
                <p class="text-2xl font-black leading-tight">${h.name}</p>
                <div class="flex items-center gap-2 mt-2 opacity-80">
                    <i class="bi bi-calendar3"></i>
                    <span class="text-sm font-bold">${h.formatted_date}</span>
                </div>
            `;
            $('#next-holiday').html(html);
        }
    });

    <?php if ($isAdmin): ?>
    // Load Admin Stats
    $.get('api/get_admin_stats.php', function(res) {
        if(res.status === 'success') {
            const s = res.data;
            $('#stat-waiting-verify').text(s.waiting_staff);
            $('#stat-on-leave-today').text(s.on_leave_today);
            $('#stat-waiting-director').text(s.waiting_director);
        }
    });
    <?php endif; ?>
});

// ── Director Signature Canvas ──
let _currentApprovalId = null;
let _dirSigCanvas, _dirSigCtx, _dirDrawing = false;

function openApproval(request) {
    if (typeof request === 'number') {
        // Compatibility for old calls if any
        _currentApprovalId = request;
        document.getElementById('modalTitle').textContent = 'อนุมัติใบลา';
        document.getElementById('sigLabel').innerHTML = 'ลายเซ็นผู้อำนวยการ <span class="text-rose-500">*</span>';
    } else {
        _currentApprovalId = request.id;
        if (request.level_at == 1) {
            document.getElementById('modalTitle').textContent = 'ตรวจสอบใบลา';
            document.getElementById('modalSubtitle').textContent = 'เจ้าหน้าที่ตรวจสอบข้อมูลเบื้องต้น';
            document.getElementById('sigLabel').innerHTML = 'ลายเซ็นเจ้าหน้าที่ <span class="text-rose-500">*</span>';
        } else {
            document.getElementById('modalTitle').textContent = 'อนุมัติใบลา';
            document.getElementById('modalSubtitle').textContent = 'ผู้อำนวยการ/รองฯ พิจารณาอนุมัติ';
            document.getElementById('sigLabel').innerHTML = 'ลายเซ็นผู้อำนวยการ/รองฯ <span class="text-rose-500">*</span>';
        }
    }

    // Attachment link handling
    const attSection = document.getElementById('attachment-view-section');
    const attLink = document.getElementById('btn-view-att');
    if (request.attachment_path) {
        attSection.classList.remove('hidden');
        attLink.href = BASE_PATH + '/' + request.attachment_path;
    } else {
        attSection.classList.add('hidden');
    }

    document.getElementById('approveComment').value = '';
    document.getElementById('approvalModal').classList.remove('hidden');

    // Init canvas
    _dirSigCanvas = document.getElementById('directorSigCanvas');
    _dirSigCtx    = _dirSigCanvas.getContext('2d');
    resizeDirectorCanvas();
    clearDirectorSig();
    bindDirectorSig();
}

function closeApprovalModal() {
    document.getElementById('approvalModal').classList.add('hidden');
}

function resizeDirectorCanvas() {
    const rect = _dirSigCanvas.getBoundingClientRect();
    _dirSigCanvas.width  = rect.width  * (window.devicePixelRatio || 1);
    _dirSigCanvas.height = rect.height * (window.devicePixelRatio || 1);
    _dirSigCtx.scale(window.devicePixelRatio || 1, window.devicePixelRatio || 1);
    _dirSigCtx.lineWidth   = 2.5;
    _dirSigCtx.strokeStyle = '#1e293b';
    _dirSigCtx.lineCap     = 'round';
    _dirSigCtx.lineJoin    = 'round';
}

function clearDirectorSig() {
    if (!_dirSigCtx) return;
    _dirSigCtx.clearRect(0, 0, _dirSigCanvas.width, _dirSigCanvas.height);
}

function isDirectorSigEmpty() {
    const data = _dirSigCtx.getImageData(0, 0, _dirSigCanvas.width, _dirSigCanvas.height).data;
    return !data.some(v => v !== 0);
}

function bindDirectorSig() {
    const c = _dirSigCanvas;
    const getPos = (e) => {
        const r = c.getBoundingClientRect();
        const src = e.touches ? e.touches[0] : e;
        return { x: src.clientX - r.left, y: src.clientY - r.top };
    };
    c.onpointerdown = (e) => { _dirDrawing = true; const p=getPos(e); _dirSigCtx.beginPath(); _dirSigCtx.moveTo(p.x,p.y); e.preventDefault(); };
    c.onpointermove = (e) => { if(!_dirDrawing) return; const p=getPos(e); _dirSigCtx.lineTo(p.x,p.y); _dirSigCtx.stroke(); e.preventDefault(); };
    c.onpointerup   = () => { _dirDrawing = false; };
}

function submitApprove() {
    if (isDirectorSigEmpty()) {
        Swal.fire({ icon: 'warning', title: 'กรุณาลงนาม', text: 'ผู้อำนวยการต้องลายเซ็นก่อนอนุมัติ', confirmButtonColor: '#2563eb' });
        return;
    }
    const sig     = _dirSigCanvas.toDataURL('image/png');
    const comment = document.getElementById('approveComment').value;
    closeApprovalModal();
    processApproval(_currentApprovalId, 1, comment, sig);
}

function submitReject() {
    const comment = document.getElementById('approveComment').value;
    closeApprovalModal();
    processApproval(_currentApprovalId, 2, comment, null);
}

async function processApproval(id, status, comment, signature) {
    try {
        const res = await fetch('api/approve_leave.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id, status, comment, signature })
        });
        const json = await res.json();
        if (json.status === 'success') {
            Swal.fire({ icon: 'success', title: 'ดำเนินการสำเร็จ', text: json.message, timer: 1800, showConfirmButton: false });
            $('#leaveTable').DataTable().ajax.reload();
            // Refresh counts if admin
            <?php if ($isAdmin): ?>
            updateAdminStats();
            <?php endif; ?>
        } else {
            Swal.fire('Error', json.message, 'error');
        }
    } catch (e) {
        Swal.fire('Error', 'เกิดข้อผิดพลาดในการเชื่อมต่อ', 'error');
    }
}

function cancelRequest(id) {
    Swal.fire({
        title: 'ยืนยันการยกเลิก?',
        text: "คุณต้องการยกเลิกใบลาใบนี้ใช่หรือไม่?",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'ยืนยัน ยกเลิกใบลา',
        cancelButtonText: 'ปิดหน้าต่าง',
        confirmButtonColor: '#ef4444',
        customClass: { popup: 'rounded-[2rem]' }
    }).then(async (result) => {
        if (result.isConfirmed) {
            try {
                const res = await fetch('api/cancel_request.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id })
                });
                const json = await res.json();
                if (json.status === 'success') {
                    Swal.fire({ icon: 'success', title: 'ยกเลิกสำเร็จ', text: json.message, timer: 1500, showConfirmButton: false });
                    $('#leaveTable').DataTable().ajax.reload();
                    <?php if ($isAdmin): ?>
                    updateAdminStats();
                    <?php endif; ?>
                } else {
                    Swal.fire('Error', json.message, 'error');
                }
            } catch (e) {
                Swal.fire('Error', 'ไม่สามารถเชื่อมต่อได้', 'error');
            }
        }
    });
}

<?php if ($isAdmin): ?>
function updateAdminStats() {
    $.get('api/get_admin_stats.php', function(res) {
        if(res.status === 'success') {
            const s = res.data;
            $('#stat-waiting-verify').text(s.waiting_staff);
            $('#stat-on-leave-today').text(s.on_leave_today);
            $('#stat-waiting-director').text(s.waiting_director);
        }
    });
}
<?php endif; ?>

// ปิด modal เมื่อ click พื้นหลัง
document.getElementById('approvalModal').addEventListener('click', function(e) {
    if (e.target === this) closeApprovalModal();
});
</script>

<?php require_once '../components/layout_end.php'; ?>
