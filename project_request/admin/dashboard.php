<?php
session_start();
$pageTitle = 'Dashboard';
$pageSubtitle = 'ระบบบริหารจัดการงบประมาณโรงเรียน';
require_once __DIR__ . '/../components/layout_start.php';

try {
    $pdo = getPdo();
    
    // 1. Total Budget (sum of all budget columns in budget_projects)
    $stmt = $pdo->query("SELECT SUM(budget_subsidy + budget_quality + budget_revenue + budget_operation + budget_reserve) as total FROM budget_projects WHERE is_active = 1");
    $totalBudget = $stmt->fetch()['total'] ?? 0;
    
    // 2. Total Requests
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM project_requests");
    $totalRequests = $stmt->fetch()['count'] ?? 0;
    
    // 3. Pending Approvals
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM project_requests WHERE status = 'submitted'");
    $pendingRequests = $stmt->fetch()['count'] ?? 0;
    
    // 4. Approved Amount
    $stmt = $pdo->query("SELECT SUM(amount_requested) as total FROM project_requests WHERE status = 'approved'");
    $approvedAmount = $stmt->fetch()['total'] ?? 0;

} catch (Exception $e) {
    error_log($e->getMessage());
}
?>

<!-- KPI Stats -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-10">
    <!-- Total Budget -->
    <div class="bg-gradient-to-br from-blue-600 to-indigo-700 rounded-[2rem] p-8 text-white shadow-xl shadow-blue-200">
        <div class="flex items-center justify-between mb-4">
            <div class="w-12 h-12 bg-white/20 rounded-2xl flex items-center justify-center text-2xl">
                <i class="bi bi-bank"></i>
            </div>
            <span class="text-[10px] font-black bg-white/20 px-3 py-1 rounded-full uppercase tracking-widest">Total Budget</span>
        </div>
        <p class="text-[11px] font-bold text-blue-100 uppercase tracking-widest mb-1">งบประมาณรวมทั้งสิ้น</p>
        <h3 class="text-3xl font-black"><?= number_format($totalBudget, 2) ?></h3>
        <p class="text-xs text-blue-100/60 mt-4 font-bold uppercase tracking-tighter">ปีงบประมาณ <?= FISCAL_YEAR ?></p>
    </div>

    <!-- Approved Requests -->
    <div class="bg-white rounded-[2rem] p-8 border border-slate-100 shadow-xl shadow-slate-200/50">
        <div class="flex items-center justify-between mb-4">
            <div class="w-12 h-12 bg-emerald-50 text-emerald-500 rounded-2xl flex items-center justify-center text-2xl">
                <i class="bi bi-check-circle-fill"></i>
            </div>
            <span class="text-[10px] font-black bg-emerald-50 text-emerald-600 px-3 py-1 rounded-full uppercase tracking-widest">Approved</span>
        </div>
        <p class="text-[11px] font-bold text-slate-400 uppercase tracking-widest mb-1">งบที่อนุมัติใช้แล้ว</p>
        <h3 class="text-3xl font-black text-slate-800"><?= number_format($approvedAmount, 2) ?></h3>
        <div class="w-full bg-slate-100 h-2 rounded-full mt-4 overflow-hidden">
            <?php $percent = $totalBudget > 0 ? ($approvedAmount / $totalBudget) * 100 : 0; ?>
            <div class="bg-emerald-500 h-full" style="width: <?= $percent ?>%"></div>
        </div>
    </div>

    <!-- Pending Requests -->
    <div class="bg-white rounded-[2rem] p-8 border border-slate-100 shadow-xl shadow-slate-200/50">
        <div class="flex items-center justify-between mb-4">
            <div class="w-12 h-12 bg-amber-50 text-amber-500 rounded-2xl flex items-center justify-center text-2xl">
                <i class="bi bi-hourglass-split"></i>
            </div>
            <span class="text-[10px] font-black bg-amber-50 text-amber-600 px-3 py-1 rounded-full uppercase tracking-widest">Pending</span>
        </div>
        <p class="text-[11px] font-bold text-slate-400 uppercase tracking-widest mb-1">คำขอรออนุมัติ</p>
        <h3 class="text-3xl font-black text-slate-800"><?= number_format($pendingRequests) ?></h3>
        <p class="text-xs text-slate-400 mt-4 font-bold">จากทั้งหมด <?= number_format($totalRequests) ?> คำขอ</p>
    </div>

    <!-- Users Count -->
    <div class="bg-white rounded-[2rem] p-8 border border-slate-100 shadow-xl shadow-slate-200/50">
        <div class="flex items-center justify-between mb-4">
            <div class="w-12 h-12 bg-indigo-50 text-indigo-500 rounded-2xl flex items-center justify-center text-2xl">
                <i class="bi bi-people-fill"></i>
            </div>
            <span class="text-[10px] font-black bg-indigo-50 text-indigo-600 px-3 py-1 rounded-full uppercase tracking-widest">Staff</span>
        </div>
        <p class="text-[11px] font-bold text-slate-400 uppercase tracking-widest mb-1">จำนวนผู้ใช้งาน</p>
        <?php 
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
            $userCount = $stmt->fetch()['count'] ?? 0;
        ?>
        <h3 class="text-3xl font-black text-slate-800"><?= number_format($userCount) ?></h3>
        <p class="text-xs text-slate-400 mt-4 font-bold">แยกตามบทบาทในระบบ</p>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
    <!-- Chart Section (Placeholder for now) -->
    <div class="lg:col-span-2 bg-white rounded-[2.5rem] p-8 border border-slate-100 shadow-xl shadow-slate-200/50">
        <div class="flex items-center justify-between mb-8">
            <div>
                <h4 class="text-lg font-black text-slate-800">งบประมาณตามกลุ่มงาน</h4>
                <p class="text-xs text-slate-400 font-bold uppercase tracking-wider">Budget Allocation by Department</p>
            </div>
        </div>
        <div class="h-80 w-full flex items-center justify-center bg-slate-50 rounded-3xl border-2 border-dashed border-slate-100">
            <canvas id="deptChart"></canvas>
        </div>
    </div>

    <!-- Quick Actions / Notifications -->
    <div class="bg-white rounded-[2.5rem] p-8 border border-slate-100 shadow-xl shadow-slate-200/50">
        <h4 class="text-lg font-black text-slate-800 mb-6">เมนูเร่งด่วน</h4>
        <div class="space-y-4">
            <a href="import_budget.php" class="flex items-center gap-4 p-4 bg-blue-50 rounded-2xl border border-blue-100 hover:bg-blue-100 transition-all group">
                <div class="w-12 h-12 bg-blue-600 rounded-xl flex items-center justify-center text-white text-xl shadow-lg shadow-blue-100 group-hover:scale-110 transition-transform">
                    <i class="bi bi-file-earmark-arrow-up"></i>
                </div>
                <div>
                    <p class="font-black text-blue-900 text-sm">นำเข้างบประมาณ</p>
                    <p class="text-[10px] text-blue-400 font-bold uppercase">Import from Excel</p>
                </div>
            </a>
            <a href="users.php" class="flex items-center gap-4 p-4 bg-slate-50 rounded-2xl border border-slate-100 hover:bg-slate-100 transition-all group">
                <div class="w-12 h-12 bg-slate-800 rounded-xl flex items-center justify-center text-white text-xl shadow-lg shadow-slate-200 group-hover:scale-110 transition-transform">
                    <i class="bi bi-person-plus"></i>
                </div>
                <div>
                    <p class="font-black text-slate-800 text-sm">จัดการผู้ใช้งาน</p>
                    <p class="text-[10px] text-slate-400 font-bold uppercase">Manage User Roles</p>
                </div>
            </a>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('deptChart').getContext('2d');
    
    <?php
        // Fetch budget by department for the chart
        $stmt = $pdo->query("SELECT d.name, SUM(b.budget_subsidy + b.budget_quality + b.budget_revenue + b.budget_operation + b.budget_reserve) as total 
                             FROM budget_projects b 
                             JOIN departments d ON b.department_id = d.id 
                             GROUP BY d.id");
        $deptData = $stmt->fetchAll();
        $labels = array_column($deptData, 'name');
        $data = array_column($deptData, 'total');
    ?>

    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: <?= json_encode($labels) ?>,
            datasets: [{
                label: 'งบประมาณ (บาท)',
                data: <?= json_encode($data) ?>,
                backgroundColor: 'rgba(37, 99, 235, 0.1)',
                borderColor: 'rgba(37, 99, 235, 1)',
                borderWidth: 3,
                borderRadius: 12,
                borderSkipped: false,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false }
            },
            scales: {
                y: { beginAtZero: true, grid: { display: false }, border: { display: false } },
                x: { grid: { display: false }, border: { display: false } }
            }
        }
    });
});
</script>

<?php require_once __DIR__ . '/../components/layout_end.php'; ?>
