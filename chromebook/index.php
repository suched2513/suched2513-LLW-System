<?php
session_start();
// Auth: super_admin or cb_admin
if (!isset($_SESSION['llw_role']) || !in_array($_SESSION['llw_role'], ['super_admin', 'cb_admin'])) {
    header('Location: ../login.php'); exit();
}

$pageTitle = 'จัดการ Chromebook';
$pageSubtitle = 'ระบบยืม-คืนและตรวจสอบสภาพอุปกรณ์';
$activeSystem = 'chromebook';

require_once '../components/layout_start.php';
?>

<style>
    .cb-card { background: #fff; border-radius: 2rem; box-shadow: 0 4px 20px rgba(0,0,0,0.03); padding: 1.5rem; border: 1px solid rgba(0,0,0,0.05); }
    .loader { border: 3px solid #f3f3f3; border-radius: 50%; border-top: 3px solid #3b82f6; width: 20px; height: 20px; animation: spin 1s linear infinite; display: inline-block; }
    @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
    .modal-bg { position: fixed; inset: 0; background: rgba(15, 23, 42, 0.4); backdrop-filter: blur(8px); display: flex; align-items: center; justify-content: center; z-index: 1000; opacity: 0; pointer-events: none; transition: all 0.3s ease; }
    .modal-bg.active { opacity: 1; pointer-events: auto; }
    .modal-content { background: #fff; border-radius: 2.5rem; padding: 2.5rem; max-width: 32rem; width: 90%; transform: scale(0.9) translateY(20px); transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1); border: 1px solid rgba(255,255,255,0.5); }
    .modal-bg.active .modal-content { transform: scale(1) translateY(0); }
</style>

<div class="flex flex-col gap-8">

    <!-- Summary Cards -->
    <div class="grid grid-cols-2 lg:grid-cols-5 gap-6" id="dashboard-cards">
        <div class="col-span-full text-center py-10 text-slate-400 font-bold"><div class="loader mr-2"></div> กำลังดึงข้อมูลสถิติ...</div>
    </div>

    <!-- Main Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Left: Borrow Form -->
        <div class="lg:col-span-1 space-y-6">
            <div id="borrow-form-card" class="cb-card bg-white sticky top-28">
                <h3 class="text-lg font-black text-slate-800 mb-6 flex items-center gap-3">
                    <i class="bi bi-file-earmark-plus-fill text-blue-600"></i> บันทึกรายการยืมใหม่
                </h3>
                <form id="borrow-form" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1">ประเภทผู้ยืม</label>
                        <select id="borrower-type" class="w-full border border-slate-200 bg-slate-50 p-2.5 rounded-xl text-sm" required>
                            <option value="" disabled selected>-- เลือก --</option>
                            <option value="Teacher">ครู</option>
                            <option value="Student">นักเรียน</option>
                        </select>
                    </div>
                    <div id="dynamic-borrower-fields" class="space-y-4 hidden">
                        <div id="class-select-wrapper" class="hidden">
                            <label class="block text-sm font-medium text-slate-600 mb-1">ชั้นเรียน</label>
                            <select id="class-select" class="w-full border border-slate-200 bg-slate-50 p-2.5 rounded-xl text-sm"></select>
                        </div>
                        <div>
                            <label id="borrower-name-label" class="block text-sm font-medium text-slate-600 mb-1">ชื่อผู้ยืม</label>
                            <select id="borrower-id" class="w-full border border-slate-200 bg-slate-50 p-2.5 rounded-xl text-sm" required></select>
                        </div>
                    </div>
                    <div>
                        <div class="flex justify-between items-center mb-1">
                            <label class="block text-sm font-medium text-slate-600">Chromebook</label>
                            <button type="button" onclick="openScanner()" class="text-xs bg-indigo-50 hover:bg-indigo-100 text-indigo-600 px-3 py-1 rounded-full flex items-center gap-1 transition">
                                <i class="bi bi-qr-code-scan"></i> สแกน QR
                            </button>
                        </div>
                        <select id="chromebook-id" class="w-full border border-slate-200 bg-slate-50 p-2.5 rounded-xl text-sm" required><option>รอข้อมูล...</option></select>
                    </div>
                    <div class="border-2 border-dashed border-slate-200 p-4 rounded-xl text-center cursor-pointer hover:bg-slate-50 transition" onclick="document.getElementById('images').click()">
                        <input type="file" id="images" accept="image/*" multiple class="hidden" onchange="previewImages(this, 'preview-container')" />
                        <i class="bi bi-camera text-slate-400 text-xl mb-1"></i>
                        <p class="text-sm text-slate-400">ถ่ายรูปหลักฐาน (Max 3)</p>
                        <div id="preview-container" class="flex justify-center gap-2 mt-2 flex-wrap"></div>
                    </div>
                    <button type="submit" id="btn-submit-borrow" class="w-full bg-emerald-600 text-white py-3 rounded-xl hover:bg-emerald-700 font-bold shadow-lg shadow-emerald-100 transition-all">บันทึกข้อมูล</button>
                </form>
            </div>
        </div>

        <!-- Right: Table -->
        <div class="lg:col-span-2 space-y-6">
            <div class="flex flex-col sm:flex-row gap-4 justify-between items-center bg-white p-4 rounded-[2rem] border border-slate-100 shadow-sm">
                <div class="flex gap-2 overflow-x-auto w-full sm:w-auto" id="borrow-tabs">
                    <!-- Dynamic Tabs -->
                </div>
                <div class="relative w-full sm:w-72 flex-shrink-0">
                    <i class="bi bi-search absolute left-4 top-1/2 -translate-y-1/2 text-slate-300"></i>
                    <input type="text" id="search-input" placeholder="ค้นหา Serial, ชื่อ..." class="w-full bg-slate-50 border border-slate-200 rounded-2xl pl-12 pr-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400 transition-all font-bold" />
                </div>
            </div>

            <div class="cb-card !p-0 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm text-left">
                        <thead class="bg-slate-50/50 text-[10px] font-black text-slate-400 uppercase tracking-[0.15em] border-b border-slate-100">
                            <tr>
                                <th class="px-6 py-5">สถานะ</th>
                                <th class="px-6 py-5">ผู้ยืม / ระดับชั้น</th>
                                <th class="px-6 py-5">เครื่อง (ID/Serial)</th>
                                <th class="px-6 py-5">วันยืม / ตรวจสภาพ</th>
                                <th class="px-6 py-5 text-center">หลักฐาน</th>
                                <th class="px-6 py-5 text-right"></th>
                            </tr>
                        </thead>
                        <tbody id="table-body" class="divide-y divide-slate-50 text-slate-600">
                            <tr><td colspan="6" class="text-center py-20 text-slate-400 font-bold">กำลังประมวลผลตารางยืม...</td></tr>
                        </tbody>
                    </table>
                </div>
                <div class="bg-slate-50/80 px-6 py-4 flex justify-between items-center border-t border-slate-100">
                    <span class="text-[10px] font-black text-slate-400 uppercase tracking-widest" id="page-info">0 รายการ</span>
                    <div class="flex gap-2" id="pagination"></div>
                </div>
            </div>

            <a href="dashboard.php" class="flex items-center justify-between p-6 bg-blue-600 rounded-[2rem] text-white shadow-xl shadow-blue-100 hover:scale-[1.01] transition-all">
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 bg-white/20 rounded-2xl flex items-center justify-center text-xl"><i class="bi bi-pie-chart-fill"></i></div>
                    <div>
                        <h4 class="font-black">สถิติและแดชบอร์ด</h4>
                        <p class="text-[10px] text-blue-100 uppercase tracking-widest font-bold">View full device analytics &amp; reports</p>
                    </div>
                </div>
                <i class="bi bi-chevron-right text-2xl"></i>
            </a>
        </div>
    </div>
</div>


    <div id="master-data-modal" class="modal-bg z-[1100]">
        <div class="modal-content relative max-w-2xl">
            <button onclick="document.getElementById('master-data-modal').classList.remove('active')" class="absolute top-4 right-4 text-gray-400"><i class="fa-solid fa-times"></i></button>
            <h3 class="text-xl font-bold mb-4">จัดการข้อมูลพื้นฐาน</h3>
            
            <div class="flex border-b mb-4">
                <button class="px-4 py-2 border-b-2 border-blue-600 text-blue-600 font-semibold master-tab" onclick="switchMasterTab('teacher')">ครู</button>
                <button class="px-4 py-2 text-gray-500 hover:text-blue-600 master-tab" onclick="switchMasterTab('student')">นักเรียน</button>
                <button class="px-4 py-2 text-gray-500 hover:text-blue-600 master-tab" onclick="switchMasterTab('chromebook')">Chromebook</button>
            </div>

            <!-- Teacher Tab -->
            <div id="tab-teacher" class="master-tab-content">
                <form onsubmit="submitMasterData(event, 'teacher')" class="flex gap-2 items-end mb-4">
                    <div class="flex-1">
                        <label class="block text-sm mb-1">รหัสครู</label>
                        <input type="text" id="md-teacher-id" class="w-full border p-2 rounded" required />
                    </div>
                    <div class="flex-1">
                        <label class="block text-sm mb-1">ชื่อ-สกุล</label>
                        <input type="text" id="md-teacher-name" class="w-full border p-2 rounded" required />
                    </div>
                    <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded">บันทึก</button>
                </form>
            </div>

            <!-- Student Tab -->
            <div id="tab-student" class="master-tab-content hidden">
                <form onsubmit="submitMasterData(event, 'student')" class="flex gap-2 items-end mb-4">
                    <div class="flex-1">
                        <label class="block text-sm mb-1">รหัสนักเรียน</label>
                        <input type="text" id="md-student-id" class="w-full border p-2 rounded" required />
                    </div>
                    <div class="flex-1">
                        <label class="block text-sm mb-1">ชื่อ-สกุล</label>
                        <input type="text" id="md-student-name" class="w-full border p-2 rounded" required />
                    </div>
                    <div class="w-24">
                        <label class="block text-sm mb-1">ชั้นเรียน</label>
                        <input type="text" id="md-student-class" placeholder="ม.4/1" class="w-full border p-2 rounded" required />
                    </div>
                    <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded">บันทึก</button>
                </form>
            </div>

            <!-- Chromebook Tab -->
            <div id="tab-chromebook" class="master-tab-content hidden">
                <form onsubmit="submitMasterData(event, 'chromebook')" class="flex gap-2 items-end mb-4">
                    <div class="flex-1">
                        <label class="block text-sm mb-1">รหัสเครื่อง</label>
                        <input type="text" id="md-cb-id" placeholder="CB-001" class="w-full border p-2 rounded" required />
                    </div>
                    <div class="flex-1">
                        <label class="block text-sm mb-1">รุ่น</label>
                        <input type="text" id="md-cb-model" class="w-full border p-2 rounded" required />
                    </div>
                    <div class="flex-1">
                        <label class="block text-sm mb-1">Serial Number</label>
                        <input type="text" id="md-cb-serial" class="w-full border p-2 rounded" required />
                    </div>
                    <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded">บันทึก</button>
                </form>
            </div>

            <p class="text-xs text-gray-500 mt-4">* เมื่อเพิ่มหรือแก้ไขข้อมูลแล้ว ระบบจะอัปเดตข้อมูลอัตโนมัติ</p>
        </div>
    </div>

    <div id="login-modal" class="modal-bg">
        <div class="modal-content relative">
            <button onclick="hideLogin()" class="absolute top-4 right-4 text-gray-400"><i class="fa-solid fa-times"></i></button>
            <h3 class="text-xl font-bold text-center mb-4">เข้าสู่ระบบ Admin</h3>
            <form id="login-form" class="space-y-4">
                <input type="text" id="admin-id" placeholder="Admin ID" class="w-full border p-2 rounded-lg" required />
                <input type="password" id="admin-pass" placeholder="Password" class="w-full border p-2 rounded-lg" required />
                <button type="submit" class="w-full bg-blue-600 text-white py-2 rounded-lg">เข้าสู่ระบบ</button>
            </form>
        </div>
    </div>

    <div id="edit-modal" class="modal-bg z-[1050]">
        <div class="modal-content relative">
            <button onclick="document.getElementById('edit-modal').classList.remove('active')" class="absolute top-4 right-4 text-gray-400"><i class="fa-solid fa-times"></i></button>
            <h3 class="text-xl font-bold mb-4">แก้ไขข้อมูล / เพิ่มรูปภาพ</h3>
            <form id="edit-form" class="space-y-4">
                <input type="hidden" id="edit-entry-id">
                <input type="hidden" id="edit-borrower-type">
                <input type="hidden" id="edit-borrower-id">
                <input type="hidden" id="edit-class-name">
                <div class="bg-gray-50 p-3 rounded">
                    <p class="text-sm text-gray-500">ผู้ยืม: <span id="edit-show-name" class="font-bold text-gray-800"></span></p>
                    <p class="text-sm text-gray-500">สถานะ: <span id="edit-show-status" class="font-bold"></span></p>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">เพิ่มรูปภาพใหม่ (อัปโหลดเข้าโฟลเดอร์เดิม)</label>
                    <div class="border-2 border-dashed p-4 rounded-lg text-center cursor-pointer hover:bg-gray-50" onclick="document.getElementById('edit-images').click()">
                        <input type="file" id="edit-images" accept="image/*" multiple class="hidden" onchange="previewImages(this, 'edit-preview-container')" />
                        <i class="fa-solid fa-plus-circle text-blue-500 mb-1"></i> <span class="text-sm text-blue-600">เลือกรูปเพิ่ม</span>
                        <div id="edit-preview-container" class="flex justify-center gap-2 mt-2 flex-wrap"></div>
                    </div>
                </div>
                <button type="submit" class="w-full bg-blue-600 text-white py-2 rounded-lg hover:bg-blue-700">บันทึกการแก้ไข</button>
            </form>
        </div>
    </div>

    <div id="inspect-modal" class="modal-bg z-[1050]">
        <div class="modal-content relative">
            <button onclick="document.getElementById('inspect-modal').classList.remove('active')" class="absolute top-4 right-4 text-gray-400"><i class="fa-solid fa-times"></i></button>
            <h3 class="text-xl font-bold mb-4 text-indigo-700"><i class="fa-solid fa-magnifying-glass-chart"></i> ตรวจสอบสภาพเครื่อง (Audit)</h3>
            <form id="inspect-form" class="space-y-4">
                <input type="hidden" id="inspect-entry-id">
                <div class="bg-indigo-50 p-3 rounded border border-indigo-100">
                    <p class="text-sm text-gray-600">ผู้ยืม: <span id="inspect-show-name" class="font-bold text-gray-800"></span></p>
                    <p class="text-sm text-gray-600">เครื่องรหัส: <span id="inspect-show-cb" class="font-bold text-gray-800"></span></p>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">สภาพเครื่องปัจจุบัน</label>
                    <select id="inspect-condition" class="w-full border p-2 rounded-lg" required>
                        <option value="Normal">สภาพปกติ (Normal)</option>
                        <option value="Damaged">ชำรุด / เสียหาย (Damaged)</option>
                        <option value="Lost">สูญหาย (Lost)</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">หมายเหตุ (ถ้ามีระบุจุดที่เสียหาย)</label>
                    <textarea id="inspect-notes" class="w-full border p-2 rounded-lg" rows="2" placeholder="เช่น หน้าจอร้าว, สายชาร์จหาย..."></textarea>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">อัปโหลดรูปภาพหลักฐาน</label>
                    <div class="border-2 border-dashed border-indigo-200 bg-indigo-50/30 p-4 rounded-lg text-center cursor-pointer hover:bg-indigo-50" onclick="document.getElementById('inspect-images').click()">
                        <input type="file" id="inspect-images" accept="image/*" multiple class="hidden" onchange="previewImages(this, 'inspect-preview-container')" />
                        <i class="fa-solid fa-camera text-indigo-400 mb-1"></i> <span class="text-sm text-indigo-600">ถ่ายรูปเครื่องล่าสุด</span>
                        <div id="inspect-preview-container" class="flex justify-center gap-2 mt-2 flex-wrap"></div>
                    </div>
                </div>
                <button type="submit" class="w-full bg-indigo-600 text-white py-2 rounded-lg hover:bg-indigo-700">บันทึกผลการตรวจสอบ</button>
            </form>
        </div>
    </div>

    <!-- QR Scanner Modal -->
    <div id="scanner-modal" class="modal-bg z-[1200]">
        <div class="modal-content relative w-full max-w-sm">
            <button onclick="closeScanner()" class="absolute top-3 right-3 text-gray-400 hover:text-gray-700 z-10"><i class="fa-solid fa-times text-lg"></i></button>
            <h3 class="text-lg font-bold text-indigo-700 mb-1"><i class="fa-solid fa-qrcode mr-2"></i>สแกน QR Code / Barcode</h3>
            <p class="text-xs text-gray-500 mb-3">สแกนรหัสนักเรียน/ครู หรือรหัสเครื่อง Chromebook</p>
            <div id="qr-reader" class="w-full rounded-lg overflow-hidden border-2 border-indigo-200" style="min-height:220px"></div>
            <div id="scan-result" class="mt-3 hidden">
                <div class="bg-indigo-50 border border-indigo-200 rounded-lg p-3 text-sm">
                    <div class="flex items-start gap-2">
                        <i class="fa-solid fa-circle-check text-indigo-500 mt-0.5"></i>
                        <div>
                            <p class="font-bold text-indigo-800" id="scan-result-text"></p>
                            <p class="text-indigo-600 text-xs" id="scan-result-desc"></p>
                        </div>
                    </div>
                </div>
            </div>
            <p class="text-xs text-slate-400 text-center mt-3"><i class="fa-solid fa-lightbulb text-amber-400 mr-1"></i>ยิงรหัสนักเรียน แล้วยิงรหัสเครื่อง ระบบจะเติมข้อมูลอัตโนมัติ</p>
        </div>
    </div>

    <div id="image-modal" class="modal-bg z-[1100]" onclick="this.classList.remove('active')">
        <div class="relative max-w-4xl mx-4">
            <img src="" id="modal-full-img" class="w-full max-h-[90vh] object-contain rounded-lg shadow-2xl" onclick="event.stopPropagation()" />
        </div>
    </div>

    <script>
        console.log("App Starting...");
        // Auth ผ่าน PHP session แล้ว ถ้ามาถึงหน้านี้ได้แสดงว่าเป็น admin
        let state = { isAdmin: true, adminId: '<?= htmlspecialchars($_SESSION['username'] ?? 'admin') ?>', data: [], students: [], teachers: [], chromebooks: [], page: 1, limit: 10, tab: 'all' };
        
        function run(action, payload) {
            if (!payload) payload = {};
            return new Promise((resolve, reject) => {
                if (localStorage.getItem('adminToken')) payload.token = localStorage.getItem('adminToken');
                
                fetch('api.php?action=' + action, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: action, payload: payload })
                })
                .then(res => res.json())
                .then(res => {
             function renderDash() {
            const active = state.data.filter(r => r[7] === 'Borrowed');
            const teachers = active.filter(r => r[1] === 'Teacher').length;
            const m4 = active.filter(r => String(r[3]).startsWith('ม.4')).length;
            const m5_6 = active.filter(r => String(r[3]).startsWith('ม.5') || String(r[3]).startsWith('ม.6')).length;
            const total = state.chromebooks.length;
            const remaining = total - active.length;

            const cards = [
                {t: 'Teacher Borrowed', v: teachers, c: 'text-purple-600', b: 'bg-white', i: 'bi-person-badge', bg: 'bg-purple-50'},
                {t: 'M.4 Borrowed', v: m4, c: 'text-indigo-600', b: 'bg-white', i: 'bi-mortarboard-fill', bg: 'bg-indigo-50'},
                {t: 'M.5-M.6 Borrowed', v: m5_6, c: 'text-blue-600', b: 'bg-white', i: 'bi-mortarboard-fill', bg: 'bg-blue-50'},
                {t: 'Available', v: remaining, c: 'text-emerald-600', b: 'bg-white', i: 'bi-check-circle-fill', bg: 'bg-emerald-50 text-emerald-600'},
                {t: 'Total Devices', v: total, c: 'text-slate-600', b: 'bg-white', i: 'bi-laptop', bg: 'bg-slate-50'}
            ];
            
            document.getElementById('dashboard-cards').innerHTML = cards.map(c => 
                `<div class="cb-card flex items-center gap-6 group hover:shadow-xl hover:shadow-blue-50 transition-all duration-500">
                    <div class="w-14 h-14 ${c.bg} rounded-2xl flex items-center justify-center text-2xl group-hover:scale-110 transition-all"><i class="bi ${c.i}"></i></div>
                    <div>
                        <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">${c.t}</p>
                        <h4 class="text-3xl font-black text-slate-800 tracking-tight">${c.v}</h4>
                    </div>
                </div>`
            ).join('');
        }

        function renderTable() {
            const search = document.getElementById('search-input').value.toLowerCase();
            const filtered = state.data.filter(r => {
                const matchesSearch = r.some(c => String(c).toLowerCase().includes(search));
                let matchesTab = true;
                if (state.tab !== 'all') {
                    if (state.tab === 'Teacher') matchesTab = r[1] === 'Teacher';
                    else matchesTab = r[3] === state.tab;
                }
                return matchesTab && matchesSearch;
            });

            const show = filtered.slice((state.page-1)*state.limit, state.page*state.limit);
            document.getElementById('page-info').innerText = `${filtered.length} ITEMS TOTAL`;
            
            if (show.length === 0) {
                document.getElementById('table-body').innerHTML = `<tr><td colspan="6" class="text-center py-20 text-slate-400 font-bold italic">No matching records found.</td></tr>`;
                document.getElementById('pagination').innerHTML = '';
                return;
            }

            const rowsHtml = show.map(r => {
                const isBorrowed = r[7] === 'Borrowed';
                let name = r[2];
                if (r[1] === 'Teacher') {
                    const t = state.teachers.find(x => String(x[0]) == String(r[2]));
                    if(t) name = t[1];
                } else {
                    const s = state.students.find(x => String(x[0]) == String(r[2]));
                    if(s) name = s[1];
                }
                
                let imgHtml = '<span class="text-slate-300 italic text-[10px]">No Image</span>';
                if (r[6] && String(r[6]).trim() !== '') {
                    const ids = String(r[6]).split(',').filter(x => x);
                    if(ids.length > 0) {
                        imgHtml = '<div class="flex -space-x-3 overflow-hidden justify-center">';
                        ids.slice(0, 3).forEach(id => {
                            const imgUrl = 'uploads/' + id;
                            imgHtml += `<img src="${imgUrl}" class="inline-block h-10 w-10 rounded-2xl ring-4 ring-white cursor-pointer object-cover shadow-sm hover:z-10 transition-all hover:scale-110" onclick="viewImage('${imgUrl}')">`;
                        });
                        if(ids.length > 3) {
                            imgHtml += `<div class="h-10 w-10 rounded-2xl bg-slate-100 flex items-center justify-center text-[10px] font-black text-slate-400 ring-4 ring-white shadow-sm">+${ids.length-3}</div>`;
                        }
                        imgHtml += '</div>';
                    }
                }

                let dateStr = '-';
                if (r[8]) {
                    try {
                        dateStr = new Date(r[8]).toLocaleString('th-TH', {day:'numeric', month:'short', year:'2-digit'});
                    } catch(e) {}
                }
                
                let inspectStr = '';
                if (isBorrowed && r[9]) {
                    try {
                        const iDate = new Date(r[9]).toLocaleString('th-TH', {day:'numeric', month:'short'});
                        inspectStr = `<div class="text-[9px] font-bold text-indigo-500 mt-1 uppercase tracking-tighter"><i class="bi bi-shield-check mr-1"></i>Checked: ${iDate}</div>`;
                    } catch(e) {}
                } else if (isBorrowed && !r[9]) {
                    inspectStr = `<div class="text-[9px] font-bold text-rose-400 mt-1 uppercase tracking-tighter"><i class="bi bi-exclamation-triangle mr-1"></i>Unchecked</div>`;
                }

                let actions = '';
                if (state.isAdmin) {
                    actions += `<button onclick="openEdit('${r[0]}')" class="p-2 text-blue-500 hover:bg-blue-50 rounded-xl transition-all" title="Edit/Add Image"><i class="bi bi-pencil-square"></i></button>`;
                    if (isBorrowed) {
                const statusText = isBorrowed ? 'ยืม' : 'คืน';

                return `
                <tr class="hover:bg-gray-50 border-b">
                    <td class="px-4 py-3"><span class="px-2 py-1 rounded-full text-xs font-medium ${statusClass}">${statusText}</span></td>
                    <td class="px-4 py-3"><div>${name}</div><div class="text-xs text-gray-500">${r[3]||r[1]}</div></td>
                    <td class="px-4 py-3"><div>${r[5]||'-'}</div><div class="text-xs text-gray-400">ID: ${r[4]}</div></td>
                    <td class="px-4 py-3 text-xs text-gray-500"><div>ยืม: ${dateStr}</div>${inspectStr}</td>
                    <td class="px-4 py-3 text-center">${imgHtml}</td>
                    <td class="px-4 py-3 text-right">${actions}</td>
                </tr>`;
            }).join('');

            document.getElementById('table-body').innerHTML = rowsHtml;

            let pageHtml = '';
            const totalPages = Math.ceil(filtered.length / state.limit);
            for(let i=1; i<=totalPages; i++) {
                if(totalPages > 8 && (i !== 1 && i !== totalPages && Math.abs(i - state.page) > 2)) {
                    if(!pageHtml.endsWith('...</span>')) pageHtml += '<span class="px-1 text-gray-400">...</span>';
                    continue;
                }
                const activeClass = i===state.page ? 'bg-blue-600 text-white' : 'bg-white hover:bg-gray-100';
                pageHtml += `<button onclick="state.page=${i};renderTable()" class="px-3 py-1 rounded border ${activeClass}">${i}</button>`;
            }
            document.getElementById('pagination').innerHTML = pageHtml;
        }

        function openEdit(entryId) {
            const row = state.data.find(r => String(r[0]) === String(entryId));
            if (!row) return;
            
            let name = row[2];
            if (row[1] === 'Teacher') {
                const t = state.teachers.find(x => String(x[0]) == String(row[2]));
                if(t) name = t[1];
            } else {
                const s = state.students.find(x => String(x[0]) == String(row[2]));
                if(s) name = s[1];
            }
            
            document.getElementById('edit-entry-id').value = entryId;
            document.getElementById('edit-borrower-type').value = row[1];
            document.getElementById('edit-borrower-id').value = row[2]; 
            document.getElementById('edit-class-name').value = row[3];
            document.getElementById('edit-show-name').innerText = name + (row[3] ? ` (${row[3]})` : '');
            document.getElementById('edit-show-status').innerText = row[7];
            document.getElementById('edit-preview-container').innerHTML = '';
            document.getElementById('edit-images').value = '';
            document.getElementById('edit-modal').classList.add('active');
        }

        document.getElementById('edit-form').addEventListener('submit', async (e) => {
            e.preventDefault();
            const btn = e.target.querySelector('button');
            const oldText = btn.innerHTML;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> กำลังบันทึก...'; btn.disabled = true;
            try {
                const files = document.getElementById('edit-images').files;
                const blobs = [];
                for(let f of files) blobs.push(await new Promise(r => { const rd = new FileReader(); rd.onload=ev=>r(rd.result); rd.readAsDataURL(f); }));
                
                const res = await run('editBorrow', {
                    entryId: document.getElementById('edit-entry-id').value,
                    borrowerType: document.getElementById('edit-borrower-type').value,
                    borrowerId: document.getElementById('edit-borrower-id').value,
                    className: document.getElementById('edit-class-name').value,
                    newImageBlobs: blobs
                });
                
                if(res.success) {
                    Swal.fire('สำเร็จ', 'อัปเดตข้อมูลแล้ว', 'success');
                    document.getElementById('edit-modal').classList.remove('active');
                    loadData();
                } else throw res.error;
            } catch(err) { Swal.fire('ผิดพลาด', String(err), 'error'); }
            btn.innerHTML = oldText; btn.disabled = false;
        });

        function openInspect(entryId) {
            const row = state.data.find(r => String(r[0]) === String(entryId));
            if (!row) return;
            
            let name = row[2];
            if (row[1] === 'Teacher') {
                const t = state.teachers.find(x => String(x[0]) == String(row[2]));
                if(t) name = t[1];
            } else {
                const s = state.students.find(x => String(x[0]) == String(row[2]));
                if(s) name = s[1];
            }
            
            document.getElementById('inspect-entry-id').value = entryId;
            document.getElementById('inspect-show-name').innerText = name + (row[3] ? ` (${row[3]})` : '');
            document.getElementById('inspect-show-cb').innerText = row[4] + ' (' + row[5] + ')';
            document.getElementById('inspect-condition').value = 'Normal';
            document.getElementById('inspect-notes').value = '';
            document.getElementById('inspect-preview-container').innerHTML = '';
            document.getElementById('inspect-images').value = '';
            document.getElementById('inspect-modal').classList.add('active');
        }

        document.getElementById('inspect-form').addEventListener('submit', async (e) => {
            e.preventDefault();
            const btn = e.target.querySelector('button');
            const oldText = btn.innerHTML;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> กำลังบันทึก...'; btn.disabled = true;
            try {
                const files = document.getElementById('inspect-images').files;
                const blobs = [];
                for(let f of files) blobs.push(await new Promise(r => { const rd = new FileReader(); rd.onload=ev=>r(rd.result); rd.readAsDataURL(f); }));
                
                const res = await run('addInspection', {
                    entryId: document.getElementById('inspect-entry-id').value,
                    condition: document.getElementById('inspect-condition').value,
                    notes: document.getElementById('inspect-notes').value,
                    imageBlobs: blobs
                });
                
                if(res.success) {
                    Swal.fire('สำเร็จ', 'บันทึกการตรวจสอบสภาพเครื่องแล้ว', 'success');
                    document.getElementById('inspect-modal').classList.remove('active');
                    loadData();
                } else throw res.error;
            } catch(err) { Swal.fire('ผิดพลาด', String(err), 'error'); }
            btn.innerHTML = oldText; btn.disabled = false;
        });

        async function doAction(type, id) {
            Swal.fire({
                title: type === 'return' ? 'ยืนยันการคืน?' : 'ยืนยันการลบ?',
                text: type === 'delete' ? "ข้อมูลจะหายไปถาวร" : "",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: type === 'delete' ? '#d33' : '#3085d6',
                confirmButtonText: 'ตกลง',
                cancelButtonText: 'ยกเลิก'
            }).then(async (r) => {
                if(r.isConfirmed) {
                    const res = await run(type === 'return' ? 'returnBorrow' : 'deleteBorrow', {entryId: id});
                    if(res.success) { 
                        Swal.fire('สำเร็จ', '', 'success'); 
                        loadData(); 
                    } else {
                        Swal.fire('ผิดพลาด', res.error, 'error');
                    }
                }
            });
        }

        function renderDropdowns() {
            const borrowedIds = state.data.filter(r => r[7] === 'Borrowed').map(r => String(r[4])); 
            const availableChromebooks = state.chromebooks.filter(c => !borrowedIds.includes(String(c[0])));

            document.getElementById('chromebook-id').innerHTML = 
                `<option value="" disabled selected>-- เลือก (ว่าง ${availableChromebooks.length} เครื่อง) --</option>` + 
                availableChromebooks.map(c => `<option value="${c[0]}">${c[1]} - ${c[2]} (${c[0]})</option>`).join('');
        }

        document.getElementById('borrower-type').addEventListener('change', function() {
            const type = this.value;
            const nameSel = document.getElementById('borrower-id');
            const clsWrap = document.getElementById('class-select-wrapper');
            
            const activeBorrowerIds = state.data
                .filter(r => r[7] === 'Borrowed')
                .map(r => String(r[2]));

            document.getElementById('dynamic-borrower-fields').classList.remove('hidden');
            nameSel.innerHTML = `<option value="" disabled selected>-- เลือก --</option>`;
            
            if (type === 'Teacher') {
                clsWrap.classList.add('hidden');
                document.getElementById('class-select').value = ""; 
                document.getElementById('borrower-name-label').innerText = 'เลือกครู';
                
                const availableTeachers = state.teachers.filter(t => !activeBorrowerIds.includes(String(t[0])));
                
                if (availableTeachers.length === 0) {
                     nameSel.innerHTML += `<option value="" disabled>ครูทุกคนยืมเครื่องไปหมดแล้ว</option>`;
                } else {
                     nameSel.innerHTML += availableTeachers.map(t=>`<option value="${t[0]}">${t[1]}</option>`).join('');
                }

            } else {
                clsWrap.classList.remove('hidden');
                document.getElementById('borrower-name-label').innerText = 'เลือกนักเรียน';
                const classes = [...new Set(state.students.map(s=>s[2]))].sort();
                document.getElementById('class-select').innerHTML = `<option value="" disabled selected>-- ชั้น --</option>` + classes.map(c=>`<option value="${c}">${c}</option>`).join('');
                
                document.getElementById('class-select').onchange = function() {
                    const selectedClass = this.value;
                    const availableStudents = state.students.filter(s => 
                        s[2] === selectedClass && 
                        !activeBorrowerIds.includes(String(s[0]))
                    );

                    nameSel.innerHTML = `<option value="" disabled selected>-- เลือก --</option>`;
                    if(availableStudents.length === 0) {
                        nameSel.innerHTML += `<option value="" disabled>นักเรียนห้องนี้ยืมครบทุกคนแล้ว</option>`;
                    } else {
                        nameSel.innerHTML += availableStudents.map(s=>`<option value="${s[0]}">${s[1]}</option>`).join('');
                    }
                };
            }
        });

        document.getElementById('borrow-form').addEventListener('submit', async (e) => {
            e.preventDefault(); 
            if(!state.isAdmin) return showLogin();
            
            const btn = document.getElementById('btn-submit-borrow'); 
            const oldText = btn.innerHTML;
            btn.innerHTML='<i class="fa-solid fa-spinner fa-spin"></i> กำลังบันทึก...'; btn.disabled=true;
            try {
                const files = document.getElementById('images').files;
                const blobs = [];
                for(let f of files) blobs.push(await new Promise(r => { const rd = new FileReader(); rd.onload=e=>r(rd.result); rd.readAsDataURL(f); }));
                
                const res = await run('addBorrow', {
                    borrowerType: document.getElementById('borrower-type').value,
                    borrowerId: document.getElementById('borrower-id').value, 
                    className: document.getElementById('class-select').value,
                    chromebookId: document.getElementById('chromebook-id').value,
                    imageBlobs: blobs
                });
                
                if(res.success) { 
                    Swal.fire('สำเร็จ', 'บันทึกข้อมูลเรียบร้อย', 'success'); 
                    e.target.reset(); 
                    document.getElementById('dynamic-borrower-fields').classList.add('hidden'); 
                    document.getElementById('preview-container').innerHTML=''; 
                    loadData(); 
                }
                else throw res.error;
            } catch(err) { Swal.fire('ผิดพลาด', String(err), 'error'); }
            btn.innerHTML=oldText; btn.disabled=false;
        });

        document.getElementById('login-form').addEventListener('submit', async (e) => {
            e.preventDefault();
            try { 
                const res = await run('login', {adminId: document.getElementById('admin-id').value, password: document.getElementById('admin-pass').value});
                if(res.success) { 
                    localStorage.setItem('adminToken', res.token); 
                    localStorage.setItem('adminId', res.adminId); 
                    state.isAdmin=true; 
                    state.adminId=res.adminId; 
                    hideLogin(); 
                    updateAuth(); 
                    Swal.fire('ยินดีต้อนรับ', 'เข้าสู่ระบบสำเร็จ', 'success'); 
                } else {
                    Swal.fire('ไม่สำเร็จ', res.error, 'error');
                }
            } catch(e){}
        });

        function switchMasterTab(tabId) {
            document.querySelectorAll('.master-tab-content').forEach(el => el.classList.add('hidden'));
            document.getElementById('tab-' + tabId).classList.remove('hidden');
            
            const tabs = document.querySelectorAll('.master-tab');
            tabs.forEach(t => { t.classList.remove('border-b-2', 'border-blue-600', 'text-blue-600', 'font-semibold'); t.classList.add('text-gray-500'); });
            event.target.classList.remove('text-gray-500');
            event.target.classList.add('border-b-2', 'border-blue-600', 'text-blue-600', 'font-semibold');
        }

        async function submitMasterData(e, type) {
            e.preventDefault();
            const btn = e.target.querySelector('button[type="submit"]');
            const oldText = btn.innerHTML;
            btn.innerHTML = '...'; btn.disabled = true;

            try {
                let res;
                if (type === 'teacher') {
                    res = await run('addTeacher', {
                        teacher_id: document.getElementById('md-teacher-id').value,
                        name: document.getElementById('md-teacher-name').value
                    });
                } else if (type === 'student') {
                    res = await run('addStudent', {
                        student_id: document.getElementById('md-student-id').value,
                        name: document.getElementById('md-student-name').value,
                        class_name: document.getElementById('md-student-class').value
                    });
                } else if (type === 'chromebook') {
                    res = await run('addChromebook', {
                        chromebook_id: document.getElementById('md-cb-id').value,
                        model: document.getElementById('md-cb-model').value,
                        serial_number: document.getElementById('md-cb-serial').value
                    });
                }
                if (res.success) {
                    Swal.fire('สำเร็จ', 'บันทึกข้อมูลเรียบร้อย', 'success');
                    e.target.reset();
                    loadData();
                } else {
                    Swal.fire('ผิดพลาด', res.error || 'บันทึกไม่สำเร็จ', 'error');
                }
            } catch(err) {
                Swal.fire('ผิดพลาด', String(err), 'error');
            }
            btn.innerHTML = oldText; btn.disabled = false;
        }

        function updateAuth() {
            document.getElementById('btn-login-show').classList.toggle('hidden', state.isAdmin);
            document.getElementById('admin-panel').classList.toggle('hidden', !state.isAdmin);
            document.getElementById('borrow-form-card').classList.toggle('hidden', !state.isAdmin);
            document.getElementById('guest-welcome').classList.toggle('hidden', state.isAdmin);
            document.getElementById('admin-display-name').innerText = state.adminId;
            renderTable(); 
        }

        function showLogin() { document.getElementById('login-modal').classList.add('active'); }
        function hideLogin() { document.getElementById('login-modal').classList.remove('active'); }
        function logout() { localStorage.clear(); state.isAdmin = false; updateAuth(); }

        function filterTab(t, b) { 
            state.tab = t; 
            state.page = 1; 
            document.querySelectorAll('.tab-btn').forEach(btn=>btn.classList.remove('bg-blue-100','text-blue-700')); 
            b.classList.add('bg-blue-100','text-blue-700'); 
            renderTable(); 
        }

        function previewImages(inp, containerId) { 
            const c = document.getElementById(containerId); c.innerHTML = '';
            Array.from(inp.files).forEach(f => { 
                const r = new FileReader(); 
                r.onload=e=>{
                    const i=document.createElement('img');
                    i.src=e.target.result;
                    i.className='w-16 h-16 object-cover border rounded shadow-sm';
                    c.appendChild(i);
                }; 
                r.readAsDataURL(f); 
            });
        }

        function viewImage(url) { 
            document.getElementById('modal-full-img').src=url; 
            document.getElementById('image-modal').classList.add('active'); 
        }

        document.getElementById('search-input').addEventListener('input', e => { 
            clearTimeout(window.st); 
            window.st = setTimeout(() => {state.page=1; renderTable();}, 300); 
        });

        // ===== QR / BARCODE SCANNER =====
        let html5Qrcode = null;

        function openScanner() {
            document.getElementById('scanner-modal').classList.add('active');
            document.getElementById('scan-result').classList.add('hidden');
            html5Qrcode = new Html5Qrcode('qr-reader');
            html5Qrcode.start(
                { facingMode: 'environment' },
                { fps: 10, qrbox: { width: 220, height: 220 } },
                onScanSuccess,
                (err) => {}
            ).catch(err => {
                Swal.fire('ไม่สามารถเปิดกล้องได้', 'กรุณาตรวจสอบว่าเบราว์เซอร์ได้รับอนุญาตใช้กล้อง และระบบรันอยู่บน localhost หรือ HTTPS', 'warning');
                closeScanner();
            });
        }

        function closeScanner() {
            document.getElementById('scanner-modal').classList.remove('active');
            if (html5Qrcode) {
                html5Qrcode.stop().catch(() => {}).finally(() => { html5Qrcode = null; });
            }
        }

        function onScanSuccess(decodedText) {
            // Check if it is a student
            const student = state.students.find(s => String(s[0]) === String(decodedText));
            if (student) {
                // Set borrower type to student, populate fields
                document.getElementById('borrower-type').value = 'Student';
                document.getElementById('borrower-type').dispatchEvent(new Event('change'));
                // Wait for classes to populate then select correct class
                setTimeout(() => {
                    const clsSel = document.getElementById('class-select');
                    clsSel.value = student[2];
                    clsSel.dispatchEvent(new Event('change'));
                    setTimeout(() => {
                        document.getElementById('borrower-id').value = student[0];
                    }, 100);
                }, 100);
                showScanResult('นักเรียน: ' + student[1], 'ชั้น ' + student[2] + ' | รหัส: ' + student[0]);
                return;
            }

            // Check if it is a teacher
            const teacher = state.teachers.find(t => String(t[0]) === String(decodedText));
            if (teacher) {
                document.getElementById('borrower-type').value = 'Teacher';
                document.getElementById('borrower-type').dispatchEvent(new Event('change'));
                setTimeout(() => { document.getElementById('borrower-id').value = teacher[0]; }, 150);
                showScanResult('ครู: ' + teacher[1], 'รหัสครู: ' + teacher[0]);
                return;
            }

            // Check if it is a Chromebook
            const cb = state.chromebooks.find(c => String(c[0]) === String(decodedText) || String(c[2]) === String(decodedText));
            if (cb) {
                document.getElementById('chromebook-id').value = cb[0];
                showScanResult('Chromebook: ' + cb[1], 'รหัส: ' + cb[0] + ' | S/N: ' + cb[2]);
                return;
            }

            showScanResult('ไม่พบข้อมูล: ' + decodedText, 'รหัสนี้ไม่มีในระบบ กรุณาเพิ่มข้อมูลก่อน');
        }

        function showScanResult(title, desc) {
            document.getElementById('scan-result-text').textContent = title;
            document.getElementById('scan-result-desc').textContent = desc;
            document.getElementById('scan-result').classList.remove('hidden');
        }
    </script>
<?php require_once '../components/layout_end.php'; ?>
