<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/_guard.php';

$pdo   = getPdo();
$uid   = (int)$_SESSION['student_uid'];
$name  = $_SESSION['student_name'];
$class = $_SESSION['student_class'];

$subject_id = (int)($_GET['subject_id'] ?? 0);
if (!$subject_id) { header('Location: /student/lms.php'); exit(); }

// Verify subject exists and student's classroom is enrolled
$ss = $pdo->prepare("
    SELECT s.* FROM lms_subjects s
    JOIN lms_subject_classrooms sc ON sc.subject_id=s.id
    WHERE s.id=? AND sc.classroom=?
    LIMIT 1
");
$ss->execute([$subject_id,$class]);
$subject = $ss->fetch();
if (!$subject) { header('Location: /student/lms.php'); exit(); }

// Show reset banner if just reset
$was_reset = isset($_GET['reset']);

$es = $pdo->prepare("SELECT * FROM lms_exam_settings WHERE subject_id=?"); $es->execute([$subject_id]); $settings = $es->fetch();
$pre_pass  = $settings['pre_pass_score'] ?? 6;
$post_pass = $settings['post_pass_score'] ?? 6;
$max_att   = $settings['post_max_attempts'] ?? 3;

$now_ts   = time();
$open_ts  = !empty($settings['post_exam_open_at'])  ? strtotime($settings['post_exam_open_at'])  : null;
$close_ts = !empty($settings['post_exam_close_at']) ? strtotime($settings['post_exam_close_at']) : null;
$post_window_set = ($open_ts || $close_ts);
$post_in_window  = (!$open_ts || $now_ts >= $open_ts) && (!$close_ts || $now_ts <= $close_ts);
$was_exam_closed = isset($_GET['exam_closed']);

$tpre = $pdo->prepare("SELECT COUNT(*) FROM lms_pre_questions WHERE subject_id=?"); $tpre->execute([$subject_id]); $total_pre = (int)$tpre->fetchColumn();
$tpost = $pdo->prepare("SELECT COUNT(*) FROM lms_post_questions WHERE subject_id=?"); $tpost->execute([$subject_id]); $total_post = (int)$tpost->fetchColumn();

// Pre-exam status
$pre_passed = $pdo->prepare("SELECT id,score,total FROM lms_student_pre_exam WHERE student_uid=? AND subject_id=? AND passed=1 LIMIT 1");
$pre_passed->execute([$uid,$subject_id]); $pre_passed = $pre_passed->fetch();

// Auto-pass pre-exam when no questions exist
if (!$pre_passed && $total_pre === 0) {
    $pre_passed = ['id' => 0, 'score' => 0, 'total' => 0, 'auto' => true];
}

$pre_latest = $pdo->prepare("SELECT score,total,attempt_no FROM lms_student_pre_exam WHERE student_uid=? AND subject_id=? ORDER BY taken_at DESC LIMIT 1");
$pre_latest->execute([$uid,$subject_id]); $pre_latest = $pre_latest->fetch();

// Post-exam status
$post_passed = $pdo->prepare("SELECT id,score,total FROM lms_student_post_exam WHERE student_uid=? AND subject_id=? AND passed=1 LIMIT 1");
$post_passed->execute([$uid,$subject_id]); $post_passed = $post_passed->fetch();

// Auto-pass post-exam when no questions exist
if (!$post_passed && $total_post === 0) {
    $post_passed = ['id' => 0, 'score' => 0, 'total' => 0, 'auto' => true];
}

$stmt = $pdo->prepare("SELECT COUNT(*) FROM lms_student_post_exam WHERE student_uid=? AND subject_id=?");
$stmt->execute([$uid,$subject_id]); $post_attempt_count = (int)$stmt->fetchColumn();

// Units with progress (batch queries — no N+1)
$units_stmt = $pdo->prepare("SELECT * FROM lms_units WHERE subject_id=? ORDER BY order_no");
$units_stmt->execute([$subject_id]); $units = $units_stmt->fetchAll();

$unit_progress = [];
$total_all_ex  = 0;
$total_all_sub = 0;
if (!empty($units)) {
    $uids = array_column($units, 'id');
    $uph  = implode(',', array_fill(0, count($uids), '?'));

    $exr = $pdo->prepare("SELECT id, unit_id FROM lms_unit_exercises WHERE unit_id IN ($uph) ORDER BY id");
    $exr->execute($uids); $all_exs = $exr->fetchAll(); $exs_by_unit = [];
    foreach ($all_exs as $ex) $exs_by_unit[$ex['unit_id']][] = $ex;

    $sub_ids = [];
    if (!empty($all_exs)) {
        $eids = array_column($all_exs, 'id');
        $eph  = implode(',', array_fill(0, count($eids), '?'));
        $sr   = $pdo->prepare("SELECT exercise_id FROM lms_student_exercises WHERE student_uid=? AND exercise_id IN ($eph) AND subject_id=?");
        $sr->execute(array_merge([$uid], $eids, [$subject_id]));
        $sub_ids = array_flip($sr->fetchAll(PDO::FETCH_COLUMN));
    }

    $tcr = $pdo->prepare("SELECT unit_id, COUNT(*) cnt FROM lms_topics WHERE unit_id IN ($uph) GROUP BY unit_id");
    $tcr->execute($uids); $topics_map = array_column($tcr->fetchAll(), 'cnt', 'unit_id');

    foreach ($units as $u) {
        $exs      = $exs_by_unit[$u['id']] ?? [];
        $ex_total = count($exs); $submitted = 0;
        foreach ($exs as $ex) { if (isset($sub_ids[$ex['id']])) $submitted++; }
        $topics_count = (int)($topics_map[$u['id']] ?? 0);
        $unit_progress[$u['id']] = ['ex_total'=>$ex_total,'submitted'=>$submitted,'topics_count'=>$topics_count];
        $total_all_ex  += $ex_total;
        $total_all_sub += $submitted;
    }
}

// Total score for this student in graded exercises
$sc = $pdo->prepare("
    SELECT COALESCE(SUM(se.grade),0) AS earned,
           COALESCE(SUM(e.max_score),0) AS possible
    FROM lms_unit_exercises e
    JOIN lms_units u ON u.id = e.unit_id
    LEFT JOIN lms_student_exercises se
           ON se.exercise_id = e.id AND se.student_uid = ? AND se.subject_id = ? AND se.reviewed_at IS NOT NULL
    WHERE u.subject_id = ? AND e.max_score > 0
");
$sc->execute([$uid, $subject_id, $subject_id]); $sc = $sc->fetch();
$score_earned   = (float)($sc['earned']   ?? 0);
$score_possible = (int)($sc['possible'] ?? 0);
?><!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<title><?=htmlspecialchars($subject['subject_name'],ENT_QUOTES,'UTF-8')?> | โรงเรียนละลมวิทยา</title>
<meta name="theme-color" content="#7C3AED">
<link href="https://fonts.googleapis.com/css2?family=Prompt:wght@400;600;700;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<script src="https://cdn.tailwindcss.com"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>body{font-family:'Prompt',sans-serif;}</style>
</head>
<body class="min-h-screen bg-slate-50" style="padding-top:env(safe-area-inset-top)">

<?php if ($was_reset): ?>
<script>
window.addEventListener('load',()=>{
  Swal.fire({icon:'info',title:'รีเซ็ตแล้ว',text:'ประวัติสอบถูกรีเซ็ตทั้งหมด เริ่มต้นใหม่ได้เลย',confirmButtonColor:'#7C3AED',timer:3000,showConfirmButton:false});
});
</script>
<?php endif; ?>
<?php if ($was_exam_closed): ?>
<script>
window.addEventListener('load',()=>{
  Swal.fire({icon:'warning',title:'ยังไม่ถึงเวลาสอบ',text:'แบบทดสอบหลังเรียนยังไม่เปิดในขณะนี้ กรุณารอจนกว่าจะถึงเวลาที่กำหนด',confirmButtonColor:'#7C3AED'});
});
</script>
<?php endif; ?>

<!-- Header -->
<div class="text-white px-5 pt-5 pb-6 shadow-xl" style="background:linear-gradient(135deg,#7C3AED,#4F46E5)">
  <div class="flex items-center gap-3 mb-4">
    <a href="/student/lms.php" class="w-9 h-9 bg-white/15 rounded-xl flex items-center justify-center border border-white/20 active:bg-white/25">
      <i class="bi bi-arrow-left"></i>
    </a>
    <div class="flex-1 min-w-0">
      <h1 class="font-black text-base leading-tight truncate"><?=htmlspecialchars($subject['subject_name'],ENT_QUOTES,'UTF-8')?></h1>
      <p class="text-violet-200 text-xs font-bold"><?=htmlspecialchars($name,ENT_QUOTES,'UTF-8')?> · <?=htmlspecialchars($class,ENT_QUOTES,'UTF-8')?></p>
    </div>
  </div>
  <div class="grid grid-cols-3 gap-3">
    <div class="bg-white/15 rounded-2xl p-3 text-center border border-white/20">
      <p class="text-xl font-black"><?=count($units)?></p>
      <p class="text-xs opacity-80 font-bold">หน่วยเรียน</p>
    </div>
    <div class="bg-white/15 rounded-2xl p-3 text-center border border-white/20">
      <p class="text-xl font-black"><?=$pre_passed?'✓':'—'?></p>
      <p class="text-xs opacity-80 font-bold">ก่อนเรียน</p>
    </div>
    <div class="bg-white/15 rounded-2xl p-3 text-center border border-white/20">
      <p class="text-xl font-black"><?=$post_passed?'✓':'—'?></p>
      <p class="text-xs opacity-80 font-bold">หลังเรียน</p>
    </div>
  </div>
  <?php if ($score_possible > 0): ?>
  <?php $spct = min(100, round($score_earned / $score_possible * 100)); ?>
  <div class="mt-3 bg-white/15 rounded-2xl px-4 py-2.5 border border-white/20">
    <div class="flex items-center justify-between mb-1.5">
      <span class="text-xs font-bold opacity-80">คะแนนรวม</span>
      <span class="text-sm font-black"><?=rtrim(rtrim(number_format($score_earned,1),'0'),'.') ?> / <?=$score_possible?> คะแนน
        <span class="text-xs opacity-70">(<?=$spct?>%)</span>
      </span>
    </div>
    <div class="w-full bg-white/20 rounded-full h-1.5">
      <div class="h-1.5 rounded-full bg-white transition-all" style="width:<?=$spct?>%"></div>
    </div>
  </div>
  <?php endif; ?>
  <?php if ($pre_passed && $total_all_ex > 0): ?>
  <a href="/student/lms_assignments.php?subject_id=<?=$subject_id?>"
     class="mt-3 flex items-center justify-between px-4 py-3 bg-white/15 rounded-2xl border border-white/25 active:bg-white/25 transition-all">
    <div class="flex items-center gap-2">
      <i class="bi bi-list-task text-sm"></i>
      <span class="text-sm font-black">ดูงานทั้งหมด</span>
    </div>
    <div class="flex items-center gap-2">
      <span class="text-xs font-bold">
        <?=$total_all_sub?>/<?=$total_all_ex?> ชิ้น
        <?php if ($total_all_sub >= $total_all_ex): ?><i class="bi bi-check-circle-fill text-emerald-300 ml-1"></i><?php endif; ?>
      </span>
      <i class="bi bi-chevron-right text-xs opacity-60"></i>
    </div>
  </a>
  <?php endif; ?>
</div>

<div class="px-4 py-5 space-y-4 max-w-lg mx-auto">

  <!-- Pre-exam card -->
  <div class="rounded-2xl border p-5 shadow-sm <?=$pre_passed?'border-emerald-200 bg-emerald-50/50':'border-blue-200 bg-blue-50/50'?>">
    <div class="flex items-start justify-between gap-3">
      <div>
        <p class="font-black text-slate-800 text-sm">แบบทดสอบก่อนเรียน</p>
        <p class="text-xs text-slate-400 mt-0.5">วัดความรู้ก่อนเรียน · ทำครั้งเดียว</p>
      </div>
      <?php if ($pre_passed): ?>
      <span class="px-2.5 py-1 bg-emerald-100 text-emerald-700 text-xs font-black rounded-full flex-shrink-0">ทำแล้ว</span>
      <?php else: ?>
      <span class="px-2.5 py-1 bg-blue-100 text-blue-700 text-xs font-black rounded-full flex-shrink-0">รอทำ</span>
      <?php endif; ?>
    </div>
    <?php if ($pre_passed): ?>
    <?php if (!empty($pre_passed['auto'])): ?>
    <div class="mt-3 p-3 bg-white rounded-xl border border-emerald-100 text-center">
      <p class="text-xs text-slate-500">ยังไม่มีข้อสอบ — เข้าเรียนได้เลย</p>
    </div>
    <?php else: ?>
    <div class="mt-3 p-3 bg-white rounded-xl border border-emerald-100 text-center">
      <p class="text-xs text-slate-500">คะแนนที่ทำได้</p>
      <p class="text-lg font-black text-emerald-600"><?=$pre_passed['score']?> / <?=$pre_passed['total']?></p>
    </div>
    <?php endif; ?>
    <?php else: ?>
    <a href="/student/lms_pre_exam.php?subject_id=<?=$subject_id?>"
       class="mt-3 w-full flex items-center justify-center gap-2 py-2.5 bg-blue-600 text-white font-bold text-sm rounded-xl shadow-lg shadow-blue-200/50 active:scale-95 transition-transform">
      <i class="bi bi-play-circle-fill"></i> เริ่มทำแบบทดสอบก่อนเรียน
    </a>
    <?php endif; ?>
  </div>

  <!-- Units -->
  <?php if (!$pre_passed): ?>
  <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm text-center">
    <i class="bi bi-lock-fill text-slate-300 text-3xl mb-2 block"></i>
    <p class="text-sm font-bold text-slate-400">ต้องทำแบบทดสอบก่อนเรียนก่อน</p>
  </div>
  <?php else: ?>
  <?php foreach ($units as $u):
    $p = $unit_progress[$u['id']];
    $all_done = $p['ex_total'] > 0 && $p['submitted'] >= $p['ex_total'];
  ?>
  <a href="/student/lms_unit.php?unit_id=<?=$u['id']?>"
     class="block rounded-2xl border <?=$all_done?'border-emerald-200 bg-emerald-50/50':'border-slate-200 bg-white'?> p-5 shadow-sm active:scale-[0.98] transition-transform">
    <div class="flex items-start justify-between gap-3 mb-3">
      <div class="flex-1 min-w-0">
        <p class="font-black text-slate-800 text-sm"><?=htmlspecialchars($u['unit_name'],ENT_QUOTES,'UTF-8')?></p>
        <p class="text-xs text-slate-400 mt-0.5"><?=$p['topics_count']?> เรื่อง</p>
      </div>
      <?php if ($all_done): ?>
      <span class="px-2.5 py-1 bg-emerald-100 text-emerald-700 text-xs font-black rounded-full flex-shrink-0"><i class="bi bi-check-circle-fill mr-1"></i>เสร็จแล้ว</span>
      <?php elseif ($p['ex_total'] > 0): ?>
      <span class="px-2.5 py-1 bg-amber-100 text-amber-700 text-xs font-black rounded-full flex-shrink-0"><?=$p['submitted']?>/<?=$p['ex_total']?> งาน</span>
      <?php else: ?>
      <span class="px-2.5 py-1 bg-slate-100 text-slate-500 text-xs font-black rounded-full flex-shrink-0">เข้าดูได้</span>
      <?php endif; ?>
    </div>
    <?php if ($p['ex_total'] > 0): ?>
    <div class="w-full bg-slate-100 rounded-full h-1.5">
      <div class="h-1.5 rounded-full bg-violet-500 transition-all" style="width:<?=$p['ex_total']?round($p['submitted']/$p['ex_total']*100):0?>%"></div>
    </div>
    <?php endif; ?>
  </a>
  <?php endforeach; ?>
  <?php endif; ?>

  <!-- Post-exam card -->
  <?php if ($pre_passed): ?>
  <?php
    $card_border = $post_passed ? 'border-emerald-200 bg-emerald-50/50'
                 : ($post_window_set && !$post_in_window ? 'border-slate-200 bg-slate-50/50'
                 : 'border-rose-200 bg-rose-50/50');
  ?>
  <div class="rounded-2xl border p-5 shadow-sm <?=$card_border?>">
    <div class="flex items-start justify-between gap-3">
      <div>
        <p class="font-black text-slate-800 text-sm">แบบทดสอบหลังเรียน</p>
        <p class="text-xs text-slate-400 mt-0.5">ผ่านเกณฑ์ <?=$post_pass?>/<?=$total_post?> ข้อ · สอบได้ <?=$max_att?> ครั้ง</p>
      </div>
      <?php if ($post_passed): ?>
      <span class="px-2.5 py-1 bg-emerald-100 text-emerald-700 text-xs font-black rounded-full flex-shrink-0">ผ่านแล้ว</span>
      <?php elseif ($post_attempt_count >= $max_att): ?>
      <span class="px-2.5 py-1 bg-slate-100 text-slate-500 text-xs font-black rounded-full flex-shrink-0">รีเซ็ตแล้ว</span>
      <?php elseif ($post_window_set && !$post_in_window): ?>
      <span class="px-2.5 py-1 bg-slate-100 text-slate-500 text-xs font-black rounded-full flex-shrink-0"><i class="bi bi-clock mr-1"></i>ยังไม่เปิด</span>
      <?php else: ?>
      <span class="px-2.5 py-1 bg-rose-100 text-rose-600 text-xs font-black rounded-full flex-shrink-0">รอสอบ (<?=$post_attempt_count?>/<?=$max_att?>)</span>
      <?php endif; ?>
    </div>
    <?php if ($post_passed): ?>
    <div class="mt-3 p-3 bg-white rounded-xl border border-emerald-100 text-center">
      <?php if (!empty($post_passed['auto'])): ?>
      <p class="text-xs text-slate-500">ยังไม่มีข้อสอบ — ผ่านอัตโนมัติ</p>
      <?php else: ?>
      <p class="text-xs text-slate-500">คะแนนที่ผ่าน</p>
      <p class="text-lg font-black text-emerald-600"><?=$post_passed['score']?> / <?=$post_passed['total']?></p>
      <?php endif; ?>
    </div>
    <?php elseif ($post_window_set && !$post_in_window): ?>
    <div class="mt-3 p-3 bg-white rounded-xl border border-slate-100">
      <?php if ($open_ts && $now_ts < $open_ts): ?>
      <p class="text-xs text-slate-500 text-center"><i class="bi bi-calendar-event mr-1"></i>เปิดสอบ <?=date('d/m/Y เวลา H:i', $open_ts)?> น.</p>
      <?php elseif ($close_ts && $now_ts > $close_ts): ?>
      <p class="text-xs text-slate-500 text-center"><i class="bi bi-calendar-x mr-1"></i>หมดเวลาสอบแล้ว (ปิด <?=date('d/m/Y H:i', $close_ts)?> น.)</p>
      <?php endif; ?>
    </div>
    <?php elseif (!$post_passed): ?>
    <?php if ($post_window_set && $close_ts): ?>
    <div class="mt-2 text-xs text-slate-400 flex items-center gap-1">
      <i class="bi bi-clock"></i> ปิดสอบ <?=date('d/m/Y H:i', $close_ts)?> น.
    </div>
    <?php endif; ?>
    <a href="/student/lms_post_exam.php?subject_id=<?=$subject_id?>"
       class="mt-3 w-full flex items-center justify-center gap-2 py-2.5 bg-rose-600 text-white font-bold text-sm rounded-xl shadow-lg shadow-rose-200/50 active:scale-95 transition-transform">
      <i class="bi bi-flag-fill"></i> เริ่มสอบหลังเรียน
    </a>
    <?php endif; ?>
  </div>
  <?php endif; ?>

</div>
</body>
</html>
