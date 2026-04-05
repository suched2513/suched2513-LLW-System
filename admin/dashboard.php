<?php
/**
 * admin/dashboard.php — Enhanced WFH Admin Dashboard with Premium Aesthetics
 */
session_start();
require_once '../config.php';

// Auth: super_admin or WFH admin
if (!isset($_SESSION['llw_role']) || !in_array($_SESSION['llw_role'], ['super_admin', 'wfh_admin'])) {
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
    SELECT u.firstname, u.lastname, u.pos_id, d.dept_name, t.check_in_time, t.check_out_time, t.check_in_status
    FROM wfh_timelogs t
    JOIN wfh_users u ON t.user_id = u.user_id
    LEFT JOIN wfh_departments d ON u.dept_id = d.dept_id
    WHERE t.log_date = '$today'
    ORDER BY t.check_in_time ASC
");
$today_logs = $stmt->fetch_all(MYSQLI_ASSOC);

require_once '../components/layout_start.php';
?>

<div class="flex flex-col gap-10">
    
    <!-- Premium Navigation Tabs -->
    <div class="flex gap-4 overflow-x-auto no-scrollbar scroll-smooth">
        <a href="dashboard.php" class="px-8 py-3.5 bg-blue-600 text-white rounded-2xl font-black text-[11px] uppercase tracking-[0.2em] shadow-2xl shadow-blue-100 whitespace-nowrap transition-all border border-blue-600">Overview</a>
        <a href="manage_users.php" class="px-8 py-3.5 bg-white text-slate-500 rounded-2xl font-black text-[11px] uppercase tracking-[0.2em] border border-slate-200/60 hover:bg-slate-50 transition-all whitespace-nowrap">Manage Users</a>
        <a href="reports.php" class="px-8 py-3.5 bg-white text-slate-500 rounded-2xl font-black text-[11px] uppercase tracking-[0.2em] border border-slate-200/60 hover:bg-slate-50 transition-all whitespace-nowrap">Annual Reports</a>
        <a href="settings.php" class="px-8 py-3.5 bg-white text-slate-500 rounded-2xl font-black text-[11px] uppercase tracking-[0.2em] border border-slate-200/60 hover:bg-slate-50 transition-all whitespace-nowrap">System Settings</a>
    </div>

    <!-- Vibrant Stat Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-8">
        <!-- Card 1: Total Staff -->
        <div class="relative group bg-gradient-to-br from-indigo-600 to-indigo-700 p-8 rounded-[40px] shadow-2xl shadow-indigo-100 overflow-hidden transition-all hover:-translate-y-2">
            <div class="absolute -right-6 -bottom-6 text-white/10 text-9xl transform rotate-12 group-hover:scale-110 transition-all">
                <i class="bi bi-people"></i>
            </div>
            <div class="flex items-center gap-4 mb-6">
                <div class="w-12 h-12 bg-white/20 rounded-2xl flex items-center justify-center text-white text-2xl backdrop-blur-sm">
                    <i class="bi bi-people-fill"></i>
                </div>
                <span class="text-[10px] font-black text-indigo-100 uppercase tracking-widest">Total Staff</span>
            </div>
            <h3 class="text-5xl font-black text-white tracking-tight"><?= number_format($total_users) ?></h3>
            <p class="text-[10px] text-indigo-100 font-bold mt-4 uppercase tracking-[0.2em]">Active personnel</p>
        </div>

        <!-- Card 2: Checked-in -->
        <div class="relative group bg-gradient-to-br from-emerald-500 to-emerald-600 p-8 rounded-[40px] shadow-2xl shadow-emerald-100 overflow-hidden transition-all hover:-translate-y-2">
            <div class="absolute -right-6 -bottom-6 text-white/10 text-9xl transform -rotate-12 group-hover:scale-110 transition-all">
                <i class="bi bi-check-circle"></i>
            </div>
            <div class="flex items-center gap-4 mb-6">
                <div class="w-12 h-12 bg-white/20 rounded-2xl flex items-center justify-center text-white text-2xl backdrop-blur-sm">
                    <i class="bi bi-person-check-fill"></i>
                </div>
                <span class="text-[10px] font-black text-emerald-50 uppercase tracking-widest">Checked-in</span>
            </div>
            <h3 class="text-5xl font-black text-white tracking-tight"><?= number_format($checkedin_today) ?></h3>
            <p class="text-[10px] text-emerald-50 font-bold mt-4 uppercase tracking-[0.2em]">Presence today</p>
        </div>

        <!-- Card 3: Late -->
        <div class="relative group bg-gradient-to-br from-amber-500 to-orange-600 p-8 rounded-[40px] shadow-2xl shadow-orange-100 overflow-hidden transition-all hover:-translate-y-2">
            <div class="absolute -right-6 -bottom-6 text-white/10 text-9xl group-hover:scale-110 transition-all">
                <i class="bi bi-clock-history"></i>
            </div>
            <div class="flex items-center gap-4 mb-6">
                <div class="w-12 h-12 bg-white/20 rounded-2xl flex items-center justify-center text-white text-2xl backdrop-blur-sm">
                    <i class="bi bi-hourglass-split"></i>
                </div>
                <span class="text-[10px] font-black text-orange-50 uppercase tracking-widest">Today Late</span>
            </div>
            <h3 class="text-5xl font-black text-white tracking-tight"><?= number_format($late_today) ?></h3>
            <p class="text-[10px] text-orange-50 font-bold mt-4 uppercase tracking-[0.2em]">Efficiency alert</p>
        </div>

        <!-- Card 4: Pending -->
        <div class="relative group bg-gradient-to-br from-rose-500 to-rose-600 p-8 rounded-[40px] shadow-2xl shadow-rose-100 overflow-hidden transition-all hover:-translate-y-2">
            <div class="absolute -right-6 -bottom-6 text-white/10 text-9xl transform rotate-45 group-hover:scale-110 transition-all">
                <i class="bi bi-shield-x"></i>
            </div>
            <div class="flex items-center gap-4 mb-6">
                <div class="w-12 h-12 bg-white/20 rounded-2xl flex items-center justify-center text-white text-2xl backdrop-blur-sm">
                    <i class="bi bi-dash-circle-fill"></i>
                </div>
                <span class="text-[10px] font-black text-rose-50 uppercase tracking-widest">Pending</span>
            </div>
            <h3 class="text-5xl font-black text-white tracking-tight"><?= number_format($not_yet) ?></h3>
            <p class="text-[10px] text-rose-50 font-bold mt-4 uppercase tracking-[0.2em]">Awaiting logs</p>
        </div>
    </div>

    <!-- Data Visualization Row -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-10 overflow-visible">
        <!-- Status Analytics -->
        <div class="bg-white p-10 rounded-[48px] shadow-sm border border-slate-200/60 flex flex-col items-center">
            <div class="flex justify-between items-center w-full mb-10">
                <h3 class="font-black text-slate-800 flex items-center gap-3 text-lg"><i class="bi bi-pie-chart-fill text-blue-600"></i> วิเคราะห์สถานะรายวัน</h3>
            </div>
            <div class="flex-1 w-full relative min-h-[320px] flex items-center justify-center">
                <canvas id="donutChart"></canvas>
                <div class="absolute flex flex-col items-center">
                    <span class="text-4xl font-black text-slate-800"><?= $total_users > 0 ? round(($checkedin_today/$total_users)*100) : 0 ?>%</span>
                    <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest">Presence</p>
                </div>
            </div>
        </div>

        <!-- Real-time Activity Table -->
        <div class="lg:col-span-2 bg-white rounded-[48px] shadow-sm border border-slate-200/60 overflow-hidden">
            <div class="px-10 py-9 border-b border-slate-50 flex items-center justify-between bg-slate-50/20">
                <div class="flex flex-col">
                    <h3 class="font-black text-slate-800 flex items-center gap-3 text-lg"><i class="bi bi-list-stars text-blue-600"></i> รายการปฏิบัติงานวันนี้</h3>
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mt-1">Live tracking personnel attendance logs</p>
                </div>
                <button class="bg-blue-600 text-white p-3 rounded-2xl shadow-xl shadow-blue-100 hover:scale-105 transition-all">
                    <i class="bi bi-arrow-clockwise"></i>
                </button>
            </div>
            
            <div class="overflow-x-auto">
                <?php if (empty($today_logs)): ?>
                    <div class="flex flex-col items-center justify-center py-24 text-slate-300">
                        <i class="bi bi-calendar2-x text-7xl mb-6 opacity-30"></i>
                        <p class="font-black italic uppercase tracking-[0.2em] text-[10px]">No records detected for today</p>
                    </div>
                <?php else: ?>
                    <table class="w-full">
                        <thead class="bg-slate-50/30 text-[10px] font-black text-slate-400 uppercase tracking-[0.15em]">
                            <tr>
                                <th class="px-10 py-6 text-left">Staff Member</th>
                                <th class="px-6 py-6 text-left">Check-in</th>
                                <th class="px-6 py-6 text-left">Check-out</th>
                                <th class="px-10 py-6 text-right">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-50/50 text-slate-600">
                            <?php foreach ($today_logs as $r): ?>
                                <tr class="hover:bg-slate-50/30 transition-all group">
                                    <td class="px-10 py-6">
                                        <div class="flex items-center gap-4">
                                            <div class="w-10 h-10 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center font-black text-sm group-hover:scale-110 transition-all">
                                                <?= mb_substr($r['firstname'], 0, 1) ?>
                                            </div>
                                            <div class="flex flex-col">
                                                <div class="font-black text-slate-800 text-[13px]"><?= htmlspecialchars($r['firstname'].' '.$r['lastname']) ?></div>
                                                <div class="text-[9px] font-bold text-slate-400 uppercase tracking-widest"><?= htmlspecialchars($r['dept_name'] ?? 'General') ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-6">
                                        <div class="flex items-center gap-2">
                                            <i class="bi bi-clock text-emerald-500"></i>
                                            <span class="font-black text-slate-700 text-sm"><?= $r['check_in_time'] ?></span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-6">
                                        <?php if ($r['check_out_time']): ?>
                                            <div class="flex items-center gap-2">
                                                <i class="bi bi-clock-history text-rose-500"></i>
                                                <span class="font-black text-slate-700 text-sm"><?= $r['check_out_time'] ?></span>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-slate-200 italic font-bold">Waiting...</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-10 py-6 text-right">
                                        <?php if ($r['check_in_status'] === 'มาสาย'): ?>
                                            <span class="px-4 py-1.5 rounded-xl bg-rose-50 text-rose-600 font-black text-[9px] uppercase tracking-widest border border-rose-100">LATE</span>
                                        <?php else: ?>
                                            <span class="px-4 py-1.5 rounded-xl bg-emerald-50 text-emerald-600 font-black text-[9px] uppercase tracking-widest border border-emerald-100">ON TIME</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const ctx = document.getElementById('donutChart').getContext('2d');
        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['ON TIME', 'LATE', 'ABSENT'],
                datasets: [{
                    data: [<?= $checkedin_today - $late_today ?>, <?= $late_today ?>, <?= max(0,$not_yet) ?>],
                    backgroundColor: ['#10b981', '#f59e0b', '#f43f5e'],
                    borderWidth: 0,
                    hoverOffset: 25,
                    weight: 2
                }]
            },
            options: {
                plugins: { 
                    legend: { 
                        position: 'bottom', 
                        labels: { 
                            usePointStyle: true,
                            pointStyle: 'rectRounded',
                            padding: 25,
                            font: { family: 'Prompt', weight: '900', size: 10 } 
                        } 
                    },
                    tooltip: {
                        backgroundColor: '#111827',
                        padding: 12,
                        cornerRadius: 15,
                        bodyFont: { family: 'Prompt', weight: 'bold' }
                    }
                },
                cutout: '82%', 
                responsive: true, 
                maintainAspectRatio: false
            }
        });
    });
</script>

<?php require_once '../components/layout_end.php'; ?>
