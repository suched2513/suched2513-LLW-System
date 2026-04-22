<?php
session_start();
require_once __DIR__ . '/../config.php';

// Auth guard
if (!isset($_SESSION['llw_role'])) {
    header('Location: /login.php');
    exit();
}

$pageTitle = 'ขออนุญาตดำเนินงาน';
$pageSubtitle = 'กรอกรายละเอียดเพื่อขออนุมัติเบิกจ่ายงบประมาณโครงการ';
$activeSystem = 'budget';

try {
    $pdo = getPdo();
    
    // Get Projects for current active year
    $stmt = $pdo->query("SELECT id FROM sbms_fiscal_years WHERE is_active = 1 LIMIT 1");
    $activeYearId = $stmt->fetchColumn();
    
    $stmt = $pdo->prepare("SELECT id, project_name, project_code FROM sbms_projects WHERE fiscal_year_id = ? ORDER BY project_name");
    $stmt->execute([$activeYearId]);
    $projects = $stmt->fetchAll();
    
} catch (Exception $e) {
    error_log($e->getMessage());
    $projects = [];
}

require_once __DIR__ . '/../components/layout_start.php';
?>

<div class="max-w-5xl mx-auto">
    <div class="bg-white/70 backdrop-blur-xl rounded-[2.5rem] shadow-2xl border border-white/50 overflow-hidden">
        <div class="bg-gradient-to-r from-emerald-600 to-teal-600 p-8 text-white">
            <h3 class="text-xl font-black flex items-center gap-3">
                <i class="bi bi-pencil-square"></i> แบบฟอร์มขออนุญาตดำเนินงานและสรุปโครงการ
            </h3>
        </div>
        
        <form id="requestForm" action="api/save_request.php" method="POST" class="p-8 sm:p-12 space-y-10">
            <!-- Section 1: กิจกรรม -->
            <div class="space-y-6">
                <div class="flex items-center gap-3 mb-4">
                    <span class="w-8 h-8 rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center text-sm font-black">1</span>
                    <h4 class="text-lg font-black text-slate-800">รายละเอียดกิจกรรม</h4>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="md:col-span-2">
                        <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 block">เลือกโครงการ</label>
                        <select id="project_id" name="project_id" required onchange="fetchActivities(this.value)" class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-6 py-4 text-sm focus:ring-2 focus:ring-emerald-500 outline-none transition-all">
                            <option value="">-- เลือกโครงการ --</option>
                            <?php foreach ($projects as $p): ?>
                            <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['project_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 block">เลือกกิจกรรม</label>
                        <select id="activity_id" name="activity_id" required class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-6 py-4 text-sm focus:ring-2 focus:ring-emerald-500 outline-none transition-all">
                            <option value="">-- เลือกกิจกรรม --</option>
                        </select>
                    </div>

                    <div>
                        <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 block">งบประมาณที่จัดสรร (บาท)</label>
                        <input type="text" id="budget_allocated" readonly class="w-full bg-slate-100 border border-slate-200 rounded-2xl px-6 py-4 text-sm text-slate-500 outline-none">
                    </div>
                    
                    <div>
                        <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 block">เบิกจ่ายไปแล้ว (บาท)</label>
                        <input type="text" id="budget_used" readonly class="w-full bg-slate-100 border border-slate-200 rounded-2xl px-6 py-4 text-sm text-slate-500 outline-none">
                    </div>

                    <div>
                        <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 block">จ่ายครั้งนี้ (บาท)</label>
                        <input type="number" step="0.01" name="amount" required class="w-full bg-emerald-50 border border-emerald-100 rounded-2xl px-6 py-4 text-sm focus:ring-2 focus:ring-emerald-500 outline-none font-bold text-emerald-700">
                    </div>
                </div>
            </div>

            <!-- Section 2: ผู้รับผิดชอบ -->
            <div class="space-y-6 pt-6 border-t border-slate-100">
                <div class="flex items-center gap-3 mb-4">
                    <span class="w-8 h-8 rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center text-sm font-black">2</span>
                    <h4 class="text-lg font-black text-slate-800">ผู้รับผิดชอบโครงการ/กิจกรรม</h4>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 block">ชื่อ-นามสกุล</label>
                        <input type="text" name="requester_name" value="<?= $_SESSION['fullname'] ?>" required class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-6 py-4 text-sm focus:ring-2 focus:ring-emerald-500 outline-none transition-all">
                    </div>
                    <div>
                        <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 block">ตำแหน่ง</label>
                        <input type="text" name="requester_position" placeholder="เช่น ครูวิทยฐานะชำนาญการพิเศษ" required class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-6 py-4 text-sm focus:ring-2 focus:ring-emerald-500 outline-none transition-all">
                    </div>
                </div>
            </div>

            <!-- Section 3: เอกสารอ้างอิง -->
            <div class="space-y-6 pt-6 border-t border-slate-100">
                <div class="flex items-center gap-3 mb-4">
                    <span class="w-8 h-8 rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center text-sm font-black">3</span>
                    <h4 class="text-lg font-black text-slate-800">ข้อมูลเลขที่หนังสือ</h4>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 block">เลขที่หนังสือ / บันทึกข้อความ</label>
                        <input type="text" name="book_no" placeholder="ศธ 04321/..." required class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-6 py-4 text-sm focus:ring-2 focus:ring-emerald-500 outline-none transition-all">
                    </div>
                    <div>
                        <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 block">ลงวันที่</label>
                        <input type="date" name="book_date" value="<?= date('Y-m-d') ?>" required class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-6 py-4 text-sm focus:ring-2 focus:ring-emerald-500 outline-none transition-all">
                    </div>
                </div>
            </div>

            <!-- Section 4: ลงนาม -->
            <div class="space-y-6 pt-6 border-t border-slate-100">
                <div class="flex items-center gap-3 mb-4">
                    <span class="w-8 h-8 rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center text-sm font-black">4</span>
                    <h4 class="text-lg font-black text-slate-800">ลงลายมือชื่อ</h4>
                </div>
                
                <div class="flex flex-col items-center p-6 bg-slate-50 rounded-[2rem] border border-slate-200 border-dashed">
                    <canvas id="signature-pad" class="bg-white rounded-xl shadow-inner border border-slate-200 w-full max-w-[400px] h-[150px] cursor-crosshair"></canvas>
                    <div class="mt-4 flex gap-4">
                        <button type="button" onclick="clearSignature()" class="text-[10px] font-black text-rose-500 uppercase tracking-widest hover:text-rose-700 transition-all">
                            <i class="bi bi-eraser-fill"></i> ล้างลายเซ็น
                        </button>
                    </div>
                    <input type="hidden" name="signature" id="signature_input">
                </div>
            </div>

            <div class="pt-10">
                <button type="submit" class="w-full bg-emerald-600 hover:bg-emerald-700 text-white py-6 rounded-[2rem] font-black text-lg shadow-2xl shadow-emerald-200 hover:scale-[1.02] transition-all flex items-center justify-center gap-4">
                    <i class="bi bi-save-fill"></i> บันทึกข้อมูลและออกรายงาน
                </button>
                <p class="text-center text-[10px] text-slate-400 mt-4 uppercase tracking-[0.2em] font-black italic">LLW School Management System - SBMS Module</p>
            </div>
        </form>
    </div>
</div>

<script>
    // Signature Pad Logic
    const canvas = document.getElementById('signature-pad');
    const ctx = canvas.getContext('2d');
    let isDrawing = false;
    let lastX = 0;
    let lastY = 0;

    function getPointerPos(canvas, e) {
        const rect = canvas.getBoundingClientRect();
        const clientX = e.touches ? e.touches[0].clientX : e.clientX;
        const clientY = e.touches ? e.touches[0].clientY : e.clientY;
        return {
            x: clientX - rect.left,
            y: clientY - rect.top
        };
    }

    function startDrawing(e) {
        isDrawing = true;
        const pos = getPointerPos(canvas, e);
        lastX = pos.x; lastY = pos.y;
    }

    function draw(e) {
        if (!isDrawing) return;
        const pos = getPointerPos(canvas, e);
        ctx.beginPath();
        ctx.moveTo(lastX, lastY);
        ctx.lineTo(pos.x, pos.y);
        ctx.strokeStyle = '#000';
        ctx.lineWidth = 2;
        ctx.lineCap = 'round';
        ctx.stroke();
        lastX = pos.x; lastY = pos.y;
        e.preventDefault();
    }

    function stopDrawing() {
        isDrawing = false;
        document.getElementById('signature_input').value = canvas.toDataURL();
    }

    canvas.addEventListener('mousedown', startDrawing);
    canvas.addEventListener('mousemove', draw);
    canvas.addEventListener('mouseup', stopDrawing);
    canvas.addEventListener('touchstart', startDrawing);
    canvas.addEventListener('touchmove', draw);
    canvas.addEventListener('touchend', stopDrawing);

    function clearSignature() {
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        document.getElementById('signature_input').value = '';
    }

    // AJAX Fetch Activities
    async function fetchActivities(projectId) {
        const select = document.getElementById('activity_id');
        select.innerHTML = '<option value="">-- กำลังโหลด... --</option>';
        
        if (!projectId) {
            select.innerHTML = '<option value="">-- เลือกโครงการก่อน --</option>';
            return;
        }

        try {
            const response = await fetch(`api/get_activities.php?project_id=${projectId}`);
            const data = await response.json();
            
            select.innerHTML = '<option value="">-- เลือกกิจกรรม --</option>';
            data.forEach(act => {
                const opt = document.createElement('option');
                opt.value = act.id;
                opt.text = act.activity_name;
                opt.dataset.allocated = act.budget_allocated;
                opt.dataset.used = act.budget_used;
                select.appendChild(opt);
            });
        } catch (e) {
            select.innerHTML = '<option value="">-- เกิดข้อผิดพลาด --</option>';
        }
    }

    document.getElementById('activity_id').onchange = function() {
        const opt = this.options[this.selectedIndex];
        if (opt.value) {
            document.getElementById('budget_allocated').value = new Intl.NumberFormat().format(opt.dataset.allocated);
            document.getElementById('budget_used').value = new Intl.NumberFormat().format(opt.dataset.used);
        } else {
            document.getElementById('budget_allocated').value = '';
            document.getElementById('budget_used').value = '';
        }
    };
</script>

<?php require_once __DIR__ . '/../components/layout_end.php'; ?>
