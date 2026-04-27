<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/layout.php';
requireRole(['budget_officer','admin','director']);
$db = getDB();
$fy = FISCAL_YEAR;
$budgetUsage = $db->query("SELECT * FROM v_budget_usage WHERE fiscal_year=$fy ORDER BY department_name")->fetchAll();
$totalAlloc = array_sum(array_column($budgetUsage,'alloc_total'));
$totalUsed = array_sum(array_column($budgetUsage,'used_total'));
$totalRemain = $totalAlloc - $totalUsed;
$usagePct = $totalAlloc > 0 ? round($totalUsed/$totalAlloc*100,1) : 0;

renderHead('Dashboard ฝ่ายงบประมาณ');
echo '<div class="d-flex">'; renderSidebar(); echo '<div class="main-content flex-grow-1">'; renderTopbar('Dashboard ฝ่ายงบประมาณ'); echo '<div class="page-content">'; showFlash();
?>
<div class="row g-3 mb-4">
  <div class="col-md-3"><div class="stat-card" style="background:linear-gradient(135deg,#1a56db,#3b82f6)"><div class="stat-value"><?=number_format($totalAlloc,0)?></div><div class="stat-label">งบจัดสรรทั้งหมด (บาท)</div></div></div>
  <div class="col-md-3"><div class="stat-card" style="background:linear-gradient(135deg,#ef4444,#f87171)"><div class="stat-value"><?=number_format($totalUsed,0)?></div><div class="stat-label">ใช้ไปแล้ว (บาท)</div></div></div>
  <div class="col-md-3"><div class="stat-card" style="background:linear-gradient(135deg,#10b981,#34d399)"><div class="stat-value"><?=number_format($totalRemain,0)?></div><div class="stat-label">คงเหลือ (บาท)</div></div></div>
  <div class="col-md-3"><div class="stat-card" style="background:linear-gradient(135deg,<?=$usagePct>90?'#ef4444,#f87171':($usagePct>70?'#f59e0b,#fbbf24':'#6366f1,#8b5cf6')?>')"><div class="stat-value"><?=$usagePct?>%</div><div class="stat-label">% การใช้งบ</div></div></div>
</div>

<div class="card mb-4">
  <div class="card-header d-flex justify-content-between">
    <span><i class="bi bi-bar-chart-horizontal me-2"></i>ยอดงบรายฝ่าย</span>
    <a href="/reports/budget_overview.php" class="btn btn-sm btn-outline-primary">รายงานเต็ม</a>
  </div>
  <div class="card-body">
    <?php foreach ($budgetUsage as $b): ?>
    <?php $pct = (float)$b['usage_pct']; $colorClass = $pct>90?'danger':($pct>70?'warning':'good'); ?>
    <div class="mb-3">
      <div class="d-flex justify-content-between mb-1">
        <span style="font-size:14px;font-weight:500"><?=h($b['department_name'])?></span>
        <span style="font-size:13px;color:#64748b"><?=formatMoney($b['used_total'])?> / <?=formatMoney($b['alloc_total'])?> บาท
          <span class="badge bg-<?=$pct>90?'danger':($pct>70?'warning':'success')?> ms-1"><?=number_format($pct,1)?>%</span>
        </span>
      </div>
      <div class="progress-bar-custom"><div class="progress-fill <?=$colorClass?>" style="width:<?=min($pct,100)?>%"></div></div>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php echo '</div></div></div>'; renderFooter(); ?>
