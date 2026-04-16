<?php
session_start();
require_once __DIR__ . '/../config.php';

// Auth guard
if (!isset($_SESSION['llw_role'])) {
    header('Location: /login.php');
    exit();
}

$pdo = getPdo();
$userId = $_SESSION['user_id'];
$groupId = $_GET['group_id'] ?? 0;
$phase = $_GET['phase'] ?? 'Plan';

// Fetch group details
$stmt = $pdo->prepare("SELECT * FROM plc_groups WHERE id = ?");
$stmt->execute([$groupId]);
$group = $stmt->fetch();

if (!$group) {
    header('Location: dashboard.php');
    exit();
}

// Phase Config
$phaseConfig = [
    'Plan'  => ['title' => 'PLAN (วิเคราะห์ปัญหาและวางแผน)', 'color' => 'blue',    'icon' => 'bi-pencil-square', 'detail_label' => 'การวิเคราะห์ปัญหา / วัตถุประสงค์ / ลำดับขั้นตอนการดำเนินงาน'],
    'Do'    => ['title' => 'DO (ปฏิบัติและสังเกตการเรียนรู้)',     'color' => 'emerald', 'icon' => 'bi-play-circle-fill', 'detail_label' => 'รายละเอียดการจัดกิจกรรม / นวัตกรรมที่ใช้ / บันทึกผลเบื้องต้น'],
    'Check' => ['title' => 'CHECK (สะท้อนผลและตรวจสอบ)',        'color' => 'amber',   'icon' => 'bi-search', 'detail_label' => 'ผลการทดลองใช้ / การเปรียบเทียบผลกับเป้าหมาย', 'reflection_label' => 'การสะท้อนผล (Reflection) เพื่อนำไปปรับปรุง'],
    'Act'   => ['title' => 'ACT (สรุปและรายงานผล)',            'color' => 'rose',    'icon' => 'bi-award-fill', 'detail_label' => 'สรุปองค์ความรู้ที่ได้ / ข้อเสนอแนะเพิ่มเติม'],
][$phase] ?? $phaseConfig['Plan'];

$pageTitle = "บันทึกกิจกรรม PLC";
$pageSubtitle = htmlspecialchars($group['group_name']);
$activeSystem = 'plc';

require_once __DIR__ . '/../components/layout_start.php';
?>

<div class="max-w-4xl mx-auto space-y-8 animate-in zoom-in-95 duration-700 pb-20">
    
    <!-- Phase Header -->
    <div class="flex items-center justify-between gap-6 px-4">
        <div class="flex items-center gap-6">
            <div class="w-16 h-16 rounded-[1.5rem] bg-<?= $phaseConfig['color'] ?>-50 text-<?= $phaseConfig['color'] ?>-600 flex items-center justify-center text-3xl shadow-lg shadow-<?= $phaseConfig['color'] ?>-100">
                <i class="bi <?= $phaseConfig['icon'] ?>"></i>
            </div>
            <div>
                <h2 class="text-2xl font-black text-slate-800 tracking-tight italic"><?= $phaseConfig['title'] ?></h2>
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-[0.2em] mt-1">New PDCA Log Entry</p>
            </div>
        </div>
        <a href="view_group.php?id=<?= $groupId ?>" class="w-12 h-12 bg-white rounded-2xl flex items-center justify-center text-slate-400 hover:text-rose-500 hover:bg-rose-50 shadow-xl shadow-slate-100/50 border border-slate-100 transition-all">
            <i class="bi bi-x-lg"></i>
        </a>
    </div>

    <!-- Form -->
    <div class="bg-white/70 backdrop-blur-xl rounded-[2.5rem] p-8 sm:p-12 shadow-2xl shadow-slate-200/50 border border-white/50 relative overflow-hidden group">
        <form id="addLogForm" class="space-y-8 relative z-10">
            <input type="hidden" name="action" value="add_log">
            <input type="hidden" name="group_id" value="<?= $groupId ?>">
            <input type="hidden" name="phase" value="<?= $phase ?>">

            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <div class="md:col-span-2">
                    <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest px-4 mb-3 block">หัวข้อกิจกรรม / ชื่อหัวข้อย่อย</label>
                    <input type="text" name="topic" required placeholder="เช่น ประชุมวางแผนพัฒนารูปแบบนวัตกรรม..." class="w-full bg-slate-50 border border-slate-100 rounded-3xl px-8 py-5 text-sm font-bold text-slate-800 focus:ring-4 focus:ring-<?= $phaseConfig['color'] ?>-500/10 focus:border-<?= $phaseConfig['color'] ?>-500 outline-none transition-all">
                </div>
                <div>
                    <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest px-4 mb-3 block">วันที่จัดกิจกรรม</label>
                    <input type="date" name="log_date" value="<?= date('Y-m-d') ?>" required class="w-full bg-slate-50 border border-slate-100 rounded-3xl px-8 py-5 text-sm font-bold text-slate-800 focus:ring-4 focus:ring-<?= $phaseConfig['color'] ?>-500/10 focus:border-<?= $phaseConfig['color'] ?>-500 outline-none transition-all">
                </div>

                <div class="md:col-span-3">
                    <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest px-4 mb-3 block"><?= $phaseConfig['detail_label'] ?></label>
                    <textarea name="details" rows="6" required placeholder="ระบุรายละเอียดที่ได้ดำเนินการ..." class="w-full bg-slate-50 border border-slate-100 rounded-[2rem] px-8 py-6 text-sm font-bold text-slate-800 focus:ring-4 focus:ring-<?= $phaseConfig['color'] ?>-500/10 focus:border-<?= $phaseConfig['color'] ?>-500 outline-none transition-all leading-relaxed"></textarea>
                </div>

                <?php if (isset($phaseConfig['reflection_label'])): ?>
                <div class="md:col-span-3">
                    <div class="p-8 bg-violet-50/30 rounded-[2rem] border border-violet-100/50">
                        <label class="text-[10px] font-black text-violet-400 uppercase tracking-widest mb-3 block"><?= $phaseConfig['reflection_label'] ?></label>
                        <textarea name="reflection" rows="4" placeholder="บันทึกสิ่งที่น่าพอใจ ปัญหาที่พบ และแนวทางแก้ไขคราวถัดไป..." class="w-full bg-white/50 border border-violet-100 rounded-2xl px-6 py-4 text-sm font-bold text-violet-800 focus:ring-4 focus:ring-violet-500/10 focus:border-violet-500 outline-none transition-all italic leading-relaxed"></textarea>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Evidence Upload -->
                <div class="md:col-span-3">
                    <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest px-4 mb-3 block">หลักฐานประกอบกิจกรรม (ภาพถ่าย/แผน/เอกสาร)</label>
                    <div id="dropZone" class="p-8 border-2 border-dashed border-slate-100 rounded-[2rem] text-center hover:border-<?= $phaseConfig['color'] ?>-500 transition-all group/file relative cursor-pointer active:scale-[0.99] bg-slate-50/30">
                        <input type="file" id="fileInput" name="evidence_files[]" class="hidden" multiple accept="image/*,.pdf,.docx,.xlsx,.pptx">
                        <div id="uploadPlaceholder">
                            <i class="bi bi-cloud-arrow-up text-4xl text-slate-300 group-hover/file:text-<?= $phaseConfig['color'] ?>-500 transition-colors mb-4 block"></i>
                            <p class="text-xs font-black text-slate-500">คลิกหรือลากไฟล์มาวางเพื่ออัปโหลด</p>
                            <p class="text-[10px] text-slate-300 mt-2 uppercase tracking-[0.2em] font-bold">PDF, Word, Excel, Images (MAX 10MB)</p>
                        </div>
                        <div id="fileList" class="hidden mt-4 grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <!-- Selected files will appear here -->
                        </div>
                    </div>
                </div>
            </div>

            <div class="pt-8 flex flex-col sm:flex-row gap-4">
                <button type="submit" class="flex-1 bg-gradient-to-r from-<?= $phaseConfig['color'] ?>-500 to-<?= $phaseConfig['color'] ?>-700 text-white rounded-[1.5rem] py-5 font-black text-sm shadow-xl shadow-<?= $phaseConfig['color'] ?>-200 hover:shadow-2xl hover:scale-[1.01] active:scale-[0.99] transition-all flex items-center justify-center gap-3">
                    บันทึกข้อมูลเรียบร้อย <i class="bi bi-check2-circle text-lg"></i>
                </button>
                <a href="view_group.php?id=<?= $groupId ?>" class="px-10 py-5 bg-slate-50 text-slate-400 rounded-[1.5rem] font-black text-sm hover:bg-slate-100 hover:text-slate-600 transition-all text-center">
                    ยกเลิก
                </a>
            </div>
        </form>
    </div>
</div>

<script>
    const fileInput = document.getElementById('fileInput');
    const dropZone = document.getElementById('dropZone');
    const fileList = document.getElementById('fileList');
    const uploadPlaceholder = document.getElementById('uploadPlaceholder');
    let selectedFiles = [];

    dropZone.addEventListener('click', () => fileInput.click());

    fileInput.addEventListener('change', (e) => {
        handleFiles(e.target.files);
    });

    function handleFiles(files) {
        selectedFiles = Array.from(files);
        renderFileList();
    }

    function renderFileList() {
        if (selectedFiles.length === 0) {
            fileList.classList.add('hidden');
            uploadPlaceholder.classList.remove('hidden');
            return;
        }

        fileList.classList.remove('hidden');
        uploadPlaceholder.classList.add('hidden');
        fileList.innerHTML = '';

        selectedFiles.forEach((file, index) => {
            const card = document.createElement('div');
            card.className = 'flex items-center gap-3 p-3 bg-white rounded-xl border border-slate-100 text-left';
            card.innerHTML = `
                <div class="w-8 h-8 rounded-lg bg-slate-50 flex items-center justify-center text-slate-400">
                    <i class="bi bi-file-earmark-check"></i>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-[10px] font-black text-slate-800 truncate">${file.name}</p>
                    <p class="text-[8px] text-slate-400 uppercase font-bold">${(file.size / 1024).toFixed(1)} KB</p>
                </div>
            `;
            fileList.appendChild(card);
        });
    }

    document.getElementById('addLogForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        
        try {
            const formData = new FormData(e.target);
            
            // Show loading
            Swal.fire({
                title: 'กำลังบันทึก...',
                allowOutsideClick: false,
                didOpen: () => Swal.showLoading()
            });

            const response = await fetch('api/plc_handler.php', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            if (result.status === 'success') {
                Swal.fire({
                    icon: 'success',
                    title: 'สำเร็จ',
                    text: result.message,
                    confirmButtonColor: '#7c3aed'
                }).then(() => {
                    location.href = 'view_group.php?id=<?= $groupId ?>';
                });
            } else {
                throw new Error(result.message);
            }
        } catch (error) {
            Swal.fire({
                icon: 'error',
                title: 'เกิดข้อผิดพลาด',
                text: error.message
            });
        }
    });
</script>

<?php require_once __DIR__ . '/../components/layout_end.php'; ?>
