<?php
/**
 * layout_start.php — Modern LLW Layout Wrapper
 */
require_once __DIR__ . '/header.php';
require_once __DIR__ . '/sidebar.php';

// Breadcrumb
$breadcrumbs = [];
$breadcrumbs[] = ['label' => 'Home', 'url' => $base_path . '/index.php', 'icon' => 'fas fa-home'];
$systemLabels = [
    'attendance'    => 'เช็คชื่อนักเรียน',
    'chromebook'    => 'Chromebook',
    'wfh'           => 'ลงเวลาปฏิบัติงาน',
    'leave'         => 'ขออนุญาตออกนอก',
    'portal'        => 'แดชบอร์ดกลาง',
    'budget'        => 'ระบบงบประมาณ SBMS',
    'bus'           => 'ระบบรถรับส่งนักเรียน',
    'assembly'      => 'เช็คชื่อเข้าแถว',
    'duty'          => 'ระบบเวรประจำวัน',
    'edoc'          => 'ระบบสารบรรณ',
    'club'          => 'ระบบชุมนุม',
    'behavior'      => 'งานที่ปรึกษา',
    'info'          => 'สารสนเทศ',
    'student_leave' => 'ใบลานักเรียน',
    'plc'           => 'PLC',
    'homeroom'      => 'ระบบที่ปรึกษา',
];
if (isset($activeSystem) && $activeSystem !== 'portal') {
    $breadcrumbs[] = ['label' => $systemLabels[$activeSystem] ?? $activeSystem];
}
if (isset($pageTitle)) {
    $breadcrumbs[] = ['label' => $pageTitle];
}

// Per-module gradient + icon for page header
$headerBg = [
    'portal'        => 'linear-gradient(135deg,#1d4ed8 0%,#4338ca 100%)',
    'attendance'    => 'linear-gradient(135deg,#4338ca 0%,#1d4ed8 100%)',
    'assembly'      => 'linear-gradient(135deg,#0d9488 0%,#0891b2 100%)',
    'chromebook'    => 'linear-gradient(135deg,#0891b2 0%,#1d4ed8 100%)',
    'wfh'           => 'linear-gradient(135deg,#059669 0%,#0d9488 100%)',
    'leave'         => 'linear-gradient(135deg,#e11d48 0%,#db2777 100%)',
    'student_leave' => 'linear-gradient(135deg,#e11d48 0%,#be123c 100%)',
    'duty'          => 'linear-gradient(135deg,#ea580c 0%,#d97706 100%)',
    'budget'        => 'linear-gradient(135deg,#7c3aed 0%,#6d28d9 100%)',
    'bus'           => 'linear-gradient(135deg,#d97706 0%,#ea580c 100%)',
    'edoc'          => 'linear-gradient(135deg,#0284c7 0%,#1d4ed8 100%)',
    'club'          => 'linear-gradient(135deg,#a21caf 0%,#7c3aed 100%)',
    'behavior'      => 'linear-gradient(135deg,#d97706 0%,#ea580c 100%)',
    'homeroom'      => 'linear-gradient(135deg,#d97706 0%,#b45309 100%)',
    'info'          => 'linear-gradient(135deg,#475569 0%,#334155 100%)',
    'plc'           => 'linear-gradient(135deg,#16a34a 0%,#0d9488 100%)',
];
$headerIcons = [
    'portal'        => 'fas fa-th-large',
    'attendance'    => 'fas fa-user-check',
    'assembly'      => 'fas fa-users',
    'chromebook'    => 'fas fa-laptop-code',
    'wfh'           => 'fas fa-fingerprint',
    'leave'         => 'fas fa-walking',
    'student_leave' => 'fas fa-file-medical-alt',
    'duty'          => 'fas fa-shield-alt',
    'budget'        => 'fas fa-hand-holding-usd',
    'bus'           => 'fas fa-bus',
    'edoc'          => 'fas fa-file-invoice',
    'club'          => 'fas fa-users-cog',
    'behavior'      => 'fas fa-user-graduate',
    'homeroom'      => 'fas fa-chalkboard-teacher',
    'info'          => 'fas fa-address-book',
    'plc'           => 'fas fa-book-reader',
];
$bgStyle   = $headerBg[$activeSystem]    ?? $headerBg['portal'];
$hdrIcon   = $headerIcons[$activeSystem] ?? 'fas fa-cog';
?>

<!-- ─── Top Navbar ─── -->
<nav class="app-header navbar navbar-expand bg-white border-bottom" style="box-shadow:0 1px 12px rgba(0,0,0,0.07);">
    <div class="container-fluid px-3">

        <!-- Left: toggle + breadcrumb -->
        <ul class="navbar-nav align-items-center gap-1">
            <li class="nav-item">
                <a class="nav-link px-2 rounded-lg" data-lte-toggle="sidebar" href="#" role="button"
                   style="color:#64748b;">
                    <i class="fas fa-bars"></i>
                </a>
            </li>
            <li class="nav-item d-none d-md-flex align-items-center">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0" style="font-size:0.78rem;">
                        <?php foreach ($breadcrumbs as $i => $bc): ?>
                            <?php if ($i < count($breadcrumbs) - 1): ?>
                                <li class="breadcrumb-item">
                                    <a href="<?= $bc['url'] ?? '#' ?>"
                                       style="color:#3b82f6;text-decoration:none;">
                                        <?php if (isset($bc['icon'])): ?>
                                            <i class="<?= $bc['icon'] ?> me-1" style="font-size:0.72rem;"></i>
                                        <?php endif; ?>
                                        <?= htmlspecialchars($bc['label']) ?>
                                    </a>
                                </li>
                            <?php else: ?>
                                <li class="breadcrumb-item active" style="color:#94a3b8;">
                                    <?= htmlspecialchars($bc['label']) ?>
                                </li>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </ol>
                </nav>
            </li>
        </ul>

        <!-- Right: date + user pill -->
        <ul class="navbar-nav ms-auto align-items-center gap-2">
            <li class="nav-item d-none d-lg-flex align-items-center">
                <span id="topbar-clock" style="font-size:0.75rem;color:#94a3b8;"></span>
            </li>
            <li class="nav-item dropdown">
                <a href="#" class="nav-link dropdown-toggle p-0" data-bs-toggle="dropdown" style="text-decoration:none;">
                    <div class="d-flex align-items-center gap-2 px-3 py-2 rounded-pill border"
                         style="background:#f8fafc;border-color:#e2e8f0!important;">
                        <div class="d-flex align-items-center justify-content-center rounded-circle text-white fw-bold"
                             style="width:28px;height:28px;background:linear-gradient(135deg,#2563eb,#7c3aed);font-size:0.8rem;flex-shrink:0;">
                            <?= mb_substr($userName, 0, 1, 'UTF-8') ?>
                        </div>
                        <div class="d-none d-md-block" style="line-height:1.25;">
                            <div style="font-size:0.8rem;font-weight:700;color:#334155;">
                                <?= htmlspecialchars($userName) ?>
                            </div>
                            <div style="font-size:0.65rem;color:#94a3b8;"><?= $roleName ?></div>
                        </div>
                        <i class="fas fa-chevron-down" style="font-size:0.6rem;color:#94a3b8;"></i>
                    </div>
                </a>
                <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0 rounded-3 mt-1"
                    style="min-width:200px;">
                    <li class="px-3 py-2 border-bottom">
                        <div style="font-size:0.82rem;font-weight:700;color:#1e293b;">
                            <?= htmlspecialchars($userName) ?>
                        </div>
                        <div style="font-size:0.7rem;color:#94a3b8;"><?= $roleName ?></div>
                    </li>
                    <li>
                        <a class="dropdown-item small py-2" href="<?= $base_path ?>/change_password.php">
                            <i class="fas fa-key me-2" style="color:#3b82f6;"></i>เปลี่ยนรหัสผ่าน
                        </a>
                    </li>
                    <li><hr class="dropdown-divider my-1"></li>
                    <li>
                        <a class="dropdown-item small py-2 text-danger" href="<?= $base_path ?>/logout.php">
                            <i class="fas fa-power-off me-2"></i>ออกจากระบบ
                        </a>
                    </li>
                </ul>
            </li>
        </ul>

    </div>
</nav>

<!-- ─── Main Content ─── -->
<main class="app-main">

    <!-- Page Header Banner -->
    <div style="<?= $bgStyle ?>;padding:1.4rem 1.75rem 3.8rem;position:relative;overflow:hidden;">
        <!-- Decorative circles -->
        <div style="position:absolute;top:-50px;right:-50px;width:200px;height:200px;border-radius:50%;background:rgba(255,255,255,0.07);pointer-events:none;"></div>
        <div style="position:absolute;bottom:-70px;right:100px;width:250px;height:250px;border-radius:50%;background:rgba(255,255,255,0.04);pointer-events:none;"></div>
        <div style="position:absolute;top:20px;right:200px;width:80px;height:80px;border-radius:50%;background:rgba(255,255,255,0.05);pointer-events:none;"></div>

        <div class="d-flex align-items-center gap-3" style="position:relative;z-index:1;">
            <div style="width:46px;height:46px;background:rgba(255,255,255,0.18);border-radius:14px;display:flex;align-items:center;justify-content:center;flex-shrink:0;backdrop-filter:blur(4px);">
                <i class="<?= $hdrIcon ?>" style="font-size:1.25rem;color:#fff;"></i>
            </div>
            <div>
                <h1 style="font-size:1.35rem;font-weight:900;color:#fff;margin:0;letter-spacing:-0.01em;">
                    <?= htmlspecialchars($pageTitle ?? 'Dashboard') ?>
                </h1>
                <?php if (!empty($pageSubtitle)): ?>
                <p style="font-size:0.82rem;color:rgba(255,255,255,0.78);margin:0.2rem 0 0;">
                    <?= htmlspecialchars($pageSubtitle) ?>
                </p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Content area — pulls up over the banner -->
    <div class="app-content" style="margin-top:-2.5rem;position:relative;z-index:2;">
        <div class="container-fluid">
            <!-- Page Content Starts Here -->

<script>
(function(){
    function tick(){
        const now = new Date();
        const d = ['อาทิตย์','จันทร์','อังคาร','พุธ','พฤหัสบดี','ศุกร์','เสาร์'][now.getDay()];
        const dd = String(now.getDate()).padStart(2,'0');
        const mm = String(now.getMonth()+1).padStart(2,'0');
        const yy = now.getFullYear()+543;
        const hh = String(now.getHours()).padStart(2,'0');
        const mi = String(now.getMinutes()).padStart(2,'0');
        const el = document.getElementById('topbar-clock');
        if(el) el.textContent = `${d} ${dd}/${mm}/${yy}  ${hh}:${mi}`;
    }
    tick();
    setInterval(tick, 30000);
})();
</script>
