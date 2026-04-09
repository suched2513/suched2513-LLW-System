<?php
session_start();
if (!isset($_SESSION['llw_role']) || !in_array($_SESSION['llw_role'], ['super_admin', 'cb_admin'])) {
    header('Location: /login.php?redirect=/chromebook/index.php'); exit();
}
$pageTitle = 'จัดการ Chromebook';
$pageSubtitle = 'ระบบยืม-คืนและตรวจสอบสภาพอุปกรณ์';
$activeSystem = 'chromebook';
require_once '../components/layout_start.php';
?>

<style>
    .modal-bg { position:fixed; inset:0; background:rgba(15,23,42,0.5); backdrop-filter:blur(12px); display:flex; align-items:center; justify-content:center; z-index:1000; opacity:0; pointer-events:none; transition:all .3s ease; }
    .modal-bg.active { opacity:1; pointer-events:auto; }
    .modal-box { background:#fff; border-radius:2rem; padding:2rem; width:90%; transform:scale(0.92) translateY(16px); transition:all .3s cubic-bezier(.34,1.56,.64,1); max-height:90vh; overflow-y:auto; box-shadow:0 25px 60px -12px rgba(0,0,0,0.25); }
    .modal-bg.active .modal-box { transform:scale(1) translateY(0); }
    .spin { animation:spin 1s linear infinite; }
    @keyframes spin { to { transform:rotate(360deg); } }
    .kpi-card { position:relative; overflow:hidden; border-radius:1.5rem; padding:1.5rem; color:#fff; transition:all .3s ease; cursor:default; }
    .kpi-card:hover { transform:translateY(-3px); }
    .kpi-card .kpi-icon { position:absolute; right:-0.5rem; bottom:-0.75rem; font-size:4.5rem; opacity:0.15; transition:all .4s ease; }
    .kpi-card:hover .kpi-icon { opacity:0.25; transform:scale(1.1) rotate(-5deg); }
    .inp { width:100%; background:#f8fafc; border:1.5px solid #e2e8f0; border-radius:0.875rem; padding:0.75rem 1rem; font-size:0.875rem; font-weight:700; outline:none; transition:all .2s; color:#1e293b; }
    .inp:focus { border-color:#06b6d4; box-shadow:0 0 0 3px rgba(6,182,212,0.15); background:#fff; }
    .form-label { display:block; font-size:0.625rem; font-weight:900; color:#94a3b8; text-transform:uppercase; letter-spacing:0.08em; margin-bottom:0.5rem; }
    tr.row-item:hover td { background:rgba(6,182,212,0.04); }
</style>

<div class="flex flex-col gap-6">

    <!-- KPI Cards -->
    <div class="grid grid-cols-2 lg:grid-cols-5 gap-4" id="kpi-cards">
        <div class="col-span-full">
            <div class="bg-gradient-to-r from-cyan-600 to-blue-600 rounded-2xl p-8 flex items-center justify-center gap-3">
                <i class="bi bi-arrow-repeat spin text-white text-xl"></i>
                <span class="text-white font-black">กำลังโหลดข้อมูล...</span>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <!-- Borrow Form -->
        <div class="lg:col-span-1">
            <div class="rounded-2xl overflow-hidden shadow-xl shadow-cyan-100/50 sticky top-28">
                <!-- Card Header Gradient -->
                <div class="bg-gradient-to-br from-cyan-500 to-blue-600 px-6 pt-6 pb-10 relative overflow-hidden">
                    <div class="absolute inset-0 opacity-10" style="background-image:radial-gradient(circle at 80% 20%, white 1px, transparent 1px); background-size:20px 20px;"></div>
                    <div class="relative z-10">
                        <div class="w-11 h-11 bg-white/20 backdrop-blur rounded-2xl flex items-center justify-center text-white text-xl mb-3">
                            <i class="bi bi-laptop"></i>
                        </div>
                        <h3 class="font-black text-white text-lg leading-tight">บันทึกการยืม</h3>
                        <p class="text-cyan-100 text-xs font-bold mt-0.5">กรอกข้อมูลผู้ยืม Chromebook</p>
                    </div>
                </div>
                <!-- Form Body -->
                <div class="bg-white px-6 pb-6 -mt-4 rounded-t-3xl relative z-10">
                    <form id="borrow-form" class="space-y-4 pt-5">
                        <div>
                            <label class="form-label">ประเภทผู้ยืม</label>
                            <select id="borrower-type" class="inp" required>
                                <option value="" disabled selected>— เลือก —</option>
                                <option value="Teacher">👤 ครู</option>
                                <option value="Student">🎓 นักเรียน</option>
                            </select>
                        </div>
                        <div id="cls-wrap" class="hidden">
                            <label class="form-label">ชั้นเรียน</label>
                            <select id="class-select" class="inp"></select>
                        </div>
                        <div id="borrower-wrap" class="hidden">
                            <label class="form-label" id="borrower-label">ชื่อผู้ยืม</label>
                            <select id="borrower-id" class="inp" required></select>
                        </div>
                        <div>
                            <div class="flex items-center justify-between mb-2">
                                <label class="form-label mb-0">Chromebook</label>
                                <button type="button" onclick="openScanner()" class="text-[10px] font-black text-cyan-600 bg-cyan-50 border border-cyan-100 px-3 py-1.5 rounded-lg hover:bg-cyan-100 transition flex items-center gap-1.5">
                                    <i class="bi bi-qr-code-scan"></i> สแกน QR
                                </button>
                            </div>
                            <select id="chromebook-id" class="inp" required>
                                <option>รอข้อมูล...</option>
                            </select>
                        </div>
                        <div class="border-2 border-dashed border-cyan-200 bg-cyan-50/50 rounded-2xl p-5 text-center cursor-pointer hover:bg-cyan-50 transition-all group" onclick="document.getElementById('borrow-imgs').click()">
                            <input type="file" id="borrow-imgs" accept="image/*" multiple class="hidden" onchange="previewImgs(this,'borrow-preview')">
                            <i class="bi bi-camera-fill text-cyan-300 text-2xl group-hover:text-cyan-400 transition"></i>
                            <p class="text-xs text-cyan-500 font-black mt-1">ถ่ายรูปหลักฐาน <span class="opacity-60">(Max 3)</span></p>
                            <div id="borrow-preview" class="flex flex-wrap gap-2 justify-center mt-2"></div>
                        </div>
                        <button type="submit" id="btn-borrow" class="w-full bg-gradient-to-r from-cyan-500 to-blue-600 text-white py-3.5 rounded-2xl font-black text-sm shadow-lg shadow-cyan-200/50 hover:shadow-cyan-300/50 hover:scale-[1.01] active:scale-[0.99] transition-all flex items-center justify-center gap-2">
                            <i class="bi bi-check-circle-fill"></i> บันทึกข้อมูล
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Borrow Log Table -->
        <div class="lg:col-span-2 space-y-4">

            <!-- Toolbar -->
            <div class="bg-white rounded-2xl border border-slate-100 shadow-sm px-4 py-3 flex flex-wrap gap-3 items-center justify-between">
                <div class="flex gap-1.5 flex-wrap" id="tabs-row"></div>
                <div class="flex items-center gap-2">
                    <div class="relative">
                        <i class="bi bi-search absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-300 text-sm"></i>
                        <input type="text" id="search-inp" placeholder="ค้นหา Serial, ชื่อ..." class="w-44 bg-slate-50 border border-slate-200 rounded-xl pl-10 pr-3 py-2 text-sm font-bold outline-none focus:border-cyan-400 focus:ring-2 focus:ring-cyan-100 transition">
                    </div>
                    <button onclick="openMaster()" class="bg-slate-800 text-white px-3.5 py-2 rounded-xl text-xs font-black hover:bg-slate-900 transition flex items-center gap-1.5 shadow-sm">
                        <i class="bi bi-database-gear"></i> ข้อมูลพื้นฐาน
                    </button>
                    <a href="dashboard.php" class="bg-gradient-to-r from-blue-500 to-indigo-600 text-white px-3.5 py-2 rounded-xl text-xs font-black hover:opacity-90 transition flex items-center gap-1.5 shadow-sm shadow-blue-200">
                        <i class="bi bi-bar-chart-fill"></i> รายงาน
                    </a>
                </div>
            </div>

            <!-- Table -->
            <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-slate-50 text-[10px] font-black text-slate-400 uppercase tracking-widest">
                            <tr>
                                <th class="px-5 py-4 text-left">สถานะ</th>
                                <th class="px-5 py-4 text-left">ผู้ยืม / ห้อง</th>
                                <th class="px-5 py-4 text-left">เครื่อง</th>
                                <th class="px-5 py-4 text-left">วันที่</th>
                                <th class="px-5 py-4 text-center">รูป</th>
                                <th class="px-5 py-4 text-right"></th>
                            </tr>
                        </thead>
                        <tbody id="table-body" class="divide-y divide-slate-50">
                            <tr><td colspan="6" class="text-center py-16 text-slate-400 font-bold"><i class="bi bi-arrow-repeat spin mr-1"></i> กำลังโหลด...</td></tr>
                        </tbody>
                    </table>
                </div>
                <div class="px-5 py-3 bg-slate-50/50 border-t border-slate-100 flex items-center justify-between">
                    <span class="text-[10px] font-black text-slate-400 uppercase tracking-widest" id="page-info">–</span>
                    <div class="flex gap-1" id="pagination"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ═══ MODALS ═══════════════════════════════════════════════ -->

<!-- Master Data -->
<div id="modal-master" class="modal-bg">
  <div class="modal-box max-w-2xl">
    <div class="flex items-center justify-between mb-5">
        <h3 class="font-black text-slate-800 text-lg flex items-center gap-2"><i class="bi bi-database-gear text-slate-600"></i> ข้อมูลพื้นฐาน</h3>
        <button onclick="closeModal('modal-master')" class="w-8 h-8 rounded-xl bg-slate-100 hover:bg-slate-200 flex items-center justify-center text-slate-500 transition"><i class="bi bi-x-lg text-sm"></i></button>
    </div>
    <div class="flex gap-1 mb-5 bg-slate-100 p-1 rounded-xl">
        <button class="master-tab flex-1 py-2 rounded-lg text-xs font-black transition active-tab" data-tab="teacher" onclick="switchMasterTab(this,'teacher')">👤 ครู</button>
        <button class="master-tab flex-1 py-2 rounded-lg text-xs font-black transition" data-tab="student" onclick="switchMasterTab(this,'student')">🎓 นักเรียน</button>
        <button class="master-tab flex-1 py-2 rounded-lg text-xs font-black transition" data-tab="chromebook" onclick="switchMasterTab(this,'chromebook')">💻 Chromebook</button>
    </div>

    <!-- Teacher Tab -->
    <div id="mtab-teacher">
        <form onsubmit="saveMaster(event,'teacher')" class="grid grid-cols-3 gap-2 mb-3">
            <input id="m-t-id"   placeholder="รหัสครู"  class="col-span-1 bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-sm font-bold outline-none focus:ring-2 focus:ring-cyan-400" required>
            <input id="m-t-name" placeholder="ชื่อ-สกุล" class="col-span-1 bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-sm font-bold outline-none focus:ring-2 focus:ring-cyan-400" required>
            <button type="submit" class="bg-cyan-600 text-white rounded-xl font-black text-sm hover:bg-cyan-700 transition">+ เพิ่ม</button>
        </form>
        <div class="flex items-center gap-2 mb-3">
            <label class="flex items-center gap-2 cursor-pointer bg-emerald-50 hover:bg-emerald-100 border border-emerald-200 text-emerald-700 text-xs font-black px-4 py-2 rounded-xl transition">
                <i class="bi bi-file-earmark-spreadsheet-fill"></i> นำเข้า CSV
                <input type="file" accept=".csv,.txt" class="hidden" onchange="importCSV(this,'teacher')">
            </label>
            <a href="data:text/csv;charset=utf-8,%EF%BB%BF%E0%B8%A3%E0%B8%AB%E0%B8%B1%E0%B8%AA%E0%B8%84%E0%B8%A3%E0%B8%B9,%E0%B8%8A%E0%B8%B7%E0%B9%88%E0%B8%AD-%E0%B8%AA%E0%B8%81%E0%B8%B8%E0%B8%A5%0AT001,%E0%B8%99%E0%B8%B2%E0%B8%A2%20%E0%B8%AA%E0%B8%A1%E0%B8%84%E0%B8%A3" download="template_teacher.csv" class="text-[10px] font-bold text-slate-400 hover:text-slate-600 transition flex items-center gap-1">
                <i class="bi bi-download"></i> ดาวน์โหลด Template
            </a>
        </div>
        <div id="list-teacher" class="space-y-2 max-h-52 overflow-y-auto"></div>
    </div>

    <!-- Student Tab -->
    <div id="mtab-student" class="hidden">
        <form onsubmit="saveMaster(event,'student')" class="grid grid-cols-4 gap-2 mb-3">
            <input id="m-s-id"    placeholder="รหัส"     class="bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-sm font-bold outline-none focus:ring-2 focus:ring-cyan-400" required>
            <input id="m-s-name"  placeholder="ชื่อ-สกุล" class="bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-sm font-bold outline-none focus:ring-2 focus:ring-cyan-400" required>
            <input id="m-s-cls"   placeholder="ห้อง เช่น ม.4/1" class="bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-sm font-bold outline-none focus:ring-2 focus:ring-cyan-400" required>
            <button type="submit" class="bg-cyan-600 text-white rounded-xl font-black text-sm hover:bg-cyan-700 transition">+ เพิ่ม</button>
        </form>
        <div class="flex items-center gap-2 mb-3">
            <label class="flex items-center gap-2 cursor-pointer bg-emerald-50 hover:bg-emerald-100 border border-emerald-200 text-emerald-700 text-xs font-black px-4 py-2 rounded-xl transition">
                <i class="bi bi-file-earmark-spreadsheet-fill"></i> นำเข้า CSV
                <input type="file" accept=".csv,.txt" class="hidden" onchange="importCSV(this,'student')">
            </label>
            <a href="data:text/csv;charset=utf-8,%EF%BB%BF%E0%B8%A3%E0%B8%AB%E0%B8%B1%E0%B8%AA,%E0%B8%8A%E0%B8%B7%E0%B9%88%E0%B8%AD-%E0%B8%AA%E0%B8%81%E0%B8%B8%E0%B8%A5,%E0%B8%AB%E0%B9%89%E0%B8%AD%E0%B8%87%0A4100,%E0%B8%99%E0%B8%B2%E0%B8%A2%20%E0%B8%AA%E0%B8%A1%E0%B8%84%E0%B8%A3,%E0%B8%A1.4%2F1" download="template_student.csv" class="text-[10px] font-bold text-slate-400 hover:text-slate-600 transition flex items-center gap-1">
                <i class="bi bi-download"></i> ดาวน์โหลด Template
            </a>
        </div>
        <div id="list-student" class="space-y-2 max-h-52 overflow-y-auto"></div>
    </div>

    <!-- Chromebook Tab -->
    <div id="mtab-chromebook" class="hidden">
        <form onsubmit="saveMaster(event,'chromebook')" class="grid grid-cols-4 gap-2 mb-3">
            <input id="m-c-id"     placeholder="รหัส CB-001" class="bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-sm font-bold outline-none focus:ring-2 focus:ring-cyan-400" required>
            <input id="m-c-model"  placeholder="รุ่น"        class="bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-sm font-bold outline-none focus:ring-2 focus:ring-cyan-400" required>
            <input id="m-c-serial" placeholder="Serial No."  class="bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-sm font-bold outline-none focus:ring-2 focus:ring-cyan-400" required>
            <button type="submit" class="bg-cyan-600 text-white rounded-xl font-black text-sm hover:bg-cyan-700 transition">+ เพิ่ม</button>
        </form>
        <div class="flex items-center gap-2 mb-3">
            <label class="flex items-center gap-2 cursor-pointer bg-emerald-50 hover:bg-emerald-100 border border-emerald-200 text-emerald-700 text-xs font-black px-4 py-2 rounded-xl transition">
                <i class="bi bi-file-earmark-spreadsheet-fill"></i> นำเข้า CSV
                <input type="file" accept=".csv,.txt" class="hidden" onchange="importCSV(this,'chromebook')">
            </label>
            <a href="data:text/csv;charset=utf-8,%EF%BB%BF%E0%B8%A3%E0%B8%AB%E0%B8%B1%E0%B8%AA%E0%B9%80%E0%B8%84%E0%B8%A3%E0%B8%B7%E0%B9%88%E0%B8%AD%E0%B8%87,%E0%B8%A3%E0%B8%B8%E0%B9%88%E0%B8%99,Serial%0ACB-001,Acer%20C933,SN123456" download="template_chromebook.csv" class="text-[10px] font-bold text-slate-400 hover:text-slate-600 transition flex items-center gap-1">
                <i class="bi bi-download"></i> ดาวน์โหลด Template
            </a>
        </div>
        <div id="list-chromebook" class="space-y-2 max-h-52 overflow-y-auto"></div>
    </div>
  </div>
</div>

<!-- Edit (add image) -->
<div id="modal-edit" class="modal-bg">
  <div class="modal-box max-w-md">
    <div class="flex items-center justify-between mb-5">
        <h3 class="font-black text-slate-800 text-lg"><i class="bi bi-pencil-square text-blue-500 mr-2"></i>แก้ไขรายการ</h3>
        <button onclick="closeModal('modal-edit')" class="w-8 h-8 rounded-xl bg-slate-100 hover:bg-slate-200 flex items-center justify-center text-slate-500 transition"><i class="bi bi-x-lg text-sm"></i></button>
    </div>
    <form id="edit-form" class="space-y-4">
        <input type="hidden" id="edit-id">
        <div class="bg-slate-50 rounded-xl p-4 text-sm">
            <p class="font-black text-slate-700" id="edit-show-name">–</p>
            <p class="text-slate-400 text-xs font-bold mt-1" id="edit-show-status">–</p>
        </div>
        <div class="border-2 border-dashed border-blue-200 rounded-2xl p-4 text-center cursor-pointer hover:bg-blue-50 transition" onclick="document.getElementById('edit-imgs').click()">
            <input type="file" id="edit-imgs" accept="image/*" multiple class="hidden" onchange="previewImgs(this,'edit-preview')">
            <i class="bi bi-plus-circle text-blue-400 text-2xl"></i>
            <p class="text-xs text-blue-500 font-bold mt-1">เพิ่มรูปหลักฐาน</p>
            <div id="edit-preview" class="flex flex-wrap gap-2 justify-center mt-2"></div>
        </div>
        <button type="submit" class="w-full bg-blue-600 text-white py-3 rounded-xl font-black hover:bg-blue-700 transition">บันทึก</button>
    </form>
  </div>
</div>

<!-- Inspect -->
<div id="modal-inspect" class="modal-bg">
  <div class="modal-box max-w-md">
    <div class="flex items-center justify-between mb-5">
        <h3 class="font-black text-slate-800 text-lg"><i class="bi bi-shield-check text-indigo-500 mr-2"></i>ตรวจสภาพเครื่อง</h3>
        <button onclick="closeModal('modal-inspect')" class="w-8 h-8 rounded-xl bg-slate-100 hover:bg-slate-200 flex items-center justify-center text-slate-500 transition"><i class="bi bi-x-lg text-sm"></i></button>
    </div>
    <form id="inspect-form" class="space-y-4">
        <input type="hidden" id="inspect-id">
        <div class="bg-indigo-50 rounded-xl p-4 text-sm border border-indigo-100">
            <p class="font-black text-slate-700" id="inspect-show-name">–</p>
            <p class="text-indigo-500 text-xs font-bold mt-1" id="inspect-show-cb">–</p>
        </div>
        <div>
            <label class="block text-xs font-black text-slate-400 uppercase tracking-wider mb-2">สภาพเครื่อง</label>
            <select id="inspect-cond" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-sm font-bold outline-none focus:ring-2 focus:ring-indigo-400" required>
                <option value="Normal">✅ สภาพปกติ</option>
                <option value="Damaged">⚠️ ชำรุด/เสียหาย</option>
                <option value="Lost">🚨 สูญหาย</option>
            </select>
        </div>
        <div>
            <label class="block text-xs font-black text-slate-400 uppercase tracking-wider mb-2">หมายเหตุ</label>
            <textarea id="inspect-notes" rows="2" placeholder="เช่น หน้าจอร้าว, สายชาร์จหาย..." class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-sm font-bold outline-none focus:ring-2 focus:ring-indigo-400"></textarea>
        </div>
        <div class="border-2 border-dashed border-indigo-200 rounded-2xl p-4 text-center cursor-pointer hover:bg-indigo-50 transition" onclick="document.getElementById('inspect-imgs').click()">
            <input type="file" id="inspect-imgs" accept="image/*" multiple class="hidden" onchange="previewImgs(this,'inspect-preview')">
            <i class="bi bi-camera text-indigo-300 text-2xl"></i>
            <p class="text-xs text-indigo-400 font-bold mt-1">ถ่ายรูปสภาพเครื่อง</p>
            <div id="inspect-preview" class="flex flex-wrap gap-2 justify-center mt-2"></div>
        </div>
        <button type="submit" class="w-full bg-indigo-600 text-white py-3 rounded-xl font-black hover:bg-indigo-700 transition">บันทึกผลตรวจสอบ</button>
    </form>
  </div>
</div>

<!-- QR Scanner -->
<div id="modal-scanner" class="modal-bg z-[1200]">
  <div class="modal-box max-w-sm">
    <div class="flex items-center justify-between mb-4">
        <h3 class="font-black text-slate-800"><i class="bi bi-qr-code-scan text-cyan-600 mr-2"></i>สแกน QR / Barcode</h3>
        <button onclick="closeScanner()" class="w-8 h-8 rounded-xl bg-slate-100 hover:bg-slate-200 flex items-center justify-center text-slate-500 transition"><i class="bi bi-x-lg text-sm"></i></button>
    </div>
    <div id="qr-reader" class="w-full rounded-2xl overflow-hidden border-2 border-cyan-200" style="min-height:220px"></div>
    <div id="scan-result" class="hidden mt-3 bg-cyan-50 border border-cyan-200 rounded-xl p-3 text-sm font-bold text-cyan-700"></div>
    <p class="text-[10px] text-slate-400 text-center mt-3 font-bold">สแกนรหัสนักเรียน/ครู → แล้วสแกนรหัสเครื่อง</p>
  </div>
</div>

<!-- Image Viewer -->
<div id="modal-img" class="modal-bg z-[1300]" onclick="closeModal('modal-img')">
  <div class="relative max-w-4xl mx-4" onclick="event.stopPropagation()">
    <img id="modal-img-src" src="" class="max-w-full max-h-[90vh] object-contain rounded-2xl shadow-2xl">
  </div>
</div>

<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
<script>
// ── State ───────────────────────────────────────────────────────
const S = { logs:[], teachers:[], students:[], chromebooks:[], page:1, limit:15, tab:'all' };
let qrScanner = null;

// ── API ─────────────────────────────────────────────────────────
async function api(action, payload={}) {
    const r = await fetch('api.php?action=' + action, {
        method:'POST', headers:{'Content-Type':'application/json'},
        body: JSON.stringify({action, payload})
    });
    return r.json();
}

async function loadAll() {
    const [logs, teachers, students, chromebooks] = await Promise.all([
        api('getData',{sheetName:'BorrowLog'}),
        api('getData',{sheetName:'Teachers'}),
        api('getData',{sheetName:'Students'}),
        api('getData',{sheetName:'Chromebooks'})
    ]);
    if (logs.success)        S.logs        = logs.data;
    if (teachers.success)    S.teachers    = teachers.data;
    if (students.success)    S.students    = students.data;
    if (chromebooks.success) S.chromebooks = chromebooks.data;
    renderKPI(); renderTabs(); renderTable(); renderDropdowns();
}

// ── Helpers ─────────────────────────────────────────────────────
function getName(row) {
    if (row[1]==='Teacher') { const t=S.teachers.find(x=>String(x[0])===String(row[2])); return t?t[1]:row[2]; }
    const s=S.students.find(x=>String(x[0])===String(row[2])); return s?s[1]:row[2];
}
function fmtDate(d) { try{ return new Date(d).toLocaleString('th-TH',{day:'numeric',month:'short',year:'2-digit',hour:'2-digit',minute:'2-digit'}); }catch{return d||'–'; } }
async function blobsFrom(fileInput) {
    return Promise.all(Array.from(fileInput.files).slice(0,3).map(f=>new Promise(res=>{const r=new FileReader();r.onload=e=>res(e.target.result);r.readAsDataURL(f);})));
}

// ── KPI ─────────────────────────────────────────────────────────
function renderKPI() {
    const borrowed  = S.logs.filter(r=>r[7]==='Borrowed').length;
    const available = S.chromebooks.length - borrowed;
    const now = new Date();
    const overdue = S.logs.filter(r=>r[7]==='Borrowed'&&(now-new Date(r[8]))/(864e5)>2).length;
    const cards = [
        {label:'อุปกรณ์ทั้งหมด', val:S.chromebooks.length, icon:'bi-laptop',                  grad:'from-cyan-500 to-blue-600',    shadow:'shadow-cyan-200/50'},
        {label:'กำลังยืมอยู่',   val:borrowed,              icon:'bi-hand-index-thumb-fill',   grad:'from-amber-400 to-orange-500', shadow:'shadow-amber-200/50'},
        {label:'ว่างพร้อมใช้',   val:available,             icon:'bi-check-circle-fill',        grad:'from-emerald-500 to-teal-500', shadow:'shadow-emerald-200/50'},
        {label:'ค้างคืน >2 วัน', val:overdue,               icon:'bi-exclamation-triangle-fill', grad:'from-rose-500 to-pink-600',    shadow:'shadow-rose-200/50'},
        {label:'ครูในระบบ',      val:S.teachers.length,     icon:'bi-person-badge-fill',        grad:'from-violet-500 to-purple-600',shadow:'shadow-violet-200/50'},
    ];
    document.getElementById('kpi-cards').innerHTML = cards.map(c=>`
        <div class="kpi-card bg-gradient-to-br ${c.grad} shadow-xl ${c.shadow}">
            <p class="text-[10px] font-black uppercase tracking-widest opacity-80 mb-1">${c.label}</p>
            <p class="text-4xl font-black tracking-tight">${c.val}</p>
            <i class="bi ${c.icon} kpi-icon"></i>
        </div>`).join('');
}

// ── Tabs ─────────────────────────────────────────────────────────
function renderTabs() {
    const classes = [...new Set(S.logs.filter(r=>r[1]==='Student').map(r=>r[3]).filter(Boolean))].sort();
    const tabs = [{key:'all',label:'🗂 ทั้งหมด'},{key:'Teacher',label:'👤 ครู'},...classes.map(c=>({key:c,label:c}))];
    document.getElementById('tabs-row').innerHTML = tabs.map(t=>`
        <button onclick="setTab('${t.key}')" data-tab="${t.key}"
            class="tab-btn px-3 py-1.5 rounded-xl text-[11px] font-black transition-all ${
                S.tab===t.key
                    ? 'bg-gradient-to-r from-cyan-500 to-blue-600 text-white shadow-md shadow-cyan-200/50 scale-105'
                    : 'bg-slate-100 text-slate-500 hover:bg-slate-200'
            }">${t.label}</button>
    `).join('');
}
function setTab(t) { S.tab=t; S.page=1; renderTabs(); renderTable(); }

// ── Table ────────────────────────────────────────────────────────
function renderTable() {
    const q = document.getElementById('search-inp').value.toLowerCase();
    const data = S.logs.filter(r=>{
        const matchTab = S.tab==='all'||r[1]==='Teacher'&&S.tab==='Teacher'||r[3]===S.tab;
        const matchQ   = !q || [getName(r),r[4],r[5],r[3]||''].some(v=>String(v).toLowerCase().includes(q));
        return matchTab && matchQ;
    });
    const total      = data.length;
    const totalPages = Math.max(1,Math.ceil(total/S.limit));
    S.page = Math.min(S.page, totalPages);
    const slice = data.slice((S.page-1)*S.limit, S.page*S.limit);

    document.getElementById('page-info').textContent = `${total} รายการ`;
    document.getElementById('table-body').innerHTML = slice.length ? slice.map(r=>{
        const isBorrowed = r[7]==='Borrowed';
        const name       = getName(r);
        const dateStr    = fmtDate(r[8]);
        const imgs = r[6]?r[6].split(',').filter(Boolean):[];
        const imgHtml = imgs.length
            ? `<div class="flex -space-x-2">${imgs.slice(0,3).map(i=>`<img src="uploads/${i}" class="w-9 h-9 rounded-xl ring-2 ring-white object-cover cursor-pointer hover:z-10 hover:scale-110 transition" onclick="viewImg('uploads/${i}')">`).join('')}${imgs.length>3?`<div class="w-9 h-9 rounded-xl bg-slate-100 text-slate-400 text-[10px] font-black flex items-center justify-center ring-2 ring-white">+${imgs.length-3}</div>`:''}</div>`
            : `<span class="text-slate-300 text-[10px] font-bold">—</span>`;
        const nowD = new Date(), daysAgo = (nowD-new Date(r[8]))/(864e5);
        const overdue = isBorrowed && daysAgo>2;
        const statusBadge = isBorrowed
            ? `<span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[10px] font-black bg-gradient-to-r from-amber-400 to-orange-400 text-white shadow-sm"><i class="bi bi-hand-index-thumb-fill text-[8px]"></i>ยืมอยู่</span>`
            : `<span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[10px] font-black bg-gradient-to-r from-emerald-400 to-teal-500 text-white shadow-sm"><i class="bi bi-check-lg text-[8px]"></i>คืนแล้ว</span>`;
        return `<tr class="hover:bg-slate-50/50 transition ${overdue?'bg-rose-50/30':''}">
            <td class="px-5 py-4">
                <span class="px-2.5 py-1 rounded-full text-[10px] font-black ${isBorrowed?'bg-amber-100 text-amber-700':'bg-emerald-100 text-emerald-700'}">${isBorrowed?'ยืมอยู่':'คืนแล้ว'}</span>
                ${overdue?`<div class="text-[9px] font-black text-rose-500 mt-0.5"><i class="bi bi-exclamation-triangle-fill"></i> ค้าง ${Math.floor(daysAgo)} วัน</div>`:''}
            </td>
            <td class="px-5 py-4"><p class="font-bold text-slate-700">${name}</p><p class="text-[10px] text-slate-400 font-bold">${r[3]||r[1]}</p></td>
            <td class="px-5 py-4"><p class="font-mono font-black text-xs text-cyan-600">${r[4]}</p><p class="text-[9px] text-slate-300 font-bold">${r[5]}</p></td>
            <td class="px-5 py-4 text-xs text-slate-400 font-bold">${dateStr}</td>
            <td class="px-5 py-4 text-center">${imgHtml}</td>
            <td class="px-5 py-4 text-right">
                <div class="flex items-center justify-end gap-1">
                    <button onclick="openEdit('${r[0]}')" class="p-2 text-blue-500 hover:bg-blue-50 rounded-xl transition" title="แก้ไข/เพิ่มรูป"><i class="bi bi-pencil-square"></i></button>
                    ${isBorrowed?`<button onclick="openInspect('${r[0]}')" class="p-2 text-indigo-500 hover:bg-indigo-50 rounded-xl transition" title="ตรวจสภาพ"><i class="bi bi-shield-check"></i></button>
                    <button onclick="doReturn('${r[0]}')" class="p-2 text-emerald-500 hover:bg-emerald-50 rounded-xl transition" title="คืนเครื่อง"><i class="bi bi-box-arrow-in-left"></i></button>`:''}
                    <button onclick="doDelete('${r[0]}')" class="p-2 text-rose-400 hover:bg-rose-50 rounded-xl transition" title="ลบ"><i class="bi bi-trash3"></i></button>
                </div>
            </td>
        </tr>`;
    }).join('') : `<tr><td colspan="6" class="text-center py-16 text-slate-400 font-bold">ไม่พบข้อมูล</td></tr>`;

    // Pagination
    let pg = '';
    const tp = Math.ceil(data.length/S.limit);
    for(let i=1;i<=tp;i++){
        if(tp>8&&i!==1&&i!==tp&&Math.abs(i-S.page)>2){if(!pg.endsWith('</span>'))pg+=`<span class="px-1 text-slate-200">…</span>`;continue;}
        pg+=`<button onclick="S.page=${i};renderTable()" class="w-8 h-8 rounded-xl text-xs font-black transition-all ${i===S.page?'bg-gradient-to-r from-cyan-500 to-blue-600 text-white shadow-md':'bg-slate-100 text-slate-500 hover:bg-slate-200'}">${i}</button>`;
    }
    document.getElementById('pagination').innerHTML = pg;
}

// ── Borrow Form ───────────────────────────────────────────────────
function renderDropdowns() {
    const borrowed = S.logs.filter(r=>r[7]==='Borrowed').map(r=>String(r[4]));
    const avail    = S.chromebooks.filter(c=>!borrowed.includes(String(c[0])));
    document.getElementById('chromebook-id').innerHTML =
        `<option value="" disabled selected>— ว่าง ${avail.length} เครื่อง —</option>`+
        avail.map(c=>`<option value="${c[0]}">${c[0]} – ${c[1]} (${c[2]})</option>`).join('');
}

document.getElementById('borrower-type').addEventListener('change', function(){
    const type = this.value;
    const activeIds = S.logs.filter(r=>r[7]==='Borrowed').map(r=>String(r[2]));
    document.getElementById('cls-wrap').classList.toggle('hidden', type==='Teacher');
    document.getElementById('borrower-wrap').classList.remove('hidden');
    document.getElementById('borrower-label').textContent = type==='Teacher'?'เลือกครู':'เลือกนักเรียน';
    const sel = document.getElementById('borrower-id');
    if (type==='Teacher') {
        const avail = S.teachers.filter(t=>!activeIds.includes(String(t[0])));
        sel.innerHTML = `<option value="" disabled selected>— เลือกครู —</option>`+avail.map(t=>`<option value="${t[0]}">${t[1]}</option>`).join('');
    } else {
        const classes = [...new Set(S.students.map(s=>s[2]))].sort();
        document.getElementById('class-select').innerHTML = `<option value="" disabled selected>— เลือกชั้น —</option>`+classes.map(c=>`<option value="${c}">${c}</option>`).join('');
        sel.innerHTML = `<option value="" disabled selected>— เลือกชั้นก่อน —</option>`;
        document.getElementById('class-select').onchange = function(){
            const cls = this.value;
            const avail = S.students.filter(s=>s[2]===cls&&!activeIds.includes(String(s[0])));
            sel.innerHTML = `<option value="" disabled selected>— เลือกนักเรียน —</option>`+avail.map(s=>`<option value="${s[0]}">${s[1]}</option>`).join('');
        };
    }
});

document.getElementById('borrow-form').addEventListener('submit', async e=>{
    e.preventDefault();
    const btn = document.getElementById('btn-borrow');
    btn.disabled=true; btn.innerHTML='<i class="bi bi-arrow-repeat spin mr-1"></i> กำลังบันทึก...';
    const blobs = await blobsFrom(document.getElementById('borrow-imgs'));
    const res = await api('addBorrow',{
        borrowerType: document.getElementById('borrower-type').value,
        borrowerId:   document.getElementById('borrower-id').value,
        className:    document.getElementById('class-select').value||null,
        chromebookId: document.getElementById('chromebook-id').value,
        imageBlobs:   blobs
    });
    if (res.success) {
        Swal.fire({icon:'success',title:'บันทึกสำเร็จ',timer:1500,showConfirmButton:false});
        e.target.reset();
        document.getElementById('cls-wrap').classList.add('hidden');
        document.getElementById('borrower-wrap').classList.add('hidden');
        document.getElementById('borrow-preview').innerHTML='';
        await loadAll();
    } else Swal.fire('ผิดพลาด', res.error, 'error');
    btn.disabled=false; btn.innerHTML='<i class="bi bi-check-lg"></i> บันทึกข้อมูล';
});

// ── Actions ───────────────────────────────────────────────────────
async function doReturn(id) {
    const r = await Swal.fire({title:'ยืนยันการคืน?',icon:'question',showCancelButton:true,confirmButtonColor:'#059669',confirmButtonText:'ยืนยัน',cancelButtonText:'ยกเลิก'});
    if(!r.isConfirmed) return;
    const res = await api('returnBorrow',{entryId:id});
    res.success ? (Swal.fire({icon:'success',title:'คืนเครื่องแล้ว',timer:1500,showConfirmButton:false}), loadAll()) : Swal.fire('ผิดพลาด',res.error,'error');
}
async function doDelete(id) {
    const r = await Swal.fire({title:'ลบรายการนี้?',text:'ข้อมูลจะหายถาวร',icon:'warning',showCancelButton:true,confirmButtonColor:'#e11d48',confirmButtonText:'ลบเลย',cancelButtonText:'ยกเลิก'});
    if(!r.isConfirmed) return;
    const res = await api('deleteBorrow',{entryId:id});
    res.success ? (Swal.fire({icon:'success',title:'ลบแล้ว',timer:1200,showConfirmButton:false}), loadAll()) : Swal.fire('ผิดพลาด',res.error,'error');
}

// ── Edit modal ────────────────────────────────────────────────────
function openEdit(id) {
    const row = S.logs.find(r=>String(r[0])===String(id)); if(!row) return;
    document.getElementById('edit-id').value = id;
    document.getElementById('edit-show-name').textContent = getName(row)+' '+(row[3]?`(${row[3]})`:'');
    document.getElementById('edit-show-status').textContent = row[7]==='Borrowed'?'กำลังยืม':'คืนแล้ว';
    document.getElementById('edit-preview').innerHTML='';
    document.getElementById('edit-imgs').value='';
    openModal('modal-edit');
}
document.getElementById('edit-form').addEventListener('submit', async e=>{
    e.preventDefault();
    const btn=e.target.querySelector('button[type=submit]'); btn.disabled=true; btn.textContent='กำลังบันทึก...';
    const blobs = await blobsFrom(document.getElementById('edit-imgs'));
    const res = await api('editBorrow',{entryId:document.getElementById('edit-id').value, newImageBlobs:blobs});
    if(res.success){ Swal.fire({icon:'success',title:'อัปเดตแล้ว',timer:1200,showConfirmButton:false}); closeModal('modal-edit'); loadAll(); }
    else Swal.fire('ผิดพลาด',res.error,'error');
    btn.disabled=false; btn.textContent='บันทึก';
});

// ── Inspect modal ─────────────────────────────────────────────────
function openInspect(id) {
    const row = S.logs.find(r=>String(r[0])===String(id)); if(!row) return;
    document.getElementById('inspect-id').value = id;
    document.getElementById('inspect-show-name').textContent = getName(row)+' '+(row[3]?`(${row[3]})`:'');
    document.getElementById('inspect-show-cb').textContent = `${row[4]} (${row[5]})`;
    document.getElementById('inspect-cond').value='Normal';
    document.getElementById('inspect-notes').value='';
    document.getElementById('inspect-preview').innerHTML='';
    document.getElementById('inspect-imgs').value='';
    openModal('modal-inspect');
}
document.getElementById('inspect-form').addEventListener('submit', async e=>{
    e.preventDefault();
    const btn=e.target.querySelector('button[type=submit]'); btn.disabled=true; btn.textContent='กำลังบันทึก...';
    const blobs = await blobsFrom(document.getElementById('inspect-imgs'));
    const res = await api('addInspection',{
        entryId: document.getElementById('inspect-id').value,
        condition: document.getElementById('inspect-cond').value,
        notes: document.getElementById('inspect-notes').value,
        imageBlobs: blobs
    });
    if(res.success){ Swal.fire({icon:'success',title:'บันทึกการตรวจสอบแล้ว',timer:1400,showConfirmButton:false}); closeModal('modal-inspect'); loadAll(); }
    else Swal.fire('ผิดพลาด',res.error,'error');
    btn.disabled=false; btn.textContent='บันทึกผลตรวจสอบ';
});

// ── Master Data ───────────────────────────────────────────────────
function openMaster() { renderMasterList(); openModal('modal-master'); }
function switchMasterTab(btn, tab) {
    document.querySelectorAll('.master-tab').forEach(b=>{b.classList.remove('active-tab','bg-white','text-slate-800','shadow-sm');b.classList.add('text-slate-500');});
    btn.classList.add('active-tab','bg-white','text-slate-800','shadow-sm'); btn.classList.remove('text-slate-500');
    ['teacher','student','chromebook'].forEach(t=>document.getElementById('mtab-'+t).classList.toggle('hidden',t!==tab));
    renderMasterList(tab);
}
function renderMasterList(tab) {
    // Teachers
    document.getElementById('list-teacher').innerHTML = S.teachers.length
        ? S.teachers.map(t=>`<div class="flex items-center justify-between bg-slate-50 rounded-xl px-4 py-2.5">
            <div><p class="font-bold text-sm text-slate-700">${t[1]}</p><p class="font-mono text-[10px] text-slate-400">${t[0]}</p></div>
            <button onclick="delMaster('teacher','${t[0]}')" class="text-rose-400 hover:text-rose-600 transition p-1"><i class="bi bi-trash3 text-sm"></i></button>
        </div>`).join('')
        : '<p class="text-center text-slate-400 text-sm py-4 font-bold">ยังไม่มีข้อมูลครู</p>';
    // Students
    document.getElementById('list-student').innerHTML = S.students.length
        ? S.students.map(s=>`<div class="flex items-center justify-between bg-slate-50 rounded-xl px-4 py-2.5">
            <div><p class="font-bold text-sm text-slate-700">${s[1]} <span class="text-[10px] text-emerald-600 font-black bg-emerald-50 px-2 py-0.5 rounded-lg">${s[2]}</span></p><p class="font-mono text-[10px] text-slate-400">${s[0]}</p></div>
            <button onclick="delMaster('student','${s[0]}')" class="text-rose-400 hover:text-rose-600 transition p-1"><i class="bi bi-trash3 text-sm"></i></button>
        </div>`).join('')
        : '<p class="text-center text-slate-400 text-sm py-4 font-bold">ยังไม่มีข้อมูลนักเรียน</p>';
    // Chromebooks
    document.getElementById('list-chromebook').innerHTML = S.chromebooks.length
        ? S.chromebooks.map(c=>`<div class="flex items-center justify-between bg-slate-50 rounded-xl px-4 py-2.5">
            <div><p class="font-bold text-sm text-slate-700">${c[0]} – ${c[1]}</p><p class="font-mono text-[10px] text-slate-400">${c[2]}</p></div>
            <button onclick="delMaster('chromebook','${c[0]}')" class="text-rose-400 hover:text-rose-600 transition p-1"><i class="bi bi-trash3 text-sm"></i></button>
        </div>`).join('')
        : '<p class="text-center text-slate-400 text-sm py-4 font-bold">ยังไม่มีข้อมูลอุปกรณ์</p>';
}
async function saveMaster(e, type) {
    e.preventDefault();
    let payload = {};
    if (type==='teacher')    payload = {teacher_id:document.getElementById('m-t-id').value, name:document.getElementById('m-t-name').value};
    else if (type==='student') payload = {student_id:document.getElementById('m-s-id').value, name:document.getElementById('m-s-name').value, class_name:document.getElementById('m-s-cls').value};
    else payload = {chromebook_id:document.getElementById('m-c-id').value, model:document.getElementById('m-c-model').value, serial_number:document.getElementById('m-c-serial').value};

    const action = type==='teacher'?'addTeacher':type==='student'?'addStudent':'addChromebook';
    const res = await api(action, payload);
    if (res.success) { e.target.reset(); await loadAll(); renderMasterList(type); Swal.fire({icon:'success',title:'บันทึกแล้ว',timer:1000,showConfirmButton:false}); }
    else Swal.fire('ผิดพลาด', res.error, 'error');
}
async function delMaster(type, id) {
    const r = await Swal.fire({title:'ลบข้อมูล?',icon:'warning',showCancelButton:true,confirmButtonColor:'#e11d48',confirmButtonText:'ลบ',cancelButtonText:'ยกเลิก'});
    if(!r.isConfirmed) return;
    const action = type==='teacher'?'deleteTeacher':type==='student'?'deleteStudent':'deleteChromebook';
    const key    = type==='teacher'?'teacher_id':type==='student'?'student_id':'chromebook_id';
    const res = await api(action, {[key]:id});
    if(res.success){ await loadAll(); renderMasterList(type); }
    else Swal.fire('ผิดพลาด',res.error,'error');
}

// ── QR Scanner ────────────────────────────────────────────────────
function openScanner() {
    openModal('modal-scanner');
    document.getElementById('scan-result').classList.add('hidden');
    qrScanner = new Html5Qrcode('qr-reader');
    qrScanner.start({facingMode:'environment'},{fps:10,qrbox:{width:200,height:200}},onScan,()=>{}).catch(()=>{
        Swal.fire('ไม่สามารถเปิดกล้อง','กรุณาใช้ HTTPS หรือ localhost','warning'); closeScanner();
    });
}
function closeScanner() {
    closeModal('modal-scanner');
    if(qrScanner) qrScanner.stop().catch(()=>{}).finally(()=>{ qrScanner=null; document.getElementById('qr-reader').innerHTML=''; });
}
function onScan(text) {
    const student = S.students.find(s=>String(s[0])===text);
    const teacher = S.teachers.find(t=>String(t[0])===text);
    const cb      = S.chromebooks.find(c=>String(c[0])===text);
    let msg = '';
    if (student) {
        document.getElementById('borrower-type').value='Student'; document.getElementById('borrower-type').dispatchEvent(new Event('change'));
        setTimeout(()=>{const cs=document.getElementById('class-select');cs.value=student[2];cs.dispatchEvent(new Event('change'));setTimeout(()=>{document.getElementById('borrower-id').value=student[0];},100);},100);
        msg = `🎓 ${student[1]} (${student[2]})`;
    } else if (teacher) {
        document.getElementById('borrower-type').value='Teacher'; document.getElementById('borrower-type').dispatchEvent(new Event('change'));
        setTimeout(()=>{document.getElementById('borrower-id').value=teacher[0];},100);
        msg = `👤 ${teacher[1]}`;
    } else if (cb) {
        document.getElementById('chromebook-id').value=cb[0];
        msg = `💻 ${cb[0]} – ${cb[1]}`;
    } else { msg = `ไม่พบรหัส: ${text}`; }
    const rd = document.getElementById('scan-result'); rd.classList.remove('hidden'); rd.textContent=msg;
}

// ── Utilities ─────────────────────────────────────────────────────
function openModal(id)  { document.getElementById(id).classList.add('active'); }
function closeModal(id) { document.getElementById(id).classList.remove('active'); }
function viewImg(url)   { document.getElementById('modal-img-src').src=url; openModal('modal-img'); }
function previewImgs(inp, cId) {
    const c=document.getElementById(cId); c.innerHTML='';
    Array.from(inp.files).slice(0,3).forEach(f=>{ const r=new FileReader(); r.onload=e=>{const img=document.createElement('img');img.src=e.target.result;img.className='w-16 h-16 object-cover rounded-xl border border-slate-200 shadow-sm';c.appendChild(img);}; r.readAsDataURL(f); });
}
document.getElementById('search-inp').addEventListener('input',()=>{ clearTimeout(window._st); window._st=setTimeout(()=>{S.page=1;renderTable();},250); });

// Init tab style
document.querySelector('.master-tab').classList.add('bg-white','text-slate-800','shadow-sm');

// ── CSV Import ─────────────────────────────────────────────────────
function parseCSV(text) {
    return text.trim().split(/\r?\n/).map(line => {
        // Handle quoted fields
        const cols = []; let cur = '', inQ = false;
        for (let i = 0; i < line.length; i++) {
            const ch = line[i];
            if (ch === '"') { inQ = !inQ; }
            else if (ch === ',' && !inQ) { cols.push(cur.trim()); cur = ''; }
            else { cur += ch; }
        }
        cols.push(cur.trim());
        return cols;
    });
}

async function importCSV(input, type) {
    const file = input.files[0]; if (!file) return;
    input.value = ''; // reset so same file can re-trigger

    const text = await file.text();
    const allRows = parseCSV(text);
    // Skip header row (if first cell looks like text label, not a code)
    const headers = allRows[0];
    const isHeader = isNaN(headers[0]) && !/^CB-/i.test(headers[0]);
    const dataRows = isHeader ? allRows.slice(1) : allRows;
    const valid = dataRows.filter(r => r.some(c => c.trim() !== ''));

    if (!valid.length) { Swal.fire('ไม่พบข้อมูล', 'CSV ว่างเปล่าหรือรูปแบบไม่ถูกต้อง', 'warning'); return; }

    const labels = { teacher: ['รหัสครู','ชื่อ-สกุล'], student: ['รหัส','ชื่อ-สกุล','ห้อง'], chromebook: ['รหัส','รุ่น','Serial'] };
    const cols   = labels[type];
    const previewRows = valid.slice(0, 5).map(r => `<tr>${r.slice(0,cols.length).map(c=>`<td class="px-3 py-1.5 border-b border-slate-100 text-xs text-slate-600">${c}</td>`).join('')}</tr>`).join('');
    const moreTxt = valid.length > 5 ? `<p class="text-[10px] text-slate-400 font-bold mt-2">... และอีก ${valid.length-5} แถว</p>` : '';

    const confirm = await Swal.fire({
        title: `นำเข้า ${valid.length} แถว?`,
        html: `<div class="text-left overflow-x-auto"><table class="min-w-full"><thead><tr>${cols.map(c=>`<th class="px-3 py-2 text-[10px] font-black text-slate-400 uppercase">${c}</th>`).join('')}</tr></thead><tbody>${previewRows}</tbody></table>${moreTxt}</div>`,
        icon: 'question', showCancelButton: true,
        confirmButtonColor: '#0891b2', confirmButtonText: `นำเข้า ${valid.length} แถว`,
        cancelButtonText: 'ยกเลิก'
    });
    if (!confirm.isConfirmed) return;

    const res = await api('importCSV', { type, rows: valid });
    if (res.success) {
        const d = res.data;
        Swal.fire({ icon:'success', title:'นำเข้าสำเร็จ', text:`บันทึก ${d.inserted} แถว${d.skipped?` / ข้าม ${d.skipped} แถว`:''}`, timer:2000, showConfirmButton:false });
        await loadAll(); renderMasterList(type);
    } else {
        Swal.fire('ผิดพลาด', res.error, 'error');
    }
}

document.addEventListener('DOMContentLoaded', loadAll);
</script>

<?php require_once '../components/layout_end.php'; ?>
