<?php
session_start();
require_once __DIR__ . '/../config.php';

// Auth guard
if (!isset($_SESSION['llw_role'])) {
    header('Location: /login.php');
    exit();
}

$pageTitle = 'โครงการ & กิจกรรม';
$pageSubtitle = 'จัดการโครงการภายใต้แผนงบประมาณประจำปี';
$activeSystem = 'budget';

try {
    $pdo = getPdo();
    
    // Get Projects with Budget and Fiscal Year info
    $stmt = $pdo->query("
        SELECT p.*, b.plan_name, f.year_name 
        FROM sbms_projects p
        JOIN sbms_fiscal_years f ON p.fiscal_year_id = f.id
        LEFT JOIN sbms_budgets b ON p.budget_id = b.id
        ORDER BY p.created_at DESC
    ");
    $projects = $stmt->fetchAll();
    
    // Get Budgets for the form
    $stmt = $pdo->query("SELECT b.*, f.year_name FROM sbms_budgets b JOIN sbms_fiscal_years f ON b.fiscal_year_id = f.id WHERE b.status = 'active'");
    $budgets = $stmt->fetchAll();
    
    // Get Active Fiscal Year
    $stmt = $pdo->query("SELECT * FROM sbms_fiscal_years WHERE is_active = 1 LIMIT 1");
    $activeYear = $stmt->fetch();
    
} catch (Exception $e) {
    error_log($e-<style>
    .bg-navy { background-color: #0B1C3E; }
    .text-gold { color: #F59E0B; }
    .bg-gold { background-color: #F59E0B; }
    .border-gold { border-color: #F59E0B; }
</style>

<div class="space-y-6">
    <!-- Action Header: Navy/Gold -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 bg-navy p-6 sm:p-8 rounded-3xl sm:rounded-[2.5rem] shadow-2xl relative overflow-hidden group">
        <div class="absolute -right-10 -top-10 w-40 h-40 bg-gold/10 rounded-full blur-3xl group-hover:scale-150 transition-transform duration-700"></div>
        <div class="relative z-10">
            <h3 class="text-xl font-black text-gold tracking-tight">โครงการ & กิจกรรม</h3>
            <p class="text-[10px] text-white/60 mt-1 font-black uppercase tracking-[0.2em]">จัดการแผนงบประมาณรายโครงการ</p>
        </div>
        <button onclick="openModal('addProjectModal')" class="w-full sm:w-auto bg-gold hover:bg-amber-600 text-navy px-8 py-3.5 rounded-2xl font-black shadow-xl shadow-gold/20 flex items-center justify-center gap-3 transition-all hover:scale-[1.05] relative z-10">
            <i class="bi bi-plus-lg"></i> เพิ่มโครงการ
        </button>
    </div>

    <!-- Project List -->
    <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
        <?php if (empty($projects)): ?>
        <div class="xl:col-span-2 bg-slate-50 border-2 border-dashed border-slate-200 rounded-3xl sm:rounded-[2.5rem] p-12 text-center">
            <i class="bi bi-folder2-open text-5xl text-slate-300"></i>
            <p class="text-slate-400 mt-4 font-bold">ไม่พบข้อมูลโครงการในระบบ</p>
            <button onclick="openModal('addProjectModal')" class="text-navy text-[10px] font-black uppercase tracking-widest mt-2 hover:text-gold transition-colors">คลิกเพื่อเริ่มสร้างโครงการแรก</button>
        </div>
        <?php endif; ?>

        <?php foreach ($projects as $p): 
            $statusColors = [
                'draft'       => 'bg-slate-100 text-slate-500',
                'approved'    => 'bg-emerald-50 text-emerald-600',
                'in_progress' => 'bg-navy text-gold',
                'completed'   => 'bg-blue-50 text-blue-600',
                'rejected'    => 'bg-rose-50 text-rose-600',
            ];
            $statusColor = $statusColors[$p['status']] ?? 'bg-slate-100 text-slate-500';
            $percent = $p['approved_amount'] > 0 ? round(($p['used_amount'] / $p['approved_amount']) * 100, 1) : 0;
        ?>
        <div class="bg-white rounded-3xl sm:rounded-[2.5rem] p-6 sm:p-8 border border-slate-100 shadow-xl hover:shadow-2xl transition-all group border-l-8 <?= $p['status'] === 'approved' || $p['status'] === 'in_progress' ? 'border-l-gold' : 'border-l-slate-300' ?>">
            <div class="flex justify-between items-start mb-6">
                <div class="flex-1 min-w-0 pr-4">
                    <span class="px-3 py-1 rounded-full <?= $statusColor ?> text-[9px] font-black uppercase tracking-widest mb-3 inline-block">
                        <?= $p['status'] ?>
                    </span>
                    <h4 class="text-lg font-black text-navy truncate group-hover:text-gold transition-colors tracking-tight"><?= htmlspecialchars($p['project_name']) ?></h4>
                    <p class="text-[10px] text-slate-400 font-bold mt-1 uppercase tracking-wider">
                        Code: <?= $p['project_code'] ?: 'N/A' ?> | Fiscal Year: <?= $p['year_name'] ?>
                    </p>
                </div>
                <div class="text-right">
                    <p class="text-[9px] font-black text-slate-300 uppercase tracking-[0.2em] mb-1">งบที่ได้รับ</p>
                    <p class="text-xl font-black text-navy">฿<?= number_format($p['approved_amount'], 2) ?></p>
                </div>
            </div>

            <div class="space-y-4">
                <!-- Progress -->
                <div>
                    <div class="flex justify-between items-center mb-2">
                        <span class="text-[9px] font-black text-slate-400 uppercase tracking-widest italic">ใช้ไปแล้ว ฿<?= number_format($p['used_amount'], 2) ?></span>
                        <span class="text-[10px] font-black text-navy"><?= $percent ?>%</span>
                    </div>
                    <div class="w-full h-1.5 bg-slate-100 rounded-full overflow-hidden">
                        <div class="h-full bg-navy rounded-full transition-all duration-1000" style="width: <?= $percent ?>%"></div>
                    </div>
                </div>

                <div class="pt-5 border-t border-slate-50 flex justify-between items-center">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-slate-50 flex items-center justify-center text-slate-300 group-hover:bg-gold group-hover:text-navy transition-all">
                            <i class="bi bi-briefcase-fill"></i>
                        </div>
                        <div>
                            <p class="text-[9px] font-black text-slate-300 uppercase tracking-widest">หมวดงบประมาณ</p>
                            <p class="text-[11px] font-bold text-slate-500"><?= htmlspecialchars($p['plan_name'] ?: 'ไม่ระบุงบประมาณ') ?></p>
                        </div>
                    </div>
                    <div class="flex gap-2">
                        <button class="w-10 h-10 rounded-xl bg-slate-50 text-slate-400 hover:bg-navy hover:text-gold transition-all">
                            <i class="bi bi-eye-fill"></i>
                        </button>
                        <button class="w-10 h-10 rounded-xl bg-slate-50 text-slate-400 hover:bg-gold hover:text-navy transition-all">
                            <i class="bi bi-pencil-fill"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
          <button class="w-9 h-9 rounded-xl bg-slate-50 text-slate-400 hover:bg-amber-50 hover:text-amber-600 transition-all">
                            <i class="bi bi-pencil-fill"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Modal: Add Project (Conceptual) -->
<div id="addProjectModal" class="fixed inset-0 z-[60] hidden flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" onclick="closeModal('addProjectModal')"></div>
    <div class="bg-white rounded-[2.5rem] w-full max-w-xl relative z-10 shadow-2xl overflow-hidden animate-in fade-in zoom-in duration-300">
        <div class="p-8 border-b border-slate-100 flex justify-between items-center">
            <h3 class="text-xl font-black text-slate-800">สร้างโครงการใหม่</h3>
            <button onclick="closeModal('addProjectModal')" class="text-slate-400 hover:text-slate-600"><i class="bi bi-x-lg"></i></button>
        </div>
        <form action="api/save_project.php" method="POST" class="p-8 space-y-6">
            <div>
                <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 block">ชื่อโครงการ</label>
                <input type="text" name="project_name" required class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-4 py-3 text-sm focus:ring-2 focus:ring-amber-500 outline-none transition-all">
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 block">ปีงบประมาณ</label>
                    <select name="fiscal_year_id" class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-4 py-3 text-sm focus:ring-2 focus:ring-amber-500 outline-none">
                        <option value="<?= $activeYear['id'] ?>"><?= $activeYear['year_name'] ?></option>
                    </select>
                </div>
                <div>
                    <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 block">งบประมาณที่ใช้</label>
                    <select name="budget_id" class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-4 py-3 text-sm focus:ring-2 focus:ring-amber-500 outline-none">
                        <?php foreach ($budgets as $b): ?>
                        <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['plan_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div>
                <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 block">งบประมาณที่ขอ (฿)</label>
                <input type="number" step="0.01" name="requested_amount" required class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-4 py-3 text-sm focus:ring-2 focus:ring-amber-500 outline-none transition-all">
            </div>
            <div class="pt-4">
                <button type="submit" class="w-full bg-amber-500 hover:bg-amber-600 text-white py-4 rounded-2xl font-black shadow-xl shadow-amber-200 transition-all">
                    บันทึกโครงการ
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    function openModal(id) { document.getElementById(id).classList.remove('hidden'); document.getElementById(id).classList.add('flex'); }
    function closeModal(id) { document.getElementById(id).classList.add('hidden'); document.getElementById(id).classList.remove('flex'); }
</script>

<?php require_once __DIR__ . '/../components/layout_end.php'; ?>
