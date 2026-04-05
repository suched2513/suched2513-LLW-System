<?php
/**
 * sidebar.php — Premium Navigation for LLW Unified System
 */
$current_page = basename($_SERVER['PHP_SELF']);
$current_dir = basename(dirname($_SERVER['PHP_SELF']));

// Determine active system for highlighting
$base_path = '/llw';
$activeSystem = $activeSystem ?? 'portal';
if ($current_dir === 'attendance_system') $activeSystem = 'attendance';
if ($current_dir === 'chromebook')      $activeSystem = 'chromebook';
if ($current_page === 'leave_system.php') $activeSystem = 'leave';
if ($current_dir === 'user' || $current_dir === 'admin' || $current_page === 'index_wfh.php') $activeSystem = 'wfh';
if ($current_page === 'central_dashboard.php' || $current_page === 'index.php') $activeSystem = 'portal';

// User context
$userName = $_SESSION['firstname'] ?? ($_SESSION['teacher_name'] ?? 'User');
$userRole = $_SESSION['llw_role'] ?? 'staff';
$roleName = [
    'super_admin' => 'Super Admin',
    'wfh_admin' => 'WFH Admin',
    'wfh_staff' => 'Personnel',
    'cb_admin' => 'Device Manager',
    'att_teacher' => 'Academic Staff'
][$userRole] ?? 'Staff Member';
?>

<aside id="sidebar" class="w-72 bg-white flex-shrink-0 border-r border-slate-200/60 z-50 flex flex-col h-full transition-all duration-300 transform no-print fixed lg:static -translate-x-full lg:translate-x-0">
    
    <!-- Premium Brand Section -->
    <div class="px-8 py-12 flex items-center gap-4">
        <div class="w-12 h-12 bg-blue-600 rounded-[18px] shadow-2xl shadow-blue-100 flex items-center justify-center text-white text-xl font-black italic transform transition-transform hover:rotate-6">
            LLW
        </div>
        <div class="flex flex-col">
            <span class="text-xl font-black text-slate-800 tracking-tight leading-none">Platinum</span>
            <span class="text-[10px] font-black text-blue-500 uppercase tracking-[0.2em] mt-1.5 opacity-70">School AI Suite</span>
        </div>
    </div>

    <!-- Enhanced Navigation -->
    <nav class="flex-1 px-5 py-2 space-y-1.5 overflow-y-auto">
        
        <!-- Section: Discovery -->
        <div class="pb-6">
            <p class="text-[10px] font-black text-slate-400 uppercase tracking-[0.2em] pl-4 mb-4">Main Portal</p>
            <a href="<?= $base_path ?>/index.php" class="flex items-center gap-4 px-5 py-4 rounded-2xl text-[13px] font-bold transition-all <?= $activeSystem === 'portal' ? 'bg-blue-600 text-white shadow-xl shadow-blue-100' : 'text-slate-500 hover:bg-slate-50 hover:pl-6' ?>">
                <i class="bi bi-grid-fill text-lg"></i> แดชบอร์ดกลาง
            </a>
        </div>

        <!-- Section: Academic Support -->
        <div class="pb-6">
            <p class="text-[10px] font-black text-slate-400 uppercase tracking-[0.2em] pl-4 mb-4">Academic & Management</p>
            <a href="<?= $base_path ?>/attendance_system/dashboard.php" class="flex items-center gap-4 px-5 py-4 rounded-2xl text-[13px] font-bold transition-all <?= $activeSystem === 'attendance' ? 'bg-indigo-600 text-white shadow-xl shadow-indigo-100' : 'text-slate-500 hover:bg-slate-50 hover:pl-6' ?>">
                <i class="bi bi-person-check-fill text-lg"></i> ระบบเช็คชื่อนักเรียน
            </a>
            <a href="<?= $base_path ?>/chromebook/index.php" class="flex items-center gap-4 px-5 py-4 rounded-2xl text-[13px] font-bold transition-all <?= $activeSystem === 'chromebook' ? 'bg-cyan-600 text-white shadow-xl shadow-cyan-100' : 'text-slate-500 hover:bg-slate-50 hover:pl-6' ?>">
                <i class="bi bi-laptop text-lg"></i> จัดการ Chromebook
            </a>
        </div>

        <!-- Section: Human Resources -->
        <div class="pb-6">
            <p class="text-[10px] font-black text-slate-400 uppercase tracking-[0.2em] pl-4 mb-4">Staff & Attendance</p>
            <a href="<?= $base_path ?>/index_wfh.php" class="flex items-center gap-4 px-5 py-4 rounded-2xl text-[13px] font-bold transition-all <?= $activeSystem === 'wfh' ? 'bg-emerald-600 text-white shadow-xl shadow-emerald-100' : 'text-slate-500 hover:bg-slate-50 hover:pl-6' ?>">
                <i class="bi bi-geo-alt-fill text-lg"></i> ลงเวลาปฏิบัติงาน
            </a>
            <a href="<?= $base_path ?>/leave_system.php" class="flex items-center gap-4 px-5 py-4 rounded-2xl text-[13px] font-bold transition-all <?= $activeSystem === 'leave' ? 'bg-rose-600 text-white shadow-xl shadow-rose-100' : 'text-slate-500 hover:bg-slate-50 hover:pl-6' ?>">
                <i class="bi bi-person-walking text-lg"></i> ขอออกนอกบริเวณ
            </a>
        </div>

    </nav>

    <!-- Refined Profile Section -->
    <div class="p-6">
        <div class="p-5 bg-slate-50/50 rounded-3xl border border-slate-100 hover:bg-white hover:shadow-xl hover:shadow-slate-100 transition-all duration-500 group">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 rounded-[14px] bg-blue-600 text-white flex items-center justify-center font-black text-lg shadow-lg group-hover:rotate-6 transition-transform">
                    <?= mb_substr($userName, 0, 1) ?>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-[13px] font-black text-slate-800 truncate"><?= htmlspecialchars($userName) ?></p>
                    <p class="text-[10px] font-bold text-blue-500 uppercase tracking-widest truncate"><?= $roleName ?></p>
                </div>
            </div>
            <div class="mt-4 pt-4 border-t border-slate-200/50 flex justify-between items-center">
                <a href="<?= $base_path ?>/logout.php" class="flex items-center gap-2 text-rose-500 font-black text-[10px] uppercase tracking-widest hover:text-rose-700 transition-colors">
                    <i class="bi bi-power text-md"></i> Sign Out
                </a>
                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
            </div>
        </div>
    </div>
</aside>

<!-- Sophisticated Overlay for mobile -->
<div id="sidebar-overlay" class="fixed inset-0 bg-slate-900/40 backdrop-blur-md z-40 hidden lg:hidden transition-all duration-500"></div>

<script>
    function toggleSidebar() {
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebar-overlay');
        sidebar.classList.toggle('-translate-x-full');
        overlay.classList.toggle('hidden');
    }
    document.getElementById('sidebar-overlay')?.addEventListener('click', toggleSidebar);
</script>
