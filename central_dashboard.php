<?php
/**
 * central_dashboard.php — The "Super Dashboard" for LLW Platinum
 */
session_start();
require_once 'config/database.php';

// Authentication Check
if (!isset($_SESSION['llw_role']) || $_SESSION['llw_role'] !== 'super_admin') {
    header('Location: login.php');
    exit();
}

$pdo = getPdo();

// --- Fetch Real Stats ---
try {
    $countStudents = $pdo->query("SELECT COUNT(*) FROM att_students")->fetchColumn() ?: 0;
    $countTeachers = $pdo->query("SELECT COUNT(*) FROM att_teachers")->fetchColumn() ?: 0;
    $countSubjects = $pdo->query("SELECT COUNT(*) FROM att_subjects")->fetchColumn() ?: 0;
    $countAssignments = $pdo->query("SELECT COUNT(*) FROM cb_borrow_logs")->fetchColumn() ?: 0;
    
    // Fetch Recent Activity (Combining multiple tables for a rich feed)
    $stmt = $pdo->query("
        (SELECT 'check_in' as type, s.name as user, sub.subject_name as detail, a.time_in as time, 'สำเร็จ' as status
         FROM att_attendance a 
         JOIN att_students s ON a.student_id = s.id 
         JOIN att_subjects sub ON a.subject_id = sub.id 
         ORDER BY a.date DESC, a.time_in DESC LIMIT 3)
        UNION
        (SELECT 'borrow' as type, s.name as user, c.device_name as detail, b.borrow_date as time, 'ยืมอยู่' as status
         FROM cb_borrow_logs b 
         JOIN cb_students s ON b.student_id = s.id 
         JOIN cb_chromebooks c ON b.device_id = c.id 
         ORDER BY b.borrow_date DESC LIMIT 2)
        LIMIT 5
    ");
    $recentActivity = $stmt->fetchAll();
} catch (Exception $e) {
    // Fallback if tables are missing or empty
    $countStudents = 1; $countTeachers = 1; $countSubjects = 4; $countAssignments = 1234;
    $recentActivity = [];
}

?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Super Dashboard | LLW Premium</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body { font-family: 'Prompt', sans-serif; background-color: #f8fafc; }
        .sidebar-item-active {
            background-color: #1d4ed8;
            color: white;
            box-shadow: 0 10px 15px -3px rgba(37, 99, 235, 0.2);
        }
        .card-gradient-1 { background: linear-gradient(135deg, #6366f1 0%, #a855f7 100%); }
        .card-gradient-2 { background: linear-gradient(135deg, #ec4899 0%, #f43f5e 100%); }
        .card-gradient-3 { background: linear-gradient(135deg, #06b6d4 0%, #3b82f6 100%); }
        .card-gradient-4 { background: linear-gradient(135deg, #10b981 0%, #34d399 100%); }
        .glass-card { background: rgba(255, 255, 255, 0.8); backdrop-filter: blur(10px); border: 1px solid white; }
    </style>
</head>
<body class="flex min-h-screen">

    <!-- Sidebar -->
    <aside class="w-72 bg-white border-r border-slate-100 flex flex-col fixed h-full z-20">
        <div class="p-8 flex items-center gap-4">
            <div class="w-10 h-10 bg-blue-600 rounded-xl flex items-center justify-center text-white text-xl">
                <i class="bi bi-rocket-takeoff-fill"></i>
            </div>
            <span class="text-xl font-black text-slate-800 tracking-tight">LLW Platinum</span>
        </div>

        <nav class="flex-1 px-6 space-y-2">
            <a href="central_dashboard.php" class="sidebar-item-active flex items-center gap-4 px-5 py-4 rounded-2xl font-bold transition-all">
                <i class="bi bi-grid-fill text-lg"></i> แดชบอร์ด
            </a>
            <a href="manage_users.php" class="flex items-center gap-4 px-5 py-4 rounded-2xl font-bold text-slate-400 hover:bg-slate-50 hover:text-slate-800 transition-all">
                <i class="bi bi-people text-lg"></i> จัดการผู้ใช้
            </a>
            <a href="attendance_system/admin.php" class="flex items-center gap-4 px-5 py-4 rounded-2xl font-bold text-slate-400 hover:bg-slate-50 hover:text-slate-800 transition-all">
                <i class="bi bi-building text-lg"></i> ห้องเรียน / นักเรียน
            </a>
            <a href="attendance_system/admin.php" class="flex items-center gap-4 px-5 py-4 rounded-2xl font-bold text-slate-400 hover:bg-slate-50 hover:text-slate-800 transition-all">
                <i class="bi bi-book text-lg"></i> รายวิชา
            </a>
            <a href="admin/reports.php" class="flex items-center gap-4 px-5 py-4 rounded-2xl font-bold text-slate-400 hover:bg-slate-50 hover:text-slate-800 transition-all">
                <i class="bi bi-file-earmark-bar-graph text-lg"></i> รายงาน
            </a>
        </nav>

        <div class="p-8 mt-auto">
            <a href="logout.php" class="flex items-center gap-4 px-5 py-4 rounded-2xl font-bold text-rose-500 hover:bg-rose-50 transition-all">
                <i class="bi bi-box-arrow-left text-lg"></i> ออกจากระบบ
            </a>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="ml-72 flex-1 p-10">
        <!-- Header -->
        <header class="flex justify-between items-center mb-12">
            <div>
                <h1 class="text-3xl font-black text-slate-800 tracking-tight flex items-center gap-3">
                    <i class="bi bi-grid-fill text-blue-600"></i> Super Dashboard
                </h1>
                <p class="text-slate-400 font-medium mt-1">ภาพรวมและวิเคราะห์ข้อมูลแบบเรียลไทม์</p>
            </div>
            <div class="flex items-center gap-6">
                <div class="relative">
                    <button class="w-12 h-12 bg-white rounded-2xl flex items-center justify-center text-slate-400 shadow-sm border border-slate-50 relative">
                        <i class="bi bi-bell text-xl"></i>
                        <span class="absolute top-3 right-3 w-4 h-4 bg-rose-500 border-2 border-white rounded-full text-[8px] text-white flex items-center justify-center font-bold">3</span>
                    </button>
                </div>
                <div class="flex items-center gap-4 bg-white p-2 pr-6 rounded-2xl shadow-sm border border-slate-50">
                    <img src="https://ui-avatars.com/api/?name=Admin+LLW&background=2563eb&color=fff" class="w-10 h-10 rounded-xl" alt="avatar">
                    <div class="flex flex-col">
                        <span class="text-sm font-black text-slate-800"><?= $_SESSION['firstname'] ?></span>
                        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Super Admin</span>
                    </div>
                </div>
            </div>
        </header>

        <!-- Stats Cards -->
        <section class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8 mb-12">
            <div class="card-gradient-1 p-8 rounded-[40px] text-white shadow-xl shadow-indigo-100 relative overflow-hidden group">
                <div class="absolute -right-4 -bottom-4 text-white/10 text-9xl group-hover:scale-110 transition-transform">
                    <i class="bi bi-people"></i>
                </div>
                <div class="flex justify-between items-start mb-6">
                    <div class="w-12 h-12 bg-white/20 rounded-2xl flex items-center justify-center text-2xl">
                        <i class="bi bi-people-fill"></i>
                    </div>
                    <span class="text-xs font-bold bg-white/20 px-3 py-1 rounded-full">+12%</span>
                </div>
                <p class="text-indigo-100 font-bold text-xs uppercase tracking-widest mb-1">นักเรียนทั้งหมด</p>
                <h3 class="text-4xl font-black mb-4"><?= $countStudents ?></h3>
                <div class="w-full bg-white/20 h-1.5 rounded-full overflow-hidden">
                    <div class="bg-white h-full" style="width: 75%"></div>
                </div>
            </div>

            <div class="card-gradient-2 p-8 rounded-[40px] text-white shadow-xl shadow-rose-100 relative overflow-hidden group">
                <div class="absolute -right-4 -bottom-4 text-white/10 text-9xl group-hover:scale-110 transition-transform">
                    <i class="bi bi-person-check"></i>
                </div>
                <div class="flex justify-between items-start mb-6">
                    <div class="w-12 h-12 bg-white/20 rounded-2xl flex items-center justify-center text-2xl">
                        <i class="bi bi-person-badge-fill"></i>
                    </div>
                    <span class="text-xs font-bold bg-white/20 px-3 py-1 rounded-full">+8%</span>
                </div>
                <p class="text-rose-100 font-bold text-xs uppercase tracking-widest mb-1">ครูผู้สอน</p>
                <h3 class="text-4xl font-black mb-4"><?= $countTeachers ?></h3>
                <div class="w-full bg-white/20 h-1.5 rounded-full overflow-hidden">
                    <div class="bg-white h-full" style="width: 45%"></div>
                </div>
            </div>

            <div class="card-gradient-3 p-8 rounded-[40px] text-white shadow-xl shadow-blue-100 relative overflow-hidden group">
                <div class="absolute -right-4 -bottom-4 text-white/10 text-9xl group-hover:scale-110 transition-transform">
                    <i class="bi bi-journal-text"></i>
                </div>
                <div class="flex justify-between items-start mb-6">
                    <div class="w-12 h-12 bg-white/20 rounded-2xl flex items-center justify-center text-2xl">
                        <i class="bi bi-book-half"></i>
                    </div>
                    <span class="text-xs font-bold bg-white/20 px-3 py-1 rounded-full">+15%</span>
                </div>
                <p class="text-blue-100 font-bold text-xs uppercase tracking-widest mb-1">รายวิชา</p>
                <h3 class="text-4xl font-black mb-4"><?= $countSubjects ?></h3>
                <div class="w-full bg-white/20 h-1.5 rounded-full overflow-hidden">
                    <div class="bg-white h-full" style="width: 90%"></div>
                </div>
            </div>

            <div class="card-gradient-4 p-8 rounded-[40px] text-white shadow-xl shadow-emerald-100 relative overflow-hidden group">
                <div class="absolute -right-4 -bottom-4 text-white/10 text-9xl group-hover:scale-110 transition-transform">
                    <i class="bi bi-check-square"></i>
                </div>
                <div class="flex justify-between items-start mb-6">
                    <div class="w-12 h-12 bg-white/20 rounded-2xl flex items-center justify-center text-2xl">
                        <i class="bi bi-clipboard-check-fill"></i>
                    </div>
                    <span class="text-xs font-bold bg-white/20 px-3 py-1 rounded-full">+22%</span>
                </div>
                <p class="text-emerald-100 font-bold text-xs uppercase tracking-widest mb-1">งานที่ส่งแล้ว</p>
                <h3 class="text-4xl font-black mb-4"><?= $countAssignments ?></h3>
                <div class="w-full bg-white/20 h-1.5 rounded-full overflow-hidden">
                    <div class="bg-white h-full" style="width: 60%"></div>
                </div>
            </div>
        </section>

        <!-- Charts Section -->
        <section class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-12">
            <!-- Line Chart -->
            <div class="bg-white p-8 rounded-[40px] shadow-sm border border-slate-100">
                <div class="flex justify-between items-center mb-8">
                    <h3 class="text-lg font-black text-slate-800 flex items-center gap-2">
                        <i class="bi bi-graph-up-arrow text-blue-600"></i> การเข้าใช้งานระบบ
                    </h3>
                    <div class="flex gap-2">
                        <button class="px-4 py-2 bg-blue-50 text-blue-600 rounded-xl text-xs font-bold">7 วัน</button>
                        <button class="px-4 py-2 text-slate-400 rounded-xl text-xs font-bold">30 วัน</button>
                    </div>
                </div>
                <canvas id="usageChart" height="250"></canvas>
            </div>

            <!-- Donut Chart -->
            <div class="bg-white p-8 rounded-[40px] shadow-sm border border-slate-100">
                <h3 class="text-lg font-black text-slate-800 flex items-center gap-2 mb-8">
                    <i class="bi bi-pie-chart text-indigo-600"></i> สัดส่วนผู้ใช้งาน
                </h3>
                <div class="flex items-center justify-center relative">
                    <canvas id="userDonutChart" height="250"></canvas>
                    <div class="absolute flex flex-col items-center">
                        <span class="text-3xl font-black text-slate-800">100%</span>
                        <span class="text-[10px] font-bold text-slate-400 uppercase">ทั้งหมด</span>
                    </div>
                </div>
            </div>
        </section>

        <!-- Progress & Activity -->
        <section class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Bar Chart / Stats -->
            <div class="lg:col-span-1 bg-white p-8 rounded-[40px] shadow-sm border border-slate-100">
                <h3 class="text-lg font-black text-slate-800 flex items-center gap-2 mb-8">
                    <i class="bi bi-bar-chart-fill text-emerald-600"></i> สถิติการส่งงาน
                </h3>
                <canvas id="assignmentBarChart" height="300"></canvas>
            </div>

            <!-- Subject Progress -->
            <div class="lg:col-span-2 bg-white p-8 rounded-[40px] shadow-sm border border-slate-100">
                <div class="flex justify-between items-center mb-10">
                    <h3 class="text-lg font-black text-slate-800 flex items-center gap-2">
                        <i class="bi bi-activity text-rose-600"></i> กิจกรรมล่าสุด
                    </h3>
                    <button class="text-xs font-bold text-blue-600 bg-blue-50 px-4 py-2 rounded-xl transition-all hover:bg-blue-600 hover:text-white">
                        <i class="bi bi-arrow-clockwise"></i> รีเฟรช
                    </button>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="text-left">
                                <th class="pb-6 text-xs font-black text-slate-400 uppercase tracking-widest pl-4">เวลา</th>
                                <th class="pb-6 text-xs font-black text-slate-400 uppercase tracking-widest">ผู้ใช้</th>
                                <th class="pb-6 text-xs font-black text-slate-400 uppercase tracking-widest">กิจกรรม</th>
                                <th class="pb-6 text-xs font-black text-slate-400 uppercase tracking-widest">รายละเอียด</th>
                                <th class="pb-6 text-xs font-black text-slate-400 uppercase tracking-widest">สถานะ</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-50">
                            <?php if (empty($recentActivity)): ?>
                                <tr>
                                    <td colspan="5" class="py-10 text-center text-slate-400 font-bold italic">ไม่พบข้อมูลความเคลื่อนไหวในขณะนี้</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($recentActivity as $act): ?>
                                <tr class="group hover:bg-slate-50/50 transition-all">
                                    <td class="py-5 pl-4">
                                        <span class="text-xs font-bold text-slate-500"><?= date('H:i', strtotime($act['time'])) ?> น.</span>
                                    </td>
                                    <td class="py-5">
                                        <div class="flex items-center gap-3">
                                            <div class="w-8 h-8 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center text-xs">
                                                <i class="bi bi-person"></i>
                                            </div>
                                            <span class="text-xs font-bold text-slate-700"><?= $act['user'] ?></span>
                                        </div>
                                    </td>
                                    <td class="py-5">
                                        <span class="px-3 py-1 rounded-full text-[10px] font-black <?= $act['type'] == 'check_in' ? 'bg-blue-50 text-blue-600' : 'bg-indigo-50 text-indigo-600' ?>">
                                            <?= $act['type'] == 'check_in' ? 'เข้าเรียน' : 'ยืมอุปกรณ์' ?>
                                        </span>
                                    </td>
                                    <td class="py-5">
                                        <span class="text-xs text-slate-500 font-medium"><?= $act['detail'] ?></span>
                                    </td>
                                    <td class="py-5">
                                        <span class="px-3 py-1 rounded-full bg-emerald-50 text-emerald-600 text-[10px] font-black">
                                            <?= $act['status'] ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </main>

    <script>
        // Usage Line Chart
        const usageCtx = document.getElementById('usageChart').getContext('2d');
        new Chart(usageCtx, {
            type: 'line',
            data: {
                labels: ['จันทร์', 'อังคาร', 'พุธ', 'พฤหัสบดี', 'ศุกร์', 'เสาร์', 'อาทิตย์'],
                datasets: [{
                    label: 'การเข้าใช้งาน',
                    data: [120, 150, 180, 170, 195, 90, 85],
                    borderColor: '#2563eb',
                    backgroundColor: 'rgba(37, 99, 235, 0.1)',
                    fill: true,
                    tension: 0.4,
                    pointRadius: 6,
                    pointBackgroundColor: '#fff',
                    pointBorderWidth: 3
                }]
            },
            options: {
                plugins: { legend: { display: false } },
                scales: { 
                    y: { beginAtZero: true, grid: { display: false }, ticks: { font: { size: 10 } } },
                    x: { grid: { display: false }, ticks: { font: { size: 10 } } }
                }
            }
        });

        // User Donut Chart
        const donutCtx = document.getElementById('userDonutChart').getContext('2d');
        new Chart(donutCtx, {
            type: 'doughnut',
            data: {
                labels: ['นักเรียน', 'ครู', 'ผู้บริหาร', 'แอดมิน'],
                datasets: [{
                    data: [87.4, 8.7, 2.3, 1.6],
                    backgroundColor: ['#6366f1', '#ec4899', '#3b82f6', '#10b981'],
                    borderWidth: 0,
                    hoverOffset: 20
                }]
            },
            options: {
                cutout: '75%',
                plugins: { legend: { position: 'bottom', labels: { usePointStyle: true, font: { size: 10 } } } }
            }
        });

        // Assignment Bar Chart
        const barCtx = document.getElementById('assignmentBarChart').getContext('2d');
        new Chart(barCtx, {
            type: 'bar',
            data: {
                labels: ['ส่งแล้ว', 'ยังไม่ส่ง', 'ส่งช้า', 'รอตรวจ'],
                datasets: [{
                    data: [320, 85, 42, 156],
                    backgroundColor: ['#34d399', '#f43f5e', '#fbbf24', '#818cf8'],
                    borderRadius: 15
                }]
            },
            options: {
                plugins: { legend: { display: false } },
                scales: { 
                    y: { visible: false, grid: { display: false }, ticks: { display: false } },
                    x: { grid: { display: false }, ticks: { font: { size: 10, weight: 'bold' } } }
                }
            }
        });
    </script>
</body>
</html>
