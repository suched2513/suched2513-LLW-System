<?php
session_start();
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../config.php';
busRequireStaff(['bus_admin', 'bus_finance', 'super_admin', 'wfh_admin']);

$pdo      = getPdo();
$semester = busGetSemester();
$semLabel = busSemesterLabel($semester);
$msg      = '';
$err      = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action   = $_POST['action'] ?? '';
    $cancelId = (int)($_POST['cancel_id'] ?? 0);
    $note     = trim($_POST['note'] ?? '');
    $staffId  = (int)($_SESSION['user_id'] ?? 0);

    if ($cancelId <= 0 || !in_array($action, ['approve', 'reject'])) {
        $err = 'ข้อมูลไม่ถูกต้อง';
    } else {
        try {
            $pdo->beginTransaction();

            // Get cancel request + registration
            $crStmt = $pdo->prepare("SELECT cr.*, reg.student_id, reg.semester FROM bus_cancel_requests cr JOIN bus_registrations reg ON reg.id = cr.registration_id WHERE cr.id = ? AND cr.status = 'pending'");
            $crStmt->execute([$cancelId]);
            $cr = $crStmt->fetch(PDO::FETCH_ASSOC);

            if (!$cr) {
                $pdo->rollBack();
                $err = 'ไม่พบคำขอยกเลิกหรือดำเนินการไปแล้ว';
            } else {
                if ($action === 'approve') {
                    $pdo->prepare("UPDATE bus_cancel_requests SET status='approved', admin_note=?, reviewed_by=?, reviewed_at=NOW() WHERE id=?")
                        ->execute([$note, $staffId ?: null, $cancelId]);
                    $pdo->prepare("UPDATE bus_registrations SET status='cancelled', cancelled_at=NOW(), notes=? WHERE id=?")
                        ->execute([$note, $cr['registration_id']]);
                    $msg = 'อนุมัติการยกเลิกเรียบร้อยแล้ว';
                } else {
                    $pdo->prepare("UPDATE bus_cancel_requests SET status='rejected', admin_note=?, reviewed_by=?, reviewed_at=NOW() WHERE id=?")
                        ->execute([$note, $staffId ?: null, $cancelId]);
                    $pdo->prepare("UPDATE bus_registrations SET status='active' WHERE id=?")
                        ->execute([$cr['registration_id']]);
                    $msg = 'ปฏิเสธคำขอยกเลิกเรียบร้อยแล้ว';
                }
                $pdo->commit();
            }
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log($e->getMessage());
            $err = 'เกิดข้อผิดพลาด กรุณาลองใหม่';
        }
    }
}

$filterStatus = $_GET['status'] ?? 'pending';
$validStatuses = ['pending', 'approved', 'rejected', 'all'];
if (!in_array($filterStatus, $validStatuses)) $filterStatus = 'pending';

try {
    $where  = $filterStatus === 'all' ? '' : "AND cr.status = ?";
    $params = [$semester];
    if ($filterStatus !== 'all') $params[] = $filterStatus;

    $stmt = $pdo->prepare("
        SELECT cr.id, cr.reason, cr.admin_note, cr.status, cr.created_at, cr.reviewed_at,
               s.student_id, s.fullname, s.classroom,
               rt.route_code, rt.route_name, rt.price,
               reviewer.firstname as reviewer_name,
               reg.id as reg_id
        FROM bus_cancel_requests cr
        JOIN bus_registrations reg ON reg.id = cr.registration_id
        JOIN bus_students s ON s.id = reg.student_id
        JOIN bus_routes rt ON rt.id = reg.route_id
        LEFT JOIN llw_users reviewer ON reviewer.user_id = cr.reviewed_by
        WHERE reg.semester = ? $where
        ORDER BY cr.created_at DESC
    ");
    $stmt->execute($params);
    $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log($e->getMessage());
    $requests = [];
}

$pageTitle    = 'คำขอยกเลิก';
$pageSubtitle = $semLabel;
$activeSystem = 'bus';
require_once __DIR__ . '/../../components/layout_start.php';
?>

<?php if ($msg): ?>
<div class="alert alert-success alert-dismissible fade show"><i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($msg) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>
<?php if ($err): ?>
<div class="alert alert-danger alert-dismissible fade show"><i class="fas fa-exclamation-triangle me-2"></i><?= htmlspecialchars($err) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>

<div class="container-fluid">

  <!-- Filter Tabs -->
  <div class="card border-0 shadow-sm mb-4">
    <div class="card-body py-2">
      <div class="d-flex gap-2">
        <?php foreach (['pending'=>'รอดำเนินการ','approved'=>'อนุมัติแล้ว','rejected'=>'ปฏิเสธแล้ว','all'=>'ทั้งหมด'] as $val => $label): ?>
        <a href="?status=<?= $val ?>" class="btn btn-sm <?= $filterStatus === $val ? 'btn-primary' : 'btn-outline-secondary' ?> fw-bold"><?= $label ?></a>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <div class="card border-0 shadow-sm">
    <div class="card-header bg-white border-0">
      <h6 class="fw-black mb-0">
        <i class="fas fa-times-circle me-2 text-danger"></i>
        คำขอยกเลิก (<?= count($requests) ?> รายการ)
      </h6>
    </div>
    <div class="card-body p-0">
      <?php if (empty($requests)): ?>
      <p class="text-center text-muted py-5"><i class="fas fa-check-circle text-success me-2"></i>ไม่มีรายการ</p>
      <?php else: ?>
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th class="text-xs fw-bold text-uppercase text-muted ps-4">นักเรียน</th>
              <th class="text-xs fw-bold text-uppercase text-muted">สายรถ</th>
              <th class="text-xs fw-bold text-uppercase text-muted">เหตุผล</th>
              <th class="text-xs fw-bold text-uppercase text-muted text-center">วันที่ยื่น</th>
              <th class="text-xs fw-bold text-uppercase text-muted text-center">สถานะ</th>
              <th class="text-xs fw-bold text-uppercase text-muted text-end pe-4">จัดการ</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($requests as $r):
              $statusClass = ['pending'=>'warning','approved'=>'success','rejected'=>'secondary'][$r['status']] ?? 'secondary';
              $statusLabel = ['pending'=>'รอดำเนินการ','approved'=>'อนุมัติแล้ว','rejected'=>'ปฏิเสธแล้ว'][$r['status']] ?? $r['status'];
            ?>
            <tr>
              <td class="ps-4">
                <div class="fw-bold"><?= htmlspecialchars($r['fullname']) ?></div>
                <div class="small text-muted"><?= htmlspecialchars($r['student_id']) ?> · <?= htmlspecialchars($r['classroom']) ?></div>
              </td>
              <td class="small"><?= htmlspecialchars($r['route_code']) ?> <?= htmlspecialchars($r['route_name']) ?><br><span class="text-muted"><?= number_format($r['price'], 0) ?> ฿</span></td>
              <td class="small text-muted" style="max-width:200px"><?= htmlspecialchars(mb_strimwidth($r['reason'], 0, 80, '...')) ?></td>
              <td class="text-center small"><?= date('d/m/Y', strtotime($r['created_at'])) ?></td>
              <td class="text-center">
                <span class="badge bg-<?= $statusClass ?> bg-opacity-10 text-<?= $statusClass ?> fw-bold"><?= $statusLabel ?></span>
                <?php if ($r['reviewer_name'] && $r['status'] !== 'pending'): ?>
                <div class="small text-muted mt-1"><?= htmlspecialchars($r['reviewer_name']) ?></div>
                <?php endif; ?>
              </td>
              <td class="text-end pe-4">
                <?php if ($r['status'] === 'pending'): ?>
                <button type="button" onclick="openAction(<?= (int)$r['id'] ?>, 'approve', '<?= htmlspecialchars(addslashes($r['fullname'])) ?>')"
                  class="btn btn-sm btn-success fw-bold me-1"><i class="fas fa-check"></i> อนุมัติ</button>
                <button type="button" onclick="openAction(<?= (int)$r['id'] ?>, 'reject', '<?= htmlspecialchars(addslashes($r['fullname'])) ?>')"
                  class="btn btn-sm btn-outline-danger fw-bold"><i class="fas fa-times"></i> ปฏิเสธ</button>
                <?php elseif ($r['admin_note']): ?>
                <span class="small text-muted fst-italic"><?= htmlspecialchars(mb_strimwidth($r['admin_note'], 0, 50, '...')) ?></span>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>
  </div>

</div>

<!-- Action Modal -->
<div class="modal fade" id="actionModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content border-0 shadow">
      <form method="POST">
        <?= csrf_field() ?>
        <input type="hidden" name="action" id="modalAction">
        <input type="hidden" name="cancel_id" id="modalCancelId">
        <div class="modal-header border-0">
          <h5 class="modal-title fw-black" id="modalTitle"></h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <p class="mb-3" id="modalDesc"></p>
          <div>
            <label class="form-label fw-bold small">หมายเหตุ (ไม่บังคับ)</label>
            <textarea name="note" class="form-control" rows="3" maxlength="500" placeholder="เหตุผลในการดำเนินการ..."></textarea>
          </div>
        </div>
        <div class="modal-footer border-0">
          <button type="button" class="btn btn-light fw-bold" data-bs-dismiss="modal">ยกเลิก</button>
          <button type="submit" class="btn fw-bold" id="modalSubmitBtn"></button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function openAction(id, action, name) {
    document.getElementById('modalAction').value = action;
    document.getElementById('modalCancelId').value = id;
    if (action === 'approve') {
        document.getElementById('modalTitle').textContent = 'อนุมัติการยกเลิก';
        document.getElementById('modalDesc').innerHTML = `อนุมัติคำขอยกเลิกของ <b>${name}</b>?<br><span class="text-muted small">นักเรียนจะถูกยกเลิกการลงทะเบียน</span>`;
        document.getElementById('modalSubmitBtn').className = 'btn btn-success fw-bold';
        document.getElementById('modalSubmitBtn').textContent = 'ยืนยันอนุมัติ';
    } else {
        document.getElementById('modalTitle').textContent = 'ปฏิเสธคำขอยกเลิก';
        document.getElementById('modalDesc').innerHTML = `ปฏิเสธคำขอยกเลิกของ <b>${name}</b>?<br><span class="text-muted small">นักเรียนจะยังคงสถานะลงทะเบียนอยู่</span>`;
        document.getElementById('modalSubmitBtn').className = 'btn btn-danger fw-bold';
        document.getElementById('modalSubmitBtn').textContent = 'ยืนยันปฏิเสธ';
    }
    new bootstrap.Modal(document.getElementById('actionModal')).show();
}
</script>

<?php require_once __DIR__ . '/../../components/layout_end.php'; ?>
