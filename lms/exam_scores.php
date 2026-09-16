<?php
session_start();
require_once __DIR__ . '/../config.php';
if (!isset($_SESSION['llw_role'])) { header('Location: ' . $base_path . '/login.php'); exit(); }
if (!in_array($_SESSION['llw_role'], ['super_admin','att_teacher'])) { header('Location: ' . $base_path . '/login.php'); exit(); }
require_once __DIR__ . '/_helpers.php';

$pdo        = getPdo();
$is_admin   = $_SESSION['llw_role'] === 'super_admin';
$teacher_id = (int)($_SESSION['teacher_id'] ?? 0);

$subject_id = (int)($_GET['subject_id'] ?? 0);
if (!$subject_id) { header('Location: subjects.php'); exit(); }
$subject = lms_get_owned_subject($pdo, $subject_id, $is_admin, $teacher_id);
if (!$subject) { header('Location: subjects.php'); exit(); }

$type = ($_GET['type'] ?? 'final') === 'midterm' ? 'midterm' : 'final';
$table = $type === 'midterm' ? 'lms_student_midterm_exam' : 'lms_student_final_exam';
$label = $type === 'midterm' ? 'กลางภาค' : 'ปลายภาค';

$sel_class = $_GET['class'] ?? '';

$cs = $pdo->prepare("SELECT classroom FROM lms_subject_classrooms WHERE subject_id=? ORDER BY classroom");
$cs->execute([$subject_id]); $classrooms = $cs->fetchAll(PDO::FETCH_COLUMN);

$es = $pdo->prepare("SELECT * FROM lms_subject_settings WHERE subject_id=?"); $es->execute([$subject_id]); $settings = $es->fetch();
$pass_score = (int)($settings[$type.'_pass_score'] ?? 6);
$max_att    = (int)($settings[$type.'_max_attempts'] ?? 1);

$students = [];
if (!empty($classrooms)) {
    $use_classes = $sel_class !== '' ? [$sel_class] : $classrooms;
    $ph = implode(',', array_fill(0, count($use_classes), '?'));
    $q = $pdo->prepare("
        SELECT id, student_id, name AS student_name, classroom
        FROM att_students
        WHERE classroom IN ($ph) AND status='active'
          AND student_id REGEXP '^[0-9]+$'
        ORDER BY classroom, student_id
    ");
    $q->execute($use_classes);
    $students = $q->fetchAll();
}

$rows = [];
if (!empty($students)) {
    $stu_ids = array_column($students, 'id');
    $ph = implode(',', array_fill(0, count($stu_ids), '?'));
    $at = $pdo->prepare("SELECT * FROM $table WHERE subject_id=? AND student_uid IN ($ph) ORDER BY student_uid, attempt_no");
    $at->execute(array_merge([$subject_id], $stu_ids));
    $attempts_by_uid = [];
    foreach ($at->fetchAll() as $a) {
        $attempts_by_uid[$a['student_uid']][] = $a;
    }
    foreach ($students as $s) {
        $attempts = $attempts_by_uid[$s['id']] ?? [];
        $best = null;
        foreach ($attempts as $a) {
            if ($best === null || $a['score'] > $best['score']) $best = $a;
        }
        $best_passed = $best ? (($best['total'] == 0 || $best['score'] >= $pass_score) ? 1 : 0) : null;
        $rows[] = ['student' => $s, 'attempts' => $attempts, 'best' => $best, 'best_passed' => $best_passed];
    }
}

$pageTitle    = 'คะแนนสอบ' . $label;
$pageSubtitle = htmlspecialchars($subject['subject_name'], ENT_QUOTES, 'UTF-8');
$activeSystem = 'lms';
require_once __DIR__ . '/../components/layout_start.php';
?>

<div class="flex flex-wrap items-center justify-between gap-4 mb-6">
  <div class="flex items-center gap-3">
    <div class="w-10 h-10 rounded-xl flex items-center justify-center shadow-lg <?=$type==='midterm'?'bg-gradient-to-br from-indigo-500 to-purple-600':'bg-gradient-to-br from-amber-400 to-orange-500'?>">
      <i class="fas fa-list-ol text-white"></i>
    </div>
    <div>
      <h2 class="text-lg font-black text-slate-800">คะแนนสอบ<?=$label?></h2>
      <p class="text-xs text-slate-400"><?=htmlspecialchars($subject['subject_name'],ENT_QUOTES,'UTF-8')?> · ผ่านเกณฑ์ <?=$pass_score?> ข้อ · สอบได้ <?=$max_att?> ครั้ง · ใช้คะแนนสูงสุดเป็นคะแนนจริง</p>
    </div>
  </div>
  <a href="subject_dashboard.php?subject_id=<?=$subject_id?>&tab=exam"
     class="px-3 py-2 bg-slate-100 text-slate-600 text-xs font-bold rounded-xl hover:bg-slate-200 transition-all">
    <i class="fas fa-arrow-left mr-1"></i> กลับ
  </a>
</div>

<div class="bg-white rounded-2xl shadow-xl shadow-slate-100/50 border border-slate-100 p-5 mb-5">
  <form method="GET" class="flex gap-3 items-center flex-wrap">
    <input type="hidden" name="subject_id" value="<?=$subject_id?>">
    <label class="text-xs font-black text-slate-500">ประเภท:</label>
    <select name="type" onchange="this.form.submit()"
      class="border border-slate-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-amber-400 outline-none">
      <option value="midterm" <?=$type==='midterm'?'selected':''?>>กลางภาค</option>
      <option value="final" <?=$type==='final'?'selected':''?>>ปลายภาค</option>
    </select>
    <?php if (!empty($classrooms)): ?>
    <label class="text-xs font-black text-slate-500">ห้อง:</label>
    <select name="class" onchange="this.form.submit()"
      class="border border-slate-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-amber-400 outline-none">
      <option value="">-- ทุกห้อง --</option>
      <?php foreach ($classrooms as $cl): ?>
      <option value="<?=htmlspecialchars($cl,ENT_QUOTES,'UTF-8')?>" <?=$sel_class===$cl?'selected':''?>>
        <?=htmlspecialchars($cl,ENT_QUOTES,'UTF-8')?>
      </option>
      <?php endforeach; ?>
    </select>
    <?php endif; ?>
  </form>
</div>

<?php if (empty($classrooms)): ?>
<div class="bg-white rounded-2xl shadow-xl shadow-slate-100/50 border border-slate-100 p-16 text-center text-slate-300">
  <i class="fas fa-users text-5xl mb-3 block opacity-30"></i><p>วิชานี้ยังไม่ได้กำหนดห้องเรียน</p>
</div>
<?php elseif (empty($rows)): ?>
<div class="bg-white rounded-2xl shadow-xl shadow-slate-100/50 border border-slate-100 p-16 text-center text-slate-300">
  <i class="fas fa-user-slash text-5xl mb-3 block opacity-30"></i><p>ไม่พบนักเรียนในห้องที่เลือก</p>
</div>
<?php else: ?>
<div class="bg-white rounded-2xl shadow-lg overflow-hidden border border-slate-100">
  <div class="overflow-auto" style="max-height:70vh">
    <table class="w-full text-sm">
      <thead class="bg-slate-50 sticky top-0 z-10">
        <tr>
          <th class="text-left px-4 py-3 text-xs font-bold text-slate-400 uppercase tracking-wider sticky left-0 bg-slate-50">นักเรียน</th>
          <th class="text-left px-4 py-3 text-xs font-bold text-slate-400 uppercase tracking-wider">ห้อง</th>
          <?php for ($i = 1; $i <= $max_att; $i++): ?>
          <th class="text-center px-4 py-3 text-xs font-bold text-slate-400 uppercase tracking-wider">ครั้งที่ <?=$i?></th>
          <?php endfor; ?>
          <th class="text-center px-4 py-3 text-xs font-bold text-slate-400 uppercase tracking-wider">คะแนนจริง (สูงสุด)</th>
          <th class="text-center px-4 py-3 text-xs font-bold text-slate-400 uppercase tracking-wider">สถานะ</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-50">
        <?php foreach ($rows as $r): $s = $r['student']; ?>
        <tr class="hover:bg-slate-50/50">
          <td class="px-4 py-3 sticky left-0 bg-white">
            <p class="font-bold text-slate-700"><?=htmlspecialchars($s['student_name'],ENT_QUOTES,'UTF-8')?></p>
            <p class="text-[11px] text-slate-400"><?=htmlspecialchars($s['student_id'],ENT_QUOTES,'UTF-8')?></p>
          </td>
          <td class="px-4 py-3 text-slate-500"><?=htmlspecialchars($s['classroom'],ENT_QUOTES,'UTF-8')?></td>
          <?php for ($i = 1; $i <= $max_att; $i++):
            $a = null;
            foreach ($r['attempts'] as $att) { if ((int)$att['attempt_no'] === $i) { $a = $att; break; } }
          ?>
          <td class="text-center px-4 py-3">
            <?php if ($a): ?>
              <span class="font-bold <?=$a['passed']?'text-emerald-600':'text-slate-500'?>"><?=$a['score']?>/<?=$a['total']?></span>
              <?php if (!empty($a['tab_switch_count'])): ?>
              <span title="สลับแท็บ <?=$a['tab_switch_count']?> ครั้ง" class="ml-1 text-amber-500"><i class="fas fa-triangle-exclamation text-[10px]"></i></span>
              <?php endif; ?>
            <?php else: ?>
              <span class="text-slate-300">—</span>
            <?php endif; ?>
          </td>
          <?php endfor; ?>
          <td class="text-center px-4 py-3">
            <?php if ($r['best']): ?>
            <span class="font-black <?=$r['best_passed']?'text-emerald-600':'text-rose-500'?>"><?=$r['best']['score']?>/<?=$r['best']['total']?></span>
            <?php else: ?>
            <span class="text-slate-300">ยังไม่สอบ</span>
            <?php endif; ?>
          </td>
          <td class="text-center px-4 py-3">
            <?php if ($r['best_passed'] === 1): ?>
            <span class="px-3 py-1 rounded-full bg-emerald-50 text-emerald-600 text-xs font-bold">ผ่าน</span>
            <?php elseif ($r['best_passed'] === 0): ?>
            <span class="px-3 py-1 rounded-full bg-rose-50 text-rose-500 text-xs font-bold">ไม่ผ่าน</span>
            <?php else: ?>
            <span class="px-3 py-1 rounded-full bg-slate-100 text-slate-400 text-xs font-bold">ยังไม่สอบ</span>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../components/layout_end.php'; ?>
