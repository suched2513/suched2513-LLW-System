<?php
session_start();
require_once __DIR__ . '/../config.php';
if (!isset($_SESSION['llw_role'])) { header('Location: ' . $base_path . '/login.php'); exit(); }
if (!in_array($_SESSION['llw_role'], ['super_admin'])) { header('Location: ' . $base_path . '/login.php'); exit(); }

$pdo       = getPdo();
$year      = (int)($_GET['year']      ?? 2569);
$sem       = (int)($_GET['semester']  ?? 1);
$classroom = trim($_GET['classroom']  ?? '');
if (!in_array($sem, [1, 2])) $sem = 1;

$health_ok = (bool)$pdo->query("SHOW TABLES LIKE 'health_records'")->fetch();

// Classrooms list
$classrooms = $pdo->query("
    SELECT DISTINCT classroom FROM att_students
    WHERE status='active' AND student_id REGEXP '^[0-9]+$'
      AND student_id NOT IN (SELECT subject_code FROM att_subjects)
    ORDER BY classroom
")->fetchAll(PDO::FETCH_COLUMN);

if (!$classroom && !empty($classrooms)) $classroom = $classrooms[0];

$students = [];
$stats    = ['total'=>0,'has_record'=>0,'normal'=>0,'thin'=>0,'very_thin'=>0,'fat'=>0,'obese'=>0,'short'=>0,'very_short'=>0,'avg_bmi'=>null,'avg_weight'=>null,'avg_height'=>null];

if ($health_ok && $classroom) {
    // Full student list with latest record for this classroom/year/sem
    $rows = $pdo->prepare("
        SELECT s.id, s.name, s.gender, s.birthdate,
               hr.id AS rec_id, hr.weight_kg, hr.height_cm, hr.bmi,
               hr.bfa_status, hr.hfa_status, hr.record_date
        FROM att_students s
        LEFT JOIN (
            SELECT hr1.*
            FROM health_records hr1
            JOIN (
                SELECT student_id, MAX(record_date) latest
                FROM health_records WHERE academic_year=? AND semester=?
                GROUP BY student_id
            ) lr ON lr.student_id=hr1.student_id AND lr.latest=hr1.record_date
            WHERE hr1.academic_year=? AND hr1.semester=?
        ) hr ON hr.student_id = s.id
        WHERE s.classroom=? AND s.status='active'
          AND s.student_id REGEXP '^[0-9]+$'
          AND s.student_id NOT IN (SELECT subject_code FROM att_subjects)
        ORDER BY s.name
    ");
    $rows->execute([$year, $sem, $year, $sem, $classroom]);
    $students = $rows->fetchAll();

    // Compute stats
    $stats['total']      = count($students);
    $bmis = $weights = $heights = [];
    foreach ($students as $s) {
        if (!$s['rec_id']) continue;
        $stats['has_record']++;
        if ($s['bfa_status'] === 'สมส่วน')      $stats['normal']++;
        if ($s['bfa_status'] === 'ผอม')          $stats['thin']++;
        if ($s['bfa_status'] === 'ผอมมาก')       $stats['very_thin']++;
        if ($s['bfa_status'] === 'น้ำหนักเกิน')  $stats['fat']++;
        if ($s['bfa_status'] === 'อ้วน')          $stats['obese']++;
        if ($s['hfa_status'] === 'เตี้ย')         $stats['short']++;
        if ($s['hfa_status'] === 'เตี้ยมาก')      $stats['very_short']++;
        $bmis[]    = (float)$s['bmi'];
        $weights[] = (float)$s['weight_kg'];
        $heights[] = (float)$s['height_cm'];
    }
    if (!empty($bmis)) {
        $stats['avg_bmi']    = round(array_sum($bmis)    / count($bmis),    1);
        $stats['avg_weight'] = round(array_sum($weights) / count($weights), 1);
        $stats['avg_height'] = round(array_sum($heights) / count($heights), 1);
    }
}

// Export CSV
if (isset($_GET['export']) && !empty($students)) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="health_class_' . $classroom . '_' . $year . '_' . $sem . '.csv"');
    echo "\xEF\xBB\xBF";
    echo "ลำดับ,ชื่อ,เพศ,น้ำหนัก(kg),ส่วนสูง(cm),BMI,ภาวะโภชนาการ,ส่วนสูงตามวัย,วันที่วัด\n";
    $n = 0;
    foreach ($students as $s) {
        $n++;
        echo implode(',', [
            $n,
            '"' . str_replace('"','""',$s['name']) . '"',
            $s['gender'] === 'หญิง' ? 'หญิง' : 'ชาย',
            $s['weight_kg'] ?? '',
            $s['height_cm'] ?? '',
            $s['bmi'] ? number_format($s['bmi'],1) : '',
            '"' . ($s['bfa_status'] ?? '—') . '"',
            '"' . ($s['hfa_status'] ?? '—') . '"',
            $s['record_date'] ?? '',
        ]) . "\n";
    }
    exit;
}

$pageTitle    = 'รายงานระดับห้องเรียน';
$pageSubtitle = $classroom ? "ห้อง $classroom · ปี $year ภาค $sem" : 'เลือกห้องเรียน';
$activeSystem = 'health';
require_once __DIR__ . '/../components/layout_start.php';

$bfa_colors = ['ผอมมาก'=>'rose','ผอม'=>'amber','สมส่วน'=>'emerald','น้ำหนักเกิน'=>'orange','อ้วน'=>'rose'];
$hfa_colors = ['เตี้ยมาก'=>'rose','เตี้ย'=>'amber','ปกติ'=>'slate','สูง'=>'blue'];
?>

<!-- Header -->
<div class="flex items-center justify-between mb-6 flex-wrap gap-3">
  <div class="flex items-center gap-3">
    <a href="/health/dashboard.php" class="w-9 h-9 bg-slate-100 rounded-xl flex items-center justify-center hover:bg-slate-200 transition-all">
      <i class="fas fa-arrow-left text-slate-600 text-sm"></i>
    </a>
    <div>
      <h2 class="text-lg font-black text-slate-800">รายงานระดับห้องเรียน</h2>
      <p class="text-xs text-slate-400">สถิติและภาวะโภชนาการแยกรายห้อง</p>
    </div>
  </div>
  <?php if ($classroom && $health_ok && $stats['has_record'] > 0): ?>
  <a href="?year=<?=$year?>&semester=<?=$sem?>&classroom=<?=urlencode($classroom)?>&export=1"
     class="px-4 py-2 bg-emerald-50 text-emerald-700 text-xs font-bold rounded-xl hover:bg-emerald-100 transition-all">
    <i class="fas fa-file-csv mr-1"></i>Export CSV
  </a>
  <?php endif; ?>
</div>

<!-- Filter bar -->
<form method="get" class="bg-white rounded-2xl shadow-xl shadow-slate-100/50 border border-slate-100 p-4 mb-5 flex flex-wrap gap-3 items-center">
  <select name="classroom" onchange="this.form.submit()" class="text-xs border border-slate-200 rounded-xl px-3 py-2 bg-slate-50 focus:ring-2 focus:ring-emerald-400 outline-none font-bold">
    <?php foreach ($classrooms as $cl): ?>
    <option value="<?=htmlspecialchars($cl,ENT_QUOTES,'UTF-8')?>" <?=$cl===$classroom?'selected':''?>><?=htmlspecialchars($cl,ENT_QUOTES,'UTF-8')?></option>
    <?php endforeach; ?>
  </select>
  <select name="year" onchange="this.form.submit()" class="text-xs border border-slate-200 rounded-xl px-3 py-2 bg-slate-50 focus:ring-2 focus:ring-emerald-400 outline-none">
    <?php foreach ([2569,2568,2567] as $y): ?>
    <option value="<?=$y?>" <?=$y===$year?'selected':''?>>ปี <?=$y?></option>
    <?php endforeach; ?>
  </select>
  <select name="semester" onchange="this.form.submit()" class="text-xs border border-slate-200 rounded-xl px-3 py-2 bg-slate-50 focus:ring-2 focus:ring-emerald-400 outline-none">
    <option value="1" <?=$sem===1?'selected':''?>>ภาค 1</option>
    <option value="2" <?=$sem===2?'selected':''?>>ภาค 2</option>
  </select>
  <span class="text-xs text-slate-400">นักเรียน <?=$stats['total']?> คน · มีข้อมูล <?=$stats['has_record']?> คน</span>
</form>

<?php if (!$health_ok): ?>
<div class="bg-amber-50 border border-amber-200 rounded-2xl p-6 text-center">
  <p class="text-amber-700 font-bold text-sm">ยังไม่ได้รัน Migration — รัน <code class="bg-amber-100 px-1 rounded">php database/migrate.php</code> บน production ก่อน</p>
</div>
<?php elseif (empty($classrooms)): ?>
<div class="bg-slate-50 border border-slate-200 rounded-2xl p-6 text-center"><p class="text-slate-400 text-sm">ไม่พบข้อมูลห้องเรียน</p></div>
<?php else: ?>

<!-- KPI Row -->
<div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-7 gap-3 mb-5">
  <?php
  $kcards = [
    ['l'=>'นักเรียน',      'v'=>$stats['total'],       'c'=>'slate'],
    ['l'=>'มีข้อมูล',      'v'=>$stats['has_record'],  'c'=>'blue'],
    ['l'=>'สมส่วน',        'v'=>$stats['normal'],      'c'=>'emerald'],
    ['l'=>'ผอม/ผอมมาก',   'v'=>$stats['thin']+$stats['very_thin'], 'c'=>'amber'],
    ['l'=>'น้ำหนักเกิน/อ้วน','v'=>$stats['fat']+$stats['obese'],  'c'=>'rose'],
    ['l'=>'เตี้ย/เตี้ยมาก','v'=>$stats['short']+$stats['very_short'],'c'=>'violet'],
    ['l'=>'BMI เฉลี่ย',    'v'=>$stats['avg_bmi'] ?? '—', 'c'=>'teal'],
  ];
  foreach ($kcards as $c):
  ?>
  <div class="bg-white rounded-2xl border border-slate-100 shadow-lg p-4 text-center">
    <p class="text-[10px] font-black text-slate-400 uppercase tracking-wider mb-1"><?=$c['l']?></p>
    <p class="text-2xl font-black text-<?=$c['c']?>-600"><?=$c['v']?></p>
  </div>
  <?php endforeach; ?>
</div>

<!-- Charts -->
<?php if ($stats['has_record'] > 0): ?>
<div class="grid grid-cols-1 lg:grid-cols-2 gap-5 mb-5">
  <div class="bg-white rounded-2xl shadow-xl shadow-slate-100/50 border border-slate-100 p-5">
    <h3 class="font-black text-slate-700 text-sm mb-3 flex items-center gap-2">
      <i class="fas fa-chart-pie text-emerald-500"></i> สัดส่วนภาวะโภชนาการ
    </h3>
    <canvas id="pieChart" height="220"></canvas>
  </div>
  <div class="bg-white rounded-2xl shadow-xl shadow-slate-100/50 border border-slate-100 p-5">
    <h3 class="font-black text-slate-700 text-sm mb-3 flex items-center gap-2">
      <i class="fas fa-chart-bar text-blue-500"></i> การกระจาย BMI
    </h3>
    <canvas id="bmiDistChart" height="220"></canvas>
  </div>
</div>
<?php endif; ?>

<!-- Student Table -->
<div class="bg-white rounded-2xl shadow-xl shadow-slate-100/50 border border-slate-100 overflow-hidden">
  <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
    <h3 class="font-black text-slate-700 text-sm">รายชื่อนักเรียน — ห้อง <?=htmlspecialchars($classroom,ENT_QUOTES,'UTF-8')?></h3>
    <span class="text-xs text-slate-400"><?=$stats['has_record']?>/<?=$stats['total']?> คนมีข้อมูล</span>
  </div>
  <div class="overflow-x-auto">
    <table class="w-full text-sm">
      <thead class="bg-slate-50 text-xs text-slate-400 font-black uppercase tracking-wider">
        <tr>
          <th class="px-4 py-3 text-left">#</th>
          <th class="px-4 py-3 text-left">ชื่อ</th>
          <th class="px-4 py-3 text-center">เพศ</th>
          <th class="px-4 py-3 text-center">น้ำหนัก</th>
          <th class="px-4 py-3 text-center">ส่วนสูง</th>
          <th class="px-4 py-3 text-center">BMI</th>
          <th class="px-4 py-3 text-center">ภาวะโภชนาการ</th>
          <th class="px-4 py-3 text-center">ส่วนสูงตามวัย</th>
          <th class="px-4 py-3 text-center">วันที่วัด</th>
          <th class="px-4 py-3 text-center">จัดการ</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-50">
        <?php if (empty($students)): ?>
        <tr><td colspan="10" class="px-5 py-10 text-center text-slate-300">ไม่มีข้อมูลนักเรียนในห้องนี้</td></tr>
        <?php endif; ?>
        <?php $n = 0; foreach ($students as $s):
          $n++;
          $bc = $bfa_colors[$s['bfa_status'] ?? ''] ?? 'slate';
          $hc = $hfa_colors[$s['hfa_status'] ?? ''] ?? 'slate';
          $no_rec = !$s['rec_id'];
        ?>
        <tr class="hover:bg-slate-50/50 transition-colors <?=$no_rec ? 'opacity-60' : ''?>">
          <td class="px-4 py-3 text-xs text-slate-400"><?=$n?></td>
          <td class="px-4 py-3 font-bold text-slate-700 text-xs"><?=htmlspecialchars($s['name'],ENT_QUOTES,'UTF-8')?></td>
          <td class="px-4 py-3 text-center text-xs <?=$s['gender']==='หญิง'?'text-rose-500':'text-blue-500'?> font-bold"><?=htmlspecialchars($s['gender']??'—',ENT_QUOTES,'UTF-8')?></td>
          <?php if ($no_rec): ?>
          <td colspan="6" class="px-4 py-3 text-center text-xs text-slate-300">— ยังไม่มีข้อมูล —</td>
          <?php else: ?>
          <td class="px-4 py-3 text-center text-xs font-bold"><?=number_format($s['weight_kg'],1)?></td>
          <td class="px-4 py-3 text-center text-xs font-bold"><?=number_format($s['height_cm'],1)?></td>
          <td class="px-4 py-3 text-center">
            <span class="text-xs font-black <?=((float)$s['bmi']<14.5)?'text-rose-500':((float)$s['bmi']>22?'text-amber-600':'text-slate-700')?>"><?=number_format($s['bmi'],1)?></span>
          </td>
          <td class="px-4 py-3 text-center"><span class="px-2 py-0.5 bg-<?=$bc?>-50 text-<?=$bc?>-600 text-xs font-bold rounded-full"><?=htmlspecialchars($s['bfa_status']??'—',ENT_QUOTES,'UTF-8')?></span></td>
          <td class="px-4 py-3 text-center"><span class="px-2 py-0.5 bg-<?=$hc?>-50 text-<?=$hc?>-600 text-xs font-bold rounded-full"><?=htmlspecialchars($s['hfa_status']??'—',ENT_QUOTES,'UTF-8')?></span></td>
          <td class="px-4 py-3 text-center text-xs text-slate-400"><?=date('d/m/Y',strtotime($s['record_date']))?></td>
          <?php endif; ?>
          <td class="px-4 py-3 text-center">
            <?php if ($no_rec): ?>
            <a href="/health/record.php?prefill_student=<?=$s['id']?>" class="px-2 py-1 bg-emerald-50 text-emerald-700 text[10px] font-bold rounded-lg hover:bg-emerald-100 transition-all text-[10px]">
              <i class="fas fa-plus mr-1"></i>บันทึก
            </a>
            <?php else: ?>
            <div class="flex gap-1 justify-center">
              <a href="/health/record.php?id=<?=$s['rec_id']?>" class="px-2 py-1 bg-blue-50 text-blue-700 text-[10px] font-bold rounded-lg hover:bg-blue-100 transition-all"><i class="fas fa-edit"></i></a>
              <a href="/health/profile.php?student_id=<?=$s['id']?>" class="px-2 py-1 bg-violet-50 text-violet-700 text-[10px] font-bold rounded-lg hover:bg-violet-100 transition-all"><i class="fas fa-chart-line"></i></a>
            </div>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
      <?php if ($stats['has_record'] > 0): ?>
      <tfoot class="bg-slate-50 border-t-2 border-slate-200">
        <tr>
          <td colspan="3" class="px-4 py-3 text-xs font-black text-slate-500 uppercase tracking-wider">เฉลี่ยห้อง</td>
          <td class="px-4 py-3 text-center text-xs font-black text-blue-600"><?=$stats['avg_weight']?> kg</td>
          <td class="px-4 py-3 text-center text-xs font-black text-teal-600"><?=$stats['avg_height']?> cm</td>
          <td class="px-4 py-3 text-center text-xs font-black text-violet-600"><?=$stats['avg_bmi']?></td>
          <td colspan="4"></td>
        </tr>
      </tfoot>
      <?php endif; ?>
    </table>
  </div>
</div>

<?php if ($stats['has_record'] > 0):
  $bmi_vals = array_filter(array_column($students,'bmi'));
  $bmi_ranges = ['< 14.5'=>0, '14.5–16'=>0, '16–18.5'=>0, '18.5–22'=>0, '22–25'=>0, '> 25'=>0];
  foreach ($bmi_vals as $b) {
    $b = (float)$b;
    if ($b < 14.5)       $bmi_ranges['< 14.5']++;
    elseif ($b < 16)     $bmi_ranges['14.5–16']++;
    elseif ($b < 18.5)   $bmi_ranges['16–18.5']++;
    elseif ($b < 22)     $bmi_ranges['18.5–22']++;
    elseif ($b < 25)     $bmi_ranges['22–25']++;
    else                 $bmi_ranges['> 25']++;
  }
?>
<script>
new Chart(document.getElementById('pieChart'), {
  type:'doughnut',
  data:{
    labels:['สมส่วน','ผอม','ผอมมาก','น้ำหนักเกิน','อ้วน'],
    datasets:[{
      data:[<?=$stats['normal']?>,<?=$stats['thin']?>,<?=$stats['very_thin']?>,<?=$stats['fat']?>,<?=$stats['obese']?>],
      backgroundColor:['#10B981','#F59E0B','#F97316','#EF4444','#BE123C'],
      borderWidth:2, borderColor:'#fff'
    }]
  },
  options:{ responsive:true, cutout:'60%',
    plugins:{ legend:{ position:'bottom', labels:{ font:{family:'Prompt',size:11}, padding:10 } } }
  }
});

new Chart(document.getElementById('bmiDistChart'), {
  type:'bar',
  data:{
    labels:<?=json_encode(array_keys($bmi_ranges))?>,
    datasets:[{
      label:'จำนวนนักเรียน',
      data:<?=json_encode(array_values($bmi_ranges))?>,
      backgroundColor:['#F43F5E','#F59E0B','#10B981','#10B981','#F59E0B','#F43F5E'],
      borderRadius:6, borderSkipped:false
    }]
  },
  options:{
    responsive:true,
    plugins:{ legend:{display:false} },
    scales:{
      x:{ grid:{display:false}, ticks:{font:{family:'Prompt',size:10}} },
      y:{ beginAtZero:true, ticks:{stepSize:1, font:{family:'Prompt',size:10}}, grid:{color:'#f3f4f6'} }
    }
  }
});
</script>
<?php endif; ?>

<?php endif; ?>

<?php require_once __DIR__ . '/../components/layout_end.php'; ?>
