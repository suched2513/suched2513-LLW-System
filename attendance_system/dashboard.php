<?php
/**
 * attendance_system/dashboard.php — Enhanced Academic Dashboard with Premium Aesthetics
 */
require_once 'functions.php';
checkLogin();

$teacher_id = $_SESSION['teacher_id'];
$pageTitle = 'แดชบอร์ดการเช็คชื่อ';
$pageSubtitle = 'ภาพรวมข้อมูลวิชาเรียนรายวัน';
$activeSystem = 'attendance';

// Receive Date Filter
$start_date = $_GET['start_date'] ?? date('Y-m-d');
$end_date = $_GET['end_date'] ?? date('Y-m-d');

// teacher_id=0 implies Super Admin access
$whereClause = (int)$teacher_id === 0 ? "WHERE 1=1" : "WHERE a.teacher_id = :teacher_id";

// ── 1. Summary Statistics ──
$statsQuery = "SELECT a.status, COUNT(*) as total FROM att_attendance a $whereClause AND (a.date BETWEEN :start_date AND :end_date) GROUP BY a.status";
$statsStmt = $pdo->prepare($statsQuery);
if ((int)$teacher_id === 0) {
    $statsStmt->execute(['start_date' => $start_date, 'end_date' => $end_date]);
} else {
    $statsStmt->execute(['teacher_id' => $teacher_id, 'start_date' => $start_date, 'end_date' => $end_date]);
}
$statsData = $statsStmt->fetchAll();

$summary = ['มา' => 0, 'ขาด' => 0, 'ลา' => 0, 'โดด' => 0, 'สาย' => 0];
foreach($statsData as $row) { if(isset($summary[$row['status']])) $summary[$row['status']] = $row['total']; }

// ── 2. Graph Data (Per Period) ──
$chartQuery = "SELECT a.period, a.status, COUNT(*) as count FROM att_attendance a $whereClause AND (a.date BETWEEN :start_date AND :end_date) GROUP BY a.period, a.status";
$chartStmt = $pdo->prepare($chartQuery);
if ((int)$teacher_id === 0) {
    $chartStmt->execute(['start_date' => $start_date, 'end_date' => $end_date]);
} else {
    $chartStmt->execute(['teacher_id' => $teacher_id, 'start_date' => $start_date, 'end_date' => $end_date]);
}
$chartData = $chartStmt->fetchAll();

$datasets = ['มา' => array_fill(1, 8, 0), 'ขาด' => array_fill(1, 8, 0), 'ลา' => array_fill(1, 8, 0), 'โดด' => array_fill(1, 8, 0), 'สาย' => array_fill(1, 8, 0)];
foreach($chartData as $row) {
    $p = $row['period']; $s = $row['status'];
    if($p >= 1 && $p <= 8 && isset($datasets[$s])) $datasets[$s][$p] = $row['count'];
}

// ── 3. Recent Activity ──
$recentQuery = "
    SELECT a.*, s.name as student_name, sub.subject_name 
    FROM att_attendance a
    JOIN att_students s ON s.id = a.student_id
    JOIN att_subjects sub ON sub.id = a.subject_id
    $whereClause
    ORDER BY a.created_at DESC LIMIT 6
";
$recentStmt = $pdo->prepare($recentQuery);
if ((int)$teacher_id === 0) { $recentStmt->execute(); } 
else { $recentStmt->execute(['teacher_id' => $teacher_id]); }
$recentLogs = $recentStmt->fetchAll();

// ── 4. Smart Monitoring Data ──
$riskStudents = getStudentsAtRisk($teacher_id, $pdo, 5);
$highlightStudents = getStudentHighlights($teacher_id, $pdo, 5);

require_once '../components/layout_start.php';
?>

<div class="flex flex-col gap-10">

    <!-- Premium Filters -->
    <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center gap-6 no-print">
        <form method="GET" class="bg-white p-3 pl-6 rounded-[28px] shadow-sm border border-slate-200/60 flex flex-wrap items-center gap-5">
            <div class="flex items-center gap-4">
                <i class="bi bi-calendar-range-fill text-indigo-500"></i>
                <input type="date" name="start_date" value="<?= htmlspecialchars($start_date) ?>" class="text-sm font-black outline-none bg-transparent text-slate-700">
                <span class="text-slate-300 font-bold">~</span>
                <input type="date" name="end_date" value="<?= htmlspecialchars($end_date) ?>" class="text-sm font-black outline-none bg-transparent text-slate-700">
            </div>
            <button type="submit" class="bg-indigo-600 text-white px-6 py-2.5 rounded-2xl text-xs font-black uppercase tracking-widest hover:bg-indigo-700 transition-all shadow-xl shadow-indigo-100">
                Filter Data
            </button>
        </form>

        <div class="flex gap-3">
            <a href="attendance.php" class="bg-blue-600 text-white px-6 py-3.5 rounded-2xl text-xs font-black uppercase tracking-widest hover:bg-blue-700 transition shadow-xl shadow-blue-100">
                <i class="bi bi-plus-circle-fill mr-2"></i>New Check-in
            </a>
            <button onclick="window.print()" class="bg-slate-800 text-white px-6 py-3.5 rounded-2xl text-xs font-black uppercase tracking-widest hover:bg-slate-900 transition shadow-xl shadow-slate-100">
                <i class="bi bi-printer-fill mr-2"></i>Print Reports
            </button>
        </div>
    </div>

    <!-- Vibrant Academic KPI Cards -->
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-8 pt-4">
        <?php
        $kpis = [
            ['label' => 'มาเรียน', 'val' => $summary['มา'], 'from' => 'emerald-500', 'to' => 'emerald-600', 'icon' => 'check-circle'],
            ['label' => 'ขาดเรียน', 'val' => $summary['ขาด'], 'from' => 'rose-500', 'to' => 'rose-600', 'icon' => 'x-circle'],
            ['label' => 'ลา', 'val' => $summary['ลา'], 'from' => 'amber-500', 'to' => 'amber-600', 'icon' => 'patch-question'],
            ['label' => 'โดดเรียน', 'val' => $summary['โดด'], 'from' => 'indigo-500', 'to' => 'indigo-600', 'icon' => 'dash-circle'],
            ['label' => 'สาย', 'val' => $summary['สาย'], 'from' => 'orange-500', 'to' => 'orange-600', 'icon' => 'clock-history'],
        ];
        foreach ($kpis as $k):
        ?>
        <div class="bg-gradient-to-br from-<?= $k['from'] ?> to-<?= $k['to'] ?> rounded-[32px] p-6 shadow-2xl shadow-<?= $k['from'] ?>/20 flex flex-col gap-6 relative overflow-hidden group hover:-translate-y-2 transition-all duration-500">
            <div class="absolute -right-4 -bottom-4 text-white/10 text-7xl group-hover:scale-110 transition-transform">
                <i class="bi bi-<?= $k['icon'] ?>"></i>
            </div>
            <div class="flex items-center justify-between">
                <div class="w-10 h-10 rounded-xl bg-white/20 text-white flex items-center justify-center text-xl backdrop-blur-sm">
                    <i class="bi bi-<?= $k['icon'] ?>"></i>
                </div>
                <span class="text-[10px] font-black text-white/80 uppercase tracking-widest"><?= $k['label'] ?></span>
            </div>
            <div>
                <p class="text-4xl font-black text-white leading-none"><?= number_format($k['val']) ?></p>
                <p class="text-[9px] text-white/60 font-black uppercase tracking-[0.2em] mt-3 italic">Total Count</p>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Smart Monitoring Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-10">
        <!-- At-Risk Monitoring (Early Warning) -->
        <div class="bg-white/70 backdrop-blur-xl rounded-[48px] p-10 shadow-sm border border-rose-100/50 flex flex-col gap-8 relative overflow-hidden">
            <div class="absolute -right-10 -top-10 w-40 h-40 bg-rose-50 rounded-full blur-3xl opacity-50"></div>
            <div class="flex items-center justify-between relative z-10">
                <div class="flex flex-col">
                    <h3 class="font-black text-slate-800 flex items-center gap-3 text-lg">
                        <i class="bi bi-exclamation-triangle-fill text-rose-500"></i> ติดตามเกณฑ์เสี่ยง (มส.)
                    </h3>
                    <p class="text-[10px] font-bold text-rose-400 uppercase tracking-widest mt-1">Students below 80% attendance rate</p>
                </div>
                <div class="bg-rose-50 text-rose-600 px-4 py-1.5 rounded-xl text-[9px] font-black uppercase tracking-wider border border-rose-100">Warning</div>
            </div>

            <div class="flex flex-col gap-4 relative z-10">
                <?php if (empty($riskStudents)): ?>
                    <div class="py-12 bg-slate-50/50 rounded-3xl text-center border-2 border-dashed border-slate-100">
                        <i class="bi bi-shield-check text-4xl text-emerald-400 opacity-50"></i>
                        <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mt-3">No critical risks detected</p>
                    </div>
                <?php else: ?>
                    <?php foreach($riskStudents as $student): ?>
                    <div class="flex items-center justify-between p-5 bg-white rounded-[28px] border border-slate-100 shadow-sm hover:shadow-md transition-all group cursor-pointer">
                        <div class="flex items-center gap-4">
                            <div class="w-11 h-11 rounded-2xl bg-rose-50 text-rose-500 flex items-center justify-center font-black group-hover:rotate-12 transition-all">
                                <?= mb_substr($student['name'], 0, 1) ?>
                            </div>
                            <div>
                                <p class="text-[13px] font-black text-slate-800"><?= htmlspecialchars($student['name']) ?></p>
                                <p class="text-[9px] text-slate-400 font-bold uppercase tracking-widest">ห้อง <?= $student['classroom'] ?></p>
                            </div>
                        </div>
                        <div class="text-right">
                            <p class="text-lg font-black text-rose-600"><?= $student['presence_rate'] ?><span class="text-[10px] ml-0.5">%</span></p>
                            <p class="text-[8px] font-bold text-slate-300 uppercase">Presence</p>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <a href="report.php" class="w-full py-4 rounded-2xl bg-slate-50 text-slate-400 text-center text-[10px] font-black uppercase tracking-widest hover:bg-slate-100 transition-all">View All Risk Analysis</a>
        </div>

        <!-- Productivity Highlights (Diligence) -->
        <div class="bg-white/70 backdrop-blur-xl rounded-[48px] p-10 shadow-sm border border-emerald-100/50 flex flex-col gap-8 relative overflow-hidden">
            <div class="absolute -right-10 -top-10 w-40 h-40 bg-emerald-50 rounded-full blur-3xl opacity-50"></div>
            <div class="flex items-center justify-between relative z-10">
                <div class="flex flex-col">
                    <h3 class="font-black text-slate-800 flex items-center gap-3 text-lg">
                        <i class="bi bi-trophy-fill text-emerald-500"></i> นักเรียนเช็คชื่อดีเด่น
                    </h3>
                    <p class="text-[10px] font-bold text-emerald-400 uppercase tracking-widest mt-1">Students with exemplary records</p>
                </div>
                <div class="bg-emerald-50 text-emerald-600 px-4 py-1.5 rounded-xl text-[9px] font-black uppercase tracking-wider border border-emerald-100">Top Star</div>
            </div>

            <div class="flex flex-col gap-4 relative z-10">
                <?php if (empty($highlightStudents)): ?>
                    <div class="py-12 bg-slate-50/50 rounded-3xl text-center border-2 border-dashed border-slate-100">
                        <i class="bi bi-hourglass-split text-4xl text-slate-300 opacity-50"></i>
                        <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mt-3">Analyzing performance data...</p>
                    </div>
                <?php else: ?>
                    <?php foreach($highlightStudents as $student): ?>
                    <div class="flex items-center justify-between p-5 bg-white rounded-[28px] border border-slate-100 shadow-sm hover:shadow-md transition-all group cursor-pointer">
                        <div class="flex items-center gap-4">
                            <div class="w-11 h-11 rounded-2xl bg-emerald-50 text-emerald-500 flex items-center justify-center font-black group-hover:scale-110 transition-all">
                                <?= mb_substr($student['name'], 0, 1) ?>
                            </div>
                            <div>
                                <p class="text-[13px] font-black text-slate-800"><?= htmlspecialchars($student['name']) ?></p>
                                <p class="text-[9px] text-slate-400 font-bold uppercase tracking-widest">ห้อง <?= $student['classroom'] ?></p>
                            </div>
                        </div>
                        <div class="text-right">
                            <p class="text-lg font-black text-emerald-600"><?= $student['presence_rate'] ?><span class="text-[10px] ml-0.5">%</span></p>
                            <p class="text-[8px] font-bold text-slate-300 uppercase">High Presence</p>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <a href="report.php" class="w-full py-4 rounded-2xl bg-slate-50 text-slate-400 text-center text-[10px] font-black uppercase tracking-widest hover:bg-slate-100 transition-all">View Full Performance</a>
        </div>
    </div>
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-10">
        <!-- Main Stats Chart -->
        <div class="lg:col-span-2 bg-white rounded-[48px] p-10 shadow-sm border border-slate-200/60 flex flex-col gap-10">
            <div class="flex items-center justify-between">
                <div class="flex flex-col">
                    <h3 class="font-black text-slate-800 flex items-center gap-3 text-lg">
                        <i class="bi bi-graph-up-arrow text-indigo-600"></i> สถิติการเข้าเรียนแยกตามคาบ
                    </h3>
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mt-1">Attendance records by period & status</p>
                </div>
            </div>
            <div class="relative h-[380px] w-full">
                <canvas id="attendanceChart"></canvas>
            </div>
        </div>

        <!-- Recent Activity Feed -->
        <div class="bg-white rounded-[48px] p-10 shadow-sm border border-slate-200/60 flex flex-col gap-10">
            <div class="flex items-center justify-between">
                <h3 class="font-black text-slate-800 flex items-center gap-3 text-lg">
                    <i class="bi bi-stars text-blue-600"></i> กิจกรรมล่าสุด
                </h3>
                <a href="report_subject.php" class="bg-slate-50 text-slate-400 p-2.5 rounded-xl hover:bg-slate-100 transition-all">
                    <i class="bi bi-arrow-right-short text-xl"></i>
                </a>
            </div>
            
            <div class="flex flex-col gap-6">
                <?php if (empty($recentLogs)): ?>
                    <div class="py-20 text-center text-slate-200 flex flex-col items-center gap-4">
                        <i class="bi bi-cloud-slash text-6xl opacity-30"></i>
                        <p class="text-[10px] font-black uppercase tracking-widest italic">No activity detected</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($recentLogs as $log): 
                        $statusMeta = [
                            'มา' => ['c' => 'emerald', 'i' => 'check-circle'],
                            'ขาด' => ['c' => 'rose', 'i' => 'x-circle'],
                            'ลา' => ['c' => 'amber', 'i' => 'chat-left-text'],
                            'โดด' => ['c' => 'indigo', 'i' => 'dash-circle'],
                            'สาย' => ['c' => 'orange', 'i' => 'clock']
                        ];
                        $meta = $statusMeta[$log['status']] ?? ['c' => 'slate', 'i' => 'dot'];
                        $sc = $meta['c'];
                    ?>
                    <div class="flex gap-5 items-center p-4 rounded-3xl hover:bg-slate-50/50 transition border border-transparent hover:border-slate-100 group">
                        <div class="w-12 h-12 rounded-2xl bg-<?= $sc ?>-50 text-<?= $sc ?>-600 flex flex-col items-center justify-center flex-shrink-0 group-hover:scale-110 transition-all">
                            <span class="text-[10px] font-black opacity-40 leading-none mb-1">P</span>
                            <span class="font-black text-lg leading-none"><?= $log['period'] ?></span>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-[13px] font-black text-slate-800 truncate"><?= htmlspecialchars($log['student_name']) ?></p>
                            <p class="text-[9px] text-slate-400 font-bold truncate tracking-widest uppercase mt-0.5"><?= htmlspecialchars($log['subject_name']) ?></p>
                        </div>
                        <div class="text-right">
                            <span class="inline-block px-3 py-1 rounded-xl bg-<?= $sc ?>-50 text-<?= $sc ?>-700 text-[9px] font-black uppercase tracking-widest border border-<?= $sc ?>-100/50"><?= $log['status'] ?></span>
                            <p class="text-[8px] font-bold text-slate-300 mt-2"><?= date('H:i', strtotime($log['created_at'])) ?></p>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <div class="mt-auto pt-6">
                <?php 
                    $total = array_sum($summary);
                    $rate = $total > 0 ? round(($summary['มา'] / $total) * 100, 1) : 0;
                ?>
                <div class="bg-gradient-to-br from-indigo-50 to-blue-50 rounded-[32px] p-6 border border-blue-100/50 relative overflow-hidden">
                    <div class="absolute -right-2 -bottom-2 text-blue-100 text-6xl opacity-50">
                        <i class="bi bi-graph-up-arrow"></i>
                    </div>
                    <div class="flex justify-between items-center relative z-10">
                        <div>
                            <p class="text-[10px] text-indigo-400 font-black uppercase tracking-widest mb-1">Global Presence Rate</p>
                            <h4 class="text-3xl font-black text-indigo-700 leading-none"><?= $rate ?><span class="text-sm ml-1">%</span></h4>
                        </div>
                        <div class="w-14 h-14 rounded-3xl bg-white shadow-xl shadow-indigo-100 flex items-center justify-center group-hover:rotate-12 transition-all">
                            <i class="bi bi-activity text-2xl text-indigo-600"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const ctx = document.getElementById('attendanceChart').getContext('2d');
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: ['P1', 'P2', 'P3', 'P4', 'P5', 'P6', 'P7', 'P8'],
                datasets: [
                    { label: 'มา', data: <?= json_encode(array_values($datasets['มา'])) ?>, backgroundColor: '#10b981', borderRadius: 8, barThickness: 12 },
                    { label: 'สาย', data: <?= json_encode(array_values($datasets['สาย'])) ?>, backgroundColor: '#f97316', borderRadius: 8, barThickness: 12 },
                    { label: 'ลา', data: <?= json_encode(array_values($datasets['ลา'])) ?>, backgroundColor: '#f59e0b', borderRadius: 8, barThickness: 12 },
                    { label: 'โดด', data: <?= json_encode(array_values($datasets['โดด'])) ?>, backgroundColor: '#8b5cf6', borderRadius: 8, barThickness: 12 },
                    { label: 'ขาด', data: <?= json_encode(array_values($datasets['ขาด'])) ?>, backgroundColor: '#ef4444', borderRadius: 8, barThickness: 12 }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                categoryPercentage: 0.8,
                barPercentage: 0.4,
                scales: {
                    x: { stacked: true, grid: { display: false }, ticks: { font: { family: 'Prompt', weight: 'bold', size: 10 } } },
                    y: { 
                        stacked: true, 
                        beginAtZero: true, 
                        ticks: { precision: 0, font: { weight: 'bold', size: 10 } }, 
                        grid: { color: '#f1f5f9', borderDash: [6, 6] } 
                    }
                },
                plugins: {
                    legend: { 
                        position: 'bottom', 
                        labels: { 
                            usePointStyle: true,
                            pointStyle: 'rectRounded',
                            padding: 25, 
                            font: { family: 'Prompt', weight: 'black', size: 10 } 
                        } 
                    },
                    tooltip: { 
                        backgroundColor: '#1e293b', 
                        padding: 16, 
                        cornerRadius: 16, 
                        titleFont: { family: 'Prompt', size: 13, weight: 'black' },
                        bodyFont: { family: 'Prompt', size: 12 }
                    }
                }
            }
        });
    });
</script>

<?php require_once '../components/layout_end.php'; ?>
