<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/_guard.php';

$pdo       = getPdo();
$uid       = (int)$_SESSION['student_uid'];
$code      = $_SESSION['student_code'];
$name      = $_SESSION['student_name'];
$class     = $_SESSION['student_class'];
$hasBus    = !empty($_SESSION['bus_student_id']);
$semester  = busGetSemester(); // reuse helper from bus/config.php (loaded via config.php → bus auto-load? No)

// Load bus helper independently
if (!function_exists('busGetSemester')) {
    function busGetSemester(): string {
        $m = (int)date('n'); $y = (int)date('Y') + 543;
        return $y . '-' . ($m >= 5 && $m <= 10 ? 1 : 2);
    }
}
$semester = busGetSemester();

$flashMsg  = $_GET['msg'] ?? '';
$flashType = $_GET['t']   ?? 'ok';

// ── Attendance stats (this semester / academic year) ──────────────
$attStats = ['total' => 0, 'present' => 0, 'absent' => 0, 'late' => 0, 'leave' => 0];
try {
    // Current Thai academic year (May–April)
    $month = (int)date('n');
    $yearStart = (int)date('Y') . ($month >= 5 ? '-05-01' : '-01-01');
    // Actually filter by current semester months
    $semParts  = explode('-', $semester);
    $thaiYear  = (int)$semParts[0]; $sem = (int)($semParts[1] ?? 1);
    $gregYear  = $thaiYear - 543;
    $dateFrom  = $sem === 1 ? "$gregYear-05-01" : "$gregYear-11-01";
    $dateTo    = $sem === 1 ? "$gregYear-10-31" : ($gregYear + 1) . "-04-30";

    $stmt = $pdo->prepare("
        SELECT status, COUNT(*) as cnt
        FROM att_attendance
        WHERE student_id = ? AND date BETWEEN ? AND ?
        GROUP BY status
    ");
    $stmt->execute([$code, $dateFrom, $dateTo]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $attStats['total'] += (int)$row['cnt'];
        $map = ['มา'=>'present','ขาด'=>'absent','สาย'=>'late','ลา'=>'leave','โดด'=>'absent'];
        $key = $map[$row['status']] ?? null;
        if ($key) $attStats[$key] += (int)$row['cnt'];
    }
} catch (Exception $e) { error_log($e->getMessage()); }

$attPct = $attStats['total'] > 0
    ? round(($attStats['present'] + $attStats['late']) / $attStats['total'] * 100)
    : null;

// ── Transport survey status ────────────────────────────────────────
$transport = null;
try {
    $stmt = $pdo->prepare("SELECT * FROM student_transport WHERE att_student_id = ? AND semester = ? LIMIT 1");
    $stmt->execute([$uid, $semester]);
    $transport = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) { error_log($e->getMessage()); }

// ── Bus registration status ────────────────────────────────────────
$busReg = null;
if ($hasBus) {
    try {
        $stmt = $pdo->prepare("
            SELECT r.status, r.created_at, rt.route_name, rt.route_code, rt.price
            FROM bus_registrations r
            JOIN bus_routes rt ON rt.id = r.route_id
            WHERE r.student_id = ? AND r.semester = ?
            ORDER BY r.created_at DESC LIMIT 1
        ");
        $stmt->execute([$_SESSION['bus_student_id'], $semester]);
        $busReg = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) { error_log($e->getMessage()); }
}

// Transport label map
$typeLabel = [
    'school_bus'  => ['label'=>'ใช้รถรับส่งโรงเรียน', 'icon'=>'bi-bus-front-fill',   'color'=>'orange'],
    'motorcycle'  => ['label'=>'ขี่มอเตอร์ไซค์',       'icon'=>'bi-bicycle',           'color'=>'amber'],
    'bicycle'     => ['label'=>'ขี่จักรยาน',            'icon'=>'bi-bicycle',           'color'=>'lime'],
    'walk'        => ['label'=>'เดินมาโรงเรียน',         'icon'=>'bi-person-walking',    'color'=>'emerald'],
    'private_car' => ['label'=>'รถส่วนตัว/ผู้ปกครอง',  'icon'=>'bi-car-front-fill',    'color'=>'blue'],
    'other'       => ['label'=>'อื่นๆ',                  'icon'=>'bi-three-dots-vertical','color'=>'slate'],
];

// Thai greeting by hour
$h = (int)date('H');
$greeting = $h < 12 ? 'อรุณสวัสดิ์' : ($h < 17 ? 'สวัสดีตอนบ่าย' : 'สวัสดีตอนเย็น');
?><!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<title>พอร์ทัลนักเรียน | โรงเรียนละลมวิทยา</title>
<meta name="theme-color" content="#0d9488">
<meta name="apple-mobile-web-app-capable" content="yes">
<link href="https://fonts.googleapis.com/css2?family=Prompt:wght@400;600;700;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<script src="https://cdn.tailwindcss.com"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
body { font-family:'Prompt',sans-serif; overscroll-behavior-y:contain; }
@keyframes fadeUp { from{opacity:0;transform:translateY(16px)} to{opacity:1;transform:translateY(0)} }
.fade-up { animation:fadeUp .4s ease-out both; }
</style>
</head>
<body class="bg-slate-100 min-h-screen" style="padding-bottom:env(safe-area-inset-bottom)">

<!-- ── Header ──────────────────────────────────────────────────── -->
<header class="bg-gradient-to-r from-teal-600 to-cyan-600 text-white sticky top-0 z-50 shadow-lg"
        style="padding-top:env(safe-area-inset-top)">
    <div class="max-w-lg mx-auto flex items-center justify-between px-4 py-3">
        <div class="flex items-center gap-3">
            <div class="w-9 h-9 bg-white/20 rounded-xl flex items-center justify-center border border-white/20">
                <i class="bi bi-mortarboard-fill text-base"></i>
            </div>
            <div>
                <div class="font-black text-sm leading-tight">พอร์ทัลนักเรียน</div>
                <div class="text-teal-200 text-[10px] font-bold">โรงเรียนละลมวิทยา</div>
            </div>
        </div>
        <a href="/student/logout.php"
           class="w-9 h-9 bg-white/15 rounded-xl flex items-center justify-center border border-white/20 active:bg-white/25">
            <i class="bi bi-box-arrow-right"></i>
        </a>
    </div>
</header>

<div class="max-w-lg mx-auto px-4 py-5 space-y-4">

<?php if ($flashMsg): ?>
<div class="rounded-2xl px-4 py-3 text-sm font-bold flex items-center gap-2
            <?= $flashType === 'ok' ? 'bg-emerald-50 text-emerald-700 border border-emerald-100' : 'bg-rose-50 text-rose-700 border border-rose-100' ?>">
    <i class="bi bi-<?= $flashType === 'ok' ? 'check-circle-fill' : 'exclamation-triangle-fill' ?>"></i>
    <?= htmlspecialchars($flashMsg, ENT_QUOTES, 'UTF-8') ?>
</div>
<?php endif; ?>

<!-- ── Profile Card ──────────────────────────────────────────────── -->
<div class="bg-gradient-to-br from-teal-500 to-cyan-600 rounded-3xl p-5 text-white shadow-xl shadow-teal-200/60 relative overflow-hidden fade-up">
    <div class="absolute -right-8 -bottom-8 w-32 h-32 bg-white/10 rounded-full pointer-events-none"></div>
    <div class="absolute right-16 -top-4 w-20 h-20 bg-white/5 rounded-full pointer-events-none"></div>
    <div class="flex items-center gap-4">
        <div class="w-14 h-14 bg-white/20 rounded-2xl flex items-center justify-center border border-white/30 flex-shrink-0 text-2xl font-black">
            <?= mb_substr($name, 0, 1, 'UTF-8') ?>
        </div>
        <div class="flex-1 min-w-0">
            <p class="text-teal-200 text-[10px] font-bold"><?= htmlspecialchars($greeting) ?></p>
            <p class="font-black text-base leading-tight truncate"><?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?></p>
            <div class="flex items-center gap-2 mt-1">
                <span class="px-2 py-0.5 bg-white/15 rounded-full text-[10px] font-black border border-white/20">
                    <?= htmlspecialchars($class, ENT_QUOTES, 'UTF-8') ?>
                </span>
                <span class="text-teal-200 text-[10px]">รหัส <?= htmlspecialchars($code, ENT_QUOTES, 'UTF-8') ?></span>
            </div>
        </div>
    </div>

    <!-- Attendance bar -->
    <?php if ($attStats['total'] > 0): ?>
    <div class="mt-4 pt-4 border-t border-white/20">
        <div class="flex items-center justify-between mb-1.5">
            <span class="text-teal-100 text-[10px] font-bold">การเข้าเรียนภาคเรียนนี้</span>
            <span class="text-white font-black text-sm"><?= $attPct ?>%</span>
        </div>
        <div class="h-2 bg-white/20 rounded-full overflow-hidden">
            <div class="h-full rounded-full transition-all duration-1000
                        <?= $attPct >= 80 ? 'bg-emerald-300' : ($attPct >= 60 ? 'bg-amber-300' : 'bg-rose-300') ?>"
                 style="width:<?= $attPct ?>%"></div>
        </div>
        <div class="flex gap-3 mt-2 text-[10px] text-teal-200 font-bold">
            <span><i class="bi bi-check-circle"></i> มา <?= $attStats['present'] + $attStats['late'] ?></span>
            <span><i class="bi bi-x-circle"></i> ขาด <?= $attStats['absent'] ?></span>
            <span><i class="bi bi-calendar-x"></i> ลา <?= $attStats['leave'] ?></span>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- ── Alert: ยังไม่กรอกแบบสำรวจ ────────────────────────────────── -->
<?php if (!$transport): ?>
<a href="/student/transport.php"
   class="flex items-center gap-3 bg-amber-50 border border-amber-200 rounded-2xl px-4 py-3.5 active:bg-amber-100 transition-colors">
    <div class="w-10 h-10 bg-amber-100 rounded-xl flex items-center justify-center flex-shrink-0">
        <i class="bi bi-exclamation-triangle-fill text-amber-500 text-lg"></i>
    </div>
    <div class="flex-1 min-w-0">
        <p class="font-black text-amber-800 text-sm">ยังไม่ได้กรอกแบบสำรวจการเดินทาง</p>
        <p class="text-amber-600 text-[11px] font-medium">กรุณากรอกเพื่อช่วยโรงเรียนวางแผนรถรับส่ง</p>
    </div>
    <i class="bi bi-chevron-right text-amber-400"></i>
</a>
<?php endif; ?>

<!-- ── Feature Grid ───────────────────────────────────────────────── -->
<div class="grid grid-cols-2 gap-3">

    <!-- การเดินทาง -->
    <a href="/student/transport.php"
       class="bg-white rounded-3xl p-4 shadow-sm border border-slate-100 active:scale-95 transition-transform flex flex-col gap-3">
        <div class="w-12 h-12 bg-orange-50 rounded-2xl flex items-center justify-center">
            <i class="bi bi-signpost-fill text-orange-500 text-2xl"></i>
        </div>
        <div>
            <p class="font-black text-slate-700 text-sm leading-tight">การเดินทาง</p>
            <?php if ($transport): ?>
                <?php $tInfo = $typeLabel[$transport['transport_type']] ?? ['label'=>$transport['transport_type'],'color'=>'slate']; ?>
                <p class="text-<?= $tInfo['color'] ?>-600 text-[11px] font-bold mt-0.5"><?= htmlspecialchars($tInfo['label']) ?></p>
                <span class="inline-block mt-1 px-2 py-0.5 rounded-full text-[9px] font-black
                             <?= $transport['status'] === 'confirmed' ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700' ?>">
                    <?= $transport['status'] === 'confirmed' ? 'ยืนยันแล้ว' : 'รอยืนยัน' ?>
                </span>
            <?php else: ?>
                <p class="text-slate-400 text-[11px] font-bold mt-0.5">ยังไม่ได้กรอก</p>
            <?php endif; ?>
        </div>
    </a>

    <!-- เวลาเรียน -->
    <a href="/student/attendance.php"
       class="bg-white rounded-3xl p-4 shadow-sm border border-slate-100 active:scale-95 transition-transform flex flex-col gap-3">
        <div class="w-12 h-12 bg-blue-50 rounded-2xl flex items-center justify-center">
            <i class="bi bi-calendar-check-fill text-blue-500 text-2xl"></i>
        </div>
        <div>
            <p class="font-black text-slate-700 text-sm leading-tight">เวลาเรียน</p>
            <?php if ($attStats['total'] > 0): ?>
                <p class="text-blue-600 text-[11px] font-bold mt-0.5"><?= $attPct ?>% เข้าเรียน</p>
                <p class="text-slate-400 text-[10px]"><?= $attStats['total'] ?> คาบรวม</p>
            <?php else: ?>
                <p class="text-slate-400 text-[11px] font-bold mt-0.5">ดูประวัติ</p>
            <?php endif; ?>
        </div>
    </a>

    <!-- รถรับส่ง -->
    <?php if ($hasBus): ?>
    <a href="/bus/dashboard.php"
       class="bg-white rounded-3xl p-4 shadow-sm border border-slate-100 active:scale-95 transition-transform flex flex-col gap-3">
        <div class="w-12 h-12 bg-orange-50 rounded-2xl flex items-center justify-center">
            <i class="bi bi-bus-front-fill text-orange-500 text-2xl"></i>
        </div>
        <div>
            <p class="font-black text-slate-700 text-sm leading-tight">รถรับส่ง</p>
            <?php if ($busReg): ?>
                <p class="text-orange-600 text-[11px] font-bold mt-0.5">สาย <?= htmlspecialchars($busReg['route_code'], ENT_QUOTES, 'UTF-8') ?></p>
                <span class="inline-block mt-1 px-2 py-0.5 rounded-full text-[9px] font-black
                             <?= $busReg['status'] === 'active' ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500' ?>">
                    <?= $busReg['status'] === 'active' ? 'ลงทะเบียนแล้ว' : htmlspecialchars($busReg['status']) ?>
                </span>
            <?php else: ?>
                <p class="text-slate-400 text-[11px] font-bold mt-0.5">ดูสถานะ</p>
            <?php endif; ?>
        </div>
    </a>
    <?php else: ?>
    <!-- ยังไม่มีบัญชีรถ: แสดงเป็น placeholder ชี้ไป transport survey -->
    <a href="/student/transport.php"
       class="bg-white rounded-3xl p-4 shadow-sm border border-slate-100 active:scale-95 transition-transform flex flex-col gap-3">
        <div class="w-12 h-12 bg-orange-50 rounded-2xl flex items-center justify-center">
            <i class="bi bi-bus-front-fill text-orange-400 text-2xl"></i>
        </div>
        <div>
            <p class="font-black text-slate-700 text-sm leading-tight">รถรับส่ง</p>
            <p class="text-slate-400 text-[11px] font-bold mt-0.5">สำรวจการเดินทาง</p>
        </div>
    </a>
    <?php endif; ?>

    <!-- พฤติกรรม (coming soon) -->
    <div class="bg-white rounded-3xl p-4 shadow-sm border border-slate-100 opacity-60 flex flex-col gap-3">
        <div class="w-12 h-12 bg-violet-50 rounded-2xl flex items-center justify-center">
            <i class="bi bi-star-fill text-violet-400 text-2xl"></i>
        </div>
        <div>
            <p class="font-black text-slate-700 text-sm leading-tight">คะแนนพฤติกรรม</p>
            <span class="inline-block mt-1 px-2 py-0.5 bg-slate-100 text-slate-400 rounded-full text-[9px] font-black">เร็วๆ นี้</span>
        </div>
    </div>

</div>

<!-- ── Second row: ใบลา + ชุมนุม (coming soon) ─────────────────── -->
<div class="grid grid-cols-2 gap-3">
    <div class="bg-white rounded-3xl p-4 shadow-sm border border-slate-100 opacity-50 flex items-center gap-3">
        <div class="w-10 h-10 bg-rose-50 rounded-xl flex items-center justify-center flex-shrink-0">
            <i class="bi bi-file-earmark-text-fill text-rose-400 text-xl"></i>
        </div>
        <div>
            <p class="font-black text-slate-600 text-xs">ใบลาออนไลน์</p>
            <p class="text-slate-400 text-[10px]">เร็วๆ นี้</p>
        </div>
    </div>
    <div class="bg-white rounded-3xl p-4 shadow-sm border border-slate-100 opacity-50 flex items-center gap-3">
        <div class="w-10 h-10 bg-indigo-50 rounded-xl flex items-center justify-center flex-shrink-0">
            <i class="bi bi-people-fill text-indigo-400 text-xl"></i>
        </div>
        <div>
            <p class="font-black text-slate-600 text-xs">เลือกชุมนุม</p>
            <p class="text-slate-400 text-[10px]">เร็วๆ นี้</p>
        </div>
    </div>
</div>

<!-- ── Footer ──────────────────────────────────────────────────── -->
<p class="text-center text-[10px] text-slate-400 font-bold pt-2 pb-4">
    © <?= date('Y') ?> โรงเรียนละลมวิทยา &nbsp;·&nbsp; ภาคเรียน <?= htmlspecialchars($semester) ?>
</p>

</div><!-- /container -->
</body>
</html>
