<?php
session_start();
require_once __DIR__ . '/../config.php';
if (!isset($_SESSION['llw_role'])) { header('Location: ' . $base_path . '/login.php'); exit(); }
if (!in_array($_SESSION['llw_role'], ['super_admin','att_teacher'])) {
    header('Location: ' . $base_path . '/login.php'); exit();
}

$pdo        = getPdo();
$is_admin   = $_SESSION['llw_role'] === 'super_admin';
$teacher_id = $_SESSION['teacher_id'] ?? 0;

// AJAX: delete submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['ajax'] ?? '') === 'delete') {
    header('Content-Type: application/json');
    try {
        $sub_id = (int)$_POST['sub_id'];
        $row = $pdo->prepare("SELECT id, file_paths FROM lms_student_exercises WHERE id=?");
        $row->execute([$sub_id]); $row = $row->fetch();
        if ($row) {
            if (!empty($row['file_paths'])) {
                $files = json_decode($row['file_paths'], true) ?: [$row['file_paths']];
                foreach ($files as $f) {
                    $fp = __DIR__ . '/../' . $f;
                    if (file_exists($fp)) @unlink($fp);
                }
            }
            $pdo->prepare("DELETE FROM lms_student_exercises WHERE id=?")->execute([$sub_id]);
        }
        echo json_encode(['ok' => true]);
    } catch (Exception $e) {
        error_log($e->getMessage());
        echo json_encode(['ok' => false]);
    }
    exit();
}

// AJAX: save grade + feedback
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['ajax'] ?? '') === 'save') {
    header('Content-Type: application/json');
    try {
        $sub_id   = (int)$_POST['sub_id'];
        $grade    = $_POST['grade'] !== '' ? max(0, (float)$_POST['grade']) : null;
        $feedback = trim($_POST['feedback'] ?? '') ?: null;
        $allowed_quality = ['น่าชื่นชมมาก!ครับ','ทำได้ดีมาก!ครับ','ดีขึ้นเรื่อยๆ เลย!ครับ','สู้ๆ ครูเชื่อในตัวเธอครับ','อย่าท้อ ลองใหม่นะครับ'];
        $quality  = in_array($_POST['quality'] ?? '', $allowed_quality) ? $_POST['quality'] : null;
        $has_quality = (bool)$pdo->query("SHOW COLUMNS FROM `lms_student_exercises` LIKE 'quality'")->fetch();
        if ($has_quality) {
            $pdo->prepare("UPDATE lms_student_exercises SET grade=?, feedback=?, quality=?, reviewed_at=NOW() WHERE id=?")
                ->execute([$grade, $feedback, $quality, $sub_id]);
        } else {
            $pdo->prepare("UPDATE lms_student_exercises SET grade=?, feedback=?, reviewed_at=NOW() WHERE id=?")
                ->execute([$grade, $feedback, $sub_id]);
        }
        echo json_encode(['ok' => true]);
    } catch (Exception $e) {
        error_log($e->getMessage());
        echo json_encode(['ok' => false]);
    }
    exit();
}

$subject_id  = (int)($_GET['subject_id'] ?? 0);
$exercise_id = (int)($_GET['exercise_id'] ?? 0);
$sel_class   = trim($_GET['class'] ?? '');

// Subjects accessible to this teacher
if ($is_admin) {
    $subjects = $pdo->query("SELECT * FROM lms_subjects ORDER BY subject_name")->fetchAll();
} else {
    $st = $pdo->prepare("SELECT * FROM lms_subjects WHERE teacher_id=? OR teacher_id IS NULL ORDER BY subject_name");
    $st->execute([$teacher_id]);
    $subjects = $st->fetchAll();
}

$subject = null; $exercises = []; $exercise = null; $submissions = []; $classes = [];

if ($subject_id) {
    $ss = $pdo->prepare("SELECT * FROM lms_subjects WHERE id=?"); $ss->execute([$subject_id]); $subject = $ss->fetch();

    if ($subject) {
        // Classrooms enrolled in this subject
        $cs = $pdo->prepare("SELECT classroom FROM lms_subject_classrooms WHERE subject_id=? ORDER BY classroom");
        $cs->execute([$subject_id]); $classes = $cs->fetchAll(PDO::FETCH_COLUMN);

        // All exercises in this subject, filtered by classroom assignment when a class is selected
        $_ex_cls_tbl = (bool)$pdo->query("SHOW TABLES LIKE 'lms_exercise_classrooms'")->fetch();

        $class_join = $sel_class
            ? "LEFT JOIN lms_student_exercises se ON se.exercise_id = e.id AND se.subject_id = ? AND se.student_uid IN (SELECT id FROM att_students WHERE classroom=? AND status='active')"
            : "LEFT JOIN lms_student_exercises se ON se.exercise_id = e.id AND se.subject_id = ?";

        // Only show exercises assigned to selected classroom (or with no classroom restriction)
        $_cls_ex_filter = ($sel_class && $_ex_cls_tbl)
            ? "AND (NOT EXISTS (SELECT 1 FROM lms_exercise_classrooms ec WHERE ec.exercise_id = e.id) OR EXISTS (SELECT 1 FROM lms_exercise_classrooms ec WHERE ec.exercise_id = e.id AND ec.classroom = ?))"
            : '';

        if ($sel_class) {
            // param order: se.subject_id, classroom (for JOIN), u.subject_id, classroom (for filter)
            $params = [$subject_id, $sel_class, $subject_id];
            if ($_ex_cls_tbl) $params[] = $sel_class;
        } else {
            $params = [$subject_id, $subject_id];
        }

        $_has_reviewed = (bool)$pdo->query("SHOW COLUMNS FROM `lms_student_exercises` LIKE 'reviewed_at'")->fetch();
        $_rev_expr     = $_has_reviewed ? 'SUM(CASE WHEN se.reviewed_at IS NOT NULL THEN 1 ELSE 0 END)' : '0';
        $exercises = $pdo->prepare("
            SELECT e.id, e.exercise_title, e.max_score, e.due_date, u.unit_name,
                   COUNT(se.id) AS sub_count,
                   {$_rev_expr} AS reviewed_count
            FROM lms_unit_exercises e
            JOIN lms_units u ON u.id = e.unit_id
            {$class_join}
            WHERE u.subject_id = ?
            {$_cls_ex_filter}
            GROUP BY e.id
            ORDER BY u.order_no, e.id
        ");
        $exercises->execute($params);
        $exercises = $exercises->fetchAll();
    }
}

$_lk_col      = (bool)$pdo->query("SHOW COLUMNS FROM `lms_student_exercises` LIKE 'link_url'")->fetch()    ? ', se.link_url'                                        : ", '' AS link_url";
$_grade_cols  = (bool)$pdo->query("SHOW COLUMNS FROM `lms_student_exercises` LIKE 'reviewed_at'")->fetch() ? ', se.grade, se.feedback, se.reviewed_at'               : ', NULL AS grade, NULL AS feedback, NULL AS reviewed_at';
$_quality_col = (bool)$pdo->query("SHOW COLUMNS FROM `lms_student_exercises` LIKE 'quality'")->fetch()     ? ', se.quality'                                           : ", '' AS quality";

// Pre-fetch enrolled count once to avoid N+1 in exercise list
$enrolled_count = 0;
if ($subject_id && $subject) {
    $ec = $pdo->prepare("SELECT COUNT(*) FROM lms_subject_classrooms sc JOIN att_students st ON st.classroom=sc.classroom WHERE sc.subject_id=? AND st.status='active'");
    $ec->execute([$subject_id]);
    $enrolled_count = (int)$ec->fetchColumn();
}

if ($subject_id && $exercise_id) {
    $ex_stmt = $pdo->prepare("SELECT e.*, u.unit_name FROM lms_unit_exercises e JOIN lms_units u ON u.id=e.unit_id WHERE e.id=? AND u.subject_id=?");
    $ex_stmt->execute([$exercise_id, $subject_id]); $exercise = $ex_stmt->fetch();

    if ($exercise) {
        $class_where = $sel_class ? "AND s.classroom = ?" : "";
        $q = $pdo->prepare("
            SELECT se.id AS sub_id, se.student_uid, se.answer_text, se.file_paths{$_lk_col}{$_grade_cols}{$_quality_col},
                   se.submitted_at,
                   s.name AS student_name, s.student_id AS student_no, s.classroom
            FROM lms_student_exercises se
            JOIN att_students s ON s.id = se.student_uid
            WHERE se.exercise_id = ? {$class_where}
            ORDER BY s.classroom, se.submitted_at DESC
        ");
        $params = $sel_class ? [$exercise_id, $sel_class] : [$exercise_id];
        $q->execute($params);
        $submissions = $q->fetchAll();
    }
}

$pageTitle    = 'ตรวจงานนักเรียน';
$pageSubtitle = 'ตรวจและให้คะแนนชิ้นงาน';
$activeSystem = 'lms';
require_once __DIR__ . '/../components/layout_start.php';
?>

<div class="flex flex-wrap items-center gap-3 mb-6">
  <div class="w-10 h-10 rounded-xl flex items-center justify-center shadow-lg bg-gradient-to-br from-violet-500 to-purple-600">
    <i class="bi bi-check2-square text-white text-lg"></i>
  </div>
  <div>
    <h2 class="text-lg font-black text-slate-800">ตรวจงานนักเรียน</h2>
    <p class="text-xs text-slate-400">ดูงานที่ส่ง · ให้คะแนน · แสดงความคิดเห็น</p>
  </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

  <!-- Left: subject + classroom + exercise list -->
  <div class="space-y-4">
    <!-- Subject selector -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-4">
      <p class="text-xs font-black text-slate-400 uppercase tracking-wider mb-3">เลือกวิชา</p>
      <?php if (empty($subjects)): ?>
      <p class="text-sm text-slate-400">ไม่มีวิชาที่จัดการได้</p>
      <?php else: ?>
      <div class="space-y-1">
        <?php foreach ($subjects as $s): ?>
        <a href="grade_exercises.php?subject_id=<?=$s['id']?>"
           class="block px-3 py-2 rounded-xl text-sm font-bold transition-all <?=$s['id']==$subject_id?'bg-violet-600 text-white':'text-slate-700 hover:bg-slate-50'?>">
          <?=htmlspecialchars($s['subject_name'],ENT_QUOTES,'UTF-8')?>
        </a>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>

    <!-- Classroom filter -->
    <?php if ($subject && !empty($classes)): ?>
    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-4">
      <p class="text-xs font-black text-slate-400 uppercase tracking-wider mb-3">กรองตามห้อง</p>
      <div class="flex flex-wrap gap-1.5">
        <a href="grade_exercises.php?subject_id=<?=$subject_id?><?=$exercise_id?'&exercise_id='.$exercise_id:''?>"
           class="px-3 py-1 rounded-full text-xs font-bold transition-all <?=$sel_class===''?'bg-violet-600 text-white':'bg-slate-100 text-slate-600 hover:bg-slate-200'?>">
          ทุกห้อง
        </a>
        <?php foreach ($classes as $cl): ?>
        <a href="grade_exercises.php?subject_id=<?=$subject_id?>&class=<?=urlencode($cl)?><?=$exercise_id?'&exercise_id='.$exercise_id:''?>"
           class="px-3 py-1 rounded-full text-xs font-bold transition-all <?=$sel_class===$cl?'bg-violet-600 text-white':'bg-slate-100 text-slate-600 hover:bg-slate-200'?>">
          <?=htmlspecialchars($cl,ENT_QUOTES,'UTF-8')?>
        </a>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <!-- Exercise list -->
    <?php if ($subject && !empty($exercises)): ?>
    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-4">
      <p class="text-xs font-black text-slate-400 uppercase tracking-wider mb-3">ชิ้นงาน</p>
      <div class="space-y-1.5">
        <?php foreach ($exercises as $e): ?>
        <?php $pending = $e['sub_count'] - $e['reviewed_count']; ?>
        <a href="grade_exercises.php?subject_id=<?=$subject_id?>&exercise_id=<?=$e['id']?><?=$sel_class?'&class='.urlencode($sel_class):''?>"
           class="flex items-center justify-between px-3 py-2.5 rounded-xl transition-all <?=$e['id']==$exercise_id?'bg-violet-600 text-white':'text-slate-700 hover:bg-slate-50 border border-slate-100'?>">
          <div class="flex-1 min-w-0">
            <p class="text-xs font-black truncate"><?=htmlspecialchars($e['exercise_title'],ENT_QUOTES,'UTF-8')?></p>
            <p class="text-[10px] opacity-70 truncate"><?=htmlspecialchars($e['unit_name'],ENT_QUOTES,'UTF-8')?></p>
          </div>
          <div class="flex items-center gap-1.5 ml-2 flex-shrink-0">
            <?php if ($pending > 0): ?>
            <span class="px-1.5 py-0.5 rounded-full text-[10px] font-black <?=$e['id']==$exercise_id?'bg-white/30 text-white':'bg-amber-100 text-amber-700'?>">
              <?=$pending?> รอ
            </span>
            <?php endif; ?>
            <span class="text-[10px] opacity-70"><?=$e['sub_count']?>/<?=$enrolled_count?></span>
          </div>
        </a>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>
  </div>

  <!-- Right: submissions -->
  <div class="lg:col-span-2">
    <?php if (!$subject): ?>
    <div class="bg-white rounded-2xl border border-slate-100 p-16 text-center text-slate-300 shadow-sm">
      <i class="bi bi-journal-check text-5xl block mb-3"></i>
      <p class="font-bold">เลือกวิชาก่อน</p>
    </div>

    <?php elseif (!$exercise_id || !$exercise): ?>
    <div class="bg-white rounded-2xl border border-slate-100 p-16 text-center text-slate-300 shadow-sm">
      <i class="bi bi-list-task text-5xl block mb-3"></i>
      <p class="font-bold">เลือกชิ้นงานจากรายการทางซ้าย</p>
    </div>

    <?php else: ?>
    <!-- Exercise header -->
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5 mb-4">
      <div class="flex items-start justify-between gap-3">
        <div>
          <h3 class="font-black text-slate-800"><?=htmlspecialchars($exercise['exercise_title'],ENT_QUOTES,'UTF-8')?></h3>
          <p class="text-xs text-slate-400 mt-0.5"><?=htmlspecialchars($exercise['unit_name'],ENT_QUOTES,'UTF-8')?></p>
        </div>
        <div class="flex gap-2 flex-shrink-0">
          <?php if ($exercise['max_score']): ?>
          <span class="px-3 py-1 bg-amber-50 text-amber-700 text-xs font-black rounded-full border border-amber-100">
            <i class="bi bi-star-fill mr-1"></i><?=$exercise['max_score']?> คะแนน
          </span>
          <?php endif; ?>
          <?php if ($sel_class): ?>
          <span class="px-3 py-1 bg-indigo-50 text-indigo-700 text-xs font-black rounded-full border border-indigo-100">
            <i class="bi bi-door-open mr-1"></i><?=htmlspecialchars($sel_class,ENT_QUOTES,'UTF-8')?>
          </span>
          <?php endif; ?>
          <span class="px-3 py-1 bg-violet-50 text-violet-700 text-xs font-black rounded-full border border-violet-100">
            <?=count($submissions)?> งานที่ส่ง
          </span>
        </div>
      </div>
      <?php if (!empty($exercise['description'])): ?>
      <div class="mt-3 bg-blue-50 border border-blue-100 rounded-xl px-3 py-2">
        <p class="text-xs text-blue-700"><?=nl2br(htmlspecialchars($exercise['description'],ENT_QUOTES,'UTF-8'))?></p>
      </div>
      <?php endif; ?>
    </div>

    <?php if (empty($submissions)): ?>
    <div class="bg-white rounded-2xl border border-slate-100 p-12 text-center text-slate-300 shadow-sm">
      <i class="bi bi-inbox text-4xl block mb-2"></i>
      <p class="font-bold text-sm">ยังไม่มีนักเรียนส่งงาน</p>
    </div>
    <?php else: ?>
    <div class="space-y-4" id="submissionList">
      <?php foreach ($submissions as $s): ?>
      <?php
        $sub_files = [];
        if (!empty($s['file_paths'])) {
            $sub_files = json_decode($s['file_paths'], true) ?: [$s['file_paths']];
        }
        $reviewed = !empty($s['reviewed_at']);
      ?>
      <div class="bg-white rounded-2xl border <?=$reviewed?'border-emerald-200':'border-amber-200'?> shadow-sm overflow-hidden" id="card_<?=$s['sub_id']?>">
        <!-- Student header -->
        <div class="flex items-center justify-between px-5 py-3 <?=$reviewed?'bg-emerald-50':'bg-amber-50'?> border-b <?=$reviewed?'border-emerald-100':'border-amber-100'?>" id="hdr_<?=$s['sub_id']?>">
          <div class="flex items-center gap-3">
            <div class="w-8 h-8 rounded-xl bg-white flex items-center justify-center border <?=$reviewed?'border-emerald-200':'border-amber-200'?>" id="icon_<?=$s['sub_id']?>">
              <i class="bi bi-person-fill text-sm <?=$reviewed?'text-emerald-600':'text-amber-600'?>" id="iconi_<?=$s['sub_id']?>"></i>
            </div>
            <div>
              <p class="text-sm font-black text-slate-800"><?=htmlspecialchars($s['student_name'],ENT_QUOTES,'UTF-8')?></p>
              <p class="text-[10px] text-slate-400"><?=htmlspecialchars($s['student_no'],ENT_QUOTES,'UTF-8')?> · <?=htmlspecialchars($s['classroom'],ENT_QUOTES,'UTF-8')?></p>
            </div>
          </div>
          <div class="text-right flex flex-col items-end gap-0.5">
            <span id="badge_<?=$s['sub_id']?>" class="px-2 py-0.5 <?=$reviewed?'bg-emerald-100 text-emerald-700':'bg-amber-100 text-amber-700'?> text-[10px] font-black rounded-full">
              <?php if ($reviewed): ?><i class="bi bi-check-circle-fill mr-0.5"></i>ตรวจแล้ว<?php else: ?>รอตรวจ<?php endif; ?>
            </span>
            <?php
              $qmap = ['น่าชื่นชมมาก!ครับ'=>'bg-violet-100 text-violet-700','ทำได้ดีมาก!ครับ'=>'bg-blue-100 text-blue-700','ดีขึ้นเรื่อยๆ เลย!ครับ'=>'bg-emerald-100 text-emerald-700','สู้ๆ ครูเชื่อในตัวเธอครับ'=>'bg-amber-100 text-amber-700','อย่าท้อ ลองใหม่นะครับ'=>'bg-rose-100 text-rose-700'];
              $qval = $s['quality'] ?? '';
            ?>
            <span id="qlabel_<?=$s['sub_id']?>" class="px-2 py-0.5 text-[10px] font-black rounded-full <?=$qval && isset($qmap[$qval]) ? $qmap[$qval] : 'hidden'?>">
              <?=htmlspecialchars($qval,ENT_QUOTES,'UTF-8')?>
            </span>
            <p class="text-xs font-black text-emerald-700" id="score_<?=$s['sub_id']?>"><?=$s['grade'] !== null ? $s['grade'].($exercise['max_score'] ? ' / '.$exercise['max_score'] : '').' คะแนน' : ''?></p>
            <p class="text-[10px] text-slate-400"><?=date('d/m H:i', strtotime($s['submitted_at']))?></p>
          </div>
        </div>

        <!-- Submission content -->
        <div class="px-5 py-4 space-y-3">
          <!-- Files -->
          <?php if (!empty($sub_files)): ?>
          <div class="space-y-2">
            <?php foreach ($sub_files as $fp):
              $fext      = strtolower(pathinfo($fp, PATHINFO_EXTENSION));
              $is_img    = in_array($fext, ['jpg','jpeg','png','gif','webp']);
              $is_vid    = in_array($fext, ['mp4','mov','avi','3gp','webm']);
              $file_disk = __DIR__ . '/../' . $fp;
              $file_ok   = file_exists($file_disk);
              $file_url  = htmlspecialchars($base_path . '/lms/serve_exercise.php?f=' . rawurlencode(basename($fp)), ENT_QUOTES, 'UTF-8');
            ?>
            <?php if (!$file_ok): ?>
            <div class="flex items-center gap-2 px-3 py-2 bg-rose-50 border border-rose-200 rounded-xl text-xs text-rose-700 font-bold">
              <i class="bi bi-exclamation-triangle-fill"></i>
              <span>ไฟล์ถูกลบออกจากเซิร์ฟเวอร์แล้ว — นักเรียนต้องส่งงานใหม่</span>
            </div>
            <?php elseif ($is_img): ?>
            <div>
              <a href="<?=$file_url?>" target="_blank">
                <img src="<?=$file_url?>"
                     class="max-w-full rounded-xl border border-slate-200 hover:opacity-80 transition-all"
                     style="max-height:300px"
                     onerror="this.style.display='none';this.nextElementSibling.style.display='flex'"
                     alt="">
              </a>
              <a href="<?=$file_url?>" target="_blank" style="display:none"
                 class="items-center gap-2 px-3 py-2.5 bg-blue-50 border border-blue-100 rounded-xl text-sm text-blue-700 font-bold hover:bg-blue-100 transition-all">
                <i class="bi bi-image text-xl flex-shrink-0"></i>
                <span>เปิดรูปภาพ (คลิก)</span>
                <i class="bi bi-box-arrow-up-right text-xs ml-auto"></i>
              </a>
            </div>
            <?php elseif ($is_vid): ?>
            <div class="space-y-1">
              <video src="<?=$file_url?>" controls
                     class="w-full rounded-xl border border-slate-200 max-h-56"
                     onerror="this.style.display='none';this.nextElementSibling.style.display='flex'"></video>
              <a href="<?=$file_url?>" download style="display:none"
                 class="items-center gap-2 px-3 py-2.5 bg-indigo-50 border border-indigo-100 rounded-xl text-sm text-indigo-700 font-bold hover:bg-indigo-100 transition-all">
                <i class="bi bi-download text-xl flex-shrink-0"></i>
                <span>ดาวน์โหลดวิดีโอ (เปิดด้วยโปรแกรมอื่น)</span>
              </a>
              <a href="<?=$file_url?>" target="_blank"
                 class="flex items-center gap-2 px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-500 font-bold hover:bg-slate-100 transition-all">
                <i class="bi bi-box-arrow-up-right"></i>
                <span>เปิดวิดีโอใน tab ใหม่ / ดาวน์โหลด</span>
              </a>
            </div>
            <?php else: ?>
            <a href="<?=$file_url?>" target="_blank"
               class="flex items-center gap-2 px-3 py-2 bg-red-50 border border-red-100 rounded-xl text-sm text-red-700 font-bold hover:bg-red-100 transition-all">
              <i class="bi bi-file-earmark-pdf text-xl"></i><span>ดู PDF</span>
            </a>
            <?php endif; ?>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>

          <!-- Link -->
          <?php if (!empty($s['link_url'])): ?>
          <a href="<?=htmlspecialchars($s['link_url'],ENT_QUOTES,'UTF-8')?>" target="_blank" rel="noopener"
             class="flex items-center gap-2 px-3 py-2.5 bg-violet-50 border border-violet-100 rounded-xl text-sm text-violet-700 font-bold hover:bg-violet-100 transition-all">
            <i class="bi bi-link-45deg text-xl flex-shrink-0"></i>
            <span class="truncate"><?=htmlspecialchars($s['link_url'],ENT_QUOTES,'UTF-8')?></span>
            <i class="bi bi-box-arrow-up-right text-xs ml-auto flex-shrink-0"></i>
          </a>
          <?php endif; ?>

          <!-- Text answer -->
          <?php if (!empty($s['answer_text'])): ?>
          <div class="bg-slate-50 rounded-xl px-4 py-3 border border-slate-100">
            <p class="text-[10px] font-black text-slate-400 mb-1">คำตอบ</p>
            <p class="text-sm text-slate-700 leading-relaxed whitespace-pre-wrap"><?=htmlspecialchars($s['answer_text'],ENT_QUOTES,'UTF-8')?></p>
          </div>
          <?php endif; ?>

          <!-- No content fallback -->
          <?php if (empty($sub_files) && empty($s['link_url']) && empty($s['answer_text'])): ?>
          <div class="bg-amber-50 border border-amber-100 rounded-xl px-4 py-3 text-center">
            <p class="text-xs text-amber-600 font-bold"><i class="bi bi-exclamation-circle mr-1"></i>ไม่พบเนื้อหาที่ส่ง — นักเรียนอาจส่งข้อมูลผ่านระบบเก่า</p>
            <p class="text-[10px] text-amber-500 mt-0.5">ขอให้นักเรียนส่งงานใหม่อีกครั้ง</p>
          </div>
          <?php endif; ?>

          <!-- Delete button -->
          <div class="flex justify-end">
            <button onclick="deleteSubmission(<?=$s['sub_id']?>)"
              class="flex items-center gap-1 px-3 py-1.5 text-[10px] font-bold text-rose-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition-all">
              <i class="bi bi-trash3"></i> ลบงานนี้
            </button>
          </div>

          <!-- Grade form -->
          <div class="bg-slate-50 rounded-xl p-4 border border-slate-100 space-y-2">
            <p class="text-xs font-black text-slate-500">ให้คะแนน / แสดงความคิดเห็น</p>
            <!-- Row 1: quality dropdown -->
            <select id="q_<?=$s['sub_id']?>"
              class="w-full border border-slate-200 rounded-xl px-3 py-2 text-sm font-bold focus:ring-2 focus:ring-violet-400 outline-none bg-white">
              <option value="">— ระดับคุณภาพ —</option>
              <?php foreach(['น่าชื่นชมมาก!ครับ','ทำได้ดีมาก!ครับ','ดีขึ้นเรื่อยๆ เลย!ครับ','สู้ๆ ครูเชื่อในตัวเธอครับ','อย่าท้อ ลองใหม่นะครับ'] as $ql): ?>
              <option value="<?=$ql?>" <?=($s['quality']??'')===$ql?'selected':''?>><?=$ql?></option>
              <?php endforeach; ?>
            </select>
            <!-- Row 2: score + feedback + save -->
            <div class="flex items-center gap-3">
              <div class="flex items-center gap-2 flex-shrink-0">
                <input type="number" min="0" max="<?=$exercise['max_score']??100?>"
                  value="<?=$s['grade']??''?>" placeholder="—"
                  class="w-20 border border-slate-200 rounded-xl px-3 py-2 text-sm font-bold text-center focus:ring-2 focus:ring-violet-400 outline-none"
                  id="g_<?=$s['sub_id']?>">
                <?php if ($exercise['max_score']): ?>
                <span class="text-xs text-slate-400 font-bold">/ <?=$exercise['max_score']?></span>
                <?php endif; ?>
              </div>
              <input type="text" value="<?=htmlspecialchars($s['feedback']??'',ENT_QUOTES,'UTF-8')?>"
                placeholder="ความคิดเห็น..."
                class="flex-1 border border-slate-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-violet-400 outline-none"
                id="f_<?=$s['sub_id']?>">
              <button onclick="saveFeedback(<?=$s['sub_id']?>)"
                class="px-4 py-2 <?=$reviewed?'bg-emerald-500 hover:bg-emerald-600':'bg-violet-600 hover:bg-violet-700'?> text-white font-bold text-xs rounded-xl transition-all flex-shrink-0"
                id="btn_<?=$s['sub_id']?>">
                <i class="bi bi-save mr-1"></i><?=$reviewed?'อัปเดต':'บันทึก'?>
              </button>
            </div>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <?php endif; ?>
  </div>
</div>

<script>
const QUALITY_CLASS = {
  'น่าชื่นชมมาก!ครับ':        'bg-violet-100 text-violet-700',
  'ทำได้ดีมาก!ครับ':          'bg-blue-100 text-blue-700',
  'ดีขึ้นเรื่อยๆ เลย!ครับ':           'bg-emerald-100 text-emerald-700',
  'สู้ๆ ครูเชื่อในตัวเธอครับ': 'bg-amber-100 text-amber-700',
  'อย่าท้อ ลองใหม่นะครับ':    'bg-rose-100 text-rose-700',
};

async function deleteSubmission(subId) {
  const r = await Swal.fire({
    title: 'ลบงานนี้?',
    text: 'ไฟล์และคำตอบของนักเรียนจะถูกลบถาวร นักเรียนสามารถส่งใหม่ได้',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#ef4444',
    cancelButtonColor: '#6b7280',
    confirmButtonText: 'ลบเลย',
    cancelButtonText: 'ยกเลิก',
  });
  if (!r.isConfirmed) return;
  const fd = new FormData();
  fd.append('ajax','delete'); fd.append('sub_id', subId);
  const res = await fetch('grade_exercises.php?subject_id=<?=$subject_id?>', {method:'POST', body:fd}).then(r=>r.json());
  if (res.ok) {
    const card = document.getElementById('card_' + subId);
    if (card) card.remove();
    Swal.fire({icon:'success', title:'ลบแล้ว', timer:1500, showConfirmButton:false});
  } else {
    Swal.fire({icon:'error', title:'เกิดข้อผิดพลาด', confirmButtonColor:'#7C3AED'});
  }
}

async function saveFeedback(subId) {
  const grade    = document.getElementById('g_' + subId).value;
  const feedback = document.getElementById('f_' + subId).value;
  const quality  = document.getElementById('q_' + subId)?.value ?? '';
  const btn      = document.getElementById('btn_' + subId);
  btn.disabled = true;
  btn.innerHTML = '<i class="bi bi-hourglass-split mr-1"></i>';
  const fd = new FormData();
  fd.append('ajax','save'); fd.append('sub_id', subId);
  fd.append('grade', grade); fd.append('feedback', feedback);
  fd.append('quality', quality);
  const res = await fetch('grade_exercises.php?subject_id=<?=$subject_id?>', {method:'POST', body:fd}).then(r=>r.json());
  if (res.ok) {
    btn.innerHTML = '<i class="bi bi-check-lg mr-1"></i>บันทึกแล้ว';
    btn.className = btn.className.replace('bg-violet-600 hover:bg-violet-700','bg-emerald-500 hover:bg-emerald-600');
    setTimeout(() => { btn.disabled = false; btn.innerHTML = '<i class="bi bi-save mr-1"></i>อัปเดต'; }, 2000);

    // Update status badge
    const badge = document.getElementById('badge_' + subId);
    if (badge) {
      badge.className = 'px-2 py-0.5 bg-emerald-100 text-emerald-700 text-[10px] font-black rounded-full';
      badge.innerHTML = '<i class="bi bi-check-circle-fill mr-0.5"></i>ตรวจแล้ว';
    }
    // Update quality badge
    const qlabel = document.getElementById('qlabel_' + subId);
    if (qlabel) {
      if (quality && QUALITY_CLASS[quality]) {
        qlabel.className = 'px-2 py-0.5 text-[10px] font-black rounded-full ' + QUALITY_CLASS[quality];
        qlabel.textContent = quality;
      } else {
        qlabel.className = 'hidden';
        qlabel.textContent = '';
      }
    }
    // Update card / header colors
    const card = document.getElementById('card_' + subId);
    if (card) card.className = card.className.replace('border-amber-200','border-emerald-200');
    const hdr = document.getElementById('hdr_' + subId);
    if (hdr) hdr.className = hdr.className.replace('bg-amber-50','bg-emerald-50').replace('border-amber-100','border-emerald-100');
    const icon = document.getElementById('icon_' + subId);
    if (icon) icon.className = icon.className.replace('border-amber-200','border-emerald-200');
    const iconi = document.getElementById('iconi_' + subId);
    if (iconi) iconi.className = iconi.className.replace('text-amber-600','text-emerald-600');
    const scoreEl = document.getElementById('score_' + subId);
    if (scoreEl && grade !== '') scoreEl.textContent = grade + '<?=$exercise['max_score'] ? ' / '.$exercise['max_score'] : ''?> คะแนน';
  } else {
    btn.disabled = false; btn.innerHTML = '<i class="bi bi-save mr-1"></i>ลองใหม่';
    Swal.fire({icon:'error', title:'เกิดข้อผิดพลาด', confirmButtonColor:'#7C3AED'});
  }
}
</script>

<?php require_once __DIR__ . '/../components/layout_end.php'; ?>
