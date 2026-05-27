<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/_guard.php';

$pdo   = getPdo();
$uid   = (int)$_SESSION['student_uid'];
$name  = $_SESSION['student_name'];
$class = $_SESSION['student_class'];

// Subjects for this classroom
$st = $pdo->prepare("
    SELECT s.*
    FROM lms_subjects s
    JOIN lms_subject_classrooms sc ON sc.subject_id = s.id
    WHERE sc.classroom = ?
    ORDER BY s.subject_name
");
$st->execute([$class]);
$subjects = $st->fetchAll();

$subject_stats = [];
$sids = array_column($subjects, 'id');

if (!empty($sids)) {
    $ph = implode(',', array_fill(0, count($sids), '?'));

    // Pre-exam passed
    $r = $pdo->prepare("SELECT DISTINCT subject_id FROM lms_student_pre_exam WHERE student_uid=? AND subject_id IN ($ph) AND passed=1");
    $r->execute(array_merge([$uid], $sids));
    $pre_done = array_flip($r->fetchAll(PDO::FETCH_COLUMN));

    // Post-exam passed
    $r = $pdo->prepare("SELECT DISTINCT subject_id FROM lms_student_post_exam WHERE student_uid=? AND subject_id IN ($ph) AND passed=1");
    $r->execute(array_merge([$uid], $sids));
    $post_done = array_flip($r->fetchAll(PDO::FETCH_COLUMN));

    // Has pre-exam questions (determines if gate applies)
    $r = $pdo->prepare("SELECT subject_id FROM lms_pre_questions WHERE subject_id IN ($ph) GROUP BY subject_id");
    $r->execute($sids);
    $has_pre_q = array_flip($r->fetchAll(PDO::FETCH_COLUMN));

    // Has post-exam questions
    $r = $pdo->prepare("SELECT subject_id FROM lms_post_questions WHERE subject_id IN ($ph) GROUP BY subject_id");
    $r->execute($sids);
    $has_post_q = array_flip($r->fetchAll(PDO::FETCH_COLUMN));

    // Unit count
    $r = $pdo->prepare("SELECT subject_id, COUNT(*) cnt FROM lms_units WHERE subject_id IN ($ph) GROUP BY subject_id");
    $r->execute($sids);
    $unit_map = array_column($r->fetchAll(), 'cnt', 'subject_id');

    // Exercise totals + submitted
    $r = $pdo->prepare("
        SELECT u.subject_id,
               COUNT(e.id) total_ex,
               SUM(CASE WHEN se.id IS NOT NULL THEN 1 ELSE 0 END) submitted_ex
        FROM lms_units u
        JOIN lms_unit_exercises e ON e.unit_id = u.id
        LEFT JOIN lms_student_exercises se ON se.exercise_id = e.id AND se.student_uid = ?
        WHERE u.subject_id IN ($ph)
        GROUP BY u.subject_id
    ");
    $r->execute(array_merge([$uid], $sids));
    $ex_map = array_column($r->fetchAll(), null, 'subject_id');

    foreach ($subjects as $subj) {
        $sid = $subj['id'];
        $ex  = $ex_map[$sid] ?? null;
        $tot = (int)($ex['total_ex']    ?? 0);
        $sub = (int)($ex['submitted_ex'] ?? 0);

        $pre_passed  = isset($pre_done[$sid])  || !isset($has_pre_q[$sid]);
        $post_passed = isset($post_done[$sid]) || !isset($has_post_q[$sid]);
        $pending     = max(0, $tot - $sub);

        // Determine CTA state
        if (!$pre_passed) {
            $cta_type = 'pre_exam';
        } elseif ($pending > 0) {
            $cta_type = 'pending_work';
        } elseif (!$post_passed && isset($has_post_q[$sid])) {
            $cta_type = 'post_exam';
        } elseif ($tot > 0) {
            $cta_type = 'done';
        } else {
            $cta_type = 'browse';
        }

        $subject_stats[$sid] = [
            'pre_passed'   => $pre_passed,
            'post_passed'  => $post_passed,
            'unit_count'   => (int)($unit_map[$sid] ?? 0),
            'total_ex'     => $tot,
            'pending_ex'   => $pending,
            'progress_pct' => $tot > 0 ? round($sub / $tot * 100) : 0,
            'cta_type'     => $cta_type,
        ];
    }
}

$total_pending = array_sum(array_column($subject_stats, 'pending_ex'));
?><!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<title>บทเรียน LMS | โรงเรียนละลมวิทยา</title>
<meta name="theme-color" content="#7C3AED">
<link href="https://fonts.googleapis.com/css2?family=Prompt:wght@400;600;700;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<script src="https://cdn.tailwindcss.com"></script>
<style>body { font-family: 'Prompt', sans-serif; }</style>
</head>
<body class="min-h-screen bg-slate-50 pb-10" style="padding-top:env(safe-area-inset-top)">

<!-- ── Header ─────────────────────────────────────────────── -->
<div class="text-white px-5 pt-5 pb-6 shadow-xl" style="background:linear-gradient(135deg,#7C3AED,#4F46E5)">
  <div class="flex items-center gap-3 mb-4">
    <a href="/student/dashboard.php"
       class="w-9 h-9 bg-white/15 rounded-xl flex items-center justify-center border border-white/20 active:bg-white/25">
      <i class="bi bi-arrow-left"></i>
    </a>
    <div>
      <h1 class="font-black text-lg leading-tight">บทเรียน LMS</h1>
      <p class="text-violet-200 text-xs font-bold"><?=htmlspecialchars($name,ENT_QUOTES,'UTF-8')?> · ห้อง <?=htmlspecialchars($class,ENT_QUOTES,'UTF-8')?></p>
    </div>
  </div>

  <!-- Summary pills -->
  <div class="flex gap-2 flex-wrap">
    <div class="bg-white/15 rounded-2xl px-4 py-2 border border-white/20 text-sm font-black">
      <i class="bi bi-book-half mr-1.5 opacity-80"></i><?=count($subjects)?> วิชา
    </div>
    <?php if ($total_pending > 0): ?>
    <div class="bg-amber-400/30 rounded-2xl px-4 py-2 border border-amber-200/40 text-sm font-black animate-pulse">
      <i class="bi bi-exclamation-circle-fill mr-1.5"></i><?=$total_pending?> งานค้าง
    </div>
    <?php endif; ?>
  </div>
</div>

<!-- ── Subject list ─────────────────────────────────────────── -->
<div class="px-4 py-4 space-y-3 max-w-lg mx-auto">

  <?php if (empty($subjects)): ?>
  <div class="bg-white rounded-2xl border border-slate-200 p-12 text-center shadow-sm mt-4">
    <i class="bi bi-book text-slate-200 text-4xl mb-3 block"></i>
    <p class="text-slate-400 font-bold text-sm">ยังไม่มีวิชาสำหรับห้องนี้</p>
    <p class="text-slate-300 text-xs mt-1">ครูจะเพิ่มวิชาให้เร็วๆ นี้</p>
  </div>

  <?php else: ?>
  <?php foreach ($subjects as $subj):
    $sid  = $subj['id'];
    $stat = $subject_stats[$sid];
    $cta  = $stat['cta_type'];
    $is_fully_done = $stat['pre_passed'] && $stat['post_passed'] && $stat['pending_ex'] === 0;

    // Card border & icon color by CTA type
    $card_border = match($cta) {
        'done'         => 'border-emerald-200',
        'pre_exam'     => 'border-blue-200',
        'post_exam'    => 'border-rose-200',
        'pending_work' => 'border-amber-200',
        default        => 'border-slate-200',
    };
  ?>
  <a href="/student/lms_subject.php?subject_id=<?=$sid?>"
     class="block bg-white rounded-2xl border-2 <?=$card_border?> p-4 shadow-sm active:scale-[0.98] transition-transform">

    <div class="flex items-start gap-3 mb-3">
      <!-- Icon -->
      <div class="w-10 h-10 rounded-xl flex items-center justify-center flex-shrink-0
        <?=match($cta){
          'done'         => 'bg-emerald-100',
          'pre_exam'     => 'bg-blue-100',
          'post_exam'    => 'bg-rose-100',
          'pending_work' => 'bg-amber-100',
          default        => 'bg-violet-100',
        }?>">
        <i class="bi bi-<?=match($cta){
          'done'         => 'check-circle-fill text-emerald-500',
          'pre_exam'     => 'play-circle-fill text-blue-500',
          'post_exam'    => 'flag-fill text-rose-500',
          'pending_work' => 'pencil-fill text-amber-500',
          default        => 'book-half text-violet-500',
        }?> text-lg"></i>
      </div>

      <!-- Subject info -->
      <div class="flex-1 min-w-0">
        <p class="font-black text-slate-800 text-sm leading-snug"><?=htmlspecialchars($subj['subject_name'],ENT_QUOTES,'UTF-8')?></p>
        <?php if ($subj['subject_code']): ?>
        <p class="text-[10px] text-slate-400 mt-0.5"><?=htmlspecialchars($subj['subject_code'],ENT_QUOTES,'UTF-8')?></p>
        <?php endif; ?>
        <p class="text-[10px] text-slate-400 mt-0.5"><?=$stat['unit_count']?> หน่วยการเรียน</p>
      </div>

      <!-- Status badge -->
      <span class="flex-shrink-0 px-2.5 py-1 text-[10px] font-black rounded-full
        <?=match($cta){
          'done'         => 'bg-emerald-100 text-emerald-700',
          'pre_exam'     => 'bg-blue-100 text-blue-700',
          'post_exam'    => 'bg-rose-100 text-rose-600',
          'pending_work' => 'bg-amber-100 text-amber-700',
          default        => 'bg-violet-100 text-violet-700',
        }?>">
        <?=match($cta){
          'done'         => '✓ เสร็จแล้ว',
          'pre_exam'     => 'ยังไม่เริ่ม',
          'post_exam'    => 'รอสอบหลัง',
          'pending_work' => $stat['pending_ex'].' ค้าง',
          default        => 'เข้าดูได้',
        }?>
      </span>
    </div>

    <!-- Progress bar (if has exercises) -->
    <?php if ($stat['total_ex'] > 0): ?>
    <div class="mb-3">
      <div class="flex items-center justify-between mb-1">
        <span class="text-[10px] text-slate-400 font-bold">ใบงาน</span>
        <span class="text-[10px] font-black <?=$stat['progress_pct']>=100?'text-emerald-600':'text-violet-600'?>">
          <?=$stat['progress_pct']?>%
        </span>
      </div>
      <div class="w-full bg-slate-100 rounded-full h-1.5">
        <div class="h-1.5 rounded-full transition-all
          <?=$stat['progress_pct']>=100?'bg-emerald-500':'bg-violet-500'?>"
             style="width:<?=$stat['progress_pct']?>%"></div>
      </div>
    </div>
    <?php endif; ?>

    <!-- CTA button strip -->
    <div class="rounded-xl py-2.5 px-3 flex items-center justify-between
      <?=match($cta){
        'done'         => 'bg-emerald-50 text-emerald-700',
        'pre_exam'     => 'bg-blue-50 text-blue-700',
        'post_exam'    => 'bg-rose-50 text-rose-700',
        'pending_work' => 'bg-amber-50 text-amber-700',
        default        => 'bg-violet-50 text-violet-700',
      }?>">
      <span class="text-xs font-black flex items-center gap-1.5">
        <i class="bi bi-<?=match($cta){
          'done'         => 'check2-circle',
          'pre_exam'     => 'play-circle-fill',
          'post_exam'    => 'flag-fill',
          'pending_work' => 'pencil-square',
          default        => 'book-open',
        }?>"></i>
        <?=match($cta){
          'done'         => 'เสร็จสมบูรณ์',
          'pre_exam'     => 'ทำแบบทดสอบก่อนเรียน',
          'post_exam'    => 'สอบหลังเรียน',
          'pending_work' => 'ดูใบงาน · ' . $stat['pending_ex'] . ' ชิ้นค้าง',
          default        => 'เข้าดูเนื้อหา',
        }?>
      </span>
      <i class="bi bi-chevron-right text-xs opacity-60"></i>
    </div>

  </a>
  <?php endforeach; ?>
  <?php endif; ?>

</div>
</body>
</html>
