<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/layout.php';
requireRole(['admin','super_admin','director','budget_officer']);
$u  = getCurrentUser();
$db = getDB();

$isAdmin = in_array($u['role'], ['admin', 'super_admin', 'director']);

// ── Ensure table exists (graceful fallback before migration) ─
try {
    $db->query("SELECT 1 FROM budget_amendments LIMIT 1");
} catch (Exception $e) {
    flashMessage('warning', 'ยังไม่ได้รัน migration กรุณาเปิด _migrate.php?run=1 ก่อน');
    renderHead('ขอโอน/เพิ่มวงเงิน');
    echo '<div class="d-flex">'; renderSidebar(); echo '<div class="main-content flex-grow-1">'; renderTopbar('การขอโอน/เพิ่มวงเงินงบประมาณ'); echo '<div class="page-content">'; showFlash();
    echo '</div></div></div>'; renderFooter();
    exit;
}

// ── POST: submit new amendment request ──────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'request') {
    $type      = $_POST['type'] === 'transfer' ? 'transfer' : 'increase';
    $toId      = (int)($_POST['to_project_id']   ?? 0);
    $fromId    = $type === 'transfer' ? (int)($_POST['from_project_id'] ?? 0) : null;
    $amount    = (float)($_POST['amount'] ?? 0);
    $reason    = trim($_POST['reason'] ?? '');
    $linkedId  = (int)($_POST['linked_request_id'] ?? 0) ?: null;

    if ($toId <= 0 || $amount <= 0 || $reason === '') {
        flashMessage('danger', 'กรุณากรอกข้อมูลให้ครบถ้วน');
    } elseif ($type === 'transfer' && ($fromId <= 0 || $fromId === $toId)) {
        flashMessage('danger', 'กรุณาเลือกโครงการที่จะโอนออกให้ถูกต้อง');
    } else {
        try {
            $stmt = $db->prepare("
                INSERT INTO budget_amendments
                    (type, to_project_id, from_project_id, amount, reason, linked_request_id, requested_by)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$type, $toId, $fromId, $amount, $reason, $linkedId, $u['id']]);
            auditLog('amendment_request', 'budget_amendments', $db->lastInsertId(), null, compact('type','toId','amount'));
            flashMessage('success', 'ยื่นคำขอโอน/เพิ่มวงเงินแล้ว รอผู้บริหารอนุมัติ');
        } catch (Exception $e) {
            error_log($e->getMessage());
            flashMessage('danger', 'เกิดข้อผิดพลาด กรุณาลองใหม่');
        }
    }
    header('Location: budget_amendment.php'); exit;
}

// ── POST: admin approve / reject ────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($_POST['action'] ?? '', ['approve', 'reject']) && $isAdmin) {
    $amId   = (int)($_POST['amendment_id'] ?? 0);
    $action = $_POST['action'];
    $note   = trim($_POST['note'] ?? '');

    try {
        $amStmt = $db->prepare("SELECT * FROM budget_amendments WHERE id = ? AND status = 'pending'");
        $amStmt->execute([$amId]);
        $am = $amStmt->fetch();

        if (!$am) {
            flashMessage('danger', 'ไม่พบคำขอหรือดำเนินการไปแล้ว');
        } else {
            $db->beginTransaction();
            if ($action === 'approve') {
                if ($am['type'] === 'increase') {
                    $db->prepare("UPDATE budget_projects SET total_budget = total_budget + ? WHERE id = ?")
                       ->execute([$am['amount'], $am['to_project_id']]);
                    // Distribute to budget_subsidy as default top-up
                    $db->prepare("UPDATE budget_projects SET budget_subsidy = budget_subsidy + ? WHERE id = ?")
                       ->execute([$am['amount'], $am['to_project_id']]);
                } else {
                    // Transfer: deduct from source
                    $db->prepare("UPDATE budget_projects SET total_budget = total_budget - ?, budget_subsidy = budget_subsidy - ? WHERE id = ?")
                       ->execute([$am['amount'], $am['amount'], $am['from_project_id']]);
                    // Add to target
                    $db->prepare("UPDATE budget_projects SET total_budget = total_budget + ?, budget_subsidy = budget_subsidy + ? WHERE id = ?")
                       ->execute([$am['amount'], $am['amount'], $am['to_project_id']]);
                }
            }
            $db->prepare("UPDATE budget_amendments SET status = ?, reviewed_by = ?, review_note = ?, reviewed_at = NOW() WHERE id = ?")
               ->execute([$action === 'approve' ? 'approved' : 'rejected', $u['id'], $note, $amId]);
            $db->commit();
            auditLog('amendment_' . $action, 'budget_amendments', $amId);
            flashMessage('success', $action === 'approve' ? 'อนุมัติและปรับวงเงินแล้ว' : 'ปฏิเสธคำขอแล้ว');
        }
    } catch (Exception $e) {
        if ($db->inTransaction()) $db->rollBack();
        error_log($e->getMessage());
        flashMessage('danger', 'เกิดข้อผิดพลาด');
    }
    header('Location: budget_amendment.php'); exit;
}

// ── Data: pending + history ──────────────────────────────────
$tab = $_GET['tab'] ?? 'pending';

$aStmt = $db->prepare("
    SELECT ba.*,
           bp_to.project_name AS to_project_name,
           bp_fr.project_name AS from_project_name,
           CONCAT(u.firstname,' ',u.lastname) AS requested_by_name,
           CONCAT(rv.firstname,' ',rv.lastname) AS reviewed_by_name
    FROM budget_amendments ba
    JOIN budget_projects bp_to ON bp_to.id = ba.to_project_id
    LEFT JOIN budget_projects bp_fr ON bp_fr.id = ba.from_project_id
    JOIN llw_users u  ON u.user_id = ba.requested_by
    LEFT JOIN llw_users rv ON rv.user_id = ba.reviewed_by
    " . ($tab === 'pending' ? "WHERE ba.status = 'pending'" : ($tab === 'approved' ? "WHERE ba.status = 'approved'" : "WHERE ba.status = 'rejected'")) . "
    ORDER BY ba.created_at DESC
    LIMIT 100
");
$aStmt->execute();
$amendments = $aStmt->fetchAll();

$pendingCount = (int)$db->query("SELECT COUNT(*) FROM budget_amendments WHERE status='pending'")->fetchColumn();

// Projects for form
$projects = $db->prepare("
    SELECT bp.id, bp.project_name, d.name AS dept_name,
           (bp.budget_subsidy + bp.budget_quality + bp.budget_revenue + bp.budget_operation + bp.budget_reserve) AS total_alloc
    FROM budget_projects bp
    JOIN departments d ON d.id = bp.department_id
    WHERE bp.is_active = 1 AND bp.fiscal_year = ?
    ORDER BY d.order_no, bp.id
");
$projects->execute([FISCAL_YEAR]);
$projects = $projects->fetchAll();

renderHead('ขอโอน/เพิ่มวงเงิน');
echo '<div class="d-flex">'; renderSidebar(); echo '<div class="main-content flex-grow-1">'; renderTopbar('การขอโอน/เพิ่มวงเงินงบประมาณ'); echo '<div class="page-content">'; showFlash();
?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <ul class="nav nav-pills">
    <li class="nav-item"><a class="nav-link <?= $tab === 'pending'  ? 'active' : '' ?>" href="?tab=pending">
      รอพิจารณา <?= $pendingCount > 0 ? '<span class="badge bg-danger ms-1">'.$pendingCount.'</span>' : '' ?>
    </a></li>
    <li class="nav-item"><a class="nav-link <?= $tab === 'approved' ? 'active' : '' ?>" href="?tab=approved">อนุมัติแล้ว</a></li>
    <li class="nav-item"><a class="nav-link <?= $tab === 'rejected' ? 'active' : '' ?>" href="?tab=rejected">ปฏิเสธ</a></li>
  </ul>
  <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalRequest">
    <i class="bi bi-plus-circle me-1"></i>ยื่นคำขอใหม่
  </button>
</div>

<!-- Amendment list -->
<div class="card">
  <div class="card-header"><i class="bi bi-arrow-left-right me-2"></i>
    <?= $tab === 'pending' ? 'รอพิจารณา' : ($tab === 'approved' ? 'อนุมัติแล้ว' : 'ปฏิเสธแล้ว') ?>
    (<?= count($amendments) ?> รายการ)
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead><tr>
          <th class="ps-4">ประเภท</th>
          <th>โครงการที่ขอ</th>
          <th>โอนออกจาก</th>
          <th class="text-end">จำนวนเงิน</th>
          <th>เหตุผล</th>
          <th>ผู้ยื่น</th>
          <th>วันที่</th>
          <?php if ($isAdmin && $tab === 'pending'): ?><th class="text-center">ดำเนินการ</th><?php endif; ?>
          <?php if ($tab !== 'pending'): ?><th>หมายเหตุ</th><?php endif; ?>
        </tr></thead>
        <tbody>
        <?php foreach ($amendments as $a): ?>
        <tr>
          <td class="ps-4">
            <?php if ($a['type'] === 'increase'): ?>
            <span class="badge bg-primary">เพิ่มวงเงิน</span>
            <?php else: ?>
            <span class="badge bg-info text-dark">โอนงบ</span>
            <?php endif; ?>
          </td>
          <td><small><?= h($a['to_project_name']) ?></small></td>
          <td><small><?= $a['from_project_name'] ? h($a['from_project_name']) : '<span class="text-muted">-</span>' ?></small></td>
          <td class="text-end fw-semibold text-primary"><?= formatMoney($a['amount']) ?></td>
          <td style="max-width:200px;white-space:normal;font-size:12px"><?= h(mb_substr($a['reason'], 0, 80)) ?></td>
          <td style="font-size:12px"><?= h($a['requested_by_name']) ?></td>
          <td style="font-size:12px"><?= formatDate($a['created_at']) ?></td>
          <?php if ($isAdmin && $tab === 'pending'): ?>
          <td class="text-center">
            <button class="btn btn-success btn-sm me-1"
                    onclick="reviewAmendment(<?= $a['id'] ?>,'approve')"
                    title="อนุมัติ"><i class="bi bi-check-lg"></i></button>
            <button class="btn btn-danger btn-sm"
                    onclick="reviewAmendment(<?= $a['id'] ?>,'reject')"
                    title="ปฏิเสธ"><i class="bi bi-x-lg"></i></button>
          </td>
          <?php endif; ?>
          <?php if ($tab !== 'pending'): ?>
          <td style="font-size:12px;color:#64748b"><?= h($a['review_note'] ?? '-') ?></td>
          <?php endif; ?>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($amendments)): ?>
        <tr><td colspan="9" class="text-center py-4 text-muted">ไม่มีรายการ</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Modal: Request Amendment -->
<div class="modal fade" id="modalRequest" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <form method="post">
      <input type="hidden" name="action" value="request">
      <div class="modal-content">
        <div class="modal-header bg-primary text-white">
          <h5 class="modal-title"><i class="bi bi-plus-circle me-2"></i>ยื่นคำขอโอน/เพิ่มวงเงิน</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label fw-semibold">ประเภทคำขอ</label>
            <div class="d-flex gap-3">
              <div class="form-check">
                <input class="form-check-input" type="radio" name="type" value="increase" id="typeIncrease" checked onchange="toggleTransfer()">
                <label class="form-check-label" for="typeIncrease">
                  <strong>ขอเพิ่มวงเงิน</strong> — ขอวงเงินเพิ่มจากงบสำรอง/ผู้บริหาร
                </label>
              </div>
              <div class="form-check">
                <input class="form-check-input" type="radio" name="type" value="transfer" id="typeTransfer" onchange="toggleTransfer()">
                <label class="form-check-label" for="typeTransfer">
                  <strong>โอนงบ</strong> — โอนจากโครงการที่มีเหลือมาให้โครงการนี้
                </label>
              </div>
            </div>
          </div>

          <div class="row g-3">
            <div class="col-md-6" id="fromProjectWrap" style="display:none">
              <label class="form-label fw-semibold">โอนออกจากโครงการ <span class="text-danger">*</span></label>
              <select class="form-select" name="from_project_id">
                <option value="">-- เลือกโครงการต้นทาง --</option>
                <?php foreach ($projects as $p): ?>
                <option value="<?= $p['id'] ?>"><?= h($p['project_name']) ?> (<?= h($p['dept_name']) ?>)</option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">โครงการที่ขอรับงบ <span class="text-danger">*</span></label>
              <select class="form-select" name="to_project_id" required>
                <option value="">-- เลือกโครงการ --</option>
                <?php foreach ($projects as $p): ?>
                <option value="<?= $p['id'] ?>"><?= h($p['project_name']) ?> (<?= h($p['dept_name']) ?>)</option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label fw-semibold">จำนวนเงินที่ขอ (บาท) <span class="text-danger">*</span></label>
              <input type="number" class="form-control" name="amount" min="1" step="0.01" placeholder="0.00" required>
            </div>
            <div class="col-12">
              <label class="form-label fw-semibold">เหตุผลความจำเป็น <span class="text-danger">*</span></label>
              <textarea class="form-control" name="reason" rows="3"
                        placeholder="อธิบายเหตุผลที่ต้องการเพิ่ม/โอนวงเงิน เช่น ราคากลางสูงกว่าที่ประมาณ, มีกิจกรรมเพิ่มเติม..." required></textarea>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">ยกเลิก</button>
          <button type="submit" class="btn btn-primary"><i class="bi bi-send me-1"></i>ยื่นคำขอ</button>
        </div>
      </div>
    </form>
  </div>
</div>

<!-- Modal: Review (approve/reject) -->
<div class="modal fade" id="modalReview" tabindex="-1">
  <div class="modal-dialog">
    <form method="post" id="formReview">
      <input type="hidden" name="action" id="reviewAction" value="approve">
      <input type="hidden" name="amendment_id" id="reviewAmId" value="">
      <div class="modal-content">
        <div class="modal-header" id="reviewHeader">
          <h5 class="modal-title" id="reviewTitle">อนุมัติคำขอ</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">หมายเหตุ (ถ้ามี)</label>
            <textarea class="form-control" name="note" rows="3" placeholder="ระบุหมายเหตุประกอบการพิจารณา..."></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">ยกเลิก</button>
          <button type="submit" class="btn" id="reviewBtn">ยืนยัน</button>
        </div>
      </div>
    </form>
  </div>
</div>

<script>
function toggleTransfer() {
    const isTransfer = document.getElementById('typeTransfer').checked;
    document.getElementById('fromProjectWrap').style.display = isTransfer ? '' : 'none';
}
function reviewAmendment(id, action) {
    document.getElementById('reviewAmId').value = id;
    document.getElementById('reviewAction').value = action;
    if (action === 'approve') {
        document.getElementById('reviewHeader').className = 'modal-header bg-success text-white';
        document.getElementById('reviewTitle').textContent = 'ยืนยันการอนุมัติ';
        document.getElementById('reviewBtn').className = 'btn btn-success';
        document.getElementById('reviewBtn').textContent = 'อนุมัติ';
    } else {
        document.getElementById('reviewHeader').className = 'modal-header bg-danger text-white';
        document.getElementById('reviewTitle').textContent = 'ยืนยันการปฏิเสธ';
        document.getElementById('reviewBtn').className = 'btn btn-danger';
        document.getElementById('reviewBtn').textContent = 'ปฏิเสธ';
    }
    new bootstrap.Modal(document.getElementById('modalReview')).show();
}
</script>
<?php echo '</div></div></div>'; renderFooter(); ?>
