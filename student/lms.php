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
$units_total_all = 0; $units_completed_all = 0; $improve_pairs = [];

if (!empty($sids)) {
    $ph = implode(',', array_fill(0, count($sids), '?'));

    // Units per subject, in order (exams and exercises are unit-scoped)
    $r = $pdo->prepare("SELECT id, subject_id, order_no FROM lms_units WHERE subject_id IN ($ph) AND status='published' AND deleted_at IS NULL ORDER BY subject_id, order_no");
    $r->execute($sids);
    $units_by_subject = [];
    foreach ($r->fetchAll() as $u) $units_by_subject[$u['subject_id']][] = $u;

    $_ex_cls_exists = (bool)$pdo->query("SHOW TABLES LIKE 'lms_exercise_classrooms'")->fetch();
    $_cls_ex_filter = $_ex_cls_exists
        ? "AND (NOT EXISTS (SELECT 1 FROM lms_exercise_classrooms ec WHERE ec.exercise_id = e.id) OR EXISTS (SELECT 1 FROM lms_exercise_classrooms ec WHERE ec.exercise_id = e.id AND ec.classroom = ?))"
        : '';

    foreach ($subjects as $subj) {
        $sid   = $subj['id'];
        $units = $units_by_subject[$sid] ?? [];
        $tot_ex_all = 0; $sub_ex_all = 0;
        $cta_type = 'browse';
        $found_cta = false;

        foreach ($units as $u) {
            $un = $u['id'];
            $units_total_all++;

            $pq = $pdo->prepare("SELECT COUNT(*) FROM lms_pre_questions WHERE unit_id=?"); $pq->execute([$un]); $has_pre = (int)$pq->fetchColumn() > 0;
            $pre_ok = true; $pre_pct = null;
            if ($has_pre) {
                $pc = $pdo->prepare("SELECT score,total FROM lms_student_pre_exam WHERE student_uid=? AND unit_id=? AND passed=1 ORDER BY taken_at DESC LIMIT 1");
                $pc->execute([$uid, $un]); $prow = $pc->fetch();
                $pre_ok = (bool)$prow;
                if ($prow && $prow['total'] > 0) $pre_pct = $prow['score'] / $prow['total'] * 100;
            }

            $eq = $pdo->prepare("
                SELECT COUNT(e.id) total_ex, SUM(CASE WHEN se.id IS NOT NULL THEN 1 ELSE 0 END) submitted_ex
                FROM lms_unit_exercises e
                LEFT JOIN lms_student_exercises se ON se.exercise_id = e.id AND se.student_uid = ?
                WHERE e.unit_id=? AND e.status='published' AND e.deleted_at IS NULL AND e.is_remedial=0 {$_cls_ex_filter}
            ");
            $eq->execute($_ex_cls_exists ? [$uid, $un, $class] : [$uid, $un]);
            $exr = $eq->fetch();
            $u_tot = (int)($exr['total_ex'] ?? 0); $u_sub = (int)($exr['submitted_ex'] ?? 0);
            $tot_ex_all += $u_tot; $sub_ex_all += $u_sub;

            $poq = $pdo->prepare("SELECT COUNT(*) FROM lms_post_questions WHERE unit_id=?"); $poq->execute([$un]); $has_post = (int)$poq->fetchColumn() > 0;
            $post_ok = true; $post_pct = null;
            if ($has_post) {
                $pc = $pdo->prepare("SELECT score,total FROM lms_student_post_exam WHERE student_uid=? AND unit_id=? AND passed=1 ORDER BY taken_at DESC LIMIT 1");
                $pc->execute([$uid, $un]); $prow = $pc->fetch();
                $post_ok = (bool)$prow;
                if ($prow && $prow['total'] > 0) $post_pct = $prow['score'] / $prow['total'] * 100;
            }

            if ($pre_pct !== null && $post_pct !== null) $improve_pairs[] = $post_pct - $pre_pct;
            if ($pre_ok && $u_sub >= $u_tot && $post_ok) $units_completed_all++;

            if (!$found_cta) {
                if (!$pre_ok)              { $cta_type = 'pre_exam'; $found_cta = true; }
                elseif ($u_sub < $u_tot)   { $cta_type = 'pending_work'; $found_cta = true; }
                elseif (!$post_ok)         { $cta_type = 'post_exam'; $found_cta = true; }
            }
        }

        if (!$found_cta) $cta_type = empty($units) ? 'browse' : 'done';
        $pending = max(0, $tot_ex_all - $sub_ex_all);

        $subject_stats[$sid] = [
            'pre_passed'   => $cta_type !== 'pre_exam',
            'post_passed'  => !in_array($cta_type, ['pre_exam','pending_work','post_exam'], true),
            'unit_count'   => count($units),
            'total_ex'     => $tot_ex_all,
            'pending_ex'   => $pending,
            'progress_pct' => $tot_ex_all > 0 ? round($sub_ex_all / $tot_ex_all * 100) : 0,
            'cta_type'     => $cta_type,
        ];
    }
}

$total_pending = array_sum(array_column($subject_stats, 'pending_ex'));
$avg_improve   = !empty($improve_pairs) ? round(array_sum($improve_pairs) / count($improve_pairs)) : null;

// ── Submission streak: consecutive days (ending today or yesterday) with any LMS activity ──
$active_dates = [];
$r = $pdo->prepare("SELECT DISTINCT DATE(taken_at) d FROM lms_student_pre_exam WHERE student_uid=?");
$r->execute([$uid]); foreach ($r->fetchAll(PDO::FETCH_COLUMN) as $d) $active_dates[$d] = true;
$r = $pdo->prepare("SELECT DISTINCT DATE(taken_at) d FROM lms_student_post_exam WHERE student_uid=?");
$r->execute([$uid]); foreach ($r->fetchAll(PDO::FETCH_COLUMN) as $d) $active_dates[$d] = true;
$r = $pdo->prepare("SELECT DISTINCT DATE(submitted_at) d FROM lms_student_exercises WHERE student_uid=? AND submitted_at IS NOT NULL");
$r->execute([$uid]); foreach ($r->fetchAll(PDO::FETCH_COLUMN) as $d) $active_dates[$d] = true;

$streak = 0;
$cursor = new DateTime('today');
if (!isset($active_dates[$cursor->format('Y-m-d')])) $cursor->modify('-1 day');
while (isset($active_dates[$cursor->format('Y-m-d')])) {
    $streak++;
    $cursor->modify('-1 day');
}
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

<!-- ── Personal progress stats ──────────────────────────────── -->
<?php if (!empty($subjects)): ?>
<div class="px-4 pt-4 max-w-2xl mx-auto">
  <div class="grid grid-cols-3 gap-2">
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-3 text-center">
      <div class="text-lg font-black text-violet-600"><?=$units_completed_all?>/<?=$units_total_all?></div>
      <div class="text-[9px] text-slate-400 font-bold mt-0.5">หน่วยที่เรียนจบ</div>
    </div>
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-3 text-center">
      <?php if ($avg_improve !== null): ?>
      <div class="text-lg font-black <?=$avg_improve>=0?'text-emerald-600':'text-slate-500'?>"><?=$avg_improve>=0?'+':''?><?=$avg_improve?>%</div>
      <?php else: ?>
      <div class="text-lg font-black text-slate-300">—</div>
      <?php endif; ?>
      <div class="text-[9px] text-slate-400 font-bold mt-0.5">คะแนนดีขึ้นเฉลี่ย</div>
    </div>
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-3 text-center">
      <div class="text-lg font-black <?=$streak>0?'text-amber-500':'text-slate-300'?>">
        <?=$streak?><?php if ($streak > 0): ?> <i class="bi bi-fire text-sm"></i><?php endif; ?>
      </div>
      <div class="text-[9px] text-slate-400 font-bold mt-0.5">วันต่อเนื่อง</div>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- ── Subject list ─────────────────────────────────────────── -->
<div class="px-4 py-4 space-y-3 max-w-2xl mx-auto">

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
