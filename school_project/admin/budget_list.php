<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/layout.php';
requireRole(['admin','director','budget_officer','procurement_head','finance_head','deputy_director']);
$db = getDB();

$dept = (int)($_GET['dept'] ?? 0);
$fy   = (int)($_GET['fy']   ?? FISCAL_YEAR);

$params = [$fy];
$sql = "
    SELECT
        bp.*,
        d.name AS dept_name,
        COALESCE(SUM(CASE WHEN pr.status = 'approved'  THEN pr.amount_requested ELSE 0 END), 0) AS used_total,
        COALESCE(SUM(CASE WHEN pr.status = 'submitted' THEN pr.amount_requested ELSE 0 END), 0) AS committed_total,
        COUNT(CASE WHEN pr.status = 'submitted' THEN 1 END) AS pending_count
    FROM budget_projects bp
    JOIN departments d ON bp.department_id = d.id
    LEFT JOIN project_requests pr ON pr.budget_project_id = bp.id
    WHERE bp.is_active = 1 AND bp.fiscal_year = ?
";
if ($dept) { $sql .= " AND bp.department_id = ?"; $params[] = $dept; }
$sql .= " GROUP BY bp.id ORDER BY d.order_no, bp.id";

$s = $db->prepare($sql);
$s->execute($params);
$projects = $s->fetchAll();

$depts = $db->query("SELECT * FROM departments ORDER BY order_no")->fetchAll();

// Totals
$grandAlloc     = 0; $grandUsed = 0; $grandCommitted = 0;
foreach ($projects as $p) {
    $total = $p['budget_subsidy'] + $p['budget_quality'] + $p['budget_revenue']
           + $p['budget_operation'] + $p['budget_reserve'];
    $grandAlloc     += $total;
    $grandUsed      += $p['used_total'];
    $grandCommitted += $p['committed_total'];
}
$grandAvailable = $grandAlloc - $grandUsed - $grandCommitted;

renderHead('รายการงบประมาณ');
echo '<div class="d-flex">'; renderSidebar(); echo '<div class="main-content flex-grow-1">'; renderTopbar('รายการงบประมาณโครงการ'); echo '<div class="page-content">'; showFlash();
?>

<!-- Filter -->
<div class="d-flex justify-content-between mb-3 flex-wrap gap-2">
  <div class="d-flex gap-2 flex-wrap">
    <select class="form-select form-select-sm" style="width:auto"
            onchange="location='?fy='+this.value+'&dept=<?= $dept ?>'">
      <?php for ($y = 2567; $y <= 2572; $y++): ?>
      <option value="<?= $y ?>" <?= $fy == $y ? 'selected' : '' ?>><?= $y ?></option>
      <?php endfor; ?>
    </select>
    <select class="form-select form-select-sm" style="width:auto"
            onchange="location='?fy=<?= $fy ?>&dept='+this.value">
      <option value="0">-- ทุกฝ่าย --</option>
      <?php foreach ($depts as $d): ?>
      <option value="<?= $d['id'] ?>" <?= $dept == $d['id'] ? 'selected' : '' ?>><?= h($d['name']) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <?php if (in_array((getCurrentUser())['role'], ['admin','super_admin'])): ?>
  <a href="<?= BASE_URL ?>/admin/import_budget.php" class="btn btn-sm btn-success">
    <i class="bi bi-upload me-1"></i>Import งบประมาณ
  </a>
  <?php endif; ?>
</div>

<!-- Summary KPIs -->
<div class="row g-3 mb-4">
  <div class="col-6 col-md-3">
    <div class="stat-card" style="background:linear-gradient(135deg,#1a56db,#3b82f6)">
      <div class="stat-value"><?= number_format($grandAlloc, 0) ?></div>
      <div class="stat-label">งบจัดสรรรวม</div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="stat-card" style="background:linear-gradient(135deg,#ef4444,#f87171)">
      <div class="stat-value"><?= number_format($grandUsed, 0) ?></div>
      <div class="stat-label">เบิกจ่ายแล้ว</div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="stat-card" style="background:linear-gradient(135deg,#f59e0b,#fbbf24)">
      <div class="stat-value"><?= number_format($grandCommitted, 0) ?></div>
      <div class="stat-label">ผูกพัน/รออนุมัติ</div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="stat-card" style="background:linear-gradient(135deg,#10b981,#34d399)">
      <div class="stat-value"><?= number_format(max(0, $grandAvailable), 0) ?></div>
      <div class="stat-label">คงเหลือจริง</div>
    </div>
  </div>
</div>

<!-- Table -->
<div class="card">
  <div class="card-header d-flex justify-content-between align-items-center">
    <span><i class="bi bi-table me-2"></i>รายการงบประมาณ ปีงบประมาณ <?= $fy ?> (<?= count($projects) ?> โครงการ)</span>
    <small class="text-muted">
      <span class="badge bg-danger me-1">■</span>เบิกจ่าย
      <span class="badge bg-warning text-dark ms-2 me-1">■</span>ผูกพัน
      <span class="badge bg-success ms-2 me-1">■</span>คงเหลือ
    </small>
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover table-sm mb-0">
        <thead>
          <tr>
            <th class="ps-3">โครงการ/กิจกรรม</th>
            <th>ฝ่าย</th>
            <th>ผู้รับผิดชอบ</th>
            <th class="text-end">งบจัดสรร</th>
            <th class="text-end">เบิกจ่าย</th>
            <th class="text-end">ผูกพัน</th>
            <th class="text-end">คงเหลือ</th>
            <th class="text-center" style="min-width:100px">สัดส่วน</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($projects as $p):
            $alloc     = $p['budget_subsidy'] + $p['budget_quality'] + $p['budget_revenue']
                       + $p['budget_operation'] + $p['budget_reserve'];
            $used      = (float)$p['used_total'];
            $committed = (float)$p['committed_total'];
            $avail     = $alloc - $used - $committed;
            $usedPct   = $alloc > 0 ? min(round($used      / $alloc * 100, 1), 100) : 0;
            $commPct   = $alloc > 0 ? min(round($committed  / $alloc * 100, 1), 100 - $usedPct) : 0;
        ?>
        <tr>
          <td class="ps-3">
            <div style="font-size:13px;font-weight:500"><?= h($p['project_name']) ?></div>
            <div style="font-size:11px;color:#64748b"><?= h(mb_substr($p['activity'] ?? '', 0, 60)) ?></div>
          </td>
          <td style="font-size:12px"><?= h($p['dept_name']) ?></td>
          <td style="font-size:12px"><?= h($p['owner_name'] ?? '') ?></td>
          <td class="text-end" style="font-size:12px;font-weight:600;color:#1a56db"><?= formatMoney($alloc) ?></td>
          <td class="text-end" style="font-size:12px;color:#ef4444"><?= $used > 0 ? formatMoney($used) : '<span class="text-muted">-</span>' ?></td>
          <td class="text-end" style="font-size:12px;color:#f59e0b">
            <?php if ($committed > 0): ?>
            <?= formatMoney($committed) ?>
            <span class="badge bg-warning text-dark ms-1" style="font-size:9px"><?= $p['pending_count'] ?></span>
            <?php else: ?>
            <span class="text-muted">-</span>
            <?php endif; ?>
          </td>
          <td class="text-end" style="font-size:12px;font-weight:600;<?= $avail < 0 ? 'color:#ef4444' : 'color:#10b981' ?>">
            <?= formatMoney($avail) ?>
            <?php if ($avail < 0): ?><i class="bi bi-exclamation-triangle-fill ms-1"></i><?php endif; ?>
          </td>
          <td>
            <div class="progress" style="height:8px;border-radius:4px;background:#e5e7eb">
              <div class="progress-bar bg-danger"  style="width:<?= $usedPct ?>%"></div>
              <div class="progress-bar bg-warning" style="width:<?= $commPct ?>%"></div>
            </div>
            <div style="font-size:9px;text-align:center;margin-top:1px;color:#64748b">
              <?= number_format($usedPct + $commPct, 1) ?>%
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($projects)): ?>
        <tr><td colspan="8" class="text-center py-4 text-muted">ไม่พบข้อมูลงบประมาณ</td></tr>
        <?php endif; ?>
        </tbody>
        <tfoot class="table-light">
          <tr class="fw-bold">
            <td class="ps-3" colspan="3">รวมทั้งหมด</td>
            <td class="text-end" style="color:#1a56db"><?= formatMoney($grandAlloc) ?></td>
            <td class="text-end" style="color:#ef4444"><?= formatMoney($grandUsed) ?></td>
            <td class="text-end" style="color:#f59e0b"><?= formatMoney($grandCommitted) ?></td>
            <td class="text-end <?= $grandAvailable < 0 ? 'text-danger' : 'text-success' ?>"><?= formatMoney($grandAvailable) ?></td>
            <td></td>
          </tr>
        </tfoot>
      </table>
    </div>
  </div>
</div>
<?php echo '</div></div></div>'; renderFooter(); ?>
