<?php
session_start();
require_once __DIR__ . '/../config.php';

if (!isset($_SESSION['is_student']) || $_SESSION['is_student'] !== true) {
    header('Location: /student/login.php?redirect=' . urlencode('/student_club/index.php'));
    exit();
}

$student_code  = $_SESSION['student_code'] ?? '';
$student_name  = $_SESSION['student_name'] ?? '';
$student_class = $_SESSION['student_class'] ?? '';

$pdo = getPdo();
$cfg = $pdo->query("SELECT * FROM club_settings WHERE is_active = 1 LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$semester = (int)($cfg['semester'] ?? 0);
$year     = (int)($cfg['year'] ?? 0);

// Current registration
$myReg = null;
if ($semester) {
    $stmt = $pdo->prepare("
        SELECT cr.*, cg.name AS club_name, cg.room, t.name AS teacher_name
        FROM club_registrations cr
        JOIN club_groups cg ON cg.id = cr.club_id
        LEFT JOIN att_teachers t ON t.id = cg.teacher_id
        WHERE cr.student_id = ? AND cr.semester = ? AND cr.year = ?
        LIMIT 1
    ");
    $stmt->execute([$student_code, $semester, $year]);
    $myReg = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Open clubs with vacancy
$clubs = [];
if ($semester) {
    $stmt = $pdo->prepare("
        SELECT cg.*, t.name AS teacher_name,
               COUNT(cr.id) AS registered_count
        FROM club_groups cg
        LEFT JOIN att_teachers t ON t.id = cg.teacher_id
        LEFT JOIN club_registrations cr ON cr.club_id = cg.id AND cr.semester = ? AND cr.year = ?
        WHERE cg.status = 'open' AND cg.semester = ? AND cg.year = ?
        GROUP BY cg.id ORDER BY cg.name
    ");
    $stmt->execute([$semester, $year, $semester, $year]);
    $clubs = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Check if registration is open
$now       = date('Y-m-d H:i:s');
$regOpen   = $cfg && (!$cfg['reg_open'] || $now >= $cfg['reg_open']) && (!$cfg['reg_close'] || $now <= $cfg['reg_close']);
$canChange = $myReg && $cfg && $cfg['allow_change'] && $regOpen;
?><!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<title>เลือกชุมนุม | โรงเรียนละลมวิทยา</title>
<meta name="theme-color" content="#7c3aed">
<link href="https://fonts.googleapis.com/css2?family=Prompt:wght@400;600;700;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<script src="https://cdn.tailwindcss.com"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>body { font-family:'Prompt',sans-serif; overscroll-behavior-y:contain; }
@keyframes fadeUp { from{opacity:0;transform:translateY(16px)} to{opacity:1;transform:translateY(0)} }
.fade-up { animation:fadeUp .35s ease-out both; }</style>
</head>
<body class="bg-slate-100 min-h-screen" style="padding-bottom:env(safe-area-inset-bottom)">

<header class="bg-gradient-to-r from-violet-600 to-purple-600 text-white sticky top-0 z-50 shadow-lg"
        style="padding-top:env(safe-area-inset-top)">
    <div class="max-w-lg mx-auto px-4 py-3 flex items-center gap-3">
        <button onclick="history.back()"
                class="w-9 h-9 bg-white/20 rounded-xl flex items-center justify-center border border-white/20 active:bg-white/30 flex-shrink-0">
            <i class="bi bi-arrow-left text-lg"></i>
        </button>
        <div>
            <div class="font-black text-sm leading-tight">เลือกชุมนุม</div>
            <div class="text-violet-200 text-xs font-bold"><?= htmlspecialchars($student_name, ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars($student_class, ENT_QUOTES, 'UTF-8') ?></div>
        </div>
    </div>
</header>

<div class="max-w-lg mx-auto px-4 py-5 space-y-4">

<?php if (!$cfg || !$cfg['is_active']): ?>
<div class="bg-white rounded-3xl shadow-sm border border-slate-100 p-8 text-center fade-up">
    <i class="bi bi-clock text-4xl text-slate-300 block mb-2"></i>
    <p class="font-black text-slate-600">ยังไม่เปิดระบบเลือกชุมนุม</p>
    <p class="text-slate-400 text-sm">กรุณารอการแจ้งจากโรงเรียน</p>
</div>

<?php else: ?>

<!-- Registration Status Banner -->
<?php if (!$regOpen && $cfg['reg_close'] && $now > $cfg['reg_close']): ?>
<div class="bg-red-50 border border-red-200 rounded-2xl p-3 text-center text-red-600 text-sm font-bold fade-up">
    <i class="bi bi-x-circle me-1"></i>หมดเวลาลงทะเบียนแล้ว
</div>
<?php elseif (!$regOpen): ?>
<div class="bg-amber-50 border border-amber-200 rounded-2xl p-3 text-center text-amber-700 text-sm font-bold fade-up">
    <i class="bi bi-clock me-1"></i>ยังไม่ถึงเวลาลงทะเบียน
</div>
<?php endif; ?>

<!-- My Club -->
<?php if ($myReg): ?>
<div class="bg-violet-50 border-2 border-violet-200 rounded-3xl p-4 fade-up">
    <div class="flex items-start gap-3">
        <div class="w-10 h-10 bg-violet-100 rounded-xl flex items-center justify-center flex-shrink-0">
            <i class="bi bi-people-fill text-violet-600 text-lg"></i>
        </div>
        <div class="flex-1">
            <p class="text-xs font-black text-violet-500 uppercase tracking-wider mb-1">ชุมนุมที่เลือก</p>
            <p class="font-black text-slate-800"><?= htmlspecialchars($myReg['club_name'], ENT_QUOTES, 'UTF-8') ?></p>
            <p class="text-slate-500 text-xs mt-0.5">
                <i class="bi bi-person me-1"></i><?= htmlspecialchars($myReg['teacher_name'] ?? '-', ENT_QUOTES, 'UTF-8') ?>
                <?= $myReg['room'] ? ' · <i class="bi bi-door-open me-1"></i>'.htmlspecialchars($myReg['room'],ENT_QUOTES,'UTF-8') : '' ?>
            </p>
        </div>
        <a href="/student_club/my_club.php"
           class="text-xs font-black text-violet-600 bg-white rounded-xl px-3 py-1.5 border border-violet-200">
            รายละเอียด
        </a>
    </div>
    <?php if ($canChange): ?>
    <p class="text-violet-500 text-xs mt-2 text-center">สามารถเปลี่ยนชุมนุมได้ (ยังอยู่ในช่วงลงทะเบียน)</p>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- Club List -->
<?php if ($regOpen || !$myReg): ?>
<div class="fade-up" style="animation-delay:.1s">
    <div class="flex items-center gap-2 px-1 mb-3">
        <div class="w-8 h-8 bg-violet-50 rounded-xl flex items-center justify-center flex-shrink-0">
            <i class="bi bi-grid text-violet-600"></i>
        </div>
        <h2 class="font-black text-slate-800">ชุมนุมทั้งหมด</h2>
    </div>

    <?php if (empty($clubs)): ?>
    <div class="bg-white rounded-3xl shadow-sm border border-slate-100 p-8 text-center">
        <i class="bi bi-inbox text-4xl text-slate-300 block mb-2"></i>
        <p class="text-slate-400 font-bold text-sm">ยังไม่มีชุมนุมที่เปิดรับ</p>
    </div>
    <?php else: ?>
    <div class="space-y-3">
    <?php foreach ($clubs as $c):
        $left  = (int)$c['max_capacity'] - (int)$c['registered_count'];
        $full  = $left <= 0;
        $isMy  = $myReg && $myReg['club_id'] == $c['id'];
        $pct   = $c['max_capacity'] > 0 ? round($c['registered_count'] / $c['max_capacity'] * 100) : 0;
    ?>
    <div class="bg-white rounded-3xl shadow-sm border <?= $isMy ? 'border-violet-300 border-2' : 'border-slate-100' ?> p-4 fade-up">
        <div class="flex items-start gap-3">
            <div class="w-10 h-10 <?= $full ? 'bg-slate-100' : 'bg-violet-50' ?> rounded-xl flex items-center justify-center flex-shrink-0">
                <i class="bi bi-people <?= $full ? 'text-slate-400' : 'text-violet-500' ?> text-lg"></i>
            </div>
            <div class="flex-1 min-w-0">
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <p class="font-black text-slate-800 text-sm"><?= htmlspecialchars($c['name'], ENT_QUOTES, 'UTF-8') ?></p>
                        <p class="text-slate-500 text-xs mt-0.5">
                            <i class="bi bi-person me-1"></i><?= htmlspecialchars($c['teacher_name'] ?? '-', ENT_QUOTES, 'UTF-8') ?>
                            <?= $c['room'] ? ' · <i class="bi bi-door-open me-1"></i>'.htmlspecialchars($c['room'],ENT_QUOTES,'UTF-8') : '' ?>
                        </p>
                    </div>
                    <?php if ($isMy): ?>
                    <span class="px-2 py-1 bg-violet-100 text-violet-700 rounded-full text-xs font-black flex-shrink-0">เลือกอยู่</span>
                    <?php elseif ($full): ?>
                    <span class="px-2 py-1 bg-slate-100 text-slate-500 rounded-full text-xs font-black flex-shrink-0">เต็มแล้ว</span>
                    <?php endif; ?>
                </div>

                <?php if ($c['description']): ?>
                <p class="text-slate-500 text-xs mt-1 line-clamp-2"><?= htmlspecialchars($c['description'], ENT_QUOTES, 'UTF-8') ?></p>
                <?php endif; ?>

                <div class="flex items-center gap-3 mt-2">
                    <div class="flex-1">
                        <div class="flex justify-between text-xs text-slate-400 mb-0.5">
                            <span>ที่เหลือ <?= max(0,$left) ?> จาก <?= $c['max_capacity'] ?></span>
                            <span><?= $pct ?>%</span>
                        </div>
                        <div class="bg-slate-100 rounded-full h-1.5">
                            <div class="<?= $full ? 'bg-slate-400' : ($pct>=80?'bg-amber-400':'bg-violet-500') ?> h-1.5 rounded-full transition-all" style="width:<?= $pct ?>%"></div>
                        </div>
                    </div>
                    <?php if (!$isMy && $regOpen && ($canChange || !$myReg)): ?>
                    <button onclick="selectClub(<?= $c['id'] ?>,'<?= htmlspecialchars($c['name'],ENT_QUOTES,'UTF-8') ?>')"
                            <?= $full ? 'disabled' : '' ?>
                            class="px-4 py-1.5 <?= $full ? 'bg-slate-200 text-slate-400 cursor-not-allowed' : 'bg-violet-600 text-white hover:bg-violet-700 active:scale-95' ?> rounded-xl text-xs font-black transition-all flex-shrink-0">
                        <?= $full ? 'เต็ม' : ($myReg ? 'เปลี่ยน' : 'เลือก') ?>
                    </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php endif; ?>
</div>

<script>
async function selectClub(clubId, clubName) {
    const action = <?= json_encode($myReg ? 'เปลี่ยน' : 'เลือก') ?>;
    const { isConfirmed } = await Swal.fire({
        icon:'question',
        title: action + 'ชุมนุม?',
        text: clubName,
        showCancelButton:true,
        confirmButtonText: action,
        cancelButtonText:'ยกเลิก',
        confirmButtonColor:'#7c3aed'
    });
    if (!isConfirmed) return;

    const res  = await fetch('/student_club/api/register.php', {
        method:'POST', headers:{'Content-Type':'application/json'},
        body: JSON.stringify({ club_id: clubId })
    });
    const data = await res.json();
    if (data.status === 'success') {
        await Swal.fire({ icon:'success', title:'สำเร็จ!', text:data.message, confirmButtonColor:'#7c3aed' });
        location.reload();
    } else {
        Swal.fire({ icon:'error', title:'เกิดข้อผิดพลาด', text:data.message, confirmButtonColor:'#7c3aed' });
    }
}
</script>
</body>
</html>
