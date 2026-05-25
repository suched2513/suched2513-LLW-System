<?php
session_start();
require_once __DIR__ . '/../config.php';
if (!isset($_SESSION['llw_role'])) { header('Location: ' . $base_path . '/login.php'); exit(); }
if (!in_array($_SESSION['llw_role'], ['super_admin'])) { header('Location: ' . $base_path . '/login.php'); exit(); }

$pdo  = getPdo();
$year = (int)($_GET['year'] ?? 2569);
$sem  = (int)($_GET['semester'] ?? 1);
if (!in_array($sem, [1, 2])) $sem = 1;

// Check tables exist (migration may not have run on production yet)
$health_table_exists = (bool)$pdo->query("SHOW TABLES LIKE 'health_records'")->fetch();

$k          = ['total'=>0,'normal'=>0,'thin'=>0,'fat'=>0,'short_st'=>0];
$cls_data   = [];
$recent     = [];
$years_list = [2569];

if ($health_table_exists) {
    // KPI
    $kpi = $pdo->prepare("
        SELECT
            COUNT(DISTINCT hr.student_id)                                      AS total,
            SUM(hr.bfa_status = 'สมส่วน')                                      AS normal,
            SUM(hr.bfa_status IN ('ผอม','ผอมมาก'))                             AS thin,
            SUM(hr.bfa_status IN ('น้ำหนักเกิน','อ้วน'))                        AS fat,
            SUM(hr.hfa_status IN ('เตี้ย','เตี้ยมาก'))                          AS short_st
        FROM health_records hr
        JOIN (
            SELECT student_id, MAX(record_date) AS latest
            FROM health_records WHERE academic_year=? AND semester=?
            GROUP BY student_id
        ) latest_row ON latest_row.student_id = hr.student_id AND latest_row.latest = hr.record_date
        WHERE hr.academic_year=? AND hr.semester=?
    ");
    $kpi->execute([$year, $sem, $year, $sem]);
    $k = $kpi->fetch() ?: $k;

    // Per-classroom breakdown
    $cls_rows = $pdo->prepare("
        SELECT s.classroom,
               COUNT(DISTINCT hr.student_id)             AS cnt,
               SUM(hr.bfa_status = 'สมส่วน')             AS normal,
               SUM(hr.bfa_status IN ('ผอม','ผอมมาก'))    AS thin,
               SUM(hr.bfa_status IN ('น้ำหนักเกิน','อ้วน')) AS fat
        FROM health_records hr
        JOIN att_students s ON s.id = hr.student_id
        JOIN (
            SELECT student_id, MAX(record_date) latest
            FROM health_records WHERE academic_year=? AND semester=?
            GROUP BY student_id
        ) lr ON lr.student_id=hr.student_id AND lr.latest=hr.record_date
        WHERE hr.academic_year=? AND hr.semester=?
        GROUP BY s.classroom ORDER BY s.classroom
    ");
    $cls_rows->execute([$year, $sem, $year, $sem]);
    $cls_data = $cls_rows->fetchAll();

    // Recent 10 records
    $r = $pdo->prepare("
        SELECT hr.*, s.name AS student_name, s.classroom, s.gender
        FROM health_records hr
        JOIN att_students s ON s.id = hr.student_id
        WHERE hr.academic_year=? AND hr.semester=?
        ORDER BY hr.created_at DESC LIMIT 10
    ");
    $r->execute([$year, $sem]);
    $recent = $r->fetchAll();

    // Available years
    $yl = $pdo->query("SELECT DISTINCT academic_year FROM health_records ORDER BY academic_year DESC")->fetchAll(PDO::FETCH_COLUMN);
    if (!empty($yl)) $years_list = $yl;
}
if (empty($years_list)) $years_list = [2569];

$total = max(1, (int)$k['total']);
$pct   = fn($v) => $total > 1 ? round($v / $total * 100, 1) : 0;

$pageTitle    = 'สุขภาวะนักเรียน';
$pageSubtitle = 'ระบบบันทึกน้ำหนัก ส่วนสูง และภาวะโภชนาการ';
$activeSystem = 'health';
require_once __DIR__ . '/../components/layout_start.php';
?>

<?php if (!$health_table_exists): ?>
<div class="mb-5 p-4 bg-amber-50 border border-amber-200 rounded-2xl flex items-center gap-3">
  <i class="fas fa-exclamation-triangle text-amber-500 text-xl flex-shrink-0"></i>
  <div>
    <p class="font-black text-amber-800 text-sm">ยังไม่ได้รัน Migration</p>
    <p class="text-xs text-amber-600 mt-0.5">ตารางฐานข้อมูลยังไม่ถูกสร้าง — SSH เข้า production แล้วรัน: <code class="bg-amber-100 px-1 rounded font-mono">php database/migrate.php</code></p>
  </div>
</div>
<?php endif; ?>

<!-- Header -->
<div class="flex items-center justify-between mb-6 flex-wrap gap-3">
  <div class="flex items-center gap-3">
    <div class="w-10 h-10 rounded-xl flex items-center justify-center shadow-lg" style="background:linear-gradient(135deg,#10B981,#0D9488)">
      <i class="fas fa-heartbeat text-white"></i>
    </div>
    <div>
      <h2 class="text-lg font-black text-slate-800">ระบบสุขภาวะนักเรียน</h2>
      <p class="text-xs text-slate-400">น้ำหนัก ส่วนสูง และภาวะโภชนาการ ตามมาตรฐานกรมอนามัย</p>
    </div>
  </div>
  <div class="flex items-center gap-2 flex-wrap">
    <form method="get" class="flex items-center gap-2">
      <select name="year" onchange="this.form.submit()" class="text-xs border border-slate-200 rounded-xl px-3 py-2 bg-white focus:ring-2 focus:ring-emerald-400 outline-none">
        <?php foreach ($years_list as $y): ?>
        <option value="<?=$y?>" <?=$y==$year?'selected':''?>>ปีการศึกษา <?=$y?></option>
        <?php endforeach; ?>
        <?php if (!in_array(2569, $years_list)): ?>
        <option value="2569" <?=2569==$year?'selected':''?>>ปีการศึกษา 2569</option>
        <?php endif; ?>
      </select>
      <select name="semester" onchange="this.form.submit()" class="text-xs border border-slate-200 rounded-xl px-3 py-2 bg-white focus:ring-2 focus:ring-emerald-400 outline-none">
        <option value="1" <?=$sem==1?'selected':''?>>ภาคเรียนที่ 1</option>
        <option value="2" <?=$sem==2?'selected':''?>>ภาคเรียนที่ 2</option>
      </select>
    </form>
    <a href="/health/record.php" class="px-4 py-2 bg-emerald-600 text-white text-xs font-bold rounded-xl shadow-lg shadow-emerald-200 hover:bg-emerald-700 transition-all">
      <i class="fas fa-plus mr-1"></i>บันทึกข้อมูล
    </a>
    <a href="/health/students.php" class="px-4 py-2 bg-slate-100 text-slate-600 text-xs font-bold rounded-xl hover:bg-slate-200 transition-all">
      <i class="fas fa-users mr-1"></i>รายชื่อนักเรียน
    </a>
    <a href="/health/standards.php" class="px-4 py-2 bg-slate-100 text-slate-600 text-xs font-bold rounded-xl hover:bg-slate-200 transition-all">
      <i class="fas fa-table mr-1"></i>เกณฑ์มาตรฐาน
    </a>
  </div>
</div>

<!-- KPI Cards -->
<div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-4 mb-6">
  <?php
  $cards = [
    ['label'=>'นักเรียนทั้งหมด', 'val'=>(int)$k['total'], 'pct'=>null,       'color'=>'slate',   'icon'=>'fa-users'],
    ['label'=>'สมส่วน',          'val'=>(int)$k['normal'], 'pct'=>$pct($k['normal']), 'color'=>'emerald', 'icon'=>'fa-check-circle'],
    ['label'=>'ผอม/ผอมมาก',      'val'=>(int)$k['thin'],   'pct'=>$pct($k['thin']),   'color'=>'amber',   'icon'=>'fa-exclamation-triangle'],
    ['label'=>'น้ำหนักเกิน/อ้วน', 'val'=>(int)$k['fat'],    'pct'=>$pct($k['fat']),    'color'=>'rose',    'icon'=>'fa-exclamation-circle'],
    ['label'=>'เตี้ย/เตี้ยมาก',   'val'=>(int)$k['short_st'],'pct'=>$pct($k['short_st']),'color'=>'violet','icon'=>'fa-arrow-down'],
  ];
  $gradients = ['slate'=>'from-slate-500 to-slate-700','emerald'=>'from-emerald-500 to-teal-600','amber'=>'from-amber-400 to-orange-500','rose'=>'from-rose-500 to-pink-600','violet'=>'from-violet-500 to-purple-600'];
  $shadows   = ['slate'=>'shadow-slate-200/50','emerald'=>'shadow-emerald-200/50','amber'=>'shadow-amber-200/50','rose'=>'shadow-rose-200/50','violet'=>'shadow-violet-200/50'];
  foreach ($cards as $c):
  ?>
  <div class="bg-gradient-to-br <?=$gradients[$c['color']]?> rounded-2xl p-5 text-white shadow-xl <?=$shadows[$c['color']]?>">
    <div class="flex items-center justify-between mb-2">
      <p class="text-xs font-bold opacity-80"><?=$c['label']?></p>
      <i class="fas <?=$c['icon']?> opacity-60 text-lg"></i>
    </div>
    <p class="text-3xl font-black"><?=$c['val']?></p>
    <?php if ($c['pct'] !== null): ?>
    <p class="text-xs opacity-75 mt-1"><?=$c['pct']?>%</p>
    <?php endif; ?>
  </div>
  <?php endforeach; ?>
</div>

<!-- Charts Row -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-5 mb-6">

  <!-- Pie: nutrition status -->
  <div class="bg-white rounded-2xl shadow-xl shadow-slate-100/50 border border-slate-100 p-5">
    <h3 class="font-black text-slate-700 text-sm mb-4 flex items-center gap-2">
      <i class="fas fa-chart-pie text-emerald-500"></i> ภาวะโภชนาการ
    </h3>
    <?php if ((int)$k['total'] === 0): ?>
    <div class="flex items-center justify-center h-40 text-slate-300 text-sm">ยังไม่มีข้อมูล</div>
    <?php else: ?>
    <canvas id="pieChart" height="220"></canvas>
    <?php endif; ?>
  </div>

  <!-- Bar: per classroom -->
  <div class="bg-white rounded-2xl shadow-xl shadow-slate-100/50 border border-slate-100 p-5 lg:col-span-2">
    <h3 class="font-black text-slate-700 text-sm mb-4 flex items-center gap-2">
      <i class="fas fa-chart-bar text-blue-500"></i> ภาวะโภชนาการแยกห้องเรียน
    </h3>
    <?php if (empty($cls_data)): ?>
    <div class="flex items-center justify-center h-40 text-slate-300 text-sm">ยังไม่มีข้อมูล</div>
    <?php else: ?>
    <canvas id="barChart" height="220"></canvas>
    <?php endif; ?>
  </div>
</div>

<!-- Recent Records -->
<div class="bg-white rounded-2xl shadow-xl shadow-slate-100/50 border border-slate-100 overflow-hidden">
  <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
    <h3 class="font-black text-slate-700 text-sm flex items-center gap-2">
      <i class="fas fa-history text-orange-500"></i> บันทึกล่าสุด
    </h3>
    <a href="/health/students.php?year=<?=$year?>&semester=<?=$sem?>" class="text-xs text-emerald-600 font-bold hover:underline">ดูทั้งหมด →</a>
  </div>
  <div class="overflow-x-auto">
    <table class="w-full text-sm">
      <thead class="bg-slate-50 text-xs text-slate-400 font-black uppercase tracking-wider">
        <tr>
          <th class="px-4 py-3 text-left">ชื่อ</th>
          <th class="px-4 py-3 text-left">ห้อง</th>
          <th class="px-4 py-3 text-center">น้ำหนัก</th>
          <th class="px-4 py-3 text-center">ส่วนสูง</th>
          <th class="px-4 py-3 text-center">BMI</th>
          <th class="px-4 py-3 text-center">ภาวะโภชนาการ</th>
          <th class="px-4 py-3 text-center">ส่วนสูงตามวัย</th>
          <th class="px-4 py-3 text-center">วันที่</th>
          <th class="px-4 py-3 text-center">จัดการ</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-50">
        <?php if (empty($recent)): ?>
        <tr><td colspan="9" class="px-5 py-10 text-center text-slate-300">ยังไม่มีข้อมูล — <a href="/health/record.php" class="text-emerald-600 font-bold">เริ่มบันทึก</a></td></tr>
        <?php endif; ?>
        <?php
        $bfa_colors = ['ผอมมาก'=>'rose','ผอม'=>'amber','สมส่วน'=>'emerald','น้ำหนักเกิน'=>'orange','อ้วน'=>'rose'];
        $hfa_colors = ['เตี้ยมาก'=>'rose','เตี้ย'=>'amber','ปกติ'=>'slate','สูง'=>'blue'];
        foreach ($recent as $r):
          $bc = $bfa_colors[$r['bfa_status']] ?? 'slate';
          $hc = $hfa_colors[$r['hfa_status']] ?? 'slate';
        ?>
        <tr class="hover:bg-slate-50/50 transition-colors">
          <td class="px-4 py-3 font-bold text-slate-700 text-xs"><?=htmlspecialchars($r['student_name'],ENT_QUOTES,'UTF-8')?></td>
          <td class="px-4 py-3"><span class="px-2 py-0.5 bg-blue-50 text-blue-600 text-xs font-bold rounded-full"><?=htmlspecialchars($r['classroom'],ENT_QUOTES,'UTF-8')?></span></td>
          <td class="px-4 py-3 text-center text-xs font-bold"><?=number_format($r['weight_kg'],1)?> kg</td>
          <td class="px-4 py-3 text-center text-xs font-bold"><?=number_format($r['height_cm'],1)?> cm</td>
          <td class="px-4 py-3 text-center text-xs font-bold text-slate-600"><?=number_format($r['bmi'],1)?></td>
          <td class="px-4 py-3 text-center">
            <span class="px-2 py-0.5 bg-<?=$bc?>-50 text-<?=$bc?>-600 text-xs font-bold rounded-full"><?=htmlspecialchars($r['bfa_status']??'—',ENT_QUOTES,'UTF-8')?></span>
          </td>
          <td class="px-4 py-3 text-center">
            <span class="px-2 py-0.5 bg-<?=$hc?>-50 text-<?=$hc?>-600 text-xs font-bold rounded-full"><?=htmlspecialchars($r['hfa_status']??'—',ENT_QUOTES,'UTF-8')?></span>
          </td>
          <td class="px-4 py-3 text-center text-xs text-slate-400"><?=date('d/m/Y',strtotime($r['record_date']))?></td>
          <td class="px-4 py-3 text-center">
            <a href="/health/record.php?id=<?=$r['id']?>" class="px-2 py-1 bg-emerald-50 text-emerald-700 text-[10px] font-bold rounded-lg hover:bg-emerald-100 transition-all">
              <i class="fas fa-edit"></i>
            </a>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
<?php if ((int)$k['total'] > 0): ?>
new Chart(document.getElementById('pieChart'), {
  type: 'doughnut',
  data: {
    labels: ['สมส่วน','ผอม/ผอมมาก','น้ำหนักเกิน/อ้วน','เตี้ย/เตี้ยมาก'],
    datasets: [{
      data: [<?=(int)$k['normal']?>,<?=(int)$k['thin']?>,<?=(int)$k['fat']?>,<?=(int)$k['short_st']?>],
      backgroundColor: ['#10B981','#F59E0B','#F43F5E','#8B5CF6'],
      borderWidth: 2, borderColor: '#fff'
    }]
  },
  options: {
    responsive: true,
    plugins: {
      legend: { position: 'bottom', labels: { font: { family: 'Prompt', size: 11 }, padding: 12 } }
    },
    cutout: '60%'
  }
});
<?php endif; ?>

<?php if (!empty($cls_data)): ?>
new Chart(document.getElementById('barChart'), {
  type: 'bar',
  data: {
    labels: <?=json_encode(array_column($cls_data,'classroom'))?>,
    datasets: [
      { label: 'สมส่วน',        data: <?=json_encode(array_map('intval',array_column($cls_data,'normal')))?>, backgroundColor: '#10B981', borderRadius: 4, borderSkipped: false },
      { label: 'ผอม/ผอมมาก',    data: <?=json_encode(array_map('intval',array_column($cls_data,'thin')))?>,   backgroundColor: '#F59E0B', borderRadius: 4, borderSkipped: false },
      { label: 'น้ำหนักเกิน/อ้วน',data: <?=json_encode(array_map('intval',array_column($cls_data,'fat')))?>,    backgroundColor: '#F43F5E', borderRadius: 4, borderSkipped: false },
    ]
  },
  options: {
    responsive: true,
    plugins: { legend: { position: 'bottom', labels: { font: { family: 'Prompt', size: 11 }, padding: 10 } } },
    scales: {
      x: { stacked: false, grid: { display: false } },
      y: { beginAtZero: true, ticks: { stepSize: 1 }, grid: { color: '#f3f4f6' } }
    }
  }
});
<?php endif; ?>
</script>

<?php require_once __DIR__ . '/../components/layout_end.php'; ?>
