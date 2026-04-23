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

$pdo = getPdo();

// --- Fetch Real Stats ---
$today = date('Y-m-d');
try {
    // 1. นักเรียนทั้งหมด (baseline)
    $countStudents = $pdo->query("SELECT COUNT(*) FROM att_students")->fetchColumn() ?: 0;

    // 2. ใบลาที่รอดำเนินการ (action required!)
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

    // รายชื่อห้องที่ยังไม่ได้เช็ค
    $stmtUnchecked = $pdo->prepare("
        SELECT DISTINCT classroom FROM att_students
        WHERE classroom NOT IN (
            SELECT DISTINCT s2.classroom
            FROM att_attendance a2
            JOIN att_students s2 ON s2.id = a2.student_id
            WHERE a2.date = ?
        )
        ORDER BY classroom ASC
    ");
    $stmtUnchecked->execute([$today]);
    $uncheckedRooms = $stmtUnchecked->fetchAll(PDO::FETCH_COLUMN);

    // 4. บุคลากรลาวันนี้ (operations)
    $stmtOnLeave = $pdo->prepare("SELECT COUNT(*) FROM tl_requests WHERE status = 'approved' AND date_start <= ? AND date_end >= ?");
    $stmtOnLeave->execute([$today, $today]);
    $countOnLeaveToday = (int)$stmtOnLeave->fetchColumn();

    // Recent Activity — เช็คชื่อล่าสุด
    $stmt = $pdo->prepare("
        SELECT s.name as user, sub.subject_name as detail,
               a.date as time, a.status as status, a.period
        FROM att_attendance a
        JOIN att_students s ON a.student_id = s.id
        JOIN att_subjects sub ON a.subject_id = sub.id
        ORDER BY a.date DESC, a.id DESC LIMIT 5
    ");
    $stmt->execute();
    $recentActivity = $stmt->fetchAll();

    // 5. สถิติการใช้งานระบบแยกตามครู
    $statsMonth = preg_match('/^\d{4}-\d{2}$/', $_GET['stats_month'] ?? '') ? $_GET['stats_month'] : date('Y-m');
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
    $teacherStats = $stmtTeacherStats->fetchAll();

    // 6. สถานะเช็คชื่อเดือนนี้ (real data แทน fake assignment chart)
    $monthStart = date('Y-m-01');
    $monthEnd   = date('Y-m-t');
    $stmtAttStats = $pdo->prepare("
        SELECT status, COUNT(*) as cnt
        FROM att_attendance
        WHERE date BETWEEN ? AND ?
        GROUP BY status
    ");
    $stmtAttStats->execute([$monthStart, $monthEnd]);
    $attStatusRaw = $stmtAttStats->fetchAll(PDO::FETCH_KEY_PAIR);
    // เรียงตามลำดับที่ต้องการ
    $attStatusOrder = ['มา', 'ขาด', 'ลา', 'โดด', 'สาย'];
    $attStatusData  = array_map(fn($s) => (int)($attStatusRaw[$s] ?? 0), $attStatusOrder);

    // 7. แนวโน้มเช็คชื่อ 7 วันย้อนหลัง (real)
    $stmtTrend = $pdo->prepare("SELECT date, COUNT(*) cnt FROM att_attendance WHERE date BETWEEN DATE_SUB(?,INTERVAL 6 DAY) AND ? GROUP BY date ORDER BY date");
    $stmtTrend->execute([$today, $today]);
    $trendRaw = $stmtTrend->fetchAll(PDO::FETCH_KEY_PAIR);
    $trendLabels = []; $trendData = [];
    $daysThai = ['อาทิตย์','จันทร์','อังคาร','พุธ','พฤหัสบดี','ศุกร์','เสาร์'];
    for ($i=6; $i>=0; $i--) { 
        $d = date('Y-m-d', strtotime("-$i days")); 
        $trendLabels[] = $daysThai[date('w', strtotime($d))]; 
        $trendData[] = (int)($trendRaw[$d] ?? 0); 
    }

    // 8. สัดส่วนผู้ใช้งานแยกตามบทบาท (real)
    $stmtUserProportion = $pdo->query("SELECT role, COUNT(*) as cnt FROM llw_users GROUP BY role");
    $userProportionRaw = $stmtUserProportion->fetchAll(PDO::FETCH_KEY_PAIR);
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

    // 9. จำนวนนักเรียนรายห้อง (Hybrid Data: Counts + Advisors)
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
    $enrollmentStats = $stmtEnrollment->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log('[Dashboard] ' . $e->getMessage());
    $countStudents = 0; $countPendingLeave = 0;
    $countCheckedRooms = 0; $countTotalRooms = 0;
    $countRemainingRooms = 0; $roomProgress = 0;
    $countOnLeaveToday = 0; 
    $uncheckedRooms = []; $recentActivity = [];
    $teacherStats = []; $attStatusData = [0,0,0,0,0]; $trendLabels=[]; $trendData=[];
    $userProportionLabels = []; $userProportionData = [];
}

?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Super Dashboard | LLW Premium</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body { font-family: 'Prompt', sans-serif; background-color: #f8fafc; }
        .sidebar-item-active {
            background-color: #1d4ed8;
            color: white;
            box-shadow: 0 10px 15px -3px rgba(37, 99, 235, 0.2);
        }
        .card-gradient-1{background:linear-gradient(135deg,#6366f1,#a855f7)}
        .card-gradient-2{background:linear-gradient(135deg,#ec4899,#f43f5e)}
        .card-gradient-3{background:linear-gradient(135deg,#06b6d4,#3b82f6)}
        .card-gradient-4{background:linear-gradient(135deg,#10b981,#34d399)}
        .section-label{display:flex;align-items:center;gap:10px;margin-bottom:20px}
        .section-label .bar{width:4px;height:20px;border-radius:9999px;flex-shrink:0}
        .section-label h2{font-size:11px;font-weight:900;color:#94a3b8;text-transform:uppercase;letter-spacing:.1em}
        .section-label .ln{flex:1;height:1px;background:#f1f5f9}
        .section-label .pill{font-size:10px;font-weight:700;padding:3px 12px;border-radius:9999px;white-space:nowrap}
        @media print{
            aside,.no-print{display:none!important}
            main{margin-left:0!important;padding:1rem!important}
            section:not(#print-section){display:none!important}
            #print-section{display:block!important}
            .section-label{display:none!important}
            body{background:white!important}
            thead{background-color:#f1f5f9!important;-webkit-print-color-adjust:exact}
        }
    </style>
</head>
<body class="flex min-h-screen">

    <!-- Sidebar -->
    <aside class="w-72 bg-white border-r border-slate-100 flex flex-col fixed h-full z-20 no-print">
        <div class="p-8 flex items-center gap-4">
            <div class="w-12 h-12 bg-gradient-to-br from-blue-600 to-indigo-700 rounded-2xl flex items-center justify-center text-white text-2xl shadow-xl shadow-blue-200/50">
                <i class="bi bi-rocket-takeoff-fill"></i>
            </div>
            <div>
                <span class="text-xl font-black text-slate-800 tracking-tight block leading-none">Platinum</span>
                <span class="text-[10px] font-bold text-indigo-500 uppercase tracking-widest mt-1">Super Admin</span>
            </div>
        </div>

        <nav class="flex-1 px-6 space-y-2 overflow-y-auto">
            <p class="text-[10px] font-black text-slate-300 uppercase tracking-[0.2em] pl-4 mb-4 mt-6">Management</p>
            <a href="central_dashboard.php" class="flex items-center gap-4 px-5 py-4 rounded-2xl font-bold bg-blue-600 text-white shadow-lg shadow-blue-200/50 transition-all">
                <i class="bi bi-grid-fill text-lg"></i> แดชบอร์ด
            </a>
            <a href="manage_users.php" class="flex items-center gap-4 px-5 py-4 rounded-2xl font-bold text-slate-400 hover:bg-slate-50 hover:text-slate-800 transition-all">
                <i class="bi bi-people text-lg"></i> จัดการผู้ใช้
            </a>
            <a href="manage_advisors.php" class="flex items-center gap-4 px-5 py-4 rounded-2xl font-bold text-slate-400 hover:bg-slate-50 hover:text-slate-800 transition-all">
                <i class="bi bi-mortarboard text-lg"></i> จัดการที่ปรึกษา
            </a>
            <p class="text-[10px] font-black text-slate-300 uppercase tracking-[0.2em] pl-4 mb-4 mt-6">Systems</p>
            <a href="teacher_leave/index.php" class="flex items-center gap-4 px-5 py-4 rounded-2xl font-bold text-slate-400 hover:bg-slate-50 hover:text-slate-800 transition-all">
                <i class="bi bi-file-earmark-text text-lg"></i> ระบบใบลา
            </a>
            <a href="attendance_system/dashboard.php" class="flex items-center gap-4 px-5 py-4 rounded-2xl font-bold text-slate-400 hover:bg-slate-50 hover:text-slate-800 transition-all">
                <i class="bi bi-person-check text-lg"></i> ระบบเช็คชื่อ
            </a>
            <a href="behavior/admin.php" class="flex items-center gap-4 px-5 py-4 rounded-2xl font-bold text-slate-400 hover:bg-slate-50 hover:text-slate-800 transition-all">
                <i class="bi bi-journal-text text-lg"></i> ระบบพฤติกรรม
            </a>
        </nav>

        <div class="p-8 mt-auto space-y-4">
            <a href="change_password.php" class="flex items-center gap-4 px-5 py-4 rounded-2xl font-bold text-indigo-500 hover:bg-indigo-50 transition-all">
                <i class="bi bi-key-fill text-lg"></i> เปลี่ยนรหัส
            </a>
            <a href="logout.php" class="flex items-center gap-4 px-5 py-4 rounded-2xl font-bold text-rose-500 hover:bg-rose-50 transition-all">
                <i class="bi bi-box-arrow-left text-lg"></i> ออกจากระบบ
            </a>
        </div>
    </aside>

    <!-- Main -->
    <main class="ml-72 flex-1 p-8 pb-20">

        <!-- Header -->
        <header class="flex justify-between items-center mb-10 no-print">
            <div>
                <div class="flex items-center gap-3 mb-1">
                    <div class="w-1.5 h-9 bg-gradient-to-b from-indigo-500 to-purple-600 rounded-full"></div>
                    <h1 class="text-3xl font-black text-slate-800">Super Dashboard</h1>
                </div>
                <p class="text-sm text-slate-400 pl-5">สวัสดี, <span class="text-slate-600 font-bold"><?= htmlspecialchars($_SESSION['firstname']) ?></span> &mdash; <?= date('l, d F Y') ?></p>
            </div>
            <div class="flex items-center gap-3">
                <div class="bg-white border border-slate-100 shadow-sm rounded-2xl px-5 py-3 flex items-center gap-2">
                    <i class="bi bi-clock text-indigo-500 text-sm"></i>
                    <span id="liveClock" class="font-black text-slate-700 tabular-nums text-sm">--:--:--</span>
                </div>
                <div class="flex items-center gap-3 bg-white p-2 pr-5 rounded-2xl shadow-sm border border-slate-100">
                    <img src="https://ui-avatars.com/api/?name=<?= urlencode($_SESSION['firstname']??'Admin') ?>&background=4f46e5&color=fff" class="w-10 h-10 rounded-xl" alt="avatar">
                    <div>
                        <p class="text-sm font-black text-slate-800"><?= htmlspecialchars($_SESSION['firstname']) ?></p>
                        <p class="text-[10px] font-bold text-indigo-400 uppercase tracking-widest">Super Admin</p>
                    </div>
                </div>
            </div>
        </header>

        <!-- E: สถิติครู (print-ready) -->
        <div class="section-label no-print"><div class="bar" style="background:#7c3aed"></div><h2>สถิติการใช้งานระบบแยกตามครู</h2><div class="ln"></div><span class="pill" style="background:#ede9fe;color:#6d28d9">ผลงานเชิงประจักษ์</span></div>

        <!-- Stats Cards (Premium) -->
        <section class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8 mb-12">

            <!-- Card 1: นักเรียนทั้งหมด -->
            <div class="card-gradient-1 p-8 rounded-[40px] text-white shadow-xl shadow-indigo-100 relative overflow-hidden group">
                <div class="absolute -right-4 -bottom-4 text-white/10 text-9xl group-hover:scale-110 transition-transform">
                    <i class="bi bi-people"></i>
                </div>
                <div class="flex justify-between items-start mb-6">
                    <div class="w-12 h-12 bg-white/20 rounded-2xl flex items-center justify-center text-2xl">
                        <i class="bi bi-people-fill"></i>
                    </div>
                    <span class="text-[10px] font-bold bg-white/20 px-3 py-1 rounded-full uppercase tracking-widest">จำนวนนักเรียน</span>
                </div>
                <p class="text-indigo-100 font-bold text-xs uppercase tracking-widest mb-1">นักเรียนทั้งหมด</p>
                <h3 class="text-4xl font-black mb-4"><?= number_format($countStudents) ?></h3>
                <p class="text-indigo-200 text-[10px] font-bold">เข้ารับการในระบบเช็คชื่อ</p>
            </div>

            <!-- Card 2: ใบลารอดำเนินการ (Action Required!) -->
            <a href="teacher_leave/index.php" class="card-gradient-2 p-8 rounded-[40px] text-white shadow-xl shadow-rose-100 relative overflow-hidden group block hover:scale-[1.02] transition-transform">
                <div class="absolute -right-4 -bottom-4 text-white/10 text-9xl group-hover:scale-110 transition-transform">
                    <i class="bi bi-hourglass-split"></i>
                </div>
                <div class="flex justify-between items-start mb-6">
                    <div class="w-12 h-12 bg-white/20 rounded-2xl flex items-center justify-center text-2xl">
                        <i class="bi bi-hourglass-split"></i>
                    </div>
                    <?php if($countPendingLeave > 0): ?>
                    <span class="text-[10px] font-black bg-white text-rose-600 px-3 py-1 rounded-full animate-pulse">ด่วน!</span>
                    <?php else: ?>
                    <span class="text-[10px] font-bold bg-white/20 px-3 py-1 rounded-full">ไม่มีค้าง</span>
                    <?php endif; ?>
                </div>
                <p class="text-rose-100 font-bold text-xs uppercase tracking-widest mb-1">ใบลารอพิจารณา</p>
                <h3 class="text-4xl font-black mb-4"><?= $countPendingLeave ?></h3>
                <p class="text-rose-200 text-[10px] font-bold"><?= $countPendingLeave > 0 ? 'คลิกเพื่อดำเนินการใบลา' : 'ไม่มีใบลารอดำเนินการ' ?></p>
            </a>

            <!-- Card 3: ห้องเรียนที่เช็คชื่อแล้ว -->
            <div class="card-gradient-3 p-8 rounded-[40px] text-white shadow-xl shadow-blue-100 relative overflow-hidden group">
                <div class="absolute -right-4 -bottom-4 text-white/10 text-9xl group-hover:scale-110 transition-transform">
                    <i class="bi bi-door-open"></i>
                </div>
                <div class="flex justify-between items-start mb-6">
                    <div class="w-12 h-12 bg-white/20 rounded-2xl flex items-center justify-center text-2xl">
                        <i class="bi bi-calendar-check-fill"></i>
                    </div>
                    <span class="text-[10px] font-bold bg-white/20 px-3 py-1 rounded-full"><?= date('d/m/Y') ?></span>
                </div>
                <p class="text-blue-100 font-bold text-xs uppercase tracking-widest mb-1">ห้องที่เช็คชื่อแล้ว</p>
                <h3 class="text-4xl font-black mb-1"><?= $countCheckedRooms ?> <span class="text-xl font-bold opacity-70">/ <?= $countTotalRooms ?></span></h3>
                <p class="text-blue-200 text-[10px] font-bold mb-3">ยังเหลืออีก <?= $countRemainingRooms ?> ห้อง</p>
                <div class="w-full bg-white/20 h-2 rounded-full overflow-hidden">
                    <div class="bg-white h-full rounded-full transition-all" style="width: <?= $roomProgress ?>%"></div>
                </div>
                <p class="text-blue-200 text-[10px] font-bold mt-1"><?= $roomProgress ?>% เสร็จแล้ว</p>
            </div>

            <!-- Card 4: บุคลากรลาวันนี้ -->
            <div class="card-gradient-4 p-8 rounded-[40px] text-white shadow-xl shadow-emerald-100 relative overflow-hidden group">
                <div class="absolute -right-4 -bottom-4 text-white/10 text-9xl group-hover:scale-110 transition-transform">
                    <i class="bi bi-person-slash"></i>
                </div>
                <div class="flex justify-between items-start mb-6">
                    <div class="w-12 h-12 bg-white/20 rounded-2xl flex items-center justify-center text-2xl">
                        <i class="bi bi-person-slash"></i>
                    </div>
                    <span class="text-[10px] font-bold bg-white/20 px-3 py-1 rounded-full">วันนี้</span>
                </div>
                <p class="text-emerald-100 font-bold text-xs uppercase tracking-widest mb-1">บุคลากรลาวันนี้</p>
                <h3 class="text-4xl font-black mb-4"><?= $countOnLeaveToday ?></h3>
                <p class="text-emerald-200 text-[10px] font-bold"><?= $countOnLeaveToday > 0 ? 'คนอนุมัติลาแล้ววันนี้' : 'ไม่มีบุคลากรลาวันนี้' ?></p>
            </div>
        </section>

        <!-- Unchecked Rooms Section -->
        <?php if ($countRemainingRooms > 0): ?>
        <section class="mb-8">
            <div class="bg-white rounded-[32px] shadow-sm border border-amber-100 p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="font-black text-slate-800 flex items-center gap-2">
                        <div class="w-8 h-8 bg-amber-100 text-amber-600 rounded-xl flex items-center justify-center">
                            <i class="bi bi-exclamation-triangle-fill text-sm"></i>
                        </div>
                        ห้องที่ยังไม่ได้เช็คชื่อวันนี้
                        <span class="text-xs font-bold bg-amber-100 text-amber-600 px-3 py-1 rounded-full"><?= $countRemainingRooms ?> ห้อง</span>
                    </h3>
                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest"><?= date('d/m/Y') ?></span>
                </div>
                <div class="flex flex-wrap gap-2">
                    <?php foreach($uncheckedRooms as $room): ?>
                    <a href="attendance_system/attendance.php" class="px-4 py-2 bg-amber-50 text-amber-700 text-xs font-black rounded-xl border border-amber-200 hover:bg-amber-100 transition-all hover:scale-105">
                        <i class="bi bi-door-closed-fill mr-1"></i><?= htmlspecialchars($room) ?>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
        <?php else: ?>
        <section class="mb-8">
            <div class="bg-emerald-50 rounded-[32px] border border-emerald-100 p-5 flex items-center gap-4">
                <div class="w-10 h-10 bg-emerald-500 text-white rounded-2xl flex items-center justify-center text-lg shadow-lg shadow-emerald-200">
                    <i class="bi bi-check-circle-fill"></i>
                </div>
                <div>
                    <p class="font-black text-emerald-700">เช็คชื่อครบทุกห้องแล้ววันนี้! 🎉</p>
                    <p class="text-[10px] font-bold text-emerald-500 uppercase tracking-widest"><?= $countTotalRooms ?> ห้องเรียน — <?= date('d/m/Y') ?></p>
                </div>
            </div>
        </section>
        <?php endif; ?>

        <!-- Charts Section -->
        <section class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-12">
            <!-- Line Chart -->
            <div class="bg-white p-8 rounded-[40px] shadow-sm border border-slate-100">
                <div class="flex justify-between items-center mb-8">
                    <h3 class="text-lg font-black text-slate-800 flex items-center gap-2">
                        <i class="bi bi-graph-up-arrow text-blue-600"></i> การเข้าใช้งานระบบ
                    </h3>
                    <div class="flex gap-2">
                        <button class="px-4 py-2 bg-blue-50 text-blue-600 rounded-xl text-xs font-bold">7 วัน</button>
                        <button class="px-4 py-2 text-slate-400 rounded-xl text-xs font-bold">30 วัน</button>
                    </div>
                </div>
                <canvas id="usageChart" height="250"></canvas>
            </div>

            <!-- Donut Chart -->
            <div class="bg-white p-8 rounded-[40px] shadow-sm border border-slate-100">
                <h3 class="text-lg font-black text-slate-800 flex items-center gap-2 mb-8">
                    <i class="bi bi-pie-chart text-indigo-600"></i> สัดส่วนผู้ใช้งาน
                </h3>
                <div class="flex items-center justify-center relative">
                    <canvas id="userDonutChart" height="250"></canvas>
                    <div class="absolute flex flex-col items-center">
                        <span class="text-3xl font-black text-slate-800">100%</span>
                        <span class="text-[10px] font-bold text-slate-400 uppercase">ทั้งหมด</span>
                    </div>
                </div>
            </div>
        </section>

        <!-- Progress & Activity -->
        <section class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Attendance Status Bar Chart -->
            <div class="lg:col-span-1 bg-white p-8 rounded-[40px] shadow-sm border border-slate-100">
                <div class="flex items-center justify-between mb-2">
                    <h3 class="text-lg font-black text-slate-800 flex items-center gap-2">
                        <i class="bi bi-bar-chart-fill text-indigo-600"></i> สถานะเช็คชื่อ
                    </h3>
                </div>
                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-6">รวมทั้งเดือน <?= date('m/Y') ?></p>
                <canvas id="attStatusChart" height="300"></canvas>
            </div>

            <!-- Subject Progress -->
            <div class="lg:col-span-2 bg-white p-8 rounded-[40px] shadow-sm border border-slate-100">
                <div class="flex justify-between items-center mb-10">
                    <h3 class="text-lg font-black text-slate-800 flex items-center gap-2">
                        <i class="bi bi-activity text-rose-600"></i> กิจกรรมล่าสุด
                    </h3>
                    <button onclick="location.reload()" class="text-xs font-bold text-blue-600 bg-blue-50 px-4 py-2 rounded-xl transition-all hover:bg-blue-600 hover:text-white">
                        <i class="bi bi-arrow-clockwise"></i> รีเฟรช
                    </button>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="text-left">
                                <th class="pb-6 text-xs font-black text-slate-400 uppercase tracking-widest pl-4">เวลา</th>
                                <th class="pb-6 text-xs font-black text-slate-400 uppercase tracking-widest">ผู้ใช้</th>
                                <th class="pb-6 text-xs font-black text-slate-400 uppercase tracking-widest">กิจกรรม</th>
                                <th class="pb-6 text-xs font-black text-slate-400 uppercase tracking-widest">รายละเอียด</th>
                                <th class="pb-6 text-xs font-black text-slate-400 uppercase tracking-widest">สถานะ</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-50">
                            <?php if (empty($recentActivity)): ?>
                                <tr>
                                    <td colspan="5" class="py-10 text-center text-slate-400 font-bold italic">ยังไม่มีการเช็คชื่อวันนี้</td>
                                </tr>
                            <?php else: ?>
                                <?php
                                $statusColors = ['มา'=>'emerald','ขาด'=>'rose','ลา'=>'amber','โดด'=>'violet','สาย'=>'orange'];
                                foreach ($recentActivity as $act):
                                    $sc = $statusColors[$act['status']] ?? 'slate';
                                ?>
                                <tr class="group hover:bg-slate-50/50 transition-all">
                                    <td class="py-5 pl-4">
                                        <span class="text-xs font-bold text-slate-500"><?= date('d/m', strtotime($act['time'])) ?></span>
                                    </td>
                                    <td class="py-5">
                                        <div class="flex items-center gap-3">
                                            <div class="w-8 h-8 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center text-xs">
                                                <i class="bi bi-person"></i>
                                            </div>
                                            <span class="text-xs font-bold text-slate-700"><?= htmlspecialchars($act['user']) ?></span>
                                        </div>
                                    </td>
                                    <td class="py-5">
                                        <span class="px-3 py-1 rounded-full text-[10px] font-black bg-blue-50 text-blue-600">เช็คชื่อ P<?= $act['period'] ?></span>
                                    </td>
                                    <td class="py-5">
                                        <span class="text-xs text-slate-500 font-medium"><?= htmlspecialchars($act['detail']) ?></span>
                                    </td>
                                    <td class="py-5">
                                        <span class="px-3 py-1 rounded-full bg-<?= $sc ?>-50 text-<?= $sc ?>-600 text-[10px] font-black">
                                            <?= $act['status'] ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <!-- Classroom Enrollment Section (Hybrid Widget) -->
        <section class="mt-12 no-print">
            <div class="flex items-center justify-between mb-6">
                <div class="section-label mb-0"><div class="bar" style="background:#10b981"></div><h2>ตรวจสอบจำนวนนักเรียนรายห้อง (Hybrid View)</h2><div class="ln"></div></div>
                <div class="flex gap-2">
                    <button onclick="toggleEnrollmentView('grid')" id="btn-view-grid" class="view-btn active w-10 h-10 rounded-xl bg-white border border-slate-200 text-slate-400 flex items-center justify-center transition-all shadow-sm">
                        <i class="bi bi-grid-fill"></i>
                    </button>
                    <button onclick="toggleEnrollmentView('list')" id="btn-view-list" class="view-btn w-10 h-10 rounded-xl bg-white border border-slate-200 text-slate-400 flex items-center justify-center transition-all shadow-sm">
                        <i class="bi bi-list-task text-lg"></i>
                    </button>
                </div>
            </div>

            <div class="bg-white/50 backdrop-blur-xl rounded-[40px] border border-white/50 p-8 shadow-xl shadow-slate-200/50">
                <!-- Search Bar -->
                <div class="relative mb-8 max-w-md">
                    <i class="bi bi-search absolute left-4 top-1/2 -translate-y-1/2 text-slate-400"></i>
                    <input type="text" id="roomSearch" onkeyup="filterEnrollment()" placeholder="ค้นหาห้องเรียน หรือชื่อครู..." 
                           class="w-full bg-white/80 border border-slate-200 rounded-2xl pl-12 pr-6 py-4 text-sm font-bold focus:ring-2 focus:ring-emerald-500 outline-none transition-all shadow-sm">
                </div>

                <!-- Grid View -->
                <div id="enrollment-grid" class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-6 transition-all duration-500">
                    <?php foreach($enrollmentStats as $room): ?>
                    <div class="enrollment-card bg-white p-6 rounded-[32px] border border-slate-100 shadow-sm hover:shadow-xl hover:-translate-y-1 transition-all group" 
                         data-room="<?= htmlspecialchars(mb_strtolower($room['classroom'])) ?>"
                         data-advisors="<?= htmlspecialchars(mb_strtolower($room['advisors'])) ?>">
                        <div class="flex justify-between items-start mb-4">
                            <div class="w-10 h-10 bg-emerald-50 text-emerald-600 rounded-xl flex items-center justify-center font-black text-xs">
                                <?= explode('/', $room['classroom'])[0] ?>
                            </div>
                            <span class="text-[9px] font-black bg-slate-50 text-slate-400 px-2 py-1 rounded-lg uppercase tracking-tighter italic">Room Check</span>
                        </div>
                        <p class="text-xl font-black text-slate-800 tracking-tight"><?= htmlspecialchars($room['classroom']) ?></p>
                        <p class="text-[10px] font-bold text-slate-400 mt-1 truncate" title="<?= htmlspecialchars($room['advisors']) ?>">
                            <?= $room['advisors'] ? 'ครู' . $room['advisors'] : 'ยังไม่ระบุครู' ?>
                        </p>
                        <div class="mt-4 pt-4 border-t border-slate-50 flex items-end justify-between">
                            <span class="text-3xl font-black text-emerald-600 leading-none"><?= $room['cnt'] ?></span>
                            <span class="text-[10px] font-black text-slate-300 uppercase mb-1">Students</span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- List View (Hidden by default) -->
                <div id="enrollment-list" class="hidden overflow-x-auto transition-all duration-500">
                    <table class="w-full">
                        <thead>
                            <tr class="text-left">
                                <th class="pb-6 text-xs font-black text-slate-400 uppercase tracking-widest pl-4">ห้องเรียน</th>
                                <th class="pb-6 text-xs font-black text-slate-400 uppercase tracking-widest">ครูที่ปรึกษา</th>
                                <th class="pb-6 text-xs font-black text-slate-400 uppercase tracking-widest text-center">จำนวนนักเรียน</th>
                                <th class="pb-6 text-xs font-black text-slate-400 uppercase tracking-widest text-right pr-4">สถานะ</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-50">
                            <?php foreach($enrollmentStats as $room): ?>
                            <tr class="enrollment-row group hover:bg-slate-50/50 transition-all" 
                                data-room="<?= htmlspecialchars(mb_strtolower($room['classroom'])) ?>"
                                data-advisors="<?= htmlspecialchars(mb_strtolower($room['advisors'])) ?>">
                                <td class="py-4 pl-4 font-black text-slate-700"><?= htmlspecialchars($room['classroom']) ?></td>
                                <td class="py-4 text-xs font-bold text-slate-500"><?= $room['advisors'] ? 'ครู' . htmlspecialchars($room['advisors']) : '-' ?></td>
                                <td class="py-4 text-center font-black text-lg text-emerald-600"><?= $room['cnt'] ?></td>
                                <td class="py-4 text-right pr-4">
                                    <span class="px-3 py-1 rounded-full bg-emerald-50 text-emerald-600 text-[10px] font-black border border-emerald-100">
                                        <i class="bi bi-check-circle-fill mr-1"></i> ตรวจสอบแล้ว
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <style>
            .view-btn.active {
                background: #10b981;
                color: white;
                border-color: #10b981;
                box-shadow: 0 10px 15px -3px rgba(16, 185, 129, 0.2);
            }
        </style>

        <script>
            function toggleEnrollmentView(view) {
                const grid = document.getElementById('enrollment-grid');
                const list = document.getElementById('enrollment-list');
                const btnGrid = document.getElementById('btn-view-grid');
                const btnList = document.getElementById('btn-view-list');

                if (view === 'grid') {
                    grid.classList.remove('hidden');
                    list.classList.add('hidden');
                    btnGrid.classList.add('active');
                    btnList.classList.remove('active');
                } else {
                    grid.classList.add('hidden');
                    list.classList.remove('hidden');
                    btnGrid.classList.remove('active');
                    btnList.classList.add('active');
                }
            }

            function filterEnrollment() {
                const q = document.getElementById('roomSearch').value.toLowerCase();
                const cards = document.querySelectorAll('.enrollment-card');
                const rows = document.querySelectorAll('.enrollment-row');

                cards.forEach(card => {
                    const match = card.dataset.room.includes(q) || card.dataset.advisors.includes(q);
                    card.style.display = match ? '' : 'none';
                });

                rows.forEach(row => {
                    const match = row.dataset.room.includes(q) || row.dataset.advisors.includes(q);
                    row.style.display = match ? '' : 'none';
                });
            }
        </script>

        <!-- Teacher Usage Stats Section -->
        <section id="print-section" class="mt-8">
            <div class="bg-white rounded-[40px] shadow-sm border border-slate-100 overflow-hidden">
                <!-- Header + Controls -->
                <div class="px-8 py-6 border-b border-slate-50">
                    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                        <div>
                            <h3 class="text-lg font-black text-slate-800 flex items-center gap-2">
                                <i class="bi bi-person-lines-fill text-indigo-600"></i>
                                สถิติการใช้งานระบบเช็คชื่อแยกตามครู
                            </h3>
                            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mt-1">ผลงานเชิงประจักษ์ — เพื่อประกอบการพิจารณา</p>
                        </div>
                        <!-- Filter Controls -->
                        <div class="flex flex-wrap gap-3 items-center no-print">
                            <!-- Search by name -->
                            <div class="relative">
                                <i class="bi bi-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                                <input id="teacherSearch" type="text" placeholder="ค้นชื่อครู..."
                                    class="pl-8 pr-4 py-2 text-xs bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-indigo-400 outline-none w-40">
                            </div>
                            <!-- Filter by status -->
                            <select id="statusFilter" class="px-3 py-2 text-xs bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-indigo-400 outline-none">
                                <option value="">ทุกสถานะ</option>
                                <option value="Active">Active</option>
                                <option value="ใช้บ้างๆ">ใช้บ้างๆ</option>
                                <option value="ไม่ใช้งาน">ไม่ใช้งาน</option>
                                <option value="ไม่เคยใช้">ไม่เคยใช้</option>
                            </select>
                            <!-- Month selector -->
                            <form method="GET" id="monthForm">
                                <input type="month" name="stats_month" value="<?= $statsMonth ?>"
                                    onchange="document.getElementById('monthForm').submit()"
                                    class="px-3 py-2 text-xs bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-indigo-400 outline-none">
                            </form>
                            <!-- Print button -->
                            <button onclick="window.print()" class="flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white text-xs font-bold rounded-xl hover:bg-indigo-700 transition-all shadow-lg shadow-indigo-200">
                                <i class="bi bi-printer-fill"></i> พิมพ์
                            </button>
                        </div>
                    </div>
                    <!-- Print header (only shows on print) -->
                    <div class="hidden print:block mt-4 text-center border-b pb-4">
                        <h2 class="text-xl font-black">โรงเรียนละลมวิทยา — สถิติการใช้งานระบบเช็คชื่อ</h2>
                        <p class="text-sm text-slate-500">เดือน: <?= $statsMonthLabel ?> | พิมพ์เมื่อ: <?= date('d/m/Y H:i') ?> น.</p>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table id="teacherTable" class="min-w-full text-xs">
                        <thead class="bg-slate-50 text-[10px] font-black text-slate-400 uppercase tracking-widest">
                            <tr>
                                <th class="px-6 py-4 text-left">#</th>
                                <th class="px-6 py-4 text-left">ชื่อครู</th>
                                <th class="px-4 py-4 text-center">วิชาที่สอน</th>
                                <th class="px-4 py-4 text-center">คาบทั้งหมด</th>
                                <th class="px-4 py-4 text-center">วันที่ใช้งาน</th>
                                <th class="px-4 py-4 text-center">เดือน <?= $statsMonthLabel ?></th>
                                <th class="px-4 py-4 text-center">ใช้ล่าสุด</th>
                                <th class="px-4 py-4 text-center">สถานะ</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-50">
                            <?php if (empty($teacherStats)): ?>
                            <tr><td colspan="8" class="px-6 py-10 text-center text-slate-300 font-bold">ไม่พบข้อมูลครู</td></tr>
                            <?php else: ?>
                            <?php foreach($teacherStats as $i => $t):
                                $daysSince = $t['last_active']
                                    ? (int)((strtotime($today) - strtotime($t['last_active'])) / 86400)
                                    : 999;
                                if ($t['total_records'] === 0) {
                                    $badge = ['label' => 'ไม่เคยใช้', 'color' => 'slate'];
                                } elseif ($daysSince <= 7) {
                                    $badge = ['label' => 'Active', 'color' => 'emerald'];
                                } elseif ($daysSince <= 30) {
                                    $badge = ['label' => 'ใช้บ้างๆ', 'color' => 'amber'];
                                } else {
                                    $badge = ['label' => 'ไม่ใช้งาน', 'color' => 'rose'];
                                }
                            ?>
                            <tr class="hover:bg-slate-50/50 transition-all <?= $t['total_records'] === 0 ? 'opacity-50' : '' ?>"
                                data-name="<?= htmlspecialchars(mb_strtolower($t['name']), ENT_QUOTES) ?>"
                                data-status="<?= htmlspecialchars($badge['label'], ENT_QUOTES) ?>">
                                <td class="px-6 py-4 font-black text-slate-300"><?= $i + 1 ?></td>
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-3">
                                        <div class="w-8 h-8 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center font-black text-[10px]">
                                            <?= mb_substr($t['name'], 0, 1) ?>
                                        </div>
                                        <span class="font-bold text-slate-700"><?= htmlspecialchars($t['name']) ?></span>
                                    </div>
                                </td>
                                <td class="px-4 py-4 text-center">
                                    <span class="font-black text-indigo-600"><?= $t['subject_count'] ?></span>
                                    <span class="text-slate-400 ml-0.5">วิชา</span>
                                </td>
                                <td class="px-4 py-4 text-center">
                                    <span class="font-black text-slate-700"><?= number_format($t['total_records']) ?></span>
                                    <span class="text-slate-400 ml-0.5">คาบ</span>
                                </td>
                                <td class="px-4 py-4 text-center">
                                    <span class="font-black text-blue-600"><?= $t['active_days'] ?></span>
                                    <span class="text-slate-400 ml-0.5">วัน</span>
                                </td>
                                <td class="px-4 py-4 text-center">
                                    <?php if ($t['this_month'] > 0): ?>
                                    <span class="font-black text-emerald-600"><?= $t['this_month'] ?></span>
                                    <span class="text-slate-400 ml-0.5">คาบ</span>
                                    <?php else: ?>
                                    <span class="text-slate-300 font-bold">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-4 text-center">
                                    <?php if ($t['last_active']): ?>
                                    <span class="text-slate-500 font-bold"><?= date('d/m/Y', strtotime($t['last_active'])) ?></span>
                                    <?php else: ?>
                                    <span class="text-slate-300 font-bold">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-4 text-center">
                                    <span class="px-3 py-1 rounded-full text-[10px] font-black bg-<?= $badge['color'] ?>-50 text-<?= $badge['color'] ?>-600 border border-<?= $badge['color'] ?>-100">
                                        <?= $badge['label'] ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <div class="px-8 py-4 bg-slate-50/50 border-t border-slate-50 flex flex-wrap gap-6 items-center justify-between text-[10px] font-bold text-slate-400">
                    <div class="flex gap-6">
                        <span class="flex items-center gap-1.5"><span class="w-2 h-2 bg-emerald-500 rounded-full"></span> Active = ใช้งานภายน7วัน</span>
                        <span class="flex items-center gap-1.5"><span class="w-2 h-2 bg-amber-400 rounded-full"></span> ใช้บ้างๆ = 8-30 วัน</span>
                        <span class="flex items-center gap-1.5"><span class="w-2 h-2 bg-rose-500 rounded-full"></span> ไม่ใช้งาน = เกิน 30 วัน</span>
                    </div>
                    <span id="filteredCount" class="no-print text-slate-500"></span>
                </div>
            </div>
        </section>
    </main>

    <script>
        // Usage Line Chart (Dynamic)
        const usageCtx = document.getElementById('usageChart').getContext('2d');
        new Chart(usageCtx, {
            type: 'line',
            data: {
                labels: <?= json_encode($trendLabels) ?>,
                datasets: [{
                    label: 'การเข้าใช้งาน',
                    data: <?= json_encode($trendData) ?>,
                    borderColor: '#2563eb',
                    backgroundColor: 'rgba(37, 99, 235, 0.1)',
                    fill: true,
                    tension: 0.4,
                    pointRadius: 6,
                    pointBackgroundColor: '#fff',
                    pointBorderWidth: 3
                }]
            },
            options: {
                plugins: { legend: { display: false } },
                scales: { 
                    y: { beginAtZero: true, grid: { display: false }, ticks: { font: { size: 10 } } },
                    x: { grid: { display: false }, ticks: { font: { size: 10 } } }
                }
            }
        });

        // User Donut Chart (Dynamic)
        const donutCtx = document.getElementById('userDonutChart').getContext('2d');
        new Chart(donutCtx, {
            type: 'doughnut',
            data: {
                labels: <?= json_encode($userProportionLabels) ?>,
                datasets: [{
                    data: <?= json_encode($userProportionData) ?>,
                    backgroundColor: ['#6366f1', '#ec4899', '#3b82f6', '#10b981', '#f59e0b'],
                    borderWidth: 0,
                    hoverOffset: 20
                }]
            },
            options: {
                cutout: '75%',
                plugins: { legend: { position: 'bottom', labels: { usePointStyle: true, font: { size: 10 } } } }
            }
        });

        // Attendance Status Bar Chart (real data)
        const attCtx = document.getElementById('attStatusChart').getContext('2d');
        new Chart(attCtx, {
            type: 'bar',
            data: {
                labels: ['มา', 'ขาด', 'ลา', 'โดด', 'สาย'],
                datasets: [{
                    data: <?= json_encode($attStatusData) ?>,
                    backgroundColor: ['#10b981', '#f43f5e', '#f59e0b', '#8b5cf6', '#f97316'],
                    borderRadius: 14,
                    borderSkipped: false
                }]
            },
            options: {
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: '#1e293b',
                        padding: 10,
                        cornerRadius: 10,
                        callbacks: {
                            label: ctx => ` ${ctx.parsed.y.toLocaleString()} ครั้ง`
                        }
                    }
                },
                scales: {
                    y: { beginAtZero: true, grid: { display: false }, ticks: { font: { size: 10 } } },
                    x: { grid: { display: false }, ticks: { font: { size: 11, weight: 'bold' } } }
                }
            }
        });
        // Teacher table filter
        const searchInput  = document.getElementById('teacherSearch');
        const statusSelect = document.getElementById('statusFilter');
        const filteredCount = document.getElementById('filteredCount');

        function filterTable() {
            const q      = searchInput.value.trim().toLowerCase();
            const status = statusSelect.value;
            const rows   = document.querySelectorAll('#teacherTable tbody tr[data-name]');
            let visible  = 0;

            rows.forEach(row => {
                const name   = row.dataset.name || '';
                const badge  = row.dataset.status || '';
                const matchName   = !q || name.includes(q);
                const matchStatus = !status || badge === status;
                const show = matchName && matchStatus;
                row.style.display = show ? '' : 'none';
                if (show) visible++;
            });
            filteredCount.textContent = `แสดง ${visible} จาก ${rows.length} คน`;
        }

        searchInput.addEventListener('input', filterTable);
        statusSelect.addEventListener('change', filterTable);
        filterTable(); // init count
    </script>
</body>
</html>
