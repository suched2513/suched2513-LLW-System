<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/layout.php';
requireRole(['admin','director','budget_officer']);
$db = getDB();
$dept = (int)($_GET['dept'] ?? 0);
$fy = (int)($_GET['fy'] ?? FISCAL_YEAR);
$sql = "SELECT bp.*,d.name AS dept_name FROM budget_projects bp JOIN departments d ON bp.department_id=d.id WHERE bp.is_active=1 AND bp.fiscal_year=?";
$params = [$fy];
if ($dept) { $sql .= " AND bp.department_id=?"; $params[] = $dept; }
$sql .= " ORDER BY d.order_no,bp.id";
$s = $db->prepare($sql); $s->execute($params); $projects = $s->fetchAll();
$depts = $db->query("SELECT * FROM departments ORDER BY order_no")->fetchAll();
renderHead('รายการงบประมาณ');
echo '<div class="d-flex">'; renderSidebar(); echo '<div class="main-content flex-grow-1">'; renderTopbar('รายการงบประมาณโครงการ'); echo '<div class="page-content">'; showFlash();
?>
<div class="d-flex justify-content-between mb-3 flex-wrap gap-2">
  <div class="d-flex gap-2">
    <select class="form-select form-select-sm" style="width:auto" onchange="location='?fy='+this.value+'&dept=<?=$dept?>'">
      <?php for($y=2567;$y<=2572;$y++): ?><option value="<?=$y?>" <?=$fy==$y?'selected':''?>><?=$y?></option><?php endfor; ?>
    </select>
    <select class="form-select form-select-sm" style="width:auto" onchange="location='?fy=<?=$fy?>&dept='+this.value">
      <option value="0">-- ทุกฝ่าย --</option>
      <?php foreach ($depts as $d): ?><option value="<?=$d['id']?>" <?=$dept==$d['id']?'selected':''?>><?=h($d['name'])?></option><?php endforeach; ?>
    </select>
  </div>
  <a href="/admin/import_budget.php" class="btn btn-sm btn-success"><i class="bi bi-upload me-1"></i>Import งบประมาณ</a>
</div>
<div class="card">
  <div class="card-header"><i class="bi bi-table me-2"></i>รายการงบประมาณ ปีงบประมาณ <?=$fy?> (<?=count($projects)?> รายการ)</div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover table-sm mb-0">
        <thead><tr><th class="ps-3">โครงการ/กิจกรรม</th><th>ฝ่าย</th><th>ผู้รับผิดชอบ</th><th class="text-end">อุดหนุน</th><th class="text-end">คุณภาพ</th><th class="text-end">รายได้</th><th class="text-end">รวม</th></tr></thead>
        <tbody>
        <?php foreach ($projects as $p): ?>
        <?php $total = $p['budget_subsidy']+$p['budget_quality']+$p['budget_revenue']+$p['budget_operation']+$p['budget_reserve']; ?>
        <tr>
          <td class="ps-3"><div style="font-size:13px;font-weight:500"><?=h($p['project_name'])?></div><div style="font-size:11px;color:#64748b"><?=h(mb_substr($p['activity']??'',0,60))?></div></td>
          <td style="font-size:12px"><?=h($p['dept_name'])?></td>
          <td style="font-size:12px"><?=h($p['owner_name'])?></td>
          <td class="text-end" style="font-size:12px"><?=$p['budget_subsidy']>0?formatMoney($p['budget_subsidy']):'<span class="text-muted">-</span>'?></td>
          <td class="text-end" style="font-size:12px"><?=$p['budget_quality']>0?formatMoney($p['budget_quality']):'<span class="text-muted">-</span>'?></td>
          <td class="text-end" style="font-size:12px"><?=$p['budget_revenue']>0?formatMoney($p['budget_revenue']):'<span class="text-muted">-</span>'?></td>
          <td class="text-end fw-semibold" style="color:#1a56db"><?=formatMoney($total)?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php echo '</div></div></div>'; renderFooter(); ?>