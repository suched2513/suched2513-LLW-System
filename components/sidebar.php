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

if (strpos($full_url, '/attendance_system/') !== false || strpos($full_url, '/student/admin/') !== false) $activeSystem = 'attendance';
elseif (strpos($full_url, '/chromebook/') !== false)        $activeSystem = 'chromebook';
elseif (strpos($full_url, '/plc_system/') !== false)        $activeSystem = 'plc';
elseif (strpos($full_url, '/assembly/') !== false)          $activeSystem = 'assembly';
elseif (strpos($full_url, '/behavior/') !== false)          $activeSystem = 'behavior';
elseif (strpos($full_url, '/homeroom/') !== false)          $activeSystem = 'homeroom';
elseif (strpos($full_url, '/school_project/') !== false)     $activeSystem = 'budget';
elseif (basename($full_url) === 'student_info.php' || basename($full_url) === 'teacher_info.php') $activeSystem = 'info';
elseif (strpos($full_url, '/bus/admin/') !== false || strpos($full_url, '/bus/finance/') !== false) $activeSystem = 'bus';
elseif (strpos($full_url, '/edocument/') !== false)        $activeSystem = 'edoc';
elseif (strpos($full_url, '/student_leave/') !== false)   $activeSystem = 'student_leave';
elseif (strpos($full_url, '/duty/') !== false)            $activeSystem = 'duty';
elseif (strpos($full_url, '/club/') !== false || strpos($full_url, '/student_club/') !== false) $activeSystem = 'club';
elseif (strpos($full_url, '/homework/') !== false)        $activeSystem = 'homework';
elseif (strpos($full_url, '/lms/') !== false)             $activeSystem = 'lms';
elseif (strpos($full_url, '/parent_meeting/') !== false)   $activeSystem = 'parent_meeting';
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
    'att_teacher' => 'Academic Staff',
    'bus_admin'   => 'Bus Administrator',
    'bus_finance' => 'Bus Finance',
    'club_admin'  => 'Club Administrator'
][$userRole] ?? 'Staff Member';

// Sub-menu definitions per module
$subMenus = [
    'assembly' => [
        ['icon' => 'fas fa-check-double',   'label' => 'เช็คชื่อเข้าแถว',   'url' => $base_path . '/assembly/dashboard.php'],
        ['icon' => 'fas fa-chart-line',     'label' => 'รายงานผู้บริหาร',   'url' => $base_path . '/assembly/admin.php',            'roles' => ['super_admin','wfh_admin']],
        ['icon' => 'fas fa-users-cog',      'label' => 'จัดการนักเรียน',    'url' => $base_path . '/assembly/manage_students.php',  'roles' => ['super_admin']],
    ],
    'student_leave' => [
        ['icon' => 'fas fa-clipboard-list', 'label' => 'รายการใบลา',  'url' => $base_path . '/student_leave/teacher.php'],
        ['icon' => 'fas fa-clock',          'label' => 'รออนุมัติ',    'url' => $base_path . '/student_leave/teacher.php?status=pending'],
    ],
    'club' => [
        ['icon' => 'fas fa-users',          'label' => 'รายชื่อชุมนุม',   'url' => $base_path . '/club/index.php'],
        ['icon' => 'fas fa-calendar-alt',   'label' => 'รายงาน',          'url' => $base_path . '/club/report.php'],
        ['icon' => 'fas fa-plus-circle',    'label' => 'สร้างชุมนุม',     'url' => $base_path . '/club/manage.php',   'roles' => ['super_admin', 'club_admin']],
        ['icon' => 'fas fa-cog',            'label' => 'ตั้งค่า',          'url' => $base_path . '/club/settings.php', 'roles' => ['super_admin', 'club_admin']],
    ],
    'attendance' => [
        ['icon' => 'fas fa-tachometer-alt', 'label' => 'Dashboard',         'url' => $base_path . '/attendance_system/dashboard.php'],
        ['icon' => 'fas fa-user-check',     'label' => 'เช็คชื่อวิชาเรียน',    'url' => $base_path . '/attendance_system/attendance.php'],
        ['icon' => 'fas fa-chart-bar',      'label' => 'รายงานการเข้าเรียน',  'url' => $base_path . '/attendance_system/report.php'],
        ['icon' => 'fas fa-file-alt',       'label' => 'รายงาน ปพ.5',          'url' => $base_path . '/attendance_system/report_p5.php'],
        ['icon' => 'fas fa-users',          'label' => 'จัดการข้อมูลวิชา',    'url' => $base_path . '/attendance_system/admin.php', 'roles' => ['super_admin','wfh_admin']],
        ['icon' => 'fas fa-user-friends',   'label' => 'จัดการข้อมูลนักเรียน', 'url' => $base_path . '/attendance_system/import_students.php', 'roles' => ['super_admin','wfh_admin']],
        ['icon' => 'fas fa-file-csv',       'label' => 'นำเข้ารายวิชา CSV',   'url' => $base_path . '/attendance_system/import_subjects.php', 'roles' => ['super_admin','wfh_admin']],
        ['icon' => 'fas fa-chart-pie',      'label' => 'รายงานผู้บริหาร',   'url' => $base_path . '/attendance_system/report_admin.php', 'roles' => ['super_admin','wfh_admin']],
        ['icon' => 'fas fa-id-card',        'label' => 'จัดการเลขบัตรประชาชน', 'url' => $base_path . '/student/admin/manage_nid.php', 'roles' => ['super_admin','wfh_admin']],
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
        ['icon' => 'fas fa-user-edit',          'label' => 'บันทึกพฤติกรรม',  'url' => $base_path . '/behavior/dashboard.php'],
        ['icon' => 'fas fa-chalkboard-teacher', 'label' => 'จัดการที่ปรึกษา', 'url' => $base_path . '/behavior/manage_advisors_ui.php', 'roles' => ['super_admin','wfh_admin']],
        ['icon' => 'fas fa-tachometer-alt',     'label' => 'Admin Dashboard',  'url' => $base_path . '/behavior/admin.php',              'roles' => ['super_admin','wfh_admin']],
    ],
    'homeroom' => [
        ['icon' => 'fas fa-tachometer-alt', 'label' => 'ระบบที่ปรึกษา', 'url' => $base_path . '/homeroom/index.php'],
    ],
    'info' => [
        ['icon' => 'fas fa-users',          'label' => 'ข้อมูลนักเรียน',      'url' => $base_path . '/student_info.php',  'roles' => ['super_admin','wfh_admin']],
        ['icon' => 'fas fa-address-card',   'label' => 'ข้อมูลครูและบุคลากร', 'url' => $base_path . '/teacher_info.php', 'roles' => ['super_admin','wfh_admin']],
    ],
    'bus' => [
        ['icon' => 'fas fa-tachometer-alt',       'label' => 'ภาพรวมระบบ',       'url' => $base_path . '/bus/admin/dashboard.php'],
        ['icon' => 'fas fa-bus',                  'label' => 'จัดการสายรถ',       'url' => $base_path . '/bus/admin/routes.php',   'roles' => ['super_admin','bus_admin','wfh_admin']],
        ['icon' => 'fas fa-user-graduate',        'label' => 'รายชื่อนักเรียน',   'url' => $base_path . '/bus/admin/students.php', 'roles' => ['super_admin','bus_admin','wfh_admin']],
        ['icon' => 'fas fa-chart-bar',            'label' => 'รายงาน',             'url' => $base_path . '/bus/admin/reports.php'],
        ['icon' => 'fas fa-file-invoice-dollar',  'label' => 'บันทึกการชำระเงิน', 'url' => $base_path . '/bus/finance/payments.php',       'roles' => ['super_admin','bus_admin','bus_finance','wfh_admin']],
        ['icon' => 'fas fa-image',                'label' => 'ตรวจสอบสลิปโอนเงิน','url' => $base_path . '/bus/finance/slips.php',           'roles' => ['super_admin','bus_admin','bus_finance','wfh_admin']],
        ['icon' => 'fas fa-times-circle',         'label' => 'คำขอยกเลิก',        'url' => $base_path . '/bus/finance/cancellations.php',  'roles' => ['super_admin','bus_admin','bus_finance','wfh_admin']],
        ['icon' => 'fas fa-poll',                 'label' => 'สำรวจการเดินทาง',   'url' => $base_path . '/student/admin/survey_report.php', 'roles' => ['super_admin','bus_admin','wfh_admin','att_teacher']],
    ],
    'edoc' => [
        ['icon' => 'fas fa-inbox',                'label' => 'หนังสือรับ',       'url' => $base_path . '/edocument/incoming.php'],
        ['icon' => 'fas fa-paper-plane',          'label' => 'หนังสือส่ง',       'url' => $base_path . '/edocument/outgoing.php'],
        ['icon' => 'fas fa-file-signature',       'label' => 'คำสั่ง',            'url' => $base_path . '/edocument/orders.php'],
        ['icon' => 'fas fa-sticky-note',          'label' => 'บันทึกข้อความ',     'url' => $base_path . '/edocument/memos.php'],
    ],
    'homework' => [
        ['icon' => 'fas fa-tachometer-alt', 'label' => 'ภาพรวมงาน',   'url' => $base_path . '/homework/dashboard.php'],
        ['icon' => 'fas fa-plus-circle',    'label' => 'สั่งงานใหม่',  'url' => $base_path . '/homework/create.php'],
        ['icon' => 'fas fa-tasks',          'label' => 'คิวตรวจงาน',   'url' => $base_path . '/homework/queue.php'],
    ],
    'lms' => [
        ['icon' => 'fas fa-tachometer-alt',    'label' => 'Dashboard LMS',  'url' => $base_path . '/lms/dashboard.php'],
        ['icon' => 'fas fa-layer-group',       'label' => 'วิชาทั้งหมด',    'url' => $base_path . '/lms/subjects.php'],
        ['icon' => 'fas fa-chart-line',        'label' => 'ความคืบหน้า',    'url' => $base_path . '/lms/progress.php'],
    ],
    'duty' => [
        ['icon' => 'fas fa-camera',              'label' => 'ส่งรายงานเวร (ครู)', 'url' => $base_path . '/duty/index.php',          'roles' => ['att_teacher','super_admin','wfh_admin']],
        ['icon' => 'fas fa-chart-line',          'label' => 'รายงานเวรประจำวัน', 'url' => $base_path . '/duty/admin/reports.php',  'roles' => ['att_teacher','super_admin','wfh_admin']],
        ['icon' => 'fas fa-calendar-week',        'label' => 'จัดการตารางเวร',   'url' => $base_path . '/duty/admin/schedule.php', 'roles' => ['super_admin','wfh_admin']],
        ['icon' => 'fas fa-layer-group',          'label' => 'จัดการกลุ่มเวร',   'url' => $base_path . '/duty/admin/groups.php',   'roles' => ['super_admin','wfh_admin']],
        ['icon' => 'fas fa-chalkboard-teacher',   'label' => 'จัดการรายชื่อครู', 'url' => $base_path . '/duty/admin/teachers.php', 'roles' => ['super_admin','wfh_admin']],
        ['icon' => 'fas fa-cog',                  'label' => 'ตั้งค่าระบบเวร',    'url' => $base_path . '/duty/admin/settings.php', 'roles' => ['super_admin']],
    ],
    'parent_meeting' => [
        ['icon' => 'fas fa-tachometer-alt',  'label' => 'Dashboard',         'url' => $base_path . '/parent_meeting/dashboard.php'],
        ['icon' => 'fas fa-handshake',        'label' => 'บันทึกการประชุม (ลลว.)', 'url' => $base_path . '/parent_meeting/meetings.php', 'roles' => ['super_admin','att_teacher','wfh_staff','cb_admin','club_admin','bus_admin','bus_finance']],
        ['icon' => 'fas fa-users-cog',        'label' => 'เครือข่ายผู้ปกครอง',     'url' => $base_path . '/parent_meeting/network.php', 'roles' => ['super_admin','att_teacher','wfh_staff','cb_admin','club_admin','bus_admin','bus_finance']],
        ['icon' => 'fas fa-file-invoice',     'label' => 'รายงานทั้งหมด',       'url' => $base_path . '/parent_meeting/reports.php', 'roles' => ['super_admin','wfh_admin']],
        ['icon' => 'fas fa-users',            'label' => 'จัดการผู้ใช้งาน',        'url' => $base_path . '/parent_meeting/users.php', 'roles' => ['super_admin']],
        ['icon' => 'fas fa-cog',              'label' => 'ตั้งค่าระบบ (ห้องเรียน)', 'url' => $base_path . '/parent_meeting/settings.php', 'roles' => ['super_admin']],
    ],

];

?>

<aside class="app-sidebar shadow">
    <!-- Sidebar Brand -->
    <div class="sidebar-brand">
        <a href="<?= $base_path ?>/index.php" class="brand-link d-flex align-items-center gap-2 px-3 py-3">
            <div style="width:34px;height:34px;background:linear-gradient(135deg,#3b82f6,#6366f1);border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0;box-shadow:0 4px 10px rgba(59,130,246,0.45);">
                <i class="fas fa-graduation-cap text-white" style="font-size:0.95rem;"></i>
            </div>
            <span class="brand-text">
                <?= ($activeSystem === 'budget') ? 'SBMS <strong>2569</strong>' : 'LLW <strong>System</strong>' ?>
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
                <?php if (in_array($userRole, ['super_admin','bus_admin','bus_finance','wfh_admin','att_teacher'])): ?>
                <li class="nav-item <?= $activeSystem === 'bus' ? 'menu-open' : '' ?>">
                    <a href="#" class="nav-link <?= $activeSystem === 'bus' ? 'active' : '' ?>">
                        <i class="nav-icon fas fa-bus text-warning"></i>
                        <p class="text-warning">ระบบรถรับส่งนักเรียน <i class="nav-arrow fas fa-angle-left"></i></p>
                    </a>
                    <ul class="nav nav-treeview">
                        <?php foreach ($subMenus['bus'] as $sub):
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
                <?php endif; ?>
                <?php if (in_array($userRole, ['super_admin', 'wfh_admin'])): ?>
                <li class="nav-item">
                    <a href="<?= $base_path ?>/manage_users.php" class="nav-link <?= $current_page === 'manage_users.php' ? 'active' : '' ?>">
                        <i class="nav-icon fas fa-user-shield"></i>
                        <p>จัดการผู้ใช้งานระบบ</p>
                    </a>
                </li>
                <?php endif; ?>
                <?php if ($userRole === 'super_admin'): ?>
                <li class="nav-item <?= $activeSystem === 'homework' ? 'menu-open' : '' ?>">
                    <a href="#" class="nav-link <?= $activeSystem === 'homework' ? 'active' : '' ?>">
                        <i class="nav-icon fas fa-book-open" style="color:#6366f1"></i>
                        <p style="color:<?= $activeSystem === 'homework' ? '#fff' : '#a5b4fc' ?>">ระบบการบ้าน <i class="nav-arrow fas fa-angle-left"></i></p>
                    </a>
                    <ul class="nav nav-treeview">
                        <?php foreach ($subMenus['homework'] as $sub): ?>
                        <li class="nav-item">
                            <a href="<?= $sub['url'] ?>" class="nav-link <?= isLinkActive($sub['url']) ? 'active' : '' ?>">
                                <i class="nav-icon <?= $sub['icon'] ?>"></i>
                                <p><?= $sub['label'] ?></p>
                            </a>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </li>
                <!-- LMS -->
                <li class="nav-item <?= $activeSystem === 'lms' ? 'menu-open' : '' ?>">
                    <a href="#" class="nav-link <?= $activeSystem === 'lms' ? 'active' : '' ?>">
                        <i class="nav-icon fas fa-chalkboard" style="color:#8B5CF6"></i>
                        <p style="color:<?= $activeSystem === 'lms' ? '#fff' : '#c4b5fd' ?>">ระบบ LMS <i class="nav-arrow fas fa-angle-left"></i></p>
                    </a>
                    <ul class="nav nav-treeview">
                        <?php foreach ($subMenus['lms'] as $sub): ?>
                        <li class="nav-item">
                            <a href="<?= $sub['url'] ?>" class="nav-link <?= isLinkActive($sub['url']) ? 'active' : '' ?>">
                                <i class="nav-icon <?= $sub['icon'] ?>"></i>
                                <p><?= $sub['label'] ?></p>
                            </a>
                        </li>
                        <?php endforeach; ?>
                    </ul>
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

                <!-- Student Leave -->
                <li class="nav-item <?= $activeSystem === 'student_leave' ? 'menu-open' : '' ?>">
                    <a href="#" class="nav-link <?= $activeSystem === 'student_leave' ? 'active' : '' ?>">
                        <i class="nav-icon fas fa-file-medical-alt"></i>
                        <p>ใบลานักเรียน <i class="nav-arrow fas fa-angle-left"></i></p>
                    </a>
                    <ul class="nav nav-treeview">
                        <?php foreach ($subMenus['student_leave'] as $sub):
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

                <!-- Club System -->
                <li class="nav-item <?= $activeSystem === 'club' ? 'menu-open' : '' ?>">
                    <a href="#" class="nav-link <?= $activeSystem === 'club' ? 'active' : '' ?>">
                        <i class="nav-icon fas fa-users"></i>
                        <p>ระบบชุมนุม <i class="nav-arrow fas fa-angle-left"></i></p>
                    </a>
                    <ul class="nav nav-treeview">
                        <?php foreach ($subMenus['club'] as $sub):
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

                <!-- Parent Meeting -->
                <li class="nav-item <?= $activeSystem === 'parent_meeting' ? 'menu-open' : '' ?>">
                    <a href="#" class="nav-link <?= $activeSystem === 'parent_meeting' ? 'active' : '' ?>">
                        <i class="nav-icon fas fa-handshake"></i>
                        <p>ระบบประชุมผู้ปกครอง <i class="nav-arrow fas fa-angle-left"></i></p>
                    </a>
                    <ul class="nav nav-treeview">
                        <?php foreach ($subMenus['parent_meeting'] as $sub): 
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

                <!-- Student Info (admin only) -->
                <?php if (in_array($userRole, ['super_admin','wfh_admin'])): ?>
                <li class="nav-item <?= $activeSystem === 'info' ? 'menu-open' : '' ?>">
                    <a href="#" class="nav-link <?= $activeSystem === 'info' ? 'active' : '' ?>">
                        <i class="nav-icon fas fa-address-book"></i>
                        <p>สารสนเทศนักเรียน/ครู <i class="nav-arrow fas fa-angle-left"></i></p>
                    </a>
                    <ul class="nav nav-treeview">
                        <?php foreach ($subMenus['info'] as $sub):
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
                <?php endif; ?>

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

                <!-- Budget (SBMS 2569) -->
                <li class="nav-item <?= $activeSystem === 'budget' ? 'menu-open' : '' ?>">
                    <a href="<?= $base_path ?>/school_project/admin/dashboard.php" class="nav-link <?= $activeSystem === 'budget' ? 'active' : '' ?>">
                        <i class="nav-icon fas fa-hand-holding-usd"></i>
                        <p>ระบบบริหารงบประมาณ <i class="nav-arrow fas fa-angle-left"></i></p>
                    </a>
                    <ul class="nav nav-treeview">
                        <li class="nav-item">
                            <a href="<?= $base_path ?>/school_project/admin/dashboard.php" class="nav-link <?= isLinkActive($base_path . '/school_project/admin/dashboard.php') ? 'active' : '' ?>">
                                <i class="nav-icon fas fa-tachometer-alt"></i>
                                <p>สรุปงบประมาณ</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="<?= $base_path ?>/school_project/admin/budget_list.php" class="nav-link <?= isLinkActive($base_path . '/school_project/admin/budget_list.php') ? 'active' : '' ?>">
                                <i class="nav-icon fas fa-tasks"></i>
                                <p>จัดการโครงการ</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="<?= $base_path ?>/school_project/teacher/request_form.php" class="nav-link <?= isLinkActive($base_path . '/school_project/teacher/request_form.php') ? 'active' : '' ?>">
                                <i class="nav-icon fas fa-file-alt"></i>
                                <p>ยื่นขออนุมัติ</p>
                            </a>
                        </li>
                    </ul>
                </li>

                <!-- e-Document -->
                <li class="nav-item <?= $activeSystem === 'edoc' ? 'menu-open' : '' ?>">
                    <a href="#" class="nav-link <?= $activeSystem === 'edoc' ? 'active' : '' ?>">
                        <i class="nav-icon fas fa-file-invoice text-info"></i>
                        <p class="text-info">ระบบงาน e-สารบรรณ <i class="nav-arrow fas fa-angle-left"></i></p>
                    </a>
                    <ul class="nav nav-treeview">
                        <?php foreach ($subMenus['edoc'] as $sub): ?>
                        <li class="nav-item">
                            <a href="<?= $sub['url'] ?>" class="nav-link <?= isLinkActive($sub['url']) ? 'active' : '' ?>">
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

                <!-- Duty System -->
                <?php if (in_array($userRole, ['super_admin','wfh_admin','att_teacher'])): ?>
                <li class="nav-item <?= $activeSystem === 'duty' ? 'menu-open' : '' ?>">
                    <a href="#" class="nav-link <?= $activeSystem === 'duty' ? 'active' : '' ?>">
                        <i class="nav-icon fas fa-shield-alt text-danger"></i>
                        <p>ระบบเวรประจำวัน <i class="nav-arrow fas fa-angle-left"></i></p>
                    </a>
                    <ul class="nav nav-treeview">
                        <?php foreach ($subMenus['duty'] as $sub):
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
                <?php endif; ?>

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
    <div class="sidebar-footer p-3">
        <div class="d-flex align-items-center gap-2 mb-2">
            <div style="width:36px;height:36px;background:linear-gradient(135deg,#2563eb,#7c3aed);border-radius:10px;display:flex;align-items:center;justify-content:center;font-weight:700;color:#fff;font-size:1rem;flex-shrink:0;">
                <?= mb_substr($userName, 0, 1, 'UTF-8') ?>
            </div>
            <div style="overflow:hidden;flex:1;">
                <div style="font-size:0.78rem;font-weight:700;color:#f1f5f9;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:140px;">
                    <?= htmlspecialchars($userName) ?>
                </div>
                <div style="font-size:0.64rem;color:rgba(148,163,184,0.65);"><?= $roleName ?></div>
            </div>
        </div>
        <a href="<?= $base_path ?>/logout.php"
           style="display:block;text-align:center;background:rgba(239,68,68,0.13);color:#fca5a5;border:1px solid rgba(239,68,68,0.22);border-radius:10px;padding:0.45rem;font-size:0.78rem;font-weight:600;text-decoration:none;transition:all 0.15s;"
           onmouseover="this.style.background='rgba(239,68,68,0.25)';this.style.color='#fff'"
           onmouseout="this.style.background='rgba(239,68,68,0.13)';this.style.color='#fca5a5'">
            <i class="fas fa-power-off me-1"></i>ออกจากระบบ
        </a>
    </div>
</aside>
