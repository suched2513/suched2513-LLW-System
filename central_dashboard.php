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

// --- Configuration & Filters ---
$currentYear = (int)($_GET['year'] ?? 2569);
$currentSemester = (int)($_GET['semester'] ?? 1);

// --- Handle Web Migration (One-click Update) ---
$updateMessage = '';
if (isset($_POST['run_migration'])) {
    try {
        $migrationFile = __DIR__ . '/database/migrations/2026_04_23_000002_enhance_student_demographics.php';
        if (file_exists($migrationFile)) {
            $migration = require $migrationFile;
            if (is_array($migration) && isset($migration['up'])) {
                $migration['up']($pdo);
                // Check if we need to record it
                $stmt = $pdo->prepare("INSERT IGNORE INTO _migrations (migration, batch) VALUES (?, (SELECT COALESCE(MAX(batch),0)+1 FROM _migrations))");
                $stmt->execute(['2026_04_23_000002_enhance_student_demographics']);
                $updateMessage = '<div class="bg-emerald-500 text-white p-4 rounded-2xl mb-6 font-bold shadow-lg shadow-emerald-200">✓ อัปเดตโครงสร้างฐานข้อมูล (เพศ/ปีการศึกษา) สำเร็จแล้ว!</div>';
            }
        }
    } catch (Exception $e) {
        $updateMessage = '<div class="bg-rose-500 text-white p-4 rounded-2xl mb-6 font-bold">✗ เกิดข้อผิดพลาด: ' . htmlspecialchars($e->getMessage()) . '</div>';
    }
}

// --- Fetch Real Stats ---
$today = date('Y-m-d');
try {
    // Check if columns exist (for safe UI rendering)
    $hasDemographics = false;
    try {
        $pdo->query("SELECT gender, academic_year FROM att_students LIMIT 1");
        $hasDemographics = true;
    } catch (Exception $e) { $hasDemographics = false; }

    $whereClause = $hasDemographics ? " WHERE academic_year = $currentYear AND semester = $currentSemester" : "";

    // 1. นักเรียนทั้งหมด (filtered)
    $countStudents = $pdo->query("SELECT COUNT(*) FROM att_students" . $whereClause)->fetchColumn() ?: 0;

    // 2. ใบลาที่รอดำเนินการ
    $stmtLeave = $pdo->prepare("SELECT COUNT(*) FROM tl_requests WHERE status = 'pending'");
    $stmtLeave->execute();
    $countPendingLeave = (int)$stmtLeave->fetchColumn();

    // 3. ห้องเรียนที่เช็คชื่อแล้ววันนี้
    $countTotalRooms = (int)$pdo->query("SELECT COUNT(DISTINCT classroom) FROM att_students" . $whereClause)->fetchColumn();

    $stmtChecked = $pdo->prepare("
        SELECT COUNT(DISTINCT s.classroom)
        FROM att_attendance a
        JOIN att_students s ON s.id = a.student_id
        WHERE a.date = ?" . ($hasDemographics ? " AND s.academic_year = $currentYear AND s.semester = $currentSemester" : "")
    );
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
        " . ($hasDemographics ? " WHERE s.academic_year = $currentYear AND s.semester = $currentSemester" : "") . "
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
        SELECT a.status, COUNT(*) as cnt
        FROM att_attendance a
        " . ($hasDemographics ? " JOIN att_students s ON a.student_id = s.id" : "") . "
        WHERE a.date BETWEEN ? AND ?" . ($hasDemographics ? " AND s.academic_year = $currentYear AND s.semester = $currentSemester" : "") . "
        GROUP BY a.status
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
        SELECT a.date, COUNT(*) cnt 
        FROM att_attendance a
        " . ($hasDemographics ? " JOIN att_students s ON a.student_id = s.id" : "") . "
        WHERE a.date >= DATE_SUB(?, INTERVAL 6 DAY) " . ($hasDemographics ? " AND s.academic_year = $currentYear AND s.semester = $currentSemester" : "") . "
        GROUP BY a.date 
        ORDER BY a.date
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

    // 9. จำนวนนักเรียนรายห้อง (Detailed with Gender)
    $enrollmentStats = [];
    $sqlEnrollment = "
        SELECT 
            s.classroom, 
            COUNT(DISTINCT s.id) as cnt,
            COUNT(DISTINCT CASE WHEN s.gender = 'ชาย' OR s.name LIKE 'เด็กชาย%' OR s.name LIKE 'นาย%' OR s.name LIKE 'ด.ช.%' THEN s.id END) as male_cnt,
            COUNT(DISTINCT CASE WHEN s.gender = 'หญิง' OR s.name LIKE 'เด็กหญิง%' OR s.name LIKE 'นางสาว%' OR s.name LIKE 'ด.ญ.%' THEN s.id END) as female_cnt,
            GROUP_CONCAT(DISTINCT u.firstname SEPARATOR ' / ') as advisors
        FROM att_students s
        LEFT JOIN llw_class_advisors ca ON s.classroom = ca.classroom
        LEFT JOIN llw_users u ON ca.user_id = u.user_id
        " . ($hasDemographics ? " WHERE s.academic_year = $currentYear AND s.semester = $currentSemester" : "") . "
        GROUP BY s.classroom 
        ORDER BY s.classroom
    ";
    
    try {
        $stmtEnrollment = $pdo->query($sqlEnrollment);
        $enrollmentStats = $stmtEnrollment->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Exception $e) {
        // Fallback for missing gender column
        $stmtFallback = $pdo->query("SELECT classroom, COUNT(*) as cnt, 0 as male_cnt, 0 as female_cnt, '' as advisors FROM att_students GROUP BY classroom ORDER BY classroom");
        $enrollmentStats = $stmtFallback->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
} catch (Exception $e) {
    error_log('[Dashboard Error] ' . $e->getMessage());
    $countStudents = 0; $countPendingLeave = 0; $countCheckedRooms = 0; $countTotalRooms = 0;
    $countRemainingRooms = 0; $roomProgress = 0; $countOnLeaveToday = 0;
    $recentActivity = []; $teacherStats = []; $attStatusData = [0,0,0,0,0];
    $trendLabels = []; $trendData = []; $userProportionLabels = []; $userProportionData = [];
    $enrollmentStats = []; $hasDemographics = false;
}

$activeSystem = 'portal';
$statsMonth = $statsMonth ?? date('Y-m');
$statsMonthLabel = $statsMonthLabel ?? date('F Y');
require_once 'components/layout_start.php';
?>
    
        
        <?= $updateMessage ?>

        <section class="mb-10 exec-banner">
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-8">
                <div>
                    <h1 class="text-4xl font-black tracking-tight">Executive Dashboard</h1>
                    <p class="text-slate-400 mt-2 flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                        สรุปสถิติจริง: <?= date('d F Y H:i') ?> น.
                    </p>
                </div>
                
                <!-- Filters -->
                <div class="flex gap-4 bg-white/5 p-2 rounded-3xl border border-white/10 backdrop-blur-md">
                    <form action="" method="GET" class="flex gap-2">
                        <select name="year" onchange="this.form.submit()" class="bg-slate-800 text-white text-xs font-bold px-4 py-3 rounded-2xl outline-none border border-white/5 focus:border-indigo-500">
                            <?php for($y=2569; $y<=2573; $y++): ?>
                                <option value="<?= $y ?>" <?= $currentYear == $y ? 'selected' : '' ?>>ปีการศึกษา <?= $y ?></option>
                            <?php endfor; ?>
                        </select>
                        <select name="semester" onchange="this.form.submit()" class="bg-slate-800 text-white text-xs font-bold px-4 py-3 rounded-2xl outline-none border border-white/5 focus:border-indigo-500">
                            <option value="1" <?= $currentSemester == 1 ? 'selected' : '' ?>>ภาคเรียนที่ 1</option>
                            <option value="2" <?= $currentSemester == 2 ? 'selected' : '' ?>>ภาคเรียนที่ 2</option>
                        </select>
                    </form>
                    <?php if(!$hasDemographics): ?>
                    <form action="" method="POST">
                        <button name="run_migration" class="bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-black px-6 py-3 rounded-2xl transition-all shadow-lg shadow-indigo-500/20">
                            <i class="bi bi-rocket-takeoff mr-2"></i> อัปเดตระบบ v2
                        </button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <!-- KPI Grid -->
        <section class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-6 gap-6 mb-10">
            <div class="glass-card p-6">
                <div class="w-12 h-12 rounded-2xl bg-indigo-500 text-white flex items-center justify-center text-xl mb-4 shadow-lg shadow-indigo-200"><i class="bi bi-people-fill"></i></div>
                <p class="text-3xl font-black text-slate-800"><?= number_format($countStudents) ?></p>
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mt-1">นักเรียนทั้งหมด</p>
            </div>
            <div class="glass-card p-6 border-l-4 border-l-amber-500">
                <div class="w-12 h-12 rounded-2xl bg-amber-500 text-white flex items-center justify-center text-xl mb-4 shadow-lg shadow-amber-200"><i class="bi bi-envelope-paper-fill"></i></div>
                <p class="text-3xl font-black text-slate-800"><?= $countPendingLeave ?></p>
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mt-1">ใบลาที่ค้างอยู่</p>
            </div>
            <div class="glass-card p-6">
                <div class="w-12 h-12 rounded-2xl bg-emerald-500 text-white flex items-center justify-center text-xl mb-4 shadow-lg shadow-emerald-200"><i class="bi bi-calendar-check-fill"></i></div>
                <p class="text-3xl font-black text-slate-800"><?= $countCheckedRooms ?></p>
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mt-1">ห้องที่เช็คแล้ว</p>
            </div>
            <div class="glass-card p-6">
                <div class="w-12 h-12 rounded-2xl bg-rose-500 text-white flex items-center justify-center text-xl mb-4 shadow-lg shadow-rose-200"><i class="bi bi-calendar-x-fill"></i></div>
                <p class="text-3xl font-black text-slate-800"><?= $countRemainingRooms ?></p>
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mt-1">ห้องที่ยังไม่เช็ค</p>
            </div>
            <div class="glass-card p-6">
                <div class="w-12 h-12 rounded-2xl bg-cyan-500 text-white flex items-center justify-center text-xl mb-4 shadow-lg shadow-cyan-200"><i class="bi bi-person-workspace"></i></div>
                <p class="text-3xl font-black text-slate-800"><?= $countOnLeaveToday ?></p>
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mt-1">บุคลากรลาวันนี้</p>
            </div>
            <div class="glass-card p-6">
                <div class="w-12 h-12 rounded-2xl bg-slate-800 text-white flex items-center justify-center text-xl mb-4 shadow-lg shadow-slate-200"><i class="bi bi-lightning-fill"></i></div>
                <p class="text-3xl font-black text-slate-800"><?= count($recentActivity) ?></p>
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mt-1">กิจกรรมล่าสุด</p>
            </div>
        </section>

        <!-- Charts Section -->
        <section class="grid grid-cols-1 lg:grid-cols-3 gap-8 mb-10">
            <div class="glass-card p-8 min-h-[300px] flex flex-col">
                <h3 class="text-xs font-black text-slate-400 uppercase tracking-[0.2em] mb-6 flex justify-between items-center">
                    สถานะการเช็คชื่อ
                    <span class="stat-badge bg-blue-50 text-blue-600">Monthly</span>
                </h3>
                <div class="flex-1 relative">
                    <?php if(array_sum($attStatusData) > 0): ?>
                        <canvas id="attChart"></canvas>
                    <?php else: ?>
                        <div class="absolute inset-0 flex flex-col items-center justify-center text-slate-300 opacity-50">
                            <i class="bi bi-bar-chart text-4xl mb-2"></i>
                            <p class="text-[10px] font-black uppercase tracking-widest">ยังไม่มีข้อมูลการเช็คชื่อปี <?= $currentYear ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="glass-card p-8 min-h-[300px] flex flex-col">
                <h3 class="text-xs font-black text-slate-400 uppercase tracking-[0.2em] mb-6 flex justify-between items-center">
                    แนวโน้ม 7 วันล่าสุด
                    <span class="stat-badge bg-emerald-50 text-emerald-600">Trend</span>
                </h3>
                <div class="flex-1 relative">
                    <?php if(array_sum($trendData) > 0): ?>
                        <canvas id="trendChart"></canvas>
                    <?php else: ?>
                        <div class="absolute inset-0 flex flex-col items-center justify-center text-slate-300 opacity-50">
                            <i class="bi bi-graph-up text-4xl mb-2"></i>
                            <p class="text-[10px] font-black uppercase tracking-widest">รอการบันทึกกิจกรรมย้อนหลัง</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="glass-card p-8 min-h-[300px] flex flex-col">
                <h3 class="text-xs font-black text-slate-400 uppercase tracking-[0.2em] mb-6 flex justify-between items-center">
                    สัดส่วนผู้ใช้งาน
                    <span class="stat-badge bg-indigo-50 text-indigo-600">Roles</span>
                </h3>
                <div class="flex-1 relative">
                    <?php if(array_sum($userProportionData) > 0): ?>
                        <canvas id="roleChart"></canvas>
                    <?php else: ?>
                        <div class="absolute inset-0 flex flex-col items-center justify-center text-slate-300 opacity-50">
                            <i class="bi bi-pie-chart text-4xl mb-2"></i>
                            <p class="text-[10px] font-black uppercase tracking-widest">ไม่พบข้อมูลผู้ใช้งาน</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <!-- Detailed Enrollment Grid -->
        <section class="mb-10">
            <div class="flex items-center justify-between mb-8">
                <div>
                    <h2 class="text-2xl font-black text-slate-800">จำนวนนักเรียนแยกรายห้อง</h2>
                    <p class="text-xs text-slate-400 font-bold mt-1 uppercase tracking-widest">Enrollment Breakdown by Gender</p>
                </div>
                <div class="flex gap-2">
                    <span class="stat-badge bg-blue-500 text-white">ชาย</span>
                    <span class="stat-badge bg-pink-500 text-white">หญิง</span>
                </div>
            </div>
            
            <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-6">
                <?php foreach($enrollmentStats as $room): ?>
                <div class="glass-card p-6 group">
                    <div class="flex justify-between items-start mb-4">
                        <div class="w-10 h-10 bg-slate-100 rounded-xl flex items-center justify-center font-black text-xs text-slate-500 group-hover:bg-indigo-600 group-hover:text-white transition-all">
                            <?= explode('/', $room['classroom'])[0] ?>
                        </div>
                        <i class="bi bi-chevron-right text-slate-200 group-hover:text-indigo-600 transition-all"></i>
                    </div>
                    <h4 class="text-xl font-black text-slate-800 tracking-tighter"><?= htmlspecialchars($room['classroom']) ?></h4>
                    <p class="text-[10px] font-bold text-slate-400 mt-1 truncate" title="<?= htmlspecialchars($room['advisors']) ?>">
                        <?= $room['advisors'] ? 'ครู' . $room['advisors'] : 'ยังไม่ระบุครูที่ปรึกษา' ?>
                    </p>
                    
                    <div class="mt-6 pt-4 border-t border-slate-50 flex items-center justify-between">
                        <div>
                            <p class="text-[8px] font-black text-slate-300 uppercase tracking-widest">Total</p>
                            <p class="text-3xl font-black text-slate-800 leading-none"><?= $room['cnt'] ?></p>
                        </div>
                        <div class="text-right flex flex-col gap-1">
                            <span class="px-2 py-1 rounded-lg bg-blue-50 text-blue-600 text-[10px] font-black">♂ <?= $room['male_cnt'] ?></span>
                            <span class="px-2 py-1 rounded-lg bg-pink-50 text-pink-600 text-[10px] font-black">♀ <?= $room['female_cnt'] ?></span>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- Teacher Performance Section -->
        <section class="glass-card overflow-hidden">
            <div class="p-8 border-b border-slate-100 flex flex-col md:flex-row md:items-center justify-between gap-6">
                <div>
                    <h3 class="text-lg font-black text-slate-800 flex items-center gap-2">
                        <i class="bi bi-graph-up-arrow text-indigo-600"></i>
                        สถิติการใช้งานแยกตามครูผู้สอน
                    </h3>
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mt-1">Monthly Usage Performance Index</p>
                </div>
                <form method="GET" class="flex gap-3">
                    <input type="hidden" name="year" value="<?= $currentYear ?>">
                    <input type="hidden" name="semester" value="<?= $currentSemester ?>">
                    <input type="month" name="stats_month" value="<?= $statsMonth ?>" onchange="this.form.submit()" 
                           class="bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-xs font-bold outline-none focus:ring-2 focus:ring-indigo-500">
                </form>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-xs text-left">
                    <thead class="bg-slate-50/50 text-[10px] font-black text-slate-400 uppercase tracking-[0.2em]">
                        <tr>
                            <th class="px-8 py-5">ชื่อ-นามสกุล ครูผู้สอน</th>
                            <th class="px-6 py-5 text-center">วิชา</th>
                            <th class="px-6 py-5 text-center">คาบรวม</th>
                            <th class="px-6 py-5 text-center">วันที่เข้าใช้</th>
                            <th class="px-6 py-5 text-center">เดือนนี้</th>
                            <th class="px-6 py-5 text-center">ใช้ล่าสุด</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        <?php foreach($teacherStats as $t): ?>
                        <tr class="hover:bg-slate-50/30 transition-all">
                            <td class="px-8 py-5">
                                <div class="flex items-center gap-4">
                                    <div class="w-10 h-10 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center font-black text-xs">
                                        <?= llw_sub($t['name'], 0, 1) ?>
                                    </div>
                                    <span class="font-bold text-slate-700"><?= htmlspecialchars($t['name']) ?></span>
                                </div>
                            </td>
                            <td class="px-6 py-5 text-center font-black text-slate-500"><?= $t['subject_count'] ?></td>
                            <td class="px-6 py-5 text-center font-black text-slate-800"><?= number_format($t['total_records']) ?></td>
                            <td class="px-6 py-5 text-center font-black text-blue-600"><?= $t['active_days'] ?> วัน</td>
                            <td class="px-6 py-5 text-center">
                                <span class="px-3 py-1.5 rounded-xl bg-emerald-50 text-emerald-600 font-black"><?= $t['this_month'] ?></span>
                            </td>
                            <td class="px-6 py-5 text-center text-slate-400 font-bold"><?= $t['last_active'] ?: '-' ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>

    <script>
        const chartOptions = {
            plugins: { legend: { display: false } },
            scales: { 
                y: { beginAtZero: true, grid: { display: false }, ticks: { font: { size: 9, weight: 'bold' } } },
                x: { grid: { display: false }, ticks: { font: { size: 9, weight: 'bold' } } }
            },
            responsive: true,
            maintainAspectRatio: false
        };

        const ctxAtt = document.getElementById('attChart');
        new Chart(ctxAtt, { 
            type: 'bar', 
            data: { 
                labels: ['มา','ขาด','ลา','โดด','สาย'], 
                datasets: [{ 
                    data: <?= json_encode($attStatusData) ?>, 
                    backgroundColor: ['#10b981','#f43f5e','#f59e0b','#8b5cf6','#f97316'], 
                    borderRadius: 12 
                }] 
            }, 
            options: chartOptions 
        });

        const ctxTrend = document.getElementById('trendChart');
        new Chart(ctxTrend, { 
            type: 'line', 
            data: { 
                labels: <?= json_encode($trendLabels) ?>, 
                datasets: [{ 
                    data: <?= json_encode($trendData) ?>, 
                    borderColor: '#6366f1', 
                    tension: 0.4, 
                    fill: true, 
                    backgroundColor: 'rgba(99, 102, 241, 0.05)',
                    pointRadius: 4,
                    pointBackgroundColor: '#fff',
                    pointBorderWidth: 2
                }] 
            }, 
            options: chartOptions 
        });

        const ctxRole = document.getElementById('roleChart');
        if (ctxRole) {
            new Chart(ctxRole, { 
                type: 'doughnut', 
                data: { 
                    labels: <?= json_encode($userProportionLabels) ?>, 
                    datasets: [{ 
                        data: <?= json_encode($userProportionData) ?>, 
                        backgroundColor: ['#6366f1','#ec4899','#3b82f6','#10b981','#f59e0b'],
                        borderWidth: 0,
                        hoverOffset: 15
                    }] 
                }, 
                options: { 
                    cutout: '75%',
                    plugins: { legend: { position: 'bottom', labels: { usePointStyle: true, font: { size: 9, weight: 'bold' } } } },
                    maintainAspectRatio: false
                } 
            });
        }
    </script>
<?php require_once 'components/layout_end.php'; ?>
