<?php
session_start();
require_once __DIR__ . '/../config.php';

if (!isset($_SESSION['llw_role'])) { header('Location: ' . $base_path . '/login.php'); exit(); }
if ($_SESSION['llw_role'] !== 'super_admin') { header('Location: ' . $base_path . '/login.php'); exit(); }

$pdo = getPdo();

$classrooms = $pdo->query("SELECT DISTINCT classroom FROM att_students WHERE status='active' AND classroom != '' ORDER BY classroom")->fetchAll(PDO::FETCH_COLUMN);

$pageTitle    = 'สั่งงานใหม่';
$pageSubtitle = 'กำหนดงานให้นักเรียน';
$activeSystem = 'homework';
require_once __DIR__ . '/../components/layout_start.php';
?>

<div class="max-w-2xl mx-auto">
<div class="bg-white rounded-2xl shadow-xl shadow-indigo-100/50 border border-indigo-100/30 p-8">
    <div class="flex items-center gap-3 mb-7">
        <div class="w-10 h-10 bg-gradient-to-br from-indigo-500 to-indigo-700 rounded-xl flex items-center justify-center shadow-lg shadow-indigo-200/50">
            <i class="bi bi-pencil-square text-white"></i>
        </div>
        <div>
            <h2 class="text-base font-black text-slate-800">สั่งงานใหม่</h2>
            <p class="text-xs text-slate-400">กรอกรายละเอียดงานที่ต้องการมอบหมาย</p>
        </div>
    </div>

    <form id="createForm" class="space-y-5">
        <div>
            <label class="block text-xs font-black text-slate-500 uppercase tracking-wider mb-1.5">ชื่องาน <span class="text-rose-400">*</span></label>
            <input type="text" id="title" required
                   class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-4 py-3 text-sm focus:ring-2 focus:ring-indigo-500 outline-none transition-all"
                   placeholder="เช่น ใบงานที่ 1 เรื่องสมการเชิงเส้น">
        </div>

        <div class="grid grid-cols-2 gap-4">
            <!-- Subject select + manage -->
            <div>
                <label class="block text-xs font-black text-slate-500 uppercase tracking-wider mb-1.5">วิชา</label>
                <div class="flex gap-2">
                    <select id="subject"
                            class="flex-1 bg-slate-50 border border-slate-200 rounded-2xl px-4 py-3 text-sm focus:ring-2 focus:ring-indigo-500 outline-none transition-all">
                        <option value="">-- ไม่ระบุ --</option>
                    </select>
                    <button type="button" onclick="openManageSubjects()"
                            class="w-10 h-[46px] bg-slate-100 hover:bg-indigo-50 border border-slate-200 hover:border-indigo-300 rounded-2xl text-slate-500 hover:text-indigo-600 transition-all flex items-center justify-center"
                            title="จัดการรายวิชา">
                        <i class="bi bi-pencil-square text-sm"></i>
                    </button>
                </div>
            </div>
            <div>
                <label class="block text-xs font-black text-slate-500 uppercase tracking-wider mb-1.5">คะแนนเต็ม</label>
                <input type="number" id="max_score" value="10" min="1" max="100"
                       class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-4 py-3 text-sm focus:ring-2 focus:ring-indigo-500 outline-none transition-all">
            </div>
        </div>

        <div>
            <label class="block text-xs font-black text-slate-500 uppercase tracking-wider mb-1.5">ห้องเรียน <span class="text-rose-400">*</span></label>
            <div class="grid grid-cols-3 sm:grid-cols-4 gap-2" id="classroomPicker">
                <?php foreach ($classrooms as $cls): ?>
                <label class="flex items-center gap-2 p-2.5 bg-slate-50 border border-slate-200 rounded-xl cursor-pointer hover:border-indigo-300 hover:bg-indigo-50 transition-all has-[:checked]:border-indigo-500 has-[:checked]:bg-indigo-50">
                    <input type="checkbox" name="classroom[]" value="<?= htmlspecialchars($cls, ENT_QUOTES, 'UTF-8') ?>" class="w-4 h-4 accent-indigo-600">
                    <span class="text-xs font-bold text-slate-700"><?= htmlspecialchars($cls, ENT_QUOTES, 'UTF-8') ?></span>
                </label>
                <?php endforeach; ?>
            </div>
        </div>

        <div>
            <label class="block text-xs font-black text-slate-500 uppercase tracking-wider mb-1.5">กำหนดส่ง <span class="text-rose-400">*</span></label>
            <input type="datetime-local" id="due_date" required
                   class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-4 py-3 text-sm focus:ring-2 focus:ring-indigo-500 outline-none transition-all">
        </div>

        <div>
            <label class="block text-xs font-black text-slate-500 uppercase tracking-wider mb-1.5">รายละเอียด / คำสั่งงาน</label>
            <textarea id="description" rows="5"
                      class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-4 py-3 text-sm focus:ring-2 focus:ring-indigo-500 outline-none transition-all resize-none"
                      placeholder="อธิบายสิ่งที่ต้องการให้นักเรียนทำ..."></textarea>
        </div>

        <div class="flex gap-3 pt-2">
            <button type="submit"
                    class="flex-1 bg-indigo-600 text-white py-3 rounded-2xl font-bold text-sm shadow-lg shadow-indigo-200 hover:bg-indigo-700 hover:scale-[1.02] transition-all flex items-center justify-center gap-2">
                <i class="bi bi-send-fill"></i> สั่งงาน
            </button>
            <a href="<?= $base_path ?>/homework/dashboard.php"
               class="px-6 py-3 bg-slate-100 text-slate-600 rounded-2xl font-bold text-sm hover:bg-slate-200 transition-all">
                ยกเลิก
            </a>
        </div>
    </form>
</div>
</div>

<script>
// Load subjects into select on page load
async function loadSubjects(selectedName) {
    const res = await fetch('<?= $base_path ?>/homework/api/manage_subjects.php?action=list').then(r => r.json());
    const sel = document.getElementById('subject');
    const prev = selectedName !== undefined ? selectedName : sel.value;
    sel.innerHTML = '<option value="">-- ไม่ระบุ --</option>';
    (res.data || []).forEach(s => {
        const opt = document.createElement('option');
        opt.value = s.name;
        opt.textContent = s.name;
        if (s.name === prev) opt.selected = true;
        sel.appendChild(opt);
    });
}
loadSubjects();

// Manage subjects modal
async function openManageSubjects() {
    const res = await fetch('<?= $base_path ?>/homework/api/manage_subjects.php?action=list').then(r => r.json());
    const subjects = res.data || [];

    const listHtml = subjects.length
        ? subjects.map(s => `
            <div class="flex items-center justify-between bg-slate-50 rounded-xl px-3 py-2 mb-1" id="srow_${s.id}">
                <span class="text-sm font-bold text-slate-700">${escHtml(s.name)}</span>
                <button onclick="deleteSubject(${s.id},'${escHtml(s.name)}')"
                        class="text-rose-400 hover:text-rose-600 text-xs font-bold transition-colors">
                    <i class="bi bi-trash3"></i> ลบ
                </button>
            </div>`).join('')
        : '<p class="text-slate-400 text-sm text-center py-2">ยังไม่มีวิชา</p>';

    Swal.fire({
        title: '<span class="text-base font-black">จัดการรายวิชา</span>',
        html: `
            <div class="text-left mb-3" id="subjectListContainer">${listHtml}</div>
            <div class="flex gap-2">
                <input type="text" id="newSubjectName" placeholder="ชื่อวิชาใหม่..."
                       class="flex-1 border border-slate-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-400 outline-none"
                       onkeydown="if(event.key==='Enter'){event.preventDefault();addSubject();}">
                <button onclick="addSubject()"
                        class="bg-indigo-600 text-white px-4 py-2 rounded-xl text-sm font-bold hover:bg-indigo-700 transition-colors">
                    เพิ่ม
                </button>
            </div>`,
        showConfirmButton: true,
        confirmButtonText: 'เสร็จสิ้น',
        confirmButtonColor: '#4f46e5',
        showCloseButton: true,
        willClose: () => loadSubjects()
    });
}

function escHtml(str) {
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

window.addSubject = async function() {
    const input = document.getElementById('newSubjectName');
    const name  = input.value.trim();
    if (!name) return;

    const res = await fetch('<?= $base_path ?>/homework/api/manage_subjects.php?action=add', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ name })
    }).then(r => r.json());

    if (res.status === 'success') {
        input.value = '';
        const container = document.getElementById('subjectListContainer');
        const existsEmpty = container.querySelector('p');
        if (existsEmpty) existsEmpty.remove();
        container.insertAdjacentHTML('beforeend', `
            <div class="flex items-center justify-between bg-slate-50 rounded-xl px-3 py-2 mb-1" id="srow_${res.id}">
                <span class="text-sm font-bold text-slate-700">${escHtml(res.name)}</span>
                <button onclick="deleteSubject(${res.id},'${escHtml(res.name)}')"
                        class="text-rose-400 hover:text-rose-600 text-xs font-bold transition-colors">
                    <i class="bi bi-trash3"></i> ลบ
                </button>
            </div>`);
    } else {
        Swal.showValidationMessage(res.message);
    }
};

window.deleteSubject = async function(id, name) {
    const conf = await Swal.fire({
        title: `ลบ "${name}"?`,
        text: 'งานที่ใช้วิชานี้ไม่ได้รับผลกระทบ',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'ลบ',
        cancelButtonText: 'ยกเลิก',
        confirmButtonColor: '#ef4444'
    });
    if (!conf.isConfirmed) return;

    await fetch('<?= $base_path ?>/homework/api/manage_subjects.php?action=delete', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id })
    });
    document.getElementById('srow_' + id)?.remove();
};

document.getElementById('createForm').addEventListener('submit', async function(e) {
    e.preventDefault();

    const classrooms = [...document.querySelectorAll('input[name="classroom[]"]:checked')].map(c => c.value);
    if (!classrooms.length) {
        Swal.fire({ icon: 'warning', title: 'กรุณาเลือกห้องเรียนอย่างน้อย 1 ห้อง', confirmButtonColor: '#4f46e5' });
        return;
    }

    const payload = {
        title:       document.getElementById('title').value.trim(),
        subject:     document.getElementById('subject').value,
        max_score:   parseInt(document.getElementById('max_score').value),
        due_date:    document.getElementById('due_date').value,
        description: document.getElementById('description').value.trim(),
        classrooms,
    };

    Swal.fire({ title: 'กำลังบันทึก...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

    const res = await fetch('<?= $base_path ?>/homework/api/save_assignment.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    }).then(r => r.json());

    Swal.close();

    if (res.status === 'success') {
        await Swal.fire({ icon: 'success', title: 'สั่งงานเรียบร้อย!', text: `สร้างงาน ${res.count} รายการ`, confirmButtonColor: '#4f46e5' });
        location.href = '<?= $base_path ?>/homework/dashboard.php';
    } else {
        Swal.fire({ icon: 'error', title: 'ผิดพลาด', text: res.message, confirmButtonColor: '#4f46e5' });
    }
});
</script>

<?php require_once __DIR__ . '/../components/layout_end.php'; ?>
