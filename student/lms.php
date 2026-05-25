<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/_guard.php';

$pdo   = getPdo();
$uid   = (int)$_SESSION['student_uid'];
$name  = $_SESSION['student_name'];
$class = $_SESSION['student_class'];

if (isset($_GET['reset'])) {
    // Subject-specific reset banner is now handled on lms_subject.php
}

// Subjects assigned to student's classroom
$st = $pdo->prepare("
    SELECT s.*
    FROM lms_subjects s
    JOIN lms_subject_classrooms sc ON sc.subject_id = s.id
    WHERE sc.classroom = ?
    ORDER BY s.subject_name
");
$st->execute([$class]);
$subjects = $st->fetchAll();

// Per-subject stats — batch queries instead of N+1 loop
$subject_stats = [];
$sids = array_column($subjects, 'id');
if (!empty($sids)) {
    $ph = implode(',', array_fill(0, count($sids), '?'));

    $r = $pdo->prepare("SELECT DISTINCT subject_id FROM lms_student_pre_exam WHERE student_uid=? AND subject_id IN ($ph) AND passed=1");
    $r->execute(array_merge([$uid], $sids));
    $pre_done = array_flip($r->fetchAll(PDO::FETCH_COLUMN));

    $r = $pdo->prepare("SELECT DISTINCT subject_id FROM lms_student_post_exam WHERE student_uid=? AND subject_id IN ($ph) AND passed=1");
    $r->execute(array_merge([$uid], $sids));
    $post_done = array_flip($r->fetchAll(PDO::FETCH_COLUMN));

    $r = $pdo->prepare("SELECT subject_id, COUNT(*) cnt FROM lms_units WHERE subject_id IN ($ph) GROUP BY subject_id");
    $r->execute($sids); $unit_map = array_column($r->fetchAll(), 'cnt', 'subject_id');

    $r = $pdo->prepare("
        SELECT u.subject_id, COUNT(e.id) total_ex,
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
        $subject_stats[$sid] = [
            'pre_passed'   => isset($pre_done[$sid]),
            'post_passed'  => isset($post_done[$sid]),
            'unit_count'   => (int)($unit_map[$sid] ?? 0),
            'total_ex'     => $tot,
            'pending_ex'   => max(0, $tot - $sub),
            'progress_pct' => $tot > 0 ? round($sub / $tot * 100) : 0,
        ];
    }
}
$total_pending = array_sum(array_column($subject_stats, 'pending_ex'));
?><!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<title>บทเรียน | โรงเรียนละลมวิทยา</title>
<meta name="theme-color" content="#7C3AED">
<link href="https://fonts.googleapis.com/css2?family=Prompt:wght@400;600;700;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<script src="https://cdn.tailwindcss.com"></script>
<style>body{font-family:'Prompt',sans-serif;}</style>
</head>
<body class="min-h-screen bg-slate-50" style="padding-top:env(safe-area-inset-top)">

<div class="text-white px-5 pt-5 pb-6 shadow-xl" style="background:linear-gradient(135deg,#7C3AED,#4F46E5)">
  <div class="flex items-center gap-3 mb-3">
    <a href="/student/dashboard.php" class="w-9 h-9 bg-white/15 rounded-xl flex items-center justify-center border border-white/20 active:bg-white/25">
      <i class="bi bi-arrow-left"></i>
    </a>
    <div>
      <h1 class="font-black text-lg leading-tight">บทเรียน LMS</h1>
      <p class="text-violet-200 text-xs font-bold"><?=htmlspecialchars($name,ENT_QUOTES,'UTF-8')?> · <?=htmlspecialchars($class,ENT_QUOTES,'UTF-8')?></p>
    </div>
  </div>
  <div class="flex gap-2">
    <div class="bg-white/15 rounded-2xl px-4 py-2.5 border border-white/20 text-sm font-bold">
      <?=count($subjects)?> วิชา
    </div>
    <?php if ($total_pending > 0): ?>
    <div class="bg-amber-400/30 rounded-2xl px-4 py-2.5 border border-amber-200/30 text-sm font-bold animate-pulse">
      <i class="bi bi-exclamation-circle-fill mr-1"></i><?=$total_pending?> งานค้าง
    </div>
    <?php endif; ?>
  </div>
</div>

<div class="px-4 py-5 space-y-3 max-w-lg mx-auto">
  <?php if (empty($subjects)): ?>
  <div class="rounded-2xl border border-slate-200 bg-white p-10 text-center shadow-sm">
    <i class="bi bi-book text-slate-300 text-4xl mb-3 block"></i>
    <p class="text-slate-400 font-bold text-sm">ยังไม่มีวิชาสำหรับห้องนี้</p>
  </div>
  <?php else: ?>
  <?php foreach ($subjects as $subj):
    $sid  = $subj['id'];
    $stat = $subject_stats[$sid];
    $done = $stat['pre_passed'] && $stat['post_passed'];
  ?>
  <a href="/student/lms_subject.php?subject_id=<?=$sid?>"
     class="block rounded-2xl border <?=$done?'border-emerald-200 bg-emerald-50/60':'border-slate-200 bg-white'?> p-5 shadow-sm active:scale-[0.98] transition-transform">
    <div class="flex items-start justify-between gap-3">
      <div class="flex-1 min-w-0">
        <p class="font-black text-slate-800 text-sm leading-snug"><?=htmlspecialchars($subj['subject_name'],ENT_QUOTES,'UTF-8')?></p>
        <?php if ($subj['subject_code']): ?>
        <p class="text-xs text-slate-400 mt-0.5"><?=htmlspecialchars($subj['subject_code'],ENT_QUOTES,'UTF-8')?></p>
        <?php endif; ?>
        <p class="text-xs text-slate-400 mt-1"><?=$stat['unit_count']?> หน่วยการเรียน</p>
      </div>
      <div class="flex flex-col items-end gap-1 flex-shrink-0">
        <?php if ($done): ?>
        <span class="px-2.5 py-1 bg-emerald-100 text-emerald-700 text-xs font-black rounded-full"><i class="bi bi-check-circle-fill mr-1"></i>สำเร็จ</span>
        <?php elseif ($stat['pre_passed']): ?>
        <span class="px-2.5 py-1 bg-blue-100 text-blue-700 text-xs font-black rounded-full">กำลังเรียน</span>
        <?php else: ?>
        <span class="px-2.5 py-1 bg-violet-100 text-violet-700 text-xs font-black rounded-full">ยังไม่เริ่ม</span>
        <?php endif; ?>
        <?php if ($stat['pending_ex'] > 0): ?>
        <span class="px-2 py-0.5 bg-amber-100 text-amber-700 text-[10px] font-black rounded-full animate-pulse">
          <i class="bi bi-exclamation-circle-fill mr-0.5"></i><?=$stat['pending_ex']?> งานค้าง
        </span>
        <?php endif; ?>
      </div>
    </div>
    <?php if ($stat['total_ex'] > 0): ?>
    <div class="mt-3">
      <div class="flex items-center justify-between mb-1">
        <span class="text-[10px] text-slate-400 font-bold">ส่งงานแล้ว</span>
        <span class="text-[10px] font-black <?=$stat['progress_pct']>=100?'text-emerald-600':'text-violet-600'?>"><?=$stat['progress_pct']?>%</span>
      </div>
      <div class="w-full bg-slate-100 rounded-full h-1.5">
        <div class="h-1.5 rounded-full <?=$stat['progress_pct']>=100?'bg-emerald-500':'bg-violet-500'?> transition-all"
             style="width:<?=$stat['progress_pct']?>%"></div>
      </div>
    </div>
    <?php endif; ?>
    <div class="flex gap-3 mt-3">
      <div class="flex items-center gap-1.5 text-xs <?=$stat['pre_passed']?'text-emerald-600':'text-slate-400'?>">
        <i class="bi bi-<?=$stat['pre_passed']?'check-circle-fill':'circle'?>"></i> ก่อนเรียน
      </div>
      <div class="flex items-center gap-1.5 text-xs <?=$stat['post_passed']?'text-emerald-600':'text-slate-400'?>">
        <i class="bi bi-<?=$stat['post_passed']?'check-circle-fill':'circle'?>"></i> หลังเรียน
      </div>
    </div>
  </a>
  <?php endforeach; ?>
  <?php endif; ?>
</div>
</body>
</html>
