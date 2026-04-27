<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/layout.php';
requireRole(['admin','director','budget_officer']);
$db = getDB();
$fy = (int)($_GET['fy'] ?? FISCAL_YEAR);
$budgetUsage = $db->prepare("SELECT * FROM v_budget_usage WHERE fiscal_year=? ORDER BY department_name");
$budgetUsage->execute([$fy]); $rows = $budgetUsage->fetchAll();
$reqStats = $db->prepare("SELECT d.name,COUNT(pr.id) AS total,SUM(pr.status='approved') AS approved,SUM(pr.status='rejected') AS rejected,SUM(CASE WHEN pr.status='approved' THEN pr.amount_requested ELSE 0 END) AS total_approved FROM project_requests pr JOIN budget_projects bp ON pr.budget_project_id=bp.id JOIN departments d ON bp.department_id=d.id WHERE bp.fiscal_year=? GROUP BY d.id");
$reqStats->execute([$fy]); $reqRows = $reqStats->fetchAll();
$totalAlloc = array_sum(array_column($rows,'alloc_total'));
$totalUsed = array_sum(array_column($rows,'used_total'));
renderHead('สรุปประจำปี');
echo '<div class="d-flex">'; renderSidebar(); echo '<div class="main-content flex-grow-1">'; renderTopbar('รายงานสรุปสิ้นปีงบประมาณ'); echo '<div class="page-content">'; showFlash();
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <select class="form-select" style="width:auto" onchange="location='?fy='+this.value">
    <?php for($y=2567;$y<=2572;$y++): ?><option value="<?=$y?>" <?=$fy==$y?'selected':''?>><?=$y?></option><?php endfor; ?>
  </select>
  <div class="d-flex gap-2">
    <a href="/reports/export_excel.php?type=annual&fy=<?=$fy?>" class="btn btn-sm btn-success"><i class="bi bi-file-earmark-excel me-1"></i>Excel</a>
    <a href="/reports/export_pdf.php?type=annual&fy=<?=$fy?>" class="btn btn-sm btn-danger" target="_blank"><i class="bi bi-file-earmark-pdf me-1"></i>PDF</a>
  </div>
</div>
<div class="row g-3 mb-4">
  <div class="col-md-3"><div class="stat-card" style="background:linear-gradient(135deg,#1a56db,#3b82f6)"><div class="stat-value"><?=number_format($totalAlloc,0)?></div><div class="stat-label">งบจัดสรรทั้งหมด</div></div></div>
  <div class="col-md-3"><div class="stat-card" style="background:linear-gradient(135deg,#ef4444,#f87171)"><div class="stat-value"><?=number_format($totalUsed,0)?></div><div class="stat-label">ยอดอนุมัติรวม</div></div></div>
  <div class="col-md-3"><div class="stat-card" style="background:linear-gradient(135deg,#10b981,#34d399)"><div class="stat-value"><?=number_format($totalAlloc-$totalUsed,0)?></div><div class="stat-label">คงเหลือ/คืน</div></div></div>
  <div class="col-md-3"><div class="stat-card" style="background:linear-gradient(135deg,#f59e0b,#fbbf24)"><div class="stat-value"><?=$totalAlloc>0?number_format($totalUsed/$totalAlloc*100,1):0?>%</div><div class="stat-label">% การใช้งบรวม</div></div></div>
</div>
<div class="card">
  <div class="card-header">สรุปรายฝ่าย ปีงบประมาณ <?=$fy?></div>
  <div class="card-body p-0">
    <table class="table table-hover mb-0">
      <thead><tr><th class="ps-4">ฝ่าย</th><th class="text-end">งบจัดสรร</th><th class="text-end">ยอดอนุมัติ</th><th class="text-end">คงเหลือ</th><th class="text-center">% ใช้</th></tr></thead>
      <tbody>
      <?php foreach ($rows as $r): ?>
      <tr>
        <td class="ps-4 fw-semibold"><?=h($r['department_name'])?></td>
        <td class="text-end"><?=formatMoney($r['alloc_total'])?></td>
        <td class="text-end text-danger"><?=formatMoney($r['used_total'])?></td>
        <td class="text-end text-success"><?=formatMoney($r['alloc_total']-$r['used_total'])?></td>
        <td class="text-center"><span class="badge bg-<?=(float)$r['usage_pct']>90?'danger':((float)$r['usage_pct']>70?'warning text-dark':'success')?>"><?=number_format((float)$r['usage_pct'],1)?>%</span></td>
      </tr>
      <?php endforeach; ?>
      <tr class="table-light fw-bold"><td class="ps-4">รวม</td><td class="text-end"><?=formatMoney($totalAlloc)?></td><td class="text-end text-danger"><?=formatMoney($totalUsed)?></td><td class="text-end text-success"><?=formatMoney($totalAlloc-$totalUsed)?></td><td class="text-center"><?=$totalAlloc>0?number_format($totalUsed/$totalAlloc*100,1):0?>%</td></tr>
      </tbody>
    </table>
  </div>
</div>
<?php echo '</div></div></div>'; renderFooter(); ?>