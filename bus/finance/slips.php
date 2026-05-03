<?php
session_start();
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../config.php';
busRequireStaff(['bus_admin', 'bus_finance', 'super_admin', 'wfh_admin']);

$pdo      = getPdo();
$semester = busGetSemester();
$semLabel = busSemesterLabel($semester);
$staffId  = (int)($_SESSION['user_id'] ?? 0);
$msg = '';
$err = '';

$filterStatus = trim($_GET['status'] ?? 'pending');
$filterClass  = trim($_GET['classroom'] ?? '');

// ── POST ──────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'approve_slip') {
        $slipId = (int)($_POST['slip_id'] ?? 0);
        $note   = trim($_POST['note'] ?? '');
        if ($slipId > 0) {
            try {
                $pdo->beginTransaction();
                $slip = $pdo->prepare("
                    SELECT sp.*, reg.status AS reg_status, rt.price,
                           COALESCE((SELECT SUM(amount) FROM bus_payments WHERE registration_id = sp.registration_id), 0) AS already_paid
                    FROM bus_payment_slips sp
                    JOIN bus_registrations reg ON reg.id = sp.registration_id
                    JOIN bus_routes rt ON rt.id = reg.route_id
                    WHERE sp.id = ? AND sp.status = 'pending'
                ");
                $slip->execute([$slipId]);
                $slip = $slip->fetch();
                if ($slip && in_array($slip['reg_status'], ['active', 'pending_cancel'])) {
                    $balance = (float)$slip['price'] - (float)$slip['already_paid'];
                    $amount  = min((float)$slip['amount'], $balance);
                    if ($amount > 0) {
                        $pdo->prepare("INSERT INTO bus_payments (registration_id, amount, note, recorded_by) VALUES (?,?,?,?)")
                            ->execute([$slip['registration_id'], $amount, 'อนุมัติจากสลิป #' . $slipId . ($note ? ' — ' . $note : ''), $staffId ?: null]);
                        $pdo->prepare("UPDATE bus_payment_slips SET status='approved', admin_note=?, reviewed_by=?, reviewed_at=NOW() WHERE id=?")
                            ->execute([$note ?: null, $staffId ?: null, $slipId]);
                        $pdo->commit();
                        $msg = 'อนุมัติสลิปและบันทึกชำระ ' . number_format($amount, 0) . ' บาท เรียบร้อยแล้ว';
                    } else { $pdo->rollBack(); $err = 'ชำระครบแล้ว ไม่ต้องอนุมัติ'; }
                } else { $pdo->rollBack(); $err = 'ไม่พบสลิปหรือถูกตรวจสอบแล้ว'; }
            } catch (Exception $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                error_log($e->getMessage()); $err = 'เกิดข้อผิดพลาด';
            }
        }
    } elseif ($action === 'reject_slip') {
        $slipId    = (int)($_POST['slip_id'] ?? 0);
        $adminNote = trim($_POST['admin_note'] ?? '');
        if ($slipId > 0) {
            try {
                $upd = $pdo->prepare("UPDATE bus_payment_slips SET status='rejected', admin_note=?, reviewed_by=?, reviewed_at=NOW() WHERE id=? AND status='pending'");
                $upd->execute([$adminNote ?: null, $staffId ?: null, $slipId]);
                $msg = $upd->rowCount() > 0 ? 'ปฏิเสธสลิปเรียบร้อยแล้ว' : 'ไม่พบสลิป';
            } catch (Exception $e) { error_log($e->getMessage()); $err = 'เกิดข้อผิดพลาด'; }
        }
    }
}

// ── Counts for tabs ───────────────────────────────────────────────────────────
try {
    $cntStmt = $pdo->prepare("
        SELECT sp.status, COUNT(*) AS cnt
        FROM bus_payment_slips sp
        JOIN bus_registrations reg ON reg.id = sp.registration_id AND reg.semester = ?
        GROUP BY sp.status
    ");
    $cntStmt->execute([$semester]);
    $counts = ['pending' => 0, 'approved' => 0, 'rejected' => 0];
    foreach ($cntStmt->fetchAll() as $c) $counts[$c['status']] = (int)$c['cnt'];
} catch (Exception $e) { $counts = ['pending' => 0, 'approved' => 0, 'rejected' => 0]; }

// ── Load classrooms ───────────────────────────────────────────────────────────
try {
    $clsStmt = $pdo->prepare("
        SELECT DISTINCT s.classroom FROM bus_students s
        JOIN bus_registrations r ON r.student_id = s.id AND r.semester = ?
        WHERE s.is_active = 1 AND s.classroom != '' ORDER BY s.classroom
    ");
    $clsStmt->execute([$semester]);
    $classrooms = $clsStmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) { $classrooms = []; }

// ── Load slips ────────────────────────────────────────────────────────────────
$sql = "
    SELECT sp.id, sp.amount, sp.slip_image, sp.note, sp.status, sp.admin_note,
           sp.created_at, sp.reviewed_at,
           s.fullname AS student_name, s.student_id AS student_sid, s.classroom,
           rt.route_code, rt.route_name, rt.price AS route_price,
           COALESCE((SELECT SUM(p.amount) FROM bus_payments p WHERE p.registration_id = sp.registration_id), 0) AS already_paid,
           u.firstname AS reviewed_by_name
    FROM bus_payment_slips sp
    JOIN bus_registrations reg ON reg.id = sp.registration_id AND reg.semester = ?
    JOIN bus_students s ON s.id = reg.student_id
    JOIN bus_routes rt ON rt.id = reg.route_id
    LEFT JOIN llw_users u ON u.user_id = sp.reviewed_by
    WHERE sp.status = ?
";
$params = [$semester, $filterStatus ?: 'pending'];
if ($filterClass !== '') { $sql .= " AND s.classroom = ?"; $params[] = $filterClass; }
$sql .= " ORDER BY sp.created_at DESC";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $slips = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { error_log($e->getMessage()); $slips = []; }

$pageTitle    = 'ตรวจสอบสลิปการโอนเงิน';
$pageSubtitle = $semLabel;
$activeSystem = 'bus';
require_once __DIR__ . '/../../components/layout_start.php';
?>

<?php if ($msg): ?>
<div class="alert alert-success alert-dismissible fade show border-0 shadow-sm">
    <i class="bi bi-check-circle-fill me-2"></i><?= htmlspecialchars($msg) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>
<?php if ($err): ?>
<div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm">
    <i class="bi bi-exclamation-triangle-fill me-2"></i><?= htmlspecialchars($err) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- Status Tabs -->
<div class="d-flex gap-2 mb-4 flex-wrap">
    <?php
    $tabs = [
        'pending'  => ['label' => 'รอตรวจสอบ',     'color' => 'warning', 'icon' => 'bi-hourglass-split'],
        'approved' => ['label' => 'อนุมัติแล้ว',    'color' => 'success', 'icon' => 'bi-check-circle-fill'],
        'rejected' => ['label' => 'ปฏิเสธแล้ว',    'color' => 'danger',  'icon' => 'bi-x-circle-fill'],
    ];
    foreach ($tabs as $key => $tab):
        $active = ($filterStatus === $key || (!$filterStatus && $key === 'pending'));
    ?>
    <a href="?status=<?= $key ?><?= $filterClass ? '&classroom=' . urlencode($filterClass) : '' ?>"
       class="btn <?= $active ? 'btn-' . $tab['color'] : 'btn-outline-' . $tab['color'] ?> fw-bold">
        <i class="bi <?= $tab['icon'] ?> me-1"></i><?= $tab['label'] ?>
        <span class="badge <?= $active ? 'bg-white text-' . $tab['color'] : 'bg-' . $tab['color'] . ' text-white' ?> ms-1">
            <?= $counts[$key] ?>
        </span>
    </a>
    <?php endforeach; ?>

    <div class="ms-auto">
        <select class="form-select form-select-sm" onchange="location.href='?status=<?= urlencode($filterStatus) ?>&classroom='+this.value" style="min-width:130px">
            <option value="">ทุกห้อง</option>
            <?php foreach ($classrooms as $cls): ?>
            <option value="<?= htmlspecialchars($cls) ?>" <?= $filterClass === $cls ? 'selected' : '' ?>><?= htmlspecialchars($cls) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
</div>

<!-- Slip Cards -->
<?php if (empty($slips)): ?>
<div class="card border-0 shadow-sm">
    <div class="card-body text-center py-6 text-muted">
        <i class="bi bi-image fs-1 d-block mb-3 opacity-25"></i>
        <p class="fw-bold mb-0">ไม่มีสลิป<?= $filterStatus === 'pending' ? 'รอตรวจสอบ' : ($filterStatus === 'approved' ? 'ที่อนุมัติแล้ว' : 'ที่ปฏิเสธ') ?></p>
    </div>
</div>
<?php else: ?>

<!-- Summary bar -->
<div class="d-flex align-items-center gap-3 mb-3 px-1">
    <span class="text-muted small fw-bold"><?= count($slips) ?> รายการ</span>
    <?php if ($filterStatus === 'pending'): ?>
    <span class="text-muted small">· ยอดรวม <?= number_format(array_sum(array_column($slips, 'amount')), 0) ?> บาท</span>
    <?php endif; ?>
</div>

<div class="row g-3" id="slipGrid">
<?php foreach ($slips as $sp):
    $balance    = max(0, (float)$sp['route_price'] - (float)$sp['already_paid']);
    $isPending  = $sp['status'] === 'pending';
    $isApproved = $sp['status'] === 'approved';
?>
<div class="col-12 col-md-6 col-xl-4" id="card-<?= (int)$sp['id'] ?>">
    <div class="card border-0 shadow-sm h-100 <?= $isPending ? 'border-start border-warning border-4' : ($isApproved ? 'border-start border-success border-4' : 'border-start border-danger border-4') ?>">
        <div class="card-body">

            <!-- Header: student info + status badge -->
            <div class="d-flex align-items-start justify-content-between gap-2 mb-3">
                <div>
                    <div class="fw-black"><?= htmlspecialchars($sp['student_name']) ?></div>
                    <div class="text-muted small"><?= htmlspecialchars($sp['student_sid']) ?> · <?= htmlspecialchars($sp['classroom']) ?></div>
                    <div class="text-muted small"><i class="bi bi-bus-front me-1"></i>สาย <?= htmlspecialchars($sp['route_code']) ?> <?= htmlspecialchars($sp['route_name']) ?></div>
                </div>
                <?php if ($isPending): ?>
                <span class="badge bg-warning text-dark flex-shrink-0">รอตรวจ</span>
                <?php elseif ($isApproved): ?>
                <span class="badge bg-success flex-shrink-0">อนุมัติแล้ว</span>
                <?php else: ?>
                <span class="badge bg-danger flex-shrink-0">ปฏิเสธ</span>
                <?php endif; ?>
            </div>

            <!-- Amount info -->
            <div class="row g-2 mb-3">
                <div class="col-4 text-center">
                    <div class="text-muted" style="font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em">ยอดสลิป</div>
                    <div class="fw-black text-primary fs-5"><?= number_format($sp['amount'], 0) ?></div>
                    <div class="text-muted" style="font-size:.7rem">บาท</div>
                </div>
                <div class="col-4 text-center border-start border-end">
                    <div class="text-muted" style="font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em">ชำระแล้ว</div>
                    <div class="fw-black text-success fs-5"><?= number_format($sp['already_paid'], 0) ?></div>
                    <div class="text-muted" style="font-size:.7rem">บาท</div>
                </div>
                <div class="col-4 text-center">
                    <div class="text-muted" style="font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em">ค้างชำระ</div>
                    <div class="fw-black <?= $balance > 0.01 ? 'text-danger' : 'text-success' ?> fs-5"><?= number_format($balance, 0) ?></div>
                    <div class="text-muted" style="font-size:.7rem">บาท</div>
                </div>
            </div>

            <!-- Slip image -->
            <a href="/bus/uploads/slips/<?= htmlspecialchars($sp['slip_image']) ?>" target="_blank"
               class="d-block rounded-3 overflow-hidden mb-3 border"
               style="height:180px;background:#f8fafc">
                <img src="/bus/uploads/slips/<?= htmlspecialchars($sp['slip_image']) ?>"
                     alt="สลิป"
                     style="width:100%;height:100%;object-fit:cover"
                     onerror="this.parentElement.innerHTML='<div class=\'d-flex align-items-center justify-content-center h-100 text-muted\'><i class=\'bi bi-image-fill fs-1 opacity-25\'></i></div>'">
            </a>

            <!-- Note from student -->
            <?php if ($sp['note']): ?>
            <div class="bg-light rounded-2 px-3 py-2 mb-3 small text-muted">
                <i class="bi bi-chat-left-text me-1"></i><?= htmlspecialchars($sp['note']) ?>
            </div>
            <?php endif; ?>

            <!-- Admin note (approved/rejected) -->
            <?php if (!$isPending && $sp['admin_note']): ?>
            <div class="bg-<?= $isApproved ? 'success' : 'danger' ?>-subtle rounded-2 px-3 py-2 mb-3 small">
                <i class="bi bi-person-check me-1"></i><strong><?= $isApproved ? 'หมายเหตุ:' : 'เหตุผลปฏิเสธ:' ?></strong> <?= htmlspecialchars($sp['admin_note']) ?>
            </div>
            <?php endif; ?>

            <!-- Meta -->
            <div class="d-flex justify-content-between text-muted" style="font-size:.75rem">
                <span><i class="bi bi-clock me-1"></i>ส่ง <?= date('d/m/Y H:i', strtotime($sp['created_at'])) ?></span>
                <?php if (!$isPending && $sp['reviewed_at']): ?>
                <span><i class="bi bi-person me-1"></i><?= htmlspecialchars($sp['reviewed_by_name'] ?? '-') ?> · <?= date('d/m/Y H:i', strtotime($sp['reviewed_at'])) ?></span>
                <?php endif; ?>
            </div>

            <!-- Actions -->
            <?php if ($isPending): ?>
            <div class="d-flex gap-2 mt-3 pt-3 border-top">
                <button type="button" class="btn btn-success fw-bold flex-fill"
                    onclick="openApprove(<?= (int)$sp['id'] ?>,'<?= htmlspecialchars(addslashes($sp['student_name'])) ?>',<?= number_format($sp['amount'], 2, '.', '') ?>)">
                    <i class="bi bi-check-lg me-1"></i>อนุมัติ
                </button>
                <button type="button" class="btn btn-outline-danger fw-bold flex-fill"
                    onclick="openReject(<?= (int)$sp['id'] ?>,'<?= htmlspecialchars(addslashes($sp['student_name'])) ?>')">
                    <i class="bi bi-x-lg me-1"></i>ปฏิเสธ
                </button>
            </div>
            <?php endif; ?>

        </div>
    </div>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>

<!-- ── Approve Modal ──────────────────────────────────────────────────────── -->
<div class="modal fade" id="approveModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-success text-white border-0">
                <h6 class="modal-title fw-bold"><i class="bi bi-check-circle-fill me-2"></i>อนุมัติสลิป</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="approve_slip">
                <input type="hidden" name="slip_id" id="approveSlipId">
                <div class="modal-body">
                    <div class="alert alert-light border rounded-3 mb-3">
                        <div class="fw-bold" id="approveStudentName"></div>
                        <div class="small text-muted">จำนวน <strong id="approveAmount" class="text-success"></strong> บาท</div>
                    </div>
                    <label class="form-label fw-bold small">หมายเหตุ (ไม่บังคับ)</label>
                    <input type="text" name="note" class="form-control" placeholder="เช่น ตรวจสอบแล้ว ถูกต้อง" maxlength="255">
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn btn-success fw-bold px-4"><i class="bi bi-check-lg me-1"></i>ยืนยันอนุมัติ</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ── Reject Modal ───────────────────────────────────────────────────────── -->
<div class="modal fade" id="rejectModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-danger text-white border-0">
                <h6 class="modal-title fw-bold"><i class="bi bi-x-circle-fill me-2"></i>ปฏิเสธสลิป</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="reject_slip">
                <input type="hidden" name="slip_id" id="rejectSlipId">
                <div class="modal-body">
                    <p class="small mb-3">ปฏิเสธสลิปของ <strong id="rejectStudentName"></strong>?</p>
                    <label class="form-label fw-bold small">เหตุผล <span class="text-danger">*</span></label>
                    <textarea name="admin_note" class="form-control" rows="3" placeholder="เช่น สลิปไม่ชัดเจน, ยอดเงินไม่ตรง, บัญชีไม่ถูกต้อง" required maxlength="500"></textarea>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn btn-danger fw-bold px-4"><i class="bi bi-x-lg me-1"></i>ยืนยันปฏิเสธ</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openApprove(id, name, amount) {
    document.getElementById('approveSlipId').value = id;
    document.getElementById('approveStudentName').textContent = name;
    document.getElementById('approveAmount').textContent = Number(amount).toLocaleString('th-TH', {maximumFractionDigits:0});
    new bootstrap.Modal(document.getElementById('approveModal')).show();
}
function openReject(id, name) {
    document.getElementById('rejectSlipId').value = id;
    document.getElementById('rejectStudentName').textContent = name;
    new bootstrap.Modal(document.getElementById('rejectModal')).show();
}
</script>

<?php require_once __DIR__ . '/../../components/layout_end.php'; ?>
