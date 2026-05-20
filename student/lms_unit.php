<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/_guard.php';

$pdo = getPdo();
$uid  = (int)$_SESSION['student_uid'];
$name = $_SESSION['student_name'];

$unit_id = (int)($_GET['unit_id'] ?? 0);
$unit    = $pdo->prepare("SELECT * FROM lms_units WHERE id=?");
$unit->execute([$unit_id]);
$unit = $unit->fetch();
if (!$unit) { header('Location: /student/lms.php'); exit(); }

$subject_id = (int)$unit['subject_id'];
$back_url   = '/student/lms_subject.php?subject_id='.$subject_id;

// Must have passed pre-exam for this subject
$pre_passed = $pdo->prepare("SELECT id FROM lms_student_pre_exam WHERE student_uid=? AND subject_id=? AND passed=1 LIMIT 1");
$pre_passed->execute([$uid,$subject_id]);
if (!$pre_passed->fetch()) {
    header('Location: '.$back_url); exit();
}

// Handle exercise submission
$ex_msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['exercise_id'])) {
    $ex_id  = (int)$_POST['exercise_id'];
    $answer = trim($_POST['answer_text'] ?? '');
    if ($answer !== '') {
        $exists = $pdo->prepare("SELECT id FROM lms_student_exercises WHERE student_uid=? AND exercise_id=? LIMIT 1");
        $exists->execute([$uid, $ex_id]);
        if ($exists->fetch()) {
            $pdo->prepare("UPDATE lms_student_exercises SET answer_text=?, submitted_at=NOW() WHERE student_uid=? AND exercise_id=?")
                ->execute([$answer, $uid, $ex_id]);
        } else {
            $pdo->prepare("INSERT INTO lms_student_exercises (student_uid, exercise_id, unit_id, subject_id, answer_text) VALUES (?,?,?,?,?)")
                ->execute([$uid, $ex_id, $unit_id, $subject_id, $answer]);
        }
        $ex_msg = 'success';
    }
    header('Location: /student/lms_unit.php?unit_id='.$unit_id.'&ex_done=1'); exit();
}

// Load topics
$topics = $pdo->prepare("SELECT * FROM lms_topics WHERE unit_id=? ORDER BY order_no");
$topics->execute([$unit_id]);
$topics = $topics->fetchAll();

// Load exercises
$exercises_stmt = $pdo->prepare("SELECT * FROM lms_unit_exercises WHERE unit_id=? ORDER BY id");
$exercises_stmt->execute([$unit_id]);
$exercises = $exercises_stmt->fetchAll();

// Student submissions + teacher feedback for this unit
$submitted_map = [];
foreach ($exercises as $ex) {
    $sub = $pdo->prepare("SELECT answer_text, grade, feedback, reviewed_at FROM lms_student_exercises WHERE student_uid=? AND exercise_id=? LIMIT 1");
    $sub->execute([$uid, $ex['id']]);
    $row = $sub->fetch();
    if ($row) $submitted_map[$ex['id']] = $row;
}

$ex_total    = count($exercises);
$ex_submitted = count($submitted_map);
?><!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<title><?=htmlspecialchars($unit['unit_name'],ENT_QUOTES,'UTF-8')?> | โรงเรียนละลมวิทยา</title>
<meta name="theme-color" content="#7C3AED">
<link href="https://fonts.googleapis.com/css2?family=Prompt:wght@400;600;700;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<script src="https://cdn.tailwindcss.com"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
body { font-family: 'Prompt', sans-serif; }
.topic-body { display: none; }
.topic-body.open { display: block; }
.topic-chevron { transition: transform .2s; }
.topic-chevron.open { transform: rotate(180deg); }
</style>
</head>
<body class="min-h-screen bg-slate-50 pb-10" style="padding-top:env(safe-area-inset-top)">

<!-- Header -->
<div class="text-white px-5 pt-5 pb-6 shadow-xl" style="background:linear-gradient(135deg,#7C3AED,#4F46E5)">
  <div class="flex items-center gap-3 mb-4">
    <a href="<?=$back_url?>" class="w-9 h-9 bg-white/15 rounded-xl flex items-center justify-center border border-white/20 active:bg-white/25">
      <i class="bi bi-arrow-left"></i>
    </a>
    <div class="flex-1 min-w-0">
      <h1 class="font-black text-lg leading-tight truncate"><?=htmlspecialchars($unit['unit_name'],ENT_QUOTES,'UTF-8')?></h1>
      <p class="text-violet-200 text-xs font-bold"><?=htmlspecialchars($name,ENT_QUOTES,'UTF-8')?></p>
    </div>
  </div>
  <?php if ($ex_total > 0): ?>
  <div class="bg-white/15 rounded-2xl px-4 py-3 border border-white/20">
    <div class="flex justify-between text-xs font-bold mb-2">
      <span>แบบฝึกหัด <?=$ex_submitted?>/<?=$ex_total?> ข้อ</span>
      <span><?=round($ex_submitted/$ex_total*100)?>%</span>
    </div>
    <div class="w-full bg-white/20 rounded-full h-2">
      <div class="h-2 rounded-full bg-white transition-all" style="width:<?=$ex_total?round($ex_submitted/$ex_total*100):0?>%"></div>
    </div>
  </div>
  <?php endif; ?>
</div>

<div class="px-4 py-5 space-y-3 max-w-lg mx-auto">

  <!-- Topics -->
  <?php if (empty($topics)): ?>
  <div class="bg-white rounded-2xl border border-slate-200 p-8 text-center text-slate-300 shadow-sm">
    <i class="bi bi-journal-x text-4xl mb-2 block opacity-30"></i>
    <p class="font-bold text-sm">ยังไม่มีเนื้อหา</p>
  </div>
  <?php else: ?>
  <p class="text-xs font-black text-slate-400 uppercase tracking-wider px-1">เนื้อหา (<?=count($topics)?> เรื่อง)</p>
  <?php foreach ($topics as $i => $t): ?>
  <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
    <button type="button" onclick="toggleTopic(<?=$t['id']?>)"
      class="w-full flex items-center gap-3 px-5 py-4 text-left">
      <span class="w-8 h-8 rounded-xl bg-violet-100 text-violet-600 text-xs font-black flex items-center justify-center flex-shrink-0"><?=$i+1?></span>
      <span class="flex-1 font-bold text-slate-700 text-sm"><?=htmlspecialchars($t['topic_name'],ENT_QUOTES,'UTF-8')?></span>
      <i class="bi bi-chevron-down text-slate-400 topic-chevron" id="chev_<?=$t['id']?>"></i>
    </button>
    <div class="topic-body" id="tbody_<?=$t['id']?>">
      <div class="px-5 pb-5 space-y-4 border-t border-slate-100 pt-4">
        <?php if (!empty($t['content'])): ?>
        <div class="text-sm text-slate-600 leading-relaxed whitespace-pre-wrap"><?=htmlspecialchars($t['content'],ENT_QUOTES,'UTF-8')?></div>
        <?php endif; ?>

        <?php
        // Links
        $links = $pdo->prepare("SELECT * FROM lms_topic_links WHERE topic_id=? ORDER BY id");
        $links->execute([$t['id']]);
        $links = $links->fetchAll();
        if ($links):
        ?>
        <div>
          <p class="text-xs font-black text-slate-400 mb-2"><i class="bi bi-link-45deg mr-1"></i>ลิงก์เพิ่มเติม</p>
          <div class="space-y-2">
          <?php foreach ($links as $lk): ?>
          <a href="<?=htmlspecialchars($lk['url'],ENT_QUOTES,'UTF-8')?>" target="_blank" rel="noopener"
             class="flex items-center gap-2 bg-blue-50 border border-blue-100 rounded-xl px-3 py-2.5 text-sm text-blue-700 font-bold active:opacity-70">
            <i class="bi bi-box-arrow-up-right flex-shrink-0"></i>
            <span class="truncate"><?=htmlspecialchars($lk['link_label']?:$lk['url'],ENT_QUOTES,'UTF-8')?></span>
          </a>
          <?php endforeach; ?>
          </div>
        </div>
        <?php endif; ?>

        <?php
        // YouTube
        $ytbs = $pdo->prepare("SELECT * FROM lms_topic_youtube WHERE topic_id=? ORDER BY id");
        $ytbs->execute([$t['id']]);
        $ytbs = $ytbs->fetchAll();
        if ($ytbs):
        ?>
        <div class="space-y-3">
          <p class="text-xs font-black text-slate-400"><i class="bi bi-youtube mr-1 text-red-500"></i>วิดีโอ</p>
          <?php foreach ($ytbs as $yt):
            preg_match('/(?:v=|youtu\.be\/)([A-Za-z0-9_\-]{11})/', $yt['url'], $m);
            $vid = $m[1] ?? '';
          ?>
          <?php if ($vid): ?>
          <div class="rounded-2xl overflow-hidden border border-slate-200 bg-black">
            <?php if (!empty($yt['video_label'])): ?>
            <div class="px-3 py-2 text-xs font-bold text-white bg-black/80 border-b border-white/10"><?=htmlspecialchars($yt['video_label'],ENT_QUOTES,'UTF-8')?></div>
            <?php endif; ?>
            <div class="relative" style="padding-bottom:56.25%">
              <iframe class="absolute inset-0 w-full h-full" src="https://www.youtube.com/embed/<?=$vid?>?rel=0" frameborder="0" allowfullscreen loading="lazy"></iframe>
            </div>
          </div>
          <?php else: ?>
          <a href="<?=htmlspecialchars($yt['url'],ENT_QUOTES,'UTF-8')?>" target="_blank" rel="noopener"
             class="flex items-center gap-2 bg-red-50 border border-red-100 rounded-xl px-3 py-2.5 text-sm text-red-600 font-bold">
            <i class="bi bi-play-circle-fill"></i>
            <span class="truncate"><?=htmlspecialchars($yt['video_label']?:$yt['url'],ENT_QUOTES,'UTF-8')?></span>
          </a>
          <?php endif; ?>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php
        // Files
        $files = $pdo->prepare("SELECT * FROM lms_topic_files WHERE topic_id=? ORDER BY id");
        $files->execute([$t['id']]);
        $files = $files->fetchAll();
        if ($files):
        ?>
        <div>
          <p class="text-xs font-black text-slate-400 mb-2"><i class="bi bi-paperclip mr-1"></i>ไฟล์แนบ</p>
          <div class="space-y-2">
          <?php foreach ($files as $f): ?>
          <a href="/<?=htmlspecialchars($f['filename'],ENT_QUOTES,'UTF-8')?>" target="_blank" download
             class="flex items-center gap-2 bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-sm text-slate-700 font-bold active:opacity-70">
            <i class="bi bi-file-earmark-arrow-down text-violet-500 flex-shrink-0"></i>
            <span class="truncate"><?=htmlspecialchars($f['original_name']?:basename($f['filename']),ENT_QUOTES,'UTF-8')?></span>
          </a>
          <?php endforeach; ?>
          </div>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
  <?php endif; ?>

  <!-- Exercises -->
  <?php if (!empty($exercises)): ?>
  <p class="text-xs font-black text-slate-400 uppercase tracking-wider px-1 pt-2">แบบฝึกหัด (<?=$ex_total?> ข้อ)</p>
  <?php foreach ($exercises as $ex):
    $sub  = $submitted_map[$ex['id']] ?? null;
    $done = $sub !== null;
    $reviewed = $done && $sub['reviewed_at'] !== null;
  ?>
  <div class="bg-white rounded-2xl border <?=$reviewed?'border-violet-200':($done?'border-emerald-200':'border-amber-200')?> p-5 shadow-sm">
    <div class="flex items-start justify-between gap-3 mb-3">
      <p class="font-bold text-slate-800 text-sm flex-1"><?=htmlspecialchars($ex['exercise_title'],ENT_QUOTES,'UTF-8')?></p>
      <?php if ($reviewed): ?>
      <span class="px-2.5 py-1 bg-violet-100 text-violet-700 text-xs font-black rounded-full flex-shrink-0"><i class="bi bi-patch-check-fill mr-1"></i>ตรวจแล้ว</span>
      <?php elseif ($done): ?>
      <span class="px-2.5 py-1 bg-emerald-100 text-emerald-700 text-xs font-black rounded-full flex-shrink-0"><i class="bi bi-check-circle-fill mr-1"></i>ส่งแล้ว</span>
      <?php else: ?>
      <span class="px-2.5 py-1 bg-amber-100 text-amber-700 text-xs font-black rounded-full flex-shrink-0">รอส่ง</span>
      <?php endif; ?>
    </div>
    <?php if ($done): ?>
    <div class="bg-emerald-50 rounded-xl p-3 text-xs text-emerald-700 border border-emerald-100">
      <p class="font-black mb-1">คำตอบของคุณ:</p>
      <p class="leading-relaxed"><?=nl2br(htmlspecialchars($sub['answer_text'],ENT_QUOTES,'UTF-8'))?></p>
    </div>
    <?php if ($reviewed): ?>
    <div class="mt-2 bg-violet-50 rounded-xl p-3 border border-violet-100 space-y-1">
      <div class="flex items-center justify-between">
        <p class="text-xs font-black text-violet-700"><i class="bi bi-person-check-fill mr-1"></i>ครูตรวจแล้ว</p>
        <?php if ($sub['grade'] !== null): ?>
        <span class="px-2.5 py-0.5 bg-violet-600 text-white text-xs font-black rounded-full"><?=$sub['grade']?> คะแนน</span>
        <?php endif; ?>
      </div>
      <?php if (!empty($sub['feedback'])): ?>
      <p class="text-xs text-violet-600 leading-relaxed"><?=nl2br(htmlspecialchars($sub['feedback'],ENT_QUOTES,'UTF-8'))?></p>
      <?php endif; ?>
    </div>
    <?php endif; ?>
    <button onclick="openExercise(<?=$ex['id']?>, <?=htmlspecialchars(json_encode($ex['exercise_title']),ENT_QUOTES,'UTF-8')?>, <?=htmlspecialchars(json_encode($sub['answer_text']),ENT_QUOTES,'UTF-8')?>)"
      class="mt-2 w-full py-2 border border-emerald-300 text-emerald-700 font-bold text-xs rounded-xl active:opacity-70">
      <i class="bi bi-pencil-fill mr-1"></i> แก้ไขคำตอบ
    </button>
    <?php else: ?>
    <button onclick="openExercise(<?=$ex['id']?>, <?=htmlspecialchars(json_encode($ex['exercise_title']),ENT_QUOTES,'UTF-8')?>, '')"
      class="w-full py-2.5 bg-violet-600 text-white font-bold text-sm rounded-xl shadow-lg shadow-violet-200/50 active:scale-95 transition-transform">
      <i class="bi bi-pencil-fill mr-1"></i> ตอบแบบฝึกหัด
    </button>
    <?php endif; ?>
  </div>
  <?php endforeach; ?>
  <?php endif; ?>

</div>

<!-- Exercise Modal -->
<div id="exModal" class="fixed inset-0 z-50 hidden items-center justify-center p-4" style="background:rgba(0,0,0,0.5)">
  <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg">
    <div class="flex items-center justify-between px-5 py-4 border-b border-slate-100">
      <div>
        <p class="font-black text-slate-800 text-sm" id="exModalTitle">แบบฝึกหัด</p>
      </div>
      <button onclick="closeExModal()" class="text-slate-400 active:text-slate-600"><i class="bi bi-x-lg text-lg"></i></button>
    </div>
    <form method="POST" class="p-5 space-y-4">
      <input type="hidden" name="exercise_id" id="exModalId">
      <div>
        <label class="block text-xs font-black text-slate-500 mb-2">คำตอบของคุณ <span class="text-rose-500">*</span></label>
        <textarea name="answer_text" id="exModalAnswer" rows="6" required
          class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-violet-400 resize-none"
          placeholder="พิมพ์คำตอบที่นี่..."></textarea>
      </div>
      <div class="flex gap-3">
        <button type="button" onclick="closeExModal()" class="flex-1 py-2.5 border border-slate-200 text-slate-600 font-bold text-sm rounded-xl">ยกเลิก</button>
        <button type="submit" class="flex-1 py-2.5 bg-violet-600 text-white font-bold text-sm rounded-xl shadow-lg shadow-violet-200/50">
          <i class="bi bi-send-fill mr-1"></i> ส่งคำตอบ
        </button>
      </div>
    </form>
  </div>
</div>

<script>
<?php if (isset($_GET['ex_done'])): ?>
window.addEventListener('load',()=>{
  Swal.fire({icon:'success',title:'ส่งคำตอบแล้ว',confirmButtonColor:'#7C3AED',timer:2000,showConfirmButton:false});
});
<?php endif; ?>

function toggleTopic(id) {
  const body = document.getElementById('tbody_' + id);
  const chev = document.getElementById('chev_' + id);
  body.classList.toggle('open');
  chev.classList.toggle('open');
}

function openExercise(id, title, existing) {
  document.getElementById('exModalId').value = id;
  document.getElementById('exModalTitle').textContent = title;
  document.getElementById('exModalAnswer').value = existing || '';
  const modal = document.getElementById('exModal');
  modal.classList.remove('hidden');
  modal.classList.add('flex');
  document.getElementById('exModalAnswer').focus();
}
function closeExModal() {
  const modal = document.getElementById('exModal');
  modal.classList.add('hidden');
  modal.classList.remove('flex');
}
document.getElementById('exModal').addEventListener('click', function(e) {
  if (e.target === this) closeExModal();
});
</script>
</body>
</html>
