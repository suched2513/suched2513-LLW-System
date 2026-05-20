<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/_guard.php';

$pdo = getPdo();
$uid = (int)$_SESSION['student_uid'];
$name = $_SESSION['student_name'];

$settings  = $pdo->query("SELECT * FROM lms_exam_settings LIMIT 1")->fetch();
$pre_pass  = $settings['pre_pass_score'] ?? 6;

// Already passed?
$already_passed = $pdo->prepare("SELECT id FROM lms_student_pre_exam WHERE student_uid=? AND passed=1 LIMIT 1");
$already_passed->execute([$uid]);
if ($already_passed->fetch()) {
    header('Location: /student/lms.php'); exit();
}

// Get attempt count
$stmt = $pdo->prepare("SELECT COUNT(*) FROM lms_student_pre_exam WHERE student_uid=?");
$stmt->execute([$uid]);
$attempt_no = (int)$stmt->fetchColumn() + 1;

// Handle submit
$result = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $questions = $pdo->query("SELECT * FROM lms_pre_questions ORDER BY id")->fetchAll();
    $total = count($questions);
    $score = 0;
    $answers = [];
    foreach ($questions as $q) {
        $chosen = (int)($_POST['q_' . $q['id']] ?? 0);
        $correct = (int)$q['correct_answer'] === $chosen;
        if ($correct) $score++;
        $answers[] = ['id' => $q['id'], 'chosen' => $chosen, 'correct_answer' => (int)$q['correct_answer'], 'is_correct' => $correct];
    }
    $passed = $score >= $pre_pass ? 1 : 0;
    $pdo->prepare("INSERT INTO lms_student_pre_exam (student_uid, score, total, passed, attempt_no) VALUES (?,?,?,?,?)")
        ->execute([$uid, $score, $total, $passed, $attempt_no]);
    $result = ['score' => $score, 'total' => $total, 'passed' => $passed, 'answers' => $answers, 'questions' => $questions];
}

$questions = $pdo->query("SELECT * FROM lms_pre_questions ORDER BY id")->fetchAll();
$total_q = count($questions);
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

<!-- Header -->
<div class="text-white px-5 pt-5 pb-6 shadow-xl" style="background:linear-gradient(135deg,#7C3AED,#4F46E5)">
  <div class="flex items-center gap-3 mb-1">
    <a href="/student/lms.php" class="w-9 h-9 bg-white/15 rounded-xl flex items-center justify-center border border-white/20 active:bg-white/25">
      <i class="bi bi-arrow-left"></i>
    </a>
    <div>
      <h1 class="font-black text-lg leading-tight">แบบทดสอบก่อนเรียน</h1>
      <p class="text-violet-200 text-xs font-bold"><?=htmlspecialchars($name,ENT_QUOTES,'UTF-8')?> · ครั้งที่ <?=$attempt_no?></p>
    </div>
  </div>
  <?php if (!$result): ?>
  <div class="mt-4 bg-white/15 rounded-2xl px-4 py-3 flex items-center justify-between border border-white/20">
    <span class="text-sm font-bold"><?=$total_q?> ข้อ · ผ่านเกณฑ์ <?=$pre_pass?> ข้อ</span>
    <span class="text-xs opacity-75">สอบได้ไม่จำกัดครั้ง</span>
  </div>
  <?php endif; ?>
</div>

<?php if ($total_q === 0): ?>
<div class="px-4 py-12 text-center text-slate-400">
  <i class="bi bi-clipboard-x text-5xl mb-3 block opacity-30"></i>
  <p class="font-bold">ยังไม่มีข้อสอบ</p>
</div>
<?php elseif ($result): ?>
<!-- Result screen -->
<div class="px-4 py-5 max-w-lg mx-auto space-y-4">
  <div class="rounded-2xl p-6 text-center shadow-sm <?=$result['passed']?'border-2 border-emerald-300 bg-emerald-50':'border-2 border-rose-300 bg-rose-50'?>">
    <div class="text-5xl mb-3"><?=$result['passed']?'🎉':'😢'?></div>
    <p class="font-black text-xl text-slate-800"><?=$result['passed']?'ผ่านแล้ว!':'ยังไม่ผ่าน'?></p>
    <p class="text-4xl font-black mt-2 <?=$result['passed']?'text-emerald-600':'text-rose-500'?>"><?=$result['score']?> <span class="text-lg text-slate-400 font-bold">/ <?=$result['total']?></span></p>
    <p class="text-sm text-slate-500 mt-1">เกณฑ์ผ่าน <?=$pre_pass?> ข้อ</p>
  </div>
  <!-- Answer review -->
  <p class="text-xs font-black text-slate-400 uppercase tracking-wider px-1">เฉลย</p>
  <?php foreach ($result['questions'] as $i => $q):
    $ans = null;
    foreach ($result['answers'] as $a) { if ($a['id'] == $q['id']) { $ans = $a; break; } }
    $is_correct = $ans && $ans['is_correct'];
  ?>
  <div class="rounded-2xl border bg-white p-4 shadow-sm <?=$is_correct?'border-emerald-200':'border-rose-200'?>">
    <p class="text-sm font-bold text-slate-700 mb-3"><?=$i+1?>. <?=htmlspecialchars($q['question_text'],ENT_QUOTES,'UTF-8')?></p>
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
  </div>
  <?php endforeach; ?>
  <div class="flex gap-3">
    <?php if ($result['passed']): ?>
    <a href="/student/lms.php" class="flex-1 py-3 bg-violet-600 text-white font-bold text-sm rounded-xl text-center shadow-lg shadow-violet-200/50">
      <i class="bi bi-mortarboard-fill mr-1"></i> เข้าสู่บทเรียน
    </a>
    <?php else: ?>
    <a href="/student/lms.php" class="flex-1 py-3 border border-slate-200 text-slate-600 font-bold text-sm rounded-xl text-center">
      <i class="bi bi-arrow-left mr-1"></i> กลับ
    </a>
    <a href="/student/lms_pre_exam.php" class="flex-1 py-3 bg-violet-600 text-white font-bold text-sm rounded-xl text-center shadow-lg shadow-violet-200/50">
      <i class="bi bi-arrow-repeat mr-1"></i> ทำซ้ำ
    </a>
    <?php endif; ?>
  </div>
</div>
<?php else: ?>
<!-- Exam form -->
<form method="POST" id="examForm" class="px-4 py-5 max-w-lg mx-auto space-y-4 pb-24">
  <?php foreach ($questions as $i => $q): ?>
  <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm" id="q<?=$q['id']?>">
    <p class="text-sm font-bold text-slate-800 mb-1 leading-snug">
      <span class="text-violet-600 font-black mr-1"><?=$i+1?>.</span>
      <?=htmlspecialchars($q['question_text'],ENT_QUOTES,'UTF-8')?>
    </p>
    <?php if (!empty($q['question_image'])): ?>
    <img src="/<?=htmlspecialchars($q['question_image'],ENT_QUOTES,'UTF-8')?>" class="rounded-xl w-full max-h-48 object-contain my-3 bg-slate-50">
    <?php endif; ?>
    <div class="space-y-2 mt-3">
      <?php for($n=1;$n<=4;$n++): ?>
      <label class="choice-label flex items-center gap-3 border border-slate-200 rounded-xl px-3 py-2.5 bg-slate-50">
        <input type="radio" name="q_<?=$q['id']?>" value="<?=$n?>" class="hidden" required>
        <span class="choice-letter w-6 h-6 rounded-full bg-violet-100 text-violet-700 text-xs font-black flex items-center justify-center flex-shrink-0"><?=$n?></span>
        <span class="text-sm text-slate-700"><?=htmlspecialchars($q["choice{$n}"],ENT_QUOTES,'UTF-8')?></span>
      </label>
      <?php endfor; ?>
    </div>
  </div>
  <?php endforeach; ?>
</form>
<!-- Submit bar -->
<div class="fixed bottom-0 left-0 right-0 bg-white/90 backdrop-blur border-t border-slate-200 px-4 py-4 safe-area-bottom">
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
<?php if ($result && $result['passed']): ?>
window.addEventListener('load', () => {
  Swal.fire({icon:'success',title:'ผ่านแล้ว!',text:'คะแนน <?=$result['score']?>/<?=$result['total']?> ข้อ — ปลดล็อกบทเรียนแล้ว',confirmButtonColor:'#7C3AED',timer:3000,showConfirmButton:false});
});
<?php elseif ($result && !$result['passed']): ?>
window.addEventListener('load', () => {
  Swal.fire({icon:'error',title:'ยังไม่ผ่าน',text:'คะแนน <?=$result['score']?>/<?=$result['total']?> ข้อ (ต้องได้ <?=$pre_pass?> ข้อ)',confirmButtonColor:'#7C3AED'});
});
<?php else: ?>
// Track answered count
const radios = document.querySelectorAll('input[type=radio]');
const total = <?=$total_q?>;
function countAnswered() {
  const names = new Set([...radios].map(r => r.name));
  let cnt = 0;
  names.forEach(n => { if (document.querySelector(`input[name="${n}"]:checked`)) cnt++; });
  document.getElementById('answered').textContent = cnt;
  document.getElementById('progressBar').style.width = (cnt/total*100)+'%';
}
radios.forEach(r => r.addEventListener('change', countAnswered));

function submitExam() {
  const names = new Set([...radios].map(r => r.name));
  let cnt = 0;
  names.forEach(n => { if (document.querySelector(`input[name="${n}"]:checked`)) cnt++; });
  if (cnt < total) {
    Swal.fire({icon:'warning',title:'ยังไม่ครบ',text:`กรุณาตอบให้ครบทุกข้อ (ตอบแล้ว ${cnt}/${total} ข้อ)`,confirmButtonColor:'#7C3AED'});
    return;
  }
  Swal.fire({
    icon:'question', title:'ส่งคำตอบ?',
    text:`ตอบครบ ${total} ข้อแล้ว`,
    showCancelButton:true, confirmButtonColor:'#7C3AED',
    cancelButtonText:'ตรวจสอบอีกครั้ง', confirmButtonText:'ส่งเลย'
  }).then(r => { if (r.isConfirmed) document.getElementById('examForm').submit(); });
}
<?php endif; ?>
</script>
</body>
</html>
