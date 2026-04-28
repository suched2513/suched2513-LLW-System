<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/layout.php';
requireRole(['teacher','head']);
$u = getCurrentUser();
$db = getDB();

$projectId = (int)($_GET['project_id'] ?? 0);
$reqId = (int)($_GET['req_id'] ?? 0);
$project = null; $request = null; $items = []; $committee = [];

if ($projectId) {
    $s = $db->prepare("SELECT bp.*,d.name AS dept_name FROM budget_projects bp JOIN departments d ON bp.department_id=d.id WHERE bp.id=?");
    $s->execute([$projectId]); $project = $s->fetch();
}
if ($reqId) {
    $s = $db->prepare("SELECT pr.*,bp.project_name,bp.activity,bp.budget_subsidy,bp.budget_quality,bp.budget_revenue,bp.budget_operation,bp.budget_reserve,d.name AS dept_name FROM project_requests pr JOIN budget_projects bp ON pr.budget_project_id=bp.id JOIN departments d ON bp.department_id=d.id WHERE pr.id=? AND pr.user_id=?");
    $s->execute([$reqId,$u['id']]); $request = $s->fetch();
    if ($request) { $projectId = $request['budget_project_id']; $project = $request; }
    $si = $db->prepare("SELECT * FROM request_items WHERE request_id=? ORDER BY item_order");
    $si->execute([$reqId]); $items = $si->fetchAll();
    $sc = $db->prepare("SELECT * FROM request_committee WHERE request_id=? ORDER BY member_order");
    $sc->execute([$reqId]); $committee = $sc->fetchAll();
}

$signatories = $db->query("SELECT * FROM signatories WHERE is_active=1 ORDER BY order_no")->fetchAll();

// Handle POST
if ($_SERVER['REQUEST_METHOD']==='POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? 'save';
    $status = ($action==='submit') ? 'submitted' : 'draft';
    $data = [
        'budget_project_id' => (int)$_POST['budget_project_id'],
        'user_id' => $u['id'],
        'project_no' => $_POST['project_no'] ?? '',
        'request_date' => $_POST['request_date'] ?: date('Y-m-d'),
        'proc_type' => $_POST['proc_type'] ?? 'hire',
        'reason' => $_POST['reason'] ?? '',
        'activity_detail' => $_POST['activity_detail'] ?? '',
        'inspector_name' => $_POST['inspector_name'] ?? '',
        'inspector_position' => $_POST['inspector_position'] ?? '',
        'fund_type_used' => $_POST['fund_type_used'] ?? '',
        'amount_requested' => (float)($_POST['amount_requested'] ?? 0),
        'status' => $status
    ];
    if ($reqId) {
        $keys = implode('=?,', array_keys($data)) . '=?';
        $s = $db->prepare("UPDATE project_requests SET $keys WHERE id=? AND user_id=?");
        $s->execute([...array_values($data), $reqId, $u['id']]);
        $db->prepare("DELETE FROM request_items WHERE request_id=?")->execute([$reqId]);
        $db->prepare("DELETE FROM request_committee WHERE request_id=?")->execute([$reqId]);
        $currentReqId = $reqId;
    } else {
        $cols = implode(',', array_keys($data));
        $vals = implode(',', array_fill(0, count($data), '?'));
        $s = $db->prepare("INSERT INTO project_requests ($cols) VALUES ($vals)");
        $s->execute(array_values($data));
        $currentReqId = $db->lastInsertId();
    }
    // items
    $names = $_POST['item_name'] ?? [];
    foreach ($names as $i => $name) {
        if (!trim($name)) continue;
        $qty = (float)($_POST['item_qty'][$i] ?? 1);
        $price = (float)($_POST['item_price'][$i] ?? 0);
        $si = $db->prepare("INSERT INTO request_items (request_id,item_order,item_name,quantity,unit,unit_price,total_price) VALUES (?,?,?,?,?,?,?)");
        $si->execute([$currentReqId,$i+1,$name,$qty,$_POST['item_unit'][$i]??'',$price,$qty*$price]);
    }
    // committee
    $mnames = $_POST['member_name'] ?? [];
    foreach ($mnames as $i => $mname) {
        if (!trim($mname)) continue;
        $sc = $db->prepare("INSERT INTO request_committee (request_id,member_order,member_name,position,role) VALUES (?,?,?,?,?)");
        $sc->execute([$currentReqId,$i+1,$mname,$_POST['member_pos'][$i]??'',$_POST['member_role'][$i]??'กรรมการ']);
    }
    auditLog($reqId?'update':'create','project_request',$currentReqId);
    if ($action==='submit') {
        // notify director
        $directors = $db->query("SELECT user_id AS id FROM llw_users WHERE role IN ('director','budget_officer','super_admin') AND status='active'")->fetchAll();
        foreach ($directors as $d) addNotification($d['id'],'pending_approval','มีคำขอดำเนินโครงการใหม่','จาก '.$u['full_name'],$currentReqId,'project_request');
        flashMessage('success','ส่งคำขอเรียบร้อยแล้ว รอผู้อำนวยการอนุมัติ');
        header('Location: ' . BASE_URL . '/teacher/request_list.php'); exit;
    }
    flashMessage('success','บันทึก draft เรียบร้อย');
    header('Location: ' . BASE_URL . '/teacher/request_form.php?req_id='.$currentReqId); exit;
}

renderHead('ขอดำเนินโครงการ');
echo '<div class="d-flex">';
renderSidebar();
echo '<div class="main-content flex-grow-1">';
renderTopbar('ขอดำเนินโครงการ');
echo '<div class="page-content">';
showFlash();
$budgetTotal = $project ? ($project['budget_subsidy']+$project['budget_quality']+$project['budget_revenue']+$project['budget_operation']+$project['budget_reserve']) : 0;
?>
<div class="card mb-3">
  <div class="card-body py-3">
    <div class="row align-items-center">
      <div class="col-md-8">
        <h6 class="fw-bold mb-1 text-primary"><?= h($project['project_name'] ?? '') ?></h6>
        <div style="font-size:13px;color:#64748b"><?= h($project['activity'] ?? '') ?></div>
        <div style="font-size:13px;color:#64748b">ฝ่าย: <?= h($project['dept_name'] ?? '') ?></div>
      </div>
      <div class="col-md-4 text-end">
        <div style="font-size:12px;color:#64748b">วงเงินที่ได้รับจัดสรร</div>
        <div style="font-size:22px;font-weight:700;color:#1a56db"><?= formatMoney($budgetTotal) ?> บาท</div>
      </div>
    </div>
  </div>
</div>

<div class="wizard-steps mb-4">
  <?php $steps = ['ข้อมูลโครงการ','รายการขอใช้เงิน','คณะกรรมการ','สรุป']; ?>
  <?php foreach ($steps as $i => $step): ?>
  <div class="wizard-step">
    <div class="step-circle <?= $i===0?'active':'' ?>" id="circle-<?=$i?>"><?= $i+1 ?></div>
    <div class="step-label <?= $i===0?'active':'' ?>" id="label-<?=$i?>"><?= $step ?></div>
  </div>
  <?php endforeach; ?>
</div>

<form method="POST" id="mainForm">
<input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
<input type="hidden" name="budget_project_id" value="<?= $projectId ?>">
<input type="hidden" name="action" id="formAction" value="save">
<input type="hidden" name="amount_requested" id="total-hidden" value="<?= $request['amount_requested']??0 ?>">

<!-- Step 1 -->
<div class="wizard-section card mb-3">
  <div class="card-header">ขั้นตอนที่ 1: ข้อมูลโครงการ</div>
  <div class="card-body">
    <div class="row g-3">
      <div class="col-md-4">
        <label class="form-label">เลขที่ (ถ้ามี)</label>
        <input type="text" name="project_no" class="form-control" value="<?= h($request['project_no']??'') ?>" placeholder="เช่น 107/2568">
      </div>
      <div class="col-md-4">
        <label class="form-label">วันที่</label>
        <input type="date" name="request_date" class="form-control" value="<?= h($request['request_date']??date('Y-m-d')) ?>">
      </div>
      <div class="col-md-4">
        <label class="form-label">ประเภท</label>
        <select name="proc_type" class="form-select">
          <option value="hire" <?= ($request['proc_type']??'hire')==='hire'?'selected':'' ?>>จัดจ้าง</option>
          <option value="buy" <?= ($request['proc_type']??'')==='buy'?'selected':'' ?>>จัดซื้อ</option>
        </select>
      </div>
      <div class="col-md-6">
        <label class="form-label">ผู้ตรวจรับ</label>
        <input type="text" name="inspector_name" class="form-control" value="<?= h($request['inspector_name']??$u['full_name']) ?>">
      </div>
      <div class="col-md-6">
        <label class="form-label">ตำแหน่งผู้ตรวจรับ</label>
        <input type="text" name="inspector_position" class="form-control" value="<?= h($request['inspector_position']??'ครู') ?>">
      </div>
      <div class="col-md-6">
        <label class="form-label">ประเภทเงินที่ขอใช้</label>
        <select name="fund_type_used" class="form-select">
          <?php $fundTypes=['budget_subsidy'=>'เงินอุดหนุน','budget_quality'=>'งบพัฒนาคุณภาพผู้เรียน','budget_revenue'=>'เงินรายได้สถานศึกษา','budget_operation'=>'งบงานประจำ','budget_reserve'=>'เงินสำรองจ่าย']; ?>
          <?php foreach ($fundTypes as $k=>$v): ?>
          <option value="<?=$k?>" <?= ($request['fund_type_used']??'')===$k?'selected':'' ?>><?=$v?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-12">
        <label class="form-label">เหตุผลความจำเป็น <span class="text-danger">*</span></label>
        <textarea name="reason" class="form-control" rows="3" required><?= h($request['reason']??'') ?></textarea>
      </div>
    </div>
    <div class="text-end mt-3"><button type="button" class="btn btn-primary" onclick="showStep(2)">ถัดไป →</button></div>
  </div>
</div>

<!-- Step 2 -->
<div class="wizard-section card mb-3" style="display:none">
  <div class="card-header">ขั้นตอนที่ 2: รายการขอใช้เงิน</div>
  <div class="card-body">
    <div class="table-responsive mb-3">
      <table class="table table-bordered" id="itemTable">
        <thead class="table-light"><tr>
          <th width="40">#</th><th>รายการ</th><th width="80">จำนวน</th>
          <th width="80">หน่วย</th><th width="120">ราคา/หน่วย</th>
          <th width="120">รวม (บาท)</th><th width="40"></th>
        </tr></thead>
        <tbody id="itemBody">
<?php foreach ($items as $i => $item): ?>
        <tr class="item-row">
          <td><?= $i+1 ?></td>
          <td><input type="text" name="item_name[]" class="form-control form-control-sm" value="<?= h($item['item_name']) ?>" required></td>
          <td><input type="number" name="item_qty[]" class="form-control form-control-sm qty" value="<?= $item['quantity'] ?>" min="0.01" step="any" oninput="calcTotal()"></td>
          <td><input type="text" name="item_unit[]" class="form-control form-control-sm" value="<?= h($item['unit']) ?>"></td>
          <td><input type="number" name="item_price[]" class="form-control form-control-sm price" value="<?= $item['unit_price'] ?>" min="0" step="any" oninput="calcTotal()"></td>
          <td class="text-end fw-semibold total-display"><?= formatMoney($item['total_price']) ?></td>
          <td><button type="button" class="btn btn-sm btn-outline-danger" onclick="removeRow(this)"><i class="bi bi-trash"></i></button></td>
        </tr>
<?php endforeach; ?>
<?php if (empty($items)): ?>
        <tr class="item-row">
          <td>1</td>
          <td><input type="text" name="item_name[]" class="form-control form-control-sm" placeholder="ชื่อรายการ" required></td>
          <td><input type="number" name="item_qty[]" class="form-control form-control-sm qty" value="1" min="0.01" step="any" oninput="calcTotal()"></td>
          <td><input type="text" name="item_unit[]" class="form-control form-control-sm" placeholder="หน่วย"></td>
          <td><input type="number" name="item_price[]" class="form-control form-control-sm price" value="0" min="0" step="any" oninput="calcTotal()"></td>
          <td class="text-end fw-semibold total-display">0.00</td>
          <td><button type="button" class="btn btn-sm btn-outline-danger" onclick="removeRow(this)"><i class="bi bi-trash"></i></button></td>
        </tr>
<?php endif; ?>
        </tbody>
        <tfoot><tr class="table-light">
          <td colspan="4"></td>
          <td class="fw-bold text-end">รวมทั้งสิ้น</td>
          <td class="fw-bold text-end text-primary" id="grand-total"><?= formatMoney($request['amount_requested']??0) ?></td>
          <td></td>
        </tr></tfoot>
      </table>
    </div>
    <button type="button" class="btn btn-outline-primary btn-sm mb-3" onclick="addRow()">
      <i class="bi bi-plus-circle me-1"></i>เพิ่มรายการ
    </button>
    <div class="alert alert-info d-flex align-items-center gap-2">
      <i class="bi bi-info-circle"></i>
      วงเงินที่ได้รับจัดสรร: <strong><?= formatMoney($budgetTotal) ?> บาท</strong>
    </div>
    <div class="d-flex justify-content-between mt-2">
      <button type="button" class="btn btn-outline-secondary" onclick="showStep(1)">← ย้อนกลับ</button>
      <button type="button" class="btn btn-primary" onclick="showStep(3)">ถัดไป →</button>
    </div>
  </div>
</div>

<!-- Step 3 -->
<div class="wizard-section card mb-3" style="display:none">
  <div class="card-header">ขั้นตอนที่ 3: คณะกรรมการกำหนดราคากลาง</div>
  <div class="card-body">
    <div class="table-responsive mb-3">
      <table class="table table-bordered" id="committeeTable">
        <thead class="table-light"><tr>
          <th width="40">#</th><th>ชื่อ-สกุล</th><th width="150">ตำแหน่ง</th>
          <th width="200">บทบาทในคณะกรรมการ</th><th width="40"></th>
        </tr></thead>
        <tbody id="committeeBody">
<?php $roles=['ประธานกรรมการ','กรรมการ','กรรมการ','กรรมการและเลขานุการ']; ?>
<?php foreach ($committee ?: [['member_name'=>'','position'=>'ครู','role'=>'ประธานกรรมการ'],['member_name'=>'','position'=>'ครู','role'=>'กรรมการ'],['member_name'=>'','position'=>'ครู','role'=>'กรรมการและเลขานุการ']] as $i=>$m): ?>
        <tr>
          <td><?=$i+1?></td>
          <td><input type="text" name="member_name[]" class="form-control form-control-sm" value="<?=h($m['member_name'])?>"></td>
          <td><input type="text" name="member_pos[]" class="form-control form-control-sm" value="<?=h($m['position']??'ครู')?>"></td>
          <td><select name="member_role[]" class="form-select form-select-sm">
            <?php foreach (['ประธานกรรมการ','กรรมการ','กรรมการและเลขานุการ'] as $r): ?>
            <option value="<?=$r?>" <?=($m['role']??'')===$r?'selected':''?>><?=$r?></option>
            <?php endforeach; ?>
          </select></td>
          <td><button type="button" class="btn btn-sm btn-outline-danger" onclick="this.closest('tr').remove()"><i class="bi bi-trash"></i></button></td>
        </tr>
<?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <button type="button" class="btn btn-outline-primary btn-sm mb-3" onclick="addMember()">
      <i class="bi bi-plus-circle me-1"></i>เพิ่มกรรมการ
    </button>
    <div class="d-flex justify-content-between">
      <button type="button" class="btn btn-outline-secondary" onclick="showStep(2)">← ย้อนกลับ</button>
      <button type="button" class="btn btn-primary" onclick="showStep(4)">ถัดไป →</button>
    </div>
  </div>
</div>

<!-- Step 4 -->
<div class="wizard-section card mb-3" style="display:none">
  <div class="card-header">ขั้นตอนที่ 4: สรุปและส่งคำขอ</div>
  <div class="card-body">
    <div class="row g-3 mb-4">
      <div class="col-md-6"><div class="bg-light rounded p-3">
        <div class="text-muted small mb-1">โครงการ</div>
        <div class="fw-semibold"><?= h($project['project_name']??'') ?></div>
      </div></div>
      <div class="col-md-6"><div class="bg-light rounded p-3">
        <div class="text-muted small mb-1">วงเงินที่ขอ</div>
        <div class="fw-bold text-primary fs-5" id="summary-total"><?= formatMoney($request['amount_requested']??0) ?> บาท</div>
      </div></div>
    </div>
    <div class="alert alert-warning d-flex align-items-center gap-2">
      <i class="bi bi-exclamation-triangle"></i>
      เมื่อกด "ส่งคำขอ" จะไม่สามารถแก้ไขได้จนกว่าผู้อำนวยการจะพิจารณา
    </div>
    <div class="d-flex flex-wrap gap-2 justify-content-between">
      <button type="button" class="btn btn-outline-secondary" onclick="showStep(3)">← ย้อนกลับ</button>
      <div class="d-flex gap-2">
        <button type="submit" class="btn btn-outline-primary" onclick="document.getElementById('formAction').value='save'">
          <i class="bi bi-save me-1"></i>บันทึก Draft
        </button>
        <button type="button" class="btn btn-success" onclick="confirmSubmit()">
          <i class="bi bi-send me-1"></i>ส่งคำขออนุมัติ
        </button>
      </div>
    </div>
  </div>
</div>
</form>

<?php echo '</div></div></div>'; ?>
<script>
var rowCount = <?= max(count($items),1) ?>;
var memberCount = <?= max(count($committee),3) ?>;
function addRow() {
  rowCount++;
  var tr = '<tr class="item-row"><td>'+rowCount+'</td><td><input type="text" name="item_name[]" class="form-control form-control-sm" required></td><td><input type="number" name="item_qty[]" class="form-control form-control-sm qty" value="1" min="0.01" step="any" oninput="calcTotal()"></td><td><input type="text" name="item_unit[]" class="form-control form-control-sm"></td><td><input type="number" name="item_price[]" class="form-control form-control-sm price" value="0" step="any" oninput="calcTotal()"></td><td class="text-end fw-semibold total-display">0.00</td><td><button type="button" class="btn btn-sm btn-outline-danger" onclick="removeRow(this)"><i class="bi bi-trash"></i></button></td></tr>';
  document.getElementById('itemBody').insertAdjacentHTML('beforeend', tr);
}
function removeRow(btn) { btn.closest('tr').remove(); calcTotal(); }
function addMember() {
  memberCount++;
  var tr = '<tr><td>'+memberCount+'</td><td><input type="text" name="member_name[]" class="form-control form-control-sm"></td><td><input type="text" name="member_pos[]" class="form-control form-control-sm" value="ครู"></td><td><select name="member_role[]" class="form-select form-select-sm"><option value="ประธานกรรมการ">ประธานกรรมการ</option><option value="กรรมการ" selected>กรรมการ</option><option value="กรรมการและเลขานุการ">กรรมการและเลขานุการ</option></select></td><td><button type="button" class="btn btn-sm btn-outline-danger" onclick="this.closest(\'tr\').remove()"><i class="bi bi-trash"></i></button></td></tr>';
  document.getElementById('committeeBody').insertAdjacentHTML('beforeend', tr);
}
function confirmSubmit() {
  Swal.fire({title:'ยืนยันการส่งคำขอ',text:'เมื่อส่งแล้วจะไม่สามารถแก้ไขได้จนกว่าจะได้รับการพิจารณา',icon:'question',showCancelButton:true,confirmButtonText:'ส่งคำขอ',cancelButtonText:'ยกเลิก',confirmButtonColor:'#10b981'}).then(r=>{if(r.isConfirmed){document.getElementById('formAction').value='submit';document.getElementById('mainForm').submit();}});
}
function updateSummaryTotal() {
  var total = 0;
  document.querySelectorAll('.item-row').forEach(function(row) {
    var qty = parseFloat(row.querySelector('.qty')?.value)||0;
    var price = parseFloat(row.querySelector('.price')?.value)||0;
    total += qty * price;
    var td = row.querySelector('.total-display');
    if (td) td.textContent = (qty*price).toLocaleString('th-TH',{minimumFractionDigits:2});
  });
  document.getElementById('grand-total').textContent = total.toLocaleString('th-TH',{minimumFractionDigits:2});
  document.getElementById('total-hidden').value = total.toFixed(2);
  var st = document.getElementById('summary-total');
  if (st) st.textContent = total.toLocaleString('th-TH',{minimumFractionDigits:2}) + ' บาท';
}
window.calcTotal = updateSummaryTotal;
window.currentStep = 1;
</script>
<?php renderFooter(); ?>
