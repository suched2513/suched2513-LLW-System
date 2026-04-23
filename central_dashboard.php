<?php
/**
 * central_dashboard.php — The "Super Dashboard" for LLW Platinum
 */
session_start();
require_once 'config/database.php';

// Authentication Check
if (!isset($_SESSION['llw_role']) || $_SESSION['llw_role'] !== 'super_admin') {
    header('Location: login.php');
    exit();
}

// Safe multi-byte string functions
function llw_low($str) {
    return function_exists('mb_strtolower') ? mb_strtolower($str, 'UTF-8') : strtolower($str);
}
function llw_sub($str, $start, $len) {
    return function_exists('mb_substr') ? mb_substr($str, $start, $len, 'UTF-8') : substr($str, $start, $len);
}

$pdo = getPdo();

// --- Fetch Real Stats ---
$today = date('Y-m-d');
try {
    // 1. นักเรียนทั้งหมด
    $countStudents = $pdo->query("SELECT COUNT(*) FROM att_students")->fetchColumn() ?: 0;

    // 2. ใบลาที่รอดำเนินการ
    $stmtLeave = $pdo->prepare("SELECT COUNT(*) FROM tl_requests WHERE status = 'pending'");
    $stmtLeave->execute();
    $countPendingLeave = (int)$stmtLeave->fetchColumn();

    // 3. ห้องเรียนที่เช็คชื่อแล้ววันนี้
    $countTotalRooms = (int)$pdo->query("SELECT COUNT(DISTINCT classroom) FROM att_students")->fetchColumn();

    $stmtChecked = $pdo->prepare("
        SELECT COUNT(DISTINCT s.classroom)
        FROM att_attendance a
        JOIN att_students s ON s.id = a.student_id
        WHERE a.date = ?
    ");
    $stmtChecked->execute([$today]);
    $countCheckedRooms = (int)$stmtChecked->fetchColumn();
    $countRemainingRooms = $countTotalRooms - $countCheckedRooms;
    $roomProgress = $countTotalRooms > 0 ? round(($countCheckedRooms / $countTotalRooms) * 100) : 0;

    // 4. บุคลากรลาวันนี้
    $stmtOnLeave = $pdo->prepare("SELECT COUNT(*) FROM tl_requests WHERE status = 'approved' AND date_start <= ? AND date_end >= ?");
    $stmtOnLeave->execute([$today, $today]);
    $countOnLeaveToday = (int)$stmtOnLeave->fetchColumn();

    // Recent Activity
    $stmt = $pdo->prepare("
        SELECT s.name as user, sub.subject_name as detail,
               a.date as time, a.status as status, a.period
        FROM att_attendance a
        JOIN att_students s ON a.student_id = s.id
        JOIN att_subjects sub ON a.subject_id = sub.id
        ORDER BY a.date DESC, a.id DESC LIMIT 5
    ");
    $stmt->execute();
    $recentActivity = $stmt->fetchAll() ?: [];

    // 5. สถิติการใช้งานระบบแยกตามครู
    $statsMonth = (isset($_GET['stats_month']) && preg_match('/^\d{4}-\d{2}$/', $_GET['stats_month'])) ? $_GET['stats_month'] : date('Y-m');
    $statsMonthLabel = date('F Y', strtotime($statsMonth . '-01'));
    $stmtTeacherStats = $pdo->prepare("
        SELECT
            t.id,
            t.name,
            COUNT(DISTINCT sub.id)          AS subject_count,
            COUNT(DISTINCT a.id)            AS total_records,
            COUNT(DISTINCT a.date)          AS active_days,
            SUM(CASE WHEN DATE_FORMAT(a.date,'%Y-%m') = ? THEN 1 ELSE 0 END) AS this_month,
            MAX(a.date)                     AS last_active
        FROM att_teachers t
        LEFT JOIN att_subjects sub ON sub.teacher_id = t.id
        LEFT JOIN att_attendance a  ON a.teacher_id  = t.id
        GROUP BY t.id, t.name
        ORDER BY total_records DESC
    ");
    $stmtTeacherStats->execute([$statsMonth]);
    $teacherStats = $stmtTeacherStats->fetchAll() ?: [];

    // 6. สถานะเช็คชื่อเดือนนี้
    $monthStart = date('Y-m-01');
    $monthEnd   = date('Y-m-t');
    $stmtAttStats = $pdo->prepare("
        SELECT status, COUNT(*) as cnt
        FROM att_attendance
        WHERE date BETWEEN ? AND ?
        GROUP BY status
    ");
    $stmtAttStats->execute([$monthStart, $monthEnd]);
    $attStatusRaw = $stmtAttStats->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];
    
    $attStatusOrder = ['มา', 'ขาด', 'ลา', 'โดด', 'สาย'];
    $attStatusData = [];
    foreach ($attStatusOrder as $s) {
        $attStatusData[] = (int)($attStatusRaw[$s] ?? 0);
    }

    // 7. แนวโน้มเช็คชื่อ 7 วันย้อนหลัง
    $stmtTrend = $pdo->prepare("
        SELECT date, COUNT(*) cnt 
        FROM att_attendance 
        WHERE date >= DATE_SUB(?, INTERVAL 6 DAY)
        GROUP BY date 
        ORDER BY date
    ");
    $stmtTrend->execute([$today]);
    $trendRaw = $stmtTrend->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];
    $trendLabels = []; $trendData = [];
    $daysThai = ['อาทิตย์','จันทร์','อังคาร','พุธ','พฤหัสบดี','ศุกร์','เสาร์'];
    for ($i=6; $i>=0; $i--) { 
        $d = date('Y-m-d', strtotime("-$i days")); 
        $trendLabels[] = $daysThai[date('w', strtotime($d))]; 
        $trendData[] = (int)($trendRaw[$d] ?? 0); 
    }

    // 8. สัดส่วนบทบาท
    $stmtUserProportion = $pdo->query("SELECT role, COUNT(*) as cnt FROM llw_users GROUP BY role");
    $userProportionRaw = $stmtUserProportion->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];
    $rolesThai = [
        'super_admin' => 'แอดมิน',
        'wfh_admin'   => 'ผู้บริหาร',
        'att_teacher' => 'ครู',
        'wfh_staff'   => 'บุคลากร',
        'cb_admin'    => 'เจ้าหน้าที่ CB'
    ];
    $userProportionLabels = [];
    $userProportionData = [];
    foreach ($rolesThai as $key => $label) {
        if (isset($userProportionRaw[$key])) {
            $userProportionLabels[] = $label;
            $userProportionData[] = (int)$userProportionRaw[$key];
        }
    }

    // 9. จำนวนนักเรียนรายห้อง
    $enrollmentStats = [];
    try {
        $stmtEnrollment = $pdo->query("
            SELECT 
                s.classroom, 
                COUNT(*) as cnt,
                GROUP_CONCAT(u.firstname SEPARATOR ' / ') as advisors
            FROM att_students s
            LEFT JOIN llw_class_advisors a ON s.classroom = a.classroom
            LEFT JOIN llw_users u ON a.user_id = u.user_id
            GROUP BY s.classroom 
            ORDER BY s.classroom
        ");
        $enrollmentStats = $stmtEnrollment->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Exception $e) {
        $stmtFallback = $pdo->query("SELECT classroom, COUNT(*) as cnt, '' as advisors FROM att_students GROUP BY classroom ORDER BY classroom");
        $enrollmentStats = $stmtFallback->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
} catch (Exception $e) {
    error_log('[Dashboard Error] ' . $e->getMessage());
    $countStudents = 0; $countPendingLeave = 0; $countCheckedRooms = 0; $countTotalRooms = 0;
    $countRemainingRooms = 0; $roomProgress = 0; $countOnLeaveToday = 0;
    $recentActivity = []; $teacherStats = []; $attStatusData = [0,0,0,0,0];
    $trendLabels = []; $trendData = []; $userProportionLabels = []; $userProportionData = [];
    $enrollmentStats = [];
}

$activeSystem = 'portal';
$statsMonth = $statsMonth ?? date('Y-m');
$statsMonthLabel = $statsMonthLabel ?? date('F Y');
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | LLW</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body { font-family: 'Prompt', sans-serif; background: #f8fafc; }
        .exec-banner { background: linear-gradient(135deg, #1e293b 0%, #334155 100%); border-radius: 2rem; padding: 2.5rem; color: white; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); }
        .kpi-card { background: white; border-radius: 1.5rem; padding: 1.5rem; border: 1px solid #f1f5f9; transition: all 0.3s; }
        .kpi-card:hover { transform: translateY(-3px); box-shadow: 0 10px 15px -3px rgba(0,0,0,0.05); }
        .dark-panel { background: #1e293b; border-radius: 2rem; padding: 2rem; color: white; }
    </style>
</head>
<body class="flex min-h-screen">
    <?php if (file_exists('components/sidebar.php')) include 'components/sidebar.php'; ?>
    
    <main class="flex-1 p-6 lg:p-10">
        <section class="mb-8 exec-banner">
            <h1 class="text-3xl font-bold">Executive Dashboard</h1>
            <p class="text-slate-400 mt-1">อัปเดตข้อมูลล่าสุด: <?= date('d/m/Y H:i') ?></p>
        </section>

        <section class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-6 gap-4 mb-8">
            <div class="kpi-card flex items-center gap-4">
                <div class="w-12 h-12 rounded-xl bg-blue-500 text-white flex items-center justify-center text-xl"><i class="bi bi-people"></i></div>
                <div><p class="text-2xl font-bold text-slate-800"><?= number_format($countStudents) ?></p><p class="text-[10px] font-bold text-slate-400 uppercase">นักเรียน</p></div>
            </div>
            <div class="kpi-card flex items-center gap-4">
                <div class="w-12 h-12 rounded-xl bg-amber-500 text-white flex items-center justify-center text-xl"><i class="bi bi-hourglass"></i></div>
                <div><p class="text-2xl font-bold text-slate-800"><?= $countPendingLeave ?></p><p class="text-[10px] font-bold text-slate-400 uppercase">ใบลารอ</p></div>
            </div>
            <div class="kpi-card flex items-center gap-4">
                <div class="w-12 h-12 rounded-xl bg-emerald-500 text-white flex items-center justify-center text-xl"><i class="bi bi-check-lg"></i></div>
                <div><p class="text-2xl font-bold text-slate-800"><?= $countCheckedRooms ?></p><p class="text-[10px] font-bold text-slate-400 uppercase">เช็คแล้ว</p></div>
            </div>
            <div class="kpi-card flex items-center gap-4">
                <div class="w-12 h-12 rounded-xl bg-rose-500 text-white flex items-center justify-center text-xl"><i class="bi bi-x-lg"></i></div>
                <div><p class="text-2xl font-bold text-slate-800"><?= $countRemainingRooms ?></p><p class="text-[10px] font-bold text-slate-400 uppercase">ค้างเช็ค</p></div>
            </div>
            <div class="kpi-card flex items-center gap-4">
                <div class="w-12 h-12 rounded-xl bg-indigo-500 text-white flex items-center justify-center text-xl"><i class="bi bi-person-slash"></i></div>
                <div><p class="text-2xl font-bold text-slate-800"><?= $countOnLeaveToday ?></p><p class="text-[10px] font-bold text-slate-400 uppercase">บุคลากรลา</p></div>
            </div>
            <div class="kpi-card flex items-center gap-4">
                <div class="w-12 h-12 rounded-xl bg-slate-500 text-white flex items-center justify-center text-xl"><i class="bi bi-activity"></i></div>
                <div><p class="text-2xl font-bold text-slate-800"><?= count($recentActivity) ?></p><p class="text-[10px] font-bold text-slate-400 uppercase">กิจกรรม</p></div>
            </div>
        </section>

        <section class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
            <div class="dark-panel flex items-center gap-6">
                <div class="text-6xl font-bold text-emerald-400"><?= $roomProgress ?>%</div>
                <div><p class="text-xs font-bold text-slate-400 uppercase">ความก้าวหน้าเช็คชื่อ</p><p class="text-sm text-slate-500 mt-1">วันนี้จากทั้งหมด <?= $countTotalRooms ?> ห้อง</p></div>
            </div>
            <div class="dark-panel flex items-center gap-6">
                <div class="text-6xl font-bold text-amber-400"><?= $countPendingLeave ?></div>
                <div><p class="text-xs font-bold text-slate-400 uppercase">ใบลาที่ต้องอนุมัติ</p><p class="text-sm text-slate-500 mt-1">รายการสะสมรอการดำเนินการ</p></div>
            </div>
        </section>

        <section class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div class="bg-white p-6 rounded-3xl border border-slate-100">
                <h3 class="text-xs font-bold text-slate-400 uppercase mb-4">สัดส่วนบทบาท</h3>
                <canvas id="roleChart" height="200"></canvas>
            </div>
            <div class="bg-white p-6 rounded-3xl border border-slate-100">
                <h3 class="text-xs font-bold text-slate-400 uppercase mb-4">สถานะเช็คชื่อ</h3>
                <canvas id="attChart" height="200"></canvas>
            </div>
            <div class="bg-white p-6 rounded-3xl border border-slate-100">
                <h3 class="text-xs font-bold text-slate-400 uppercase mb-4">แนวโน้ม 7 วัน</h3>
                <canvas id="trendChart" height="200"></canvas>
            </div>
        </section>

        <section class="bg-white rounded-3xl border border-slate-100 p-8 mb-8">
            <h3 class="text-lg font-bold text-slate-800 mb-6">จำนวนนักเรียนแยกรายห้อง</h3>
            <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-4">
                <?php foreach($enrollmentStats as $room): ?>
                <div class="p-4 bg-slate-50 rounded-2xl border border-slate-100">
                    <p class="text-xs font-bold text-slate-400 uppercase tracking-tighter mb-1"><?= htmlspecialchars($room['classroom']) ?></p>
                    <p class="text-3xl font-bold text-slate-800"><?= $room['cnt'] ?></p>
                    <p class="text-[9px] text-slate-500 truncate mt-1"><?= $room['advisors'] ?: '-' ?></p>
                </div>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="bg-white rounded-3xl border border-slate-100 overflow-hidden">
            <div class="p-6 border-b border-slate-50 flex justify-between items-center">
                <h3 class="font-bold text-slate-800">สถิติการใช้งานแยกครู</h3>
                <form method="GET" class="flex gap-2">
                    <input type="month" name="stats_month" value="<?= $statsMonth ?>" onchange="this.form.submit()" class="text-xs p-2 border rounded-lg">
                </form>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-50 text-slate-400 font-bold uppercase">
                        <tr><th class="px-6 py-4">ครู</th><th class="px-6 py-4">วิชา</th><th class="px-6 py-4">คาบรวม</th><th class="px-6 py-4">เดือนนี้</th><th class="px-6 py-4">ใช้ล่าสุด</th></tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        <?php foreach($teacherStats as $t): ?>
                        <tr>
                            <td class="px-6 py-4 font-bold"><?= htmlspecialchars($t['name']) ?></td>
                            <td class="px-6 py-4"><?= $t['subject_count'] ?></td>
                            <td class="px-6 py-4"><?= $t['total_records'] ?></td>
                            <td class="px-6 py-4 text-emerald-600 font-bold"><?= $t['this_month'] ?></td>
                            <td class="px-6 py-4 text-slate-400"><?= $t['last_active'] ?: '-' ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>

    <script>
        const ctxRole = document.getElementById('roleChart');
        new Chart(ctxRole, { type: 'doughnut', data: { labels: <?= json_encode($userProportionLabels) ?>, datasets: [{ data: <?= json_encode($userProportionData) ?>, backgroundColor: ['#6366f1','#ec4899','#3b82f6','#10b981','#f59e0b'] }] }, options: { plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, font: { size: 9 } } } } } });
        
        const ctxAtt = document.getElementById('attChart');
        new Chart(ctxAtt, { type: 'bar', data: { labels: ['มา','ขาด','ลา','โดด','สาย'], datasets: [{ data: <?= json_encode($attStatusData) ?>, backgroundColor: ['#10b981','#f43f5e','#f59e0b','#8b5cf6','#f97316'], borderRadius: 8 }] }, options: { plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, display: false }, x: { grid: { display: false }, ticks: { font: { size: 9 } } } } } });

        const ctxTrend = document.getElementById('trendChart');
        new Chart(ctxTrend, { type: 'line', data: { labels: <?= json_encode($trendLabels) ?>, datasets: [{ data: <?= json_encode($trendData) ?>, borderColor: '#3b82f6', tension: 0.4, fill: true, backgroundColor: 'rgba(59, 130, 246, 0.05)' }] }, options: { plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, display: false }, x: { grid: { display: false }, ticks: { font: { size: 9 } } } } } });
    </script>
</body>
</html>
