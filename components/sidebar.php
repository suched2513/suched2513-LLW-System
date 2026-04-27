<?php
/**
 * sidebar.php — Reorganized AdminLTE 4 Navigation for LLW System
 */
$current_page = basename($_SERVER['PHP_SELF']);
$current_dir  = basename(dirname($_SERVER['PHP_SELF']));

// Define base path if not set
$base_path = $base_path ?? '';

// Determine active system with better precision
$full_url = $_SERVER['REQUEST_URI'];
$activeSystem = $activeSystem ?? 'portal';

if (strpos($full_url, '/attendance_system/') !== false) $activeSystem = 'attendance';
elseif (strpos($full_url, '/chromebook/') !== false)        $activeSystem = 'chromebook';
elseif (strpos($full_url, '/plc_system/') !== false)        $activeSystem = 'plc';
elseif (strpos($full_url, '/assembly/') !== false)          $activeSystem = 'assembly';
elseif (strpos($full_url, '/behavior/') !== false)          $activeSystem = 'behavior';
elseif (strpos($full_url, '/homeroom/') !== false)          $activeSystem = 'homeroom';
elseif (strpos($full_url, '/teacher_leave/') !== false)     $activeSystem = 'teacher_leave';
elseif (strpos($full_url, '/project_request/') !== false)     $activeSystem = 'budget';
elseif (strpos($full_url, '/user/') !== false || strpos($full_url, '/admin/') !== false) $activeSystem = 'wfh';
elseif (basename($full_url) === 'leave_system.php')         $activeSystem = 'leave';
elseif (basename($full_url) === 'student_info.php' || basename($full_url) === 'teacher_info.php') $activeSystem = 'info';
elseif (basename($full_url) === 'central_dashboard.php' || basename($full_url) === 'index.php')   $activeSystem = 'portal';

/**
 * Helper to check if a menu item is active
 */
function isLinkActive($url) {
    $current = $_SERVER['REQUEST_URI'];
    // Remove query string for comparison
    $current_path = parse_url($current, PHP_URL_PATH);
    $target_path = parse_url($url, PHP_URL_PATH);
    return $current_path === $target_path;
}

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
        ['icon' => 'fas fa-check-double',   'label' => 'เช็คชื่อเข้าแถว',   'url' => $base_path . '/assembly/dashboard.php'],
        ['icon' => 'fas fa-chart-line',     'label' => 'รายงานผู้บริหาร',   'url' => $base_path . '/assembly/admin.php',            'roles' => ['super_admin','wfh_admin']],
        ['icon' => 'fas fa-users-cog',      'label' => 'จัดการนักเรียน',    'url' => $base_path . '/assembly/manage_students.php',  'roles' => ['super_admin']],
    ],
    'attendance' => [
        ['icon' => 'fas fa-tachometer-alt', 'label' => 'Dashboard',         'url' => $base_path . '/attendance_system/dashboard.php'],
        ['icon' => 'fas fa-user-check',     'label' => 'เช็คชื่อวิชาเรียน',    'url' => $base_path . '/attendance_system/attendance.php'],
        ['icon' => 'fas fa-chart-bar',      'label' => 'รายงานการเข้าเรียน',  'url' => $base_path . '/attendance_system/report.php'],
        ['icon' => 'fas fa-users',          'label' => 'จัดการข้อมูลวิชา',    'url' => $base_path . '/attendance_system/admin.php'],
        ['icon' => 'fas fa-chart-pie',      'label' => 'รายงานผู้บริหาร',   'url' => $base_path . '/attendance_system/report_admin.php', 'roles' => ['super_admin','wfh_admin']],
    ],
    'chromebook' => [
        ['icon' => 'fas fa-tachometer-alt',  'label' => 'Dashboard',  'url' => $base_path . '/chromebook/index.php'],
        ['icon' => 'fas fa-exchange-alt',    'label' => 'ยืม-คืนอุปกรณ์',   'url' => $base_path . '/chromebook/dashboard.php'],
    ],
    'wfh' => [
        ['icon' => 'fas fa-tachometer-alt', 'label' => 'Admin Dashboard',  'url' => $base_path . '/admin/dashboard.php',  'roles' => ['super_admin','wfh_admin']],
        ['icon' => 'fas fa-fingerprint',    'label' => 'ลงเวลาปฏิบัติงาน',   'url' => $base_path . '/user/dashboard.php'],
        ['icon' => 'fas fa-file-invoice',   'label' => 'รายงานลงเวลา',     'url' => $base_path . '/admin/reports.php',    'roles' => ['super_admin','wfh_admin']],
        ['icon' => 'fas fa-users-cog',      'label' => 'จัดการบุคลากร',     'url' => $base_path . '/admin/manage_users.php', 'roles' => ['super_admin','wfh_admin']],
    ],
    'teacher_leave' => [
        ['icon' => 'fas fa-tachometer-alt', 'label' => 'สรุปการลา', 'url' => $base_path . '/teacher_leave/index.php'],
        ['icon' => 'fas fa-plus-circle',    'label' => 'ยื่นใบลาใหม่', 'url' => $base_path . '/teacher_leave/form.php'],
    ],
    'plc' => [
        ['icon' => 'fas fa-tachometer-alt', 'label' => 'Dashboard PLC', 'url' => $base_path . '/plc_system/dashboard.php'],
        ['icon' => 'fas fa-book-medical',   'label' => 'บันทึก PDCA', 'url' => $base_path . '/plc_system/add_log.php'],
        ['icon' => 'fas fa-file-invoice',   'label' => 'รายงานสรุป', 'url' => $base_path . '/plc_system/report_print.php'],
    ],
    'supervision' => [
        ['icon' => 'fas fa-edit',           'label' => 'บันทึกการนิเทศ', 'url' => $base_path . '/supervision.php?tab=record'],
        ['icon' => 'fas fa-id-card',        'label' => 'รายงานรายบุคคล', 'url' => $base_path . '/supervision.php?tab=individual'],
        ['icon' => 'fas fa-chart-pie',      'label' => 'รายงานภาพรวม', 'url' => $base_path . '/supervision.php?tab=summary', 'roles' => ['super_admin','wfh_admin']],
    ],
    'behavior' => [
        ['icon' => 'fas fa-user-edit',      'label' => 'บันทึกพฤติกรรม',    'url' => $base_path . '/behavior/dashboard.php'],
        ['icon' => 'fas fa-chalkboard-teacher', 'label' => 'จัดการที่ปรึกษา', 'url' => $base_path . '/behavior/manage_advisors_ui.php'],
        ['icon' => 'fas fa-tachometer-alt',   'label' => 'Admin Dashboard',   'url' => $base_path . '/behavior/admin.php',   'roles' => ['super_admin','wfh_admin']],
    ],
    'homeroom' => [
        ['icon' => 'fas fa-tachometer-alt', 'label' => 'ระบบที่ปรึกษา', 'url' => $base_path . '/homeroom/index.php'],
    ],
    'budget' => [
        ['icon' => 'fas fa-tachometer-alt', 'label' => 'สรุปงบประมาณ', 'url' => $base_path . '/project_request/admin/dashboard.php'],
        ['icon' => 'fas fa-tasks',          'label' => 'จัดการโครงการ', 'url' => $base_path . '/project_request/admin/projects.php'],
        ['icon' => 'fas fa-file-earmark-arrow-up-fill', 'label' => 'นำเข้างบประมาณ', 'url' => $base_path . '/project_request/admin/import_v2.php'],
        ['icon' => 'fas fa-file-alt',       'label' => 'ยื่นขออนุมัติ', 'url' => $base_path . '/project_request/disbursements.php'],
    ],
    'info' => [
        ['icon' => 'fas fa-users',          'label' => 'ข้อมูลนักเรียน',   'url' => $base_path . '/student_info.php'],
        ['icon' => 'fas fa-address-card',   'label' => 'ข้อมูลครูและบุคลากร', 'url' => $base_path . '/teacher_info.php'],
    ],
];

?>

<aside class="app-sidebar bg-body-secondary shadow" data-bs-theme="dark">
    <!-- Sidebar Brand -->
    <div class="sidebar-brand">
        <a href="<?= $base_path ?>/index.php" class="brand-link">
            <span class="brand-text font-weight-light">
                <?= ($activeSystem === 'budget') ? 'SBMS <strong>2569</strong>' : 'LLW <strong>Platinum</strong>' ?>
            </span>
        </a>
    </div>

    <!-- Sidebar Wrapper -->
    <div class="sidebar-wrapper">
        <nav class="mt-2">
            <ul class="nav sidebar-menu flex-column" data-lte-toggle="treeview" role="menu" data-accordion="true">
                
                <!-- 1. CORE SYSTEM -->
                <li class="nav-header">ระบบส่วนกลาง</li>
                <li class="nav-item">
                    <a href="<?= $base_path ?>/index.php" class="nav-link <?= $activeSystem === 'portal' && $current_page !== 'manage_advisors.php' ? 'active' : '' ?>">
                        <i class="nav-icon fas fa-th-large"></i>
                        <p>แดชบอร์ดกลาง</p>
                    </a>
                </li>
                <?php if ($userRole === 'super_admin'): ?>
                <li class="nav-item">
                    <a href="<?= $base_path ?>/manage_users.php" class="nav-link <?= $current_page === 'manage_users.php' ? 'active' : '' ?>">
                        <i class="nav-icon fas fa-user-shield"></i>
                        <p>จัดการผู้ใช้งานระบบ</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?= $base_path ?>/manage_advisors.php" class="nav-link <?= $current_page === 'manage_advisors.php' ? 'active' : '' ?>">
                        <i class="nav-icon fas fa-user-cog"></i>
                        <p>จัดการครูที่ปรึกษา</p>
                    </a>
                </li>
                <?php endif; ?>

                <!-- 2. ADVISOR & STUDENT -->
                <li class="nav-header">งานนักเรียนและที่ปรึกษา</li>
                
                <!-- Assembly -->
                <li class="nav-item <?= $activeSystem === 'assembly' ? 'menu-open' : '' ?>">
                    <a href="#" class="nav-link <?= $activeSystem === 'assembly' ? 'active' : '' ?>">
                        <i class="nav-icon fas fa-users-viewfinder"></i>
                        <p>เช็คชื่อเข้าแถว <i class="nav-arrow fas fa-angle-left"></i></p>
                    </a>
                    <ul class="nav nav-treeview">
                        <?php foreach ($subMenus['assembly'] as $sub): 
                             if (isset($sub['roles']) && !in_array($userRole, $sub['roles'])) continue;
                        ?>
                        <li class="nav-item">
                            <a href="<?= $sub['url'] ?>" class="nav-link <?= isLinkActive($sub['url']) ? 'active' : '' ?>">
                                <i class="nav-icon <?= $sub['icon'] ?>"></i>
                                <p><?= $sub['label'] ?></p>
                            </a>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </li>

                <!-- Behavior & Advisor -->
                <li class="nav-item <?= in_array($activeSystem, ['behavior', 'homeroom']) ? 'menu-open' : '' ?>">
                    <a href="#" class="nav-link <?= in_array($activeSystem, ['behavior', 'homeroom']) ? 'active' : '' ?>">
                        <i class="nav-icon fas fa-user-graduate"></i>
                        <p>งานที่ปรึกษา & พฤติกรรม <i class="nav-arrow fas fa-angle-left"></i></p>
                    </a>
                    <ul class="nav nav-treeview">
                        <li class="nav-item">
                            <a href="<?= $base_path ?>/homeroom/index.php" class="nav-link <?= $activeSystem === 'homeroom' ? 'active' : '' ?>">
                                <i class="nav-icon fas fa-chalkboard-teacher"></i>
                                <p>ระบบที่ปรึกษา</p>
                            </a>
                        </li>
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

                <!-- Student Info -->
                <li class="nav-item <?= $activeSystem === 'info' ? 'menu-open' : '' ?>">
                    <a href="#" class="nav-link <?= $activeSystem === 'info' ? 'active' : '' ?>">
                        <i class="nav-icon fas fa-address-book"></i>
                        <p>สารสนเทศนักเรียน/ครู <i class="nav-arrow fas fa-angle-left"></i></p>
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

                <!-- 3. ACADEMIC & TEACHING -->
                <li class="nav-header">งานวิชาการและการสอน</li>
                
                <!-- Class Attendance -->
                <li class="nav-item <?= $activeSystem === 'attendance' ? 'menu-open' : '' ?>">
                    <a href="#" class="nav-link <?= $activeSystem === 'attendance' ? 'active' : '' ?>">
                        <i class="nav-icon fas fa-user-check"></i>
                        <p>ระบบเช็คชื่อวิชาเรียน <i class="nav-arrow fas fa-angle-left"></i></p>
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

                <!-- Supervision & PLC -->
                <li class="nav-item <?= in_array($activeSystem, ['supervision', 'plc']) ? 'menu-open' : '' ?>">
                    <a href="#" class="nav-link <?= in_array($activeSystem, ['supervision', 'plc']) ? 'active' : '' ?>">
                        <i class="nav-icon fas fa-book-reader"></i>
                        <p>นิเทศ & PLC <i class="nav-arrow fas fa-angle-left"></i></p>
                    </a>
                    <ul class="nav nav-treeview">
                        <?php foreach ($subMenus['supervision'] as $sub): 
                            if (isset($sub['roles']) && !in_array($userRole, $sub['roles'])) continue;
                        ?>
                        <li class="nav-item">
                            <a href="<?= $sub['url'] ?>" class="nav-link <?= $current_page === basename($sub['url']) ? 'active' : '' ?>">
                                <i class="nav-icon <?= $sub['icon'] ?>"></i>
                                <p><?= $sub['label'] ?></p>
                            </a>
                        </li>
                        <?php endforeach; ?>
                        <?php foreach ($subMenus['plc'] as $sub): ?>
                        <li class="nav-item">
                            <a href="<?= $sub['url'] ?>" class="nav-link <?= $current_page === basename($sub['url']) ? 'active' : '' ?>">
                                <i class="nav-icon <?= $sub['icon'] ?>"></i>
                                <p><?= $sub['label'] ?></p>
                            </a>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </li>

                <!-- 4. ADMINISTRATION & ASSETS -->
                <li class="nav-header">งานบริหารและทรัพยากร</li>

                <!-- Budget -->
                <li class="nav-item <?= $activeSystem === 'budget' ? 'menu-open' : '' ?>">
                    <a href="#" class="nav-link <?= $activeSystem === 'budget' ? 'active' : '' ?>">
                        <i class="nav-icon fas fa-hand-holding-usd"></i>
                        <p>ระบบบริหารงบประมาณ <i class="nav-arrow fas fa-angle-left"></i></p>
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
                        <i class="nav-icon fas fa-laptop-code"></i>
                        <p>จัดการ Chromebook <i class="nav-arrow fas fa-angle-left"></i></p>
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

                <!-- 5. PERSONNEL & HR -->
                <li class="nav-header">งานบุคคลและสวัสดิการ</li>

                <!-- WFH & Leave -->
                <li class="nav-item <?= in_array($activeSystem, ['wfh', 'teacher_leave', 'leave']) ? 'menu-open' : '' ?>">
                    <a href="#" class="nav-link <?= in_array($activeSystem, ['wfh', 'teacher_leave', 'leave']) ? 'active' : '' ?>">
                        <i class="nav-icon fas fa-user-clock"></i>
                        <p>ลงเวลา & การลา <i class="nav-arrow fas fa-angle-left"></i></p>
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
                        <?php foreach ($subMenus['teacher_leave'] as $sub): ?>
                        <li class="nav-item">
                            <a href="<?= $sub['url'] ?>" class="nav-link <?= $current_page === basename($sub['url']) ? 'active' : '' ?>">
                                <i class="nav-icon <?= $sub['icon'] ?>"></i>
                                <p><?= $sub['label'] ?></p>
                            </a>
                        </li>
                        <?php endforeach; ?>
                        <li class="nav-item">
                            <a href="<?= $base_path ?>/leave_system.php" class="nav-link <?= $activeSystem === 'leave' ? 'active' : '' ?>">
                                <i class="nav-icon fas fa-walking"></i>
                                <p>ขอออกนอกบริเวณ</p>
                            </a>
                        </li>
                    </ul>
                </li>

            </ul>
        </nav>
    </div>
    
    <!-- Sidebar Footer -->
    <div class="sidebar-footer border-top p-3 text-center">
        <div class="d-flex align-items-center mb-2 px-2">
            <div class="bg-primary rounded-circle p-2 text-white me-2" style="width: 32px; height: 32px; display: flex; align-items: center; justify-content: center;">
                <?= mb_substr($userName, 0, 1) ?>
            </div>
            <div class="text-start overflow-hidden">
                <div class="text-xs text-white-50 text-uppercase" style="font-size: 0.65rem;"><?= $roleName ?></div>
                <div class="text-sm text-truncate" style="max-width: 140px;"><?= htmlspecialchars($userName) ?></div>
            </div>
        </div>
        <a href="<?= $base_path ?>/logout.php" class="btn btn-danger btn-sm w-100">
            <i class="fas fa-power-off me-2"></i>ออกจากระบบ
        </a>
    </div>
</aside>
