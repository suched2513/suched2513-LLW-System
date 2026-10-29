<?php
$schoolName = \App\Services\SettingService::get('school_name', 'โรงเรียนละลมวิทยา');
$schoolLogo = \App\Services\SettingService::get('school_logo_path', '');
$activeFyBe = $activeFiscalYear['year_be'] ?? thai_fiscal_year();
$currentRoute = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
$baseUrl = defined('BASE_URL') ? BASE_URL : '';
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle ?? 'ระบบบริหารการเงินและบัญชีสถานศึกษา') ?> - <?= e($schoolName) ?></title>
    <!-- Google Fonts: Sarabun -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <!-- Custom Style -->
    <link rel="stylesheet" href="<?= $baseUrl ?>/assets/css/style.css">
</head>
<body>
<div class="main-wrapper">
    <!-- Sidebar -->
    <nav id="sidebar" class="d-flex flex-column flex-shrink-0">
        <a href="<?= $baseUrl ?>/dashboard" class="sidebar-brand">
            <span class="gold-emblem"><i class="bi bi-bank2"></i></span>
            <div class="lh-sm">
                <div class="fw-bold" style="font-size: 0.95rem;">ระบบบริหารการเงิน</div>
                <div class="text-white-50" style="font-size: 0.75rem;"><?= e($schoolName) ?></div>
            </div>
        </a>

        <div class="overflow-y-auto flex-grow-1 py-2">
            <div class="nav-header">ภาพรวม</div>
            <a href="<?= $baseUrl ?>/dashboard" class="nav-link <?= str_ends_with($currentRoute, '/dashboard') ? 'active' : '' ?>">
                <i class="bi bi-speedometer2"></i> แดชบอร์ด
            </a>

            <div class="nav-header">การเงินและงบประมาณ</div>
            <a href="<?= $baseUrl ?>/receipts" class="nav-link <?= str_contains($currentRoute, '/receipts') ? 'active' : '' ?>">
                <i class="bi bi-wallet2"></i> ระบบรับเงิน
            </a>
            <a href="<?= $baseUrl ?>/disbursements" class="nav-link <?= str_contains($currentRoute, '/disbursements') ? 'active' : '' ?>">
                <i class="bi bi-cash-stack"></i> เบิกจ่ายเงิน
            </a>
            <a href="<?= $baseUrl ?>/advances" class="nav-link <?= str_contains($currentRoute, '/advances') ? 'active' : '' ?>">
                <i class="bi bi-journal-arrow-up"></i> เงินยืมและล้างยืม
            </a>
            <a href="<?= $baseUrl ?>/budgets" class="nav-link <?= str_contains($currentRoute, '/budgets') ? 'active' : '' ?>">
                <i class="bi bi-pie-chart"></i> บริหารงบประมาณ
            </a>
            <a href="<?= $baseUrl ?>/budgets/documents" class="nav-link <?= (str_contains($currentRoute, '/budgets/documents') || str_contains($currentRoute, '/phong')) ? 'active' : '' ?>">
                <i class="bi bi-file-earmark-ruled"></i> ทะเบียนเอกสาร ผง.
            </a>
            <a href="<?= $baseUrl ?>/funding-sources" class="nav-link <?= str_contains($currentRoute, '/funding-sources') ? 'active' : '' ?>">
                <i class="bi bi-tags"></i> ประเภทการเงิน
            </a>
            <a href="<?= $baseUrl ?>/banks" class="nav-link <?= str_contains($currentRoute, '/banks') ? 'active' : '' ?>">
                <i class="bi bi-credit-card-2-front"></i> ธนาคารและกระทบยอด
            </a>
            <a href="<?= $baseUrl ?>/special-programs" class="nav-link <?= str_contains($currentRoute, '/special-programs') ? 'active' : '' ?>">
                <i class="bi bi-mortarboard"></i> ค่าบำรุงการศึกษา
            </a>

            <div class="nav-header">บัญชีและพัสดุ</div>
            <a href="<?= $baseUrl ?>/accounts" class="nav-link <?= str_contains($currentRoute, '/accounts') ? 'active' : '' ?>">
                <i class="bi bi-book"></i> บัญชีและสมุดรายวัน
            </a>
            <a href="<?= $baseUrl ?>/procurement" class="nav-link <?= str_contains($currentRoute, '/procurement') ? 'active' : '' ?>">
                <i class="bi bi-cart-check"></i> จัดซื้อจัดจ้าง
            </a>
            <a href="<?= $baseUrl ?>/inventory" class="nav-link <?= str_contains($currentRoute, '/inventory') ? 'active' : '' ?>">
                <i class="bi bi-boxes"></i> พัสดุและครุภัณฑ์
            </a>
            <a href="<?= $baseUrl ?>/reports" class="nav-link <?= str_contains($currentRoute, '/reports') ? 'active' : '' ?>">
                <i class="bi bi-file-earmark-bar-graph"></i> เอกสารและ 28 รายงาน
            </a>

            <div class="nav-header">จัดการระบบ</div>
            <a href="<?= $baseUrl ?>/users" class="nav-link <?= str_contains($currentRoute, '/users') ? 'active' : '' ?>">
                <i class="bi bi-people"></i> ผู้ใช้งานและสิทธิ์
            </a>
            <a href="<?= $baseUrl ?>/fiscal-years" class="nav-link <?= str_contains($currentRoute, '/fiscal-years') ? 'active' : '' ?>">
                <i class="bi bi-calendar-range"></i> ปีงบประมาณ/งวดบัญชี
            </a>
            <a href="<?= $baseUrl ?>/audit-logs" class="nav-link <?= str_contains($currentRoute, '/audit-logs') ? 'active' : '' ?>">
                <i class="bi bi-clock-history"></i> ประวัติการใช้งาน
            </a>
            <a href="<?= $baseUrl ?>/settings" class="nav-link <?= str_contains($currentRoute, '/settings') ? 'active' : '' ?>">
                <i class="bi bi-gear"></i> ตั้งค่าสถานศึกษา
            </a>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="content-area">
        <!-- Top Navbar -->
        <header class="top-navbar d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center gap-3">
                <button class="btn btn-sm btn-outline-secondary d-lg-none" id="sidebarToggle" type="button" aria-label="Toggle navigation">
                    <i class="bi bi-list fs-5"></i>
                </button>
                <div class="fiscal-year-badge">
                    <i class="bi bi-calendar-check-fill"></i>
                    <span>ปีงบประมาณ พ.ศ. <?= e($activeFyBe) ?></span>
                </div>
            </div>

            <div class="d-flex align-items-center gap-3">
                <div class="dropdown">
                    <a href="#" class="d-flex align-items-center text-dark text-decoration-none dropdown-toggle" id="userDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                        <div class="avatar-circle bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-2" style="width: 34px; height: 34px; font-weight: 600;">
                            <?= mb_substr($currentUser['full_name'] ?? 'U', 0, 1) ?>
                        </div>
                        <div class="d-none d-md-block text-start lh-sm">
                            <div class="fw-semibold" style="font-size: 0.9rem;"><?= e($currentUser['full_name'] ?? 'ผู้ใช้งาน') ?></div>
                            <div class="text-muted" style="font-size: 0.75rem;"><?= e(implode(', ', $currentUser['role_names'] ?? ['-'])) ?></div>
                        </div>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end shadow-sm" aria-labelledby="userDropdown">
                        <li><a class="dropdown-item" href="<?= $baseUrl ?>/profile"><i class="bi bi-person me-2"></i> ข้อมูลส่วนตัว</a></li>
                        <li><a class="dropdown-item" href="<?= $baseUrl ?>/settings"><i class="bi bi-gear me-2"></i> ตั้งค่าระบบ</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger" href="<?= $baseUrl ?>/logout"><i class="bi bi-box-arrow-right me-2"></i> ออกจากระบบ</a></li>
                    </ul>
                </div>
            </div>
        </header>

        <!-- Body Content -->
        <main class="page-body">
            <!-- Flash Message Banner -->
            <?php if (!empty($flash)): ?>
                <div class="alert alert-<?= e($flash['type']) ?> alert-dismissible fade show shadow-sm" role="alert">
                    <div class="d-flex align-items-center">
                        <i class="bi <?= $flash['type'] === 'success' ? 'bi-check-circle-fill' : ($flash['type'] === 'danger' ? 'bi-exclamation-triangle-fill' : 'bi-info-circle-fill') ?> me-2"></i>
                        <div><?= e($flash['message']) ?></div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <?= $content ?>
        </main>

        <!-- Footer -->
        <footer class="gov-footer d-flex flex-wrap justify-content-between align-items-center">
            <div>
                © <?= thai_fiscal_year() ?> <?= e($schoolName) ?> — ระบบบริหารการเงินและบัญชีสถานศึกษา
            </div>
            <div class="text-muted">
                เวอร์ชัน 1.0.0 (Release)
            </div>
        </footer>
    </div>
</div>

<!-- Bootstrap 5 JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<!-- Custom App JS -->
<script src="<?= $baseUrl ?>/assets/js/app.js"></script>
</body>
</html>
