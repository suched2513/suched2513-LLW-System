<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/layout.php';
requireRole(['teacher','head','admin','budget_officer','director']);
$u = getCurrentUser();
$db = getDB();
$id = (int)($_GET['id'] ?? 0);
$req = $db->prepare("SELECT pr.*,bp.project_name,bp.activity,d.name AS dept_name,CONCAT(u2.firstname,' ',u2.lastname) AS teacher_name,bp.budget_subsidy,bp.budget_quality,bp.budget_revenue FROM project_requests pr JOIN budget_projects bp ON pr.budget_project_id=bp.id JOIN departments d ON bp.department_id=d.id JOIN llw_users u2 ON pr.user_id=u2.user_id WHERE pr.id=?");
$req->execute([$id]); $r = $req->fetch();
if (!$r) { flashMessage('danger','ไม่พบข้อมูล'); header('Location: ' . BASE_URL . '/index.php'); exit; }
$items = $db->prepare("SELECT * FROM request_items WHERE request_id=? ORDER BY item_order");
$items->execute([$id]); $itemList = $items->fetchAll();
$committee = $db->prepare("SELECT * FROM request_committee WHERE request_id=? ORDER BY member_order");
$committee->execute([$id]); $members = $committee->fetchAll();
renderHead('รายละเอียดคำขอ');
echo '<div class="d-flex">'; renderSidebar(); echo '<div class="main-content flex-grow-1">'; renderTopbar('รายละเอียดคำขอ'); echo '<div class="page-content">'; showFlash();
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <div><?=statusBadge($r['status'])?></div>
  <?php if (in_array($r['status'],['submitted','approved'])): ?>
  <div class="d-flex gap-2">
    <a href="<?= BASE_URL ?>/documents/gen_memo.php?id=<?=$r['id']?>" target="_blank" class="btn btn-sm btn-outline-primary"><i class="bi bi-file-word me-1"></i>บันทึกขออนุมัติ</a>
    <a href="<?= BASE_URL ?>/documents/gen_committee.php?id=<?=$r['id']?>" target="_blank" class="btn btn-sm btn-outline-primary"><i class="bi bi-file-word me-1"></i>แต่งตั้งกรรมการ</a>
    <a href="<?= BASE_URL ?>/documents/gen_delivery.php?id=<?=$r['id']?>" target="_blank" class="btn btn-sm btn-outline-primary"><i class="bi bi-file-word me-1"></i>ใบส่งมอบ</a>
  </div>
  <?php endif; ?>
</div>
<div class="card mb-3"><div class="card-header">ข้อมูลโครงการ</div><div class="card-body">
  <div class="row g-3">
    <div class="col-md-6"><label class="form-label text-muted">โครงการ</label><div class="fw-semibold"><?=h($r['project_name'])?></div></div>
    <div class="col-md-6"><label class="form-label text-muted">ฝ่าย</label><div><?=h($r['dept_name'])?></div></div>
    <div class="col-md-4"><label class="form-label text-muted">ประเภท</label><div><?=$r['proc_type']==='hire'?'จัดจ้าง':'จัดซื้อ'?></div></div>
    <div class="col-md-4"><label class="form-label text-muted">วันที่</label><div><?=formatDate($r['request_date'])?></div></div>
    <div class="col-md-4"><label class="form-label text-muted">วงเงิน</label><div class="fw-bold text-primary"><?=formatMoney($r['amount_requested'])?> บาท</div></div>
    <div class="col-12"><label class="form-label text-muted">เหตุผล</label><div><?=h($r['reason'])?></div></div>
    <?php if ($r['director_note']): ?><div class="col-12"><label class="form-label text-muted">หมายเหตุผู้อำนวยการ</label><div class="alert alert-<?=$r['status']==='approved'?'success':'danger'?> py-2"><?=h($r['director_note'])?></div></div><?php endif; ?>
  </div>
</div></div>
<div class="card mb-3"><div class="card-header">รายการขอใช้เงิน</div><div class="card-body p-0">
  <table class="table table-sm mb-0">
    <thead><tr><th class="ps-3">#</th><th>รายการ</th><th class="text-center">จำนวน</th><th>หน่วย</th><th class="text-end">ราคา/หน่วย</th><th class="text-end">รวม</th></tr></thead>
    <tbody>
    <?php foreach ($itemList as $i=>$item): ?>
    <tr><td class="ps-3"><?=$i+1?></td><td><?=h($item['item_name'])?></td><td class="text-center"><?=$item['quantity']?></td><td><?=h($item['unit'])?></td><td class="text-end"><?=formatMoney($item['unit_price'])?></td><td class="text-end fw-semibold"><?=formatMoney($item['total_price'])?></td></tr>
    <?php endforeach; ?>
    <tr class="table-light fw-bold"><td colspan="4"></td><td class="text-end">รวม</td><td class="text-end text-primary"><?=formatMoney($r['amount_requested'])?></td></tr>
    </tbody>
  </table>
</div></div>
<?php if (!empty($members)): ?>
<div class="card"><div class="card-header">คณะกรรมการ</div><div class="card-body p-0">
  <table class="table table-sm mb-0">
    <thead><tr><th class="ps-3">#</th><th>ชื่อ-สกุล</th><th>ตำแหน่ง</th><th>หน้าที่</th></tr></thead>
    <tbody>
    <?php foreach ($members as $i=>$m): ?>
    <tr><td class="ps-3"><?=$i+1?></td><td><?=h($m['member_name'])?></td><td><?=h($m['position'])?></td><td><span class="badge bg-light text-dark"><?=h($m['role'])?></span></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div></div>
<?php endif; ?>
<?php echo '</div></div></div>'; renderFooter(); ?>