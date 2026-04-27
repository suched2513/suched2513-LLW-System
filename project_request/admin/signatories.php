<?php
session_start();
$pageTitle = 'ตั้งค่าผู้ลงนาม';
$pageSubtitle = 'กำหนดชื่อและตำแหน่งของผู้มีอำนาจลงนามในเอกสาร';
require_once __DIR__ . '/../components/layout_start.php';

$pdo = getPdo();
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'save') {
        $id = $_POST['id'] ?? '';
        $role_label = trim($_POST['role_label']);
        $full_name = trim($_POST['full_name']);
        $position = trim($_POST['position']);
        $is_active = isset($_POST['is_active']) ? 1 : 0;

        if ($id) {
            $pdo->prepare("UPDATE signatories SET role_label=?, full_name=?, position=?, is_active=? WHERE id=?")
                ->execute([$role_label, $full_name, $position, $is_active, $id]);
        } else {
            $pdo->prepare("INSERT INTO signatories (role_label, full_name, position, is_active) VALUES (?, ?, ?, ?)")
                ->execute([$role_label, $full_name, $position, $is_active]);
        }
        $message = 'บันทึกสำเร็จ';
    }
}

$signatories = $pdo->query("SELECT * FROM signatories ORDER BY id")->fetchAll();
?>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
    <div class="bg-white rounded-[2.5rem] p-8 shadow-xl shadow-slate-200/50 border border-slate-100">
        <h3 class="text-xl font-black text-slate-800 mb-6">รายชื่อผู้ลงนาม</h3>
        <div class="space-y-4">
            <?php foreach ($signatories as $s): ?>
            <div class="p-6 bg-slate-50 rounded-2xl border border-slate-100 flex items-center justify-between">
                <div>
                    <span class="text-[10px] font-black bg-blue-100 text-blue-600 px-2 py-0.5 rounded uppercase mb-2 inline-block"><?= htmlspecialchars($s['role_label']) ?></span>
                    <p class="font-black text-slate-800"><?= htmlspecialchars($s['full_name']) ?></p>
                    <p class="text-xs text-slate-400 font-medium"><?= htmlspecialchars($s['position']) ?></p>
                </div>
                <button onclick='editSigner(<?= json_encode($s) ?>)' class="bg-white w-10 h-10 rounded-xl shadow-sm border border-slate-100 flex items-center justify-center text-slate-400 hover:text-blue-600 transition-all">
                    <i class="bi bi-pencil-fill"></i>
                </button>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="bg-white rounded-[2.5rem] p-8 shadow-xl shadow-slate-200/50 border border-slate-100">
        <h3 id="formTitle" class="text-xl font-black text-slate-800 mb-6">เพิ่ม/แก้ไข ผู้ลงนาม</h3>
        <form method="POST" class="space-y-5">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" id="signerId">
            
            <div>
                <label class="block text-[11px] font-black text-slate-400 uppercase tracking-widest mb-2">บทบาท (เช่น ผู้อำนวยการ)</label>
                <input type="text" name="role_label" id="roleLabel" class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-5 py-4 text-sm font-bold outline-none" required>
            </div>

            <div>
                <label class="block text-[11px] font-black text-slate-400 uppercase tracking-widest mb-2">ชื่อ-นามสกุล</label>
                <input type="text" name="full_name" id="fullName" class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-5 py-4 text-sm font-bold outline-none" required>
            </div>

            <div>
                <label class="block text-[11px] font-black text-slate-400 uppercase tracking-widest mb-2">ตำแหน่ง</label>
                <input type="text" name="position" id="position" class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-5 py-4 text-sm font-bold outline-none" required>
            </div>

            <div class="flex items-center gap-3 py-2">
                <input type="checkbox" name="is_active" id="isActive" checked class="w-5 h-5 rounded-md border-slate-200 text-blue-600 focus:ring-blue-500">
                <label for="isActive" class="text-sm font-bold text-slate-600">ใช้งาน (Active)</label>
            </div>

            <div class="flex gap-4 pt-4">
                <button type="reset" onclick="resetForm()" class="flex-1 bg-slate-100 text-slate-500 py-4 rounded-2xl font-black text-sm">รีเซ็ต</button>
                <button type="submit" class="flex-[2] bg-blue-600 text-white py-4 rounded-2xl font-black text-sm shadow-xl shadow-blue-100">บันทึกข้อมูล</button>
            </div>
        </form>
    </div>
</div>

<script>
function editSigner(s) {
    document.getElementById('formTitle').innerText = 'แก้ไขผู้ลงนาม: ' + s.role_label;
    document.getElementById('signerId').value = s.id;
    document.getElementById('roleLabel').value = s.role_label;
    document.getElementById('fullName').value = s.full_name;
    document.getElementById('position').value = s.position;
    document.getElementById('isActive').checked = s.is_active == 1;
}
function resetForm() {
    document.getElementById('formTitle').innerText = 'เพิ่ม/แก้ไข ผู้ลงนาม';
    document.getElementById('signerId').value = '';
}
</script>

<?php require_once __DIR__ . '/../components/layout_end.php'; ?>
