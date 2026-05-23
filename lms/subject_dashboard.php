<?php
session_start();
require_once __DIR__ . '/../config.php';
if (!isset($_SESSION['llw_role'])) { header('Location: ' . $base_path . '/login.php'); exit(); }
if (!in_array($_SESSION['llw_role'], ['super_admin','att_teacher'])) { header('Location: ' . $base_path . '/login.php'); exit(); }

$pdo        = getPdo();
$is_admin   = $_SESSION['llw_role'] === 'super_admin';
$teacher_id = (int)($_SESSION['teacher_id'] ?? 0);

$subject_id = (int)($_GET['subject_id'] ?? 0);
if (!$subject_id) { header('Location: subjects.php'); exit(); }

$subject = $pdo->prepare("SELECT * FROM lms_subjects WHERE id=?");
$subject->execute([$subject_id]); $subject = $subject->fetch();
if (!$subject) { header('Location: subjects.php'); exit(); }

$tab = $_GET['tab'] ?? 'overview';

// ── Guard: SHOW COLUMNS ──────────────────────────────────────────
$_has_grade   = (bool)$pdo->query("SHOW COLUMNS FROM `lms_student_exercises` LIKE 'reviewed_at'")->fetch();
$_has_link    = (bool)$pdo->query("SHOW COLUMNS FROM `lms_student_exercises` LIKE 'link_url'")->fetch();
$_grade_cols  = $_has_grade ? ', se.grade, se.feedback, se.reviewed_at' : ', NULL AS grade, NULL AS feedback, NULL AS reviewed_at';
$_lk_col      = $_has_link  ? ', se.link_url' : ", '' AS link_url";

// ── Data ─────────────────────────────────────────────────────────
$classrooms = $pdo->prepare("SELECT classroom FROM lms_subject_classrooms WHERE subject_id=? ORDER BY classroom");
$classrooms->execute([$subject_id]); $classrooms = $classrooms->fetchAll(PDO::FETCH_COLUMN);

$settings = $pdo->prepare("SELECT * FROM lms_exam_settings WHERE subject_id=?");
$settings->execute([$subject_id]); $settings = $settings->fetch();

$units = $pdo->prepare("
    SELECT u.*, COUNT(DISTINCT t.id) topic_count, COUNT(DISTINCT e.id) ex_count
    FROM lms_units u
    LEFT JOIN lms_topics t ON t.unit_id = u.id
    LEFT JOIN lms_unit_exercises e ON e.unit_id = u.id
    WHERE u.subject_id=?
    GROUP BY u.id ORDER BY u.order_no
");
$units->execute([$subject_id]); $units = $units->fetchAll();

$pre_q_count  = (int)$pdo->prepare("SELECT COUNT(*) FROM lms_pre_questions  WHERE subject_id=?")->execute([$subject_id]) ? $pdo->prepare("SELECT COUNT(*) FROM lms_pre_questions WHERE subject_id=?")->execute([$subject_id]) ?: 0 : 0;
$s = $pdo->prepare("SELECT COUNT(*) FROM lms_pre_questions  WHERE subject_id=?"); $s->execute([$subject_id]); $pre_q_count  = (int)$s->fetchColumn();
$s = $pdo->prepare("SELECT COUNT(*) FROM lms_post_questions WHERE subject_id=?"); $s->execute([$subject_id]); $post_q_count = (int)$s->fetchColumn();

$enrolled_students = 0;
if (!empty($classrooms)) {
    $pl = implode(',', array_fill(0, count($classrooms), '?'));
    $s  = $pdo->prepare("SELECT COUNT(*) FROM att_students WHERE classroom IN ($pl) AND status='active' AND student_id REGEXP '^[0-9]+$'");
    $s->execute($classrooms); $enrolled_students = (int)$s->fetchColumn();
}

$pending_ex = 0;
if ($_has_grade) {
    $s = $pdo->prepare("SELECT COUNT(*) FROM lms_student_exercises WHERE subject_id=? AND reviewed_at IS NULL");
    $s->execute([$subject_id]); $pending_ex = (int)$s->fetchColumn();
}

$s = $pdo->prepare("SELECT COUNT(DISTINCT student_uid) FROM lms_student_post_exam WHERE subject_id=? AND passed=1");
$s->execute([$subject_id]); $post_passed = (int)$s->fetchColumn();

// ── Tab: ตรวจงาน ─────────────────────────────────────────────────
$sel_class   = trim($_GET['class'] ?? '');
$exercise_id = (int)($_GET['exercise_id'] ?? 0);
$exercise    = null; $submissions = [];

$exercises_all = $pdo->prepare("
    SELECT e.id, e.exercise_title, e.max_score, e.due_date, u.unit_name,
           COUNT(se.id) AS sub_count,
           SUM(CASE WHEN se.reviewed_at IS NOT NULL THEN 1 ELSE 0 END) AS reviewed_count
    FROM lms_unit_exercises e
    JOIN lms_units u ON u.id = e.unit_id
    LEFT JOIN lms_student_exercises se ON se.exercise_id = e.id AND se.subject_id = ?
    WHERE u.subject_id = ?
    GROUP BY e.id ORDER BY u.order_no, e.id
");
$exercises_all->execute([$subject_id, $subject_id]); $exercises_all = $exercises_all->fetchAll();

if ($tab === 'grade' && $exercise_id) {
    $ex_stmt = $pdo->prepare("SELECT e.*, u.unit_name FROM lms_unit_exercises e JOIN lms_units u ON u.id=e.unit_id WHERE e.id=? AND u.subject_id=?");
    $ex_stmt->execute([$exercise_id, $subject_id]); $exercise = $ex_stmt->fetch();
    if ($exercise) {
        $class_where = $sel_class ? "AND s.classroom = ?" : "";
        $q = $pdo->prepare("
            SELECT se.id AS sub_id, se.student_uid, se.answer_text, se.file_paths{$_lk_col}{$_grade_cols},
                   se.submitted_at, s.name AS student_name, s.student_id AS student_no, s.classroom
            FROM lms_student_exercises se
            JOIN att_students s ON s.id = se.student_uid
            WHERE se.exercise_id = ? {$class_where}
            ORDER BY s.classroom, se.submitted_at DESC
        ");
        $params = $sel_class ? [$exercise_id, $sel_class] : [$exercise_id];
        $q->execute($params); $submissions = $q->fetchAll();
    }
}

// ── Tab: ความคืบหน้า ──────────────────────────────────────────────
$prog_class    = trim($_GET['prog_class'] ?? '');
$prog_students = []; $prog_units = [];
if ($tab === 'progress' && $prog_class) {
    $q = $pdo->prepare("SELECT id, student_id, name AS student_name FROM att_students WHERE classroom=? AND status='active' AND student_id REGEXP '^[0-9]+$' ORDER BY student_id");
    $q->execute([$prog_class]); $prog_students = $q->fetchAll();
    $prog_units = $units;
}

// ── AJAX: save grade ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['ajax'] ?? '') === 'save') {
    header('Content-Type: application/json');
    try {
        $sub_id   = (int)$_POST['sub_id'];
        $grade    = $_POST['grade'] !== '' ? max(0, (float)$_POST['grade']) : null;
        $feedback = trim($_POST['feedback'] ?? '') ?: null;
        if ($_has_grade) {
            $pdo->prepare("UPDATE lms_student_exercises SET grade=?, feedback=?, reviewed_at=NOW() WHERE id=?")
                ->execute([$grade, $feedback, $sub_id]);
        }
        echo json_encode(['ok' => true]);
    } catch (Exception $e) { error_log($e->getMessage()); echo json_encode(['ok' => false]); }
    exit();
}

$pageTitle    = htmlspecialchars($subject['subject_name'], ENT_QUOTES, 'UTF-8');
$pageSubtitle = 'จัดการวิชา · ตรวจงาน · ความคืบหน้า';
$activeSystem = 'lms';
require_once __DIR__ . '/../components/layout_start.php';
?>

<!-- Breadcrumb + Header -->
<div class="mb-6">
  <a href="<?=$base_path?>/lms/subjects.php" class="inline-flex items-center gap-1 text-xs text-violet-600 hover:text-violet-800 mb-2">
    <i class="fas fa-arrow-left"></i> วิชาทั้งหมด
  </a>
  <div class="flex flex-wrap items-start justify-between gap-4">
    <div class="flex items-center gap-3">
      <div class="w-12 h-12 rounded-2xl flex items-center justify-center shadow-lg flex-shrink-0"
           style="background:linear-gradient(135deg,#7C3AED,#4F46E5)">
        <i class="fas fa-book text-white text-lg"></i>
      </div>
      <div>
        <h2 class="text-xl font-black text-slate-800"><?=htmlspecialchars($subject['subject_name'],ENT_QUOTES,'UTF-8')?></h2>
        <div class="flex flex-wrap gap-1 mt-1">
          <?php if ($subject['subject_code']): ?>
          <span class="px-2 py-0.5 bg-violet-100 text-violet-700 text-xs font-bold rounded-full"><?=htmlspecialchars($subject['subject_code'],ENT_QUOTES,'UTF-8')?></span>
          <?php endif; ?>
          <?php foreach ($classrooms as $cl): ?>
          <span class="px-2 py-0.5 bg-indigo-50 text-indigo-600 text-xs font-bold rounded-full"><i class="fas fa-door-open mr-1"></i><?=htmlspecialchars($cl,ENT_QUOTES,'UTF-8')?></span>
          <?php endforeach; ?>
          <?php if (empty($classrooms)): ?>
          <span class="px-2 py-0.5 bg-amber-50 text-amber-600 text-xs font-bold rounded-full"><i class="fas fa-exclamation-triangle mr-1"></i>ยังไม่มีห้องเรียน</span>
          <?php endif; ?>
        </div>
      </div>
    </div>
    <!-- Quick Actions -->
    <div class="flex flex-wrap gap-2">
      <a href="<?=$base_path?>/lms/exam_settings.php?subject_id=<?=$subject_id?>"
         class="px-3 py-1.5 bg-slate-100 text-slate-600 text-xs font-bold rounded-xl hover:bg-slate-200 transition-all">
        <i class="fas fa-cog mr-1"></i>ตั้งค่า
      </a>
      <a href="<?=$base_path?>/lms/subjects.php" onclick="sessionStorage.setItem('openEdit','<?=$subject_id?>')"
         class="px-3 py-1.5 bg-amber-100 text-amber-700 text-xs font-bold rounded-xl hover:bg-amber-200 transition-all">
        <i class="fas fa-edit mr-1"></i>แก้ไขวิชา
      </a>
    </div>
  </div>
</div>

<!-- KPI Cards -->
<div class="grid grid-cols-2 lg:grid-cols-5 gap-3 mb-6">
  <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-4 text-center">
    <div class="text-2xl font-black text-indigo-600"><?=$enrolled_students?></div>
    <div class="text-xs text-slate-400 mt-1 font-bold">นักเรียน</div>
  </div>
  <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-4 text-center">
    <div class="text-2xl font-black text-violet-600"><?=count($units)?></div>
    <div class="text-xs text-slate-400 mt-1 font-bold">หน่วยเรียน</div>
  </div>
  <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-4 text-center">
    <div class="text-2xl font-black <?=$pending_ex>0?'text-rose-500':'text-emerald-600'?>"><?=$pending_ex?></div>
    <div class="text-xs text-slate-400 mt-1 font-bold">งานรอตรวจ</div>
  </div>
  <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-4 text-center">
    <div class="text-2xl font-black text-blue-600"><?=$pre_q_count?></div>
    <div class="text-xs text-slate-400 mt-1 font-bold">ข้อก่อนเรียน</div>
  </div>
  <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-4 text-center">
    <div class="text-2xl font-black text-emerald-600"><?=$post_passed?></div>
    <div class="text-xs text-slate-400 mt-1 font-bold">ผ่านหลังเรียน</div>
  </div>
</div>

<!-- Tabs -->
<div class="flex gap-1 mb-5 flex-wrap">
  <?php
  $tabs = [
    'overview' => ['icon'=>'fas fa-tachometer-alt','label'=>'ภาพรวม'],
    'units'    => ['icon'=>'fas fa-book-open',     'label'=>'หน่วยการเรียน'],
    'grade'    => ['icon'=>'fas fa-tasks',          'label'=>'ตรวจงาน'.($pending_ex>0?' ('.$pending_ex.')':'')],
    'exam'     => ['icon'=>'fas fa-clipboard-list', 'label'=>'ข้อสอบ'],
    'progress' => ['icon'=>'fas fa-chart-line',     'label'=>'ความคืบหน้า'],
  ];
  foreach ($tabs as $k => $t):
    $active = $tab === $k;
  ?>
  <a href="subject_dashboard.php?subject_id=<?=$subject_id?>&tab=<?=$k?>"
     class="px-4 py-2 rounded-xl text-xs font-bold transition-all flex items-center gap-1.5
            <?=$active?'bg-violet-600 text-white shadow-lg shadow-violet-200':'bg-white border border-slate-200 text-slate-600 hover:bg-violet-50 hover:text-violet-700'?>">
    <i class="<?=$t['icon']?>"></i><?=$t['label']?>
  </a>
  <?php endforeach; ?>
</div>

<!-- ════════════════════════════════════════════════════════════ -->
<!-- TAB: ภาพรวม -->
<!-- ════════════════════════════════════════════════════════════ -->
<?php if ($tab === 'overview'): ?>
<div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
  <!-- Left: Quick Actions -->
  <div class="space-y-4">
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5">
      <p class="text-xs font-black text-slate-400 uppercase tracking-wider mb-3">จัดการวิชา</p>
      <div class="space-y-2">
        <a href="<?=$base_path?>/lms/units.php?subject_id=<?=$subject_id?>"
           class="flex items-center gap-3 p-3 rounded-xl hover:bg-violet-50 transition-all group">
          <div class="w-8 h-8 rounded-lg bg-orange-100 text-orange-600 flex items-center justify-center group-hover:bg-orange-200 transition-all">
            <i class="fas fa-book-open text-sm"></i>
          </div>
          <div class="flex-1">
            <p class="text-xs font-black text-slate-700">หน่วยการเรียนรู้</p>
            <p class="text-[10px] text-slate-400"><?=count($units)?> หน่วย</p>
          </div>
          <i class="fas fa-chevron-right text-slate-300 text-xs group-hover:text-violet-400 transition-all"></i>
        </a>
        <a href="<?=$base_path?>/lms/pre_exam.php?subject_id=<?=$subject_id?>"
           class="flex items-center gap-3 p-3 rounded-xl hover:bg-blue-50 transition-all group">
          <div class="w-8 h-8 rounded-lg bg-blue-100 text-blue-600 flex items-center justify-center group-hover:bg-blue-200 transition-all">
            <i class="fas fa-play-circle text-sm"></i>
          </div>
          <div class="flex-1">
            <p class="text-xs font-black text-slate-700">ข้อสอบก่อนเรียน</p>
            <p class="text-[10px] text-slate-400"><?=$pre_q_count?> ข้อ · ผ่าน ≥ <?=$settings['pre_pass_score']??6?></p>
          </div>
          <i class="fas fa-chevron-right text-slate-300 text-xs group-hover:text-blue-400 transition-all"></i>
        </a>
        <a href="<?=$base_path?>/lms/post_exam.php?subject_id=<?=$subject_id?>"
           class="flex items-center gap-3 p-3 rounded-xl hover:bg-rose-50 transition-all group">
          <div class="w-8 h-8 rounded-lg bg-rose-100 text-rose-600 flex items-center justify-center group-hover:bg-rose-200 transition-all">
            <i class="fas fa-flag-checkered text-sm"></i>
          </div>
          <div class="flex-1">
            <p class="text-xs font-black text-slate-700">ข้อสอบหลังเรียน</p>
            <p class="text-[10px] text-slate-400"><?=$post_q_count?> ข้อ · สอบได้ <?=$settings['post_max_attempts']??3?> ครั้ง</p>
          </div>
          <i class="fas fa-chevron-right text-slate-300 text-xs group-hover:text-rose-400 transition-all"></i>
        </a>
        <a href="<?=$base_path?>/lms/exam_answers.php?subject_id=<?=$subject_id?>"
           class="flex items-center gap-3 p-3 rounded-xl hover:bg-violet-50 transition-all group">
          <div class="w-8 h-8 rounded-lg bg-violet-100 text-violet-600 flex items-center justify-center group-hover:bg-violet-200 transition-all">
            <i class="fas fa-pen-fancy text-sm"></i>
          </div>
          <div class="flex-1">
            <p class="text-xs font-black text-slate-700">ตรวจข้อสอบอัตนัย</p>
            <p class="text-[10px] text-slate-400">ดูคำตอบและให้คะแนน</p>
          </div>
          <i class="fas fa-chevron-right text-slate-300 text-xs group-hover:text-violet-400 transition-all"></i>
        </a>
        <a href="<?=$base_path?>/lms/progress.php?subject_id=<?=$subject_id?>"
           class="flex items-center gap-3 p-3 rounded-xl hover:bg-amber-50 transition-all group">
          <div class="w-8 h-8 rounded-lg bg-amber-100 text-amber-600 flex items-center justify-center group-hover:bg-amber-200 transition-all">
            <i class="fas fa-chart-line text-sm"></i>
          </div>
          <div class="flex-1">
            <p class="text-xs font-black text-slate-700">รายงานความคืบหน้า</p>
            <p class="text-[10px] text-slate-400">ผลสอบรายนักเรียน</p>
          </div>
          <i class="fas fa-chevron-right text-slate-300 text-xs group-hover:text-amber-400 transition-all"></i>
        </a>
      </div>
    </div>
  </div>

  <!-- Right: Unit summary + pending -->
  <div class="lg:col-span-2 space-y-4">
    <!-- Units -->
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
      <div class="flex items-center justify-between px-5 py-3 border-b border-slate-50">
        <p class="text-xs font-black text-slate-700 flex items-center gap-2"><i class="fas fa-book-open text-orange-500"></i> หน่วยการเรียนรู้</p>
        <a href="<?=$base_path?>/lms/units.php?subject_id=<?=$subject_id?>" class="text-xs text-violet-600 hover:underline font-bold">จัดการ →</a>
      </div>
      <?php if (empty($units)): ?>
      <div class="p-8 text-center text-slate-300 text-sm">ยังไม่มีหน่วย — <a href="<?=$base_path?>/lms/units.php?subject_id=<?=$subject_id?>" class="text-violet-500 font-bold">เพิ่มหน่วยแรก</a></div>
      <?php else: ?>
      <div class="divide-y divide-slate-50">
        <?php foreach ($units as $u): ?>
        <div class="flex items-center justify-between px-5 py-3 hover:bg-slate-50/50 transition-colors">
          <div class="flex items-center gap-3">
            <span class="w-7 h-7 rounded-lg bg-orange-50 text-orange-600 text-xs font-black flex items-center justify-center flex-shrink-0"><?=$u['order_no']?></span>
            <div>
              <p class="text-sm font-bold text-slate-700"><?=htmlspecialchars($u['unit_name'],ENT_QUOTES,'UTF-8')?></p>
              <p class="text-[10px] text-slate-400"><?=$u['topic_count']?> เรื่อง · <?=$u['ex_count']?> ชิ้นงาน</p>
            </div>
          </div>
          <a href="<?=$base_path?>/lms/topics.php?unit_id=<?=$u['id']?>&subject_id=<?=$subject_id?>"
             class="px-3 py-1 bg-teal-50 text-teal-700 text-xs font-bold rounded-lg hover:bg-teal-100 transition-all">
            <i class="fas fa-folder-open mr-1"></i>เรื่อง
          </a>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>

    <!-- Pending exercises -->
    <?php if ($pending_ex > 0): ?>
    <div class="bg-rose-50 rounded-2xl border border-rose-100 shadow-sm overflow-hidden">
      <div class="flex items-center justify-between px-5 py-3 border-b border-rose-100">
        <p class="text-xs font-black text-rose-700 flex items-center gap-2">
          <i class="fas fa-exclamation-circle"></i> งานรอตรวจ <?=$pending_ex?> ชิ้น
        </p>
        <a href="subject_dashboard.php?subject_id=<?=$subject_id?>&tab=grade" class="text-xs text-rose-600 hover:underline font-bold">ตรวจทั้งหมด →</a>
      </div>
      <div class="divide-y divide-rose-100">
        <?php foreach ($exercises_all as $e):
          $pend = $e['sub_count'] - $e['reviewed_count'];
          if ($pend <= 0) continue;
        ?>
        <a href="subject_dashboard.php?subject_id=<?=$subject_id?>&tab=grade&exercise_id=<?=$e['id']?>"
           class="flex items-center justify-between px-5 py-2.5 hover:bg-rose-100/50 transition-colors">
          <div>
            <p class="text-xs font-bold text-slate-700"><?=htmlspecialchars($e['exercise_title'],ENT_QUOTES,'UTF-8')?></p>
            <p class="text-[10px] text-slate-500"><?=htmlspecialchars($e['unit_name'],ENT_QUOTES,'UTF-8')?></p>
          </div>
          <span class="px-2 py-0.5 bg-rose-500 text-white text-[10px] font-black rounded-full"><?=$pend?> รอ</span>
        </a>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>
  </div>
</div>

<!-- ════════════════════════════════════════════════════════════ -->
<!-- TAB: หน่วยการเรียน -->
<!-- ════════════════════════════════════════════════════════════ -->
<?php elseif ($tab === 'units'): ?>
<div class="flex items-center justify-between mb-4">
  <p class="text-sm font-black text-slate-600"><?=count($units)?> หน่วย</p>
  <a href="<?=$base_path?>/lms/units.php?subject_id=<?=$subject_id?>"
     class="px-4 py-2 bg-violet-600 text-white text-xs font-bold rounded-xl shadow-lg shadow-violet-200 hover:bg-violet-700 transition-all flex items-center gap-2">
    <i class="fas fa-edit"></i> จัดการหน่วย
  </a>
</div>
<?php if (empty($units)): ?>
<div class="bg-white rounded-2xl border border-slate-100 p-16 text-center text-slate-300 shadow-sm">
  <i class="fas fa-book text-5xl mb-3 block opacity-30"></i>
  <p class="font-bold">ยังไม่มีหน่วย — <a href="<?=$base_path?>/lms/units.php?subject_id=<?=$subject_id?>" class="text-violet-500">คลิกเพื่อเพิ่ม</a></p>
</div>
<?php else: ?>
<div class="space-y-3">
  <?php foreach ($units as $u):
    $exs = $pdo->prepare("SELECT * FROM lms_unit_exercises WHERE unit_id=? ORDER BY id");
    $exs->execute([$u['id']]); $exs = $exs->fetchAll();
  ?>
  <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
    <div class="flex items-center justify-between px-5 py-4 border-b border-slate-50"
         style="background:linear-gradient(135deg,#FFF8F0,#FFFDF5)">
      <div class="flex items-center gap-3">
        <span class="w-8 h-8 rounded-xl bg-orange-100 text-orange-600 text-sm font-black flex items-center justify-center"><?=$u['order_no']?></span>
        <div>
          <p class="font-black text-slate-800"><?=htmlspecialchars($u['unit_name'],ENT_QUOTES,'UTF-8')?></p>
          <p class="text-[10px] text-slate-400"><?=$u['topic_count']?> เรื่อง · <?=$u['ex_count']?> ชิ้นงาน</p>
        </div>
      </div>
      <div class="flex gap-2">
        <a href="<?=$base_path?>/lms/topics.php?unit_id=<?=$u['id']?>&subject_id=<?=$subject_id?>"
           class="px-3 py-1.5 bg-teal-500 text-white text-xs font-bold rounded-lg hover:bg-teal-600 transition-all">
          <i class="fas fa-folder-open mr-1"></i>เรื่อง/เนื้อหา
        </a>
        <a href="<?=$base_path?>/lms/units.php?subject_id=<?=$subject_id?>"
           class="px-3 py-1.5 bg-amber-400 text-white text-xs font-bold rounded-lg hover:bg-amber-500 transition-all">
          <i class="fas fa-edit"></i>
        </a>
      </div>
    </div>
    <?php if (!empty($exs)): ?>
    <div class="px-5 py-3 flex flex-wrap gap-2">
      <?php foreach ($exs as $ex): ?>
      <span class="inline-flex items-center gap-1 px-2.5 py-1 bg-emerald-50 text-emerald-700 text-xs font-bold rounded-full border border-emerald-100">
        <i class="fas fa-tasks text-[10px]"></i>
        <?=htmlspecialchars($ex['exercise_title'],ENT_QUOTES,'UTF-8')?>
        <?php if ($ex['max_score']): ?><span class="bg-amber-100 text-amber-600 px-1.5 rounded-full text-[10px]"><?=$ex['max_score']?>pt</span><?php endif; ?>
      </span>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- ════════════════════════════════════════════════════════════ -->
<!-- TAB: ตรวจงาน -->
<!-- ════════════════════════════════════════════════════════════ -->
<?php elseif ($tab === 'grade'): ?>
<div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
  <!-- Left: exercise list + class filter -->
  <div class="space-y-3">
    <?php if (!empty($classrooms)): ?>
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-4">
      <p class="text-xs font-black text-slate-400 uppercase tracking-wider mb-2">กรองห้อง</p>
      <div class="flex flex-wrap gap-1.5">
        <a href="subject_dashboard.php?subject_id=<?=$subject_id?>&tab=grade<?=$exercise_id?'&exercise_id='.$exercise_id:''?>"
           class="px-3 py-1 rounded-full text-xs font-bold <?=$sel_class===''?'bg-violet-600 text-white':'bg-slate-100 text-slate-600 hover:bg-slate-200'?> transition-all">ทุกห้อง</a>
        <?php foreach ($classrooms as $cl): ?>
        <a href="subject_dashboard.php?subject_id=<?=$subject_id?>&tab=grade&class=<?=urlencode($cl)?><?=$exercise_id?'&exercise_id='.$exercise_id:''?>"
           class="px-3 py-1 rounded-full text-xs font-bold <?=$sel_class===$cl?'bg-violet-600 text-white':'bg-slate-100 text-slate-600 hover:bg-slate-200'?> transition-all">
          <?=htmlspecialchars($cl,ENT_QUOTES,'UTF-8')?>
        </a>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-3">
      <p class="text-xs font-black text-slate-400 uppercase tracking-wider mb-2">ชิ้นงาน</p>
      <?php if (empty($exercises_all)): ?>
      <p class="text-xs text-slate-400 text-center py-4">ยังไม่มีชิ้นงาน</p>
      <?php else: ?>
      <div class="space-y-1">
        <?php foreach ($exercises_all as $e):
          $pend = $e['sub_count'] - $e['reviewed_count'];
        ?>
        <a href="subject_dashboard.php?subject_id=<?=$subject_id?>&tab=grade&exercise_id=<?=$e['id']?><?=$sel_class?'&class='.urlencode($sel_class):''?>"
           class="flex items-center justify-between px-3 py-2.5 rounded-xl transition-all <?=$exercise_id===$e['id']?'bg-violet-600 text-white':'border border-slate-100 text-slate-700 hover:bg-slate-50'?>">
          <div class="flex-1 min-w-0 mr-2">
            <p class="text-xs font-black truncate"><?=htmlspecialchars($e['exercise_title'],ENT_QUOTES,'UTF-8')?></p>
            <p class="text-[10px] <?=$exercise_id===$e['id']?'opacity-70':'text-slate-400'?> truncate"><?=htmlspecialchars($e['unit_name'],ENT_QUOTES,'UTF-8')?></p>
          </div>
          <?php if ($pend > 0): ?>
          <span class="px-1.5 py-0.5 rounded-full text-[10px] font-black flex-shrink-0 <?=$exercise_id===$e['id']?'bg-white/30 text-white':'bg-amber-100 text-amber-700'?>">
            <?=$pend?> รอ
          </span>
          <?php endif; ?>
        </a>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Right: submissions -->
  <div class="lg:col-span-2">
    <?php if (!$exercise_id || !$exercise): ?>
    <div class="bg-white rounded-2xl border border-slate-100 p-16 text-center text-slate-300 shadow-sm">
      <i class="fas fa-list-ul text-5xl mb-3 block opacity-30"></i>
      <p class="font-bold">เลือกชิ้นงานจากรายการซ้ายมือ</p>
    </div>
    <?php else: ?>
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-4 mb-4">
      <div class="flex items-center justify-between flex-wrap gap-2">
        <div>
          <h3 class="font-black text-slate-800"><?=htmlspecialchars($exercise['exercise_title'],ENT_QUOTES,'UTF-8')?></h3>
          <p class="text-xs text-slate-400"><?=htmlspecialchars($exercise['unit_name'],ENT_QUOTES,'UTF-8')?></p>
        </div>
        <div class="flex gap-2 flex-wrap">
          <?php if ($sel_class): ?>
          <span class="px-3 py-1 bg-indigo-50 text-indigo-700 text-xs font-black rounded-full border border-indigo-100"><?=htmlspecialchars($sel_class,ENT_QUOTES,'UTF-8')?></span>
          <?php endif; ?>
          <?php if ($exercise['max_score']): ?>
          <span class="px-3 py-1 bg-amber-50 text-amber-700 text-xs font-black rounded-full border border-amber-100"><?=$exercise['max_score']?> คะแนน</span>
          <?php endif; ?>
          <span class="px-3 py-1 bg-violet-50 text-violet-700 text-xs font-black rounded-full border border-violet-100"><?=count($submissions)?> งานที่ส่ง</span>
        </div>
      </div>
    </div>

    <?php if (empty($submissions)): ?>
    <div class="bg-white rounded-2xl border border-slate-100 p-12 text-center text-slate-300 shadow-sm">
      <i class="fas fa-inbox text-4xl mb-2 block"></i>
      <p class="font-bold text-sm">ยังไม่มีนักเรียนส่งงาน</p>
    </div>
    <?php else: ?>
    <div class="space-y-3" id="submissionList">
      <?php foreach ($submissions as $s):
        $sub_files = [];
        if (!empty($s['file_paths'])) {
            $sub_files = json_decode($s['file_paths'], true) ?: [$s['file_paths']];
        }
        $reviewed = !empty($s['reviewed_at']);
      ?>
      <div class="bg-white rounded-2xl border <?=$reviewed?'border-emerald-200':'border-amber-200'?> shadow-sm overflow-hidden">
        <div class="flex items-center justify-between px-4 py-3 <?=$reviewed?'bg-emerald-50':'bg-amber-50'?> border-b <?=$reviewed?'border-emerald-100':'border-amber-100'?>">
          <div class="flex items-center gap-2">
            <div class="w-7 h-7 rounded-lg bg-white flex items-center justify-center border <?=$reviewed?'border-emerald-200':'border-amber-200'?>">
              <i class="fas fa-user text-xs <?=$reviewed?'text-emerald-500':'text-amber-500'?>"></i>
            </div>
            <div>
              <p class="text-sm font-black text-slate-800"><?=htmlspecialchars($s['student_name'],ENT_QUOTES,'UTF-8')?></p>
              <p class="text-[10px] text-slate-400"><?=htmlspecialchars($s['student_no'],ENT_QUOTES,'UTF-8')?> · <?=htmlspecialchars($s['classroom'],ENT_QUOTES,'UTF-8')?></p>
            </div>
          </div>
          <div class="text-right">
            <?php if ($reviewed): ?>
            <span class="px-2 py-0.5 bg-emerald-100 text-emerald-700 text-[10px] font-black rounded-full"><i class="fas fa-check mr-0.5"></i>ตรวจแล้ว</span>
            <?php if ($s['grade'] !== null): ?>
            <p class="text-xs font-black text-emerald-700 mt-0.5"><?=$s['grade']?><?=$exercise['max_score']?' / '.$exercise['max_score']:''?> คะแนน</p>
            <?php endif; ?>
            <?php else: ?>
            <span class="px-2 py-0.5 bg-amber-100 text-amber-700 text-[10px] font-black rounded-full">รอตรวจ</span>
            <?php endif; ?>
            <p class="text-[10px] text-slate-400 mt-0.5"><?=date('d/m H:i',strtotime($s['submitted_at']))?></p>
          </div>
        </div>
        <div class="px-4 py-3 space-y-2">
          <?php if (!empty($sub_files)): ?>
          <div class="flex flex-wrap gap-2">
            <?php foreach ($sub_files as $fp):
              $fext  = strtolower(pathinfo($fp, PATHINFO_EXTENSION));
              $is_img = in_array($fext, ['jpg','jpeg','png','gif','webp']);
              $is_vid = in_array($fext, ['mp4','mov','avi','3gp','webm']);
            ?>
            <?php if ($is_img): ?>
            <a href="<?=$base_path.'/'.htmlspecialchars($fp,ENT_QUOTES,'UTF-8')?>" target="_blank">
              <img src="<?=$base_path.'/'.htmlspecialchars($fp,ENT_QUOTES,'UTF-8')?>" class="h-28 w-auto rounded-xl border border-slate-200 object-cover hover:opacity-80 transition-all" alt="">
            </a>
            <?php elseif ($is_vid): ?>
            <video src="<?=$base_path.'/'.htmlspecialchars($fp,ENT_QUOTES,'UTF-8')?>" controls class="w-full rounded-xl border border-slate-200 max-h-40"></video>
            <?php else: ?>
            <a href="<?=$base_path.'/'.htmlspecialchars($fp,ENT_QUOTES,'UTF-8')?>" target="_blank"
               class="flex items-center gap-2 px-3 py-2 bg-red-50 border border-red-100 rounded-xl text-xs text-red-700 font-bold hover:bg-red-100 transition-all">
              <i class="fas fa-file-pdf text-base"></i><span>ดูไฟล์</span>
            </a>
            <?php endif; ?>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>
          <?php if (!empty($s['link_url'])): ?>
          <a href="<?=htmlspecialchars($s['link_url'],ENT_QUOTES,'UTF-8')?>" target="_blank" rel="noopener"
             class="flex items-center gap-2 px-3 py-2 bg-violet-50 border border-violet-100 rounded-xl text-xs text-violet-700 font-bold hover:bg-violet-100 transition-all">
            <i class="fas fa-link text-base flex-shrink-0"></i>
            <span class="truncate"><?=htmlspecialchars($s['link_url'],ENT_QUOTES,'UTF-8')?></span>
            <i class="fas fa-external-link-alt text-xs ml-auto flex-shrink-0"></i>
          </a>
          <?php endif; ?>
          <?php if (!empty($s['answer_text'])): ?>
          <div class="bg-slate-50 rounded-xl px-3 py-2 border border-slate-100">
            <p class="text-[10px] font-black text-slate-400 mb-1">คำตอบ</p>
            <p class="text-xs text-slate-700 whitespace-pre-wrap"><?=htmlspecialchars($s['answer_text'],ENT_QUOTES,'UTF-8')?></p>
          </div>
          <?php endif; ?>
          <div class="flex items-center gap-2 flex-wrap">
            <input type="number" min="0" max="<?=$exercise['max_score']??100?>" value="<?=$s['grade']??''?>" placeholder="—"
              class="w-18 border border-slate-200 rounded-xl px-2 py-1.5 text-xs font-bold text-center focus:ring-2 focus:ring-violet-400 outline-none" style="width:4.5rem"
              id="g_<?=$s['sub_id']?>">
            <?php if ($exercise['max_score']): ?><span class="text-xs text-slate-400">/ <?=$exercise['max_score']?></span><?php endif; ?>
            <input type="text" value="<?=htmlspecialchars($s['feedback']??'',ENT_QUOTES,'UTF-8')?>" placeholder="ความคิดเห็น..."
              class="flex-1 border border-slate-200 rounded-xl px-2 py-1.5 text-xs focus:ring-2 focus:ring-violet-400 outline-none min-w-0"
              id="f_<?=$s['sub_id']?>">
            <button onclick="saveFeedback(<?=$s['sub_id']?>)"
              class="px-3 py-1.5 text-xs font-bold rounded-xl transition-all flex-shrink-0 <?=$reviewed?'bg-emerald-500 hover:bg-emerald-600':'bg-violet-600 hover:bg-violet-700'?> text-white"
              id="btn_<?=$s['sub_id']?>">
              <i class="fas fa-save mr-1"></i><?=$reviewed?'อัปเดต':'บันทึก'?>
            </button>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <?php endif; ?>
  </div>
</div>

<!-- ════════════════════════════════════════════════════════════ -->
<!-- TAB: ข้อสอบ -->
<!-- ════════════════════════════════════════════════════════════ -->
<?php elseif ($tab === 'exam'): ?>
<div class="grid grid-cols-1 md:grid-cols-2 gap-5">
  <!-- Pre exam -->
  <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
    <div class="px-5 py-4 border-b border-slate-50 flex items-center justify-between"
         style="background:linear-gradient(135deg,#EFF6FF,#F0F9FF)">
      <div class="flex items-center gap-3">
        <div class="w-9 h-9 rounded-xl bg-blue-100 text-blue-600 flex items-center justify-center">
          <i class="fas fa-play-circle"></i>
        </div>
        <div>
          <p class="font-black text-slate-800 text-sm">ข้อสอบก่อนเรียน</p>
          <p class="text-[10px] text-slate-400">ทดสอบก่อนเข้าเรียนบทเรียน</p>
        </div>
      </div>
      <a href="<?=$base_path?>/lms/pre_exam.php?subject_id=<?=$subject_id?>"
         class="px-3 py-1.5 bg-blue-500 text-white text-xs font-bold rounded-xl hover:bg-blue-600 transition-all">
        <i class="fas fa-edit mr-1"></i>จัดการ
      </a>
    </div>
    <div class="p-5 space-y-3">
      <div class="flex justify-between items-center">
        <span class="text-xs text-slate-500">จำนวนข้อ</span>
        <span class="font-black text-blue-600"><?=$pre_q_count?> ข้อ</span>
      </div>
      <div class="flex justify-between items-center">
        <span class="text-xs text-slate-500">เกณฑ์ผ่าน</span>
        <span class="font-black text-slate-700">≥ <?=$settings['pre_pass_score']??6?> คะแนน</span>
      </div>
      <?php if ($pre_q_count === 0): ?>
      <div class="flex items-center gap-2 px-3 py-2 bg-amber-50 border border-amber-100 rounded-xl text-xs text-amber-700 font-bold">
        <i class="fas fa-exclamation-triangle"></i> ยังไม่มีข้อสอบ — นักเรียนจะผ่านอัตโนมัติ
      </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Post exam -->
  <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
    <div class="px-5 py-4 border-b border-slate-50 flex items-center justify-between"
         style="background:linear-gradient(135deg,#FFF1F2,#FFF7F7)">
      <div class="flex items-center gap-3">
        <div class="w-9 h-9 rounded-xl bg-rose-100 text-rose-600 flex items-center justify-center">
          <i class="fas fa-flag-checkered"></i>
        </div>
        <div>
          <p class="font-black text-slate-800 text-sm">ข้อสอบหลังเรียน</p>
          <p class="text-[10px] text-slate-400">ทดสอบหลังเรียนครบทุกหน่วย</p>
        </div>
      </div>
      <a href="<?=$base_path?>/lms/post_exam.php?subject_id=<?=$subject_id?>"
         class="px-3 py-1.5 bg-rose-500 text-white text-xs font-bold rounded-xl hover:bg-rose-600 transition-all">
        <i class="fas fa-edit mr-1"></i>จัดการ
      </a>
    </div>
    <div class="p-5 space-y-3">
      <div class="flex justify-between items-center">
        <span class="text-xs text-slate-500">จำนวนข้อ</span>
        <span class="font-black text-rose-600"><?=$post_q_count?> ข้อ</span>
      </div>
      <div class="flex justify-between items-center">
        <span class="text-xs text-slate-500">เกณฑ์ผ่าน</span>
        <span class="font-black text-slate-700">≥ <?=$settings['post_pass_score']??6?> คะแนน</span>
      </div>
      <div class="flex justify-between items-center">
        <span class="text-xs text-slate-500">สอบได้สูงสุด</span>
        <span class="font-black text-slate-700"><?=$settings['post_max_attempts']??3?> ครั้ง</span>
      </div>
      <div class="flex justify-between items-center">
        <span class="text-xs text-slate-500">ผ่านแล้ว</span>
        <span class="font-black text-emerald-600"><?=$post_passed?> คน</span>
      </div>
    </div>
  </div>

  <!-- Exam settings link -->
  <div class="md:col-span-2">
    <a href="<?=$base_path?>/lms/exam_settings.php?subject_id=<?=$subject_id?>"
       class="flex items-center gap-4 px-5 py-4 bg-white rounded-2xl border border-slate-100 shadow-sm hover:bg-slate-50 transition-all group">
      <div class="w-10 h-10 rounded-xl bg-slate-100 text-slate-500 flex items-center justify-center group-hover:bg-violet-100 group-hover:text-violet-600 transition-all">
        <i class="fas fa-cog"></i>
      </div>
      <div class="flex-1">
        <p class="font-black text-slate-700">ตั้งค่าข้อสอบ</p>
        <p class="text-xs text-slate-400">แก้ไขเกณฑ์ผ่าน จำนวนครั้งที่สอบได้ ช่วงเวลาเปิด-ปิดข้อสอบ</p>
      </div>
      <i class="fas fa-chevron-right text-slate-300 group-hover:text-violet-500 transition-all"></i>
    </a>
  </div>
</div>

<!-- ════════════════════════════════════════════════════════════ -->
<!-- TAB: ความคืบหน้า -->
<!-- ════════════════════════════════════════════════════════════ -->
<?php elseif ($tab === 'progress'): ?>
<div class="flex flex-wrap items-center gap-3 mb-5">
  <span class="text-xs font-black text-slate-500">เลือกห้อง:</span>
  <a href="subject_dashboard.php?subject_id=<?=$subject_id?>&tab=progress"
     class="px-3 py-1.5 rounded-xl text-xs font-bold transition-all <?=$prog_class===''?'bg-violet-600 text-white':'bg-white border border-slate-200 text-slate-600 hover:bg-slate-50'?>">
    ทุกห้อง
  </a>
  <?php foreach ($classrooms as $cl): ?>
  <a href="subject_dashboard.php?subject_id=<?=$subject_id?>&tab=progress&prog_class=<?=urlencode($cl)?>"
     class="px-3 py-1.5 rounded-xl text-xs font-bold transition-all <?=$prog_class===$cl?'bg-violet-600 text-white':'bg-white border border-slate-200 text-slate-600 hover:bg-slate-50'?>">
    <?=htmlspecialchars($cl,ENT_QUOTES,'UTF-8')?>
  </a>
  <?php endforeach; ?>
  <a href="<?=$base_path?>/lms/progress.php?subject_id=<?=$subject_id?><?=$prog_class?'&class='.urlencode($prog_class):''?>"
     class="ml-auto px-3 py-1.5 bg-amber-50 text-amber-700 border border-amber-100 text-xs font-bold rounded-xl hover:bg-amber-100 transition-all">
    <i class="fas fa-external-link-alt mr-1"></i>เปิดหน้าเต็ม
  </a>
</div>

<?php if (!$prog_class): ?>
<div class="bg-white rounded-2xl border border-slate-100 p-16 text-center text-slate-300 shadow-sm">
  <i class="fas fa-users text-5xl mb-3 block opacity-30"></i>
  <p class="font-bold">เลือกห้องเรียนเพื่อดูความคืบหน้า</p>
</div>
<?php elseif (empty($prog_students)): ?>
<div class="bg-white rounded-2xl border border-slate-100 p-12 text-center text-slate-300 shadow-sm">
  <p class="font-bold text-sm">ไม่มีนักเรียนในห้องนี้</p>
</div>
<?php else: ?>
<?php
  $prog_settings = $settings;
  $pre_pass  = $prog_settings['pre_pass_score']  ?? 6;
  $post_pass = $prog_settings['post_pass_score'] ?? 6;
?>
<div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
  <div class="overflow-x-auto">
    <table class="w-full text-xs">
      <thead class="bg-slate-50 text-slate-400 font-black uppercase tracking-wider">
        <tr>
          <th class="px-4 py-3 w-8 text-center">#</th>
          <th class="px-4 py-3 text-left">ชื่อ–สกุล</th>
          <th class="px-4 py-3 text-center">ก่อนเรียน</th>
          <?php foreach ($prog_units as $u): ?>
          <th class="px-3 py-3 text-center" style="min-width:72px"><?=htmlspecialchars(mb_substr($u['unit_name'],0,8),ENT_QUOTES,'UTF-8')?></th>
          <?php endforeach; ?>
          <th class="px-4 py-3 text-center">หลังเรียน<br><span class="font-normal text-[10px]">≥<?=$post_pass?></span></th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-50">
        <?php foreach ($prog_students as $i => $st):
          $uid = $st['id'];
          $pre  = $pdo->prepare("SELECT score,total FROM lms_student_pre_exam  WHERE student_uid=? AND subject_id=? AND passed=1 ORDER BY taken_at DESC LIMIT 1"); $pre->execute([$uid,$subject_id]); $pre=$pre->fetch();
          $pre_l= $pdo->prepare("SELECT score,total FROM lms_student_pre_exam  WHERE student_uid=? AND subject_id=? ORDER BY taken_at DESC LIMIT 1"); $pre_l->execute([$uid,$subject_id]); $pre_l=$pre_l->fetch();
          $post = $pdo->prepare("SELECT score,total FROM lms_student_post_exam WHERE student_uid=? AND subject_id=? AND passed=1 ORDER BY taken_at DESC LIMIT 1"); $post->execute([$uid,$subject_id]); $post=$post->fetch();
          $post_l=$pdo->prepare("SELECT score,total FROM lms_student_post_exam WHERE student_uid=? AND subject_id=? ORDER BY taken_at DESC LIMIT 1"); $post_l->execute([$uid,$subject_id]); $post_l=$post_l->fetch();
        ?>
        <tr class="hover:bg-slate-50/50 transition-colors">
          <td class="px-4 py-2.5 text-center text-slate-400"><?=$i+1?></td>
          <td class="px-4 py-2.5">
            <p class="font-bold text-slate-700"><?=htmlspecialchars($st['student_name'],ENT_QUOTES,'UTF-8')?></p>
            <p class="text-[10px] text-slate-400"><?=htmlspecialchars($st['student_id'],ENT_QUOTES,'UTF-8')?></p>
          </td>
          <td class="px-4 py-2.5 text-center">
            <?php if ($pre): ?>
            <span class="px-2 py-0.5 bg-emerald-50 text-emerald-600 font-black rounded-full"><i class="fas fa-check-circle mr-0.5"></i><?=$pre['score']?>/<?=$pre['total']?></span>
            <?php else: ?>
            <span class="px-2 py-0.5 bg-slate-50 text-slate-400 rounded-full"><?=$pre_l?$pre_l['score'].'/'.$pre_l['total']:'—'?></span>
            <?php endif; ?>
          </td>
          <?php foreach ($prog_units as $u):
            $exs_c = $pdo->prepare("SELECT COUNT(*) FROM lms_unit_exercises WHERE unit_id=?"); $exs_c->execute([$u['id']]); $ex_total=(int)$exs_c->fetchColumn();
            $sub_c = $pdo->prepare("SELECT COUNT(*) FROM lms_student_exercises WHERE student_uid=? AND unit_id=? AND subject_id=?"); $sub_c->execute([$uid,$u['id'],$subject_id]); $submitted=(int)$sub_c->fetchColumn();
          ?>
          <td class="px-3 py-2.5 text-center">
            <?php if ($ex_total===0): ?>
            <span class="text-slate-300">—</span>
            <?php else: ?>
            <span class="px-2 py-0.5 rounded-full font-bold <?=$submitted>=$ex_total?'bg-emerald-50 text-emerald-600':'bg-amber-50 text-amber-600'?>"><?=$submitted?>/<?=$ex_total?></span>
            <?php endif; ?>
          </td>
          <?php endforeach; ?>
          <td class="px-4 py-2.5 text-center">
            <?php if ($post): ?>
            <span class="px-2 py-0.5 bg-emerald-50 text-emerald-600 font-black rounded-full"><i class="fas fa-check-circle mr-0.5"></i><?=$post['score']?>/<?=$post['total']?></span>
            <?php else: ?>
            <span class="px-2 py-0.5 bg-slate-50 text-slate-400 rounded-full"><?=$post_l?$post_l['score'].'/'.$post_l['total']:'—'?></span>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>
<?php endif; ?>

<script>
const SUBJECT_ID = <?=$subject_id?>;
async function saveFeedback(subId) {
  const grade    = document.getElementById('g_' + subId).value;
  const feedback = document.getElementById('f_' + subId).value;
  const btn      = document.getElementById('btn_' + subId);
  btn.disabled = true;
  btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
  const fd = new FormData();
  fd.append('ajax','save'); fd.append('sub_id',subId);
  fd.append('grade',grade); fd.append('feedback',feedback);
  const res = await fetch('subject_dashboard.php?subject_id='+SUBJECT_ID+'&tab=grade', {method:'POST',body:fd}).then(r=>r.json());
  if (res.ok) {
    btn.innerHTML = '<i class="fas fa-check mr-1"></i>บันทึกแล้ว';
    btn.className = btn.className.replace(/bg-violet-600 hover:bg-violet-700|bg-emerald-500 hover:bg-emerald-600/g,'') + ' bg-emerald-500';
    setTimeout(()=>{ btn.disabled=false; btn.innerHTML='<i class="fas fa-save mr-1"></i>อัปเดต'; },2500);
  } else {
    btn.disabled=false; btn.innerHTML='<i class="fas fa-save mr-1"></i>ลองใหม่';
    Swal.fire({icon:'error',title:'เกิดข้อผิดพลาด',confirmButtonColor:'#7C3AED'});
  }
}
</script>

<?php require_once __DIR__ . '/../components/layout_end.php'; ?>
