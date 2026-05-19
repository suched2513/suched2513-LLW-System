<?php
session_start();
require_once __DIR__ . '/../config.php';

if (!isset($_SESSION['llw_role'])) { header('Location: ' . $base_path . '/login.php'); exit(); }
if ($_SESSION['llw_role'] !== 'super_admin') { header('Location: ' . $base_path . '/login.php'); exit(); }

$pdo = getPdo();

// Classroom list from att_students
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
            <div>
                <label class="block text-xs font-black text-slate-500 uppercase tracking-wider mb-1.5">วิชา</label>
                <input type="text" id="subject"
                       class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-4 py-3 text-sm focus:ring-2 focus:ring-indigo-500 outline-none transition-all"
                       placeholder="เช่น คณิตศาสตร์">
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
document.getElementById('createForm').addEventListener('submit', async function(e) {
    e.preventDefault();

    const classrooms = [...document.querySelectorAll('input[name="classroom[]"]:checked')].map(c => c.value);
    if (!classrooms.length) {
        Swal.fire({ icon: 'warning', title: 'กรุณาเลือกห้องเรียนอย่างน้อย 1 ห้อง', confirmButtonColor: '#4f46e5' });
        return;
    }

    const payload = {
        title:       document.getElementById('title').value.trim(),
        subject:     document.getElementById('subject').value.trim(),
        max_score:   parseInt(document.getElementById('max_score').value),
        due_date:    document.getElementById('due_date').value,
        description: document.getElementById('description').value.trim(),
        classrooms:  classrooms,
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
