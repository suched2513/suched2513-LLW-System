<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/_guard.php';

$pdo  = getPdo();
$uid  = (int)$_SESSION['student_uid'];
$name = $_SESSION['student_name'];
$class= $_SESSION['student_class'];

$subject_id = (int)($_GET['subject_id'] ?? $_POST['subject_id'] ?? 0);
if (!$subject_id) { header('Location: /student/lms.php'); exit(); }

$ss = $pdo->prepare("SELECT s.* FROM lms_subjects s JOIN lms_subject_classrooms sc ON sc.subject_id=s.id WHERE s.id=? AND sc.classroom=? LIMIT 1");
$ss->execute([$subject_id,$class]); $subject = $ss->fetch();
if (!$subject) { header('Location: /student/lms.php'); exit(); }

$es = $pdo->prepare("SELECT * FROM lms_exam_settings WHERE subject_id=?"); $es->execute([$subject_id]); $settings = $es->fetch();
$post_pass = $settings['post_pass_score'] ?? 6;
$max_att   = $settings['post_max_attempts'] ?? 3;
$back_url  = '/student/lms_subject.php?subject_id='.$subject_id;

// Check post-exam time window
$now_ts    = time();
$open_ts   = !empty($settings['post_exam_open_at'])  ? strtotime($settings['post_exam_open_at'])  : null;
$close_ts  = !empty($settings['post_exam_close_at']) ? strtotime($settings['post_exam_close_at']) : null;
$window_set = ($open_ts || $close_ts);
$in_window  = (!$open_ts || $now_ts >= $open_ts) && (!$close_ts || $now_ts <= $close_ts);

// Already passed?
$already_passed = $pdo->prepare("SELECT id FROM lms_student_post_exam WHERE student_uid=? AND subject_id=? AND passed=1 LIMIT 1");
$already_passed->execute([$uid,$subject_id]);
if ($already_passed->fetch()) { header('Location: '.$back_url); exit(); }

// Must have done pre-exam (passed=1 since pre always passes now)
$pre_done = $pdo->prepare("SELECT id FROM lms_student_pre_exam WHERE student_uid=? AND subject_id=? AND passed=1 LIMIT 1");
$pre_done->execute([$uid,$subject_id]);
if (!$pre_done->fetch()) { header('Location: '.$back_url); exit(); }

// Block if outside time window (skip block if student already passed — let redirect above handle it)
if ($window_set && !$in_window) {
    header('Location: '.$back_url.'&exam_closed=1'); exit();
}

// Attempt count
$stmt = $pdo->prepare("SELECT COUNT(*) FROM lms_student_post_exam WHERE student_uid=? AND subject_id=?");
$stmt->execute([$uid,$subject_id]);
$attempt_count = (int)$stmt->fetchColumn();

// Max attempts reached — reset this subject
if ($attempt_count >= $max_att) {
    try {
        $pdo->beginTransaction();
        $pdo->prepare("DELETE FROM lms_student_pre_exam  WHERE student_uid=? AND subject_id=?")->execute([$uid,$subject_id]);
        $pdo->prepare("DELETE FROM lms_student_post_exam WHERE student_uid=? AND subject_id=?")->execute([$uid,$subject_id]);
        $pdo->prepare("DELETE FROM lms_student_exercises WHERE student_uid=? AND subject_id=?")->execute([$uid,$subject_id]);
        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack(); error_log($e->getMessage());
    }
    header('Location: '.$back_url.'&reset=1'); exit();
}

$attempt_no = $attempt_count + 1;

// Handle submit
$result = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $qs = $pdo->prepare("SELECT * FROM lms_post_questions WHERE subject_id=? ORDER BY id");
    $qs->execute([$subject_id]); $questions_post = $qs->fetchAll();
    $score = 0; $total_choice = 0; $answers = [];
    foreach ($questions_post as $q) {
        $qtype = $q['question_type'] ?? 'choice';
        if ($qtype === 'text') {
            $answers[] = ['id'=>$q['id'],'type'=>'text','text'=>trim($_POST['q_'.$q['id']] ?? '')];
            continue;
        }
        $total_choice++;
        $chosen  = (int)($_POST['q_'.$q['id']] ?? 0);
        $correct = ((int)$q['correct_answer'] === $chosen);
        if ($correct) $score++;
        $answers[] = ['id'=>$q['id'],'type'=>'choice','chosen'=>$chosen,'correct_answer'=>(int)$q['correct_answer'],'is_correct'=>$correct];
    }
    $passed = ($total_choice === 0 || $score >= $post_pass) ? 1 : 0;
    $pdo->prepare("INSERT INTO lms_student_post_exam (student_uid,subject_id,score,total,passed,attempt_no) VALUES (?,?,?,?,?,?)")
        ->execute([$uid,$subject_id,$score,$total_choice,$passed,$attempt_no]);
    $result = ['score'=>$score,'total'=>$total_choice,'passed'=>$passed,'answers'=>$answers,'questions'=>$questions_post,
               'attempt_no'=>$attempt_no,'max_att'=>$max_att];
}

$qs = $pdo->prepare("SELECT * FROM lms_post_questions WHERE subject_id=? ORDER BY id");
$qs->execute([$subject_id]); $questions = $qs->fetchAll();
$total_q   = count($questions);
$remaining = $max_att - $attempt_count;

// Pre-generate shuffled choice order per question
$shuffled_orders = [];
foreach ($questions as $q) {
    $order = [1,2,3,4]; shuffle($order);
    $shuffled_orders[$q['id']] = $order;
}
?><!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<title>แบบทดสอบหลังเรียน | โรงเรียนละลมวิทยา</title>
<meta name="theme-color" content="#E11D48">
<link href="https://fonts.googleapis.com/css2?family=Prompt:wght@400;600;700;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<script src="https://cdn.tailwindcss.com"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
body { font-family: 'Prompt', sans-serif; }
.choice-label { cursor: pointer; transition: all .15s; }
.choice-label:has(input:checked) { background: #E11D48; color: white; border-color: #E11D48; }
.choice-label:has(input:checked) .choice-letter { background: white; color: #E11D48; }
</style>
</head>
<body class="min-h-screen bg-slate-50" style="padding-top:env(safe-area-inset-top)">

<div class="text-white px-5 pt-5 pb-6 shadow-xl" style="background:linear-gradient(135deg,#E11D48,#9F1239)">
  <div class="flex items-center gap-3 mb-1">
    <a href="<?=$back_url?>" class="w-9 h-9 bg-white/15 rounded-xl flex items-center justify-center border border-white/20 active:bg-white/25">
      <i class="bi bi-arrow-left"></i>
    </a>
    <div>
      <h1 class="font-black text-lg leading-tight">แบบทดสอบหลังเรียน</h1>
      <p class="text-rose-200 text-xs font-bold"><?=htmlspecialchars($name,ENT_QUOTES,'UTF-8')?> · ครั้งที่ <?=$attempt_no?> / <?=$max_att?></p>
    </div>
  </div>
  <?php if (!$result): ?>
  <div class="mt-4 bg-white/15 rounded-2xl px-4 py-3 flex items-center justify-between border border-white/20">
    <span class="text-sm font-bold"><?=$total_q?> ข้อ · ผ่านเกณฑ์ <?=$post_pass?> ข้อ</span>
    <span class="text-xs opacity-75">เหลือ <?=$remaining?> ครั้ง</span>
  </div>
  <?php endif; ?>
</div>

<?php if ($total_q === 0): ?>
<div class="px-4 py-12 text-center text-slate-400">
  <i class="bi bi-clipboard-x text-5xl mb-3 block opacity-30"></i>
  <p class="font-bold">ยังไม่มีข้อสอบ</p>
</div>
<?php elseif ($result): ?>
<div class="px-4 py-5 max-w-lg mx-auto space-y-4">
  <?php $exhausted = !$result['passed'] && $result['attempt_no'] >= $result['max_att']; ?>
  <div class="rounded-2xl p-6 text-center shadow-sm <?=$result['passed']?'border-2 border-emerald-300 bg-emerald-50':($exhausted?'border-2 border-slate-300 bg-slate-50':'border-2 border-rose-300 bg-rose-50')?>">
    <div class="text-5xl mb-3"><?=$result['passed']?'🎉':($exhausted?'🔄':'😢')?></div>
    <p class="font-black text-xl text-slate-800"><?=$result['passed']?'ผ่านแล้ว!':($exhausted?'ครบจำนวนครั้ง — รีเซ็ต':'ยังไม่ผ่าน')?></p>
    <?php if ($result['total'] > 0): ?>
    <p class="text-4xl font-black mt-2 <?=$result['passed']?'text-emerald-600':($exhausted?'text-slate-500':'text-rose-500')?>">
      <?=$result['score']?> <span class="text-lg text-slate-400 font-bold">/ <?=$result['total']?></span>
    </p>
    <p class="text-sm text-slate-500 mt-1">เกณฑ์ผ่าน <?=$post_pass?> ข้อ</p>
    <?php endif; ?>
    <?php if ($exhausted): ?>
    <p class="text-xs text-slate-400 mt-3 bg-white/70 rounded-xl p-2">เมื่อกลับหน้าบทเรียน ระบบจะรีเซ็ตประวัติทั้งหมดเพื่อเริ่มใหม่</p>
    <?php endif; ?>
  </div>
  <p class="text-xs font-black text-slate-400 uppercase tracking-wider px-1">เฉลย</p>
  <?php
  $ans_map = [];
  foreach ($result['answers'] as $a) $ans_map[$a['id']] = $a;
  foreach ($result['questions'] as $i => $q):
    $ans = $ans_map[$q['id']] ?? null;
    $qtype = $q['question_type'] ?? 'choice';
    $is_correct = ($qtype === 'choice') && $ans && $ans['is_correct'];
  ?>
  <div class="rounded-2xl border bg-white p-4 shadow-sm <?=$qtype==='text'?'border-violet-200':($is_correct?'border-emerald-200':'border-rose-200')?>">
    <p class="text-sm font-bold text-slate-700 mb-3"><?=$i+1?>. <?=htmlspecialchars($q['question_text'],ENT_QUOTES,'UTF-8')?>
      <?php if ($qtype === 'text'): ?>
      <span class="ml-1 px-1.5 py-0.5 bg-violet-100 text-violet-600 text-[10px] font-black rounded-full">อัตนัย</span>
      <?php endif; ?>
    </p>
    <?php if ($qtype === 'text'): ?>
    <div class="rounded-xl bg-slate-50 border border-slate-200 px-3 py-2 text-sm text-slate-600">
      <?=htmlspecialchars($ans['text'] ?? '—',ENT_QUOTES,'UTF-8')?>
    </div>
    <?php else: ?>
    <?php for($n=1;$n<=4;$n++):
      $is_chosen = $ans && $ans['chosen'] == $n;
      $is_right  = $q['correct_answer'] == $n;
      $cls = 'border rounded-xl px-3 py-2 text-xs flex items-center gap-2 ';
      if ($is_right) $cls .= 'bg-emerald-50 border-emerald-300 text-emerald-700 font-bold';
      elseif ($is_chosen) $cls .= 'bg-rose-50 border-rose-300 text-rose-600';
      else $cls .= 'bg-slate-50 border-slate-100 text-slate-500';
    ?>
    <div class="<?=$cls?> mb-1.5">
      <span class="w-5 h-5 rounded-full text-center leading-5 text-[10px] font-black flex-shrink-0 <?=$is_right?'bg-emerald-500 text-white':($is_chosen?'bg-rose-400 text-white':'bg-slate-200 text-slate-500')?>"><?=$n?></span>
      <?=htmlspecialchars($q["choice{$n}"],ENT_QUOTES,'UTF-8')?>
      <?php if ($is_right): ?><i class="bi bi-check-circle-fill text-emerald-500 ml-auto"></i><?php endif; ?>
      <?php if ($is_chosen && !$is_right): ?><i class="bi bi-x-circle-fill text-rose-400 ml-auto"></i><?php endif; ?>
    </div>
    <?php endfor; ?>
    <?php endif; ?>
  </div>
  <?php endforeach; ?>
  <a href="<?=$back_url?>"
     class="flex items-center justify-center gap-2 py-3 <?=$result['passed']?'bg-violet-600 shadow-violet-200/50':'bg-slate-600'?> text-white font-bold text-sm rounded-xl shadow-lg">
    <i class="bi bi-arrow-left"></i> กลับหน้าบทเรียน
  </a>
</div>
<?php else: ?>
<form method="POST" id="examForm" class="px-4 py-5 max-w-lg mx-auto space-y-4 pb-24">
  <input type="hidden" name="subject_id" value="<?=$subject_id?>">
  <?php foreach ($questions as $i => $q): $qtype = $q['question_type'] ?? 'choice'; ?>
  <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
    <p class="text-sm font-bold text-slate-800 mb-1 leading-snug">
      <span class="text-rose-600 font-black mr-1"><?=$i+1?>.</span>
      <?=htmlspecialchars($q['question_text'],ENT_QUOTES,'UTF-8')?>
      <?php if ($qtype === 'text'): ?>
      <span class="ml-1 px-1.5 py-0.5 bg-violet-100 text-violet-600 text-[10px] font-black rounded-full">อัตนัย</span>
      <?php endif; ?>
    </p>
    <?php if (!empty($q['question_img'])): ?>
    <img src="/uploads/lms/questions/<?=htmlspecialchars($q['question_img'],ENT_QUOTES,'UTF-8')?>" class="rounded-xl w-full max-h-48 object-contain my-3 bg-slate-50">
    <?php endif; ?>
    <?php if ($qtype === 'text'): ?>
    <textarea name="q_<?=$q['id']?>" rows="3" class="w-full mt-3 border border-slate-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-rose-400 outline-none resize-none" placeholder="พิมพ์คำตอบของคุณ..." data-type="text"></textarea>
    <?php else: ?>
    <div class="space-y-2 mt-3">
      <?php foreach ($shuffled_orders[$q['id']] as $n): ?>
      <label class="choice-label flex items-center gap-3 border border-slate-200 rounded-xl px-3 py-2.5 bg-slate-50">
        <input type="radio" name="q_<?=$q['id']?>" value="<?=$n?>" class="hidden" data-type="choice">
        <span class="choice-letter w-6 h-6 rounded-full bg-rose-100 text-rose-700 text-xs font-black flex items-center justify-center flex-shrink-0"><?=$n?></span>
        <span class="text-sm text-slate-700"><?=htmlspecialchars($q["choice{$n}"],ENT_QUOTES,'UTF-8')?></span>
      </label>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
  <?php endforeach; ?>
</form>
<div class="fixed bottom-0 left-0 right-0 bg-white/90 backdrop-blur border-t border-slate-200 px-4 py-4">
  <div class="max-w-lg mx-auto flex items-center gap-4">
    <div class="flex-1">
      <div class="text-xs text-slate-400 font-bold mb-1">ตอบแล้ว <span id="answered">0</span> / <?=$total_q?> ข้อ</div>
      <div class="w-full bg-slate-100 rounded-full h-1.5">
        <div id="progressBar" class="h-1.5 rounded-full bg-rose-500 transition-all" style="width:0%"></div>
      </div>
    </div>
    <button type="button" onclick="submitExam()" class="px-6 py-2.5 bg-rose-600 text-white font-bold text-sm rounded-xl shadow-lg shadow-rose-200/50 active:scale-95 transition-transform">
      ส่งคำตอบ
    </button>
  </div>
</div>
<?php endif; ?>

<script>
<?php if ($result && $result['passed']): ?>
window.addEventListener('load', () => {
  Swal.fire({icon:'success',title:'ผ่านแล้ว!',text:'<?php if($result["total"]>0): ?>คะแนน <?=$result['score']?>/<?=$result['total']?> ข้อ — ยินดีด้วย!<?php else: ?>ผ่านอัตโนมัติ<?php endif; ?>',confirmButtonColor:'#7C3AED',timer:3000,showConfirmButton:false});
});
<?php elseif ($result && !$result['passed'] && $result['attempt_no'] >= $result['max_att']): ?>
window.addEventListener('load', () => {
  Swal.fire({icon:'info',title:'ครบจำนวนครั้งแล้ว',
    text:'ระบบจะรีเซ็ตประวัติทั้งหมดเมื่อกลับหน้าบทเรียน เพื่อให้เริ่มต้นใหม่',
    confirmButtonColor:'#64748b',confirmButtonText:'รับทราบ'});
});
<?php elseif ($result && !$result['passed']): ?>
window.addEventListener('load', () => {
  const left = <?=$result['max_att']?> - <?=$result['attempt_no']?>;
  Swal.fire({icon:'error',title:'ยังไม่ผ่าน',
    text:`คะแนน <?=$result['score']?>/<?=$result['total']?> ข้อ · เหลืออีก ${left} ครั้ง`,
    confirmButtonColor:'#E11D48'});
});
<?php else: ?>
const total = <?=$total_q?>;
function countAnswered() {
  let cnt = 0;
  const qIds = new Set();
  document.querySelectorAll('[data-type="choice"]').forEach(r => qIds.add(r.name));
  qIds.forEach(n => { if (document.querySelector(`input[name="${n}"]:checked`)) cnt++; });
  document.querySelectorAll('[data-type="text"]').forEach(ta => { if (ta.value.trim()) cnt++; });
  document.getElementById('answered').textContent = cnt;
  document.getElementById('progressBar').style.width = (cnt/total*100)+'%';
}
document.querySelectorAll('[data-type="choice"]').forEach(r => r.addEventListener('change', countAnswered));
document.querySelectorAll('[data-type="text"]').forEach(ta => ta.addEventListener('input', countAnswered));
function submitExam() {
  let cnt = 0;
  const qIds = new Set();
  document.querySelectorAll('[data-type="choice"]').forEach(r => qIds.add(r.name));
  qIds.forEach(n => { if (document.querySelector(`input[name="${n}"]:checked`)) cnt++; });
  document.querySelectorAll('[data-type="text"]').forEach(ta => { if (ta.value.trim()) cnt++; });
  if (cnt < total) {
    Swal.fire({icon:'warning',title:'ยังไม่ครบ',text:`กรุณาตอบให้ครบทุกข้อ (ตอบแล้ว ${cnt}/${total} ข้อ)`,confirmButtonColor:'#E11D48'});
    return;
  }
  Swal.fire({
    icon:'question', title:'ส่งคำตอบ?',
    text:`ครั้งที่ <?=$attempt_no?> จาก <?=$max_att?> ครั้ง`,
    showCancelButton:true, confirmButtonColor:'#E11D48',
    cancelButtonText:'ตรวจสอบอีกครั้ง', confirmButtonText:'ส่งเลย'
  }).then(r => { if (r.isConfirmed) document.getElementById('examForm').submit(); });
}
<?php endif; ?>
</script>
</body>
</html>
