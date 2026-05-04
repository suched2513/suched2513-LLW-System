<?php
/**
 * Layout สำหรับโมดูลโฮมรูม (แยกจาก school_project/budget)
 * Requires school_project/includes/layout.php for flashMessage, formatDate, etc.
 */
if (!function_exists('flashMessage')) {
    require_once __DIR__ . '/../../includes/layout.php';
}

function hrRenderHead($title = '') {
    echo '<!DOCTYPE html><html lang="th"><head>';
    echo '<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
    echo '<title>' . h($title) . ' | โฮมรูม | ' . h(defined('SCHOOL_NAME') ? SCHOOL_NAME : 'LLW') . '</title>';
    echo '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">';
    echo '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">';
    echo '<link rel="stylesheet" href="' . BASE_URL . '/assets/css/style.css">';
    echo '<style>
        :root { --hr-primary: #d97706; --hr-sidebar: #1c1917; }
        .hr-sidebar { width:240px; height:100vh; background:var(--hr-sidebar); position:fixed; top:0; left:0; z-index:100; display:flex; flex-direction:column; overflow:hidden; }
        .hr-sidebar .sidebar-brand { background: linear-gradient(135deg,#d97706,#b45309); flex-shrink:0; }
        .hr-sidebar .sidebar-nav { flex:1; overflow-y:auto; overflow-x:hidden; padding:12px 0; }
        .hr-sidebar .sidebar-nav::-webkit-scrollbar { width:4px; }
        .hr-sidebar .sidebar-nav::-webkit-scrollbar-thumb { background:rgba(255,255,255,.15); border-radius:2px; }
        .hr-sidebar .sidebar-nav a { display:flex; align-items:center; gap:10px; padding:10px 16px; color:#d6d3d1; text-decoration:none; font-size:14px; transition:all .2s; }
        .hr-sidebar .sidebar-nav a:hover,
        .hr-sidebar .sidebar-nav a.active { background:rgba(255,255,255,.08); color:#fff; border-right:3px solid var(--hr-primary); }
        .hr-sidebar .sidebar-nav a i { width:20px; text-align:center; }
        .hr-sidebar .nav-section { padding:8px 16px 4px; color:#78716c; font-size:11px; text-transform:uppercase; letter-spacing:.05em; }
        .hr-sidebar .sidebar-footer { flex-shrink:0; border-top:1px solid rgba(255,255,255,.1); padding:6px 0; }
        .hr-sidebar .sidebar-footer a { display:flex; align-items:center; gap:10px; padding:9px 16px; color:#d6d3d1; text-decoration:none; font-size:14px; transition:all .2s; }
        .hr-sidebar .sidebar-footer a:hover { background:rgba(255,255,255,.08); color:#fff; }
        @media(max-width:768px) {
            .hr-sidebar { transform:translateX(-100%); }
            .hr-sidebar.open { transform:translateX(0); }
            .main-content { margin-left:0 !important; }
        }
    </style>';
    echo '</head><body>';
}

function hrNavLink($path, $icon, $label) {
    $active = (strpos($_SERVER['PHP_SELF'] ?? '', $path) !== false) ? ' active' : '';
    return '<a href="' . BASE_URL . $path . '" class="' . $active . '"><i class="bi ' . $icon . '"></i> ' . $label . '</a>';
}

function hrRenderSidebar() {
    $u    = getCurrentUser();
    $role = $u['role'];
    $isAdmin = in_array($role, ['admin', 'super_admin']);

    echo '<div class="hr-sidebar" id="sidebar">';
    echo '<div class="sidebar-brand" style="padding:20px 16px;border-bottom:1px solid rgba(255,255,255,.15)">';
    echo '<h6 style="color:#fff;margin:0;font-size:14px;font-weight:600"><i class="bi bi-journal-text me-2"></i>ระบบโฮมรูม</h6>';
    echo '<small style="color:#fde68a;font-size:12px">' . h(defined('SCHOOL_NAME') ? SCHOOL_NAME : '') . '</small>';
    echo '</div>';

    echo '<div class="sidebar-nav">';

    if ($isAdmin) {
        echo '<div class="nav-section">ผู้ดูแล</div>';
        echo hrNavLink('/homeroom/admin/inbox.php', 'bi-inbox', 'Inbox รายงาน');
        echo hrNavLink('/homeroom/reports/list.php', 'bi-journal-text', 'รายงานทั้งหมด');
    } else {
        echo '<div class="nav-section">รายงานโฮมรูม</div>';
        echo hrNavLink('/homeroom/reports/list.php', 'bi-journal-text', 'รายงานของฉัน');
        echo hrNavLink('/homeroom/reports/edit.php', 'bi-plus-circle', 'สร้างรายงานใหม่');
    }

    echo '</div>'; // sidebar-nav

    echo '<div class="sidebar-footer">';
    echo '<a href="' . BASE_URL . '/index.php"><i class="bi bi-grid-fill"></i> กลับระบบงบประมาณ</a>';
    echo '<a href="/index.php"><i class="bi bi-house"></i> หน้าหลัก LLW</a>';
    echo '<a href="' . BASE_URL . '/logout.php"><i class="bi bi-box-arrow-right text-danger"></i> <span class="text-danger">ออกจากระบบ</span></a>';
    echo '</div>';
    echo '</div>'; // hr-sidebar
}

function hrRenderTopbar($title) {
    $u = getCurrentUser();
    $notifs = getUnreadNotifications($u['id']);
    $nCount = count($notifs);
    echo '<div class="topbar"><div class="d-flex align-items-center gap-2">';
    echo '<button class="btn btn-sm btn-outline-secondary d-md-none" onclick="document.getElementById(\'sidebar\').classList.toggle(\'open\')"><i class="bi bi-list"></i></button>';
    echo '<span class="topbar-title">' . h($title) . '</span></div>';
    echo '<div class="d-flex align-items-center gap-3"><div class="dropdown">';
    echo '<button class="btn btn-sm btn-outline-secondary position-relative" data-bs-toggle="dropdown"><i class="bi bi-bell"></i>';
    if ($nCount > 0) echo '<span class="notification-badge position-absolute top-0 start-100 translate-middle">' . $nCount . '</span>';
    echo '</button><div class="dropdown-menu dropdown-menu-end" style="width:300px;max-height:380px;overflow-y:auto">';
    if (empty($notifs)) echo '<div class="dropdown-item text-muted small">ไม่มีการแจ้งเตือน</div>';
    foreach ($notifs as $n) {
        echo '<a class="dropdown-item py-2" href="' . BASE_URL . '/api/notifications.php?read=' . $n['id'] . '">';
        echo '<div style="font-size:13px;font-weight:500">' . h($n['title']) . '</div>';
        echo '<div style="font-size:12px;color:#64748b">' . h($n['message']) . '</div></a>';
    }
    echo '</div></div>';
    echo '<span style="font-size:13px;color:#64748b"><i class="bi bi-person-circle me-1"></i>' . h($u['full_name']) . '</span>';
    echo '</div></div>';
}

function hrRenderFooter() {
    echo '<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>';
    echo '<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>';
    echo '</body></html>';
}

function hrShowFlash() {
    if (!empty($_SESSION['flash'])) {
        $f = $_SESSION['flash']; unset($_SESSION['flash']);
        echo '<div class="alert alert-' . $f['type'] . ' alert-dismissible mb-3 d-flex align-items-center gap-2">';
        echo h($f['msg']) . '<button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button></div>';
    }
}
