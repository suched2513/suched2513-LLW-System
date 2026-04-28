<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/layout.php';

requireRole(['director','admin','budget_officer','procurement_head','finance_head','deputy_director']);
$u = getCurrentUser();
$db = getDB();
$id = (int)($_GET['id'] ?? 0);

$stmt = $db->prepare("SELECT pr.*, bp.project_name, bp.activity, CONCAT(u.firstname,' ',u.lastname) AS teacher_name, u.user_id AS teacher_id, d.name AS dept_name
  FROM project_requests pr
  JOIN budget_projects bp ON pr.budget_project_id=bp.id
  JOIN llw_users u ON pr.user_id=u.user_id
  JOIN departments d ON bp.department_id=d.id
  WHERE pr.id=?");
$stmt->execute([$id]);
$req = $stmt->fetch();
if (!$req) { header('Location: pending.php'); exit; }

$items = $db->prepare("SELECT * FROM request_items WHERE request_id=? ORDER BY item_order");
$items->execute([$id]); $itemList = $items->fetchAll();

// Determine next step logic
$steps = ['submitted', 'budget_approved', 'procurement_approved', 'finance_approved', 'deputy_approved', 'completed'];
$currentIndex = array_search($req['current_step'], $steps);

// Role mapping to steps
$roleStepMap = [
    'budget_officer' => 'submitted',
    'wfh_admin' => 'submitted',
    'procurement_head' => 'budget_approved',
    'finance_head' => 'procurement_approved',
    'deputy_director' => 'finance_approved',
    'director' => 'deputy_approved',
    'super_admin' => $req['current_step'],
    'admin' => $req['current_step']
];
$myCanApprove = ($roleStepMap[$u['role']] ?? '') === $req['current_step'];

// POST approve/reject
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $myCanApprove) {
    verifyCsrf();
    $action = $_POST['action'] ?? '';
    $note   = trim($_POST['note'] ?? '');
    
    if ($action === 'reject') {
        $db->prepare("UPDATE project_requests SET status='rejected', director_note=? WHERE id=?")->execute([$note, $id]);
        addNotification($req['teacher_id'], 'rejected', 'คำขอถูกปฏิเสธโดย' . roleLabel($u['role']), $req['project_name'], $id, 'project_request');
        auditLog('reject', 'project_request', $id);
        flashMessage('danger', 'ปฏิเสธคำขอเรียบร้อย');
        header('Location: ' . BASE_URL . '/director/pending.php'); exit;
    } 
    elseif ($action === 'approve') {
        $nextStep = $steps[$currentIndex + 1] ?? 'completed';
        $status = ($nextStep === 'completed') ? 'approved' : 'submitted';
        
        $sql = "UPDATE project_requests SET current_step=?, status=?";
        $params = [$nextStep, $status];
        
        if ($req['current_step'] === 'submitted') {
            $sql .= ", budget_ok_at=NOW(), budget_user_id=?, budget_note=?";
            array_push($params, $u['id'], $note);
        } elseif ($req['current_step'] === 'budget_approved') {
            $sql .= ", proc_ok_at=NOW(), proc_user_id=?, proc_note=?";
            array_push($params, $u['id'], $note);
        } elseif ($req['current_step'] === 'procurement_approved') {
            $sql .= ", fin_ok_at=NOW(), fin_user_id=?, fin_note=?";
            array_push($params, $u['id'], $note);
        } elseif ($req['current_step'] === 'finance_approved') {
            $sql .= ", deputy_ok_at=NOW(), deputy_user_id=?, deputy_note=?";
            array_push($params, $u['id'], $note);
        } elseif ($req['current_step'] === 'deputy_approved') {
            $sql .= ", approved_at=NOW(), director_note=?";
            array_push($params, $note);
        }
        
        $sql .= " WHERE id=?";
        $params[] = $id;
        
        $db->prepare($sql)->execute($params);
        
        addNotification($req['teacher_id'], 'info', 'คำขอผ่านขั้นตอน: ' . statusLabel($req['current_step']), $req['project_name'], $id, 'project_request');
        auditLog('approve', 'project_request', $id);
        
        flashMessage('success', 'ลงนามเรียบร้อยแล้ว');
        header('Location: ' . BASE_URL . '/director/pending.php'); exit;
    }
}

renderHead('พิจารณาและลงนามดิจิทัล');
echo '<div class="d-flex">'; renderSidebar(); echo '<div class="main-content flex-grow-1">'; renderTopbar('พิจารณาและลงนามดิจิทัล'); echo '<div class="page-content">'; showFlash();
?>

<div class="row g-3">
  <div class="col-lg-8">
    <!-- Project Info Card -->
    <div class="card mb-3">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span>รายละเอียดคำขอ</span>
        <span class="badge bg-primary"><?= statusLabel($req['current_step']) ?></span>
      </div>
      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-6">
            <label class="text-muted small">โครงการ</label>
            <div class="fw-bold"><?= h($req['project_name']) ?></div>
          </div>
          <div class="col-md-6">
            <label class="text-muted small">ผู้ขอโครงการ</label>
            <div><?= h($req['teacher_name']) ?> (ฝ่าย<?= h($req['dept_name']) ?>)</div>
          </div>
          <div class="col-md-6">
            <label class="text-muted small">ประเภท</label>
            <div><?= $req['proc_type'] === 'hire' ? 'จัดจ้าง' : 'จัดซื้อ' ?> | <?= h($req['project_no']) ?></div>
          </div>
          <div class="col-md-6">
            <label class="text-muted small">วันที่ขอ</label>
            <div><?= formatDate($req['request_date']) ?></div>
          </div>
          <div class="col-12">
            <label class="text-muted small">เหตุผลความจำเป็น</label>
            <div class="p-2 bg-light rounded" style="font-size:14px"><?= nl2br(h($req['reason'])) ?></div>
          </div>
        </div>
      </div>
    </div>

    <!-- Items Table Card -->
    <div class="card mb-3">
      <div class="card-header">รายการที่ขอซื้อ/จ้าง</div>
      <div class="card-body p-0">
        <table class="table mb-0" style="font-size:14px">
          <thead class="table-light"><tr><th>#</th><th>รายการ</th><th class="text-end">จำนวน</th><th class="text-end">ราคา/หน่วย</th><th class="text-end">รวม</th></tr></thead>
          <tbody>
          <?php $total=0; foreach ($itemList as $i => $it): $total += $it['total_price']; ?>
          <tr>
            <td><?= $i+1 ?></td>
            <td><?= h($it['item_name']) ?></td>
            <td class="text-end"><?= number_format($it['quantity'],0) ?> <?= h($it['unit']) ?></td>
            <td class="text-end"><?= number_format($it['unit_price'],2) ?></td>
            <td class="text-end"><?= number_format($it['total_price'],2) ?></td>
          </tr>
          <?php endforeach; ?>
          <tr class="table-light fw-bold"><td colspan="4" class="text-end">รวมทั้งสิ้น</td><td class="text-end text-primary"><?= number_format($total,2) ?> บาท</td></tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Approval History Card -->
    <div class="card">
      <div class="card-header">ประวัติการลงนามดิจิทัล</div>
      <div class="card-body p-0">
        <ul class="list-group list-group-flush">
          <li class="list-group-item d-flex justify-content-between align-items-start <?= $req['budget_ok_at']?'':'opacity-50' ?>">
            <div class="ms-2 me-auto">
              <div class="fw-bold">ฝ่ายงบประมาณ (แผนงาน)</div>
              <small class="text-muted"><?= $req['budget_note'] ?: '-' ?></small>
            </div>
            <?php if($req['budget_ok_at']): ?>
              <span class="badge bg-success rounded-pill">ลงนามแล้วเมื่อ <?= formatDate($req['budget_ok_at'],true) ?></span>
            <?php else: ?>
              <span class="badge bg-secondary rounded-pill">รอลงนาม</span>
            <?php endif; ?>
          </li>
          <li class="list-group-item d-flex justify-content-between align-items-start <?= $req['proc_ok_at']?'':'opacity-50' ?>">
            <div class="ms-2 me-auto">
              <div class="fw-bold">ฝ่ายพัสดุ</div>
              <small class="text-muted"><?= $req['proc_note'] ?: '-' ?></small>
            </div>
            <?php if($req['proc_ok_at']): ?>
              <span class="badge bg-success rounded-pill">ลงนามแล้วเมื่อ <?= formatDate($req['proc_ok_at'],true) ?></span>
            <?php else: ?>
              <span class="badge bg-secondary rounded-pill">รอลงนาม</span>
            <?php endif; ?>
          </li>
          <li class="list-group-item d-flex justify-content-between align-items-start <?= $req['fin_ok_at']?'':'opacity-50' ?>">
            <div class="ms-2 me-auto">
              <div class="fw-bold">ฝ่ายการเงิน</div>
              <small class="text-muted"><?= $req['fin_note'] ?: '-' ?></small>
            </div>
            <?php if($req['fin_ok_at']): ?>
              <span class="badge bg-success rounded-pill">ลงนามแล้วเมื่อ <?= formatDate($req['fin_ok_at'],true) ?></span>
            <?php else: ?>
              <span class="badge bg-secondary rounded-pill">รอลงนาม</span>
            <?php endif; ?>
          </li>
          <li class="list-group-item d-flex justify-content-between align-items-start <?= $req['deputy_ok_at']?'':'opacity-50' ?>">
            <div class="ms-2 me-auto">
              <div class="fw-bold">รองผู้อำนวยการ</div>
              <small class="text-muted"><?= $req['deputy_note'] ?: '-' ?></small>
            </div>
            <?php if($req['deputy_ok_at']): ?>
              <span class="badge bg-success rounded-pill">ลงนามแล้วเมื่อ <?= formatDate($req['deputy_ok_at'],true) ?></span>
            <?php else: ?>
              <span class="badge bg-secondary rounded-pill">รอลงนาม</span>
            <?php endif; ?>
          </li>
          <li class="list-group-item d-flex justify-content-between align-items-start <?= $req['approved_at']?'':'opacity-50' ?>">
            <div class="ms-2 me-auto">
              <div class="fw-bold">ผู้อำนวยการโรงเรียน</div>
              <small class="text-muted"><?= $req['director_note'] ?: '-' ?></small>
            </div>
            <?php if($req['approved_at']): ?>
              <span class="badge bg-success rounded-pill">อนุมัติแล้วเมื่อ <?= formatDate($req['approved_at'],true) ?></span>
            <?php else: ?>
              <span class="badge bg-secondary rounded-pill">รออนุมัติ</span>
            <?php endif; ?>
          </li>
        </ul>
      </div>
    </div>
  </div>

  <div class="col-lg-4">
    <!-- Action Card -->
    <div class="card border-primary shadow-sm mb-3">
      <div class="card-header bg-primary text-white">ดำเนินการลงนาม</div>
      <div class="card-body">
        <?php if (!$myCanApprove): ?>
          <div class="alert alert-warning small">
            <i class="bi bi-exclamation-triangle me-1"></i> 
            ขณะนี้อยู่ในขั้นตอนของ <strong><?= statusLabel($req['current_step']) ?></strong><br>
            คุณล็อกอินในฐานะ <strong><?= roleLabel($u['role']) ?></strong> ไม่มีสิทธิ์ลงนามในขั้นตอนนี้
          </div>
        <?php else: ?>
          <form method="post" id="approveForm">
            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
            <div class="mb-3">
              <label class="form-label fw-bold">บันทึกข้อความ/หมายเหตุ</label>
              <textarea name="note" class="form-control" rows="4" placeholder="ระบุข้อความประกอบการลงนาม (ถ้ามี)"></textarea>
            </div>
            <div class="d-grid gap-2">
              <button type="submit" name="action" value="approve" class="btn btn-success btn-lg shadow" onclick="return confirm('ยืนยันการลงนามดิจิทัล?')">
                <i class="bi bi-pen me-1"></i> ลงนามดิจิทัล
              </button>
              <button type="submit" name="action" value="reject" class="btn btn-outline-danger" onclick="return confirm('ยืนยันการปฏิเสธคำขอ?')">
                <i class="bi bi-x-circle me-1"></i> ปฏิเสธ/ตีกลับ
              </button>
            </div>
          </form>
        <?php endif; ?>
        <hr>
        <div class="d-grid gap-2">
          <a href="<?= BASE_URL ?>/documents/gen_memo.php?id=<?= $id ?>" target="_blank" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-file-earmark-pdf me-1"></i> ดูตัวอย่างเอกสาร
          </a>
          <a href="pending.php" class="btn btn-light btn-sm">กลับรายการรออนุมัติ</a>
        </div>
      </div>
    </div>
  </div>
</div>

<?php echo '</div></div></div>'; renderFooter(); ?>
