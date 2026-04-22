<?php
session_start();
require_once __DIR__ . '/../config.php';

// Auth guard
if (!isset($_SESSION['llw_role'])) {
    header('Location: /login.php');
    exit();
}

$pageTitle = 'ระบบโครงการ & งบประมาณ';
$pageSubtitle = 'ภาพรวมการดำเนินโครงการและสถานะการคลัง';
$activeSystem = 'budget';

// Data fetching
$totalBudget = 0;
$totalUsed = 0;
$remaining = 0;
$usedPercent = 0;
$totalActivities = 0;

try {
    $pdo = getPdo();
    
    // Get all fiscal years
    $stmt = $pdo->query("SELECT * FROM sbms_fiscal_years ORDER BY year_name DESC");
    $fiscalYears = $stmt->fetchAll();
    
    // Set active year
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

    // Fetch Stats
    $stmt = $pdo->prepare("SELECT SUM(total_amount) as total, SUM(used_amount) as used FROM sbms_budgets WHERE fiscal_year_id = ?");
    $stmt->execute([$activeYearId]);
    $res = $stmt->fetch();
    
    $totalBudget = (float)($res['total'] ?? 0);
    $totalUsed   = (float)($res['used'] ?? 0);
    $remaining   = $totalBudget - $totalUsed;
    $usedPercent = $totalBudget > 0 ? round(($totalUsed / $totalBudget) * 100, 1) : 0;
    
    // Count Activities
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM sbms_activities a JOIN sbms_projects p ON a.project_id = p.id WHERE p.fiscal_year_id = ?");
    $stmt->execute([$activeYearId]);
    $totalActivities = $stmt->fetchColumn();

    // Get Project List for table
    $stmt = $pdo->prepare("
        SELECT a.*, p.project_name, p.project_code
        FROM sbms_activities a
        JOIN sbms_projects p ON a.project_id = p.id
        WHERE p.fiscal_year_id = ?
        ORDER BY a.created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$activeYearId]);
    $recentActivities = $stmt->fetchAll();
    
} catch (Exception $e) {
    error_log($e->getMessage());
    $recentActivities = [];
}

require_once __DIR__ . '/../components/layout_start.php';
?>

<div class="space-y-6">
    <!-- Header Card -->
    <div class="bg-gradient-to-br from-emerald-600 to-teal-700 p-8 rounded-[2.5rem] shadow-2xl shadow-emerald-200/50 flex flex-col md:flex-row justify-between items-center gap-6 relative overflow-hidden">
        <div class="absolute -right-10 -top-10 w-48 h-48 bg-white/10 rounded-full blur-3xl"></div>
        <div class="relative z-10 text-center md:text-left">
            <h3 class="text-2xl font-black text-white tracking-tight">ระบบขออนุญาตดำเนินงานและสรุปโครงการ</h3>
            <div class="flex items-center justify-center md:justify-start gap-2 mt-2">
                <span class="text-[10px] text-emerald-100 font-black uppercase tracking-[0.2em]">ปีงบประมาณ:</span>
                <select onchange="window.location.href='?year_id='+this.value" class="bg-white/20 text-white border-none text-xs font-black rounded-xl px-3 py-1 outline-none cursor-pointer hover:bg-white/30 transition-all">
                    <?php foreach ($fiscalYears as $fy): ?>
                    <option value="<?= $fy['id'] ?>" <?= $activeYearId == $fy['id'] ? 'selected' : '' ?> class="text-emerald-900">พ.ศ. <?= $fy['year_name'] ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="flex gap-4 relative z-10">
            <a href="request_form.php" class="bg-white hover:bg-emerald-50 text-emerald-700 px-8 py-4 rounded-2xl font-black shadow-xl transition-all hover:scale-[1.05] flex items-center gap-3">
                <i class="bi bi-plus-circle-fill"></i> ขออนุญาตดำเนินงาน
            </a>
        </div>
    </div>

    <!-- Stats Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <!-- Received Budget -->
        <div class="bg-white rounded-[2.5rem] p-8 border border-slate-100 shadow-xl shadow-slate-200/40 relative overflow-hidden group">
            <div class="absolute left-0 top-0 w-2 h-full bg-emerald-500"></div>
            <div class="flex justify-between items-start">
                <div>
                    <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">งบประมาณที่ได้รับ</p>
                    <h3 class="text-2xl font-black text-slate-800 mt-2"><?= number_format($totalBudget) ?> บาท</h3>
                </div>
                <div class="w-12 h-12 bg-emerald-50 text-emerald-500 rounded-2xl flex items-center justify-center">
                    <i class="bi bi-wallet2 text-xl"></i>
                </div>
            </div>
        </div>

        <!-- Spent Budget -->
        <div class="bg-white rounded-[2.5rem] p-8 border border-slate-100 shadow-xl shadow-slate-200/40 relative overflow-hidden group">
            <div class="absolute left-0 top-0 w-2 h-full bg-amber-500"></div>
            <div class="flex justify-between items-start">
                <div>
                    <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">ใช้จ่ายงบประมาณไปแล้ว</p>
                    <h3 class="text-2xl font-black text-slate-800 mt-2"><?= number_format($totalUsed) ?> บาท</h3>
                </div>
                <div class="w-12 h-12 bg-amber-50 text-amber-500 rounded-2xl flex items-center justify-center">
                    <i class="bi bi-currency-dollar text-xl"></i>
                </div>
            </div>
        </div>

        <!-- Percentage -->
        <div class="bg-white rounded-[2.5rem] p-8 border border-slate-100 shadow-xl shadow-slate-200/40 relative overflow-hidden group">
            <div class="absolute left-0 top-0 w-2 h-full bg-teal-500"></div>
            <div class="flex justify-between items-start">
                <div class="w-full">
                    <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">ใช้จ่ายไปแล้วร้อยละ</p>
                    <h3 class="text-2xl font-black text-slate-800 mt-2"><?= $usedPercent ?>%</h3>
                    <div class="w-full h-2 bg-slate-100 rounded-full mt-4 overflow-hidden">
                        <div class="h-full bg-teal-500 transition-all duration-1000" style="width: <?= $usedPercent ?>%"></div>
                    </div>
                </div>
                <div class="w-12 h-12 bg-teal-50 text-teal-500 rounded-2xl flex items-center justify-center ml-4">
                    <i class="bi bi-clipboard-check text-xl"></i>
                </div>
            </div>
        </div>

        <!-- Activities Count -->
        <div class="bg-white rounded-[2.5rem] p-8 border border-slate-100 shadow-xl shadow-slate-200/40 relative overflow-hidden group">
            <div class="absolute left-0 top-0 w-2 h-full bg-indigo-500"></div>
            <div class="flex justify-between items-start">
                <div>
                    <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">จำนวนกิจกรรมทั้งหมด</p>
                    <h3 class="text-2xl font-black text-slate-800 mt-2"><?= $totalActivities ?> กิจกรรม</h3>
                </div>
                <div class="w-12 h-12 bg-indigo-50 text-indigo-500 rounded-2xl flex items-center justify-center">
                    <i class="bi bi-chat-dots text-xl"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Activities Table -->
        <div class="lg:col-span-2 bg-white rounded-[2.5rem] border border-slate-100 shadow-xl overflow-hidden">
            <div class="p-8 border-b border-slate-50 flex justify-between items-center">
                <h3 class="text-lg font-black text-slate-800">รายงานโครงการ</h3>
                <div class="flex gap-2">
                    <button class="px-4 py-2 bg-slate-100 hover:bg-slate-200 rounded-xl text-[10px] font-black text-slate-600 transition-all">Copy</button>
                    <button class="px-4 py-2 bg-slate-100 hover:bg-slate-200 rounded-xl text-[10px] font-black text-slate-600 transition-all">Excel</button>
                    <button class="px-4 py-2 bg-slate-100 hover:bg-slate-200 rounded-xl text-[10px] font-black text-slate-600 transition-all">Print</button>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-8 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">ลำดับ</th>
                            <th class="px-8 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">กิจกรรม</th>
                            <th class="px-8 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">ผู้รับผิดชอบ</th>
                            <th class="px-8 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest text-center">สรุปกิจกรรม</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        <?php if (empty($recentActivities)): ?>
                        <tr>
                            <td colspan="4" class="px-8 py-12 text-center text-slate-400 font-bold">ไม่พบข้อมูลกิจกรรม</td>
                        </tr>
                        <?php endif; ?>
                        <?php foreach ($recentActivities as $idx => $act): ?>
                        <tr class="hover:bg-slate-50 transition-all group">
                            <td class="px-8 py-4">
                                <span class="w-8 h-8 rounded-full bg-emerald-50 text-emerald-600 flex items-center justify-center text-xs font-black"><?= $idx + 1 ?></span>
                            </td>
                            <td class="px-8 py-4">
                                <p class="text-sm font-bold text-slate-700"><?= htmlspecialchars($act['activity_name']) ?></p>
                                <p class="text-[10px] text-slate-400 mt-1"><?= htmlspecialchars($act['project_name']) ?></p>
                            </td>
                            <td class="px-8 py-4 text-sm text-slate-500"><?= htmlspecialchars($act['responsible_name'] ?: '-') ?></td>
                            <td class="px-8 py-4 text-center">
                                <a href="summary_form.php?id=<?= $act['id'] ?>" class="text-rose-500 hover:text-rose-700 transition-all text-xl">
                                    <i class="bi bi-printer"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="p-6 bg-slate-50 border-t border-slate-100 flex justify-between items-center text-[10px] font-bold text-slate-400">
                <span>Showing <?= count($recentActivities) ?> of <?= $totalActivities ?> entries</span>
                <div class="flex gap-2">
                    <button class="px-3 py-1 bg-white border border-slate-200 rounded-lg">Previous</button>
                    <button class="px-3 py-1 bg-emerald-500 text-white rounded-lg">1</button>
                    <button class="px-3 py-1 bg-white border border-slate-200 rounded-lg">Next</button>
                </div>
            </div>
        </div>

        <!-- Budget Chart -->
        <div class="bg-white rounded-[2.5rem] p-8 border border-slate-100 shadow-xl flex flex-col items-center">
            <h3 class="text-lg font-black text-slate-800 w-full mb-8">ใช้จ่ายงบประมาณไปแล้ว</h3>
            <div class="relative w-full max-w-[250px] aspect-square">
                <canvas id="budgetChart"></canvas>
                <div class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none">
                    <p class="text-2xl font-black text-slate-800"><?= number_format($totalUsed) ?></p>
                    <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mt-1">จาก <?= number_format($totalBudget) ?></p>
                </div>
            </div>
            <div class="mt-12 w-full space-y-4">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <span class="w-3 h-3 rounded-full bg-rose-200"></span>
                        <span class="text-xs font-bold text-slate-500">งบที่ได้รับ</span>
                    </div>
                    <span class="text-xs font-black text-slate-700"><?= number_format($totalBudget) ?></span>
                </div>
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <span class="w-3 h-3 rounded-full bg-orange-200"></span>
                        <span class="text-xs font-bold text-slate-500">งบที่ใช้แล้ว</span>
                    </div>
                    <span class="text-xs font-black text-slate-700"><?= number_format($totalUsed) ?></span>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    const ctx = document.getElementById('budgetChart').getContext('2d');
    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['ใช้แล้ว', 'คงเหลือ'],
            datasets: [{
                data: [<?= $totalUsed ?>, <?= $remaining ?>],
                backgroundColor: ['#fed7aa', '#fecdd3'],
                borderWidth: 0,
                cutout: '80%'
            }]
        },
        options: {
            plugins: { legend: { display: false } },
            maintainAspectRatio: true
        }
    });
</script>

<?php require_once __DIR__ . '/../components/layout_end.php'; ?>
