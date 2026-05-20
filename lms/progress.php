<?php
session_start();
require_once __DIR__ . '/../config.php';
if (!isset($_SESSION['llw_role'])) { header('Location: ' . $base_path . '/login.php'); exit(); }
if ($_SESSION['llw_role'] !== 'super_admin') { header('Location: ' . $base_path . '/login.php'); exit(); }

$pdo = getPdo();

// AJAX: student exercise answers
if (isset($_GET['ajax']) && $_GET['ajax'] === 'answers') {
    header('Content-Type: application/json');
    $uid  = (int)($_GET['student_uid'] ?? 0);
    $uid2 = (int)($_GET['unit_id'] ?? 0);
    $sid  = (int)($_GET['subject_id'] ?? 0);
    $rows = $pdo->prepare("
        SELECT e.exercise_title, se.answer_text, se.submitted_at
        FROM lms_student_exercises se
        JOIN lms_unit_exercises e ON e.id = se.exercise_id
        WHERE se.student_uid=? AND se.unit_id=? AND se.subject_id=?
        ORDER BY se.submitted_at
    ");
    $rows->execute([$uid,$uid2,$sid]);
    echo json_encode($rows->fetchAll()); exit();
}

$sel_subject = (int)($_GET['subject_id'] ?? 0);
$sel_class   = $_GET['class'] ?? '';

$subjects = $pdo->query("SELECT * FROM lms_subjects ORDER BY subject_name")->fetchAll();

$subject = null;
$classes = [];
$units   = [];
$students = [];
$settings = null;

if ($sel_subject) {
    $ss = $pdo->prepare("SELECT * FROM lms_subjects WHERE id=?"); $ss->execute([$sel_subject]); $subject = $ss->fetch();
    if ($subject) {
        $cs = $pdo->prepare("SELECT classroom FROM lms_subject_classrooms WHERE subject_id=? ORDER BY classroom");
        $cs->execute([$sel_subject]);
        $classes = $cs->fetchAll(PDO::FETCH_COLUMN);

        $us = $pdo->prepare("SELECT * FROM lms_units WHERE subject_id=? ORDER BY order_no");
        $us->execute([$sel_subject]);
        $units = $us->fetchAll();

        $es = $pdo->prepare("SELECT * FROM lms_exam_settings WHERE subject_id=?");
        $es->execute([$sel_subject]);
        $settings = $es->fetch();
    }
}

if ($sel_subject && $sel_class) {
    $q = $pdo->prepare("
        SELECT id, student_id, name AS student_name
        FROM att_students
        WHERE classroom=? AND status='active'
          AND student_id REGEXP '^[0-9]+$'
          AND student_id NOT IN (SELECT subject_code FROM att_subjects)
        ORDER BY student_id
    ");
    $q->execute([$sel_class]);
    $students = $q->fetchAll();
}

$pre_pass  = $settings['pre_pass_score'] ?? 6;
$post_pass = $settings['post_pass_score'] ?? 6;

$pageTitle    = 'ความคืบหน้า';
$pageSubtitle = 'ผลการเรียนและสถานะนักเรียน';
$activeSystem = 'lms';
require_once __DIR__ . '/../components/layout_start.php';
?>

<div class="flex items-center gap-3 mb-6">
  <div class="w-10 h-10 rounded-xl flex items-center justify-center shadow-lg bg-gradient-to-br from-amber-400 to-orange-500">
    <i class="fas fa-chart-line text-white"></i>
  </div>
  <div>
    <h2 class="text-lg font-black text-slate-800">ความคืบหน้า</h2>
    <p class="text-xs text-slate-400">ตรวจสอบผลการเรียนแต่ละวิชา</p>
  </div>
</div>

<!-- Selectors -->
<div class="bg-white rounded-2xl shadow-xl shadow-slate-100/50 border border-slate-100 p-5 mb-5">
  <form method="GET" class="flex gap-3 items-center flex-wrap">
    <label class="text-xs font-black text-slate-500">วิชา :</label>
    <select name="subject_id" onchange="this.form.submit()"
      class="border border-slate-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-violet-400 outline-none">
      <option value="">-- เลือกวิชา --</option>
      <?php foreach ($subjects as $subj): ?>
      <option value="<?=$subj['id']?>" <?=$sel_subject===$subj['id']?'selected':''?>>
        <?=htmlspecialchars($subj['subject_name'],ENT_QUOTES,'UTF-8')?>
      </option>
      <?php endforeach; ?>
    </select>
    <?php if ($sel_subject && !empty($classes)): ?>
    <label class="text-xs font-black text-slate-500">ห้อง :</label>
    <select name="class" onchange="this.form.submit()"
      class="border border-slate-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-violet-400 outline-none">
      <option value="">-- เลือกห้อง --</option>
      <?php foreach ($classes as $cl): ?>
      <option value="<?=htmlspecialchars($cl,ENT_QUOTES,'UTF-8')?>" <?=$sel_class===$cl?'selected':''?>>
        <?=htmlspecialchars($cl,ENT_QUOTES,'UTF-8')?>
      </option>
      <?php endforeach; ?>
    </select>
    <?php endif; ?>
  </form>
</div>

<?php if (!$sel_subject): ?>
<div class="bg-white rounded-2xl shadow-xl shadow-slate-100/50 border border-slate-100 p-16 text-center text-slate-300">
  <i class="fas fa-hand-point-up text-5xl mb-3 block opacity-30"></i>
  <p>โปรดเลือกวิชาก่อน</p>
</div>
<?php elseif (!$sel_class): ?>
<div class="bg-white rounded-2xl shadow-xl shadow-slate-100/50 border border-slate-100 p-16 text-center text-slate-300">
  <i class="fas fa-users text-5xl mb-3 block opacity-30"></i>
  <p>โปรดเลือกห้องเรียน<?=empty($classes)?' (วิชานี้ยังไม่ได้กำหนดห้อง)':''?></p>
</div>
<?php elseif (empty($students)): ?>
<div class="bg-white rounded-2xl shadow-xl shadow-slate-100/50 border border-slate-100 p-16 text-center text-slate-300">
  <p>ไม่มีนักเรียนในห้องนี้</p>
</div>
<?php else: ?>
<div class="bg-white rounded-2xl shadow-xl shadow-slate-100/50 border border-slate-100 overflow-hidden">
  <div class="overflow-x-auto">
    <table class="w-full text-xs">
      <thead class="bg-slate-50 text-slate-400 font-black uppercase tracking-wider">
        <tr>
          <th class="px-4 py-3 w-10">#</th>
          <th class="px-4 py-3 text-left">ชื่อ</th>
          <th class="px-4 py-3 text-center" style="min-width:90px">ก่อนเรียน<br><span class="font-normal text-[10px]">ผ่าน ≥ <?=$pre_pass?></span></th>
          <?php foreach ($units as $u): ?>
          <th class="px-4 py-3 text-center" style="min-width:80px"><?=htmlspecialchars(mb_substr($u['unit_name'],0,12),ENT_QUOTES,'UTF-8')?><br><span class="font-normal text-[10px]">แบบฝึกหัด</span></th>
          <?php endforeach; ?>
          <th class="px-4 py-3 text-center" style="min-width:90px">หลังเรียน<br><span class="font-normal text-[10px]">ผ่าน ≥ <?=$post_pass?></span></th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-50">
        <?php foreach ($students as $i => $s):
          $uid = $s['id'];
          $pre  = $pdo->prepare("SELECT score,total FROM lms_student_pre_exam WHERE student_uid=? AND subject_id=? AND passed=1 ORDER BY taken_at DESC LIMIT 1"); $pre->execute([$uid,$sel_subject]); $pre=$pre->fetch();
          $pre_latest = $pdo->prepare("SELECT score,total FROM lms_student_pre_exam WHERE student_uid=? AND subject_id=? ORDER BY taken_at DESC LIMIT 1"); $pre_latest->execute([$uid,$sel_subject]); $pre_latest=$pre_latest->fetch();
          $post = $pdo->prepare("SELECT score,total FROM lms_student_post_exam WHERE student_uid=? AND subject_id=? AND passed=1 ORDER BY taken_at DESC LIMIT 1"); $post->execute([$uid,$sel_subject]); $post=$post->fetch();
          $post_latest = $pdo->prepare("SELECT score,total FROM lms_student_post_exam WHERE student_uid=? AND subject_id=? ORDER BY taken_at DESC LIMIT 1"); $post_latest->execute([$uid,$sel_subject]); $post_latest=$post_latest->fetch();
        ?>
        <tr class="hover:bg-slate-50/50 transition-colors">
          <td class="px-4 py-3 text-center text-slate-400"><?=$i+1?></td>
          <td class="px-4 py-3 font-bold text-slate-700"><?=htmlspecialchars($s['student_name'],ENT_QUOTES,'UTF-8')?><div class="text-[10px] text-slate-400"><?=htmlspecialchars($s['student_id'],ENT_QUOTES,'UTF-8')?></div></td>
          <td class="px-4 py-3 text-center">
            <?php if ($pre): ?>
            <span class="px-2 py-0.5 bg-emerald-50 text-emerald-600 font-black rounded-full"><i class="fas fa-check-circle"></i> <?=$pre['score']?>/<?=$pre['total']?></span>
            <?php else: ?>
            <span class="px-2 py-0.5 bg-rose-50 text-rose-400 rounded-full"><?=$pre_latest?$pre_latest['score'].'/'.$pre_latest['total']:'—'?></span>
            <?php endif; ?>
          </td>
          <?php foreach ($units as $u):
            $exs = $pdo->prepare("SELECT id FROM lms_unit_exercises WHERE unit_id=?"); $exs->execute([$u['id']]); $exs=$exs->fetchAll();
            $ex_total = count($exs);
            $submitted = 0;
            foreach ($exs as $ex) {
                $chk = $pdo->prepare("SELECT id FROM lms_student_exercises WHERE student_uid=? AND exercise_id=? AND subject_id=? LIMIT 1");
                $chk->execute([$uid,$ex['id'],$sel_subject]);
                if ($chk->fetch()) $submitted++;
            }
          ?>
          <td class="px-4 py-3 text-center">
            <?php if ($ex_total===0): ?>
            <span class="text-slate-300">ไม่มี</span>
            <?php else: ?>
            <button onclick="viewAnswers(<?=$u['id']?>,<?=$uid?>,'<?=htmlspecialchars($s['student_name'],ENT_QUOTES,'UTF-8')?>','<?=htmlspecialchars($u['unit_name'],ENT_QUOTES,'UTF-8')?>')"
              class="px-2 py-0.5 rounded-full cursor-pointer hover:opacity-80 transition font-bold <?=$submitted>=$ex_total?'bg-emerald-50 text-emerald-600':'bg-amber-50 text-amber-600'?>">
              <?=$submitted?>/<?=$ex_total?>
            </button>
            <?php endif; ?>
          </td>
          <?php endforeach; ?>
          <td class="px-4 py-3 text-center">
            <?php if ($post): ?>
            <span class="px-2 py-0.5 bg-emerald-50 text-emerald-600 font-black rounded-full"><i class="fas fa-check-circle"></i> <?=$post['score']?>/<?=$post['total']?></span>
            <?php else: ?>
            <span class="px-2 py-0.5 bg-rose-50 text-rose-400 rounded-full"><?=$post_latest?$post_latest['score'].'/'.$post_latest['total']:'—'?></span>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<!-- Answer Modal -->
<div id="ansModal" class="fixed inset-0 z-50 hidden items-center justify-center p-4" style="background:rgba(0,0,0,0.4)">
  <div class="bg-white rounded-2xl shadow-2xl w-full max-w-xl">
    <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100">
      <div>
        <h3 class="font-black text-slate-800 text-sm" id="ansName">ชื่อนักเรียน</h3>
        <p class="text-xs text-slate-400 mt-0.5" id="ansUnit">หน่วย</p>
      </div>
      <button onclick="closeModal('ansModal')" class="text-slate-400 hover:text-slate-600"><i class="fas fa-times text-lg"></i></button>
    </div>
    <div id="ansContent" class="p-6 space-y-4 max-h-[60vh] overflow-y-auto">
      <div class="text-center text-slate-300 py-8">กำลังโหลด...</div>
    </div>
  </div>
</div>

<script>
function openModal(id){const el=document.getElementById(id);el.classList.remove('hidden');el.classList.add('flex');}
function closeModal(id){const el=document.getElementById(id);el.classList.add('hidden');el.classList.remove('flex');}

async function viewAnswers(unitId, uid, name, unitName) {
  document.getElementById('ansName').textContent = name;
  document.getElementById('ansUnit').textContent = unitName;
  document.getElementById('ansContent').innerHTML = '<div class="text-center py-8 text-slate-300">กำลังโหลด...</div>';
  openModal('ansModal');
  const data = await fetch(`progress.php?ajax=answers&unit_id=${unitId}&student_uid=${uid}&subject_id=<?=$sel_subject?>`).then(r=>r.json());
  if (!data.length) {
    document.getElementById('ansContent').innerHTML = '<div class="text-center py-8 text-slate-300">ยังไม่มีคำตอบ</div>';
    return;
  }
  let html = '';
  data.forEach(d => {
    const dt = new Date(d.submitted_at).toLocaleString('th-TH');
    const ans = (d.answer_text||'').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/\n/g,'<br>') || '<em class="text-slate-300">ไม่มีข้อความ</em>';
    html += `<div class="rounded-xl bg-slate-50 border border-slate-100 p-4">
      <div class="flex justify-between items-center mb-2">
        <span class="text-xs font-black text-slate-700"><i class="fas fa-tasks text-teal-500 mr-1"></i>${d.exercise_title}</span>
        <span class="text-xs text-slate-400">${dt}</span>
      </div>
      <div class="text-xs text-slate-600 bg-white p-3 rounded-lg border-l-4 border-teal-400 leading-relaxed">${ans}</div>
    </div>`;
  });
  document.getElementById('ansContent').innerHTML = html;
}
</script>

<?php require_once __DIR__ . '/../components/layout_end.php'; ?>
