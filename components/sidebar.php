<?php
$current_page = basename($_SERVER['PHP_SELF']);
$current_dir = basename(dirname($_SERVER['PHP_SELF']));

// Determine active system
$activeSystem = $activeSystem ?? 'portal';
if ($current_dir === 'attendance_system') $activeSystem = 'attendance';
if ($current_dir === 'chromebook')      $activeSystem = 'chromebook';
if ($current_page === 'leave_system.php') $activeSystem = 'leave';
if ($current_dir === 'user' || $current_dir === 'admin' || $current_page === 'index_wfh.php') $activeSystem = 'wfh';
if ($current_page === 'central_dashboard.php') $activeSystem = 'admin';
if ($current_page === 'index.php' && $current_dir === 'llw') $activeSystem = 'portal';

// User info
$userName = $_SESSION['firstname'] ?? ($_SESSION['teacher_name'] ?? 'User');
$userRole = $_SESSION['llw_role'] ?? 'staff';
$isSuperAdmin = ($userRole === 'super_admin');
?>

<aside id="sidebar" class="w-72 bg-white flex-shrink-0 border-r border-slate-200 z-50 flex flex-col h-full transition-all duration-300 transform no-print fixed lg:static -translate-x-full lg:translate-x-0">
    <!-- Brand -->
    <div class="px-8 py-10 flex flex-col items-center">
        <div class="w-16 h-16 bg-blue-600 rounded-3xl shadow-xl shadow-blue-200 flex items-center justify-center mb-6 text-white text-3xl font-black">
            LLW
        </div>
        <h2 class="text-xl font-bold text-slate-800 tracking-tight">LLW Platform</h2>
        <p class="text-[10px] font-black text-blue-500 uppercase tracking-[0.2em] mt-1">Management Suite</p>
    </div>

    <!-- Navigation -->
    <nav class="flex-1 px-4 py-2 space-y-1 overflow-y-auto">
        
        <!-- Portal / Global -->
        <div class="px-4 pb-2">
            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest pl-2 mb-2">Main Portal</p>
            <a href="/llw/central_dashboard.php" class="flex items-center gap-3 px-4 py-3 rounded-2xl text-sm font-bold transition-all <?= $activeSystem === 'admin' ? 'sidebar-item-active' : 'text-slate-500 hover:bg-slate-50' ?>">
                <i class="bi bi-grid-fill text-lg"></i> แดชบอร์ดกลาง
            </a>
        </div>

        <!-- Attendance System -->
        <div class="px-4 py-2">
            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest pl-2 mb-2">Academic</p>
            <a href="/llw/attendance_system/dashboard.php" class="flex items-center gap-3 px-4 py-3 rounded-2xl text-sm font-bold transition-all <?= $activeSystem === 'attendance' ? 'sidebar-item-active' : 'text-slate-500 hover:bg-slate-50' ?>">
                <i class="bi bi-person-check-fill text-lg"></i> เช็คชื่อนักเรียน
            </a>
        </div>

        <!-- Chromebook System -->
        <div class="px-4 py-2">
            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest pl-2 mb-2">Inventory</p>
            <a href="/llw/chromebook/index.php" class="flex items-center gap-3 px-4 py-3 rounded-2xl text-sm font-bold transition-all <?= $activeSystem === 'chromebook' ? 'sidebar-item-active' : 'text-slate-500 hover:bg-slate-50' ?>">
                <i class="bi bi-laptop text-lg"></i> จัดการ Chromebook
            </a>
        </div>

        <!-- WFH System -->
        <div class="px-4 py-2">
            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest pl-2 mb-2">Staff</p>
            <a href="/llw/index_wfh.php" class="flex items-center gap-3 px-4 py-3 rounded-2xl text-sm font-bold transition-all <?= $activeSystem === 'wfh' ? 'sidebar-item-active' : 'text-slate-500 hover:bg-slate-50' ?>">
                <i class="bi bi-house-door-fill text-lg"></i> ลงเวลา (WFH)
            </a>
            <a href="/llw/leave_system.php" class="flex items-center gap-3 px-4 py-3 rounded-2xl text-sm font-bold transition-all <?= $activeSystem === 'leave' ? 'sidebar-item-active' : 'text-slate-500 hover:bg-slate-50' ?>">
                <i class="bi bi-person-walking text-lg"></i> ขอออกนอกบริเวณ
            </a>
        </div>

    </nav>

    <!-- Footer / Profile -->
    <div class="p-4 border-t border-slate-50 bg-slate-50/50">
        <div class="p-4 bg-white rounded-3xl border border-slate-100 shadow-sm">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-2xl bg-blue-100 flex items-center justify-center text-blue-600 font-bold text-lg">
                    <?= mb_substr($userName, 0, 1) ?>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-xs font-bold text-slate-800 truncate"><?= htmlspecialchars($userName) ?></p>
                    <p class="text-[9px] font-black text-blue-400 uppercase tracking-wider truncate"><?= strtoupper($userRole) ?></p>
                </div>
                <a href="/llw/logout.php" class="p-2 text-rose-500 hover:bg-rose-50 rounded-xl transition-all" title="ออกจากระบบ">
                    <i class="bi bi-power"></i>
                </a>
            </div>
        </div>
    </div>
</aside>

<!-- Overlay for mobile -->
<div id="sidebar-overlay" class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm z-40 hidden lg:hidden"></div>

<script>
    // Sidebar toggle for mobile
    function toggleSidebar() {
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebar-overlay');
        sidebar.classList.toggle('-translate-x-full');
        overlay.classList.toggle('hidden');
    }
    
    document.getElementById('sidebar-overlay')?.addEventListener('click', toggleSidebar);
</script>
