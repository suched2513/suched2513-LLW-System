<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/layout.php';
requireRole(['director','admin']);
$db = getDB();
$fy = FISCAL_YEAR;
$stats = $db->query("SELECT COUNT(*) AS total, SUM(status='submitted') AS submitted, SUM(status='approved') AS approved, SUM(status='rejected') AS rejected FROM project_requests pr JOIN budget_projects bp ON pr.budget_project_id=bp.id WHERE bp.fiscal_year=$fy")->fetch();
$budgetUsage = $db->query("SELECT * FROM v_budget_usage WHERE fiscal_year=$fy ORDER BY usage_pct DESC")->fetchAll();
$pending = $db->query("SELECT pr.*,bp.project_name,u.full_name AS teacher_name,d.name AS dept_name FROM project_requests pr JOIN budget_projects bp ON pr.budget_project_id=bp.id JOIN users u ON pr.user_id=u.id JOIN departments d ON bp.department_id=d.id WHERE pr.status='submitted' ORDER BY pr.created_at ASC LIMIT 5")->fetchAll();
$overdue = $db->query("SELECT bp.*,d.name AS dept_name,DATEDIFF(NOW(),bp.created_at) AS days_ago FROM budget_projects bp JOIN departments d ON bp.department_id=d.id LEFT JOIN project_requests pr ON pr.budget_project_id=bp.id WHERE bp.is_active=1 AND bp.fiscal_year=$fy AND pr.id IS NULL AND DATEDIFF(NOW(),bp.created_at)>30 LIMIT 5")->fetchAll();
$monthly = $db->query("SELECT DATE_FORMAT(created_at,'%Y-%m') AS month, COUNT(*) AS cnt FROM project_requests WHERE YEAR(created_at)>=".($fy-543)." GROUP BY month ORDER BY month DESC LIMIT 12")->fetchAll();

renderHead('Dashboard ผู้อำนวยการ');
echo '<div class="d-flex">'; renderSidebar(); echo '<div class="main-content flex-grow-1">'; renderTopbar('Dashboard ผู้อำนวยการ'); echo '<div class="page-content">'; showFlash();
?>
<div class="row g-3 mb-4">
  <div class="col-md-3"><div class="stat-card" style="background:linear-gradient(135deg,#1a56db,#3b82f6)"><div class="d-flex justify-content-between align-items-start"><div><div class="stat-value"><?=$stats['total']?0?></div><div class="stat-label">คำขอทั้งหมด</div></div><i class="bi bi-folder2 stat-icon"></i></div></div></div>
  <div class="col-md-3"><div class="stat-card" style="background:linear-gradient(135deg,#f59e0b,#fbbf24)"><div class="d-flex justify-content-between align-items-start"><div><div class="stat-value"><?=$stats['submitted']?0?></div><div class="stat-label">รออนุมัติ</div></div><i class="bi bi-hourglass stat-icon"></i></div></div></div>
  <div class="col-md-3"><div class="stat-card" style="background:linear-gradient(135deg,#10b981,#34d399)"><div class="d-flex justify-content-between align-items-start"><div><div class="stat-value"><?=$stats['approved']?0?></div><div class="stat-label">อนุมัติแล้ว</div></div><i class="bi bi-check-circle stat-icon"></i></div></div></div>
  <div class="col-md-3"><div class="stat-card" style="background:linear-gradient(135deg,#ef4444,#f87171)"><div class="d-flex justify-content-between align-items-start"><div><div class="stat-value"><?=count($overdue)?></div><div class="stat-label">โครงการค้างดำเนินการ</div></div><i class="bi bi-exclamation-triangle stat-icon"></i></div></div></div>
</div>

<div class="row g-3 mb-4">
  <div class="col-md-8">
    <div class="card h-100">
      <div class="card-header">ยอดใช้งบรายฝ่าย</div>
      <div class="card-body"><canvas id="budgetChart" height="200"></canvas></div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="card h-100">
      <div class="card-header">สถานะคำขอ</div>
      <div class="card-body"><canvas id="statusChart"></canvas></div>
    </div>
  </div>
</div>

<?php if (!empty($pending)): ?>
<div class="card mb-4">
  <div class="card-header d-flex justify-content-between align-items-center">
    <span><i class="bi bi-hourglass-split me-2"></i>รายการรออนุมัติ</span>
    <a href="/director/pending.php" class="btn btn-sm btn-outline-primary">ดูทั้งหมด</a>
  </div>
  <div class="card-body p-0">
    <table class="table table-hover mb-0">
      <thead><tr><th class="ps-4">โครงการ</th><th>ผู้ขอ</th><th class="text-end">วงเงิน</th><th class="text-center">ดำเนินการ</th></tr></thead>
      <tbody>
      <?php foreach ($pending as $r): ?>
      <tr>
        <td class="ps-4"><div style="font-size:14px;font-weight:500"><?=h($r['project_name'])?></div><div style="font-size:12px;color:#64748b"><?=h($r['dept_name'])?></div></td>
        <td><?=h($r['teacher_name'])?></td>
        <td class="text-end fw-semibold text-primary"><?=formatMoney($r['amount_requested'])?></td>
        <td class="text-center"><a href="/director/pending.php" class="btn btn-sm btn-success">พิจารณา</a></td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<?php if (!empty($overdue)): ?>
<div class="card mb-4">
  <div class="card-header text-danger"><i class="bi bi-exclamation-triangle me-2"></i>โครงการที่ยังไม่ดำเนินการ (เกิน 30 วัน)</div>
  <div class="card-body p-0">
    <table class="table table-hover mb-0">
      <thead><tr><th class="ps-4">โครงการ</th><th>ฝ่าย</th><th>ผู้รับผิดชอบ</th><th>ค้างมา (วัน)</th></tr></thead>
      <tbody>
      <?php foreach ($overdue as $p): ?>
      <tr><td class="ps-4"><?=h($p['project_name'])?></td><td><?=h($p['dept_name'])?></td><td><?=h($p['owner_name'])?></td><td><span class="badge bg-danger"><?=$p['days_ago']?> วัน</span></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
var budgetData = <?= json_encode(array_map(fn($b)=>['dept'=>$b['department_name'],'alloc'=>(float)$b['alloc_total'],'used'=>(float)$b['used_total'],'pct'=>(float)$b['usage_pct']], $budgetUsage)) ?>;
new Chart(document.getElementById('budgetChart'), {
  type:'bar', data:{labels:budgetData.map(d=>d.dept),
    datasets:[{label:'งบจัดสรร',data:budgetData.map(d=>d.alloc),backgroundColor:'rgba(26,86,219,0.2)',borderColor:'#1a56db',borderWidth:1},
              {label:'ใช้ไปแล้ว',data:budgetData.map(d=>d.used),backgroundColor:'rgba(16,185,129,0.7)',borderColor:'#10b981',borderWidth:1}]},
  options:{responsive:true,plugins:{legend:{position:'top'}},scales:{y:{beginAtZero:true,ticks:{callback:v=>v.toLocaleString('th-TH')}}}}
});
new Chart(document.getElementById('statusChart'), {
  type:'doughnut',
  data:{labels:['รออนุมัติ','อนุมัติ','ปฏิเสธ','Draft'],
        datasets:[{data:[<?=$stats['submitted']?0?>,<?=$stats['approved']?0?>,<?=$stats['rejected']?0?>,<?=($stats['total']?0)-($stats['submitted']?0)-($stats['approved']?0)-($stats['rejected']?0)?>],
        backgroundColor:['#f59e0b','#10b981','#ef4444','#94a3b8']}]},
  options:{responsive:true,plugins:{legend:{position:'bottom'}}}
});
</script>
<?php echo '</div></div></div>'; renderFooter(); ?>
