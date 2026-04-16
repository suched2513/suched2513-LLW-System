<?php
/**
 * index.php — LLW Platinum Portal (Landing Page)
 * World-class landing with animations, responsive design, light & shadow effects
 */
session_start();
require_once 'config.php';

$isLoggedIn = isset($_SESSION['user_id']);
$userRole   = $_SESSION['llw_role'] ?? 'guest';
$fullname   = $_SESSION['fullname'] ?? '';
$firstname  = $_SESSION['firstname'] ?? 'U';

// ถ้า logged in → ดึง Stats (prepared statements)
$stats = ['wfh_today' => 0, 'cb_borrowed' => 0, 'att_today' => 0, 'leave_pending' => 0, 'assembly_today' => 0];
if ($isLoggedIn) {
    try {
        $pdo = getPdo();
        $today = date('Y-m-d');

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM wfh_timelogs WHERE log_date = ?");
        $stmt->execute([$today]);
        $stats['wfh_today'] = (int)$stmt->fetchColumn();

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM cb_borrow_logs WHERE status = ?");
        $stmt->execute(['Borrowed']);
        $stats['cb_borrowed'] = (int)$stmt->fetchColumn();

        $stmt = $pdo->prepare("SELECT COUNT(DISTINCT student_id) FROM att_attendance WHERE date = ?");
        $stmt->execute([$today]);
        $stats['att_today'] = (int)$stmt->fetchColumn();

        // Assembly: เช็คชื่อเข้าแถววันนี้
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM assembly_attendance WHERE date = ?");
            $stmt->execute([$today]);
            $stats['assembly_today'] = (int)$stmt->fetchColumn();
        } catch (Exception $e) { $stats['assembly_today'] = 0; }

        // leave_requests อาจยังไม่มีตาราง
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM leave_requests WHERE status_boss1 = 0");
            $stmt->execute();
            $stats['leave_pending'] = (int)$stmt->fetchColumn();
        } catch (Exception $e) {}
    } catch (Exception $e) {
        // stats ไม่แสดงถ้า DB error
    }
}

// Quick access ตาม role
$quickAccess = [];
if ($isLoggedIn) {
    switch ($userRole) {
        case 'super_admin':
            $quickAccess = [
                ['url' => 'central_dashboard.php', 'icon' => 'bi-shield-lock-fill', 'label' => 'Admin Panel', 'color' => 'blue'],
                ['url' => 'admin/dashboard.php', 'icon' => 'bi-speedometer2', 'label' => 'WFH Admin', 'color' => 'emerald'],
                ['url' => 'supervision.php', 'icon' => 'bi-mortarboard-fill', 'label' => 'นิเทศการสอน', 'color' => 'indigo'],
                ['url' => 'teacher_leave/index.php', 'icon' => 'bi-calendar-check-fill', 'label' => 'ใบลาออนไลน์', 'color' => 'rose'],
                ['url' => 'plc_system/dashboard.php', 'icon' => 'bi-journal-richtext', 'label' => 'PLC Online', 'color' => 'violet'],
            ];
            break;
        case 'wfh_admin':
            $quickAccess = [
                ['url' => 'admin/dashboard.php', 'icon' => 'bi-speedometer2', 'label' => 'WFH Admin', 'color' => 'emerald'],
                ['url' => 'admin/reports.php', 'icon' => 'bi-bar-chart-fill', 'label' => 'รายงาน', 'color' => 'indigo'],
                ['url' => 'supervision.php', 'icon' => 'bi-mortarboard-fill', 'label' => 'นิเทศการสอน', 'color' => 'rose'],
                ['url' => 'teacher_leave/index.php', 'icon' => 'bi-calendar-check-fill', 'label' => 'ใบลาออนไลน์', 'color' => 'indigo'],
                ['url' => 'plc_system/dashboard.php', 'icon' => 'bi-journal-richtext', 'label' => 'PLC Online', 'color' => 'violet'],
            ];
            break;
        case 'att_teacher':
            $quickAccess = [
                ['url' => 'assembly/dashboard.php',          'icon' => 'bi-people-fill',       'label' => 'เช็คชื่อเข้าแถว', 'color' => 'amber'],
                ['url' => 'attendance_system/dashboard.php', 'icon' => 'bi-person-check-fill', 'label' => 'เช็คชื่อในคาบ',  'color' => 'indigo'],
                ['url' => 'supervision.php',                 'icon' => 'bi-mortarboard-fill',  'label' => 'รายงานนิเทศ',    'color' => 'rose'],
                ['url' => 'teacher_leave/index.php',         'icon' => 'bi-calendar-check-fill', 'label' => 'ใบลาออนไลน์',  'color' => 'amber'],
                ['url' => 'plc_system/dashboard.php',        'icon' => 'bi-journal-richtext',  'label' => 'PLC Online',    'color' => 'violet'],
            ];
            break;
        case 'cb_admin':
            $quickAccess = [
                ['url' => 'chromebook/index.php', 'icon' => 'bi-laptop', 'label' => 'Chromebook', 'color' => 'cyan'],
            ];
            break;
        default:
            $quickAccess = [
                ['url' => 'user/dashboard.php', 'icon' => 'bi-geo-alt-fill', 'label' => 'ลงเวลา', 'color' => 'emerald'],
            ];
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LLW Platinum Portal | โรงเรียนละลมวิทยา</title>
    <meta name="description" content="ศูนย์รวมระบบบริหารจัดการโรงเรียนละลมวิทยา ครอบคลุมการเช็คชื่อ จัดการ Chromebook ลงเวลาบุคลากร และระบบขออนุญาต">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { font-family: 'Prompt', sans-serif; }

        /* ── Gradient text ── */
        .gradient-text {
            background: linear-gradient(135deg, #3b82f6 0%, #6366f1 50%, #a855f7 100%);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        /* ── Background mesh ── */
        .bg-mesh {
            background-color: #f8fafc;
            background-image:
                radial-gradient(ellipse at 20% 30%, rgba(59,130,246,0.07) 0%, transparent 60%),
                radial-gradient(ellipse at 80% 70%, rgba(99,102,241,0.07) 0%, transparent 60%),
                radial-gradient(ellipse at 50% 10%, rgba(16,185,129,0.05) 0%, transparent 50%);
        }

        /* ── Floating orbs ── */
        .orb {
            position: absolute; border-radius: 50%; pointer-events: none;
            filter: blur(80px); opacity: 0.5;
        }
        @keyframes floatA { 0%,100% { transform: translate(0,0) scale(1); } 50% { transform: translate(30px,-40px) scale(1.1); } }
        @keyframes floatB { 0%,100% { transform: translate(0,0) scale(1); } 50% { transform: translate(-20px,30px) scale(0.9); } }
        @keyframes floatC { 0%,100% { transform: translate(0,0); } 50% { transform: translate(15px,-20px); } }

        /* ── Card animations ── */
        .portal-card {
            transition: all 0.5s cubic-bezier(0.34, 1.56, 0.64, 1);
            transform-style: preserve-3d; perspective: 800px;
        }
        .portal-card:hover {
            transform: translateY(-16px) scale(1.03);
        }
        .portal-card:hover .card-icon {
            transform: rotateY(15deg) scale(1.2);
            box-shadow: 0 20px 40px rgba(0,0,0,0.12);
        }
        .portal-card:hover .card-arrow {
            opacity: 1; transform: translateX(0);
        }
        .portal-card:hover .card-bg-icon {
            transform: scale(1.3) rotate(8deg);
            opacity: 0.08;
        }
        .portal-card:hover .card-glow {
            opacity: 1;
        }
        .card-icon {
            transition: all 0.5s cubic-bezier(0.34, 1.56, 0.64, 1);
            transform-style: preserve-3d;
        }
        .card-arrow {
            opacity: 0; transform: translateX(-8px);
            transition: all 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
        }
        .card-bg-icon {
            transition: all 0.6s ease; opacity: 0.04;
        }
        .card-glow {
            opacity: 0; transition: opacity 0.5s ease;
            position: absolute; inset: -1px; border-radius: inherit;
            z-index: -1;
        }

        /* ── Stagger entrance ── */
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(50px) scale(0.95); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }
        .animate-slide-up {
            animation: slideUp 0.6s cubic-bezier(0.22, 1, 0.36, 1) both;
        }

        /* ── Hero fade in ── */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-fade-in { animation: fadeIn 0.8s ease-out both; }
        .animate-fade-in-delay-1 { animation: fadeIn 0.8s ease-out 0.15s both; }
        .animate-fade-in-delay-2 { animation: fadeIn 0.8s ease-out 0.3s both; }
        .animate-fade-in-delay-3 { animation: fadeIn 0.8s ease-out 0.45s both; }

        /* ── Stat card pulse ── */
        @keyframes statPulse {
            0%,100% { box-shadow: 0 0 0 0 rgba(59,130,246,0.1); }
            50% { box-shadow: 0 0 0 8px rgba(59,130,246,0); }
        }
        .stat-card:hover { animation: statPulse 1.5s ease infinite; }

        /* ── Glow effect ── */
        @keyframes glowPulse {
            0%,100% { opacity: 0.4; } 50% { opacity: 0.8; }
        }

        /* ── Badge float ── */
        @keyframes badgeFloat {
            0%,100% { transform: translateY(0); } 50% { transform: translateY(-6px); }
        }
        .animate-badge-float { animation: badgeFloat 3s ease-in-out infinite; }

        /* ── Scrollbar ── */
        ::-webkit-scrollbar { width: 5px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }

        /* ── Wave ── */
        .wave-divider { position: relative; overflow: hidden; }
        .wave-divider::before {
            content: ''; position: absolute; top: -1px; left: 0; right: 0; height: 40px;
            background: url("data:image/svg+xml,%3Csvg viewBox='0 0 1200 40' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M0 20 Q300 0 600 20 Q900 40 1200 20 V0 H0Z' fill='%23f8fafc'/%3E%3C/svg%3E") no-repeat center;
            background-size: cover;
        }
    </style>
</head>
<body class="bg-mesh text-slate-800 overflow-x-hidden">

<!-- ===== FLOATING ORBS ===== -->
<div class="fixed inset-0 pointer-events-none overflow-hidden z-0" aria-hidden="true">
    <div class="orb w-[500px] h-[500px] bg-blue-400/30 -top-40 -left-40" style="animation: floatA 20s ease-in-out infinite;"></div>
    <div class="orb w-[400px] h-[400px] bg-indigo-400/20 top-1/3 -right-32" style="animation: floatB 25s ease-in-out infinite;"></div>
    <div class="orb w-[300px] h-[300px] bg-emerald-400/20 -bottom-20 left-1/4" style="animation: floatC 18s ease-in-out infinite;"></div>
    <div class="orb w-[200px] h-[200px] bg-rose-400/15 bottom-1/4 right-1/4" style="animation: floatA 22s ease-in-out infinite 3s;"></div>
</div>

<!-- ===== NAVIGATION ===== -->
<nav class="h-20 px-4 sm:px-6 md:px-12 flex items-center justify-between sticky top-0 z-50 bg-white/70 backdrop-blur-2xl border-b border-slate-200/40 shadow-lg shadow-slate-100/20">
    <a href="index.php" class="flex items-center gap-3 sm:gap-4 group">
        <div class="w-11 h-11 sm:w-12 sm:h-12 bg-gradient-to-br from-blue-600 to-indigo-700 rounded-2xl flex items-center justify-center text-white text-lg sm:text-xl font-black shadow-xl shadow-blue-200/50 italic group-hover:rotate-6 group-hover:scale-110 transition-all duration-300">LLW</div>
        <div>
            <div class="text-base sm:text-lg font-black text-slate-800 tracking-tight leading-none">Platinum Portal</div>
            <div class="text-[8px] sm:text-[9px] font-black text-blue-400 uppercase tracking-[0.2em] sm:tracking-[0.25em] mt-0.5">Unified School Ecosystem</div>
        </div>
    </a>
    <div class="flex items-center gap-2 sm:gap-3">
        <?php if ($isLoggedIn): ?>
            <!-- Quick Access Shortcuts -->
            <?php foreach ($quickAccess as $qa): ?>
            <a href="<?= htmlspecialchars($qa['url']) ?>" class="hidden md:flex w-10 h-10 items-center justify-center text-<?= $qa['color'] ?>-600 bg-<?= $qa['color'] ?>-50 rounded-xl hover:bg-<?= $qa['color'] ?>-600 hover:text-white transition-all shadow-sm hover:shadow-lg hover:scale-110 hover:-translate-y-0.5" title="<?= htmlspecialchars($qa['label']) ?>">
                <i class="bi <?= $qa['icon'] ?>"></i>
            </a>
            <?php endforeach; ?>
            <div class="hidden sm:flex items-center gap-3 px-3 sm:px-4 py-2 sm:py-2.5 bg-slate-50/80 rounded-2xl border border-slate-100">
                <div class="w-8 h-8 sm:w-9 sm:h-9 rounded-xl bg-gradient-to-br from-blue-500 to-indigo-600 text-white flex items-center justify-center font-black text-xs sm:text-sm shadow-lg shadow-blue-200/50"><?= mb_substr($firstname, 0, 1) ?></div>
                <div>
                    <div class="text-xs sm:text-sm font-black text-slate-700 leading-none"><?= htmlspecialchars($fullname) ?></div>
                    <div class="text-[8px] sm:text-[9px] font-bold text-slate-400 uppercase tracking-widest mt-0.5"><?= htmlspecialchars(strtoupper($userRole)) ?></div>
                </div>
            </div>
            <a href="logout.php" class="w-10 h-10 sm:w-11 sm:h-11 flex items-center justify-center text-rose-500 bg-rose-50 rounded-2xl hover:bg-rose-500 hover:text-white transition-all shadow-sm hover:shadow-lg hover:scale-110">
                <i class="bi bi-power text-lg"></i>
            </a>
        <?php else: ?>
            <a href="login.php" class="bg-gradient-to-r from-blue-600 to-indigo-600 text-white px-6 sm:px-8 py-2.5 sm:py-3 rounded-2xl font-black text-xs sm:text-sm shadow-xl shadow-blue-200/50 hover:shadow-2xl hover:shadow-blue-300/50 hover:scale-105 hover:-translate-y-0.5 transition-all flex items-center gap-2">
                เข้าสู่ระบบ <i class="bi bi-arrow-right-short text-xl"></i>
            </a>
        <?php endif; ?>
    </div>
</nav>

<!-- ===== HERO ===== -->
<section class="relative container mx-auto px-4 sm:px-6 md:px-12 py-16 sm:py-20 md:py-28 text-center max-w-5xl z-10">
    <div class="animate-badge-float inline-flex items-center gap-2 px-4 sm:px-5 py-2 bg-white/80 backdrop-blur-sm border border-blue-100 text-blue-600 rounded-full text-[9px] sm:text-[10px] font-black uppercase tracking-[0.2em] sm:tracking-[0.3em] mb-6 sm:mb-8 shadow-lg shadow-blue-100/30 animate-fade-in">
        <i class="bi bi-stars"></i>
        Welcome to Lalom Wittaya School
    </div>
    <h1 class="text-4xl sm:text-5xl md:text-6xl lg:text-7xl font-black tracking-tight mb-6 sm:mb-8 leading-[1.1] animate-fade-in-delay-1">
        ระบบบริหารจัดการ<br>
        <span class="gradient-text">วิถีถิ่น ยุคใหม่ 2026</span>
    </h1>
    <p class="text-slate-400 text-sm sm:text-base md:text-lg font-medium max-w-2xl mx-auto leading-relaxed animate-fade-in-delay-2 px-4">
        แพลตฟอร์มศูนย์กลางเพื่อบุคลากรและนักเรียน ครอบคลุมทุกมิติการศึกษาด้วยเทคโนโลยียุคใหม่
    </p>

    <?php if (!$isLoggedIn): ?>
    <div class="mt-8 sm:mt-12 flex flex-col sm:flex-row gap-3 sm:gap-4 justify-center animate-fade-in-delay-3 px-4">
        <a href="login.php" class="px-8 sm:px-10 py-3.5 sm:py-4 bg-gradient-to-r from-blue-600 to-indigo-600 text-white rounded-2xl font-black shadow-2xl shadow-blue-200/50 hover:shadow-blue-300/70 hover:scale-105 hover:-translate-y-1 transition-all text-sm flex items-center justify-center gap-2">
            <i class="bi bi-box-arrow-in-right"></i>เข้าสู่ระบบ Platinum
        </a>
        <a href="#modules" class="px-8 sm:px-10 py-3.5 sm:py-4 bg-white/80 backdrop-blur-sm text-slate-600 border border-slate-200 rounded-2xl font-black hover:bg-white hover:shadow-xl hover:scale-105 hover:-translate-y-1 transition-all text-sm shadow-lg flex items-center justify-center gap-2">
            <i class="bi bi-grid-1x2-fill"></i>ดูระบบทั้งหมด
        </a>
    </div>
    <?php endif; ?>
</section>

<!-- ===== LIVE STATS (เมื่อ Login) ===== -->
<?php if ($isLoggedIn): ?>
<section class="relative container mx-auto px-4 sm:px-6 md:px-12 mb-12 sm:mb-16 max-w-5xl z-10">
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
        <?php
        $statItems = [
            ['value' => $stats['wfh_today'],       'label' => 'เข้างานวันนี้',   'icon' => 'bi-geo-alt-fill',     'from' => 'emerald-500', 'to' => 'teal-600',    'shadow' => 'emerald'],
            ['value' => $stats['cb_borrowed'],      'label' => 'CB ยืมอยู่',     'icon' => 'bi-laptop',           'from' => 'indigo-500',  'to' => 'purple-600',  'shadow' => 'indigo'],
            ['value' => $stats['assembly_today'],   'label' => 'เข้าแถววันนี้',  'icon' => 'bi-people-fill',      'from' => 'amber-500',   'to' => 'orange-500',  'shadow' => 'amber'],
            ['value' => $stats['leave_pending'],    'label' => 'รออนุมัติ',      'icon' => 'bi-hourglass-split',  'from' => 'rose-500',    'to' => 'pink-600',    'shadow' => 'rose'],
        ];
        foreach ($statItems as $i => $s):
        ?>
        <div class="stat-card animate-slide-up bg-gradient-to-br from-<?= $s['from'] ?> to-<?= $s['to'] ?> rounded-2xl sm:rounded-3xl p-4 sm:p-6 text-white shadow-xl shadow-<?= $s['shadow'] ?>-200/40 hover:shadow-2xl hover:shadow-<?= $s['shadow'] ?>-300/50 hover:-translate-y-1 transition-all cursor-default group" style="animation-delay: <?= $i * 0.1 ?>s">
            <div class="flex items-center justify-between mb-2 sm:mb-3">
                <i class="bi <?= $s['icon'] ?> text-lg sm:text-xl opacity-80 group-hover:scale-125 transition-transform"></i>
                <div class="w-2 h-2 rounded-full bg-white/40 animate-pulse"></div>
            </div>
            <div class="text-2xl sm:text-3xl md:text-4xl font-black leading-none counter" data-target="<?= $s['value'] ?>">0</div>
            <div class="text-[9px] sm:text-[10px] font-bold opacity-80 uppercase tracking-widest mt-1 sm:mt-2"><?= $s['label'] ?></div>
        </div>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<!-- ===== MODULE CARDS ===== -->
<section id="modules" class="relative container mx-auto px-4 sm:px-6 md:px-12 pb-16 sm:pb-24 max-w-5xl z-10">
    <div class="text-center mb-8 sm:mb-12">
        <h2 class="text-xl sm:text-2xl font-black text-slate-800 tracking-tight">ระบบทั้งหมด</h2>
        <p class="text-xs sm:text-sm text-slate-400 font-bold mt-2 uppercase tracking-widest">All Management Modules</p>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4 sm:gap-6">

        <?php
        $modules = [
            [
                'url'      => 'assembly/dashboard.php',
                'icon'     => 'bi-people-fill',
                'bgIcon'   => 'bi-people',
                'title'    => 'เช็คชื่อเข้าแถว',
                'desc'     => 'บันทึกการเข้าแถวประจำวัน ตรวจเครื่องแต่งกาย พร้อมแจ้งเตือน Telegram',
                'color'    => 'amber',
                'gradient' => 'from-amber-500 to-orange-500',
                'delay'    => 0,
            ],
            [
                'url'      => 'attendance_system/dashboard.php',
                'icon'     => 'bi-person-check-fill',
                'bgIcon'   => 'bi-person-check',
                'title'    => 'เช็คชื่อในคาบเรียน',
                'desc'     => 'บันทึกเวลาเรียนรายวิชา ขาด ลา มา สาย แบบ Real-time',
                'color'    => 'blue',
                'gradient' => 'from-blue-500 to-cyan-500',
                'delay'    => 0.1,
            ],
            [
                'url'      => 'chromebook/index.php',
                'icon'     => 'bi-laptop',
                'bgIcon'   => 'bi-laptop',
                'title'    => 'จัดการ Chromebook',
                'desc'     => 'ระบบยืม-คืนอุปกรณ์ดิจิทัล ตรวจสอบสถานะและคลังพัสดุ',
                'color'    => 'indigo',
                'gradient' => 'from-indigo-500 to-purple-500',
                'delay'    => 0.2,
            ],
            [
                'url'      => 'index_wfh.php',
                'icon'     => 'bi-person-badge-fill',
                'bgIcon'   => 'bi-geo-alt',
                'title'    => 'ลงเวลาบุคลากร',
                'desc'     => 'ระบบลงเวลาเข้า-ออกงานด้วย GPS ยืนยันตัวตนผ่านพิกัดโรงเรียน',
                'color'    => 'emerald',
                'gradient' => 'from-emerald-500 to-teal-500',
                'delay'    => 0.3,
            ],
            [
                'url'      => 'leave_system.php',
                'icon'     => 'bi-door-open-fill',
                'bgIcon'   => 'bi-door-open',
                'title'    => 'ขออนุญาตออกนอก',
                'desc'     => 'ระบบยื่นคำขอลาออนไลน์ พร้อมแจ้งเตือนผู้บริหารผ่าน Telegram',
                'color'    => 'rose',
                'gradient' => 'from-rose-500 to-pink-500',
                'delay'    => 0.4,
            ],
            [
                'url'      => 'supervision.php',
                'icon'     => 'bi-mortarboard-fill',
                'bgIcon'   => 'bi-mortarboard',
                'title'    => 'นิเทศการสอน',
                'desc'     => 'ระบบนิเทศการจัดการเรียนรู้เชิงรุก ติดตามสมรรถนะครูรายบุคคล',
                'color'    => 'indigo',
                'gradient' => 'from-indigo-600 to-blue-600',
                'delay'    => 0.5,
            ],
            [
                'url'      => 'teacher_leave/index.php',
                'icon'     => 'bi-calendar-check-fill',
                'bgIcon'   => 'bi-file-earmark-text',
                'title'    => 'ใบลาออนไลน์',
                'desc'     => 'ระบบยื่นใบลาป่วย กิจ พักผ่อน ตามระเบียบราชการ พร้อมสถิติสะสม',
                'color'    => 'rose',
                'gradient' => 'from-rose-600 to-red-600',
                'delay'    => 0.6,
            ],
            [
                'url'      => 'plc_system/dashboard.php',
                'icon'     => 'bi-journal-richtext',
                'bgIcon'   => 'bi-journal-bookmark',
                'title'    => 'ระบบ PLC ออนไลน์',
                'desc'     => 'ชุมชนแห่งการเรียนรู้ทางวิชาชีพ บันทึกกิจกรรมตามกระบวนการ PDCA',
                'color'    => 'violet',
                'gradient' => 'from-violet-600 to-purple-600',
                'delay'    => 0.7,
            ],
        ];

        foreach ($modules as $m):
            // ถ้า login แล้ว → ไปหน้าโมดูลตรงๆ; ถ้ายังไม่ login → ผ่าน login?redirect=
            $moduleUrl = $isLoggedIn ? $m['url'] : ('login.php?redirect=/' . $m['url']);
        ?>
        <a href="<?= htmlspecialchars($moduleUrl) ?>"
           class="portal-card animate-slide-up group relative bg-white/80 backdrop-blur-sm p-6 sm:p-8 rounded-[2rem] sm:rounded-[2.5rem] border border-slate-100/80 shadow-lg shadow-slate-100/50 hover:shadow-2xl hover:shadow-<?= $m['color'] ?>-200/40 overflow-hidden"
           style="animation-delay: <?= $m['delay'] ?>s">

            <!-- Glow border on hover -->
            <div class="card-glow bg-gradient-to-br <?= $m['gradient'] ?> blur-sm"></div>

            <!-- Background icon -->
            <div class="card-bg-icon absolute -right-6 -bottom-6 text-<?= $m['color'] ?>-500 text-[8rem] sm:text-[9rem] transition-all duration-500">
                <i class="bi <?= $m['bgIcon'] ?>"></i>
            </div>

            <!-- Icon -->
            <div class="card-icon relative w-14 h-14 sm:w-16 sm:h-16 bg-gradient-to-br <?= $m['gradient'] ?> text-white rounded-2xl sm:rounded-3xl flex items-center justify-center text-2xl sm:text-3xl mb-6 sm:mb-8 shadow-xl shadow-<?= $m['color'] ?>-200/50">
                <i class="bi <?= $m['icon'] ?>"></i>
            </div>

            <!-- Content -->
            <h3 class="relative text-base sm:text-lg font-black text-slate-800 mb-2 sm:mb-3"><?= $m['title'] ?></h3>
            <p class="relative text-[11px] sm:text-xs text-slate-400 font-bold leading-relaxed"><?= $m['desc'] ?></p>

            <!-- Arrow -->
            <div class="card-arrow relative mt-5 sm:mt-6 flex items-center gap-2 text-<?= $m['color'] ?>-500 text-[10px] font-black uppercase tracking-widest">
                <?= $isLoggedIn ? 'เข้าสู่ระบบ' : 'เข้าสู่ระบบก่อน' ?> <i class="bi bi-arrow-right"></i>
            </div>
        </a>
        <?php endforeach; ?>

    </div>
</section>

<?php if ($isLoggedIn && !empty($quickAccess)): ?>
<!-- ===== QUICK ACCESS BAR ===== -->
<section class="relative container mx-auto px-4 sm:px-6 md:px-12 pb-16 sm:pb-24 max-w-5xl z-10">
    <div class="bg-white/70 backdrop-blur-xl rounded-[2rem] sm:rounded-[2.5rem] border border-slate-100 shadow-xl p-6 sm:p-8">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h3 class="text-sm sm:text-base font-black text-slate-800 flex items-center gap-2">
                    <i class="bi bi-lightning-charge-fill text-amber-500"></i> Quick Access
                </h3>
                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mt-1">ทางลัดสำหรับ <?= htmlspecialchars(strtoupper($userRole)) ?></p>
            </div>
            <div class="flex flex-wrap gap-2 sm:gap-3">
                <?php foreach ($quickAccess as $qa): ?>
                <a href="<?= htmlspecialchars($qa['url']) ?>"
                   class="flex items-center gap-2 px-4 sm:px-5 py-2.5 sm:py-3 bg-<?= $qa['color'] ?>-50 text-<?= $qa['color'] ?>-600 rounded-xl sm:rounded-2xl font-bold text-xs sm:text-sm hover:bg-<?= $qa['color'] ?>-600 hover:text-white transition-all shadow-sm hover:shadow-lg hover:scale-105">
                    <i class="bi <?= $qa['icon'] ?>"></i>
                    <?= htmlspecialchars($qa['label']) ?>
                </a>
                <?php endforeach; ?>
                <a href="central_dashboard.php"
                   class="flex items-center gap-2 px-4 sm:px-5 py-2.5 sm:py-3 bg-slate-50 text-slate-500 rounded-xl sm:rounded-2xl font-bold text-xs sm:text-sm hover:bg-slate-700 hover:text-white transition-all shadow-sm hover:shadow-lg hover:scale-105">
                    <i class="bi bi-grid-fill"></i> ทั้งหมด
                </a>
            </div>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- ===== FOOTER ===== -->
<footer class="relative wave-divider border-t border-slate-100/50 pt-16 pb-10 bg-white/60 backdrop-blur-sm z-10">
    <div class="container mx-auto px-4 sm:px-6 max-w-4xl">

        <!-- Logo + Name -->
        <div class="text-center mb-8">
            <div class="w-14 h-14 bg-gradient-to-br from-blue-600 to-indigo-700 rounded-2xl flex items-center justify-center text-white text-xl font-black italic mx-auto mb-4 shadow-xl shadow-blue-200/50 hover:rotate-12 transition-transform cursor-default">LLW</div>
            <p class="text-base font-black text-slate-700">โรงเรียนละลมวิทยา</p>
            <p class="text-[10px] font-black text-slate-300 uppercase tracking-[0.35em] mt-1">Lalom Wittaya School • Powered by Advanced School Intelligence</p>
        </div>

        <!-- Divider -->
        <div class="h-px bg-gradient-to-r from-transparent via-slate-200 to-transparent mb-8"></div>

        <!-- Developer Credit -->
        <div class="flex flex-col sm:flex-row items-center justify-center gap-4 mb-8">
            <div class="flex items-center gap-3 bg-slate-50 border border-slate-100 rounded-2xl px-5 py-3 shadow-sm hover:shadow-md hover:-translate-y-0.5 transition-all">
                <div class="w-9 h-9 bg-gradient-to-br from-blue-500 to-indigo-600 rounded-xl flex items-center justify-center text-white font-black text-sm shadow-lg shadow-blue-200/50">ส</div>
                <div class="text-left">
                    <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">พัฒนาโดย</p>
                    <p class="text-sm font-black text-slate-700 leading-tight">นายสุเชษฐ์ ไพรบึง</p>
                    <p class="text-[10px] font-bold text-slate-400">ครูโรงเรียนละลมวิทยา</p>
                </div>
            </div>
            <a href="https://www.facebook.com/suched.p?locale=th_TH" target="_blank" rel="noopener noreferrer"
               class="flex items-center gap-2.5 bg-[#1877F2] text-white px-5 py-3 rounded-2xl font-bold text-sm hover:bg-[#166FE5] hover:shadow-lg hover:shadow-blue-200/50 hover:-translate-y-0.5 transition-all shadow-md">
                <i class="bi bi-facebook text-lg"></i>
                <div class="text-left">
                    <p class="text-[9px] font-black uppercase tracking-widest opacity-80">ติดตามผ่าน</p>
                    <p class="font-black text-sm leading-tight">Facebook</p>
                </div>
            </a>
        </div>

        <!-- Links + Copyright -->
        <div class="flex flex-col sm:flex-row items-center justify-between gap-3">
            <div class="flex gap-5 text-[9px] font-bold text-slate-300 uppercase tracking-widest">
                <a href="#" class="hover:text-blue-500 transition-colors">Privacy Policy</a>
                <span class="text-slate-200">|</span>
                <a href="login.php" class="hover:text-blue-500 transition-colors">Admin Login</a>
                <span class="text-slate-200">|</span>
                <a href="#modules" class="hover:text-blue-500 transition-colors">ระบบทั้งหมด</a>
            </div>
            <span class="text-[9px] font-black text-slate-300 uppercase tracking-widest">© <?= date('Y') ?> LLW System</span>
        </div>
    </div>
</footer>

<!-- ===== COUNT UP ANIMATION ===== -->
<script>
document.addEventListener('DOMContentLoaded', () => {
    // Count-up animation
    document.querySelectorAll('.counter').forEach(el => {
        const target = parseInt(el.dataset.target) || 0;
        if (target === 0) { el.textContent = '0'; return; }
        const duration = 1500;
        const step = Math.max(1, Math.ceil(target / (duration / 16)));
        let current = 0;
        const timer = setInterval(() => {
            current += step;
            if (current >= target) { current = target; clearInterval(timer); }
            el.textContent = current.toLocaleString();
        }, 16);
    });

    // Intersection Observer for scroll animations
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.style.animationPlayState = 'running';
                observer.unobserve(entry.target);
            }
        });
    }, { threshold: 0.1 });

    document.querySelectorAll('.animate-slide-up').forEach(el => {
        el.style.animationPlayState = 'paused';
        observer.observe(el);
    });
});
</script>

</body>
</html>
