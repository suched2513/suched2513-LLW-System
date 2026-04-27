<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/layout.php';
requireRole(['teacher','head']);
$u = getCurrentUser();
$db = getDB();
$s = $db->prepare("SELECT pr.*,bp.project_name,bp.activity,d.name AS dept_name FROM project_requests pr JOIN budget_projects bp ON pr.budget_project_id=bp.id JOIN departments d ON bp.department_id=d.id WHERE pr.user_id=? ORDER BY pr.created_at DESC");
$s->execute([$u['id']]);
$requests = $s->fetchAll();
renderHead('ประวัติคำขอ');
echo '<div class="d-flex">'; renderSidebar(); echo '<div class="main-content flex-grow-1">'; renderTopbar('ประวัติคำขอของฉัน'); echo '<div class="page-content">'; showFlash();
?>
<div class="card">
  <div class="card-header"><i class="bi bi-list-check me-2"></i>รายการคำขอทั้งหมด</div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead><tr><th class="ps-4">โครงการ</th><th>วันที่</th><th>ประเภท</th><th class="text-end">วงเงิน</th><th class="text-center">สถานะ</th><th class="text-center">เอกสาร</th></tr></thead>
        <tbody>
<?php foreach ($requests as $r): ?>
        <tr>
          <td class="ps-4"><div style="font-weight:500"><?=h($r['project_name'])?></div><div style="font-size:12px;color:#64748b"><?=h(mb_substr($r['activity']??'',0,60))?></div></td>
          <td><?=formatDate($r['request_date'])?></td>
          <td><?=$r['proc_type']==='hire'?'จัดจ้าง':'จัดซื้อ'?></td>
          <td class="text-end fw-semibold"><?=formatMoney($r['amount_requested'])?></td>
          <td class="text-center"><?=statusBadge($r['status'])?></td>
          <td class="text-center">
            <?php if (in_array($r['status'],['submitted','approved'])): ?>
            <div class="dropdown"><button class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">เอกสาร</button>
            <ul class="dropdown-menu"><li><a class="dropdown-item" href="/documents/gen_memo.php?id=<?=$r['id']?>" target="_blank"><i class="bi bi-file-word me-2 text-primary"></i>บันทึกขออนุมัติ</a></li>
            <li><a class="dropdown-item" href="/documents/gen_committee.php?id=<?=$r['id']?>" target="_blank"><i class="bi bi-file-word me-2 text-primary"></i>แต่งตั้งกรรมการ</a></li>
            <li><a class="dropdown-item" href="/documents/gen_delivery.php?id=<?=$r['id']?>" target="_blank"><i class="bi bi-file-word me-2 text-primary"></i>ใบส่งมอบงาน</a></li></ul></div>
            <?php elseif ($r['status']==='draft'): ?>
            <a href="/teacher/request_form.php?req_id=<?=$r['id']?>" class="btn btn-sm btn-warning"><i class="bi bi-pencil me-1"></i>แก้ไข</a>
            <?php else: ?><span class="text-muted small">-</span><?php endif; ?>
          </td>
        </tr>
<?php endforeach; if(empty($requests)):?><tr><td colspan="6" class="text-center py-5 text-muted"><i class="bi bi-inbox fs-2 d-block mb-2"></i>ยังไม่มีประวัติคำขอ</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php echo '</div></div></div>'; renderFooter(); ?>
