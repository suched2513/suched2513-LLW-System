<?php
$role = $_SESSION['pm_role'] ?? '';
$activePage = $activePage ?? '';
?>
<!-- Sidebar -->
<nav id="sidebar" class="bg-primary text-white shadow">
    <div class="sidebar-header p-4 border-bottom border-white/10">
        <h5 class="m-0 font-black tracking-wide text-uppercase d-flex align-items-center">
            <i class="bi bi-people-fill me-2 text-warning fs-4"></i>
            <span>Parent Meeting</span>
        </h5>
        <small class="text-white-50">ระบบบันทึกรายงานการประชุม</small>
    </div>

    <ul class="list-unstyled components p-3">
        <!-- Dashboard (มีทุกคน) -->
        <li class="nav-item mb-1">
            <a href="<?= pm_url('dashboard.php') ?>" class="nav-link px-3 py-2.5 rounded d-flex align-items-center <?= $activePage === 'dashboard' ? 'active' : '' ?>">
                <i class="bi bi-speedometer2 me-2"></i> Dashboard
            </a>
        </li>

        <!-- ครูที่ปรึกษา และ แอดมิน -->
        <?php if ($role === 'teacher' || $role === 'admin'): ?>
            <li class="nav-item mb-1">
                <a href="<?= pm_url('meetings.php') ?>" class="nav-link px-3 py-2.5 rounded d-flex align-items-center <?= $activePage === 'meetings' ? 'active' : '' ?>">
                    <i class="bi bi-journal-text me-2"></i> บันทึกการประชุม
                </a>
            </li>
            <li class="nav-item mb-1">
                <a href="<?= pm_url('network.php') ?>" class="nav-link px-3 py-2.5 rounded d-flex align-items-center <?= $activePage === 'network' ? 'active' : '' ?>">
                    <i class="bi bi-diagram-3 me-2"></i> เครือข่ายผู้ปกครอง
                </a>
            </li>
        <?php endif; ?>

        <!-- ผู้บริหาร และ แอดมิน -->
        <?php if ($role === 'executive' || $role === 'admin'): ?>
            <li class="nav-item mb-1">
                <a href="<?= pm_url('reports.php') ?>" class="nav-link px-3 py-2.5 rounded d-flex align-items-center <?= $activePage === 'reports' ? 'active' : '' ?>">
                    <i class="bi bi-file-earmark-bar-graph me-2"></i> รายงานทั้งหมด
                </a>
            </li>
        <?php endif; ?>

        <!-- แอดมิน เท่านั้น -->
        <?php if ($role === 'admin'): ?>
            <li class="border-top border-white/10 my-3"></li>
            <li class="nav-item mb-1">
                <a href="<?= pm_url('users.php') ?>" class="nav-link px-3 py-2.5 rounded d-flex align-items-center <?= $activePage === 'users' ? 'active' : '' ?>">
                    <i class="bi bi-people me-2"></i> จัดการผู้ใช้งาน
                </a>
            </li>
            <li class="nav-item mb-1">
                <a href="<?= pm_url('settings.php') ?>" class="nav-link px-3 py-2.5 rounded d-flex align-items-center <?= $activePage === 'settings' ? 'active' : '' ?>">
                    <i class="bi bi-gear me-2"></i> ตั้งค่าระบบ (ห้องเรียน)
                </a>
            </li>
        <?php endif; ?>

        <li class="border-top border-white/10 my-3"></li>
        
        <!-- กลับระบบหลัก -->
        <li class="nav-item mb-1">
            <a href="/index.php" class="nav-link px-3 py-2.5 rounded d-flex align-items-center text-white-50 hover-white">
                <i class="bi bi-arrow-left-circle me-2"></i> กลับหน้าหลักโรงเรียน
            </a>
        </li>

        <!-- Logout -->
        <li class="nav-item mb-1">
            <a href="<?= pm_url('logout.php') ?>" class="nav-link px-3 py-2.5 rounded d-flex align-items-center text-warning hover-danger">
                <i class="bi bi-box-arrow-right me-2"></i> ออกจากระบบ
            </a>
        </li>
    </ul>
</nav>
