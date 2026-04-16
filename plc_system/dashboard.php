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
$userRole = $_SESSION['llw_role'];

// Fetch user's PLC groups
$stmt = $pdo->prepare("
    SELECT g.*, m.role as my_role,
           (SELECT COUNT(*) FROM plc_logs l WHERE l.group_id = g.id) as log_count,
           (SELECT COUNT(DISTINCT phase) FROM plc_logs l WHERE l.group_id = g.id) as phase_count
    FROM plc_groups g
    JOIN plc_members m ON g.id = m.group_id
    WHERE m.user_id = ?
    ORDER BY g.created_at DESC
");
$stmt->execute([$userId]);
$myGroups = $stmt->fetchAll();

// Statistics
$totalGroups = count($myGroups);
$activePdca = 0;
foreach ($myGroups as $g) {
    if ($g['status'] === 'active') $activePdca++;
}

$pageTitle = 'PLC Dashboard';
$pageSubtitle = 'ระบบบริหารจัดการชุมชนแห่งการเรียนรู้ทางวิชาชีพ';
$activeSystem = 'plc';

require_once __DIR__ . '/../components/layout_start.php';
?>

<div class="space-y-8 animate-in fade-in duration-700">
    <!-- Header Stats -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="bg-gradient-to-br from-violet-600 to-purple-700 rounded-[2rem] p-8 text-white shadow-xl shadow-violet-200/50 relative overflow-hidden group">
            <div class="absolute -right-4 -top-4 w-32 h-32 bg-white/10 rounded-full blur-3xl group-hover:scale-150 transition-transform duration-700"></div>
            <p class="text-xs font-black uppercase tracking-[0.2em] opacity-80">กลุ่ม PLC ของฉัน</p>
            <div class="flex items-end justify-between mt-4">
                <p class="text-5xl font-black italic"><?= $totalGroups ?></p>
                <i class="bi bi-people-fill text-4xl opacity-30"></i>
            </div>
        </div>

        <div class="bg-white rounded-[2rem] p-8 shadow-xl shadow-slate-100/50 border border-slate-100 group hover:border-violet-200 transition-all">
            <p class="text-[10px] font-black text-slate-400 uppercase tracking-[0.2em]">กำลังดำเนินการ (Active)</p>
            <div class="flex items-end justify-between mt-4">
                <p class="text-5xl font-black text-slate-800 italic"><?= $activePdca ?></p>
                <div class="w-12 h-12 bg-emerald-50 rounded-2xl flex items-center justify-center text-emerald-500">
                    <i class="bi bi-activity text-2xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-[2rem] p-8 shadow-xl shadow-slate-100/50 border border-slate-100 group hover:border-violet-200 transition-all relative overflow-hidden">
            <div class="absolute inset-0 bg-gradient-to-br from-violet-50/50 to-transparent opacity-0 group-hover:opacity-100 transition-opacity"></div>
            <p class="text-[10px] font-black text-slate-400 uppercase tracking-[0.2em]">บันทึกกิจกรรมทั้งหมด</p>
            <div class="flex items-end justify-between mt-4">
                <?php
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM plc_logs l JOIN plc_members m ON l.group_id = m.group_id WHERE m.user_id = ?");
                $stmt->execute([$userId]);
                $totalLogs = $stmt->fetchColumn();
                ?>
                <p class="text-5xl font-black text-slate-800 italic"><?= $totalLogs ?></p>
                <div class="w-12 h-12 bg-violet-50 rounded-2xl flex items-center justify-center text-violet-500">
                    <i class="bi bi-journal-text text-2xl"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Actions & List -->
    <div class="flex flex-col lg:flex-row gap-8">
        <!-- Group List -->
        <div class="flex-1 space-y-6">
            <div class="flex items-center justify-between px-2">
                <h3 class="text-xl font-black text-slate-800 tracking-tight">รายการกลุ่ม PLC</h3>
                <button onclick="openCreateModal()" class="flex items-center gap-2 bg-violet-600 text-white px-6 py-3 rounded-2xl font-bold shadow-lg shadow-violet-200 hover:bg-violet-700 hover:scale-[1.02] active:scale-95 transition-all text-sm">
                    <i class="bi bi-plus-lg"></i> สร้างกลุ่มใหม่
                </button>
            </div>

            <?php if (empty($myGroups)): ?>
            <div class="bg-white rounded-[2.5rem] p-16 text-center border-2 border-dashed border-slate-100">
                <div class="w-20 h-20 bg-slate-50 rounded-full flex items-center justify-center mx-auto mb-6 text-slate-300">
                    <i class="bi bi-journal-x text-4xl"></i>
                </div>
                <h4 class="text-lg font-black text-slate-800">ยังไม่มีข้อมูลกลุ่ม PLC</h4>
                <p class="text-slate-400 text-sm mt-2">เริ่มต้นโดยการสร้างกลุ่มใหม่หรือขอเข้าร่วมกลุ่ม</p>
            </div>
            <?php else: ?>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <?php foreach ($myGroups as $group): 
                    $progress = ($group['phase_count'] / 4) * 100;
                ?>
                <a href="view_group.php?id=<?= $group['id'] ?>" class="group bg-white rounded-[2.5rem] p-8 shadow-xl shadow-slate-100/50 border border-slate-100 hover:border-violet-500 transition-all duration-500 flex flex-col h-full">
                    <div class="flex items-start justify-between mb-6">
                        <div class="w-14 h-14 bg-gradient-to-br from-violet-500 to-purple-600 rounded-2xl flex items-center justify-center text-white text-2xl shadow-lg shadow-violet-100 group-hover:rotate-6 transition-transform">
                            <i class="bi bi-bookmarks-fill"></i>
                        </div>
                        <span class="px-3 py-1 rounded-full bg-violet-50 text-violet-600 text-[10px] font-black uppercase tracking-widest">
                            <?= htmlspecialchars($group['my_role']) ?>
                        </span>
                    </div>
                    
                    <h4 class="text-lg font-black text-slate-800 group-hover:text-violet-600 transition-colors"><?= htmlspecialchars($group['group_name']) ?></h4>
                    <p class="text-slate-400 text-xs font-bold mt-1 uppercase tracking-wider italic">
                        ปีการศึกษา <?= htmlspecialchars($group['academic_year']) ?> / <?= htmlspecialchars($group['semester']) ?>
                    </p>

                    <div class="mt-8 space-y-3">
                        <div class="flex justify-between items-center text-[10px] font-black uppercase tracking-widest text-slate-400">
                            <span>PDCA PROGRESS</span>
                            <span class="text-violet-600"><?= number_format($progress) ?>%</span>
                        </div>
                        <div class="h-2 bg-slate-50 rounded-full overflow-hidden">
                            <div class="h-full bg-gradient-to-r from-violet-500 to-purple-500 transition-all duration-1000" style="width: <?= $progress ?>%"></div>
                        </div>
                    </div>

                    <div class="mt-auto pt-6 flex items-center justify-between border-t border-slate-50 mt-6">
                        <div class="flex -space-x-3">
                            <!-- Placeholder for member avatars -->
                            <?php for($i=0; $i<3; $i++): ?>
                            <div class="w-8 h-8 rounded-full bg-slate-100 border-2 border-white flex items-center justify-center text-[10px] font-bold text-slate-400">
                                <i class="bi bi-person"></i>
                            </div>
                            <?php endfor; ?>
                        </div>
                        <span class="text-slate-400 text-[10px] font-black uppercase tracking-widest italic">
                            <?= $group['log_count'] ?> กิจกรรม
                        </span>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- Right Side: Recent Activity or Info -->
        <div class="w-full lg:w-80 space-y-6">
            <div class="bg-white rounded-[2rem] p-8 shadow-xl shadow-slate-100/50 border border-slate-100">
                <h3 class="text-sm font-black text-slate-800 uppercase tracking-[0.2em] mb-6">PDCA Guide</h3>
                <div class="space-y-6">
                    <div class="flex gap-4">
                        <div class="w-8 h-8 rounded-lg bg-blue-50 text-blue-500 flex items-center justify-center font-black italic">P</div>
                        <div>
                            <p class="text-xs font-black text-slate-800">Plan</p>
                            <p class="text-[10px] text-slate-400 mt-0.5">วิเคราะห์ปัญหาและวางแผน</p>
                        </div>
                    </div>
                    <div class="flex gap-4">
                        <div class="w-8 h-8 rounded-lg bg-emerald-50 text-emerald-500 flex items-center justify-center font-black italic">D</div>
                        <div>
                            <p class="text-xs font-black text-slate-800">Do</p>
                            <p class="text-[10px] text-slate-400 mt-0.5">ปฏิบัติและสังเกตการเรียนรู้</p>
                        </div>
                    </div>
                    <div class="flex gap-4">
                        <div class="w-8 h-8 rounded-lg bg-amber-50 text-amber-500 flex items-center justify-center font-black italic">C</div>
                        <div>
                            <p class="text-xs font-black text-slate-800">Check</p>
                            <p class="text-[10px] text-slate-400 mt-0.5">สะท้อนผลและตรวจสอบ</p>
                        </div>
                    </div>
                    <div class="flex gap-4">
                        <div class="w-8 h-8 rounded-lg bg-rose-50 text-rose-500 flex items-center justify-center font-black italic">A</div>
                        <div>
                            <p class="text-xs font-black text-slate-800">Act</p>
                            <p class="text-[10px] text-slate-400 mt-0.5">สรุปและรายงานผล</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Create Group Modal -->
<div id="createModal" class="fixed inset-0 z-50 flex items-center justify-center hidden p-4 sm:p-6">
    <div class="absolute inset-0 bg-slate-900/40 backdrop-blur-md" onclick="closeCreateModal()"></div>
    <div class="bg-white/90 backdrop-blur-xl w-full max-w-xl rounded-[2.5rem] shadow-2xl relative z-10 overflow-hidden animate-in zoom-in-95 duration-300">
        <div class="p-8 sm:p-10">
            <div class="flex items-center justify-between mb-8">
                <div>
                    <h3 class="text-2xl font-black text-slate-800 italic">สร้างกลุ่ม PLC ใหม่</h3>
                    <p class="text-slate-400 text-xs font-black uppercase tracking-[0.2em] mt-1">New Collaboration Group</p>
                </div>
                <button onclick="closeCreateModal()" class="w-10 h-10 rounded-full bg-slate-50 flex items-center justify-center text-slate-400 hover:text-rose-500 hover:bg-rose-50 transition-all">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>

            <form id="createGroupForm" class="space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="md:col-span-2">
                        <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest pl-2 mb-2 block">ชื่อกลุ่ม PLC</label>
                        <input type="text" name="group_name" required placeholder="เช่น ชุมชนคณิตศาสตร์คิดสร้างสรรค์" class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-6 py-4 text-sm focus:ring-4 focus:ring-violet-500/20 focus:border-violet-500 outline-none transition-all font-bold">
                    </div>
                    <div>
                        <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest pl-2 mb-2 block">ปีการศึกษา</label>
                        <input type="text" name="academic_year" required placeholder="2567" class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-6 py-4 text-sm focus:ring-4 focus:ring-violet-500/20 focus:border-violet-500 outline-none transition-all font-bold">
                    </div>
                    <div>
                        <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest pl-2 mb-2 block">ภาคเรียน</label>
                        <select name="semester" required class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-6 py-4 text-sm focus:ring-4 focus:ring-violet-500/20 focus:border-violet-500 outline-none transition-all font-bold">
                            <option value="1">ภาคเรียนที่ 1</option>
                            <option value="2">ภาคเรียนที่ 2</option>
                        </select>
                    </div>
                    <div class="md:col-span-2">
                        <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest pl-2 mb-2 block">กลุ่มเป้าหมาย / รายวิชา</label>
                        <input type="text" name="target_group" placeholder="เช่น นักเรียนชั้นมัธยมศึกษาปีที่ 1" class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-6 py-4 text-sm focus:ring-4 focus:ring-violet-500/20 focus:border-violet-500 outline-none transition-all font-bold">
                    </div>
                </div>

                <div class="pt-4">
                    <button type="submit" class="w-full bg-gradient-to-r from-violet-600 to-purple-600 text-white rounded-2xl py-5 font-black text-sm shadow-xl shadow-violet-200 hover:shadow-2xl hover:scale-[1.01] active:scale-[0.99] transition-all flex items-center justify-center gap-3">
                        ตกลงสร้างกลุ่ม <i class="bi bi-arrow-right"></i>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function openCreateModal() {
        document.getElementById('createModal').classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }

    function closeCreateModal() {
        document.getElementById('createModal').classList.add('hidden');
        document.body.style.overflow = '';
    }

    document.getElementById('createGroupForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        const formData = new FormData(e.target);
        const data = Object.fromEntries(formData.entries());
        
        try {
            const response = await fetch('api/plc_handler.php', {
                method: 'POST',
                body: JSON.stringify({
                    action: 'create_group',
                    ...data
                }),
                headers: { 'Content-Type': 'application/json' }
            });
            
            const result = await response.json();
            if (result.status === 'success') {
                Swal.fire({
                    icon: 'success',
                    title: 'สร้างกลุ่มสำเร็จ',
                    confirmButtonColor: '#7c3aed'
                }).then(() => location.reload());
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
