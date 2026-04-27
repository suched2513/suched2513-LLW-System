<?php
$role = $_SESSION['role'] ?? '';
$user = currentUser();
$current_page = $_SERVER['PHP_SELF'];

$menu = [];

// Normalize role for menu display
if (in_array($role, ['admin', 'super_admin', 'wfh_admin'])) {
    $menu = [
        ['label' => 'Dashboard', 'icon' => 'bi-grid-fill', 'url' => '/admin/dashboard.php'],
        ['label' => 'รายงานภาพรวม', 'icon' => 'bi-graph-up-arrow', 'url' => '/reports/budget_overview.php'],
        ['label' => 'Audit Logs', 'icon' => 'bi-shield-lock-fill', 'url' => '/reports/audit_log.php'],
        ['label' => 'นำเข้างบประมาณ', 'icon' => 'bi-file-earmark-arrow-up-fill', 'url' => '/admin/import_budget.php'],
        ['label' => 'จัดการผู้ใช้งาน', 'icon' => 'bi-people-fill', 'url' => '/admin/users.php'],
        ['label' => 'จัดการฝ่าย/กลุ่มงาน', 'icon' => 'bi-building-fill', 'url' => '/admin/departments.php'],
        ['label' => 'ตั้งค่าผู้ลงนาม', 'icon' => 'bi-pen-fill', 'url' => '/admin/signatories.php'],
    ];
} elseif (in_array($role, ['teacher', 'att_teacher', 'wfh_staff'])) {
    $menu = [
        ['label' => 'Dashboard', 'icon' => 'bi-grid-fill', 'url' => '/teacher/dashboard.php'],
        ['label' => 'โครงการที่รับผิดชอบ', 'icon' => 'bi-folder-fill', 'url' => '/teacher/my_projects.php'],
        ['label' => 'ขอดำเนินโครงการ', 'icon' => 'bi-plus-circle-fill', 'url' => '/teacher/request_form.php'],
        ['label' => 'ประวัติคำขอ', 'icon' => 'bi-clock-history', 'url' => '/teacher/request_list.php'],
    ];
} elseif ($role === 'director') {
    $menu = [
        ['label' => 'Dashboard', 'icon' => 'bi-speedometer2', 'url' => '/dashboard/director.php'],
        ['label' => 'รออนุมัติ', 'icon' => 'bi-check2-square', 'url' => '/director/pending.php'],
        ['label' => 'รายงานงบประมาณ', 'icon' => 'bi-bar-chart-line-fill', 'url' => '/reports/budget_overview.php'],
        ['label' => 'โครงการค้างดำเนินการ', 'icon' => 'bi-exclamation-octagon-fill', 'url' => '/reports/project_overdue.php'],
        ['label' => 'สรุปรายปี', 'icon' => 'bi-calendar-check-fill', 'url' => '/reports/annual_summary.php'],
    ];
} elseif ($role === 'budget_officer') {
    $menu = [
        ['label' => 'Dashboard', 'icon' => 'bi-graph-up-arrow', 'url' => '/dashboard/budget_officer.php'],
        ['label' => 'รายงานงบประมาณ', 'icon' => 'bi-bar-chart-line-fill', 'url' => '/reports/budget_overview.php'],
        ['label' => 'โครงการค้างดำเนินการ', 'icon' => 'bi-exclamation-octagon-fill', 'url' => '/reports/project_overdue.php'],
        ['label' => 'สรุปรายปี', 'icon' => 'bi-calendar-check-fill', 'url' => '/reports/annual_summary.php'],
    ];
}
?>

<aside class="fixed left-0 top-0 h-full w-64 bg-white border-r border-slate-100 z-50 flex flex-col shadow-sm">
    <!-- Brand -->
    <div class="p-6 border-b border-slate-50 flex items-center gap-3">
        <div class="w-10 h-10 bg-blue-600 rounded-xl flex items-center justify-center text-white text-xl shadow-lg shadow-blue-100">
            <i class="bi bi-wallet2"></i>
        </div>
        <div class="overflow-hidden">
            <p class="font-black text-slate-800 text-sm leading-tight truncate"><?= htmlspecialchars(APP_NAME) ?></p>
            <p class="text-[10px] text-slate-400 font-bold uppercase tracking-wider">SBMS 2569</p>
        </div>
    </div>

    <!-- Navigation -->
    <nav class="flex-1 overflow-y-auto py-6">
        <div class="px-4 mb-4">
            <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest px-2 mb-2">Main Menu</p>
            <ul class="space-y-1">
                <?php foreach ($menu as $item): 
                    $isActive = strpos($current_page, $item['url']) !== false;
                ?>
                <li>
                    <a href="<?= BASE_URL . $item['url'] ?>" 
                       class="flex items-center gap-3 px-4 py-3 rounded-xl font-bold text-sm transition-all hover:bg-slate-50 <?= $isActive ? 'sidebar-item-active' : 'text-slate-500' ?>">
                        <i class="bi <?= $item['icon'] ?> <?= $isActive ? 'text-blue-600' : 'text-slate-400' ?> text-lg"></i>
                        <?= $item['label'] ?>
                    </a>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </nav>

    <!-- User Profile -->
    <div class="p-4 bg-slate-50 border-t border-slate-100">
        <div class="flex items-center gap-3 p-2 mb-2">
            <div class="w-10 h-10 bg-slate-200 rounded-full flex items-center justify-center text-slate-500">
                <i class="bi bi-person-circle text-2xl"></i>
            </div>
            <div class="overflow-hidden">
                <p class="font-black text-slate-800 text-xs truncate"><?= htmlspecialchars($user['full_name']) ?></p>
                <p class="text-[9px] text-slate-400 font-bold uppercase"><?= htmlspecialchars($user['role']) ?></p>
            </div>
        </div>
        <a href="<?= BASE_URL ?>/logout.php" class="flex items-center justify-center gap-2 w-full bg-white border border-rose-100 text-rose-500 py-2 rounded-xl font-black text-[11px] shadow-sm hover:bg-rose-50 transition-all">
            <i class="bi bi-box-arrow-left"></i>
            ออกจากระบบ
        </a>
    </div>
</aside>
