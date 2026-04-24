<?php
require_once 'config.php';
if (!isLoggedIn()) {
    header('Location: ../login.php');
    exit();
}

$db = connectDB();

try {
    // รายงานภาพรวมงบประมาณ
    $stmt = $db->query("
        SELECT 
            SUM(total_budget) as total_budget,
            COUNT(*) as total_projects,
            COUNT(CASE WHEN status = 'active' THEN 1 END) as active_projects,
            COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_projects,
            (SELECT COUNT(*) FROM budget_disbursements WHERE status = 'pending') as pending_requests
        FROM budget_projects
    ");
    $summary = $stmt->fetch(PDO::FETCH_ASSOC);

    // รายงานการใช้จ่ายรวม
    $stmt = $db->query("
        SELECT 
            SUM(CASE WHEN transaction_type = 'expense' THEN amount ELSE 0 END) as total_expense,
            SUM(CASE WHEN transaction_type = 'income' THEN amount ELSE 0 END) as total_income
        FROM budget_transactions
    ");
    $transactions_summary = $stmt->fetch(PDO::FETCH_ASSOC);

    // โครงการที่กำลังดำเนินการ
    $stmt = $db->query("
        SELECT p.*, 
            (SELECT SUM(amount) FROM budget_transactions 
             WHERE project_id = p.project_id AND transaction_type = 'expense') as used_budget
        FROM budget_projects p
        WHERE status = 'active'
        ORDER BY end_date ASC
        LIMIT 5
    ");
    $active_projects = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // รายการขอเบิกจ่ายล่าสุด
    $stmt = $db->query("
        SELECT d.*, p.project_name, u.firstname
        FROM budget_disbursements d
        JOIN budget_projects p ON d.project_id = p.project_id
        JOIN llw_users u ON d.requested_by = u.user_id
        ORDER BY d.created_at DESC
        LIMIT 5
    ");
    $recent_requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ข้อมูลสำหรับแผนภูมิแท่ง (Top 5 Projects by Budget)
    $stmt = $db->query("
        SELECT 
            p.project_name,
            p.total_budget,
            COALESCE(SUM(CASE WHEN t.transaction_type = 'expense' 
                           THEN t.amount ELSE 0 END), 0) as used_budget
        FROM budget_projects p
        LEFT JOIN budget_transactions t ON p.project_id = t.project_id
        WHERE p.status = 'active'
        GROUP BY p.project_id, p.project_name, p.total_budget
        ORDER BY p.total_budget DESC
        LIMIT 5
    ");
    $chart_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ข้อมูลสำหรับแผนภูมิวงกลม
    $pie_data = [
        [
            'name' => 'งบประมาณคงเหลือ',
            'value' => max(0, $summary['total_budget'] - $transactions_summary['total_expense'])
        ],
        [
            'name' => 'ใช้งบประมาณไปแล้ว',
            'value' => $transactions_summary['total_expense']
        ]
    ];
} catch (PDOException $e) {
    die("Query failed: " . $e->getMessage());
}

$pageTitle = 'ระบบงบประมาณ';
$pageSubtitle = 'ภาพรวมงบประมาณและการดำเนินงาน';
$activeSystem = 'budget';
require_once __DIR__ . '/../components/layout_start.php';
?>

<div class="space-y-6">
    <!-- Stats Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
        <div class="bg-gradient-to-br from-indigo-600 to-blue-700 rounded-[2rem] p-6 text-white shadow-xl shadow-indigo-200/50 relative overflow-hidden group">
            <div class="relative z-10">
                <p class="text-[10px] font-black opacity-70 uppercase tracking-widest">งบประมาณรวม</p>
                <p class="text-2xl font-black mt-1 italic">฿<?php echo number_format($summary['total_budget'], 2); ?></p>
                <div class="mt-4 flex items-center gap-2">
                    <span class="px-2 py-0.5 rounded-full bg-white/20 text-[9px] font-black uppercase"><?php echo $summary['total_projects']; ?> โครงการ</span>
                </div>
            </div>
            <i class="bi bi-bank absolute -right-2 -bottom-2 text-6xl opacity-10 group-hover:scale-110 transition-transform"></i>
        </div>

        <div class="bg-gradient-to-br from-emerald-500 to-teal-600 rounded-[2rem] p-6 text-white shadow-xl shadow-emerald-200/50 relative overflow-hidden group">
            <div class="relative z-10">
                <p class="text-[10px] font-black opacity-70 uppercase tracking-widest">งบประมาณคงเหลือ</p>
                <p class="text-2xl font-black mt-1 italic">฿<?php echo number_format($summary['total_budget'] - $transactions_summary['total_expense'], 2); ?></p>
                <div class="mt-4 flex items-center gap-2">
                    <span class="text-[9px] font-black uppercase opacity-70">คิดเป็น <?php echo number_format(($summary['total_budget'] - $transactions_summary['total_expense']) / max(1, $summary['total_budget']) * 100, 1); ?>%</span>
                </div>
            </div>
            <i class="bi bi-wallet2 absolute -right-2 -bottom-2 text-6xl opacity-10 group-hover:scale-110 transition-transform"></i>
        </div>

        <div class="bg-gradient-to-br from-rose-500 to-pink-600 rounded-[2rem] p-6 text-white shadow-xl shadow-rose-200/50 relative overflow-hidden group">
            <div class="relative z-10">
                <p class="text-[10px] font-black opacity-70 uppercase tracking-widest">ใช้ไปแล้ว</p>
                <p class="text-2xl font-black mt-1 italic">฿<?php echo number_format($transactions_summary['total_expense'], 2); ?></p>
                <div class="mt-4 flex items-center gap-2">
                    <span class="text-[9px] font-black uppercase opacity-70">คิดเป็น <?php echo number_format($transactions_summary['total_expense'] / max(1, $summary['total_budget']) * 100, 1); ?>%</span>
                </div>
            </div>
            <i class="bi bi-receipt-cutoff absolute -right-2 -bottom-2 text-6xl opacity-10 group-hover:scale-110 transition-transform"></i>
        </div>

        <div class="bg-gradient-to-br from-amber-500 to-orange-600 rounded-[2rem] p-6 text-white shadow-xl shadow-amber-200/50 relative overflow-hidden group">
            <div class="relative z-10">
                <p class="text-[10px] font-black opacity-70 uppercase tracking-widest">รอดำเนินการ</p>
                <p class="text-2xl font-black mt-1 italic"><?php echo $summary['pending_requests']; ?> <span class="text-sm font-bold uppercase opacity-80 not-italic">โครงการ</span></p>
                <div class="mt-4 flex items-center gap-2">
                    <span class="px-2 py-0.5 rounded-full bg-white/20 text-[9px] font-black uppercase tracking-widest">คำขอที่ยังไม่อนุมัติ</span>
                </div>
            </div>
            <i class="bi bi-hourglass-split absolute -right-4 -bottom-4 text-7xl opacity-20 group-hover:scale-110 group-hover:-rotate-12 transition-all"></i>
        </div>
    </div>

    <!-- Charts Row -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 bg-white rounded-[2rem] shadow-xl shadow-slate-100/50 border border-slate-100 p-8 overflow-hidden relative group">
            <div class="flex items-center justify-between mb-8">
                <h3 class="text-lg font-black text-slate-800">งบประมาณรายโครงการ <span class="text-xs font-bold text-slate-400 block mt-1 uppercase tracking-widest">(Top 5 Projects)</span></h3>
                <i class="bi bi-bar-chart text-blue-500 text-xl"></i>
            </div>
            <div class="h-[300px] w-full">
                <canvas id="barChart"></canvas>
            </div>
        </div>

        <div class="bg-white rounded-[2rem] shadow-xl shadow-slate-100/50 border border-slate-100 p-8 overflow-hidden relative group">
            <div class="flex items-center justify-between mb-8">
                <h3 class="text-lg font-black text-slate-800">สัดส่วนการใช้ <span class="text-xs font-bold text-slate-400 block mt-1 uppercase tracking-widest">(Budget Usage)</span></h3>
                <i class="bi bi-pie-chart text-indigo-500 text-xl"></i>
            </div>
            <div class="h-[300px] w-full">
                <canvas id="pieChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Tables Row -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Active Projects -->
        <div class="bg-white rounded-[2rem] shadow-xl shadow-slate-100/50 border border-slate-100 overflow-hidden">
            <div class="px-8 py-6 border-b border-slate-100 flex items-center justify-between">
                <h3 class="text-lg font-black text-slate-800">โครงการปัจจุบัน</h3>
                <a href="projects.php" class="text-xs font-black text-blue-600 hover:text-blue-700 uppercase tracking-widest">ดูทั้งหมด</a>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead class="bg-slate-50/50">
                        <tr>
                            <th class="px-8 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">โครงการ</th>
                            <th class="px-8 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">ความคืบหน้า</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        <?php foreach ($active_projects as $project): ?>
                            <?php
                            $used = $project['used_budget'] ?? 0;
                            $percentage = ($used / max(1, $project['total_budget'])) * 100;
                            $status_color = $percentage > 80 ? 'rose' : ($percentage > 60 ? 'amber' : 'emerald');
                            ?>
                            <tr class="hover:bg-slate-50/50 transition-all">
                                <td class="px-8 py-5">
                                    <p class="text-sm font-bold text-slate-800"><?php echo h($project['project_name']); ?></p>
                                    <p class="text-[10px] text-slate-400 mt-1 italic">Ends: <?php echo date('d/m/Y', strtotime($project['end_date'])); ?></p>
                                </td>
                                <td class="px-8 py-5">
                                    <div class="flex items-center justify-between mb-2">
                                        <span class="text-[9px] font-black text-<?php echo $status_color; ?>-600 uppercase"><?php echo number_format($percentage, 1); ?>%</span>
                                        <span class="text-[9px] font-bold text-slate-300">฿<?php echo number_format($used, 0); ?></span>
                                    </div>
                                    <div class="h-1.5 w-full bg-slate-100 rounded-full overflow-hidden">
                                        <div class="h-full bg-<?php echo $status_color; ?>-500 rounded-full" style="width: <?php echo min(100, $percentage); ?>%"></div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Recent Transactions -->
        <div class="bg-white rounded-[2rem] shadow-xl shadow-slate-100/50 border border-slate-100 overflow-hidden">
            <div class="px-8 py-6 border-b border-slate-100 flex items-center justify-between">
                <h3 class="text-lg font-black text-slate-800">รายการล่าสุด</h3>
                <span class="text-[10px] font-black text-slate-300 uppercase tracking-widest">History</span>
            </div>
            <div class="divide-y divide-slate-50">
                <?php foreach ($recent_transactions as $trans): ?>
                    <div class="px-8 py-5 hover:bg-slate-50/50 transition-all flex items-center justify-between group">
                        <div class="flex-1">
                            <p class="text-xs font-black text-slate-400 uppercase tracking-widest mb-1"><?php echo h($trans['project_name']); ?></p>
                            <p class="text-sm font-bold text-slate-700"><?php echo h($trans['description']); ?></p>
                            <div class="flex items-center gap-2 mt-2">
                                <div class="w-5 h-5 rounded-md bg-slate-100 flex items-center justify-center text-[9px] font-black text-slate-400 uppercase">
                                    <?php echo mb_substr($trans['username'] ?? 'U', 0, 1); ?>
                                </div>
                                <span class="text-[10px] font-bold text-slate-400"><?php echo h($trans['username'] ?? 'System'); ?> • <?php echo date('d/m/Y', strtotime($trans['created_at'])); ?></span>
                            </div>
                        </div>
                        <div class="text-right">
                            <p class="text-sm font-black italic <?php echo $trans['transaction_type'] == 'income' ? 'text-emerald-500' : 'text-rose-500'; ?>">
                                <?php echo $trans['transaction_type'] == 'income' ? '+' : '-'; ?>฿<?php echo number_format($trans['amount'], 2); ?>
                            </p>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const formatMoney = (value) => {
        return '฿' + new Intl.NumberFormat('th-TH').format(value);
    };

    // Bar Chart
    const chartData = <?php echo json_encode($chart_data); ?>;
    const barCtx = document.getElementById('barChart').getContext('2d');
    new Chart(barCtx, {
        type: 'bar',
        data: {
            labels: chartData.map(item => item.project_name.length > 15 ? item.project_name.substring(0, 15) + '...' : item.project_name),
            datasets: [
                {
                    label: 'งบประมาณ',
                    data: chartData.map(item => item.total_budget),
                    backgroundColor: '#4f46e5',
                    borderRadius: 8,
                    barThickness: 20
                },
                {
                    label: 'ใช้ไปแล้ว',
                    data: chartData.map(item => item.used_budget),
                    backgroundColor: '#fb7185',
                    borderRadius: 8,
                    barThickness: 20
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: (ctx) => ctx.dataset.label + ': ' + formatMoney(ctx.raw)
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: { color: '#f8fafc' },
                    ticks: {
                        font: { size: 10, weight: 'bold' },
                        color: '#94a3b8',
                        callback: (v) => v >= 1000 ? (v/1000) + 'k' : v
                    }
                },
                x: {
                    grid: { display: false },
                    ticks: { font: { size: 10, weight: 'bold' }, color: '#94a3b8' }
                }
            }
        }
    });

    // Pie Chart
    const pieData = <?php echo json_encode($pie_data); ?>;
    const pieCtx = document.getElementById('pieChart').getContext('2d');
    new Chart(pieCtx, {
        type: 'doughnut',
        data: {
            labels: pieData.map(item => item.name),
            datasets: [{
                data: pieData.map(item => item.value),
                backgroundColor: ['#10b981', '#f43f5e'],
                borderWidth: 0,
                hoverOffset: 10
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '70%',
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        font: { size: 10, weight: 'bold' },
                        color: '#64748b',
                        usePointStyle: true,
                        padding: 20
                    }
                },
                tooltip: {
                    callbacks: {
                        label: (ctx) => {
                            const total = ctx.dataset.data.reduce((a, b) => a + b, 0);
                            const perc = ((ctx.raw / total) * 100).toFixed(1);
                            return ctx.label + ': ' + formatMoney(ctx.raw) + ' (' + perc + '%)';
                        }
                    }
                }
            }
        }
    });
});
</script>

<?php require_once __DIR__ . '/../components/layout_end.php'; ?>