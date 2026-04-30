<?php
session_start();
require_once __DIR__ . '/config.php';
busRequireStudent();

$pdo      = getPdo();
$busId    = (int)$_SESSION['bus_student_id'];
$semester = busGetSemester();
$name     = $_SESSION['bus_student_name'];
$class    = $_SESSION['bus_student_class'];

// Check: already registered this semester?
$check = $pdo->prepare("SELECT id, status FROM bus_registrations WHERE student_id = ? AND semester = ?");
$check->execute([$busId, $semester]);
$existing = $check->fetch(PDO::FETCH_ASSOC);
if ($existing && $existing['status'] !== 'cancelled') {
    header('Location: /bus/dashboard.php?msg=' . urlencode('คุณได้ลงทะเบียนในภาคเรียนนี้แล้ว') . '&t=ok');
    exit();
}

// POST: register
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $routeId = (int)($_POST['route_id'] ?? 0);
    if ($routeId <= 0) {
        header('Location: /bus/register.php?err=' . urlencode('กรุณาเลือกสายรถ'));
        exit();
    }
    try {
        // Verify route exists and is active
        $rStmt = $pdo->prepare("SELECT id, seats FROM bus_routes WHERE id = ? AND is_active = 1");
        $rStmt->execute([$routeId]);
        $route = $rStmt->fetch(PDO::FETCH_ASSOC);
        if (!$route) {
            header('Location: /bus/register.php?err=' . urlencode('ไม่พบสายรถที่เลือก'));
            exit();
        }
        // Check seat availability
        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM bus_registrations WHERE route_id = ? AND semester = ? AND status = 'active'");
        $countStmt->execute([$routeId, $semester]);
        $taken = (int)$countStmt->fetchColumn();
        if ($route['seats'] > 0 && $taken >= $route['seats']) {
            header('Location: /bus/register.php?err=' . urlencode('สายนี้เต็มแล้ว กรุณาเลือกสายอื่น'));
            exit();
        }

        if ($existing && $existing['status'] === 'cancelled') {
            // Re-register: update existing
            $stmt = $pdo->prepare("UPDATE bus_registrations SET route_id=?, status='active', registered_at=NOW(), cancelled_at=NULL, notes=NULL WHERE id=?");
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

// Fetch routes with seat count
try {
    $routes = $pdo->query("
        SELECT r.*,
               COUNT(CASE WHEN reg.status='active' AND reg.semester='" . addslashes($semester) . "' THEN 1 END) as taken
        FROM bus_routes r
        LEFT JOIN bus_registrations reg ON reg.route_id = r.id
        WHERE r.is_active = 1
        GROUP BY r.id
        ORDER BY r.route_code
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log($e->getMessage());
    $routes = [];
}

$err = htmlspecialchars($_GET['err'] ?? '', ENT_QUOTES, 'UTF-8');
$semLabel = busSemesterLabel($semester);
?><!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<title>ลงทะเบียน | รถรับส่ง LLW</title>
<meta name="theme-color" content="#f97316">
<link href="https://fonts.googleapis.com/css2?family=Prompt:wght@400;600;700;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<script src="https://cdn.tailwindcss.com"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>body{font-family:'Prompt',sans-serif;}</style>
</head>
<body class="bg-slate-100 min-h-screen pb-8" style="padding-bottom:max(env(safe-area-inset-bottom),32px)">

<header class="bg-gradient-to-r from-orange-500 to-amber-500 text-white sticky top-0 z-50 shadow-lg" style="padding-top:env(safe-area-inset-top)">
    <div class="max-w-lg mx-auto flex items-center justify-between px-4 py-3.5">
        <div class="flex items-center gap-2.5">
            <a href="/bus/dashboard.php" class="w-9 h-9 bg-white/20 rounded-xl flex items-center justify-center border border-white/20 active:bg-white/30">
                <i class="bi bi-arrow-left"></i>
            </a>
            <div>
                <div class="font-black text-sm">เลือกสายรถ</div>
                <div class="text-orange-100 text-[9px] font-bold"><?= htmlspecialchars($semLabel) ?></div>
            </div>
        </div>
        <div class="text-right">
            <div class="font-bold text-xs leading-tight truncate max-w-[120px]"><?= htmlspecialchars($name) ?></div>
            <div class="text-orange-100 text-[10px]"><?= htmlspecialchars($class) ?></div>
        </div>
    </div>
</header>

<div class="max-w-lg mx-auto px-4 pt-5 space-y-4">

    <?php if ($err): ?>
    <div class="bg-rose-50 text-rose-700 rounded-2xl px-4 py-3 text-sm font-bold flex items-center gap-2 border border-rose-100">
        <i class="bi bi-exclamation-triangle-fill"></i> <?= $err ?>
    </div>
    <?php endif; ?>

    <div>
        <p class="text-xs font-black text-slate-500 uppercase tracking-widest mb-3">สายที่เปิดให้บริการ <?= count($routes) ?> สาย</p>

        <?php if (empty($routes)): ?>
        <div class="bg-white rounded-3xl p-8 shadow-sm border border-slate-100 text-center">
            <i class="bi bi-bus-front text-4xl text-slate-300 block mb-2"></i>
            <p class="text-slate-400 font-bold text-sm">ยังไม่มีสายรถที่เปิดให้บริการ</p>
            <p class="text-slate-300 text-xs mt-1">กรุณาติดต่อเจ้าหน้าที่</p>
        </div>
        <?php endif; ?>

        <?php foreach ($routes as $rt):
            $available = $rt['seats'] > 0 ? ($rt['seats'] - (int)$rt['taken']) : 999;
            $isFull = $rt['seats'] > 0 && $available <= 0;
        ?>
        <div class="bg-white rounded-3xl shadow-sm border border-slate-100 overflow-hidden mb-3 <?= $isFull ? 'opacity-60' : '' ?>">
            <div class="p-5">
                <div class="flex items-start justify-between gap-3">
                    <div class="flex items-center gap-3 flex-1 min-w-0">
                        <div class="w-12 h-12 bg-orange-100 rounded-2xl flex items-center justify-center flex-shrink-0">
                            <i class="bi bi-bus-front-fill text-orange-500 text-xl"></i>
                        </div>
                        <div class="min-w-0">
                            <p class="font-black text-slate-800"><?= htmlspecialchars($rt['route_name']) ?></p>
                            <p class="text-[10px] text-slate-400 font-bold">สาย <?= htmlspecialchars($rt['route_code']) ?></p>
                        </div>
                    </div>
                    <div class="text-right flex-shrink-0">
                        <p class="text-xl font-black text-orange-600"><?= number_format($rt['price'], 0) ?></p>
                        <p class="text-[9px] text-slate-400">บาท/ภาคเรียน</p>
                    </div>
                </div>
                <?php if ($rt['description']): ?>
                <p class="text-xs text-slate-500 mt-3 bg-slate-50 rounded-xl px-3 py-2"><?= htmlspecialchars($rt['description']) ?></p>
                <?php endif; ?>
                <div class="flex items-center justify-between mt-3">
                    <div class="flex items-center gap-3 text-[11px] text-slate-400">
                        <?php if ($rt['driver_name']): ?>
                        <span class="flex items-center gap-1"><i class="bi bi-person-fill"></i><?= htmlspecialchars($rt['driver_name']) ?></span>
                        <?php endif; ?>
                        <?php if ($rt['seats'] > 0): ?>
                        <span class="flex items-center gap-1"><i class="bi bi-people-fill"></i><?= $isFull ? 'เต็มแล้ว' : "ว่าง {$available}/{$rt['seats']}" ?></span>
                        <?php endif; ?>
                    </div>
                    <?php if (!$isFull): ?>
                    <form method="POST">
                        <?= csrf_field() ?>
                        <input type="hidden" name="route_id" value="<?= $rt['id'] ?>">
                        <button type="button"
                            onclick="confirmReg(<?= $rt['id'] ?>, '<?= htmlspecialchars(addslashes($rt['route_name'])) ?>', <?= number_format($rt['price'], 0) ?>)"
                            class="px-5 py-2.5 bg-gradient-to-r from-orange-500 to-amber-500 text-white rounded-2xl font-black text-xs shadow-lg shadow-orange-200/50 active:scale-95 transition-transform">
                            เลือกสายนี้
                        </button>
                    </form>
                    <?php else: ?>
                    <span class="px-4 py-2 bg-slate-100 text-slate-400 rounded-2xl font-black text-xs">เต็มแล้ว</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

</div>

<script>
function confirmReg(routeId, name, price) {
    Swal.fire({
        title: 'ยืนยันการลงทะเบียน?',
        html: `สาย <b>${name}</b><br>ค่าใช้จ่าย <b>${price.toLocaleString()} บาท/ภาคเรียน</b>`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#f97316',
        confirmButtonText: 'ยืนยัน',
        cancelButtonText: 'ยกเลิก',
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
