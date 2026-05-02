<?php
/**
 * leave_stats.php — สถิติการขออนุญาตออกนอกบริเวณ
 */
session_start();
require_once 'config.php';

if (!isset($_SESSION['llw_role'])) {
    header('Location: login.php'); exit();
}
// อนุญาต: super_admin, wfh_admin, wfh_staff, att_teacher (ดูเฉพาะของตัวเอง)
$isAdmin = in_array($_SESSION['llw_role'], ['super_admin', 'wfh_admin']);
$myId    = (int)$_SESSION['user_id'];

$pdo = getPdo();

// ─── Filter ──────────────────────────────────────────────
$filterYear   = (int)($_GET['year']   ?? date('Y'));
$filterMonth  = (int)($_GET['month']  ?? 0);
$filterPerson = (int)($_GET['person'] ?? 0); // 0 = ทุกคน

// ─── ดึงรายชื่อสำหรับ dropdown (admin เท่านั้น) ──────────────────
$personList = [];
if ($isAdmin) {
    $personList = $pdo->query("
        SELECT DISTINCT r.teacher_id, CONCAT(u.firstname,' ',u.lastname) as name
        FROM leave_requests r
        JOIN llw_users u ON r.teacher_id = u.user_id
        ORDER BY name
    ")->fetchAll(PDO::FETCH_ASSOC);
}

// ─── KPI ─────────────────────────────────────────────────
$whereBase  = $isAdmin ? "WHERE YEAR(req_date) = ?" : "WHERE teacher_id = ? AND YEAR(req_date) = ?";
$paramsKpi  = $isAdmin ? [$filterYear] : [$myId, $filterYear];
if ($filterMonth  > 0 && $isAdmin) { $whereBase .= " AND MONTH(req_date) = ?";  $paramsKpi[] = $filterMonth; }
elseif ($filterMonth > 0)          { $whereBase .= " AND MONTH(req_date) = ?";  $paramsKpi[] = $filterMonth; }
if ($isAdmin && $filterPerson > 0) { $whereBase .= " AND teacher_id = ?"; $paramsKpi[] = $filterPerson; }

$stmt = $pdo->prepare("SELECT
    COUNT(*) as total,
    SUM(CASE WHEN status_boss1=1 THEN 1 ELSE 0 END) as approved,
    SUM(CASE WHEN status_boss1=0 THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN status_boss1=2 THEN 1 ELSE 0 END) as rejected,
    SUM(total_hr) as total_hours
    FROM leave_requests $whereBase");
$stmt->execute($paramsKpi);
$kpi = $stmt->fetch(PDO::FETCH_ASSOC);

// ─── แนวโน้มรายเดือน ──────────────────────────────────────
$whereMonthly  = $isAdmin ? "" : "AND teacher_id = ?";
$paramsMonthly = $isAdmin ? [$filterYear] : [$filterYear, $myId];
if ($isAdmin && $filterPerson > 0) { $whereMonthly .= " AND teacher_id = ?"; $paramsMonthly[] = $filterPerson; }
$stmtMonthly = $pdo->prepare("SELECT
    MONTH(req_date) as m,
    COUNT(*) as cnt,
    SUM(total_hr) as hrs
    FROM leave_requests
    WHERE YEAR(req_date) = ? $whereMonthly
    GROUP BY MONTH(req_date)
    ORDER BY m");
$stmtMonthly->execute($paramsMonthly);
$monthlyRaw = $stmtMonthly->fetchAll(PDO::FETCH_ASSOC);
$monthlyData = array_fill(1, 12, 0);
$monthlyHrs  = array_fill(1, 12, 0);
foreach ($monthlyRaw as $row) {
    $monthlyData[(int)$row['m']] = (int)$row['cnt'];
    $monthlyHrs[(int)$row['m']]  = (float)$row['hrs'];
}

// ─── แยกตามประเภทเหตุผล ────────────────────────────────────
$whereType  = $isAdmin ? "WHERE YEAR(req_date) = ?" : "WHERE teacher_id = ? AND YEAR(req_date) = ?";
$paramsType = $isAdmin ? [$filterYear] : [$myId, $filterYear];
if ($filterMonth  > 0) { $whereType .= " AND MONTH(req_date) = ?"; $paramsType[] = $filterMonth; }
if ($isAdmin && $filterPerson > 0) { $whereType .= " AND teacher_id = ?"; $paramsType[] = $filterPerson; }
$stmtType = $pdo->prepare("SELECT reason_type, COUNT(*) as cnt FROM leave_requests $whereType GROUP BY reason_type ORDER BY cnt DESC");
$stmtType->execute($paramsType);
$typeData = $stmtType->fetchAll(PDO::FETCH_ASSOC);

// ─── รายบุคคล (admin เท่านั้น) ────────────────────────────
$perPerson = [];
if ($isAdmin) {
    $whereP  = "WHERE YEAR(r.req_date) = ?";
    $paramsP = [$filterYear];
    if ($filterMonth  > 0) { $whereP .= " AND MONTH(r.req_date) = ?"; $paramsP[] = $filterMonth; }
    if ($filterPerson > 0) { $whereP .= " AND r.teacher_id = ?";      $paramsP[] = $filterPerson; }
    $stmtP = $pdo->prepare("SELECT
        CONCAT(u.firstname,' ',u.lastname) as name,
        COUNT(*) as cnt,
        SUM(r.total_hr) as hrs,
        SUM(CASE WHEN r.status_boss1=1 THEN 1 ELSE 0 END) as approved
        FROM leave_requests r
        JOIN llw_users u ON r.teacher_id = u.user_id
        $whereP
        GROUP BY r.teacher_id
        ORDER BY cnt DESC
        LIMIT 15");
    $stmtP->execute($paramsP);
    $perPerson = $stmtP->fetchAll(PDO::FETCH_ASSOC);
}

// ─── ตารางย้อนหลัง ─────────────────────────────────────────
$whereList  = $isAdmin ? "WHERE YEAR(r.req_date) = ?" : "WHERE r.teacher_id = ? AND YEAR(r.req_date) = ?";
$paramsList = $isAdmin ? [$filterYear] : [$myId, $filterYear];
if ($filterMonth  > 0) { $whereList .= " AND MONTH(r.req_date) = ?";  $paramsList[] = $filterMonth; }
if ($isAdmin && $filterPerson > 0) { $whereList .= " AND r.teacher_id = ?"; $paramsList[] = $filterPerson; }
$stmtList = $pdo->prepare("SELECT r.*,
    CONCAT(u.firstname,' ',u.lastname) as t_name
    FROM leave_requests r
    JOIN llw_users u ON r.teacher_id = u.user_id
    $whereList
    ORDER BY r.req_date DESC
    LIMIT 50");
$stmtList->execute($paramsList);
$requests = $stmtList->fetchAll(PDO::FETCH_ASSOC);

$thMonths = ['','ม.ค.','ก.พ.','มี.ค.','เม.ย.','พ.ค.','มิ.ย.','ก.ค.','ส.ค.','ก.ย.','ต.ค.','พ.ย.','ธ.ค.'];
$typeColors = [
    'ติดต่อราชการ'      => ['bg' => '#3b82f6', 'light' => 'bg-blue-100 text-blue-700'],
    'ป่วย / รักษาพยาบาล'=> ['bg' => '#ef4444', 'light' => 'bg-rose-100 text-rose-700'],
    'ธุระส่วนตัว'       => ['bg' => '#f59e0b', 'light' => 'bg-amber-100 text-amber-700'],
    'อบรม / พัฒนาตนเอง' => ['bg' => '#8b5cf6', 'light' => 'bg-purple-100 text-purple-700'],
    'กิจกรรมโรงเรียน'   => ['bg' => '#10b981', 'light' => 'bg-emerald-100 text-emerald-700'],
    'อื่นๆ'             => ['bg' => '#94a3b8', 'light' => 'bg-slate-100 text-slate-600'],
];

$pageTitle    = 'สถิติการขออนุญาต';
$pageSubtitle = 'ภาพรวมและรายงานแบบ Visual';
$activeSystem = 'leave';
require_once __DIR__ . '/components/layout_start.php';
?>

<?php
// JSON for Charts
$monthLabels  = json_encode(array_values($thMonths));
$monthCntData = json_encode(array_values($monthlyData));
$monthHrsData = json_encode(array_values($monthlyHrs));
$typeLabels   = json_encode(array_column($typeData, 'reason_type'));
$typeCounts   = json_encode(array_column($typeData, 'cnt'));
$typeBgColors = json_encode(array_map(fn($t) => $typeColors[$t['reason_type']]['bg'] ?? '#94a3b8', $typeData));
$personNames  = json_encode(array_column($perPerson, 'name'));
$personCounts = json_encode(array_column($perPerson, 'cnt'));
$personHrs    = json_encode(array_column($perPerson, 'hrs'));
?>

<div class="flex flex-wrap items-center gap-3 mb-8 bg-white rounded-2xl shadow-sm border border-slate-100 p-4">
    <i class="bi bi-funnel-fill text-indigo-500 text-lg"></i>
    <span class="text-sm font-black text-slate-600">ตัวกรอง:</span>
    <form method="GET" class="flex flex-wrap gap-3 items-center flex-1">
        <select name="year" onchange="this.form.submit()"
            class="bg-slate-50 border border-slate-200 rounded-xl px-4 py-2 text-sm font-bold focus:ring-2 focus:ring-indigo-400 outline-none">
            <?php for ($y = date('Y'); $y >= date('Y')-3; $y--): ?>
            <option value="<?= $y ?>" <?= $y == $filterYear ? 'selected' : '' ?>>ปี <?= $y+543 ?></option>
            <?php endfor; ?>
        </select>
        <select name="month" onchange="this.form.submit()"
            class="bg-slate-50 border border-slate-200 rounded-xl px-4 py-2 text-sm font-bold focus:ring-2 focus:ring-indigo-400 outline-none">
            <option value="0" <?= $filterMonth===0 ? 'selected' : '' ?>>ทั้งปี</option>
            <?php foreach ($thMonths as $n => $lbl): if ($n===0) continue; ?>
            <option value="<?= $n ?>" <?= $n==$filterMonth ? 'selected' : '' ?>><?= $lbl ?></option>
            <?php endforeach; ?>
        </select>
        <?php if ($isAdmin && !empty($personList)): ?>
        <select name="person" onchange="this.form.submit()"
            class="bg-slate-50 border border-slate-200 rounded-xl px-4 py-2 text-sm font-bold focus:ring-2 focus:ring-indigo-400 outline-none min-w-[180px]">
            <option value="0" <?= $filterPerson===0 ? 'selected' : '' ?>><i class="bi bi-people"></i> ทุกคน</option>
            <?php foreach ($personList as $pl): ?>
            <option value="<?= $pl['teacher_id'] ?>" <?= $pl['teacher_id']==$filterPerson ? 'selected' : '' ?>>
                <?= htmlspecialchars($pl['name'], ENT_QUOTES) ?>
            </option>
            <?php endforeach; ?>
        </select>
        <?php endif; ?>
        <?php if ($filterMonth || $filterYear != date('Y') || $filterPerson): ?>
        <a href="leave_stats.php" class="text-xs font-bold text-slate-400 hover:text-rose-500 transition-all">✕ ล้างตัวกรอง</a>
        <?php endif; ?>
    </form>
    <a href="leave_system.php" class="flex items-center gap-2 text-sm font-bold text-indigo-600 bg-indigo-50 px-4 py-2 rounded-xl hover:bg-indigo-600 hover:text-white transition-all">
        <i class="bi bi-arrow-left"></i> กลับ
    </a>
</div>

<!-- KPI Cards -->
<div class="grid grid-cols-2 lg:grid-cols-5 gap-5 mb-8">
    <div class="bg-gradient-to-br from-indigo-500 to-blue-600 p-5 rounded-2xl text-white shadow-xl shadow-indigo-200/50 col-span-2 lg:col-span-1">
        <p class="text-xs font-black uppercase tracking-widest opacity-80">คำขอทั้งหมด</p>
        <p class="text-4xl font-black mt-1"><?= number_format($kpi['total'] ?? 0) ?></p>
        <p class="text-xs opacity-70 mt-1">รวม <?= number_format($kpi['total_hours'] ?? 0, 1) ?> ชม.</p>
    </div>
    <div class="bg-gradient-to-br from-emerald-500 to-teal-600 p-5 rounded-2xl text-white shadow-xl shadow-emerald-200/50">
        <p class="text-xs font-black uppercase tracking-widest opacity-80">อนุญาต</p>
        <p class="text-4xl font-black mt-1"><?= $kpi['approved'] ?? 0 ?></p>
        <p class="text-xs opacity-70 mt-1"><?= $kpi['total'] ? round(($kpi['approved']/$kpi['total'])*100) : 0 ?>% ของทั้งหมด</p>
    </div>
    <div class="bg-gradient-to-br from-amber-500 to-orange-500 p-5 rounded-2xl text-white shadow-xl shadow-amber-200/50">
        <p class="text-xs font-black uppercase tracking-widest opacity-80">รออนุญาต</p>
        <p class="text-4xl font-black mt-1"><?= $kpi['pending'] ?? 0 ?></p>
        <p class="text-xs opacity-70 mt-1">รอการพิจารณา</p>
    </div>
    <div class="bg-gradient-to-br from-rose-500 to-pink-600 p-5 rounded-2xl text-white shadow-xl shadow-rose-200/50">
        <p class="text-xs font-black uppercase tracking-widest opacity-80">ไม่อนุญาต</p>
        <p class="text-4xl font-black mt-1"><?= $kpi['rejected'] ?? 0 ?></p>
        <p class="text-xs opacity-70 mt-1">ปฏิเสธ</p>
    </div>
    <div class="bg-white border border-slate-100 shadow-sm p-5 rounded-2xl">
        <p class="text-xs font-black uppercase tracking-widest text-slate-400">เวลารวม</p>
        <p class="text-4xl font-black text-slate-800 mt-1"><?= number_format($kpi['total_hours'] ?? 0, 1) ?></p>
        <p class="text-xs text-slate-400 mt-1">ชั่วโมงสะสม</p>
    </div>
</div>

<!-- Charts Row 1 -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">

    <!-- Line Chart: แนวโน้มรายเดือน -->
    <div class="lg:col-span-2 bg-white rounded-2xl shadow-sm border border-slate-100 p-6">
        <div class="flex items-center justify-between mb-6">
            <div>
                <h3 class="font-black text-slate-800 flex items-center gap-2">
                    <i class="bi bi-graph-up-arrow text-indigo-600"></i> แนวโน้มรายเดือน
                </h3>
                <p class="text-xs text-slate-400 mt-0.5">จำนวนคำขอและชั่วโมง ปี <?= $filterYear+543 ?></p>
            </div>
        </div>
        <canvas id="monthlyChart" height="120"></canvas>
    </div>

    <!-- Donut: แยกตามประเภท -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6">
        <h3 class="font-black text-slate-800 flex items-center gap-2 mb-4">
            <i class="bi bi-pie-chart-fill text-rose-500"></i> แยกตามประเภท
        </h3>
        <?php if (empty($typeData)): ?>
        <div class="flex items-center justify-center h-40 text-slate-300">
            <div class="text-center"><i class="bi bi-inbox text-4xl"></i><p class="text-sm mt-2">ยังไม่มีข้อมูล</p></div>
        </div>
        <?php else: ?>
        <div class="flex justify-center"><canvas id="typeChart" height="200" style="max-height:200px"></canvas></div>
        <div class="mt-4 space-y-2">
            <?php foreach ($typeData as $t):
                $col = $typeColors[$t['reason_type']]['light'] ?? 'bg-slate-100 text-slate-600';
            ?>
            <div class="flex items-center justify-between">
                <span class="text-xs px-2 py-0.5 rounded-full font-bold <?= $col ?>"><?= htmlspecialchars($t['reason_type'], ENT_QUOTES) ?></span>
                <span class="text-sm font-black text-slate-700"><?= $t['cnt'] ?> ครั้ง</span>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Bar Chart: รายบุคคล (admin only) -->
<?php if ($isAdmin && !empty($perPerson)): ?>
<div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6 mb-6">
    <h3 class="font-black text-slate-800 flex items-center gap-2 mb-2">
        <i class="bi bi-person-lines-fill text-blue-600"></i> สถิติรายบุคคล
    </h3>
    <p class="text-xs text-slate-400 mb-6">จำนวนครั้งที่ขออนุญาต (สูงสุด 15 คน)</p>
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <canvas id="personChart" height="200"></canvas>
        <div class="overflow-y-auto max-h-64">
            <table class="w-full text-sm">
                <thead><tr>
                    <th class="text-left text-xs font-black text-slate-400 uppercase tracking-widest pb-3">#</th>
                    <th class="text-left text-xs font-black text-slate-400 uppercase tracking-widest pb-3">ชื่อ</th>
                    <th class="text-center text-xs font-black text-slate-400 uppercase tracking-widest pb-3">ครั้ง</th>
                    <th class="text-center text-xs font-black text-slate-400 uppercase tracking-widest pb-3">ชม.</th>
                    <th class="text-center text-xs font-black text-slate-400 uppercase tracking-widest pb-3">อนุญาต</th>
                </tr></thead>
                <tbody class="divide-y divide-slate-50">
                <?php foreach ($perPerson as $i => $p): ?>
                <tr class="hover:bg-slate-50/50">
                    <td class="py-2 text-slate-400 font-bold"><?= $i+1 ?></td>
                    <td class="py-2 font-bold text-slate-700"><?= htmlspecialchars($p['name'], ENT_QUOTES) ?></td>
                    <td class="py-2 text-center">
                        <span class="px-2 py-0.5 bg-indigo-50 text-indigo-600 rounded-full text-xs font-black"><?= $p['cnt'] ?></span>
                    </td>
                    <td class="py-2 text-center text-slate-500"><?= number_format($p['hrs'],1) ?></td>
                    <td class="py-2 text-center">
                        <span class="px-2 py-0.5 bg-emerald-50 text-emerald-600 rounded-full text-xs font-black"><?= $p['approved'] ?></span>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ตารางรายการล่าสุด -->
<div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
    <div class="px-6 py-5 border-b border-slate-100 flex items-center justify-between">
        <h3 class="font-black text-slate-800 flex items-center gap-2">
            <i class="bi bi-table text-slate-400"></i> รายการทั้งหมด
        </h3>
        <span class="text-xs text-slate-400"><?= count($requests) ?> รายการ</span>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-5 py-4 text-left text-xs font-black text-slate-400 uppercase tracking-widest">วันที่</th>
                    <?php if ($isAdmin): ?>
                    <th class="px-5 py-4 text-left text-xs font-black text-slate-400 uppercase tracking-widest">ผู้ขอ</th>
                    <?php endif; ?>
                    <th class="px-5 py-4 text-left text-xs font-black text-slate-400 uppercase tracking-widest">ประเภท</th>
                    <th class="px-5 py-4 text-left text-xs font-black text-slate-400 uppercase tracking-widest">เหตุผล</th>
                    <th class="px-5 py-4 text-center text-xs font-black text-slate-400 uppercase tracking-widest">ชม.</th>
                    <th class="px-5 py-4 text-center text-xs font-black text-slate-400 uppercase tracking-widest">สถานะ</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
            <?php if (empty($requests)): ?>
                <tr><td colspan="6" class="py-12 text-center text-slate-300 font-bold italic">ไม่พบข้อมูลในช่วงที่เลือก</td></tr>
            <?php endif; ?>
            <?php foreach ($requests as $r):
                $col  = $typeColors[$r['reason_type'] ?? 'อื่นๆ']['light'] ?? 'bg-slate-100 text-slate-600';
                $sBadge = match((int)$r['status_boss1']) {
                    1 => '<span class="px-3 py-1 bg-emerald-50 text-emerald-600 rounded-full text-xs font-black">✓ อนุญาต</span>',
                    2 => '<span class="px-3 py-1 bg-rose-50 text-rose-600 rounded-full text-xs font-black">✗ ไม่อนุญาต</span>',
                    default => '<span class="px-3 py-1 bg-amber-50 text-amber-600 rounded-full text-xs font-black">⏳ รออนุญาต</span>',
                };
                $d = date('j', strtotime($r['req_date'] ?? date('Y-m-d')));
                $m = $thMonths[(int)date('n', strtotime($r['req_date'] ?? date('Y-m-d')))];
                $y = date('Y', strtotime($r['req_date'] ?? date('Y-m-d'))) + 543;
            ?>
            <tr class="hover:bg-slate-50/50">
                <td class="px-5 py-3 font-bold text-slate-600"><?= "$d $m $y" ?></td>
                <?php if ($isAdmin): ?>
                <td class="px-5 py-3 font-bold text-slate-800"><?= htmlspecialchars($r['t_name'] ?? '', ENT_QUOTES) ?></td>
                <?php endif; ?>
                <td class="px-5 py-3">
                    <span class="px-2 py-0.5 rounded-full text-xs font-black <?= $col ?>">
                        <?= htmlspecialchars($r['reason_type'] ?? 'อื่นๆ', ENT_QUOTES) ?>
                    </span>
                </td>
                <td class="px-5 py-3 text-slate-500 max-w-[200px] truncate"><?= htmlspecialchars($r['reason'] ?? '', ENT_QUOTES) ?></td>
                <td class="px-5 py-3 text-center font-bold text-slate-700"><?= number_format($r['total_hr'] ?? 0, 1) ?></td>
                <td class="px-5 py-3 text-center"><?= $sBadge ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
// ── Monthly Trend ──────────────────────────────────────────────────
const monthLabels  = <?= $monthLabels ?>.slice(1); // ตัด index 0 ออก
const monthCntData = <?= $monthCntData ?>.slice(1);
const monthHrsData = <?= $monthHrsData ?>.slice(1);

new Chart(document.getElementById('monthlyChart').getContext('2d'), {
    type: 'bar',
    data: {
        labels: monthLabels,
        datasets: [
            {
                type: 'line',
                label: 'ชั่วโมง',
                data: monthHrsData,
                borderColor: '#f59e0b',
                backgroundColor: 'rgba(245,158,11,0.1)',
                tension: 0.4,
                yAxisID: 'y2',
                pointRadius: 4,
                pointBackgroundColor: '#f59e0b',
                fill: false,
                borderWidth: 2
            },
            {
                type: 'bar',
                label: 'จำนวนคำขอ',
                data: monthCntData,
                backgroundColor: 'rgba(99,102,241,0.7)',
                borderRadius: 8,
                yAxisID: 'y'
            }
        ]
    },
    options: {
        responsive: true,
        interaction: { mode: 'index' },
        plugins: {
            legend: { position: 'top', labels: { usePointStyle: true, font: { size: 11 } } }
        },
        scales: {
            y:  { beginAtZero: true, position: 'left',  grid: { display: false }, ticks: { precision: 0, font: {size:10} } },
            y2: { beginAtZero: true, position: 'right', grid: { display: false }, ticks: { font: {size:10} } },
            x:  { grid: { display: false }, ticks: { font: {size:10} } }
        }
    }
});

// ── Type Donut ────────────────────────────────────────────────────
<?php if (!empty($typeData)): ?>
new Chart(document.getElementById('typeChart').getContext('2d'), {
    type: 'doughnut',
    data: {
        labels: <?= $typeLabels ?>,
        datasets: [{ data: <?= $typeCounts ?>, backgroundColor: <?= $typeBgColors ?>, borderWidth: 0, hoverOffset: 16 }]
    },
    options: {
        cutout: '68%',
        plugins: { legend: { display: false } }
    }
});
<?php endif; ?>

// ── Per Person Bar ────────────────────────────────────────────────
<?php if ($isAdmin && !empty($perPerson)): ?>
new Chart(document.getElementById('personChart').getContext('2d'), {
    type: 'bar',
    data: {
        labels: <?= $personNames ?>,
        datasets: [{
            label: 'จำนวนครั้ง',
            data: <?= $personCounts ?>,
            backgroundColor: 'rgba(99,102,241,0.75)',
            borderRadius: 8,
        },{
            label: 'ชั่วโมง',
            data: <?= $personHrs ?>,
            backgroundColor: 'rgba(245,158,11,0.6)',
            borderRadius: 8,
        }]
    },
    options: {
        indexAxis: 'y',
        responsive: true,
        plugins: { legend: { position: 'top', labels: { usePointStyle: true, font: {size:10} } } },
        scales: {
            x: { beginAtZero: true, grid: { display: false }, ticks: { precision:0, font:{size:9} } },
            y: { grid: { display: false }, ticks: { font: {size:10} } }
        }
    }
});
<?php endif; ?>
</script>

<?php require_once __DIR__ . '/components/layout_end.php'; ?>
