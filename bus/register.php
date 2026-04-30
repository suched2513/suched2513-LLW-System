<?php
session_start();
require_once __DIR__ . '/config.php';
busRequireStudent();

$pdo      = getPdo();
$busId    = (int)$_SESSION['bus_student_id'];
$semester = busGetSemester();
$name     = $_SESSION['bus_student_name'];
$class    = $_SESSION['bus_student_class'];

// Already registered?
$check = $pdo->prepare("SELECT id, status FROM bus_registrations WHERE student_id = ? AND semester = ?");
$check->execute([$busId, $semester]);
$existing = $check->fetch(PDO::FETCH_ASSOC);
if ($existing && $existing['status'] !== 'cancelled') {
    header('Location: /bus/dashboard.php?msg=' . urlencode('คุณได้ลงทะเบียนในภาคเรียนนี้แล้ว') . '&t=ok');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $routeId = (int)($_POST['route_id'] ?? 0);
    if ($routeId <= 0) {
        header('Location: /bus/register.php?err=' . urlencode('กรุณาเลือกสายรถ'));
        exit();
    }
    try {
        $rStmt = $pdo->prepare("SELECT id, seats FROM bus_routes WHERE id = ? AND is_active = 1");
        $rStmt->execute([$routeId]);
        $route = $rStmt->fetch(PDO::FETCH_ASSOC);
        if (!$route) {
            header('Location: /bus/register.php?err=' . urlencode('ไม่พบสายรถที่เลือก'));
            exit();
        }
        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM bus_registrations WHERE route_id=? AND semester=? AND status='active'");
        $countStmt->execute([$routeId, $semester]);
        $taken = (int)$countStmt->fetchColumn();
        if ($route['seats'] > 0 && $taken >= $route['seats']) {
            header('Location: /bus/register.php?err=' . urlencode('สายนี้เต็มแล้ว กรุณาเลือกสายอื่น'));
            exit();
        }
        if ($existing && $existing['status'] === 'cancelled') {
            $stmt = $pdo->prepare("UPDATE bus_registrations SET route_id=?,status='active',registered_at=NOW(),cancelled_at=NULL,notes=NULL WHERE id=?");
            $stmt->execute([$routeId, $existing['id']]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO bus_registrations (student_id,route_id,semester) VALUES (?,?,?)");
            $stmt->execute([$busId, $routeId, $semester]);
        }
        header('Location: /bus/dashboard.php?msg=' . urlencode('ลงทะเบียนใช้บริการรถรับส่งสำเร็จแล้ว!') . '&t=ok');
        exit();
    } catch (Exception $e) {
        error_log($e->getMessage());
        header('Location: /bus/register.php?err=' . urlencode('เกิดข้อผิดพลาด กรุณาลองใหม่'));
        exit();
    }
}

try {
    $stmt = $pdo->prepare("
        SELECT r.*,
               COUNT(CASE WHEN reg.status='active' AND reg.semester=? THEN 1 END) as taken
        FROM bus_routes r
        LEFT JOIN bus_registrations reg ON reg.route_id = r.id
        WHERE r.is_active = 1
        GROUP BY r.id
        ORDER BY r.route_code
    ");
    $stmt->execute([$semester]);
    $routes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log($e->getMessage());
    $routes = [];
}

$err      = htmlspecialchars($_GET['err'] ?? '', ENT_QUOTES, 'UTF-8');
$semLabel = busSemesterLabel($semester);
?><!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<title>เลือกสายรถ | รถรับส่ง LLW</title>
<meta name="theme-color" content="#f97316">
<meta name="apple-mobile-web-app-capable" content="yes">
<link href="https://fonts.googleapis.com/css2?family=Prompt:wght@400;600;700;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<script src="https://cdn.tailwindcss.com"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>body{font-family:'Prompt',sans-serif;}</style>
</head>
<body class="bg-slate-50 min-h-screen" style="padding-bottom:calc(64px + env(safe-area-inset-bottom))">

<!-- Header -->
<header class="bg-gradient-to-r from-orange-500 to-amber-500 text-white sticky top-0 z-50 shadow-md"
        style="padding-top:env(safe-area-inset-top)">
    <div class="max-w-lg mx-auto flex items-center justify-between px-4 py-3">
        <div class="flex items-center gap-2.5">
            <a href="/bus/dashboard.php"
               class="w-9 h-9 bg-white/20 rounded-xl flex items-center justify-center border border-white/20 active:bg-white/30">
                <i class="bi bi-arrow-left"></i>
            </a>
            <div>
                <div class="font-black text-sm leading-tight">เลือกสายรถ</div>
                <div class="text-orange-100 text-[9px] font-bold"><?= htmlspecialchars($semLabel) ?></div>
            </div>
        </div>
        <div class="text-right">
            <div class="font-bold text-xs leading-tight truncate max-w-[130px]"><?= htmlspecialchars($name) ?></div>
            <div class="text-orange-100 text-[10px]"><?= htmlspecialchars($class) ?></div>
        </div>
    </div>
</header>

<div class="max-w-lg mx-auto px-4 pt-4 space-y-3">

    <?php if ($err): ?>
    <div class="bg-rose-50 border border-rose-100 text-rose-700 rounded-2xl px-4 py-3 text-sm font-bold flex items-center gap-2">
        <i class="bi bi-exclamation-triangle-fill flex-shrink-0"></i> <?= $err ?>
    </div>
    <?php endif; ?>

    <p class="text-[11px] font-black text-slate-400 uppercase tracking-widest px-1">
        สายที่เปิดให้บริการ <?= count($routes) ?> สาย
    </p>

    <?php if (empty($routes)): ?>
    <div class="bg-white rounded-3xl p-10 shadow-sm border border-slate-100 text-center">
        <i class="bi bi-bus-front text-5xl text-slate-200 block mb-3"></i>
        <p class="text-slate-400 font-bold">ยังไม่มีสายรถที่เปิดให้บริการ</p>
        <p class="text-slate-300 text-xs mt-1">กรุณาติดต่อเจ้าหน้าที่</p>
    </div>
    <?php endif; ?>

    <?php foreach ($routes as $rt):
        $taken     = (int)$rt['taken'];
        $seats     = (int)$rt['seats'];
        $available = $seats > 0 ? $seats - $taken : 999;
        $isFull    = $seats > 0 && $available <= 0;
        $seatPct   = $seats > 0 ? min(100, round($taken / $seats * 100)) : 0;
        $seatColor = $seatPct >= 100 ? 'rose' : ($seatPct >= 75 ? 'amber' : 'emerald');
    ?>
    <div class="bg-white rounded-3xl shadow-sm border border-slate-100 overflow-hidden <?= $isFull ? 'opacity-60' : '' ?>">

        <!-- Route Header -->
        <div class="flex items-center gap-3 px-5 pt-4 pb-3">
            <div class="w-12 h-12 bg-orange-100 rounded-2xl flex items-center justify-center flex-shrink-0">
                <i class="bi bi-bus-front-fill text-orange-500 text-2xl"></i>
            </div>
            <div class="flex-1 min-w-0">
                <p class="font-black text-slate-800 leading-tight"><?= htmlspecialchars($rt['route_name']) ?></p>
                <p class="text-[10px] text-slate-400 font-bold mt-0.5">สาย <?= htmlspecialchars($rt['route_code']) ?></p>
            </div>
            <div class="text-right flex-shrink-0">
                <p class="text-2xl font-black text-orange-600 leading-none"><?= number_format($rt['price'], 0) ?></p>
                <p class="text-[9px] text-slate-400 font-bold">บาท/ภาคเรียน</p>
            </div>
        </div>

        <?php if ($rt['description']): ?>
        <div class="mx-5 mb-3 bg-slate-50 rounded-2xl px-3 py-2">
            <p class="text-[11px] text-slate-500"><?= htmlspecialchars($rt['description']) ?></p>
        </div>
        <?php endif; ?>

        <!-- Seat availability -->
        <?php if ($seats > 0): ?>
        <div class="px-5 mb-3">
            <div class="flex justify-between text-[10px] font-bold mb-1">
                <span class="text-slate-400 flex items-center gap-1">
                    <i class="bi bi-people-fill text-slate-300"></i>
                    <?= $isFull ? 'เต็มแล้ว' : "ว่าง {$available}/{$seats} ที่นั่ง" ?>
                </span>
                <span class="text-<?= $seatColor ?>-500"><?= $seatPct ?>%</span>
            </div>
            <div class="w-full bg-slate-100 rounded-full h-1.5">
                <div class="h-1.5 rounded-full bg-<?= $seatColor ?>-400 transition-all"
                     style="width:<?= $seatPct ?>%"></div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Driver & Action -->
        <div class="flex items-center justify-between px-5 pb-4 gap-3">
            <div class="text-[11px] text-slate-400 min-w-0">
                <?php if ($rt['driver_name']): ?>
                <span class="flex items-center gap-1 truncate">
                    <i class="bi bi-person-fill"></i>
                    <span class="truncate"><?= htmlspecialchars($rt['driver_name']) ?></span>
                </span>
                <?php endif; ?>
            </div>
            <?php if (!$isFull): ?>
            <form method="POST" class="flex-shrink-0">
                <?= csrf_field() ?>
                <input type="hidden" name="route_id" value="<?= (int)$rt['id'] ?>">
                <button type="button"
                    onclick="confirmReg(<?= (int)$rt['id'] ?>, '<?= htmlspecialchars(addslashes($rt['route_name'])) ?>', <?= (float)$rt['price'] ?>)"
                    class="px-5 py-2.5 bg-gradient-to-r from-orange-500 to-amber-500 text-white rounded-2xl
                           font-black text-xs shadow-md shadow-orange-200/50 active:scale-95 transition-transform">
                    เลือกสายนี้
                </button>
            </form>
            <?php else: ?>
            <span class="px-4 py-2 bg-slate-100 text-slate-400 rounded-2xl font-black text-xs flex-shrink-0">
                <i class="bi bi-x-circle me-1"></i>เต็มแล้ว
            </span>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>

    <div class="pb-2 text-center">
        <p class="text-[10px] text-slate-300">หากไม่มีสายที่ต้องการ กรุณาติดต่อเจ้าหน้าที่</p>
    </div>

</div>

<!-- Bottom Navigation -->
<nav class="fixed bottom-0 left-0 right-0 bg-white border-t border-slate-100 shadow-[0_-4px_20px_rgba(0,0,0,0.06)] z-40"
     style="padding-bottom:env(safe-area-inset-bottom)">
    <div class="max-w-lg mx-auto grid grid-cols-3 h-16">
        <a href="/bus/dashboard.php" class="flex flex-col items-center justify-center gap-1 text-slate-400 active:text-orange-500 transition-colors">
            <i class="bi bi-house-door text-xl"></i>
            <span class="text-[10px] font-bold">หน้าหลัก</span>
        </a>
        <a href="/bus/register.php" class="flex flex-col items-center justify-center gap-1 text-orange-500">
            <i class="bi bi-bus-front-fill text-xl"></i>
            <span class="text-[10px] font-black">เลือกสาย</span>
        </a>
        <a href="/bus/logout.php" class="flex flex-col items-center justify-center gap-1 text-slate-400 active:text-rose-500 transition-colors">
            <i class="bi bi-box-arrow-right text-xl"></i>
            <span class="text-[10px] font-bold">ออกจากระบบ</span>
        </a>
    </div>
</nav>

<script>
function confirmReg(routeId, name, price) {
    Swal.fire({
        title: 'ยืนยันการลงทะเบียน?',
        html: `<div style="font-family:'Prompt',sans-serif">
            สาย <b>${name}</b><br>
            ค่าใช้จ่าย <b style="color:#f97316">${price.toLocaleString()} บาท/ภาคเรียน</b>
        </div>`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#f97316',
        confirmButtonText: 'ยืนยันลงทะเบียน',
        cancelButtonText: 'ยกเลิก',
        customClass: { popup: 'rounded-3xl' }
    }).then(r => {
        if (r.isConfirmed) {
            const f = document.createElement('form');
            f.method = 'POST';
            f.innerHTML = `
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="route_id" value="${routeId}">
            `;
            document.body.appendChild(f);
            f.submit();
        }
    });
}
</script>
</body>
</html>
