<?php
session_start();
require_once __DIR__ . '/../config.php';
if (!isset($_SESSION['llw_role'])) { header('Location: ' . $base_path . '/login.php'); exit(); }
if ($_SESSION['llw_role'] !== 'super_admin') { header('Location: ' . $base_path . '/login.php'); exit(); }

$pdo = getPdo();

$total_units   = (int)$pdo->query("SELECT COUNT(*) FROM lms_units")->fetchColumn();
$total_pre_q   = (int)$pdo->query("SELECT COUNT(*) FROM lms_pre_questions")->fetchColumn();
$total_post_q  = (int)$pdo->query("SELECT COUNT(*) FROM lms_post_questions")->fetchColumn();
$total_pre_done = (int)$pdo->query("SELECT COUNT(DISTINCT student_uid) FROM lms_student_pre_exam WHERE passed=1")->fetchColumn();
$total_post_done = (int)$pdo->query("SELECT COUNT(DISTINCT student_uid) FROM lms_student_post_exam WHERE passed=1")->fetchColumn();
$settings = $pdo->query("SELECT * FROM lms_exam_settings LIMIT 1")->fetch();

// students by classroom
$class_data = $pdo->query("
    SELECT classroom, COUNT(*) cnt
    FROM att_students
    WHERE status='active' AND student_id REGEXP '^[0-9]+$'
      AND student_id NOT IN (SELECT subject_code FROM att_subjects)
    GROUP BY classroom ORDER BY classroom
")->fetchAll();
$class_labels = array_column($class_data, 'classroom');
$class_counts = array_column($class_data, 'cnt');

// recent pre-exam
$recent = $pdo->query("
    SELECT s.student_name, s.classroom, e.score, e.total, e.passed, e.taken_at
    FROM lms_student_pre_exam e
    JOIN att_students s ON s.id = e.student_uid
    ORDER BY e.taken_at DESC LIMIT 8
")->fetchAll();

$pageTitle    = 'LMS แดชบอร์ด';
$pageSubtitle = 'ภาพรวมระบบจัดการเรียนการสอน';
$activeSystem = 'lms';
require_once __DIR__ . '/../components/layout_start.php';
?>

<div class="flex items-center gap-3 mb-6">
  <div class="w-10 h-10 rounded-xl flex items-center justify-center shadow-lg" style="background:linear-gradient(135deg,#7C3AED,#4F46E5)">
    <i class="fas fa-graduation-cap text-white"></i>
  </div>
  <div>
    <h2 class="text-lg font-black text-slate-800">แดชบอร์ด LMS</h2>
    <p class="text-xs text-slate-400">ภาพรวมระบบจัดการเรียนการสอน</p>
  </div>
</div>

<!-- KPI Cards -->
<div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-4 mb-6">
<?php
$kpis = [
    ['icon'=>'fas fa-book-open',        'label'=>'หน่วยการเรียน',   'val'=>$total_units,    'color'=>'#7C3AED','bg'=>'#EDE9FE'],
    ['icon'=>'fas fa-clipboard-list',   'label'=>'ข้อสอบก่อนเรียน', 'val'=>$total_pre_q,    'color'=>'#0288D1','bg'=>'#E1F5FE'],
    ['icon'=>'fas fa-clipboard-check',  'label'=>'ข้อสอบหลังเรียน', 'val'=>$total_post_q,   'color'=>'#EF5350','bg'=>'#FFEBEE'],
    ['icon'=>'fas fa-user-check',       'label'=>'ผ่านก่อนเรียน',   'val'=>$total_pre_done, 'color'=>'#26A69A','bg'=>'#E0F2F1'],
    ['icon'=>'fas fa-award',            'label'=>'ผ่านหลังเรียน',   'val'=>$total_post_done,'color'=>'#F57C00','bg'=>'#FFF3E0'],
];
foreach ($kpis as $k): ?>
<div class="bg-white rounded-2xl shadow-lg shadow-slate-100/50 border border-slate-100 p-4 text-center">
  <div class="w-10 h-10 rounded-xl mx-auto mb-2 flex items-center justify-center text-lg"
       style="background:<?=$k['bg']?>;color:<?=$k['color']?>"><i class="<?=$k['icon']?>"></i></div>
  <div class="text-2xl font-black" style="color:<?=$k['color']?>"><?=$k['val']?></div>
  <div class="text-xs text-slate-400 mt-1"><?=$k['label']?></div>
</div>
<?php endforeach; ?>
</div>

<!-- Chart + Settings -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-5 mb-6">
  <div class="bg-white rounded-2xl shadow-xl shadow-slate-100/50 border border-slate-100 p-5 lg:col-span-2">
    <h3 class="font-black text-slate-700 mb-4 flex items-center gap-2 text-sm">
      <i class="fas fa-chart-bar text-violet-500"></i> จำนวนนักเรียนแต่ละชั้น
    </h3>
    <canvas id="classChart" height="220"></canvas>
  </div>
  <div class="bg-white rounded-2xl shadow-xl shadow-slate-100/50 border border-slate-100 p-5">
    <h3 class="font-black text-slate-700 mb-4 flex items-center gap-2 text-sm">
      <i class="fas fa-cog text-amber-500"></i> ตั้งค่าการสอบปัจจุบัน
    </h3>
    <?php if ($settings): ?>
    <div class="space-y-3">
      <div class="rounded-xl p-3 bg-blue-50 border border-blue-100">
        <div class="text-xs text-slate-500">คะแนนผ่าน (ก่อนเรียน)</div>
        <div class="text-2xl font-black text-blue-600"><?=$settings['pre_pass_score']?></div>
        <div class="text-xs text-slate-400">จาก <?=$total_pre_q?> ข้อ · สอบได้ไม่จำกัดครั้ง</div>
      </div>
      <div class="rounded-xl p-3 bg-rose-50 border border-rose-100">
        <div class="text-xs text-slate-500">คะแนนผ่าน (หลังเรียน)</div>
        <div class="text-2xl font-black text-rose-500"><?=$settings['post_pass_score']?></div>
        <div class="text-xs text-slate-400">จาก <?=$total_post_q?> ข้อ</div>
      </div>
      <div class="rounded-xl p-3 bg-violet-50 border border-violet-100">
        <div class="text-xs text-slate-500">สอบหลังเรียนได้กี่ครั้ง</div>
        <div class="text-2xl font-black text-violet-600"><?=$settings['post_max_attempts']?></div>
        <div class="text-xs text-slate-400">ครั้ง</div>
      </div>
    </div>
    <?php else: ?>
    <p class="text-slate-400 text-sm">ยังไม่ได้ตั้งค่า</p>
    <?php endif; ?>
  </div>
</div>

<!-- Recent Pre-exam -->
<div class="bg-white rounded-2xl shadow-xl shadow-slate-100/50 border border-slate-100 overflow-hidden">
  <div class="px-5 py-4 border-b border-slate-100">
    <h3 class="font-black text-slate-700 text-sm flex items-center gap-2">
      <i class="fas fa-history text-orange-500"></i> ผลสอบก่อนเรียนล่าสุด
    </h3>
  </div>
  <div class="overflow-x-auto">
    <table class="w-full text-sm">
      <thead class="bg-slate-50 text-xs text-slate-400 font-black uppercase tracking-wider">
        <tr>
          <th class="px-5 py-3 text-left">#</th>
          <th class="px-5 py-3 text-left">ชื่อ</th>
          <th class="px-5 py-3 text-left">ชั้น</th>
          <th class="px-5 py-3 text-center">คะแนน</th>
          <th class="px-5 py-3 text-center">สถานะ</th>
          <th class="px-5 py-3 text-center">เวลา</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-50">
        <?php if (empty($recent)): ?>
        <tr><td colspan="6" class="px-5 py-10 text-center text-slate-300">ยังไม่มีข้อมูล</td></tr>
        <?php endif; ?>
        <?php foreach ($recent as $i => $r): ?>
        <tr class="hover:bg-slate-50/50 transition-colors">
          <td class="px-5 py-3 text-slate-400"><?=$i+1?></td>
          <td class="px-5 py-3 font-bold text-slate-700"><?=htmlspecialchars($r['student_name'],ENT_QUOTES,'UTF-8')?></td>
          <td class="px-5 py-3"><span class="px-2 py-0.5 bg-blue-50 text-blue-600 text-xs font-bold rounded-full"><?=htmlspecialchars($r['classroom'],ENT_QUOTES,'UTF-8')?></span></td>
          <td class="px-5 py-3 text-center font-bold"><?=$r['score']?>/<?=$r['total']?></td>
          <td class="px-5 py-3 text-center">
            <?php if ($r['passed']): ?>
            <span class="px-2 py-0.5 bg-emerald-50 text-emerald-600 text-xs font-bold rounded-full"><i class="fas fa-check mr-1"></i>ผ่าน</span>
            <?php else: ?>
            <span class="px-2 py-0.5 bg-rose-50 text-rose-500 text-xs font-bold rounded-full"><i class="fas fa-times mr-1"></i>ไม่ผ่าน</span>
            <?php endif; ?>
          </td>
          <td class="px-5 py-3 text-center text-xs text-slate-400"><?=date('d/m/Y H:i',strtotime($r['taken_at']))?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
new Chart(document.getElementById('classChart'), {
  type: 'bar',
  data: {
    labels: <?=json_encode($class_labels)?>,
    datasets: [{
      label: 'จำนวนนักเรียน',
      data: <?=json_encode(array_map('intval',$class_counts))?>,
      backgroundColor: ['#7C3AED','#4F46E5','#0288D1','#26A69A','#F57C00','#EF5350','#AED581','#FFCA28'],
      borderRadius: 8, borderSkipped: false
    }]
  },
  options: {
    responsive: true,
    plugins: { legend: { display: false } },
    scales: {
      y: { beginAtZero: true, ticks: { stepSize: 1 }, grid: { color: '#f3f4f6' } },
      x: { grid: { display: false } }
    }
  }
});
</script>

<?php require_once __DIR__ . '/../components/layout_end.php'; ?>
