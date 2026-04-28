<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/layout.php';
requireRole(['teacher','head','admin','budget_officer']);
$u = getCurrentUser();
$db = getDB();

// ดึงโครงการที่ตัวเองรับผิดชอบ - ใช้ user_id จาก llw_users
$ownerName = $u['owner_name'] ?? '';
$sql = "SELECT bp.*, d.name AS dept_name,
        pr.id AS req_id, pr.status AS req_status, pr.amount_requested, pr.proc_type
        FROM budget_projects bp
        JOIN departments d ON bp.department_id=d.id
        LEFT JOIN project_requests pr ON pr.budget_project_id=bp.id AND pr.user_id=?
        WHERE bp.is_active=1 AND bp.fiscal_year=?";
$params = [$u['id'], FISCAL_YEAR];
if ($ownerName) {
    $sql .= " AND bp.owner_name LIKE ?";
    $params[] = '%'.$ownerName.'%';
}
$sql .= " ORDER BY bp.department_id, bp.id";
$s = $db->prepare($sql);
$s->execute($params);
$projects = $s->fetchAll();

renderHead('โครงการของฉัน');
echo '<div class="d-flex">';
renderSidebar();
echo '<div class="main-content flex-grow-1">';
renderTopbar('โครงการของฉัน');
echo '<div class="page-content">';
showFlash();
?>
<div class="row g-3 mb-4">
  <div class="col-md-3"><div class="stat-card" style="background:linear-gradient(135deg,#1a56db,#3b82f6)">
    <div class="stat-value"><?= count($projects) ?></div><div class="stat-label">โครงการทั้งหมด</div></div></div>
  <div class="col-md-3"><div class="stat-card" style="background:linear-gradient(135deg,#f59e0b,#fbbf24)">
    <div class="stat-value"><?= count(array_filter($projects, fn($p) => $p['req_status']==='submitted')) ?></div>
    <div class="stat-label">รออนุมัติ</div></div></div>
  <div class="col-md-3"><div class="stat-card" style="background:linear-gradient(135deg,#10b981,#34d399)">
    <div class="stat-value"><?= count(array_filter($projects, fn($p) => $p['req_status']==='approved')) ?></div>
    <div class="stat-label">อนุมัติแล้ว</div></div></div>
  <div class="col-md-3"><div class="stat-card" style="background:linear-gradient(135deg,#6366f1,#8b5cf6)">
    <div class="stat-value"><?= count(array_filter($projects, fn($p) => !$p['req_id'])) ?></div>
    <div class="stat-label">ยังไม่ดำเนินการ</div></div></div>
</div>

<div class="card">
  <div class="card-header d-flex justify-content-between align-items-center">
    <span><i class="bi bi-folder2-open me-2"></i>รายการโครงการ ปีงบประมาณ <?= FISCAL_YEAR ?></span>
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead><tr>
          <th class="ps-4">โครงการ / กิจกรรม</th>
          <th>ฝ่าย</th>
          <th class="text-end">งบจัดสรร (บาท)</th>
          <th class="text-center">สถานะ</th>
          <th class="text-center">ดำเนินการ</th>
        </tr></thead>
        <tbody>
<?php foreach ($projects as $p): ?>
        <tr>
          <td class="ps-4">
            <div style="font-weight:500;font-size:14px"><?= h($p['project_name']) ?></div>
            <?php if ($p['activity']): ?>
            <div style="font-size:12px;color:#64748b;margin-top:2px"><?= h(mb_substr($p['activity'],0,80)) ?>...</div>
            <?php endif; ?>
          </td>
          <td><span class="badge bg-light text-dark"><?= h($p['dept_name']) ?></span></td>
          <td class="text-end">
            <?php $total = $p['budget_subsidy']+$p['budget_quality']+$p['budget_revenue']+$p['budget_operation']+$p['budget_reserve']; ?>
            <div style="font-weight:600;color:#1a56db"><?= formatMoney($total) ?></div>
            <?php if ($p['budget_quality']>0): ?><div style="font-size:11px;color:#64748b">คุณภาพ: <?= formatMoney($p['budget_quality']) ?></div><?php endif; ?>
            <?php if ($p['budget_revenue']>0): ?><div style="font-size:11px;color:#64748b">รายได้: <?= formatMoney($p['budget_revenue']) ?></div><?php endif; ?>
            <?php if ($p['budget_subsidy']>0): ?><div style="font-size:11px;color:#64748b">อุดหนุน: <?= formatMoney($p['budget_subsidy']) ?></div><?php endif; ?>
          </td>
          <td class="text-center">
            <?php if (!$p['req_id']): ?>
              <span class="badge bg-secondary">ยังไม่ดำเนินการ</span>
            <?php else: echo statusBadge($p['req_status']); endif; ?>
          </td>
          <td class="text-center">
            <?php if (!$p['req_id']): ?>
              <a href="<?= BASE_URL ?>/teacher/request_form.php?project_id=<?= $p['id'] ?>" class="btn btn-sm btn-primary">
                <i class="bi bi-plus-circle me-1"></i>ขอดำเนินการ
              </a>
            <?php elseif ($p['req_status']==='draft'): ?>
              <a href="<?= BASE_URL ?>/teacher/request_form.php?req_id=<?= $p['req_id'] ?>" class="btn btn-sm btn-warning">
                <i class="bi bi-pencil me-1"></i>แก้ไข
              </a>
            <?php else: ?>
              <a href="<?= BASE_URL ?>/teacher/request_view.php?id=<?= $p['req_id'] ?>" class="btn btn-sm btn-outline-primary">
                <i class="bi bi-eye me-1"></i>ดูรายละเอียด
              </a>
            <?php endif; ?>
          </td>
        </tr>
<?php endforeach; ?>
        <?php if (empty($projects)): ?>
        <tr><td colspan="5" class="text-center py-5 text-muted"><i class="bi bi-inbox fs-2 d-block mb-2"></i>ไม่พบโครงการที่รับผิดชอบ</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php
echo '</div></div></div>';
renderFooter();
