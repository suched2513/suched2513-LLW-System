<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/layout.php';
requireRole(['admin','super_admin','director','budget_officer','wfh_admin','procurement_head','finance_head','deputy_director']);
$db = getDB();
$fy = (int)($_GET['fy'] ?? FISCAL_YEAR);

try {
    $stmt = $db->prepare("
        SELECT 
            d.name AS department_name,
            COALESCE(SUM(bp.budget_subsidy + bp.budget_quality + bp.budget_revenue + bp.budget_operation + bp.budget_reserve), 0) AS alloc_total,
            COALESCE(SUM(pr_approved.amount_requested), 0) AS used_total
        FROM departments d
        LEFT JOIN budget_projects bp ON bp.department_id = d.id AND bp.fiscal_year = ?
        LEFT JOIN (
            SELECT budget_project_id, SUM(amount_requested) AS amount_requested
            FROM project_requests WHERE status = 'approved' GROUP BY budget_project_id
        ) pr_approved ON pr_approved.budget_project_id = bp.id
        GROUP BY d.id, d.name
        ORDER BY d.order_no, d.name
    ");
    $stmt->execute([$fy]);
    $rows = $stmt->fetchAll();
    
    // Add usage_pct
    foreach ($rows as &$r) {
        $r['usage_pct'] = $r['alloc_total'] > 0 ? round($r['used_total'] / $r['alloc_total'] * 100, 1) : 0;
    }
    unset($r);
} catch (Exception $e) {
    $rows = [];
    error_log($e->getMessage());
}

$totalAlloc = array_sum(array_column($rows, 'alloc_total'));
$totalUsed  = array_sum(array_column($rows, 'used_total'));
renderHead('สรุปประจำปี');
echo '<div class="d-flex">'; renderSidebar(); echo '<div class="main-content flex-grow-1">'; renderTopbar('รายงานสรุปสิ้นปีงบประมาณ'); echo '<div class="page-content">'; showFlash();
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <select class="form-select" style="width:auto" onchange="location='?fy='+this.value">
    <?php for($y=2567;$y<=2572;$y++): ?><option value="<?=$y?>" <?=$fy==$y?'selected':''?>><?=$y?></option><?php endfor; ?>
  </select>
  <div class="d-flex gap-2">
    <a href="<?= BASE_URL ?>/reports/export_excel.php?type=annual&fy=<?=$fy?>" class="btn btn-sm btn-success"><i class="bi bi-file-earmark-excel me-1"></i>Excel</a>
    <a href="<?= BASE_URL ?>/reports/export_pdf.php?type=annual&fy=<?=$fy?>" class="btn btn-sm btn-danger" target="_blank"><i class="bi bi-file-earmark-pdf me-1"></i>PDF</a>
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