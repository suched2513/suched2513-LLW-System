<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/layout.php';
requireRole(['budget_officer','wfh_admin','admin','director','super_admin']);
$db = getDB();
$fy = FISCAL_YEAR;

try {
    $stmt = $db->prepare("
        SELECT
            d.name AS department_name,
            COALESCE(SUM(bp.budget_subsidy + bp.budget_quality + bp.budget_revenue
                         + bp.budget_operation + bp.budget_reserve), 0) AS alloc_total,
            COALESCE(SUM(CASE WHEN pr.status = 'approved'  THEN pr.amount_requested ELSE 0 END), 0) AS used_total,
            COALESCE(SUM(CASE WHEN pr.status = 'submitted' THEN pr.amount_requested ELSE 0 END), 0) AS committed_total
        FROM departments d
        LEFT JOIN budget_projects bp ON bp.department_id = d.id AND bp.fiscal_year = ?
        LEFT JOIN project_requests pr ON pr.budget_project_id = bp.id
        GROUP BY d.id, d.name
        ORDER BY d.order_no, d.name
    ");
    $stmt->execute([$fy]);
    $budgetUsage = $stmt->fetchAll();
    foreach ($budgetUsage as &$b) {
        $b['available']  = $b['alloc_total'] - $b['used_total'] - $b['committed_total'];
        $b['usage_pct']  = $b['alloc_total'] > 0
            ? round(($b['used_total'] + $b['committed_total']) / $b['alloc_total'] * 100, 1) : 0;
    }
    unset($b);
} catch (Exception $e) {
    $budgetUsage = [];
    error_log($e->getMessage());
}

$totalAlloc     = array_sum(array_column($budgetUsage, 'alloc_total'));
$totalUsed      = array_sum(array_column($budgetUsage, 'used_total'));
$totalCommitted = array_sum(array_column($budgetUsage, 'committed_total'));
$totalAvailable = $totalAlloc - $totalUsed - $totalCommitted;
$usagePct = $totalAlloc > 0 ? round(($totalUsed + $totalCommitted) / $totalAlloc * 100, 1) : 0;

// Pending amendments
$pendingAmCount = (int)$db->query("SELECT COUNT(*) FROM budget_amendments WHERE status='pending'")->fetchColumn();

renderHead('Dashboard ฝ่ายงบประมาณ');
echo '<div class="d-flex">'; renderSidebar(); echo '<div class="main-content flex-grow-1">'; renderTopbar('Dashboard ฝ่ายงบประมาณ'); echo '<div class="page-content">'; showFlash();
?>

<div class="row g-3 mb-4">
  <div class="col-6 col-md-3">
    <div class="stat-card" style="background:linear-gradient(135deg,#1a56db,#3b82f6)">
      <div class="stat-value"><?= number_format($totalAlloc, 0) ?></div>
      <div class="stat-label">งบจัดสรรทั้งหมด (บาท)</div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="stat-card" style="background:linear-gradient(135deg,#ef4444,#f87171)">
      <div class="stat-value"><?= number_format($totalUsed, 0) ?></div>
      <div class="stat-label">เบิกจ่ายแล้ว (บาท)</div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="stat-card" style="background:linear-gradient(135deg,#f59e0b,#fbbf24)">
      <div class="stat-value"><?= number_format($totalCommitted, 0) ?></div>
      <div class="stat-label">ผูกพัน/รออนุมัติ (บาท)</div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="stat-card" style="background:linear-gradient(135deg,<?= $usagePct > 90 ? '#ef4444,#f87171' : ($usagePct > 70 ? '#f59e0b,#fbbf24' : '#10b981,#34d399') ?>)">
      <div class="stat-value"><?= number_format(max(0, $totalAvailable), 0) ?></div>
      <div class="stat-label">คงเหลือจริง (บาท)</div>
    </div>
  </div>
</div>

<?php if ($pendingAmCount > 0): ?>
<div class="alert alert-warning d-flex align-items-center gap-2 mb-3">
  <i class="bi bi-exclamation-triangle-fill fs-5"></i>
  <div>มีคำขอโอน/เพิ่มวงเงินรอพิจารณา <strong><?= $pendingAmCount ?> รายการ</strong>
    <a href="<?= BASE_URL ?>/admin/budget_amendment.php" class="btn btn-sm btn-warning ms-2">พิจารณาเลย</a>
  </div>
</div>
<?php endif; ?>

<div class="card mb-4">
  <div class="card-header d-flex justify-content-between">
    <span><i class="bi bi-bar-chart-horizontal me-2"></i>ยอดงบรายฝ่าย ปีงบประมาณ <?= $fy ?></span>
    <a href="<?= BASE_URL ?>/reports/budget_overview.php" class="btn btn-sm btn-outline-primary">รายงานเต็ม</a>
  </div>
  <div class="card-body">
    <div class="mb-2 d-flex gap-3" style="font-size:11px">
      <span><span style="display:inline-block;width:10px;height:10px;border-radius:2px;background:#ef4444;margin-right:3px"></span>เบิกจ่าย</span>
      <span><span style="display:inline-block;width:10px;height:10px;border-radius:2px;background:#f59e0b;margin-right:3px"></span>ผูกพัน</span>
      <span><span style="display:inline-block;width:10px;height:10px;border-radius:2px;background:#e5e7eb;margin-right:3px"></span>คงเหลือ</span>
    </div>
    <?php foreach ($budgetUsage as $b):
        $pct  = (float)$b['usage_pct'];
        $uPct = $b['alloc_total'] > 0 ? round($b['used_total']      / $b['alloc_total'] * 100, 1) : 0;
        $cPct = $b['alloc_total'] > 0 ? round($b['committed_total']  / $b['alloc_total'] * 100, 1) : 0;
    ?>
    <div class="mb-3">
      <div class="d-flex justify-content-between mb-1">
        <span style="font-size:13px;font-weight:500"><?= h($b['department_name']) ?></span>
        <span style="font-size:12px;color:#64748b">
          <span class="text-danger"><?= formatMoney($b['used_total']) ?></span>
          <?php if ($b['committed_total'] > 0): ?>
          + <span class="text-warning"><?= formatMoney($b['committed_total']) ?></span>
          <?php endif; ?>
          / <?= formatMoney($b['alloc_total']) ?>
          <span class="badge bg-<?= $pct > 90 ? 'danger' : ($pct > 70 ? 'warning text-dark' : 'success') ?> ms-1">
            <?= number_format($pct, 1) ?>%
          </span>
        </span>
      </div>
      <div class="progress" style="height:10px;border-radius:5px;background:#e5e7eb">
        <div class="progress-bar bg-danger"  style="width:<?= min($uPct, 100) ?>%"></div>
        <div class="progress-bar bg-warning" style="width:<?= min($cPct, 100 - $uPct) ?>%"></div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php echo '</div></div></div>'; renderFooter(); ?>
