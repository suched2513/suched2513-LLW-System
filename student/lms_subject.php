<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/_guard.php';

$pdo   = getPdo();
$uid   = (int)$_SESSION['student_uid'];
$name  = $_SESSION['student_name'];
$class = $_SESSION['student_class'];

$subject_id = (int)($_GET['subject_id'] ?? 0);
if (!$subject_id) { header('Location: /student/lms.php'); exit(); }

$ss = $pdo->prepare("SELECT s.* FROM lms_subjects s JOIN lms_subject_classrooms sc ON sc.subject_id=s.id WHERE s.id=? AND sc.classroom=? LIMIT 1");
$ss->execute([$subject_id, $class]);
$subject = $ss->fetch();
if (!$subject) { header('Location: /student/lms.php'); exit(); }

// ── POST: submit exercise ──────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['exercise_id'])) {
    $ex_id  = (int)$_POST['exercise_id'];
    $uid_p  = (int)$_POST['unit_id'];
    $answer = trim($_POST['answer_text'] ?? '');
    $link   = trim($_POST['link_url'] ?? '');
    if ($link !== '' && !filter_var($link, FILTER_VALIDATE_URL)) $link = '';
    $files = [];
    $allowed_mime = ['image/jpeg','image/png','image/gif','image/webp','application/pdf',
                     'video/mp4','video/quicktime','video/x-msvideo','video/3gpp','video/webm'];
    $allowed_ext  = ['jpg','jpeg','png','gif','webp','pdf','mp4','mov','avi','3gp','webm'];
    $dir = __DIR__ . '/../lms/uploads/exercises/';
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    if (!empty($_FILES['exercise_files']['name'][0])) {
        for ($i = 0; $i < min(count($_FILES['exercise_files']['name']), 3); $i++) {
            if ($_FILES['exercise_files']['error'][$i] !== UPLOAD_ERR_OK) continue;
            $fi = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($fi, $_FILES['exercise_files']['tmp_name'][$i]);
            finfo_close($fi);
            $ext = strtolower(pathinfo($_FILES['exercise_files']['name'][$i], PATHINFO_EXTENSION));
            if (!in_array($mime, $allowed_mime) || !in_array($ext, $allowed_ext)) continue;
            $max = strpos($mime, 'video/') === 0 ? 100 * 1024 * 1024 : 10 * 1024 * 1024;
            if ($_FILES['exercise_files']['size'][$i] > $max) continue;
            $fn = $uid . '_' . $ex_id . '_' . time() . '_' . $i . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            if (move_uploaded_file($_FILES['exercise_files']['tmp_name'][$i], $dir . $fn))
                $files[] = 'lms/uploads/exercises/' . $fn;
        }
    }
    $fp = !empty($files) ? json_encode($files) : null;
    $_sub_has_link = (bool)$pdo->query("SHOW COLUMNS FROM `lms_student_exercises` LIKE 'link_url'")->fetch();
    if ($answer !== '' || !empty($files) || ($link !== '' && $_sub_has_link)) {
        $eq = $pdo->prepare("SELECT id, file_paths FROM lms_student_exercises WHERE student_uid=? AND exercise_id=? LIMIT 1");
        $eq->execute([$uid, $ex_id]); $existing = $eq->fetch();
        if ($existing) {
            if (!empty($files) && !empty($existing['file_paths'])) {
                foreach (json_decode($existing['file_paths'], true) ?? [] as $of) {
                    $op = __DIR__ . '/../' . $of; if (file_exists($op)) @unlink($op);
                }
            }
            if ($_sub_has_link) {
                $pdo->prepare("UPDATE lms_student_exercises SET answer_text=?,file_paths=COALESCE(?,file_paths),link_url=?,submitted_at=NOW() WHERE student_uid=? AND exercise_id=?")
                    ->execute([$answer, $fp, $link ?: null, $uid, $ex_id]);
            } else {
                $pdo->prepare("UPDATE lms_student_exercises SET answer_text=?,file_paths=COALESCE(?,file_paths),submitted_at=NOW() WHERE student_uid=? AND exercise_id=?")
                    ->execute([$answer, $fp, $uid, $ex_id]);
            }
        } else {
            if ($_sub_has_link) {
                $pdo->prepare("INSERT INTO lms_student_exercises (student_uid,exercise_id,unit_id,subject_id,answer_text,file_paths,link_url) VALUES (?,?,?,?,?,?,?)")
                    ->execute([$uid, $ex_id, $uid_p, $subject_id, $answer, $fp, $link ?: null]);
            } else {
                $pdo->prepare("INSERT INTO lms_student_exercises (student_uid,exercise_id,unit_id,subject_id,answer_text,file_paths) VALUES (?,?,?,?,?,?)")
                    ->execute([$uid, $ex_id, $uid_p, $subject_id, $answer, $fp]);
            }
        }
        header('Location: /student/lms_subject.php?subject_id=' . $subject_id . '&done=1'); exit();
    }
    header('Location: /student/lms_subject.php?subject_id=' . $subject_id . '&err=1'); exit();
}

// ── Exam settings ──────────────────────────────────────────
$es = $pdo->prepare("SELECT * FROM lms_exam_settings WHERE subject_id=?");
$es->execute([$subject_id]); $settings = $es->fetch();
$pre_pass  = (int)($settings['pre_pass_score']  ?? 6);
$post_pass = (int)($settings['post_pass_score'] ?? 6);
$max_att   = (int)($settings['post_max_attempts'] ?? 3);
$now_ts    = time();
$open_ts   = !empty($settings['post_exam_open_at'])  ? strtotime($settings['post_exam_open_at'])  : null;
$close_ts  = !empty($settings['post_exam_close_at']) ? strtotime($settings['post_exam_close_at']) : null;
$post_in_window = (!$open_ts || $now_ts >= $open_ts) && (!$close_ts || $now_ts <= $close_ts);

$stmt = $pdo->prepare("SELECT COUNT(*) FROM lms_pre_questions WHERE subject_id=?");
$stmt->execute([$subject_id]); $total_pre = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM lms_post_questions WHERE subject_id=?");
$stmt->execute([$subject_id]); $total_post = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT score,total FROM lms_student_pre_exam WHERE student_uid=? AND subject_id=? AND passed=1 LIMIT 1");
$stmt->execute([$uid, $subject_id]); $pre_passed = $stmt->fetch();
if (!$pre_passed && $total_pre === 0) $pre_passed = ['score' => 0, 'total' => 0, 'auto' => true];

$stmt = $pdo->prepare("SELECT score,total FROM lms_student_post_exam WHERE student_uid=? AND subject_id=? AND passed=1 LIMIT 1");
$stmt->execute([$uid, $subject_id]); $post_passed = $stmt->fetch();
if (!$post_passed && $total_post === 0) $post_passed = ['score' => 0, 'total' => 0, 'auto' => true];

$stmt = $pdo->prepare("SELECT COUNT(*) FROM lms_student_post_exam WHERE student_uid=? AND subject_id=?");
$stmt->execute([$uid, $subject_id]); $post_att_count = (int)$stmt->fetchColumn();

// ── Exercises feed (newest first) ─────────────────────────
$stmt = $pdo->prepare("
    SELECT e.id, e.exercise_title, e.description, e.max_score, e.due_date, e.allow_resubmit,
           u.id AS unit_id, u.unit_name, u.order_no
    FROM lms_unit_exercises e
    JOIN lms_units u ON u.id = e.unit_id
    WHERE u.subject_id = ?
    ORDER BY e.id DESC
");
$stmt->execute([$subject_id]); $all_exercises = $stmt->fetchAll();

$_sub_lk_col  = (bool)$pdo->query("SHOW COLUMNS FROM `lms_student_exercises` LIKE 'link_url'")->fetch()   ? ', link_url'                             : ", '' AS link_url";
$_sub_gr_cols = (bool)$pdo->query("SHOW COLUMNS FROM `lms_student_exercises` LIKE 'reviewed_at'")->fetch() ? ', grade, feedback, reviewed_at'          : ', NULL AS grade, NULL AS feedback, NULL AS reviewed_at';
$stmt = $pdo->prepare("SELECT exercise_id, answer_text, file_paths{$_sub_lk_col}{$_sub_gr_cols}, submitted_at FROM lms_student_exercises WHERE student_uid=? AND subject_id=?");
$stmt->execute([$uid, $subject_id]); $submissions = array_column($stmt->fetchAll(), null, 'exercise_id');

$cnt_pending = count(array_filter($all_exercises, fn($e) => !isset($submissions[$e['id']])));

// ── Score summary ──────────────────────────────────────────
$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(se.grade),0) AS earned, COALESCE(SUM(e.max_score),0) AS possible
    FROM lms_unit_exercises e JOIN lms_units u ON u.id=e.unit_id
    LEFT JOIN lms_student_exercises se ON se.exercise_id=e.id AND se.student_uid=? AND se.subject_id=? AND se.reviewed_at IS NOT NULL
    WHERE u.subject_id=? AND e.max_score > 0
");
$stmt->execute([$uid, $subject_id, $subject_id]); $sc = $stmt->fetch();
$score_earned   = (float)($sc['earned']   ?? 0);
$score_possible = (int)($sc['possible']   ?? 0);

// ── Units + Topics (batch, no N+1) ────────────────────────
$stmt = $pdo->prepare("SELECT * FROM lms_units WHERE subject_id=? ORDER BY order_no");
$stmt->execute([$subject_id]); $units = $stmt->fetchAll();

$topics_by_unit = []; $unit_ex_progress = [];
if (!empty($units)) {
    $uids = array_column($units, 'id');
    $uph  = implode(',', array_fill(0, count($uids), '?'));

    $stmt = $pdo->prepare("SELECT * FROM lms_topics WHERE unit_id IN ($uph) ORDER BY unit_id, order_no");
    $stmt->execute($uids); $all_topics = $stmt->fetchAll();

    $links_map = []; $yt_map = []; $files_map = [];
    $tids = array_column($all_topics, 'id');
    if (!empty($tids)) {
        $tph = implode(',', array_fill(0, count($tids), '?'));
        $stmt = $pdo->prepare("SELECT * FROM lms_topic_links WHERE topic_id IN ($tph) ORDER BY id");
        $stmt->execute($tids);
        foreach ($stmt->fetchAll() as $r) $links_map[$r['topic_id']][] = $r;

        $stmt = $pdo->prepare("SELECT * FROM lms_topic_youtube WHERE topic_id IN ($tph) ORDER BY id");
        $stmt->execute($tids);
        foreach ($stmt->fetchAll() as $r) $yt_map[$r['topic_id']][] = $r;

        $stmt = $pdo->prepare("SELECT * FROM lms_topic_files WHERE topic_id IN ($tph) ORDER BY id");
        $stmt->execute($tids);
        foreach ($stmt->fetchAll() as $r) $files_map[$r['topic_id']][] = $r;
    }
    foreach ($all_topics as $t) {
        $t['links']   = $links_map[$t['id']] ?? [];
        $t['youtube'] = $yt_map[$t['id']]    ?? [];
        $t['files']   = $files_map[$t['id']] ?? [];
        $topics_by_unit[$t['unit_id']][] = $t;
    }

    foreach ($units as $u) {
        $exs = array_filter($all_exercises, fn($e) => $e['unit_id'] == $u['id']);
        $total = count($exs);
        $done  = count(array_filter($exs, fn($e) => isset($submissions[$e['id']])));
        $unit_ex_progress[$u['id']] = ['total' => $total, 'done' => $done];
    }
}

$total_ex  = count($all_exercises);
$total_sub = count(array_filter($all_exercises, fn($e) => isset($submissions[$e['id']])));

// Default active tab
$default_tab = $pre_passed ? 'work' : 'progress';
if (isset($_GET['tab']) && in_array($_GET['tab'], ['work', 'content', 'progress']))
    $default_tab = $_GET['tab'];
?><!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<title><?=htmlspecialchars($subject['subject_name'],ENT_QUOTES,'UTF-8')?> | LMS</title>
<meta name="theme-color" content="#7C3AED">
<link href="https://fonts.googleapis.com/css2?family=Prompt:wght@400;600;700;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<script src="https://cdn.tailwindcss.com"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
body { font-family: 'Prompt', sans-serif; }
.tab-btn.active { background:#7C3AED; color:#fff; box-shadow:0 4px 14px #7C3AED40; }
.tab-btn { background:#f1f5f9; color:#64748b; }
.tab-pane { display:none; }
.tab-pane.active { display:block; }
.topic-body { display:none; }
.ex-form { display:none; }
.yt-frame { aspect-ratio:16/9; width:100%; border-radius:12px; border:0; }
</style>
</head>
<body class="min-h-screen bg-slate-50 pb-10" style="padding-top:env(safe-area-inset-top)">

<?php if (isset($_GET['done'])): ?>
<script>window.addEventListener('load',()=>{Swal.fire({icon:'success',title:'ส่งงานแล้ว!',confirmButtonColor:'#7C3AED',timer:2000,showConfirmButton:false});});</script>
<?php elseif (isset($_GET['err'])): ?>
<script>window.addEventListener('load',()=>{Swal.fire({icon:'warning',title:'กรุณาส่งงาน',text:'พิมพ์คำตอบหรือแนบไฟล์อย่างน้อย 1 อย่าง',confirmButtonColor:'#7C3AED'});});</script>
<?php endif; ?>

<!-- ── Header ───────────────────────────────────────────── -->
<div class="text-white px-5 pt-5 pb-6 shadow-xl" style="background:linear-gradient(135deg,#7C3AED,#4F46E5)">
  <div class="flex items-center gap-3 mb-3">
    <a href="/student/lms.php"
       class="w-9 h-9 bg-white/15 rounded-xl flex items-center justify-center border border-white/20 active:bg-white/25 flex-shrink-0">
      <i class="bi bi-arrow-left"></i>
    </a>
    <div class="flex-1 min-w-0">
      <h1 class="font-black text-base leading-tight truncate"><?=htmlspecialchars($subject['subject_name'],ENT_QUOTES,'UTF-8')?></h1>
      <p class="text-violet-200 text-xs font-bold"><?=htmlspecialchars($name,ENT_QUOTES,'UTF-8')?> · <?=htmlspecialchars($class,ENT_QUOTES,'UTF-8')?></p>
    </div>
    <?php if ($cnt_pending > 0): ?>
    <span class="flex-shrink-0 px-2.5 py-1 bg-amber-400/30 border border-amber-200/40 rounded-full text-xs font-black animate-pulse">
      <?=$cnt_pending?> ค้าง
    </span>
    <?php endif; ?>
  </div>
  <!-- Overall progress bar -->
  <?php if ($total_ex > 0): ?>
  <?php $pct = round($total_sub / $total_ex * 100); ?>
  <div class="mb-1 flex justify-between text-[10px] font-bold opacity-80">
    <span>ความคืบหน้า</span>
    <span><?=$total_sub?>/<?=$total_ex?> ใบงาน (<?=$pct?>%)</span>
  </div>
  <div class="w-full bg-white/20 rounded-full h-2">
    <div class="h-2 rounded-full bg-white transition-all" style="width:<?=$pct?>%"></div>
  </div>
  <?php endif; ?>
</div>

<!-- ── Sticky Tab Bar ────────────────────────────────────── -->
<div class="sticky top-0 z-40 bg-white border-b border-slate-100 shadow-sm px-4 py-2.5">
  <div class="flex gap-2 max-w-lg mx-auto">
    <button onclick="switchTab('work')" id="tab-work"
      class="tab-btn <?=$default_tab==='work'?'active':''?> flex-1 py-2 rounded-xl text-xs font-black transition-all flex items-center justify-center gap-1.5">
      <i class="bi bi-list-task"></i> งาน
      <?php if ($cnt_pending > 0): ?><span class="bg-amber-400 text-white text-[10px] px-1.5 py-0.5 rounded-full leading-none"><?=$cnt_pending?></span><?php endif; ?>
    </button>
    <button onclick="switchTab('content')" id="tab-content"
      class="tab-btn <?=$default_tab==='content'?'active':''?> flex-1 py-2 rounded-xl text-xs font-black transition-all flex items-center justify-center gap-1.5">
      <i class="bi bi-book-half"></i> เนื้อหา
    </button>
    <button onclick="switchTab('progress')" id="tab-progress"
      class="tab-btn <?=$default_tab==='progress'?'active':''?> flex-1 py-2 rounded-xl text-xs font-black transition-all flex items-center justify-center gap-1.5">
      <i class="bi bi-bar-chart-fill"></i> ผลงาน
    </button>
  </div>
</div>

<div class="max-w-lg mx-auto px-4 pt-4 space-y-3">

<!-- ════════════════════════════════════════════════════════
     TAB: งาน
     ════════════════════════════════════════════════════════ -->
<div id="pane-work" class="tab-pane <?=$default_tab==='work'?'active':''?>">

  <?php if (!$pre_passed && $total_pre > 0): ?>
  <!-- Pre-exam gate — friendly locked state -->
  <div class="bg-white rounded-2xl border-2 border-amber-200 p-6 text-center shadow-sm">
    <div class="w-14 h-14 bg-amber-100 rounded-2xl flex items-center justify-center mx-auto mb-3">
      <i class="bi bi-lock-fill text-amber-500 text-2xl"></i>
    </div>
    <p class="font-black text-slate-800 mb-1">ทำแบบทดสอบก่อนเรียนก่อน</p>
    <p class="text-xs text-slate-400 mb-4">ทำแบบทดสอบก่อนเรียนเพื่อปลดล็อกใบงานทั้งหมด</p>
    <a href="/student/lms_pre_exam.php?subject_id=<?=$subject_id?>"
       class="inline-flex items-center gap-2 px-6 py-3 bg-violet-600 text-white font-bold text-sm rounded-2xl shadow-lg shadow-violet-200 active:scale-95 transition-transform">
      <i class="bi bi-play-circle-fill"></i> เริ่มทำแบบทดสอบ
    </a>
  </div>

  <?php elseif (empty($all_exercises)): ?>
  <div class="bg-white rounded-2xl border border-slate-100 p-12 text-center shadow-sm">
    <i class="bi bi-inbox text-slate-200 text-5xl mb-3 block"></i>
    <p class="text-slate-400 font-bold text-sm">ครูยังไม่ได้สั่งงาน</p>
  </div>

  <?php else: ?>
  <!-- Pending exercises first -->
  <?php
  $pending_exs  = array_filter($all_exercises, fn($e) => !isset($submissions[$e['id']]));
  $finished_exs = array_filter($all_exercises, fn($e) =>  isset($submissions[$e['id']]));
  ?>

  <?php if (!empty($pending_exs)): ?>
  <div class="flex items-center gap-2 px-1 mb-1">
    <span class="w-2 h-2 rounded-full bg-amber-400"></span>
    <p class="text-xs font-black text-slate-500 uppercase tracking-wider">รอส่ง (<?=count($pending_exs)?>)</p>
  </div>
  <?php foreach ($pending_exs as $ex): ?>
  <?php $due_ts = $ex['due_date'] ? strtotime($ex['due_date']) : null;
        $overdue  = $due_ts && $now_ts > $due_ts;
        $due_soon = $due_ts && !$overdue && ($due_ts - $now_ts) < 86400; ?>
  <div class="bg-white rounded-2xl border-2 border-amber-200 shadow-sm overflow-hidden">
    <div class="p-4">
      <div class="flex items-start gap-3">
        <div class="w-9 h-9 rounded-xl bg-amber-100 flex items-center justify-center flex-shrink-0">
          <i class="bi bi-pencil-fill text-amber-500"></i>
        </div>
        <div class="flex-1 min-w-0">
          <p class="font-black text-slate-800 text-sm leading-snug"><?=htmlspecialchars($ex['exercise_title'],ENT_QUOTES,'UTF-8')?></p>
          <p class="text-[10px] text-slate-400 mt-0.5">หน่วย <?=$ex['order_no']?>: <?=htmlspecialchars($ex['unit_name'],ENT_QUOTES,'UTF-8')?></p>
          <div class="flex flex-wrap gap-1.5 mt-2">
            <?php if ($ex['max_score']): ?>
            <span class="px-2 py-0.5 bg-violet-50 text-violet-600 text-[10px] font-black rounded-full">
              <i class="bi bi-star-fill mr-0.5"></i><?=$ex['max_score']?> คะแนน
            </span>
            <?php endif; ?>
            <?php if ($due_ts): ?>
            <span class="px-2 py-0.5 text-[10px] font-black rounded-full <?=$overdue?'bg-rose-100 text-rose-600':($due_soon?'bg-amber-100 text-amber-600':'bg-slate-100 text-slate-500')?>">
              <i class="bi bi-clock mr-0.5"></i>
              <?=$overdue?'เลยกำหนด':($due_soon?'ใกล้หมด · '.date('d/m H:i',$due_ts):'ส่งภายใน '.date('d/m/Y',$due_ts))?>
            </span>
            <?php endif; ?>
          </div>
        </div>
        <span class="flex-shrink-0 px-2.5 py-1 bg-amber-100 text-amber-700 text-[10px] font-black rounded-full">รอส่ง</span>
      </div>
      <?php if (!empty($ex['description'])): ?>
      <div class="mt-3 bg-blue-50 border border-blue-100 rounded-xl px-3 py-2">
        <p class="text-xs text-blue-700 leading-relaxed"><?=nl2br(htmlspecialchars($ex['description'],ENT_QUOTES,'UTF-8'))?></p>
      </div>
      <?php endif; ?>
    </div>
    <!-- Submit button -->
    <div class="px-4 pb-4">
      <button onclick="toggleForm(<?=$ex['id']?>)"
        class="w-full py-2.5 bg-violet-600 text-white font-black text-sm rounded-xl shadow-md shadow-violet-200 active:scale-95 transition-transform flex items-center justify-center gap-2">
        <i class="bi bi-send-fill"></i> ส่งงาน
      </button>
    </div>
    <!-- Inline form (hidden by default) -->
    <form id="form_<?=$ex['id']?>" class="ex-form border-t border-slate-100 px-4 py-4 space-y-3 bg-slate-50"
          method="POST" enctype="multipart/form-data"
          onsubmit="return validateForm(this,<?=$ex['id']?>)">
      <input type="hidden" name="exercise_id" value="<?=$ex['id']?>">
      <input type="hidden" name="unit_id"     value="<?=$ex['unit_id']?>">
      <!-- File -->
      <input type="file" id="fi_<?=$ex['id']?>" name="exercise_files[]"
             accept="image/*,video/*,.pdf" multiple class="hidden"
             onchange="previewFiles(this,<?=$ex['id']?>)">
      <label for="fi_<?=$ex['id']?>"
        class="flex items-center justify-center gap-2 w-full py-3 border-2 border-dashed border-violet-300 rounded-xl text-violet-500 font-bold text-sm cursor-pointer hover:bg-violet-50 transition-all active:opacity-70">
        <i class="bi bi-camera-fill text-lg"></i> ถ่ายรูป / แนบไฟล์ / วีดีโอ
      </label>
      <div id="fp_<?=$ex['id']?>" class="space-y-2"></div>
      <!-- Link -->
      <input type="url" name="link_url" placeholder="🔗 ลิงค์ YouTube / Google Drive"
        class="w-full border border-slate-200 rounded-xl px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-violet-400 bg-white">
      <!-- Text -->
      <textarea name="answer_text" rows="3" placeholder="✏️ พิมพ์คำตอบ (ถ้ามี)"
        class="w-full border border-slate-200 rounded-xl px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-violet-400 resize-none bg-white"></textarea>
      <!-- Buttons -->
      <div class="flex gap-2">
        <button type="button" onclick="toggleForm(<?=$ex['id']?>)"
          class="px-4 py-2.5 border border-slate-200 text-slate-500 font-bold text-xs rounded-xl active:opacity-70">ยกเลิก</button>
        <button type="submit"
          class="flex-1 py-2.5 bg-violet-600 text-white font-black text-sm rounded-xl shadow-md shadow-violet-200 active:scale-95 transition-transform">
          <i class="bi bi-send-fill mr-1"></i> ยืนยันส่งงาน
        </button>
      </div>
    </form>
  </div>
  <?php endforeach; ?>
  <?php endif; ?>

  <!-- Finished exercises -->
  <?php if (!empty($finished_exs)): ?>
  <div class="flex items-center gap-2 px-1 mt-4 mb-1">
    <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
    <p class="text-xs font-black text-slate-500 uppercase tracking-wider">ส่งแล้ว / ตรวจแล้ว (<?=count($finished_exs)?>)</p>
  </div>
  <?php foreach ($finished_exs as $ex):
    $sub      = $submissions[$ex['id']];
    $reviewed = $sub['reviewed_at'] !== null;
    $can_edit = (bool)$ex['allow_resubmit'];
  ?>
  <div class="bg-white rounded-2xl border-2 <?=$reviewed?'border-violet-200':'border-emerald-200'?> shadow-sm overflow-hidden">
    <div class="p-4">
      <div class="flex items-start gap-3">
        <div class="w-9 h-9 rounded-xl <?=$reviewed?'bg-violet-100':'bg-emerald-100'?> flex items-center justify-center flex-shrink-0">
          <i class="bi bi-<?=$reviewed?'patch-check-fill text-violet-500':'check-circle-fill text-emerald-500'?> text-lg"></i>
        </div>
        <div class="flex-1 min-w-0">
          <p class="font-black text-slate-800 text-sm leading-snug"><?=htmlspecialchars($ex['exercise_title'],ENT_QUOTES,'UTF-8')?></p>
          <p class="text-[10px] text-slate-400 mt-0.5">หน่วย <?=$ex['order_no']?>: <?=htmlspecialchars($ex['unit_name'],ENT_QUOTES,'UTF-8')?></p>
        </div>
        <?php if ($reviewed): ?>
        <span class="flex-shrink-0 px-2.5 py-1 bg-violet-100 text-violet-700 text-[10px] font-black rounded-full whitespace-nowrap">
          <?php if ($sub['grade'] !== null): ?>
          <i class="bi bi-star-fill mr-0.5"></i><?=$sub['grade']?><?=$ex['max_score']?' / '.$ex['max_score']:'?'?>
          <?php else: ?><i class="bi bi-patch-check-fill mr-0.5"></i>ตรวจแล้ว<?php endif; ?>
        </span>
        <?php else: ?>
        <span class="flex-shrink-0 px-2.5 py-1 bg-emerald-100 text-emerald-700 text-[10px] font-black rounded-full">ส่งแล้ว</span>
        <?php endif; ?>
      </div>

      <!-- Teacher feedback -->
      <?php if ($reviewed && !empty($sub['feedback'])): ?>
      <div class="mt-3 bg-violet-50 border border-violet-100 rounded-xl px-3 py-2">
        <p class="text-[10px] font-black text-violet-500 mb-1"><i class="bi bi-chat-quote-fill mr-1"></i>ความคิดเห็นครู</p>
        <p class="text-xs text-violet-700 leading-relaxed"><?=nl2br(htmlspecialchars($sub['feedback'],ENT_QUOTES,'UTF-8'))?></p>
      </div>
      <?php endif; ?>

      <!-- What student submitted -->
      <?php $has_content = !empty($sub['answer_text']) || !empty($sub['file_paths']) || !empty($sub['link_url']); ?>
      <?php if ($has_content): ?>
      <div class="mt-3 bg-slate-50 border border-slate-100 rounded-xl p-3 space-y-2">
        <?php if (!empty($sub['answer_text'])): ?>
        <p class="text-xs text-slate-600 leading-relaxed line-clamp-3"><?=nl2br(htmlspecialchars($sub['answer_text'],ENT_QUOTES,'UTF-8'))?></p>
        <?php endif; ?>
        <?php if (!empty($sub['file_paths'])): ?>
        <?php $sfiles = json_decode($sub['file_paths'], true) ?? [$sub['file_paths']]; ?>
        <?php foreach ($sfiles as $fp2):
              $fext = strtolower(pathinfo($fp2, PATHINFO_EXTENSION)); ?>
        <?php if (in_array($fext,['jpg','jpeg','png','gif','webp'])): ?>
        <img src="/<?=htmlspecialchars($fp2,ENT_QUOTES,'UTF-8')?>" class="w-full max-h-40 object-contain rounded-xl bg-white border border-slate-100" alt="">
        <?php elseif (in_array($fext,['mp4','mov','avi','3gp','webm'])): ?>
        <video src="/<?=htmlspecialchars($fp2,ENT_QUOTES,'UTF-8')?>" controls class="w-full rounded-xl max-h-40 border border-slate-100"></video>
        <?php else: ?>
        <a href="/<?=htmlspecialchars($fp2,ENT_QUOTES,'UTF-8')?>" target="_blank" rel="noopener"
           class="flex items-center gap-2 text-xs text-blue-600 font-bold">
          <i class="bi bi-file-earmark-pdf text-red-500"></i><span class="underline">ดูไฟล์แนบ</span>
        </a>
        <?php endif; ?>
        <?php endforeach; ?>
        <?php endif; ?>
        <?php if (!empty($sub['link_url'])): ?>
        <a href="<?=htmlspecialchars($sub['link_url'],ENT_QUOTES,'UTF-8')?>" target="_blank" rel="noopener"
           class="flex items-center gap-2 text-xs text-blue-600 font-bold">
          <i class="bi bi-link-45deg text-violet-500 text-base"></i>
          <span class="underline truncate"><?=htmlspecialchars($sub['link_url'],ENT_QUOTES,'UTF-8')?></span>
        </a>
        <?php endif; ?>
      </div>
      <?php endif; ?>
    </div>

    <?php if ($can_edit): ?>
    <div class="px-4 pb-4">
      <button onclick="toggleForm(<?=$ex['id']?>)"
        class="w-full py-2 border border-slate-200 text-slate-500 font-bold text-xs rounded-xl active:opacity-70 flex items-center justify-center gap-1.5">
        <i class="bi bi-pencil"></i> แก้ไขงาน
      </button>
    </div>
    <form id="form_<?=$ex['id']?>" class="ex-form border-t border-slate-100 px-4 py-4 space-y-3 bg-slate-50"
          method="POST" enctype="multipart/form-data"
          onsubmit="return validateForm(this,<?=$ex['id']?>)">
      <input type="hidden" name="exercise_id" value="<?=$ex['id']?>">
      <input type="hidden" name="unit_id"     value="<?=$ex['unit_id']?>">
      <input type="file" id="fi_<?=$ex['id']?>" name="exercise_files[]" accept="image/*,video/*,.pdf" multiple class="hidden" onchange="previewFiles(this,<?=$ex['id']?>)">
      <label for="fi_<?=$ex['id']?>" class="flex items-center justify-center gap-2 w-full py-3 border-2 border-dashed border-violet-300 rounded-xl text-violet-500 font-bold text-sm cursor-pointer hover:bg-violet-50 transition-all">
        <i class="bi bi-camera-fill text-lg"></i> ถ่ายรูป / แนบไฟล์ใหม่
      </label>
      <div id="fp_<?=$ex['id']?>" class="space-y-2"></div>
      <input type="url" name="link_url" value="<?=htmlspecialchars($sub['link_url']??'',ENT_QUOTES,'UTF-8')?>" placeholder="🔗 ลิงค์"
        class="w-full border border-slate-200 rounded-xl px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-violet-400 bg-white">
      <textarea name="answer_text" rows="3" class="w-full border border-slate-200 rounded-xl px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-violet-400 resize-none bg-white"
        ><?=htmlspecialchars($sub['answer_text']??'',ENT_QUOTES,'UTF-8')?></textarea>
      <div class="flex gap-2">
        <button type="button" onclick="toggleForm(<?=$ex['id']?>)"
          class="px-4 py-2.5 border border-slate-200 text-slate-500 font-bold text-xs rounded-xl">ยกเลิก</button>
        <button type="submit" class="flex-1 py-2.5 bg-violet-600 text-white font-black text-sm rounded-xl shadow-md">
          <i class="bi bi-send-fill mr-1"></i> ส่งซ้ำ
        </button>
      </div>
    </form>
    <?php endif; ?>
  </div>
  <?php endforeach; ?>
  <?php endif; ?>

  <?php endif; ?>
</div><!-- /pane-work -->

<!-- ════════════════════════════════════════════════════════
     TAB: เนื้อหา
     ════════════════════════════════════════════════════════ -->
<div id="pane-content" class="tab-pane <?=$default_tab==='content'?'active':''?>">
  <?php if (empty($units)): ?>
  <div class="bg-white rounded-2xl border border-slate-100 p-12 text-center shadow-sm">
    <i class="bi bi-book text-slate-200 text-5xl mb-3 block"></i>
    <p class="text-slate-400 font-bold text-sm">ยังไม่มีเนื้อหา</p>
  </div>
  <?php else: ?>
  <?php foreach ($units as $ui => $u):
    $prg = $unit_ex_progress[$u['id']] ?? ['total'=>0,'done'=>0];
    $unit_done = $prg['total'] > 0 && $prg['done'] >= $prg['total'];
    $topics = $topics_by_unit[$u['id']] ?? [];
  ?>
  <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
    <!-- Unit header (clickable accordion) -->
    <button onclick="toggleUnit(<?=$u['id']?>)"
      class="w-full flex items-center gap-3 px-4 py-4 text-left active:bg-slate-50 transition-colors">
      <div class="w-9 h-9 rounded-xl flex items-center justify-center flex-shrink-0 font-black text-sm
        <?=$unit_done?'bg-emerald-100 text-emerald-600':'bg-violet-100 text-violet-600'?>">
        <?=$u['order_no']?>
      </div>
      <div class="flex-1 min-w-0">
        <p class="font-black text-slate-800 text-sm truncate"><?=htmlspecialchars($u['unit_name'],ENT_QUOTES,'UTF-8')?></p>
        <p class="text-[10px] text-slate-400 mt-0.5">
          <?=count($topics)?> เรื่อง
          <?php if ($prg['total'] > 0): ?> · งาน <?=$prg['done']?>/<?=$prg['total']?><?php endif; ?>
        </p>
      </div>
      <?php if ($unit_done): ?>
      <i class="bi bi-check-circle-fill text-emerald-500 flex-shrink-0"></i>
      <?php endif; ?>
      <i class="bi bi-chevron-down text-slate-400 flex-shrink-0 transition-transform unit-chevron" id="chev-<?=$u['id']?>"></i>
    </button>

    <!-- Unit body (topics list) -->
    <div id="unit-<?=$u['id']?>" class="unit-body border-t border-slate-100 divide-y divide-slate-50" style="display:none">
      <?php if (empty($topics)): ?>
      <div class="px-4 py-6 text-center text-slate-300 text-xs">ยังไม่มีเรื่อง</div>
      <?php else: ?>
      <?php foreach ($topics as $ti => $topic): ?>
      <div>
        <!-- Topic row -->
        <button onclick="toggleTopic(<?=$topic['id']?>)"
          class="w-full flex items-center gap-3 px-4 py-3 text-left hover:bg-slate-50 transition-colors">
          <div class="w-6 h-6 rounded-lg bg-slate-100 flex items-center justify-center flex-shrink-0 text-[10px] font-black text-slate-500">
            <?=($ti+1)?>
          </div>
          <p class="flex-1 text-sm font-bold text-slate-700 truncate"><?=htmlspecialchars($topic['topic_name'],ENT_QUOTES,'UTF-8')?></p>
          <!-- Content type pills -->
          <div class="flex gap-1 flex-shrink-0">
            <?php if (!empty($topic['content'])): ?><span class="w-5 h-5 bg-blue-50 text-blue-400 rounded-lg flex items-center justify-center"><i class="bi bi-card-text text-[10px]"></i></span><?php endif; ?>
            <?php if (!empty($topic['youtube'])): ?><span class="w-5 h-5 bg-red-50 text-red-400 rounded-lg flex items-center justify-center"><i class="bi bi-youtube text-[10px]"></i></span><?php endif; ?>
            <?php if (!empty($topic['files'])): ?><span class="w-5 h-5 bg-amber-50 text-amber-400 rounded-lg flex items-center justify-center"><i class="bi bi-paperclip text-[10px]"></i></span><?php endif; ?>
            <?php if (!empty($topic['links'])): ?><span class="w-5 h-5 bg-violet-50 text-violet-400 rounded-lg flex items-center justify-center"><i class="bi bi-link text-[10px]"></i></span><?php endif; ?>
          </div>
          <i class="bi bi-chevron-right text-slate-300 text-xs flex-shrink-0 transition-transform topic-chevron" id="tchev-<?=$topic['id']?>"></i>
        </button>

        <!-- Topic content (inline) -->
        <div id="topic-<?=$topic['id']?>" class="topic-body px-4 pb-4 space-y-3 bg-slate-50/50 border-t border-slate-100">
          <!-- Text content -->
          <?php if (!empty($topic['content'])): ?>
          <div class="bg-white rounded-xl border border-slate-100 px-4 py-3">
            <p class="text-xs leading-relaxed text-slate-700"><?=nl2br(htmlspecialchars($topic['content'],ENT_QUOTES,'UTF-8'))?></p>
          </div>
          <?php endif; ?>

          <!-- YouTube videos -->
          <?php foreach ($topic['youtube'] as $yt):
            preg_match('/(?:v=|youtu\.be\/|embed\/)([A-Za-z0-9_-]{11})/', $yt['url'], $m);
            $vid = $m[1] ?? null; ?>
          <div>
            <?php if (!empty($yt['video_label'])): ?>
            <p class="text-[10px] font-black text-red-500 mb-1.5 flex items-center gap-1"><i class="bi bi-youtube"></i><?=htmlspecialchars($yt['video_label'],ENT_QUOTES,'UTF-8')?></p>
            <?php endif; ?>
            <?php if ($vid): ?>
            <iframe class="yt-frame" src="https://www.youtube.com/embed/<?=htmlspecialchars($vid,ENT_QUOTES,'UTF-8')?>" allowfullscreen loading="lazy"></iframe>
            <?php else: ?>
            <a href="<?=htmlspecialchars($yt['url'],ENT_QUOTES,'UTF-8')?>" target="_blank" rel="noopener"
               class="flex items-center gap-2 px-3 py-2.5 bg-red-50 border border-red-100 rounded-xl text-xs font-bold text-red-600">
              <i class="bi bi-youtube text-base"></i><span class="underline truncate"><?=htmlspecialchars($yt['url'],ENT_QUOTES,'UTF-8')?></span>
            </a>
            <?php endif; ?>
          </div>
          <?php endforeach; ?>

          <!-- Files -->
          <?php foreach ($topic['files'] as $tf):
            $fext2 = strtolower(pathinfo($tf['filename'], PATHINFO_EXTENSION));
            $is_img = in_array($fext2, ['jpg','jpeg','png','gif','webp']);
            $is_vid = in_array($fext2, ['mp4','mov','avi','webm']); ?>
          <?php if ($is_img): ?>
          <img src="/uploads/lms/<?=htmlspecialchars($tf['filename'],ENT_QUOTES,'UTF-8')?>"
               class="w-full rounded-xl border border-slate-100 object-contain max-h-64 bg-white" alt="<?=htmlspecialchars($tf['original_name']??'',ENT_QUOTES,'UTF-8')?>">
          <?php elseif ($is_vid): ?>
          <video src="/uploads/lms/<?=htmlspecialchars($tf['filename'],ENT_QUOTES,'UTF-8')?>" controls class="w-full rounded-xl max-h-60 border border-slate-100"></video>
          <?php else: ?>
          <a href="/uploads/lms/<?=htmlspecialchars($tf['filename'],ENT_QUOTES,'UTF-8')?>" target="_blank" rel="noopener"
             class="flex items-center gap-3 px-3 py-3 bg-amber-50 border border-amber-100 rounded-xl text-xs font-bold text-amber-700 active:opacity-70">
            <i class="bi bi-file-earmark-arrow-down text-xl flex-shrink-0"></i>
            <span class="flex-1 truncate"><?=htmlspecialchars($tf['original_name'] ?? $tf['filename'],ENT_QUOTES,'UTF-8')?></span>
            <i class="bi bi-download flex-shrink-0"></i>
          </a>
          <?php endif; ?>
          <?php endforeach; ?>

          <!-- Links -->
          <?php foreach ($topic['links'] as $lk): ?>
          <a href="<?=htmlspecialchars($lk['url'],ENT_QUOTES,'UTF-8')?>" target="_blank" rel="noopener"
             class="flex items-center gap-3 px-3 py-3 bg-violet-50 border border-violet-100 rounded-xl text-xs font-bold text-violet-700 active:opacity-70">
            <i class="bi bi-link-45deg text-xl flex-shrink-0"></i>
            <span class="flex-1 truncate"><?=htmlspecialchars($lk['link_label'] ?? $lk['url'],ENT_QUOTES,'UTF-8')?></span>
            <i class="bi bi-box-arrow-up-right text-xs flex-shrink-0"></i>
          </a>
          <?php endforeach; ?>

          <?php if (empty($topic['content']) && empty($topic['youtube']) && empty($topic['files']) && empty($topic['links'])): ?>
          <p class="text-center text-xs text-slate-300 py-2">ยังไม่มีเนื้อหา</p>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
  <?php endforeach; ?>
  <?php endif; ?>
</div><!-- /pane-content -->

<!-- ════════════════════════════════════════════════════════
     TAB: ผลงาน
     ════════════════════════════════════════════════════════ -->
<div id="pane-progress" class="tab-pane <?=$default_tab==='progress'?'active':''?> space-y-3">

  <!-- Pre-exam card -->
  <div class="bg-white rounded-2xl border-2 <?=$pre_passed?'border-emerald-200':'border-blue-200'?> p-5 shadow-sm">
    <div class="flex items-center justify-between gap-3 mb-3">
      <div class="flex items-center gap-3">
        <div class="w-9 h-9 rounded-xl <?=$pre_passed?'bg-emerald-100':'bg-blue-100'?> flex items-center justify-center flex-shrink-0">
          <i class="bi bi-<?=$pre_passed?'check-circle-fill text-emerald-500':'play-circle-fill text-blue-500'?> text-lg"></i>
        </div>
        <div>
          <p class="font-black text-slate-800 text-sm">แบบทดสอบก่อนเรียน</p>
          <p class="text-[10px] text-slate-400">วัดความรู้ก่อนเรียน · ทำครั้งเดียว</p>
        </div>
      </div>
      <span class="px-2.5 py-1 <?=$pre_passed?'bg-emerald-100 text-emerald-700':'bg-blue-100 text-blue-700'?> text-xs font-black rounded-full flex-shrink-0">
        <?=$pre_passed?'ผ่านแล้ว':'รอทำ'?>
      </span>
    </div>
    <?php if ($pre_passed && empty($pre_passed['auto'])): ?>
    <div class="bg-emerald-50 rounded-xl px-4 py-2.5 flex items-center justify-between">
      <span class="text-xs text-emerald-600 font-bold">คะแนนที่ได้</span>
      <span class="text-lg font-black text-emerald-700"><?=$pre_passed['score']?> / <?=$pre_passed['total']?></span>
    </div>
    <?php elseif (!$pre_passed): ?>
    <a href="/student/lms_pre_exam.php?subject_id=<?=$subject_id?>"
       class="w-full flex items-center justify-center gap-2 py-2.5 bg-blue-600 text-white font-bold text-sm rounded-xl shadow-md shadow-blue-200 active:scale-95 transition-transform">
      <i class="bi bi-play-circle-fill"></i> เริ่มทำแบบทดสอบ
    </a>
    <?php endif; ?>
  </div>

  <!-- Score summary (only if has graded work) -->
  <?php if ($score_possible > 0): ?>
  <?php $spct = min(100, round($score_earned / $score_possible * 100)); ?>
  <div class="bg-white rounded-2xl border border-slate-100 p-5 shadow-sm">
    <p class="font-black text-slate-800 text-sm mb-3 flex items-center gap-2">
      <i class="bi bi-trophy-fill text-amber-400"></i> คะแนนรวมใบงาน
    </p>
    <div class="flex items-end justify-between mb-2">
      <span class="text-3xl font-black text-violet-600"><?=rtrim(rtrim(number_format($score_earned,1),'0'),'.')?></span>
      <span class="text-sm text-slate-400 font-bold">/ <?=$score_possible?> คะแนน</span>
    </div>
    <div class="w-full bg-slate-100 rounded-full h-2.5 mb-1">
      <div class="h-2.5 rounded-full bg-violet-500 transition-all" style="width:<?=$spct?>%"></div>
    </div>
    <p class="text-right text-[10px] text-slate-400 font-bold"><?=$spct?>%</p>
  </div>
  <?php endif; ?>

  <!-- Exercise summary counts -->
  <?php if ($total_ex > 0): ?>
  <div class="grid grid-cols-3 gap-2">
    <div class="bg-white rounded-2xl border border-slate-100 p-3 text-center shadow-sm">
      <p class="text-xl font-black text-amber-500"><?=$cnt_pending?></p>
      <p class="text-[10px] text-slate-400 font-bold mt-0.5">รอส่ง</p>
    </div>
    <div class="bg-white rounded-2xl border border-slate-100 p-3 text-center shadow-sm">
      <?php $cnt_sub_only = count(array_filter($all_exercises, fn($e) => isset($submissions[$e['id']]) && $submissions[$e['id']]['reviewed_at'] === null)); ?>
      <p class="text-xl font-black text-emerald-500"><?=$cnt_sub_only?></p>
      <p class="text-[10px] text-slate-400 font-bold mt-0.5">ส่งแล้ว</p>
    </div>
    <div class="bg-white rounded-2xl border border-slate-100 p-3 text-center shadow-sm">
      <?php $cnt_rev = count(array_filter($all_exercises, fn($e) => isset($submissions[$e['id']]) && $submissions[$e['id']]['reviewed_at'] !== null)); ?>
      <p class="text-xl font-black text-violet-500"><?=$cnt_rev?></p>
      <p class="text-[10px] text-slate-400 font-bold mt-0.5">ตรวจแล้ว</p>
    </div>
  </div>
  <?php endif; ?>

  <!-- Post-exam card -->
  <?php if ($pre_passed): ?>
  <?php
    $post_locked  = !$post_in_window;
    $post_maxed   = !$post_passed && $post_att_count >= $max_att;
    $card_border  = $post_passed ? 'border-emerald-200' : ($post_locked ? 'border-slate-200' : 'border-rose-200');
  ?>
  <div class="bg-white rounded-2xl border-2 <?=$card_border?> p-5 shadow-sm">
    <div class="flex items-center justify-between gap-3 mb-3">
      <div class="flex items-center gap-3">
        <div class="w-9 h-9 rounded-xl <?=$post_passed?'bg-emerald-100':($post_locked?'bg-slate-100':'bg-rose-100')?> flex items-center justify-center flex-shrink-0">
          <i class="bi bi-<?=$post_passed?'check-circle-fill text-emerald-500':($post_locked?'lock-fill text-slate-400':'flag-fill text-rose-500')?> text-lg"></i>
        </div>
        <div>
          <p class="font-black text-slate-800 text-sm">แบบทดสอบหลังเรียน</p>
          <p class="text-[10px] text-slate-400">
            ผ่านเกณฑ์ <?=$post_pass?>/<?=$total_post?> · สอบได้ <?=$max_att?> ครั้ง
            <?php if (!$post_passed && !$post_locked): ?>(ครั้งที่ <?=($post_att_count+1)?>)<?php endif; ?>
          </p>
        </div>
      </div>
      <span class="px-2.5 py-1 text-xs font-black rounded-full flex-shrink-0
        <?=$post_passed?'bg-emerald-100 text-emerald-700':($post_locked?'bg-slate-100 text-slate-500':($post_maxed?'bg-slate-100 text-slate-500':'bg-rose-100 text-rose-600'))?>">
        <?=$post_passed?'ผ่านแล้ว':($post_locked?'ยังไม่เปิด':($post_maxed?'หมดสิทธิ์':'รอสอบ'))?>
      </span>
    </div>

    <?php if ($post_passed && empty($post_passed['auto'])): ?>
    <div class="bg-emerald-50 rounded-xl px-4 py-2.5 flex items-center justify-between">
      <span class="text-xs text-emerald-600 font-bold">คะแนนที่ได้</span>
      <span class="text-lg font-black text-emerald-700"><?=$post_passed['score']?> / <?=$post_passed['total']?></span>
    </div>
    <?php elseif ($post_locked): ?>
    <div class="bg-slate-50 rounded-xl px-4 py-2.5 text-center">
      <?php if ($open_ts && $now_ts < $open_ts): ?>
      <p class="text-xs text-slate-500"><i class="bi bi-calendar-event mr-1"></i>เปิดสอบ <?=date('d/m/Y เวลา H:i',$open_ts)?> น.</p>
      <?php elseif ($close_ts && $now_ts > $close_ts): ?>
      <p class="text-xs text-slate-500"><i class="bi bi-calendar-x mr-1"></i>หมดเวลาสอบแล้ว</p>
      <?php endif; ?>
    </div>
    <?php elseif (!$post_passed && !$post_maxed): ?>
    <a href="/student/lms_post_exam.php?subject_id=<?=$subject_id?>"
       class="w-full flex items-center justify-center gap-2 py-2.5 bg-rose-600 text-white font-bold text-sm rounded-xl shadow-md shadow-rose-200 active:scale-95 transition-transform">
      <i class="bi bi-flag-fill"></i> เริ่มสอบหลังเรียน
    </a>
    <?php endif; ?>
  </div>
  <?php endif; ?>

</div><!-- /pane-progress -->

</div><!-- /container -->

<script>
function switchTab(name) {
  ['work','content','progress'].forEach(t => {
    document.getElementById('tab-' + t).classList.toggle('active', t === name);
    document.getElementById('pane-' + t).classList.toggle('active', t === name);
  });
  history.replaceState(null,'',location.pathname + '?subject_id=<?=$subject_id?>&tab=' + name);
}

function toggleUnit(id) {
  const body  = document.getElementById('unit-' + id);
  const chev  = document.getElementById('chev-' + id);
  const open  = body.style.display === 'block';
  body.style.display = open ? 'none' : 'block';
  chev.style.transform = open ? '' : 'rotate(180deg)';
}

function toggleTopic(id) {
  const body = document.getElementById('topic-' + id);
  const chev = document.getElementById('tchev-' + id);
  const open = body.style.display === 'block';
  body.style.display = open ? 'none' : 'block';
  chev.style.transform = open ? '' : 'rotate(90deg)';
}

function toggleForm(id) {
  const form = document.getElementById('form_' + id);
  form.style.display = form.style.display === 'block' ? 'none' : 'block';
  if (form.style.display === 'block') form.scrollIntoView({ behavior:'smooth', block:'nearest' });
}

function previewFiles(input, exId) {
  const preview = document.getElementById('fp_' + exId);
  const files   = Array.from(input.files).slice(0, 3);
  preview.innerHTML = '';
  files.forEach(file => {
    if (file.type.startsWith('image/')) {
      const reader = new FileReader();
      const div = document.createElement('div');
      div.className = 'bg-white rounded-xl p-2 border border-slate-100 text-center';
      preview.appendChild(div);
      reader.onload = e => {
        div.innerHTML = `<img src="${e.target.result}" class="max-h-32 rounded-lg mx-auto object-contain">
          <p class="text-[10px] text-emerald-600 font-bold mt-1 truncate">${file.name}</p>`;
      };
      reader.readAsDataURL(file);
    } else if (file.type.startsWith('video/')) {
      const url = URL.createObjectURL(file);
      preview.insertAdjacentHTML('beforeend',
        `<div class="rounded-xl overflow-hidden border border-slate-200 bg-black">
          <video src="${url}" controls class="w-full max-h-36"></video>
          <p class="text-[10px] text-slate-400 px-2 py-1 truncate">${file.name}</p>
        </div>`);
    } else {
      preview.insertAdjacentHTML('beforeend',
        `<div class="flex items-center gap-3 px-3 py-2 bg-amber-50 rounded-xl border border-amber-100">
          <i class="bi bi-file-earmark text-amber-500 text-xl flex-shrink-0"></i>
          <p class="text-xs font-bold text-slate-700 truncate">${file.name}</p>
        </div>`);
    }
  });
}

function validateForm(form, exId) {
  const text  = (form.querySelector('[name=answer_text]')?.value || '').trim();
  const link  = (form.querySelector('[name=link_url]')?.value || '').trim();
  const files = form.querySelector('[name="exercise_files[]"]')?.files;
  if (!text && !link && (!files || files.length === 0)) {
    Swal.fire({icon:'warning',title:'กรุณาส่งงาน',text:'ถ่ายรูป / ใส่ลิงค์ / หรือพิมพ์คำตอบอย่างน้อย 1 อย่าง',confirmButtonColor:'#7C3AED'});
    return false;
  }
  const btn = form.querySelector('[type=submit]');
  btn.disabled = true;
  btn.innerHTML = '<i class="bi bi-hourglass-split mr-1"></i>กำลังส่ง...';
  return true;
}
</script>
</body>
</html>
