<?php
/**
 * sidebar.php — AdminLTE 4 Premium Sidebar for LLW System
 */
$current_page = basename($_SERVER['PHP_SELF']);
$current_dir  = basename(dirname($_SERVER['PHP_SELF']));

// Determine active system
$base_path = '';
$activeSystem = $activeSystem ?? 'portal';
if ($current_dir === 'attendance_system') $activeSystem = 'attendance';
if ($current_dir === 'chromebook')        $activeSystem = 'chromebook';
if ($current_page === 'leave_system.php') $activeSystem = 'leave';
if ($current_dir === 'plc_system')        $activeSystem = 'plc';
if ($current_dir === 'lms_system')        $activeSystem = 'lms';
if ($current_dir === 'user' || $current_dir === 'admin' || $current_page === 'index_wfh.php') $activeSystem = 'wfh';
if ($current_page === 'central_dashboard.php' || $current_page === 'index.php') $activeSystem = 'portal';

// User context
$userName = $_SESSION['firstname'] ?? ($_SESSION['teacher_name'] ?? 'User');
$userRole = $_SESSION['llw_role'] ?? 'staff';
$roleName = [
    'super_admin' => 'Super Admin',
    'wfh_admin'   => 'WFH Admin',
    'wfh_staff'   => 'Personnel',
    'cb_admin'    => 'Device Manager',
    'att_teacher' => 'Academic Staff',
    'student'     => 'นักเรียน',
    'club_admin'  => 'Club Admin',
    'bus_admin'   => 'Bus Admin',
    'bus_finance' => 'Bus Finance',
][$userRole] ?? 'Staff Member';

// Sub-menu definitions per module
$subMenus = [
    'attendance' => [
        ['icon' => 'bi-speedometer2',  'label' => 'Dashboard',    'url' => $base_path . '/attendance_system/dashboard.php'],
        ['icon' => 'bi-check2-square', 'label' => 'เช็คชื่อ',       'url' => $base_path . '/attendance_system/attendance.php'],
        ['icon' => 'bi-bar-chart',     'label' => 'รายงาน',        'url' => $base_path . '/attendance_system/report.php'],
        ['icon' => 'bi-people',        'label' => 'จัดการข้อมูล',    'url' => $base_path . '/attendance_system/admin.php'],
    ],
    'chromebook' => [
        ['icon' => 'bi-speedometer2',     'label' => 'Dashboard',  'url' => $base_path . '/chromebook/index.php'],
        ['icon' => 'bi-arrow-left-right', 'label' => 'ยืม-คืน',     'url' => $base_path . '/chromebook/dashboard.php'],
    ],
    'wfh' => [
        ['icon' => 'bi-speedometer2',  'label' => 'Dashboard',     'url' => $base_path . '/admin/dashboard.php',  'roles' => ['super_admin','wfh_admin']],
        ['icon' => 'bi-clock-history', 'label' => 'ลงเวลา',        'url' => $base_path . '/user/dashboard.php'],
        ['icon' => 'bi-bar-chart',     'label' => 'รายงาน',         'url' => $base_path . '/admin/reports.php',    'roles' => ['super_admin','wfh_admin']],
        ['icon' => 'bi-people',        'label' => 'จัดการบุคลากร',  'url' => $base_path . '/admin/manage_users.php', 'roles' => ['super_admin','wfh_admin']],
        ['icon' => 'bi-gear',          'label' => 'ตั้งค่า',         'url' => $base_path . '/admin/settings.php',  'roles' => ['super_admin','wfh_admin']],
    ],
    'leave' => [
        ['icon' => 'bi-list-check', 'label' => 'รายการคำขอ', 'url' => $base_path . '/leave_system.php'],
    ],
    'plc' => [
        ['icon' => 'bi-speedometer2',            'label' => 'Dashboard',   'url' => $base_path . '/plc_system/dashboard.php'],
        ['icon' => 'bi-journal-plus',            'label' => 'บันทึก PDCA', 'url' => $base_path . '/plc_system/add_log.php'],
        ['icon' => 'bi-file-earmark-bar-graph',  'label' => 'รายงานสรุป',  'url' => $base_path . '/plc_system/report_print.php'],
    ],
    'lms' => [
        ['icon' => 'bi-folder-fill',    'label' => 'จัดการหน่วยเรียน',  'url' => $base_path . '/lms_system/manage_units.php',    'roles' => ['super_admin','att_teacher']],
        ['icon' => 'bi-bar-chart-line', 'label' => 'รายงานผลสอบ',       'url' => $base_path . '/lms_system/quiz_reports.php',    'roles' => ['super_admin','att_teacher']],
        ['icon' => 'bi-pencil-square',  'label' => 'แบบทดสอบของฉัน',   'url' => $base_path . '/lms_system/student_quizzes.php', 'roles' => ['student','super_admin']],
    ],
];

// Module color map for active sidebar items
$moduleColor = [
    'attendance' => '#4338ca',
    'chromebook' => '#0891b2',
    'wfh'        => '#059669',
    'leave'      => '#e11d48',
    'plc'        => '#7c3aed',
    'lms'        => '#7c3aed',
    'portal'     => '#1d4ed8',
];
$activeColor = $moduleColor[$activeSystem] ?? '#1d4ed8';
?>

<aside class="app-sidebar shadow" style="background:linear-gradient(175deg,#111c35 0%,#0b1426 100%);">
    <!-- Sidebar Brand -->
    <div class="sidebar-brand" style="background:rgba(0,0,0,0.35);border-bottom:1px solid rgba(255,255,255,0.07);">
        <a href="<?= $base_path ?>/index.php" class="brand-link d-flex align-items-center gap-2 px-3 py-3" style="text-decoration:none;">
            <div style="width:34px;height:34px;background:linear-gradient(135deg,#3b82f6,#6366f1);border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0;box-shadow:0 4px 10px rgba(59,130,246,0.45);">
                <i class="bi bi-mortarboard-fill text-white" style="font-size:0.95rem;"></i>
            </div>
            <span class="brand-text fw-bold text-light">LLW <strong>System</strong></span>
        </a>
    </div>

    <!-- Sidebar Wrapper -->
    <div class="sidebar-wrapper">
        <nav class="mt-2">
            <ul class="nav sidebar-menu flex-column" data-lte-toggle="treeview" role="menu" data-accordion="true">

                <!-- Section: MAIN -->
                <li class="nav-header" style="color:rgba(148,163,184,0.4);font-size:0.58rem;font-weight:800;letter-spacing:0.16em;padding:1.2rem 1rem 0.3rem;">
                    MAIN
                </li>

                <!-- Portal -->
                <li class="nav-item">
                    <a href="<?= $base_path ?>/index.php"
                       class="nav-link <?= $activeSystem === 'portal' ? 'active' : '' ?>">
                        <i class="nav-icon bi bi-grid-fill me-2"></i>
                        <p>แดชบอร์ดกลาง</p>
                    </a>
                </li>

                <!-- Section: ACADEMIC -->
                <li class="nav-header" style="color:rgba(148,163,184,0.4);font-size:0.58rem;font-weight:800;letter-spacing:0.16em;padding:1.2rem 1rem 0.3rem;">
                    ACADEMIC
                </li>

                <!-- Attendance -->
                <?php if ($userRole !== 'student'): ?>
                <li class="nav-item <?= $activeSystem === 'attendance' ? 'menu-open' : '' ?>">
                    <a href="<?= $base_path ?>/attendance_system/dashboard.php"
                       class="nav-link <?= $activeSystem === 'attendance' ? 'active' : '' ?>">
                        <i class="nav-icon bi bi-person-check-fill me-2"></i>
                        <p>
                            ระบบเช็คชื่อ
                            <i class="nav-arrow bi bi-chevron-right"></i>
                        </p>
                    </a>
                    <ul class="nav nav-treeview">
                        <?php foreach ($subMenus['attendance'] as $sub): ?>
                        <li class="nav-item">
                            <a href="<?= $sub['url'] ?>"
                               class="nav-link <?= $current_page === basename($sub['url']) ? 'active' : '' ?>">
                                <i class="nav-icon bi <?= $sub['icon'] ?> me-2"></i>
                                <p><?= $sub['label'] ?></p>
                            </a>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </li>

                <!-- Chromebook -->
                <?php if (in_array($userRole, ['super_admin','cb_admin','att_teacher'])): ?>
                <li class="nav-item <?= $activeSystem === 'chromebook' ? 'menu-open' : '' ?>">
                    <a href="<?= $base_path ?>/chromebook/index.php"
                       class="nav-link <?= $activeSystem === 'chromebook' ? 'active' : '' ?>">
                        <i class="nav-icon bi bi-laptop me-2"></i>
                        <p>
                            จัดการ Chromebook
                            <i class="nav-arrow bi bi-chevron-right"></i>
                        </p>
                    </a>
                    <ul class="nav nav-treeview">
                        <?php foreach ($subMenus['chromebook'] as $sub): ?>
                        <li class="nav-item">
                            <a href="<?= $sub['url'] ?>"
                               class="nav-link <?= $current_page === basename($sub['url']) ? 'active' : '' ?>">
                                <i class="nav-icon bi <?= $sub['icon'] ?> me-2"></i>
                                <p><?= $sub['label'] ?></p>
                            </a>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </li>
                <?php endif; ?>
                <?php endif; ?>

                <!-- LMS & Quiz -->
                <?php
                $lmsHome = ($userRole === 'student') ? '/lms_system/student_quizzes.php' : '/lms_system/manage_units.php';
                ?>
                <li class="nav-item <?= $activeSystem === 'lms' ? 'menu-open' : '' ?>">
                    <a href="<?= $base_path . $lmsHome ?>"
                       class="nav-link <?= $activeSystem === 'lms' ? 'active' : '' ?>">
                        <i class="nav-icon bi bi-journal-check me-2"></i>
                        <p>
                            LMS &amp; สอบออนไลน์
                            <i class="nav-arrow bi bi-chevron-right"></i>
                        </p>
                    </a>
                    <ul class="nav nav-treeview">
                        <?php foreach ($subMenus['lms'] as $sub):
                            if (isset($sub['roles']) && !in_array($userRole, $sub['roles'])) continue;
                        ?>
                        <li class="nav-item">
                            <a href="<?= $sub['url'] ?>"
                               class="nav-link <?= $current_page === basename($sub['url']) ? 'active' : '' ?>">
                                <i class="nav-icon bi <?= $sub['icon'] ?> me-2"></i>
                                <p><?= htmlspecialchars($sub['label']) ?></p>
                            </a>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </li>

                <?php if ($userRole !== 'student'): ?>
                <!-- Section: STAFF -->
                <li class="nav-header" style="color:rgba(148,163,184,0.4);font-size:0.58rem;font-weight:800;letter-spacing:0.16em;padding:1.2rem 1rem 0.3rem;">
                    STAFF
                </li>

                <!-- WFH -->
                <?php if (in_array($userRole, ['super_admin','wfh_admin','wfh_staff'])): ?>
                <li class="nav-item <?= $activeSystem === 'wfh' ? 'menu-open' : '' ?>">
                    <a href="<?= $base_path ?>/index_wfh.php"
                       class="nav-link <?= $activeSystem === 'wfh' ? 'active' : '' ?>">
                        <i class="nav-icon bi bi-geo-alt-fill me-2"></i>
                        <p>
                            ลงเวลาปฏิบัติงาน
                            <i class="nav-arrow bi bi-chevron-right"></i>
                        </p>
                    </a>
                    <ul class="nav nav-treeview">
                        <?php foreach ($subMenus['wfh'] as $sub):
                            if (isset($sub['roles']) && !in_array($userRole, $sub['roles'])) continue;
                        ?>
                        <li class="nav-item">
                            <a href="<?= $sub['url'] ?>"
                               class="nav-link <?= $current_page === basename($sub['url']) ? 'active' : '' ?>">
                                <i class="nav-icon bi <?= $sub['icon'] ?> me-2"></i>
                                <p><?= $sub['label'] ?></p>
                            </a>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </li>
                <?php endif; ?>

                <!-- Leave -->
                <li class="nav-item">
                    <a href="<?= $base_path ?>/leave_system.php"
                       class="nav-link <?= $activeSystem === 'leave' ? 'active' : '' ?>">
                        <i class="nav-icon bi bi-person-walking me-2"></i>
                        <p>ขอออกนอกบริเวณ</p>
                    </a>
                </li>

                <!-- Section: DEVELOPMENT -->
                <?php if (in_array($userRole, ['super_admin','att_teacher'])): ?>
                <li class="nav-header" style="color:rgba(148,163,184,0.4);font-size:0.58rem;font-weight:800;letter-spacing:0.16em;padding:1.2rem 1rem 0.3rem;">
                    DEVELOPMENT
                </li>

                <!-- PLC -->
                <li class="nav-item <?= $activeSystem === 'plc' ? 'menu-open' : '' ?>">
                    <a href="<?= $base_path ?>/plc_system/dashboard.php"
                       class="nav-link <?= $activeSystem === 'plc' ? 'active' : '' ?>">
                        <i class="nav-icon bi bi-journal-richtext me-2"></i>
                        <p>
                            ระบบ PLC ออนไลน์
                            <i class="nav-arrow bi bi-chevron-right"></i>
                        </p>
                    </a>
                    <ul class="nav nav-treeview">
                        <?php foreach ($subMenus['plc'] as $sub): ?>
                        <li class="nav-item">
                            <a href="<?= $sub['url'] ?>"
                               class="nav-link <?= $current_page === basename($sub['url']) ? 'active' : '' ?>">
                                <i class="nav-icon bi <?= $sub['icon'] ?> me-2"></i>
                                <p><?= $sub['label'] ?></p>
                            </a>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </li>
                <?php endif; ?>
                <?php endif; // end not student ?>

            </ul>
        </nav>
    </div>

    <!-- Sidebar Footer -->
    <div class="sidebar-footer p-3">
        <div class="d-flex align-items-center gap-2">
            <div style="width:32px;height:32px;border-radius:10px;background:linear-gradient(135deg,#2563eb,#7c3aed);display:flex;align-items:center;justify-content:center;font-weight:900;color:#fff;font-size:0.8rem;flex-shrink:0;">
                <?= mb_substr($userName, 0, 1, 'UTF-8') ?>
            </div>
            <div style="flex:1;min-width:0;">
                <div style="font-size:0.75rem;font-weight:700;color:#e2e8f0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                    <?= htmlspecialchars($userName) ?>
                </div>
                <div style="font-size:0.62rem;color:#64748b;"><?= $roleName ?></div>
            </div>
            <a href="<?= $base_path ?>/logout.php" title="ออกจากระบบ"
               style="color:#ef4444;font-size:1rem;flex-shrink:0;">
                <i class="bi bi-power"></i>
            </a>
        </div>
    </div>

</aside>
