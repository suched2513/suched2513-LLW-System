<?php
function renderHead($title = '') {
    $school = SCHOOL_NAME;
    echo '<!DOCTYPE html><html lang="th"><head>';
    echo '<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
    echo '<title>' . h($title) . ' | ' . h($school) . '</title>';
    echo '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">';
    echo '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">';
    echo '<link rel="stylesheet" href="' . BASE_URL . '/assets/css/style.css">';
    echo '</head><body>';
}
function navLink($path, $icon, $label, $active) {
    $cls = (strpos($_SERVER['PHP_SELF'] ?? '', $path) !== false || $active === $path) ? ' active' : '';
    return '<a href="' . BASE_URL . $path . '" class="' . $cls . '"><i class="bi ' . $icon . '"></i> ' . $label . '</a>';
}
function renderSidebar($activePage = '') {
    $u = getCurrentUser(); $role = $u['role'];
    // Map platform role to local functional role for sidebar display
    $displayRole = $role;
    if ($role === 'wfh_admin') $displayRole = 'budget_officer';
    if (in_array($role, ['procurement_head', 'finance_head', 'deputy_director'])) $displayRole = 'approver';
    
    echo '<div class="sidebar" id="sidebar">';
    echo '<div class="sidebar-brand"><h6><i class="bi bi-mortarboard-fill me-2"></i>' . SCHOOL_NAME . '</h6>';
    echo '<small>' . SCHOOL_DISTRICT . ' ' . SCHOOL_PROVINCE . '</small></div>';
    echo '<div class="sidebar-nav">';
    
    if (in_array($role, ['admin', 'super_admin'])) {
        echo '<div class="nav-section">ภาพรวม</div>';
        echo navLink('/admin/dashboard.php','bi-speedometer2','Dashboard',$activePage);
        echo navLink('/admin/all_requests.php','bi-inbox','คำขอทั้งหมด',$activePage);
        echo navLink('/admin/budget_list.php','bi-table','งบประมาณ',$activePage);
        echo navLink('/director/pending.php','bi-hourglass-split','รออนุมัติ',$activePage);
    }
    elseif ($displayRole === 'budget_officer') {
        echo '<div class="nav-section">ภาพรวม</div>';
        echo navLink('/dashboard/budget_officer.php','bi-speedometer2','Dashboard',$activePage);
        echo navLink('/director/pending.php','bi-hourglass-split','รออนุมัติ',$activePage);
        echo navLink('/admin/all_requests.php','bi-inbox','คำขอทั้งหมด',$activePage);
        echo navLink('/admin/budget_list.php','bi-table','งบประมาณ',$activePage);
    }
    elseif ($displayRole === 'approver' || $role === 'director') {
        echo '<div class="nav-section">ภาพรวม</div>';
        if ($role === 'director') echo navLink('/dashboard/director.php','bi-speedometer2','Dashboard',$activePage);
        echo navLink('/director/pending.php','bi-hourglass-split','รออนุมัติ',$activePage);
        echo navLink('/admin/all_requests.php','bi-inbox','ประวัติคำขอ',$activePage);
    }
    else {
        echo '<div class="nav-section">โครงการของฉัน</div>';
        echo navLink('/teacher/my_projects.php','bi-folder2-open','โครงการของฉัน',$activePage);
        echo navLink('/teacher/request_list.php','bi-list-check','ประวัติคำขอ',$activePage);
    }

    if (in_array($role, ['admin','super_admin','budget_officer','wfh_admin','director','procurement_head','finance_head','deputy_director'])) {
        echo '<div class="nav-section">รายงาน</div>';
        echo navLink('/reports/budget_overview.php','bi-bar-chart','ภาพรวมงบประมาณ',$activePage);
        echo navLink('/reports/project_progress.php','bi-clipboard-data','ความคืบหน้า',$activePage);
        echo navLink('/reports/annual_summary.php','bi-file-earmark-bar-graph','สรุปประจำปี',$activePage);
    }

    if (in_array($role, ['admin', 'super_admin'])) {
        echo '<div class="nav-section">ตั้งค่า</div>';
        echo navLink('/admin/users.php','bi-people','จัดการผู้ใช้',$activePage);
        echo navLink('/admin/departments.php','bi-building','ฝ่าย',$activePage);
        echo navLink('/admin/signatories.php','bi-pen','ผู้ลงนาม',$activePage);
        echo navLink('/admin/settings.php','bi-gear','ตั้งค่าระบบ',$activePage);
        echo navLink('/admin/import_budget.php','bi-upload','Import งบประมาณ',$activePage);
    }
    echo '<div class="nav-section">บัญชี</div>';
    echo '<a href="/index.php"><i class="bi bi-grid-fill"></i> กลับเมนูหลัก LLW</a>';
    echo '<a href="' . BASE_URL . '/logout.php"><i class="bi bi-box-arrow-right"></i> ออกจากระบบ</a>';
    echo '</div></div>';
}
function renderTopbar($title) {
    $u = getCurrentUser(); $notifs = getUnreadNotifications($u['id']); $nCount = count($notifs);
    echo '<div class="topbar"><div class="d-flex align-items-center gap-2">';
    echo '<button class="btn btn-sm btn-outline-secondary d-md-none" onclick="toggleSidebar()"><i class="bi bi-list"></i></button>';
    echo '<span class="topbar-title">' . h($title) . '</span></div>';
    echo '<div class="d-flex align-items-center gap-3"><div class="dropdown">';
    echo '<button class="btn btn-sm btn-outline-secondary position-relative" data-bs-toggle="dropdown"><i class="bi bi-bell"></i>';
    if ($nCount>0) echo '<span class="notification-badge position-absolute top-0 start-100 translate-middle">'.$nCount.'</span>';
    echo '</button><div class="dropdown-menu dropdown-menu-end" style="width:300px;max-height:380px;overflow-y:auto">';
    if (empty($notifs)) echo '<div class="dropdown-item text-muted small">ไม่มีการแจ้งเตือน</div>';
    foreach ($notifs as $n) { echo '<a class="dropdown-item py-2" href="'.BASE_URL.'/api/notifications.php?read='.$n['id'].'"><div style="font-size:13px;font-weight:500">'.h($n['title']).'</div><div style="font-size:12px;color:#64748b">'.h($n['message']).'</div></a>'; }
    echo '</div></div>';
    echo '<span style="font-size:13px;color:#64748b"><i class="bi bi-person-circle me-1"></i>'.h($u['full_name']).'</span>';
    echo '</div></div>';
}
function renderFooter() {
    echo '<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>';
    echo '<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>';
    echo '<script src="' . BASE_URL . '/assets/js/app.js"></script>';
    echo '</body></html>';
}
function flashMessage($type, $msg) { $_SESSION['flash'] = ['type'=>$type,'msg'=>$msg]; }
function showFlash() {
    if (!empty($_SESSION['flash'])) {
        $f = $_SESSION['flash']; unset($_SESSION['flash']);
        echo '<div class="alert alert-'.$f['type'].' alert-dismissible alert-auto mb-3 d-flex align-items-center gap-2">';
        echo h($f['msg']).'<button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button></div>';
    }
}
