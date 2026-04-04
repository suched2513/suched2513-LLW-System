<?php
/**
 * layout_start.php - เริ่มต้น Layout ของหน้าเว็บ
 */
require_once __DIR__ . '/header.php';
require_once __DIR__ . '/sidebar.php';
?>

<!-- Main Content Area -->
<main id="main-content" class="flex-1 min-w-0 h-full overflow-y-auto bg-slate-50 relative flex flex-col pt-4 sm:pt-6 px-4 sm:px-8 pb-12 transition-all duration-300">
    <!-- Navbar header (for mobile/additional info) -->
    <header class="flex items-center justify-between mb-8 no-print min-h-[4rem]">
        <div class="flex items-center gap-4">
            <!-- Mobile Menu Toggle Button (For future mobile enhancement) -->
            <button id="sidebar-toggle" class="lg:hidden p-2 text-gray-500 hover:bg-white rounded-xl shadow-sm border border-slate-200">
                <i class="bi bi-list text-xl"></i>
            </button>
            <div>
                <h2 class="text-2xl font-bold text-gray-800 leading-tight"><?= $pageTitle ?? 'ระบบเช็คชื่อ' ?></h2>
                <p class="text-sm text-gray-500 font-medium">โรงเรียนละลมวิทยา — <?= $pageSubtitle ?? date('d F Y') ?></p>
            </div>
        </div>
        
        <div class="flex items-center gap-3">
            <div class="hidden sm:flex flex-col text-right mr-3">
                <span class="text-xs font-bold text-gray-800"><?= htmlspecialchars($_SESSION['teacher_name']) ?></span>
                <span class="text-[10px] text-gray-400 font-medium uppercase tracking-wider"><?= ($_SESSION['llw_role'] ?? 'att_teacher') === 'super_admin' ? 'Super Admin' : 'Teacher' ?></span>
            </div>
            <a href="logout.php" class="p-2.5 text-red-500 bg-red-50 hover:bg-red-100 rounded-xl transition shadow-sm border border-red-100">
                <i class="bi bi-box-arrow-right text-lg"></i>
            </a>
        </div>
    </header>

    <!-- CONTENT STARTS HERE -->
