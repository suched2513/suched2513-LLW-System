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

// Already submitted pre-exam? Redirect back (pre-exam is once-only)
$already_done = $pdo->prepare("SELECT id FROM lms_student_pre_exam WHERE student_uid=? AND subject_id=? LIMIT 1");
$already_done->execute([$uid,$subject_id]);
if ($already_done->fetch()) {
    header('Location: /student/lms_subject.php?subject_id='.$subject_id); exit();
}

$attempt_no = 1;

// Handle submit
$result = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $qs = $pdo->prepare("SELECT * FROM lms_pre_questions WHERE subject_id=? ORDER BY id");
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
    // Pre-exam always passes — it's before-learning assessment only
    $pdo->prepare("INSERT INTO lms_student_pre_exam (student_uid,subject_id,score,total,passed,attempt_no) VALUES (?,?,?,?,1,?)")
        ->execute([$uid,$subject_id,$score,$total_choice,$attempt_no]);
    $result = ['score'=>$score,'total'=>$total_choice,'answers'=>$answers,'questions'=>$questions_post];
}

$qs = $pdo->prepare("SELECT * FROM lms_pre_questions WHERE subject_id=? ORDER BY id");
$qs->execute([$subject_id]); $questions = $qs->fetchAll();
$total_q = count($questions);

// Pre-generate shuffled choice order for each question (only for choice type)
$shuffled_orders = [];
foreach ($questions as $q) {
    $order = [1,2,3,4]; shuffle($order);
    $shuffled_orders[$q['id']] = $order;
}

$back_url = '/student/lms_subject.php?subject_id='.$subject_id;
?><!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<title>แบบทดสอบก่อนเรียน | โรงเรียนละลมวิทยา</title>
<meta name="theme-color" content="#7C3AED">
<link href="https://fonts.googleapis.com/css2?family=Prompt:wght@400;600;700;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<script src="https://cdn.tailwindcss.com"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
body { font-family: 'Prompt', sans-serif; }
.choice-label { cursor: pointer; transition: all .15s; }
.choice-label:has(input:checked) { background: #7C3AED; color: white; border-color: #7C3AED; }
.choice-label:has(input:checked) .choice-letter { background: white; color: #7C3AED; }
</style>
</head>
<body class="min-h-screen bg-slate-50" style="padding-top:env(safe-area-inset-top)">

<div class="text-white px-5 pt-5 pb-6 shadow-xl" style="background:linear-gradient(135deg,#7C3AED,#4F46E5)">
  <div class="flex items-center gap-3 mb-1">
    <a href="<?=$back_url?>" class="w-9 h-9 bg-white/15 rounded-xl flex items-center justify-center border border-white/20 active:bg-white/25">
      <i class="bi bi-arrow-left"></i>
    </a>
    <div>
      <h1 class="font-black text-lg leading-tight">แบบทดสอบก่อนเรียน</h1>
      <p class="text-violet-200 text-xs font-bold"><?=htmlspecialchars($name,ENT_QUOTES,'UTF-8')?></p>
    </div>
  </div>
  <?php if (!$result): ?>
  <div class="mt-4 bg-white/15 rounded-2xl px-4 py-3 flex items-center justify-between border border-white/20">
    <span class="text-sm font-bold"><?=$total_q?> ข้อ</span>
    <span class="text-xs opacity-75">วัดความรู้ก่อนเรียน</span>
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
  <div class="rounded-2xl p-6 text-center shadow-sm border-2 border-violet-200 bg-violet-50">
    <div class="text-5xl mb-3">📝</div>
    <p class="font-black text-xl text-slate-800">ส่งคำตอบแล้ว</p>
    <?php if ($result['total'] > 0): ?>
    <p class="text-4xl font-black mt-2 text-violet-600"><?=$result['score']?> <span class="text-lg text-slate-400 font-bold">/ <?=$result['total']?></span></p>
    <p class="text-sm text-slate-500 mt-1">คะแนนข้อปรนัย</p>
    <?php endif; ?>
  </div>
  <p class="text-xs font-black text-slate-400 uppercase tracking-wider px-1">คำตอบของคุณ</p>
  <?php
  $ans_map = [];
  foreach ($result['answers'] as $a) $ans_map[$a['id']] = $a;
  foreach ($result['questions'] as $i => $q):
    $ans = $ans_map[$q['id']] ?? null;
    $qtype = $q['question_type'] ?? 'choice';
  ?>
  <div class="rounded-2xl border bg-white p-4 shadow-sm border-slate-200">
    <p class="text-sm font-bold text-slate-700 mb-3"><?=$i+1?>. <?=htmlspecialchars($q['question_text'],ENT_QUOTES,'UTF-8')?></p>
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
  <a href="<?=$back_url?>" class="block py-3 bg-violet-600 text-white font-bold text-sm rounded-xl text-center shadow-lg shadow-violet-200/50">
    <i class="bi bi-mortarboard-fill mr-1"></i> เข้าสู่บทเรียน
  </a>
</div>
<?php else: ?>
<form method="POST" id="examForm" class="px-4 py-5 max-w-lg mx-auto space-y-4 pb-24">
  <input type="hidden" name="subject_id" value="<?=$subject_id?>">
  <?php foreach ($questions as $i => $q): $qtype = $q['question_type'] ?? 'choice'; ?>
  <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
    <p class="text-sm font-bold text-slate-800 mb-1 leading-snug">
      <span class="text-violet-600 font-black mr-1"><?=$i+1?>.</span>
      <?=htmlspecialchars($q['question_text'],ENT_QUOTES,'UTF-8')?>
      <?php if ($qtype === 'text'): ?>
      <span class="ml-1 px-1.5 py-0.5 bg-violet-100 text-violet-600 text-[10px] font-black rounded-full">อัตนัย</span>
      <?php endif; ?>
    </p>
    <?php if (!empty($q['question_img'])): ?>
    <img src="/uploads/lms/questions/<?=htmlspecialchars($q['question_img'],ENT_QUOTES,'UTF-8')?>" class="rounded-xl w-full max-h-48 object-contain my-3 bg-slate-50">
    <?php endif; ?>
    <?php if ($qtype === 'text'): ?>
    <textarea name="q_<?=$q['id']?>" rows="3" class="w-full mt-3 border border-slate-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-violet-400 outline-none resize-none" placeholder="พิมพ์คำตอบของคุณ..." data-type="text"></textarea>
    <?php else: ?>
    <div class="space-y-2 mt-3">
      <?php foreach ($shuffled_orders[$q['id']] as $n): ?>
      <label class="choice-label flex items-center gap-3 border border-slate-200 rounded-xl px-3 py-2.5 bg-slate-50">
        <input type="radio" name="q_<?=$q['id']?>" value="<?=$n?>" class="hidden" data-type="choice">
        <span class="choice-letter w-6 h-6 rounded-full bg-violet-100 text-violet-700 text-xs font-black flex items-center justify-center flex-shrink-0"><?=$n?></span>
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
        <div id="progressBar" class="h-1.5 rounded-full bg-violet-500 transition-all" style="width:0%"></div>
      </div>
    </div>
    <button type="button" onclick="submitExam()" class="px-6 py-2.5 bg-violet-600 text-white font-bold text-sm rounded-xl shadow-lg shadow-violet-200/50 active:scale-95 transition-transform">
      ส่งคำตอบ
    </button>
  </div>
</div>
<?php endif; ?>

<script>
<?php if ($result): ?>
window.addEventListener('load', () => {
  Swal.fire({icon:'success',title:'ส่งคำตอบแล้ว',<?php if($result['total']>0): ?>text:'คะแนนข้อปรนัย <?=$result['score']?>/<?=$result['total']?> ข้อ',<?php endif; ?>confirmButtonColor:'#7C3AED',timer:2500,showConfirmButton:false});
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
    Swal.fire({icon:'warning',title:'ยังไม่ครบ',text:`กรุณาตอบให้ครบทุกข้อ (ตอบแล้ว ${cnt}/${total} ข้อ)`,confirmButtonColor:'#7C3AED'});
    return;
  }
  Swal.fire({
    icon:'question', title:'ส่งคำตอบ?', text:`ตอบครบ ${total} ข้อแล้ว`,
    showCancelButton:true, confirmButtonColor:'#7C3AED',
    cancelButtonText:'ตรวจสอบอีกครั้ง', confirmButtonText:'ส่งเลย'
  }).then(r => { if (r.isConfirmed) document.getElementById('examForm').submit(); });
}
<?php endif; ?>
</script>
</body>
</html>
