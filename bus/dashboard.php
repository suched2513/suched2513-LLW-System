<?php
session_start();
require_once __DIR__ . '/config.php';
busRequireStudent();

$pdo       = getPdo();
$busId     = (int)$_SESSION['bus_student_id'];
$studentSid = $_SESSION['bus_student_sid'];
$name      = $_SESSION['bus_student_name'];
$class     = $_SESSION['bus_student_class'];
$semester  = busGetSemester();
$semLabel  = busSemesterLabel($semester);

// Flash message from redirects
$flash     = $_GET['msg'] ?? '';
$flashType = $_GET['t'] ?? 'ok';

// Current registration
$reg  = null;
$paid = 0.0;
$cancelReq = null;
try {
    $stmt = $pdo->prepare("
        SELECT r.id, r.semester, r.status, r.registered_at, r.cancelled_at,
               rt.route_name, rt.route_code, rt.description, rt.price,
               rt.driver_name, rt.driver_phone
        FROM bus_registrations r
        JOIN bus_routes rt ON rt.id = r.route_id
        WHERE r.student_id = ? AND r.semester = ?
        LIMIT 1
    ");
    $stmt->execute([$busId, $semester]);
    $reg = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($reg) {
        $paid = busGetPaid($pdo, $reg['id']);
        $cStmt = $pdo->prepare("
            SELECT * FROM bus_cancel_requests
            WHERE registration_id = ?
            ORDER BY requested_at DESC LIMIT 1
        ");
        $cStmt->execute([$reg['id']]);
        $cancelReq = $cStmt->fetch(PDO::FETCH_ASSOC);
    }

    // Payment history (current + past)
    $histStmt = $pdo->prepare("
        SELECT p.*, r.semester, rt.route_name
        FROM bus_payments p
        JOIN bus_registrations r ON r.id = p.registration_id
        JOIN bus_routes rt ON rt.id = r.route_id
        WHERE p.student_id = ?
        ORDER BY p.payment_date DESC, p.created_at DESC
        LIMIT 20
    ");
    $histStmt->execute([$busId]);
    $history = $histStmt->fetchAll(PDO::FETCH_ASSOC);

    // Available routes (for registration prompt)
    $routes = $pdo->query("SELECT * FROM bus_routes WHERE is_active = 1 ORDER BY route_code")
                  ->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log($e->getMessage());
    $history = [];
    $routes  = [];
}

$balance  = $reg ? (float)$reg['price'] - $paid : 0;
$isPaidFull = $balance <= 0 && $reg;

$thaiMonths = ['','ม.ค.','ก.พ.','มี.ค.','เม.ย.','พ.ค.','มิ.ย.','ก.ค.','ส.ค.','ก.ย.','ต.ค.','พ.ย.','ธ.ค.'];
function thDate(string $d, array $m): string {
    if (!$d) return '-';
    $t = strtotime($d);
    return date('j', $t) . ' ' . $m[(int)date('n', $t)] . ' ' . ((int)date('Y', $t) + 543);
}
?><!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<title>ข้อมูลของฉัน | รถรับส่ง LLW</title>
<meta name="theme-color" content="#f97316">
<link href="https://fonts.googleapis.com/css2?family=Prompt:wght@400;600;700;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<script src="https://cdn.tailwindcss.com"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>body{font-family:'Prompt',sans-serif;}</style>
</head>
<body class="bg-slate-100 min-h-screen" style="padding-bottom:max(env(safe-area-inset-bottom),24px)">

<!-- App Header -->
<header class="bg-gradient-to-r from-orange-500 to-amber-500 text-white sticky top-0 z-50 shadow-lg" style="padding-top:env(safe-area-inset-top)">
    <div class="max-w-lg mx-auto flex items-center justify-between px-4 py-3.5">
        <div class="flex items-center gap-2.5">
            <div class="w-9 h-9 bg-white/20 rounded-xl flex items-center justify-center border border-white/20">
                <i class="bi bi-bus-front-fill text-lg"></i>
            </div>
            <div>
                <div class="font-black text-sm leading-tight">รถรับส่งนักเรียน</div>
                <div class="text-orange-100 text-[9px] font-bold uppercase tracking-wider">โรงเรียนละลมวิทยา</div>
            </div>
        </div>
        <div class="flex items-center gap-2">
            <div class="text-right hidden xs:block">
                <div class="font-bold text-xs leading-tight truncate max-w-[120px]"><?= htmlspecialchars($name) ?></div>
                <div class="text-orange-100 text-[10px]"><?= htmlspecialchars($class) ?></div>
            </div>
            <a href="/bus/logout.php" class="w-8 h-8 bg-white/15 rounded-xl flex items-center justify-center border border-white/20 active:bg-white/30 transition-colors">
                <i class="bi bi-power text-sm"></i>
            </a>
        </div>
    </div>
</header>

<div class="max-w-lg mx-auto px-4 pt-5 space-y-4">

    <?php if ($flash !== ''): ?>
    <div class="rounded-2xl px-4 py-3 text-sm font-bold flex items-center gap-2 <?= $flashType === 'ok' ? 'bg-emerald-50 text-emerald-700 border border-emerald-100' : 'bg-rose-50 text-rose-700 border border-rose-100' ?>">
        <i class="bi bi-<?= $flashType === 'ok' ? 'check-circle-fill' : 'exclamation-triangle-fill' ?> flex-shrink-0"></i>
        <?= htmlspecialchars($flash, ENT_QUOTES, 'UTF-8') ?>
    </div>
    <?php endif; ?>

    <!-- Student Info Card -->
    <div class="bg-gradient-to-br from-orange-500 to-amber-500 rounded-3xl p-5 text-white shadow-xl shadow-orange-200/50 relative overflow-hidden">
        <div class="absolute -right-6 -bottom-6 w-28 h-28 bg-white/10 rounded-full"></div>
        <div class="absolute -right-2 top-4 w-16 h-16 bg-white/5 rounded-full"></div>
        <div class="flex items-start justify-between relative">
            <div>
                <p class="text-orange-100 text-[10px] font-bold uppercase tracking-wider"><?= htmlspecialchars($semLabel) ?></p>
                <h2 class="text-xl font-black mt-0.5 leading-tight"><?= htmlspecialchars($name) ?></h2>
                <p class="text-orange-100 text-xs mt-1 flex items-center gap-1">
                    <i class="bi bi-mortarboard-fill"></i>
                    <?= htmlspecialchars($class) ?> &nbsp;·&nbsp; รหัส <?= htmlspecialchars($studentSid) ?>
                </p>
            </div>
            <div class="w-12 h-12 bg-white/15 rounded-2xl flex items-center justify-center border border-white/20 flex-shrink-0">
                <i class="bi bi-person-fill text-2xl"></i>
            </div>
        </div>
    </div>

    <!-- Registration Status -->
    <?php if (!$reg): ?>
    <!-- Not registered -->
    <div class="bg-white rounded-3xl p-5 shadow-sm border border-slate-100">
        <div class="flex items-center gap-3 mb-4">
            <div class="w-10 h-10 bg-slate-100 rounded-2xl flex items-center justify-center">
                <i class="bi bi-bus-front text-slate-400 text-xl"></i>
            </div>
            <div>
                <p class="font-black text-slate-700">ยังไม่ได้ลงทะเบียน</p>
                <p class="text-xs text-slate-400"><?= htmlspecialchars($semLabel) ?></p>
            </div>
        </div>
        <?php if (!empty($routes)): ?>
        <a href="/bus/register.php" class="flex items-center justify-center gap-2 w-full py-3.5 bg-gradient-to-r from-orange-500 to-amber-500 text-white rounded-2xl font-black text-sm shadow-lg shadow-orange-200/50 active:scale-95 transition-transform">
            <i class="bi bi-plus-circle-fill"></i> ลงทะเบียนใช้บริการ
        </a>
        <?php else: ?>
        <p class="text-center text-sm text-slate-400 py-4">ยังไม่มีสายรถเปิดให้บริการในขณะนี้</p>
        <?php endif; ?>
    </div>

    <?php else: ?>
    <!-- Registered — route info -->
    <?php
    $statusMap = [
        'active'         => ['bg-emerald-100 text-emerald-700', 'bi-check-circle-fill', 'ใช้บริการอยู่'],
        'pending_cancel' => ['bg-amber-100 text-amber-700',    'bi-hourglass-split',   'รออนุมัติยกเลิก'],
        'cancelled'      => ['bg-slate-100 text-slate-500',    'bi-x-circle-fill',     'ยกเลิกแล้ว'],
    ];
    [$sbadge, $sicon, $slabel] = $statusMap[$reg['status']] ?? $statusMap['cancelled'];
    ?>
    <div class="bg-white rounded-3xl p-5 shadow-sm border border-slate-100 space-y-3">
        <div class="flex items-start justify-between">
            <div class="flex items-center gap-3">
                <div class="w-11 h-11 bg-orange-100 rounded-2xl flex items-center justify-center flex-shrink-0">
                    <i class="bi bi-bus-front-fill text-orange-500 text-xl"></i>
                </div>
                <div>
                    <p class="font-black text-slate-800 text-sm"><?= htmlspecialchars($reg['route_name']) ?></p>
                    <p class="text-[10px] text-slate-400 font-bold">สาย <?= htmlspecialchars($reg['route_code']) ?></p>
                </div>
            </div>
            <span class="px-3 py-1 rounded-full text-[10px] font-black <?= $sbadge ?>">
                <i class="bi <?= $sicon ?> mr-1"></i><?= $slabel ?>
            </span>
        </div>
        <?php if ($reg['description']): ?>
        <p class="text-xs text-slate-500 bg-slate-50 rounded-xl px-3 py-2"><?= htmlspecialchars($reg['description']) ?></p>
        <?php endif; ?>
        <?php if ($reg['driver_name']): ?>
        <p class="text-xs text-slate-500 flex items-center gap-1.5">
            <i class="bi bi-person-fill text-slate-400"></i>
            คนขับ: <strong><?= htmlspecialchars($reg['driver_name']) ?></strong>
            <?php if ($reg['driver_phone']): ?>
            &nbsp;
            <a href="tel:<?= htmlspecialchars($reg['driver_phone']) ?>" class="text-orange-500 font-bold">
                <i class="bi bi-telephone-fill"></i> <?= htmlspecialchars($reg['driver_phone']) ?>
            </a>
            <?php endif; ?>
        </p>
        <?php endif; ?>
        <p class="text-xs text-slate-400">ลงทะเบียน: <?= thDate($reg['registered_at'], $thaiMonths) ?></p>
    </div>

    <!-- Payment Summary -->
    <div class="grid grid-cols-3 gap-3">
        <div class="bg-white rounded-2xl p-4 shadow-sm border border-slate-100 text-center">
            <p class="text-[10px] font-black text-slate-400 uppercase mb-1">ราคาเต็ม</p>
            <p class="text-lg font-black text-slate-800"><?= number_format($reg['price'], 0) ?></p>
            <p class="text-[9px] text-slate-400">บาท</p>
        </div>
        <div class="bg-white rounded-2xl p-4 shadow-sm border border-emerald-100 text-center">
            <p class="text-[10px] font-black text-emerald-400 uppercase mb-1">ชำระแล้ว</p>
            <p class="text-lg font-black text-emerald-600"><?= number_format($paid, 0) ?></p>
            <p class="text-[9px] text-emerald-400">บาท</p>
        </div>
        <div class="bg-white rounded-2xl p-4 shadow-sm border border-<?= $isPaidFull ? 'emerald' : 'rose' ?>-100 text-center">
            <p class="text-[10px] font-black text-<?= $isPaidFull ? 'emerald' : 'rose' ?>-400 uppercase mb-1">คงเหลือ</p>
            <p class="text-lg font-black text-<?= $isPaidFull ? 'emerald' : 'rose' ?>-600"><?= number_format(max(0, $balance), 0) ?></p>
            <p class="text-[9px] text-<?= $isPaidFull ? 'emerald' : 'rose' ?>-400">บาท</p>
        </div>
    </div>

    <!-- Balance progress bar -->
    <?php if ($reg['price'] > 0): ?>
    <div>
        <div class="flex justify-between text-[10px] font-bold text-slate-400 mb-1">
            <span>ความคืบหน้าการชำระ</span>
            <span><?= number_format(min(100, $paid / $reg['price'] * 100), 1) ?>%</span>
        </div>
        <div class="w-full bg-slate-100 rounded-full h-2">
            <div class="bg-gradient-to-r from-orange-400 to-emerald-500 h-2 rounded-full transition-all"
                 style="width:<?= min(100, $paid / $reg['price'] * 100) ?>%"></div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Cancel request status -->
    <?php if ($cancelReq): ?>
    <?php
    $crMap = [
        'pending'  => ['bg-amber-50 border-amber-200 text-amber-700',  'bi-hourglass-split',   'รออนุมัติการยกเลิก'],
        'approved' => ['bg-emerald-50 border-emerald-200 text-emerald-700', 'bi-check-circle-fill', 'อนุมัติยกเลิกแล้ว'],
        'rejected' => ['bg-rose-50 border-rose-200 text-rose-700',     'bi-x-circle-fill',     'ปฏิเสธคำขอยกเลิก'],
    ];
    [$crstyle, $cricon, $crlabel] = $crMap[$cancelReq['status']] ?? $crMap['pending'];
    ?>
    <div class="rounded-2xl border px-4 py-3 flex items-center gap-3 text-sm <?= $crstyle ?>">
        <i class="bi <?= $cricon ?> flex-shrink-0"></i>
        <div>
            <p class="font-black"><?= $crlabel ?></p>
            <?php if ($cancelReq['process_note']): ?>
            <p class="text-xs mt-0.5 opacity-80"><?= htmlspecialchars($cancelReq['process_note']) ?></p>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Action: Cancel button -->
    <?php if ($reg['status'] === 'active' && (!$cancelReq || $cancelReq['status'] === 'rejected')): ?>
    <button onclick="showCancelModal()" class="w-full py-3 bg-rose-50 text-rose-600 border border-rose-100 rounded-2xl font-bold text-sm active:bg-rose-100 transition-colors flex items-center justify-center gap-2">
        <i class="bi bi-x-circle"></i> ขอยกเลิกการใช้บริการ
    </button>
    <?php endif; ?>
    <?php endif; /* end $reg */ ?>

    <!-- Payment History -->
    <?php if (!empty($history)): ?>
    <div class="bg-white rounded-3xl shadow-sm border border-slate-100 overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-50 flex items-center gap-2">
            <i class="bi bi-receipt text-orange-400"></i>
            <p class="font-black text-slate-700 text-sm">ประวัติการชำระเงิน</p>
        </div>
        <div class="divide-y divide-slate-50">
            <?php foreach ($history as $h): ?>
            <div class="flex items-center justify-between px-5 py-3.5">
                <div>
                    <p class="font-bold text-sm text-slate-700"><?= number_format($h['amount'], 0) ?> บาท</p>
                    <p class="text-[10px] text-slate-400"><?= thDate($h['payment_date'], $thaiMonths) ?> · <?= htmlspecialchars($h['received_by']) ?></p>
                    <?php if ($h['notes']): ?>
                    <p class="text-[10px] text-slate-300 italic"><?= htmlspecialchars($h['notes']) ?></p>
                    <?php endif; ?>
                </div>
                <div class="w-9 h-9 bg-emerald-50 rounded-xl flex items-center justify-center">
                    <i class="bi bi-check-circle-fill text-emerald-500"></i>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Back to portal -->
    <div class="text-center pb-4">
        <a href="/index.php" class="text-slate-400 text-xs hover:text-orange-500 transition-colors flex items-center justify-center gap-1">
            <i class="bi bi-arrow-left"></i> กลับหน้าหลัก LLW
        </a>
    </div>

</div><!-- /content -->

<!-- Cancel Modal -->
<div id="cancelModal" class="hidden fixed inset-0 z-50 flex items-end sm:items-center justify-center bg-black/50 backdrop-blur-sm p-4">
    <div class="bg-white rounded-[28px] shadow-2xl w-full max-w-sm overflow-hidden">
        <div class="bg-gradient-to-r from-rose-500 to-pink-500 px-6 py-4 text-white">
            <h3 class="font-black">ขอยกเลิกการใช้บริการ</h3>
            <p class="text-rose-100 text-xs mt-0.5">กรุณากรอกข้อมูลยืนยันตัวตน</p>
        </div>
        <form method="POST" action="/bus/cancel.php" class="p-6 space-y-4">
            <?= csrf_field() ?>
            <div>
                <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1.5 block">เหตุผลการยกเลิก</label>
                <textarea name="reason" rows="2" required placeholder="กรุณาระบุเหตุผล..."
                          class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-4 py-3 text-sm resize-none focus:ring-2 focus:ring-rose-400 outline-none transition-all"></textarea>
            </div>
            <div>
                <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1.5 block">ยืนยันตัวตน — เลขบัตรประชาชน 13 หลัก</label>
                <input type="password" name="national_id" required inputmode="numeric" maxlength="13"
                       placeholder="• • • • • • • • • • • • •"
                       class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-4 py-3 text-sm font-bold focus:ring-2 focus:ring-rose-400 outline-none transition-all">
            </div>
            <div class="flex gap-3">
                <button type="button" onclick="hideCancelModal()"
                    class="flex-1 py-3 bg-slate-100 text-slate-500 rounded-2xl font-bold text-sm hover:bg-slate-200 transition-all">ยกเลิก</button>
                <button type="submit"
                    class="flex-[2] py-3 bg-gradient-to-r from-rose-500 to-pink-500 text-white rounded-2xl font-black text-sm shadow-lg hover:opacity-90 transition-all">
                    <i class="bi bi-send-fill mr-1"></i> ส่งคำขอ
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function showCancelModal() { document.getElementById('cancelModal').classList.remove('hidden'); }
function hideCancelModal() { document.getElementById('cancelModal').classList.add('hidden'); }
document.getElementById('cancelModal').addEventListener('click', function(e) {
    if (e.target === this) hideCancelModal();
});
</script>
</body>
</html>
