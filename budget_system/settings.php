<?php
session_start();
require_once __DIR__ . '/../config.php';

// Auth guard - Super Admin only
if (!isset($_SESSION['llw_role']) || $_SESSION['llw_role'] !== 'super_admin') {
    header('Location: /login.php');
    exit();
}

$pageTitle = 'ตั้งค่าระบบงบประมาณ';
$pageSubtitle = 'จัดการปีงบประมาณและหมวดหมู่เงินทุน';
$activeSystem = 'budget';

try {
    $pdo = getPdo();
    
    // Fetch Fiscal Years
    $stmt = $pdo->query("SELECT * FROM sbms_fiscal_years ORDER BY year_name DESC");
    $years = $stmt->fetchAll();
    
    // Fetch Budget Categories (Plans) for the active year
    $activeYearId = $_GET['year_id'] ?? null;
    if (!$activeYearId) {
        $stmt = $pdo->query("SELECT id FROM sbms_fiscal_years WHERE is_active = 1 LIMIT 1");
        $activeYearId = $stmt->fetchColumn();
    }
    
    $stmt = $pdo->prepare("SELECT * FROM sbms_budgets WHERE fiscal_year_id = ?");
    $stmt->execute([$activeYearId]);
    $budgets = $stmt->fetchAll();
    
} catch (Exception $e) {
    error_log($e->getMessage());
    $years = [];
    $budgets = [];
}

require_once __DIR__ . '/../components/layout_start.php';
?>

<style>
    .bg-navy { background-color: #0B1C3E; }
    .text-gold { color: #F59E0B; }
    .bg-gold { background-color: #F59E0B; }
</style>

<div class="space-y-6">
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        
        <!-- Fiscal Year Management -->
        <div class="lg:col-span-1 space-y-6">
            <div class="bg-white rounded-[2.5rem] shadow-xl border border-slate-100 overflow-hidden">
                <div class="bg-navy p-6 text-white flex justify-between items-center">
                    <div>
                        <h3 class="text-lg font-black text-gold">ปีงบประมาณ</h3>
                        <p class="text-[9px] font-bold text-white/50 uppercase tracking-widest">Fiscal Year Management</p>
                    </div>
                    <button onclick="openModal('addYearModal')" class="w-10 h-10 rounded-xl bg-gold text-navy flex items-center justify-center hover:scale-110 transition-all shadow-lg shadow-gold/20">
                        <i class="bi bi-plus-lg"></i>
                    </button>
                </div>
                <div class="p-6">
                    <div class="space-y-3">
                        <?php foreach ($years as $y): ?>
                        <div class="flex items-center justify-between p-4 rounded-2xl border <?= $y['is_active'] ? 'border-gold bg-amber-50/50' : 'border-slate-100 hover:bg-slate-50' ?> transition-all group">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-xl <?= $y['is_active'] ? 'bg-navy text-gold' : 'bg-slate-100 text-slate-400' ?> flex items-center justify-center font-black text-sm">
                                    <?= substr($y['year_name'], -2) ?>
                                </div>
                                <div>
                                    <p class="text-sm font-black text-navy">พ.ศ. <?= $y['year_name'] ?></p>
                                    <p class="text-[9px] text-slate-400 font-bold uppercase tracking-wider"><?= date('d M Y', strtotime($y['start_date'])) ?> - <?= date('d M Y', strtotime($y['end_date'])) ?></p>
                                </div>
                            </div>
                            <?php if ($y['is_active']): ?>
                                <span class="px-2 py-1 bg-navy text-gold text-[8px] font-black uppercase tracking-widest rounded-lg shadow-md">Active</span>
                            <?php else: ?>
                                <a href="api/set_active_year.php?id=<?= $y['id'] ?>" class="opacity-0 group-hover:opacity-100 px-3 py-1.5 bg-slate-100 hover:bg-navy hover:text-white rounded-xl text-[9px] font-black uppercase tracking-widest transition-all">Set Active</a>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Budget Plan Management -->
        <div class="lg:col-span-2 space-y-6">
            <div class="bg-white rounded-[2.5rem] shadow-xl border border-slate-100 overflow-hidden">
                <div class="bg-navy p-6 text-white flex justify-between items-center">
                    <div>
                        <h3 class="text-lg font-black text-gold">หมวดหมู่เงินงบประมาณ</h3>
                        <p class="text-[9px] font-bold text-white/50 uppercase tracking-widest">Budget Plans for Year <?= $_GET['year_name'] ?? 'Current' ?></p>
                    </div>
                    <button onclick="openModal('addBudgetModal')" class="bg-gold text-navy px-5 py-2.5 rounded-xl font-black text-xs flex items-center gap-2 hover:scale-105 transition-all shadow-lg shadow-gold/20">
                        <i class="bi bi-plus-circle-fill"></i> เพิ่มหมวดงบ
                    </button>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-100">
                                <th class="px-8 py-5 text-[10px] font-black text-slate-400 uppercase tracking-widest">หมวดเงิน</th>
                                <th class="px-8 py-5 text-[10px] font-black text-slate-400 uppercase tracking-widest text-right">งบประมาณรวม</th>
                                <th class="px-8 py-5 text-[10px] font-black text-slate-400 uppercase tracking-widest text-right">เบิกจ่ายไปแล้ว</th>
                                <th class="px-8 py-5 text-[10px] font-black text-slate-400 uppercase tracking-widest text-right">คงเหลือ</th>
                                <th class="px-8 py-5 text-[10px] font-black text-slate-400 uppercase tracking-widest text-right">จัดการ</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-50">
                            <?php foreach ($budgets as $b): 
                                $rem = $b['total_amount'] - $b['used_amount'];
                            ?>
                            <tr class="hover:bg-slate-50 transition-colors">
                                <td class="px-8 py-5">
                                    <p class="text-xs font-black text-navy"><?= htmlspecialchars($b['plan_name']) ?></p>
                                    <p class="text-[9px] text-slate-400 font-bold uppercase tracking-widest"><?= $b['budget_type'] ?></p>
                                </td>
                                <td class="px-8 py-5 text-right font-bold text-navy text-sm">฿<?= number_format($b['total_amount'], 2) ?></td>
                                <td class="px-8 py-5 text-right font-bold text-rose-500 text-sm">฿<?= number_format($b['used_amount'], 2) ?></td>
                                <td class="px-8 py-5 text-right font-black text-emerald-600 text-sm">฿<?= number_format($rem, 2) ?></td>
                                <td class="px-8 py-5 text-right">
                                    <button class="w-8 h-8 rounded-lg bg-slate-50 text-slate-300 hover:bg-gold hover:text-navy transition-all"><i class="bi bi-pencil-fill"></i></button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- Modal: Add Year -->
<div id="addYearModal" class="fixed inset-0 z-[60] hidden flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" onclick="closeModal('addYearModal')"></div>
    <div class="bg-white rounded-[2.5rem] w-full max-w-md relative z-10 shadow-2xl overflow-hidden animate-in fade-in zoom-in duration-300">
        <div class="p-8 border-b border-slate-100 flex justify-between items-center bg-navy text-white">
            <h3 class="text-xl font-black text-gold">เพิ่มปีงบประมาณใหม่</h3>
            <button onclick="closeModal('addYearModal')" class="text-white/50 hover:text-white"><i class="bi bi-x-lg"></i></button>
        </div>
        <form action="api/save_settings.php?action=add_year" method="POST" class="p-8 space-y-6">
            <div>
                <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 block">พ.ศ. ปีงบประมาณ</label>
                <input type="text" name="year_name" placeholder="เช่น 2568" required class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-4 py-3 text-sm focus:ring-2 focus:ring-gold outline-none">
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 block">วันที่เริ่มต้น</label>
                    <input type="date" name="start_date" required class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-4 py-3 text-sm focus:ring-2 focus:ring-gold outline-none">
                </div>
                <div>
                    <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 block">วันที่สิ้นสุด</label>
                    <input type="date" name="end_date" required class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-4 py-3 text-sm focus:ring-2 focus:ring-gold outline-none">
                </div>
            </div>
            <div class="pt-4">
                <button type="submit" class="w-full bg-navy text-gold py-4 rounded-2xl font-black shadow-xl shadow-navy/20 transition-all">
                    บันทึกข้อมูล
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    function openModal(id) { document.getElementById(id).classList.remove('hidden'); document.getElementById(id).classList.add('flex'); }
    function closeModal(id) { document.getElementById(id).classList.add('hidden'); document.getElementById(id).classList.remove('flex'); }

    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('success')) {
        Swal.fire({ icon: 'success', title: 'สำเร็จ!', text: 'บันทึกการตั้งค่าเรียบร้อยแล้ว', timer: 2000, showConfirmButton: false });
    }
</script>

<?php require_once __DIR__ . '/../components/layout_end.php'; ?>
