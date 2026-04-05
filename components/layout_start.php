<?php
/**
 * layout_start.php — Premium Layout Wrapper for LLW System
 */
require_once 'header.php';
require_once 'sidebar.php';
?>

<style>
    .mesh-bg {
        background-color: #f8fafc;
        background-image: 
            radial-gradient(at 0% 0%, rgba(59, 130, 246, 0.03) 0, transparent 50%), 
            radial-gradient(at 50% 0%, rgba(99, 102, 241, 0.03) 0, transparent 50%), 
            radial-gradient(at 100% 0%, rgba(236, 72, 153, 0.03) 0, transparent 50%);
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
    
    <!-- Premium Top Header -->
    <header class="h-20 flex-shrink-0 flex items-center justify-between px-10 glass-header no-print z-40 sticky top-0">
        <div class="flex items-center gap-6">
            <button onclick="toggleSidebar()" class="lg:hidden p-3 text-slate-500 hover:bg-slate-100/80 rounded-2xl transition-all">
                <i class="bi bi-list text-2xl"></i>
            </button>
            <div class="flex flex-col">
                <h1 class="text-xl font-black text-slate-800 tracking-tight leading-tight"><?= $pageTitle ?? 'LLW Management' ?></h1>
                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-[0.2em] leading-tight mt-1.5 opacity-70"><?= $pageSubtitle ?? 'Lalom Wittaya School System' ?></p>
            </div>
        </div>

        <div class="flex items-center gap-6">
            <!-- Calendar Widget -->
            <div class="hidden md:flex items-center gap-3 px-5 py-2.5 bg-white/50 rounded-2xl border border-slate-200/50 text-slate-500 text-xs font-bold transition-all hover:bg-white hover:shadow-xl hover:shadow-slate-100 hover:-translate-y-0.5">
                <i class="bi bi-calendar3 text-blue-500"></i>
                <span><?= date('D, d M Y') ?></span>
            </div>
            
            <!-- Quick Tools -->
            <div class="flex items-center gap-2">
                <button class="w-11 h-11 flex items-center justify-center text-slate-400 hover:text-blue-600 hover:bg-blue-50 rounded-xl transition-all border border-transparent hover:border-blue-100 relative group">
                    <i class="bi bi-bell text-xl"></i>
                    <span class="absolute top-3 right-3 w-2 h-2 bg-rose-500 rounded-full animate-ping"></span>
                    <span class="absolute top-3 right-3 w-2 h-2 bg-rose-500 rounded-full"></span>
                </button>
                <button class="w-11 h-11 flex items-center justify-center text-slate-400 hover:text-indigo-600 hover:bg-indigo-50 rounded-xl transition-all border border-transparent hover:border-indigo-100">
                    <i class="bi bi-gear text-xl"></i>
                </button>
            </div>
            
            <div class="w-px h-8 bg-slate-200 mx-2"></div>
            
            <!-- Profile Thumbnail -->
            <div class="flex items-center gap-3 pl-4">
                <div class="w-10 h-10 rounded-2xl bg-gradient-to-br from-blue-600 to-indigo-600 flex items-center justify-center text-white text-md font-black shadow-lg">
                    <?= mb_substr($_SESSION['firstname'] ?? 'U', 0, 1) ?>
                </div>
            </div>
        </div>
    </header>

    <!-- Scrollable Content Area -->
    <div class="flex-1 overflow-y-auto px-10 py-12 scroll-smooth">
        <!-- Page Content Starts Here -->
