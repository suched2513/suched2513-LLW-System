<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/layout.php';
requireRole(['admin','director','budget_officer']);
$db = getDB();
$days = (int)(getSetting('overdue_days') ?? 30);
$fy = FISCAL_YEAR;
$rows = $db->query("SELECT bp.*,d.name AS dept_name,DATEDIFF(NOW(),bp.created_at) AS days_ago FROM budget_projects bp JOIN departments d ON bp.department_id=d.id LEFT JOIN project_requests pr ON pr.budget_project_id=bp.id WHERE bp.is_active=1 AND bp.fiscal_year=$fy AND pr.id IS NULL AND DATEDIFF(NOW(),bp.created_at)>$days ORDER BY days_ago DESC")->fetchAll();
renderHead('โครงการค้าง');
echo '<div class="d-flex">'; renderSidebar(); echo '<div class="main-content flex-grow-1">'; renderTopbar('โครงการค้างดำเนินการ'); echo '<div class="page-content">'; showFlash();
?>
<div class="alert alert-warning d-flex align-items-center gap-2">
  <i class="bi bi-exclamation-triangle fs-5"></i>
  <div>โครงการที่ยังไม่มีคำขอดำเนินการ เกินกว่า <strong><?=$days?> วัน</strong> พบทั้งหมด <strong><?=count($rows)?></strong> โครงการ</div>
</div>
<div class="card">
  <div class="card-header"><i class="bi bi-exclamation-circle me-2"></i>รายการโครงการค้าง</div>
  <div class="card-body p-0">
    <table class="table table-hover mb-0">
      <thead><tr><th class="ps-4">โครงการ</th><th>ฝ่าย</th><th>ผู้รับผิดชอบ</th><th class="text-end">งบที่ได้รับ</th><th class="text-center">ค้างมา (วัน)</th></tr></thead>
      <tbody>
      <?php foreach ($rows as $r): ?>
      <tr>
        <td class="ps-4"><div style="font-weight:500"><?=h($r['project_name'])?></div><div style="font-size:12px;color:#64748b"><?=h(mb_substr($r['activity']??''，0，60))?></div></td>
        <td><?=h($r['dept_name'])?></td>
        <td><?=h($r['owner_name'])?></td>
        <td class="text-end"><?=formatMoney($r['budget_subsidy']+$r['budget_quality']+$r['budget_revenue']+$r['budget_operation']+$r['budget_reserve'])?></td>
        <td class="text-center"><span class="badge bg-<?=$r['days_ago']>60?'danger':'warning text-dark'"><?=$r['days_ago']?> วัน</span></td>
      </tr>
      <?php endforeach; if(empty($rows)): ?>
      <tr><td colspan="5" class="text-center py-5 text-muted"><i class="bi bi-check-all fs-2 d-block mb-2"></i>ไม่มีโครงการค้างดำเนินการ</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php echo '</div></div></div>'; renderFooter(); ?>