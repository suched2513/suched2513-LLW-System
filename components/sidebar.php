<?php
/**
 * sidebar.php — Premium Navigation with Sub-menus per Module
 */
$current_page = basename($_SERVER['PHP_SELF']);
$current_dir  = basename(dirname($_SERVER['PHP_SELF']));

// Determine active system
$base_path = '/llw';
$activeSystem = $activeSystem ?? 'portal';
if ($current_dir === 'attendance_system') $activeSystem = 'attendance';
if ($current_dir === 'chromebook')        $activeSystem = 'chromebook';
if ($current_page === 'leave_system.php') $activeSystem = 'leave';
if ($current_dir === 'user' || $current_dir === 'admin' || $current_page === 'index_wfh.php') $activeSystem = 'wfh';
if ($current_page === 'central_dashboard.php' || $current_page === 'index.php') $activeSystem = 'portal';

// User context
$userName = $_SESSION['firstname'] ?? ($_SESSION['teacher_name'] ?? 'User');
$userRole = $_SESSION['llw_role'] ?? 'staff';
$roleName = [
    'super_admin' => 'Super Admin',
    'wfh_admin'   => 'WFH Admin',
    'wfh_staff'   => 'Personnel',
    'cb_admin'    => 'Device Manager',
    'att_teacher' => 'Academic Staff'
][$userRole] ?? 'Staff Member';

// Sub-menu definitions per module
$subMenus = [
    'attendance' => [
        ['icon' => 'bi-speedometer2',  'label' => 'Dashboard',    'url' => $base_path . '/attendance_system/dashboard.php'],
        ['icon' => 'bi-check2-square', 'label' => 'เช็คชื่อ',       'url' => $base_path . '/attendance_system/attendance.php'],
        ['icon' => 'bi-bar-chart',     'label' => 'รายงาน',        'url' => $base_path . '/attendance_system/report.php'],
        ['icon' => 'bi-people',        'label' => 'จัดการข้อมูล',    'url' => $base_path . '/attendance_system/admin.php'],
    ],
    'chromebook' => [
        ['icon' => 'bi-speedometer2',     'label' => 'Dashboard',  'url' => $base_path . '/chromebook/index.php'],
        ['icon' => 'bi-arrow-left-right', 'label' => 'ยืม-คืน',     'url' => $base_path . '/chromebook/dashboard.php'],
    ],
    'wfh' => [
        ['icon' => 'bi-speedometer2', 'label' => 'Dashboard',     'url' => $base_path . '/admin/dashboard.php',  'roles' => ['super_admin','wfh_admin']],
        ['icon' => 'bi-clock-history', 'label' => 'ลงเวลา',       'url' => $base_path . '/user/dashboard.php'],
        ['icon' => 'bi-bar-chart',    'label' => 'รายงาน',        'url' => $base_path . '/admin/reports.php',    'roles' => ['super_admin','wfh_admin']],
        ['icon' => 'bi-people',       'label' => 'จัดการบุคลากร',  'url' => $base_path . '/admin/manage_users.php', 'roles' => ['super_admin','wfh_admin']],
        ['icon' => 'bi-gear',         'label' => 'ตั้งค่า',        'url' => $base_path . '/admin/settings.php',  'roles' => ['super_admin','wfh_admin']],
    ],
    'leave' => [
        ['icon' => 'bi-list-check', 'label' => 'รายการคำขอ', 'url' => $base_path . '/leave_system.php'],
    ],
];
?>

<style>
    .sub-menu { max-height: 0; overflow: hidden; transition: max-height 0.4s cubic-bezier(0.4, 0, 0.2, 1); }
    .sub-menu.open { max-height: 300px; }
    .sub-item { transition: all 0.2s ease; }
    .sub-item:hover { padding-left: 3.5rem; }
    .nav-link-active { position: relative; }
    .nav-link-active::before {
        content: ''; position: absolute; left: 0; top: 50%; transform: translateY(-50%);
        width: 4px; height: 60%; border-radius: 0 4px 4px 0; background: currentColor; opacity: 0.5;
    }
</style>

<aside id="sidebar" class="w-72 bg-white flex-shrink-0 border-r border-slate-200/60 z-50 flex flex-col h-full transition-all duration-300 transform no-print fixed lg:static -translate-x-full lg:translate-x-0 shadow-2xl lg:shadow-none">

    <!-- Brand -->
    <div class="px-6 sm:px-8 py-8 sm:py-10 flex items-center gap-4">
        <div class="w-11 h-11 sm:w-12 sm:h-12 bg-gradient-to-br from-blue-600 to-indigo-700 rounded-[16px] sm:rounded-[18px] shadow-xl shadow-blue-200/50 flex items-center justify-center text-white text-lg sm:text-xl font-black italic hover:rotate-6 transition-transform">
            LLW
        </div>
        <div class="flex flex-col">
            <span class="text-lg sm:text-xl font-black text-slate-800 tracking-tight leading-none">Platinum</span>
            <span class="text-[9px] sm:text-[10px] font-black text-blue-500 uppercase tracking-[0.2em] mt-1 opacity-70">School AI Suite</span>
        </div>
    </div>

    <!-- Navigation -->
    <nav class="flex-1 px-4 sm:px-5 py-2 space-y-1 overflow-y-auto">

        <!-- Main Portal -->
        <div class="pb-4 sm:pb-6">
            <p class="text-[9px] sm:text-[10px] font-black text-slate-300 uppercase tracking-[0.2em] pl-4 mb-3 sm:mb-4">Main Portal</p>
            <a href="<?= $base_path ?>/index.php" class="flex items-center gap-3 sm:gap-4 px-4 sm:px-5 py-3 sm:py-3.5 rounded-xl sm:rounded-2xl text-xs sm:text-[13px] font-bold transition-all <?= $activeSystem === 'portal' ? 'bg-gradient-to-r from-blue-600 to-indigo-600 text-white shadow-lg shadow-blue-200/50' : 'text-slate-500 hover:bg-slate-50 hover:pl-6' ?>">
                <i class="bi bi-grid-fill text-base sm:text-lg"></i> แดชบอร์ดกลาง
            </a>
        </div>

        <!-- Academic & Management -->
        <div class="pb-4 sm:pb-6">
            <p class="text-[9px] sm:text-[10px] font-black text-slate-300 uppercase tracking-[0.2em] pl-4 mb-3 sm:mb-4">Academic & Management</p>

            <!-- Attendance -->
            <a href="<?= $base_path ?>/attendance_system/dashboard.php" class="flex items-center gap-3 sm:gap-4 px-4 sm:px-5 py-3 sm:py-3.5 rounded-xl sm:rounded-2xl text-xs sm:text-[13px] font-bold transition-all <?= $activeSystem === 'attendance' ? 'bg-gradient-to-r from-indigo-600 to-blue-600 text-white shadow-lg shadow-indigo-200/50' : 'text-slate-500 hover:bg-slate-50 hover:pl-6' ?>">
                <i class="bi bi-person-check-fill text-base sm:text-lg"></i> ระบบเช็คชื่อ
                <?php if ($activeSystem === 'attendance'): ?>
                <i class="bi bi-chevron-down ml-auto text-xs opacity-60"></i>
                <?php endif; ?>
            </a>
            <div class="sub-menu <?= $activeSystem === 'attendance' ? 'open' : '' ?> ml-4 sm:ml-6 mt-1 space-y-0.5">
                <?php foreach ($subMenus['attendance'] as $sub): ?>
                <a href="<?= $sub['url'] ?>" class="sub-item flex items-center gap-3 px-4 py-2 sm:py-2.5 rounded-lg sm:rounded-xl text-[11px] sm:text-xs font-bold <?= $current_page === basename($sub['url']) ? 'text-indigo-600 bg-indigo-50' : 'text-slate-400 hover:text-slate-600 hover:bg-slate-50' ?>">
                    <i class="bi <?= $sub['icon'] ?> text-sm"></i> <?= $sub['label'] ?>
                </a>
                <?php endforeach; ?>
            </div>

            <!-- Chromebook -->
            <a href="<?= $base_path ?>/chromebook/index.php" class="flex items-center gap-3 sm:gap-4 px-4 sm:px-5 py-3 sm:py-3.5 rounded-xl sm:rounded-2xl text-xs sm:text-[13px] font-bold transition-all mt-1 <?= $activeSystem === 'chromebook' ? 'bg-gradient-to-r from-cyan-600 to-blue-600 text-white shadow-lg shadow-cyan-200/50' : 'text-slate-500 hover:bg-slate-50 hover:pl-6' ?>">
                <i class="bi bi-laptop text-base sm:text-lg"></i> จัดการ Chromebook
                <?php if ($activeSystem === 'chromebook'): ?>
                <i class="bi bi-chevron-down ml-auto text-xs opacity-60"></i>
                <?php endif; ?>
            </a>
            <div class="sub-menu <?= $activeSystem === 'chromebook' ? 'open' : '' ?> ml-4 sm:ml-6 mt-1 space-y-0.5">
                <?php foreach ($subMenus['chromebook'] as $sub): ?>
                <a href="<?= $sub['url'] ?>" class="sub-item flex items-center gap-3 px-4 py-2 sm:py-2.5 rounded-lg sm:rounded-xl text-[11px] sm:text-xs font-bold <?= $current_page === basename($sub['url']) ? 'text-cyan-600 bg-cyan-50' : 'text-slate-400 hover:text-slate-600 hover:bg-slate-50' ?>">
                    <i class="bi <?= $sub['icon'] ?> text-sm"></i> <?= $sub['label'] ?>
                </a>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Staff & HR -->
        <div class="pb-4 sm:pb-6">
            <p class="text-[9px] sm:text-[10px] font-black text-slate-300 uppercase tracking-[0.2em] pl-4 mb-3 sm:mb-4">Staff & Attendance</p>

            <!-- WFH -->
            <a href="<?= $base_path ?>/index_wfh.php" class="flex items-center gap-3 sm:gap-4 px-4 sm:px-5 py-3 sm:py-3.5 rounded-xl sm:rounded-2xl text-xs sm:text-[13px] font-bold transition-all <?= $activeSystem === 'wfh' ? 'bg-gradient-to-r from-emerald-600 to-teal-600 text-white shadow-lg shadow-emerald-200/50' : 'text-slate-500 hover:bg-slate-50 hover:pl-6' ?>">
                <i class="bi bi-geo-alt-fill text-base sm:text-lg"></i> ลงเวลาปฏิบัติงาน
                <?php if ($activeSystem === 'wfh'): ?>
                <i class="bi bi-chevron-down ml-auto text-xs opacity-60"></i>
                <?php endif; ?>
            </a>
            <div class="sub-menu <?= $activeSystem === 'wfh' ? 'open' : '' ?> ml-4 sm:ml-6 mt-1 space-y-0.5">
                <?php foreach ($subMenus['wfh'] as $sub):
                    // ตรวจสอบ role access
                    if (isset($sub['roles']) && !in_array($userRole, $sub['roles'])) continue;
                ?>
                <a href="<?= $sub['url'] ?>" class="sub-item flex items-center gap-3 px-4 py-2 sm:py-2.5 rounded-lg sm:rounded-xl text-[11px] sm:text-xs font-bold <?= $current_page === basename($sub['url']) ? 'text-emerald-600 bg-emerald-50' : 'text-slate-400 hover:text-slate-600 hover:bg-slate-50' ?>">
                    <i class="bi <?= $sub['icon'] ?> text-sm"></i> <?= $sub['label'] ?>
                </a>
                <?php endforeach; ?>
            </div>

            <!-- Leave -->
            <a href="<?= $base_path ?>/leave_system.php" class="flex items-center gap-3 sm:gap-4 px-4 sm:px-5 py-3 sm:py-3.5 rounded-xl sm:rounded-2xl text-xs sm:text-[13px] font-bold transition-all mt-1 <?= $activeSystem === 'leave' ? 'bg-gradient-to-r from-rose-600 to-pink-600 text-white shadow-lg shadow-rose-200/50' : 'text-slate-500 hover:bg-slate-50 hover:pl-6' ?>">
                <i class="bi bi-person-walking text-base sm:text-lg"></i> ขอออกนอกบริเวณ
            </a>
        </div>

    </nav>

    <!-- Profile -->
    <div class="p-4 sm:p-6">
        <div class="p-4 sm:p-5 bg-gradient-to-br from-slate-50 to-blue-50/30 rounded-2xl sm:rounded-3xl border border-slate-100 hover:shadow-xl hover:shadow-blue-100/30 transition-all duration-500 group">
            <div class="flex items-center gap-3 sm:gap-4">
                <div class="w-10 h-10 sm:w-12 sm:h-12 rounded-xl sm:rounded-[14px] bg-gradient-to-br from-blue-600 to-indigo-600 text-white flex items-center justify-center font-black text-sm sm:text-lg shadow-lg shadow-blue-200/50 group-hover:rotate-6 transition-transform">
                    <?= mb_substr($userName, 0, 1) ?>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-xs sm:text-[13px] font-black text-slate-800 truncate"><?= htmlspecialchars($userName) ?></p>
                    <p class="text-[9px] sm:text-[10px] font-bold text-blue-500 uppercase tracking-widest truncate"><?= $roleName ?></p>
                </div>
            </div>
            <div class="mt-3 sm:mt-4 pt-3 sm:pt-4 border-t border-slate-200/50 flex justify-between items-center">
                <a href="<?= $base_path ?>/logout.php" class="flex items-center gap-2 text-rose-500 font-black text-[9px] sm:text-[10px] uppercase tracking-widest hover:text-rose-700 transition-colors">
                    <i class="bi bi-power"></i> Sign Out
                </a>
                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
            </div>
        </div>
    </div>
</aside>

<!-- Mobile overlay -->
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
