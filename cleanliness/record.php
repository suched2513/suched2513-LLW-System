<?php
session_start();
require_once __DIR__ . '/../config.php';

// Auth guard
if (!isset($_SESSION['llw_role'])) {
    header('Location: /login.php');
    exit();
}

$id = $_GET['id'] ?? null;
if (!$id) {
    header('Location: index.php');
    exit();
}

$pdo = getPdo();
$area = null;
$classrooms = ['ม.1/1', 'ม.1/2', 'ม.2/1', 'ม.2/2', 'ม.3/1', 'ม.3/2', 'ม.4/1', 'ม.4/2', 'ม.5/1', 'ม.5/2', 'ม.6/1', 'ม.6/2'];

try {
    // Fetch area info
    $stmtArea = $pdo->prepare("SELECT * FROM clean_areas WHERE id = ?");
    $stmtArea->execute([$id]);
    $area = $stmtArea->fetch(PDO::FETCH_ASSOC);

    if (!$area) {
        header('Location: index.php');
        exit();
    }

    // Classroom Discovery
    $rooms = [];
    $check_tables = ['att_subjects', 'llw_class_advisors', 'att_students'];
    foreach ($check_tables as $tbl) {
        try {
            $data = $pdo->query("SELECT DISTINCT classroom FROM $tbl WHERE classroom != '' LIMIT 50")->fetchAll(PDO::FETCH_COLUMN);
            if ($data) $rooms = array_merge($rooms, $data);
        } catch (Exception $e2) {}
    }
    
    if (!empty($rooms)) {
        $classrooms = array_values(array_unique(array_filter($rooms)));
        sort($classrooms);
    }

} catch (Exception $e) {
    error_log($e->getMessage());
    header('Location: index.php');
    exit();
}

$pageTitle = 'บันทึกคะแนน';
$pageSubtitle = htmlspecialchars($area['name']);
$activeSystem = 'cleanliness';

require_once __DIR__ . '/../components/layout_start.php';
?>

<div class="max-w-4xl mx-auto">
    <a href="index.php" class="inline-flex items-center gap-2 text-slate-400 hover:text-emerald-600 font-bold text-sm mb-6 transition-colors">
        <i class="bi bi-arrow-left"></i>
        <span>กลับหน้าหลัก</span>
    </a>

    <div class="bg-white rounded-[2.5rem] shadow-2xl shadow-slate-200/50 border border-slate-100 overflow-hidden">
        <div class="bg-gradient-to-r from-emerald-500 to-teal-600 p-8 text-white">
            <div class="flex items-center gap-6">
                <div class="w-16 h-16 rounded-2xl bg-white/20 backdrop-blur-md flex items-center justify-center text-3xl">
                    <i class="bi bi-clipboard-check"></i>
                </div>
                <div>
                    <h2 class="text-2xl font-black"><?= htmlspecialchars($area['name']) ?></h2>
                    <p class="opacity-80 font-medium italic mt-1">
                        <i class="bi bi-info-circle mr-1"></i>
                        <?= htmlspecialchars($area['description'] ?: 'ไม่มีรายละเอียดพื้นที่') ?>
                    </p>
                </div>
            </div>
        </div>

        <form id="recordForm" class="p-8 space-y-8">
            <input type="hidden" name="area_id" value="<?= $area['id'] ?>">
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <div class="space-y-3">
                    <label class="text-xs font-black text-slate-400 uppercase tracking-widest pl-1">ห้องเรียนที่รับผิดชอบ</label>
                    <div class="flex flex-col gap-2">
                        <select onchange="document.getElementById('class_name_manual').value = this.value" class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-5 py-4 text-sm font-bold focus:ring-4 focus:ring-emerald-500/10 focus:border-emerald-500 outline-none transition-all">
                            <option value="">-- เลือกห้องเรียน --</option>
                            <?php foreach ($classrooms as $room): ?>
                                <option value="<?= htmlspecialchars($room) ?>" <?= $area['assigned_class'] === $room ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($room) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <input type="text" name="class_name" id="class_name_manual" value="<?= htmlspecialchars($area['assigned_class'] ?: '') ?>" placeholder="หรือพิมพ์ชื่อห้อง..." class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-5 py-4 text-sm font-bold focus:ring-4 focus:ring-emerald-500/10 focus:border-emerald-500 outline-none transition-all">
                    </div>
                </div>

                <div class="space-y-3">
                    <label class="text-xs font-black text-slate-400 uppercase tracking-widest pl-1">วันที่ประเมิน</label>
                    <input type="date" name="score_date" value="<?= date('Y-m-d') ?>" class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-5 py-4 text-sm font-bold focus:ring-4 focus:ring-emerald-500/10 focus:border-emerald-500 outline-none transition-all">
                </div>
            </div>

            <hr class="border-slate-50">

            <div class="grid grid-cols-1 md:grid-cols-2 gap-12">
                <div class="space-y-6">
                    <div class="flex items-center justify-between">
                        <label class="text-sm font-black text-slate-700">ความสะอาด (1-5)</label>
                        <span id="cleanliness_val" class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center font-black">3</span>
                    </div>
                    <div class="relative pt-1">
                        <input type="range" name="cleanliness_score" min="1" max="5" value="3" step="1" 
                               class="w-full h-2 bg-slate-100 rounded-lg appearance-none cursor-pointer accent-emerald-500"
                               oninput="document.getElementById('cleanliness_val').innerText = this.value; updateOverall();">
                    </div>
                </div>

                <div class="space-y-6">
                    <div class="flex items-center justify-between">
                        <label class="text-sm font-black text-slate-700">ความเป็นระเบียบ (1-5)</label>
                        <span id="orderliness_val" class="w-10 h-10 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center font-black">3</span>
                    </div>
                    <div class="relative pt-1">
                        <input type="range" name="orderliness_score" min="1" max="5" value="3" step="1" 
                               class="w-full h-2 bg-slate-100 rounded-lg appearance-none cursor-pointer accent-blue-500"
                               oninput="document.getElementById('orderliness_val').innerText = this.value; updateOverall();">
                    </div>
                </div>
            </div>

            <div class="space-y-3">
                <label class="text-xs font-black text-slate-400 uppercase tracking-widest pl-1">หมายเหตุ / ข้อเสนอแนะ</label>
                <textarea name="notes" rows="3" placeholder="ระบุรายละเอียดเพิ่มเติม..." class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-5 py-4 text-sm font-medium focus:ring-4 focus:ring-emerald-500/10 focus:border-emerald-500 outline-none transition-all"></textarea>
            </div>

            <div class="bg-slate-50 rounded-3xl p-6 flex items-center justify-between border border-slate-100">
                <div>
                    <h4 class="text-lg font-black text-slate-800">คะแนนรวมที่ได้</h4>
                </div>
                <div class="text-right">
                    <span id="total_score" class="text-4xl font-black text-emerald-600">60</span>
                    <span class="text-lg font-bold text-slate-400">/ 100</span>
                </div>
            </div>

            <button type="submit" class="w-full bg-emerald-600 text-white font-black py-5 rounded-[1.5rem] shadow-xl shadow-emerald-200 hover:bg-emerald-700 hover:scale-[1.01] transition-all flex items-center justify-center gap-3">
                <i class="bi bi-save2-fill"></i>
                บันทึกคะแนน
            </button>
        </form>
    </div>
</div>

<script>
function updateOverall() {
    const clean = parseInt(document.getElementsByName('cleanliness_score')[0].value);
    const order = parseInt(document.getElementsByName('orderliness_score')[0].value);
    document.getElementById('total_score').innerText = (clean + order) * 10;
}

document.getElementById('recordForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    const data = Object.fromEntries(formData.entries());
    
    if (!data.class_name) {
        Swal.fire({ icon: 'warning', title: 'กรุณาระบุห้องเรียน', confirmButtonColor: '#059669' });
        return;
    }

    try {
        const response = await fetch('api/save_score.php', {
            method: 'POST',
            body: JSON.stringify(data),
            headers: { 'Content-Type': 'application/json' }
        });
        const res = await response.json();
        if (res.status === 'success') {
            Swal.fire({ icon: 'success', title: 'บันทึกสำเร็จ', showConfirmButton: false, timer: 1500 })
            .then(() => window.location.href = 'index.php');
        } else {
            throw new Error(res.message);
        }
    } catch (error) {
        Swal.fire({ icon: 'error', title: 'ผิดพลาด', text: error.message });
    }
});
</script>

<?php require_once __DIR__ . '/../components/layout_end.php'; ?>
