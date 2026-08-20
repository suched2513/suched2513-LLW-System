<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/../lms/_helpers.php';

$pdo  = getPdo();
$uid  = (int)$_SESSION['student_uid'];
$name = $_SESSION['student_name'];
$class= $_SESSION['student_class'];

$unit_id = (int)($_GET['unit_id'] ?? $_POST['unit_id'] ?? 0);
if (!$unit_id) { header('Location: /student/lms.php'); exit(); }

$us = $pdo->prepare("
    SELECT u.*, s.subject_name FROM lms_units u
    JOIN lms_subjects s ON s.id = u.subject_id
    JOIN lms_subject_classrooms sc ON sc.subject_id = s.id
    WHERE u.id=? AND sc.classroom=? AND u.status='published' AND u.deleted_at IS NULL
    LIMIT 1
");
$us->execute([$unit_id, $class]); $unit = $us->fetch();
if (!$unit) { header('Location: /student/lms.php'); exit(); }
$subject_id = (int)$unit['subject_id'];
$back_url   = '/student/lms_subject.php?subject_id='.$subject_id;

$es = $pdo->prepare("SELECT * FROM lms_exam_settings WHERE unit_id=?"); $es->execute([$unit_id]); $settings = $es->fetch();
$post_pass   = $settings['post_pass_score'] ?? 6;
$max_att     = $settings['post_max_attempts'] ?? 3;
$show_answer = ($settings['post_show_answer'] ?? 1) ? true : false;

// Check post-exam time window
$now_ts    = time();
$open_ts   = !empty($settings['post_exam_open_at'])  ? strtotime($settings['post_exam_open_at'])  : null;
$close_ts  = !empty($settings['post_exam_close_at']) ? strtotime($settings['post_exam_close_at']) : null;
$window_set = ($open_ts || $close_ts);
$in_window  = (!$open_ts || $now_ts >= $open_ts) && (!$close_ts || $now_ts <= $close_ts);

// Already passed?
$already_passed = $pdo->prepare("SELECT id FROM lms_student_post_exam WHERE student_uid=? AND unit_id=? AND passed=1 LIMIT 1");
$already_passed->execute([$uid,$unit_id]);
if ($already_passed->fetch()) { header('Location: '.$back_url); exit(); }

// Must have done this unit's pre-exam (passed=1 since pre always passes now)
$pre_done = $pdo->prepare("SELECT id FROM lms_student_pre_exam WHERE student_uid=? AND unit_id=? AND passed=1 LIMIT 1");
$pre_done->execute([$uid,$unit_id]);
if (!$pre_done->fetch()) { header('Location: '.$back_url); exit(); }

// Block if outside time window (skip block if student already passed — let redirect above handle it)
if ($window_set && !$in_window) {
    header('Location: '.$back_url.'&exam_closed=1'); exit();
}

// Attempt count
$stmt = $pdo->prepare("SELECT COUNT(*) FROM lms_student_post_exam WHERE student_uid=? AND unit_id=?");
$stmt->execute([$uid,$unit_id]);
$attempt_count = (int)$stmt->fetchColumn();

// Max attempts reached — reset this unit only
if ($attempt_count >= $max_att) {
    try {
        $pdo->beginTransaction();
        $pdo->prepare("DELETE FROM lms_student_pre_exam  WHERE student_uid=? AND unit_id=?")->execute([$uid,$unit_id]);
        $pdo->prepare("DELETE FROM lms_student_post_exam WHERE student_uid=? AND unit_id=?")->execute([$uid,$unit_id]);
        $pdo->prepare("DELETE FROM lms_student_exercises WHERE student_uid=? AND unit_id=?")->execute([$uid,$unit_id]);
        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack(); error_log($e->getMessage());
    }
    header('Location: '.$back_url.'&reset=1'); exit();
}

$attempt_no = $attempt_count + 1;

function lms_save_upload_answer(int $qid, int $uid): ?string {
    $key = 'qf_' . $qid;
    if (empty($_FILES[$key]['name']) || $_FILES[$key]['error'] !== UPLOAD_ERR_OK) return null;
    $allowed_mime = ['image/jpeg','image/png','image/gif','image/webp','application/pdf'];
    $allowed_ext  = ['jpg','jpeg','png','gif','webp','pdf'];
    $ext = strtolower(pathinfo($_FILES[$key]['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed_ext, true)) return null;
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime  = finfo_file($finfo, $_FILES[$key]['tmp_name']);
    finfo_close($finfo);
    if (!in_array($mime, $allowed_mime, true)) return null;
    if ($_FILES[$key]['size'] > 10 * 1024 * 1024) return null;
    $dir = __DIR__ . '/../lms/uploads/exam_answers/';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
        file_put_contents($dir . '.htaccess', "Options -Indexes -ExecCGI\n<FilesMatch \"\\.(php|phtml|phar|php3|php4|php5|php7)$\">\n    Require all denied\n</FilesMatch>\n");
    }
    $fname = 'ans_' . $uid . '_' . $qid . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    if (!move_uploaded_file($_FILES[$key]['tmp_name'], $dir . $fname)) return null;
    return 'lms/uploads/exam_answers/' . $fname;
}

// Handle submit
$result = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $qs = $pdo->prepare("SELECT * FROM lms_post_questions WHERE unit_id=? ORDER BY id");
    $qs->execute([$unit_id]); $questions_post = $qs->fetchAll();
    $score = 0; $total_auto = 0; $answers = [];
    foreach ($questions_post as $q) {
        $qtype = $q['question_type'] ?? 'choice';
        $g = lms_grade_exam_answer($q);
        if ($qtype === 'upload') {
            $g['file_path'] = lms_save_upload_answer((int)$q['id'], $uid);
        }
        if ($g['auto']) {
            $total_auto++;
            if ($g['correct']) $score++;
        }
        $answers[] = ['id' => $q['id']] + $g;
    }
    $passed = ($total_auto === 0 || $score >= $post_pass) ? 1 : 0;
    $tab_switch_count = max(0, (int)($_POST['tab_switch_count'] ?? 0));
    $pdo->prepare("INSERT INTO lms_student_post_exam (student_uid,subject_id,unit_id,score,total,passed,attempt_no,tab_switch_count) VALUES (?,?,?,?,?,?,?,?)")
        ->execute([$uid,$subject_id,$unit_id,$score,$total_auto,$passed,$attempt_no,$tab_switch_count]);
    $exam_record_id = (int)$pdo->lastInsertId();
    // Save manually-graded answers (text / upload) for teacher review
    // Save item-level results for auto-graded types (feeds item-analysis reports)
    $itemStmt = $pdo->prepare("INSERT INTO lms_exam_item_results (student_uid,subject_id,exam_type,exam_record_id,question_id,is_correct) VALUES (?,?,'post',?,?,?)");
    foreach ($answers as $a) {
        if (!$a['auto'] && (!empty($a['text']) || !empty($a['file_path']))) {
            $pdo->prepare("INSERT INTO lms_student_exam_answers (student_uid,subject_id,exam_type,exam_record_id,question_id,answer_text,file_path) VALUES (?,?,'post',?,?,?,?)")
                ->execute([$uid,$subject_id,$exam_record_id,$a['id'],$a['text'] ?? '',$a['file_path'] ?? null]);
        }
        if ($a['auto']) {
            $itemStmt->execute([$uid,$subject_id,$exam_record_id,$a['id'],$a['correct'] ? 1 : 0]);
        }
    }
    $result = ['score'=>$score,'total'=>$total_auto,'passed'=>$passed,'answers'=>$answers,'questions'=>$questions_post,
               'attempt_no'=>$attempt_no,'max_att'=>$max_att];
}

$qs = $pdo->prepare("SELECT * FROM lms_post_questions WHERE unit_id=? ORDER BY id");
$qs->execute([$unit_id]); $questions = $qs->fetchAll();
$total_q   = count($questions);
$remaining = $max_att - $attempt_count;

// Pre-generate shuffled choice order per question
$shuffled_orders = [];
foreach ($questions as $q) {
    $order = !empty($q['choice5']) ? [1,2,3,4,5] : [1,2,3,4];
    shuffle($order);
    $shuffled_orders[$q['id']] = $order;
}

// Remedial exercises (of this unit) to suggest when the student has exhausted their attempts
$remedial_exs = [];
if ($result && !$result['passed'] && $result['attempt_no'] >= $result['max_att']) {
    $re = $pdo->prepare("
        SELECT exercise_title FROM lms_unit_exercises
        WHERE unit_id=? AND is_remedial=1 AND status='published' AND deleted_at IS NULL
        ORDER BY id
    ");
    $re->execute([$unit_id]);
    $remedial_exs = $re->fetchAll();
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
      <p class="text-rose-200 text-xs font-bold"><?=htmlspecialchars($unit['unit_name'],ENT_QUOTES,'UTF-8')?> · ครั้งที่ <?=$attempt_no?> / <?=$max_att?></p>
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
<div class="px-4 py-5 max-w-2xl mx-auto space-y-4">
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
    <p class="text-xs text-slate-400 mt-3 bg-white/70 rounded-xl p-2">เมื่อกลับหน้าบทเรียน ระบบจะรีเซ็ตประวัติของหน่วยนี้เพื่อเริ่มใหม่</p>
    <?php endif; ?>
  </div>

  <?php if ($exhausted && !empty($remedial_exs)): ?>
  <div class="rounded-2xl border-2 border-orange-200 bg-orange-50 p-5">
    <p class="font-black text-orange-700 text-sm mb-1"><i class="bi bi-life-preserver mr-1"></i>แนะนำให้ทบทวนแบบฝึกซ่อมเสริมก่อนสอบใหม่</p>
    <p class="text-xs text-orange-600 mb-3">ครูจัดแบบฝึกเหล่านี้ไว้ช่วยทบทวนก่อนลองสอบหลังเรียนอีกครั้ง</p>
    <div class="space-y-1.5">
      <?php foreach ($remedial_exs as $rex): ?>
      <div class="flex items-center gap-2 bg-white border border-orange-100 rounded-xl px-3 py-2">
        <i class="bi bi-life-preserver text-orange-400 flex-shrink-0"></i>
        <p class="text-xs font-bold text-slate-700 truncate"><?=htmlspecialchars($rex['exercise_title'],ENT_QUOTES,'UTF-8')?></p>
      </div>
      <?php endforeach; ?>
    </div>
    <p class="text-[10px] text-orange-500 mt-3">แบบฝึกซ่อมเสริมเหล่านี้จะยังอยู่ในหน้าบทเรียนหลังรีเซ็ต — ไปทำได้จากแท็บ "งาน"</p>
  </div>
  <?php endif; ?>

  <?php if ($show_answer): ?>
  <p class="text-xs font-black text-slate-400 uppercase tracking-wider px-1">เฉลย</p>
  <?php
  $ans_map = [];
  foreach ($result['answers'] as $a) $ans_map[$a['id']] = $a;
  $qtypes = lms_question_types();
  foreach ($result['questions'] as $i => $q):
    $ans = $ans_map[$q['id']] ?? null;
    $qtype = $q['question_type'] ?? 'choice';
    $is_manual = !($qtypes[$qtype]['auto'] ?? true);
    $is_correct = !$is_manual && $ans && $ans['correct'];
  ?>
  <div class="rounded-2xl border bg-white p-4 shadow-sm <?=$is_manual?'border-violet-200':($is_correct?'border-emerald-200':'border-rose-200')?>">
    <p class="text-sm font-bold text-slate-700 mb-3"><?=$i+1?>. <?=htmlspecialchars($q['question_text'],ENT_QUOTES,'UTF-8')?>
      <?php if ($is_manual): ?>
      <span class="ml-1 px-1.5 py-0.5 bg-violet-100 text-violet-600 text-[10px] font-black rounded-full"><?=htmlspecialchars($qtypes[$qtype]['label'] ?? '',ENT_QUOTES,'UTF-8')?></span>
      <?php endif; ?>
    </p>
    <?=lms_render_exam_result_review($q, $ans)?>
  </div>
  <?php endforeach; ?>
  <?php else: ?>
  <div class="rounded-2xl border border-slate-200 bg-slate-50 p-5 text-center">
    <i class="bi bi-eye-slash text-slate-300 text-3xl block mb-2"></i>
    <p class="text-xs text-slate-400 font-bold">ครูปิดการแสดงเฉลยสำหรับข้อสอบชุดนี้</p>
  </div>
  <?php endif; ?>
  <a href="<?=$back_url?>"
     class="flex items-center justify-center gap-2 py-3 <?=$result['passed']?'bg-violet-600 shadow-violet-200/50':'bg-slate-600'?> text-white font-bold text-sm rounded-xl shadow-lg">
    <i class="bi bi-arrow-left"></i> กลับหน้าบทเรียน
  </a>
</div>
<?php else: ?>
<form method="POST" id="examForm" class="px-4 py-5 max-w-2xl mx-auto space-y-4 pb-24" enctype="multipart/form-data">
  <input type="hidden" name="unit_id" value="<?=$unit_id?>">
  <input type="hidden" name="tab_switch_count" id="tab_switch_count" value="0">
  <?php $qtypes = lms_question_types(); foreach ($questions as $i => $q): $qtype = $q['question_type'] ?? 'choice'; ?>
  <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
    <p class="text-sm font-bold text-slate-800 mb-1 leading-snug">
      <span class="text-rose-600 font-black mr-1"><?=$i+1?>.</span>
      <?=htmlspecialchars($q['question_text'],ENT_QUOTES,'UTF-8')?>
      <?php if (!($qtypes[$qtype]['auto'] ?? true)): ?>
      <span class="ml-1 px-1.5 py-0.5 bg-violet-100 text-violet-600 text-[10px] font-black rounded-full"><?=htmlspecialchars($qtypes[$qtype]['label'] ?? '',ENT_QUOTES,'UTF-8')?></span>
      <?php endif; ?>
    </p>
    <?php if (!empty($q['question_img'])): ?>
    <img src="/uploads/lms/questions/<?=htmlspecialchars($q['question_img'],ENT_QUOTES,'UTF-8')?>" class="rounded-xl w-full max-h-48 object-contain my-3 bg-slate-50">
    <?php endif; ?>
    <?=lms_render_exam_question_input($q, $shuffled_orders[$q['id']] ?? [1,2,3,4])?>
  </div>
  <?php endforeach; ?>
</form>
<div class="fixed bottom-0 left-0 right-0 bg-white/90 backdrop-blur border-t border-slate-200 px-4 py-4">
  <div class="max-w-2xl mx-auto flex items-center gap-4">
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
<?=lms_exam_js_helpers()?>
<?php if ($result && $result['passed']): ?>
window.addEventListener('load', () => {
  Swal.fire({icon:'success',title:'ผ่านแล้ว!',text:'<?php if($result["total"]>0): ?>คะแนน <?=$result['score']?>/<?=$result['total']?> ข้อ — ยินดีด้วย!<?php else: ?>ผ่านอัตโนมัติ<?php endif; ?>',confirmButtonColor:'#7C3AED',timer:3000,showConfirmButton:false});
});
<?php elseif ($result && !$result['passed'] && $result['attempt_no'] >= $result['max_att']): ?>
window.addEventListener('load', () => {
  Swal.fire({icon:'info',title:'ครบจำนวนครั้งแล้ว',
    text:'ระบบจะรีเซ็ตประวัติของหน่วยนี้เมื่อกลับหน้าบทเรียน เพื่อให้เริ่มต้นใหม่',
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
lmsInitTabSwitchGuard();
const total = <?=$total_q?>;
function countAnswered() {
  const cnt = lmsCountAnsweredQids();
  document.getElementById('answered').textContent = cnt;
  document.getElementById('progressBar').style.width = (cnt/total*100)+'%';
  return cnt;
}
document.querySelectorAll('[data-qid]').forEach(el => el.addEventListener('change', countAnswered));
document.querySelectorAll('textarea[data-qid], input[type=text][data-qid]').forEach(el => el.addEventListener('input', countAnswered));
function submitExam() {
  const cnt = countAnswered();
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
