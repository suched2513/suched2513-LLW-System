<?php
session_start();
require_once __DIR__ . '/config.php';
busRequireStudent();

$pdo        = getPdo();
$busId      = (int)$_SESSION['bus_student_id'];
$studentSid = $_SESSION['bus_student_sid'];
$name       = $_SESSION['bus_student_name'];
$class      = $_SESSION['bus_student_class'];
$semester   = busGetSemester();
$semLabel   = busSemesterLabel($semester);

$flash     = $_GET['msg'] ?? '';
$flashType = $_GET['t'] ?? 'ok';

$reg = null; $paid = 0.0; $cancelReq = null;
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
        $cStmt = $pdo->prepare("SELECT * FROM bus_cancel_requests WHERE registration_id = ? ORDER BY created_at DESC LIMIT 1");
        $cStmt->execute([$reg['id']]);
        $cancelReq = $cStmt->fetch(PDO::FETCH_ASSOC);
    }

    $histStmt = $pdo->prepare("
        SELECT p.amount, p.paid_at, p.note, r.semester, rt.route_name
        FROM bus_payments p
        JOIN bus_registrations r ON r.id = p.registration_id
        JOIN bus_routes rt ON rt.id = r.route_id
        WHERE r.student_id = ?
        ORDER BY p.paid_at DESC
        LIMIT 20
    ");
    $histStmt->execute([$busId]);
    $history = $histStmt->fetchAll(PDO::FETCH_ASSOC);

    $hasRoutes = (bool)$pdo->query("SELECT 1 FROM bus_routes WHERE is_active=1 LIMIT 1")->fetchColumn();
} catch (Exception $e) {
    error_log($e->getMessage());
    $history = []; $hasRoutes = false;
}

$balance    = $reg ? max(0, (float)$reg['price'] - $paid) : 0;
$isPaidFull = $reg && $balance <= 0.01;
$pctPaid    = ($reg && $reg['price'] > 0) ? min(100, round($paid / $reg['price'] * 100)) : 0;

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
<meta name="apple-mobile-web-app-capable" content="yes">
<link href="https://fonts.googleapis.com/css2?family=Prompt:wght@400;600;700;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<script src="https://cdn.tailwindcss.com"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
body { font-family:'Prompt',sans-serif; }
.bottom-nav-h { height: calc(64px + env(safe-area-inset-bottom)); }
</style>
</head>
<body class="bg-slate-50 min-h-screen" style="padding-bottom:calc(64px + env(safe-area-inset-bottom))">

<!-- Header -->
<header class="bg-gradient-to-r from-orange-500 to-amber-500 text-white sticky top-0 z-50 shadow-md" style="padding-top:env(safe-area-inset-top)">
    <div class="max-w-lg mx-auto flex items-center justify-between px-4 py-3">
        <div class="flex items-center gap-2.5">
            <div class="w-9 h-9 bg-white/20 rounded-xl flex items-center justify-center border border-white/20">
                <i class="bi bi-bus-front-fill text-lg"></i>
            </div>
            <div>
                <div class="font-black text-sm leading-tight">รถรับส่งนักเรียน</div>
                <div class="text-orange-100 text-[9px] font-bold">โรงเรียนละลมวิทยา</div>
            </div>
        </div>
        <div class="flex items-center gap-2">
            <div class="text-right">
                <div class="font-bold text-xs leading-tight max-w-[130px] truncate"><?= htmlspecialchars($name) ?></div>
                <div class="text-orange-100 text-[10px]"><?= htmlspecialchars($class) ?></div>
            </div>
            <a href="/bus/logout.php" title="ออกจากระบบ"
               class="w-8 h-8 bg-white/15 rounded-xl flex items-center justify-center border border-white/20 active:bg-white/30">
                <i class="bi bi-box-arrow-right text-sm"></i>
            </a>
        </div>
    </div>
</header>

<div class="max-w-lg mx-auto px-4 pt-4 space-y-4 pb-4">

    <!-- Flash -->
    <?php if ($flash !== ''): ?>
    <div class="rounded-2xl px-4 py-3 text-sm font-bold flex items-center gap-2
        <?= $flashType === 'ok' ? 'bg-emerald-50 text-emerald-700 border border-emerald-100' : 'bg-rose-50 text-rose-700 border border-rose-100' ?>">
        <i class="bi bi-<?= $flashType === 'ok' ? 'check-circle-fill' : 'exclamation-triangle-fill' ?> flex-shrink-0"></i>
        <?= htmlspecialchars($flash, ENT_QUOTES, 'UTF-8') ?>
    </div>
    <?php endif; ?>

    <!-- Hero Card -->
    <div class="bg-gradient-to-br from-orange-500 via-orange-500 to-amber-400 rounded-3xl p-5 text-white shadow-xl shadow-orange-200/60 relative overflow-hidden">
        <div class="absolute -right-8 -bottom-8 w-32 h-32 bg-white/10 rounded-full pointer-events-none"></div>
        <div class="absolute right-8 -top-4 w-16 h-16 bg-white/5 rounded-full pointer-events-none"></div>
        <div class="relative flex items-start justify-between">
            <div class="flex-1 min-w-0">
                <p class="text-orange-100 text-[10px] font-bold uppercase tracking-wider"><?= htmlspecialchars($semLabel) ?></p>
                <h2 class="text-xl font-black mt-0.5 leading-snug truncate"><?= htmlspecialchars($name) ?></h2>
                <p class="text-orange-100 text-xs mt-1.5 flex items-center gap-1.5">
                    <i class="bi bi-mortarboard-fill"></i>
                    <?= htmlspecialchars($class) ?>
                    <span class="opacity-60">·</span>
                    รหัส <?= htmlspecialchars($studentSid) ?>
                </p>
            </div>
            <div class="w-12 h-12 bg-white/20 rounded-2xl flex items-center justify-center border border-white/30 flex-shrink-0 ml-3">
                <span class="font-black text-xl"><?= mb_substr($name, 0, 1) ?></span>
            </div>
        </div>
        <!-- Semester pill -->
        <div class="mt-4 inline-flex items-center gap-1.5 bg-white/20 border border-white/20 rounded-full px-3 py-1 text-[10px] font-bold">
            <i class="bi bi-calendar3"></i> <?= htmlspecialchars($semLabel) ?>
        </div>
    </div>

    <?php if (!$reg): ?>
    <!-- ══ NOT REGISTERED ══ -->
    <div class="bg-white rounded-3xl overflow-hidden shadow-sm border border-slate-100">
        <div class="px-5 pt-6 pb-4 text-center">
            <div class="w-16 h-16 bg-orange-50 rounded-full flex items-center justify-center mx-auto mb-3">
                <i class="bi bi-bus-front text-orange-400 text-3xl"></i>
            </div>
            <p class="font-black text-slate-700 text-base">ยังไม่ได้ลงทะเบียนใช้บริการ</p>
            <p class="text-xs text-slate-400 mt-1">ลงทะเบียนเพื่อจองที่นั่งรถรับส่งประจำภาคเรียนนี้</p>
        </div>
        <?php if ($hasRoutes): ?>
        <div class="px-5 pb-5">
            <a href="/bus/register.php"
               class="flex items-center justify-center gap-2 w-full py-4 bg-gradient-to-r from-orange-500 to-amber-500
                      text-white rounded-2xl font-black text-sm shadow-lg shadow-orange-200/60 active:scale-95 transition-transform">
                <i class="bi bi-plus-circle-fill text-lg"></i> ลงทะเบียนใช้บริการ
            </a>
        </div>
        <?php else: ?>
        <div class="px-5 pb-5">
            <div class="bg-slate-50 rounded-2xl px-4 py-3 text-center text-sm text-slate-400 font-bold">
                <i class="bi bi-clock me-1"></i> ยังไม่มีสายรถเปิดให้บริการ
            </div>
        </div>
        <?php endif; ?>
    </div>

    <?php else: ?>
    <!-- ══ REGISTERED ══ -->
    <?php
    $statusMap = [
        'active'         => ['emerald', 'bi-check-circle-fill', 'ใช้บริการอยู่'],
        'pending_cancel' => ['amber',   'bi-hourglass-split',   'รออนุมัติยกเลิก'],
        'cancelled'      => ['slate',   'bi-x-circle-fill',     'ยกเลิกแล้ว'],
    ];
    [$sc, $si, $sl] = $statusMap[$reg['status']] ?? $statusMap['cancelled'];
    ?>

    <!-- Route Card -->
    <div class="bg-white rounded-3xl shadow-sm border border-slate-100 overflow-hidden">
        <div class="flex items-center gap-3 px-5 py-4 border-b border-slate-50">
            <div class="w-11 h-11 bg-orange-100 rounded-2xl flex items-center justify-center flex-shrink-0">
                <i class="bi bi-bus-front-fill text-orange-500 text-xl"></i>
            </div>
            <div class="flex-1 min-w-0">
                <p class="font-black text-slate-800 leading-tight"><?= htmlspecialchars($reg['route_name']) ?></p>
                <p class="text-[11px] text-slate-400 font-bold mt-0.5">สาย <?= htmlspecialchars($reg['route_code']) ?></p>
            </div>
            <span class="px-2.5 py-1 rounded-full text-[10px] font-black flex-shrink-0
                bg-<?= $sc ?>-100 text-<?= $sc ?>-700">
                <i class="bi <?= $si ?>"></i> <?= $sl ?>
            </span>
        </div>
        <?php if ($reg['description']): ?>
        <div class="px-5 py-2.5 bg-slate-50 border-b border-slate-100">
            <p class="text-[11px] text-slate-500"><?= htmlspecialchars($reg['description']) ?></p>
        </div>
        <?php endif; ?>
        <?php if ($reg['driver_name']): ?>
        <div class="px-5 py-3 flex items-center justify-between">
            <div class="flex items-center gap-2 text-xs text-slate-500">
                <i class="bi bi-person-fill text-slate-400"></i>
                <span>คนขับ: <strong class="text-slate-700"><?= htmlspecialchars($reg['driver_name']) ?></strong></span>
            </div>
            <?php if ($reg['driver_phone']): ?>
            <a href="tel:<?= htmlspecialchars($reg['driver_phone']) ?>"
               class="flex items-center gap-1.5 px-3 py-1.5 bg-emerald-50 text-emerald-600 rounded-xl text-xs font-bold active:bg-emerald-100">
                <i class="bi bi-telephone-fill"></i> <?= htmlspecialchars($reg['driver_phone']) ?>
            </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        <div class="px-5 pb-3 text-[10px] text-slate-400 font-bold">
            ลงทะเบียนเมื่อ <?= thDate($reg['registered_at'], $thaiMonths) ?>
        </div>
    </div>

    <!-- Payment Card -->
    <?php if ($isPaidFull): ?>
    <!-- Paid full — celebration state -->
    <div class="bg-gradient-to-br from-emerald-500 to-teal-500 rounded-3xl p-5 text-white shadow-lg shadow-emerald-200/50 relative overflow-hidden">
        <div class="absolute -right-6 -bottom-6 w-24 h-24 bg-white/10 rounded-full pointer-events-none"></div>
        <div class="flex items-center gap-4">
            <div class="w-14 h-14 bg-white/20 rounded-2xl flex items-center justify-center flex-shrink-0 border border-white/30">
                <i class="bi bi-patch-check-fill text-3xl"></i>
            </div>
            <div>
                <p class="font-black text-lg">ชำระครบแล้ว!</p>
                <p class="text-emerald-100 text-xs mt-0.5">ยอดชำระ <?= number_format($paid, 0) ?> บาท — ขอบคุณครับ</p>
            </div>
        </div>
    </div>
    <?php else: ?>
    <!-- Partial payment -->
    <div class="bg-white rounded-3xl shadow-sm border border-slate-100 p-5 space-y-4">
        <div class="flex items-center justify-between">
            <p class="font-black text-slate-700 text-sm flex items-center gap-1.5">
                <i class="bi bi-wallet2 text-orange-400"></i> สรุปการชำระเงิน
            </p>
            <span class="text-[10px] font-bold text-slate-400"><?= $pctPaid ?>% ชำระแล้ว</span>
        </div>
        <!-- Progress -->
        <div class="w-full bg-slate-100 rounded-full h-2.5">
            <div class="bg-gradient-to-r from-orange-400 to-emerald-400 h-2.5 rounded-full transition-all duration-500"
                 style="width:<?= $pctPaid ?>%"></div>
        </div>
        <!-- 3 cols -->
        <div class="grid grid-cols-3 gap-2 text-center">
            <div class="bg-slate-50 rounded-2xl p-3">
                <p class="text-[9px] font-black text-slate-400 uppercase tracking-wider mb-0.5">ราคาเต็ม</p>
                <p class="text-base font-black text-slate-700"><?= number_format($reg['price'], 0) ?></p>
                <p class="text-[9px] text-slate-400">บาท</p>
            </div>
            <div class="bg-emerald-50 rounded-2xl p-3">
                <p class="text-[9px] font-black text-emerald-400 uppercase tracking-wider mb-0.5">ชำระแล้ว</p>
                <p class="text-base font-black text-emerald-600"><?= number_format($paid, 0) ?></p>
                <p class="text-[9px] text-emerald-400">บาท</p>
            </div>
            <div class="bg-rose-50 rounded-2xl p-3">
                <p class="text-[9px] font-black text-rose-400 uppercase tracking-wider mb-0.5">คงเหลือ</p>
                <p class="text-base font-black text-rose-600"><?= number_format($balance, 0) ?></p>
                <p class="text-[9px] text-rose-400">บาท</p>
            </div>
        </div>
        <p class="text-[10px] text-slate-400 text-center">ติดต่อเจ้าหน้าที่การเงินเพื่อชำระเงินค่าบริการ</p>
    </div>
    <?php endif; ?>

    <!-- Cancel request status -->
    <?php if ($cancelReq): ?>
    <?php
    $crMap = [
        'pending'  => ['amber',   'bi-hourglass-split',    'รออนุมัติการยกเลิก'],
        'approved' => ['emerald', 'bi-check-circle-fill',  'อนุมัติยกเลิกแล้ว'],
        'rejected' => ['rose',    'bi-x-circle-fill',      'ปฏิเสธคำขอยกเลิก'],
    ];
    [$cc, $ci, $clabel] = $crMap[$cancelReq['status']] ?? $crMap['pending'];
    ?>
    <div class="rounded-2xl border border-<?= $cc ?>-200 bg-<?= $cc ?>-50 px-4 py-3 flex items-start gap-3">
        <i class="bi <?= $ci ?> text-<?= $cc ?>-500 mt-0.5 flex-shrink-0"></i>
        <div>
            <p class="font-black text-<?= $cc ?>-700 text-sm"><?= $clabel ?></p>
            <?php if ($cancelReq['admin_note']): ?>
            <p class="text-xs text-<?= $cc ?>-600 mt-0.5"><?= htmlspecialchars($cancelReq['admin_note']) ?></p>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Cancel button -->
    <?php if ($reg['status'] === 'active' && (!$cancelReq || $cancelReq['status'] === 'rejected')): ?>
    <button onclick="showCancelModal()"
        class="w-full py-3 border border-rose-200 bg-rose-50 text-rose-600 rounded-2xl font-bold text-sm
               flex items-center justify-center gap-2 active:bg-rose-100 transition-colors">
        <i class="bi bi-x-circle"></i> ขอยกเลิกการใช้บริการ
    </button>
    <?php endif; ?>
    <?php endif; /* end registered */ ?>

    <!-- Payment History -->
    <?php if (!empty($history)): ?>
    <div class="bg-white rounded-3xl shadow-sm border border-slate-100 overflow-hidden">
        <div class="flex items-center justify-between px-5 py-4 border-b border-slate-50">
            <div class="flex items-center gap-2">
                <i class="bi bi-receipt text-orange-400"></i>
                <p class="font-black text-slate-700 text-sm">ประวัติการชำระเงิน</p>
            </div>
            <span class="text-[10px] font-bold text-slate-400 bg-slate-100 px-2 py-0.5 rounded-full"><?= count($history) ?> รายการ</span>
        </div>
        <div class="divide-y divide-slate-50">
            <?php foreach ($history as $h): ?>
            <div class="flex items-center gap-3 px-5 py-3.5">
                <div class="w-9 h-9 bg-emerald-50 rounded-xl flex items-center justify-center flex-shrink-0">
                    <i class="bi bi-check2 text-emerald-500 font-bold"></i>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="font-bold text-sm text-slate-700"><?= number_format($h['amount'], 0) ?> บาท</p>
                    <p class="text-[10px] text-slate-400 truncate">
                        <?= thDate($h['paid_at'], $thaiMonths) ?>
                        <?php if ($h['note']): ?> · <?= htmlspecialchars($h['note']) ?><?php endif; ?>
                    </p>
                    <p class="text-[10px] text-orange-400 font-bold truncate"><?= htmlspecialchars($h['route_name']) ?> · <?= htmlspecialchars(busSemesterLabel($h['semester'])) ?></p>
                </div>
                <span class="text-[10px] font-black text-emerald-500 bg-emerald-50 px-2 py-1 rounded-xl flex-shrink-0">ชำระแล้ว</span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <div class="text-center pb-2">
        <a href="/index.php" class="text-slate-400 text-xs flex items-center justify-center gap-1 hover:text-orange-500 transition-colors">
            <i class="bi bi-arrow-left"></i> กลับหน้าหลัก LLW
        </a>
    </div>

</div>

<!-- Bottom Navigation -->
<nav class="fixed bottom-0 left-0 right-0 bg-white border-t border-slate-100 shadow-[0_-4px_20px_rgba(0,0,0,0.06)] z-40"
     style="padding-bottom:env(safe-area-inset-bottom)">
    <div class="max-w-lg mx-auto grid grid-cols-3 h-16">
        <a href="/bus/dashboard.php" class="flex flex-col items-center justify-center gap-1 text-orange-500">
            <i class="bi bi-house-door-fill text-xl"></i>
            <span class="text-[10px] font-black">หน้าหลัก</span>
        </a>
        <a href="/bus/register.php" class="flex flex-col items-center justify-center gap-1 text-slate-400 active:text-orange-500 transition-colors">
            <i class="bi bi-bus-front text-xl"></i>
            <span class="text-[10px] font-bold">เลือกสาย</span>
        </a>
        <a href="/bus/logout.php" class="flex flex-col items-center justify-center gap-1 text-slate-400 active:text-rose-500 transition-colors">
            <i class="bi bi-box-arrow-right text-xl"></i>
            <span class="text-[10px] font-bold">ออกจากระบบ</span>
        </a>
    </div>
</nav>

<!-- Cancel Modal -->
<div id="cancelModal" class="hidden fixed inset-0 z-50 flex items-end justify-center bg-black/50 backdrop-blur-sm">
    <div class="bg-white rounded-t-[32px] shadow-2xl w-full max-w-lg overflow-hidden"
         style="padding-bottom:max(env(safe-area-inset-bottom),16px)">
        <div class="w-10 h-1 bg-slate-200 rounded-full mx-auto mt-3 mb-1"></div>
        <div class="bg-gradient-to-r from-rose-500 to-pink-500 px-6 py-4 text-white">
            <h3 class="font-black text-base">ขอยกเลิกการใช้บริการ</h3>
            <p class="text-rose-100 text-xs mt-0.5">กรุณายืนยันตัวตนด้วยเลขบัตรประชาชน</p>
        </div>
        <form method="POST" action="/bus/cancel.php" class="p-5 space-y-4">
            <?= csrf_field() ?>
            <div>
                <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1.5 block">เหตุผลการยกเลิก <span class="text-rose-500">*</span></label>
                <textarea name="reason" rows="3" required placeholder="กรุณาระบุเหตุผล..."
                          class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-4 py-3 text-sm resize-none focus:ring-2 focus:ring-rose-400 outline-none transition-all"></textarea>
            </div>
            <div>
                <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1.5 block">เลขบัตรประชาชน 13 หลัก <span class="text-rose-500">*</span></label>
                <input type="password" name="national_id" required inputmode="numeric" maxlength="13"
                       placeholder="• • • • • • • • • • • • •"
                       class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-4 py-3.5 text-sm font-bold tracking-widest focus:ring-2 focus:ring-rose-400 outline-none transition-all">
            </div>
            <div class="flex gap-3 pt-1">
                <button type="button" onclick="hideCancelModal()"
                    class="flex-1 py-3.5 bg-slate-100 text-slate-500 rounded-2xl font-bold text-sm active:bg-slate-200">ยกเลิก</button>
                <button type="submit"
                    class="flex-[2] py-3.5 bg-gradient-to-r from-rose-500 to-pink-500 text-white rounded-2xl font-black text-sm shadow-lg shadow-rose-200/50 active:opacity-90">
                    <i class="bi bi-send-fill me-1"></i> ส่งคำขอ
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function showCancelModal() {
    document.getElementById('cancelModal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}
function hideCancelModal() {
    document.getElementById('cancelModal').classList.add('hidden');
    document.body.style.overflow = '';
}
document.getElementById('cancelModal').addEventListener('click', function(e) {
    if (e.target === this) hideCancelModal();
});
</script>
</body>
</html>
