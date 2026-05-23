<?php
session_start();
require_once __DIR__ . '/../config.php';
if (!isset($_SESSION['llw_role'])) { header('Location: ' . $base_path . '/login.php'); exit(); }
if (!in_array($_SESSION['llw_role'], ['super_admin','att_teacher'])) { header('Location: ' . $base_path . '/login.php'); exit(); }

$pdo        = getPdo();
$is_admin   = $_SESSION['llw_role'] === 'super_admin';
$teacher_id = (int)($_SESSION['teacher_id'] ?? 0);

$_has_tid = (bool)$pdo->query("SHOW COLUMNS FROM `lms_subjects` LIKE 'teacher_id'")->fetch();

if ($_has_tid && !$is_admin) {
    $st = $pdo->prepare("SELECT * FROM lms_subjects WHERE teacher_id=? ORDER BY subject_name");
    $st->execute([$teacher_id]);
    $subjects = $st->fetchAll();
} else {
    $subjects = $pdo->query("SELECT * FROM lms_subjects ORDER BY subject_name")->fetchAll();
}

$subject_stats = [];
foreach ($subjects as $subj) {
    $sid = $subj['id'];
    $st_units = $pdo->prepare("SELECT COUNT(*) FROM lms_units WHERE subject_id=?"); $st_units->execute([$sid]); $units = (int)$st_units->fetchColumn();
    $st_preq  = $pdo->prepare("SELECT COUNT(*) FROM lms_pre_questions WHERE subject_id=?"); $st_preq->execute([$sid]); $pre_q = (int)$st_preq->fetchColumn();
    $st_postq = $pdo->prepare("SELECT COUNT(*) FROM lms_post_questions WHERE subject_id=?"); $st_postq->execute([$sid]); $post_q = (int)$st_postq->fetchColumn();
    $st_pre_pass = $pdo->prepare("SELECT COUNT(DISTINCT student_uid) FROM lms_student_pre_exam WHERE subject_id=? AND passed=1"); $st_pre_pass->execute([$sid]); $pre_pass = (int)$st_pre_pass->fetchColumn();
    $st_post_pass = $pdo->prepare("SELECT COUNT(DISTINCT student_uid) FROM lms_student_post_exam WHERE subject_id=? AND passed=1"); $st_post_pass->execute([$sid]); $post_pass = (int)$st_post_pass->fetchColumn();
    $st_cls = $pdo->prepare("SELECT GROUP_CONCAT(classroom ORDER BY classroom SEPARATOR ', ') FROM lms_subject_classrooms WHERE subject_id=?"); $st_cls->execute([$sid]); $classrooms = $st_cls->fetchColumn() ?: '—';
    $st_cfg = $pdo->prepare("SELECT * FROM lms_exam_settings WHERE subject_id=?"); $st_cfg->execute([$sid]); $cfg = $st_cfg->fetch();
    $subject_stats[$sid] = compact('units','pre_q','post_q','pre_pass','post_pass','classrooms','cfg');
}

// students per classroom (across all active students for chart context)
$class_data = $pdo->query("
    SELECT classroom, COUNT(*) cnt
    FROM att_students
    WHERE status='active' AND student_id REGEXP '^[0-9]+$'
      AND student_id NOT IN (SELECT subject_code FROM att_subjects)
    GROUP BY classroom ORDER BY classroom
")->fetchAll();

// recent activity across all subjects
$recent = $pdo->query("
    SELECT s.name AS student_name, s.classroom, e.score, e.total, e.passed, e.taken_at,
           subj.subject_name
    FROM lms_student_pre_exam e
    JOIN att_students s ON s.id = e.student_uid
    JOIN lms_subjects subj ON subj.id = e.subject_id
    ORDER BY e.taken_at DESC LIMIT 8
")->fetchAll();

$pageTitle    = 'LMS แดชบอร์ด';
$pageSubtitle = 'ภาพรวมระบบจัดการเรียนการสอน';
$activeSystem = 'lms';
require_once __DIR__ . '/../components/layout_start.php';
?>

<div class="flex items-center justify-between mb-6">
  <div class="flex items-center gap-3">
    <div class="w-10 h-10 rounded-xl flex items-center justify-center shadow-lg" style="background:linear-gradient(135deg,#7C3AED,#4F46E5)">
      <i class="fas fa-graduation-cap text-white"></i>
    </div>
    <div>
      <h2 class="text-lg font-black text-slate-800">แดชบอร์ด LMS</h2>
      <p class="text-xs text-slate-400"><?=count($subjects)?> วิชา</p>
    </div>
  </div>
  <a href="subjects.php" class="px-4 py-2 bg-violet-600 text-white text-xs font-bold rounded-xl shadow-lg shadow-violet-200 hover:bg-violet-700 transition-all">
    <i class="fas fa-book mr-1"></i> จัดการวิชา
  </a>
</div>

<?php if (empty($subjects)): ?>
<div class="bg-white rounded-2xl shadow-xl shadow-slate-100/50 border border-slate-100 p-16 text-center">
  <i class="fas fa-book-open text-5xl mb-4 block" style="color:#DDD8FE"></i>
  <p class="text-slate-400 font-bold mb-4">ยังไม่มีวิชา</p>
  <a href="subjects.php" class="px-5 py-2 bg-violet-600 text-white text-sm font-bold rounded-xl shadow-lg shadow-violet-200 hover:bg-violet-700 transition-all">
    <i class="fas fa-plus mr-1"></i> เพิ่มวิชาแรก
  </a>
</div>
<?php else: ?>

<!-- Subject Cards -->
<div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5 mb-6">
<?php foreach ($subjects as $subj):
  $sid  = $subj['id'];
  $stat = $subject_stats[$sid];
  $cfg  = $stat['cfg'];
?>
<div class="bg-white rounded-2xl shadow-xl shadow-slate-100/50 border border-slate-100 overflow-hidden">
  <div class="px-5 py-4 border-b border-slate-50 flex items-start justify-between gap-2">
    <div>
      <h3 class="font-black text-slate-800 text-sm leading-tight"><?=htmlspecialchars($subj['subject_name'],ENT_QUOTES,'UTF-8')?></h3>
      <?php if ($subj['subject_code']): ?>
      <span class="text-[10px] text-slate-400"><?=htmlspecialchars($subj['subject_code'],ENT_QUOTES,'UTF-8')?></span>
      <?php endif; ?>
    </div>
    <span class="shrink-0 px-2 py-0.5 bg-violet-50 text-violet-600 text-[10px] font-bold rounded-full"><?=$stat['classrooms']?></span>
  </div>
  <div class="grid grid-cols-4 divide-x divide-slate-50 text-center">
    <div class="py-3">
      <div class="text-xl font-black text-violet-600"><?=$stat['units']?></div>
      <div class="text-[10px] text-slate-400">หน่วย</div>
    </div>
    <div class="py-3">
      <div class="text-xl font-black text-blue-600"><?=$stat['pre_q']?></div>
      <div class="text-[10px] text-slate-400">ก่อนเรียน</div>
    </div>
    <div class="py-3">
      <div class="text-xl font-black text-rose-500"><?=$stat['post_q']?></div>
      <div class="text-[10px] text-slate-400">หลังเรียน</div>
    </div>
    <div class="py-3">
      <div class="text-xl font-black text-emerald-600"><?=$stat['post_pass']?></div>
      <div class="text-[10px] text-slate-400">ผ่านแล้ว</div>
    </div>
  </div>
  <?php if ($cfg): ?>
  <div class="px-5 py-2 bg-slate-50 flex gap-4 text-[10px] text-slate-400 border-t border-slate-100">
    <span>ก่อนเรียน ≥ <strong class="text-blue-600"><?=$cfg['pre_pass_score']?></strong></span>
    <span>หลังเรียน ≥ <strong class="text-rose-500"><?=$cfg['post_pass_score']?></strong></span>
    <span>สอบได้ <strong class="text-slate-600"><?=$cfg['post_max_attempts']?></strong> ครั้ง</span>
  </div>
  <?php endif; ?>
  <div class="px-5 py-3 flex gap-2 flex-wrap border-t border-slate-50">
    <a href="units.php?subject_id=<?=$sid?>" class="px-2.5 py-1 bg-violet-50 text-violet-700 text-[10px] font-bold rounded-lg hover:bg-violet-100 transition-all">
      <i class="fas fa-book-open mr-1"></i>หน่วย
    </a>
    <a href="pre_exam.php?subject_id=<?=$sid?>" class="px-2.5 py-1 bg-blue-50 text-blue-700 text-[10px] font-bold rounded-lg hover:bg-blue-100 transition-all">
      <i class="fas fa-clipboard-list mr-1"></i>ก่อนเรียน
    </a>
    <a href="post_exam.php?subject_id=<?=$sid?>" class="px-2.5 py-1 bg-rose-50 text-rose-700 text-[10px] font-bold rounded-lg hover:bg-rose-100 transition-all">
      <i class="fas fa-clipboard-check mr-1"></i>หลังเรียน
    </a>
    <a href="progress.php?subject_id=<?=$sid?>" class="px-2.5 py-1 bg-amber-50 text-amber-700 text-[10px] font-bold rounded-lg hover:bg-amber-100 transition-all">
      <i class="fas fa-chart-line mr-1"></i>ความคืบหน้า
    </a>
    <a href="exam_settings.php?subject_id=<?=$sid?>" class="px-2.5 py-1 bg-slate-100 text-slate-600 text-[10px] font-bold rounded-lg hover:bg-slate-200 transition-all">
      <i class="fas fa-cog mr-1"></i>ตั้งค่า
    </a>
  </div>
</div>
<?php endforeach; ?>
</div>

<!-- Chart + Recent -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
  <div class="bg-white rounded-2xl shadow-xl shadow-slate-100/50 border border-slate-100 p-5 lg:col-span-1">
    <h3 class="font-black text-slate-700 mb-4 flex items-center gap-2 text-sm">
      <i class="fas fa-chart-bar text-violet-500"></i> นักเรียนแต่ละชั้น
    </h3>
    <canvas id="classChart" height="280"></canvas>
  </div>

  <div class="bg-white rounded-2xl shadow-xl shadow-slate-100/50 border border-slate-100 overflow-hidden lg:col-span-2">
    <div class="px-5 py-4 border-b border-slate-100">
      <h3 class="font-black text-slate-700 text-sm flex items-center gap-2">
        <i class="fas fa-history text-orange-500"></i> ผลสอบก่อนเรียนล่าสุด
      </h3>
    </div>
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead class="bg-slate-50 text-xs text-slate-400 font-black uppercase tracking-wider">
          <tr>
            <th class="px-4 py-3 text-left">ชื่อ</th>
            <th class="px-4 py-3 text-left">วิชา</th>
            <th class="px-4 py-3 text-left">ชั้น</th>
            <th class="px-4 py-3 text-center">คะแนน</th>
            <th class="px-4 py-3 text-center">สถานะ</th>
            <th class="px-4 py-3 text-center">เวลา</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-50">
          <?php if (empty($recent)): ?>
          <tr><td colspan="6" class="px-5 py-10 text-center text-slate-300">ยังไม่มีข้อมูล</td></tr>
          <?php endif; ?>
          <?php foreach ($recent as $r): ?>
          <tr class="hover:bg-slate-50/50 transition-colors">
            <td class="px-4 py-3 font-bold text-slate-700 text-xs"><?=htmlspecialchars($r['student_name'],ENT_QUOTES,'UTF-8')?></td>
            <td class="px-4 py-3 text-xs text-slate-500"><?=htmlspecialchars(mb_substr($r['subject_name'],0,16),ENT_QUOTES,'UTF-8')?></td>
            <td class="px-4 py-3"><span class="px-2 py-0.5 bg-blue-50 text-blue-600 text-xs font-bold rounded-full"><?=htmlspecialchars($r['classroom'],ENT_QUOTES,'UTF-8')?></span></td>
            <td class="px-4 py-3 text-center text-xs font-bold"><?=$r['score']?>/<?=$r['total']?></td>
            <td class="px-4 py-3 text-center">
              <?php if ($r['passed']): ?>
              <span class="px-2 py-0.5 bg-emerald-50 text-emerald-600 text-xs font-bold rounded-full"><i class="fas fa-check mr-1"></i>ผ่าน</span>
              <?php else: ?>
              <span class="px-2 py-0.5 bg-rose-50 text-rose-500 text-xs font-bold rounded-full"><i class="fas fa-times mr-1"></i>ไม่ผ่าน</span>
              <?php endif; ?>
            </td>
            <td class="px-4 py-3 text-center text-xs text-slate-400"><?=date('d/m H:i',strtotime($r['taken_at']))?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<script>
new Chart(document.getElementById('classChart'), {
  type: 'bar',
  data: {
    labels: <?=json_encode(array_column($class_data,'classroom'))?>,
    datasets: [{
      label: 'นักเรียน',
      data: <?=json_encode(array_map('intval',array_column($class_data,'cnt')))?>,
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
<?php endif; ?>

<?php require_once __DIR__ . '/../components/layout_end.php'; ?>
