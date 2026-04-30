<?php
/**
 * layout_start.php — AdminLTE 4 Layout Wrapper for LLW System
 */
require_once __DIR__ . '/header.php';
require_once __DIR__ . '/sidebar.php';

// Breadcrumb logic
$breadcrumbs = [];
$breadcrumbs[] = ['label' => 'Home', 'url' => $base_path . '/index.php', 'icon' => 'fas fa-home'];
$systemLabels = [
    'attendance' => 'เช็คชื่อนักเรียน',
    'chromebook' => 'Chromebook',
    'wfh'        => 'ลงเวลาปฏิบัติงาน',
    'leave'      => 'ขออนุญาตออกนอก',
    'portal'     => 'แดชบอร์ดกลาง',
    'budget'     => 'ระบบงบประมาณ SBMS',
    'bus'        => 'ระบบรถรับส่งนักเรียน',
];
if (isset($activeSystem) && $activeSystem !== 'portal') {
    $breadcrumbs[] = ['label' => $systemLabels[$activeSystem] ?? $activeSystem];
}
if (isset($pageTitle)) {
    $breadcrumbs[] = ['label' => $pageTitle];
}
?>

<!-- Top Navbar -->
<nav class="app-header navbar navbar-expand bg-body shadow-sm">
    <div class="container-fluid">
        <!-- Start navbar links -->
        <ul class="navbar-nav">
            <li class="nav-item">
                <a class="nav-link" data-lte-toggle="sidebar" href="#" role="button">
                    <i class="fas fa-bars"></i>
                </a>
            </li>
            <li class="nav-item d-none d-md-block">
                <a href="<?= $base_path ?>/index.php" class="nav-link">Home</a>
            </li>
        </ul>
        
        <!-- End navbar links -->
        <ul class="navbar-nav ms-auto">
            <!-- Notifications Dropdown -->
            <li class="nav-item dropdown">
                <a class="nav-link" data-bs-toggle="dropdown" href="#">
                    <i class="fas fa-bell"></i>
                    <span class="navbar-badge badge text-bg-warning">3</span>
                </a>
                <div class="dropdown-menu dropdown-menu-lg dropdown-menu-end">
                    <span class="dropdown-item dropdown-header">3 Notifications</span>
                    <div class="dropdown-divider"></div>
                    <a href="#" class="dropdown-item">
                        <i class="fas fa-envelope me-2"></i> 4 new messages
                    </a>
                    <div class="dropdown-divider"></div>
                    <a href="#" class="dropdown-item dropdown-footer">See All Notifications</a>
                </div>
            </li>
            
            <!-- User Menu -->
            <li class="nav-item dropdown user-menu">
                <a href="#" class="nav-link dropdown-toggle" data-bs-toggle="dropdown">
                    <span class="d-none d-md-inline"><?= htmlspecialchars($userName) ?></span>
                </a>
                <ul class="dropdown-menu dropdown-menu-lg dropdown-menu-end">
                    <!-- User image -->
                    <li class="user-header text-bg-primary">
                        <p>
                            <?= htmlspecialchars($userName) ?> - <?= $roleName ?>
                        </p>
                    </li>
                    <!-- Menu Footer-->
                    <li class="user-footer">
                        <a href="<?= $base_path ?>/change_password.php" class="btn btn-default btn-flat">Profile</a>
                        <a href="<?= $base_path ?>/logout.php" class="btn btn-default btn-flat float-end">Sign out</a>
                    </li>
                </ul>
            </li>
        </ul>
    </div>
</nav>

<!-- Main Content Wrapper -->
<main class="app-main">
    <!-- Content Header (Page header) -->
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row">
                <div class="col-sm-6">
                    <h3 class="mb-0"><?= htmlspecialchars($pageTitle ?? 'Dashboard') ?></h3>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <?php foreach ($breadcrumbs as $i => $bc): ?>
                            <?php if ($i < count($breadcrumbs) - 1): ?>
                                <li class="breadcrumb-item">
                                    <a href="<?= $bc['url'] ?? '#' ?>">
                                        <?php if (isset($bc['icon'])): ?><i class="<?= $bc['icon'] ?> me-1"></i><?php endif; ?>
                                        <?= htmlspecialchars($bc['label']) ?>
                                    </a>
                                </li>
                            <?php else: ?>
                                <li class="breadcrumb-item active" aria-current="page">
                                    <?= htmlspecialchars($bc['label']) ?>
                                </li>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </ol>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Main Content -->
    <div class="app-content">
        <div class="container-fluid">
            <!-- Page Content Starts Here -->
