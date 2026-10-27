<?php
/**
 * teacher_leave/index.php
 * Leave System Dashboard & Approval Center
 */
session_start();
require_once '../config.php';

if (!isset($_SESSION['llw_role'])) {
    header('Location: /login.php'); exit();
}

$pageTitle = 'ระบบใบลาออนไลน์';
$pageSubtitle = 'ติดตามสถานะการลาและสถิติการลาสะสม';
$activeSystem = 'teacher_leave';

$isAdmin = function_exists('llw_has_any_role')
    ? llw_has_any_role(['super_admin', 'wfh_admin', 'director', 'deputy_director'])
    : in_array($_SESSION['llw_role'] ?? '', ['super_admin', 'wfh_admin', 'director', 'deputy_director']);

$currentUserId = (int)($_SESSION['user_id'] ?? 0);

if (!$isAdmin && $currentUserId > 0) {
    try {
        $stmtRole = getPdo()->prepare("SELECT 1 FROM llw_user_roles WHERE user_id = ? AND role IN ('super_admin', 'wfh_admin', 'director', 'deputy_director') LIMIT 1");
        $stmtRole->execute([$currentUserId]);
        if ($stmtRole->fetchColumn()) {
            $isAdmin = true;
        }
    } catch (\Throwable $e) {}
}

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
                <p class="text-xs font-black text-slate-400 uppercase tracking-widest mt-1 italic">
                    <?= $isAdmin ? 'ศูนย์จัดการและอนุมัติใบลาบุคลากร' : 'ติดตามสถานะและประวัติการลาของคุณ' ?>
                </p>
            </div>
        </div>
        <div class="flex items-center gap-3">
            <?php if ($isAdmin): ?>
            <a href="admin_overview.php" class="px-6 py-4 bg-slate-100 text-slate-700 rounded-2xl font-black text-sm hover:bg-slate-200 transition-all flex items-center gap-2">
                <i class="bi bi-pie-chart-fill text-rose-500"></i> ภาพรวมการลา
            </a>
            <?php endif; ?>
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
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4" id="admin-stats-row">
                <!-- รวมรออนุมัติ -->
                <div class="bg-white rounded-3xl p-5 border border-slate-100 shadow-sm flex items-center gap-4 group hover:shadow-md transition-all">
                    <div class="w-12 h-12 bg-rose-50 text-rose-500 rounded-2xl flex items-center justify-center text-xl group-hover:scale-110 transition-all">
                        <i class="bi bi-hourglass-split"></i>
                    </div>
                    <div>
                        <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest leading-none mb-1">รออนุมัติทั้งหมด</p>
                        <p class="text-2xl font-black text-rose-600" id="stat-waiting-total">0</p>
                    </div>
                </div>
                <!-- รอเจ้าหน้าที่ -->
                <div class="bg-white rounded-3xl p-5 border border-slate-100 shadow-sm flex items-center gap-4 group hover:shadow-md transition-all">
                    <div class="w-12 h-12 bg-amber-50 text-amber-500 rounded-2xl flex items-center justify-center text-xl group-hover:scale-110 transition-all">
                        <i class="bi bi-person-workspace"></i>
                    </div>
                    <div>
                        <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest leading-none mb-1">รอการตรวจสอบ</p>
                        <p class="text-2xl font-black text-amber-600" id="stat-waiting-verify">0</p>
                    </div>
                </div>
                <!-- รอ ผอ. -->
                <div class="bg-white rounded-3xl p-5 border border-slate-100 shadow-sm flex items-center gap-4 group hover:shadow-md transition-all">
                    <div class="w-12 h-12 bg-blue-50 text-blue-500 rounded-2xl flex items-center justify-center text-xl group-hover:scale-110 transition-all">
                        <i class="bi bi-mortarboard"></i>
                    </div>
                    <div>
                        <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest leading-none mb-1">รอ ผอ./รองฯ</p>
                        <p class="text-2xl font-black text-blue-600" id="stat-waiting-director">0</p>
                    </div>
                </div>
                <!-- ลาวันนี้ -->
                <div class="bg-white rounded-3xl p-5 border border-slate-100 shadow-sm flex items-center gap-4 group hover:shadow-md transition-all">
                    <div class="w-12 h-12 bg-emerald-50 text-emerald-500 rounded-2xl flex items-center justify-center text-xl group-hover:scale-110 transition-all">
                        <i class="bi bi-calendar-event"></i>
                    </div>
                    <div>
                        <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest leading-none mb-1">ลางานวันนี้</p>
                        <p class="text-2xl font-black text-emerald-600" id="stat-on-leave-today">0</p>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <div class="bg-white rounded-[2.5rem] shadow-sm border border-slate-100 overflow-hidden">
                <div class="px-8 py-6 border-b border-slate-100 flex flex-wrap items-center justify-between gap-4 bg-slate-50/50">
                    <div>
                        <h3 class="font-black text-slate-800 flex items-center gap-3">
                            <i class="bi bi-clock-history text-rose-600"></i> 
                            <?= $isAdmin ? 'รายการใบลาบุคลากรทั้งหมด' : 'ประวัติการลาของฉัน' ?>
                        </h3>
                        <p class="text-xs text-slate-400 font-bold mt-0.5">
                            <?= $isAdmin ? 'คลิก "อนุมัติ" เพื่อลงนามตรวจสอบหรืออนุมัติใบลา' : 'ดูสถานะและพิมพ์ใบลา' ?>
                        </p>
                    </div>
                    <!-- Quick Filters -->
                    <div class="flex items-center gap-2 bg-white p-1 rounded-2xl border border-slate-200">
                        <button type="button" onclick="filterStatus('all')" id="btn-f-all" class="filter-tab px-4 py-2 rounded-xl text-xs font-black transition-all bg-rose-50 text-rose-600">
                            ทั้งหมด
                        </button>
                        <button type="button" onclick="filterStatus('pending')" id="btn-f-pending" class="filter-tab px-4 py-2 rounded-xl text-xs font-black transition-all text-slate-500 hover:text-slate-800">
                            ⏳ รออนุมัติ
                        </button>
                        <button type="button" onclick="filterStatus('approved')" id="btn-f-approved" class="filter-tab px-4 py-2 rounded-xl text-xs font-black transition-all text-slate-500 hover:text-slate-800">
                            ✅ อนุมัติแล้ว
                        </button>
                        <button type="button" onclick="filterStatus('rejected')" id="btn-f-rejected" class="filter-tab px-4 py-2 rounded-xl text-xs font-black transition-all text-slate-500 hover:text-slate-800">
                            ❌ ไม่อนุมัติ
                        </button>
                    </div>
                </div>
                
                <div class="p-8">
                    <div class="overflow-x-auto">
                        <table id="leaveTable" class="min-w-full text-sm">
                            <thead class="bg-slate-50/50 text-xs font-black text-slate-400 uppercase tracking-widest border-b border-slate-100">
                                <tr>
                                    <th class="px-6 py-5">ชื่อผู้ลา / ประเภทใบลา</th>
                                    <th class="px-6 py-5 text-center">จำนวน</th>
                                    <th class="px-6 py-5">สถานะ</th>
                                    <th class="px-6 py-5 text-right">จัดการ</th>
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

<!-- Modal Approve with Director/Staff Signature -->
<div id="approvalModal" class="fixed inset-0 z-50 hidden flex items-center justify-center p-4" style="background:rgba(0,0,0,0.5);backdrop-filter:blur(4px)">
    <div class="bg-white rounded-[2.5rem] shadow-2xl w-full max-w-lg p-8 flex flex-col gap-6 max-h-[90vh] overflow-y-auto">
        <div class="flex items-center gap-4">
            <div class="w-12 h-12 bg-emerald-50 text-emerald-600 rounded-2xl flex items-center justify-center text-2xl">
                <i class="bi bi-pen"></i>
            </div>
            <div>
                <h3 class="font-black text-slate-800 text-lg" id="modalTitle">อนุมัติใบลา</h3>
                <p class="text-xs font-black text-slate-400 uppercase tracking-widest" id="modalSubtitle">กรุณาลงนามและยืนยัน</p>
            </div>
        </div>

        <!-- Detail summary -->
        <div id="modalSummaryBox" class="p-4 bg-slate-50 rounded-2xl border border-slate-100 text-xs space-y-1">
            <p class="font-bold text-slate-700"><span class="text-slate-400">ผู้ขอลา:</span> <span id="sumApplicant" class="font-black text-slate-900">-</span></p>
            <p class="font-bold text-slate-700"><span class="text-slate-400">ประเภท:</span> <span id="sumLeaveType" class="text-rose-600 font-black">-</span> (<span id="sumDays">-</span> วัน)</p>
            <p class="font-bold text-slate-700"><span class="text-slate-400">ช่วงวันที่:</span> <span id="sumDates">-</span></p>
            <p class="font-bold text-slate-700"><span class="text-slate-400">เหตุผล:</span> <span id="sumReason" class="font-normal text-slate-600">-</span></p>
        </div>

        <!-- Comment -->
        <div>
            <label class="text-xs font-black text-slate-500 uppercase tracking-widest block mb-2">ความคิดเห็น (ไม่บังคับ)</label>
            <textarea id="approveComment" rows="2"
                class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-4 py-3 text-sm focus:ring-2 focus:ring-emerald-500 outline-none transition-all resize-none font-bold"
                placeholder="ใส่เหตุผลหรือข้อความเพิ่มเติม..."></textarea>
        </div>

        <!-- Signature Canvas -->
        <div>
            <div class="flex items-center justify-between mb-2">
                <label class="text-xs font-black text-slate-500 uppercase tracking-widest" id="sigLabel">ลายเซ็นผู้อนุมัติ <span class="text-rose-500">*</span></label>
                <button type="button" onclick="clearDirectorSig()" class="text-xs font-black text-slate-400 hover:text-rose-500 transition-all uppercase tracking-widest flex items-center gap-1">
                    <i class="bi bi-arrow-counterclockwise"></i> ล้าง
                </button>
            </div>
            <canvas id="directorSigCanvas"
                class="w-full border-2 border-dashed border-slate-200 rounded-2xl bg-slate-50 cursor-crosshair touch-none"
                style="height:140px"></canvas>
            <p class="text-xs text-slate-400 mt-1 font-bold">วาดลายเซ็นในกรอบด้านบน</p>
        </div>

        <!-- Direct Final Option (Super Admin / ผอ.) -->
        <?php if ($isAdmin): ?>
        <div id="directFinalWrap" class="p-3 bg-blue-50/70 rounded-2xl border border-blue-100 flex items-center gap-3">
            <input type="checkbox" id="chkDirectFinal" class="w-4 h-4 rounded text-blue-600 focus:ring-blue-500" checked>
            <label for="chkDirectFinal" class="text-xs font-black text-blue-800 cursor-pointer">
                อนุมัติขั้นสุดท้ายทันที (อนุมัติเสร็จสิ้นในฐานะผู้บริหาร/ผอ.)
            </label>
        </div>
        <?php endif; ?>

        <!-- Attachment (If any) -->
        <div id="attachment-view-section" class="hidden">
            <div class="p-4 bg-rose-50 rounded-2xl border border-rose-100 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-white rounded-xl flex items-center justify-center text-xl text-rose-500 shadow-sm">
                        <i class="bi bi-file-earmark-medical"></i>
                    </div>
                    <div>
                        <p class="text-xs font-black text-slate-800 uppercase tracking-wider leading-none mb-1">ใบรับรองแพทย์ / ไฟล์แนบ</p>
                        <p class="text-xs font-bold text-slate-400">คลิกเพื่อดูเอกสารหลักฐาน</p>
                    </div>
                </div>
                <a id="btn-view-att" href="#" target="_blank" class="px-4 py-2 bg-rose-500 text-white text-xs font-black uppercase tracking-widest rounded-xl hover:bg-rose-600 transition-all shadow-md shadow-rose-100">ดูไฟล์</a>
            </div>
        </div>

        <!-- Buttons -->
        <div class="grid grid-cols-3 gap-3 mt-2">
            <button type="button" onclick="closeApprovalModal()" class="col-span-1 py-3 bg-slate-100 text-slate-600 rounded-2xl font-black text-sm hover:bg-slate-200 transition-all">
                ยกเลิก
            </button>
            <button type="button" onclick="submitReject()" class="col-span-1 py-3 bg-rose-500 text-white rounded-2xl font-black text-sm shadow-lg shadow-rose-100 hover:bg-rose-600 transition-all">
                <i class="bi bi-x-lg"></i> ไม่อนุมัติ
            </button>
            <button type="button" onclick="submitApprove()" class="col-span-1 py-3 bg-emerald-600 text-white rounded-2xl font-black text-sm shadow-lg shadow-emerald-100 hover:bg-emerald-700 transition-all">
                <i class="bi bi-check-lg"></i> อนุมัติ
            </button>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>

<script>
const BASE_PATH = '<?= $base_path ?>';
const CURRENT_USER_ID = <?= (int)$currentUserId ?>;
const IS_ADMIN = <?= $isAdmin ? 'true' : 'false' ?>;

let leaveDataTable = null;
let _currentApprovalItem = null;
let _dirSigCanvas, _dirSigCtx, _dirDrawing = false;

$(document).ready(function() {
    leaveDataTable = $('#leaveTable').DataTable({
        ajax: { 
            url: BASE_PATH + '/teacher_leave/api/get_requests.php', 
            dataSrc: 'data' 
        },
        dom: 'rtip',
        order: [[0, 'desc']],
        columns: [
            { 
                data: 'date_start',
                render: (d, t, r) => {
                    const dt = new Date(d);
                    const monthStr = dt.toLocaleString('th-TH', { month: 'short' });
                    const dayNum = dt.getDate();
                    return `
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl bg-slate-100 flex flex-col items-center justify-center font-black leading-none shrink-0 border border-slate-200">
                                <span class="text-[9px] text-slate-400 uppercase">${monthStr}</span>
                                <span class="text-base text-slate-700">${dayNum}</span>
                            </div>
                            <div>
                                ${IS_ADMIN ? `<div class="text-xs font-black text-rose-600 uppercase tracking-wide mb-0.5">${r.t_name}</div>` : ''}
                                <div class="font-bold text-slate-800 text-sm">${r.leave_type_text}</div>
                                <div class="text-[11px] font-bold text-slate-400 tracking-wider">${d} ถึง ${r.date_end}</div>
                                ${r.reason ? `<div class="text-[11px] text-slate-500 font-medium truncate max-w-[280px] mt-0.5" title="${r.reason}">เหตุผล: ${r.reason}</div>` : ''}
                            </div>
                        </div>
                    `;
                }
            },
            { 
                data: 'days_count',
                className: 'text-center',
                render: (d) => `<div class="font-black text-slate-700 text-base">${d} <span class="text-xs text-slate-400">วัน</span></div>`
            },
            { 
                data: 'status',
                render: (data, t, r) => {
                    if (data === 'approved') {
                        return `<span class="inline-flex items-center gap-1 px-3 py-1.5 rounded-full bg-emerald-50 text-emerald-600 text-xs font-black tracking-wider whitespace-nowrap">
                            <i class="bi bi-check-circle-fill"></i> อนุมัติแล้ว
                        </span>`;
                    }
                    if (data === 'rejected') {
                        return `<span class="inline-flex items-center gap-1 px-3 py-1.5 rounded-full bg-rose-50 text-rose-600 text-xs font-black tracking-wider whitespace-nowrap">
                            <i class="bi bi-x-circle-fill"></i> ไม่อนุมัติ
                        </span>`;
                    }
                    // pending
                    const lvl = parseInt(r.level_at, 10);
                    if (lvl <= 1) {
                        return `<span class="inline-flex items-center gap-1 px-3 py-1.5 rounded-full bg-amber-50 text-amber-600 text-xs font-black tracking-wider whitespace-nowrap">
                            <i class="bi bi-hourglass-split"></i> รอตรวจสอบจากเจ้าหน้าที่
                        </span>`;
                    }
                    if (lvl === 2) {
                        return `<span class="inline-flex items-center gap-1 px-3 py-1.5 rounded-full bg-blue-50 text-blue-600 text-xs font-black tracking-wider whitespace-nowrap">
                            <i class="bi bi-person-check"></i> รออนุมัติ ผอ./รองฯ
                        </span>`;
                    }
                    return `<span class="inline-flex items-center gap-1 px-3 py-1.5 rounded-full bg-slate-50 text-slate-500 text-xs font-black tracking-wider whitespace-nowrap">
                        <i class="bi bi-clock"></i> รอดำเนินการ
                    </span>`;
                }
            },
            {
                data: 'id',
                className: 'text-right',
                render: (id, t, r, meta) => {
                    let html = `<div class="flex items-center justify-end gap-2">`;
                    html += `<a href="${BASE_PATH}/teacher_leave/print_leave.php?id=${id}" target="_blank" class="w-8 h-8 rounded-lg bg-slate-100 text-slate-500 flex items-center justify-center hover:bg-slate-200 transition-all shadow-sm" title="พิมพ์ใบลา"><i class="bi bi-printer-fill"></i></a>`;
                    
                    // ปุ่มยกเลิก
                    if (r.status === 'pending' && parseInt(r.user_id, 10) === CURRENT_USER_ID) {
                        html += `<button type="button" class="btn-cancel-act w-8 h-8 rounded-lg bg-rose-50 text-rose-500 flex items-center justify-center hover:bg-rose-100 transition-all shadow-sm" data-id="${id}" title="ยกเลิกใบลา"><i class="bi bi-trash-fill"></i></button>`;
                    }

                    // ปุ่มอนุมัติสำหรับ Admin
                    if (IS_ADMIN && r.status === 'pending') {
                        html += `<button type="button" class="btn-open-approve px-4 py-2 bg-blue-600 text-white rounded-xl text-xs font-black uppercase tracking-widest hover:scale-105 transition-all shadow-lg shadow-blue-100" data-row-idx="${meta.row}">อนุมัติ</button>`;
                    }
                    
                    html += `</div>`;
                    return html;
                }
            }
        ],
        language: { 
            emptyTable: "ไม่พบข้อมูลการลา",
            zeroRecords: "ไม่พบข้อมูลที่ตรงกับตัวกรอง"
        }
    });

    // Event Delegation สำหรับปุ่มอนุมัติ
    $('#leaveTable').on('click', '.btn-open-approve', function() {
        const rowIdx = $(this).data('row-idx');
        const r = leaveDataTable.row(rowIdx).data();
        if (r) openApproval(r);
    });

    // Event Delegation สำหรับปุ่มยกเลิก
    $('#leaveTable').on('click', '.btn-cancel-act', function() {
        const id = $(this).data('id');
        if (id) cancelRequest(id);
    });

    // Load Stats
    $.get(BASE_PATH + '/teacher_leave/api/get_stats.php', function(res) {
        if(res.status === 'success') {
            const s = res.data;
            renderStatsChart(s);

            let html = `
                <div class="p-4 bg-blue-50/50 rounded-2xl border border-blue-100 flex justify-between items-center group hover:bg-blue-50 transition-all">
                    <div><p class="text-xs font-black text-blue-400 uppercase tracking-widest mb-1">ลาป่วยสะสม</p><p class="text-xl font-black text-blue-700">${s.sick_taken} วัน</p></div>
                    <div class="w-10 h-10 bg-blue-600 text-white rounded-xl flex items-center justify-center text-xl shadow-lg shadow-blue-200 group-hover:scale-110 transition-all"><i class="bi bi-heart-pulse"></i></div>
                </div>
                <div class="p-4 bg-emerald-50/50 rounded-2xl border border-emerald-100 flex justify-between items-center group hover:bg-emerald-50 transition-all">
                    <div><p class="text-xs font-black text-emerald-400 uppercase tracking-widest mb-1">ลากิจสะสม</p><p class="text-xl font-black text-emerald-700">${s.personal_taken} วัน</p></div>
                    <div class="w-10 h-10 bg-emerald-500 text-white rounded-xl flex items-center justify-center text-xl shadow-lg shadow-emerald-200 group-hover:scale-110 transition-all"><i class="bi bi-briefcase"></i></div>
                </div>
                <div class="p-4 bg-indigo-50/50 rounded-2xl border border-indigo-100 flex justify-between items-center group hover:bg-indigo-50 transition-all">
                    <div><p class="text-xs font-black text-indigo-400 uppercase tracking-widest mb-1">ลาพักผ่อนคงเหลือ</p><p class="text-xl font-black text-indigo-700">${s.vacation_quota - s.vacation_taken} / ${s.vacation_quota}</p></div>
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
                    data: [s.sick_taken, s.personal_taken, s.vacation_taken, Math.max(0, s.vacation_quota - s.vacation_taken)],
                    backgroundColor: ['#2563eb', '#10b981', '#6366f1', '#e2e8f0'],
                    borderWidth: 0,
                    hoverOffset: 4
                }]
            },
            options: {
                cutout: '75%',
                plugins: { legend: { display: false } },
                maintainAspectRatio: false
            }
        });
    }

    // Load Next Holiday
    $.get(BASE_PATH + '/teacher_leave/api/get_holidays.php?limit=1', function(res) {
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

    if (IS_ADMIN) {
        updateAdminStats();
    }
});

function updateAdminStats() {
    $.get(BASE_PATH + '/teacher_leave/api/get_admin_stats.php', function(res) {
        if(res.status === 'success') {
            const s = res.data;
            $('#stat-waiting-total').text(s.waiting_total ?? (s.waiting_staff + s.waiting_director));
            $('#stat-waiting-verify').text(s.waiting_staff);
            $('#stat-waiting-director').text(s.waiting_director);
            $('#stat-on-leave-today').text(s.on_leave_today);
        }
    });
}

function filterStatus(status) {
    $('.filter-tab').removeClass('bg-rose-50 text-rose-600').addClass('text-slate-500');
    $(`#btn-f-${status}`).addClass('bg-rose-50 text-rose-600').removeClass('text-slate-500');

    if (status === 'all') {
        leaveDataTable.column(2).search('').draw();
    } else if (status === 'pending') {
        leaveDataTable.column(2).search('รอ').draw();
    } else if (status === 'approved') {
        leaveDataTable.column(2).search('อนุมัติแล้ว').draw();
    } else if (status === 'rejected') {
        leaveDataTable.column(2).search('ไม่อนุมัติ').draw();
    }
}

// ── Approval Modal Functions ──
function openApproval(request) {
    _currentApprovalItem = request;

    document.getElementById('sumApplicant').textContent = request.t_name || 'บุคลากร';
    document.getElementById('sumLeaveType').textContent = request.leave_type_text || request.leave_type;
    document.getElementById('sumDays').textContent = request.days_count || '0';
    document.getElementById('sumDates').textContent = `${request.date_start} ถึง ${request.date_end}`;
    document.getElementById('sumReason').textContent = request.reason || '—';

    const lvl = parseInt(request.level_at, 10);
    if (lvl <= 1) {
        document.getElementById('modalTitle').textContent = 'ตรวจสอบ / พิจารณาใบลา';
        document.getElementById('modalSubtitle').textContent = 'เจ้าหน้าที่ตรวจสอบข้อมูล หรือ ผอ. อนุมัติขั้นสุดท้าย';
        document.getElementById('sigLabel').innerHTML = 'ลายเซ็นผู้ตรวจสอบ/ผู้อนุมัติ <span class="text-rose-500">*</span>';
    } else {
        document.getElementById('modalTitle').textContent = 'อนุมัติใบลา (ขั้นสุดท้าย)';
        document.getElementById('modalSubtitle').textContent = 'ผู้อำนวยการ/รองฯ พิจารณาอนุมัติ';
        document.getElementById('sigLabel').innerHTML = 'ลายเซ็นผู้อำนวยการ/รองฯ <span class="text-rose-500">*</span>';
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

    // Init canvas after modal is displayed
    setTimeout(() => {
        _dirSigCanvas = document.getElementById('directorSigCanvas');
        _dirSigCtx    = _dirSigCanvas.getContext('2d');
        resizeDirectorCanvas();
        clearDirectorSig();
        bindDirectorSig();
    }, 50);
}

function closeApprovalModal() {
    document.getElementById('approvalModal').classList.add('hidden');
    _currentApprovalItem = null;
}

function resizeDirectorCanvas() {
    if (!_dirSigCanvas) return;
    const rect = _dirSigCanvas.getBoundingClientRect();
    if (rect.width <= 0) return;
    _dirSigCanvas.width  = rect.width * (window.devicePixelRatio || 1);
    _dirSigCanvas.height = 140 * (window.devicePixelRatio || 1);
    _dirSigCtx.scale(window.devicePixelRatio || 1, window.devicePixelRatio || 1);
    _dirSigCtx.lineWidth   = 2.5;
    _dirSigCtx.strokeStyle = '#1e293b';
    _dirSigCtx.lineCap     = 'round';
    _dirSigCtx.lineJoin    = 'round';
}

function clearDirectorSig() {
    if (!_dirSigCtx || !_dirSigCanvas) return;
    _dirSigCtx.clearRect(0, 0, _dirSigCanvas.width, _dirSigCanvas.height);
}

function isDirectorSigEmpty() {
    if (!_dirSigCtx || !_dirSigCanvas) return true;
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
    c.onpointerdown = (e) => { 
        _dirDrawing = true; 
        const p = getPos(e); 
        _dirSigCtx.beginPath(); 
        _dirSigCtx.moveTo(p.x, p.y); 
        e.preventDefault(); 
    };
    c.onpointermove = (e) => { 
        if(!_dirDrawing) return; 
        const p = getPos(e); 
        _dirSigCtx.lineTo(p.x, p.y); 
        _dirSigCtx.stroke(); 
        e.preventDefault(); 
    };
    c.onpointerup = () => { _dirDrawing = false; };
}

function submitApprove() {
    if (isDirectorSigEmpty()) {
        Swal.fire({ icon: 'warning', title: 'กรุณาลงนาม', text: 'กรุณาวาดลายเซ็นในกรอบก่อนอนุมัติ', confirmButtonColor: '#2563eb' });
        return;
    }
    const sig = _dirSigCanvas.toDataURL('image/png');
    const comment = document.getElementById('approveComment').value;
    const directFinal = document.getElementById('chkDirectFinal') ? document.getElementById('chkDirectFinal').checked : true;
    
    const reqId = _currentApprovalItem ? _currentApprovalItem.id : null;
    closeApprovalModal();
    processApproval(reqId, 1, comment, sig, directFinal);
}

function submitReject() {
    const comment = document.getElementById('approveComment').value;
    const reqId = _currentApprovalItem ? _currentApprovalItem.id : null;
    closeApprovalModal();
    processApproval(reqId, 2, comment, null, false);
}

async function processApproval(id, status, comment, signature, directFinal) {
    if (!id) return;
    try {
        Swal.fire({ title: 'กำลังประมวลผล...', didOpen: () => Swal.showLoading() });
        const res = await fetch(BASE_PATH + '/teacher_leave/api/approve_leave.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id, status, comment, signature, direct_final: directFinal })
        });
        const json = await res.json();
        if (json.status === 'success') {
            Swal.fire({ icon: 'success', title: 'ดำเนินการสำเร็จ', text: json.message, timer: 1800, showConfirmButton: false });
            leaveDataTable.ajax.reload();
            if (IS_ADMIN) updateAdminStats();
        } else {
            Swal.fire('ข้อผิดพลาด', json.message, 'error');
        }
    } catch (e) {
        Swal.fire('ข้อผิดพลาด', 'เกิดข้อผิดพลาดในการเชื่อมต่อเซิร์ฟเวอร์', 'error');
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
                const res = await fetch(BASE_PATH + '/teacher_leave/api/cancel_request.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id })
                });
                const json = await res.json();
                if (json.status === 'success') {
                    Swal.fire({ icon: 'success', title: 'ยกเลิกสำเร็จ', text: json.message, timer: 1500, showConfirmButton: false });
                    leaveDataTable.ajax.reload();
                    if (IS_ADMIN) updateAdminStats();
                } else {
                    Swal.fire('Error', json.message, 'error');
                }
            } catch (e) {
                Swal.fire('Error', 'ไม่สามารถเชื่อมต่อได้', 'error');
            }
        }
    });
}

document.getElementById('approvalModal').addEventListener('click', function(e) {
    if (e.target === this) closeApprovalModal();
});
</script>

<?php require_once '../components/layout_end.php'; ?>
