<?php
session_start();
require_once __DIR__ . '/../config.php';
if (!isset($_SESSION['llw_role'])) { header('Location: ' . $base_path . '/login.php'); exit(); }
if (!in_array($_SESSION['llw_role'], ['super_admin','att_teacher'])) { header('Location: ' . $base_path . '/login.php'); exit(); }

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

        $pdo->beginTransaction();

        // Get or create unit
        if (!$unit_id) {
            if (!$new_unit) throw new Exception('กรุณาเลือกหรือสร้างหน่วยการเรียน');
            $r = $pdo->prepare("SELECT COALESCE(MAX(order_no),0)+1 FROM lms_units WHERE subject_id=?");
            $r->execute([$subject_id]); $next_order = (int)$r->fetchColumn();
            $pdo->prepare("INSERT INTO lms_units (subject_id, order_no, unit_name) VALUES (?,?,?)")
                ->execute([$subject_id, $next_order, $new_unit]);
            $unit_id = (int)$pdo->lastInsertId();
        }

        // Create exercise
        $pdo->prepare("INSERT INTO lms_unit_exercises (unit_id, exercise_title, description, max_score, due_date) VALUES (?,?,?,?,?)")
            ->execute([$unit_id, $title, $desc, $score, $due]);
        $ex_id = (int)$pdo->lastInsertId();

        // Classroom assignment — only record if NOT sending to all enrolled classrooms
        // (skip if migration hasn't been run yet on this server)
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
    $st = $pdo->prepare("SELECT * FROM lms_subjects WHERE teacher_id=? ORDER BY subject_name");
    $st->execute([$teacher_id]); $subjects = $st->fetchAll();
}

$sel_subject_id = (int)($_GET['subject_id'] ?? ($subjects[0]['id'] ?? 0));
$sel_subject    = null;
$sel_classrooms = [];
$sel_units      = [];
$recent_ex      = [];

if ($sel_subject_id) {
    foreach ($subjects as $s) {
        if ($s['id'] == $sel_subject_id) { $sel_subject = $s; break; }
    }
    $r = $pdo->prepare("SELECT classroom FROM lms_subject_classrooms WHERE subject_id=? ORDER BY classroom");
    $r->execute([$sel_subject_id]); $sel_classrooms = $r->fetchAll(PDO::FETCH_COLUMN);

    $r = $pdo->prepare("SELECT id, unit_name, order_no FROM lms_units WHERE subject_id=? ORDER BY order_no");
    $r->execute([$sel_subject_id]); $sel_units = $r->fetchAll();

    // Recent exercises (with classroom info)
    try {
        $r = $pdo->prepare("
            SELECT e.id, e.exercise_title, e.max_score, e.due_date,
                   u.unit_name,
                   GROUP_CONCAT(ec.classroom ORDER BY ec.classroom SEPARATOR ', ') AS ex_classes,
                   COUNT(DISTINCT se.id) AS sub_count
            FROM lms_unit_exercises e
            JOIN lms_units u ON u.id = e.unit_id
            LEFT JOIN lms_exercise_classrooms ec ON ec.exercise_id = e.id
            LEFT JOIN lms_student_exercises se ON se.exercise_id = e.id
            WHERE u.subject_id = ?
            GROUP BY e.id
            ORDER BY e.id DESC
            LIMIT 8
        ");
        $r->execute([$sel_subject_id]); $recent_ex = $r->fetchAll();
    } catch (Exception $e) { $recent_ex = []; }
}

$pageTitle    = 'สั่งงาน';
$pageSubtitle = 'มอบหมายใบงานให้นักเรียนตามห้อง';
$activeSystem = 'lms';
require_once __DIR__ . '/../components/layout_start.php';
?>

<!-- Page header -->
<div class="flex flex-wrap items-center gap-3 mb-6">
  <div class="w-10 h-10 rounded-xl flex items-center justify-center shadow-lg bg-gradient-to-br from-violet-500 to-purple-600">
    <i class="bi bi-plus-square-fill text-white text-lg"></i>
  </div>
  <div>
    <h2 class="text-lg font-black text-slate-800">สั่งงาน</h2>
    <p class="text-xs text-slate-400">เลือกวิชา → เลือกห้อง → กรอกใบงาน → กดสั่งงานได้เลย</p>
  </div>
</div>

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

<div class="grid grid-cols-1 lg:grid-cols-4 gap-5">

  <!-- ── Left: subject selector ─────────────────────────────── -->
  <div class="lg:col-span-1">
    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-4 sticky top-4">
      <p class="text-xs font-black text-slate-400 uppercase tracking-wider mb-3">เลือกวิชา</p>
      <?php if (empty($subjects)): ?>
      <p class="text-sm text-slate-400 text-center py-4">ยังไม่มีวิชา<br><a href="subjects.php" class="text-violet-600 font-bold text-xs">+ สร้างวิชา</a></p>
      <?php else: ?>
      <div class="space-y-1">
        <?php foreach ($subjects as $s): ?>
        <a href="assign_work.php?subject_id=<?=$s['id']?>"
           class="flex items-center gap-2 px-3 py-2.5 rounded-xl text-sm font-bold transition-all
                  <?=$s['id']==$sel_subject_id?'bg-violet-600 text-white shadow-md shadow-violet-200':'text-slate-700 hover:bg-slate-50 border border-transparent hover:border-slate-100'?>">
          <i class="bi bi-book-half flex-shrink-0 text-sm <?=$s['id']==$sel_subject_id?'':'text-violet-400'?>"></i>
          <span class="truncate"><?=htmlspecialchars($s['subject_name'],ENT_QUOTES,'UTF-8')?></span>
        </a>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- ── Right: assignment form + recent ────────────────────── -->
  <div class="lg:col-span-3 space-y-5">

    <?php if (!$sel_subject): ?>
    <div class="bg-white rounded-2xl border border-slate-100 p-16 text-center text-slate-300 shadow-sm">
      <i class="bi bi-arrow-left-circle text-5xl block mb-3"></i>
      <p class="font-bold">เลือกวิชาทางซ้ายก่อน</p>
    </div>

    <?php elseif (empty($sel_classrooms)): ?>
    <div class="bg-amber-50 rounded-2xl border border-amber-200 p-8 text-center shadow-sm">
      <i class="bi bi-exclamation-triangle-fill text-amber-400 text-4xl block mb-3"></i>
      <p class="font-black text-slate-700 text-sm">วิชานี้ยังไม่ได้ผูกห้องเรียน</p>
      <a href="subjects.php" class="mt-3 inline-flex items-center gap-1 text-xs font-bold text-violet-600 hover:text-violet-800">
        <i class="bi bi-gear-fill"></i> ตั้งค่าห้องเรียน
      </a>
    </div>

    <?php else: ?>
    <?php
        // Group classrooms by grade level (prefix before '/')
        $grade_groups = [];
        foreach ($sel_classrooms as $cl) {
            $grade = strstr($cl, '/', true);
            if ($grade === false) $grade = $cl;
            $grade_groups[$grade][] = $cl;
        }
        $has_grade_btns = count($grade_groups) >= 2;
    ?>
    <!-- ── Assignment form ─────────────────────────────────── -->
    <form method="POST" action="assign_work.php">
      <input type="hidden" name="subject_id" value="<?=$sel_subject_id?>">

      <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
        <!-- Form header -->
        <div class="px-6 py-4 border-b border-slate-50" style="background:linear-gradient(135deg,#F5F3FF,#EDE9FE)">
          <p class="font-black text-violet-800 text-sm flex items-center gap-2">
            <i class="bi bi-plus-square-fill"></i>
            ใบงานใหม่ — <?=htmlspecialchars($sel_subject['subject_name'],ENT_QUOTES,'UTF-8')?>
          </p>
          <p class="text-xs text-violet-500 mt-0.5">กรอกข้อมูลด้านล่าง แล้วกด "สั่งงาน"</p>
        </div>

        <div class="p-6 space-y-5">

          <!-- ① ห้องเรียน -->
          <div>
            <label class="block text-xs font-black text-slate-500 uppercase tracking-wider mb-2">
              <i class="bi bi-door-open-fill text-violet-400 mr-1"></i>
              ① ส่งถึงห้อง <span class="text-rose-400">*</span>
            </label>
            <!-- Quick-select row -->
            <div class="flex flex-wrap gap-2 mb-2">
              <button type="button" onclick="toggleAll()"
                class="px-3 py-1.5 rounded-xl text-xs font-black border-2 border-slate-200 text-slate-500 hover:border-violet-400 hover:text-violet-600 transition-all">
                เลือกทั้งหมด
              </button>
              <?php if ($has_grade_btns): foreach ($grade_groups as $grade => $gCls): ?>
              <button type="button" onclick="toggleGrade(this)"
                data-grade="<?=htmlspecialchars($grade,ENT_QUOTES,'UTF-8')?>"
                class="grade-btn px-3 py-1.5 rounded-xl text-xs font-black border-2 border-violet-500 bg-violet-100 text-violet-700 transition-all">
                <?=htmlspecialchars($grade,ENT_QUOTES,'UTF-8')?>
              </button>
              <?php endforeach; endif; ?>
            </div>
            <!-- Individual classroom pills -->
            <div class="flex flex-wrap gap-2" id="classroomPills">
              <?php foreach ($sel_classrooms as $cl): ?>
              <label class="cursor-pointer">
                <input type="checkbox" name="classrooms[]" value="<?=htmlspecialchars($cl,ENT_QUOTES,'UTF-8')?>"
                       class="hidden cl-check" checked onchange="updatePillStyle(this); updateAllGradeBtns();">
                <span class="pill px-3 py-1.5 rounded-xl text-xs font-black border-2 select-none transition-all
                             border-violet-500 bg-violet-600 text-white">
                  <i class="bi bi-check2 mr-0.5"></i><?=htmlspecialchars($cl,ENT_QUOTES,'UTF-8')?>
                </span>
              </label>
              <?php endforeach; ?>
            </div>
          </div>

          <!-- ② หน่วยการเรียน -->
          <div>
            <label class="block text-xs font-black text-slate-500 uppercase tracking-wider mb-2">
              <i class="bi bi-collection-fill text-violet-400 mr-1"></i>
              ② หน่วยการเรียน <span class="text-rose-400">*</span>
            </label>
            <?php if (!empty($sel_units)): ?>
            <select name="unit_id" id="unitSelect" onchange="toggleNewUnit()"
              class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm font-bold focus:ring-2 focus:ring-violet-400 outline-none">
              <?php foreach ($sel_units as $u): ?>
              <option value="<?=$u['id']?>"><?=$u['order_no']?>. <?=htmlspecialchars($u['unit_name'],ENT_QUOTES,'UTF-8')?></option>
              <?php endforeach; ?>
              <option value="0">+ สร้างหน่วยใหม่...</option>
            </select>
            <input type="text" name="new_unit_name" id="newUnitField"
              placeholder="ชื่อหน่วยการเรียนใหม่"
              class="hidden mt-2 w-full bg-slate-50 border border-violet-300 rounded-xl px-4 py-2.5 text-sm font-bold focus:ring-2 focus:ring-violet-400 outline-none">
            <?php else: ?>
            <input type="hidden" name="unit_id" value="0">
            <input type="text" name="new_unit_name" required placeholder="ชื่อหน่วยการเรียน (เช่น หน่วย 1: อ่านออกเสียง)"
              class="w-full bg-violet-50 border border-violet-300 rounded-xl px-4 py-2.5 text-sm font-bold focus:ring-2 focus:ring-violet-400 outline-none">
            <p class="text-[10px] text-violet-500 mt-1"><i class="bi bi-info-circle mr-0.5"></i>วิชานี้ยังไม่มีหน่วย จะสร้างหน่วยใหม่ให้อัตโนมัติ</p>
            <?php endif; ?>
          </div>

          <!-- ③ ชื่อใบงาน -->
          <div>
            <label class="block text-xs font-black text-slate-500 uppercase tracking-wider mb-2">
              <i class="bi bi-pencil-fill text-violet-400 mr-1"></i>
              ③ ชื่อใบงาน <span class="text-rose-400">*</span>
            </label>
            <input type="text" name="exercise_title" required placeholder="เช่น คัดลายมือ บทที่ 1"
              class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm font-bold focus:ring-2 focus:ring-violet-400 outline-none">
          </div>

          <!-- ④ คำอธิบาย (optional) -->
          <div>
            <label class="block text-xs font-black text-slate-500 uppercase tracking-wider mb-2">
              <i class="bi bi-card-text text-violet-400 mr-1"></i>
              ④ คำอธิบาย / โจทย์ <span class="text-slate-300">(ไม่บังคับ)</span>
            </label>
            <textarea name="description" rows="2" placeholder="รายละเอียดเพิ่มเติมสำหรับนักเรียน..."
              class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm resize-none focus:ring-2 focus:ring-violet-400 outline-none"></textarea>
          </div>

          <!-- ⑤ คะแนน + กำหนดส่ง -->
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-xs font-black text-slate-500 uppercase tracking-wider mb-2">
                <i class="bi bi-star-fill text-amber-400 mr-1"></i>
                ⑤ คะแนนเต็ม <span class="text-slate-300">(ไม่บังคับ)</span>
              </label>
              <input type="number" name="max_score" min="1" max="100" placeholder="เช่น 10"
                class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm font-bold focus:ring-2 focus:ring-violet-400 outline-none">
            </div>
            <div>
              <label class="block text-xs font-black text-slate-500 uppercase tracking-wider mb-2">
                <i class="bi bi-calendar-event-fill text-rose-400 mr-1"></i>
                ⑥ กำหนดส่ง <span class="text-slate-300">(ไม่บังคับ)</span>
              </label>
              <input type="datetime-local" name="due_date"
                class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm font-bold focus:ring-2 focus:ring-violet-400 outline-none">
            </div>
          </div>

          <!-- Submit -->
          <button type="submit"
            class="w-full py-3.5 bg-gradient-to-r from-violet-600 to-purple-600 text-white font-black text-sm rounded-xl shadow-lg shadow-violet-200 hover:from-violet-700 hover:to-purple-700 transition-all flex items-center justify-center gap-2">
            <i class="bi bi-send-fill text-base"></i>
            สั่งงานเลย
          </button>
        </div>
      </div>
    </form>

    <!-- ── Recent exercises ─────────────────────────────────── -->
    <?php if (!empty($recent_ex)): ?>
    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
      <div class="px-5 py-3.5 border-b border-slate-50 flex items-center justify-between">
        <p class="text-xs font-black text-slate-400 uppercase tracking-wider">งานที่สั่งล่าสุด</p>
        <a href="grade_exercises.php?subject_id=<?=$sel_subject_id?>"
           class="text-[10px] font-bold text-violet-600 hover:text-violet-800">ตรวจงาน →</a>
      </div>
      <div class="divide-y divide-slate-50">
        <?php foreach ($recent_ex as $re):
          $cls_label = $re['ex_classes'] ?? 'ทุกห้อง';
        ?>
        <div class="px-5 py-3 flex items-center justify-between gap-3">
          <div class="flex-1 min-w-0">
            <p class="text-sm font-black text-slate-700 truncate"><?=htmlspecialchars($re['exercise_title'],ENT_QUOTES,'UTF-8')?></p>
            <p class="text-[10px] text-slate-400 mt-0.5">
              <i class="bi bi-collection mr-0.5"></i><?=htmlspecialchars($re['unit_name'],ENT_QUOTES,'UTF-8')?> ·
              <i class="bi bi-door-open ml-1 mr-0.5"></i><?=htmlspecialchars($cls_label,ENT_QUOTES,'UTF-8')?>
              <?php if ($re['due_date']): ?>
              · <i class="bi bi-clock ml-1 mr-0.5"></i><?=date('d/m/Y',strtotime($re['due_date']))?>
              <?php endif; ?>
            </p>
          </div>
          <div class="flex items-center gap-2 flex-shrink-0">
            <?php if ($re['max_score']): ?>
            <span class="px-2 py-0.5 bg-amber-50 text-amber-600 text-[10px] font-black rounded-full"><?=$re['max_score']?> คะ</span>
            <?php endif; ?>
            <span class="px-2.5 py-1 bg-violet-50 text-violet-600 text-xs font-black rounded-full"><?=$re['sub_count']?> ส่ง</span>
            <a href="grade_exercises.php?subject_id=<?=$sel_subject_id?>&exercise_id=<?=$re['id']?>"
               class="p-1.5 rounded-lg bg-slate-50 text-slate-400 hover:bg-violet-50 hover:text-violet-600 transition-all">
              <i class="bi bi-check2-square text-sm"></i>
            </a>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>
    <?php endif; ?>

  </div>
</div>

<script>
function updatePillStyle(checkbox) {
    const span = checkbox.nextElementSibling;
    if (checkbox.checked) {
        span.classList.remove('border-slate-200','bg-white','text-slate-500');
        span.classList.add('border-violet-500','bg-violet-600','text-white');
        span.innerHTML = '<i class="bi bi-check2 mr-0.5"></i>' + span.textContent.trim();
    } else {
        span.classList.remove('border-violet-500','bg-violet-600','text-white');
        span.classList.add('border-slate-200','bg-white','text-slate-500');
        span.innerHTML = span.textContent.trim();
    }
}

function gradeOf(val) {
    const idx = val.indexOf('/');
    return idx >= 0 ? val.substring(0, idx) : val;
}

function toggleGrade(btn) {
    const grade = btn.dataset.grade;
    const checks = [...document.querySelectorAll('.cl-check')].filter(c => gradeOf(c.value) === grade);
    const allChecked = checks.every(c => c.checked);
    checks.forEach(c => { c.checked = !allChecked; updatePillStyle(c); });
    updateAllGradeBtns();
}

function updateAllGradeBtns() {
    document.querySelectorAll('.grade-btn').forEach(btn => {
        const grade = btn.dataset.grade;
        const checks = [...document.querySelectorAll('.cl-check')].filter(c => gradeOf(c.value) === grade);
        const allChecked = checks.length > 0 && checks.every(c => c.checked);
        const noneChecked = checks.every(c => !c.checked);
        btn.classList.toggle('border-violet-500', allChecked);
        btn.classList.toggle('bg-violet-100',     allChecked);
        btn.classList.toggle('text-violet-700',   allChecked);
        btn.classList.toggle('border-amber-400',  !allChecked && !noneChecked);
        btn.classList.toggle('bg-amber-50',       !allChecked && !noneChecked);
        btn.classList.toggle('text-amber-600',    !allChecked && !noneChecked);
        btn.classList.toggle('border-slate-200',  noneChecked);
        btn.classList.toggle('bg-white',          noneChecked);
        btn.classList.toggle('text-slate-400',    noneChecked);
    });
}

function toggleAll() {
    const checks = document.querySelectorAll('.cl-check');
    const allChecked = [...checks].every(c => c.checked);
    checks.forEach(c => { c.checked = !allChecked; updatePillStyle(c); });
    updateAllGradeBtns();
}

function toggleNewUnit() {
    const sel = document.getElementById('unitSelect');
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
