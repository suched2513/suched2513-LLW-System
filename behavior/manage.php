<?php
/**
 * behavior/manage.php — การจัดการระบบ (Students, Templates, Users)
 * Roles: super_admin
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

$pageTitle    = 'จัดการระบบพฤติกรรม';
$pageSubtitle = 'CRUD นักเรียน / แม่แบบ / ผู้ใช้';
$activeSystem = 'behavior';

require_once __DIR__ . '/../components/layout_start.php';
?>

<div class="bg-white rounded-2xl shadow-xl shadow-violet-100/50 p-6 border border-violet-100/30">

    <!-- Header -->
    <div class="flex flex-wrap justify-between items-center mb-5 pb-4 border-b border-slate-100">
        <div>
            <h5 class="text-lg font-black text-slate-800 flex items-center gap-2">
                <i class="bi bi-sliders text-violet-600"></i> การจัดการระบบ
            </h5>
            <p class="text-xs text-slate-400 mt-1">สำหรับผู้ดูแลระบบ (Admin Only)</p>
        </div>
        <span class="px-3 py-1 bg-slate-100 text-slate-500 rounded-full text-xs font-black uppercase tracking-wider">Admin Area</span>
    </div>

    <!-- Tabs -->
    <div class="flex flex-wrap gap-1 mb-5">
        <button onclick="switchManageTab('students', this)" class="manage-tab manage-tab-active px-4 py-2 rounded-xl text-sm font-bold transition-all flex items-center gap-2">
            <i class="bi bi-people-fill"></i> นักเรียน
        </button>
        <button onclick="switchManageTab('templates', this)" class="manage-tab px-4 py-2 rounded-xl text-sm font-bold transition-all flex items-center gap-2 text-slate-500 hover:bg-slate-50">
            <i class="bi bi-stars"></i> Template
        </button>
        <button onclick="switchManageTab('users', this)" class="manage-tab px-4 py-2 rounded-xl text-sm font-bold transition-all flex items-center gap-2 text-slate-500 hover:bg-slate-50">
            <i class="bi bi-shield-lock-fill"></i> ผู้ใช้ระบบ
        </button>
    </div>

    <!-- ═══ MANAGE STUDENTS ═══ -->
    <div id="pane-students" class="manage-pane">
        <div class="flex justify-between items-center mb-3">
            <h6 class="font-black text-slate-700">จัดการข้อมูลนักเรียน</h6>
            <button onclick="resetStudentForm()" class="text-xs text-violet-600 bg-violet-50 hover:bg-violet-100 px-3 py-1.5 rounded-lg font-bold transition-all">
                <i class="bi bi-plus-circle me-1"></i> เพิ่มใหม่
            </button>
        </div>
        <div class="bg-slate-50 rounded-xl p-4 border border-slate-100 mb-4">
            <form id="formManageStudent" class="grid grid-cols-2 md:grid-cols-7 gap-2">
                <input type="hidden" id="msOriginalId">
                <input type="text" id="msStudentId" placeholder="รหัสนักเรียน*" required class="bg-white border border-slate-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500 outline-none">
                <input type="text" id="msStudentName" placeholder="ชื่อ-สกุล*" required class="md:col-span-2 bg-white border border-slate-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500 outline-none">
                <input type="text" id="msStudentLevel" placeholder="ระดับชั้น" class="bg-white border border-slate-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500 outline-none">
                <input type="text" id="msStudentRoom" placeholder="ห้อง" class="bg-white border border-slate-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500 outline-none">
                <input type="text" id="msStudentHomeroom" placeholder="ครูที่ปรึกษา" class="bg-white border border-slate-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500 outline-none">
                <button type="submit" class="bg-violet-600 text-white rounded-xl font-bold text-sm hover:bg-violet-700 transition-all">
                    <span id="msSubmitText"><i class="bi bi-save me-1"></i> บันทึก</span>
                </button>
                <input type="text" id="msStudentImg" placeholder="URL รูปภาพ" class="col-span-2 md:col-span-7 bg-white border border-slate-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500 outline-none">
            </form>
        </div>
        <div class="rounded-xl border border-slate-100 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-4 py-3 text-xs font-black text-slate-400 uppercase tracking-wider text-left border-b">รหัส</th>
                            <th class="px-4 py-3 text-xs font-black text-slate-400 uppercase tracking-wider text-left border-b">ชื่อ-สกุล</th>
                            <th class="px-4 py-3 text-xs font-black text-slate-400 uppercase tracking-wider border-b">ระดับ</th>
                            <th class="px-4 py-3 text-xs font-black text-slate-400 uppercase tracking-wider border-b">ห้อง</th>
                            <th class="px-4 py-3 text-xs font-black text-slate-400 uppercase tracking-wider text-left border-b">ที่ปรึกษา</th>
                            <th class="px-4 py-3 text-xs font-black text-slate-400 uppercase tracking-wider text-right border-b">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody id="manageStudentBody"><tr><td colspan="6" class="px-4 py-8 text-center text-slate-400">กำลังโหลด...</td></tr></tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ═══ MANAGE TEMPLATES ═══ -->
    <div id="pane-templates" class="manage-pane hidden">
        <div class="flex justify-between items-center mb-3">
            <h6 class="font-black text-slate-700">จัดการรายการ (Quick Select)</h6>
            <div class="flex gap-2">
                <select id="mtFilterType" class="border border-slate-200 rounded-lg px-3 py-1.5 text-xs focus:ring-2 focus:ring-violet-500 outline-none">
                    <option value="">ทุกประเภท</option><option value="ความดี">ความดี</option><option value="ความผิด">ความผิด</option>
                </select>
                <button onclick="resetTemplateForm()" class="text-xs text-violet-600 bg-violet-50 hover:bg-violet-100 px-3 py-1.5 rounded-lg font-bold transition-all">
                    <i class="bi bi-plus-circle me-1"></i> เพิ่มใหม่
                </button>
            </div>
        </div>
        <div class="bg-slate-50 rounded-xl p-4 border border-slate-100 mb-4">
            <form id="formManageTemplate" class="grid grid-cols-2 md:grid-cols-4 gap-2">
                <input type="hidden" id="mtIdHidden">
                <select id="mtType" required class="bg-white border border-slate-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500 outline-none">
                    <option value="ความดี">ความดี</option><option value="ความผิด">ความผิด</option>
                </select>
                <input type="text" id="mtName" placeholder="ชื่อรายการ*" required class="bg-white border border-slate-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500 outline-none">
                <input type="number" id="mtScore" placeholder="คะแนน*" required class="bg-white border border-slate-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500 outline-none">
                <button type="submit" class="bg-violet-600 text-white rounded-xl font-bold text-sm hover:bg-violet-700 transition-all">
                    <span id="mtSubmitText"><i class="bi bi-save me-1"></i> บันทึก</span>
                </button>
            </form>
        </div>
        <div class="rounded-xl border border-slate-100 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-4 py-3 text-xs font-black text-slate-400 uppercase tracking-wider text-left border-b">ประเภท</th>
                            <th class="px-4 py-3 text-xs font-black text-slate-400 uppercase tracking-wider text-left border-b">ชื่อรายการ</th>
                            <th class="px-4 py-3 text-xs font-black text-slate-400 uppercase tracking-wider text-right border-b">คะแนน</th>
                            <th class="px-4 py-3 text-xs font-black text-slate-400 uppercase tracking-wider text-right border-b">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody id="manageTemplateBody"></tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ═══ MANAGE USERS ═══ -->
    <div id="pane-users" class="manage-pane hidden">
        <div class="flex justify-between items-center mb-3">
            <h6 class="font-black text-slate-700">จัดการผู้ใช้ระบบ</h6>
            <button onclick="resetUserForm()" class="text-xs text-violet-600 bg-violet-50 hover:bg-violet-100 px-3 py-1.5 rounded-lg font-bold transition-all">
                <i class="bi bi-plus-circle me-1"></i> เพิ่มใหม่
            </button>
        </div>
        <div class="bg-slate-50 rounded-xl p-4 border border-slate-100 mb-4">
            <form id="formManageUser" class="grid grid-cols-2 md:grid-cols-5 gap-2">
                <input type="hidden" id="muIdHidden">
                <input type="text" id="muUsername" placeholder="Username*" required class="bg-white border border-slate-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500 outline-none">
                <input type="password" id="muPassword" placeholder="รหัสผ่าน" class="bg-white border border-slate-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500 outline-none">
                <input type="text" id="muName" placeholder="ชื่อ-สกุล*" required class="bg-white border border-slate-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500 outline-none">
                <select id="muRole" required class="bg-white border border-slate-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500 outline-none">
                    <option value="teacher">ครู</option><option value="homeroom">ครูที่ปรึกษา</option><option value="admin">Admin</option>
                </select>
                <button type="submit" class="bg-violet-600 text-white rounded-xl font-bold text-sm hover:bg-violet-700 transition-all">
                    <span id="muSubmitText"><i class="bi bi-save me-1"></i> บันทึก</span>
                </button>
            </form>
        </div>
        <div class="rounded-xl border border-slate-100 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-4 py-3 text-xs font-black text-slate-400 uppercase tracking-wider text-left border-b">Username</th>
                            <th class="px-4 py-3 text-xs font-black text-slate-400 uppercase tracking-wider text-left border-b">ชื่อ-สกุล</th>
                            <th class="px-4 py-3 text-xs font-black text-slate-400 uppercase tracking-wider border-b">บทบาท</th>
                            <th class="px-4 py-3 text-xs font-black text-slate-400 uppercase tracking-wider text-center border-b">สถานะ</th>
                            <th class="px-4 py-3 text-xs font-black text-slate-400 uppercase tracking-wider text-right border-b">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody id="manageUserBody"></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<style>
.manage-tab-active { background: #eff6ff; color: #7c3aed; font-weight: 700; }
</style>

<script>
window.BASE = '<?= rtrim(str_replace("/behavior", "", dirname($_SERVER["SCRIPT_NAME"])), "/") ?>';
const esc = s => { const d = document.createElement('div'); d.textContent = String(s); return d.innerHTML; };
async function api(url) { const r = await fetch(window.BASE + url); return await r.json(); }
async function post(url, data) { const r = await fetch(window.BASE + url, { method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify(data) }); return await r.json(); }

let adminStudents = [], adminTemplates = [], adminUsers = [];

// ─── Tab Switch ───
function switchManageTab(target, btn) {
    document.querySelectorAll('.manage-pane').forEach(p => p.classList.add('hidden'));
    document.querySelectorAll('.manage-tab').forEach(b => { b.classList.remove('manage-tab-active'); b.classList.add('text-slate-500'); });
    document.getElementById('pane-' + target)?.classList.remove('hidden');
    if (btn) { btn.classList.add('manage-tab-active'); btn.classList.remove('text-slate-500'); }
}

document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('formManageStudent').onsubmit = handleStudentSubmit;
    document.getElementById('formManageTemplate').onsubmit = handleTemplateSubmit;
    document.getElementById('formManageUser').onsubmit = handleUserSubmit;
    document.getElementById('mtFilterType').onchange = renderTemplates;
    loadStudents(); loadTemplates(); loadUsers();
});

// ═══════ STUDENTS ═══════
async function loadStudents() {
    document.getElementById('manageStudentBody').innerHTML = '<tr><td colspan="6" class="px-4 py-8 text-center text-slate-400">กำลังโหลด...</td></tr>';
    const data = await api('/behavior/api/get_student_list.php');
    adminStudents = Array.isArray(data) ? data : [];
    renderStudents();
}
function renderStudents() {
    const body = document.getElementById('manageStudentBody'); body.innerHTML = '';
    if (!adminStudents.length) { body.innerHTML = '<tr><td colspan="6" class="px-4 py-8 text-center text-slate-400">ยังไม่มีข้อมูล</td></tr>'; return; }
    adminStudents.forEach(s => {
        const [l, r] = (s.classText || '/').split('/');
        const tr = document.createElement('tr');
        tr.className = 'border-b border-slate-50 hover:bg-violet-50/30 transition-colors';
        tr.innerHTML = `
            <td class="px-4 py-3 text-xs font-mono text-slate-400">${esc(s.studentId)}</td>
            <td class="px-4 py-3 font-bold text-slate-700">${esc(s.name)}</td>
            <td class="px-4 py-3 text-center text-xs">${esc(l||'')}</td>
            <td class="px-4 py-3 text-center text-xs">${esc(r||'')}</td>
            <td class="px-4 py-3 text-xs text-slate-500">${esc(s.homeroom||'')}</td>
            <td class="px-4 py-3 text-right">
                <button onclick="editStudent('${esc(s.studentId)}')" class="w-7 h-7 bg-slate-100 hover:bg-violet-100 rounded-lg inline-flex items-center justify-center transition-all me-1"><i class="bi bi-pencil text-xs text-slate-500"></i></button>
                <button onclick="deleteStudent('${esc(s.studentId)}')" class="w-7 h-7 bg-rose-50 hover:bg-rose-100 rounded-lg inline-flex items-center justify-center transition-all"><i class="bi bi-trash text-xs text-rose-500"></i></button>
            </td>`;
        body.appendChild(tr);
    });
}
function resetStudentForm() { document.getElementById('msOriginalId').value = ''; ['msStudentId','msStudentName','msStudentLevel','msStudentRoom','msStudentHomeroom','msStudentImg'].forEach(id => document.getElementById(id).value = ''); document.getElementById('msSubmitText').innerHTML = '<i class="bi bi-save me-1"></i> บันทึก'; }
function editStudent(sid) {
    const s = adminStudents.find(x => x.studentId === sid); if (!s) return;
    document.getElementById('msOriginalId').value = sid;
    document.getElementById('msStudentId').value = s.studentId;
    document.getElementById('msStudentName').value = s.name;
    const [l, r] = (s.classText || '/').split('/');
    document.getElementById('msStudentLevel').value = l || '';
    document.getElementById('msStudentRoom').value = r || '';
    document.getElementById('msStudentHomeroom').value = s.homeroom || '';
    document.getElementById('msStudentImg').value = '';
    document.getElementById('msSubmitText').innerHTML = '<i class="bi bi-save me-1"></i> แก้ไข';
}
async function handleStudentSubmit(e) {
    e.preventDefault();
    const id = document.getElementById('msStudentId').value.trim();
    const name = document.getElementById('msStudentName').value.trim();
    if (!id || !name) { Swal.fire('กรุณากรอกรหัสและชื่อ', '', 'warning'); return; }
    Swal.fire({ title: 'กำลังบันทึก...', didOpen: () => Swal.showLoading() });
    const res = await post('/behavior/api/manage_students.php', {
        action: 'save', studentId: id, originalId: document.getElementById('msOriginalId').value || null,
        name, level: document.getElementById('msStudentLevel').value.trim(),
        room: document.getElementById('msStudentRoom').value.trim(),
        homeroom: document.getElementById('msStudentHomeroom').value.trim(),
        img: document.getElementById('msStudentImg').value.trim()
    });
    Swal.fire({ icon: 'success', title: 'บันทึกสำเร็จ', timer: 1000, showConfirmButton: false });
    resetStudentForm(); loadStudents();
}
async function deleteStudent(sid) {
    const r = await Swal.fire({ title: 'ยืนยันลบ?', icon: 'warning', showCancelButton: true, confirmButtonColor: '#ef4444', confirmButtonText: 'ลบ', cancelButtonText: 'ยกเลิก' });
    if (r.isConfirmed) { await post('/behavior/api/manage_students.php', { action: 'delete', studentId: sid }); Swal.fire({ icon: 'success', title: 'ลบแล้ว', timer: 900, showConfirmButton: false }); loadStudents(); }
}

// ═══════ TEMPLATES ═══════
async function loadTemplates() {
    document.getElementById('manageTemplateBody').innerHTML = '<tr><td colspan="4" class="px-4 py-8 text-center text-slate-400">กำลังโหลด...</td></tr>';
    const data = await api('/behavior/api/manage_templates.php?action=list');
    adminTemplates = Array.isArray(data) ? data : [];
    renderTemplates();
}
function renderTemplates() {
    const body = document.getElementById('manageTemplateBody'); body.innerHTML = '';
    const filter = document.getElementById('mtFilterType').value;
    const filtered = adminTemplates.filter(t => !filter || t.type === filter);
    if (!filtered.length) { body.innerHTML = '<tr><td colspan="4" class="px-4 py-8 text-center text-slate-400">ยังไม่มีแม่แบบ</td></tr>'; return; }
    filtered.forEach(t => {
        const tr = document.createElement('tr');
        tr.className = 'border-b border-slate-50 hover:bg-violet-50/30 transition-colors';
        const isGood = t.type === 'ความดี';
        tr.innerHTML = `
            <td class="px-4 py-3"><span class="px-2 py-0.5 rounded-full text-xs font-black ${isGood ? 'bg-emerald-50 text-emerald-600' : 'bg-rose-50 text-rose-600'}">${esc(t.type)}</span></td>
            <td class="px-4 py-3 font-medium text-slate-700">${esc(t.name)}</td>
            <td class="px-4 py-3 text-right font-black text-slate-600">${t.score}</td>
            <td class="px-4 py-3 text-right">
                <button onclick="editTemplate(${t.id})" class="w-7 h-7 bg-slate-100 hover:bg-violet-100 rounded-lg inline-flex items-center justify-center transition-all me-1"><i class="bi bi-pencil text-xs text-slate-500"></i></button>
                <button onclick="deleteTemplate(${t.id})" class="w-7 h-7 bg-rose-50 hover:bg-rose-100 rounded-lg inline-flex items-center justify-center transition-all"><i class="bi bi-trash text-xs text-rose-500"></i></button>
            </td>`;
        body.appendChild(tr);
    });
}
function resetTemplateForm() { document.getElementById('mtIdHidden').value = ''; document.getElementById('mtType').value = 'ความดี'; document.getElementById('mtName').value = ''; document.getElementById('mtScore').value = ''; document.getElementById('mtSubmitText').innerHTML = '<i class="bi bi-save me-1"></i> บันทึก'; }
function editTemplate(id) {
    const t = adminTemplates.find(x => x.id == id); if (!t) return;
    document.getElementById('mtIdHidden').value = id;
    document.getElementById('mtType').value = t.type;
    document.getElementById('mtName').value = t.name;
    document.getElementById('mtScore').value = t.score;
    document.getElementById('mtSubmitText').innerHTML = '<i class="bi bi-save me-1"></i> แก้ไข';
}
async function handleTemplateSubmit(e) {
    e.preventDefault();
    const name = document.getElementById('mtName').value.trim();
    const score = parseInt(document.getElementById('mtScore').value);
    if (!name || isNaN(score) || score <= 0) { Swal.fire('กรุณากรอกข้อมูลให้ครบ', '', 'warning'); return; }
    Swal.fire({ title: 'กำลังบันทึก...', didOpen: () => Swal.showLoading() });
    await post('/behavior/api/manage_templates.php', {
        action: 'save', id: document.getElementById('mtIdHidden').value || null,
        type: document.getElementById('mtType').value, name, score
    });
    Swal.fire({ icon: 'success', title: 'บันทึกสำเร็จ', timer: 900, showConfirmButton: false });
    resetTemplateForm(); loadTemplates();
}
async function deleteTemplate(id) {
    const r = await Swal.fire({ title: 'ยืนยันลบ?', icon: 'warning', showCancelButton: true, confirmButtonColor: '#ef4444', confirmButtonText: 'ลบ', cancelButtonText: 'ยกเลิก' });
    if (r.isConfirmed) { await post('/behavior/api/manage_templates.php', { action: 'delete', id }); Swal.fire({ icon: 'success', title: 'ลบแล้ว', timer: 900, showConfirmButton: false }); loadTemplates(); }
}

// ═══════ USERS ═══════
async function loadUsers() {
    document.getElementById('manageUserBody').innerHTML = '<tr><td colspan="5" class="px-4 py-8 text-center text-slate-400">กำลังโหลด...</td></tr>';
    const data = await api('/behavior/api/manage_users.php?action=list');
    adminUsers = Array.isArray(data) ? data : [];
    renderUsers();
}
function renderUsers() {
    const body = document.getElementById('manageUserBody'); body.innerHTML = '';
    if (!adminUsers.length) { body.innerHTML = '<tr><td colspan="5" class="px-4 py-8 text-center text-slate-400">ยังไม่มีผู้ใช้</td></tr>'; return; }
    adminUsers.forEach(u => {
        const roleLabel = u.role === 'admin' ? 'Admin' : u.role === 'homeroom' ? 'ครูที่ปรึกษา' : 'ครู';
        const active = u.active !== 0;
        const tr = document.createElement('tr');
        tr.className = 'border-b border-slate-50 hover:bg-violet-50/30 transition-colors';
        tr.innerHTML = `
            <td class="px-4 py-3 text-xs font-mono text-slate-400">${esc(u.username)}</td>
            <td class="px-4 py-3 font-bold text-slate-700">${esc(u.name)}</td>
            <td class="px-4 py-3 text-center text-xs">${roleLabel}</td>
            <td class="px-4 py-3 text-center"><span class="px-2 py-0.5 rounded-full text-xs font-black ${active ? 'bg-emerald-50 text-emerald-600' : 'bg-slate-100 text-slate-400'}">${active ? 'ใช้งาน' : 'ปิด'}</span></td>
            <td class="px-4 py-3 text-right">
                <button onclick="editUser(${u.id})" class="w-7 h-7 bg-slate-100 hover:bg-violet-100 rounded-lg inline-flex items-center justify-center transition-all me-1"><i class="bi bi-pencil text-xs text-slate-500"></i></button>
                <button onclick="deleteUser(${u.id})" class="w-7 h-7 bg-rose-50 hover:bg-rose-100 rounded-lg inline-flex items-center justify-center transition-all"><i class="bi bi-trash text-xs text-rose-500"></i></button>
            </td>`;
        body.appendChild(tr);
    });
}
function resetUserForm() { document.getElementById('muIdHidden').value = ''; ['muUsername','muPassword','muName'].forEach(id => document.getElementById(id).value = ''); document.getElementById('muRole').value = 'teacher'; document.getElementById('muSubmitText').innerHTML = '<i class="bi bi-save me-1"></i> บันทึก'; }
function editUser(id) {
    const u = adminUsers.find(x => x.id == id); if (!u) return;
    document.getElementById('muIdHidden').value = id;
    document.getElementById('muUsername').value = u.username;
    document.getElementById('muName').value = u.name;
    document.getElementById('muRole').value = u.role || 'teacher';
    document.getElementById('muPassword').value = '';
    document.getElementById('muSubmitText').innerHTML = '<i class="bi bi-save me-1"></i> แก้ไข';
}
async function handleUserSubmit(e) {
    e.preventDefault();
    const username = document.getElementById('muUsername').value.trim();
    const name = document.getElementById('muName').value.trim();
    if (!username || !name) { Swal.fire('กรุณากรอก Username และชื่อ', '', 'warning'); return; }
    Swal.fire({ title: 'กำลังบันทึก...', didOpen: () => Swal.showLoading() });
    await post('/behavior/api/manage_users.php', {
        action: 'save', id: document.getElementById('muIdHidden').value || null,
        username, name, role: document.getElementById('muRole').value,
        password: document.getElementById('muPassword').value || null
    });
    Swal.fire({ icon: 'success', title: 'บันทึกสำเร็จ', timer: 1000, showConfirmButton: false });
    resetUserForm(); loadUsers();
}
async function deleteUser(id) {
    const r = await Swal.fire({ title: 'ยืนยันลบ?', icon: 'warning', showCancelButton: true, confirmButtonColor: '#ef4444', confirmButtonText: 'ลบ', cancelButtonText: 'ยกเลิก' });
    if (r.isConfirmed) { await post('/behavior/api/manage_users.php', { action: 'delete', id }); Swal.fire({ icon: 'success', title: 'ลบแล้ว', timer: 900, showConfirmButton: false }); loadUsers(); }
}
</script>

<?php require_once __DIR__ . '/../components/layout_end.php'; ?>
