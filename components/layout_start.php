<?php
require_once 'header.php';
require_once 'sidebar.php';
?>

<main class="flex-1 flex flex-col h-full overflow-hidden bg-slate-50 relative">
    <!-- Top Header / Search / Notifications bar (Refined) -->
    <header class="h-20 flex-shrink-0 flex items-center justify-between px-10 border-b border-slate-200/50 glass no-print z-40 sticky top-0">
        <div class="flex items-center gap-4">
            <button onclick="toggleSidebar()" class="lg:hidden p-2 text-slate-500 hover:bg-slate-100 rounded-xl transition-all">
                <i class="bi bi-list text-2xl"></i>
            </button>
            <div class="flex flex-col">
                <h1 class="text-lg font-black text-slate-800 tracking-tight leading-tight"><?= $pageTitle ?? 'LLW Management' ?></h1>
                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest leading-tight mt-0.5"><?= $pageSubtitle ?? 'Lalom Wittaya School System' ?></p>
            </div>
        </div>

        <div class="flex items-center gap-6">
            <div class="hidden sm:flex items-center gap-3 px-4 py-2.5 bg-slate-100/50 rounded-2xl border border-slate-200/50 text-slate-400 text-xs font-bold transition-all hover:bg-white hover:shadow-sm">
                <i class="bi bi-calendar3"></i>
                <span><?= date('D, d M Y') ?></span>
            </div>
            
            <div class="flex items-center gap-2">
                <a href="#" class="p-2.5 text-slate-400 hover:text-blue-600 hover:bg-blue-50 rounded-xl transition-all border border-transparent hover:border-blue-100">
                    <i class="bi bi-bell text-xl"></i>
                </a>
                <a href="#" class="p-2.5 text-slate-400 hover:text-blue-600 hover:bg-blue-50 rounded-xl transition-all border border-transparent hover:border-blue-100">
                    <i class="bi bi-person-circle text-xl"></i>
                </a>
            </div>
        </div>
    </header>

    <!-- Scrollable Content Area -->
    <div class="flex-1 overflow-y-auto px-10 py-10">
        <!-- Dashboard/Page Content Starts Here -->
