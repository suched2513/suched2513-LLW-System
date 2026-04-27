<?php
session_start();
require_once __DIR__ . '/../config/auth.php';
checkRole(['director', 'admin']);

$pageTitle = 'Director Dashboard';
$pageSubtitle = 'ภาพรวมงบประมาณและการดำเนินงานรายปี';
require_once __DIR__ . '/../components/layout_start.php';

$pdo = getPdo();
$fiscal_year = FISCAL_YEAR;

// 1. Fetch KPI Metrics
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'submitted' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
        SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected
    FROM project_requests
");
$stmt->execute();
$stats = $stmt->fetch();

// 2. Pending Requests List
$stmt = $pdo->query("
    SELECT r.*, p.project_name, u.full_name as teacher_name
    FROM project_requests r
    JOIN budget_projects p ON r.budget_project_id = p.id
    JOIN users u ON r.user_id = u.id
    WHERE r.status = 'submitted'
    ORDER BY r.created_at DESC LIMIT 5
");
$pendingList = $stmt->fetchAll();

// 3. Overdue Projects (> 30 days in draft or no request)
$stmt = $pdo->query("
    SELECT bp.*, d.name as dept_name, pr.status, pr.created_at as req_date
    FROM budget_projects bp
    JOIN departments d ON bp.department_id = d.id
    LEFT JOIN project_requests pr ON pr.budget_project_id = bp.id
    WHERE bp.is_active = 1 
    AND (pr.id IS NULL OR (pr.status = 'draft' AND DATEDIFF(NOW(), pr.created_at) > 30))
    LIMIT 5
");
$overdueList = $stmt->fetchAll();
?>

<!-- KPI Row -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-10">
    <div class="bg-white rounded-[2rem] p-8 border border-slate-100 shadow-xl shadow-slate-200/50 flex flex-col items-center">
        <div class="w-12 h-12 bg-blue-50 text-blue-600 rounded-2xl flex items-center justify-center text-2xl mb-4">
            <i class="bi bi-folder-fill"></i>
        </div>
        <p class="text-[11px] font-black text-slate-400 uppercase tracking-widest mb-1">โครงการทั้งหมด</p>
        <h3 class="text-3xl font-black text-slate-800"><?= number_format($stats['total'] ?? 0) ?></h3>
    </div>
    
    <div class="bg-amber-500 rounded-[2rem] p-8 text-white shadow-xl shadow-amber-200/50 flex flex-col items-center">
        <div class="w-12 h-12 bg-white/20 rounded-2xl flex items-center justify-center text-2xl mb-4">
            <i class="bi bi-hourglass-split"></i>
        </div>
        <p class="text-[11px] font-black text-amber-100 uppercase tracking-widest mb-1">รออนุมัติ</p>
        <h3 class="text-3xl font-black"><?= number_format($stats['pending'] ?? 0) ?></h3>
    </div>

    <div class="bg-emerald-500 rounded-[2rem] p-8 text-white shadow-xl shadow-emerald-200/50 flex flex-col items-center">
        <div class="w-12 h-12 bg-white/20 rounded-2xl flex items-center justify-center text-2xl mb-4">
            <i class="bi bi-check-circle-fill"></i>
        </div>
        <p class="text-[11px] font-black text-emerald-100 uppercase tracking-widest mb-1">อนุมัติแล้ว</p>
        <h3 class="text-3xl font-black"><?= number_format($stats['approved'] ?? 0) ?></h3>
    </div>

    <div class="bg-rose-500 rounded-[2rem] p-8 text-white shadow-xl shadow-rose-200/50 flex flex-col items-center">
        <div class="w-12 h-12 bg-white/20 rounded-2xl flex items-center justify-center text-2xl mb-4">
            <i class="bi bi-x-circle-fill"></i>
        </div>
        <p class="text-[11px] font-black text-rose-100 uppercase tracking-widest mb-1">ปฏิเสธ</p>
        <h3 class="text-3xl font-black"><?= number_format($stats['rejected'] ?? 0) ?></h3>
    </div>
</div>

<!-- Charts Row -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-8 mb-10">
    <!-- Chart 1: Budget Usage -->
    <div class="lg:col-span-2 bg-white rounded-[2.5rem] p-8 border border-slate-100 shadow-xl shadow-slate-200/50">
        <div class="flex items-center justify-between mb-8">
            <div>
                <h4 class="text-lg font-black text-slate-800">การใช้งบประมาณรายฝ่าย</h4>
                <p class="text-[10px] text-slate-400 font-bold uppercase tracking-wider">Budget Allocation vs Actual Usage</p>
            </div>
        </div>
        <div class="h-80 w-full">
            <canvas id="budgetUsageChart"></canvas>
        </div>
    </div>

    <!-- Chart 2: Fund Distribution -->
    <div class="bg-white rounded-[2.5rem] p-8 border border-slate-100 shadow-xl shadow-slate-200/50">
        <h4 class="text-lg font-black text-slate-800 mb-2 text-center">สัดส่วนประเภทเงิน</h4>
        <p class="text-[10px] text-slate-400 font-bold uppercase tracking-wider text-center mb-8">Fund Type Distribution</p>
        <div class="h-64 w-full">
            <canvas id="fundDistributionChart"></canvas>
        </div>
    </div>
</div>

<!-- Bottom Charts & Tables -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
    <!-- Chart 3: Monthly Requests -->
    <div class="bg-white rounded-[2.5rem] p-8 border border-slate-100 shadow-xl shadow-slate-200/50">
        <h4 class="text-lg font-black text-slate-800 mb-8">แนวโน้มคำขอใช้เงิน (12 เดือนล่าสุด)</h4>
        <div class="h-64 w-full">
            <canvas id="monthlyRequestsChart"></canvas>
        </div>
    </div>

    <!-- Pending Requests List -->
    <div class="bg-white rounded-[2.5rem] p-8 border border-slate-100 shadow-xl shadow-slate-200/50">
        <div class="flex items-center justify-between mb-8">
            <h4 class="text-lg font-black text-slate-800">รายการรออนุมัติล่าสุด</h4>
            <a href="<?= BASE_URL ?>/director/pending.php" class="text-xs font-bold text-blue-600 hover:underline">ดูทั้งหมด</a>
        </div>
        <div class="space-y-4">
            <?php foreach ($pendingList as $item): ?>
            <div class="flex items-center justify-between p-4 bg-slate-50 rounded-2xl border border-slate-100 group hover:border-blue-200 transition-all">
                <div class="flex items-center gap-4">
                    <div class="w-10 h-10 bg-white rounded-xl flex items-center justify-center text-blue-600 shadow-sm">
                        <i class="bi bi-file-earmark-text"></i>
                    </div>
                    <div>
                        <p class="font-bold text-slate-700 text-sm truncate max-w-[200px]"><?= htmlspecialchars($item['project_name']) ?></p>
                        <p class="text-[9px] text-slate-400 font-bold uppercase tracking-widest">โดย <?= htmlspecialchars($item['teacher_name']) ?></p>
                    </div>
                </div>
                <div class="text-right">
                    <p class="font-black text-slate-800 text-sm"><?= number_format($item['amount_requested'], 2) ?></p>
                    <a href="<?= BASE_URL ?>/director/pending.php" class="text-[9px] font-black text-blue-600 uppercase hover:underline">อนุมัติ</a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Chart Colors
    const colors = {
        primary: '#2563eb',
        primaryLight: 'rgba(37, 99, 235, 0.1)',
        secondary: '#10b981',
        secondaryLight: 'rgba(16, 185, 129, 0.1)',
        warning: '#f59e0b',
        danger: '#ef4444'
    };

    // 1. Budget Usage Chart
    fetch('<?= BASE_URL ?>/api/report_data.php?type=budget_usage')
        .then(res => res.json())
        .then(data => {
            new Chart(document.getElementById('budgetUsageChart').getContext('2d'), {
                type: 'bar',
                data: {
                    labels: data.labels,
                    datasets: [
                        {
                            label: 'งบจัดสรร',
                            data: data.alloc,
                            backgroundColor: colors.primaryLight,
                            borderColor: colors.primary,
                            borderWidth: 2,
                            borderRadius: 8
                        },
                        {
                            label: 'ใช้จริง',
                            data: data.used,
                            backgroundColor: colors.secondaryLight,
                            borderColor: colors.secondary,
                            borderWidth: 2,
                            borderRadius: 8
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { position: 'bottom' } },
                    scales: { y: { beginAtZero: true, grid: { display: false } }, x: { grid: { display: false } } }
                }
            });
        });

    // 2. Fund Distribution Chart
    fetch('<?= BASE_URL ?>/api/report_data.php?type=fund_types')
        .then(res => res.json())
        .then(data => {
            new Chart(document.getElementById('fundDistributionChart').getContext('2d'), {
                type: 'doughnut',
                data: {
                    labels: data.labels,
                    datasets: [{
                        data: data.values,
                        backgroundColor: ['#3b82f6', '#6366f1', '#10b981', '#f59e0b', '#f43f5e'],
                        borderWidth: 0,
                        hoverOffset: 20
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '70%',
                    plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, padding: 20 } } }
                }
            });
        });

    // 3. Monthly Requests Chart
    fetch('<?= BASE_URL ?>/api/report_data.php?type=monthly_requests')
        .then(res => res.json())
        .then(data => {
            new Chart(document.getElementById('monthlyRequestsChart').getContext('2d'), {
                type: 'line',
                data: {
                    labels: data.labels,
                    datasets: [{
                        label: 'ยอดเงินที่ขอใช้',
                        data: data.totals,
                        borderColor: colors.primary,
                        backgroundColor: colors.primaryLight,
                        borderWidth: 4,
                        pointRadius: 6,
                        pointBackgroundColor: '#fff',
                        fill: true,
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: { y: { beginAtZero: true }, x: { grid: { display: false } } }
                }
            });
        });
});
</script>

<?php require_once __DIR__ . '/../components/layout_end.php'; ?>
