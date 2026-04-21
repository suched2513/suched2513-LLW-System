<?php
require_once __DIR__ . '/../functions.php';
checkLogin();

$teacher_id   = $_SESSION['teacher_id'] ?? 0;
$teacher_name = $_SESSION['teacher_name'] ?? $_SESSION['fullname'] ?? $_SESSION['username'] ?? 'Admin';
$role = $_SESSION['llw_role'] ?? 'att_teacher';
$is_admin = ($role === 'super_admin');

// Current page for active state
$current_page = basename($_SERVER['PHP_SELF']);

// Navigation items
$nav_items = [
    ['label' => 'แดชบอร์ด', 'icon' => 'grid-1x2-fill', 'url' => 'dashboard.php'],
    ['label' => 'เช็คชื่อรายคาบ', 'icon' => 'calendar-check-fill', 'url' => 'attendance.php'],
    ['label' => 'รายงานสรุปวิชา', 'icon' => 'bar-chart-line-fill', 'url' => 'report_subject.php'],
    ['label' => 'รายงานรายบุคคล', 'icon' => 'person-badge-fill', 'url' => 'report_student.php'],
    ['label' => 'นำเข้าข้อมูลนักเรียน', 'icon' => 'cloud-arrow-up-fill', 'url' => 'import_students.php'],
];

// Admin only items
if ($is_admin) {
    $nav_items[] = ['label' => 'จัดการวิชา/ครู', 'icon' => 'gear-fill', 'url' => 'admin.php'];
}

?>

<aside id="sidebar" class="bg-white w-64 h-full border-r border-slate-200 flex flex-col flex-shrink-0 transition-all z-40 no-print overflow-y-auto">
    <!-- Header/Logo -->
    <div class="px-6 py-6 border-b border-slate-100 flex items-center justify-between">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 bg-blue-600 rounded-xl flex items-center justify-center text-white text-xl shadow-lg shadow-blue-200">
               <i class="bi bi-person-check-fill"></i>
            </div>
            <div>
                <h1 class="text-sm font-bold text-gray-800 leading-tight">LLW Attendance</h1>
                <p class="text-[10px] text-gray-400 font-medium uppercase tracking-wider">Lalom Wittaya</p>
            </div>
        </div>
    </div>

    <!-- User Profile (Mobile / Simple) -->
    <div class="px-6 py-5 bg-slate-50/50">
        <div class="flex items-center gap-3">
            <div class="w-9 h-9 bg-indigo-100 rounded-full flex items-center justify-center text-indigo-700 font-bold border border-indigo-200">
                <?= mb_substr($teacher_name, 0, 1) ?>
            </div>
            <div class="overflow-hidden">
                <p class="text-xs font-semibold text-gray-700 truncate"><?= htmlspecialchars($teacher_name) ?></p>
                <p class="text-[10px] text-gray-500 font-medium"><?= $is_admin ? 'ผู้ดูแลระบบ' : 'ครูผู้สอน' ?></p>
            </div>
        </div>
    </div>

    <!-- Navigation -->
    <div class="flex-1 px-3 py-4 space-y-1">
        <?php foreach ($nav_items as $item): ?>
            <?php 
                $activeClass = ($current_page === $item['url']) ? 'sidebar-item-active' : 'text-gray-600 hover:bg-slate-100 hover:text-blue-600';
            ?>
            <a href="<?= $item['url'] ?>" class="flex items-center gap-3 px-4 py-2.5 rounded-xl text-sm font-medium transition-all <?= $activeClass ?>">
                <i class="bi bi-<?= $item['icon'] ?> text-lg"></i>
                <span><?= $item['label'] ?></span>
                <?php if ($current_page === $item['url']): ?>
                    <div class="ml-auto w-1.5 h-1.5 bg-white rounded-full"></div>
                <?php endif; ?>
            </a>
        <?php endforeach; ?>
    </div>

    <!-- Footer -->
    <div class="p-4 border-t border-slate-100 mb-2">
        <a href="logout.php" class="flex items-center gap-3 px-4 py-2.5 rounded-xl text-sm font-medium text-red-500 hover:bg-red-50 transition-all">
            <i class="bi bi-box-arrow-left text-lg"></i>
            <span>ออกจากระบบ</span>
        </a>
    </div>
</aside>
