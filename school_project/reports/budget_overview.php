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
            COALESCE(SUM(bp.budget_subsidy),   0) AS alloc_subsidy,
            COALESCE(SUM(bp.budget_quality),   0) AS alloc_quality,
            COALESCE(SUM(bp.budget_revenue),   0) AS alloc_revenue,
            COALESCE(SUM(bp.budget_operation), 0) AS alloc_operation,
            COALESCE(SUM(bp.budget_reserve),   0) AS alloc_reserve,
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
    $rows = $stmt->fetchAll();

    foreach ($rows as &$r) {
        $r['available']  = $r['alloc_total'] - $r['used_total'] - $r['committed_total'];
        $r['usage_pct']  = $r['alloc_total'] > 0
            ? round(($r['used_total'] + $r['committed_total']) / $r['alloc_total'] * 100, 1)
            : 0;
    }
    unset($r);
} catch (Exception $e) {
    $rows = [];
    error_log($e->getMessage());
}

// สรุปรายประเภทเงิน
$budgetTypes = [
    'budget_subsidy'   => ['label' => 'งบประมาณเงินอุดหนุน',      'color' => '#1a56db', 'icon' => 'bi-bank'],
    'budget_quality'   => ['label' => 'งบพัฒนาคุณภาพผู้เรียน',    'color' => '#7c3aed', 'icon' => 'bi-mortarboard'],
    'budget_revenue'   => ['label' => 'เงินรายได้สถานศึกษา',       'color' => '#0891b2', 'icon' => 'bi-coin'],
    'budget_operation' => ['label' => 'งบดำเนินการ',                'color' => '#d97706', 'icon' => 'bi-gear'],
    'budget_reserve'   => ['label' => 'งบสำรอง',                   'color' => '#6b7280', 'icon' => 'bi-piggy-bank'],
];
$colToKey = [
    'budget_subsidy'   => 'alloc_subsidy',
    'budget_quality'   => 'alloc_quality',
    'budget_revenue'   => 'alloc_revenue',
    'budget_operation' => 'alloc_operation',
    'budget_reserve'   => 'alloc_reserve',
];
$typesSummary = [];
foreach ($budgetTypes as $col => $meta) {
    $typesSummary[$col] = array_merge($meta, ['alloc' => array_sum(array_column($rows, $colToKey[$col]))]);
}

$totalAlloc     = array_sum(array_column($rows, 'alloc_total'));
$totalUsed      = array_sum(array_column($rows, 'used_total'));
$totalCommitted = array_sum(array_column($rows, 'committed_total'));
$totalAvailable = $totalAlloc - $totalUsed - $totalCommitted;

renderHead('ภาพรวมงบประมาณ');
echo '<div class="d-flex">'; renderSidebar(); echo '<div class="main-content flex-grow-1">'; renderTopbar('รายงานภาพรวมงบประมาณ'); echo '<div class="page-content">'; showFlash();
?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <div class="d-flex gap-2">
    <select class="form-select form-select-sm" style="width:auto" onchange="location='?fy='+this.value">
      <?php for ($y = 2567; $y <= 2572; $y++): ?>
      <option value="<?= $y ?>" <?= $fy == $y ? 'selected' : '' ?>><?= $y ?></option>
      <?php endfor; ?>
    </select>
  </div>
  <div class="d-flex gap-2">
    <a href="<?= BASE_URL ?>/reports/export_excel.php?type=budget&fy=<?= $fy ?>" class="btn btn-sm btn-success">
      <i class="bi bi-file-earmark-excel me-1"></i>Excel
    </a>
    <a href="<?= BASE_URL ?>/reports/export_pdf.php?type=budget&fy=<?= $fy ?>" class="btn btn-sm btn-danger" target="_blank">
      <i class="bi bi-file-earmark-pdf me-1"></i>PDF
    </a>
  </div>
</div>

<!-- KPI Cards -->
<div class="row g-3 mb-4">
  <div class="col-6 col-md-3">
    <div class="stat-card" style="background:linear-gradient(135deg,#1a56db,#3b82f6)">
      <div class="stat-value"><?= number_format($totalAlloc, 0) ?></div>
      <div class="stat-label">งบจัดสรรรวม (บาท)</div>
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
    <div class="stat-card" style="background:linear-gradient(135deg,#10b981,#34d399)">
      <div class="stat-value"><?= number_format(max(0, $totalAvailable), 0) ?></div>
      <div class="stat-label">คงเหลือใช้ได้จริง (บาท)</div>
    </div>
  </div>
</div>

<!-- สรุปรายประเภทเงิน -->
<div class="card mb-4">
  <div class="card-header fw-semibold"><i class="bi bi-pie-chart me-2"></i>สรุปงบประมาณแยกตามประเภทเงิน ปี <?= $fy ?></div>
  <div class="card-body p-0">
    <table class="table table-hover mb-0">
      <thead class="table-light">
        <tr>
          <th class="ps-4">ประเภทเงิน</th>
          <th class="text-end">งบจัดสรร (บาท)</th>
          <th class="text-end">สัดส่วน</th>
          <th style="min-width:200px">แผนภูมิ</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($typesSummary as $col => $t):
          $pct = $totalAlloc > 0 ? round($t['alloc'] / $totalAlloc * 100, 1) : 0;
      ?>
      <?php if ($t['alloc'] > 0): ?>
      <tr>
        <td class="ps-4">
          <i class="<?= $t['icon'] ?> me-2" style="color:<?= $t['color'] ?>"></i>
          <span class="fw-semibold"><?= $t['label'] ?></span>
        </td>
        <td class="text-end fw-bold" style="color:<?= $t['color'] ?>"><?= number_format($t['alloc'], 2) ?></td>
        <td class="text-end"><?= $pct ?>%</td>
        <td>
          <div class="progress" style="height:14px;border-radius:7px;background:#e5e7eb">
            <div class="progress-bar" style="width:<?= $pct ?>%;background:<?= $t['color'] ?>;border-radius:7px"></div>
          </div>
        </td>
      </tr>
      <?php endif; ?>
      <?php endforeach; ?>
      <tr class="table-light fw-bold">
        <td class="ps-4">รวมทั้งหมด</td>
        <td class="text-end text-primary"><?= number_format($totalAlloc, 2) ?></td>
        <td class="text-end">100%</td>
        <td></td>
      </tr>
      </tbody>
    </table>
  </div>
</div>

<!-- Legend -->
<div class="d-flex gap-3 mb-3 flex-wrap" style="font-size:12px">
  <span><span class="badge bg-danger me-1">■</span>เบิกจ่ายแล้ว</span>
  <span><span class="badge bg-warning text-dark me-1">■</span>ผูกพัน (รออนุมัติ)</span>
  <span><span class="badge bg-success me-1">■</span>คงเหลือ</span>
</div>

<!-- Table -->
<div class="card">
  <div class="card-header"><i class="bi bi-bar-chart me-2"></i>ยอดงบรายฝ่าย ปีงบประมาณ <?= $fy ?></div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead>
          <tr>
            <th class="ps-4">ฝ่าย</th>
            <th class="text-end">งบจัดสรร</th>
            <th class="text-end">อุดหนุน</th>
            <th class="text-end">คุณภาพ</th>
            <th class="text-end">รายได้ฯ</th>
            <th class="text-end">เบิกจ่ายแล้ว</th>
            <th class="text-end">ผูกพัน</th>
            <th class="text-end">คงเหลือจริง</th>
            <th class="text-center" style="min-width:140px">สัดส่วน</th>
            <th>สถานะ</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $r):
            $avail = (float)$r['available'];
            $pct   = (float)$r['usage_pct'];
            $usedPct      = $r['alloc_total'] > 0 ? round($r['used_total']      / $r['alloc_total'] * 100, 1) : 0;
            $committedPct = $r['alloc_total'] > 0 ? round($r['committed_total'] / $r['alloc_total'] * 100, 1) : 0;
        ?>
        <tr>
          <td class="ps-4 fw-semibold"><?= h($r['department_name']) ?></td>
          <td class="text-end"><?= formatMoney($r['alloc_total']) ?></td>
          <td class="text-end text-muted small"><?= $r['alloc_subsidy'] > 0 ? formatMoney($r['alloc_subsidy']) : '<span class="text-muted">-</span>' ?></td>
          <td class="text-end text-muted small"><?= $r['alloc_quality'] > 0 ? formatMoney($r['alloc_quality']) : '<span class="text-muted">-</span>' ?></td>
          <td class="text-end text-muted small"><?= $r['alloc_revenue'] > 0 ? formatMoney($r['alloc_revenue']) : '<span class="text-muted">-</span>' ?></td>
          <td class="text-end text-danger fw-semibold"><?= formatMoney($r['used_total']) ?></td>
          <td class="text-end text-warning fw-semibold"><?= $r['committed_total'] > 0 ? formatMoney($r['committed_total']) : '<span class="text-muted">-</span>' ?></td>
          <td class="text-end fw-bold <?= $avail < 0 ? 'text-danger' : 'text-success' ?>"><?= formatMoney($avail) ?></td>
          <td class="text-center">
            <div class="progress" style="height:10px;border-radius:5px;background:#e5e7eb">
              <div class="progress-bar bg-danger"    style="width:<?= min($usedPct,      100) ?>%" title="เบิกจ่าย <?= $usedPct ?>%"></div>
              <div class="progress-bar bg-warning"   style="width:<?= min($committedPct, 100) ?>%" title="ผูกพัน <?= $committedPct ?>%"></div>
            </div>
            <div style="font-size:10px;margin-top:2px"><?= number_format($pct, 1) ?>% ใช้แล้ว+ผูกพัน</div>
          </td>
          <td>
            <?php if ($avail < 0): ?>
              <span class="badge bg-danger">เกินงบ</span>
            <?php elseif ($pct > 90): ?>
              <span class="badge bg-danger">เกือบหมด</span>
            <?php elseif ($pct > 70): ?>
              <span class="badge bg-warning text-dark">ระวัง</span>
            <?php else: ?>
              <span class="badge bg-success">ปกติ</span>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
        <tr class="table-light fw-bold">
          <td class="ps-4">รวมทั้งหมด</td>
          <td class="text-end"><?= formatMoney($totalAlloc) ?></td>
          <td colspan="3"></td>
          <td class="text-end text-danger"><?= formatMoney($totalUsed) ?></td>
          <td class="text-end text-warning"><?= formatMoney($totalCommitted) ?></td>
          <td class="text-end <?= $totalAvailable < 0 ? 'text-danger' : 'text-success' ?>"><?= formatMoney($totalAvailable) ?></td>
          <td colspan="2"></td>
        </tr>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php echo '</div></div></div>'; renderFooter(); ?>
