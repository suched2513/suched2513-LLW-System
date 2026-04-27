<?php
// config/layout.php  — shared HTML shell
function renderHead(string $title, string $extraCss = ''): void { ?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($title) ?> | ระบบโครงการ <?= SCHOOL_NAME ?></title>
<link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link href="<?= APP_URL ?>/assets/css/style.css" rel="stylesheet">
<?= $extraCss ?>
</head>
<body>
<?php }

function renderSidebar(): void {
    $u    = currentUser();
    $role = $u['role'];
    $unread = unreadCount();
    $base = APP_URL;
    $links = [
        'admin' => [
            ['icon'=>'bi-speedometer2','label'=>'Dashboard',       'href'=>"$base/admin/dashboard.php"],
            ['icon'=>'bi-cloud-upload','label'=>'Import งบประมาณ', 'href'=>"$base/admin/import_budget.php"],
            ['icon'=>'bi-people',      'label'=>'จัดการผู้ใช้',    'href'=>"$base/admin/users.php"],
            ['icon'=>'bi-building',    'label'=>'จัดการฝ่าย',      'href'=>"$base/admin/departments.php"],
            ['icon'=>'bi-pen',         'label'=>'ผู้ลงนาม',        'href'=>"$base/admin/signatories.php"],
            ['icon'=>'bi-list-check',  'label'=>'คำขอทั้งหมด',    'href'=>"$base/admin/all_requests.php"],
            ['icon'=>'bi-clock-history','label'=>'Audit Log',      'href'=>"$base/reports/audit_log.php"],
        ],
        'director' => [
            ['icon'=>'bi-speedometer2','label'=>'Dashboard',          'href'=>"$base/dashboard/director.php"],
            ['icon'=>'bi-check2-circle','label'=>'รออนุมัติ',          'href'=>"$base/director/pending.php"],
            ['icon'=>'bi-bar-chart',   'label'=>'รายงานภาพรวม',      'href'=>"$base/reports/budget_overview.php"],
            ['icon'=>'bi-graph-up',    'label'=>'ความคืบหน้าโครงการ', 'href'=>"$base/reports/project_progress.php"],
            ['icon'=>'bi-exclamation-triangle','label'=>'โครงการค้าง','href'=>"$base/reports/project_overdue.php"],
            ['icon'=>'bi-file-earmark-text','label'=>'สรุปสิ้นปี',   'href'=>"$base/reports/annual_summary.php"],
        ],
        'budget_officer' => [
            ['icon'=>'bi-speedometer2','label'=>'Dashboard',          'href'=>"$base/dashboard/budget_officer.php"],
            ['icon'=>'bi-bar-chart',   'label'=>'รายงานภาพรวม',      'href'=>"$base/reports/budget_overview.php"],
            ['icon'=>'bi-pie-chart',   'label'=>'งบรายฝ่าย',         'href'=>"$base/reports/budget_by_dept.php"],
            ['icon'=>'bi-graph-up-arrow','label'=>'จัดสรร vs ใช้จริง','href'=>"$base/reports/budget_vs_actual.php"],
            ['icon'=>'bi-file-earmark-excel','label'=>'สรุปสิ้นปี',  'href'=>"$base/reports/annual_summary.php"],
            ['icon'=>'bi-list-check',  'label'=>'คำขอทั้งหมด',       'href'=>"$base/admin/all_requests.php"],
        ],
        'head' => [
            ['icon'=>'bi-speedometer2','label'=>'Dashboard',       'href'=>"$base/teacher/dashboard.php"],
            ['icon'=>'bi-folder2-open','label'=>'โครงการของฝ่าย', 'href'=>"$base/teacher/my_projects.php"],
            ['icon'=>'bi-clock-history','label'=>'ประวัติคำขอ',   'href'=>"$base/teacher/request_list.php"],
            ['icon'=>'bi-bar-chart',   'label'=>'รายงานฝ่าย',     'href'=>"$base/reports/budget_by_dept.php"],
        ],
        'teacher' => [
            ['icon'=>'bi-speedometer2','label'=>'Dashboard',       'href'=>"$base/teacher/dashboard.php"],
            ['icon'=>'bi-folder2-open','label'=>'โครงการของฉัน',  'href'=>"$base/teacher/my_projects.php"],
            ['icon'=>'bi-clock-history','label'=>'ประวัติคำขอ',   'href'=>"$base/teacher/request_list.php"],
        ],
    ];
    $myLinks = $links[$role] ?? $links['teacher'];
    $current = $_SERVER['PHP_SELF'];
?>
<div class="sidebar d-flex flex-column">
  <div class="sidebar-brand">
    <div class="brand-icon"><i class="bi bi-mortarboard-fill"></i></div>
    <div>
      <div class="brand-name">ระบบโครงการ</div>
      <div class="brand-sub"><?= SCHOOL_NAME ?></div>
    </div>
  </div>
  <nav class="sidebar-nav flex-grow-1">
    <?php foreach ($myLinks as $link): ?>
    <a href="<?= $link['href'] ?>" class="nav-item <?= str_contains($current, basename($link['href'])) ? 'active' : '' ?>">
      <i class="<?= $link['icon'] ?>"></i>
      <span><?= $link['label'] ?></span>
    </a>
    <?php endforeach; ?>
  </nav>
  <div class="sidebar-footer">
    <div class="user-info">
      <div class="user-avatar"><?= mb_substr($u['name'], 0, 1) ?></div>
      <div class="user-detail">
        <div class="user-name"><?= h($u['name']) ?></div>
        <div class="user-role"><?= h($role) ?></div>
      </div>
    </div>
    <a href="<?= $base ?>/logout.php" class="btn-logout"><i class="bi bi-box-arrow-right"></i></a>
  </div>
</div>
<?php }

function renderTopbar(string $title): void {
    $unread = unreadCount();
    $base = APP_URL;
?>
<div class="topbar">
  <button class="sidebar-toggle d-lg-none" onclick="document.body.classList.toggle('sidebar-open')">
    <i class="bi bi-list"></i>
  </button>
  <h1 class="topbar-title"><?= h($title) ?></h1>
  <div class="topbar-actions">
    <a href="<?= $base ?>/api/notifications.php?view=1" class="notif-btn">
      <i class="bi bi-bell"></i>
      <?php if ($unread > 0): ?>
      <span class="notif-badge"><?= $unread ?></span>
      <?php endif; ?>
    </a>
  </div>
</div>
<?php }

function renderFoot(): void { ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="<?= APP_URL ?>/assets/js/main.js"></script>
</body></html>
<?php }
