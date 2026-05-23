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

// Verify enrolled
$ss = $pdo->prepare("
    SELECT s.* FROM lms_subjects s
    JOIN lms_subject_classrooms sc ON sc.subject_id=s.id
    WHERE s.id=? AND sc.classroom=? LIMIT 1
");
$ss->execute([$subject_id, $class]);
$subject = $ss->fetch();
if (!$subject) { header('Location: /student/lms.php'); exit(); }

// Must have done pre-exam
$pre_done = $pdo->prepare("SELECT id FROM lms_student_pre_exam WHERE student_uid=? AND subject_id=? LIMIT 1");
$pre_done->execute([$uid, $subject_id]);
if (!$pre_done->fetch()) {
    // Auto-pass check: no questions exist
    $tpre = $pdo->prepare("SELECT COUNT(*) FROM lms_pre_questions WHERE subject_id=?");
    $tpre->execute([$subject_id]);
    if ((int)$tpre->fetchColumn() > 0) {
        header('Location: /student/lms_subject.php?subject_id=' . $subject_id); exit();
    }
}

// Handle exercise submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['exercise_id'])) {
    $ex_id  = (int)$_POST['exercise_id'];
    $unit_id_post = (int)$_POST['unit_id'];
    $answer = trim($_POST['answer_text'] ?? '');

    $file_path = null;
    if (isset($_FILES['exercise_file']) && $_FILES['exercise_file']['error'] === UPLOAD_ERR_OK) {
        $allowed_mime = ['image/jpeg','image/png','image/gif','image/webp','application/pdf'];
        $allowed_ext  = ['jpg','jpeg','png','gif','webp','pdf'];
        $finfo        = finfo_open(FILEINFO_MIME_TYPE);
        $mime         = finfo_file($finfo, $_FILES['exercise_file']['tmp_name']);
        finfo_close($finfo);
        $ext = strtolower(pathinfo($_FILES['exercise_file']['name'], PATHINFO_EXTENSION));
        if (in_array($mime, $allowed_mime) && in_array($ext, $allowed_ext) && $_FILES['exercise_file']['size'] <= 10*1024*1024) {
            $upload_dir = __DIR__ . '/../lms/uploads/exercises/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
            $filename = $uid . '_' . $ex_id . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            if (move_uploaded_file($_FILES['exercise_file']['tmp_name'], $upload_dir . $filename)) {
                $file_path = 'lms/uploads/exercises/' . $filename;
            }
        }
    }

    if ($answer !== '' || $file_path !== null) {
        $exists_q = $pdo->prepare("SELECT id, file_path FROM lms_student_exercises WHERE student_uid=? AND exercise_id=? LIMIT 1");
        $exists_q->execute([$uid, $ex_id]);
        $existing = $exists_q->fetch();
        if ($existing) {
            if ($file_path && !empty($existing['file_path'])) {
                $old = __DIR__ . '/../' . $existing['file_path'];
                if (file_exists($old)) @unlink($old);
            }
            if ($file_path) {
                $pdo->prepare("UPDATE lms_student_exercises SET answer_text=?, file_path=?, submitted_at=NOW() WHERE student_uid=? AND exercise_id=?")
                    ->execute([$answer, $file_path, $uid, $ex_id]);
            } else {
                $pdo->prepare("UPDATE lms_student_exercises SET answer_text=?, submitted_at=NOW() WHERE student_uid=? AND exercise_id=?")
                    ->execute([$answer, $uid, $ex_id]);
            }
        } else {
            $pdo->prepare("INSERT INTO lms_student_exercises (student_uid, exercise_id, unit_id, subject_id, answer_text, file_path) VALUES (?,?,?,?,?,?)")
                ->execute([$uid, $ex_id, $unit_id_post, $subject_id, $answer, $file_path]);
        }
        header('Location: /student/lms_assignments.php?subject_id=' . $subject_id . '&done=1'); exit();
    }
    header('Location: /student/lms_assignments.php?subject_id=' . $subject_id . '&err=1'); exit();
}

// Load all units ordered
$units = $pdo->prepare("SELECT id, unit_name, order_no FROM lms_units WHERE subject_id=? ORDER BY order_no");
$units->execute([$subject_id]);
$units = $units->fetchAll();

// Load all student submissions for this subject (one query)
$subs_stmt = $pdo->prepare("
    SELECT exercise_id, answer_text, file_path, grade, feedback, reviewed_at, submitted_at
    FROM lms_student_exercises
    WHERE student_uid=? AND subject_id=?
");
$subs_stmt->execute([$uid, $subject_id]);
$submissions = [];
foreach ($subs_stmt->fetchAll() as $row) {
    $submissions[$row['exercise_id']] = $row;
}

// Build grouped assignment list
$now_ts    = time();
$groups    = [];
$ex_seq    = 0; // global exercise counter
$cnt_all   = 0;
$cnt_pending  = 0;
$cnt_submitted = 0;
$cnt_reviewed  = 0;

foreach ($units as $u) {
    $exs = $pdo->prepare("SELECT id, exercise_title, description, max_score, due_date, allow_resubmit FROM lms_unit_exercises WHERE unit_id=? ORDER BY id");
    $exs->execute([$u['id']]);
    $exercises = $exs->fetchAll();
    if (empty($exercises)) continue;

    $group_items = [];
    foreach ($exercises as $ex) {
        $ex_seq++;
        $sub      = $submissions[$ex['id']] ?? null;
        $reviewed = $sub && $sub['reviewed_at'] !== null;
        $done     = $sub !== null;
        $due_ts   = $ex['due_date'] ? strtotime($ex['due_date']) : null;
        $overdue  = $due_ts && $now_ts > $due_ts;
        $due_soon = $due_ts && !$overdue && ($due_ts - $now_ts) < 86400;

        if ($reviewed)  { $status = 'reviewed';  $cnt_reviewed++;  }
        elseif ($done)  { $status = 'submitted'; $cnt_submitted++; }
        else            { $status = 'pending';   $cnt_pending++;   }
        $cnt_all++;

        $group_items[] = compact('ex','sub','reviewed','done','due_ts','overdue','due_soon','status','ex_seq');
    }
    $groups[] = ['unit' => $u, 'items' => $group_items];
}
?><!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<title>งานทั้งหมด · <?=htmlspecialchars($subject['subject_name'],ENT_QUOTES,'UTF-8')?></title>
<meta name="theme-color" content="#7C3AED">
<link href="https://fonts.googleapis.com/css2?family=Prompt:wght@400;600;700;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<script src="https://cdn.tailwindcss.com"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
body { font-family: 'Prompt', sans-serif; }
.assignment-card[data-status="pending"]  { border-color: #fde68a; }
.assignment-card[data-status="submitted"]{ border-color: #a7f3d0; }
.assignment-card[data-status="reviewed"] { border-color: #ddd6fe; }
</style>
</head>
<body class="min-h-screen bg-slate-50 pb-10" style="padding-top:env(safe-area-inset-top)">

<!-- Header -->
<div class="text-white px-5 pt-5 pb-5 shadow-xl" style="background:linear-gradient(135deg,#7C3AED,#4F46E5)">
  <div class="flex items-center gap-3 mb-4">
    <a href="/student/lms_subject.php?subject_id=<?=$subject_id?>"
       class="w-9 h-9 bg-white/15 rounded-xl flex items-center justify-center border border-white/20 active:bg-white/25 flex-shrink-0">
      <i class="bi bi-arrow-left"></i>
    </a>
    <div class="flex-1 min-w-0">
      <p class="text-violet-200 text-xs font-bold truncate"><?=htmlspecialchars($subject['subject_name'],ENT_QUOTES,'UTF-8')?></p>
      <h1 class="font-black text-lg leading-tight">งานทั้งหมด</h1>
    </div>
    <div class="text-right flex-shrink-0">
      <p class="text-2xl font-black"><?=($cnt_submitted+$cnt_reviewed)?>/<?=$cnt_all?></p>
      <p class="text-violet-200 text-[10px] font-bold">ส่งแล้ว</p>
    </div>
  </div>
  <!-- Progress bar -->
  <?php if ($cnt_all > 0): ?>
  <div class="w-full bg-white/20 rounded-full h-2">
    <div class="h-2 rounded-full bg-white transition-all"
         style="width:<?=round(($cnt_submitted+$cnt_reviewed)/$cnt_all*100)?>%"></div>
  </div>
  <?php endif; ?>
</div>

<!-- Sticky filter bar -->
<div class="sticky top-0 z-30 bg-white/95 backdrop-blur border-b border-slate-100 shadow-sm">
  <div class="flex gap-1.5 px-4 py-2.5 overflow-x-auto no-scrollbar">
    <button onclick="setFilter('all')" id="f_all"
      class="filter-btn active flex-shrink-0 px-3.5 py-1.5 rounded-full text-xs font-black transition-all bg-violet-600 text-white shadow-md shadow-violet-200">
      ทั้งหมด <span class="ml-1 opacity-80"><?=$cnt_all?></span>
    </button>
    <button onclick="setFilter('pending')" id="f_pending"
      class="filter-btn flex-shrink-0 px-3.5 py-1.5 rounded-full text-xs font-black transition-all bg-slate-100 text-slate-500">
      รอส่ง <span class="ml-1"><?=$cnt_pending?></span>
    </button>
    <button onclick="setFilter('submitted')" id="f_submitted"
      class="filter-btn flex-shrink-0 px-3.5 py-1.5 rounded-full text-xs font-black transition-all bg-slate-100 text-slate-500">
      ส่งแล้ว <span class="ml-1"><?=$cnt_submitted?></span>
    </button>
    <button onclick="setFilter('reviewed')" id="f_reviewed"
      class="filter-btn flex-shrink-0 px-3.5 py-1.5 rounded-full text-xs font-black transition-all bg-slate-100 text-slate-500">
      ตรวจแล้ว <span class="ml-1"><?=$cnt_reviewed?></span>
    </button>
  </div>
</div>

<!-- Content -->
<div class="max-w-lg mx-auto px-4 pt-4 space-y-5">

<?php if ($cnt_all === 0): ?>
<div class="bg-white rounded-2xl border border-slate-100 p-12 text-center text-slate-300 shadow-sm">
  <i class="bi bi-inbox text-5xl mb-3 block opacity-30"></i>
  <p class="font-bold">ยังไม่มีงานที่สั่ง</p>
</div>
<?php endif; ?>

<?php foreach ($groups as $g):
  $g_done = array_sum(array_map(fn($i) => ($i['done'] ? 1 : 0), $g['items']));
  $g_total = count($g['items']);
?>
<div class="group-section" data-unit="<?=$g['unit']['id']?>">
  <!-- Unit header -->
  <div class="flex items-center gap-3 mb-2 px-1">
    <div class="w-7 h-7 rounded-lg bg-violet-100 text-violet-600 text-xs font-black flex items-center justify-center flex-shrink-0">
      <?=$g['unit']['order_no']?>
    </div>
    <p class="font-black text-slate-700 text-sm flex-1 truncate"><?=htmlspecialchars($g['unit']['unit_name'],ENT_QUOTES,'UTF-8')?></p>
    <span class="text-xs font-bold <?=$g_done>=$g_total?'text-emerald-600':'text-slate-400'?> flex-shrink-0">
      <?=$g_done?>/<?=$g_total?>
    </span>
  </div>

  <!-- Exercise cards -->
  <div class="space-y-3">
  <?php foreach ($g['items'] as $item):
    ['ex'=>$ex, 'sub'=>$sub, 'reviewed'=>$reviewed, 'done'=>$done,
     'due_ts'=>$due_ts, 'overdue'=>$overdue, 'due_soon'=>$due_soon,
     'status'=>$status, 'ex_seq'=>$seq] = $item;
    $can_edit = $done && (bool)$ex['allow_resubmit'];
  ?>
  <div class="assignment-card bg-white rounded-2xl border-2 p-4 shadow-sm"
       data-status="<?=$status?>">

    <!-- Top row: number + title + badge -->
    <div class="flex items-start gap-3 mb-2">
      <span class="w-7 h-7 rounded-xl flex-shrink-0 flex items-center justify-center text-xs font-black
        <?=$reviewed?'bg-violet-100 text-violet-600':($done?'bg-emerald-100 text-emerald-600':'bg-amber-100 text-amber-600')?>">
        <?=$seq?>
      </span>
      <p class="flex-1 font-bold text-slate-800 text-sm leading-snug"><?=htmlspecialchars($ex['exercise_title'],ENT_QUOTES,'UTF-8')?></p>
      <?php if ($reviewed): ?>
      <span class="px-2 py-0.5 bg-violet-100 text-violet-700 text-[10px] font-black rounded-full flex-shrink-0 whitespace-nowrap">
        <i class="bi bi-patch-check-fill mr-0.5"></i>ตรวจแล้ว
      </span>
      <?php elseif ($done): ?>
      <span class="px-2 py-0.5 bg-emerald-100 text-emerald-700 text-[10px] font-black rounded-full flex-shrink-0 whitespace-nowrap">
        <i class="bi bi-check-circle-fill mr-0.5"></i>ส่งแล้ว
      </span>
      <?php else: ?>
      <span class="px-2 py-0.5 bg-amber-100 text-amber-700 text-[10px] font-black rounded-full flex-shrink-0">รอส่ง</span>
      <?php endif; ?>
    </div>

    <!-- Info badges -->
    <?php if ($ex['max_score'] || $due_ts): ?>
    <div class="flex flex-wrap gap-1.5 mb-2 ml-10">
      <?php if ($ex['max_score']): ?>
      <span class="px-2 py-0.5 bg-amber-50 text-amber-600 text-[10px] font-black rounded-full border border-amber-100">
        <i class="bi bi-star-fill mr-0.5"></i><?=$ex['max_score']?> คะแนน
      </span>
      <?php endif; ?>
      <?php if ($due_ts): ?>
      <span class="px-2 py-0.5 text-[10px] font-black rounded-full border
        <?=$overdue?'bg-rose-50 text-rose-600 border-rose-100':($due_soon?'bg-amber-50 text-amber-600 border-amber-100':'bg-slate-50 text-slate-500 border-slate-100')?>">
        <i class="bi bi-clock mr-0.5"></i>
        <?php if ($overdue): ?>เลยกำหนดแล้ว
        <?php elseif ($due_soon): ?>ใกล้หมด · <?=date('d/m H:i',$due_ts)?>
        <?php else: ?>หมดเขต <?=date('d/m/Y H:i',$due_ts)?>
        <?php endif; ?>
      </span>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Description -->
    <?php if (!empty($ex['description'])): ?>
    <div class="ml-10 mb-2 bg-blue-50 border border-blue-100 rounded-xl px-3 py-2">
      <p class="text-xs text-blue-700 leading-relaxed"><?=nl2br(htmlspecialchars($ex['description'],ENT_QUOTES,'UTF-8'))?></p>
    </div>
    <?php endif; ?>

    <!-- Submitted content -->
    <?php if ($done): ?>
    <div class="ml-10 mb-2 bg-slate-50 rounded-xl p-3 border border-slate-100">
      <?php if (!empty($sub['answer_text'])): ?>
      <p class="text-xs text-slate-600 leading-relaxed line-clamp-3"><?=nl2br(htmlspecialchars($sub['answer_text'],ENT_QUOTES,'UTF-8'))?></p>
      <?php endif; ?>
      <?php if (!empty($sub['file_path'])): ?>
      <?php $fext = strtolower(pathinfo($sub['file_path'], PATHINFO_EXTENSION)); ?>
      <?php if (in_array($fext, ['jpg','jpeg','png','gif','webp'])): ?>
      <img src="<?=$base_path . '/' . htmlspecialchars($sub['file_path'],ENT_QUOTES,'UTF-8')?>"
           class="<?=!empty($sub['answer_text'])?'mt-2':''?> w-full max-h-40 object-contain rounded-lg bg-white border border-slate-100" alt="">
      <?php else: ?>
      <a href="<?=$base_path . '/' . htmlspecialchars($sub['file_path'],ENT_QUOTES,'UTF-8')?>" target="_blank" rel="noopener"
         class="flex items-center gap-2 <?=!empty($sub['answer_text'])?'mt-1.5':''?> text-xs text-blue-600 font-bold">
        <i class="bi bi-file-earmark-pdf text-red-500"></i><span class="underline">ดูไฟล์ที่แนบ</span>
      </a>
      <?php endif; ?>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Teacher review result -->
    <?php if ($reviewed): ?>
    <div class="ml-10 mb-2 bg-violet-50 border border-violet-100 rounded-xl px-3 py-2">
      <div class="flex items-center justify-between">
        <p class="text-xs font-black text-violet-700"><i class="bi bi-person-check-fill mr-1"></i>ครูตรวจแล้ว</p>
        <?php if ($sub['grade'] !== null): ?>
        <span class="px-2.5 py-0.5 bg-violet-600 text-white text-xs font-black rounded-full">
          <?=$sub['grade']?><?=$ex['max_score'] ? ' / ' . $ex['max_score'] . ' คะแนน' : ' คะแนน'?>
        </span>
        <?php endif; ?>
      </div>
      <?php if (!empty($sub['feedback'])): ?>
      <p class="text-xs text-violet-600 leading-relaxed mt-1"><?=nl2br(htmlspecialchars($sub['feedback'],ENT_QUOTES,'UTF-8'))?></p>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Action button -->
    <?php if (!$done): ?>
    <button onclick="openExModal(<?=$ex['id']?>,<?=$g['unit']['id']?>,<?=json_encode($ex['exercise_title'])?>,<?=json_encode($ex['description']??'')?>,<?=(int)($ex['max_score']??0)?>,<?=$due_ts??0?>,'','')"
      class="ml-10 w-[calc(100%-2.5rem)] py-2 bg-violet-600 text-white font-bold text-xs rounded-xl shadow-lg shadow-violet-200/50 active:scale-95 transition-transform">
      <i class="bi bi-send-fill mr-1"></i> ส่งงาน
    </button>
    <?php elseif ($can_edit): ?>
    <button onclick="openExModal(<?=$ex['id']?>,<?=$g['unit']['id']?>,<?=json_encode($ex['exercise_title'])?>,<?=json_encode($ex['description']??'')?>,<?=(int)($ex['max_score']??0)?>,<?=$due_ts??0?>,<?=json_encode($sub['answer_text']??'')?>,<?=json_encode($sub['file_path']??'')?>)"
      class="ml-10 w-[calc(100%-2.5rem)] py-1.5 border border-slate-200 text-slate-500 font-bold text-xs rounded-xl active:opacity-70">
      <i class="bi bi-pencil-fill mr-1"></i> แก้ไขงาน
    </button>
    <?php endif; ?>
  </div>
  <?php endforeach; ?>
  </div>
</div>
<?php endforeach; ?>

</div>

<!-- Exercise Submit Modal -->
<div id="exModal" class="fixed inset-0 z-50 hidden items-center justify-center p-4" style="background:rgba(0,0,0,0.55)">
  <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg max-h-[92vh] flex flex-col">
    <div class="flex items-start justify-between px-5 py-4 border-b border-slate-100 flex-shrink-0">
      <div class="flex-1 min-w-0 pr-3">
        <div class="flex items-center gap-2 flex-wrap">
          <p class="font-black text-slate-800 text-sm" id="exModalTitle">งาน</p>
          <span id="exModalScore" class="hidden px-2 py-0.5 bg-amber-100 text-amber-700 text-[10px] font-black rounded-full"></span>
        </div>
        <p id="exModalDue" class="hidden text-[10px] font-bold mt-0.5"></p>
      </div>
      <button onclick="closeExModal()" class="text-slate-400 active:text-slate-600 flex-shrink-0">
        <i class="bi bi-x-lg text-lg"></i>
      </button>
    </div>
    <form id="exForm" method="POST" enctype="multipart/form-data"
          class="p-5 space-y-3 overflow-y-auto flex-1" onsubmit="return validateExForm()">
      <input type="hidden" name="exercise_id" id="exModalId">
      <input type="hidden" name="unit_id"     id="exModalUnitId">
      <!-- Description -->
      <div id="exDescWrap" class="hidden bg-blue-50 border border-blue-100 rounded-xl px-3 py-2.5">
        <p class="text-xs text-blue-700 leading-relaxed" id="exModalDesc"></p>
      </div>
      <!-- Text answer -->
      <div>
        <label class="block text-xs font-black text-slate-500 mb-1.5">
          คำตอบ <span class="text-slate-400 font-normal">(ไม่บังคับถ้ามีไฟล์)</span>
        </label>
        <textarea name="answer_text" id="exModalAnswer" rows="4"
          class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-violet-400 resize-none"
          placeholder="พิมพ์คำตอบที่นี่..."></textarea>
      </div>
      <!-- File upload -->
      <div>
        <label class="block text-xs font-black text-slate-500 mb-1.5">
          <i class="bi bi-camera mr-1"></i>ถ่ายรูปงาน / แนบไฟล์
          <span class="text-slate-400 font-normal">(รูปหรือ PDF สูงสุด 10MB)</span>
        </label>
        <input type="file" name="exercise_file" id="exFileInput" accept="image/*,.pdf"
          class="hidden" onchange="previewExFile(this)">
        <label for="exFileInput"
          class="flex items-center justify-center gap-2 w-full py-3 border-2 border-dashed border-slate-200 rounded-xl text-slate-400 cursor-pointer hover:border-violet-400 hover:text-violet-500 transition-all font-bold text-sm active:opacity-70">
          <i class="bi bi-camera text-xl"></i> แตะเพื่อถ่ายรูป / เลือกไฟล์
        </label>
        <div id="exFilePreview" class="mt-2"></div>
      </div>
      <!-- Buttons -->
      <div class="flex gap-3 pt-1">
        <button type="button" onclick="closeExModal()"
          class="flex-1 py-2.5 border border-slate-200 text-slate-600 font-bold text-sm rounded-xl active:opacity-70">ยกเลิก</button>
        <button type="submit" id="exSubmitBtn"
          class="flex-1 py-2.5 bg-violet-600 text-white font-bold text-sm rounded-xl shadow-lg shadow-violet-200/50 active:scale-95 transition-transform">
          <i class="bi bi-send-fill mr-1"></i> ส่งงาน
        </button>
      </div>
    </form>
  </div>
</div>

<script>
const BASE_PATH  = '<?= $base_path ?>';
let   curFilter  = 'all';

<?php if (isset($_GET['done'])): ?>
window.addEventListener('load',()=>{
  Swal.fire({icon:'success',title:'ส่งงานแล้ว',confirmButtonColor:'#7C3AED',timer:2000,showConfirmButton:false});
});
<?php elseif (isset($_GET['err'])): ?>
window.addEventListener('load',()=>{
  Swal.fire({icon:'warning',title:'กรุณาส่งงาน',text:'พิมพ์คำตอบหรือแนบไฟล์อย่างน้อย 1 อย่าง',confirmButtonColor:'#7C3AED'});
});
<?php endif; ?>

// ── Filter logic ───────────────────────────────────
function setFilter(f) {
  curFilter = f;
  // Update button styles
  ['all','pending','submitted','reviewed'].forEach(k => {
    const btn = document.getElementById('f_' + k);
    if (k === f) {
      btn.className = btn.className.replace('bg-slate-100 text-slate-500','bg-violet-600 text-white shadow-md shadow-violet-200');
    } else {
      btn.className = btn.className.replace('bg-violet-600 text-white shadow-md shadow-violet-200','bg-slate-100 text-slate-500');
    }
  });
  // Show/hide cards
  document.querySelectorAll('.assignment-card').forEach(card => {
    const show = f === 'all' || card.dataset.status === f;
    card.style.display = show ? '' : 'none';
  });
  // Show/hide unit sections that have no visible cards
  document.querySelectorAll('.group-section').forEach(section => {
    const visible = [...section.querySelectorAll('.assignment-card')].some(c => c.style.display !== 'none');
    section.style.display = visible ? '' : 'none';
  });
}

// ── Modal logic ────────────────────────────────────
function openExModal(exId, unitId, title, desc, maxScore, dueTs, existingText, existingFile) {
  document.getElementById('exModalId').value     = exId;
  document.getElementById('exModalUnitId').value = unitId;
  document.getElementById('exModalTitle').textContent = title;
  document.getElementById('exModalAnswer').value = existingText || '';
  // Description
  const dw = document.getElementById('exDescWrap');
  if (desc) { document.getElementById('exModalDesc').textContent = desc; dw.classList.remove('hidden'); }
  else dw.classList.add('hidden');
  // Score badge
  const se = document.getElementById('exModalScore');
  if (maxScore) { se.textContent = maxScore + ' คะแนน'; se.classList.remove('hidden'); }
  else se.classList.add('hidden');
  // Due date
  const de = document.getElementById('exModalDue');
  if (dueTs) {
    const overdue = Date.now() > dueTs * 1000;
    de.innerHTML = '<i class="bi bi-clock mr-1"></i>หมดเขต: ' + new Date(dueTs*1000).toLocaleString('th-TH') + (overdue ? ' <span class="text-rose-600">(เลยกำหนด)</span>' : '');
    de.className = 'text-[10px] font-bold mt-0.5 ' + (overdue ? 'text-rose-500' : 'text-amber-500');
    de.classList.remove('hidden');
  } else de.classList.add('hidden');
  // File preview
  document.getElementById('exFileInput').value = '';
  const preview = document.getElementById('exFilePreview');
  if (existingFile) {
    const url   = BASE_PATH + '/' + existingFile;
    const isImg = /\.(jpg|jpeg|png|gif|webp)$/i.test(existingFile);
    preview.innerHTML = isImg
      ? `<div class="bg-slate-50 rounded-xl p-2 border text-center"><img src="${url}" class="max-h-40 rounded-lg mx-auto object-contain"><p class="text-[10px] text-slate-400 mt-1">ไฟล์เดิม — เลือกใหม่เพื่อเปลี่ยน</p></div>`
      : `<a href="${url}" target="_blank" class="flex items-center gap-2 px-3 py-2.5 bg-blue-50 rounded-xl border border-blue-100 text-xs text-blue-700 font-bold"><i class="bi bi-file-earmark-pdf text-red-500 text-xl"></i><span>ไฟล์เดิม — คลิกดู</span></a>`;
  } else {
    preview.innerHTML = '';
  }
  const modal = document.getElementById('exModal');
  modal.classList.remove('hidden'); modal.classList.add('flex');
  setTimeout(() => document.getElementById('exModalAnswer').focus(), 100);
}

function previewExFile(input) {
  const preview = document.getElementById('exFilePreview');
  const file = input.files[0];
  if (!file) { preview.innerHTML = ''; return; }
  if (file.type.startsWith('image/')) {
    const reader = new FileReader();
    reader.onload = e => {
      preview.innerHTML = `<div class="bg-slate-50 rounded-xl p-2 border text-center"><img src="${e.target.result}" class="max-h-48 rounded-lg mx-auto object-contain"><p class="text-xs text-emerald-600 font-bold mt-1">${file.name}</p></div>`;
    };
    reader.readAsDataURL(file);
  } else {
    preview.innerHTML = `<div class="flex items-center gap-3 px-3 py-2.5 bg-red-50 rounded-xl border border-red-100"><i class="bi bi-file-earmark-pdf text-red-500 text-2xl flex-shrink-0"></i><div><p class="text-xs font-bold text-slate-700">${file.name}</p><p class="text-[10px] text-slate-400">${(file.size/1024).toFixed(0)} KB</p></div></div>`;
  }
}

function validateExForm() {
  const text = (document.getElementById('exModalAnswer').value || '').trim();
  const file = document.getElementById('exFileInput').files[0];
  if (!text && !file) {
    Swal.fire({icon:'warning',title:'กรุณาส่งงาน',text:'พิมพ์คำตอบหรือแนบไฟล์อย่างน้อย 1 อย่าง',confirmButtonColor:'#7C3AED'});
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
