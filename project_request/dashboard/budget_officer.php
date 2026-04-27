<?php
session_start();
require_once __DIR__ . '/../config/auth.php';
checkRole(['budget_officer', 'admin']);

$pageTitle = 'Budget Officer Dashboard';
$pageSubtitle = 'ระบบควบคุมและติดตามการเบิกจ่ายงบประมาณ';
require_once __DIR__ . '/../components/layout_start.php';

$pdo = getPdo();
$fiscal_year = FISCAL_YEAR;

// 1. Fetch KPI Metrics
$stmt = $pdo->prepare("
    SELECT 
        SUM(alloc_total) as total_alloc,
        SUM(used_total) as total_used
    FROM v_budget_usage 
    WHERE fiscal_year = ?
");
$stmt->execute([$fiscal_year]);
$kpi = $stmt->fetch();

$totalAlloc = $kpi['total_alloc'] ?? 0;
$totalUsed = $kpi['total_used'] ?? 0;
$totalRemaining = $totalAlloc - $totalUsed;
$percentUsed = $totalAlloc > 0 ? ($totalUsed / $totalAlloc) * 100 : 0;

// 2. Fetch Budget by Department for Progress Bars
$stmt = $pdo->prepare("SELECT * FROM v_budget_usage WHERE fiscal_year = ? ORDER BY (used_total/alloc_total) DESC");
$stmt->execute([$fiscal_year]);
$deptBudgets = $stmt->fetchAll();

// 3. Recently Approved Requests (Waiting for disbursement)
$stmt = $pdo->query("
    SELECT r.*, p.project_name, u.full_name as teacher_name
    FROM project_requests r
    JOIN budget_projects p ON r.budget_project_id = p.id
    JOIN users u ON r.user_id = u.id
    WHERE r.status = 'approved'
    ORDER BY r.approved_at DESC LIMIT 5
");
$recentApproved = $stmt->fetchAll();
?>

<!-- KPI Row -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-10">
    <div class="bg-white rounded-[2rem] p-8 border border-slate-100 shadow-xl shadow-slate-200/50 flex flex-col items-center">
        <p class="text-[11px] font-black text-slate-400 uppercase tracking-widest mb-1">งบจัดสรรรวม</p>
        <h3 class="text-3xl font-black text-slate-800"><?= number_format($totalAlloc, 2) ?></h3>
    </div>
    
    <div class="bg-blue-600 rounded-[2rem] p-8 text-white shadow-xl shadow-blue-200/50 flex flex-col items-center">
        <p class="text-[11px] font-black text-blue-100 uppercase tracking-widest mb-1">ใช้ไปแล้ว</p>
        <h3 class="text-3xl font-black"><?= number_format($totalUsed, 2) ?></h3>
    </div>

    <div class="bg-white rounded-[2rem] p-8 border border-slate-100 shadow-xl shadow-slate-200/50 flex flex-col items-center">
        <p class="text-[11px] font-black text-slate-400 uppercase tracking-widest mb-1">งบประมาณคงเหลือ</p>
        <h3 class="text-3xl font-black text-slate-800"><?= number_format($totalRemaining, 2) ?></h3>
    </div>

    <div class="bg-white rounded-[2rem] p-8 border border-slate-100 shadow-xl shadow-slate-200/50 flex flex-col items-center">
        <p class="text-[11px] font-black text-slate-400 uppercase tracking-widest mb-1">% การใช้จ่าย</p>
        <h3 class="text-3xl font-black <?= $percentUsed > 90 ? 'text-rose-500' : 'text-emerald-500' ?>">
            <?= number_format($percentUsed, 1) ?>%
        </h3>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
    <!-- Left Side: Dept Progress Bars -->
    <div class="bg-white rounded-[2.5rem] p-8 border border-slate-100 shadow-xl shadow-slate-200/50">
        <h4 class="text-lg font-black text-slate-800 mb-8">การใช้จ่ายรายฝ่าย</h4>
        <div class="space-y-6">
            <?php foreach ($deptBudgets as $dept): 
                $p = ($dept['alloc_total'] > 0) ? ($dept['used_total'] / $dept['alloc_total']) * 100 : 0;
                $colorClass = $p > 90 ? 'bg-rose-500' : ($p > 70 ? 'bg-amber-500' : 'bg-emerald-500');
            ?>
            <div class="space-y-2">
                <div class="flex justify-between text-xs font-bold uppercase tracking-wider">
                    <span class="text-slate-500"><?= htmlspecialchars($dept['department_name']) ?></span>
                    <span class="text-slate-800"><?= number_format($p, 1) ?>%</span>
                </div>
                <div class="w-full bg-slate-100 h-3 rounded-full overflow-hidden">
                    <div class="<?= $colorClass ?> h-full transition-all duration-1000" style="width: <?= $p ?>%"></div>
                </div>
                <div class="flex justify-between text-[10px] font-black uppercase text-slate-300">
                    <span>ใช้ <?= number_format($dept['used_total']) ?></span>
                    <span>จัดสรร <?= number_format($dept['alloc_total']) ?></span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Right Side: Recent Approved & Charts -->
    <div class="space-y-8">
        <!-- Horizontal Bar Chart -->
        <div class="bg-white rounded-[2.5rem] p-8 border border-slate-100 shadow-xl shadow-slate-200/50">
            <h4 class="text-lg font-black text-slate-800 mb-8">ยอดคงเหลือรายฝ่าย (บาท)</h4>
            <div class="h-64 w-full">
                <canvas id="deptRemainingChart"></canvas>
            </div>
        </div>

        <!-- Table: Waiting for disbursement -->
        <div class="bg-white rounded-[2.5rem] p-8 border border-slate-100 shadow-xl shadow-slate-200/50">
            <div class="flex items-center justify-between mb-8">
                <h4 class="text-lg font-black text-slate-800">คำขอที่อนุมัติแล้ว</h4>
                <a href="<?= BASE_URL ?>/reports/budget_overview.php" class="text-xs font-bold text-blue-600 hover:underline">ดูรายงานทั้งหมด</a>
            </div>
            <div class="space-y-4">
                <?php foreach ($recentApproved as $item): ?>
                <div class="flex items-center justify-between p-4 bg-slate-50 rounded-2xl border border-slate-100">
                    <div>
                        <p class="font-bold text-slate-700 text-sm"><?= htmlspecialchars($item['project_name']) ?></p>
                        <p class="text-[9px] text-slate-400 font-bold uppercase tracking-widest"><?= htmlspecialchars($item['teacher_name']) ?> • <?= date('d/m/Y', strtotime($item['approved_at'])) ?></p>
                    </div>
                    <div class="text-right">
                        <p class="font-black text-slate-800 text-sm"><?= number_format($item['amount_requested'], 2) ?></p>
                        <span class="text-[9px] font-black bg-emerald-100 text-emerald-600 px-2 py-0.5 rounded-full uppercase">พร้อมเบิก</span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Dept Remaining Chart
    fetch('<?= BASE_URL ?>/api/report_data.php?type=dept_remaining')
        .then(res => res.json())
        .then(data => {
            new Chart(document.getElementById('deptRemainingChart').getContext('2d'), {
                type: 'bar',
                data: {
                    labels: data.labels,
                    datasets: [{
                        label: 'งบคงเหลือ',
                        data: data.values,
                        backgroundColor: 'rgba(37, 99, 235, 0.6)',
                        borderRadius: 12
                    }]
                },
                options: {
                    indexAxis: 'y',
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: { x: { grid: { display: false } }, y: { grid: { display: false } } }
                }
            });
        });
});
</script>

<?php require_once __DIR__ . '/../components/layout_end.php'; ?>
