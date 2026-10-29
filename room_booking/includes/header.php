<?php
/**
 * room_booking/includes/header.php
 * ส่วนหัวของระบบจองห้องประชุม โรงเรียนละลมวิทยา
 */

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/functions.php';

$isLoggedIn = !empty($_SESSION['user_id']);
$fullname   = $_SESSION['fullname'] ?? 'ผู้มาเยือน';
$userRole   = $_SESSION['llw_role'] ?? 'guest';
$isAdmin    = isRoomAdmin();
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title><?= htmlspecialchars($pageTitle ?? 'ระบบจองห้องประชุม') ?> | โรงเรียนละลมวิทยา</title>
    
    <!-- Google Fonts: Prompt -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Tailwind CSS & Bootstrap Icons -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        body { font-family: 'Prompt', sans-serif; }
        .gradient-primary { background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%); }
        .glass-card { background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(8px); }
    </style>
</head>
<body class="bg-slate-50 text-slate-800 min-h-screen flex flex-col">

    <!-- Top Navigation Bar -->
    <header class="bg-white border-b border-slate-200 sticky top-0 z-50 shadow-sm">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16 items-center">
                <!-- Brand / Logo -->
                <div class="flex items-center gap-3">
                    <a href="/index.php" class="flex items-center gap-2 text-slate-500 hover:text-blue-600 transition" title="กลับหน้าหลักโรงเรียน">
                        <i class="bi bi-arrow-left text-lg"></i>
                        <span class="text-xs font-medium hidden sm:inline">หน้าหลัก รร.</span>
                    </a>
                    <span class="text-slate-300">|</span>
                    <a href="index.php" class="flex items-center gap-2.5">
                        <div class="w-10 h-10 rounded-xl bg-blue-600 text-white flex items-center justify-center shadow-md shadow-blue-500/20">
                            <i class="bi bi-door-open-fill text-xl"></i>
                        </div>
                        <div>
                            <div class="font-bold text-slate-900 leading-tight">ระบบจองห้องประชุม</div>
                            <div class="text-[11px] text-slate-500">โรงเรียนละลมวิทยา</div>
                        </div>
                    </a>
                </div>

                <!-- Nav Menu -->
                <nav class="hidden md:flex items-center gap-1">
                    <a href="index.php" class="px-3.5 py-2 rounded-lg text-sm font-medium transition <?= $currentPage === 'index.php' ? 'bg-blue-50 text-blue-700' : 'text-slate-600 hover:bg-slate-100' ?>">
                        <i class="bi bi-calendar3 mr-1.5"></i>ปฏิทินการใช้ห้อง
                    </a>
                    <a href="book.php" class="px-3.5 py-2 rounded-lg text-sm font-medium transition <?= $currentPage === 'book.php' ? 'bg-blue-50 text-blue-700' : 'text-slate-600 hover:bg-slate-100' ?>">
                        <i class="bi bi-plus-circle-fill mr-1.5 text-blue-600"></i>จองห้องประชุม
                    </a>
                    <?php if ($isLoggedIn): ?>
                    <a href="my_bookings.php" class="px-3.5 py-2 rounded-lg text-sm font-medium transition <?= $currentPage === 'my_bookings.php' ? 'bg-blue-50 text-blue-700' : 'text-slate-600 hover:bg-slate-100' ?>">
                        <i class="bi bi-journal-text mr-1.5"></i>รายการจองของฉัน
                    </a>
                    <?php endif; ?>

                    <?php if ($isAdmin): ?>
                    <span class="h-4 w-px bg-slate-200 mx-2"></span>
                    <a href="admin/index.php" class="px-3.5 py-2 rounded-lg text-sm font-medium bg-amber-50 text-amber-800 border border-amber-200 hover:bg-amber-100 transition">
                        <i class="bi bi-shield-lock-fill mr-1.5 text-amber-600"></i>แผงควบคุมผู้ดูแล
                    </a>
                    <?php endif; ?>
                </nav>

                <!-- User Profile / Auth State -->
                <div class="flex items-center gap-2">
                    <?php if ($isLoggedIn): ?>
                        <div class="text-right hidden sm:block">
                            <div class="text-xs font-semibold text-slate-900"><?= htmlspecialchars($fullname) ?></div>
                            <div class="text-[10px] text-slate-500 uppercase"><?= htmlspecialchars($userRole) ?></div>
                        </div>
                        <a href="/logout.php" class="text-slate-500 hover:text-rose-600 p-2 rounded-lg hover:bg-slate-100 transition" title="ออกจากระบบ">
                            <i class="bi bi-box-arrow-right text-lg"></i>
                        </a>
                    <?php else: ?>
                        <a href="/login.php" class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg text-sm font-medium bg-blue-600 text-white hover:bg-blue-700 shadow-sm transition">
                            <i class="bi bi-person-fill"></i>เข้าสู่ระบบ
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content Container -->
    <main class="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-6">
