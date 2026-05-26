<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/layout.php';
requireRole(['admin','super_admin','director','budget_officer','procurement_head','finance_head','deputy_director']);
$db = getDB();

$fy     = (int)($_GET['fy']     ?? FISCAL_YEAR);
$dept   = (int)($_GET['dept']   ?? 0);
$status = $_GET['status'] ?? 'all';  // all | none | draft | submitted | approved | rejected

$depts = $db->query("SELECT * FROM departments ORDER BY order_no")->fetchAll();

// ดึงโครงการพร้อมสถานะ request ล่าสุด
$params = [$fy];
$sql = "
    SELECT
        bp.id,
        bp.project_name,
        bp.activity,
        bp.owner_name,
        bp.budget_subsidy + bp.budget_quality + bp.budget_revenue
            + bp.budget_operation + bp.budget_reserve AS alloc_total,
        d.name AS dept_name,
        d.id   AS dept_id,
        COALESCE(pr_agg.best_status, 'none') AS proj_status,
        COALESCE(pr_agg.req_count,   0)      AS req_count,
        COALESCE(pr_agg.used_total,  0)      AS used_total
    FROM budget_projects bp
    JOIN departments d ON d.id = bp.department_id
    LEFT JOIN (
        SELECT
            budget_project_id,
            COUNT(*)  AS req_count,
            COALESCE(SUM(CASE WHEN status='approved' THEN amount_requested ELSE 0 END), 0) AS used_total,
            CASE
                WHEN SUM(CASE WHEN status='approved'  THEN 1 ELSE 0 END) > 0 THEN 'approved'
                WHEN SUM(CASE WHEN status='submitted' THEN 1 ELSE 0 END) > 0 THEN 'submitted'
                WHEN SUM(CASE WHEN status='rejected'  THEN 1 ELSE 0 END) > 0 THEN 'rejected'
                WHEN SUM(CASE WHEN status='draft'     THEN 1 ELSE 0 END) > 0 THEN 'draft'
                ELSE 'none'
            END AS best_status
        FROM project_requests
        GROUP BY budget_project_id
    ) pr_agg ON pr_agg.budget_project_id = bp.id
    WHERE bp.is_active = 1 AND bp.fiscal_year = ?
";
if ($dept)   { $sql .= " AND bp.department_id = ?"; $params[] = $dept; }
if ($status !== 'all') {
    if ($status === 'none') {
        $sql .= " AND pr_agg.best_status IS NULL";
    } else {
        $sql .= " AND pr_agg.best_status = ?"; $params[] = $status;
    }
}
$sql .= " ORDER BY d.order_no, bp.id";

try {
    $s = $db->prepare($sql);
    $s->execute($params);
    $projects = $s->fetchAll();
} catch (Exception $e) {
    $projects = [];
    error_log($e->getMessage());
}

// KPI — นับจากทุกโครงการในปีงบ (ไม่ผ่านตัวกรอง)
try {
    $kpiStmt = $db->prepare("
        SELECT COALESCE(pr_agg.best_status,'none') AS s, COUNT(*) AS cnt
        FROM budget_projects bp
        LEFT JOIN (
            SELECT budget_project_id,
                CASE
                    WHEN SUM(CASE WHEN status='approved'  THEN 1 ELSE 0 END)>0 THEN 'approved'
                    WHEN SUM(CASE WHEN status='submitted' THEN 1 ELSE 0 END)>0 THEN 'submitted'
                    WHEN SUM(CASE WHEN status='rejected'  THEN 1 ELSE 0 END)>0 THEN 'rejected'
                    WHEN SUM(CASE WHEN status='draft'     THEN 1 ELSE 0 END)>0 THEN 'draft'
                    ELSE 'none'
                END AS best_status
            FROM project_requests GROUP BY budget_project_id
        ) pr_agg ON pr_agg.budget_project_id = bp.id
        WHERE bp.is_active=1 AND bp.fiscal_year=?
        GROUP BY s
    ");
    $kpiStmt->execute([$fy]);
    $kpi = ['none'=>0,'draft'=>0,'submitted'=>0,'approved'=>0,'rejected'=>0,'total'=>0];
    foreach ($kpiStmt->fetchAll() as $k) {
        $kpi[$k['s']] = (int)$k['cnt'];
        $kpi['total'] += (int)$k['cnt'];
    }
} catch (Exception $e) { $kpi = array_fill_keys(['none','draft','submitted','approved','rejected','total'],0); }

$statusLabels = [
    'none'      => ['label'=>'ยังไม่ดำเนินการ','badge'=>'warning text-dark','icon'=>'bi-hourglass'],
    'draft'     => ['label'=>'ร่าง/เตรียมการ',  'badge'=>'secondary',       'icon'=>'bi-pencil'],
    'submitted' => ['label'=>'รออนุมัติ',        'badge'=>'info text-dark',  'icon'=>'bi-send'],
    'approved'  => ['label'=>'อนุมัติแล้ว',      'badge'=>'success',         'icon'=>'bi-check-circle'],
    'rejected'  => ['label'=>'ปฏิเสธ',           'badge'=>'danger',          'icon'=>'bi-x-circle'],
];

renderHead('ความคืบหน้าโครงการ');
echo '<div class="d-flex">'; renderSidebar(); echo '<div class="main-content flex-grow-1">'; renderTopbar('สถานะและความคืบหน้าโครงการ'); echo '<div class="page-content">'; showFlash();
?>

<!-- KPI -->
<div class="row g-3 mb-3">
  <div class="col-6 col-md" style="min-width:120px">
    <a href="?fy=<?=$fy?>&dept=<?=$dept?>&status=all" class="text-decoration-none">
      <div class="card text-center py-3 <?= $status==='all' ? 'border-primary border-2' : '' ?>">
        <div class="fw-bold fs-4 text-primary"><?= $kpi['total'] ?></div>
        <div class="small text-muted">ทั้งหมด</div>
      </div>
    </a>
  </div>
  <div class="col-6 col-md" style="min-width:120px">
    <a href="?fy=<?=$fy?>&dept=<?=$dept?>&status=none" class="text-decoration-none">
      <div class="card text-center py-3 <?= $status==='none' ? 'border-warning border-2' : '' ?>">
        <div class="fw-bold fs-4 text-warning"><?= $kpi['none'] ?></div>
        <div class="small text-muted">ยังไม่ดำเนินการ</div>
      </div>
    </a>
  </div>
  <div class="col-6 col-md" style="min-width:120px">
    <a href="?fy=<?=$fy?>&dept=<?=$dept?>&status=draft" class="text-decoration-none">
      <div class="card text-center py-3 <?= $status==='draft' ? 'border-secondary border-2' : '' ?>">
        <div class="fw-bold fs-4 text-secondary"><?= $kpi['draft'] ?></div>
        <div class="small text-muted">ร่าง/เตรียมการ</div>
      </div>
    </a>
  </div>
  <div class="col-6 col-md" style="min-width:120px">
    <a href="?fy=<?=$fy?>&dept=<?=$dept?>&status=submitted" class="text-decoration-none">
      <div class="card text-center py-3 <?= $status==='submitted' ? 'border-info border-2' : '' ?>">
        <div class="fw-bold fs-4 text-info"><?= $kpi['submitted'] ?></div>
        <div class="small text-muted">รออนุมัติ</div>
      </div>
    </a>
  </div>
  <div class="col-6 col-md" style="min-width:120px">
    <a href="?fy=<?=$fy?>&dept=<?=$dept?>&status=approved" class="text-decoration-none">
      <div class="card text-center py-3 <?= $status==='approved' ? 'border-success border-2' : '' ?>">
        <div class="fw-bold fs-4 text-success"><?= $kpi['approved'] ?></div>
        <div class="small text-muted">อนุมัติแล้ว</div>
      </div>
    </a>
  </div>
  <div class="col-6 col-md" style="min-width:120px">
    <a href="?fy=<?=$fy?>&dept=<?=$dept?>&status=rejected" class="text-decoration-none">
      <div class="card text-center py-3 <?= $status==='rejected' ? 'border-danger border-2' : '' ?>">
        <div class="fw-bold fs-4 text-danger"><?= $kpi['rejected'] ?></div>
        <div class="small text-muted">ปฏิเสธ</div>
      </div>
    </a>
  </div>
</div>

<!-- Filter -->
<div class="card mb-3">
  <div class="card-body py-2">
    <form method="GET" class="d-flex flex-wrap gap-2 align-items-center">
      <select name="fy" class="form-select form-select-sm" style="width:auto" onchange="this.form.submit()">
        <?php for ($y=2567;$y<=2572;$y++): ?>
        <option value="<?=$y?>" <?=$fy==$y?'selected':''?>><?=$y?></option>
        <?php endfor; ?>
      </select>
      <select name="dept" class="form-select form-select-sm" style="width:auto" onchange="this.form.submit()">
        <option value="0">-- ทุกฝ่าย --</option>
        <?php foreach ($depts as $d): ?>
        <option value="<?=$d['id']?>" <?=$dept==$d['id']?'selected':''?>><?=h($d['name'])?></option>
        <?php endforeach; ?>
      </select>
      <select name="status" class="form-select form-select-sm" style="width:auto" onchange="this.form.submit()">
        <option value="all"      <?=$status==='all'      ?'selected':''?>>-- ทุกสถานะ --</option>
        <option value="none"     <?=$status==='none'     ?'selected':''?>>ยังไม่ดำเนินการ</option>
        <option value="draft"    <?=$status==='draft'    ?'selected':''?>>ร่าง/เตรียมการ</option>
        <option value="submitted"<?=$status==='submitted'?'selected':''?>>รออนุมัติ</option>
        <option value="approved" <?=$status==='approved' ?'selected':''?>>อนุมัติแล้ว</option>
        <option value="rejected" <?=$status==='rejected' ?'selected':''?>>ปฏิเสธ</option>
      </select>
      <span class="text-muted small">พบ <?= count($projects) ?> รายการ</span>
    </form>
  </div>
</div>

<!-- Table -->
<div class="card">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover table-sm mb-0" style="font-size:13px">
        <thead class="table-light">
          <tr>
            <th class="ps-3" style="min-width:220px">โครงการ / กิจกรรม</th>
            <th style="min-width:140px">ฝ่าย</th>
            <th>ผู้รับผิดชอบ</th>
            <th class="text-end">งบจัดสรร</th>
            <th class="text-end">เบิกจ่ายแล้ว</th>
            <th class="text-center">สถานะ</th>
          </tr>
        </thead>
        <tbody>
        <?php if (empty($projects)): ?>
        <tr><td colspan="6" class="text-center py-4 text-muted">ไม่พบข้อมูล</td></tr>
        <?php endif; ?>
        <?php foreach ($projects as $p):
            $st = $p['proj_status'];
            $sl = $statusLabels[$st] ?? $statusLabels['none'];
            $pct = $p['alloc_total'] > 0 ? min(round($p['used_total']/$p['alloc_total']*100,1),100) : 0;
            $rowCls = $st==='none' ? 'table-warning' : ($st==='approved' ? '' : '');
        ?>
        <tr class="<?= $rowCls ?>">
          <td class="ps-3">
            <div class="fw-semibold"><?= h($p['project_name']) ?></div>
            <?php if ($p['activity']): ?>
            <div class="text-muted" style="font-size:11px"><?= h(mb_substr($p['activity'],0,70)) ?></div>
            <?php endif; ?>
          </td>
          <td class="text-muted"><?= h($p['dept_name']) ?></td>
          <td><?= h($p['owner_name'] ?? '') ?></td>
          <td class="text-end fw-semibold text-primary"><?= number_format($p['alloc_total'],0) ?></td>
          <td class="text-end">
            <?php if ($p['used_total'] > 0): ?>
            <span class="text-danger fw-semibold"><?= number_format($p['used_total'],0) ?></span>
            <div class="progress mt-1" style="height:4px;border-radius:2px;background:#e5e7eb">
              <div class="progress-bar bg-danger" style="width:<?=$pct?>%"></div>
            </div>
            <div style="font-size:10px;color:#6b7280"><?=$pct?>%</div>
            <?php else: ?>
            <span class="text-muted">—</span>
            <?php endif; ?>
          </td>
          <td class="text-center">
            <span class="badge bg-<?= $sl['badge'] ?>">
              <i class="<?= $sl['icon'] ?> me-1"></i><?= $sl['label'] ?>
            </span>
          </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php echo '</div></div></div>'; renderFooter(); ?>
