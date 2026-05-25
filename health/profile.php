<?php
session_start();
require_once __DIR__ . '/../config.php';
if (!isset($_SESSION['llw_role'])) { header('Location: ' . $base_path . '/login.php'); exit(); }
if (!in_array($_SESSION['llw_role'], ['super_admin'])) { header('Location: ' . $base_path . '/login.php'); exit(); }

$pdo        = getPdo();
$student_id = (int)($_GET['student_id'] ?? 0);
if (!$student_id) { header('Location: /health/students.php'); exit(); }

$st = $pdo->prepare("SELECT * FROM att_students WHERE id=?");
$st->execute([$student_id]);
$stu = $st->fetch();
if (!$stu) { header('Location: /health/students.php'); exit(); }

// All records for this student, oldest first
$recs = $pdo->prepare("
    SELECT * FROM health_records
    WHERE student_id=?
    ORDER BY record_date ASC
");
$recs->execute([$student_id]);
$records = $recs->fetchAll();

$pageTitle    = 'ประวัติสุขภาพ: ' . $stu['name'];
$pageSubtitle = 'แนวโน้มน้ำหนัก ส่วนสูง BMI';
$activeSystem = 'health';
require_once __DIR__ . '/../components/layout_start.php';
?>

<div class="max-w-3xl mx-auto">

<!-- Back -->
<div class="flex items-center gap-3 mb-6">
  <a href="/health/students.php" class="w-9 h-9 bg-slate-100 rounded-xl flex items-center justify-center hover:bg-slate-200 transition-all">
    <i class="fas fa-arrow-left text-slate-600 text-sm"></i>
  </a>
  <div class="flex-1">
    <h2 class="text-lg font-black text-slate-800"><?=htmlspecialchars($stu['name'],ENT_QUOTES,'UTF-8')?></h2>
    <p class="text-xs text-slate-400"><?=htmlspecialchars($stu['classroom'],ENT_QUOTES,'UTF-8')?> · <?=$stu['gender']==='male'?'ชาย':'หญิง'?></p>
  </div>
  <a href="/health/record.php" class="px-4 py-2 bg-emerald-600 text-white text-xs font-bold rounded-xl shadow-lg shadow-emerald-200 hover:bg-emerald-700 transition-all">
    <i class="fas fa-plus mr-1"></i>บันทึกใหม่
  </a>
</div>

<?php if (empty($records)): ?>
<div class="bg-white rounded-2xl shadow-xl shadow-slate-100/50 border border-slate-100 p-16 text-center">
  <i class="fas fa-heartbeat text-4xl text-slate-200 mb-4 block"></i>
  <p class="text-slate-400 font-bold text-sm">ยังไม่มีข้อมูลสุขภาพ</p>
  <a href="/health/record.php" class="mt-4 inline-block px-5 py-2 bg-emerald-600 text-white text-sm font-bold rounded-xl shadow-lg shadow-emerald-200 hover:bg-emerald-700 transition-all">บันทึกครั้งแรก</a>
</div>
<?php else: ?>

<!-- Latest Stats -->
<?php $latest = end($records); reset($records); ?>
<div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
  <?php
  $stat_cards = [
    ['label'=>'น้ำหนักล่าสุด','val'=>number_format($latest['weight_kg'],1).' kg','color'=>'blue'],
    ['label'=>'ส่วนสูงล่าสุด', 'val'=>number_format($latest['height_cm'],1).' cm','color'=>'teal'],
    ['label'=>'BMI ล่าสุด',    'val'=>number_format($latest['bmi'],1),           'color'=>'violet'],
    ['label'=>'วันที่วัดล่าสุด','val'=>date('d/m/Y',strtotime($latest['record_date'])),'color'=>'slate'],
  ];
  foreach ($stat_cards as $c):
  ?>
  <div class="bg-white rounded-2xl shadow-xl shadow-<?=$c['color']?>-100/50 border border-slate-100 p-4 text-center">
    <p class="text-[10px] font-black text-slate-400 uppercase tracking-wider mb-1"><?=$c['label']?></p>
    <p class="text-xl font-black text-<?=$c['color']?>-600"><?=$c['val']?></p>
    <?php if ($c['label']==='BMI ล่าสุด' && $latest['bfa_status']): ?>
    <span class="text-[10px] font-bold text-slate-500"><?=htmlspecialchars($latest['bfa_status'],ENT_QUOTES,'UTF-8')?></span>
    <?php endif; ?>
  </div>
  <?php endforeach; ?>
</div>

<!-- Trend Charts -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-5 mb-6">
  <div class="bg-white rounded-2xl shadow-xl shadow-slate-100/50 border border-slate-100 p-5">
    <h3 class="font-black text-slate-700 text-sm mb-3 flex items-center gap-2">
      <i class="fas fa-weight text-blue-500"></i> น้ำหนัก (kg)
    </h3>
    <canvas id="weightChart" height="180"></canvas>
  </div>
  <div class="bg-white rounded-2xl shadow-xl shadow-slate-100/50 border border-slate-100 p-5">
    <h3 class="font-black text-slate-700 text-sm mb-3 flex items-center gap-2">
      <i class="fas fa-arrows-alt-v text-teal-500"></i> ส่วนสูง (cm)
    </h3>
    <canvas id="heightChart" height="180"></canvas>
  </div>
  <div class="bg-white rounded-2xl shadow-xl shadow-slate-100/50 border border-slate-100 p-5 lg:col-span-2">
    <h3 class="font-black text-slate-700 text-sm mb-3 flex items-center gap-2">
      <i class="fas fa-calculator text-violet-500"></i> BMI
    </h3>
    <canvas id="bmiChart" height="120"></canvas>
  </div>
</div>

<!-- History Table -->
<div class="bg-white rounded-2xl shadow-xl shadow-slate-100/50 border border-slate-100 overflow-hidden">
  <div class="px-5 py-4 border-b border-slate-100">
    <h3 class="font-black text-slate-700 text-sm">ประวัติทั้งหมด</h3>
  </div>
  <div class="overflow-x-auto">
    <table class="w-full text-sm">
      <thead class="bg-slate-50 text-xs text-slate-400 font-black uppercase tracking-wider">
        <tr>
          <th class="px-4 py-3 text-left">วันที่</th>
          <th class="px-4 py-3 text-center">ปี/ภาค</th>
          <th class="px-4 py-3 text-center">น้ำหนัก</th>
          <th class="px-4 py-3 text-center">ส่วนสูง</th>
          <th class="px-4 py-3 text-center">BMI</th>
          <th class="px-4 py-3 text-center">โภชนาการ</th>
          <th class="px-4 py-3 text-center">ส่วนสูงตามวัย</th>
          <th class="px-4 py-3 text-center">จัดการ</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-50">
        <?php
        $bfa_colors = ['ผอมมาก'=>'rose','ผอม'=>'amber','สมส่วน'=>'emerald','น้ำหนักเกิน'=>'orange','อ้วน'=>'rose'];
        $hfa_colors = ['เตี้ยมาก'=>'rose','เตี้ย'=>'amber','ปกติ'=>'slate','สูง'=>'blue'];
        foreach (array_reverse($records) as $r):
            $bc = $bfa_colors[$r['bfa_status']??''] ?? 'slate';
            $hc = $hfa_colors[$r['hfa_status']??''] ?? 'slate';
        ?>
        <tr class="hover:bg-slate-50/50 transition-colors">
          <td class="px-4 py-3 text-xs text-slate-500"><?=date('d/m/Y',strtotime($r['record_date']))?></td>
          <td class="px-4 py-3 text-center text-xs text-slate-400"><?=$r['academic_year']?>/<?=$r['semester']?></td>
          <td class="px-4 py-3 text-center text-xs font-bold"><?=number_format($r['weight_kg'],1)?> kg</td>
          <td class="px-4 py-3 text-center text-xs font-bold"><?=number_format($r['height_cm'],1)?> cm</td>
          <td class="px-4 py-3 text-center text-xs font-bold text-slate-600"><?=number_format($r['bmi'],1)?></td>
          <td class="px-4 py-3 text-center"><span class="px-2 py-0.5 bg-<?=$bc?>-50 text-<?=$bc?>-600 text-xs font-bold rounded-full"><?=htmlspecialchars($r['bfa_status']??'—',ENT_QUOTES,'UTF-8')?></span></td>
          <td class="px-4 py-3 text-center"><span class="px-2 py-0.5 bg-<?=$hc?>-50 text-<?=$hc?>-600 text-xs font-bold rounded-full"><?=htmlspecialchars($r['hfa_status']??'—',ENT_QUOTES,'UTF-8')?></span></td>
          <td class="px-4 py-3 text-center">
            <a href="/health/record.php?id=<?=$r['id']?>" class="px-2 py-1 bg-blue-50 text-blue-700 text-[10px] font-bold rounded-lg hover:bg-blue-100 transition-all">
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
const labels  = <?=json_encode(array_map(fn($r)=>date('d/m/Y',strtotime($r['record_date'])),$records))?>;
const weights = <?=json_encode(array_map(fn($r)=>(float)$r['weight_kg'],$records))?>;
const heights = <?=json_encode(array_map(fn($r)=>(float)$r['height_cm'],$records))?>;
const bmis    = <?=json_encode(array_map(fn($r)=>round((float)$r['bmi'],1),$records))?>;

const chartDefaults = {
    responsive:true,
    plugins:{ legend:{display:false} },
    scales:{
        x:{ grid:{display:false}, ticks:{font:{family:'Prompt',size:10}} },
        y:{ beginAtZero:false, grid:{color:'#f3f4f6'}, ticks:{font:{family:'Prompt',size:10}} }
    }
};

new Chart(document.getElementById('weightChart'),{
    type:'line', data:{ labels, datasets:[{ label:'น้ำหนัก',data:weights,
        borderColor:'#3B82F6',backgroundColor:'rgba(59,130,246,0.08)',fill:true,
        tension:0.4,pointBackgroundColor:'#3B82F6',pointRadius:4,borderWidth:2 }] },
    options:chartDefaults
});
new Chart(document.getElementById('heightChart'),{
    type:'line', data:{ labels, datasets:[{ label:'ส่วนสูง',data:heights,
        borderColor:'#14B8A6',backgroundColor:'rgba(20,184,166,0.08)',fill:true,
        tension:0.4,pointBackgroundColor:'#14B8A6',pointRadius:4,borderWidth:2 }] },
    options:chartDefaults
});
new Chart(document.getElementById('bmiChart'),{
    type:'line', data:{ labels, datasets:[{ label:'BMI',data:bmis,
        borderColor:'#8B5CF6',backgroundColor:'rgba(139,92,246,0.08)',fill:true,
        tension:0.4,pointBackgroundColor:'#8B5CF6',pointRadius:4,borderWidth:2 }] },
    options:{...chartDefaults, scales:{...chartDefaults.scales,
        y:{...chartDefaults.scales.y,
           suggestedMin:10,suggestedMax:30}}}
});
</script>

<?php endif; ?>
</div>

<?php require_once __DIR__ . '/../components/layout_end.php'; ?>
