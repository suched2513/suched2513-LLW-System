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

// ── Unlock enforcement (mirrors student/lms_subject.php's display logic) —
// server-side, since this page can be reached directly by URL regardless of what
// the subject overview shows/hides.
$mu = $pdo->prepare("SELECT id FROM lms_student_unit_unlocks WHERE student_uid=? AND unit_id=?");
$mu->execute([$uid, $unit_id]);
if (!$mu->fetch()) {
    $um = $pdo->prepare("SELECT unlock_mode FROM lms_subject_settings WHERE subject_id=?");
    $um->execute([$subject_id]); $unlock_mode = $um->fetchColumn() ?: 'open_all';

    if ($unlock_mode === 'sequential') {
        $pu = $pdo->prepare("SELECT id FROM lms_units WHERE subject_id=? AND order_no < ? AND status='published' AND deleted_at IS NULL ORDER BY order_no DESC LIMIT 1");
        $pu->execute([$subject_id, $unit['order_no']]); $prev_id = $pu->fetchColumn();
        if ($prev_id) {
            $ptq = $pdo->prepare("SELECT COUNT(*) FROM lms_post_questions WHERE unit_id=?"); $ptq->execute([$prev_id]);
            if ((int)$ptq->fetchColumn() > 0) {
                $ppost = $pdo->prepare("SELECT id FROM lms_student_post_exam WHERE student_uid=? AND unit_id=? AND passed=1 LIMIT 1");
                $ppost->execute([$uid, $prev_id]);
                if (!$ppost->fetch()) { header('Location: /student/lms_subject.php?subject_id='.$subject_id.'&locked=1'); exit(); }
            }
        }
    }
    // open_all: no cross-unit gate at all — every published unit's pre-test is reachable.
}

// Already submitted pre-exam? Redirect back (pre-exam is once-only)
$already_done = $pdo->prepare("SELECT id FROM lms_student_pre_exam WHERE student_uid=? AND unit_id=? LIMIT 1");
$already_done->execute([$uid,$unit_id]);
if ($already_done->fetch()) {
    header('Location: /student/lms_subject.php?subject_id='.$subject_id); exit();
}

$attempt_no = 1;

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
    $qs = $pdo->prepare("SELECT * FROM lms_pre_questions WHERE unit_id=? ORDER BY id");
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
    $tab_switch_count = max(0, (int)($_POST['tab_switch_count'] ?? 0));
    // Pre-exam always passes — it's before-learning assessment only
    $pdo->prepare("INSERT INTO lms_student_pre_exam (student_uid,subject_id,unit_id,score,total,passed,attempt_no,tab_switch_count) VALUES (?,?,?,?,?,1,?,?)")
        ->execute([$uid,$subject_id,$unit_id,$score,$total_auto,$attempt_no,$tab_switch_count]);
    $exam_record_id = (int)$pdo->lastInsertId();
    // Save manually-graded answers (text / upload) for teacher review
    // Save item-level results for auto-graded types (feeds item-analysis reports)
    $itemStmt = $pdo->prepare("INSERT INTO lms_exam_item_results (student_uid,subject_id,exam_type,exam_record_id,question_id,is_correct) VALUES (?,?,'pre',?,?,?)");
    foreach ($answers as $a) {
        if (!$a['auto'] && (!empty($a['text']) || !empty($a['file_path']))) {
            $pdo->prepare("INSERT INTO lms_student_exam_answers (student_uid,subject_id,exam_type,exam_record_id,question_id,answer_text,file_path) VALUES (?,?,'pre',?,?,?,?)")
                ->execute([$uid,$subject_id,$exam_record_id,$a['id'],$a['text'] ?? '',$a['file_path'] ?? null]);
        }
        if ($a['auto']) {
            $itemStmt->execute([$uid,$subject_id,$exam_record_id,$a['id'],$a['correct'] ? 1 : 0]);
        }
    }
    $result = ['score'=>$score,'total'=>$total_auto,'answers'=>$answers,'questions'=>$questions_post];
}

$qs = $pdo->prepare("SELECT * FROM lms_pre_questions WHERE unit_id=? ORDER BY id");
$qs->execute([$unit_id]); $questions = $qs->fetchAll();
$total_q = count($questions);

// Pre-generate shuffled choice order for each question (choice/multi_choice types)
$shuffled_orders = [];
foreach ($questions as $q) {
    $order = !empty($q['choice5']) ? [1,2,3,4,5] : [1,2,3,4];
    shuffle($order);
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
      <p class="text-violet-200 text-xs font-bold"><?=htmlspecialchars($unit['unit_name'],ENT_QUOTES,'UTF-8')?></p>
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
<div class="px-4 py-5 max-w-2xl mx-auto space-y-4">
  <div class="rounded-2xl p-6 text-center shadow-sm border-2 border-violet-200 bg-violet-50">
    <div class="text-5xl mb-3">📝</div>
    <p class="font-black text-xl text-slate-800">ส่งคำตอบแล้ว</p>
    <?php if ($result['total'] > 0): ?>
    <p class="text-4xl font-black mt-2 text-violet-600"><?=$result['score']?> <span class="text-lg text-slate-400 font-bold">/ <?=$result['total']?></span></p>
    <p class="text-sm text-slate-500 mt-1">คะแนนข้อที่ตรวจอัตโนมัติ</p>
    <?php endif; ?>
  </div>
  <p class="text-xs font-black text-slate-400 uppercase tracking-wider px-1">คำตอบของคุณ</p>
  <?php
  $ans_map = [];
  foreach ($result['answers'] as $a) $ans_map[$a['id']] = $a;
  $qtypes = lms_question_types();
  foreach ($result['questions'] as $i => $q):
    $ans = $ans_map[$q['id']] ?? null;
    $qtype = $q['question_type'] ?? 'choice';
  ?>
  <div class="rounded-2xl border bg-white p-4 shadow-sm border-slate-200">
    <p class="text-sm font-bold text-slate-700 mb-3"><?=$i+1?>. <?=htmlspecialchars($q['question_text'],ENT_QUOTES,'UTF-8')?>
      <?php if (!($qtypes[$qtype]['auto'] ?? true)): ?>
      <span class="ml-1 px-1.5 py-0.5 bg-violet-100 text-violet-600 text-[10px] font-black rounded-full"><?=htmlspecialchars($qtypes[$qtype]['label'] ?? '',ENT_QUOTES,'UTF-8')?></span>
      <?php endif; ?>
    </p>
    <?=lms_render_exam_result_review($q, $ans)?>
  </div>
  <?php endforeach; ?>
  <a href="/student/lms_subject.php?subject_id=<?=$subject_id?>&tab=content" class="block py-3 bg-violet-600 text-white font-bold text-sm rounded-xl text-center shadow-lg shadow-violet-200/50">
    <i class="bi bi-mortarboard-fill mr-1"></i> เข้าสู่บทเรียน
  </a>
</div>
<?php else: ?>
<form method="POST" id="examForm" class="px-4 py-5 max-w-2xl mx-auto space-y-4 pb-24" enctype="multipart/form-data">
  <input type="hidden" name="unit_id" value="<?=$unit_id?>">
  <input type="hidden" name="tab_switch_count" id="tab_switch_count" value="0">
  <?php $qtypes = lms_question_types(); foreach ($questions as $i => $q): $qtype = $q['question_type'] ?? 'choice'; ?>
  <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
    <p class="text-sm font-bold text-slate-800 mb-1 leading-snug">
      <span class="text-violet-600 font-black mr-1"><?=$i+1?>.</span>
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
<?=lms_exam_js_helpers()?>
<?php if ($result): ?>
window.addEventListener('load', () => {
  Swal.fire({icon:'success',title:'ส่งคำตอบแล้ว',<?php if($result['total']>0): ?>text:'คะแนนข้อที่ตรวจอัตโนมัติ <?=$result['score']?>/<?=$result['total']?> ข้อ',<?php endif; ?>confirmButtonColor:'#7C3AED',timer:2500,showConfirmButton:false});
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
