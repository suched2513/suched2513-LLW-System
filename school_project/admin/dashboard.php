<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/layout.php';
requireRole(['admin']);
$db = getDB();
$stats = [
    'users'    => $db->query("SELECT COUNT(*) FROM llw_users WHERE status='active'")->fetchColumn(),
    'projects' => $db->query("SELECT COUNT(*) FROM budget_projects WHERE is_active=1")->fetchColumn(),
    'requests' => $db->query("SELECT COUNT(*) FROM project_requests")->fetchColumn(),
    'pending'  => $db->query("SELECT COUNT(*) FROM project_requests WHERE status='submitted'")->fetchColumn()
];
$recentLogs = $db->query("SELECT al.*, CONCAT(u.firstname, ' ', u.lastname) AS full_name FROM audit_logs al LEFT JOIN llw_users u ON al.user_id=u.user_id ORDER BY al.created_at DESC LIMIT 10")->fetchAll();
renderHead('Admin Dashboard');
echo '<div class="d-flex">'; renderSidebar(); echo '<div class="main-content flex-grow-1">'; renderTopbar('Admin Dashboard'); echo '<div class="page-content">'; showFlash();
?>
<div class="row g-3 mb-4">
  <div class="col-md-3"><div class="stat-card" style="background:linear-gradient(135deg,#6366f1,#8b5cf6)"><div class="stat-value"><?=$stats['users']?></div><div class="stat-label">ผู้ใช้งาน</div></div></div>
  <div class="col-md-3"><div class="stat-card" style="background:linear-gradient(135deg,#1a56db,#3b82f6)"><div class="stat-value"><?=$stats['projects']?></div><div class="stat-label">โครงการในระบบ</div></div></div>
  <div class="col-md-3"><div class="stat-card" style="background:linear-gradient(135deg,#10b981,#34d399)"><div class="stat-value"><?=$stats['requests']?></div><div class="stat-label">คำขอทั้งหมด</div></div></div>
  <div class="col-md-3"><div class="stat-card" style="background:linear-gradient(135deg,#f59e0b,#fbbf24)"><div class="stat-value"><?=$stats['pending']?></div><div class="stat-label">รออนุมัติ</div></div></div>
</div>
<div class="row g-3">
  <div class="col-md-4"><div class="card"><div class="card-header">Quick Links</div><div class="card-body"><div class="d-grid gap-2">
    <a href="/admin/users.php" class="btn btn-outline-primary"><i class="bi bi-people me-2"></i>จัดการผู้ใช้</a>
    <a href="/admin/import_budget.php" class="btn btn-outline-success"><i class="bi bi-upload me-2"></i>Import งบประมาณ</a>
    <a href="/admin/settings.php" class="btn btn-outline-secondary"><i class="bi bi-gear me-2"></i>ตั้งค่าระบบ</a>
    <a href="/admin/all_requests.php" class="btn btn-outline-info"><i class="bi bi-inbox me-2"></i>คำขอทั้งหมด</a>
  </div></div></div></div>
  <div class="col-md-8"><div class="card"><div class="card-header">Log ล่าสุด</div><div class="card-body p-0">
    <table class="table table-sm mb-0">
      <thead><tr><th class="ps-3">ผู้ใช้</th><th>การกระทำ</th><th>เวลา</th></tr></thead>
      <tbody>
      <?php foreach ($recentLogs as $log): ?>
      <tr><td class="ps-3"><?=h($log['full_name']??'ไม่ระบุ')?></td><td><span class="badge bg-secondary"><?=h($log['action'])?></span></td><td style="font-size:12px"><?=formatDate($log['created_at'])?></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div></div></div>
</div>
<?php echo '</div></div></div>'; renderFooter(); ?>