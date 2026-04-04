<?php
require_once 'functions.php';
checkLogin();

$teacher_id = $_SESSION['teacher_id'];
$pageTitle = 'แดชบอร์ด';
$pageSubtitle = 'ภาพรวมข้อมูลการเช็คชื่อ';

// รับค่าช่วงวันที่
$start_date = $_GET['start_date'] ?? date('Y-m-d');
$end_date = $_GET['end_date'] ?? date('Y-m-d');

// teacher_id=0 หมายถึง Super Admin
$whereClause = (int)$teacher_id === 0 ? "WHERE 1=1" : "WHERE a.teacher_id = :teacher_id";

// ─── 1. ข้อมูลสรุป ──────────────────────────────────────────────────
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

// ─── 2. ข้อมูลกราฟรายคาบ ───────────────────────────────────────────
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

// ─── 3. กิจกรรมล่าสุด (5 รายการล่าสุด) ────────────────────────────────
$recentQuery = "
    SELECT a.*, s.name as student_name, sub.subject_name 
    FROM att_attendance a
    JOIN att_students s ON s.id = a.student_id
    JOIN att_subjects sub ON sub.id = a.subject_id
    $whereClause
    ORDER BY a.created_at DESC LIMIT 5
";
$recentStmt = $pdo->prepare($recentQuery);
if ((int)$teacher_id === 0) { $recentStmt->execute(); } 
else { $recentStmt->execute(['teacher_id' => $teacher_id]); }
$recentLogs = $recentStmt->fetchAll();

require_once '../components/layout_start.php';
?>

<div class="flex flex-col gap-8">

    <!-- ── Filter & Quick Actions ── -->
    <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center gap-4 no-print">
        <form method="GET" class="bg-white p-2 pl-4 rounded-2xl shadow-sm border border-slate-200 flex flex-wrap items-center gap-4">
            <div class="flex items-center gap-2">
                <i class="bi bi-calendar3 text-blue-600"></i>
                <input type="date" name="start_date" value="<?= htmlspecialchars($start_date) ?>" class="text-sm font-medium outline-none bg-transparent">
                <span class="text-gray-300">—</span>
                <input type="date" name="end_date" value="<?= htmlspecialchars($end_date) ?>" class="text-sm font-medium outline-none bg-transparent">
            </div>
            <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-xl text-sm font-semibold hover:bg-blue-700 transition shadow-md shadow-blue-100">
                กรองข้อมูล
            </button>
        </form>

        <div class="flex gap-2">
            <a href="attendance.php" class="bg-white text-blue-600 border border-blue-100 px-4 py-2.5 rounded-xl text-sm font-bold hover:bg-blue-50 transition shadow-sm">
                <i class="bi bi-plus-lg mr-2"></i>เช็คชื่อใหม่
            </a>
            <button onclick="window.print()" class="bg-slate-800 text-white px-4 py-2.5 rounded-xl text-sm font-bold hover:bg-slate-900 transition shadow-sm">
                <i class="bi bi-printer mr-2"></i>พิมพ์รายงาน
            </button>
        </div>
    </div>

    <!-- ── KPI Cards ── -->
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-4 sm:gap-6 pt-2">
        <?php
        $kpis = [
            ['label' => 'มาเรียน', 'val' => $summary['มา'], 'color' => 'emerald', 'icon' => 'check-circle-fill'],
            ['label' => 'ขาดเรียน', 'val' => $summary['ขาด'], 'color' => 'rose', 'icon' => 'x-circle-fill'],
            ['label' => 'ลา', 'val' => $summary['ลา'], 'color' => 'amber', 'icon' => 'info-circle-fill'],
            ['label' => 'โดดเรียน', 'val' => $summary['โดด'], 'color' => 'violet', 'icon' => 'person-x-fill'],
            ['label' => 'สาย', 'val' => $summary['สาย'], 'color' => 'orange', 'icon' => 'clock-fill'],
        ];
        foreach ($kpis as $k):
            $c = $k['color'];
        ?>
        <div class="bg-white rounded-3xl p-5 shadow-sm border border-slate-100 flex flex-col gap-3 group hover:shadow-md transition-all">
            <div class="flex items-center justify-between">
                <div class="w-10 h-10 rounded-2xl bg-<?= $c ?>-50 text-<?= $c ?>-600 flex items-center justify-center text-xl group-hover:scale-110 transition-transform">
                    <i class="bi bi-<?= $k['icon'] ?>"></i>
                </div>
                <span class="text-xs font-bold text-<?= $c ?>-600 bg-<?= $c ?>-50 px-2 py-0.5 rounded-lg"><?= $k['label'] ?></span>
            </div>
            <div>
                <p class="text-3xl font-black text-slate-800"><?= number_format($k['val']) ?></p>
                <p class="text-[10px] text-slate-400 font-bold uppercase tracking-wider mt-1">ครั้งสะสม</p>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 items-start">
        <!-- ── Chart ── -->
        <div class="lg:col-span-2 bg-white rounded-3xl p-6 sm:p-8 shadow-sm border border-slate-100 flex flex-col gap-6">
            <div class="flex items-center justify-between">
                <h3 class="font-bold text-slate-800 flex items-center gap-2">
                    <i class="bi bi-graph-up text-blue-600"></i> สถิติการเข้าเรียนแยกตามคาบ
                </h3>
            </div>
            <div class="relative h-[320px] w-full">
                <canvas id="attendanceChart"></canvas>
            </div>
        </div>

        <!-- ── Recent Activities ── -->
        <div class="bg-white rounded-3xl p-6 sm:p-8 shadow-sm border border-slate-100 flex flex-col gap-6 h-full">
            <div class="flex items-center justify-between">
                <h3 class="font-bold text-slate-800 flex items-center gap-2">
                    <i class="bi bi-clock-history text-indigo-600"></i> กิจกรรมล่าสุด
                </h3>
                <a href="report_subject.php" class="text-xs font-bold text-blue-600 hover:underline">ดูทั้งหมด</a>
            </div>
            
            <div class="flex flex-col gap-4">
                <?php if (empty($recentLogs)): ?>
                    <div class="py-12 text-center text-slate-400">
                        <i class="bi bi-inbox text-4xl opacity-20 block mb-2"></i>
                        <p class="text-xs font-medium">ไม่มีข้อมูลล่าสุด</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($recentLogs as $log): 
                        $statusColors = [
                            'มา' => 'emerald', 'ขาด' => 'rose', 'ลา' => 'amber', 'โดด' => 'violet', 'สาย' => 'orange'
                        ];
                        $sc = $statusColors[$log['status']] ?? 'slate';
                    ?>
                    <div class="flex gap-4 items-start p-3 rounded-2xl hover:bg-slate-50 transition border border-transparent hover:border-slate-100">
                        <div class="w-10 h-10 rounded-xl bg-<?= $sc ?>-100 text-<?= $sc ?>-700 flex items-center justify-center flex-shrink-0 font-bold text-sm">
                            <?= $log['period'] ?>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-bold text-slate-800 truncate"><?= htmlspecialchars($log['student_name']) ?></p>
                            <p class="text-[10px] text-slate-400 font-medium truncate italic"><?= htmlspecialchars($log['subject_name']) ?></p>
                        </div>
                        <div class="text-right">
                            <span class="inline-block px-2 py-0.5 rounded-lg bg-<?= $sc ?>-50 text-<?= $sc ?>-700 text-[10px] font-bold"><?= $log['status'] ?></span>
                            <p class="text-[9px] text-slate-300 mt-1"><?= date('H:i', strtotime($log['created_at'])) ?></p>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <div class="mt-auto pt-4">
                <div class="bg-blue-50 rounded-2xl p-4 flex items-center justify-between border border-blue-100">
                    <div>
                        <p class="text-[10px] text-blue-400 font-bold uppercase tracking-wider">ภาพรวมมาเรียน</p>
                        <?php 
                            $total = array_sum($summary);
                            $rate = $total > 0 ? round(($summary['มา'] / $total) * 100, 1) : 0;
                        ?>
                        <p class="text-xl font-black text-blue-700"><?= $rate ?>%</p>
                    </div>
                    <div class="w-12 h-12 rounded-full border-[3px] border-blue-200 border-t-blue-600 flex items-center justify-center">
                        <span class="text-[9px] font-bold text-blue-600"><?= ceil($rate) ?>%</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    const ctx = document.getElementById('attendanceChart').getContext('2d');
    const attendanceChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: ['คาบ 1', 'คาบ 2', 'คาบ 3', 'คาบ 4', 'คาบ 5', 'คาบ 6', 'คาบ 7', 'คาบ 8'],
            datasets: [
                { label: 'มา', data: <?= json_encode(array_values($datasets['มา'])) ?>, backgroundColor: '#10b981', borderRadius: 6 },
                { label: 'สาย', data: <?= json_encode(array_values($datasets['สาย'])) ?>, backgroundColor: '#f97316', borderRadius: 6 },
                { label: 'ลา', data: <?= json_encode(array_values($datasets['ลา'])) ?>, backgroundColor: '#f59e0b', borderRadius: 6 },
                { label: 'โดด', data: <?= json_encode(array_values($datasets['โดด'])) ?>, backgroundColor: '#8b5cf6', borderRadius: 6 },
                { label: 'ขาด', data: <?= json_encode(array_values($datasets['ขาด'])) ?>, backgroundColor: '#ef4444', borderRadius: 6 }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                x: { stacked: true, grid: { display: false } },
                y: { stacked: true, beginAtZero: true, ticks: { precision: 0 }, grid: { borderDash: [5, 5] } }
            },
            plugins: {
                legend: { position: 'bottom', labels: { usePointStyle: true, padding: 20, font: { size: 12, weight: 'bold' } } },
                tooltip: { backgroundColor: '#1e293b', padding: 12, cornerRadius: 10, titleFont: { size: 14 } }
            }
        }
    });
</script>

<?php require_once '../components/layout_end.php'; ?>
