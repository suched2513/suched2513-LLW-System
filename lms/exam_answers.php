<?php
session_start();
require_once __DIR__ . '/../config.php';
if (!isset($_SESSION['llw_role'])) { header('Location: ' . $base_path . '/login.php'); exit(); }
if ($_SESSION['llw_role'] !== 'super_admin') { header('Location: ' . $base_path . '/login.php'); exit(); }

$pdo = getPdo();

// AJAX: save teacher score + feedback
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_save'])) {
    header('Content-Type: application/json');
    try {
        $answer_id = (int)$_POST['answer_id'];
        $score     = $_POST['teacher_score'] !== '' ? max(0, min(100, (int)$_POST['teacher_score'])) : null;
        $feedback  = trim($_POST['teacher_feedback'] ?? '');
        $pdo->prepare("UPDATE lms_student_exam_answers SET teacher_score=?, teacher_feedback=?, reviewed_at=NOW() WHERE id=?")
            ->execute([$score, $feedback ?: null, $answer_id]);
        echo json_encode(['ok' => true]);
    } catch (Exception $e) {
        error_log($e->getMessage());
        echo json_encode(['ok' => false]);
    }
    exit();
}

$sel_subject   = (int)($_GET['subject_id'] ?? 0);
$sel_exam_type = in_array($_GET['type'] ?? '', ['pre','post']) ? $_GET['type'] : 'post';
$sel_class     = $_GET['class'] ?? '';

$subjects = $pdo->query("SELECT * FROM lms_subjects ORDER BY subject_name")->fetchAll();
$subject  = null;
$classes  = [];
$text_questions = [];
$answers_by_q   = [];

if ($sel_subject) {
    $ss = $pdo->prepare("SELECT * FROM lms_subjects WHERE id=?"); $ss->execute([$sel_subject]); $subject = $ss->fetch();
    if ($subject) {
        $cs = $pdo->prepare("SELECT classroom FROM lms_subject_classrooms WHERE subject_id=? ORDER BY classroom");
        $cs->execute([$sel_subject]); $classes = $cs->fetchAll(PDO::FETCH_COLUMN);
    }
}

if ($sel_subject && $sel_class) {
    $table = $sel_exam_type === 'pre' ? 'lms_pre_questions' : 'lms_post_questions';
    $tq = $pdo->prepare("SELECT * FROM {$table} WHERE subject_id=? AND question_type='text' ORDER BY id");
    $tq->execute([$sel_subject]); $text_questions = $tq->fetchAll();

    foreach ($text_questions as $q) {
        $rows = $pdo->prepare("
            SELECT a.id, a.student_uid, a.answer_text, a.teacher_score, a.teacher_feedback, a.reviewed_at,
                   s.name AS student_name, s.student_id AS student_no
            FROM lms_student_exam_answers a
            JOIN att_students s ON s.id = a.student_uid
            WHERE a.question_id=? AND a.exam_type=? AND a.subject_id=? AND s.classroom=?
            ORDER BY s.student_id
        ");
        $rows->execute([$q['id'], $sel_exam_type, $sel_subject, $sel_class]);
        $answers_by_q[$q['id']] = $rows->fetchAll();
    }
}

// Summary counts
$total_answers   = 0;
$reviewed_count  = 0;
foreach ($answers_by_q as $rows) {
    foreach ($rows as $r) {
        $total_answers++;
        if ($r['reviewed_at']) $reviewed_count++;
    }
}

$pageTitle    = 'ตรวจคำตอบอัตนัย';
$pageSubtitle = $subject ? htmlspecialchars($subject['subject_name'],ENT_QUOTES,'UTF-8') : 'เลือกวิชา';
$activeSystem = 'lms';
require_once __DIR__ . '/../components/layout_start.php';
?>

<div class="flex flex-wrap items-center justify-between gap-4 mb-6">
  <div class="flex items-center gap-3">
    <div class="w-10 h-10 rounded-xl flex items-center justify-center shadow-lg bg-gradient-to-br from-violet-500 to-purple-700">
      <i class="fas fa-pen-fancy text-white"></i>
    </div>
    <div>
      <a href="<?=$base_path?>/lms/subjects.php" class="text-xs text-violet-600 hover:underline"><i class="fas fa-arrow-left mr-1"></i>วิชาทั้งหมด</a>
      <h2 class="text-lg font-black text-slate-800">ตรวจคำตอบอัตนัย</h2>
      <p class="text-xs text-slate-400">ข้อสอบก่อนเรียน / หลังเรียน ประเภทตอบอิสระ</p>
    </div>
  </div>
</div>

<!-- Filters -->
<div class="bg-white rounded-2xl shadow-xl shadow-slate-100/50 border border-slate-100 p-5 mb-5">
  <form method="GET" class="flex flex-wrap gap-3 items-center">
    <label class="text-xs font-black text-slate-500">วิชา:</label>
    <select name="subject_id" onchange="this.form.submit()"
      class="border border-slate-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-violet-400 outline-none">
      <option value="">-- เลือกวิชา --</option>
      <?php foreach ($subjects as $subj): ?>
      <option value="<?=$subj['id']?>" <?=$sel_subject===$subj['id']?'selected':''?>>
        <?=htmlspecialchars($subj['subject_name'],ENT_QUOTES,'UTF-8')?>
      </option>
      <?php endforeach; ?>
    </select>
    <?php if ($sel_subject): ?>
    <label class="text-xs font-black text-slate-500">ประเภท:</label>
    <select name="type" onchange="this.form.submit()"
      class="border border-slate-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-violet-400 outline-none">
      <option value="post" <?=$sel_exam_type==='post'?'selected':''?>>หลังเรียน</option>
      <option value="pre"  <?=$sel_exam_type==='pre' ?'selected':''?>>ก่อนเรียน</option>
    </select>
    <?php if (!empty($classes)): ?>
    <label class="text-xs font-black text-slate-500">ห้อง:</label>
    <select name="class" onchange="this.form.submit()"
      class="border border-slate-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-violet-400 outline-none">
      <option value="">-- เลือกห้อง --</option>
      <?php foreach ($classes as $cl): ?>
      <option value="<?=htmlspecialchars($cl,ENT_QUOTES,'UTF-8')?>" <?=$sel_class===$cl?'selected':''?>>
        <?=htmlspecialchars($cl,ENT_QUOTES,'UTF-8')?>
      </option>
      <?php endforeach; ?>
    </select>
    <input type="hidden" name="subject_id" value="<?=$sel_subject?>">
    <?php endif; ?>
    <?php endif; ?>
  </form>
</div>

<?php if (!$sel_subject || !$sel_class): ?>
<div class="bg-white rounded-2xl shadow-xl shadow-slate-100/50 border border-slate-100 p-16 text-center text-slate-300">
  <i class="fas fa-pen-fancy text-5xl mb-3 block opacity-30"></i>
  <p>เลือกวิชาและห้องเรียนเพื่อดูคำตอบ</p>
</div>
<?php elseif (empty($text_questions)): ?>
<div class="bg-white rounded-2xl shadow-xl shadow-slate-100/50 border border-slate-100 p-12 text-center text-slate-300">
  <i class="fas fa-clipboard text-4xl mb-3 block opacity-30"></i>
  <p class="font-bold">ไม่มีข้อสอบประเภทอัตนัยใน<?=$sel_exam_type==='pre'?'ก่อนเรียน':'หลังเรียน'?>วิชานี้</p>
</div>
<?php else: ?>

<!-- KPI -->
<?php if ($total_answers > 0): ?>
<div class="grid grid-cols-3 gap-4 mb-5">
  <div class="bg-white rounded-2xl shadow-xl shadow-slate-100/50 border border-slate-100 p-4 text-center">
    <p class="text-3xl font-black text-slate-700"><?=$total_answers?></p>
    <p class="text-xs text-slate-400 font-bold mt-1">คำตอบทั้งหมด</p>
  </div>
  <div class="bg-white rounded-2xl shadow-xl shadow-slate-100/50 border border-slate-100 p-4 text-center">
    <p class="text-3xl font-black text-violet-600"><?=$reviewed_count?></p>
    <p class="text-xs text-slate-400 font-bold mt-1">ตรวจแล้ว</p>
  </div>
  <div class="bg-white rounded-2xl shadow-xl shadow-slate-100/50 border border-slate-100 p-4 text-center">
    <p class="text-3xl font-black text-amber-500"><?=$total_answers-$reviewed_count?></p>
    <p class="text-xs text-slate-400 font-bold mt-1">รอตรวจ</p>
  </div>
</div>
<?php endif; ?>

<!-- Questions + answers -->
<?php foreach ($text_questions as $qi => $q): $rows = $answers_by_q[$q['id']] ?? []; ?>
<div class="bg-white rounded-2xl shadow-xl shadow-slate-100/50 border border-slate-100 overflow-hidden mb-4">
  <div class="px-6 py-4 bg-violet-50 border-b border-violet-100 flex items-start justify-between gap-4">
    <div>
      <p class="text-xs font-black text-violet-400 uppercase tracking-wider mb-1">ข้อ <?=$qi+1?> · <?=$sel_exam_type==='pre'?'ก่อนเรียน':'หลังเรียน'?></p>
      <p class="font-bold text-slate-800 text-sm"><?=htmlspecialchars($q['question_text'],ENT_QUOTES,'UTF-8')?></p>
    </div>
    <span class="px-3 py-1 bg-violet-100 text-violet-700 text-xs font-black rounded-full flex-shrink-0"><?=count($rows)?> คำตอบ</span>
  </div>
  <?php if (empty($rows)): ?>
  <div class="px-6 py-8 text-center text-slate-300 text-sm">ยังไม่มีนักเรียนส่งคำตอบข้อนี้</div>
  <?php else: ?>
  <div class="divide-y divide-slate-50">
    <?php foreach ($rows as $r): $is_reviewed = (bool)$r['reviewed_at']; ?>
    <div class="px-5 py-4" id="row_<?=$r['id']?>">
      <div class="flex items-center justify-between mb-2">
        <div>
          <span class="font-bold text-slate-700 text-sm"><?=htmlspecialchars($r['student_name'],ENT_QUOTES,'UTF-8')?></span>
          <span class="text-xs text-slate-400 ml-2"><?=htmlspecialchars($r['student_no'],ENT_QUOTES,'UTF-8')?></span>
        </div>
        <?php if ($is_reviewed): ?>
        <span class="px-2 py-0.5 bg-violet-100 text-violet-600 text-xs font-black rounded-full">
          <i class="fas fa-check mr-1"></i>ตรวจแล้ว<?=$r['teacher_score']!==null?' · '.$r['teacher_score'].' คะแนน':''?>
        </span>
        <?php else: ?>
        <span class="px-2 py-0.5 bg-amber-50 text-amber-500 text-xs font-bold rounded-full">รอตรวจ</span>
        <?php endif; ?>
      </div>
      <div class="bg-slate-50 rounded-xl border border-slate-100 px-4 py-3 text-sm text-slate-600 leading-relaxed mb-3">
        <?=nl2br(htmlspecialchars($r['answer_text'],ENT_QUOTES,'UTF-8'))?>
      </div>
      <div class="flex gap-2 items-end flex-wrap">
        <div>
          <label class="block text-xs font-black text-slate-400 mb-1">คะแนน (0–100)</label>
          <input type="number" min="0" max="100" placeholder="—"
            value="<?=$r['teacher_score']!==null?$r['teacher_score']:''?>"
            class="w-24 border border-slate-200 rounded-xl px-3 py-2 text-sm font-bold text-center focus:ring-2 focus:ring-violet-400 outline-none"
            id="score_<?=$r['id']?>">
        </div>
        <div class="flex-1 min-w-48">
          <label class="block text-xs font-black text-slate-400 mb-1">ความคิดเห็น / คำแนะนำ</label>
          <input type="text" placeholder="พิมพ์ความคิดเห็น..."
            value="<?=htmlspecialchars($r['teacher_feedback']??'',ENT_QUOTES,'UTF-8')?>"
            class="w-full border border-slate-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-violet-400 outline-none"
            id="fb_<?=$r['id']?>">
        </div>
        <button onclick="saveAnswer(<?=$r['id']?>)"
          class="px-4 py-2 bg-violet-600 text-white text-xs font-bold rounded-xl shadow-lg shadow-violet-200 hover:bg-violet-700 transition-all" id="btn_<?=$r['id']?>">
          <i class="fas fa-save mr-1"></i>บันทึก
        </button>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>
<?php endforeach; ?>
<?php endif; ?>

<script>
async function saveAnswer(id) {
  const score = document.getElementById('score_'+id).value;
  const fb    = document.getElementById('fb_'+id).value;
  const btn   = document.getElementById('btn_'+id);
  btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>กำลังบันทึก...';
  try {
    const fd = new FormData();
    fd.append('ajax_save','1'); fd.append('answer_id',id);
    fd.append('teacher_score',score); fd.append('teacher_feedback',fb);
    const res = await fetch('exam_answers.php?subject_id=<?=$sel_subject?>&type=<?=$sel_exam_type?>&class=<?=urlencode($sel_class?:'')?>', {method:'POST',body:fd});
    const data = await res.json();
    if (data.ok) {
      btn.innerHTML = '<i class="fas fa-check mr-1"></i>บันทึกแล้ว';
      btn.className = btn.className.replace('bg-violet-600 hover:bg-violet-700','bg-emerald-500 hover:bg-emerald-600');
      setTimeout(()=>{ btn.innerHTML='<i class="fas fa-save mr-1"></i>บันทึก'; btn.disabled=false; },2500);
    } else { throw new Error(); }
  } catch {
    btn.innerHTML = '<i class="fas fa-exclamation mr-1"></i>ผิดพลาด';
    btn.disabled = false;
  }
}
</script>

<?php require_once __DIR__ . '/../components/layout_end.php'; ?>
