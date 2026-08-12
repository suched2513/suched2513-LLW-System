<?php
session_start();
require_once __DIR__ . '/../config.php';
if (!isset($_SESSION['llw_role'])) { header('Location: ' . $base_path . '/login.php'); exit(); }
if (!in_array($_SESSION['llw_role'], ['super_admin','att_teacher'])) { header('Location: ' . $base_path . '/login.php'); exit(); }
require_once __DIR__ . '/_helpers.php';

$pdo        = getPdo();
$is_admin   = $_SESSION['llw_role'] === 'super_admin';
$teacher_id = (int)($_SESSION['teacher_id'] ?? 0);

// AJAX: student exercise answers
if (isset($_GET['ajax']) && $_GET['ajax'] === 'answers') {
    header('Content-Type: application/json');
    $uid  = (int)($_GET['student_uid'] ?? 0);
    $uid2 = (int)($_GET['unit_id'] ?? 0);
    $sid  = (int)($_GET['subject_id'] ?? 0);
    $rows = $pdo->prepare("
        SELECT e.id AS exercise_id, e.exercise_title, e.max_score,
               se.id AS sub_id, se.answer_text, se.file_paths, se.grade, se.feedback, se.reviewed_at, se.submitted_at
        FROM lms_student_exercises se
        JOIN lms_unit_exercises e ON e.id = se.exercise_id
        WHERE se.student_uid=? AND se.unit_id=? AND se.subject_id=?
        ORDER BY se.submitted_at
    ");
    $rows->execute([$uid,$uid2,$sid]);
    echo json_encode($rows->fetchAll()); exit();
}

// AJAX: save exercise feedback
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax']) && $_POST['ajax'] === 'save_feedback') {
    header('Content-Type: application/json');
    try {
        $sub_id   = (int)$_POST['sub_id'];
        $grade    = $_POST['grade'] !== '' ? max(0, (int)$_POST['grade']) : null;
        $feedback = trim($_POST['feedback'] ?? '');

        $row = $pdo->prepare("SELECT subject_id FROM lms_student_exercises WHERE id=?");
        $row->execute([$sub_id]); $row = $row->fetch();
        if (!$row || !lms_get_owned_subject($pdo, (int)$row['subject_id'], $is_admin, $teacher_id)) {
            throw new Exception('ไม่มีสิทธิ์ตรวจงานนี้');
        }

        $pdo->prepare("UPDATE lms_student_exercises SET grade=?, feedback=?, reviewed_at=NOW() WHERE id=?")
            ->execute([$grade, $feedback ?: null, $sub_id]);
        lms_log_activity($pdo, 'grade', 'lms_student_exercise', $sub_id, null, ['grade' => $grade]);
        echo json_encode(['ok' => true]);
    } catch (Exception $e) {
        error_log($e->getMessage());
        echo json_encode(['ok' => false]);
    }
    exit();
}

// AJAX: toggle manual unlock override for a student/unit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax']) && $_POST['ajax'] === 'toggle_unlock') {
    header('Content-Type: application/json');
    try {
        $uid = (int)$_POST['student_uid'];
        $unitId = (int)$_POST['unit_id'];
        if (!$uid || !$unitId) throw new Exception('invalid');
        if (!lms_get_owned_unit($pdo, $unitId, $is_admin, $teacher_id)) throw new Exception('ไม่มีสิทธิ์ปลดล็อกหน่วยนี้');

        $chk = $pdo->prepare("SELECT id FROM lms_student_unit_unlocks WHERE student_uid=? AND unit_id=?");
        $chk->execute([$uid, $unitId]);
        if ($chk->fetch()) {
            $pdo->prepare("DELETE FROM lms_student_unit_unlocks WHERE student_uid=? AND unit_id=?")->execute([$uid, $unitId]);
            lms_log_activity($pdo, 'lock_unit', 'lms_student', $uid, null, ['unit_id' => $unitId]);
            echo json_encode(['ok' => true, 'unlocked' => false]);
        } else {
            $pdo->prepare("INSERT INTO lms_student_unit_unlocks (student_uid, unit_id, unlocked_by) VALUES (?,?,?)")
                ->execute([$uid, $unitId, $_SESSION['user_id'] ?? null]);
            lms_log_activity($pdo, 'unlock_unit', 'lms_student', $uid, null, ['unit_id' => $unitId]);
            echo json_encode(['ok' => true, 'unlocked' => true]);
        }
    } catch (Exception $e) {
        error_log($e->getMessage());
        echo json_encode(['ok' => false]);
    }
    exit();
}

// AJAX: reset student progress
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax']) && $_POST['ajax'] === 'reset_student') {
    header('Content-Type: application/json');
    try {
        $uid = (int)$_POST['student_uid'];
        $sid = (int)$_POST['subject_id'];
        if (!$uid || !$sid) throw new Exception('invalid');
        if (!lms_get_owned_subject($pdo, $sid, $is_admin, $teacher_id)) throw new Exception('ไม่มีสิทธิ์ล้างข้อมูลวิชานี้');
        $pdo->beginTransaction();
        $pdo->prepare("DELETE FROM lms_student_pre_exam      WHERE student_uid=? AND subject_id=?")->execute([$uid,$sid]);
        $pdo->prepare("DELETE FROM lms_student_post_exam     WHERE student_uid=? AND subject_id=?")->execute([$uid,$sid]);
        $pdo->prepare("DELETE FROM lms_student_exercises     WHERE student_uid=? AND subject_id=?")->execute([$uid,$sid]);
        $pdo->prepare("DELETE FROM lms_student_exam_answers  WHERE student_uid=? AND subject_id=?")->execute([$uid,$sid]);
        $pdo->commit();
        lms_log_activity($pdo, 'reset_progress', 'lms_student', $uid, null, ['subject_id' => $sid]);
        echo json_encode(['ok' => true]);
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log($e->getMessage());
        echo json_encode(['ok' => false]);
    }
    exit();
}

$sel_subject = (int)($_GET['subject_id'] ?? 0);
$sel_class   = $_GET['class'] ?? '';

if ($is_admin) {
    $subjects = $pdo->query("SELECT * FROM lms_subjects ORDER BY subject_name")->fetchAll();
} else {
    $st = $pdo->prepare("SELECT * FROM lms_subjects WHERE teacher_id=? OR teacher_id IS NULL ORDER BY subject_name");
    $st->execute([$teacher_id]); $subjects = $st->fetchAll();
}

$subject = null; $classes = []; $units = []; $students = []; $unlock_mode = 'open_all';

if ($sel_subject) {
    $subject = lms_get_owned_subject($pdo, $sel_subject, $is_admin, $teacher_id);
    if ($subject) {
        $cs = $pdo->prepare("SELECT classroom FROM lms_subject_classrooms WHERE subject_id=? ORDER BY classroom");
        $cs->execute([$sel_subject]); $classes = $cs->fetchAll(PDO::FETCH_COLUMN);
        $us = $pdo->prepare("SELECT * FROM lms_units WHERE subject_id=? ORDER BY order_no");
        $us->execute([$sel_subject]); $units = $us->fetchAll();
        $um = $pdo->prepare("SELECT unlock_mode FROM lms_subject_settings WHERE subject_id=?");
        $um->execute([$sel_subject]); $unlock_mode = $um->fetchColumn() ?: 'open_all';
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
    $q->execute([$sel_class]); $students = $q->fetchAll();
}

// Manual per-student unit unlocks (for sequential mode overrides)
$manual_unlocks = [];
if ($unlock_mode === 'sequential' && !empty($units) && !empty($students)) {
    $unit_ids = array_column($units, 'id');
    $stu_ids  = array_column($students, 'id');
    $uph = implode(',', array_fill(0, count($unit_ids), '?'));
    $sph = implode(',', array_fill(0, count($stu_ids), '?'));
    $mu = $pdo->prepare("SELECT student_uid, unit_id FROM lms_student_unit_unlocks WHERE unit_id IN ($uph) AND student_uid IN ($sph)");
    $mu->execute(array_merge($unit_ids, $stu_ids));
    foreach ($mu->fetchAll() as $r) $manual_unlocks[$r['student_uid'] . '_' . $r['unit_id']] = true;
}

// Pre-compute per-unit exam data + KPI
$unit_count = count($units);
$kpi = ['total'=>count($students),'not_started'=>0,'fully_completed'=>0,'units_completed_sum'=>0];
$student_data = [];
foreach ($students as $s) {
    $uid = $s['id'];
    $any_activity = false; $completed_units = 0; $per_unit = [];
    foreach ($units as $u) {
        $un = $u['id'];
        $pre  = $pdo->prepare("SELECT score,total FROM lms_student_pre_exam  WHERE student_uid=? AND unit_id=? AND passed=1 ORDER BY taken_at DESC LIMIT 1"); $pre->execute([$uid,$un]); $pre=$pre->fetch();
        $pre_latest = $pdo->prepare("SELECT score,total FROM lms_student_pre_exam  WHERE student_uid=? AND unit_id=? ORDER BY taken_at DESC LIMIT 1"); $pre_latest->execute([$uid,$un]); $pre_latest=$pre_latest->fetch();
        $post = $pdo->prepare("SELECT score,total FROM lms_student_post_exam WHERE student_uid=? AND unit_id=? AND passed=1 ORDER BY taken_at DESC LIMIT 1"); $post->execute([$uid,$un]); $post=$post->fetch();
        $post_latest = $pdo->prepare("SELECT score,total FROM lms_student_post_exam WHERE student_uid=? AND unit_id=? ORDER BY taken_at DESC LIMIT 1"); $post_latest->execute([$uid,$un]); $post_latest=$post_latest->fetch();
        if ($pre || $pre_latest || $post || $post_latest) $any_activity = true;
        if ($post) $completed_units++;
        $per_unit[$un] = compact('pre','pre_latest','post','post_latest');
    }
    if (!$any_activity) $kpi['not_started']++;
    if ($unit_count > 0 && $completed_units === $unit_count) $kpi['fully_completed']++;
    $kpi['units_completed_sum'] += $completed_units;
    $student_data[$uid] = $per_unit;
}

$pageTitle    = 'ความคืบหน้า';
$pageSubtitle = 'ผลการเรียนและสถานะนักเรียน';
$activeSystem = 'lms';
require_once __DIR__ . '/../components/layout_start.php';
?>

<div class="flex flex-wrap items-center justify-between gap-4 mb-6">
  <div class="flex items-center gap-3">
    <div class="w-10 h-10 rounded-xl flex items-center justify-center shadow-lg bg-gradient-to-br from-amber-400 to-orange-500">
      <i class="fas fa-chart-line text-white"></i>
    </div>
    <div>
      <h2 class="text-lg font-black text-slate-800">ความคืบหน้า</h2>
      <p class="text-xs text-slate-400">ผลการเรียนแต่ละวิชา</p>
    </div>
  </div>
  <?php if ($sel_subject && $sel_class): ?>
  <a href="exam_answers.php?subject_id=<?=$sel_subject?>&class=<?=urlencode($sel_class)?>"
     class="px-3 py-2 bg-violet-600 text-white text-xs font-bold rounded-xl shadow-lg shadow-violet-200 hover:bg-violet-700 transition-all">
    <i class="fas fa-pen-fancy mr-1"></i> ตรวจอัตนัย
  </a>
  <?php endif; ?>
</div>

<!-- Filters -->
<div class="bg-white rounded-2xl shadow-xl shadow-slate-100/50 border border-slate-100 p-5 mb-5">
  <form method="GET" class="flex gap-3 items-center flex-wrap">
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
    <?php if ($sel_subject && !empty($classes)): ?>
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
    <?php endif; ?>
  </form>
</div>

<?php if (!$sel_subject): ?>
<div class="bg-white rounded-2xl shadow-xl shadow-slate-100/50 border border-slate-100 p-16 text-center text-slate-300">
  <i class="fas fa-hand-point-up text-5xl mb-3 block opacity-30"></i><p>โปรดเลือกวิชาก่อน</p>
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

<!-- KPI -->
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-5">
  <div class="bg-white rounded-2xl shadow-xl shadow-slate-100/50 border border-slate-100 p-5">
    <p class="text-xs text-slate-400 font-bold mb-1">นักเรียนทั้งหมด</p>
    <p class="text-3xl font-black text-slate-700"><?=$kpi['total']?></p>
    <p class="text-xs text-slate-400 mt-1">ห้อง <?=htmlspecialchars($sel_class,ENT_QUOTES,'UTF-8')?></p>
  </div>
  <div class="bg-white rounded-2xl shadow-xl shadow-slate-100/50 border border-slate-100 p-5">
    <p class="text-xs text-slate-400 font-bold mb-1">จบครบทุกหน่วย</p>
    <p class="text-3xl font-black text-emerald-600"><?=$kpi['fully_completed']?></p>
    <p class="text-xs text-slate-400 mt-1">จาก <?=$kpi['total']?> คน (<?=$unit_count?> หน่วย)</p>
  </div>
  <div class="bg-white rounded-2xl shadow-xl shadow-slate-100/50 border border-slate-100 p-5">
    <p class="text-xs text-slate-400 font-bold mb-1">เฉลี่ยหน่วยที่จบ</p>
    <p class="text-3xl font-black text-blue-600"><?=$kpi['total']>0?round($kpi['units_completed_sum']/$kpi['total'],1):0?></p>
    <p class="text-xs text-slate-400 mt-1">จาก <?=$unit_count?> หน่วย</p>
  </div>
  <div class="bg-white rounded-2xl shadow-xl shadow-slate-100/50 border border-slate-100 p-5">
    <p class="text-xs text-slate-400 font-bold mb-1">ยังไม่เริ่ม</p>
    <p class="text-3xl font-black text-rose-500"><?=$kpi['not_started']?></p>
    <p class="text-xs text-slate-400 mt-1">ยังไม่มีประวัติเลย</p>
  </div>
</div>

<!-- Table -->
<div class="bg-white rounded-2xl shadow-xl shadow-slate-100/50 border border-slate-100 overflow-hidden">
  <div class="overflow-x-auto">
    <table class="w-full text-xs">
      <thead class="bg-slate-50 text-slate-400 font-black uppercase tracking-wider">
        <tr>
          <th class="px-4 py-3 w-8">#</th>
          <th class="px-4 py-3 text-left">ชื่อ–สกุล</th>
          <?php foreach ($units as $u): ?>
          <th class="px-3 py-3 text-center border-l border-slate-100" style="min-width:84px">
            <?=htmlspecialchars(mb_substr($u['unit_name'],0,12),ENT_QUOTES,'UTF-8')?><?=$unlock_mode==='sequential'?' <i class="fas fa-link"></i>':''?>
            <br><span class="font-normal text-[9px] normal-case">ก่อน · งาน · หลัง</span>
          </th>
          <?php endforeach; ?>
          <th class="px-4 py-3 text-center w-16">รีเซ็ต</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-50">
        <?php foreach ($students as $i => $s):
          $uid  = $s['id'];
        ?>
        <tr class="hover:bg-slate-50/50 transition-colors" id="row_<?=$uid?>">
          <td class="px-4 py-3 text-center text-slate-400"><?=$i+1?></td>
          <td class="px-4 py-3">
            <div class="font-bold text-slate-700"><?=htmlspecialchars($s['student_name'],ENT_QUOTES,'UTF-8')?></div>
            <div class="text-[10px] text-slate-400"><?=htmlspecialchars($s['student_id'],ENT_QUOTES,'UTF-8')?></div>
          </td>
          <?php $prev_unit_post_ok = true; foreach ($units as $ui => $u):
            $un   = $u['id'];
            $ud   = $student_data[$uid][$un];
            $pre  = $ud['pre']; $pre_latest = $ud['pre_latest'];
            $post = $ud['post']; $post_latest = $ud['post_latest'];

            $exs = $pdo->prepare("SELECT id, is_remedial FROM lms_unit_exercises WHERE unit_id=?"); $exs->execute([$un]); $exs=$exs->fetchAll();
            $ex_total = count($exs); $submitted = 0;
            foreach ($exs as $ex) {
                $chk = $pdo->prepare("SELECT id FROM lms_student_exercises WHERE student_uid=? AND exercise_id=? AND unit_id=? LIMIT 1");
                $chk->execute([$uid,$ex['id'],$un]);
                if ($chk->fetch()) $submitted++;
            }

            $is_locked = $unlock_mode === 'sequential' && $ui > 0 && !$prev_unit_post_ok && empty($manual_unlocks[$uid . '_' . $un]);
            $prev_unit_post_ok = (bool)$post;
          ?>
          <td class="px-3 py-3 text-center border-l border-slate-50">
            <?php if ($is_locked): ?>
            <button onclick="toggleUnlock(<?=$un?>,<?=$uid?>,this)"
              class="px-2 py-0.5 rounded-full bg-slate-100 text-slate-400 font-bold hover:bg-violet-100 hover:text-violet-600 transition-all" title="ล็อกอยู่ — กดเพื่อปลดล็อก">
              <i class="fas fa-lock"></i>
            </button>
            <?php else: ?>
            <div class="flex flex-col items-center gap-1">
              <?php if ($pre): ?>
              <span class="px-1.5 py-0.5 bg-emerald-50 text-emerald-600 text-[10px] font-black rounded-full" title="ก่อนเรียน"><?=$pre['score']?>/<?=$pre['total']?></span>
              <?php else: ?>
              <span class="px-1.5 py-0.5 bg-slate-50 text-slate-400 text-[10px] rounded-full" title="ก่อนเรียน"><?=$pre_latest?$pre_latest['score'].'/'.$pre_latest['total']:'—'?></span>
              <?php endif; ?>

              <?php if ($ex_total===0): ?>
              <span class="text-[10px] text-slate-300">ไม่มีงาน</span>
              <?php else: ?>
              <div class="flex items-center gap-1">
                <button onclick="viewAnswers(<?=$un?>,<?=$uid?>,'<?=htmlspecialchars($s['student_name'],ENT_QUOTES,'UTF-8')?>','<?=htmlspecialchars($u['unit_name'],ENT_QUOTES,'UTF-8')?>')"
                  class="px-1.5 py-0.5 rounded-full cursor-pointer hover:opacity-80 transition text-[10px] font-bold <?=$submitted>=$ex_total?'bg-emerald-50 text-emerald-600':'bg-amber-50 text-amber-600'?>" title="แบบฝึก">
                  <?=$submitted?>/<?=$ex_total?>
                </button>
                <?php if ($unlock_mode === 'sequential' && !empty($manual_unlocks[$uid . '_' . $un])): ?>
                <button onclick="toggleUnlock(<?=$un?>,<?=$uid?>,this)" class="text-violet-400 hover:text-violet-600" title="ปลดล็อกด้วยมือ — กดเพื่อยกเลิก">
                  <i class="fas fa-unlock text-[9px]"></i>
                </button>
                <?php endif; ?>
              </div>
              <?php endif; ?>

              <?php if ($post): ?>
              <span class="px-1.5 py-0.5 bg-violet-50 text-violet-600 text-[10px] font-black rounded-full" title="หลังเรียน"><?=$post['score']?>/<?=$post['total']?></span>
              <?php else: ?>
              <span class="px-1.5 py-0.5 bg-slate-50 text-slate-400 text-[10px] rounded-full" title="หลังเรียน"><?=$post_latest?$post_latest['score'].'/'.$post_latest['total']:'—'?></span>
              <?php endif; ?>
            </div>
            <?php endif; ?>
          </td>
          <?php endforeach; ?>
          <td class="px-4 py-3 text-center">
            <button onclick="resetStudent(<?=$uid?>, '<?=htmlspecialchars($s['student_name'],ENT_QUOTES,'UTF-8')?>')"
              class="w-7 h-7 bg-rose-50 text-rose-400 rounded-lg hover:bg-rose-500 hover:text-white transition-all flex items-center justify-center mx-auto"
              title="รีเซ็ตประวัติ">
              <i class="fas fa-undo text-xs"></i>
            </button>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<!-- Exercise Answer Modal -->
<div id="ansModal" class="fixed inset-0 z-50 hidden items-center justify-center p-4" style="background:rgba(0,0,0,0.4)">
  <div class="bg-white rounded-2xl shadow-2xl w-full max-w-xl max-h-[90vh] flex flex-col">
    <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100 flex-shrink-0">
      <div>
        <h3 class="font-black text-slate-800 text-sm" id="ansName"></h3>
        <p class="text-xs text-slate-400 mt-0.5" id="ansUnit"></p>
      </div>
      <button onclick="closeModal('ansModal')" class="text-slate-400 hover:text-slate-600"><i class="fas fa-times text-lg"></i></button>
    </div>
    <div id="ansContent" class="p-5 space-y-4 overflow-y-auto flex-1">
      <div class="text-center text-slate-300 py-8">กำลังโหลด...</div>
    </div>
  </div>
</div>

<script>
const SUBJECT_ID = <?=$sel_subject?:0?>;
const BASE_PATH  = '<?= $base_path ?>';

function openModal(id){const el=document.getElementById(id);el.classList.remove('hidden');el.classList.add('flex');}
function closeModal(id){const el=document.getElementById(id);el.classList.add('hidden');el.classList.remove('flex');}

async function viewAnswers(unitId, uid, name, unitName) {
  document.getElementById('ansName').textContent = name;
  document.getElementById('ansUnit').textContent = unitName;
  document.getElementById('ansContent').innerHTML = '<div class="text-center py-8 text-slate-300">กำลังโหลด...</div>';
  openModal('ansModal');
  const data = await fetch(`progress.php?ajax=answers&unit_id=${unitId}&student_uid=${uid}&subject_id=${SUBJECT_ID}`).then(r=>r.json());
  if (!data.length) {
    document.getElementById('ansContent').innerHTML = '<div class="text-center py-8 text-slate-300">ยังไม่มีคำตอบ</div>';
    return;
  }
  let html = '';
  data.forEach(d => {
    const dt       = new Date(d.submitted_at).toLocaleString('th-TH');
    const ans      = (d.answer_text||'').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/\n/g,'<br>') || '';
    const reviewed = d.reviewed_at !== null;
    const maxS     = d.max_score ? parseInt(d.max_score) : 100;
    const gradeVal = d.grade !== null ? d.grade : '';
    const fbVal    = d.feedback !== null ? d.feedback : '';
    // File display (supports JSON array of multiple files)
    let fileHtml = '';
    if (d.file_paths) {
      let fps = [];
      try { fps = JSON.parse(d.file_paths); } catch(e) { fps = [d.file_paths]; }
      if (!Array.isArray(fps)) fps = [fps];
      fileHtml = fps.map(fp => {
        const url   = BASE_PATH + '/' + fp;
        const isImg = /\.(jpg|jpeg|png|gif|webp)$/i.test(fp);
        return isImg
          ? `<img src="${url}" class="w-full rounded-xl max-h-48 object-contain bg-slate-100 mt-2">`
          : `<a href="${url}" target="_blank" class="flex items-center gap-2 mt-2 px-3 py-2 bg-blue-50 rounded-xl border border-blue-100 text-xs text-blue-700 font-bold"><i class="fas fa-file-pdf text-red-500"></i><span>ดูไฟล์ที่แนบ</span></a>`;
      }).join('');
    }
    html += `<div class="rounded-xl bg-slate-50 border border-slate-100 p-4">
      <div class="flex justify-between items-center mb-2 flex-wrap gap-2">
        <span class="text-xs font-black text-slate-700"><i class="fas fa-tasks text-teal-500 mr-1"></i>${d.exercise_title}</span>
        <div class="flex items-center gap-2">
          ${d.max_score ? `<span class="px-2 py-0.5 bg-amber-50 text-amber-600 text-[10px] font-black rounded-full border border-amber-100">${d.max_score} คะแนน</span>` : ''}
          <span class="text-xs text-slate-400">${dt}</span>
        </div>
      </div>
      ${ans ? `<div class="text-xs text-slate-600 bg-white p-3 rounded-lg border-l-4 border-teal-400 leading-relaxed">${ans}</div>` : ''}
      ${fileHtml}
      <div class="flex gap-2 items-end flex-wrap mt-3">
        <div class="flex items-end gap-1">
          <div>
            <label class="block text-[10px] font-black text-slate-400 mb-1">คะแนน</label>
            <input type="number" min="0" max="${maxS}" value="${gradeVal}" placeholder="—"
              class="w-20 border border-slate-200 rounded-lg px-2 py-1.5 text-xs font-bold text-center focus:ring-2 focus:ring-violet-400 outline-none"
              id="sg_${d.sub_id}">
          </div>
          <span class="text-xs text-slate-400 pb-2">/ ${maxS}</span>
        </div>
        <div class="flex-1 min-w-36">
          <label class="block text-[10px] font-black text-slate-400 mb-1">ความคิดเห็น</label>
          <input type="text" value="${fbVal}" placeholder="พิมพ์ความคิดเห็น..."
            class="w-full border border-slate-200 rounded-lg px-2 py-1.5 text-xs focus:ring-2 focus:ring-violet-400 outline-none"
            id="sf_${d.sub_id}">
        </div>
        <button onclick="saveExFeedback(${d.sub_id})"
          class="px-3 py-1.5 text-xs font-bold rounded-lg transition-all ${reviewed?'bg-emerald-500 text-white':'bg-violet-600 text-white hover:bg-violet-700'}"
          id="sbtn_${d.sub_id}">
          <i class="fas fa-save mr-1"></i>${reviewed?'อัปเดต':'บันทึก'}
        </button>
      </div>
    </div>`;
  });
  document.getElementById('ansContent').innerHTML = html;
}

async function saveExFeedback(subId) {
  const grade    = document.getElementById('sg_'+subId).value;
  const feedback = document.getElementById('sf_'+subId).value;
  const btn = document.getElementById('sbtn_'+subId);
  btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>';
  const fd = new FormData();
  fd.append('ajax','save_feedback'); fd.append('sub_id',subId);
  fd.append('grade',grade); fd.append('feedback',feedback);
  const res = await fetch('progress.php?subject_id='+SUBJECT_ID, {method:'POST',body:fd}).then(r=>r.json());
  if (res.ok) {
    btn.innerHTML = '<i class="fas fa-check mr-1"></i>บันทึกแล้ว';
    btn.className = btn.className.replace('bg-violet-600 hover:bg-violet-700','bg-emerald-500');
    setTimeout(()=>{ btn.disabled=false; btn.innerHTML='<i class="fas fa-save mr-1"></i>อัปเดต'; },2500);
  } else {
    btn.disabled = false; btn.innerHTML = '<i class="fas fa-save mr-1"></i>บันทึก';
  }
}

async function toggleUnlock(unitId, uid, btn) {
  btn.disabled = true;
  const fd = new FormData();
  fd.append('ajax','toggle_unlock'); fd.append('unit_id',unitId); fd.append('student_uid',uid);
  const res = await fetch('progress.php', {method:'POST',body:fd}).then(r=>r.json());
  if (res.ok) {
    location.reload();
  } else {
    btn.disabled = false;
    Swal.fire({icon:'error',title:'ผิดพลาด',confirmButtonColor:'#7C3AED'});
  }
}

function resetStudent(uid, name) {
  Swal.fire({
    icon:'warning', title:'รีเซ็ตประวัติ?',
    html:`<span class="font-bold">${name}</span><br><span class="text-sm text-slate-500">ประวัติสอบและแบบฝึกหัดทั้งหมดของวิชานี้จะถูกลบ</span>`,
    showCancelButton:true, confirmButtonColor:'#ef4444',
    cancelButtonText:'ยกเลิก', confirmButtonText:'รีเซ็ตเลย'
  }).then(async r => {
    if (!r.isConfirmed) return;
    const fd = new FormData();
    fd.append('ajax','reset_student'); fd.append('student_uid',uid); fd.append('subject_id',SUBJECT_ID);
    const res = await fetch('progress.php', {method:'POST',body:fd}).then(r=>r.json());
    if (res.ok) {
      document.getElementById('row_'+uid).style.opacity='0.4';
      Swal.fire({icon:'success',title:'รีเซ็ตแล้ว',timer:1500,showConfirmButton:false});
    }
  });
}
</script>

<?php require_once __DIR__ . '/../components/layout_end.php'; ?>
