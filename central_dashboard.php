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
        body { font-family: 'Prompt', sans-serif; background-color: #f1f5f9; }
        
        /* Executive Dark Header */
        .exec-banner {
            background: linear-gradient(135deg, #334155 0%, #1e293b 100%);
            border-radius: 32px;
            padding: 40px;
            color: white;
            position: relative;
            overflow: hidden;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
        }
        .exec-banner::before {
            content: ''; position: absolute; top: -50%; left: -50%; width: 200%; height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.05) 0%, transparent 70%);
            pointer-events: none;
        }

        /* Mini KPI Cards */
        .kpi-mini-card {
            background: white;
            border-radius: 24px;
            padding: 24px;
            display: flex;
            align-items: center;
            gap: 16px;
            border: 1px solid rgba(255,255,255,0.8);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
        }
        .kpi-mini-card:hover { transform: translateY(-4px); box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1); }
        .kpi-icon-circle {
            width: 48px; height: 48px; border-radius: 16px;
            display: flex; align-items: center; justify-content: center;
            font-size: 20px; color: white; flex-shrink: 0;
        }

        /* Dark Feature Panels */
        .dark-panel {
            background: #1e293b;
            border-radius: 32px;
            padding: 32px;
            color: white;
            position: relative;
            overflow: hidden;
        }
        .dark-panel .badge-label {
            position: absolute; right: -20px; top: 15px;
            background: rgba(255,255,255,0.1);
            padding: 4px 30px; transform: rotate(35deg);
            font-size: 10px; font-weight: 900; letter-spacing: 0.1em;
        }

        .filter-pill {
            background: white; border: 1px solid #e2e8f0;
            padding: 10px 20px; border-radius: 16px; font-size: 13px; font-weight: 600;
            color: #64748b; transition: all 0.2s;
        }
        .filter-pill:focus { border-color: #6366f1; ring: 2px; ring-color: #6366f1; }

        @media print {
            aside, .no-print { display: none !important; }
            main { margin-left: 0 !important; padding: 1rem !important; }
        }
    </style>
</head>
<body class="flex min-h-screen">

    <!-- Sidebar (Hybrid Standard) -->
    <?php include 'components/sidebar.php'; ?>

    <!-- Main Content -->
    <main class="flex-1 p-6 lg:p-10 transition-all duration-500">

        <!-- Top Header (Breadcrumb & User) -->
        <header class="flex justify-between items-center mb-8 no-print">
            <div>
                <h2 class="text-sm font-bold text-slate-400 uppercase tracking-widest">แดชบอร์ด</h2>
            </div>
            <div class="flex items-center gap-4">
                <a href="change_password.php" class="bg-white px-4 py-2 rounded-xl text-xs font-bold text-slate-600 border border-slate-200 hover:bg-slate-50 transition-all">
                    <i class="bi bi-key-fill mr-1"></i> เปลี่ยนรหัสผ่าน
                </a>
            </div>
        </header>

        <!-- Executive Banner -->
        <section class="mb-10 no-print">
            <div class="exec-banner">
                <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-6">
                    <div class="flex items-center gap-6">
                        <div class="w-16 h-16 bg-white/10 backdrop-blur-md rounded-2xl flex items-center justify-center text-3xl shadow-inner border border-white/20">
                            <i class="bi bi-bar-chart-line-fill"></i>
                        </div>
                        <div>
                            <h1 class="text-3xl font-black tracking-tight">แดชบอร์ดผู้บริหาร</h1>
                            <p class="text-slate-400 text-sm mt-1">ภาพรวมระบบบริหารจัดการโรงเรียน • อัปเดตเมื่อ <?= date('d M Y H:i') ?> น.</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3">
                        <div class="flex -space-x-2 mr-4">
                            <span class="w-4 h-4 rounded-full bg-emerald-500 border-2 border-slate-800"></span>
                            <span class="w-4 h-4 rounded-full bg-blue-500 border-2 border-slate-800"></span>
                            <span class="w-4 h-4 rounded-full bg-rose-500 border-2 border-slate-800"></span>
                            <span class="w-4 h-4 rounded-full bg-amber-500 border-2 border-slate-800"></span>
                            <span class="w-4 h-4 rounded-full bg-slate-500 border-2 border-slate-800"></span>
                        </div>
                        <button onclick="window.print()" class="bg-white/10 hover:bg-white/20 px-5 py-2.5 rounded-xl text-sm font-bold transition-all flex items-center gap-2 border border-white/10">
                            <i class="bi bi-printer"></i> พิมพ์ / PDF
                        </button>
                        <button onclick="location.reload()" class="bg-white/10 hover:bg-white/20 px-5 py-2.5 rounded-xl text-sm font-bold transition-all flex items-center gap-2 border border-white/10">
                            <i class="bi bi-arrow-clockwise"></i> รีเฟรช
                        </button>
                    </div>
                </div>
            </div>
        </section>

        <!-- Quick Filters -->
        <section class="mb-10 no-print">
            <div class="bg-white/50 backdrop-blur-md p-4 rounded-3xl border border-white/50 shadow-sm flex flex-wrap gap-4 items-center">
                <div class="flex flex-col gap-1 flex-1 min-w-[150px]">
                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest pl-2">จากวันที่</span>
                    <input type="date" class="filter-pill w-full">
                </div>
                <div class="flex flex-col gap-1 flex-1 min-w-[150px]">
                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest pl-2">ถึงวันที่</span>
                    <input type="date" class="filter-pill w-full">
                </div>
                <div class="flex flex-col gap-1 flex-1 min-w-[150px]">
                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest pl-2">สถานะ</span>
                    <select class="filter-pill w-full">
                        <option>ทุกสถานะ</option>
                    </select>
                </div>
                <div class="flex flex-col gap-1 flex-[2] min-w-[200px]">
                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest pl-2">ค้นหา</span>
                    <div class="relative">
                        <i class="bi bi-search absolute left-4 top-1/2 -translate-y-1/2 text-slate-400"></i>
                        <input type="text" placeholder="ค้นหาข้อมูล..." class="filter-pill w-full pl-10">
                    </div>
                </div>
                <button class="bg-slate-100 text-slate-500 px-6 py-3 rounded-2xl text-sm font-bold hover:bg-slate-200 transition-all self-end">
                    ล้างตัวกรอง
                </button>
            </div>
        </section>

        <!-- Mini KPI Cards Grid -->
        <section class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-6 gap-6 mb-10 no-print">
            
            <!-- Card 1: Students -->
            <div class="kpi-mini-card">
                <div class="kpi-icon-circle bg-emerald-500 shadow-lg shadow-emerald-100">
                    <i class="bi bi-people-fill"></i>
                </div>
                <div>
                    <h3 class="text-2xl font-black text-slate-800 leading-none"><?= number_format($countStudents) ?></h3>
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-tighter mt-1">นักเรียนทั้งหมด</p>
                    <p class="text-[9px] font-bold text-emerald-500 mt-0.5"><i class="bi bi-person-check"></i> ในระบบ</p>
                </div>
            </div>

            <!-- Card 2: Pending Leave -->
            <a href="teacher_leave/index.php" class="kpi-mini-card">
                <div class="kpi-icon-circle bg-amber-500 shadow-lg shadow-amber-100">
                    <i class="bi bi-hourglass-split"></i>
                </div>
                <div>
                    <h3 class="text-2xl font-black text-slate-800 leading-none"><?= $countPendingLeave ?></h3>
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-tighter mt-1">ใบลารออนุมัติ</p>
                    <p class="text-[9px] font-bold text-amber-500 mt-0.5"><i class="bi bi-lightning-fill"></i> รอจัดการ</p>
                </div>
            </a>

            <!-- Card 3: Checked Rooms -->
            <div class="kpi-mini-card">
                <div class="kpi-icon-circle bg-blue-500 shadow-lg shadow-blue-100">
                    <i class="bi bi-check-circle-fill"></i>
                </div>
                <div>
                    <h3 class="text-2xl font-black text-slate-800 leading-none"><?= $countCheckedRooms ?></h3>
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-tighter mt-1">เช็คชื่อแล้ว</p>
                    <p class="text-[9px] font-bold text-blue-500 mt-0.5"><i class="bi bi-door-open"></i> ห้องเรียน</p>
                </div>
            </div>

            <!-- Card 4: Remaining Rooms -->
            <div class="kpi-mini-card">
                <div class="kpi-icon-circle bg-indigo-500 shadow-lg shadow-indigo-100">
                    <i class="bi bi-slash-circle-fill"></i>
                </div>
                <div>
                    <h3 class="text-2xl font-black text-slate-800 leading-none"><?= $countRemainingRooms ?></h3>
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-tighter mt-1">ยังไม่เช็คชื่อ</p>
                    <p class="text-[9px] font-bold text-indigo-500 mt-0.5"><i class="bi bi-clock-history"></i> ค้างอยู่</p>
                </div>
            </div>

            <!-- Card 5: Personnel on Leave -->
            <div class="kpi-mini-card">
                <div class="kpi-icon-circle bg-rose-500 shadow-lg shadow-rose-100">
                    <i class="bi bi-person-slash"></i>
                </div>
                <div>
                    <h3 class="text-2xl font-black text-slate-800 leading-none"><?= $countOnLeaveToday ?></h3>
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-tighter mt-1">บุคลากรลาวันนี้</p>
                    <p class="text-[9px] font-bold text-rose-500 mt-0.5"><i class="bi bi-calendar-event"></i> วันนี้</p>
                </div>
            </div>

            <!-- Card 6: Total Activity -->
            <div class="kpi-mini-card">
                <div class="kpi-icon-circle bg-cyan-500 shadow-lg shadow-cyan-100">
                    <i class="bi bi-activity"></i>
                </div>
                <div>
                    <h3 class="text-2xl font-black text-slate-800 leading-none"><?= count($recentActivity) ?></h3>
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-tighter mt-1">กิจกรรมล่าสุด</p>
                    <p class="text-[9px] font-bold text-cyan-500 mt-0.5"><i class="bi bi-stars"></i> รายการ</p>
                </div>
            </div>

        </section>

        <!-- Dark Highlight Panels -->
        <section class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-10 no-print">
            
            <!-- Panel 1: Progress Score -->
            <div class="dark-panel">
                <div class="badge-label">SCORE</div>
                <div class="flex items-center gap-6">
                    <div class="w-20 h-20 bg-white/10 rounded-3xl flex items-center justify-center text-4xl">
                        <i class="bi bi-lightning-charge"></i>
                    </div>
                    <div>
                        <p class="text-xs font-bold text-slate-400 uppercase tracking-[0.2em] mb-1">ความก้าวหน้าการเช็คชื่อวันนี้</p>
                        <div class="flex items-baseline gap-2">
                            <span class="text-6xl font-black"><?= $roomProgress ?></span>
                            <span class="text-2xl font-bold text-slate-500">%</span>
                        </div>
                        <p class="text-[10px] text-slate-500 font-bold mt-2 italic">สะสมจากทุกห้องเรียนที่บันทึกข้อมูลในระบบ</p>
                    </div>
                </div>
            </div>

            <!-- Panel 2: Quick Queue -->
            <div class="dark-panel">
                <div class="badge-label">QUEUE</div>
                <div class="flex items-center gap-6">
                    <div class="w-20 h-20 bg-white/10 rounded-3xl flex items-center justify-center text-4xl">
                        <i class="bi bi-stack"></i>
                    </div>
                    <div>
                        <p class="text-xs font-bold text-slate-400 uppercase tracking-[0.2em] mb-1">คิวรออนุมัติใบลาของฉัน</p>
                        <div class="flex items-baseline gap-2">
                            <span class="text-6xl font-black"><?= $countPendingLeave ?></span>
                            <span class="text-2xl font-bold text-slate-500">รายการ</span>
                        </div>
                        <p class="text-[10px] text-slate-500 font-bold mt-2 italic">ต้องดำเนินการโดยผู้ใช้งานปัจจุบัน</p>
                    </div>
                </div>
            </div>

        </section>

        <!-- Charts Row -->
        <section class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8 mb-10 no-print">
            
            <!-- Donut: Status Distribution -->
            <div class="bg-white p-8 rounded-[40px] shadow-sm border border-slate-50">
                <h3 class="text-xs font-black text-slate-400 uppercase tracking-widest mb-6 flex justify-between">การกระจายตามสถานะคำขอ <span class="text-[9px] text-slate-300">DONUT</span></h3>
                <div class="relative flex items-center justify-center">
                    <canvas id="userDonutChart" height="200"></canvas>
                </div>
            </div>

            <!-- Bar: Attendance Status -->
            <div class="bg-white p-8 rounded-[40px] shadow-sm border border-slate-50">
                <h3 class="text-xs font-black text-slate-400 uppercase tracking-widest mb-6 flex justify-between">สถานะการเช็คชื่อเดือนนี้ <span class="text-[9px] text-slate-300">BAR</span></h3>
                <canvas id="attStatusChart" height="200"></canvas>
            </div>

            <!-- Line: Usage Trend -->
            <div class="bg-white p-8 rounded-[40px] shadow-sm border border-slate-50">
                <h3 class="text-xs font-black text-slate-400 uppercase tracking-widest mb-6 flex justify-between">แนวโน้มการใช้งาน (7 วัน) <span class="text-[9px] text-slate-300">LINE</span></h3>
                <canvas id="usageChart" height="200"></canvas>
            </div>

            <!-- Sparklines: Summary -->
            <div class="bg-white p-8 rounded-[40px] shadow-sm border border-slate-50">
                <h3 class="text-xs font-black text-slate-400 uppercase tracking-widest mb-6 flex justify-between">สรุปสถิติเชิงลึก <span class="text-[9px] text-slate-300">INLINE</span></h3>
                <div class="space-y-4 mt-4">
                    <div class="flex items-center justify-between">
                        <span class="text-[11px] font-bold text-slate-500">นักเรียนมา</span>
                        <div class="flex-1 px-4"><div class="h-1 bg-emerald-100 rounded-full overflow-hidden"><div class="bg-emerald-500 h-full" style="width: 85%"></div></div></div>
                        <span class="text-[11px] font-black text-slate-700">85%</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-[11px] font-bold text-slate-500">สาย/ลา</span>
                        <div class="flex-1 px-4"><div class="h-1 bg-amber-100 rounded-full overflow-hidden"><div class="bg-amber-500 h-full" style="width: 12%"></div></div></div>
                        <span class="text-[11px] font-black text-slate-700">12%</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-[11px] font-bold text-slate-500">ขาดเรียน</span>
                        <div class="flex-1 px-4"><div class="h-1 bg-rose-100 rounded-full overflow-hidden"><div class="bg-rose-500 h-full" style="width: 3%"></div></div></div>
                        <span class="text-[11px] font-black text-slate-700">3%</span>
                    </div>
                </div>
            </div>

        </section>            <tr class="group hover:bg-slate-50/50 transition-all">
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
