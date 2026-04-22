<?php
/**
 * homeroom/index.php — แดชบอร์ดครูที่ปรึกษา (Class Advisor Dashboard)
 * ศูนย์กลางการดูแลนักเรียนสำหรับครูที่ปรึกษา
 */
session_start();
require_once __DIR__ . '/../config.php';

// Auth Guard
if (!isset($_SESSION['llw_role'])) {
    header('Location: /login.php?redirect=' . urlencode($_SERVER['REQUEST_URI'])); exit();
}

$pdo = getPdo();
$userId = $_SESSION['user_id'];
$userRole = $_SESSION['llw_role'];

// 1. ดึงห้องที่ปรึกษาที่ได้รับมอบหมาย
$stmt = $pdo->prepare("SELECT classroom FROM llw_class_advisors WHERE user_id = ? ORDER BY classroom");
$stmt->execute([$userId]);
$myClasses = $stmt->fetchAll(PDO::FETCH_COLUMN);

// 2. ถ้าเป็น Super Admin หรือ Admin ระบบอื่น ให้สามารถดูสรุปภาพรวมได้
$isAdmin = in_array($userRole, ['super_admin', 'wfh_admin']);

$pageTitle = 'ระบบครูที่ปรึกษา';
$pageSubtitle = 'จัดการและติดตามดูแลนักเรียนในห้องที่ปรึกษา';
$activeSystem = 'portal'; // ใช้ธีม Portal หรือจะสร้างธีมใหม่ก็ได้

require_once __DIR__ . '/../components/layout_start.php';
?>

<div class="space-y-8 animate-fade-in">
    <!-- Hero Section -->
    <div class="bg-gradient-to-br from-indigo-600 to-violet-700 rounded-[2.5rem] p-8 md:p-12 text-white shadow-2xl shadow-indigo-200/50 relative overflow-hidden">
        <div class="relative z-10">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-6">
                <div>
                    <h2 class="text-3xl md:text-4xl font-black mb-3">สวัสดีครับครู <?= htmlspecialchars($_SESSION['firstname']) ?> 👋</h2>
                    <p class="text-indigo-100 text-sm md:text-base font-medium opacity-90 max-w-xl">
                        ยินดีต้อนรับสู่ศูนย์กลางการดูแลนักเรียน ครูสามารถติดตามสถานะการเข้าเรียน พฤติกรรม และข้อมูลสำคัญของนักเรียนในที่ปรึกษาได้จากที่นี่ครับ
                    </p>
                </div>
                <div class="flex gap-3">
                    <?php if ($isAdmin): ?>
                    <a href="/manage_advisors.php" class="bg-white/20 backdrop-blur-md border border-white/30 text-white px-6 py-3 rounded-2xl font-black text-xs uppercase tracking-widest hover:bg-white hover:text-indigo-600 transition-all flex items-center gap-2">
                        <i class="bi bi-gear-fill"></i> จัดการการมอบหมาย
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <!-- Decorative Orbs -->
        <div class="absolute -right-10 -bottom-10 w-64 h-64 bg-white/10 rounded-full blur-3xl"></div>
        <div class="absolute top-10 left-1/4 w-32 h-32 bg-indigo-400/20 rounded-full blur-2xl animate-pulse"></div>
        <i class="bi bi-mortarboard-fill absolute -right-4 top-1/2 -translate-y-1/2 text-[15rem] text-white/5 rotate-12"></i>
    </div>

    <!-- Main Content -->
    <?php if (empty($myClasses) && !$isAdmin): ?>
        <div class="bg-white rounded-[2.5rem] p-12 text-center border border-slate-100 shadow-xl shadow-slate-100/50">
            <div class="w-20 h-20 bg-slate-50 rounded-3xl flex items-center justify-center text-slate-300 text-4xl mx-auto mb-6">
                <i class="bi bi-person-exclamation"></i>
            </div>
            <h3 class="text-xl font-black text-slate-800 mb-2">ยังไม่พบการมอบหมายห้องที่ปรึกษา</h3>
            <p class="text-slate-400 text-sm max-w-sm mx-auto font-medium">
                ดูเหมือนว่าคุณยังไม่ได้รับมอบหมายห้องเรียนครับ กรุณาติดต่อผู้ดูแลระบบเพื่อทำการกำหนดห้องที่ปรึกษาให้คุณในระบบส่วนกลาง
            </p>
            <?php if ($isAdmin): ?>
                <div class="mt-8">
                    <a href="/manage_advisors.php" class="inline-flex items-center gap-2 px-8 py-3.5 bg-blue-600 text-white rounded-2xl font-black text-sm shadow-xl shadow-blue-200 hover:scale-105 transition-all">
                        <i class="bi bi-plus-circle"></i> ไปหน้าจัดการการมอบหมาย
                    </a>
                </div>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php 
            // รวมห้องทั้งหมดถ้าเป็น Admin หรือเฉพาะห้องตัวเอง
            $displayClasses = $isAdmin 
                ? $pdo->query("SELECT DISTINCT classroom FROM assembly_students ORDER BY classroom")->fetchAll(PDO::FETCH_COLUMN) 
                : $myClasses;
            $today = date('Y-m-d');

            foreach ($displayClasses as $room): 
                // Fetch Stats for this room
                // 1. Attendance Today (Assembly)
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM assembly_attendance WHERE date = ? AND classroom = ?");
                $stmt->execute([$today, $room]);
                $attToday = $stmt->fetchColumn();

                // 2. Behavior Points (Good/Bad) - This month
                $monthStart = date('Y-m-01');
                $stmt = $pdo->prepare("
                    SELECT 
                        SUM(CASE WHEN type = 'ความดี' THEN score ELSE 0 END) as good,
                        SUM(CASE WHEN type = 'ความผิด' THEN score ELSE 0 END) as bad
                    FROM beh_records r
                    JOIN att_students s ON r.student_id = s.student_id
                    WHERE s.classroom = ? AND r.record_date >= ?
                ");
                $stmt->execute([$room, $monthStart]);
                $behStats = $stmt->fetch(PDO::FETCH_ASSOC);

                // 3. Pending Good Deeds
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) FROM beh_records r
                    JOIN att_students s ON r.student_id = s.student_id
                    WHERE s.classroom = ? AND r.status = 'pending'
                ");
                try {
                    $stmt->execute([$room]);
                    $pendingDeeds = $stmt->fetchColumn();
                } catch(Exception $e) { $pendingDeeds = 0; }
            ?>
            <div class="bg-white rounded-[2rem] border border-slate-100 shadow-xl shadow-slate-200/40 overflow-hidden hover:shadow-2xl transition-all group">
                <div class="p-8">
                    <div class="flex items-center justify-between mb-6">
                        <div class="flex items-center gap-4">
                            <div class="w-14 h-14 bg-indigo-50 text-indigo-600 rounded-2xl flex items-center justify-center text-xl font-black shadow-inner">
                                <?= htmlspecialchars($room) ?>
                            </div>
                            <div>
                                <h3 class="text-lg font-black text-slate-800">ชั้น <?= htmlspecialchars($room) ?></h3>
                                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Class Advisor Board</p>
                            </div>
                        </div>
                        <?php if ($pendingDeeds > 0): ?>
                        <div class="flex items-center gap-1.5 px-3 py-1 bg-rose-50 text-rose-500 rounded-full animate-pulse border border-rose-100">
                            <span class="w-2 h-2 bg-rose-500 rounded-full"></span>
                            <span class="text-[10px] font-black uppercase tracking-tighter"><?= $pendingDeeds ?> Pending</span>
                        </div>
                        <?php endif; ?>
                    </div>

                    <div class="grid grid-cols-2 gap-3 mb-8">
                        <div class="bg-slate-50 rounded-2xl p-4 border border-slate-100/50 transition-all hover:bg-white hover:shadow-md">
                            <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-1">การเข้าแถววันนี้</p>
                            <p class="text-xl font-black text-slate-700"><?= $attToday > 0 ? '<span class="text-emerald-500">เช็คแล้ว</span>' : '<span class="text-amber-500">ยังไม่ได้เช็ค</span>' ?></p>
                        </div>
                        <div class="bg-slate-50 rounded-2xl p-4 border border-slate-100/50 transition-all hover:bg-white hover:shadow-md">
                            <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-1">คะแนนความดี (ด.)</p>
                            <p class="text-xl font-black text-emerald-500">+<?= number_format($behStats['good'] ?? 0) ?></p>
                        </div>
                    </div>

                    <!-- Quick Links -->
                    <div class="space-y-2">
                        <a href="/homeroom/log.php?classroom=<?= urlencode($room) ?>" class="flex items-center justify-between p-3.5 bg-emerald-50 text-emerald-700 rounded-xl font-bold text-xs hover:bg-emerald-600 hover:text-white transition-all group/link border border-emerald-100 shadow-md shadow-emerald-100/50">
                            <span class="flex items-center gap-2">
                                <i class="bi bi-book-half"></i> บันทึกกิจกรรมโฮมรูม (Logbook)
                            </span>
                            <i class="bi bi-arrow-right opacity-0 group-hover/link:opacity-100 transition-all"></i>
                        </a>
                        <a href="/assembly/dashboard.php?classroom=<?= urlencode($room) ?>" class="flex items-center justify-between p-3.5 bg-indigo-50/50 text-indigo-700 rounded-xl font-bold text-xs hover:bg-indigo-600 hover:text-white transition-all group/link">
                            <span class="flex items-center gap-2">
                                <i class="bi bi-people-fill"></i> เช็คชื่อเข้าแถว
                            </span>
                            <i class="bi bi-arrow-right opacity-0 group-hover/link:opacity-100 transition-all"></i>
                        </a>
                        <a href="/behavior/dashboard.php?classroom=<?= urlencode($room) ?>" class="flex items-center justify-between p-3.5 bg-violet-50/50 text-violet-700 rounded-xl font-bold text-xs hover:bg-violet-600 hover:text-white transition-all group/link">
                            <span class="flex items-center gap-2">
                                <i class="bi bi-journal-text"></i> บันทึกพฤติกรรมห้องนี้
                            </span>
                            <i class="bi bi-arrow-right opacity-0 group-hover/link:opacity-100 transition-all"></i>
                        </a>
                        <a href="/attendance_system/dashboard.php?classroom=<?= urlencode($room) ?>" class="flex items-center justify-between p-3.5 bg-blue-50/50 text-blue-700 rounded-xl font-bold text-xs hover:bg-blue-600 hover:text-white transition-all group/link">
                            <span class="flex items-center gap-2">
                                <i class="bi bi-person-check-fill"></i> รายงานการเรียนรายวิชา
                            </span>
                            <i class="bi bi-arrow-right opacity-0 group-hover/link:opacity-100 transition-all"></i>
                        </a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../components/layout_end.php'; ?>
