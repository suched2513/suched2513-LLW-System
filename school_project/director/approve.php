<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/layout.php';
requireRole(['director','admin']);
$db  = getDB();
$id  = (int)($_GET['id'] ?? 0);

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
$items->execute([$id]); $items = $items->fetchAll();

// POST approve/reject
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';
    $note   = trim($_POST['director_note'] ?? '');
    if (in_array($action, ['approve','reject'])) {
        $status = $action === 'approve' ? 'approved' : 'rejected';
        $db->prepare("UPDATE project_requests SET status=?,director_note=?,approved_at=NOW() WHERE id=?")->execute([$status,$note,$id]);
        addNotification($req['teacher_id'], $status, $status === 'approved' ? 'คำขอของคุณได้รับการอนุมัติ' : 'คำขอของคุณถูกปฏิเสธ', $req['project_name'] . ($note ? ': '.$note : ''), $id, 'project_request');
        auditLog($action, 'project_request', $id);
        header('Location: ' . BASE_URL . '/director/pending.php'); exit;
    }
}

renderHead('พิจารณาคำขอ');
echo '<div class="d-flex">'; renderSidebar(); echo '<div class="main-content flex-grow-1">'; renderTopbar('พิจารณาคำขอ'); echo '<div class="page-content">';
?>

<div class="row g-3">
  <div class="col-lg-8">
    <div class="card mb-3">
      <div class="card-header">รายละเอียดคำขอ</div>
      <div class="card-body">
        <dl class="row mb-0" style="font-size:14px">
          <dt class="col-4 text-muted">โครงการ</dt><dd class="col-8"><?= h($req['project_name']) ?></dd>
          <dt class="col-4 text-muted">ฝ่าย</dt><dd class="col-8"><?= h($req['dept_name']) ?></dd>
          <dt class="col-4 text-muted">ผู้ขอ</dt><dd class="col-8"><?= h($req['teacher_name']) ?></dd>
          <dt class="col-4 text-muted">วันที่ขอ</dt><dd class="col-8"><?= formatDate($req['request_date']) ?></dd>
          <dt class="col-4 text-muted">ประเภท</dt><dd class="col-8"><?= $req['proc_type'] === 'hire' ? 'จัดจ้าง' : 'จัดซื้อ' ?></dd>
          <dt class="col-4 text-muted">เหตุผล</dt><dd class="col-8"><?= nl2br(h($req['reason'])) ?></dd>
        </dl>
      </div>
    </div>
    <div class="card mb-3">
      <div class="card-header">รายการขอใช้เงิน</div>
      <div class="card-body p-0">
        <table class="table mb-0">
          <thead><tr><th>#</th><th>รายการ</th><th class="text-end">จำนวน</th><th class="text-end">ราคา/หน่วย</th><th class="text-end">รวม</th></tr></thead>
          <tbody>
          <?php $total=0; foreach ($items as $i => $it): $total += $it['total_price']; ?>
          <tr>
            <td><?= $i+1 ?></td>
            <td><?= h($it['item_name']) ?></td>
            <td class="text-end"><?= number_format($it['quantity'],0) ?> <?= h($it['unit']) ?></td>
            <td class="text-end"><?= number_format($it['unit_price'],2) ?></td>
            <td class="text-end"><?= number_format($it['total_price'],2) ?></td>
          </tr>
          <?php endforeach; ?>
          <tr class="table-light fw-600"><td colspan="4" class="text-end">รวมทั้งสิ้น</td><td class="text-end"><?= number_format($total,2) ?> บาท</td></tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="col-lg-4">
    <div class="card">
      <div class="card-header">พิจารณา</div>
      <div class="card-body">
        <?php if ($req['status'] !== 'submitted'): ?>
        <div class="alert alert-secondary">คำขอนี้มีสถานะ: <strong><?= h($req['status']) ?></strong></div>
        <?php else: ?>
        <form method="post">
          <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
          <div class="mb-3">
            <label class="form-label">หมายเหตุ (ถ้ามี)</label>
            <textarea name="director_note" class="form-control" rows="3" placeholder="ระบุเหตุผลหรือข้อแนะนำ"></textarea>
          </div>
          <div class="d-grid gap-2">
            <button type="submit" name="action" value="approve" class="btn btn-success" onclick="return confirm('ยืนยันการอนุมัติ?')">
              <i class="bi bi-check-circle me-1"></i> อนุมัติ
            </button>
            <button type="submit" name="action" value="reject" class="btn btn-outline-danger" onclick="return confirm('ยืนยันการปฏิเสธ?')">
              <i class="bi bi-x-circle me-1"></i> ปฏิเสธ
            </button>
          </div>
        </form>
        <?php endif; ?>
        <hr>
        <a href="/documents/gen_memo.php?id=<?= $id ?>" target="_blank" class="btn btn-outline-primary w-100 mb-2"><i class="bi bi-file-word me-1"></i> บันทึกขออนุมัติ</a>
        <a href="/documents/gen_committee.php?id=<?= $id ?>" target="_blank" class="btn btn-outline-secondary w-100 mb-2"><i class="bi bi-file-word me-1"></i> บันทึกแต่งตั้งคณะกรรมการ</a>
        <a href="/documents/gen_delivery.php?id=<?= $id ?>" target="_blank" class="btn btn-outline-secondary w-100"><i class="bi bi-file-word me-1"></i> ใบส่งมอบงาน</a>
      </div>
    </div>
  </div>
</div>

<?php echo '</div></div></div>'; renderFooter(); ?>
