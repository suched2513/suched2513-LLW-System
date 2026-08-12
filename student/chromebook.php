<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/_guard.php';

$pdo  = getPdo();
$name = $_SESSION['student_name'];
$code = $_SESSION['student_code'];
$class = $_SESSION['student_class'];

$loans = [];
$hasRepairsTable = false;

try {
    $hasRepairsTable = !empty($pdo->query("SHOW TABLES LIKE 'cb_repairs'")->fetchAll());
    $hasImgCol = $hasRepairsTable
        ? !empty($pdo->query("SHOW COLUMNS FROM cb_repairs LIKE 'images'")->fetchAll())
        : false;
    $imgSql = $hasImgCol ? 'r.images,' : "'' AS images,";

    $repairJoin = $hasRepairsTable
        ? "LEFT JOIN cb_repairs r ON r.borrow_log_id = b.entry_id"
        : "";
    $repairSelect = $hasRepairsTable
        ? "r.id AS repair_id, r.status AS repair_status, r.description AS repair_desc,
           r.repair_notes, $imgSql r.created_at AS repair_date,"
        : "NULL AS repair_id, NULL AS repair_status, NULL AS repair_desc,
           NULL AS repair_notes, '' AS images, NULL AS repair_date,";

    $stmt = $pdo->prepare("
        SELECT b.entry_id, b.chromebook_id, b.chromebook_serial,
               b.status AS borrow_status, b.date_borrowed, b.date_returned,
               $repairSelect
               c.model
        FROM cb_students s
        JOIN cb_borrow_logs b ON b.borrower_id = s.student_id AND b.borrower_type = 'Student'
        $repairJoin
        LEFT JOIN cb_chromebooks c ON c.chromebook_id = b.chromebook_id
        WHERE s.name = ?
        ORDER BY b.date_borrowed DESC
    ");
    $stmt->execute([$name]);
    $loans = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log($e->getMessage());
}

// Group repairs per borrow_log (take latest non-returned repair)
$byEntry = [];
foreach ($loans as $row) {
    $eid = $row['entry_id'];
    if (!isset($byEntry[$eid])) {
        $byEntry[$eid] = $row;
        $byEntry[$eid]['repairs'] = [];
    }
    if ($row['repair_id']) {
        $byEntry[$eid]['repairs'][] = $row;
    }
}
$entries = array_values($byEntry);

$statusColor = [
    'Borrowed' => ['bg' => 'bg-amber-50', 'text' => 'text-amber-700', 'border' => 'border-amber-200', 'label' => 'กำลังยืม'],
    'Returned' => ['bg' => 'bg-emerald-50', 'text' => 'text-emerald-700', 'border' => 'border-emerald-200', 'label' => 'คืนแล้ว'],
];
$repairColor = [
    'รับแจ้ง'    => ['bg' => 'bg-rose-50',   'text' => 'text-rose-700',   'icon' => 'bi-exclamation-circle-fill'],
    'ส่งซ่อม'    => ['bg' => 'bg-orange-50', 'text' => 'text-orange-700', 'icon' => 'bi-tools'],
    'ซ่อมเสร็จ'  => ['bg' => 'bg-blue-50',   'text' => 'text-blue-700',   'icon' => 'bi-check-circle-fill'],
    'รับกลับ'    => ['bg' => 'bg-emerald-50','text' => 'text-emerald-700','icon' => 'bi-bag-check-fill'],
];
?><!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<title>Chromebook ของฉัน | โรงเรียนละลมวิทยา</title>
<meta name="theme-color" content="#0891b2">
<link href="https://fonts.googleapis.com/css2?family=Prompt:wght@400;600;700;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<script src="https://cdn.tailwindcss.com"></script>
<style>
body { font-family:'Prompt',sans-serif; overscroll-behavior-y:contain; }
@keyframes fadeUp { from{opacity:0;transform:translateY(14px)} to{opacity:1;transform:translateY(0)} }
.fade-up { animation:fadeUp .35s ease-out both; }
</style>
</head>
<body class="bg-slate-100 min-h-screen" style="padding-bottom:env(safe-area-inset-bottom)">

<!-- Header -->
<header class="bg-gradient-to-r from-cyan-600 to-blue-600 text-white sticky top-0 z-50 shadow-lg"
        style="padding-top:env(safe-area-inset-top)">
    <div class="max-w-2xl mx-auto flex items-center gap-3 px-4 py-3">
        <a href="/student/dashboard.php"
           class="w-9 h-9 bg-white/15 rounded-xl flex items-center justify-center border border-white/20 active:bg-white/25 flex-shrink-0">
            <i class="bi bi-arrow-left text-base"></i>
        </a>
        <div class="w-9 h-9 bg-white/20 rounded-xl flex items-center justify-center border border-white/20 flex-shrink-0">
            <i class="bi bi-laptop text-base"></i>
        </div>
        <div class="flex-1 min-w-0">
            <div class="font-black text-sm leading-tight">Chromebook ของฉัน</div>
            <div class="text-cyan-200 text-xs font-bold truncate"><?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?></div>
        </div>
    </div>
</header>

<div class="max-w-2xl mx-auto px-4 py-5 space-y-4">

<?php if (empty($entries)): ?>
<!-- ไม่มีข้อมูล -->
<div class="bg-white rounded-3xl p-10 text-center shadow-sm border border-slate-100 fade-up">
    <div class="w-16 h-16 bg-cyan-50 rounded-2xl flex items-center justify-center mx-auto mb-4">
        <i class="bi bi-laptop text-cyan-400 text-3xl"></i>
    </div>
    <p class="font-black text-slate-600">ไม่พบประวัติการยืม Chromebook</p>
    <p class="text-slate-400 text-sm mt-1">ชื่อนักเรียน: <?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?></p>
</div>

<?php else: ?>

<!-- Summary -->
<?php
$totalBorrowed  = count(array_filter($entries, fn($e) => $e['borrow_status'] === 'Borrowed'));
$totalRepairing = 0;
foreach ($entries as $e) {
    foreach ($e['repairs'] as $r) {
        if ($r['repair_status'] !== 'รับกลับ') { $totalRepairing++; break; }
    }
}
?>
<div class="grid grid-cols-3 gap-3 fade-up">
    <div class="bg-white rounded-2xl p-3.5 text-center shadow-sm border border-slate-100">
        <p class="text-2xl font-black text-slate-700"><?= count($entries) ?></p>
        <p class="text-[11px] font-bold text-slate-400 mt-0.5">ทั้งหมด</p>
    </div>
    <div class="bg-amber-50 rounded-2xl p-3.5 text-center shadow-sm border border-amber-100">
        <p class="text-2xl font-black text-amber-600"><?= $totalBorrowed ?></p>
        <p class="text-[11px] font-bold text-amber-500 mt-0.5">กำลังยืม</p>
    </div>
    <div class="bg-rose-50 rounded-2xl p-3.5 text-center shadow-sm border border-rose-100">
        <p class="text-2xl font-black text-rose-600"><?= $totalRepairing ?></p>
        <p class="text-[11px] font-bold text-rose-500 mt-0.5">อยู่ระหว่างซ่อม</p>
    </div>
</div>

<!-- Loan cards -->
<?php foreach ($entries as $i => $entry): ?>
<?php
$bs     = $statusColor[$entry['borrow_status']] ?? $statusColor['Borrowed'];
$activeRepairs = array_filter($entry['repairs'], fn($r) => $r['repair_status'] !== 'รับกลับ');
$latestRepair  = !empty($activeRepairs) ? array_values($activeRepairs)[0] : null;
?>
<div class="bg-white rounded-3xl shadow-sm border border-slate-100 overflow-hidden fade-up"
     style="animation-delay:<?= $i * 60 ?>ms">

    <!-- Device header -->
    <div class="p-4 border-b border-slate-50">
        <div class="flex items-start gap-3">
            <div class="w-12 h-12 bg-gradient-to-br from-cyan-400 to-blue-500 rounded-2xl flex items-center justify-center flex-shrink-0 shadow-lg shadow-cyan-200/50">
                <i class="bi bi-laptop text-white text-xl"></i>
            </div>
            <div class="flex-1 min-w-0">
                <p class="font-black text-slate-700 leading-tight">
                    <?= htmlspecialchars($entry['model'] ?? 'Chromebook', ENT_QUOTES, 'UTF-8') ?>
                </p>
                <p class="text-slate-400 text-xs font-bold mt-0.5">
                    รหัส: <?= htmlspecialchars($entry['chromebook_id'], ENT_QUOTES, 'UTF-8') ?>
                    <?php if ($entry['chromebook_serial']): ?>
                    &nbsp;·&nbsp; S/N: <?= htmlspecialchars($entry['chromebook_serial'], ENT_QUOTES, 'UTF-8') ?>
                    <?php endif; ?>
                </p>
            </div>
            <span class="px-2.5 py-1 rounded-full text-xs font-black border flex-shrink-0
                         <?= $bs['bg'] ?> <?= $bs['text'] ?> <?= $bs['border'] ?>">
                <?= $bs['label'] ?>
            </span>
        </div>
    </div>

    <!-- Dates -->
    <div class="px-4 py-3 grid grid-cols-2 gap-3 border-b border-slate-50">
        <div>
            <p class="text-[10px] font-black text-slate-400 uppercase tracking-wider">วันที่รับ</p>
            <p class="text-sm font-bold text-slate-700 mt-0.5">
                <?= $entry['date_borrowed'] ? date('d/m/Y', strtotime($entry['date_borrowed'])) : '—' ?>
            </p>
        </div>
        <div>
            <p class="text-[10px] font-black text-slate-400 uppercase tracking-wider">วันที่คืน</p>
            <p class="text-sm font-bold text-slate-700 mt-0.5">
                <?= $entry['date_returned'] ? date('d/m/Y', strtotime($entry['date_returned'])) : '—' ?>
            </p>
        </div>
    </div>

    <?php if ($latestRepair): ?>
    <!-- Repair status -->
    <?php $rc = $repairColor[$latestRepair['repair_status']] ?? ['bg'=>'bg-slate-50','text'=>'text-slate-600','icon'=>'bi-wrench']; ?>
    <div class="px-4 py-3 <?= $rc['bg'] ?>">
        <div class="flex items-start gap-2">
            <i class="<?= $rc['icon'] ?> <?= $rc['text'] ?> mt-0.5"></i>
            <div class="flex-1 min-w-0">
                <div class="flex items-center justify-between gap-2">
                    <p class="text-xs font-black <?= $rc['text'] ?>">
                        สถานะซ่อม: <?= htmlspecialchars($latestRepair['repair_status'], ENT_QUOTES, 'UTF-8') ?>
                    </p>
                    <?php if ($latestRepair['repair_date']): ?>
                    <p class="text-[10px] text-slate-400 font-bold flex-shrink-0">
                        <?= date('d/m/Y', strtotime($latestRepair['repair_date'])) ?>
                    </p>
                    <?php endif; ?>
                </div>
                <?php if ($latestRepair['repair_desc']): ?>
                <p class="text-xs text-slate-600 mt-0.5 leading-relaxed">
                    <?= htmlspecialchars($latestRepair['repair_desc'], ENT_QUOTES, 'UTF-8') ?>
                </p>
                <?php endif; ?>
                <?php if ($latestRepair['repair_notes']): ?>
                <p class="text-xs text-slate-500 mt-1 italic">
                    หมายเหตุ: <?= htmlspecialchars($latestRepair['repair_notes'], ENT_QUOTES, 'UTF-8') ?>
                </p>
                <?php endif; ?>

                <?php
                $imgs = array_filter(explode(',', $latestRepair['images'] ?? ''));
                if (!empty($imgs)): ?>
                <div class="flex gap-2 mt-2 flex-wrap">
                    <?php foreach ($imgs as $img): ?>
                    <img src="/chromebook/uploads/<?= htmlspecialchars(basename($img), ENT_QUOTES, 'UTF-8') ?>"
                         alt="รูปซ่อม"
                         class="w-14 h-14 object-cover rounded-xl border border-white shadow cursor-pointer"
                         onclick="viewImg(this.src)">
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php elseif (!empty($entry['repairs'])): ?>
    <!-- All repairs done -->
    <div class="px-4 py-3 bg-emerald-50">
        <p class="text-xs font-black text-emerald-700">
            <i class="bi bi-check-circle-fill me-1"></i> ซ่อมเสร็จ รับเครื่องคืนแล้ว
        </p>
    </div>
    <?php endif; ?>

</div>
<?php endforeach; ?>

<?php endif; ?>

<!-- Footer -->
<p class="text-center text-xs text-slate-400 font-bold pt-2 pb-4">
    ข้อมูล ณ <?= date('d/m/Y H:i') ?> &nbsp;·&nbsp; ติดต่อครูเพื่อแก้ไขข้อมูล
</p>

</div>

<!-- Image viewer -->
<div id="img-overlay" class="fixed inset-0 bg-black/80 z-50 hidden items-center justify-center p-4"
     onclick="closeImg()">
    <img id="img-view" src="" alt="" class="max-w-full max-h-full rounded-2xl shadow-2xl">
</div>

<script>
function viewImg(src) {
    document.getElementById('img-view').src = src;
    document.getElementById('img-overlay').classList.replace('hidden','flex');
}
function closeImg() {
    document.getElementById('img-overlay').classList.replace('flex','hidden');
}
</script>
</body>
</html>
