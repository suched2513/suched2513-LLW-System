<?php
/**
 * assembly/manage_students.php — จัดการข้อมูลนักเรียนและห้องเรียน
 * Roles: super_admin
 */
session_start();
require_once __DIR__ . '/../config.php';

if (!isset($_SESSION['llw_role'])) {
    header('Location: ' . $base_path . '/login.php?redirect=' . urlencode($_SERVER['REQUEST_URI'])); exit();
}
if ($_SESSION['llw_role'] !== 'super_admin') {
    header('Location: ' . $base_path . '/assembly/dashboard.php'); exit();
}

$pageTitle    = 'จัดการข้อมูลนักเรียน';
$pageSubtitle = 'นำเข้า แก้ไข และผูกครูที่ปรึกษากับห้องเรียน';
$activeSystem = 'assembly';

require_once __DIR__ . '/../components/layout_start.php';

// ─── Load classrooms for teacher assignment ───
$pdo = getPdo();
$classrooms = $pdo->query("SELECT c.classroom, c.teacher_name, c.llw_user_id, u.firstname, u.lastname FROM assembly_classrooms c LEFT JOIN llw_users u ON u.user_id = c.llw_user_id ORDER BY c.classroom")->fetchAll();
$teachers   = $pdo->query("SELECT user_id, firstname, lastname, username FROM llw_users WHERE role = 'att_teacher' AND status = 'active' ORDER BY firstname")->fetchAll();
$totalStudents = $pdo->query("SELECT COUNT(*) FROM assembly_students")->fetchColumn();
$totalRooms    = $pdo->query("SELECT COUNT(*) FROM assembly_classrooms")->fetchColumn();
?>

<!-- ─── KPI Strip ─── -->
<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
    <div class="bg-gradient-to-br from-amber-500 to-orange-500 text-white rounded-2xl p-5 shadow-lg shadow-amber-200/50">
        <p class="text-xs font-black uppercase tracking-wider opacity-80">นักเรียนทั้งหมด</p>
        <p class="text-4xl font-black mt-1"><?= number_format($totalStudents) ?></p>
    </div>
    <div class="bg-gradient-to-br from-blue-500 to-indigo-600 text-white rounded-2xl p-5 shadow-lg shadow-blue-200/50">
        <p class="text-xs font-black uppercase tracking-wider opacity-80">ห้องเรียน</p>
        <p class="text-4xl font-black mt-1"><?= $totalRooms ?></p>
    </div>
    <div class="bg-gradient-to-br from-emerald-500 to-teal-600 text-white rounded-2xl p-5 shadow-lg shadow-emerald-200/50">
        <p class="text-xs font-black uppercase tracking-wider opacity-80">ครูที่ปรึกษา (ผูกแล้ว)</p>
        <p class="text-4xl font-black mt-1"><?= count(array_filter($classrooms, fn($c) => $c['llw_user_id'])) ?></p>
    </div>
    <div class="bg-gradient-to-br from-rose-500 to-pink-600 text-white rounded-2xl p-5 shadow-lg shadow-rose-200/50">
        <p class="text-xs font-black uppercase tracking-wider opacity-80">ห้องที่ยังไม่ผูก</p>
        <p class="text-4xl font-black mt-1"><?= count(array_filter($classrooms, fn($c) => !$c['llw_user_id'])) ?></p>
    </div>
</div>

<div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
    <!-- ─── Import CSV ─── -->
    <div class="bg-white rounded-2xl shadow-xl shadow-amber-100/50 p-6 border border-amber-100/30">
        <div class="flex items-center gap-3 mb-5">
            <div class="w-10 h-10 bg-gradient-to-br from-amber-500 to-orange-500 rounded-xl flex items-center justify-center shadow-lg shadow-amber-200/50">
                <i class="bi bi-cloud-upload-fill text-white text-lg"></i>
            </div>
            <div>
                <h2 class="text-base font-black text-slate-800">นำเข้านักเรียนจาก CSV</h2>
                <p class="text-xs text-slate-400">รองรับ .csv (UTF-8 / Excel Thai)</p>
            </div>
        </div>

        <!-- CSV Format info -->
        <div class="bg-amber-50 rounded-2xl p-4 mb-4 border border-amber-100">
            <p class="text-xs font-bold text-amber-700 mb-2"><i class="bi bi-info-circle-fill mr-1"></i>รูปแบบ CSV (header row บรรทัดแรก):</p>
            <code class="text-sm text-amber-600 bg-amber-100 px-2 py-1 rounded-lg block">student_id, name, classroom, teacher_name</code>
            <p class="text-xs text-amber-600 mt-2">ตัวอย่าง: <code>12345, นายสมชาย ใจดี, ม.1/1, นางสาวมาลี สุขใจ</code></p>
        </div>

        <!-- Upload form -->
        <div id="drop-zone" class="border-2 border-dashed border-amber-200 rounded-2xl p-8 text-center hover:border-amber-400 hover:bg-amber-50/50 transition-all cursor-pointer mb-4"
             onclick="document.getElementById('csv-file').click()">
            <i class="bi bi-file-earmark-spreadsheet text-4xl text-amber-400 block mb-2"></i>
            <p class="text-sm font-bold text-slate-500">คลิกหรือลากไฟล์ CSV มาวางที่นี่</p>
            <p class="text-xs text-slate-400" id="file-name">ยังไม่เลือกไฟล์</p>
        </div>
        <input type="file" id="csv-file" accept=".csv" class="hidden" onchange="onFileSelect(this)">

        <div class="flex gap-3">
            <button onclick="importCSV()" class="flex-1 bg-gradient-to-r from-amber-500 to-orange-500 text-white px-5 py-3 rounded-2xl font-bold text-sm shadow-lg shadow-amber-200/50 hover:scale-[1.02] transition-all flex items-center justify-center gap-2">
                <i class="bi bi-cloud-upload-fill"></i> นำเข้าข้อมูล
            </button>
            <a href="api/download_template.php"
               class="bg-emerald-50 text-emerald-700 border border-emerald-200 px-4 py-3 rounded-2xl font-bold text-sm hover:bg-emerald-100 transition-all flex items-center gap-2">
                <i class="bi bi-file-earmark-arrow-down-fill"></i> ดาวน์โหลด Template
            </a>
        </div>

        <div id="import-result" class="hidden mt-4 p-4 rounded-2xl border"></div>
    </div>

    <!-- ─── Classroom-Teacher Assignment ─── -->
    <div class="bg-white rounded-2xl shadow-xl shadow-blue-100/50 p-6 border border-blue-100/30">
        <div class="flex items-center gap-3 mb-5">
            <div class="w-10 h-10 bg-gradient-to-br from-blue-500 to-indigo-600 rounded-xl flex items-center justify-center shadow-lg shadow-blue-200/50">
                <i class="bi bi-person-badge-fill text-white text-lg"></i>
            </div>
            <div>
                <h2 class="text-base font-black text-slate-800">ผูกครูที่ปรึกษากับห้อง</h2>
                <p class="text-xs text-slate-400">กำหนดสิทธิ์การเข้าถึงห้องของครู</p>
            </div>
        </div>
        <div class="space-y-3 max-h-[420px] overflow-auto pr-1">
            <?php foreach ($classrooms as $c): ?>
            <div class="flex items-center gap-3 p-3 bg-slate-50 rounded-2xl border border-slate-100 hover:border-blue-200 transition-all">
                <div class="w-10 h-10 bg-gradient-to-br from-blue-50 to-indigo-50 rounded-xl flex items-center justify-center border border-blue-100">
                    <span class="text-xs font-black text-blue-600"><?= htmlspecialchars(substr($c['classroom'], 0, 4), ENT_QUOTES, 'UTF-8') ?></span>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-black text-slate-700"><?= htmlspecialchars($c['classroom'], ENT_QUOTES, 'UTF-8') ?></p>
                    <p class="text-xs text-slate-400 truncate"><?= htmlspecialchars($c['teacher_name'] ?? 'ยังไม่ระบุครู', ENT_QUOTES, 'UTF-8') ?></p>
                </div>
                <select class="bg-white border border-slate-200 rounded-xl px-2 py-1.5 text-xs font-medium focus:ring-2 focus:ring-blue-400 outline-none"
                        onchange="assignTeacher('<?= htmlspecialchars($c['classroom'], ENT_QUOTES, 'UTF-8') ?>', this.value)">
                    <option value="">— ไม่ระบุ —</option>
                    <?php foreach ($teachers as $t): ?>
                    <option value="<?= $t['user_id'] ?>" <?= $t['user_id'] == $c['llw_user_id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($t['firstname'] . ' ' . $t['lastname'], ENT_QUOTES, 'UTF-8') ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endforeach; ?>
            <?php if (empty($classrooms)): ?>
            <div class="text-center py-8 text-slate-400">
                <i class="bi bi-inbox text-4xl block mb-2 opacity-30"></i>
                ยังไม่มีข้อมูลห้องเรียน — กรุณานำเข้านักเรียนก่อน
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ─── Student List ─── -->
<div class="bg-white rounded-2xl shadow-xl shadow-slate-100/50 p-6 border border-slate-100 mt-6">
    <div class="flex items-center gap-3 mb-5">
        <div class="w-10 h-10 bg-gradient-to-br from-slate-500 to-slate-700 rounded-xl flex items-center justify-center shadow-lg shadow-slate-200/50">
            <i class="bi bi-people-fill text-white text-lg"></i>
        </div>
        <div class="flex-1">
            <h2 class="text-base font-black text-slate-800">รายชื่อนักเรียนทั้งหมด</h2>
        </div>
        <div class="flex gap-2">
            <select id="filter-class" onchange="filterStudents()" class="bg-slate-50 border border-slate-200 rounded-2xl px-4 py-2 text-sm focus:ring-2 focus:ring-slate-400 outline-none transition-all">
                <option value="">ทุกห้อง</option>
                <?php foreach ($classrooms as $c): ?>
                <option value="<?= htmlspecialchars($c['classroom'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($c['classroom'], ENT_QUOTES, 'UTF-8') ?></option>
                <?php endforeach; ?>
            </select>
            <input type="text" id="search-student" oninput="filterStudents()" placeholder="ค้นหาชื่อ/รหัส..."
                class="bg-slate-50 border border-slate-200 rounded-2xl px-4 py-2 text-sm focus:ring-2 focus:ring-slate-400 outline-none transition-all w-40">
        </div>
    </div>
    <div class="rounded-2xl border border-slate-100 overflow-hidden">
        <div class="overflow-auto max-h-[400px]">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 sticky top-0">
                    <tr>
                        <th class="px-3 py-3 text-xs font-black text-slate-400 uppercase tracking-wider text-left border-b">#</th>
                        <th class="px-3 py-3 text-xs font-black text-slate-400 uppercase tracking-wider text-left border-b">รหัส</th>
                        <th class="px-3 py-3 text-xs font-black text-slate-400 uppercase tracking-wider text-left border-b">ชื่อ-สกุล</th>
                        <th class="px-3 py-3 text-xs font-black text-slate-400 uppercase tracking-wider border-b">ห้อง</th>
                        <th class="px-3 py-3 text-xs font-black text-slate-400 uppercase tracking-wider border-b">จัดการ</th>
                    </tr>
                </thead>
                <tbody id="student-list">
                    <tr><td colspan="5" class="px-4 py-8 text-center text-slate-400">กำลังโหลด...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

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

let allStudents = [];

document.addEventListener('DOMContentLoaded', () => {
    console.log("Manage Students Ready. BASE:", window.BASE);
    loadStudentList();
});

async function loadStudentList() {
    console.log("Loading all students...");
    try {
        const res = await window.api('/assembly/api/get_students.php?classroom=all');
        if (res.status !== 'success') {
            const tbody = document.getElementById('student-list');
            if (tbody) tbody.innerHTML = `<tr><td colspan="5" class="px-4 py-8 text-center text-rose-500">ผิดพลาด: ${res.message}</td></tr>`;
            return;
        }
        allStudents = res.students;
        console.log("Students loaded:", allStudents.length);
        renderStudentList(allStudents);
    } catch (e) {
        console.error("loadStudentList failed:", e);
        const tbody = document.getElementById('student-list');
        if (tbody) tbody.innerHTML = `<tr><td colspan="5" class="px-4 py-8 text-center text-rose-500">ผิดพลาดทางการเชื่อมต่อ</td></tr>`;
    }
}

function renderStudentList(students) {
    const tbody = document.getElementById('student-list');
    if (!students.length) {
        tbody.innerHTML = `<tr><td colspan="5" class="px-4 py-8 text-center text-slate-400">ไม่พบข้อมูลนักเรียน</td></tr>`;
        return;
    }
    tbody.innerHTML = students.map((s, i) => `
        <tr class="border-b border-slate-50 hover:bg-slate-50/80 transition-colors">
            <td class="px-3 py-2.5 text-xs text-slate-400">${i+1}</td>
            <td class="px-3 py-2.5 text-xs font-bold text-slate-500">${esc(s.student_id)}</td>
            <td class="px-3 py-2.5 text-sm text-slate-700">${esc(s.name)}</td>
            <td class="px-3 py-2.5 text-center"><span class="px-2 py-0.5 bg-amber-50 text-amber-700 text-xs font-bold rounded-full">${esc(s.classroom)}</span></td>
            <td class="px-3 py-2.5 text-center">
                <button onclick="deleteStudent('${esc(s.student_id)}', '${esc(s.name)}')" class="text-rose-400 hover:text-rose-600 text-xs font-bold transition-colors">
                    <i class="bi bi-trash3"></i>
                </button>
            </td>
        </tr>
    `).join('');
}

function filterStudents() {
    const cls  = document.getElementById('filter-class').value;
    const srch = document.getElementById('search-student').value.toLowerCase();
    renderStudentList(allStudents.filter(s =>
        (!cls || s.classroom === cls) &&
        (!srch || s.name.toLowerCase().includes(srch) || s.student_id.includes(srch))
    ));
}

async function deleteStudent(id, name) {
    const confirm = await Swal.fire({
        title: 'ลบนักเรียน?',
        text: `ต้องการลบ "${name}" ออกจากระบบ?`,
        icon: 'warning', showCancelButton: true,
        confirmButtonText: 'ลบ', cancelButtonText: 'ยกเลิก',
        confirmButtonColor: '#f43f5e'
    });
    if (!confirm.isConfirmed) return;

    const res = await fetch(BASE + '/assembly/api/delete_student.php', {
        method: 'POST',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify({student_id: id})
    }).then(r => r.json());

    if (res.status === 'success') {
        allStudents = allStudents.filter(s => s.student_id !== id);
        filterStudents();
        Swal.fire('ลบแล้ว', '', 'success');
    } else {
        Swal.fire('ผิดพลาด', res.message, 'error');
    }
}

// ─── Import CSV ───
function onFileSelect(input) {
    document.getElementById('file-name').textContent = input.files[0]?.name || 'ยังไม่เลือกไฟล์';
}

async function importCSV() {
    const file = document.getElementById('csv-file').files[0];
    if (!file) { Swal.fire('กรุณาเลือกไฟล์ CSV','','warning'); return; }

    const formData = new FormData();
    formData.append('csv_file', file);

    Swal.fire({ title: 'กำลังนำเข้า...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
    const res = await fetch(BASE + '/assembly/api/import_students.php', { method: 'POST', body: formData }).then(r => r.json());
    Swal.close();

    const resultEl = document.getElementById('import-result');
    resultEl.classList.remove('hidden');
    if (res.status === 'success') {
        resultEl.className = 'mt-4 p-4 rounded-2xl border bg-emerald-50 border-emerald-200 text-emerald-700';
        resultEl.innerHTML = `<i class="bi bi-check-circle-fill mr-2"></i><strong>${res.message}</strong>` +
            (res.errors?.length ? `<ul class="mt-2 text-xs list-disc list-inside">${res.errors.map(e => `<li>${esc(e)}</li>`).join('')}</ul>` : '');
        await loadStudentList();
        setTimeout(() => location.reload(), 2000);
    } else {
        resultEl.className = 'mt-4 p-4 rounded-2xl border bg-rose-50 border-rose-200 text-rose-700';
        resultEl.innerHTML = `<i class="bi bi-x-circle-fill mr-2"></i>${esc(res.message)}`;
    }
}

// ─── Assign Teacher ───
async function assignTeacher(classroom, userId) {
    const res = await fetch(BASE + '/assembly/api/assign_teacher.php', {
        method: 'POST',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify({classroom, llw_user_id: userId || null})
    }).then(r => r.json());

    if (res.status === 'success') {
        Swal.fire({ icon:'success', title:'บันทึกแล้ว', timer:1000, showConfirmButton:false });
    } else {
        Swal.fire('ผิดพลาด', res.message, 'error');
    }
}

function esc(str) {
    const d = document.createElement('div'); d.textContent = String(str); return d.innerHTML;
}
</script>

<?php require_once __DIR__ . '/../components/layout_end.php'; ?>
