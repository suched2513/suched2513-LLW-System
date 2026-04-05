<?php
/**
 * index.php — LLW Platinum Portal (Standalone Homepage)
 */
session_start();
require_once 'config.php';

$isLoggedIn = isset($_SESSION['user_id']);
$userRole   = $_SESSION['llw_role'] ?? 'guest';
$fullname   = $_SESSION['fullname'] ?? '';
$firstname  = $_SESSION['firstname'] ?? 'U';

// ถ้า logged in → ดึง Stats เบื้องต้นจาก DB
$stats = ['wfh_today' => 0, 'cb_borrowed' => 0, 'att_today' => 0, 'leave_pending' => 0];
if ($isLoggedIn) {
    try {
        require_once 'config/database.php';
        $pdo = getPdo();
        $today = date('Y-m-d');

        $r = $pdo->query("SELECT COUNT(*) FROM wfh_timelogs WHERE log_date='$today'")->fetchColumn();
        $stats['wfh_today'] = (int)$r;

        $r = $pdo->query("SELECT COUNT(*) FROM cb_borrow_logs WHERE status='Borrowed'")->fetchColumn();
        $stats['cb_borrowed'] = (int)$r;

        $r = $pdo->query("SELECT COUNT(DISTINCT student_id) FROM att_attendance WHERE date='$today'")->fetchColumn();
        $stats['att_today'] = (int)$r;

        $r = $pdo->query("SELECT COUNT(*) FROM leave_requests WHERE status_boss1=0")->fetchColumn();
        $stats['leave_pending'] = (int)$r;
    } catch (Exception $e) {
        // stats ไม่แสดงถ้า DB error
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
        .gradient-text { background: linear-gradient(135deg, #3b82f6, #6366f1); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; }
        .portal-card { transition: all 0.4s cubic-bezier(0.34, 1.56, 0.64, 1); }
        .portal-card:hover { transform: translateY(-12px) scale(1.02); }
        .portal-card:hover .card-icon { transform: rotate(8deg) scale(1.15); }
        .card-icon { transition: transform 0.4s cubic-bezier(0.34, 1.56, 0.64, 1); }
        .stat-pill { animation: fadeInUp 0.5s ease-out both; }
        @keyframes fadeInUp { from { opacity:0; transform:translateY(20px); } to { opacity:1; transform:translateY(0); } }
        .glow-blue { box-shadow: 0 0 80px rgba(59,130,246,0.15), 0 25px 50px rgba(99,102,241,0.08); }
        .bg-mesh {
            background-color: #f8fafc;
            background-image: radial-gradient(ellipse at 20% 30%, rgba(59,130,246,0.06) 0%, transparent 60%),
                              radial-gradient(ellipse at 80% 70%, rgba(99,102,241,0.06) 0%, transparent 60%),
                              radial-gradient(ellipse at 50% 10%, rgba(16,185,129,0.04) 0%, transparent 50%);
        }
        ::-webkit-scrollbar { width: 5px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
    </style>
</head>
<body class="bg-mesh text-slate-800 overflow-x-hidden">

<!-- ===== NAVIGATION ===== -->
<nav class="h-20 px-6 md:px-12 flex items-center justify-between sticky top-0 z-50 bg-white/80 backdrop-blur-xl border-b border-slate-200/60 shadow-sm shadow-slate-100/50">
    <a href="index.php" class="flex items-center gap-4">
        <div class="w-12 h-12 bg-gradient-to-br from-blue-600 to-indigo-700 rounded-2xl flex items-center justify-center text-white text-xl font-black shadow-lg shadow-blue-200 italic hover:rotate-6 transition-transform">LLW</div>
        <div>
            <div class="text-lg font-black text-slate-800 tracking-tight leading-none">Platinum Portal</div>
            <div class="text-[9px] font-black text-blue-400 uppercase tracking-[0.25em] mt-0.5">Unified School Ecosystem</div>
        </div>
    </a>
    <div class="flex items-center gap-3">
        <?php if ($isLoggedIn): ?>
            <div class="hidden sm:flex items-center gap-3 px-4 py-2.5 bg-slate-50 rounded-2xl border border-slate-100">
                <div class="w-9 h-9 rounded-xl bg-blue-100 text-blue-600 flex items-center justify-center font-black text-sm"><?= mb_substr($firstname, 0, 1) ?></div>
                <div>
                    <div class="text-sm font-black text-slate-700 leading-none"><?= htmlspecialchars($fullname) ?></div>
                    <div class="text-[9px] font-bold text-slate-400 uppercase tracking-widest mt-0.5"><?= strtoupper($userRole) ?></div>
                </div>
            </div>
            <a href="logout.php" id="logout-btn" class="w-11 h-11 flex items-center justify-center text-rose-500 bg-rose-50 rounded-2xl hover:bg-rose-500 hover:text-white transition-all shadow-sm">
                <i class="bi bi-power text-lg"></i>
            </a>
        <?php else: ?>
            <a href="login.php" id="login-btn" class="bg-blue-600 text-white px-8 py-3 rounded-2xl font-black text-sm shadow-lg shadow-blue-200 hover:bg-blue-700 hover:scale-105 transition-all flex items-center gap-2">
                เข้าสู่ระบบ <i class="bi bi-arrow-right-short text-xl"></i>
            </a>
        <?php endif; ?>
    </div>
</nav>

<!-- ===== HERO ===== -->
<section class="container mx-auto px-6 md:px-12 py-20 md:py-28 text-center max-w-5xl">
    <div class="inline-flex items-center gap-2 px-5 py-2 bg-blue-50 border border-blue-100 text-blue-600 rounded-full text-[10px] font-black uppercase tracking-[0.3em] mb-8 shadow-sm">
        <i class="bi bi-stars"></i>
        Welcome to Lalom Wittaya School
    </div>
    <h1 class="text-5xl md:text-7xl font-black tracking-tight mb-8 leading-tight">
        ระบบบริหารจัดการ<br>
        <span class="gradient-text">วิถีถิ่น ยุคใหม่ 2026</span>
    </h1>
    <p class="text-slate-400 text-lg font-medium max-w-2xl mx-auto leading-relaxed">
        แพลตฟอร์มศูนย์กลางเพื่อบุคลากรและนักเรียน ครอบคลุมทุกมิติการศึกษาด้วยเทคโนโลยียุคใหม่
    </p>

    <?php if (!$isLoggedIn): ?>
    <div class="mt-12 flex flex-col sm:flex-row gap-4 justify-center">
        <a href="login.php" class="px-10 py-4 bg-blue-600 text-white rounded-2xl font-black shadow-xl shadow-blue-200 hover:bg-blue-700 hover:scale-105 transition-all text-sm">
            <i class="bi bi-box-arrow-in-right mr-2"></i>เข้าสู่ระบบ Platinum
        </a>
        <a href="#modules" class="px-10 py-4 bg-white text-slate-600 border border-slate-200 rounded-2xl font-black hover:bg-slate-50 hover:scale-105 transition-all text-sm shadow-sm">
            <i class="bi bi-grid-1x2-fill mr-2"></i>ดูระบบทั้งหมด
        </a>
    </div>
    <?php endif; ?>
</section>

<!-- ===== LIVE STATS (แสดงเมื่อ Login) ===== -->
<?php if ($isLoggedIn): ?>
<section class="container mx-auto px-6 md:px-12 mb-16 max-w-5xl">
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="stat-pill bg-white rounded-3xl p-6 border border-slate-100 shadow-sm text-center" style="animation-delay:0s">
            <div class="text-3xl font-black text-emerald-500 mb-1"><?= $stats['wfh_today'] ?></div>
            <div class="text-[10px] font-black text-slate-400 uppercase tracking-widest">เข้างานวันนี้</div>
        </div>
        <div class="stat-pill bg-white rounded-3xl p-6 border border-slate-100 shadow-sm text-center" style="animation-delay:0.1s">
            <div class="text-3xl font-black text-indigo-500 mb-1"><?= $stats['cb_borrowed'] ?></div>
            <div class="text-[10px] font-black text-slate-400 uppercase tracking-widest">CB ยืมอยู่</div>
        </div>
        <div class="stat-pill bg-white rounded-3xl p-6 border border-slate-100 shadow-sm text-center" style="animation-delay:0.2s">
            <div class="text-3xl font-black text-blue-500 mb-1"><?= $stats['att_today'] ?></div>
            <div class="text-[10px] font-black text-slate-400 uppercase tracking-widest">เช็คชื่อวันนี้</div>
        </div>
        <div class="stat-pill bg-white rounded-3xl p-6 border border-slate-100 shadow-sm text-center" style="animation-delay:0.3s">
            <div class="text-3xl font-black text-amber-500 mb-1"><?= $stats['leave_pending'] ?></div>
            <div class="text-[10px] font-black text-slate-400 uppercase tracking-widest">รออนุมัติ</div>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- ===== MODULE CARDS ===== -->
<section id="modules" class="container mx-auto px-6 md:px-12 pb-24 max-w-5xl">
    <div class="text-center mb-12">
        <h2 class="text-2xl font-black text-slate-800 tracking-tight">ระบบทั้งหมด</h2>
        <p class="text-sm text-slate-400 font-bold mt-2 uppercase tracking-widest">All Management Modules</p>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">

        <!-- Module 1: Attendance -->
        <a href="attendance_system/dashboard.php" id="module-attendance" class="portal-card group bg-white p-8 rounded-[2.5rem] border border-slate-100 shadow-sm hover:shadow-2xl hover:shadow-blue-100 relative overflow-hidden">
            <div class="absolute -right-8 -bottom-8 text-blue-50 text-[9rem] group-hover:scale-125 transition-transform duration-500"><i class="bi bi-person-check"></i></div>
            <div class="card-icon w-16 h-16 bg-blue-50 text-blue-600 rounded-3xl flex items-center justify-center text-3xl mb-8 group-hover:bg-blue-600 group-hover:text-white shadow-lg">
                <i class="bi bi-person-check-fill"></i>
            </div>
            <h3 class="text-lg font-black text-slate-800 mb-3">เช็คชื่อนักเรียน</h3>
            <p class="text-xs text-slate-400 font-bold leading-relaxed">บันทึกเวลาเรียนรายวิชา ขาด ลา มา สาย แบบ Real-time</p>
            <div class="mt-6 flex items-center gap-2 text-blue-500 text-[10px] font-black uppercase tracking-widest opacity-0 group-hover:opacity-100 transition-opacity">
                เข้าสู่ระบบ <i class="bi bi-arrow-right"></i>
            </div>
        </a>

        <!-- Module 2: Chromebook -->
        <a href="chromebook/index.php" id="module-chromebook" class="portal-card group bg-white p-8 rounded-[2.5rem] border border-slate-100 shadow-sm hover:shadow-2xl hover:shadow-indigo-100 relative overflow-hidden">
            <div class="absolute -right-8 -bottom-8 text-indigo-50 text-[9rem] group-hover:scale-125 transition-transform duration-500"><i class="bi bi-laptop"></i></div>
            <div class="card-icon w-16 h-16 bg-indigo-50 text-indigo-600 rounded-3xl flex items-center justify-center text-3xl mb-8 group-hover:bg-indigo-600 group-hover:text-white shadow-lg">
                <i class="bi bi-laptop"></i>
            </div>
            <h3 class="text-lg font-black text-slate-800 mb-3">จัดการ Chromebook</h3>
            <p class="text-xs text-slate-400 font-bold leading-relaxed">ระบบยืม-คืนอุปกรณ์ดิจิทัล ตรวจสอบสถานะและคลังพัสดุ</p>
            <div class="mt-6 flex items-center gap-2 text-indigo-500 text-[10px] font-black uppercase tracking-widest opacity-0 group-hover:opacity-100 transition-opacity">
                เข้าสู่ระบบ <i class="bi bi-arrow-right"></i>
            </div>
        </a>

        <!-- Module 3: WFH -->
        <a href="index_wfh.php" id="module-wfh" class="portal-card group bg-white p-8 rounded-[2.5rem] border border-slate-100 shadow-sm hover:shadow-2xl hover:shadow-emerald-100 relative overflow-hidden">
            <div class="absolute -right-8 -bottom-8 text-emerald-50 text-[9rem] group-hover:scale-125 transition-transform duration-500"><i class="bi bi-geo-alt"></i></div>
            <div class="card-icon w-16 h-16 bg-emerald-50 text-emerald-600 rounded-3xl flex items-center justify-center text-3xl mb-8 group-hover:bg-emerald-600 group-hover:text-white shadow-lg">
                <i class="bi bi-person-badge-fill"></i>
            </div>
            <h3 class="text-lg font-black text-slate-800 mb-3">ลงเวลาบุคลากร</h3>
            <p class="text-xs text-slate-400 font-bold leading-relaxed">ระบบลงเวลาเข้า-ออกงานด้วย GPS ยืนยันตัวตนผ่านพิกัดโรงเรียน</p>
            <div class="mt-6 flex items-center gap-2 text-emerald-500 text-[10px] font-black uppercase tracking-widest opacity-0 group-hover:opacity-100 transition-opacity">
                เข้าสู่ระบบ <i class="bi bi-arrow-right"></i>
            </div>
        </a>

        <!-- Module 4: Leave -->
        <a href="leave_system.php" id="module-leave" class="portal-card group bg-white p-8 rounded-[2.5rem] border border-slate-100 shadow-sm hover:shadow-2xl hover:shadow-rose-100 relative overflow-hidden">
            <div class="absolute -right-8 -bottom-8 text-rose-50 text-[9rem] group-hover:scale-125 transition-transform duration-500"><i class="bi bi-door-open"></i></div>
            <div class="card-icon w-16 h-16 bg-rose-50 text-rose-600 rounded-3xl flex items-center justify-center text-3xl mb-8 group-hover:bg-rose-600 group-hover:text-white shadow-lg">
                <i class="bi bi-door-open-fill"></i>
            </div>
            <h3 class="text-lg font-black text-slate-800 mb-3">ขออนุญาตออกนอก</h3>
            <p class="text-xs text-slate-400 font-bold leading-relaxed">ระบบยื่นคำขอลาออนไลน์ พร้อมแจ้งเตือนผู้บริหารผ่าน Telegram</p>
            <div class="mt-6 flex items-center gap-2 text-rose-500 text-[10px] font-black uppercase tracking-widest opacity-0 group-hover:opacity-100 transition-opacity">
                เข้าสู่ระบบ <i class="bi bi-arrow-right"></i>
            </div>
        </a>

    </div>
</section>

<!-- ===== FOOTER ===== -->
<footer class="border-t border-slate-100 py-12 text-center bg-white/50">
    <div class="container mx-auto px-6">
        <div class="w-10 h-10 bg-gradient-to-br from-blue-600 to-indigo-700 rounded-xl flex items-center justify-center text-white text-sm font-black italic mx-auto mb-4">LLW</div>
        <p class="text-[10px] font-black text-slate-400 uppercase tracking-[0.4em] mb-3">© 2026 Lalom Wittaya School. All Rights Reserved.</p>
        <div class="flex justify-center gap-6 text-[9px] font-bold text-slate-300 uppercase tracking-widest">
            <a href="#" class="hover:text-blue-500 transition-colors">Privacy Policy</a>
            <span>•</span>
            <a href="login.php" class="hover:text-blue-500 transition-colors">Admin Login</a>
        </div>
    </div>
</footer>

</body>
</html>
