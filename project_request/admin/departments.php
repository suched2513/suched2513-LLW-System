<?php
session_start();
$pageTitle = 'จัดการฝ่าย/กลุ่มงาน';
$pageSubtitle = 'กำหนดรายชื่อฝ่ายและลำดับการแสดงผล';
require_once __DIR__ . '/../components/layout_start.php';

$pdo = getPdo();
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'save') {
        $id = $_POST['id'] ?? '';
        $name = trim($_POST['name']);
        $order = (int)$_POST['order_no'];

        if ($id) {
            $pdo->prepare("UPDATE departments SET name=?, order_no=? WHERE id=?")->execute([$name, $order, $id]);
        } else {
            $pdo->prepare("INSERT INTO departments (name, order_no) VALUES (?, ?)")->execute([$name, $order]);
        }
        $message = 'บันทึกสำเร็จ';
    } elseif ($action === 'delete') {
        $pdo->prepare("DELETE FROM departments WHERE id = ?")->execute([$_POST['id']]);
        $message = 'ลบสำเร็จ';
    }
}

$departments = $pdo->query("SELECT * FROM departments ORDER BY order_no, name")->fetchAll();
?>

<div class="bg-white rounded-[2.5rem] p-8 shadow-xl shadow-slate-200/50 border border-slate-100 max-w-2xl">
    <div class="flex items-center justify-between mb-8">
        <h3 class="text-xl font-black text-slate-800">ฝ่าย / กลุ่มงาน</h3>
        <button onclick="openModal()" class="bg-blue-600 text-white px-6 py-2 rounded-xl font-bold shadow-lg shadow-blue-100 hover:bg-blue-700 transition-all text-sm">
            เพิ่มฝ่ายใหม่
        </button>
    </div>

    <div class="space-y-3">
        <?php foreach ($departments as $d): ?>
        <div class="flex items-center justify-between p-4 bg-slate-50 rounded-2xl border border-slate-100 group hover:border-blue-200 transition-all">
            <div class="flex items-center gap-4">
                <div class="w-8 h-8 bg-white rounded-lg flex items-center justify-center font-black text-xs text-slate-400 shadow-sm">
                    <?= $d['order_no'] ?>
                </div>
                <span class="font-bold text-slate-700"><?= htmlspecialchars($d['name']) ?></span>
            </div>
            <div class="flex items-center gap-2">
                <button onclick='editDept(<?= json_encode($d) ?>)' class="w-8 h-8 flex items-center justify-center text-slate-400 hover:text-blue-600 transition-all"><i class="bi bi-pencil-square"></i></button>
                <form method="POST" onsubmit="return confirm('ลบฝ่ายนี้?')">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= $d['id'] ?>">
                    <button type="submit" class="w-8 h-8 flex items-center justify-center text-slate-400 hover:text-rose-600 transition-all"><i class="bi bi-trash"></i></button>
                </form>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Modal -->
<div id="deptModal" class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm z-[100] hidden items-center justify-center p-4">
    <div class="bg-white w-full max-w-md rounded-[2.5rem] shadow-2xl p-8 sm:p-10">
        <h4 id="modalTitle" class="text-xl font-black text-slate-800 mb-6">เพิ่มฝ่าย</h4>
        <form method="POST" class="space-y-4">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" id="deptId">
            <div>
                <label class="block text-[11px] font-black text-slate-400 uppercase tracking-widest mb-2">ชื่อฝ่าย</label>
                <input type="text" name="name" id="deptName" class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-4 py-3 text-sm font-bold outline-none" required>
            </div>
            <div>
                <label class="block text-[11px] font-black text-slate-400 uppercase tracking-widest mb-2">ลำดับ</label>
                <input type="number" name="order_no" id="deptOrder" class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-4 py-3 text-sm font-bold outline-none" required>
            </div>
            <div class="flex gap-3 mt-6">
                <button type="button" onclick="closeModal()" class="flex-1 bg-slate-100 py-3 rounded-xl font-bold">ยกเลิก</button>
                <button type="submit" class="flex-1 bg-blue-600 text-white py-3 rounded-xl font-bold">บันทึก</button>
            </div>
        </form>
    </div>
</div>

<script>
function openModal() {
    document.getElementById('deptId').value = '';
    document.getElementById('deptName').value = '';
    document.getElementById('deptOrder').value = '0';
    document.getElementById('deptModal').classList.remove('hidden');
    document.getElementById('deptModal').classList.add('flex');
}
function closeModal() { document.getElementById('deptModal').classList.add('hidden'); document.getElementById('deptModal').classList.remove('flex'); }
function editDept(d) {
    document.getElementById('deptId').value = d.id;
    document.getElementById('deptName').value = d.name;
    document.getElementById('deptOrder').value = d.order_no;
    document.getElementById('deptModal').classList.remove('hidden');
    document.getElementById('deptModal').classList.add('flex');
}
</script>

<?php require_once __DIR__ . '/../components/layout_end.php'; ?>
