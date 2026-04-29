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
            COALESCE(SUM(bp.budget_subsidy) ,0) AS alloc_subsidy,
            COALESCE(SUM(bp.budget_quality) ,0) AS alloc_quality,
            COALESCE(SUM(bp.budget_revenue) ,0) AS alloc_revenue,
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
renderHead('ภาพรวมงบประมาณ');
echo '<div class="d-flex">'; renderSidebar(); echo '<div class="main-content flex-grow-1">'; renderTopbar('รายงานภาพรวมงบประมาณ'); echo '<div class="page-content">'; showFlash();
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <div class="d-flex gap-2">
    <select class="form-select form-select-sm" style="width:auto" onchange="location='?fy='+this.value">
      <?php for($y=2567;$y<=2572;$y++): ?><option value="<?=$y?>" <?=$fy==$y?'selected':''?>><?=$y?></option><?php endfor; ?>
    </select>
  </div>
  <div class="d-flex gap-2">
    <a href="<?= BASE_URL ?>/reports/export_excel.php?type=budget&fy=<?=$fy?>" class="btn btn-sm btn-success"><i class="bi bi-file-earmark-excel me-1"></i>Excel</a>
    <a href="<?= BASE_URL ?>/reports/export_pdf.php?type=budget&fy=<?=$fy?>" class="btn btn-sm btn-danger" target="_blank"><i class="bi bi-file-earmark-pdf me-1"></i>PDF</a>
  </div>
</div>
<div class="row g-3 mb-4">
  <div class="col-md-4"><div class="stat-card" style="background:linear-gradient(135deg,#1a56db,#3b82f6)"><div class="stat-value"><?=number_format($totalAlloc,0)?></div><div class="stat-label">งบจัดสรรรวม (บาท)</div></div></div>
  <div class="col-md-4"><div class="stat-card" style="background:linear-gradient(135deg,#ef4444,#f87171)"><div class="stat-value"><?=number_format($totalUsed,0)?></div><div class="stat-label">ใช้ไปแล้ว (บาท)</div></div></div>
  <div class="col-md-4"><div class="stat-card" style="background:linear-gradient(135deg,#10b981,#34d399)"><div class="stat-value"><?=number_format($totalAlloc-$totalUsed,0)?></div><div class="stat-label">คงเหลือ (บาท)</div></div></div>
</div>
<div class="card">
  <div class="card-header"><i class="bi bi-bar-chart me-2"></i>ยอดงบรายฝ่าย ปีงบประมาณ <?=$fy?></div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead><tr><th class="ps-4">ฝ่าย</th><th class="text-end">งบจัดสรร</th><th class="text-end">อุดหนุน</th><th class="text-end">คุณภาพ</th><th class="text-end">รายได้ฯ</th><th class="text-end">ใช้ไปแล้ว</th><th class="text-end">คงเหลือ</th><th class="text-center">%</th><th>สถานะ</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
        <?php $remain = $r['alloc_total']-$r['used_total']; $pct = (float)$r['usage_pct']; ?>
        <tr>
          <td class="ps-4 fw-semibold"><?=h($r['department_name'])?></td>
          <td class="text-end"><?=formatMoney($r['alloc_total'])?></td>
          <td class="text-end text-muted"><?=formatMoney($r['alloc_subsidy'])?></td>
          <td class="text-end text-muted"><?=formatMoney($r['alloc_quality'])?></td>
          <td class="text-end text-muted"><?=formatMoney($r['alloc_revenue'])?></td>
          <td class="text-end text-danger"><?=formatMoney($r['used_total'])?></td>
          <td class="text-end text-success fw-semibold"><?=formatMoney($remain)?></td>
          <td class="text-center"><div class="progress-bar-custom"><div class="progress-fill <?=$pct>90?'danger':($pct>70?'warning':'good')?>" style="width:<?=min($pct,100)?>%"></div></div><div style="font-size:11px"><?=number_format($pct,1)?>%</div></td>
          <td><?php if($pct>90): ?><span class="badge bg-danger">เกือบหมด</span><?php elseif($pct>70): ?><span class="badge bg-warning text-dark">ระวัง</span><?php else: ?><span class="badge bg-success">ปกติ</span><?php endif; ?></td>
        </tr>
        <?php endforeach; ?>
        <tr class="table-light fw-bold">
          <td class="ps-4">รวมทั้งหมด</td>
          <td class="text-end"><?=formatMoney($totalAlloc)?></td>
          <td class="text-end" colspan="3"></td>
          <td class="text-end text-danger"><?=formatMoney($totalUsed)?></td>
          <td class="text-end text-success"><?=formatMoney($totalAlloc-$totalUsed)?></td>
          <td colspan="2"></td>
        </tr>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php echo '</div></div></div>'; renderFooter(); ?>