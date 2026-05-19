<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/_guard.php';

$pdo      = getPdo();
$uid      = (int)$_SESSION['student_uid'];
$code     = $_SESSION['student_code'];
$name     = $_SESSION['student_name'];
$class    = $_SESSION['student_class'];

// Assignments for this classroom
$assignments = $pdo->prepare("
    SELECT a.*,
           s.id AS sub_id, s.status AS sub_status, s.content AS sub_content,
           s.score, s.teacher_comment, s.submitted_at, s.reviewed_at
    FROM hw_assignments a
    LEFT JOIN hw_submissions s ON s.assignment_id = a.id AND s.student_uid = ?
    WHERE a.status = 'published' AND FIND_IN_SET(?, REPLACE(a.classroom, ', ', ','))
    ORDER BY a.due_date ASC
");
$assignments->execute([$uid, $class]);
$assignments = $assignments->fetchAll();

$now = time();
?><!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<title>การบ้าน | โรงเรียนละลมวิทยา</title>
<meta name="theme-color" content="#4f46e5">
<link href="https://fonts.googleapis.com/css2?family=Prompt:wght@400;600;700;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<script src="https://cdn.tailwindcss.com"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>body{font-family:'Prompt',sans-serif;}</style>
</head>
<body class="min-h-screen bg-slate-50" style="padding-top:env(safe-area-inset-top)">

<!-- Header -->
<div class="bg-gradient-to-r from-indigo-600 to-indigo-800 text-white px-5 pt-5 pb-6 shadow-xl">
    <div class="flex items-center gap-3 mb-4">
        <a href="/student/dashboard.php" class="w-9 h-9 bg-white/15 rounded-xl flex items-center justify-center border border-white/20 active:bg-white/25">
            <i class="bi bi-arrow-left"></i>
        </a>
        <div>
            <h1 class="font-black text-lg leading-tight">การบ้าน</h1>
            <p class="text-indigo-200 text-xs font-bold"><?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars($class, ENT_QUOTES, 'UTF-8') ?></p>
        </div>
    </div>
    <!-- KPI -->
    <div class="grid grid-cols-3 gap-3">
        <?php
        $total    = count($assignments);
        $submitted = count(array_filter($assignments, fn($a) => $a['sub_id']));
        $graded   = count(array_filter($assignments, fn($a) => $a['score'] !== null));
        ?>
        <div class="bg-white/15 rounded-2xl p-3 text-center border border-white/20">
            <p class="text-xl font-black"><?= $total ?></p>
            <p class="text-xs opacity-80 font-bold">งานทั้งหมด</p>
        </div>
        <div class="bg-white/15 rounded-2xl p-3 text-center border border-white/20">
            <p class="text-xl font-black"><?= $submitted ?></p>
            <p class="text-xs opacity-80 font-bold">ส่งแล้ว</p>
        </div>
        <div class="bg-white/15 rounded-2xl p-3 text-center border border-white/20">
            <p class="text-xl font-black"><?= $graded ?></p>
            <p class="text-xs opacity-80 font-bold">ได้คะแนนแล้ว</p>
        </div>
    </div>
</div>

<!-- Assignment cards -->
<div class="px-4 py-5 space-y-4 max-w-lg mx-auto">
    <?php if (empty($assignments)): ?>
    <div class="text-center py-16 text-slate-400">
        <i class="bi bi-inbox text-5xl block mb-3 opacity-30"></i>
        <p class="font-bold">ยังไม่มีงานที่ได้รับมอบหมาย</p>
    </div>
    <?php else: ?>
    <?php foreach ($assignments as $a):
        $isOverdue  = strtotime($a['due_date']) < $now;
        $hasSubmit  = !empty($a['sub_id']);
        $isReviewed = $hasSubmit && $a['score'] !== null;
        $isLate     = $hasSubmit && $a['sub_status'] === 'late';

        if ($isReviewed) { $cardColor = 'border-emerald-200 bg-emerald-50/50'; $badgeColor = 'bg-emerald-100 text-emerald-700'; $badge = 'ตรวจแล้ว'; }
        elseif ($hasSubmit) { $cardColor = 'border-amber-200 bg-amber-50/50'; $badgeColor = 'bg-amber-100 text-amber-700'; $badge = $isLate ? 'ส่งช้า' : 'รอตรวจ'; }
        elseif ($isOverdue) { $cardColor = 'border-rose-200 bg-rose-50/50'; $badgeColor = 'bg-rose-100 text-rose-600'; $badge = 'เลยกำหนด'; }
        else { $cardColor = 'border-slate-200 bg-white'; $badgeColor = 'bg-indigo-100 text-indigo-700'; $badge = 'รอส่ง'; }
    ?>
    <div class="rounded-2xl border <?= $cardColor ?> p-5 shadow-sm">
        <div class="flex items-start justify-between gap-3 mb-3">
            <div class="flex-1 min-w-0">
                <p class="font-black text-slate-800 text-sm leading-tight"><?= htmlspecialchars($a['title'], ENT_QUOTES, 'UTF-8') ?></p>
                <?php if ($a['subject']): ?>
                <p class="text-xs text-slate-400 mt-0.5"><?= htmlspecialchars($a['subject'], ENT_QUOTES, 'UTF-8') ?></p>
                <?php endif; ?>
            </div>
            <span class="px-2.5 py-1 <?= $badgeColor ?> text-xs font-black rounded-full flex-shrink-0"><?= $badge ?></span>
        </div>

        <?php if ($a['description']): ?>
        <p class="text-xs text-slate-500 mb-3 leading-relaxed"><?= nl2br(htmlspecialchars($a['description'], ENT_QUOTES, 'UTF-8')) ?></p>
        <?php endif; ?>

        <div class="flex items-center gap-3 text-xs text-slate-400 font-bold mb-4">
            <span><i class="bi bi-calendar3 mr-1"></i>ส่งภายใน <?= date('d/m/Y H:i', strtotime($a['due_date'])) ?></span>
            <span><i class="bi bi-star mr-1"></i>เต็ม <?= $a['max_score'] ?></span>
        </div>

        <?php if ($isReviewed): ?>
        <!-- Score display -->
        <div class="bg-white rounded-xl p-3 border border-emerald-100 mb-3">
            <div class="flex items-center justify-between mb-1">
                <span class="text-xs font-black text-slate-500">คะแนนที่ได้</span>
                <span class="text-lg font-black text-emerald-600"><?= $a['score'] ?> / <?= $a['max_score'] ?></span>
            </div>
            <?php if ($a['teacher_comment']): ?>
            <p class="text-xs text-slate-500 mt-1 italic">"<?= htmlspecialchars($a['teacher_comment'], ENT_QUOTES, 'UTF-8') ?>"</p>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if ($hasSubmit && $a['sub_content']): ?>
        <!-- Submitted content -->
        <div class="bg-white/70 rounded-xl p-3 border border-slate-100 mb-3">
            <p class="text-xs font-black text-slate-400 mb-1">งานที่ส่ง:</p>
            <p class="text-xs text-slate-600 whitespace-pre-wrap"><?= htmlspecialchars($a['sub_content'], ENT_QUOTES, 'UTF-8') ?></p>
        </div>
        <?php endif; ?>

        <?php if (!$hasSubmit || (!$isReviewed && $hasSubmit)): ?>
        <!-- Submit / Re-submit form -->
        <div id="form-<?= $a['id'] ?>">
            <?php if (!$isOverdue || $hasSubmit): ?>
            <textarea id="content-<?= $a['id'] ?>" rows="3"
                      class="w-full bg-white border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-400 outline-none resize-none transition-all mb-2"
                      placeholder="พิมพ์คำตอบหรืองานของคุณที่นี่..."><?= $hasSubmit ? htmlspecialchars($a['sub_content'], ENT_QUOTES, 'UTF-8') : '' ?></textarea>
            <button onclick="submitWork(<?= $a['id'] ?>)"
                    class="w-full bg-indigo-600 text-white py-2.5 rounded-xl font-bold text-sm shadow-lg shadow-indigo-200/50 active:scale-95 transition-transform flex items-center justify-center gap-2">
                <i class="bi bi-send-fill"></i> <?= $hasSubmit ? 'ส่งงานใหม่อีกครั้ง' : 'ส่งงาน' ?>
            </button>
            <?php else: ?>
            <div class="text-center py-3 text-rose-400 text-xs font-bold">
                <i class="bi bi-lock-fill mr-1"></i> เลยกำหนดส่งแล้ว ไม่สามารถส่งได้
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
</div>

<script>
async function submitWork(id) {
    const content = document.getElementById('content-' + id).value.trim();
    if (!content) { Swal.fire({ icon:'warning', title:'กรุณากรอกเนื้อหางาน', confirmButtonColor:'#4f46e5' }); return; }

    const confirm = await Swal.fire({
        icon:'question', title:'ยืนยันส่งงาน?', text:'หลังส่งแล้วสามารถแก้ไขได้จนกว่าครูจะตรวจ',
        showCancelButton:true, confirmButtonText:'ส่งงาน', cancelButtonText:'ยกเลิก', confirmButtonColor:'#4f46e5'
    });
    if (!confirm.isConfirmed) return;

    Swal.fire({ title:'กำลังส่งงาน...', allowOutsideClick:false, didOpen:()=>Swal.showLoading() });

    const res = await fetch('/homework/api/save_submission.php', {
        method:'POST',
        headers:{'Content-Type':'application/json'},
        body: JSON.stringify({ assignment_id: id, content })
    }).then(r=>r.json());

    Swal.close();

    if (res.status === 'success') {
        await Swal.fire({
            icon:'success',
            title: res.late ? 'ส่งงานแล้ว (สายเกินกำหนด)' : 'ส่งงานเรียบร้อย!',
            confirmButtonColor:'#4f46e5'
        });
        location.reload();
    } else {
        Swal.fire({ icon:'error', title:'ผิดพลาด', text:res.message, confirmButtonColor:'#4f46e5' });
    }
}
</script>
</body>
</html>
