<?php
/**
 * sidebar.php — AdminLTE 4 Navigation for LLW System
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

// Sub-menu definitions per module (Updated to FontAwesome icons)
$subMenus = [
    'assembly' => [
        ['icon' => 'fas fa-check-double',   'label' => 'เช็คชื่อเข้าแถว',   'url' => $base_path . '/assembly/dashboard.php'],
        ['icon' => 'fas fa-chart-line',     'label' => 'รายงานผู้บริหาร',   'url' => $base_path . '/assembly/admin.php',            'roles' => ['super_admin','wfh_admin']],
        ['icon' => 'fas fa-users-cog',      'label' => 'จัดการนักเรียน',    'url' => $base_path . '/assembly/manage_students.php',  'roles' => ['super_admin']],
    ],
    'attendance' => [
        ['icon' => 'fas fa-tachometer-alt', 'label' => 'Dashboard',         'url' => $base_path . '/attendance_system/dashboard.php'],
        ['icon' => 'fas fa-user-check',     'label' => 'เช็คชื่อ',          'url' => $base_path . '/attendance_system/attendance.php'],
        ['icon' => 'fas fa-chart-bar',      'label' => 'รายงาน',            'url' => $base_path . '/attendance_system/report.php'],
        ['icon' => 'fas fa-users',          'label' => 'จัดการข้อมูล',      'url' => $base_path . '/attendance_system/admin.php'],
        ['icon' => 'fas fa-chart-pie',      'label' => 'รายงานผู้บริหาร',   'url' => $base_path . '/attendance_system/report_admin.php', 'roles' => ['super_admin','wfh_admin']],
        ['icon' => 'fas fa-exclamation-triangle', 'label' => 'สรุปมส. ทั้งโรงเรียน', 'url' => $base_path . '/attendance_system/report_ms.php',    'roles' => ['super_admin','wfh_admin']],
        ['icon' => 'fas fa-download',       'label' => 'Export วิชาเลือก',  'url' => $base_path . '/attendance_system/export_elective.php', 'roles' => ['super_admin','wfh_admin']],
    ],
    'chromebook' => [
        ['icon' => 'fas fa-tachometer-alt',  'label' => 'Dashboard',  'url' => $base_path . '/chromebook/index.php'],
        ['icon' => 'fas fa-exchange-alt',    'label' => 'ยืม-คืน',     'url' => $base_path . '/chromebook/dashboard.php'],
    ],
    'wfh' => [
        ['icon' => 'fas fa-tachometer-alt', 'label' => 'Dashboard',     'url' => $base_path . '/admin/dashboard.php',  'roles' => ['super_admin','wfh_admin']],
        ['icon' => 'fas fa-clock',          'label' => 'ลงเวลา',       'url' => $base_path . '/user/dashboard.php'],
        ['icon' => 'fas fa-chart-area',     'label' => 'รายงาน',        'url' => $base_path . '/admin/reports.php',    'roles' => ['super_admin','wfh_admin']],
        ['icon' => 'fas fa-users-cog',      'label' => 'จัดการบุคลากร',  'url' => $base_path . '/admin/manage_users.php', 'roles' => ['super_admin','wfh_admin']],
        ['icon' => 'fas fa-cog',            'label' => 'ตั้งค่า',        'url' => $base_path . '/admin/settings.php',  'roles' => ['super_admin','wfh_admin']],
    ],
    'leave' => [
        ['icon' => 'fas fa-clipboard-list', 'label' => 'รายการคำขอ', 'url' => $base_path . '/leave_system.php'],
    ],
    'teacher_leave' => [
        ['icon' => 'fas fa-tachometer-alt', 'label' => 'Dashboard', 'url' => $base_path . '/teacher_leave/index.php'],
        ['icon' => 'fas fa-plus-circle',    'label' => 'ยื่นใบลาใหม่', 'url' => $base_path . '/teacher_leave/form.php'],
    ],
    'plc' => [
        ['icon' => 'fas fa-tachometer-alt', 'label' => 'Dashboard', 'url' => $base_path . '/plc_system/dashboard.php'],
        ['icon' => 'fas fa-book-medical',   'label' => 'บันทึก PDCA', 'url' => $base_path . '/plc_system/add_log.php'],
        ['icon' => 'fas fa-file-invoice',   'label' => 'รายงานสรุป', 'url' => $base_path . '/plc_system/report_print.php'],
        ['icon' => 'fas fa-user-shield',    'label' => 'Admin ภาพรวม', 'url' => $base_path . '/plc_system/admin.php', 'roles' => ['super_admin']],
    ],
    'supervision' => [
        ['icon' => 'fas fa-edit',           'label' => 'บันทึกการนิเทศ', 'url' => $base_path . '/supervision.php?tab=record'],
        ['icon' => 'fas fa-id-card',        'label' => 'รายงานรายบุคคล', 'url' => $base_path . '/supervision.php?tab=individual'],
        ['icon' => 'fas fa-chart-pie',      'label' => 'รายงานภาพรวม', 'url' => $base_path . '/supervision.php?tab=summary', 'roles' => ['super_admin','wfh_admin']],
        ['icon' => 'fas fa-user-cog',       'label' => 'ตั้งค่าข้อมูลครู', 'url' => $base_path . '/supervision.php?tab=settings', 'roles' => ['super_admin']],
    ],
    'behavior' => [
        ['icon' => 'fas fa-user-edit',      'label' => 'บันทึกพฤติกรรม',    'url' => $base_path . '/behavior/dashboard.php'],
        ['icon' => 'fas fa-chalkboard-teacher', 'label' => 'จัดการห้องที่ปรึกษา', 'url' => $base_path . '/behavior/manage_advisors_ui.php'],
        ['icon' => 'fas fa-tachometer-alt',   'label' => 'Admin Dashboard',   'url' => $base_path . '/behavior/admin.php',   'roles' => ['super_admin','wfh_admin']],
        ['icon' => 'fas fa-cogs',           'label' => 'จัดการระบบ',        'url' => $base_path . '/behavior/manage.php',  'roles' => ['super_admin']],
        ['icon' => 'fas fa-user-graduate',  'label' => 'นักเรียนดูข้อมูล',  'url' => $base_path . '/behavior/student_view.php'],
    ],
    'homeroom' => [
        ['icon' => 'fas fa-tachometer-alt', 'label' => 'Advisor Dashboard', 'url' => $base_path . '/homeroom/index.php'],
        ['icon' => 'fas fa-user-friends',   'label' => 'จัดการการมอบหมาย',  'url' => $base_path . '/manage_advisors.php', 'roles' => ['super_admin']],
    ],
    'budget' => [
        ['icon' => 'fas fa-tachometer-alt', 'label' => 'Dashboard', 'url' => $base_path . '/budget_system/index.php'],
        ['icon' => 'fas fa-tasks',          'label' => 'จัดการโครงการ', 'url' => $base_path . '/budget_system/projects.php'],
        ['icon' => 'fas fa-file-alt',       'label' => 'ยื่นขออนุมัติใช้เงิน', 'url' => $base_path . '/budget_system/disbursements.php'],
        ['icon' => 'fas fa-history',        'label' => 'ประวัติการเบิกจ่าย', 'url' => $base_path . '/budget_system/transactions.php'],
    ],
    'info' => [
        ['icon' => 'fas fa-users',          'label' => 'สารสนเทศนักเรียน',   'url' => $base_path . '/student_info.php'],
        ['icon' => 'fas fa-address-card',   'label' => 'สารสนเทศครู',        'url' => $base_path . '/teacher_info.php'],
    ],
];

function isSubMenuActive($subMenu, $currentPage) {
    foreach ($subMenu as $item) {
        if (basename($item['url']) === $currentPage) return true;
    }
    return false;
}
?>

<aside class="app-sidebar bg-body-secondary shadow" data-bs-theme="dark">
    <!-- Sidebar Brand -->
    <div class="sidebar-brand">
        <a href="<?= $base_path ?>/index.php" class="brand-link">
            <span class="brand-text font-weight-light">LLW <strong>Platinum</strong></span>
        </a>
    </div>

    <!-- Sidebar Wrapper -->
    <div class="sidebar-wrapper">
        <nav class="mt-2">
            <ul class="nav sidebar-menu flex-column" data-lte-toggle="treeview" role="menu" data-accordion="false">
                
                <li class="nav-header">MAIN PORTAL</li>
                <li class="nav-item">
                    <a href="<?= $base_path ?>/index.php" class="nav-link <?= $activeSystem === 'portal' && $current_page !== 'manage_advisors.php' ? 'active' : '' ?>">
                        <i class="nav-icon fas fa-th"></i>
                        <p>แดชบอร์ดกลาง</p>
                    </a>
                </li>
                <?php if ($userRole === 'super_admin'): ?>
                <li class="nav-item">
                    <a href="<?= $base_path ?>/manage_users.php" class="nav-link <?= $current_page === 'manage_users.php' ? 'active' : '' ?>">
                        <i class="nav-icon fas fa-user-shield"></i>
                        <p>จัดการผู้ใช้งาน</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?= $base_path ?>/manage_advisors.php" class="nav-link <?= $current_page === 'manage_advisors.php' ? 'active' : '' ?>">
                        <i class="nav-icon fas fa-users-cog"></i>
                        <p>จัดการครูที่ปรึกษา</p>
                    </a>
                </li>
                <?php endif; ?>

                <li class="nav-header">ACADEMIC & INFO</li>
                
                <!-- School Info -->
                <li class="nav-item <?= $activeSystem === 'info' ? 'menu-open' : '' ?>">
                    <a href="#" class="nav-link <?= $activeSystem === 'info' ? 'active' : '' ?>">
                        <i class="nav-icon fas fa-info-circle"></i>
                        <p>ระบบสารสนเทศ <i class="nav-arrow fas fa-angle-left"></i></p>
                    </a>
                    <ul class="nav nav-treeview">
                        <?php foreach ($subMenus['info'] as $sub): ?>
                        <li class="nav-item">
                            <a href="<?= $sub['url'] ?>" class="nav-link <?= $current_page === basename($sub['url']) ? 'active' : '' ?>">
                                <i class="nav-icon <?= $sub['icon'] ?>"></i>
                                <p><?= $sub['label'] ?></p>
                            </a>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </li>

                <!-- Attendance -->
                <li class="nav-item <?= $activeSystem === 'attendance' ? 'menu-open' : '' ?>">
                    <a href="#" class="nav-link <?= $activeSystem === 'attendance' ? 'active' : '' ?>">
                        <i class="nav-icon fas fa-calendar-check"></i>
                        <p>ระบบเช็คชื่อ <i class="nav-arrow fas fa-angle-left"></i></p>
                    </a>
                    <ul class="nav nav-treeview">
                        <?php foreach ($subMenus['attendance'] as $sub): 
                            if (isset($sub['roles']) && !in_array($userRole, $sub['roles'])) continue;
                        ?>
                        <li class="nav-item">
                            <a href="<?= $sub['url'] ?>" class="nav-link <?= $current_page === basename($sub['url']) ? 'active' : '' ?>">
                                <i class="nav-icon <?= $sub['icon'] ?>"></i>
                                <p><?= $sub['label'] ?></p>
                            </a>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </li>

                <!-- Behavior -->
                <li class="nav-item <?= $activeSystem === 'behavior' ? 'menu-open' : '' ?>">
                    <a href="#" class="nav-link <?= $activeSystem === 'behavior' ? 'active' : '' ?>">
                        <i class="nav-icon fas fa-heart"></i>
                        <p>ระบบพฤติกรรม <i class="nav-arrow fas fa-angle-left"></i></p>
                    </a>
                    <ul class="nav nav-treeview">
                        <?php foreach ($subMenus['behavior'] as $sub): 
                            if (isset($sub['roles']) && !in_array($userRole, $sub['roles'])) continue;
                        ?>
                        <li class="nav-item">
                            <a href="<?= $sub['url'] ?>" class="nav-link <?= $current_page === basename($sub['url']) ? 'active' : '' ?>">
                                <i class="nav-icon <?= $sub['icon'] ?>"></i>
                                <p><?= $sub['label'] ?></p>
                            </a>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </li>

                <li class="nav-header">MANAGEMENT</li>

                <!-- Budget -->
                <li class="nav-item <?= $activeSystem === 'budget' ? 'menu-open' : '' ?>">
                    <a href="#" class="nav-link <?= $activeSystem === 'budget' ? 'active' : '' ?>">
                        <i class="nav-icon fas fa-wallet"></i>
                        <p>ระบบงบประมาณ <i class="nav-arrow fas fa-angle-left"></i></p>
                    </a>
                    <ul class="nav nav-treeview">
                        <?php foreach ($subMenus['budget'] as $sub): ?>
                        <li class="nav-item">
                            <a href="<?= $sub['url'] ?>" class="nav-link <?= $current_page === basename($sub['url']) ? 'active' : '' ?>">
                                <i class="nav-icon <?= $sub['icon'] ?>"></i>
                                <p><?= $sub['label'] ?></p>
                            </a>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </li>

                <!-- Chromebook -->
                <li class="nav-item <?= $activeSystem === 'chromebook' ? 'menu-open' : '' ?>">
                    <a href="#" class="nav-link <?= $activeSystem === 'chromebook' ? 'active' : '' ?>">
                        <i class="nav-icon fas fa-laptop"></i>
                        <p>Chromebook <i class="nav-arrow fas fa-angle-left"></i></p>
                    </a>
                    <ul class="nav nav-treeview">
                        <?php foreach ($subMenus['chromebook'] as $sub): ?>
                        <li class="nav-item">
                            <a href="<?= $sub['url'] ?>" class="nav-link <?= $current_page === basename($sub['url']) ? 'active' : '' ?>">
                                <i class="nav-icon <?= $sub['icon'] ?>"></i>
                                <p><?= $sub['label'] ?></p>
                            </a>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </li>

                <li class="nav-header">PERSONNEL & HR</li>

                <!-- WFH -->
                <li class="nav-item <?= $activeSystem === 'wfh' ? 'menu-open' : '' ?>">
                    <a href="#" class="nav-link <?= $activeSystem === 'wfh' ? 'active' : '' ?>">
                        <i class="nav-icon fas fa-clock"></i>
                        <p>ลงเวลาปฏิบัติงาน <i class="nav-arrow fas fa-angle-left"></i></p>
                    </a>
                    <ul class="nav nav-treeview">
                        <?php foreach ($subMenus['wfh'] as $sub): 
                            if (isset($sub['roles']) && !in_array($userRole, $sub['roles'])) continue;
                        ?>
                        <li class="nav-item">
                            <a href="<?= $sub['url'] ?>" class="nav-link <?= $current_page === basename($sub['url']) ? 'active' : '' ?>">
                                <i class="nav-icon <?= $sub['icon'] ?>"></i>
                                <p><?= $sub['label'] ?></p>
                            </a>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </li>

                <!-- Teacher Leave -->
                <li class="nav-item <?= $activeSystem === 'teacher_leave' ? 'menu-open' : '' ?>">
                    <a href="#" class="nav-link <?= $activeSystem === 'teacher_leave' ? 'active' : '' ?>">
                        <i class="nav-icon fas fa-file-signature"></i>
                        <p>ใบลาออนไลน์ <i class="nav-arrow fas fa-angle-left"></i></p>
                    </a>
                    <ul class="nav nav-treeview">
                        <?php foreach ($subMenus['teacher_leave'] as $sub): ?>
                        <li class="nav-item">
                            <a href="<?= $sub['url'] ?>" class="nav-link <?= $current_page === basename($sub['url']) ? 'active' : '' ?>">
                                <i class="nav-icon <?= $sub['icon'] ?>"></i>
                                <p><?= $sub['label'] ?></p>
                            </a>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </li>

            </ul>
        </nav>
    </div>
    
    <!-- Sidebar Footer -->
    <div class="sidebar-footer border-top p-3 text-center">
        <a href="<?= $base_path ?>/logout.php" class="btn btn-danger btn-sm w-100">
            <i class="fas fa-power-off me-2"></i>ออกจากระบบ
        </a>
    </div>
</aside>
