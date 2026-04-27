<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/db.php';

// Auth Guard
checkLogin();

// Include Header & Sidebar
require_once __DIR__ . '/header.php';
require_once __DIR__ . '/sidebar.php';

$user = currentUser();
?>

<!-- Main Layout Wrapper -->
<div class="pl-64 min-h-screen">
    <!-- Top Navbar -->
    <header class="h-20 bg-white/80 backdrop-blur-md border-b border-slate-100 sticky top-0 z-40 flex items-center justify-between px-8">
        <div>
            <h1 class="text-xl font-black text-slate-800"><?= $pageTitle ?? 'Dashboard' ?></h1>
            <p class="text-[11px] text-slate-400 font-bold uppercase tracking-widest"><?= $pageSubtitle ?? 'Overview & Statistics' ?></p>
        </div>
        
        <div class="flex items-center gap-4">
            <div class="flex flex-col text-right hidden sm:flex">
                <span class="text-xs font-black text-slate-700"><?= htmlspecialchars($user['full_name']) ?></span>
                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-tighter"><?= htmlspecialchars($user['dept'] ?: 'No Department') ?></span>
            </div>
            <div class="w-10 h-10 bg-gradient-to-br from-blue-500 to-indigo-600 rounded-xl flex items-center justify-center text-white shadow-lg shadow-blue-200">
                <i class="bi bi-person-fill"></i>
            </div>
        </div>
    </header>

    <!-- Main Content Area -->
    <main class="p-8">
