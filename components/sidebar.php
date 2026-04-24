<?php
/**
 * sidebar.php — Premium Navigation with Sub-menus per Module
 */
$current_page = basename($_SERVER['PHP_SELF']);
$current_dir  = basename(dirname($_SERVER['PHP_SELF']));

// Determine active system
$activeSystem = $activeSystem ?? 'portal';
if ($current_dir === 'attendance_system') $activeSystem = 'attendance';
if ($current_dir === 'chromebook')        $activeSystem = 'chromebook';
if ($current_page === 'leave_system.php') $activeSystem = 'leave';
if ($current_dir === 'plc_system')        $activeSystem = 'plc';
if ($current_dir === 'user' || $current_dir === 'admin' || $current_page === 'index_wfh.php') $activeSystem = 'wfh';
if ($current_page === 'central_dashboard.php' || $current_page === 'index.php') $activeSystem = 'portal';
if ($current_dir === 'assembly') $activeSystem = 'assembly';
if ($current_page === 'supervision.php') $activeSystem = 'supervision';
if ($current_dir === 'teacher_leave')         $activeSystem = 'teacher_leave';
if ($current_dir === 'behavior')              $activeSystem = 'behavior';
if ($current_dir === 'homeroom' || $current_page === 'manage_advisors.php') $activeSystem = 'homeroom';
if ($current_page === 'student_info.php' || $current_page === 'teacher_info.php') $activeSystem = 'info';
if ($current_dir === 'budget_system')          $activeSystem = 'budget';

// User context
$userName = $_SESSION['firstname'] ?? ($_SESSION['teacher_name'] ?? 'User');
$userRole = $_SESSION['llw_role'] ?? 'staff';
$roleName = [
    'super_admin' => 'Super Admin',
    'wfh_admin'   => 'WFH Admin',
    'wfh_staff'   => 'Personnel',
    'cb_admin'    => 'Device Manager',
    'att_teacher' => 'Academic Staff'
][$userRole] ?? 'Staff Member';

// Sub-menu definitions per module
$subMenus = [
    'assembly' => [
        ['icon' => 'bi-check2-all',        'label' => 'เช็คชื่อเข้าแถว',   'url' => $base_path . '/assembly/dashboard.php'],
        ['icon' => 'bi-graph-up-arrow',    'label' => 'รายงานผู้บริหาร',   'url' => $base_path . '/assembly/admin.php',            'roles' => ['super_admin','wfh_admin']],
        ['icon' => 'bi-person-lines-fill', 'label' => 'จัดการนักเรียน',    'url' => $base_path . '/assembly/manage_students.php',  'roles' => ['super_admin']],
    ],
    'attendance' => [
        ['icon' => 'bi-speedometer2',    'label' => 'Dashboard',         'url' => $base_path . '/attendance_system/dashboard.php'],
        ['icon' => 'bi-check2-square',   'label' => 'เช็คชื่อ',          'url' => $base_path . '/attendance_system/attendance.php'],
        ['icon' => 'bi-bar-chart',       'label' => 'รายงาน',            'url' => $base_path . '/attendance_system/report.php'],
        ['icon' => 'bi-people',          'label' => 'จัดการข้อมูล',      'url' => $base_path . '/attendance_system/admin.php'],
        ['icon' => 'bi-graph-up-arrow',  'label' => 'รายงานผู้บริหาร',   'url' => $base_path . '/attendance_system/report_admin.php', 'roles' => ['super_admin','wfh_admin']],
        ['icon' => 'bi-exclamation-triangle-fill', 'label' => 'สรุปมส. ทั้งโรงเรียน', 'url' => $base_path . '/attendance_system/report_ms.php',    'roles' => ['super_admin','wfh_admin']],
        ['icon' => 'bi-download',        'label' => 'Export วิชาเลือก',  'url' => $base_path . '/attendance_system/export_elective.php', 'roles' => ['super_admin','wfh_admin']],
    ],
    'chromebook' => [
        ['icon' => 'bi-speedometer2',     'label' => 'Dashboard',  'url' => $base_path . '/chromebook/index.php'],
        ['icon' => 'bi-arrow-left-right', 'label' => 'ยืม-คืน',     'url' => $base_path . '/chromebook/dashboard.php'],
    ],
    'wfh' => [
        ['icon' => 'bi-speedometer2', 'label' => 'Dashboard',     'url' => $base_path . '/admin/dashboard.php',  'roles' => ['super_admin','wfh_admin']],
        ['icon' => 'bi-clock-history', 'label' => 'ลงเวลา',       'url' => $base_path . '/user/dashboard.php'],
        ['icon' => 'bi-bar-chart',    'label' => 'รายงาน',        'url' => $base_path . '/admin/reports.php',    'roles' => ['super_admin','wfh_admin']],
        ['icon' => 'bi-people',       'label' => 'จัดการบุคลากร',  'url' => $base_path . '/admin/manage_users.php', 'roles' => ['super_admin','wfh_admin']],
        ['icon' => 'bi-gear',         'label' => 'ตั้งค่า',        'url' => $base_path . '/admin/settings.php',  'roles' => ['super_admin','wfh_admin']],
    ],
    'leave' => [
        ['icon' => 'bi-list-check', 'label' => 'รายการคำขอ', 'url' => $base_path . '/leave_system.php'],
    ],
    'teacher_leave' => [
        ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'url' => $base_path . '/teacher_leave/index.php'],
        ['icon' => 'bi-plus-circle',  'label' => 'ยื่นใบลาใหม่', 'url' => $base_path . '/teacher_leave/form.php'],
    ],
    'plc' => [
        ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'url' => $base_path . '/plc_system/dashboard.php'],
        ['icon' => 'bi-journal-plus', 'label' => 'บันทึก PDCA', 'url' => $base_path . '/plc_system/add_log.php'],
        ['icon' => 'bi-file-earmark-bar-graph', 'label' => 'รายงานสรุป', 'url' => $base_path . '/plc_system/report_print.php'],
        ['icon' => 'bi-shield-shaded', 'label' => 'Admin ภาพรวม', 'url' => $base_path . '/plc_system/admin.php', 'roles' => ['super_admin']],
    ],
    'supervision' => [
        ['icon' => 'bi-pencil-square', 'label' => 'บันทึกการนิเทศ', 'url' => $base_path . '/supervision.php?tab=record'],
        ['icon' => 'bi-person-badge', 'label' => 'รายงานรายบุคคล', 'url' => $base_path . '/supervision.php?tab=individual'],
        ['icon' => 'bi-pie-chart',     'label' => 'รายงานภาพรวม', 'url' => $base_path . '/supervision.php?tab=summary', 'roles' => ['super_admin','wfh_admin']],
        ['icon' => 'bi-person-gear',  'label' => 'ตั้งค่าข้อมูลครู', 'url' => $base_path . '/supervision.php?tab=settings', 'roles' => ['super_admin']],
    ],
    'behavior' => [
        ['icon' => 'bi-pencil-square',  'label' => 'บันทึกพฤติกรรม',    'url' => $base_path . '/behavior/dashboard.php'],
        ['icon' => 'bi-mortarboard-fill', 'label' => 'จัดการห้องที่ปรึกษา', 'url' => $base_path . '/behavior/manage_advisors_ui.php'],
        ['icon' => 'bi-speedometer2',   'label' => 'Admin Dashboard',   'url' => $base_path . '/behavior/admin.php',   'roles' => ['super_admin','wfh_admin']],
        ['icon' => 'bi-sliders',        'label' => 'จัดการระบบ',        'url' => $base_path . '/behavior/manage.php',  'roles' => ['super_admin']],
        ['icon' => 'bi-mortarboard',    'label' => 'นักเรียนดูข้อมูล',  'url' => $base_path . '/behavior/student_view.php'],
    ],
    'homeroom' => [
        ['icon' => 'bi-speedometer2', 'label' => 'Advisor Dashboard', 'url' => $base_path . '/homeroom/index.php'],
        ['icon' => 'bi-people-fill',   'label' => 'จัดการการมอบหมาย',  'url' => $base_path . '/manage_advisors.php', 'roles' => ['super_admin']],
    ],
    'cleanliness' => [
        ['icon' => 'bi-speedometer2', 'label' => 'Dashboard',     'url' => $base_path . '/cleanliness/index.php'],
        ['icon' => 'bi-clipboard-check', 'label' => 'บันทึกคะแนน', 'url' => $base_path . '/cleanliness/index.php'],
        ['icon' => 'bi-clock-history',   'label' => 'ประวัติการประเมิน', 'url' => $base_path . '/cleanliness/history.php'],
        ['icon' => 'bi-geo-alt-fill',   'label' => 'จัดการพื้นที่', 'url' => $base_path . '/cleanliness/manage_areas.php', 'roles' => ['super_admin']],
    ],
    'info' => [
        ['icon' => 'bi-people-fill',        'label' => 'สารสนเทศนักเรียน',   'url' => $base_path . '/student_info.php'],
        ['icon' => 'bi-person-vcard-fill',  'label' => 'สารสนเทศครู',        'url' => $base_path . '/teacher_info.php'],
    ],
];
?>

<style>
    /* Hybrid Sidebar Transitions */
    .sidebar-transition { transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1); }
    
    /* Expanded State (Default) */
    #sidebar { width: 18rem; }
    .sidebar-text { opacity: 1; display: inline-block; transition: opacity 0.3s ease; }
    .sidebar-brand-text { opacity: 1; transition: opacity 0.3s ease; }
    
    /* Collapsed State (Desktop) */
    body.sidebar-collapsed #sidebar { width: 5rem; }
    body.sidebar-collapsed .sidebar-text { opacity: 0; display: none; }
    body.sidebar-collapsed .sidebar-brand-text { display: none; }
    body.sidebar-collapsed .sidebar-group-label { display: none; }
    body.sidebar-collapsed .sub-menu { display: none !important; }
    body.sidebar-collapsed .nav-link-chevron { display: none; }
    body.sidebar-collapsed .profile-card-full { display: none; }
    body.sidebar-collapsed .profile-card-mini { display: flex !important; }
    body.sidebar-collapsed aside { padding-left: 0.75rem; padding-right: 0.75rem; }
    body.sidebar-collapsed .nav-item { justify-content: center; padding-left: 0; padding-right: 0; }
    body.sidebar-collapsed .nav-item i { font-size: 1.25rem; }

    .sub-menu { max-height: 0; overflow: hidden; transition: max-height 0.4s cubic-bezier(0.4, 0, 0.2, 1); }
    .sub-menu.open { max-height: 500px; }
    .sub-item { transition: all 0.2s ease; }
    .sub-item:hover { padding-left: 3.5rem; }
    
    /* Active Link Indicator */
    .nav-link-active { position: relative; }
    .nav-link-active::before {
        content: ''; position: absolute; left: 0; top: 50%; transform: translateY(-50%);
        width: 4px; height: 60%; border-radius: 0 4px 4px 0; background: currentColor; opacity: 0.5;
    }

    /* Tooltips for Collapsed State */
    .sidebar-tooltip { display: none; }
    body.sidebar-collapsed .nav-item { position: relative; }
    body.sidebar-collapsed .nav-item .sidebar-tooltip {
        display: block;
        position: absolute; left: 100%; top: 50%; transform: translateY(-50%);
        margin-left: 1rem; padding: 0.5rem 0.75rem;
        background: #1e293b; color: white; font-size: 11px; font-weight: bold;
        border-radius: 0.5rem; white-space: nowrap; opacity: 0; pointer-events: none;
        transition: all 0.2s ease; z-index: 100; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1);
    }
    body.sidebar-collapsed .nav-item:hover .sidebar-tooltip { opacity: 1; margin-left: 0.5rem; }
    body.sidebar-collapsed .nav-item .sidebar-tooltip::before {
        content: ''; position: absolute; right: 100%; top: 50%; transform: translateY(-50%);
        border: 5px solid transparent; border-right-color: #1e293b;
    }

    /* Mobile handling override */
    @media (max-width: 1024px) {
        #sidebar { width: 18rem !important; }
        .sidebar-text { display: inline-block !important; opacity: 1 !important; }
        .sidebar-brand-text { display: flex !important; }
        .sidebar-group-label { display: block !important; }
        .nav-link-chevron { display: block !important; }
    }
</style>

<aside id="sidebar" class="sidebar-transition bg-white flex-shrink-0 border-r border-slate-200/60 z-50 flex flex-col h-full no-print fixed lg:static -translate-x-full lg:translate-x-0 shadow-2xl lg:shadow-none overflow-hidden">

    <!-- Brand -->
    <div class="px-6 py-8 sm:py-10 flex items-center gap-4 sidebar-transition">
        <div class="w-12 h-12 flex-shrink-0 bg-gradient-to-br from-blue-600 to-indigo-700 rounded-[18px] shadow-xl shadow-blue-200/50 flex items-center justify-center text-white text-xl font-black italic hover:rotate-6 transition-transform">
            LLW
        </div>
        <div class="flex flex-col sidebar-brand-text">
            <span class="text-xl font-black text-slate-800 tracking-tight leading-none">Platinum</span>
            <span class="text-[10px] font-black text-indigo-500 uppercase tracking-[0.2em] mt-1 opacity-70">School AI Suite</span>
        </div>
    </div>

    <!-- Navigation -->
    <nav class="flex-1 px-4 sm:px-5 py-2 space-y-1 overflow-y-auto">

        <!-- Main Portal -->
        <div class="pb-6">
            <p class="sidebar-group-label text-[10px] font-black text-slate-300 uppercase tracking-[0.2em] pl-4 mb-4">Main Portal</p>
            <a href="<?= $base_path ?>/index.php" class="nav-item flex items-center gap-4 px-5 py-3.5 rounded-2xl text-[13px] font-bold transition-all <?= $activeSystem === 'portal' && $current_page !== 'manage_advisors.php' ? 'bg-gradient-to-r from-indigo-600 to-indigo-700 text-white shadow-lg shadow-indigo-200/50' : 'text-slate-500 hover:bg-indigo-50 hover:text-indigo-600 hover:pl-6' ?>">
                <i class="bi bi-grid-fill text-lg"></i> <span class="sidebar-text">แดชบอร์ดกลาง</span>
                <span class="sidebar-tooltip">แดชบอร์ดกลาง</span>
            </a>
            <?php if ($userRole === 'super_admin'): ?>
            <a href="<?= $base_path ?>/manage_advisors.php" class="nav-item flex items-center gap-4 px-5 py-3.5 rounded-2xl text-[13px] font-bold transition-all mt-1 <?= $current_page === 'manage_advisors.php' ? 'bg-gradient-to-r from-blue-600 to-blue-700 text-white shadow-lg shadow-blue-200/50' : 'text-slate-500 hover:bg-blue-50 hover:text-blue-600 hover:pl-6' ?>">
                <i class="bi bi-people-fill text-lg"></i> <span class="sidebar-text">จัดการครูที่ปรึกษา</span>
                <span class="sidebar-tooltip">จัดการครูที่ปรึกษา</span>
            </a>
            <?php endif; ?>
        </div>

        <!-- School Information -->
        <div class="pb-6">
            <p class="sidebar-group-label text-[10px] font-black text-slate-300 uppercase tracking-[0.2em] pl-4 mb-4">School Information</p>
            <a href="<?= $base_path ?>/student_info.php" class="nav-item flex items-center gap-4 px-5 py-3.5 rounded-2xl text-[13px] font-bold transition-all <?= $activeSystem === 'info' ? 'bg-gradient-to-r from-blue-600 to-indigo-700 text-white shadow-lg shadow-blue-200/50' : 'text-slate-500 hover:bg-blue-50 hover:text-blue-600 hover:pl-6' ?>">
                <i class="bi bi-bar-chart-line-fill text-lg"></i> <span class="sidebar-text">ระบบสารสนเทศ</span>
                <span class="sidebar-tooltip">ระบบสารสนเทศ</span>
                <?php if ($activeSystem === 'info'): ?>
                <i class="nav-link-chevron bi bi-chevron-down ml-auto text-xs opacity-60"></i>
                <?php endif; ?>
            </a>
            <div class="sub-menu <?= $activeSystem === 'info' ? 'open' : '' ?> ml-6 mt-1 space-y-0.5">
                <?php foreach ($subMenus['info'] as $sub): ?>
                <a href="<?= $sub['url'] ?>" class="sub-item flex items-center gap-3 px-4 py-2.5 rounded-xl text-xs font-bold <?= $current_page === basename($sub['url']) ? 'text-blue-600 bg-blue-50' : 'text-slate-400 hover:text-blue-600 hover:bg-slate-50' ?>">
                    <i class="bi <?= $sub['icon'] ?> text-sm"></i> <?= $sub['label'] ?>
                </a>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Environment & Wellness -->
        <div class="pb-6">
            <p class="sidebar-group-label text-[10px] font-black text-slate-300 uppercase tracking-[0.2em] pl-4 mb-4">Environment & Wellness</p>

            <!-- Cleanliness System -->
            <a href="<?= $base_path ?>/cleanliness/index.php" class="nav-item flex items-center gap-4 px-5 py-3.5 rounded-2xl text-[13px] font-bold transition-all <?= $activeSystem === 'cleanliness' ? 'bg-gradient-to-r from-emerald-500 to-teal-600 text-white shadow-lg shadow-emerald-200/50' : 'text-slate-500 hover:bg-emerald-50 hover:text-emerald-600 hover:pl-6' ?>">
                <i class="bi bi-stars text-lg"></i> <span class="sidebar-text">ระบบบันทึกความสะอาด</span>
                <span class="sidebar-tooltip">ระบบบันทึกความสะอาด</span>
                <?php if ($activeSystem === 'cleanliness'): ?>
                <i class="nav-link-chevron bi bi-chevron-down ml-auto text-xs opacity-60"></i>
                <?php endif; ?>
            </a>
            <div class="sub-menu <?= $activeSystem === 'cleanliness' ? 'open' : '' ?> ml-6 mt-1 space-y-0.5">
                <?php foreach ($subMenus['cleanliness'] as $sub):
                    if (isset($sub['roles']) && !in_array($userRole, $sub['roles'])) continue;
                ?>
                <a href="<?= $sub['url'] ?>" class="sub-item flex items-center gap-3 px-4 py-2.5 rounded-xl text-xs font-bold <?= $current_page === basename($sub['url']) ? 'text-emerald-600 bg-emerald-50' : 'text-slate-400 hover:text-emerald-600 hover:bg-slate-50' ?>">
                    <i class="bi <?= $sub['icon'] ?> text-sm"></i> <?= $sub['label'] ?>
                </a>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Academic & Management -->
        <div class="pb-6">
            <p class="sidebar-group-label text-[10px] font-black text-slate-300 uppercase tracking-[0.2em] pl-4 mb-4">Academic & Management</p>

            <!-- Assembly -->
            <a href="<?= $base_path ?>/assembly/dashboard.php" class="nav-item flex items-center gap-4 px-5 py-3.5 rounded-2xl text-[13px] font-bold transition-all <?= $activeSystem === 'assembly' ? 'bg-gradient-to-r from-indigo-600 to-indigo-700 text-white shadow-lg shadow-indigo-200/50' : 'text-slate-500 hover:bg-indigo-50 hover:text-indigo-600 hover:pl-6' ?>">
                <i class="bi bi-people-fill text-lg"></i> <span class="sidebar-text">เช็คชื่อเข้าแถว</span>
                <span class="sidebar-tooltip">เช็คชื่อเข้าแถว</span>
                <?php if ($activeSystem === 'assembly'): ?>
                <i class="nav-link-chevron bi bi-chevron-down ml-auto text-xs opacity-60"></i>
                <?php endif; ?>
            </a>
            <div class="sub-menu <?= $activeSystem === 'assembly' ? 'open' : '' ?> ml-6 mt-1 space-y-0.5">
                <?php foreach ($subMenus['assembly'] as $sub):
                    if (isset($sub['roles']) && !in_array($userRole, $sub['roles'])) continue;
                ?>
                <a href="<?= $sub['url'] ?>" class="sub-item flex items-center gap-3 px-4 py-2.5 rounded-xl text-xs font-bold <?= $current_page === basename($sub['url']) ? 'text-indigo-600 bg-indigo-50' : 'text-slate-400 hover:text-indigo-600 hover:bg-slate-50' ?>">
                    <i class="bi <?= $sub['icon'] ?> text-sm"></i> <?= $sub['label'] ?>
                </a>
                <?php endforeach; ?>
            </div>

            <!-- Attendance -->
            <a href="<?= $base_path ?>/attendance_system/dashboard.php" class="nav-item flex items-center gap-4 px-5 py-3.5 rounded-2xl text-[13px] font-bold transition-all mt-1 <?= $activeSystem === 'attendance' ? 'bg-gradient-to-r from-indigo-600 to-indigo-700 text-white shadow-lg shadow-indigo-200/50' : 'text-slate-500 hover:bg-indigo-50 hover:text-indigo-600 hover:pl-6' ?>">
                <i class="bi bi-person-check-fill text-lg"></i> <span class="sidebar-text">ระบบเช็คชื่อ</span>
                <span class="sidebar-tooltip">ระบบเช็คชื่อ</span>
                <?php if ($activeSystem === 'attendance'): ?>
                <i class="nav-link-chevron bi bi-chevron-down ml-auto text-xs opacity-60"></i>
                <?php endif; ?>
            </a>
            <div class="sub-menu <?= $activeSystem === 'attendance' ? 'open' : '' ?> ml-6 mt-1 space-y-0.5">
                <?php foreach ($subMenus['attendance'] as $sub):
                    if (isset($sub['roles']) && !in_array($userRole, $sub['roles'])) continue;
                ?>
                <a href="<?= $sub['url'] ?>" class="sub-item flex items-center gap-3 px-4 py-2.5 rounded-xl text-xs font-bold <?= $current_page === basename($sub['url']) ? 'text-indigo-600 bg-indigo-50' : 'text-slate-400 hover:text-indigo-600 hover:bg-slate-50' ?>">
                    <i class="bi <?= $sub['icon'] ?> text-sm"></i> <?= $sub['label'] ?>
                </a>
                <?php endforeach; ?>
            </div>

            <!-- Chromebook -->
            <a href="<?= $base_path ?>/chromebook/index.php" class="nav-item flex items-center gap-4 px-5 py-3.5 rounded-2xl text-[13px] font-bold transition-all mt-1 <?= $activeSystem === 'chromebook' ? 'bg-gradient-to-r from-indigo-600 to-indigo-700 text-white shadow-lg shadow-indigo-200/50' : 'text-slate-500 hover:bg-indigo-50 hover:text-indigo-600 hover:pl-6' ?>">
                <i class="bi bi-laptop text-lg"></i> <span class="sidebar-text">จัดการ Chromebook</span>
                <span class="sidebar-tooltip">จัดการ Chromebook</span>
                <?php if ($activeSystem === 'chromebook'): ?>
                <i class="nav-link-chevron bi bi-chevron-down ml-auto text-xs opacity-60"></i>
                <?php endif; ?>
            </a>
            <div class="sub-menu <?= $activeSystem === 'chromebook' ? 'open' : '' ?> ml-6 mt-1 space-y-0.5">
                <?php foreach ($subMenus['chromebook'] as $sub): ?>
                <a href="<?= $sub['url'] ?>" class="sub-item flex items-center gap-3 px-4 py-2.5 rounded-xl text-xs font-bold <?= $current_page === basename($sub['url']) ? 'text-indigo-600 bg-indigo-50' : 'text-slate-400 hover:text-indigo-600 hover:bg-slate-50' ?>">
                    <i class="bi <?= $sub['icon'] ?> text-sm"></i> <?= $sub['label'] ?>
                </a>
                <?php endforeach; ?>
            </div>

            <!-- Supervision -->
            <a href="<?= $base_path ?>/supervision.php" class="nav-item flex items-center gap-4 px-5 py-3.5 rounded-2xl text-[13px] font-bold transition-all mt-1 <?= $activeSystem === 'supervision' ? 'bg-gradient-to-r from-indigo-600 to-indigo-700 text-white shadow-lg shadow-indigo-200/50' : 'text-slate-500 hover:bg-indigo-50 hover:text-indigo-600 hover:pl-6' ?>">
                <i class="bi bi-mortarboard-fill text-lg"></i> <span class="sidebar-text">นิเทศการสอน</span>
                <span class="sidebar-tooltip">นิเทศการสอน</span>
                <?php if ($activeSystem === 'supervision'): ?>
                <i class="nav-link-chevron bi bi-chevron-down ml-auto text-xs opacity-60"></i>
                <?php endif; ?>
            </a>
            <div class="sub-menu <?= $activeSystem === 'supervision' ? 'open' : '' ?> ml-6 mt-1 space-y-0.5">
                <?php foreach ($subMenus['supervision'] as $sub):
                    if (isset($sub['roles']) && !in_array($userRole, $sub['roles'])) continue;
                ?>
                <a href="<?= $sub['url'] ?>" class="sub-item flex items-center gap-3 px-4 py-2.5 rounded-xl text-xs font-bold <?= ($current_page . '?tab=' . ($_GET['tab'] ?? '')) === basename($sub['url']) ? 'text-indigo-600 bg-indigo-50' : 'text-slate-400 hover:text-indigo-600 hover:bg-slate-50' ?>">
                    <i class="bi <?= $sub['icon'] ?> text-sm"></i> <?= $sub['label'] ?>
                </a>
                <?php endforeach; ?>
            </div>

            <!-- Behavior -->
            <a href="<?= $base_path ?>/behavior/dashboard.php" class="nav-item flex items-center gap-4 px-5 py-3.5 rounded-2xl text-[13px] font-bold transition-all mt-1 <?= $activeSystem === 'behavior' ? 'bg-gradient-to-r from-violet-600 to-purple-600 text-white shadow-lg shadow-violet-200/50' : 'text-slate-500 hover:bg-slate-50 hover:text-violet-600 hover:pl-6' ?>">
                <i class="bi bi-journal-text text-lg"></i> <span class="sidebar-text">บันทึกพฤติกรรม</span>
                <span class="sidebar-tooltip">บันทึกพฤติกรรม</span>
                <?php if ($activeSystem === 'behavior'): ?>
                <i class="nav-link-chevron bi bi-chevron-down ml-auto text-xs opacity-60"></i>
                <?php endif; ?>
            </a>
            <div class="sub-menu <?= $activeSystem === 'behavior' ? 'open' : '' ?> ml-6 mt-1 space-y-0.5">
                <?php foreach ($subMenus['behavior'] as $sub):
                    if (isset($sub['roles']) && !in_array($userRole, $sub['roles'])) continue;
                ?>
                <a href="<?= $sub['url'] ?>" class="sub-item flex items-center gap-3 px-4 py-2.5 rounded-xl text-xs font-bold <?= $current_page === basename($sub['url']) ? 'text-violet-600 bg-violet-50' : 'text-slate-400 hover:text-violet-600 hover:bg-slate-50' ?>">
                    <i class="bi <?= $sub['icon'] ?> text-sm"></i> <?= $sub['label'] ?>
                </a>
                <?php endforeach; ?>
            </div>

            <!-- Homeroom -->
            <a href="<?= $base_path ?>/homeroom/index.php" class="nav-item flex items-center gap-4 px-5 py-3.5 rounded-2xl text-[13px] font-bold transition-all mt-1 <?= $activeSystem === 'homeroom' ? 'bg-gradient-to-r from-indigo-600 to-violet-700 text-white shadow-lg shadow-indigo-200/50' : 'text-slate-500 hover:bg-indigo-50 hover:text-indigo-600 hover:pl-6' ?>">
                <i class="bi bi-mortarboard-fill text-lg"></i> <span class="sidebar-text">ระบบครูที่ปรึกษา</span>
                <span class="sidebar-tooltip">ระบบครูที่ปรึกษา</span>
                <?php if ($activeSystem === 'homeroom'): ?>
                <i class="nav-link-chevron bi bi-chevron-down ml-auto text-xs opacity-60"></i>
                <?php endif; ?>
            </a>
            <div class="sub-menu <?= $activeSystem === 'homeroom' ? 'open' : '' ?> ml-6 mt-1 space-y-0.5">
                <?php foreach ($subMenus['homeroom'] as $sub):
                    if (isset($sub['roles']) && !in_array($userRole, $sub['roles'])) continue;
                ?>
                <a href="<?= $sub['url'] ?>" class="sub-item flex items-center gap-3 px-4 py-2.5 rounded-xl text-xs font-bold <?= $current_page === basename($sub['url']) ? 'text-indigo-600 bg-indigo-50' : 'text-slate-400 hover:text-indigo-600 hover:bg-slate-50' ?>">
                    <i class="bi <?= $sub['icon'] ?> text-sm"></i> <?= $sub['label'] ?>
                </a>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Staff & HR -->
        <div class="pb-6">
            <p class="sidebar-group-label text-[10px] font-black text-slate-300 uppercase tracking-[0.2em] pl-4 mb-4">Staff & Attendance</p>

            <!-- WFH -->
            <a href="<?= $base_path ?>/index_wfh.php" class="nav-item flex items-center gap-4 px-5 py-3.5 rounded-2xl text-[13px] font-bold transition-all <?= $activeSystem === 'wfh' ? 'bg-gradient-to-r from-indigo-600 to-indigo-700 text-white shadow-lg shadow-indigo-200/50' : 'text-slate-500 hover:bg-indigo-50 hover:text-indigo-600 hover:pl-6' ?>">
                <i class="bi bi-geo-alt-fill text-lg"></i> <span class="sidebar-text">ลงเวลาปฏิบัติงาน</span>
                <span class="sidebar-tooltip">ลงเวลาปฏิบัติงาน</span>
                <?php if ($activeSystem === 'wfh'): ?>
                <i class="nav-link-chevron bi bi-chevron-down ml-auto text-xs opacity-60"></i>
                <?php endif; ?>
            </a>
            <div class="sub-menu <?= $activeSystem === 'wfh' ? 'open' : '' ?> ml-6 mt-1 space-y-0.5">
                <?php foreach ($subMenus['wfh'] as $sub):
                    if (isset($sub['roles']) && !in_array($userRole, $sub['roles'])) continue;
                ?>
                <a href="<?= $sub['url'] ?>" class="sub-item flex items-center gap-3 px-4 py-2.5 rounded-xl text-xs font-bold <?= $current_page === basename($sub['url']) ? 'text-indigo-600 bg-indigo-50' : 'text-slate-400 hover:text-indigo-600 hover:bg-slate-50' ?>">
                    <i class="bi <?= $sub['icon'] ?> text-sm"></i> <?= $sub['label'] ?>
                </a>
                <?php endforeach; ?>
            </div>

            <!-- Leave -->
            <a href="<?= $base_path ?>/leave_system.php" class="nav-item flex items-center gap-4 px-5 py-3.5 rounded-2xl text-[13px] font-bold transition-all mt-1 <?= $activeSystem === 'leave' ? 'bg-gradient-to-r from-rose-600 to-pink-600 text-white shadow-lg shadow-rose-200/50' : 'text-slate-500 hover:bg-slate-50 hover:pl-6' ?>">
                <i class="bi bi-person-walking text-lg"></i> <span class="sidebar-text">ขอออกนอกบริเวณ</span>
                <span class="sidebar-tooltip">ขอออกนอกบริเวณ</span>
            </a>

            <!-- Teacher Leave -->
            <a href="<?= $base_path ?>/teacher_leave/index.php" class="nav-item flex items-center gap-4 px-5 py-3.5 rounded-2xl text-[13px] font-bold transition-all mt-1 <?= $activeSystem === 'teacher_leave' ? 'bg-gradient-to-r from-rose-600 to-pink-600 text-white shadow-lg shadow-rose-200/50' : 'text-slate-500 hover:bg-slate-50 hover:pl-6' ?>">
                <i class="bi bi-file-earmark-text-fill text-lg"></i> <span class="sidebar-text">ใบลาออนไลน์</span>
                <span class="sidebar-tooltip">ใบลาออนไลน์</span>
                <?php if ($activeSystem === 'teacher_leave'): ?>
                <i class="nav-link-chevron bi bi-chevron-down ml-auto text-xs opacity-60"></i>
                <?php endif; ?>
            </a>
            <div class="sub-menu <?= $activeSystem === 'teacher_leave' ? 'open' : '' ?> ml-6 mt-1 space-y-0.5">
                <?php foreach ($subMenus['teacher_leave'] as $sub): ?>
                <a href="<?= $sub['url'] ?>" class="sub-item flex items-center gap-3 px-4 py-2.5 rounded-xl text-xs font-bold <?= $current_page === basename($sub['url']) ? 'text-rose-600 bg-rose-50' : 'text-slate-400 hover:text-rose-600 hover:bg-slate-50' ?>">
                    <i class="bi <?= $sub['icon'] ?> text-sm"></i> <?= $sub['label'] ?>
                </a>
                <?php endforeach; ?>
            </div>
        </div>

        </div>

        <!-- Research & Development -->
        <div class="pb-6">
            <p class="sidebar-group-label text-[10px] font-black text-slate-300 uppercase tracking-[0.2em] pl-4 mb-4">Research & Development</p>

            <!-- PLC -->
            <a href="<?= $base_path ?>/plc_system/dashboard.php" class="nav-item flex items-center gap-4 px-5 py-3.5 rounded-2xl text-[13px] font-bold transition-all <?= $activeSystem === 'plc' ? 'bg-gradient-to-r from-violet-600 to-purple-600 text-white shadow-lg shadow-violet-200/50' : 'text-slate-500 hover:bg-slate-50 hover:pl-6' ?>">
                <i class="bi bi-journal-richtext text-lg"></i> <span class="sidebar-text">ระบบ PLC ออนไลน์</span>
                <span class="sidebar-tooltip">ระบบ PLC ออนไลน์</span>
                <?php if ($activeSystem === 'plc'): ?>
                <i class="nav-link-chevron bi bi-chevron-down ml-auto text-xs opacity-60"></i>
                <?php endif; ?>
            </a>
            <div class="sub-menu <?= $activeSystem === 'plc' ? 'open' : '' ?> ml-6 mt-1 space-y-0.5">
                <?php foreach ($subMenus['plc'] as $sub):
                    if (isset($sub['roles']) && !in_array($userRole, $sub['roles'])) continue;
                ?>
                <a href="<?= $sub['url'] ?>" class="sub-item flex items-center gap-3 px-4 py-2.5 rounded-xl text-xs font-bold <?= $current_page === basename($sub['url']) ? 'text-violet-600 bg-violet-50' : 'text-slate-400 hover:text-slate-600 hover:bg-slate-50' ?>">
                    <i class="bi <?= $sub['icon'] ?> text-sm"></i> <?= $sub['label'] ?>
                </a>
                <?php endforeach; ?>
            </div>
        </div>

    </nav>

    <!-- Profile -->
    <div class="p-4 sm:p-6 mt-auto">
        <!-- Full Profile Card -->
        <div class="profile-card-full p-5 bg-gradient-to-br from-slate-50 to-indigo-50/30 rounded-3xl border border-slate-100 hover:shadow-xl hover:shadow-indigo-100/30 transition-all duration-500 group">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 rounded-[14px] bg-gradient-to-br from-indigo-600 to-indigo-700 text-white flex items-center justify-center font-black text-lg shadow-lg shadow-indigo-200/50 group-hover:rotate-6 transition-transform">
                    <?= mb_substr($userName, 0, 1) ?>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-[13px] font-black text-slate-800 truncate"><?= htmlspecialchars($userName) ?></p>
                    <p class="text-[10px] font-bold text-indigo-500 uppercase tracking-widest truncate"><?= $roleName ?></p>
                </div>
            </div>
            <div class="mt-4 pt-4 border-t border-slate-200/50 flex justify-between items-center">
                <div class="flex items-center gap-3">
                    <a href="<?= $base_path ?>/change_password.php" class="flex items-center gap-1.5 text-indigo-500 font-black text-[10px] uppercase tracking-widest hover:text-indigo-700 transition-colors">
                        <i class="bi bi-key-fill"></i> Pass
                    </a>
                    <a href="<?= $base_path ?>/logout.php" class="flex items-center gap-1.5 text-rose-500 font-black text-[10px] uppercase tracking-widest hover:text-rose-700 transition-colors">
                        <i class="bi bi-power"></i> Sign Out
                    </a>
                </div>
                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
            </div>
        </div>
        
        <!-- Mini Profile Card (Visible when collapsed) -->
        <div class="profile-card-mini hidden flex-col items-center gap-3">
             <a href="<?= $base_path ?>/logout.php" class="w-12 h-12 rounded-2xl bg-rose-50 text-rose-500 flex items-center justify-center text-xl shadow-sm hover:bg-rose-500 hover:text-white transition-all">
                <i class="bi bi-power"></i>
            </a>
            <div class="w-10 h-10 rounded-xl bg-indigo-600 text-white flex items-center justify-center font-black text-sm">
                <?= mb_substr($userName, 0, 1) ?>
            </div>
        </div>
    </div>
</aside>

<!-- Mobile overlay -->
<div id="sidebar-overlay" class="fixed inset-0 bg-slate-900/40 backdrop-blur-md z-40 hidden lg:hidden transition-all duration-500"></div>

<script>
    // Hybrid Sidebar Logic
    const body = document.body;
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebar-overlay');

    // Load persisted state
    if (localStorage.getItem('sidebarState') === 'collapsed' && window.innerWidth > 1024) {
        body.classList.add('sidebar-collapsed');
    }

    function toggleSidebar() {
        if (window.innerWidth > 1024) {
            // Desktop: Toggle collapsed state
            body.classList.toggle('sidebar-collapsed');
            const state = body.classList.contains('sidebar-collapsed') ? 'collapsed' : 'expanded';
            localStorage.setItem('sidebarState', state);
        } else {
            // Mobile: Toggle off-canvas
            sidebar.classList.toggle('-translate-x-full');
            overlay.classList.toggle('hidden');
        }
    }

    overlay?.addEventListener('click', toggleSidebar);
    
    // Auto-expand on mobile if was collapsed on desktop
    window.addEventListener('resize', () => {
        if (window.innerWidth <= 1024) {
            body.classList.remove('sidebar-collapsed');
        } else {
            if (localStorage.getItem('sidebarState') === 'collapsed') {
                body.classList.add('sidebar-collapsed');
            }
        }
    });
</script>
