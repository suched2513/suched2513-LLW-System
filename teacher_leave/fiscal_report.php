<?php
session_start();
require_once '../config.php';
require_once 'includes/functions.php';

if (!isset($_SESSION['llw_role'])) { header('Location: ' . $base_path . '/login.php'); exit(); }
if (!in_array($_SESSION['llw_role'], ['super_admin', 'wfh_admin'])) {
    header('Location: ' . $base_path . '/teacher_leave/index.php'); exit();
}

$pdo = getPdo();

$typeMap = [
    'sick'      => ['label' => 'ลาป่วย',      'color' => '#e11d48'],
    'personal'  => ['label' => 'ลากิจ',        'color' => '#d97706'],
    'vacation'  => ['label' => 'ลาพักผ่อน',   'color' => '#2563eb'],
    'maternity' => ['label' => 'ลาคลอดบุตร',  'color' => '#db2777'],
    'other'     => ['label' => 'ลาอื่นๆ',      'color' => '#64748b'],
];

// ── เลือกปีงบประมาณ ──
$currentFY = getThaiFiscalYear();
$fyOptions = $pdo->query("SELECT DISTINCT fiscal_year FROM tl_requests ORDER BY fiscal_year DESC")->fetchAll(PDO::FETCH_COLUMN);
if (!in_array($currentFY, $fyOptions)) { array_unshift($fyOptions, $currentFY); }
$selectedFY = isset($_GET['fiscal_year']) ? (int)$_GET['fiscal_year'] : $currentFY;

// ── ตารางรายคน (tl_stats join llw_users) ──
$stmt = $pdo->prepare("
    SELECT u.user_id, u.firstname, u.lastname, u.position,
           COALESCE(s.sick_taken, 0)     AS sick_taken,
           COALESCE(s.personal_taken, 0) AS personal_taken,
           COALESCE(s.vacation_taken, 0) AS vacation_taken,
           COALESCE(s.vacation_quota, 10.0) AS vacation_quota,
           COALESCE(s.other_taken, 0)    AS other_taken
    FROM llw_users u
    LEFT JOIN tl_stats s ON s.user_id = u.user_id AND s.fiscal_year = ?
    WHERE u.status = 'active'
    ORDER BY u.firstname, u.lastname
");
$stmt->execute([$selectedFY]);
$staffStats = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ── สรุปรวมทั้งโรงเรียนตามประเภทการลา (เฉพาะที่อนุมัติแล้ว) ──
$stmt2 = $pdo->prepare("
    SELECT leave_type, COUNT(*) AS cnt, SUM(days_count) AS total_days
    FROM tl_requests
    WHERE status = 'approved' AND fiscal_year = ?
    GROUP BY leave_type
");
$stmt2->execute([$selectedFY]);
$typeSummary = [];
foreach ($stmt2->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $typeSummary[$row['leave_type']] = $row;
}

$totalRequests = array_sum(array_column($typeSummary, 'cnt'));
$totalDays     = array_sum(array_column($typeSummary, 'total_days'));

// ── CSV Export ──
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $filename = "สรุปการลาปีงบประมาณ_{$selectedFY}_" . date('Ymd') . ".csv";
    $filename = preg_replace('/[^\p{L}\p{N}\p{M}\-_.]/u', '_', $filename);
    header('Content-Type: text/csv; charset=utf-8');
    header("Content-Disposition: attachment; filename=\"$filename\"");
    echo "\xEF\xBB\xBF";
    $out = fopen('php://output', 'w');
    fputcsv($out, ['ชื่อ-สกุล', 'ตำแหน่ง', 'ลาป่วย (วัน)', 'ลากิจ (วัน)', 'ลาพักผ่อน (ใช้/โควต้า)', 'ลาอื่นๆ (วัน)']);
    foreach ($staffStats as $s) {
        fputcsv($out, [
            $s['firstname'] . ' ' . $s['lastname'],
            $s['position'],
            $s['sick_taken'],
            $s['personal_taken'],
            $s['vacation_taken'] . ' / ' . $s['vacation_quota'],
            $s['other_taken'],
        ]);
    }
    fclose($out); exit();
}

$pageTitle    = 'สรุปการลารายปีงบประมาณ';
$pageSubtitle = "ปีงบประมาณ $selectedFY (1 ต.ค. " . ($selectedFY - 1) . " – 30 ก.ย. $selectedFY)";
$activeSystem = 'teacher_leave';
require_once '../components/layout_start.php';
?>

<div class="flex flex-wrap items-center justify-between gap-4 mb-6">
  <form method="GET" class="flex items-center gap-3">
    <label class="text-xs font-black text-slate-500 uppercase tracking-wider">ปีงบประมาณ</label>
    <select name="fiscal_year" onchange="this.form.submit()"
      class="bg-slate-50 border border-slate-200 rounded-2xl px-4 py-2.5 text-sm font-bold focus:ring-2 focus:ring-rose-400 outline-none">
      <?php foreach ($fyOptions as $fy): ?>
      <option value="<?= $fy ?>" <?= $fy == $selectedFY ? 'selected' : '' ?>>ปีงบประมาณ <?= $fy ?><?= $fy == $currentFY ? ' (ปัจจุบัน)' : '' ?></option>
      <?php endforeach; ?>
    </select>
  </form>
  <a href="fiscal_report.php?fiscal_year=<?= $selectedFY ?>&export=csv"
     class="px-4 py-2.5 bg-emerald-50 text-emerald-700 border border-emerald-200 text-sm font-bold rounded-2xl hover:bg-emerald-100 transition-all flex items-center gap-2">
    <i class="fas fa-file-csv"></i> Export CSV
  </a>
</div>

<!-- KPI -->
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
  <div class="bg-white rounded-2xl p-5 border border-slate-100 shadow-sm">
    <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">ใบลาอนุมัติแล้ว</p>
    <p class="text-3xl font-black text-slate-700 mt-1"><?= $totalRequests ?></p>
  </div>
  <div class="bg-white rounded-2xl p-5 border border-slate-100 shadow-sm">
    <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">วันลารวม</p>
    <p class="text-3xl font-black text-slate-700 mt-1"><?= $totalDays ?: 0 ?></p>
  </div>
  <?php foreach (['sick', 'vacation'] as $t): $tm = $typeMap[$t]; ?>
  <div class="bg-white rounded-2xl p-5 border border-slate-100 shadow-sm">
    <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest"><?= $tm['label'] ?></p>
    <p class="text-3xl font-black mt-1" style="color:<?= $tm['color'] ?>"><?= (float)($typeSummary[$t]['total_days'] ?? 0) ?></p>
    <p class="text-[10px] text-slate-400 font-bold">วัน</p>
  </div>
  <?php endforeach; ?>
</div>

<!-- Chart -->
<div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6 mb-6">
  <h3 class="font-black text-slate-800 mb-3">เปรียบเทียบจำนวนวันลาตามประเภท</h3>
  <canvas id="typeChart" height="90"></canvas>
</div>

<!-- Table -->
<div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
  <div class="px-6 py-4 border-b border-slate-50">
    <h3 class="font-black text-slate-800">สรุปรายบุคคล — ปีงบประมาณ <?= $selectedFY ?></h3>
  </div>
  <div class="overflow-x-auto">
    <table class="min-w-full text-sm">
      <thead class="bg-slate-50 border-b border-slate-100">
        <tr>
          <th class="px-6 py-3 text-left text-[10px] font-black text-slate-400 uppercase tracking-widest">ชื่อ-สกุล</th>
          <th class="px-6 py-3 text-left text-[10px] font-black text-slate-400 uppercase tracking-widest">ตำแหน่ง</th>
          <th class="px-6 py-3 text-center text-[10px] font-black text-rose-500 uppercase tracking-widest">ลาป่วย</th>
          <th class="px-6 py-3 text-center text-[10px] font-black text-amber-600 uppercase tracking-widest">ลากิจ</th>
          <th class="px-6 py-3 text-center text-[10px] font-black text-blue-600 uppercase tracking-widest">ลาพักผ่อน (ใช้/โควต้า)</th>
          <th class="px-6 py-3 text-center text-[10px] font-black text-slate-400 uppercase tracking-widest">อื่นๆ</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-50">
        <?php if (empty($staffStats)): ?>
        <tr><td colspan="6" class="px-6 py-10 text-center text-slate-300">ไม่พบข้อมูลบุคลากร</td></tr>
        <?php else: foreach ($staffStats as $s):
          $vacLeft = (float)$s['vacation_quota'] - (float)$s['vacation_taken'];
        ?>
        <tr class="hover:bg-slate-50/50">
          <td class="px-6 py-3 font-bold text-slate-700"><?= htmlspecialchars($s['firstname'] . ' ' . $s['lastname'], ENT_QUOTES, 'UTF-8') ?></td>
          <td class="px-6 py-3 text-slate-500"><?= htmlspecialchars($s['position'] ?: '-', ENT_QUOTES, 'UTF-8') ?></td>
          <td class="px-6 py-3 text-center font-bold <?= $s['sick_taken'] > 0 ? 'text-rose-600' : 'text-slate-300' ?>"><?= $s['sick_taken'] ?: '-' ?></td>
          <td class="px-6 py-3 text-center font-bold <?= $s['personal_taken'] > 0 ? 'text-amber-600' : 'text-slate-300' ?>"><?= $s['personal_taken'] ?: '-' ?></td>
          <td class="px-6 py-3 text-center">
            <span class="font-bold <?= $vacLeft < 0 ? 'text-rose-600' : 'text-blue-600' ?>"><?= $s['vacation_taken'] ?> / <?= $s['vacation_quota'] ?></span>
          </td>
          <td class="px-6 py-3 text-center font-bold <?= $s['other_taken'] > 0 ? 'text-slate-600' : 'text-slate-300' ?>"><?= $s['other_taken'] ?: '-' ?></td>
        </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
const ctx = document.getElementById('typeChart').getContext('2d');
new Chart(ctx, {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_column($typeMap, 'label'), JSON_UNESCAPED_UNICODE) ?>,
        datasets: [{
            label: 'วันลา',
            data: <?= json_encode(array_map(fn($k) => (float)($typeSummary[$k]['total_days'] ?? 0), array_keys($typeMap))) ?>,
            backgroundColor: <?= json_encode(array_column($typeMap, 'color')) ?>,
            borderRadius: 8
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: { y: { beginAtZero: true } }
    }
});
</script>

<?php require_once '../components/layout_end.php'; ?>
