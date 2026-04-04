<?php
session_start();
require_once '../config.php';

// Auth: super_admin or WFH admin
if (!isset($_SESSION['llw_role']) || !in_array($_SESSION['llw_role'], ['super_admin', 'wfh_admin'])) {
    // Check if they are logged in via legacy WFH session
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
        header("Location: ../login.php");
        exit();
    }
}

$pageTitle = 'WFH Dashboard';
$pageSubtitle = 'ระบบลงเวลาปฏิบัติงานนอกสถานที่';
$activeSystem = 'wfh';

$today = date('Y-m-d');

// Statistics
$total_users      = $conn->query("SELECT COUNT(*) as c FROM wfh_users WHERE role='user'")->fetch_assoc()['c'];
$checkedin_today  = $conn->query("SELECT COUNT(*) as c FROM wfh_timelogs WHERE log_date='$today' AND check_in_time IS NOT NULL")->fetch_assoc()['c'];
$late_today       = $conn->query("SELECT COUNT(*) as c FROM wfh_timelogs WHERE log_date='$today' AND check_in_status='มาสาย'")->fetch_assoc()['c'];
$not_yet          = $total_users - $checkedin_today;

// Today's logs
$stmt = $conn->query("
    SELECT u.firstname, u.lastname, u.position, d.dept_name, t.check_in_time, t.check_out_time, t.check_in_status
    FROM wfh_timelogs t
    JOIN wfh_users u ON t.user_id = u.user_id
    LEFT JOIN wfh_departments d ON u.dept_id = d.dept_id
    WHERE t.log_date = '$today'
    ORDER BY t.check_in_time ASC
");
$today_logs = $stmt->fetch_all(MYSQLI_ASSOC);

require_once '../components/layout_start.php';
?>

<div class="flex flex-col gap-8">
    
    <!-- Quick Nav -->
    <div class="flex gap-4 overflow-x-auto no-scrollbar py-2">
        <a href="dashboard.php" class="px-8 py-3 bg-blue-600 text-white rounded-2xl font-black text-[10px] uppercase tracking-widest shadow-lg shadow-blue-100 whitespace-nowrap">Overview</a>
        <a href="manage_users.php" class="px-8 py-3 bg-white text-slate-400 rounded-2xl font-black text-[10px] uppercase tracking-widest border border-slate-100 hover:bg-slate-50 transition-all whitespace-nowrap">Manage Users</a>
        <a href="reports.php" class="px-8 py-3 bg-white text-slate-400 rounded-2xl font-black text-[10px] uppercase tracking-widest border border-slate-100 hover:bg-slate-50 transition-all whitespace-nowrap">Reports</a>
        <a href="settings.php" class="px-8 py-3 bg-white text-slate-400 rounded-2xl font-black text-[10px] uppercase tracking-widest border border-slate-100 hover:bg-slate-50 transition-all whitespace-nowrap">Settings</a>
    </div>

    <!-- Stat Cards -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-6">
        <div class="bg-white p-8 rounded-[2rem] shadow-sm border border-slate-100 group hover:shadow-xl hover:shadow-blue-50 transition-all duration-500">
            <div class="w-12 h-12 bg-blue-50 text-blue-600 rounded-2xl flex items-center justify-center text-xl mb-4 group-hover:scale-110 transition-all"><i class="bi bi-people-fill"></i></div>
            <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Total Staff</p>
            <h3 class="text-4xl font-black text-slate-800 tracking-tight"><?= $total_users ?></h3>
        </div>
        <div class="bg-white p-8 rounded-[2rem] shadow-sm border border-slate-100 group hover:shadow-xl hover:shadow-emerald-50 transition-all duration-500">
            <div class="w-12 h-12 bg-emerald-50 text-emerald-600 rounded-2xl flex items-center justify-center text-xl mb-4 group-hover:scale-110 transition-all"><i class="bi bi-check-circle-fill"></i></div>
            <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Today Checked-in</p>
            <h3 class="text-4xl font-black text-emerald-600 tracking-tight"><?= $checkedin_today ?></h3>
        </div>
        <div class="bg-white p-8 rounded-[2rem] shadow-sm border border-slate-100 group hover:shadow-xl hover:shadow-amber-50 transition-all duration-500">
            <div class="w-12 h-12 bg-amber-50 text-amber-600 rounded-2xl flex items-center justify-center text-xl mb-4 group-hover:scale-110 transition-all"><i class="bi bi-clock-history"></i></div>
            <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Today Late</p>
            <h3 class="text-4xl font-black text-amber-600 tracking-tight"><?= $late_today ?></h3>
        </div>
        <div class="bg-white p-8 rounded-[2rem] shadow-sm border border-slate-100 group hover:shadow-xl hover:shadow-rose-50 transition-all duration-500">
            <div class="w-12 h-12 bg-rose-50 text-rose-600 rounded-2xl flex items-center justify-center text-xl mb-4 group-hover:scale-110 transition-all"><i class="bi bi-x-circle-fill"></i></div>
            <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Pending Check-in</p>
            <h3 class="text-4xl font-black text-rose-600 tracking-tight"><?= $not_yet ?></h3>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Donut Chart -->
        <div class="bg-white p-10 rounded-[32px] shadow-sm border border-slate-100 flex flex-col">
            <h3 class="font-black text-slate-800 mb-8 flex items-center gap-3"><i class="bi bi-pie-chart-fill text-blue-600"></i> Daily Status</h3>
            <div class="flex-1 flex items-center justify-center relative min-h-[300px]">
                <canvas id="donutChart"></canvas>
            </div>
        </div>

        <!-- Table Today -->
        <div class="lg:col-span-2 bg-white rounded-[32px] shadow-sm border border-slate-100 overflow-hidden">
            <div class="px-10 py-8 border-b border-slate-50 flex items-center justify-between bg-slate-50/30">
                <h3 class="font-black text-slate-800 flex items-center gap-3"><i class="bi bi-list-check text-blue-600"></i> รายการลงเวลาวันนี้</h3>
                <span class="text-[10px] font-black text-slate-400 uppercase tracking-widest font-bold">Real-time attendance logs</span>
            </div>
            
            <?php if (empty($today_logs)): ?>
                <div class="flex flex-col items-center justify-center py-20 text-slate-300">
                    <i class="bi bi-calendar2-x text-6xl mb-4"></i>
                    <p class="font-black italic uppercase tracking-widest text-xs">No attendance records today</p>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-slate-50/50 text-[10px] font-black text-slate-400 uppercase tracking-[0.15em] border-b border-slate-100">
                            <tr>
                                <th class="px-10 py-5 text-left">Staff Name</th>
                                <th class="px-6 py-5 text-left">Position</th>
                                <th class="px-6 py-5 text-left">Check-in</th>
                                <th class="px-6 py-5 text-left">Check-out</th>
                                <th class="px-6 py-5 text-right">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-50 text-slate-600">
                            <?php foreach ($today_logs as $r): ?>
                                <tr class="hover:bg-slate-50/50 transition-all">
                                    <td class="px-10 py-5">
                                        <div class="font-bold text-slate-800"><?= htmlspecialchars($r['firstname'].' '.$r['lastname']) ?></div>
                                        <div class="text-[9px] font-black text-slate-400 uppercase tracking-widest"><?= htmlspecialchars($r['dept_name'] ?? 'General') ?></div>
                                    </td>
                                    <td class="px-6 py-5">
                                        <span class="text-xs font-bold text-slate-500"><?= htmlspecialchars($r['position'] ?? '-') ?></span>
                                    </td>
                                    <td class="px-6 py-5">
                                        <span class="font-bold text-emerald-600"><?= $r['check_in_time'] ?></span>
                                    </td>
                                    <td class="px-6 py-5">
                                        <?= $r['check_out_time'] ? '<span class="font-bold text-rose-500">'.$r['check_out_time'].'</span>' : '<span class="text-slate-300 italic text-[10px]">--:--</span>' ?>
                                    </td>
                                    <td class="px-6 py-5 text-right">
                                        <?php if ($r['check_in_status'] === 'มาสาย'): ?>
                                            <span class="px-3 py-1 rounded-lg bg-amber-50 text-amber-600 font-black text-[9px] uppercase tracking-wider">LATE</span>
                                        <?php else: ?>
                                            <span class="px-3 py-1 rounded-lg bg-emerald-50 text-emerald-600 font-black text-[9px] uppercase tracking-wider">ON TIME</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const ctx = document.getElementById('donutChart').getContext('2d');
        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['ลงเวลาแล้ว', 'มาสาย', 'ยังไม่ลงเวลา'],
                datasets: [{
                    data: [<?= $checkedin_today - $late_today ?>, <?= $late_today ?>, <?= max(0,$not_yet) ?>],
                    backgroundColor: ['#10b981', '#f59e0b', '#f43f5e'],
                    borderWidth: 0,
                    hoverOffset: 15
                }]
            },
            options: {
                plugins: { 
                    legend: { 
                        position: 'bottom', 
                        labels: { 
                            usePointStyle: true,
                            pointStyle: 'circle',
                            font: { family: 'Prompt', weight: 'bold', size: 10 } 
                        } 
                    } 
                },
                cutout: '75%', 
                responsive: true, 
                maintainAspectRatio: false
            }
        });
    });
</script>

<?php require_once '../components/layout_end.php'; ?>
