<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/layout.php';
requireRole(['admin','super_admin']);
$db = getDB();

$fy   = (int)($_GET['fy']   ?? FISCAL_YEAR);
$dept = (int)($_GET['dept'] ?? 0);

$depts = $db->query("SELECT * FROM departments ORDER BY order_no")->fetchAll();

$params = [$fy];
$deptFilter = '';
if ($dept) { $deptFilter = " AND bp.department_id = ?"; $params[] = $dept; }

try {
    $stmt = $db->prepare("
        SELECT
            bp.owner_name,
            COUNT(DISTINCT bp.project_name)  AS project_count,
            COUNT(bp.id)                     AS activity_count,
            SUM(bp.budget_subsidy + bp.budget_quality + bp.budget_revenue
                + bp.budget_operation + bp.budget_reserve) AS budget_total,
            COALESCE(SUM(CASE WHEN pr_agg.best_status='approved'  THEN 1 ELSE 0 END),0) AS done_count,
            COALESCE(SUM(CASE WHEN pr_agg.best_status='submitted' THEN 1 ELSE 0 END),0) AS pending_count,
            COALESCE(SUM(CASE WHEN pr_agg.best_status IS NULL
                              OR pr_agg.best_status='none' THEN 1 ELSE 0 END),0)         AS none_count
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
        WHERE bp.is_active = 1 AND bp.fiscal_year = ? $deptFilter
          AND bp.owner_name IS NOT NULL AND bp.owner_name != ''
        GROUP BY bp.owner_name
        ORDER BY activity_count DESC, project_count DESC
    ");
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
} catch (Exception $e) {
    $rows = [];
    error_log($e->getMessage());
}

$totalPeople     = count($rows);
$totalActivities = array_sum(array_column($rows, 'activity_count'));
$totalProjects   = array_sum(array_column($rows, 'project_count'));
$totalBudget     = array_sum(array_column($rows, 'budget_total'));
$avgActivities   = $totalPeople > 0 ? round($totalActivities / $totalPeople, 1) : 0;
$avgProjects     = $totalPeople > 0 ? round($totalProjects    / $totalPeople, 1) : 0;
$maxActivities   = $totalPeople > 0 ? max(array_column($rows, 'activity_count')) : 1;

renderHead('ปริมาณงานรายบุคคล');
echo '<div class="d-flex">'; renderSidebar(); echo '<div class="main-content flex-grow-1">'; renderTopbar('ปริมาณงานรายบุคคล'); echo '<div class="page-content">'; showFlash();
?>

<!-- Filter -->
<div class="card mb-3">
  <div class="card-body py-2">
    <form method="GET" class="d-flex flex-wrap gap-2 align-items-center">
      <label class="small fw-semibold mb-0">ปีงบประมาณ:</label>
      <select name="fy" class="form-select form-select-sm" style="width:auto" onchange="this.form.submit()">
        <?php for ($y=2567;$y<=2572;$y++): ?>
        <option value="<?=$y?>" <?=$fy==$y?'selected':''?>><?=$y?></option>
        <?php endfor; ?>
      </select>
      <label class="small fw-semibold mb-0">ฝ่าย:</label>
      <select name="dept" class="form-select form-select-sm" style="width:auto" onchange="this.form.submit()">
        <option value="0">-- ทุกฝ่าย --</option>
        <?php foreach ($depts as $d): ?>
        <option value="<?=$d['id']?>" <?=$dept==$d['id']?'selected':''?>><?=h($d['name'])?></option>
        <?php endforeach; ?>
      </select>
    </form>
  </div>
</div>

<!-- KPI -->
<div class="row g-3 mb-4">
  <div class="col-6 col-md-3">
    <div class="card text-center py-3">
      <div class="text-primary mb-1"><i class="bi bi-people" style="font-size:1.5rem"></i></div>
      <div class="fw-bold fs-4 text-primary"><?= $totalPeople ?></div>
      <div class="small text-muted">ผู้รับผิดชอบ</div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card text-center py-3">
      <div class="text-success mb-1"><i class="bi bi-folder2" style="font-size:1.5rem"></i></div>
      <div class="fw-bold fs-4 text-success"><?= $totalProjects ?></div>
      <div class="small text-muted">โครงการรวม</div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card text-center py-3">
      <div class="text-warning mb-1"><i class="bi bi-list-task" style="font-size:1.5rem"></i></div>
      <div class="fw-bold fs-4 text-warning"><?= $totalActivities ?></div>
      <div class="small text-muted">กิจกรรมรวม</div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card text-center py-3">
      <div class="text-info mb-1"><i class="bi bi-calculator" style="font-size:1.5rem"></i></div>
      <div class="fw-bold fs-4 text-info"><?= $avgActivities ?></div>
      <div class="small text-muted">เฉลี่ย กิจกรรม/คน</div>
    </div>
  </div>
</div>

<?php if (empty($rows)): ?>
<div class="card"><div class="card-body text-center text-muted py-5">ไม่พบข้อมูล</div></div>
<?php else: ?>

<!-- Chart -->
<div class="card mb-4">
  <div class="card-header fw-semibold"><i class="bi bi-bar-chart-horizontal me-2"></i>จำนวนกิจกรรมแต่ละคน (เส้นสีแดง = ค่าเฉลี่ย <?= $avgActivities ?> กิจกรรม/คน)</div>
  <div class="card-body" style="max-height:360px">
    <canvas id="workloadChart"></canvas>
  </div>
</div>

<!-- Table -->
<div class="card">
  <div class="card-header fw-semibold">
    <i class="bi bi-table me-2"></i>รายละเอียดปริมาณงานรายบุคคล — ปี <?= $fy ?>
    <span class="badge bg-info text-dark ms-2">ค่าเฉลี่ย <?=$avgActivities?> กิจกรรม / <?=$avgProjects?> โครงการ ต่อคน</span>
  </div>
  <div class="card-body p-0">
    <table class="table table-hover mb-0" style="font-size:13px">
      <thead class="table-light">
        <tr>
          <th class="ps-3">#</th>
          <th>ผู้รับผิดชอบ</th>
          <th class="text-center">โครงการ</th>
          <th class="text-center">กิจกรรม</th>
          <th class="text-center">เทียบค่าเฉลี่ย</th>
          <th class="text-center">อนุมัติแล้ว</th>
          <th class="text-center">รออนุมัติ</th>
          <th class="text-center">ยังไม่ดำเนินการ</th>
          <th class="text-end pe-3">งบรวม (บาท)</th>
          <th>สัดส่วนงาน</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($rows as $i => $r):
          $diff    = $r['activity_count'] - $avgActivities;
          $pct     = $maxActivities > 0 ? round($r['activity_count'] / $maxActivities * 100) : 0;
          if ($diff > $avgActivities * 0.3)       { $loadLabel = 'งานมาก';  $loadBadge = 'danger'; }
          elseif ($diff < -$avgActivities * 0.3)  { $loadLabel = 'งานน้อย'; $loadBadge = 'success'; }
          else                                     { $loadLabel = 'ปกติ';    $loadBadge = 'secondary'; }
      ?>
      <tr>
        <td class="ps-3 text-muted"><?= $i+1 ?></td>
        <td class="fw-semibold"><?= h($r['owner_name']) ?></td>
        <td class="text-center">
          <span class="badge bg-primary"><?= $r['project_count'] ?></span>
        </td>
        <td class="text-center">
          <span class="fw-bold" style="font-size:15px"><?= $r['activity_count'] ?></span>
        </td>
        <td class="text-center">
          <span class="badge bg-<?= $loadBadge ?>"><?= $loadLabel ?></span>
          <div class="small text-muted" style="font-size:10px">
            <?= $diff >= 0 ? '+' : '' ?><?= round($diff, 1) ?> จากค่าเฉลี่ย
          </div>
        </td>
        <td class="text-center">
          <?= $r['done_count'] > 0
              ? '<span class="badge bg-success">'.$r['done_count'].'</span>'
              : '<span class="text-muted">—</span>' ?>
        </td>
        <td class="text-center">
          <?= $r['pending_count'] > 0
              ? '<span class="badge bg-warning text-dark">'.$r['pending_count'].'</span>'
              : '<span class="text-muted">—</span>' ?>
        </td>
        <td class="text-center">
          <?= $r['none_count'] > 0
              ? '<span class="badge bg-danger">'.$r['none_count'].'</span>'
              : '<span class="text-muted">—</span>' ?>
        </td>
        <td class="text-end pe-3 text-primary"><?= number_format($r['budget_total'], 0) ?></td>
        <td style="min-width:120px">
          <div class="progress" style="height:10px;border-radius:5px;background:#e5e7eb">
            <div class="progress-bar bg-primary" style="width:<?=$pct?>%"></div>
          </div>
          <div style="font-size:10px;color:#6b7280;margin-top:1px"><?=$pct?>% ของสูงสุด</div>
        </td>
      </tr>
      <?php endforeach; ?>
      <tr class="table-light fw-bold">
        <td class="ps-3" colspan="2">รวม</td>
        <td class="text-center"><?= $totalProjects ?></td>
        <td class="text-center"><?= $totalActivities ?></td>
        <td class="text-center"><span class="badge bg-info text-dark">เฉลี่ย <?=$avgActivities?>/คน</span></td>
        <td colspan="3"></td>
        <td class="text-end pe-3 text-primary"><?= number_format($totalBudget, 0) ?></td>
        <td></td>
      </tr>
      </tbody>
    </table>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const labels  = <?= json_encode(array_column($rows, 'owner_name')) ?>;
const data    = <?= json_encode(array_column($rows, 'activity_count')) ?>;
const avg     = <?= $avgActivities ?>;
const ctx     = document.getElementById('workloadChart').getContext('2d');
new Chart(ctx, {
    type: 'bar',
    data: {
        labels: labels,
        datasets: [
            {
                label: 'จำนวนกิจกรรม',
                data: data,
                backgroundColor: data.map(v => v > avg * 1.3 ? '#ef4444' : v < avg * 0.7 ? '#10b981' : '#3b82f6'),
                borderRadius: 4,
            },
            {
                label: 'ค่าเฉลี่ย',
                data: Array(labels.length).fill(avg),
                type: 'line',
                borderColor: '#ef4444',
                borderWidth: 2,
                borderDash: [6,3],
                pointRadius: 0,
                fill: false,
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: true } },
        scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }
    }
});
</script>

<?php endif; ?>
<?php echo '</div></div></div>'; renderFooter(); ?>
