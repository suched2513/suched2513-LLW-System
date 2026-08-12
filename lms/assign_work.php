<?php
session_start();
require_once __DIR__ . '/../config.php';
if (!isset($_SESSION['llw_role'])) { header('Location: ' . $base_path . '/login.php'); exit(); }
if (!in_array($_SESSION['llw_role'], ['super_admin','att_teacher'])) { header('Location: ' . $base_path . '/login.php'); exit(); }
require_once __DIR__ . '/_helpers.php';

$pdo        = getPdo();
$teacher_id = (int)($_SESSION['teacher_id'] ?? 0);
$is_admin   = $_SESSION['llw_role'] === 'super_admin';

// ── POST: create exercise ────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $subject_id = (int)$_POST['subject_id'];
        $unit_id    = (int)($_POST['unit_id'] ?? 0);
        $new_unit   = trim($_POST['new_unit_name'] ?? '');
        $classrooms = array_values(array_filter(array_map('trim', $_POST['classrooms'] ?? [])));
        $title      = trim($_POST['exercise_title'] ?? '');
        $desc       = trim($_POST['description'] ?? '') ?: null;
        $score      = ($_POST['max_score'] ?? '') !== '' ? max(1, (int)$_POST['max_score']) : null;
        $due        = !empty($_POST['due_date']) ? $_POST['due_date'] : null;

        if (!$title)      throw new Exception('กรุณาระบุชื่อใบงาน');
        if (!$subject_id) throw new Exception('กรุณาเลือกวิชา');
        if (empty($classrooms)) throw new Exception('กรุณาเลือกห้องเรียนอย่างน้อย 1 ห้อง');
        if (!lms_get_owned_subject($pdo, $subject_id, $is_admin, $teacher_id)) throw new Exception('ไม่มีสิทธิ์ใช้งานวิชานี้');
        if ($unit_id && !lms_get_owned_unit($pdo, $unit_id, $is_admin, $teacher_id)) throw new Exception('ไม่มีสิทธิ์ใช้งานหน่วยนี้');

        $pdo->beginTransaction();

        if (!$unit_id) {
            if (!$new_unit) throw new Exception('กรุณาเลือกหรือสร้างหน่วยการเรียน');
            $r = $pdo->prepare("SELECT COALESCE(MAX(order_no),0)+1 FROM lms_units WHERE subject_id=?");
            $r->execute([$subject_id]); $next_order = (int)$r->fetchColumn();
            $pdo->prepare("INSERT INTO lms_units (subject_id, order_no, unit_name) VALUES (?,?,?)")
                ->execute([$subject_id, $next_order, $new_unit]);
            $unit_id = (int)$pdo->lastInsertId();
        }

        $pdo->prepare("INSERT INTO lms_unit_exercises (unit_id, exercise_title, description, max_score, due_date) VALUES (?,?,?,?,?)")
            ->execute([$unit_id, $title, $desc, $score, $due]);
        $ex_id = (int)$pdo->lastInsertId();

        $all_r = $pdo->prepare("SELECT classroom FROM lms_subject_classrooms WHERE subject_id=? ORDER BY classroom");
        $all_r->execute([$subject_id]); $all_cl = $all_r->fetchAll(PDO::FETCH_COLUMN);
        $sel = $classrooms; sort($sel); $all_sorted = $all_cl; sort($all_sorted);
        if ($sel !== $all_sorted) {
            $_cls_tbl = (bool)$pdo->query("SHOW TABLES LIKE 'lms_exercise_classrooms'")->fetch();
            if ($_cls_tbl) {
                $stmt = $pdo->prepare("INSERT IGNORE INTO lms_exercise_classrooms (exercise_id, classroom) VALUES (?,?)");
                foreach ($classrooms as $cl) $stmt->execute([$ex_id, $cl]);
            }
        }

        $pdo->commit();
        $cls_label = implode(', ', $classrooms);
        header('Location: assign_work.php?subject_id=' . $subject_id . '&done=1&ex=' . urlencode($title) . '&cls=' . urlencode($cls_label));
        exit();
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        header('Location: assign_work.php?subject_id=' . (int)($_POST['subject_id'] ?? 0) . '&err=' . urlencode($e->getMessage()));
        exit();
    }
}

// ── Load subjects ────────────────────────────────────────────────
if ($is_admin) {
    $subjects = $pdo->query("SELECT * FROM lms_subjects ORDER BY subject_name")->fetchAll();
} else {
    $st = $pdo->prepare("SELECT * FROM lms_subjects WHERE teacher_id=? OR teacher_id IS NULL ORDER BY subject_name");
    $st->execute([$teacher_id]); $subjects = $st->fetchAll();
}

$sel_subject_id = (int)($_GET['subject_id'] ?? ($subjects[0]['id'] ?? 0));
$sel_subject    = null;
$sel_classrooms = [];
$sel_units      = [];
$recent_ex      = [];
$enrolled_count = 0;

if ($sel_subject_id) {
    foreach ($subjects as $s) {
        if ($s['id'] == $sel_subject_id) { $sel_subject = $s; break; }
    }
    $r = $pdo->prepare("SELECT classroom FROM lms_subject_classrooms WHERE subject_id=? ORDER BY classroom");
    $r->execute([$sel_subject_id]); $sel_classrooms = $r->fetchAll(PDO::FETCH_COLUMN);

    $r = $pdo->prepare("SELECT id, unit_name, order_no FROM lms_units WHERE subject_id=? AND deleted_at IS NULL ORDER BY order_no");
    $r->execute([$sel_subject_id]); $sel_units = $r->fetchAll();

    if (!empty($sel_classrooms)) {
        $pl = implode(',', array_fill(0, count($sel_classrooms), '?'));
        $ec = $pdo->prepare("SELECT COUNT(*) FROM att_students WHERE classroom IN ($pl) AND status='active' AND student_id REGEXP '^[0-9]+$'");
        $ec->execute($sel_classrooms); $enrolled_count = (int)$ec->fetchColumn();
    }

    try {
        $r = $pdo->prepare("
            SELECT e.id, e.exercise_title, e.max_score, e.due_date,
                   u.unit_name, u.id AS unit_id,
                   GROUP_CONCAT(ec.classroom ORDER BY ec.classroom SEPARATOR ', ') AS ex_classes,
                   COUNT(DISTINCT se.id) AS sub_count
            FROM lms_unit_exercises e
            JOIN lms_units u ON u.id = e.unit_id
            LEFT JOIN lms_exercise_classrooms ec ON ec.exercise_id = e.id
            LEFT JOIN lms_student_exercises se ON se.exercise_id = e.id
            WHERE u.subject_id = ? AND e.deleted_at IS NULL AND u.deleted_at IS NULL
            GROUP BY e.id
            ORDER BY u.order_no, e.id DESC
        ");
        $r->execute([$sel_subject_id]); $recent_ex = $r->fetchAll();
    } catch (Exception $e) { $recent_ex = []; }
}

// Group classrooms by grade
$grade_groups = [];
foreach ($sel_classrooms as $cl) {
    $grade = strstr($cl, '/', true);
    if ($grade === false) $grade = $cl;
    $grade_groups[$grade][] = $cl;
}

$pageTitle    = 'สั่งงาน';
$pageSubtitle = 'มอบหมายใบงานให้นักเรียน';
$activeSystem = 'lms';
require_once __DIR__ . '/../components/layout_start.php';
?>

<?php if (!$sel_subject || empty($subjects)): ?>
<!-- ── No subject selected: card grid ───────────────────────── -->
<div class="mb-6">
  <h2 class="text-lg font-black text-slate-800">สั่งงาน</h2>
  <p class="text-xs text-slate-400 mt-0.5">เลือกวิชาที่ต้องการสั่งงาน</p>
</div>
<?php if (empty($subjects)): ?>
<div class="bg-white rounded-2xl border border-slate-100 p-16 text-center shadow-sm">
  <i class="bi bi-book text-5xl text-slate-200 block mb-3"></i>
  <p class="font-bold text-slate-500">ยังไม่มีวิชา</p>
  <a href="subjects.php" class="mt-3 inline-flex items-center gap-1 px-4 py-2 bg-emerald-600 text-white text-xs font-bold rounded-xl hover:bg-emerald-700 transition-all">
    <i class="bi bi-plus-circle-fill"></i> สร้างวิชาแรก
  </a>
</div>
<?php else: ?>
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
  <?php foreach ($subjects as $s): ?>
  <a href="assign_work.php?subject_id=<?=$s['id']?>"
     class="group bg-white rounded-2xl border border-slate-100 shadow-sm p-5 hover:shadow-lg hover:border-emerald-200 transition-all">
    <div class="flex items-center gap-3 mb-3">
      <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-emerald-500 to-teal-600 flex items-center justify-center shadow-md flex-shrink-0">
        <i class="bi bi-book-half text-white"></i>
      </div>
      <div class="flex-1 min-w-0">
        <p class="font-black text-slate-800 text-sm truncate"><?=htmlspecialchars($s['subject_name'],ENT_QUOTES,'UTF-8')?></p>
        <?php if ($s['subject_code']): ?>
        <p class="text-[10px] text-slate-400"><?=htmlspecialchars($s['subject_code'],ENT_QUOTES,'UTF-8')?></p>
        <?php endif; ?>
      </div>
    </div>
    <div class="flex items-center justify-between">
      <span class="text-xs text-slate-400">เข้าสั่งงาน</span>
      <i class="bi bi-arrow-right-circle text-emerald-400 group-hover:text-emerald-600 transition-all"></i>
    </div>
  </a>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<?php else: ?>
<!-- ── Subject selected ──────────────────────────────────────── -->

<?php if (isset($_GET['done'])): ?>
<div class="mb-4 bg-emerald-50 border border-emerald-200 rounded-2xl px-5 py-3 flex items-center gap-3">
  <i class="bi bi-check-circle-fill text-emerald-500 text-lg flex-shrink-0"></i>
  <div>
    <p class="font-black text-emerald-700 text-sm">สั่งงานสำเร็จ!</p>
    <p class="text-xs text-emerald-600">"<?=htmlspecialchars($_GET['ex']??'',ENT_QUOTES,'UTF-8')?>" → <?=htmlspecialchars($_GET['cls']??'',ENT_QUOTES,'UTF-8')?></p>
  </div>
</div>
<?php endif; ?>
<?php if (isset($_GET['err'])): ?>
<div class="mb-4 bg-rose-50 border border-rose-200 rounded-2xl px-5 py-3 flex items-center gap-3">
  <i class="bi bi-exclamation-triangle-fill text-rose-500 text-lg flex-shrink-0"></i>
  <p class="text-sm font-bold text-rose-700"><?=htmlspecialchars($_GET['err'],ENT_QUOTES,'UTF-8')?></p>
</div>
<?php endif; ?>

<!-- Subject Hero Header -->
<div class="rounded-2xl overflow-hidden mb-5 shadow-lg"
     style="background:linear-gradient(135deg,#059669,#0d9488)">
  <div class="px-6 pt-5 pb-4">
    <a href="assign_work.php" class="inline-flex items-center gap-1.5 text-xs text-emerald-200 hover:text-white mb-4 transition-colors">
      <i class="bi bi-arrow-left"></i> วิชาทั้งหมด
    </a>
    <div class="flex flex-wrap items-start justify-between gap-4">
      <div class="flex items-center gap-4">
        <div class="w-14 h-14 rounded-2xl bg-white/20 backdrop-blur flex items-center justify-center flex-shrink-0">
          <i class="bi bi-book-half text-white text-2xl"></i>
        </div>
        <div>
          <h2 class="text-xl font-black text-white"><?=htmlspecialchars($sel_subject['subject_name'],ENT_QUOTES,'UTF-8')?></h2>
          <div class="flex flex-wrap gap-1.5 mt-1.5">
            <?php if ($sel_subject['subject_code']): ?>
            <span class="px-2 py-0.5 bg-white/20 text-white text-[10px] font-bold rounded-full"><?=htmlspecialchars($sel_subject['subject_code'],ENT_QUOTES,'UTF-8')?></span>
            <?php endif; ?>
            <?php foreach ($sel_classrooms as $cl): ?>
            <span class="px-2 py-0.5 bg-white/20 text-white text-[10px] font-bold rounded-full">
              <i class="bi bi-door-open mr-0.5"></i><?=htmlspecialchars($cl,ENT_QUOTES,'UTF-8')?>
            </span>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
      <button onclick="openModal()"
              class="px-4 py-2.5 bg-white text-emerald-700 text-sm font-black rounded-xl shadow-lg hover:bg-emerald-50 transition-all flex items-center gap-2 flex-shrink-0">
        <i class="bi bi-plus-circle-fill"></i> สั่งงานใหม่
      </button>
    </div>
  </div>

  <!-- Stats bar -->
  <div class="grid grid-cols-3 border-t border-white/20">
    <div class="px-5 py-3 text-center border-r border-white/20">
      <p class="text-xl font-black text-white"><?=$enrolled_count?></p>
      <p class="text-[10px] text-emerald-200 font-bold mt-0.5"><i class="bi bi-people mr-0.5"></i>นักเรียน</p>
    </div>
    <div class="px-5 py-3 text-center border-r border-white/20">
      <p class="text-xl font-black text-white"><?=count($sel_units)?></p>
      <p class="text-[10px] text-emerald-200 font-bold mt-0.5"><i class="bi bi-collection mr-0.5"></i>หน่วย</p>
    </div>
    <div class="px-5 py-3 text-center">
      <p class="text-xl font-black text-white"><?=count($recent_ex)?></p>
      <p class="text-[10px] text-emerald-200 font-bold mt-0.5"><i class="bi bi-file-earmark-text mr-0.5"></i>ใบงาน</p>
    </div>
  </div>
</div>

<!-- Subject switcher strip -->
<?php if (count($subjects) > 1): ?>
<div class="flex gap-2 mb-5 overflow-x-auto pb-1 scrollbar-none">
  <?php foreach ($subjects as $s): ?>
  <a href="assign_work.php?subject_id=<?=$s['id']?>"
     class="flex-shrink-0 px-3 py-1.5 rounded-xl text-xs font-bold transition-all
            <?=$s['id']==$sel_subject_id?'bg-emerald-600 text-white shadow-md':'bg-white border border-slate-200 text-slate-600 hover:border-emerald-300 hover:text-emerald-700'?>">
    <?=htmlspecialchars($s['subject_name'],ENT_QUOTES,'UTF-8')?>
  </a>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<?php if (empty($sel_classrooms)): ?>
<!-- No classrooms -->
<div class="bg-amber-50 rounded-2xl border border-amber-200 p-8 text-center shadow-sm">
  <i class="bi bi-exclamation-triangle-fill text-amber-400 text-4xl block mb-3"></i>
  <p class="font-black text-slate-700 text-sm">วิชานี้ยังไม่ได้ผูกห้องเรียน</p>
  <a href="subjects.php" class="mt-3 inline-flex items-center gap-1 text-xs font-bold text-emerald-600 hover:text-emerald-800">
    <i class="bi bi-gear-fill"></i> ตั้งค่าห้องเรียน
  </a>
</div>

<?php elseif (empty($recent_ex)): ?>
<!-- Empty state -->
<div class="bg-white rounded-2xl border border-dashed border-slate-200 p-16 text-center shadow-sm">
  <div class="w-14 h-14 rounded-2xl bg-emerald-50 flex items-center justify-center mx-auto mb-4">
    <i class="bi bi-file-earmark-plus text-emerald-500 text-2xl"></i>
  </div>
  <p class="font-black text-slate-700">ยังไม่มีใบงาน</p>
  <p class="text-xs text-slate-400 mt-1">กดปุ่ม "สั่งงานใหม่" ด้านบนเพื่อเริ่มต้น</p>
  <button onclick="openModal()"
          class="mt-4 inline-flex items-center gap-2 px-5 py-2.5 bg-emerald-600 text-white text-sm font-black rounded-xl shadow-lg shadow-emerald-200 hover:bg-emerald-700 transition-all">
    <i class="bi bi-plus-circle-fill"></i> สั่งงานใหม่
  </button>
</div>

<?php else: ?>
<!-- Exercise list -->
<div class="space-y-3" id="exerciseList">
  <?php
  $grouped = [];
  foreach ($recent_ex as $re) {
      $grouped[$re['unit_id']]['name'] = $re['unit_name'];
      $grouped[$re['unit_id']]['items'][] = $re;
  }
  ?>
  <?php foreach ($grouped as $uid => $grp): ?>
  <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
    <!-- Unit header -->
    <div class="flex items-center gap-3 px-5 py-3.5 border-b border-slate-50 bg-slate-50/60">
      <div class="w-7 h-7 rounded-lg bg-emerald-100 text-emerald-700 text-xs font-black flex items-center justify-center flex-shrink-0">
        <i class="bi bi-collection-fill text-xs"></i>
      </div>
      <span class="text-sm font-black text-slate-700"><?=htmlspecialchars($grp['name'],ENT_QUOTES,'UTF-8')?></span>
      <span class="ml-auto text-[10px] text-slate-400 font-bold"><?=count($grp['items'])?> ใบงาน</span>
    </div>

    <!-- Exercise rows -->
    <div class="divide-y divide-slate-50">
      <?php foreach ($grp['items'] as $re):
        $cls_label = $re['ex_classes'] ?? 'ทุกห้อง';
        $is_due    = $re['due_date'] && strtotime($re['due_date']) < time();
      ?>
      <div class="flex items-center gap-3 px-5 py-3.5 hover:bg-slate-50/50 transition-colors">
        <div class="w-8 h-8 rounded-xl bg-teal-50 flex items-center justify-center flex-shrink-0">
          <i class="bi bi-file-earmark-text text-teal-500 text-sm"></i>
        </div>
        <div class="flex-1 min-w-0">
          <p class="text-sm font-black text-slate-700 truncate"><?=htmlspecialchars($re['exercise_title'],ENT_QUOTES,'UTF-8')?></p>
          <p class="text-[10px] text-slate-400 mt-0.5 flex flex-wrap gap-x-2">
            <span><i class="bi bi-door-open mr-0.5"></i><?=htmlspecialchars($cls_label,ENT_QUOTES,'UTF-8')?></span>
            <?php if ($re['due_date']): ?>
            <span class="<?=$is_due?'text-rose-400':''?>">
              <i class="bi bi-clock mr-0.5"></i><?=date('d/m/Y H:i',strtotime($re['due_date']))?>
              <?=$is_due?' · หมดเวลาแล้ว':''?>
            </span>
            <?php endif; ?>
          </p>
        </div>
        <div class="flex items-center gap-2 flex-shrink-0">
          <?php if ($re['max_score']): ?>
          <span class="px-2 py-0.5 bg-amber-50 text-amber-600 text-[10px] font-black rounded-full"><?=$re['max_score']?> คะ</span>
          <?php endif; ?>
          <span class="px-2.5 py-1 <?=$re['sub_count']>0?'bg-emerald-50 text-emerald-700':'bg-slate-100 text-slate-400'?> text-xs font-black rounded-full">
            <?=$re['sub_count']?> ส่ง
          </span>
          <a href="grade_exercises.php?subject_id=<?=$sel_subject_id?>&exercise_id=<?=$re['id']?>"
             class="p-2 rounded-xl bg-teal-50 text-teal-600 hover:bg-teal-100 transition-all" title="ตรวจงาน">
            <i class="bi bi-check2-square text-sm"></i>
          </a>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<div class="mt-4 flex justify-end">
  <a href="grade_exercises.php?subject_id=<?=$sel_subject_id?>"
     class="inline-flex items-center gap-2 px-5 py-2.5 bg-white border border-slate-200 text-slate-600 text-sm font-bold rounded-xl hover:border-emerald-300 hover:text-emerald-700 transition-all">
    <i class="bi bi-check2-all"></i> ดูทั้งหมดและตรวจงาน
  </a>
</div>
<?php endif; ?>

<!-- ── Modal: สั่งงานใหม่ ─────────────────────────────────────── -->
<div id="assignModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 hidden">
  <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" onclick="closeModal()"></div>
  <div class="relative bg-white rounded-3xl shadow-2xl w-full max-w-lg overflow-hidden animate-slide-up">

    <!-- Modal header -->
    <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100"
         style="background:linear-gradient(135deg,#ecfdf5,#d1fae5)">
      <div class="flex items-center gap-3">
        <div class="w-9 h-9 rounded-xl bg-emerald-600 flex items-center justify-center shadow-md">
          <i class="bi bi-plus-square-fill text-white"></i>
        </div>
        <div>
          <p class="font-black text-emerald-800 text-sm">ใบงานใหม่</p>
          <p class="text-[10px] text-emerald-600"><?=htmlspecialchars($sel_subject['subject_name'],ENT_QUOTES,'UTF-8')?></p>
        </div>
      </div>
      <button onclick="closeModal()" class="w-8 h-8 rounded-xl bg-white/80 text-slate-400 hover:text-slate-700 flex items-center justify-center transition-all hover:bg-white">
        <i class="bi bi-x-lg text-sm"></i>
      </button>
    </div>

    <!-- Modal body -->
    <form method="POST" action="assign_work.php" class="overflow-y-auto max-h-[70vh]">
      <input type="hidden" name="subject_id" value="<?=$sel_subject_id?>">
      <div class="p-6 space-y-4">

        <!-- ① ห้องเรียน -->
        <div>
          <label class="block text-xs font-black text-slate-500 uppercase tracking-wider mb-2">
            <i class="bi bi-door-open-fill text-emerald-500 mr-1"></i>
            ส่งถึงห้อง <span class="text-rose-400">*</span>
          </label>
          <div class="flex flex-wrap gap-1.5 mb-2">
            <button type="button" onclick="toggleAll()"
              class="px-2.5 py-1 rounded-lg text-[10px] font-black border border-slate-200 text-slate-500 hover:border-emerald-400 hover:text-emerald-600 transition-all">
              เลือกทั้งหมด
            </button>
            <?php if (count($grade_groups) >= 2): foreach ($grade_groups as $grade => $gCls): ?>
            <button type="button" onclick="toggleGrade(this)" data-grade="<?=htmlspecialchars($grade,ENT_QUOTES,'UTF-8')?>"
              class="grade-btn px-2.5 py-1 rounded-lg text-[10px] font-black border border-emerald-400 bg-emerald-50 text-emerald-700 transition-all">
              <?=htmlspecialchars($grade,ENT_QUOTES,'UTF-8')?>
            </button>
            <?php endforeach; endif; ?>
          </div>
          <div class="flex flex-wrap gap-1.5" id="classroomPills">
            <?php foreach ($sel_classrooms as $cl): ?>
            <label class="cursor-pointer">
              <input type="checkbox" name="classrooms[]" value="<?=htmlspecialchars($cl,ENT_QUOTES,'UTF-8')?>"
                     class="hidden cl-check" checked onchange="updatePillStyle(this); updateAllGradeBtns();">
              <span class="pill px-2.5 py-1 rounded-lg text-[10px] font-black border-2 select-none transition-all
                           border-emerald-500 bg-emerald-600 text-white">
                <i class="bi bi-check2 mr-0.5"></i><?=htmlspecialchars($cl,ENT_QUOTES,'UTF-8')?>
              </span>
            </label>
            <?php endforeach; ?>
          </div>
        </div>

        <!-- ② หน่วยการเรียน -->
        <div>
          <label class="block text-xs font-black text-slate-500 uppercase tracking-wider mb-2">
            <i class="bi bi-collection-fill text-emerald-500 mr-1"></i>
            หน่วยการเรียน <span class="text-rose-400">*</span>
          </label>
          <?php if (!empty($sel_units)): ?>
          <select name="unit_id" id="unitSelect" onchange="toggleNewUnit()"
            class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm font-bold focus:ring-2 focus:ring-emerald-400 outline-none">
            <?php foreach ($sel_units as $u): ?>
            <option value="<?=$u['id']?>"><?=$u['order_no']?>. <?=htmlspecialchars($u['unit_name'],ENT_QUOTES,'UTF-8')?></option>
            <?php endforeach; ?>
            <option value="0">+ สร้างหน่วยใหม่...</option>
          </select>
          <input type="text" name="new_unit_name" id="newUnitField"
            placeholder="ชื่อหน่วยการเรียนใหม่"
            class="hidden mt-2 w-full bg-emerald-50 border border-emerald-300 rounded-xl px-4 py-2.5 text-sm font-bold focus:ring-2 focus:ring-emerald-400 outline-none">
          <?php else: ?>
          <input type="hidden" name="unit_id" value="0">
          <input type="text" name="new_unit_name" required placeholder="ชื่อหน่วยการเรียน (เช่น หน่วย 1)"
            class="w-full bg-emerald-50 border border-emerald-300 rounded-xl px-4 py-2.5 text-sm font-bold focus:ring-2 focus:ring-emerald-400 outline-none">
          <p class="text-[10px] text-emerald-600 mt-1"><i class="bi bi-info-circle mr-0.5"></i>วิชานี้ยังไม่มีหน่วย จะสร้างให้อัตโนมัติ</p>
          <?php endif; ?>
        </div>

        <!-- ③ ชื่อใบงาน -->
        <div>
          <label class="block text-xs font-black text-slate-500 uppercase tracking-wider mb-2">
            <i class="bi bi-pencil-fill text-emerald-500 mr-1"></i>
            ชื่อใบงาน <span class="text-rose-400">*</span>
          </label>
          <input type="text" name="exercise_title" required placeholder="เช่น คัดลายมือ บทที่ 1"
            class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm font-bold focus:ring-2 focus:ring-emerald-400 outline-none">
        </div>

        <!-- ④ คำอธิบาย -->
        <div>
          <label class="block text-xs font-black text-slate-500 uppercase tracking-wider mb-2">
            <i class="bi bi-card-text text-emerald-500 mr-1"></i>
            คำอธิบาย / โจทย์ <span class="text-slate-300 font-normal">(ไม่บังคับ)</span>
          </label>
          <textarea name="description" rows="2" placeholder="รายละเอียดสำหรับนักเรียน..."
            class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm resize-none focus:ring-2 focus:ring-emerald-400 outline-none"></textarea>
        </div>

        <!-- ⑤ คะแนน + กำหนดส่ง -->
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="block text-xs font-black text-slate-500 uppercase tracking-wider mb-2">
              <i class="bi bi-star-fill text-amber-400 mr-1"></i>
              คะแนนเต็ม <span class="text-slate-300 font-normal">(ไม่บังคับ)</span>
            </label>
            <input type="number" name="max_score" min="1" max="100" placeholder="เช่น 10"
              class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm font-bold focus:ring-2 focus:ring-emerald-400 outline-none">
          </div>
          <div>
            <label class="block text-xs font-black text-slate-500 uppercase tracking-wider mb-2">
              <i class="bi bi-calendar-event-fill text-rose-400 mr-1"></i>
              กำหนดส่ง <span class="text-slate-300 font-normal">(ไม่บังคับ)</span>
            </label>
            <input type="datetime-local" name="due_date"
              class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm font-bold focus:ring-2 focus:ring-emerald-400 outline-none">
          </div>
        </div>
      </div>

      <!-- Modal footer -->
      <div class="px-6 pb-6 flex items-center gap-3">
        <button type="button" onclick="closeModal()"
          class="flex-1 py-3 bg-slate-100 text-slate-600 font-bold text-sm rounded-xl hover:bg-slate-200 transition-all">
          ยกเลิก
        </button>
        <button type="submit"
          class="flex-1 py-3 bg-gradient-to-r from-emerald-600 to-teal-600 text-white font-black text-sm rounded-xl shadow-lg shadow-emerald-200 hover:from-emerald-700 hover:to-teal-700 transition-all flex items-center justify-center gap-2">
          <i class="bi bi-send-fill"></i> สั่งงานเลย
        </button>
      </div>
    </form>
  </div>
</div>

<?php endif; ?>

<style>
@keyframes slide-up {
  from { opacity: 0; transform: translateY(24px); }
  to   { opacity: 1; transform: translateY(0); }
}
.animate-slide-up { animation: slide-up .2s ease-out; }
.scrollbar-none::-webkit-scrollbar { display: none; }
</style>

<script>
function openModal() {
  document.getElementById('assignModal').classList.remove('hidden');
  document.body.style.overflow = 'hidden';
}
function closeModal() {
  document.getElementById('assignModal').classList.add('hidden');
  document.body.style.overflow = '';
}
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeModal(); });

<?php if (isset($_GET['err'])): ?>
window.addEventListener('DOMContentLoaded', () => openModal());
<?php endif; ?>

function updatePillStyle(checkbox) {
  const span = checkbox.nextElementSibling;
  if (checkbox.checked) {
    span.classList.remove('border-slate-200','bg-white','text-slate-500');
    span.classList.add('border-emerald-500','bg-emerald-600','text-white');
    const txt = span.textContent.trim();
    span.innerHTML = '<i class="bi bi-check2 mr-0.5"></i>' + txt;
  } else {
    span.classList.remove('border-emerald-500','bg-emerald-600','text-white');
    span.classList.add('border-slate-200','bg-white','text-slate-500');
    span.innerHTML = span.textContent.trim();
  }
}
function gradeOf(val) {
  const idx = val.indexOf('/');
  return idx >= 0 ? val.substring(0, idx) : val;
}
function toggleGrade(btn) {
  const grade  = btn.dataset.grade;
  const checks = [...document.querySelectorAll('.cl-check')].filter(c => gradeOf(c.value) === grade);
  const allCh  = checks.every(c => c.checked);
  checks.forEach(c => { c.checked = !allCh; updatePillStyle(c); });
  updateAllGradeBtns();
}
function updateAllGradeBtns() {
  document.querySelectorAll('.grade-btn').forEach(btn => {
    const grade   = btn.dataset.grade;
    const checks  = [...document.querySelectorAll('.cl-check')].filter(c => gradeOf(c.value) === grade);
    const allCh   = checks.length > 0 && checks.every(c => c.checked);
    const noneCh  = checks.every(c => !c.checked);
    btn.classList.toggle('border-emerald-400', allCh);
    btn.classList.toggle('bg-emerald-50',      allCh);
    btn.classList.toggle('text-emerald-700',   allCh);
    btn.classList.toggle('border-amber-400',   !allCh && !noneCh);
    btn.classList.toggle('bg-amber-50',        !allCh && !noneCh);
    btn.classList.toggle('text-amber-600',     !allCh && !noneCh);
    btn.classList.toggle('border-slate-200',   noneCh);
    btn.classList.toggle('bg-white',           noneCh);
    btn.classList.toggle('text-slate-400',     noneCh);
  });
}
function toggleAll() {
  const checks = document.querySelectorAll('.cl-check');
  const allCh  = [...checks].every(c => c.checked);
  checks.forEach(c => { c.checked = !allCh; updatePillStyle(c); });
  updateAllGradeBtns();
}
function toggleNewUnit() {
  const sel   = document.getElementById('unitSelect');
  const field = document.getElementById('newUnitField');
  if (!sel || !field) return;
  if (sel.value === '0') {
    field.classList.remove('hidden');
    field.required = true;
    field.focus();
  } else {
    field.classList.add('hidden');
    field.required = false;
    field.value = '';
  }
}
</script>

<?php require_once __DIR__ . '/../components/layout_end.php'; ?>
