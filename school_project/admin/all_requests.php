<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/layout.php';
requireRole(['admin','director','budget_officer','procurement_head','finance_head','deputy_director']);
$db = getDB();
$filter = $_GET['status'] ?? '';
$dept = (int)($_GET['dept'] ?? 0);
$sql = "SELECT pr.*,bp.project_name,CONCAT(u.firstname,' ',u.lastname) AS teacher_name,d.name AS dept_name FROM project_requests pr JOIN budget_projects bp ON pr.budget_project_id=bp.id JOIN llw_users u ON pr.user_id=u.user_id JOIN departments d ON bp.department_id=d.id WHERE 1=1";
$params = [];
if ($filter) { $sql .= " AND pr.status=?"; $params[] = $filter; }
if ($dept) { $sql .= " AND d.id=?"; $params[] = $dept; }
$sql .= " ORDER BY pr.created_at DESC";
$s = $db->prepare($sql); $s->execute($params); $requests = $s->fetchAll();
$depts = $db->query("SELECT * FROM departments ORDER BY order_no")->fetchAll();
renderHead('คำขอทั้งหมด');
echo '<div class="d-flex">'; renderSidebar(); echo '<div class="main-content flex-grow-1">'; renderTopbar('คำขอทั้งหมด'); echo '<div class="page-content">'; showFlash();
?>
<div class="card mb-3"><div class="card-body py-2"><div class="d-flex gap-2 flex-wrap">
  <a href="?" class="btn btn-sm <?= !$filter?'btn-primary':'btn-outline-secondary' ?>">ทั้งหมด</a>
  <?php foreach(['submitted'=>'รออนุมัติ','approved'=>'อนุมัติ','rejected'=>'ปฏิเสธ','draft'=>'Draft'] as $st=>$l): ?>
  <a href="?status=<?=$st?>&dept=<?=$dept?>" class="btn btn-sm <?=$filter===$st?'btn-primary':'btn-outline-secondary'?>"><?=$l?></a>
  <?php endforeach; ?>
  <select class="form-select form-select-sm" style="width:auto" onchange="location='?status=<?=$filter?>&dept='+this.value">
    <option value="0">-- ทุกฝ่าย --</option>
    <?php foreach ($depts as $d): ?><option value="<?=$d['id']?>" <?=$dept==$d['id']?'selected':''?>><?=h($d['name'])?></option><?php endforeach; ?>
  </select>
</div></div></div>
<div class="card"><div class="card-header"><i class="bi bi-inbox me-2"></i>คำขอทั้งหมด (<?=count($requests)?> รายการ)</div>
<div class="card-body p-0"><table class="table table-hover mb-0">
  <thead><tr><th class="ps-4">โครงการ</th><th>ฝ่าย</th><th>ผู้ขอ</th><th>ประเภท</th><th class="text-end">วงเงิน</th><th class="text-center">สถานะ</th><th class="text-center">เอกสาร</th></tr></thead>
  <tbody>
  <?php foreach ($requests as $r): ?>
  <tr>
    <td class="ps-4"><div style="font-weight:500"><?=h($r['project_name'])?></div></td>
    <td><?=h($r['dept_name'])?></td><td><?=h($r['teacher_name'])?></td>
    <td><?=$r['proc_type']==='hire'?'จัดจ้าง':'จัดซื้อ'?></td>
    <td class="text-end fw-semibold"><?=formatMoney($r['amount_requested'])?></td>
    <td class="text-center"><?=statusBadge($r['status'])?></td>
    <td class="text-center">
      <?php if(in_array($r['status'],['submitted','approved'])): ?>
      <div class="dropdown"><button class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">เอกสาร</button>
      <ul class="dropdown-menu">
        <li><a class="dropdown-item" href="<?= BASE_URL ?>/documents/gen_memo.php?id=<?=$r['id']?>" target="_blank">บันทึกขออนุมัติ</a></li>
        <li><a class="dropdown-item" href="<?= BASE_URL ?>/documents/gen_committee.php?id=<?=$r['id']?>" target="_blank">แต่งตั้งกรรมการ</a></li>
        <li><a class="dropdown-item" href="<?= BASE_URL ?>/documents/gen_delivery.php?id=<?=$r['id']?>" target="_blank">ใบส่งมอบงาน</a></li>
      </ul></div>
      <?php else: ?><span class="text-muted small">-</span><?php endif; ?>
    </td>
  </tr>
  <?php endforeach; ?>
  </tbody>
</table></div></div>
<?php echo '</div></div></div>'; renderFooter(); ?>