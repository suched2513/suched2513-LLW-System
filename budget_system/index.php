<?php
session_start();
require_once __DIR__ . '/../config.php';

// Auth guard
if (!isset($_SESSION['llw_role'])) {
    header('Location: /login.php');
    exit();
}

$pageTitle = 'Dashboard งบประมาณ';
$pageSubtitle = 'ภาพรวมสถานะการคลังและงบประมาณโรงเรียน';
$activeSystem = 'budget';

// Data fetching (Placeholder for now, will link to DB later)
$totalBudget = 0;
$totalUsed = 0;
$remaining = 0;
$usedPercent = 0;

try {
    $pdo = getPdo();
    
    // Get all fiscal years
    $stmt = $pdo->query("SELECT * FROM sbms_fiscal_years ORDER BY year_name DESC");
    $fiscalYears = $stmt->fetchAll();
    
    // Set active year from URL or find the one marked as is_active = 1
    $activeYearId = $_GET['year_id'] ?? null;
    if (!$activeYearId) {
        $stmt = $pdo->query("SELECT id FROM sbms_fiscal_years WHERE is_active = 1 LIMIT 1");
        $activeYearId = $stmt->fetchColumn();
        
        if (!$activeYearId && !empty($fiscalYears)) {
            $activeYearId = $fiscalYears[0]['id'];
        }
    }
    
    // Fetch specific year details
    $stmt = $pdo->prepare("SELECT * FROM sbms_fiscal_years WHERE id = ?");
    $stmt->execute([$activeYearId]);
    $activeYear = $stmt->fetch();

    // Fetch Stats for the active year
    $stmt = $pdo->prepare("SELECT SUM(total_amount) as total, SUM(used_amount) as used FROM sbms_budgets WHERE fiscal_year_id = ?");
    $stmt->execute([$activeYearId]);
    $res = $stmt->fetch();
    
    $totalBudget = (float)($res['total'] ?? 0);
    $totalUsed   = (float)($res['used'] ?? 0);
    $remaining   = $totalBudget - $totalUsed;
    $usedPercent = $totalBudget > 0 ? round(($totalUsed / $totalBudget) * 100, 1) : 0;
    
    // Get real budget sources
    $stmt = $pdo->prepare("SELECT * FROM sbms_budgets WHERE fiscal_year_id = ?");
    $stmt->execute([$activeYearId]);
    $budgetSources = $stmt->fetchAll();
    
} catch (Exception $e) {
    error_log($e->getMessage());
    $totalBudget = 0; $totalUsed = 0; $remaining = 0; $usedPercent = 0;
    $budgetSources = [];
    $fiscalYears = [];
}

require_once __DIR__ . '/../components/layout_start.php';
?>

<style>
    .bg-navy { background-color: #0B1C3E; }
    .text-gold { color: #F59E0B; }
    .bg-gold { background-color: #F59E0B; }
    .border-gold { border-color: #F59E0B; }
    .shadow-gold { --tw-shadow-color: rgba(245, 158, 11, 0.2); --tw-shadow: 0 10px 15px -3px var(--tw-shadow-color), 0 4px 6px -4px var(--tw-shadow-color); }
</style>

<div class="space-y-6">
    <!-- Action Header with Year Selector -->
    <div class="bg-navy p-6 sm:p-8 rounded-3xl sm:rounded-[2.5rem] shadow-2xl flex flex-col md:flex-row justify-between items-center gap-6 relative overflow-hidden">
        <div class="absolute -right-10 -top-10 w-48 h-48 bg-gold/10 rounded-full blur-3xl"></div>
        <div class="relative z-10 w-full md:w-auto text-center md:text-left">
            <h3 class="text-xl font-black text-gold tracking-tight">สรุปภาพรวมงบประมาณ</h3>
            <div class="flex items-center justify-center md:justify-start gap-2 mt-1">
                <span class="text-[10px] text-white/60 font-black uppercase tracking-[0.2em]">ปีงบประมาณ:</span>
                <select onchange="window.location.href='?year_id='+this.value" class="bg-white/10 text-gold border-none text-[10px] font-black rounded-lg px-2 py-1 outline-none cursor-pointer hover:bg-white/20 transition-all">
                    <?php foreach ($fiscalYears as $fy): ?>
                    <option value="<?= $fy['id'] ?>" <?= $activeYearId == $fy['id'] ? 'selected' : '' ?> class="text-navy">พ.ศ. <?= $fy['year_name'] ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="flex gap-4 relative z-10 w-full md:w-auto justify-center">
            <a href="projects.php" class="flex-1 md:flex-none bg-gold hover:bg-amber-600 text-navy px-8 py-3.5 rounded-2xl font-black shadow-xl shadow-gold/20 flex items-center justify-center gap-3 transition-all hover:scale-[1.05]">
                <i class="bi bi-folder-plus"></i> แผนโครงการ
            </a>
        </div>
    </div>

    <!-- Top Stats -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <!-- Total Budget Card -->
        <div class="bg-navy rounded-3xl sm:rounded-[2.5rem] p-6 sm:p-8 text-white shadow-2xl relative overflow-hidden group">
            <div class="absolute -right-8 -top-8 w-40 h-40 bg-gold/10 rounded-full blur-3xl group-hover:scale-150 transition-transform duration-700"></div>
            <div class="relative z-10">
                <div class="w-12 h-12 sm:w-14 sm:h-14 bg-gold rounded-2xl flex items-center justify-center mb-6 shadow-lg shadow-gold/40">
                    <i class="bi bi-bank2 text-xl sm:text-2xl text-navy"></i>
                </div>
                <p class="text-[10px] font-black uppercase tracking-[0.2em] text-gold/80">งบประมาณรวมทั้งปี</p>
                <h3 class="text-2xl sm:text-3xl font-black mt-2 tracking-tight">฿<?= number_format($totalBudget, 2) ?></h3>
                <div class="mt-6 flex items-center gap-2">
                    <span class="px-2.5 py-1 bg-white/10 rounded-full text-[10px] font-bold text-gold">ปีงบประมาณ <?= $activeYear['year_name'] ?? '----' ?></span>
                </div>
            </div>
        </div>

        <!-- Used Budget -->
        <div class="bg-white rounded-3xl sm:rounded-[2.5rem] p-6 sm:p-8 border border-slate-100 shadow-xl shadow-slate-200/40 relative overflow-hidden group">
            <div class="relative z-10">
                <div class="w-12 h-12 sm:w-14 sm:h-14 bg-slate-50 text-navy rounded-2xl flex items-center justify-center mb-6 group-hover:bg-navy group-hover:text-gold transition-all duration-500">
                    <i class="bi bi-graph-up-arrow text-xl sm:text-2xl"></i>
                </div>
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-[0.2em]">เบิกจ่ายสะสม</p>
                <h3 class="text-2xl sm:text-3xl font-black text-navy mt-2">฿<?= number_format($totalUsed, 2) ?></h3>
                <div class="mt-6">
                    <div class="flex justify-between items-center mb-2">
                        <span class="text-[10px] font-bold text-slate-500 italic">ดำเนินการแล้ว <?= $usedPercent ?>%</span>
                    </div>
                    <div class="w-full h-1.5 sm:h-2 bg-slate-100 rounded-full overflow-hidden">
                        <div class="h-full bg-navy transition-all duration-1000" style="width: <?= $usedPercent ?>%"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Projects Count -->
        <div class="bg-white rounded-3xl sm:rounded-[2.5rem] p-6 sm:p-8 border border-slate-100 shadow-xl shadow-slate-200/40 relative overflow-hidden group">
            <div class="relative z-10">
                <div class="w-12 h-12 sm:w-14 sm:h-14 bg-amber-50 text-gold rounded-2xl flex items-center justify-center mb-6 group-hover:scale-110 transition-transform">
                    <i class="bi bi-journal-check text-xl sm:text-2xl"></i>
                </div>
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-[0.2em]">โครงการทั้งหมด</p>
                <h3 class="text-2xl sm:text-3xl font-black text-navy mt-2">12 <span class="text-base font-bold text-slate-300 ml-1">โครงการ</span></h3>
                <div class="mt-6 flex items-center gap-2">
                    <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                    <span class="text-[10px] font-bold text-emerald-600 uppercase tracking-wider">กำลังดำเนินการ 8</span>
                </div>
            </div>
        </div>

        <!-- Quick Access Vertical -->
        <div class="grid grid-cols-2 md:grid-cols-1 md:grid-rows-2 gap-4 h-full">
            <a href="projects.php" class="bg-gold hover:bg-amber-600 text-navy rounded-2xl sm:rounded-3xl p-5 flex items-center justify-between transition-all group shadow-lg shadow-gold/20">
                <div class="flex items-center gap-3 sm:gap-4">
                    <i class="bi bi-plus-circle-fill text-lg sm:text-xl group-hover:rotate-90 transition-transform"></i>
                    <span class="text-[10px] sm:text-xs font-black uppercase tracking-wider">ใหม่</span>
                </div>
                <i class="bi bi-arrow-right"></i>
            </a>
            <a href="disbursements.php" class="bg-navy hover:bg-slate-800 text-white rounded-2xl sm:rounded-3xl p-5 flex items-center justify-between transition-all group shadow-xl">
                <div class="flex items-center gap-3 sm:gap-4">
                    <i class="bi bi-receipt text-lg sm:text-xl group-hover:scale-110 transition-transform"></i>
                    <span class="text-[10px] sm:text-xs font-black uppercase tracking-wider">เบิกจ่าย</span>
                </div>
                <i class="bi bi-arrow-right text-gold"></i>
            </a>
        </div>
    </div>

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <a href="index.php" class="bg-white/70 backdrop-blur-md p-5 rounded-2xl sm:rounded-3xl border border-slate-100 flex items-center gap-3 sm:gap-4 group hover:shadow-lg transition-all cursor-pointer">
            <div class="w-10 h-10 sm:w-12 sm:h-12 bg-navy text-gold rounded-xl sm:rounded-2xl flex items-center justify-center text-lg sm:text-xl group-hover:rotate-12 transition-transform">📊</div>
            <div class="min-w-0">
                <p class="text-xs font-black text-navy truncate">Dashboard</p>
                <p class="text-[9px] text-slate-400 font-bold uppercase tracking-widest truncate">ข้อมูลเรียลไทม์</p>
            </div>
        </a>
        <a href="procurement.php" class="bg-white/70 backdrop-blur-md p-5 rounded-2xl sm:rounded-3xl border border-slate-100 flex items-center gap-3 sm:gap-4 group hover:shadow-lg transition-all cursor-pointer">
            <div class="w-10 h-10 sm:w-12 sm:h-12 bg-navy text-gold rounded-xl sm:rounded-2xl flex items-center justify-center text-lg sm:text-xl group-hover:rotate-12 transition-transform">🏗️</div>
            <div class="min-w-0">
                <p class="text-xs font-black text-navy truncate">Procurement</p>
                <p class="text-[9px] text-slate-400 font-bold uppercase tracking-widest truncate">จัดซื้อจัดจ้าง</p>
            </div>
        </a>
        <a href="cashbook.php" class="bg-white/70 backdrop-blur-md p-5 rounded-2xl sm:rounded-3xl border border-slate-100 flex items-center gap-3 sm:gap-4 group hover:shadow-lg transition-all cursor-pointer">
            <div class="w-10 h-10 sm:w-12 sm:h-12 bg-navy text-gold rounded-xl sm:rounded-2xl flex items-center justify-center text-lg sm:text-xl group-hover:rotate-12 transition-transform">📖</div>
            <div class="min-w-0">
                <p class="text-xs font-black text-navy truncate">Cashbook</p>
                <p class="text-[9px] text-slate-400 font-bold uppercase tracking-widest truncate">ทะเบียนเงิน</p>
            </div>
        </a>
        <a href="reports.php" class="bg-white/70 backdrop-blur-md p-5 rounded-2xl sm:rounded-3xl border border-slate-100 flex items-center gap-3 sm:gap-4 group hover:shadow-lg transition-all cursor-pointer">
            <div class="w-10 h-10 sm:w-12 sm:h-12 bg-navy text-gold rounded-xl sm:rounded-2xl flex items-center justify-center text-lg sm:text-xl group-hover:rotate-12 transition-transform">📄</div>
            <div class="min-w-0">
                <p class="text-xs font-black text-navy truncate">Reports</p>
                <p class="text-[9px] text-slate-400 font-bold uppercase tracking-widest truncate">รายงานสรุป</p>
            </div>
        </a>
    </div>

    <!-- Charts Section -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Main Chart Area -->
        <div class="lg:col-span-2 bg-white rounded-3xl sm:rounded-[2.5rem] p-6 sm:p-8 border border-slate-100 shadow-xl">
            <div class="flex flex-col sm:flex-row justify-between items-start gap-4 mb-8">
                <div>
                    <h3 class="text-lg font-black text-navy italic">สถิติการใช้งบประมาณรายเดือน</h3>
                    <p class="text-[10px] text-slate-400 mt-1 uppercase tracking-[0.2em] font-black">Monthly Budget Utilization</p>
                </div>
                <div class="flex gap-4">
                    <span class="flex items-center gap-2 text-[10px] font-bold text-slate-400"><span class="w-2 h-2 rounded-full bg-navy"></span> งบประมาณ</span>
                    <span class="flex items-center gap-2 text-[10px] font-bold text-slate-400"><span class="w-2 h-2 rounded-full bg-gold"></span> เบิกจ่ายจริง</span>
                </div>
            </div>
            
            <div class="h-64 sm:h-80 flex items-center justify-center bg-slate-50/50 rounded-3xl border border-dashed border-slate-200">
                <div class="text-center p-4">
                    <i class="bi bi-graph-up text-4xl sm:text-5xl text-slate-200"></i>
                    <p class="text-[10px] sm:text-xs text-slate-400 mt-4 font-black uppercase tracking-widest">Chart Visualization Rendering...</p>
                </div>
            </div>
        </div>

        <!-- Budget Source Breakdown -->
        <div class="bg-white rounded-3xl sm:rounded-[2.5rem] p-6 sm:p-8 border border-slate-100 shadow-xl">
            <h3 class="text-lg font-black text-navy mb-6">สัดส่วนตามแหล่งงบ</h3>
            <div class="space-y-6">
                <?php
                $colors = ['navy', 'gold', 'slate-400', 'indigo', 'emerald'];
                foreach ($budgetSources as $idx => $src):
                    $pct = $src['total_amount'] > 0 ? round(($src['used_amount'] / $src['total_amount']) * 100) : 0;
                    $color = $colors[$idx % count($colors)];
                ?>
                <div>
                    <div class="flex justify-between items-center mb-2">
                        <span class="text-xs font-black text-slate-700 truncate mr-2"><?= htmlspecialchars($src['plan_name']) ?></span>
                        <span class="text-[10px] font-bold text-slate-400"><?= $pct ?>%</span>
                    </div>
                    <div class="w-full h-1.5 bg-slate-100 rounded-full">
                        <div class="h-full bg-<?= $color ?> rounded-full" style="width: <?= $pct ?>%"></div>
                    </div>
                </div>
                <?php endforeach; ?>
                
                <div class="mt-8 p-6 bg-navy text-white rounded-3xl relative overflow-hidden">
                    <div class="absolute -right-4 -bottom-4 text-white/10 text-6xl italic font-black">SBMS</div>
                    <p class="text-[9px] font-black uppercase tracking-widest opacity-60 mb-1">ยอดรวมคงเหลือทั้งหมด</p>
                    <p class="text-xl sm:text-2xl font-black text-gold tracking-tight">฿<?= number_format($remaining, 2) ?></p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // Success/Error Alerts
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('success')) {
        Swal.fire({ icon: 'success', title: 'สำเร็จ!', text: 'บันทึกข้อมูลเรียบร้อยแล้ว', timer: 2000, showConfirmButton: false, confirmButtonColor: '#0B1C3E' });
    }
    if (urlParams.has('error')) {
        Swal.fire({ icon: 'error', title: 'เกิดข้อผิดพลาด', text: urlParams.get('error'), confirmButtonColor: '#0B1C3E' });
    }
</script>

<?php require_once __DIR__ . '/../components/layout_end.php'; ?>
