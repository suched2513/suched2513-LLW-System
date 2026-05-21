<?php
$fullname = $_SESSION['pm_fullname'] ?? 'ผู้ใช้งาน';
$role = $_SESSION['pm_role'] ?? '';

$roleName = 'ครูที่ปรึกษา';
$roleBadge = 'bg-info text-dark';
if ($role === 'admin') {
    $roleName = 'ผู้ดูแลระบบ';
    $roleBadge = 'bg-danger text-white';
} elseif ($role === 'executive') {
    $roleName = 'ผู้บริหาร';
    $roleBadge = 'bg-success text-white';
}
?>
<!-- Navbar -->
<div id="content" class="w-full">
    <nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom px-4 py-3 shadow-sm">
        <div class="container-fluid p-0">
            <!-- Toggle Sidebar -->
            <button type="button" id="sidebarCollapse" class="btn btn-outline-primary border-0 me-3">
                <i class="bi bi-justify fs-4"></i>
            </button>

            <!-- Page Title -->
            <div class="me-auto d-none d-sm-block">
                <h4 class="mb-0 font-black text-dark-blue"><?= isset($pageTitle) ? esc($pageTitle) : 'ระบบรายงานการประชุมผู้ปกครอง' ?></h4>
                <?php if (isset($pageSubtitle)): ?>
                    <small class="text-muted"><?= esc($pageSubtitle) ?></small>
                <?php endif; ?>
            </div>

            <!-- Right Info -->
            <div class="d-flex align-items-center ms-auto">
                <span class="badge <?= $roleBadge ?> rounded-pill me-2 px-3 py-1.5 text-xs font-bold"><?= $roleName ?></span>
                <div class="dropdown">
                    <a class="nav-link dropdown-toggle font-bold text-dark d-flex align-items-center" href="#" role="button" id="userMenu" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-person-circle fs-5 me-2 text-primary"></i>
                        <span><?= esc($fullname) ?></span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end shadow border-0 rounded-3 mt-2" aria-labelledby="userMenu">
                        <li>
                            <div class="dropdown-header text-muted">
                                Signed in as <strong><?= esc($_SESSION['pm_username'] ?? '') ?></strong>
                            </div>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <a class="dropdown-item text-danger d-flex align-items-center" href="<?= pm_url('logout.php') ?>">
                                <i class="bi bi-box-arrow-right me-2"></i> ออกจากระบบ
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>
    <div class="container-fluid p-4">
