<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/_guard.php';

$pdo  = getPdo();
$uid  = (int)$_SESSION['student_uid'];
$name = $_SESSION['student_name'];

$settings  = $pdo->query("SELECT * FROM lms_exam_settings LIMIT 1")->fetch();
$post_pass = $settings['post_pass_score'] ?? 6;
$max_att   = $settings['post_max_attempts'] ?? 3;

// Already passed?
$already_passed = $pdo->prepare("SELECT id FROM lms_student_post_exam WHERE student_uid=? AND passed=1 LIMIT 1");
$already_passed->execute([$uid]);
if ($already_passed->fetch()) {
    header('Location: /student/lms.php'); exit();
}

// Must have passed pre-exam
$pre_passed = $pdo->prepare("SELECT id FROM lms_student_pre_exam WHERE student_uid=? AND passed=1 LIMIT 1");
$pre_passed->execute([$uid]);
if (!$pre_passed->fetch()) {
    header('Location: /student/lms.php'); exit();
}

// Attempt count
$stmt = $pdo->prepare("SELECT COUNT(*) FROM lms_student_post_exam WHERE student_uid=?");
$stmt->execute([$uid]);
$attempt_count = (int)$stmt->fetchColumn();

// Max attempts reached — reset everything and redirect
if ($attempt_count >= $max_att) {
    try {
        $pdo->beginTransaction();
        $pdo->prepare("DELETE FROM lms_student_pre_exam  WHERE student_uid=?")->execute([$uid]);
        $pdo->prepare("DELETE FROM lms_student_post_exam WHERE student_uid=?")->execute([$uid]);
        $pdo->prepare("DELETE FROM lms_student_exercises WHERE student_uid=?")->execute([$uid]);
        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log($e->getMessage());
    }
    header('Location: /student/lms.php?reset=1'); exit();
}

$attempt_no = $attempt_count + 1;

// Handle submit
$result = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $questions = $pdo->query("SELECT * FROM lms_post_questions ORDER BY id")->fetchAll();
    $total = count($questions);
    $score = 0;
    $answers = [];
    foreach ($questions as $q) {
        $chosen  = (int)($_POST['q_' . $q['id']] ?? 0);
        $correct = (int)$q['correct_answer'] === $chosen;
        if ($correct) $score++;
        $answers[] = ['id' => $q['id'], 'chosen' => $chosen, 'correct_answer' => (int)$q['correct_answer'], 'is_correct' => $correct];
    }
    $passed = $score >= $post_pass ? 1 : 0;
    $pdo->prepare("INSERT INTO lms_student_post_exam (student_uid, score, total, passed, attempt_no) VALUES (?,?,?,?,?)")
        ->execute([$uid, $score, $total, $passed, $attempt_no]);

    // Re-check: if this was the last allowed attempt and still not passed, reset on next visit
    $result = ['score' => $score, 'total' => $total, 'passed' => $passed, 'answers' => $answers, 'questions' => $questions,
               'attempt_no' => $attempt_no, 'max_att' => $max_att];
}

$questions   = $pdo->query("SELECT * FROM lms_post_questions ORDER BY id")->fetchAll();
$total_q     = count($questions);
$remaining   = $max_att - $attempt_count;
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

<!-- Header -->
<div class="text-white px-5 pt-5 pb-6 shadow-xl" style="background:linear-gradient(135deg,#E11D48,#9F1239)">
  <div class="flex items-center gap-3 mb-1">
    <a href="/student/lms.php" class="w-9 h-9 bg-white/15 rounded-xl flex items-center justify-center border border-white/20 active:bg-white/25">
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
<!-- Result -->
<div class="px-4 py-5 max-w-lg mx-auto space-y-4">
  <?php
  $new_count    = $result['attempt_no'];
  $exhausted    = !$result['passed'] && $new_count >= $result['max_att'];
  ?>
  <div class="rounded-2xl p-6 text-center shadow-sm <?=$result['passed']?'border-2 border-emerald-300 bg-emerald-50':($exhausted?'border-2 border-slate-300 bg-slate-50':'border-2 border-rose-300 bg-rose-50')?>">
    <div class="text-5xl mb-3"><?=$result['passed']?'🎉':($exhausted?'🔄':'😢')?></div>
    <p class="font-black text-xl text-slate-800">
      <?=$result['passed']?'ผ่านแล้ว!':($exhausted?'ครบจำนวนครั้ง — รีเซ็ต':'ยังไม่ผ่าน')?>
    </p>
    <p class="text-4xl font-black mt-2 <?=$result['passed']?'text-emerald-600':($exhausted?'text-slate-500':'text-rose-500')?>">
      <?=$result['score']?> <span class="text-lg text-slate-400 font-bold">/ <?=$result['total']?></span>
    </p>
    <p class="text-sm text-slate-500 mt-1">เกณฑ์ผ่าน <?=$post_pass?> ข้อ</p>
    <?php if ($exhausted): ?>
    <p class="text-xs text-slate-400 mt-3 bg-white/70 rounded-xl p-2">เมื่อกลับหน้าบทเรียน ระบบจะรีเซ็ตประวัติทั้งหมดเพื่อเริ่มใหม่</p>
    <?php endif; ?>
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

  <a href="/student/lms.php"
     class="flex items-center justify-center gap-2 py-3 <?=$result['passed']?'bg-violet-600 shadow-violet-200/50':'bg-slate-600'?> text-white font-bold text-sm rounded-xl shadow-lg">
    <i class="bi bi-arrow-left"></i> กลับหน้าบทเรียน
  </a>
</div>
<?php else: ?>
<!-- Exam form -->
<form method="POST" id="examForm" class="px-4 py-5 max-w-lg mx-auto space-y-4 pb-24">
  <?php foreach ($questions as $i => $q): ?>
  <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
    <p class="text-sm font-bold text-slate-800 mb-1 leading-snug">
      <span class="text-rose-600 font-black mr-1"><?=$i+1?>.</span>
      <?=htmlspecialchars($q['question_text'],ENT_QUOTES,'UTF-8')?>
    </p>
    <?php if (!empty($q['question_img'])): ?>
    <img src="/<?=htmlspecialchars($q['question_img'],ENT_QUOTES,'UTF-8')?>" class="rounded-xl w-full max-h-48 object-contain my-3 bg-slate-50">
    <?php endif; ?>
    <div class="space-y-2 mt-3">
      <?php for($n=1;$n<=4;$n++): ?>
      <label class="choice-label flex items-center gap-3 border border-slate-200 rounded-xl px-3 py-2.5 bg-slate-50">
        <input type="radio" name="q_<?=$q['id']?>" value="<?=$n?>" class="hidden" required>
        <span class="choice-letter w-6 h-6 rounded-full bg-rose-100 text-rose-700 text-xs font-black flex items-center justify-center flex-shrink-0"><?=$n?></span>
        <span class="text-sm text-slate-700"><?=htmlspecialchars($q["choice{$n}"],ENT_QUOTES,'UTF-8')?></span>
      </label>
      <?php endfor; ?>
    </div>
  </div>
  <?php endforeach; ?>
</form>
<!-- Submit bar -->
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
  Swal.fire({icon:'success',title:'ผ่านแล้ว!',text:'คะแนน <?=$result['score']?>/<?=$result['total']?> ข้อ — ยินดีด้วย!',confirmButtonColor:'#7C3AED',timer:3000,showConfirmButton:false});
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
const radios = document.querySelectorAll('input[type=radio]');
const total  = <?=$total_q?>;
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
