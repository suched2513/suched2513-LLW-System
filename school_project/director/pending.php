<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/layout.php';
requireRole(['director','admin','budget_officer','procurement_head','finance_head','deputy_director']);
$u = getCurrentUser();
$db = getDB();

// Determine which step this user can approve
$roleStepMap = [
    'budget_officer' => 'budget',
    'procurement_head' => 'procurement',
    'finance_head' => 'finance',
    'deputy_director' => 'deputy',
    'director' => 'director',
    'admin' => 'all', // Admin can see all
    'super_admin' => 'all'
];
$myStep = $roleStepMap[$u['role']] ?? '';

$sql = "SELECT pr.*,bp.project_name,bp.activity,CONCAT(u.firstname,' ',u.lastname) AS teacher_name,d.name AS dept_name 
        FROM project_requests pr 
        JOIN budget_projects bp ON pr.budget_project_id=bp.id 
        JOIN llw_users u ON pr.user_id=u.user_id 
        JOIN departments d ON bp.department_id=d.id 
        WHERE pr.status NOT IN ('approved','rejected','draft')";

if ($myStep !== 'all' && $myStep !== '') {
    $sql .= " AND pr.current_step = " . $db->quote($myStep);
}

$sql .= " ORDER BY pr.created_at ASC";
$requests = $db->query($sql)->fetchAll();
renderHead('รออนุมัติ');
echo '<div class="d-flex">'; renderSidebar(); echo '<div class="main-content flex-grow-1">'; renderTopbar('คำขอรออนุมัติ'); echo '<div class="page-content">'; showFlash();
?>
<div class="card">
  <div class="card-header"><i class="bi bi-hourglass-split me-2"></i>คำขอรออนุมัติ (<?=count($requests)?> รายการ)</div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead><tr><th class="ps-4">โครงการ</th><th>ฝ่าย</th><th>ผู้ขอ</th><th>ประเภท</th><th class="text-end">วงเงิน</th><th>วันที่ยื่น</th><th class="text-center">ดำเนินการ</th></tr></thead>
        <tbody>
<?php foreach ($requests as $r): ?>
        <tr>
          <td class="ps-4"><div style="font-weight:500"><?=h($r['project_name'])?></div><div style="font-size:12px;color:#64748b"><?=h(mb_substr($r['activity']??'',0,60))?></div></td>
          <td><?=h($r['dept_name'])?></td>
          <td><?=h($r['teacher_name'])?></td>
          <td><?=$r['proc_type']==='hire'?'จัดจ้าง':'จัดซื้อ'?></td>
          <td class="text-end fw-semibold text-primary"><?=formatMoney($r['amount_requested'])?></td>
          <td><?=formatDate($r['created_at'])?></td>
          <td class="text-center">
            <a href="<?= BASE_URL ?>/director/approve.php?id=<?= $r['id'] ?>" class="btn btn-sm btn-primary">
              <i class="bi bi-pencil-square me-1"></i>พิจารณาและลงนาม
            </a>
          </td>
        </tr>
<?php endforeach; if(empty($requests)):?><tr><td colspan="7" class="text-center py-5 text-muted"><i class="bi bi-check-all fs-2 d-block mb-2"></i>ไม่มีคำขอรออนุมัติในขณะนี้</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php echo '</div></div></div>'; renderFooter(); ?>
<?php exit; // End of file logic ?>
