<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/layout.php';
requireRole(['director','admin']);
$db = getDB();

if ($_SERVER['REQUEST_METHOD']==='POST') {
    verifyCsrf();
    $id = (int)$_POST['req_id'];
    $action = $_POST['action'];
    $note = $_POST['note'] ?? '';
    $status = $action==='approve' ? 'approved' : 'rejected';
    $s = $db->prepare("UPDATE project_requests SET status=?,director_note=?,approved_at=NOW() WHERE id=?");
    $s->execute([$status,$note,$id]);
    $stmt2 = $db->prepare("SELECT pr.user_id,bp.project_name FROM project_requests pr JOIN budget_projects bp ON pr.budget_project_id=bp.id WHERE pr.id=?");
    $stmt2->execute([$id]); $req = $stmt2->fetch();
    if ($req) addNotification($req['user_id'],$status,$status==='approved'?'คำขอได้รับอนุมัติ':'คำขอถูกปฏิเสธ',$req['project_name'],$id,'project_request');
    auditLog($action,'project_request',$id,['status'=>'submitted'],['status'=>$status,'note'=>$note]);
    flashMessage('success', $status==='approved'?'อนุมัติคำขอเรียบร้อย':'ปฏิเสธคำขอเรียบร้อย');
    header('Location: ' . BASE_URL . '/director/pending.php'); exit;
}

$requests = $db->query("SELECT pr.*,bp.project_name,bp.activity,CONCAT(u.firstname,' ',u.lastname) AS teacher_name,d.name AS dept_name FROM project_requests pr JOIN budget_projects bp ON pr.budget_project_id=bp.id JOIN llw_users u ON pr.user_id=u.user_id JOIN departments d ON bp.department_id=d.id WHERE pr.status='submitted' ORDER BY pr.created_at ASC")->fetchAll();
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
            <button class="btn btn-sm btn-success me-1" onclick="approveRequest(<?=$r['id']?>)"><i class="bi bi-check-lg me-1"></i>อนุมัติ</button>
            <button class="btn btn-sm btn-danger" onclick="rejectRequest(<?=$r['id']?>)"><i class="bi bi-x-lg me-1"></i>ปฏิเสธ</button>
          </td>
        </tr>
<?php endforeach; if(empty($requests)):?><tr><td colspan="7" class="text-center py-5 text-muted"><i class="bi bi-check-all fs-2 d-block mb-2"></i>ไม่มีคำขอรออนุมัติ</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<form method="POST" id="actionForm">
  <input type="hidden" name="csrf_token" value="<?=csrfToken()?>">
  <input type="hidden" name="req_id" id="req_id">
  <input type="hidden" name="action" id="req_action">
  <input type="hidden" name="note" id="req_note">
</form>
<script>
function approveRequest(id) {
  Swal.fire({title:'ยืนยันการอนุมัติ',text:'คุณต้องการอนุมัติคำขอนี้ใช่ไหม?',icon:'question',showCancelButton:true,confirmButtonText:'อนุมัติ',cancelButtonText:'ยกเลิก',confirmButtonColor:'#10b981'}).then(r=>{if(r.isConfirmed){document.getElementById('req_id').value=id;document.getElementById('req_action').value='approve';document.getElementById('actionForm').submit();}});
}
function rejectRequest(id) {
  Swal.fire({title:'ระบุเหตุผลการปฏิเสธ',input:'textarea',inputPlaceholder:'เหตุผล...',showCancelButton:true,confirmButtonText:'ปฏิเสธ',cancelButtonText:'ยกเลิก',confirmButtonColor:'#ef4444'}).then(r=>{if(r.isConfirmed){document.getElementById('req_id').value=id;document.getElementById('req_action').value='reject';document.getElementById('req_note').value=r.value;document.getElementById('actionForm').submit();}});
}
</script>
<?php echo '</div></div></div>'; renderFooter(); ?>
