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

// Per-subject quick stats for each subject
$subject_stats = [];
foreach ($subjects as $subj) {
    $sid = $subj['id'];
    $pre = $pdo->prepare("SELECT id FROM lms_student_pre_exam WHERE student_uid=? AND subject_id=? AND passed=1 LIMIT 1");
    $pre->execute([$uid,$sid]); $pre_passed = $pre->fetch();
    $post = $pdo->prepare("SELECT id FROM lms_student_post_exam WHERE student_uid=? AND subject_id=? AND passed=1 LIMIT 1");
    $post->execute([$uid,$sid]); $post_passed = $post->fetch();
    $uc = $pdo->prepare("SELECT COUNT(*) FROM lms_units WHERE subject_id=?"); $uc->execute([$sid]); $unit_count = (int)$uc->fetchColumn();
    $subject_stats[$sid] = ['pre_passed'=>$pre_passed, 'post_passed'=>$post_passed, 'unit_count'=>$unit_count];
}
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
  <div class="bg-white/15 rounded-2xl px-4 py-2.5 border border-white/20 text-sm font-bold">
    <?=count($subjects)?> วิชา
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
      <?php if ($done): ?>
      <span class="px-2.5 py-1 bg-emerald-100 text-emerald-700 text-xs font-black rounded-full flex-shrink-0"><i class="bi bi-check-circle-fill mr-1"></i>สำเร็จ</span>
      <?php elseif ($stat['pre_passed']): ?>
      <span class="px-2.5 py-1 bg-blue-100 text-blue-700 text-xs font-black rounded-full flex-shrink-0">กำลังเรียน</span>
      <?php else: ?>
      <span class="px-2.5 py-1 bg-violet-100 text-violet-700 text-xs font-black rounded-full flex-shrink-0">ยังไม่เริ่ม</span>
      <?php endif; ?>
    </div>
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
