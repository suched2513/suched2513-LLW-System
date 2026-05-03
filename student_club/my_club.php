<?php
session_start();
require_once __DIR__ . '/../config.php';

if (!isset($_SESSION['is_student']) || $_SESSION['is_student'] !== true) {
    header('Location: /student/login.php?redirect=' . urlencode('/student_club/my_club.php'));
    exit();
}

$student_code  = $_SESSION['student_code'] ?? '';
$student_name  = $_SESSION['student_name'] ?? '';
$student_class = $_SESSION['student_class'] ?? '';

$pdo = getPdo();
$cfg = $pdo->query("SELECT * FROM club_settings WHERE is_active = 1 LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$semester = (int)($cfg['semester'] ?? 0);
$year     = (int)($cfg['year'] ?? 0);

$myReg = null;
$sessions = [];
$myResult = null;

if ($semester) {
    $stmt = $pdo->prepare("
        SELECT cr.*, cg.name AS club_name, cg.room, cg.description, cg.objectives,
               cg.pass_threshold, cg.id AS club_id, t.name AS teacher_name
        FROM club_registrations cr
        JOIN club_groups cg ON cg.id = cr.club_id
        LEFT JOIN att_teachers t ON t.id = cg.teacher_id
        WHERE cr.student_id = ? AND cr.semester = ? AND cr.year = ?
        LIMIT 1
    ");
    $stmt->execute([$student_code, $semester, $year]);
    $myReg = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($myReg) {
        // Sessions with my attendance
        $stmt2 = $pdo->prepare("
            SELECT cs.id, cs.session_date, cs.topic, cs.period, cs.status AS session_status,
                   ca.status AS att_status, cal.content AS activity_content
            FROM club_sessions cs
            LEFT JOIN club_attendance ca ON ca.session_id = cs.id AND ca.student_id = ?
            LEFT JOIN club_activity_logs cal ON cal.session_id = cs.id
            WHERE cs.club_id = ?
            ORDER BY cs.session_date DESC
        ");
        $stmt2->execute([$student_code, $myReg['club_id']]);
        $sessions = $stmt2->fetchAll(PDO::FETCH_ASSOC);

        // My result
        $stmt3 = $pdo->prepare("SELECT * FROM club_results WHERE student_id = ? AND club_id = ? AND semester = ? AND year = ?");
        $stmt3->execute([$student_code, $myReg['club_id'], $semester, $year]);
        $myResult = $stmt3->fetch(PDO::FETCH_ASSOC);
    }
}

// Attendance stats
$totalDone   = count(array_filter($sessions, fn($s) => $s['session_status'] === 'done'));
$presentCnt  = count(array_filter($sessions, fn($s) => $s['session_status'] === 'done' && in_array($s['att_status'], ['present','late'])));
$pct         = $totalDone > 0 ? round($presentCnt / $totalDone * 100, 1) : 0;
$threshold   = (int)($myReg['pass_threshold'] ?? 80);

$thaiMonths  = ['','ม.ค.','ก.พ.','มี.ค.','เม.ย.','พ.ค.','มิ.ย.','ก.ค.','ส.ค.','ก.ย.','ต.ค.','พ.ย.','ธ.ค.'];
function fmtDate($d, $months) {
    if (!$d) return '-';
    $p = explode('-', $d);
    return count($p) === 3 ? ((int)$p[2].' '.$months[(int)$p[1]].' '.((int)$p[0]+543)) : $d;
}
?><!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<title>ชุมนุมของฉัน | โรงเรียนละลมวิทยา</title>
<meta name="theme-color" content="#7c3aed">
<link href="https://fonts.googleapis.com/css2?family=Prompt:wght@400;600;700;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<script src="https://cdn.tailwindcss.com"></script>
<style>body{font-family:'Prompt',sans-serif;overscroll-behavior-y:contain}
@keyframes fadeUp{from{opacity:0;transform:translateY(16px)}to{opacity:1;transform:translateY(0)}}
.fade-up{animation:fadeUp .35s ease-out both}</style>
</head>
<body class="bg-slate-100 min-h-screen" style="padding-bottom:env(safe-area-inset-bottom)">

<header class="bg-gradient-to-r from-violet-600 to-purple-600 text-white sticky top-0 z-50 shadow-lg"
        style="padding-top:env(safe-area-inset-top)">
    <div class="max-w-lg mx-auto px-4 py-3 flex items-center gap-3">
        <button onclick="history.back()" class="w-9 h-9 bg-white/20 rounded-xl flex items-center justify-center border border-white/20 active:bg-white/30 flex-shrink-0">
            <i class="bi bi-arrow-left text-lg"></i>
        </button>
        <div>
            <div class="font-black text-sm leading-tight">ชุมนุมของฉัน</div>
            <div class="text-violet-200 text-xs font-bold"><?= htmlspecialchars($student_name, ENT_QUOTES, 'UTF-8') ?></div>
        </div>
    </div>
</header>

<div class="max-w-lg mx-auto px-4 py-5 space-y-4">

<?php if (!$myReg): ?>
<div class="bg-white rounded-3xl shadow-sm border border-slate-100 p-8 text-center fade-up">
    <i class="bi bi-people text-4xl text-slate-300 block mb-2"></i>
    <p class="font-black text-slate-600">ยังไม่ได้เลือกชุมนุม</p>
    <a href="/student_club/index.php" class="inline-block mt-3 px-5 py-2 bg-violet-600 text-white rounded-2xl text-sm font-black">เลือกชุมนุม</a>
</div>
<?php else: ?>

<!-- Club Info -->
<div class="bg-gradient-to-br from-violet-600 to-purple-600 rounded-3xl p-5 text-white shadow-lg fade-up">
    <div class="flex items-start gap-3">
        <div class="w-12 h-12 bg-white/20 rounded-2xl flex items-center justify-center flex-shrink-0">
            <i class="bi bi-people-fill text-2xl"></i>
        </div>
        <div class="flex-1">
            <p class="font-black text-lg leading-tight"><?= htmlspecialchars($myReg['club_name'], ENT_QUOTES, 'UTF-8') ?></p>
            <p class="text-violet-200 text-xs mt-1">
                <i class="bi bi-person me-1"></i><?= htmlspecialchars($myReg['teacher_name'] ?? '-', ENT_QUOTES, 'UTF-8') ?>
                <?= $myReg['room'] ? ' · <i class="bi bi-door-open me-1"></i>'.htmlspecialchars($myReg['room'],ENT_QUOTES,'UTF-8') : '' ?>
            </p>
        </div>
    </div>
    <?php if ($myReg['description']): ?>
    <p class="text-violet-100 text-xs mt-3 leading-relaxed"><?= htmlspecialchars($myReg['description'], ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>
</div>

<!-- Attendance Stats -->
<div class="bg-white rounded-3xl shadow-sm border border-slate-100 p-5 fade-up" style="animation-delay:.05s">
    <p class="text-xs font-black text-slate-500 uppercase tracking-wider mb-3">สถิติการเข้าร่วม</p>
    <div class="flex items-center gap-4">
        <div class="text-center">
            <div class="text-3xl font-black <?= $pct >= $threshold ? 'text-emerald-600' : 'text-rose-500' ?>"><?= $pct ?>%</div>
            <div class="text-xs text-slate-400 font-bold">เข้าร่วม</div>
        </div>
        <div class="flex-1">
            <div class="flex justify-between text-xs text-slate-400 mb-1">
                <span><?= $presentCnt ?>/<?= $totalDone ?> คาบ</span>
                <span>เกณฑ์ผ่าน <?= $threshold ?>%</span>
            </div>
            <div class="bg-slate-100 rounded-full h-3">
                <div class="<?= $pct >= $threshold ? 'bg-emerald-500' : 'bg-rose-500' ?> h-3 rounded-full transition-all" style="width:<?= min(100,$pct) ?>%"></div>
            </div>
            <div class="mt-1.5 text-xs <?= $pct >= $threshold ? 'text-emerald-600' : 'text-rose-500' ?> font-bold">
                <?= $pct >= $threshold ? '✓ อยู่ในเกณฑ์ผ่าน' : '✗ ต่ำกว่าเกณฑ์' ?>
            </div>
        </div>
    </div>

    <?php if ($myResult): ?>
    <div class="mt-4 pt-3 border-t border-slate-100 flex items-center justify-between">
        <span class="text-xs font-black text-slate-500">ผลการประเมิน</span>
        <span class="px-3 py-1 rounded-full text-xs font-black <?= $myResult['result']==='pass' ? 'bg-emerald-100 text-emerald-700' : ($myResult['result']==='fail' ? 'bg-rose-100 text-rose-700' : 'bg-amber-100 text-amber-700') ?>">
            <?= ['pass'=>'✓ ผ่าน','fail'=>'✗ ไม่ผ่าน','pending'=>'รอประเมิน'][$myResult['result']] ?? '-' ?>
        </span>
    </div>
    <?php if ($myResult['teacher_comment']): ?>
    <p class="text-xs text-slate-500 mt-1 italic">"<?= htmlspecialchars($myResult['teacher_comment'], ENT_QUOTES, 'UTF-8') ?>"</p>
    <?php endif; ?>
    <?php endif; ?>
</div>

<!-- Session History -->
<?php if (!empty($sessions)): ?>
<div class="fade-up" style="animation-delay:.1s">
    <div class="flex items-center gap-2 px-1 mb-3">
        <div class="w-8 h-8 bg-violet-50 rounded-xl flex items-center justify-center flex-shrink-0">
            <i class="bi bi-calendar-check text-violet-600"></i>
        </div>
        <h2 class="font-black text-slate-800">ประวัติคาบ</h2>
    </div>
    <div class="space-y-2">
    <?php foreach ($sessions as $s):
        $attCls = ['present'=>'text-emerald-600 bg-emerald-50','late'=>'text-amber-600 bg-amber-50','leave'=>'text-blue-600 bg-blue-50','absent'=>'text-rose-600 bg-rose-50'];
        $attLbl = ['present'=>'✓ มา','late'=>'⏰ สาย','leave'=>'📋 ลา','absent'=>'✗ ขาด'];
        $ac = $attCls[$s['att_status'] ?? ''] ?? 'text-slate-400 bg-slate-50';
        $al = $attLbl[$s['att_status'] ?? ''] ?? '-';
    ?>
    <div class="bg-white rounded-2xl border border-slate-100 px-4 py-3 flex items-center gap-3">
        <div class="flex-1 min-w-0">
            <div class="text-xs font-black text-slate-600"><?= fmtDate($s['session_date'], $thaiMonths) ?><?= $s['period'] ? ' · '.$s['period'] : '' ?></div>
            <div class="text-slate-500 text-xs mt-0.5 truncate"><?= htmlspecialchars($s['topic'] ?? '-', ENT_QUOTES, 'UTF-8') ?></div>
        </div>
        <?php if ($s['session_status'] === 'done'): ?>
        <span class="px-2 py-1 rounded-lg text-xs font-black <?= $ac ?> flex-shrink-0"><?= $al ?></span>
        <?php else: ?>
        <span class="px-2 py-1 rounded-lg text-xs font-black text-slate-400 bg-slate-50 flex-shrink-0">วางแผน</span>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<?php endif; ?>
</div>
</body>
</html>
