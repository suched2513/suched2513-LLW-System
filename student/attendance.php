<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/_guard.php';

$pdo  = getPdo();
$code = $_SESSION['student_code'];
$name = $_SESSION['student_name'];

if (!function_exists('busGetSemester')) {
    function busGetSemester(): string {
        $m = (int)date('n'); $y = (int)date('Y') + 543;
        return $y . '-' . ($m >= 5 && $m <= 10 ? 1 : 2);
    }
}
$semester = busGetSemester();
$parts    = explode('-', $semester);
$thaiYear = (int)$parts[0];
$sem      = (int)($parts[1] ?? 1);
$gregYear = $thaiYear - 543;
$dateFrom = $sem === 1 ? "$gregYear-05-01" : "$gregYear-11-01";
$dateTo   = $sem === 1 ? "$gregYear-10-31" : ($gregYear + 1) . "-04-30";

// ── Summary per subject ────────────────────────────────────────────
$subjects = [];
try {
    $stmt = $pdo->prepare("
        SELECT
            s.subject_name, s.subject_code,
            COUNT(*)                                             AS total,
            SUM(a.status IN ('มา','สาย'))                       AS present,
            SUM(a.status = 'ขาด')                               AS absent,
            SUM(a.status = 'โดด')                               AS skip,
            SUM(a.status = 'ลา')                                AS leave,
            SUM(a.status = 'สาย')                               AS late
        FROM att_attendance a
        JOIN att_subjects s ON s.id = a.subject_id
        WHERE a.student_id = ? AND a.date BETWEEN ? AND ?
        GROUP BY s.id, s.subject_name, s.subject_code
        ORDER BY s.subject_code
    ");
    $stmt->execute([$code, $dateFrom, $dateTo]);
    $subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { error_log($e->getMessage()); }

// ── Recent records ─────────────────────────────────────────────────
$recent = [];
try {
    $stmt = $pdo->prepare("
        SELECT a.date, a.status, a.period, a.note, s.subject_name
        FROM att_attendance a
        JOIN att_subjects s ON s.id = a.subject_id
        WHERE a.student_id = ? AND a.date BETWEEN ? AND ?
        ORDER BY a.date DESC, a.period DESC
        LIMIT 30
    ");
    $stmt->execute([$code, $dateFrom, $dateTo]);
    $recent = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { error_log($e->getMessage()); }

// Overall stats
$total   = array_sum(array_column($subjects, 'total'));
$present = array_sum(array_column($subjects, 'present'));
$absent  = array_sum(array_column($subjects, 'absent'));
$leave   = array_sum(array_column($subjects, 'leave'));
$pct     = $total > 0 ? round($present / $total * 100) : 0;

$statusStyle = [
    'มา'  => ['bg'=>'bg-emerald-100','text'=>'text-emerald-700','label'=>'มา'],
    'สาย' => ['bg'=>'bg-amber-100',  'text'=>'text-amber-700',  'label'=>'สาย'],
    'ขาด' => ['bg'=>'bg-rose-100',   'text'=>'text-rose-700',   'label'=>'ขาด'],
    'โดด' => ['bg'=>'bg-red-100',    'text'=>'text-red-700',    'label'=>'โดด'],
    'ลา'  => ['bg'=>'bg-blue-100',   'text'=>'text-blue-700',   'label'=>'ลา'],
];

$thaiMonths = ['','ม.ค.','ก.พ.','มี.ค.','เม.ย.','พ.ค.','มิ.ย.','ก.ค.','ส.ค.','ก.ย.','ต.ค.','พ.ย.','ธ.ค.'];
function fmtDate(string $d, array $months): string {
    [$y,$m,$dd] = explode('-', $d);
    return (int)$dd . ' ' . $months[(int)$m] . ' ' . ((int)$y + 543);
}
?><!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<title>เวลาเรียน | นักเรียน LLW</title>
<meta name="theme-color" content="#2563eb">
<link href="https://fonts.googleapis.com/css2?family=Prompt:wght@400;600;700;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<script src="https://cdn.tailwindcss.com"></script>
<style>
body { font-family:'Prompt',sans-serif; }
</style>
</head>
<body class="bg-slate-100 min-h-screen" style="padding-bottom:env(safe-area-inset-bottom)">

<!-- Header -->
<header class="bg-gradient-to-r from-blue-600 to-indigo-600 text-white sticky top-0 z-50 shadow-md"
        style="padding-top:env(safe-area-inset-top)">
    <div class="max-w-lg mx-auto flex items-center gap-3 px-4 py-3">
        <a href="/student/dashboard.php"
           class="w-9 h-9 bg-white/20 rounded-xl flex items-center justify-center border border-white/20 active:bg-white/30 flex-shrink-0">
            <i class="bi bi-arrow-left"></i>
        </a>
        <div>
            <div class="font-black text-sm leading-tight">เวลาเรียน</div>
            <div class="text-blue-200 text-xs font-bold">ภาคเรียน <?= htmlspecialchars($semester) ?></div>
        </div>
    </div>
</header>

<div class="max-w-lg mx-auto px-4 py-5 space-y-4">

    <!-- Profile + Overall Stats -->
    <div class="bg-gradient-to-br from-blue-500 to-indigo-600 rounded-3xl p-5 text-white shadow-xl shadow-blue-200/60 relative overflow-hidden">
        <div class="absolute -right-6 -bottom-6 w-28 h-28 bg-white/10 rounded-full pointer-events-none"></div>
        <div class="flex items-center gap-3 mb-4">
            <div class="w-12 h-12 bg-white/20 rounded-2xl flex items-center justify-center border border-white/30 flex-shrink-0 font-black text-xl">
                <?= mb_substr($name, 0, 1, 'UTF-8') ?>
            </div>
            <div class="flex-1 min-w-0">
                <p class="font-black text-sm leading-tight truncate"><?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?></p>
                <p class="text-blue-200 text-xs"><?= htmlspecialchars($_SESSION['student_class'], ENT_QUOTES, 'UTF-8') ?></p>
            </div>
            <div class="text-right">
                <div class="text-3xl font-black leading-none"><?= $pct ?><span class="text-lg">%</span></div>
                <div class="text-blue-200 text-xs font-bold">เข้าเรียน</div>
            </div>
        </div>
        <!-- Progress bar -->
        <div class="h-2.5 bg-white/20 rounded-full overflow-hidden mb-3">
            <div class="h-full rounded-full <?= $pct >= 80 ? 'bg-emerald-300' : ($pct >= 60 ? 'bg-amber-300' : 'bg-rose-300') ?>"
                 style="width:<?= $pct ?>%"></div>
        </div>
        <div class="grid grid-cols-4 gap-2 text-center">
            <?php foreach ([
                ['v'=>$total,   'l'=>'ทั้งหมด', 'o'=>'opacity-80'],
                ['v'=>$present, 'l'=>'มาเรียน',  'o'=>'text-emerald-300'],
                ['v'=>$absent,  'l'=>'ขาดเรียน', 'o'=>'text-rose-300'],
                ['v'=>$leave,   'l'=>'ลา',       'o'=>'text-blue-200'],
            ] as $s): ?>
            <div>
                <div class="text-xl font-black <?= $s['o'] ?>"><?= $s['v'] ?></div>
                <div class="text-xs font-bold text-blue-200"><?= $s['l'] ?></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <?php if (empty($subjects)): ?>
    <div class="bg-white rounded-3xl p-8 text-center shadow-sm border border-slate-100">
        <i class="bi bi-calendar-x text-5xl text-slate-300"></i>
        <p class="font-black text-slate-500 mt-4">ยังไม่มีข้อมูลการเข้าเรียน</p>
        <p class="text-slate-400 text-xs mt-1">สำหรับภาคเรียน <?= htmlspecialchars($semester) ?></p>
    </div>
    <?php else: ?>

    <!-- Per-subject breakdown -->
    <div class="bg-white rounded-3xl shadow-sm border border-slate-100 overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-50">
            <h2 class="font-black text-slate-700 text-sm flex items-center gap-2">
                <i class="bi bi-book-fill text-blue-500"></i>แยกตามวิชา
            </h2>
        </div>
        <div class="divide-y divide-slate-50">
            <?php foreach ($subjects as $sub):
                $subPct = $sub['total'] > 0 ? round($sub['present'] / $sub['total'] * 100) : 0;
                $barColor = $subPct >= 80 ? 'bg-emerald-400' : ($subPct >= 60 ? 'bg-amber-400' : 'bg-rose-400');
            ?>
            <div class="px-5 py-4">
                <div class="flex items-start justify-between gap-2 mb-2">
                    <div class="flex-1 min-w-0">
                        <p class="font-black text-slate-700 text-sm truncate"><?= htmlspecialchars($sub['subject_name'], ENT_QUOTES, 'UTF-8') ?></p>
                        <p class="text-slate-400 text-sm"><?= htmlspecialchars($sub['subject_code'], ENT_QUOTES, 'UTF-8') ?></p>
                    </div>
                    <div class="text-right flex-shrink-0">
                        <span class="font-black text-sm <?= $subPct >= 80 ? 'text-emerald-600' : ($subPct >= 60 ? 'text-amber-600' : 'text-rose-600') ?>"><?= $subPct ?>%</span>
                        <p class="text-slate-400 text-xs"><?= $sub['present'] ?>/<?= $sub['total'] ?> คาบ</p>
                    </div>
                </div>
                <div class="h-1.5 bg-slate-100 rounded-full overflow-hidden mb-2">
                    <div class="h-full rounded-full <?= $barColor ?>" style="width:<?= $subPct ?>%"></div>
                </div>
                <div class="flex gap-3 text-xs font-bold text-slate-400">
                    <span class="text-emerald-600">มา <?= $sub['present'] ?></span>
                    <span class="text-rose-500">ขาด <?= $sub['absent'] + $sub['skip'] ?></span>
                    <span class="text-blue-500">ลา <?= $sub['leave'] ?></span>
                    <?php if ($sub['late'] > 0): ?><span class="text-amber-500">สาย <?= $sub['late'] ?></span><?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Recent records -->
    <?php if (!empty($recent)): ?>
    <div class="bg-white rounded-3xl shadow-sm border border-slate-100 overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-50">
            <h2 class="font-black text-slate-700 text-sm flex items-center gap-2">
                <i class="bi bi-clock-history text-blue-500"></i>บันทึกล่าสุด
            </h2>
        </div>
        <div class="divide-y divide-slate-50">
            <?php foreach ($recent as $rec):
                $st = $statusStyle[$rec['status']] ?? ['bg'=>'bg-slate-100','text'=>'text-slate-600','label'=>$rec['status']];
            ?>
            <div class="flex items-center gap-3 px-5 py-3.5">
                <span class="px-2.5 py-0.5 rounded-full text-xs font-black <?= $st['bg'] ?> <?= $st['text'] ?> flex-shrink-0 w-10 text-center">
                    <?= htmlspecialchars($st['label']) ?>
                </span>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-bold text-slate-700 truncate"><?= htmlspecialchars($rec['subject_name'], ENT_QUOTES, 'UTF-8') ?></p>
                    <p class="text-sm text-slate-400">คาบ <?= (int)$rec['period'] ?> · <?= htmlspecialchars(fmtDate($rec['date'], $thaiMonths)) ?></p>
                </div>
                <?php if ($rec['note']): ?>
                <span class="text-xs text-slate-400 italic max-w-[80px] truncate"><?= htmlspecialchars($rec['note'], ENT_QUOTES, 'UTF-8') ?></span>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <?php endif; // end subjects ?>

</div><!-- /container -->
</body>
</html>
