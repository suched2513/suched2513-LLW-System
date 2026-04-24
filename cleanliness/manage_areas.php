<?php
session_start();
require_once __DIR__ . '/../config.php';

// Role guard - Super Admin only
if (!isset($_SESSION['llw_role']) || !in_array($_SESSION['llw_role'], ['super_admin'])) {
    header('Location: index.php');
    exit();
}

try {
    $pdo = getPdo();
    
    // Process form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'add') {
            $stmt = $pdo->prepare("INSERT INTO clean_areas (name, description, assigned_class) VALUES (?, ?, ?)");
            $stmt->execute([$_POST['name'], $_POST['description'], $_POST['assigned_class']]);
        } elseif ($action === 'edit') {
            $stmt = $pdo->prepare("UPDATE clean_areas SET name = ?, description = ?, assigned_class = ? WHERE id = ?");
            $stmt->execute([$_POST['name'], $_POST['description'], $_POST['assigned_class'], $_POST['id']]);
        } elseif ($action === 'delete') {
            $stmt = $pdo->prepare("DELETE FROM clean_areas WHERE id = ?");
            $stmt->execute([$_POST['id']]);
        }
        header('Location: manage_areas.php');
        exit();
    }

    $areas = $pdo->query("SELECT * FROM clean_areas ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
    $classrooms = $pdo->query("SELECT DISTINCT classroom FROM att_students WHERE classroom IS NOT NULL AND classroom != '' ORDER BY classroom ASC")->fetchAll(PDO::FETCH_COLUMN);

} catch (Exception $e) {
    error_log($e->getMessage());
    $areas = [];
}

$pageTitle = 'จัดการพื้นที่ประเมิน';
$pageSubtitle = 'กำหนดขอบเขตและห้องเรียนที่รับผิดชอบ';
$activeSystem = 'cleanliness';

require_once __DIR__ . '/../components/layout_start.php';
?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
    <!-- Form Area -->
    <div class="lg:col-span-1">
        <div class="bg-white rounded-[2rem] shadow-xl shadow-slate-200/50 border border-slate-100 p-8 sticky top-8">
            <h3 class="text-xl font-black text-slate-800 flex items-center gap-3 mb-6">
                <i class="bi bi-plus-circle-fill text-emerald-500"></i>
                <span id="formTitle">เพิ่มพื้นที่ใหม่</span>
            </h3>
            
            <form method="POST" id="areaForm" class="space-y-6">
                <input type="hidden" name="action" id="formAction" value="add">
                <input type="hidden" name="id" id="areaId" value="">

                <div class="space-y-2">
                    <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest pl-1">ชื่อพื้นที่</label>
                    <input type="text" name="name" id="areaName" required class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-5 py-4 text-sm font-bold focus:ring-4 focus:ring-emerald-500/10 focus:border-emerald-500 outline-none transition-all">
                </div>

                <div class="space-y-2">
                    <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest pl-1">ห้องเรียนที่รับผิดชอบ</label>
                    <select name="assigned_class" id="areaClass" class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-5 py-4 text-sm font-bold focus:ring-4 focus:ring-emerald-500/10 focus:border-emerald-500 outline-none transition-all">
                        <option value="">-- ไม่ระบุ --</option>
                        <?php foreach ($classrooms as $room): ?>
                            <option value="<?= htmlspecialchars($room) ?>"><?= htmlspecialchars($room) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="space-y-2">
                    <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest pl-1">รายละเอียด</label>
                    <textarea name="description" id="areaDesc" rows="3" class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-5 py-4 text-sm font-medium focus:ring-4 focus:ring-emerald-500/10 focus:border-emerald-500 outline-none transition-all"></textarea>
                </div>

                <div class="flex gap-3 pt-2">
                    <button type="submit" class="flex-1 bg-emerald-600 text-white font-black py-4 rounded-2xl shadow-lg shadow-emerald-200 hover:bg-emerald-700 transition-all">บันทึกข้อมูล</button>
                    <button type="button" id="cancelBtn" class="hidden bg-slate-100 text-slate-500 font-black py-4 px-6 rounded-2xl hover:bg-slate-200 transition-all">ยกเลิก</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Table Area -->
    <div class="lg:col-span-2">
        <div class="bg-white/70 backdrop-blur-xl rounded-[2.5rem] shadow-2xl shadow-slate-200/40 border border-white p-8">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="bg-slate-50">
                            <th class="px-6 py-4 text-left text-[10px] font-black text-slate-400 uppercase tracking-widest rounded-l-2xl">ชื่อพื้นที่</th>
                            <th class="px-6 py-4 text-left text-[10px] font-black text-slate-400 uppercase tracking-widest">ห้องรับผิดชอบ</th>
                            <th class="px-6 py-4 text-center text-[10px] font-black text-slate-400 uppercase tracking-widest rounded-r-2xl">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        <?php foreach ($areas as $area): ?>
                            <tr class="hover:bg-white/50 transition-colors">
                                <td class="px-6 py-5">
                                    <span class="text-sm font-black text-slate-800"><?= htmlspecialchars($area['name']) ?></span>
                                    <p class="text-xs text-slate-400 mt-0.5 line-clamp-1"><?= htmlspecialchars($area['description'] ?: '-') ?></p>
                                </td>
                                <td class="px-6 py-5">
                                    <span class="px-3 py-1 rounded-full bg-emerald-50 text-emerald-600 text-xs font-black">
                                        <?= htmlspecialchars($area['assigned_class'] ?: 'ยังไม่ระบุ') ?>
                                    </span>
                                </td>
                                <td class="px-6 py-5 text-center">
                                    <div class="flex items-center justify-center gap-2">
                                        <button onclick="editArea(<?= htmlspecialchars(json_encode($area)) ?>)" class="w-8 h-8 rounded-lg bg-blue-50 text-blue-400 hover:bg-blue-500 hover:text-white transition-all">
                                            <i class="bi bi-pencil-square"></i>
                                        </button>
                                        <button onclick="deleteArea(<?= $area['id'] ?>)" class="w-8 h-8 rounded-lg bg-rose-50 text-rose-400 hover:bg-rose-500 hover:text-white transition-all">
                                            <i class="bi bi-trash3"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
function editArea(area) {
    document.getElementById('formTitle').innerText = 'แก้ไขข้อมูลพื้นที่';
    document.getElementById('formAction').value = 'edit';
    document.getElementById('areaId').value = area.id;
    document.getElementById('areaName').value = area.name;
    document.getElementById('areaClass').value = area.assigned_class || '';
    document.getElementById('areaDesc').value = area.description || '';
    document.getElementById('cancelBtn').classList.remove('hidden');
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

document.getElementById('cancelBtn').onclick = function() {
    document.getElementById('formTitle').innerText = 'เพิ่มพื้นที่ใหม่';
    document.getElementById('formAction').value = 'add';
    document.getElementById('areaId').value = '';
    document.getElementById('areaForm').reset();
    this.classList.add('hidden');
};

function deleteArea(id) {
    Swal.fire({
        title: 'ลบพื้นที่นี้?',
        text: "ข้อมูลการประเมินย้อนหลังในพื้นนี้นี้จะถูกลบไปด้วย!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#e11d48',
        confirmButtonText: 'ลบพื้นที่',
        cancelButtonText: 'ยกเลิก'
    }).then((result) => {
        if (result.isConfirmed) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `<input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="${id}">`;
            document.body.appendChild(form);
            form.submit();
        }
    });
}
</script>

<?php require_once __DIR__ . '/../components/layout_end.php'; ?>
