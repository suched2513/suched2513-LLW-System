<?php
/**
 * index.php — The LLW School Portal
 * Central Landing Page for all Sub-systems
 */
session_start();
require_once 'config.php';

// If not logged in, show the portal but with 'Lock' icons or redirect to login
$isLoggedIn = isset($_SESSION['user_id']);
$userRole = $_SESSION['llw_role'] ?? 'guest';

$pageTitle = 'LLW Portal';
$pageSubtitle = 'ศูนย์รวมระบบบริหารจัดการ โรงเรียนละลมวิทยา';

require_once 'components/header.php';
?>

<div class="min-h-screen flex flex-col bg-slate-50 relative overflow-hidden">
    <!-- Background Blobs -->
    <div class="absolute inset-0 z-0 overflow-hidden pointer-events-none">
        <div class="absolute top-[-20%] left-[-10%] w-[60%] h-[60%] bg-blue-400/10 rounded-full blur-[150px]"></div>
        <div class="absolute bottom-[-20%] right-[-10%] w-[60%] h-[60%] bg-indigo-400/10 rounded-full blur-[150px]"></div>
    </div>

    <!-- Navigation (Portal Style) -->
    <nav class="h-24 px-10 flex items-center justify-between z-10 glass border-b border-white/50 sticky top-0">
        <div class="flex items-center gap-4">
            <div class="w-12 h-12 bg-blue-600 rounded-2xl flex items-center justify-center text-white text-2xl font-black shadow-xl shadow-blue-100 italic">LLW</div>
            <div class="flex flex-col">
                <span class="text-lg font-black text-slate-800 tracking-tight">Lalom Wittaya Portal</span>
                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest leading-tight">School Management Ecosystem</span>
            </div>
        </div>
        <div class="flex items-center gap-4">
            <?php if ($isLoggedIn): ?>
                <div class="flex items-center gap-3 px-4 py-2 bg-white rounded-2xl border border-slate-100 shadow-sm">
                    <div class="w-8 h-8 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center font-bold text-xs"><?= mb_substr($_SESSION['firstname']??'U', 0, 1) ?></div>
                    <span class="text-xs font-bold text-slate-700"><?= htmlspecialchars($_SESSION['fullname']) ?></span>
                </div>
                <a href="logout.php" class="p-2.5 text-rose-500 hover:bg-rose-50 rounded-xl transition-all">
                    <i class="bi bi-power text-xl"></i>
                </a>
            <?php else: ?>
                <a href="login.php" class="bg-blue-600 text-white px-8 py-3 rounded-2xl font-black text-sm shadow-xl shadow-blue-100 hover:bg-blue-700 hover:scale-105 transition-all">
                    เข้าสู่ระบบ
                </a>
            <?php endif; ?>
        </div>
    </nav>

    <!-- Content -->
    <main class="flex-1 container mx-auto px-6 py-20 z-10">
        <div class="max-w-6xl mx-auto text-center mb-20">
            <h2 class="text-5xl font-black text-slate-800 tracking-tight mb-6 leading-tight">ยินดีต้อนรับสู่ <span class="text-blue-600">LLW Platform</span></h2>
            <p class="text-slate-400 text-lg font-medium max-w-2xl mx-auto leading-relaxed">แพลตฟอร์มบริหารจัดการโรงเรียนแบบรวมศูนย์ เพื่อความสะดวกและทันสมัยสำหรับบุคลากรและนักเรียนทุกส่วนงาน</p>
        </div>

        <!-- Modules Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8">
            
            <!-- Module 1: Attendance -->
            <a href="attendance_system/dashboard.php" class="group relative bg-white p-10 rounded-[40px] shadow-sm border border-slate-100 hover:shadow-2xl hover:shadow-blue-100 hover:-translate-y-2 transition-all duration-500">
                <div class="w-16 h-16 bg-blue-50 text-blue-600 rounded-3xl flex items-center justify-center text-3xl mb-8 group-hover:bg-blue-600 group-hover:text-white transition-all">
                    <i class="bi bi-person-check-fill"></i>
                </div>
                <h3 class="text-xl font-black text-slate-800 mb-3">ระบบเช็คชื่อนักเรียน</h3>
                <p class="text-sm text-slate-400 font-medium leading-relaxed">บันทึกเวลาเรียนรายวิชา สรุปสถิติเข้าเรียน และรายงานผลการมาเรียนอัตโนมัติ</p>
                <div class="mt-8 flex items-center gap-2 text-blue-600 font-bold text-xs uppercase tracking-widest opacity-0 group-hover:opacity-100 transition-all">
                    Go to module <i class="bi bi-arrow-right"></i>
                </div>
            </a>

            <!-- Module 2: Chromebook -->
            <a href="chromebook/index.php" class="group relative bg-white p-10 rounded-[40px] shadow-sm border border-slate-100 hover:shadow-2xl hover:shadow-indigo-100 hover:-translate-y-2 transition-all duration-500">
                <div class="w-16 h-16 bg-indigo-50 text-indigo-600 rounded-3xl flex items-center justify-center text-3xl mb-8 group-hover:bg-indigo-600 group-hover:text-white transition-all">
                    <i class="bi bi-laptop"></i>
                </div>
                <h3 class="text-xl font-black text-slate-800 mb-3">จัดการ Chromebook</h3>
                <p class="text-sm text-slate-400 font-medium leading-relaxed">ระบบยืม-คืนอุปกรณ์ส่วนกลาง ตรวจสอบสถานะการใช้งาน และรายงานความเสียหาย</p>
                <div class="mt-8 flex items-center gap-2 text-indigo-600 font-bold text-xs uppercase tracking-widest opacity-0 group-hover:opacity-100 transition-all">
                    Go to module <i class="bi bi-arrow-right"></i>
                </div>
            </a>

            <!-- Module 3: WFH -->
            <a href="index_wfh.php" class="group relative bg-white p-10 rounded-[40px] shadow-sm border border-slate-100 hover:shadow-2xl hover:shadow-emerald-100 hover:-translate-y-2 transition-all duration-500">
                <div class="w-16 h-16 bg-emerald-50 text-emerald-600 rounded-3xl flex items-center justify-center text-3xl mb-8 group-hover:bg-emerald-600 group-hover:text-white transition-all">
                    <i class="bi bi-clock-history"></i>
                </div>
                <h3 class="text-xl font-black text-slate-800 mb-3">ลงเวลาบุคลากร</h3>
                <p class="text-sm text-slate-400 font-medium leading-relaxed">บันทึกเวลาเข้า-ออกงานด้วย GPS ยืนยันตัวตนด้วยภาพถ่าย และสรุปเวลาทำงาน</p>
                <div class="mt-8 flex items-center gap-2 text-emerald-600 font-bold text-xs uppercase tracking-widest opacity-0 group-hover:opacity-100 transition-all">
                    Go to module <i class="bi bi-arrow-right"></i>
                </div>
            </a>

            <!-- Module 4: Leave System (The old index) -->
            <a href="leave_system.php" class="group relative bg-white p-10 rounded-[40px] shadow-sm border border-slate-100 hover:shadow-2xl hover:shadow-rose-100 hover:-translate-y-2 transition-all duration-500">
                <div class="w-16 h-16 bg-rose-50 text-rose-600 rounded-3xl flex items-center justify-center text-3xl mb-8 group-hover:bg-rose-600 group-hover:text-white transition-all">
                    <i class="bi bi-door-open-fill"></i>
                </div>
                <h3 class="text-xl font-black text-slate-800 mb-3">ขออนุญาตออกนอก</h3>
                <p class="text-sm text-slate-400 font-medium leading-relaxed">ระบบยื่นคำขออนุญาตออกนอกบริเวณออนไลน์ พร้อมระบบแจ้งเตือนผ่าน Telegram</p>
                <div class="mt-8 flex items-center gap-2 text-rose-600 font-bold text-xs uppercase tracking-widest opacity-0 group-hover:opacity-100 transition-all">
                    Go to module <i class="bi bi-arrow-right"></i>
                </div>
            </a>

        </div>

        <?php if ($userRole === 'super_admin'): ?>
        <div class="mt-16 flex justify-center">
            <a href="central_dashboard.php" class="flex items-center gap-3 px-8 py-4 bg-slate-800 text-white rounded-2xl font-bold hover:bg-slate-900 transition-all shadow-xl shadow-slate-200">
                <i class="bi bi-gear-fill"></i> แผงควบคุมระบบส่วนกลาง (Super Admin)
            </a>
        </div>
        <?php endif; ?>

    </main>

    <!-- Footer -->
    <footer class="py-12 border-t border-slate-200/50 text-center z-10 px-6">
        <p class="text-[10px] font-black text-slate-400 uppercase tracking-[0.3em]">© 2026 Lalom Wittaya School. Powered by Advanced School Intelligence.</p>
    </footer>
</div>

<?php require_once 'components/layout_end.php'; ?>
