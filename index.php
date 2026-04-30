<?php
/**
 * index.php — LLW Platinum Portal
 * Desktop: world-class landing  |  Mobile: native app shell
 */
session_start();
require_once 'config.php';

$isLoggedIn = isset($_SESSION['user_id']);
$userRole   = $_SESSION['llw_role'] ?? 'guest';
$fullname   = $_SESSION['fullname'] ?? '';
$firstname  = $_SESSION['firstname'] ?? 'U';

// ── Live Stats ──────────────────────────────────────────────────────────────
$stats = ['wfh_today' => 0, 'cb_borrowed' => 0, 'att_today' => 0, 'leave_pending' => 0, 'assembly_today' => 0];
if ($isLoggedIn) {
    try {
        $pdo   = getPdo();
        $today = date('Y-m-d');

        $s = $pdo->prepare("SELECT COUNT(*) FROM wfh_timelogs WHERE log_date = ?");
        $s->execute([$today]); $stats['wfh_today'] = (int)$s->fetchColumn();

        $s = $pdo->prepare("SELECT COUNT(*) FROM cb_borrow_logs WHERE status = ?");
        $s->execute(['Borrowed']); $stats['cb_borrowed'] = (int)$s->fetchColumn();

        $s = $pdo->prepare("SELECT COUNT(DISTINCT student_id) FROM att_attendance WHERE date = ?");
        $s->execute([$today]); $stats['att_today'] = (int)$s->fetchColumn();

        try {
            $s = $pdo->prepare("SELECT COUNT(*) FROM assembly_attendance WHERE date = ?");
            $s->execute([$today]); $stats['assembly_today'] = (int)$s->fetchColumn();
        } catch (Exception $e) {}

        try {
            $s = $pdo->prepare("SELECT COUNT(*) FROM leave_requests WHERE status_boss1 = 0");
            $s->execute(); $stats['leave_pending'] = (int)$s->fetchColumn();
        } catch (Exception $e) {}
    } catch (Exception $e) {}
}

// ── Quick Access by Role ─────────────────────────────────────────────────────
$quickAccess = [];
if ($isLoggedIn) {
    switch ($userRole) {
        case 'super_admin':
            $quickAccess = [
                ['url' => 'central_dashboard.php',            'icon' => 'bi-shield-lock-fill',   'label' => 'Admin Panel',    'color' => 'blue'],
                ['url' => 'admin/dashboard.php',              'icon' => 'bi-speedometer2',        'label' => 'WFH Admin',      'color' => 'emerald'],
                ['url' => 'supervision.php',                  'icon' => 'bi-mortarboard-fill',    'label' => 'นิเทศการสอน',   'color' => 'indigo'],
                ['url' => 'teacher_leave/index.php',          'icon' => 'bi-calendar-check-fill', 'label' => 'ใบลาออนไลน์',  'color' => 'rose'],
                ['url' => 'plc_system/dashboard.php',         'icon' => 'bi-journal-richtext',    'label' => 'PLC Online',     'color' => 'violet'],
                ['url' => 'behavior/dashboard.php',           'icon' => 'bi-journal-text',        'label' => 'พฤติกรรม',      'color' => 'violet'],
                ['url' => 'school_project/admin/dashboard.php','icon'=> 'bi-cash-coin',           'label' => 'งบประมาณ SBMS', 'color' => 'amber'],
            ];
            break;
        case 'wfh_admin':
            $quickAccess = [
                ['url' => 'admin/dashboard.php',              'icon' => 'bi-speedometer2',        'label' => 'WFH Admin',      'color' => 'emerald'],
                ['url' => 'admin/reports.php',                'icon' => 'bi-bar-chart-fill',      'label' => 'รายงาน',        'color' => 'indigo'],
                ['url' => 'supervision.php',                  'icon' => 'bi-mortarboard-fill',    'label' => 'นิเทศการสอน',   'color' => 'rose'],
                ['url' => 'teacher_leave/index.php',          'icon' => 'bi-calendar-check-fill', 'label' => 'ใบลาออนไลน์',  'color' => 'indigo'],
                ['url' => 'plc_system/dashboard.php',         'icon' => 'bi-journal-richtext',    'label' => 'PLC Online',     'color' => 'violet'],
                ['url' => 'behavior/dashboard.php',           'icon' => 'bi-journal-text',        'label' => 'พฤติกรรม',      'color' => 'violet'],
                ['url' => 'school_project/admin/dashboard.php','icon'=> 'bi-cash-coin',           'label' => 'งบประมาณ SBMS', 'color' => 'amber'],
            ];
            break;
        case 'att_teacher':
            $quickAccess = [
                ['url' => 'assembly/dashboard.php',          'icon' => 'bi-people-fill',         'label' => 'เช็คชื่อเข้าแถว','color'=> 'amber'],
                ['url' => 'attendance_system/dashboard.php', 'icon' => 'bi-person-check-fill',   'label' => 'เช็คชื่อในคาบ', 'color' => 'indigo'],
                ['url' => 'supervision.php',                 'icon' => 'bi-mortarboard-fill',    'label' => 'รายงานนิเทศ',   'color' => 'rose'],
                ['url' => 'teacher_leave/index.php',         'icon' => 'bi-calendar-check-fill', 'label' => 'ใบลาออนไลน์',  'color' => 'amber'],
                ['url' => 'plc_system/dashboard.php',        'icon' => 'bi-journal-richtext',    'label' => 'PLC Online',     'color' => 'violet'],
                ['url' => 'behavior/dashboard.php',          'icon' => 'bi-journal-text',        'label' => 'พฤติกรรม',      'color' => 'violet'],
                ['url' => 'school_project/index.php',        'icon' => 'bi-cash-coin',           'label' => 'งบประมาณ SBMS', 'color' => 'amber'],
            ];
            break;
        case 'cb_admin':
            $quickAccess = [['url' => 'chromebook/index.php', 'icon' => 'bi-laptop', 'label' => 'Chromebook', 'color' => 'cyan']];
            break;
        default:
            $quickAccess = [['url' => 'user/dashboard.php', 'icon' => 'bi-geo-alt-fill', 'label' => 'ลงเวลา', 'color' => 'emerald']];
    }
}

// ── Thai Date Helpers ────────────────────────────────────────────────────────
$hour       = (int)date('H');
$greeting   = $hour < 12 ? 'อรุณสวัสดิ์' : ($hour < 17 ? 'สวัสดียามบ่าย' : 'สวัสดียามเย็น');
$thaiMonths = ['','มกราคม','กุมภาพันธ์','มีนาคม','เมษายน','พฤษภาคม','มิถุนายน',
               'กรกฎาคม','สิงหาคม','กันยายน','ตุลาคม','พฤศจิกายน','ธันวาคม'];
$thaiDays   = ['อาทิตย์','จันทร์','อังคาร','พุธ','พฤหัส','ศุกร์','เสาร์'];
$buddhistYear = (int)date('Y') + 543;
$thaiDate   = 'วัน'.$thaiDays[(int)date('w')].' '.date('j').' '.$thaiMonths[(int)date('n')].' '.$buddhistYear;

// ── Modules ──────────────────────────────────────────────────────────────────
$modules = [
    ['url'=>'assembly/dashboard.php',           'icon'=>'bi-people-fill',         'bgIcon'=>'bi-people',               'title'=>'เช็คชื่อเข้าแถว',    'short'=>'เข้าแถว',    'desc'=>'บันทึกการเข้าแถวประจำวัน ตรวจเครื่องแต่งกาย พร้อมแจ้งเตือน Telegram',                 'color'=>'amber',  'gradient'=>'from-amber-500 to-orange-500',   'delay'=>0],
    ['url'=>'attendance_system/dashboard.php',  'icon'=>'bi-person-check-fill',   'bgIcon'=>'bi-person-check',         'title'=>'เช็คชื่อในคาบเรียน', 'short'=>'เช็คชื่อ',   'desc'=>'บันทึกเวลาเรียนรายวิชา ขาด ลา มา สาย แบบ Real-time',                                 'color'=>'blue',   'gradient'=>'from-blue-500 to-cyan-500',      'delay'=>0.1],
    ['url'=>'chromebook/index.php',             'icon'=>'bi-laptop',              'bgIcon'=>'bi-laptop',               'title'=>'จัดการ Chromebook',   'short'=>'Chromebook', 'desc'=>'ระบบยืม-คืนอุปกรณ์ดิจิทัล ตรวจสอบสถานะและคลังพัสดุ',                                'color'=>'indigo', 'gradient'=>'from-indigo-500 to-purple-500',  'delay'=>0.2],
    ['url'=>'index_wfh.php',                   'icon'=>'bi-person-badge-fill',   'bgIcon'=>'bi-geo-alt',              'title'=>'ลงเวลาบุคลากร',      'short'=>'ลงเวลา',    'desc'=>'ระบบลงเวลาเข้า-ออกงานด้วย GPS ยืนยันตัวตนผ่านพิกัดโรงเรียน',                       'color'=>'emerald','gradient'=>'from-emerald-500 to-teal-500',   'delay'=>0.3],
    ['url'=>'leave_system.php',                'icon'=>'bi-door-open-fill',      'bgIcon'=>'bi-door-open',            'title'=>'ขออนุญาตออกนอก',     'short'=>'ออกนอก',    'desc'=>'ระบบยื่นคำขอลาออนไลน์ พร้อมแจ้งเตือนผู้บริหารผ่าน Telegram',                       'color'=>'rose',   'gradient'=>'from-rose-500 to-pink-500',      'delay'=>0.4],
    ['url'=>'supervision.php',                 'icon'=>'bi-mortarboard-fill',    'bgIcon'=>'bi-mortarboard',          'title'=>'นิเทศการสอน',        'short'=>'นิเทศ',     'desc'=>'ระบบนิเทศการจัดการเรียนรู้เชิงรุก ติดตามสมรรถนะครูรายบุคคล',                       'color'=>'indigo', 'gradient'=>'from-indigo-600 to-blue-600',    'delay'=>0.5],
    ['url'=>'teacher_leave/index.php',         'icon'=>'bi-calendar-check-fill', 'bgIcon'=>'bi-file-earmark-text',    'title'=>'ใบลาออนไลน์',        'short'=>'ใบลา',      'desc'=>'ระบบยื่นใบลาป่วย กิจ พักผ่อน ตามระเบียบราชการ พร้อมสถิติสะสม',                    'color'=>'rose',   'gradient'=>'from-rose-600 to-red-600',       'delay'=>0.6],
    ['url'=>'plc_system/dashboard.php',        'icon'=>'bi-journal-richtext',    'bgIcon'=>'bi-journal-bookmark',     'title'=>'ระบบ PLC ออนไลน์',   'short'=>'PLC',       'desc'=>'ชุมชนแห่งการเรียนรู้ทางวิชาชีพ บันทึกกิจกรรมตามกระบวนการ PDCA',                  'color'=>'violet', 'gradient'=>'from-violet-600 to-purple-600',  'delay'=>0.7],
    ['url'=>'behavior/dashboard.php',          'icon'=>'bi-journal-text',        'bgIcon'=>'bi-journal-bookmark-fill','title'=>'บันทึกพฤติกรรม',     'short'=>'พฤติกรรม',  'desc'=>'ระบบจัดการคะแนนความประพฤติ บันทึกความดี และพฤติกรรมด้านต่างๆ',                    'color'=>'violet', 'gradient'=>'from-violet-600 to-indigo-600',  'delay'=>0.8],
    ['url'=>'behavior/student_view.php',       'icon'=>'bi-person-badge',        'bgIcon'=>'bi-mortarboard',          'title'=>'พอร์ทัลนักเรียน',    'short'=>'นักเรียน',  'desc'=>'ตรวจสอบคะแนนพฤติกรรม และส่งบันทึกความดีเพื่อขอรับคะแนน (ไม่ต้อง Login ครู)','color'=>'violet', 'gradient'=>'from-purple-500 to-indigo-500',  'delay'=>0.9,'isPublic'=>true],
    ['url'=>'homeroom/index.php',              'icon'=>'bi-mortarboard-fill',    'bgIcon'=>'bi-mortarboard',          'title'=>'ระบบครูที่ปรึกษา',   'short'=>'ที่ปรึกษา', 'desc'=>'ศูนย์กลางการดูแลหนักเรียนประจำชั้น ติดตามการเข้าแถว พฤติกรรม',                     'color'=>'indigo', 'gradient'=>'from-indigo-600 to-violet-700',  'delay'=>1.0],
    ['url'=>'school_project/index.php',        'icon'=>'bi-cash-coin',           'bgIcon'=>'bi-wallet2',              'title'=>'ระบบงบประมาณ (SBMS)', 'short'=>'งบประมาณ',  'desc'=>'จัดการโครงการ ขอดำเนินการ เบิกจ่ายงบประมาณ และพิมพ์เอกสารอนุมัติ (2569)',       'color'=>'amber',  'gradient'=>'from-amber-500 to-orange-500',   'delay'=>1.1],
    ['url'=>'bus/admin/dashboard.php',         'icon'=>'bi-bus-front-fill',      'bgIcon'=>'bi-bus-front',            'title'=>'ระบบจัดการรถรับส่ง', 'short'=>'รถรับส่ง',    'desc'=>'จัดการสายรถรับส่ง ลงทะเบียนนักเรียน และบันทึกการชำระเงินค่าบริการ',             'color'=>'orange', 'gradient'=>'from-orange-500 to-amber-500',   'delay'=>1.2],
];
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>LLW | โรงเรียนละลมวิทยา</title>
    <meta name="description" content="ศูนย์รวมระบบบริหารจัดการโรงเรียนละลมวิทยา">
    <!-- PWA / App Meta -->
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="LLW Portal">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="theme-color" content="#2563eb">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { font-family: 'Prompt', sans-serif; touch-action: manipulation; }

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
        .orb { position:absolute; border-radius:50%; pointer-events:none; filter:blur(80px); opacity:.5; }
        @keyframes floatA { 0%,100%{transform:translate(0,0) scale(1)} 50%{transform:translate(30px,-40px) scale(1.1)} }
        @keyframes floatB { 0%,100%{transform:translate(0,0) scale(1)} 50%{transform:translate(-20px,30px) scale(.9)} }
        @keyframes floatC { 0%,100%{transform:translate(0,0)} 50%{transform:translate(15px,-20px)} }

        /* ── Desktop card animations ── */
        .portal-card { transition:all .5s cubic-bezier(.34,1.56,.64,1); transform-style:preserve-3d; perspective:800px; }
        .portal-card:hover { transform:translateY(-16px) scale(1.03); }
        .portal-card:hover .card-icon { transform:rotateY(15deg) scale(1.2); box-shadow:0 20px 40px rgba(0,0,0,.12); }
        .portal-card:hover .card-arrow { opacity:1; transform:translateX(0); }
        .portal-card:hover .card-bg-icon { transform:scale(1.3) rotate(8deg); opacity:.08; }
        .portal-card:hover .card-glow { opacity:1; }
        .card-icon { transition:all .5s cubic-bezier(.34,1.56,.64,1); transform-style:preserve-3d; }
        .card-arrow { opacity:0; transform:translateX(-8px); transition:all .4s cubic-bezier(.34,1.56,.64,1); }
        .card-bg-icon { transition:all .6s ease; opacity:.04; }
        .card-glow { opacity:0; transition:opacity .5s ease; position:absolute; inset:-1px; border-radius:inherit; z-index:-1; }

        /* ── Entrance animations ── */
        @keyframes slideUp { from{opacity:0;transform:translateY(50px) scale(.95)} to{opacity:1;transform:translateY(0) scale(1)} }
        .animate-slide-up { animation:slideUp .6s cubic-bezier(.22,1,.36,1) both; }
        @keyframes fadeIn { from{opacity:0;transform:translateY(30px)} to{opacity:1;transform:translateY(0)} }
        .animate-fade-in          { animation:fadeIn .8s ease-out both; }
        .animate-fade-in-delay-1  { animation:fadeIn .8s ease-out .15s both; }
        .animate-fade-in-delay-2  { animation:fadeIn .8s ease-out .3s both; }
        .animate-fade-in-delay-3  { animation:fadeIn .8s ease-out .45s both; }

        /* ── Stat pulse ── */
        @keyframes statPulse { 0%,100%{box-shadow:0 0 0 0 rgba(59,130,246,.1)} 50%{box-shadow:0 0 0 8px rgba(59,130,246,0)} }
        .stat-card:hover { animation:statPulse 1.5s ease infinite; }

        /* ── Badge float ── */
        @keyframes badgeFloat { 0%,100%{transform:translateY(0)} 50%{transform:translateY(-6px)} }
        .animate-badge-float { animation:badgeFloat 3s ease-in-out infinite; }

        /* ── Wave ── */
        .wave-divider::before {
            content:''; position:absolute; top:-1px; left:0; right:0; height:40px;
            background:url("data:image/svg+xml,%3Csvg viewBox='0 0 1200 40' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M0 20 Q300 0 600 20 Q900 40 1200 20 V0 H0Z' fill='%23f8fafc'/%3E%3C/svg%3E") no-repeat center;
            background-size:cover;
        }

        /* ── Scrollbar ── */
        ::-webkit-scrollbar{width:5px}
        ::-webkit-scrollbar-track{background:transparent}
        ::-webkit-scrollbar-thumb{background:#cbd5e1;border-radius:10px}

        /* ════════════════════════════════════════════
           MOBILE APP STYLES  (max-width: 639px)
        ════════════════════════════════════════════ */
        @media (max-width: 639px) {
            /* App shell */
            body { background:#f1f5f9; overscroll-behavior-y:contain; }

            /* Safe area */
            .mob-safe-top    { padding-top: max(env(safe-area-inset-top, 0px), 0px); }
            .mob-safe-bottom { padding-bottom: max(env(safe-area-inset-bottom, 0px), 8px); }

            /* App icon tap feedback */
            .app-icon:active > div:first-child {
                transform: scale(0.88);
                opacity: .85;
                transition: transform .1s ease, opacity .1s ease;
            }
            .app-icon > div:first-child {
                transition: transform .25s cubic-bezier(.34,1.56,.64,1), opacity .2s ease, box-shadow .2s ease;
            }

            /* Bottom nav active indicator */
            .bnav-tab.is-active i   { color: #2563eb !important; }
            .bnav-tab.is-active span { color: #2563eb !important; font-weight: 900 !important; }
            .bnav-tab.is-active .bnav-dot { opacity: 1 !important; }

            /* Quick access pill tap */
            .qa-pill:active { transform:scale(.95); opacity:.85; }

            /* Smooth scroll */
            html { scroll-behavior: smooth; }

            /* App icon entrance */
            @keyframes appIconIn {
                from { opacity:0; transform:scale(.6) translateY(20px); }
                to   { opacity:1; transform:scale(1) translateY(0); }
            }
            .app-icon { animation: appIconIn .4s cubic-bezier(.34,1.56,.64,1) both; }
        }
    </style>
</head>
<body class="bg-mesh text-slate-800 overflow-x-hidden">

<!-- ╔══════════════════════════════════════════════════════╗
     ║  MOBILE APP LAYOUT  (hidden on sm+)                 ║
     ╚══════════════════════════════════════════════════════╝ -->
<div class="sm:hidden">

    <!-- ── App Header (fixed) ───────────────────────────────── -->
    <header class="fixed top-0 left-0 right-0 z-50 bg-gradient-to-r from-blue-600 to-indigo-700 mob-safe-top shadow-lg shadow-blue-900/30">
        <div class="flex items-center justify-between px-4 py-3">
            <!-- Logo + Name -->
            <div class="flex items-center gap-2.5">
                <div class="w-9 h-9 bg-white/15 rounded-[10px] flex items-center justify-center text-white font-black text-xs italic border border-white/20 shadow-inner">LLW</div>
                <div>
                    <div class="text-white font-black text-[14px] leading-tight">Platinum Portal</div>
                    <div class="text-blue-200 text-[9px] font-bold uppercase tracking-[0.2em]">โรงเรียนละลมวิทยา</div>
                </div>
            </div>
            <!-- Right Actions -->
            <div class="flex items-center gap-2">
                <?php if ($isLoggedIn): ?>
                    <div class="w-9 h-9 bg-white/15 rounded-full flex items-center justify-center text-white font-black text-sm border border-white/25 shadow-inner"><?= mb_substr($firstname, 0, 1) ?></div>
                    <a href="logout.php" class="w-9 h-9 bg-white/10 rounded-xl flex items-center justify-center text-white/80 border border-white/15 active:bg-white/20 transition-colors">
                        <i class="bi bi-power text-base"></i>
                    </a>
                <?php else: ?>
                    <a href="login.php" class="px-4 py-1.5 bg-white text-blue-600 rounded-xl font-black text-xs shadow-lg active:scale-95 transition-transform">เข้าสู่ระบบ</a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <!-- ── Scrollable Content ────────────────────────────────── -->
    <main class="pt-[64px] pb-[72px] min-h-screen" id="mob-main">

        <!-- ① Greeting / Hero Card ─────────────────────────── -->
        <section id="mob-home" class="px-4 pt-4 pb-2">
            <?php if ($isLoggedIn): ?>
            <!-- Logged-in greeting -->
            <div class="relative bg-gradient-to-br from-blue-500 via-blue-600 to-indigo-700 rounded-[24px] p-5 text-white shadow-xl shadow-blue-300/40 overflow-hidden">
                <!-- BG pattern -->
                <div class="absolute -right-8 -top-8 w-32 h-32 bg-white/5 rounded-full"></div>
                <div class="absolute -right-2 -bottom-6 w-24 h-24 bg-white/5 rounded-full"></div>
                <div class="absolute right-14 top-0 w-16 h-16 bg-white/5 rounded-full"></div>
                <div class="relative flex items-start justify-between">
                    <div class="flex-1 min-w-0">
                        <p class="text-blue-200 text-[11px] font-bold tracking-wider uppercase"><?= htmlspecialchars($greeting) ?></p>
                        <h2 class="text-2xl font-black mt-0.5 truncate"><?= htmlspecialchars($fullname ?: $firstname) ?></h2>
                        <div class="flex items-center gap-1.5 mt-2">
                            <span class="px-2.5 py-0.5 bg-white/15 rounded-full text-[10px] font-black uppercase tracking-wider border border-white/15"><?= htmlspecialchars(strtoupper($userRole)) ?></span>
                        </div>
                        <p class="text-blue-200 text-[11px] font-medium mt-2.5 flex items-center gap-1.5">
                            <i class="bi bi-calendar3"></i><?= htmlspecialchars($thaiDate) ?>
                        </p>
                    </div>
                    <div class="w-16 h-16 bg-white/10 rounded-2xl flex items-center justify-center border border-white/15 flex-shrink-0 ml-3">
                        <span class="text-2xl font-black italic text-white">LLW</span>
                    </div>
                </div>
            </div>

            <!-- Live Stats 2×2 -->
            <div class="grid grid-cols-2 gap-3 mt-3">
                <?php
                $mobileStats = [
                    ['v'=>$stats['wfh_today'],     'label'=>'เข้างานวันนี้', 'icon'=>'bi-geo-alt-fill',    'accent'=>'emerald', 'bg'=>'bg-emerald-50', 'ic'=>'text-emerald-600'],
                    ['v'=>$stats['cb_borrowed'],    'label'=>'CB ยืมอยู่',    'icon'=>'bi-laptop',          'accent'=>'indigo',  'bg'=>'bg-indigo-50',  'ic'=>'text-indigo-600'],
                    ['v'=>$stats['assembly_today'], 'label'=>'เข้าแถววันนี้', 'icon'=>'bi-people-fill',     'accent'=>'amber',   'bg'=>'bg-amber-50',   'ic'=>'text-amber-600'],
                    ['v'=>$stats['leave_pending'],  'label'=>'รออนุมัติ',     'icon'=>'bi-hourglass-split', 'accent'=>'rose',    'bg'=>'bg-rose-50',    'ic'=>'text-rose-600'],
                ];
                foreach ($mobileStats as $ms): ?>
                <div class="bg-white rounded-2xl p-4 shadow-sm border border-slate-100 flex items-center gap-3">
                    <div class="w-11 h-11 <?= $ms['bg'] ?> rounded-xl flex items-center justify-center flex-shrink-0">
                        <i class="bi <?= $ms['icon'] ?> text-xl <?= $ms['ic'] ?>"></i>
                    </div>
                    <div class="min-w-0">
                        <div class="text-2xl font-black text-slate-800 leading-none counter" data-target="<?= $ms['v'] ?>">0</div>
                        <div class="text-[10px] font-bold text-slate-400 mt-0.5 truncate"><?= $ms['label'] ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <?php else: ?>
            <!-- Guest hero -->
            <div class="relative bg-gradient-to-br from-blue-500 via-indigo-600 to-violet-700 rounded-[24px] p-6 text-white shadow-xl shadow-blue-300/40 overflow-hidden text-center">
                <div class="absolute inset-0 opacity-10" style="background-image:radial-gradient(circle at 30% 50%,white 1px,transparent 1px),radial-gradient(circle at 70% 20%,white 1px,transparent 1px); background-size:40px 40px;"></div>
                <div class="relative">
                    <div class="w-20 h-20 bg-white/15 rounded-[20px] flex items-center justify-center text-3xl font-black italic text-white mx-auto mb-4 border border-white/20 shadow-xl">LLW</div>
                    <h2 class="text-2xl font-black leading-tight">ระบบบริหารจัดการ<br>โรงเรียนละลมวิทยา</h2>
                    <p class="text-blue-200 text-xs font-medium mt-2"><?= htmlspecialchars($thaiDate) ?></p>
                    <div class="flex flex-col gap-2 mt-5">
                        <a href="login.php" class="flex items-center justify-center gap-2 w-full py-3 bg-white text-blue-700 rounded-2xl font-black text-sm shadow-lg active:scale-95 transition-transform">
                            <i class="bi bi-box-arrow-in-right"></i>เข้าสู่ระบบ
                        </a>
                        <a href="behavior/student_view.php" class="flex items-center justify-center gap-2 w-full py-3 bg-white/15 text-white rounded-2xl font-bold text-sm border border-white/20 active:bg-white/25 transition-colors">
                            <i class="bi bi-mortarboard-fill"></i>พอร์ทัลนักเรียน (ไม่ต้อง Login)
                        </a>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </section>

        <!-- ② Quick Access (if logged in) ─────────────────────── -->
        <?php if ($isLoggedIn && !empty($quickAccess)): ?>
        <section id="mob-quick" class="px-4 pt-4">
            <div class="flex items-center justify-between mb-3">
                <h3 class="text-[13px] font-black text-slate-700 flex items-center gap-1.5">
                    <i class="bi bi-lightning-charge-fill text-amber-500"></i>
                    ทางลัดสำหรับคุณ
                </h3>
                <span class="text-[10px] font-bold text-slate-400 uppercase"><?= htmlspecialchars(strtoupper($userRole)) ?></span>
            </div>
            <div class="flex gap-2.5 overflow-x-auto pb-2 -mx-1 px-1 snap-x snap-mandatory" style="scrollbar-width:none">
                <?php foreach ($quickAccess as $qa): ?>
                <a href="<?= htmlspecialchars($qa['url']) ?>"
                   class="qa-pill flex-shrink-0 snap-start flex items-center gap-2.5 px-4 py-3 bg-<?= $qa['color'] ?>-50 border border-<?= $qa['color'] ?>-100 rounded-2xl active:scale-95 transition-transform shadow-sm">
                    <div class="w-9 h-9 bg-<?= $qa['color'] ?>-100 rounded-xl flex items-center justify-center flex-shrink-0">
                        <i class="bi <?= $qa['icon'] ?> text-<?= $qa['color'] ?>-600 text-lg"></i>
                    </div>
                    <span class="text-[12px] font-black text-slate-700 whitespace-nowrap"><?= htmlspecialchars($qa['label']) ?></span>
                </a>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>

        <!-- ③ All Systems — App Icon Grid ──────────────────────── -->
        <section id="mob-systems" class="px-4 pt-5 pb-2">
            <div class="flex items-center justify-between mb-3">
                <h3 class="text-[13px] font-black text-slate-700 flex items-center gap-1.5">
                    <i class="bi bi-grid-fill text-blue-500"></i>
                    ระบบทั้งหมด
                </h3>
                <span class="text-[10px] font-bold text-slate-400 uppercase">All Modules</span>
            </div>

            <div class="grid grid-cols-3 gap-3">
                <?php foreach ($modules as $i => $m):
                    $isPublic  = isset($m['isPublic']) && $m['isPublic'];
                    $moduleUrl = ($isLoggedIn || $isPublic) ? $m['url'] : ('login.php?redirect=/'.$m['url']);
                ?>
                <a href="<?= htmlspecialchars($moduleUrl) ?>"
                   class="app-icon flex flex-col items-center gap-1.5 p-1.5 rounded-2xl"
                   style="animation-delay:<?= $i * 0.05 ?>s">
                    <!-- Icon Box -->
                    <div class="w-full aspect-square rounded-[22px] bg-gradient-to-br <?= $m['gradient'] ?> flex items-center justify-center shadow-lg shadow-<?= $m['color'] ?>-200/60 relative overflow-hidden">
                        <!-- Shine overlay -->
                        <div class="absolute inset-0 bg-gradient-to-b from-white/20 to-transparent rounded-[22px]"></div>
                        <i class="bi <?= $m['icon'] ?> text-white relative" style="font-size:1.85rem"></i>
                    </div>
                    <!-- Label -->
                    <span class="text-[11px] font-bold text-slate-600 text-center leading-tight line-clamp-2"><?= htmlspecialchars($m['short']) ?></span>
                </a>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- ④ Profile / Login Card ─────────────────────────────── -->
        <section id="mob-me" class="px-4 pt-5 pb-4">
            <?php if ($isLoggedIn): ?>
            <div class="bg-white rounded-[20px] p-4 shadow-sm border border-slate-100">
                <div class="flex items-center gap-3 pb-4 border-b border-slate-100">
                    <div class="w-12 h-12 bg-gradient-to-br from-blue-500 to-indigo-600 rounded-2xl flex items-center justify-center text-white font-black text-lg shadow-lg shadow-blue-200/50">
                        <?= mb_substr($firstname, 0, 1) ?>
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="font-black text-slate-800 truncate"><?= htmlspecialchars($fullname) ?></div>
                        <div class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mt-0.5"><?= htmlspecialchars(strtoupper($userRole)) ?></div>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-2 mt-3">
                    <a href="change_password.php" class="flex items-center justify-center gap-2 py-2.5 bg-slate-50 rounded-xl text-xs font-bold text-slate-600 border border-slate-100 active:bg-slate-100 transition-colors">
                        <i class="bi bi-key-fill text-slate-400"></i>เปลี่ยนรหัสผ่าน
                    </a>
                    <a href="logout.php" class="flex items-center justify-center gap-2 py-2.5 bg-rose-50 rounded-xl text-xs font-bold text-rose-600 border border-rose-100 active:bg-rose-100 transition-colors">
                        <i class="bi bi-power"></i>ออกจากระบบ
                    </a>
                </div>
            </div>
            <?php else: ?>
            <div class="bg-white rounded-[20px] p-5 shadow-sm border border-slate-100 text-center">
                <i class="bi bi-person-circle text-5xl text-slate-300"></i>
                <p class="text-sm font-black text-slate-700 mt-3">ยังไม่ได้เข้าสู่ระบบ</p>
                <p class="text-[11px] text-slate-400 mt-1 mb-4">กรุณาเข้าสู่ระบบเพื่อใช้งานฟีเจอร์ทั้งหมด</p>
                <a href="login.php" class="flex items-center justify-center gap-2 w-full py-3 bg-gradient-to-r from-blue-600 to-indigo-600 text-white rounded-2xl font-black text-sm shadow-lg shadow-blue-200/50 active:scale-95 transition-transform">
                    <i class="bi bi-box-arrow-in-right"></i>เข้าสู่ระบบ
                </a>
            </div>
            <?php endif; ?>

            <!-- Footer credit -->
            <p class="text-center text-[10px] text-slate-300 font-bold mt-4 uppercase tracking-widest">
                © <?= date('Y') ?> LLW System &nbsp;·&nbsp; โรงเรียนละลมวิทยา
            </p>
        </section>

    </main><!-- /mob-main -->

    <!-- ── Bottom Navigation (fixed) ────────────────────────── -->
    <nav class="fixed bottom-0 left-0 right-0 z-50 bg-white/95 backdrop-blur-2xl border-t border-slate-200/60 shadow-2xl shadow-slate-900/10 mob-safe-bottom">
        <div class="flex items-stretch h-14">

            <a href="#mob-home" data-tab="home"
               class="bnav-tab is-active flex-1 flex flex-col items-center justify-center gap-0.5 relative active:bg-slate-50 transition-colors">
                <div class="bnav-dot absolute top-1 w-1.5 h-1.5 rounded-full bg-blue-600 opacity-0 transition-opacity"></div>
                <i class="bi bi-house-fill text-xl text-blue-600"></i>
                <span class="text-[9px] font-black text-blue-600 uppercase">หน้าหลัก</span>
            </a>

            <a href="#mob-systems" data-tab="systems"
               class="bnav-tab flex-1 flex flex-col items-center justify-center gap-0.5 relative active:bg-slate-50 transition-colors">
                <div class="bnav-dot absolute top-1 w-1.5 h-1.5 rounded-full bg-blue-600 opacity-0 transition-opacity"></div>
                <i class="bi bi-grid-fill text-xl text-slate-400"></i>
                <span class="text-[9px] font-bold text-slate-400 uppercase">ระบบ</span>
            </a>

            <?php if ($isLoggedIn && !empty($quickAccess)): ?>
            <a href="#mob-quick" data-tab="quick"
               class="bnav-tab flex-1 flex flex-col items-center justify-center gap-0.5 relative active:bg-slate-50 transition-colors">
                <div class="bnav-dot absolute top-1 w-1.5 h-1.5 rounded-full bg-blue-600 opacity-0 transition-opacity"></div>
                <i class="bi bi-lightning-charge-fill text-xl text-slate-400"></i>
                <span class="text-[9px] font-bold text-slate-400 uppercase">ด่วน</span>
            </a>
            <?php endif; ?>

            <a href="#mob-me" data-tab="me"
               class="bnav-tab flex-1 flex flex-col items-center justify-center gap-0.5 relative active:bg-slate-50 transition-colors">
                <div class="bnav-dot absolute top-1 w-1.5 h-1.5 rounded-full bg-blue-600 opacity-0 transition-opacity"></div>
                <i class="bi bi-person-fill text-xl text-slate-400"></i>
                <span class="text-[9px] font-bold text-slate-400 uppercase"><?= $isLoggedIn ? 'ฉัน' : 'เข้าระบบ' ?></span>
            </a>

        </div>
    </nav>

</div><!-- /mobile layout -->


<!-- ╔══════════════════════════════════════════════════════╗
     ║  DESKTOP LAYOUT  (hidden on mobile, shown sm+)      ║
     ╚══════════════════════════════════════════════════════╝ -->

<!-- Floating Orbs (desktop only) -->
<div class="hidden sm:block fixed inset-0 pointer-events-none overflow-hidden z-0" aria-hidden="true">
    <div class="orb w-[500px] h-[500px] bg-blue-400/30 -top-40 -left-40" style="animation:floatA 20s ease-in-out infinite"></div>
    <div class="orb w-[400px] h-[400px] bg-indigo-400/20 top-1/3 -right-32" style="animation:floatB 25s ease-in-out infinite"></div>
    <div class="orb w-[300px] h-[300px] bg-emerald-400/20 -bottom-20 left-1/4" style="animation:floatC 18s ease-in-out infinite"></div>
    <div class="orb w-[200px] h-[200px] bg-rose-400/15 bottom-1/4 right-1/4" style="animation:floatA 22s ease-in-out infinite 3s"></div>
</div>

<!-- Desktop Navigation -->
<nav class="hidden sm:flex h-20 px-6 md:px-12 items-center justify-between sticky top-0 z-50 bg-white/70 backdrop-blur-2xl border-b border-slate-200/40 shadow-lg shadow-slate-100/20">
    <a href="index.php" class="flex items-center gap-4 group">
        <div class="w-12 h-12 bg-gradient-to-br from-blue-600 to-indigo-700 rounded-2xl flex items-center justify-center text-white text-xl font-black shadow-xl shadow-blue-200/50 italic group-hover:rotate-6 group-hover:scale-110 transition-all duration-300">LLW</div>
        <div>
            <div class="text-lg font-black text-slate-800 tracking-tight leading-none">Platinum Portal</div>
            <div class="text-[9px] font-black text-blue-400 uppercase tracking-[0.25em] mt-0.5">Unified School Ecosystem</div>
        </div>
    </a>
    <div class="flex items-center gap-3">
        <?php if ($isLoggedIn): ?>
            <?php foreach ($quickAccess as $qa): ?>
            <a href="<?= htmlspecialchars($qa['url']) ?>" class="hidden md:flex w-10 h-10 items-center justify-center text-<?= $qa['color'] ?>-600 bg-<?= $qa['color'] ?>-50 rounded-xl hover:bg-<?= $qa['color'] ?>-600 hover:text-white transition-all shadow-sm hover:shadow-lg hover:scale-110 hover:-translate-y-0.5" title="<?= htmlspecialchars($qa['label']) ?>">
                <i class="bi <?= $qa['icon'] ?>"></i>
            </a>
            <?php endforeach; ?>
            <div class="hidden sm:flex items-center gap-3 px-4 py-2.5 bg-slate-50/80 rounded-2xl border border-slate-100">
                <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-blue-500 to-indigo-600 text-white flex items-center justify-center font-black text-sm shadow-lg shadow-blue-200/50"><?= mb_substr($firstname, 0, 1) ?></div>
                <div>
                    <div class="text-sm font-black text-slate-700 leading-none"><?= htmlspecialchars($fullname) ?></div>
                    <div class="text-[9px] font-bold text-slate-400 uppercase tracking-widest mt-0.5"><?= htmlspecialchars(strtoupper($userRole)) ?></div>
                </div>
            </div>
            <a href="logout.php" class="w-11 h-11 flex items-center justify-center text-rose-500 bg-rose-50 rounded-2xl hover:bg-rose-500 hover:text-white transition-all shadow-sm hover:shadow-lg hover:scale-110">
                <i class="bi bi-power text-lg"></i>
            </a>
        <?php else: ?>
            <a href="login.php" class="bg-gradient-to-r from-blue-600 to-indigo-600 text-white px-8 py-3 rounded-2xl font-black text-sm shadow-xl shadow-blue-200/50 hover:shadow-2xl hover:shadow-blue-300/50 hover:scale-105 hover:-translate-y-0.5 transition-all flex items-center gap-2">
                เข้าสู่ระบบ <i class="bi bi-arrow-right-short text-xl"></i>
            </a>
        <?php endif; ?>
    </div>
</nav>

<!-- Desktop Hero -->
<section class="hidden sm:block relative container mx-auto px-6 md:px-12 py-20 md:py-28 text-center max-w-5xl z-10">
    <div class="animate-badge-float inline-flex items-center gap-2 px-5 py-2 bg-white/80 backdrop-blur-sm border border-blue-100 text-blue-600 rounded-full text-[10px] font-black uppercase tracking-[0.3em] mb-8 shadow-lg shadow-blue-100/30 animate-fade-in">
        <i class="bi bi-stars"></i>Welcome to Lalom Wittaya School
    </div>
    <h1 class="text-5xl md:text-6xl lg:text-7xl font-black tracking-tight mb-8 leading-[1.1] animate-fade-in-delay-1">
        ระบบบริหารจัดการ<br><span class="gradient-text">วิถีถิ่น ยุคใหม่ 2026</span>
    </h1>
    <p class="text-slate-400 text-base md:text-lg font-medium max-w-2xl mx-auto leading-relaxed animate-fade-in-delay-2">
        แพลตฟอร์มศูนย์กลางเพื่อบุคลากรและนักเรียน ครอบคลุมทุกมิติการศึกษาด้วยเทคโนโลยียุคใหม่
    </p>
    <?php if (!$isLoggedIn): ?>
    <div class="mt-12 flex flex-col sm:flex-row gap-4 justify-center animate-fade-in-delay-3">
        <a href="login.php" class="px-10 py-4 bg-gradient-to-r from-blue-600 to-indigo-600 text-white rounded-2xl font-black shadow-2xl shadow-blue-200/50 hover:shadow-blue-300/70 hover:scale-105 hover:-translate-y-1 transition-all text-sm flex items-center justify-center gap-2">
            <i class="bi bi-box-arrow-in-right"></i>เข้าสู่ระบบ Platinum
        </a>
        <a href="#desktop-modules" class="px-10 py-4 bg-white/80 backdrop-blur-sm text-slate-600 border border-slate-200 rounded-2xl font-black hover:bg-white hover:shadow-xl hover:scale-105 hover:-translate-y-1 transition-all text-sm shadow-lg flex items-center justify-center gap-2">
            <i class="bi bi-grid-1x2-fill"></i>ดูระบบทั้งหมด
        </a>
        <a href="behavior/student_view.php" class="px-10 py-4 bg-violet-600 text-white rounded-2xl font-black shadow-2xl shadow-violet-200/50 hover:shadow-violet-300/70 hover:scale-105 hover:-translate-y-1 transition-all text-sm flex items-center justify-center gap-2">
            <i class="bi bi-mortarboard-fill"></i>สำหรับนักเรียน (เช็คคะแนน)
        </a>
    </div>
    <?php endif; ?>
</section>

<!-- Desktop Live Stats -->
<?php if ($isLoggedIn): ?>
<section class="hidden sm:block relative container mx-auto px-6 md:px-12 mb-16 max-w-5xl z-10">
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <?php
        $desktopStats = [
            ['value'=>$stats['wfh_today'],     'label'=>'เข้างานวันนี้',  'icon'=>'bi-geo-alt-fill',    'from'=>'emerald-500','to'=>'teal-600',   'shadow'=>'emerald'],
            ['value'=>$stats['cb_borrowed'],   'label'=>'CB ยืมอยู่',     'icon'=>'bi-laptop',          'from'=>'indigo-500', 'to'=>'purple-600', 'shadow'=>'indigo'],
            ['value'=>$stats['assembly_today'],'label'=>'เข้าแถววันนี้',  'icon'=>'bi-people-fill',     'from'=>'amber-500',  'to'=>'orange-500', 'shadow'=>'amber'],
            ['value'=>$stats['leave_pending'], 'label'=>'รออนุมัติ',      'icon'=>'bi-hourglass-split', 'from'=>'rose-500',   'to'=>'pink-600',   'shadow'=>'rose'],
        ];
        foreach ($desktopStats as $i => $s):
        ?>
        <div class="stat-card animate-slide-up bg-gradient-to-br from-<?= $s['from'] ?> to-<?= $s['to'] ?> rounded-3xl p-6 text-white shadow-xl shadow-<?= $s['shadow'] ?>-200/40 hover:shadow-2xl hover:shadow-<?= $s['shadow'] ?>-300/50 hover:-translate-y-1 transition-all cursor-default group" style="animation-delay:<?= $i * 0.1 ?>s">
            <div class="flex items-center justify-between mb-3">
                <i class="bi <?= $s['icon'] ?> text-xl opacity-80 group-hover:scale-125 transition-transform"></i>
                <div class="w-2 h-2 rounded-full bg-white/40 animate-pulse"></div>
            </div>
            <div class="text-3xl md:text-4xl font-black leading-none counter" data-target="<?= $s['value'] ?>">0</div>
            <div class="text-[10px] font-bold opacity-80 uppercase tracking-widest mt-2"><?= $s['label'] ?></div>
        </div>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<!-- Desktop Module Cards -->
<section id="desktop-modules" class="hidden sm:block relative container mx-auto px-6 md:px-12 pb-24 max-w-5xl z-10">
    <div class="text-center mb-12">
        <h2 class="text-2xl font-black text-slate-800 tracking-tight">ระบบทั้งหมด</h2>
        <p class="text-sm text-slate-400 font-bold mt-2 uppercase tracking-widest">All Management Modules</p>
    </div>
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-6">
        <?php foreach ($modules as $m):
            $isPublic  = isset($m['isPublic']) && $m['isPublic'];
            $moduleUrl = ($isLoggedIn || $isPublic) ? $m['url'] : ('login.php?redirect=/'.$m['url']);
        ?>
        <a href="<?= htmlspecialchars($moduleUrl) ?>"
           class="portal-card animate-slide-up group relative bg-white/80 backdrop-blur-sm p-8 rounded-[2.5rem] border border-slate-100/80 shadow-lg shadow-slate-100/50 hover:shadow-2xl hover:shadow-<?= $m['color'] ?>-200/40 overflow-hidden"
           style="animation-delay:<?= $m['delay'] ?>s">
            <div class="card-glow bg-gradient-to-br <?= $m['gradient'] ?> blur-sm"></div>
            <div class="card-bg-icon absolute -right-6 -bottom-6 text-<?= $m['color'] ?>-500 text-[9rem] transition-all duration-500">
                <i class="bi <?= $m['bgIcon'] ?>"></i>
            </div>
            <div class="card-icon relative w-16 h-16 bg-gradient-to-br <?= $m['gradient'] ?> text-white rounded-3xl flex items-center justify-center text-3xl mb-8 shadow-xl shadow-<?= $m['color'] ?>-200/50">
                <i class="bi <?= $m['icon'] ?>"></i>
            </div>
            <h3 class="relative text-lg font-black text-slate-800 mb-3"><?= $m['title'] ?></h3>
            <p class="relative text-xs text-slate-400 font-bold leading-relaxed"><?= $m['desc'] ?></p>
            <div class="card-arrow relative mt-6 flex items-center gap-2 text-<?= $m['color'] ?>-500 text-[10px] font-black uppercase tracking-widest">
                <?= $isLoggedIn ? 'เข้าสู่ระบบ' : 'เข้าสู่ระบบก่อน' ?> <i class="bi bi-arrow-right"></i>
            </div>
        </a>
        <?php endforeach; ?>
    </div>
</section>

<!-- Desktop Quick Access Bar -->
<?php if ($isLoggedIn && !empty($quickAccess)): ?>
<section class="hidden sm:block relative container mx-auto px-6 md:px-12 pb-24 max-w-5xl z-10">
    <div class="bg-white/70 backdrop-blur-xl rounded-[2.5rem] border border-slate-100 shadow-xl p-8">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h3 class="text-base font-black text-slate-800 flex items-center gap-2">
                    <i class="bi bi-lightning-charge-fill text-amber-500"></i>Quick Access
                </h3>
                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mt-1">ทางลัดสำหรับ <?= htmlspecialchars(strtoupper($userRole)) ?></p>
            </div>
            <div class="flex flex-wrap gap-3">
                <?php foreach ($quickAccess as $qa): ?>
                <a href="<?= htmlspecialchars($qa['url']) ?>" class="flex items-center gap-2 px-5 py-3 bg-<?= $qa['color'] ?>-50 text-<?= $qa['color'] ?>-600 rounded-2xl font-bold text-sm hover:bg-<?= $qa['color'] ?>-600 hover:text-white transition-all shadow-sm hover:shadow-lg hover:scale-105">
                    <i class="bi <?= $qa['icon'] ?>"></i><?= htmlspecialchars($qa['label']) ?>
                </a>
                <?php endforeach; ?>
                <a href="central_dashboard.php" class="flex items-center gap-2 px-5 py-3 bg-slate-50 text-slate-500 rounded-2xl font-bold text-sm hover:bg-slate-700 hover:text-white transition-all shadow-sm hover:shadow-lg hover:scale-105">
                    <i class="bi bi-grid-fill"></i>ทั้งหมด
                </a>
            </div>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- Desktop Footer -->
<footer class="hidden sm:block relative wave-divider border-t border-slate-100/50 pt-16 pb-10 bg-white/60 backdrop-blur-sm z-10">
    <div class="container mx-auto px-6 max-w-4xl">
        <div class="text-center mb-8">
            <div class="w-14 h-14 bg-gradient-to-br from-blue-600 to-indigo-700 rounded-2xl flex items-center justify-center text-white text-xl font-black italic mx-auto mb-4 shadow-xl shadow-blue-200/50 hover:rotate-12 transition-transform cursor-default">LLW</div>
            <p class="text-base font-black text-slate-700">โรงเรียนละลมวิทยา</p>
            <p class="text-[10px] font-black text-slate-300 uppercase tracking-[0.35em] mt-1">Lalom Wittaya School &bull; Powered by Advanced School Intelligence</p>
        </div>
        <div class="h-px bg-gradient-to-r from-transparent via-slate-200 to-transparent mb-8"></div>
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
        <div class="flex flex-col sm:flex-row items-center justify-between gap-3">
            <div class="flex gap-5 text-[9px] font-bold text-slate-300 uppercase tracking-widest">
                <a href="login.php" class="hover:text-blue-500 transition-colors">Admin Login</a>
                <span class="text-slate-200">|</span>
                <a href="#desktop-modules" class="hover:text-blue-500 transition-colors">ระบบทั้งหมด</a>
            </div>
            <span class="text-[9px] font-black text-slate-300 uppercase tracking-widest">&copy; <?= date('Y') ?> LLW System</span>
        </div>
    </div>
</footer>


<!-- ═══════════════════════════════ SCRIPTS ══════════════════════════════ -->
<script>
document.addEventListener('DOMContentLoaded', () => {

    /* ── Count-up animation (both layouts) ── */
    document.querySelectorAll('.counter').forEach(el => {
        const target = parseInt(el.dataset.target) || 0;
        if (target === 0) { el.textContent = '0'; return; }
        const duration = 1400, step = Math.max(1, Math.ceil(target / (duration / 16)));
        let current = 0;
        const timer = setInterval(() => {
            current = Math.min(current + step, target);
            el.textContent = current.toLocaleString();
            if (current >= target) clearInterval(timer);
        }, 16);
    });

    /* ── Desktop: Intersection Observer for slide-up cards ── */
    if (window.innerWidth >= 640) {
        const obs = new IntersectionObserver((entries) => {
            entries.forEach(e => { if (e.isIntersecting) { e.target.style.animationPlayState = 'running'; obs.unobserve(e.target); } });
        }, { threshold: 0.1 });
        document.querySelectorAll('.animate-slide-up').forEach(el => {
            el.style.animationPlayState = 'paused'; obs.observe(el);
        });
    }

    /* ── Mobile: Bottom nav active state via IntersectionObserver ── */
    if (window.innerWidth < 640) {
        const sections = { 'mob-home': 'home', 'mob-quick': 'quick', 'mob-systems': 'systems', 'mob-me': 'me' };
        const tabs = document.querySelectorAll('.bnav-tab');

        function setActive(tabId) {
            tabs.forEach(t => {
                const isActive = t.dataset.tab === tabId;
                t.classList.toggle('is-active', isActive);
                const icon = t.querySelector('i');
                const label = t.querySelector('span');
                if (icon)  icon.className  = icon.className.replace(/text-(blue-600|slate-400)/g, isActive ? 'text-blue-600' : 'text-slate-400');
                if (label) label.className = label.className.replace(/text-(blue-600|slate-400)/g, isActive ? 'text-blue-600' : 'text-slate-400');
            });
        }

        const secObs = new IntersectionObserver((entries) => {
            entries.forEach(e => { if (e.isIntersecting && e.intersectionRatio > 0.3) setActive(sections[e.target.id]); });
        }, { threshold: [0.3, 0.6], rootMargin: '-10% 0px -50% 0px' });

        Object.keys(sections).forEach(id => { const el = document.getElementById(id); if (el) secObs.observe(el); });

        /* Tap on tab → also set active immediately */
        tabs.forEach(t => {
            t.addEventListener('click', () => setActive(t.dataset.tab));
        });
    }
});
</script>

</body>
</html>
