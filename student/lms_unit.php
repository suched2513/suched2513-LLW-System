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
$back_url   = '/student/lms_subject.php?subject_id=' . $subject_id;

$pre_passed = $pdo->prepare("SELECT id FROM lms_student_pre_exam WHERE student_uid=? AND subject_id=? LIMIT 1");
$pre_passed->execute([$uid, $subject_id]);
if (!$pre_passed->fetch()) { header('Location: ' . $back_url); exit(); }

// Handle exercise submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['exercise_id'])) {
    $ex_id  = (int)$_POST['exercise_id'];
    $answer = trim($_POST['answer_text'] ?? '');

    // Multi-file upload (up to 3)
    $file_paths_arr = [];
    $allowed_mime   = ['image/jpeg','image/png','image/gif','image/webp','application/pdf'];
    $allowed_ext    = ['jpg','jpeg','png','gif','webp','pdf'];
    $max_size       = 10 * 1024 * 1024;
    $upload_dir     = __DIR__ . '/../lms/uploads/exercises/';
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

    if (!empty($_FILES['exercise_files']['name'][0])) {
        $file_count = min(count($_FILES['exercise_files']['name']), 3);
        for ($fi = 0; $fi < $file_count; $fi++) {
            if ($_FILES['exercise_files']['error'][$fi] !== UPLOAD_ERR_OK) continue;
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime  = finfo_file($finfo, $_FILES['exercise_files']['tmp_name'][$fi]);
            finfo_close($finfo);
            $ext = strtolower(pathinfo($_FILES['exercise_files']['name'][$fi], PATHINFO_EXTENSION));
            if (!in_array($mime, $allowed_mime) || !in_array($ext, $allowed_ext)) continue;
            if ($_FILES['exercise_files']['size'][$fi] > $max_size) continue;
            $fname = $uid . '_' . $ex_id . '_' . time() . '_' . $fi . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            if (move_uploaded_file($_FILES['exercise_files']['tmp_name'][$fi], $upload_dir . $fname)) {
                $file_paths_arr[] = 'lms/uploads/exercises/' . $fname;
            }
        }
    }
    $file_paths_json = !empty($file_paths_arr) ? json_encode($file_paths_arr) : null;

    if ($answer !== '' || !empty($file_paths_arr)) {
        $exists_q = $pdo->prepare("SELECT id, file_paths FROM lms_student_exercises WHERE student_uid=? AND exercise_id=? LIMIT 1");
        $exists_q->execute([$uid, $ex_id]);
        $existing = $exists_q->fetch();
        if ($existing) {
            // Delete old uploaded files if replacing
            if (!empty($file_paths_arr) && !empty($existing['file_paths'])) {
                $old_files = json_decode($existing['file_paths'], true) ?: [$existing['file_paths']];
                foreach ($old_files as $of) {
                    $op = __DIR__ . '/../' . $of;
                    if (file_exists($op)) @unlink($op);
                }
            }
            if ($file_paths_json) {
                $pdo->prepare("UPDATE lms_student_exercises SET answer_text=?, file_paths=?, submitted_at=NOW() WHERE student_uid=? AND exercise_id=?")
                    ->execute([$answer, $file_paths_json, $uid, $ex_id]);
            } else {
                $pdo->prepare("UPDATE lms_student_exercises SET answer_text=?, submitted_at=NOW() WHERE student_uid=? AND exercise_id=?")
                    ->execute([$answer, $uid, $ex_id]);
            }
        } else {
            $pdo->prepare("INSERT INTO lms_student_exercises (student_uid, exercise_id, unit_id, subject_id, answer_text, file_paths) VALUES (?,?,?,?,?,?)")
                ->execute([$uid, $ex_id, $unit_id, $subject_id, $answer, $file_paths_json]);
        }
        header('Location: /student/lms_unit.php?unit_id=' . $unit_id . '&ex_done=1'); exit();
    }
    header('Location: /student/lms_unit.php?unit_id=' . $unit_id . '&ex_err=1'); exit();
}

// Load topics
$topics = $pdo->prepare("SELECT * FROM lms_topics WHERE unit_id=? ORDER BY order_no");
$topics->execute([$unit_id]);
$topics = $topics->fetchAll();

// Load exercises with extra fields
$exercises_stmt = $pdo->prepare("SELECT id, exercise_title, description, max_score, due_date, allow_resubmit FROM lms_unit_exercises WHERE unit_id=? ORDER BY id");
$exercises_stmt->execute([$unit_id]);
$exercises = $exercises_stmt->fetchAll();

// Student submissions + feedback for this unit
$submitted_map = [];
foreach ($exercises as $ex) {
    $sub = $pdo->prepare("SELECT answer_text, file_paths, grade, feedback, reviewed_at FROM lms_student_exercises WHERE student_uid=? AND exercise_id=? LIMIT 1");
    $sub->execute([$uid, $ex['id']]);
    $row = $sub->fetch();
    if ($row) $submitted_map[$ex['id']] = $row;
}

$ex_total     = count($exercises);
$ex_submitted = count($submitted_map);
$now_ts       = time();
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
      <span>ชิ้นงาน <?=$ex_submitted?>/<?=$ex_total?> ข้อ</span>
      <span><?=round($ex_submitted/$ex_total*100)?>%</span>
    </div>
    <div class="w-full bg-white/20 rounded-full h-2">
      <div class="h-2 rounded-full bg-white transition-all" style="width:<?=$ex_total?round($ex_submitted/$ex_total*100):0?>%"></div>
    </div>
  </div>
  <?php endif; ?>
</div>

<div class="px-4 py-5 space-y-3 max-w-lg mx-auto">

  <?php if (isset($_GET['ex_done'])): ?>
  <script>window.addEventListener('load',()=>{Swal.fire({icon:'success',title:'ส่งงานแล้ว',confirmButtonColor:'#7C3AED',timer:2000,showConfirmButton:false});});</script>
  <?php elseif (isset($_GET['ex_err'])): ?>
  <script>window.addEventListener('load',()=>{Swal.fire({icon:'warning',title:'กรุณาส่งงาน',text:'พิมพ์คำตอบหรือแนบไฟล์อย่างน้อย 1 อย่าง',confirmButtonColor:'#7C3AED'});});</script>
  <?php endif; ?>

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
        $links = $pdo->prepare("SELECT * FROM lms_topic_links WHERE topic_id=? ORDER BY id");
        $links->execute([$t['id']]); $links = $links->fetchAll();
        if ($links): ?>
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
        $ytbs = $pdo->prepare("SELECT * FROM lms_topic_youtube WHERE topic_id=? ORDER BY id");
        $ytbs->execute([$t['id']]); $ytbs = $ytbs->fetchAll();
        if ($ytbs): ?>
        <div class="space-y-3">
          <p class="text-xs font-black text-slate-400"><i class="bi bi-youtube mr-1 text-red-500"></i>วิดีโอ</p>
          <?php foreach ($ytbs as $yt):
            preg_match('/(?:v=|youtu\.be\/)([A-Za-z0-9_\-]{11})/', $yt['url'], $m);
            $vid = $m[1] ?? '';
          ?>
          <?php if ($vid): ?>
          <div class="rounded-2xl overflow-hidden border border-slate-200 bg-black">
            <?php if (!empty($yt['video_label'])): ?><div class="px-3 py-2 text-xs font-bold text-white bg-black/80 border-b border-white/10"><?=htmlspecialchars($yt['video_label'],ENT_QUOTES,'UTF-8')?></div><?php endif; ?>
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
        $files = $pdo->prepare("SELECT * FROM lms_topic_files WHERE topic_id=? ORDER BY id");
        $files->execute([$t['id']]); $files = $files->fetchAll();
        if ($files): ?>
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
  <p class="text-xs font-black text-slate-400 uppercase tracking-wider px-1 pt-2">ชิ้นงาน / แบบฝึกหัด (<?=$ex_total?> ข้อ)</p>
  <?php foreach ($exercises as $ex):
    $sub      = $submitted_map[$ex['id']] ?? null;
    $done     = $sub !== null;
    $reviewed = $done && $sub['reviewed_at'] !== null;
    $due_ts   = $ex['due_date'] ? strtotime($ex['due_date']) : null;
    $overdue  = $due_ts && $now_ts > $due_ts;
    $due_soon = $due_ts && !$overdue && ($due_ts - $now_ts) < 86400;
    $can_edit = $done && (bool)$ex['allow_resubmit'] && !($reviewed && !$ex['allow_resubmit']);
  ?>
  <div class="bg-white rounded-2xl border <?=$reviewed?'border-violet-200':($done?'border-emerald-200':'border-slate-200')?> p-5 shadow-sm">
    <!-- Title row -->
    <div class="flex items-start justify-between gap-3 mb-2">
      <p class="font-bold text-slate-800 text-sm flex-1"><?=htmlspecialchars($ex['exercise_title'],ENT_QUOTES,'UTF-8')?></p>
      <?php if ($reviewed): ?>
      <span class="px-2.5 py-1 bg-violet-100 text-violet-700 text-xs font-black rounded-full flex-shrink-0"><i class="bi bi-patch-check-fill mr-1"></i>ตรวจแล้ว</span>
      <?php elseif ($done): ?>
      <span class="px-2.5 py-1 bg-emerald-100 text-emerald-700 text-xs font-black rounded-full flex-shrink-0"><i class="bi bi-check-circle-fill mr-1"></i>ส่งแล้ว</span>
      <?php else: ?>
      <span class="px-2.5 py-1 bg-amber-100 text-amber-700 text-xs font-bold rounded-full flex-shrink-0">รอส่ง</span>
      <?php endif; ?>
    </div>

    <!-- Badges row -->
    <?php if ($ex['max_score'] || $due_ts): ?>
    <div class="flex flex-wrap gap-1.5 mb-2">
      <?php if ($ex['max_score']): ?>
      <span class="px-2 py-0.5 bg-amber-50 text-amber-600 text-[10px] font-black rounded-full border border-amber-100">
        <i class="bi bi-star-fill mr-0.5"></i><?=$ex['max_score']?> คะแนน
      </span>
      <?php endif; ?>
      <?php if ($due_ts): ?>
      <span class="px-2 py-0.5 text-[10px] font-black rounded-full border <?=$overdue?'bg-rose-50 text-rose-600 border-rose-100':($due_soon?'bg-amber-50 text-amber-600 border-amber-100':'bg-slate-50 text-slate-500 border-slate-100')?>">
        <i class="bi bi-clock mr-0.5"></i>
        <?php if ($overdue): ?>เลยกำหนดแล้ว
        <?php elseif ($due_soon): ?>ใกล้หมดเขต · <?=date('d/m H:i',$due_ts)?>
        <?php else: ?>หมดเขต <?=date('d/m/Y H:i',$due_ts)?>
        <?php endif; ?>
      </span>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Description -->
    <?php if (!empty($ex['description'])): ?>
    <div class="bg-blue-50 border border-blue-100 rounded-xl px-3 py-2.5 mb-3">
      <p class="text-xs text-blue-700 leading-relaxed"><?=nl2br(htmlspecialchars($ex['description'],ENT_QUOTES,'UTF-8'))?></p>
    </div>
    <?php endif; ?>

    <!-- Submitted answer + file -->
    <?php if ($done): ?>
    <div class="bg-emerald-50/60 rounded-xl p-3 text-xs border border-emerald-100 mb-2">
      <?php if (!empty($sub['answer_text'])): ?>
      <p class="font-black text-emerald-700 mb-1">คำตอบ:</p>
      <p class="text-slate-600 leading-relaxed"><?=nl2br(htmlspecialchars($sub['answer_text'],ENT_QUOTES,'UTF-8'))?></p>
      <?php endif; ?>
      <?php if (!empty($sub['file_paths'])): ?>
      <?php $sub_files = json_decode($sub['file_paths'], true) ?: [$sub['file_paths']]; ?>
      <?php foreach ($sub_files as $fp): $fext = strtolower(pathinfo($fp, PATHINFO_EXTENSION)); ?>
      <?php if (in_array($fext, ['jpg','jpeg','png','gif','webp'])): ?>
      <img src="<?=$base_path . '/' . htmlspecialchars($fp,ENT_QUOTES,'UTF-8')?>"
           class="mt-2 w-full max-h-56 object-contain rounded-xl bg-white border border-emerald-100" alt="งานที่ส่ง">
      <?php else: ?>
      <a href="<?=$base_path . '/' . htmlspecialchars($fp,ENT_QUOTES,'UTF-8')?>" target="_blank" rel="noopener"
         class="mt-2 flex items-center gap-2 px-3 py-2 bg-white border border-emerald-100 rounded-xl text-blue-600 font-bold active:opacity-70">
        <i class="bi bi-file-earmark-pdf text-red-500 text-xl flex-shrink-0"></i><span>ดูไฟล์ที่แนบ</span>
      </a>
      <?php endif; ?>
      <?php endforeach; ?>
      <?php endif; ?>
    </div>
    <?php if ($reviewed): ?>
    <div class="bg-violet-50 rounded-xl p-3 border border-violet-100 mb-2 space-y-1">
      <div class="flex items-center justify-between">
        <p class="text-xs font-black text-violet-700"><i class="bi bi-person-check-fill mr-1"></i>ครูตรวจแล้ว</p>
        <?php if ($sub['grade'] !== null): ?>
        <span class="px-2.5 py-0.5 bg-violet-600 text-white text-xs font-black rounded-full">
          <?=$sub['grade']?><?=$ex['max_score']?' / '.$ex['max_score'].' คะแนน':' คะแนน'?>
        </span>
        <?php endif; ?>
      </div>
      <?php if (!empty($sub['feedback'])): ?>
      <p class="text-xs text-violet-600 leading-relaxed"><?=nl2br(htmlspecialchars($sub['feedback'],ENT_QUOTES,'UTF-8'))?></p>
      <?php endif; ?>
    </div>
    <?php endif; ?>
    <?php if ($can_edit): ?>
    <button onclick="openExercise(<?=$ex['id']?>,<?=json_encode($ex['exercise_title'])?>,<?=json_encode($ex['description']??'')?>,<?=(int)($ex['max_score']??0)?>,<?=$due_ts??0?>,<?=json_encode($sub['answer_text']??'')?>,<?=json_encode(json_decode($sub['file_paths']??'[]',true)??[])?>)"
      class="w-full py-2 border border-emerald-300 text-emerald-700 font-bold text-xs rounded-xl active:opacity-70">
      <i class="bi bi-pencil-fill mr-1"></i> แก้ไขงาน
    </button>
    <?php endif; ?>
    <?php else: ?>
    <button onclick="openExercise(<?=$ex['id']?>,<?=json_encode($ex['exercise_title'])?>,<?=json_encode($ex['description']??'')?>,<?=(int)($ex['max_score']??0)?>,<?=$due_ts??0?>,'',[])"
      class="w-full py-2.5 bg-violet-600 text-white font-bold text-sm rounded-xl shadow-lg shadow-violet-200/50 active:scale-95 transition-transform">
      <i class="bi bi-send-fill mr-1"></i> ส่งงาน
    </button>
    <?php endif; ?>
  </div>
  <?php endforeach; ?>
  <?php endif; ?>

</div>

<!-- Exercise Submit Modal -->
<div id="exModal" class="fixed inset-0 z-50 hidden items-center justify-center p-4" style="background:rgba(0,0,0,0.55)">
  <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg max-h-[92vh] flex flex-col">
    <!-- Header -->
    <div class="flex items-start justify-between px-5 py-4 border-b border-slate-100 flex-shrink-0">
      <div class="flex-1 min-w-0 pr-3">
        <div class="flex items-center gap-2 flex-wrap">
          <p class="font-black text-slate-800 text-sm" id="exModalTitle">ชิ้นงาน</p>
          <span id="exModalScore" class="hidden px-2 py-0.5 bg-amber-100 text-amber-700 text-[10px] font-black rounded-full"></span>
        </div>
        <p id="exModalDue" class="hidden text-[10px] font-bold mt-0.5"></p>
      </div>
      <button onclick="closeExModal()" class="text-slate-400 active:text-slate-600 flex-shrink-0"><i class="bi bi-x-lg text-lg"></i></button>
    </div>
    <!-- Body -->
    <form id="exForm" method="POST" enctype="multipart/form-data" class="p-5 space-y-3 overflow-y-auto flex-1" onsubmit="return validateExForm()">
      <input type="hidden" name="exercise_id" id="exModalId">
      <!-- Description -->
      <div id="exDescWrap" class="hidden bg-blue-50 border border-blue-100 rounded-xl px-3 py-2.5">
        <p class="text-xs text-blue-700 leading-relaxed" id="exModalDesc"></p>
      </div>
      <!-- File upload (first — most visible on mobile) -->
      <div>
        <label class="block text-xs font-black text-slate-500 mb-1.5">
          <i class="bi bi-camera mr-1"></i>ถ่ายรูปงาน / แนบไฟล์
          <span class="text-slate-400 font-normal">(ไม่บังคับถ้ามีคำตอบ)</span>
        </label>
        <input type="file" name="exercise_files[]" id="exFileInput" accept="image/*,.pdf"
          multiple class="hidden" onchange="previewExFiles(this)">
        <label for="exFileInput"
          class="flex items-center justify-center gap-2 w-full py-3 border-2 border-dashed border-slate-200 rounded-xl text-slate-400 cursor-pointer hover:border-violet-400 hover:text-violet-500 transition-all font-bold text-sm active:opacity-70">
          <i class="bi bi-camera text-xl"></i> แตะเพื่อถ่ายรูป / เลือกไฟล์
        </label>
        <p class="text-[10px] text-slate-400 mt-1 text-center">รูปหรือ PDF สูงสุด 3 ไฟล์ · 10MB/ไฟล์</p>
        <div id="exFilePreview" class="mt-2 space-y-1.5"></div>
      </div>
      <!-- Text answer -->
      <div>
        <label class="block text-xs font-black text-slate-500 mb-1.5">คำตอบ <span class="text-slate-400 font-normal">(ไม่บังคับถ้ามีไฟล์)</span></label>
        <textarea name="answer_text" id="exModalAnswer" rows="3"
          class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-violet-400 resize-none"
          placeholder="พิมพ์คำตอบที่นี่..."></textarea>
      </div>
      <!-- Buttons -->
      <div class="flex gap-3 pt-1">
        <button type="button" onclick="closeExModal()" class="flex-1 py-2.5 border border-slate-200 text-slate-600 font-bold text-sm rounded-xl active:opacity-70">ยกเลิก</button>
        <button type="submit" id="exSubmitBtn"
          class="flex-1 py-2.5 bg-violet-600 text-white font-bold text-sm rounded-xl shadow-lg shadow-violet-200/50 active:scale-95 transition-transform">
          <i class="bi bi-send-fill mr-1"></i> ส่งงาน
        </button>
      </div>
    </form>
  </div>
</div>

<script>
const BASE_PATH = '<?= $base_path ?>';

<?php if (isset($_GET['ex_done'])): ?>
window.addEventListener('load',()=>{
  Swal.fire({icon:'success',title:'ส่งงานแล้ว',confirmButtonColor:'#7C3AED',timer:2000,showConfirmButton:false});
});
<?php elseif (isset($_GET['ex_err'])): ?>
window.addEventListener('load',()=>{
  Swal.fire({icon:'warning',title:'กรุณาส่งงาน',text:'พิมพ์คำตอบหรือแนบไฟล์อย่างน้อย 1 อย่าง',confirmButtonColor:'#7C3AED'});
});
<?php endif; ?>

function toggleTopic(id) {
  document.getElementById('tbody_' + id).classList.toggle('open');
  document.getElementById('chev_'  + id).classList.toggle('open');
}

function openExercise(id, title, desc, maxScore, dueTs, existingText, existingFiles) {
  document.getElementById('exModalId').value = id;
  document.getElementById('exModalTitle').textContent = title;
  document.getElementById('exModalAnswer').value = existingText || '';
  // Description
  const descWrap = document.getElementById('exDescWrap');
  if (desc) { document.getElementById('exModalDesc').textContent = desc; descWrap.classList.remove('hidden'); }
  else descWrap.classList.add('hidden');
  // Max score badge
  const scoreEl = document.getElementById('exModalScore');
  if (maxScore) { scoreEl.textContent = maxScore + ' คะแนน'; scoreEl.classList.remove('hidden'); }
  else scoreEl.classList.add('hidden');
  // Due date
  const dueEl = document.getElementById('exModalDue');
  if (dueTs) {
    const overdue = Date.now() > dueTs * 1000;
    dueEl.innerHTML = '<i class="bi bi-clock mr-1"></i>หมดเขต: ' + new Date(dueTs*1000).toLocaleString('th-TH') + (overdue ? ' <span class="text-rose-600">(เลยกำหนด)</span>' : '');
    dueEl.className = 'text-[10px] font-bold mt-0.5 ' + (overdue ? 'text-rose-500' : 'text-amber-500');
    dueEl.classList.remove('hidden');
  } else dueEl.classList.add('hidden');
  // Show existing files
  document.getElementById('exFileInput').value = '';
  const preview = document.getElementById('exFilePreview');
  const files = Array.isArray(existingFiles) ? existingFiles : [];
  if (files.length > 0) {
    preview.innerHTML = files.map(fp => {
      const url = BASE_PATH + '/' + fp;
      const isImg = /\.(jpg|jpeg|png|gif|webp)$/i.test(fp);
      return isImg
        ? `<div class="bg-slate-50 rounded-xl p-2 border border-slate-100 text-center"><img src="${url}" class="max-h-32 rounded-lg mx-auto object-contain"></div>`
        : `<a href="${url}" target="_blank" class="flex items-center gap-2 px-3 py-2 bg-blue-50 rounded-xl border border-blue-100 text-xs text-blue-700 font-bold"><i class="bi bi-file-earmark-pdf text-red-500 text-xl"></i><span>ไฟล์เดิม — คลิกดู</span></a>`;
    }).join('') + `<p class="text-[10px] text-slate-400 text-center">เลือกไฟล์ใหม่เพื่อแทนที่</p>`;
  } else {
    preview.innerHTML = '';
  }
  const modal = document.getElementById('exModal');
  modal.classList.remove('hidden'); modal.classList.add('flex');
  setTimeout(() => document.getElementById('exModalAnswer').focus(), 100);
}

function previewExFiles(input) {
  const preview = document.getElementById('exFilePreview');
  const files = Array.from(input.files).slice(0, 3);
  if (!files.length) { preview.innerHTML = ''; return; }
  preview.innerHTML = '';
  files.forEach(file => {
    if (file.type.startsWith('image/')) {
      const reader = new FileReader();
      const div = document.createElement('div');
      div.className = 'bg-slate-50 rounded-xl p-2 border border-slate-100 text-center';
      preview.appendChild(div);
      reader.onload = e => {
        div.innerHTML = `<img src="${e.target.result}" class="max-h-32 rounded-lg mx-auto object-contain"><p class="text-[10px] text-emerald-600 font-bold mt-1 truncate">${file.name}</p>`;
      };
      reader.readAsDataURL(file);
    } else {
      preview.insertAdjacentHTML('beforeend', `<div class="flex items-center gap-3 px-3 py-2 bg-red-50 rounded-xl border border-red-100"><i class="bi bi-file-earmark-pdf text-red-500 text-xl flex-shrink-0"></i><p class="text-xs font-bold text-slate-700 truncate">${file.name}</p></div>`);
    }
  });
}

function validateExForm() {
  const text  = (document.getElementById('exModalAnswer').value || '').trim();
  const files = document.getElementById('exFileInput').files;
  if (!text && files.length === 0) {
    Swal.fire({icon:'warning', title:'กรุณาส่งงาน', text:'พิมพ์คำตอบหรือแนบไฟล์อย่างน้อย 1 อย่าง', confirmButtonColor:'#7C3AED'});
    return false;
  }
  if (files.length > 3) {
    Swal.fire({icon:'warning', title:'ไฟล์มากเกินไป', text:'แนบได้สูงสุด 3 ไฟล์', confirmButtonColor:'#7C3AED'});
    return false;
  }
  const btn = document.getElementById('exSubmitBtn');
  btn.disabled = true;
  btn.innerHTML = '<i class="bi bi-hourglass-split mr-1"></i>กำลังส่ง...';
  return true;
}

function closeExModal() {
  const m = document.getElementById('exModal');
  m.classList.add('hidden'); m.classList.remove('flex');
}
document.getElementById('exModal').addEventListener('click', function(e) {
  if (e.target === this) closeExModal();
});
</script>
</body>
</html>
