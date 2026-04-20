<?php
/**
 * manage_advisors.php — ศูนย์กลางการจัดการครูที่ปรึกษา (LLW Class Advisors)
 * กำหนดห้องเรียน 1 ห้องต่อครูที่ปรึกษา 2 คน
 */
session_start();
require_once __DIR__ . '/config/database.php';

// Auth Guard (Allow all logged in, restrict edit to super_admin)
if (!isset($_SESSION['llw_role'])) {
    header('Location: login.php'); exit();
}
$canEdit = ($_SESSION['llw_role'] === 'super_admin');

$pdo = getPdo();
$pageTitle = 'จัดการครูที่ปรึกษา';
$activeSystem = 'portal';

// --- Fetch Students to list Rooms ---
$rooms = $pdo->query("SELECT DISTINCT classroom FROM att_students WHERE classroom != '' ORDER BY classroom")->fetchAll(PDO::FETCH_COLUMN);

// --- Fetch Teachers for selection ---
$teachers = $pdo->query("SELECT user_id, firstname, lastname FROM llw_users WHERE role IN ('att_teacher', 'super_admin') AND status = 'active' ORDER BY firstname")->fetchAll();

// --- Fetch Existing Mappings ---
$mappings = $pdo->query("SELECT * FROM llw_class_advisors")->fetchAll();
$roomAdvisors = [];
foreach ($mappings as $m) {
    if (!isset($roomAdvisors[$m['classroom']])) $roomAdvisors[$m['classroom']] = [];
    $roomAdvisors[$m['classroom']][] = $m['user_id'];
}

require_once __DIR__ . '/components/layout_start.php';
?>

<div class="mb-8">
    <div class="flex items-center gap-4 mb-2">
        <div class="w-12 h-12 bg-blue-600 rounded-2xl flex items-center justify-center text-white text-2xl shadow-lg shadow-blue-200">
            <i class="bi bi-people-fill"></i>
        </div>
        <div>
            <h1 class="text-2xl font-black text-slate-800 tracking-tight">จัดการครูที่ปรึกษา</h1>
            <p class="text-sm text-slate-500 font-medium">กำหนดครูที่ปรึกษา 2 ท่านต่อห้องเรียน เพื่อสิทธิ์การอนุมัติและรายงาน</p>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
    
    <!-- Info Card -->
    <div class="lg:col-span-1 space-y-6">
        <div class="bg-gradient-to-br from-blue-600 to-indigo-700 rounded-[2rem] p-8 text-white shadow-xl shadow-blue-200/50 relative overflow-hidden">
            <div class="relative z-10">
                <i class="bi bi-info-circle-fill text-3xl opacity-50 mb-4 block"></i>
                <h3 class="text-xl font-bold mb-2">คำแนะนำ</h3>
                <ul class="text-sm space-y-3 opacity-90 font-medium">
                    <li class="flex gap-2"><span>•</span> <span>ระบบรองรับครูที่ปรึกษาได้มากกว่า 1 ท่าน (มาตรฐานโรงเรียนคือ 2 ท่าน)</span></li>
                    <li class="flex gap-2"><span>•</span> <span>ข้อมูลนี้จะถูกใช้ร่วมกันทั้งระบบ เช็คชื่อ, พฤติกรรม และรายงานต่างๆ</span></li>
                    <li class="flex gap-2"><span>•</span> <span>ครูที่ปรึกษาที่ถูกระบุ จะมีสิทธิ์อนุมัติ "ความดี" ของนักเรียนในห้องนั้นๆ</span></li>
                </ul>
            </div>
            <div class="absolute -right-10 -bottom-10 w-40 h-40 bg-white/10 rounded-full blur-3xl"></div>
        </div>
        
        <div class="bg-white rounded-[2rem] p-8 shadow-xl shadow-slate-100 border border-slate-50">
            <h3 class="text-lg font-black text-slate-800 mb-4">สถิติการกำหนดห้อง</h3>
            <div class="space-y-4">
                <div class="flex justify-between items-center">
                    <span class="text-sm text-slate-500 font-bold uppercase tracking-wider">ห้องทั้งหมด</span>
                    <span class="text-xl font-black text-blue-600"><?= count($rooms) ?></span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-sm text-slate-500 font-bold uppercase tracking-wider">กำหนดแล้ว</span>
                    <span class="text-xl font-black text-emerald-500"><?= count($roomAdvisors) ?></span>
                </div>
                <div class="w-full bg-slate-100 h-2 rounded-full overflow-hidden">
                    <div class="bg-emerald-500 h-full" style="width: <?= (count($roomAdvisors)/max(1, count($rooms)))*100 ?>%"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Table -->
    <div class="lg:col-span-2">
        <div class="bg-white rounded-[2rem] shadow-2xl shadow-slate-200/50 border border-slate-50 overflow-hidden">
            <div class="p-8 border-b border-slate-50 flex justify-between items-center">
                <h3 class="text-xl font-black text-slate-800">รายชื่อห้องเรียน</h3>
                <?php if ($canEdit): ?>
                <button onclick="saveAllMappings()" class="bg-blue-600 text-white px-6 py-2.5 rounded-xl font-bold text-sm shadow-lg shadow-blue-200 hover:scale-[1.02] transition-all flex items-center gap-2">
                    <i class="bi bi-cloud-check-fill"></i> บันทึกทั้งหมด
                </button>
                <?php endif; ?>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full border-collapse">
                    <thead>
                        <tr class="bg-slate-50/50">
                            <th class="px-8 py-5 text-left text-[10px] font-black text-slate-400 uppercase tracking-widest border-b border-slate-50">ห้องเรียน</th>
                            <th class="px-8 py-5 text-left text-[10px] font-black text-slate-400 uppercase tracking-widest border-b border-slate-50">ครูที่ปรึกษา 1</th>
                            <th class="px-8 py-5 text-left text-[10px] font-black text-slate-400 uppercase tracking-widest border-b border-slate-50">ครูที่ปรึกษา 2</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        <?php foreach ($rooms as $room): 
                            $assigned = $roomAdvisors[$room] ?? [];
                            $isMyRoom = in_array($_SESSION['user_id'], $assigned);
                        ?>
                        <tr class="hover:bg-slate-50/50 transition-colors group <?= $isMyRoom ? 'bg-blue-50/50' : '' ?>">
                            <td class="px-8 py-5">
                                <div class="flex items-center gap-3">
                                    <span class="text-lg font-black text-slate-700"><?= htmlspecialchars($room) ?></span>
                                    <?php if ($isMyRoom): ?>
                                    <span class="px-2 py-0.5 bg-blue-600 text-white text-[9px] font-black rounded-lg uppercase tracking-tighter">My Class</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="px-4 py-5">
                                <select class="advisor-select w-full bg-white border border-slate-200 rounded-xl px-4 py-3 text-sm focus:ring-2 focus:ring-blue-500 outline-none transition-all disabled:opacity-70 disabled:cursor-not-allowed" 
                                        data-room="<?= htmlspecialchars($room) ?>" data-idx="0" <?= $canEdit ? '' : 'disabled' ?>>
                                    <option value="">-- ไม่ระบุ --</option>
                                    <?php foreach ($teachers as $t): ?>
                                    <option value="<?= $t['user_id'] ?>" <?= (isset($assigned[0]) && $assigned[0] == $t['user_id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($t['firstname'] . ' ' . $t['lastname']) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td class="px-4 py-5">
                                <select class="advisor-select w-full bg-white border border-slate-200 rounded-xl px-4 py-3 text-sm focus:ring-2 focus:ring-blue-500 outline-none transition-all disabled:opacity-70 disabled:cursor-not-allowed"
                                        data-room="<?= htmlspecialchars($room) ?>" data-idx="1" <?= $canEdit ? '' : 'disabled' ?>>
                                    <option value="">-- ไม่ระบุ --</option>
                                    <?php foreach ($teachers as $t): ?>
                                    <option value="<?= $t['user_id'] ?>" <?= (isset($assigned[1]) && $assigned[1] == $t['user_id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($t['firstname'] . ' ' . $t['lastname']) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
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
async function saveAllMappings() {
    const selects = document.querySelectorAll('.advisor-select');
    const data = {};
    
    selects.forEach(sel => {
        const room = sel.dataset.room;
        if (!data[room]) data[room] = [];
        if (sel.value) data[room].push(sel.value);
    });

    try {
        Swal.fire({ title: 'กำลังบันทึก...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
        
        const response = await fetch('/api/save_advisors.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ mappings: data })
        });
        
        const res = await response.json();
        Swal.close();
        
        if (res.status === 'success') {
            Swal.fire({
                icon: 'success',
                title: 'สำเร็จ!',
                text: 'บันทึกข้อมูลครูที่ปรึกษาเรียบร้อยแล้ว',
                confirmButtonColor: '#2563eb'
            }).then(() => location.reload());
        } else {
            Swal.fire('ผิดพลาด', res.message, 'error');
        }
    } catch (e) {
        Swal.close();
        Swal.fire('ผิดพลาด', 'ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้', 'error');
    }
}
</script>

<?php require_once __DIR__ . '/components/layout_end.php'; ?>
