<?php
/**
 * layout_start.php — Premium Layout Wrapper for LLW System
 */
require_once __DIR__ . '/header.php';
require_once __DIR__ . '/sidebar.php';

// Breadcrumb data
$breadcrumbs = [];
$breadcrumbs[] = ['label' => 'LLW', 'url' => $base_path . '/index.php', 'icon' => 'bi-house-fill'];
$systemLabels = [
    'attendance' => 'เช็คชื่อนักเ���ียน',
    'chromebook' => 'Chromebook',
    'wfh'        => 'ลงเวลาปฏิบัติงาน',
    'leave'      => 'ขออนุญาตออกนอก',
    'portal'     => 'แดชบอร์ดกลาง',
    'budget'     => 'ระบบงบประมาณ SBMS',
];
if (isset($activeSystem) && $activeSystem !== 'portal') {
    $breadcrumbs[] = ['label' => $systemLabels[$activeSystem] ?? $activeSystem];
}
if (isset($pageTitle)) {
    $breadcrumbs[] = ['label' => $pageTitle];
}
?>

<style>
    .mesh-bg {
        background-color: #f8fafc;
        background-image:
            radial-gradient(at 0% 0%, rgba(79, 70, 229, 0.04) 0, transparent 50%),
            radial-gradient(at 50% 0%, rgba(99, 102, 241, 0.04) 0, transparent 50%),
            radial-gradient(at 100% 0%, rgba(59, 130, 246, 0.04) 0, transparent 50%);
    }
    .glass-header {
        background: rgba(255, 255, 255, 0.7);
        backdrop-filter: blur(12px);
        -webkit-backdrop-filter: blur(12px);
        border-bottom: 1px solid rgba(241, 245, 249, 0.8);
    }
    .no-scrollbar::-webkit-scrollbar { display: none; }
    .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
</style>

<main class="flex-1 flex flex-col h-full overflow-hidden mesh-bg relative">

    <!-- Top Header -->
    <header class="h-16 sm:h-20 flex-shrink-0 flex items-center justify-between px-4 sm:px-6 lg:px-10 glass-header no-print z-40 sticky top-0">
        <div class="flex items-center gap-3 sm:gap-4 lg:gap-6 min-w-0">
            <button onclick="toggleSidebar()" class="lg:hidden p-2 sm:p-3 text-slate-500 hover:bg-slate-100/80 rounded-xl sm:rounded-2xl transition-all flex-shrink-0">
                <i class="bi bi-list text-xl sm:text-2xl"></i>
            </button>
            <div class="flex flex-col min-w-0">
                <!-- Breadcrumb (hidden on very small screens) -->
                <nav class="hidden sm:flex items-center gap-1.5 text-[9px] sm:text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1 overflow-hidden">
                    <?php foreach ($breadcrumbs as $i => $bc): ?>
                        <?php if ($i > 0): ?>
                            <i class="bi bi-chevron-right text-[8px] text-slate-300 flex-shrink-0"></i>
                        <?php endif; ?>
                        <?php if (isset($bc['url']) && $i < count($breadcrumbs) - 1): ?>
                            <a href="<?= $bc['url'] ?>" class="hover:text-indigo-600 transition-colors flex items-center gap-1 truncate">
                                <?php if (isset($bc['icon'])): ?><i class="bi <?= $bc['icon'] ?> text-xs"></i><?php endif; ?>
                                <?= htmlspecialchars($bc['label']) ?>
                            </a>
                        <?php else: ?>
                            <span class="text-slate-500 truncate"><?= htmlspecialchars($bc['label']) ?></span>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </nav>
                <h1 class="text-base sm:text-lg lg:text-xl font-black text-slate-800 tracking-tight leading-tight truncate"><?= htmlspecialchars($pageTitle ?? 'LLW Management') ?></h1>
            </div>
        </div>

        <div class="flex items-center gap-2 sm:gap-4 lg:gap-6 flex-shrink-0">
            <!-- Calendar Widget -->
            <div class="hidden lg:flex items-center gap-3 px-4 sm:px-5 py-2 sm:py-2.5 bg-white/50 rounded-xl sm:rounded-2xl border border-slate-200/50 text-slate-500 text-[11px] sm:text-xs font-bold transition-all hover:bg-white hover:shadow-xl hover:shadow-indigo-100/40 hover:-translate-y-0.5">
                <i class="bi bi-calendar3 text-indigo-500"></i>
                <span><?= date('D, d M Y') ?></span>
            </div>

            <!-- Quick Tools -->
            <div class="flex items-center gap-1 sm:gap-2">
                <button class="w-9 h-9 sm:w-11 sm:h-11 flex items-center justify-center text-slate-400 hover:text-indigo-600 hover:bg-indigo-50 rounded-lg sm:rounded-xl transition-all border border-transparent hover:border-indigo-100 relative">
                    <i class="bi bi-bell text-lg sm:text-xl"></i>
                    <span class="absolute top-2 right-2 sm:top-3 sm:right-3 w-2 h-2 bg-rose-500 rounded-full animate-ping"></span>
                    <span class="absolute top-2 right-2 sm:top-3 sm:right-3 w-2 h-2 bg-rose-500 rounded-full"></span>
                </button>
                <button class="hidden sm:flex w-9 h-9 sm:w-11 sm:h-11 items-center justify-center text-slate-400 hover:text-indigo-600 hover:bg-indigo-50 rounded-lg sm:rounded-xl transition-all border border-transparent hover:border-indigo-100">
                    <i class="bi bi-gear text-lg sm:text-xl"></i>
                </button>
            </div>

            <div class="hidden sm:block w-px h-8 bg-slate-200 mx-1 sm:mx-2"></div>

            <!-- Profile Thumbnail -->
            <div class="hidden sm:flex items-center gap-3">
                <div class="w-9 h-9 sm:w-10 sm:h-10 rounded-xl sm:rounded-2xl bg-gradient-to-br from-indigo-600 to-indigo-700 flex items-center justify-center text-white text-sm sm:text-md font-black shadow-lg shadow-indigo-200/50">
                    <?= mb_substr($_SESSION['firstname'] ?? 'U', 0, 1) ?>
                </div>
            </div>
        </div>
    </header>

    <!-- Scrollable Content Area -->
    <div class="flex-1 overflow-y-auto px-4 sm:px-6 lg:px-10 py-6 sm:py-8 lg:py-12 scroll-smooth">
        <!-- Page Content Starts Here -->
