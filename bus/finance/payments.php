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

$filterClass  = trim($_GET['classroom'] ?? '');
$filterStatus = trim($_GET['status'] ?? '');

// ── POST: record payment ──────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'record_payment') {
        $regId  = (int)($_POST['reg_id'] ?? 0);
        $amount = (float)($_POST['amount'] ?? 0);
        $note   = trim($_POST['note'] ?? '');

        if ($regId <= 0 || $amount <= 0) {
            $err = 'กรุณาระบุจำนวนเงินที่ถูกต้อง';
        } else {
            try {
                $rStmt = $pdo->prepare("SELECT reg.id, reg.status, rt.price FROM bus_registrations reg JOIN bus_routes rt ON rt.id = reg.route_id WHERE reg.id = ? AND reg.semester = ?");
                $rStmt->execute([$regId, $semester]);
                $regRow = $rStmt->fetch();

                if (!$regRow || !in_array($regRow['status'], ['active', 'pending_cancel'])) {
                    $err = 'ไม่พบรายการลงทะเบียน';
                } else {
                    $paidStmt = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM bus_payments WHERE registration_id = ?");
                    $paidStmt->execute([$regId]);
                    $alreadyPaid = (float)$paidStmt->fetchColumn();
                    if ($alreadyPaid + $amount > (float)$regRow['price'] + 0.01) {
                        $err = 'จำนวนเงินเกินกว่ายอดที่ต้องชำระ';
                    } else {
                        $pdo->prepare("INSERT INTO bus_payments (registration_id, amount, note, recorded_by) VALUES (?,?,?,?)")
                            ->execute([$regId, $amount, $note, $staffId ?: null]);
                        $msg = 'บันทึกการชำระเงิน ' . number_format($amount, 0) . ' บาท เรียบร้อยแล้ว';
                    }
                }
            } catch (Exception $e) {
                error_log($e->getMessage());
                $err = 'เกิดข้อผิดพลาด กรุณาลองใหม่';
            }
        }
    } elseif ($action === 'approve_slip') {
        $slipId = (int)($_POST['slip_id'] ?? 0);
        $note   = trim($_POST['note'] ?? '');
        if ($slipId > 0) {
            try {
                $pdo->beginTransaction();
                $slipStmt = $pdo->prepare("
                    SELECT sp.*, reg.status AS reg_status, rt.price,
                           COALESCE((SELECT SUM(amount) FROM bus_payments WHERE registration_id = sp.registration_id), 0) AS already_paid
                    FROM bus_payment_slips sp
                    JOIN bus_registrations reg ON reg.id = sp.registration_id
                    JOIN bus_routes rt ON rt.id = reg.route_id
                    WHERE sp.id = ? AND sp.status = 'pending'
                ");
                $slipStmt->execute([$slipId]);
                $slip = $slipStmt->fetch();
                if ($slip && in_array($slip['reg_status'], ['active', 'pending_cancel'])) {
                    $balance = (float)$slip['price'] - (float)$slip['already_paid'];
                    $amount  = min((float)$slip['amount'], $balance);
                    if ($amount > 0) {
                        $pdo->prepare("INSERT INTO bus_payments (registration_id, amount, note, recorded_by) VALUES (?,?,?,?)")
                            ->execute([$slip['registration_id'], $amount, 'อนุมัติจากสลิป #' . $slipId . ($note ? ' — ' . $note : ''), $staffId ?: null]);
                        $pdo->prepare("UPDATE bus_payment_slips SET status='approved', admin_note=?, reviewed_by=?, reviewed_at=NOW() WHERE id=?")
                            ->execute([$note ?: null, $staffId ?: null, $slipId]);
                        $pdo->commit();
                        $msg = 'อนุมัติสลิปและบันทึกชำระ ' . number_format($amount, 0) . ' บาท เรียบร้อย';
                    } else { $pdo->rollBack(); $err = 'ชำระครบแล้ว'; }
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
                $msg = $upd->rowCount() > 0 ? 'ปฏิเสธสลิปเรียบร้อย' : 'ไม่พบสลิป';
            } catch (Exception $e) { error_log($e->getMessage()); $err = 'เกิดข้อผิดพลาด'; }
        }
    }
}

// ── Load classrooms ────────────────────────────────────────────────────────────
try {
    $clsStmt = $pdo->prepare("SELECT DISTINCT s.classroom FROM bus_students s JOIN bus_registrations r ON r.student_id = s.id AND r.semester = ? WHERE s.is_active=1 AND s.classroom != '' ORDER BY s.classroom");
    $clsStmt->execute([$semester]);
    $classrooms = $clsStmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) { $classrooms = []; }

// ── Load registrations ─────────────────────────────────────────────────────────
$sql = "
    SELECT reg.id AS reg_id, reg.status AS reg_status,
           s.student_id, s.fullname, s.classroom,
           rt.route_code, rt.route_name, rt.price,
           COALESCE(SUM(p.amount), 0) AS paid_amount,
           (SELECT COUNT(*) FROM bus_payment_slips sp WHERE sp.registration_id = reg.id AND sp.status = 'pending') AS pending_slips
    FROM bus_registrations reg
    JOIN bus_students s ON s.id = reg.student_id
    JOIN bus_routes rt ON rt.id = reg.route_id
    LEFT JOIN bus_payments p ON p.registration_id = reg.id
    WHERE reg.semester = ?
";
$params = [$semester];
if ($filterClass !== '') { $sql .= " AND s.classroom = ?"; $params[] = $filterClass; }
$sql .= " GROUP BY reg.id ORDER BY s.classroom, s.fullname";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $registrations = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { error_log($e->getMessage()); $registrations = []; }

foreach ($registrations as &$r) {
    $r['balance'] = max(0, (float)$r['price'] - (float)$r['paid_amount']);
    if ($r['balance'] <= 0.01)           $r['pay_status'] = 'paid';
    elseif ((float)$r['paid_amount'] > 0.01) $r['pay_status'] = 'partial';
    else                                     $r['pay_status'] = 'unpaid';
}
unset($r);

if (in_array($filterStatus, ['paid', 'unpaid', 'partial'])) {
    $registrations = array_values(array_filter($registrations, fn($r) => $r['pay_status'] === $filterStatus));
}

// ── KPI ───────────────────────────────────────────────────────────────────────
$allReg        = $registrations;
$paidCount     = count(array_filter($allReg, fn($r) => $r['pay_status'] === 'paid'));
$unpaidCount   = count(array_filter($allReg, fn($r) => $r['pay_status'] === 'unpaid'));
$partialCount  = count(array_filter($allReg, fn($r) => $r['pay_status'] === 'partial'));
$totalCollected = array_sum(array_column($allReg, 'paid_amount'));
$totalBalance   = array_sum(array_column($allReg, 'balance'));
$totalAmount    = array_sum(array_column($allReg, 'price'));

// ── Pending slips ─────────────────────────────────────────────────────────────
try {
    $spStmt = $pdo->prepare("
        SELECT sp.*, s.fullname AS student_name, s.classroom, s.student_id AS student_sid,
               rt.route_name, rt.price AS route_price,
               COALESCE((SELECT SUM(p.amount) FROM bus_payments p WHERE p.registration_id = sp.registration_id), 0) AS already_paid
        FROM bus_payment_slips sp
        JOIN bus_registrations reg ON reg.id = sp.registration_id AND reg.semester = ?
        JOIN bus_students s ON s.id = reg.student_id
        JOIN bus_routes rt ON rt.id = reg.route_id
        WHERE sp.status = 'pending'
        ORDER BY sp.created_at DESC
    ");
    $spStmt->execute([$semester]);
    $pendingSlips = $spStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $pendingSlips = []; }

// ── Export CSV ────────────────────────────────────────────────────────────────
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="payments_' . $semester . '_' . date('Ymd') . '.csv"');
    echo "\xEF\xBB\xBF";
    $out = fopen('php://output', 'w');
    fputcsv($out, ['สาย', 'รหัสนักเรียน', 'ชื่อ-นามสกุล', 'ชั้น', 'สาย/จุดขึ้น', 'ยอดรวม', 'ชำระแล้ว', 'ค้างชำระ', 'สถานะ']);
    foreach ($registrations as $r) {
        $lbl = ['paid' => 'ชำระครบ', 'partial' => 'ชำระบางส่วน', 'unpaid' => 'ยังไม่ชำระ'][$r['pay_status']];
        fputcsv($out, [$r['route_code'], $r['student_id'], $r['fullname'], $r['classroom'], $r['route_name'],
                       number_format($r['price'], 2), number_format($r['paid_amount'], 2), number_format($r['balance'], 2), $lbl]);
    }
    fclose($out);
    exit;
}

$pageTitle    = 'บันทึกการชำระเงิน';
$pageSubtitle = $semLabel;
$activeSystem = 'bus';
require_once __DIR__ . '/../../components/layout_start.php';
?>

<?php if ($msg): ?>
<div class="alert alert-success alert-dismissible fade show shadow-sm border-0">
    <i class="bi bi-check-circle-fill me-2"></i><?= htmlspecialchars($msg) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>
<?php if ($err): ?>
<div class="alert alert-danger alert-dismissible fade show shadow-sm border-0">
    <i class="bi bi-exclamation-triangle-fill me-2"></i><?= htmlspecialchars($err) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- KPI Cards -->
<div class="row g-3 mb-4">
    <div class="col-6 col-xl-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="rounded-3 p-3 bg-primary bg-opacity-10 flex-shrink-0">
                    <i class="fas fa-users text-primary fs-4"></i>
                </div>
                <div>
                    <div class="text-muted small fw-bold">ทั้งหมด</div>
                    <div class="fs-4 fw-black text-dark"><?= count($allReg) ?> คน</div>
                    <div class="small text-muted"><?= number_format($totalAmount, 0) ?> ฿</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="rounded-3 p-3 bg-success bg-opacity-10 flex-shrink-0">
                    <i class="fas fa-check-circle text-success fs-4"></i>
                </div>
                <div>
                    <div class="text-muted small fw-bold">ชำระครบ</div>
                    <div class="fs-4 fw-black text-success"><?= $paidCount ?> คน</div>
                    <div class="small text-success"><?= number_format($totalCollected, 0) ?> ฿ รับแล้ว</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="rounded-3 p-3 bg-warning bg-opacity-10 flex-shrink-0">
                    <i class="fas fa-clock text-warning fs-4"></i>
                </div>
                <div>
                    <div class="text-muted small fw-bold">ชำระบางส่วน</div>
                    <div class="fs-4 fw-black text-warning"><?= $partialCount ?> คน</div>
                    <div class="small text-warning">ค้าง <?= number_format($totalBalance, 0) ?> ฿</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="rounded-3 p-3 bg-danger bg-opacity-10 flex-shrink-0">
                    <i class="fas fa-times-circle text-danger fs-4"></i>
                </div>
                <div>
                    <div class="text-muted small fw-bold">ยังไม่ชำระ</div>
                    <div class="fs-4 fw-black text-danger"><?= $unpaidCount ?> คน</div>
                    <div class="small text-danger">ค้าง <?= number_format(array_sum(array_column(array_filter($allReg, fn($r) => $r['pay_status'] === 'unpaid'), 'balance')), 0) ?> ฿</div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filter Bar -->
<div class="card border-0 shadow-sm mb-3">
    <div class="card-body py-3">
        <form method="GET" class="row g-2 align-items-center">
            <div class="col-auto">
                <select name="classroom" class="form-select form-select-sm" onchange="this.form.submit()" style="min-width:130px">
                    <option value="">ทุกห้อง</option>
                    <?php foreach ($classrooms as $cls): ?>
                    <option value="<?= htmlspecialchars($cls) ?>" <?= $filterClass === $cls ? 'selected' : '' ?>><?= htmlspecialchars($cls) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-auto">
                <select name="status" class="form-select form-select-sm" onchange="this.form.submit()" style="min-width:150px">
                    <option value="">ทุกสถานะ</option>
                    <option value="unpaid"  <?= $filterStatus === 'unpaid'  ? 'selected' : '' ?>>ยังไม่ชำระ</option>
                    <option value="partial" <?= $filterStatus === 'partial' ? 'selected' : '' ?>>ชำระบางส่วน</option>
                    <option value="paid"    <?= $filterStatus === 'paid'    ? 'selected' : '' ?>>ชำระครบแล้ว</option>
                </select>
            </div>
            <?php if ($filterClass || $filterStatus): ?>
            <div class="col-auto">
                <a href="/bus/finance/payments.php" class="btn btn-sm btn-light"><i class="bi bi-x me-1"></i>ล้าง</a>
            </div>
            <?php endif; ?>
            <div class="col"></div>
            <!-- Pending slips badge -->
            <?php if (!empty($pendingSlips)): ?>
            <div class="col-auto">
                <button type="button" class="btn btn-sm btn-warning fw-bold" onclick="document.getElementById('slipSection').scrollIntoView({behavior:'smooth'})">
                    <i class="bi bi-image-fill me-1"></i>สลิปรอตรวจ <span class="badge bg-dark ms-1"><?= count($pendingSlips) ?></span>
                </button>
            </div>
            <?php endif; ?>
            <div class="col-auto">
                <a href="?<?= http_build_query(array_filter(['classroom' => $filterClass, 'status' => $filterStatus, 'export' => 'csv'])) ?>"
                   class="btn btn-sm btn-outline-success fw-bold">
                    <i class="bi bi-file-earmark-spreadsheet me-1"></i>Export CSV
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Main Table -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white border-0 d-flex align-items-center justify-content-between py-3">
        <h6 class="fw-bold mb-0">
            <i class="fas fa-file-invoice-dollar me-2 text-warning"></i>รายการชำระเงิน
            <span class="badge bg-secondary ms-2"><?= count($registrations) ?> รายการ</span>
        </h6>
    </div>
    <div class="card-body p-0">
        <?php if (empty($registrations)): ?>
        <div class="text-center py-5 text-muted">
            <i class="fas fa-inbox fa-3x mb-3 opacity-25 d-block"></i>
            <p class="fw-bold">ไม่พบรายการ<?= $filterClass ? 'ในห้อง ' . htmlspecialchars($filterClass) : '' ?></p>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="payTable">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4 small text-uppercase text-muted fw-bold" style="width:50px">สาย</th>
                        <th class="small text-uppercase text-muted fw-bold">รหัส</th>
                        <th class="small text-uppercase text-muted fw-bold">ชื่อ-นามสกุล</th>
                        <th class="small text-uppercase text-muted fw-bold">ชั้น</th>
                        <th class="small text-uppercase text-muted fw-bold">จุดขึ้น</th>
                        <th class="small text-uppercase text-muted fw-bold text-end">ยอด</th>
                        <th class="small text-uppercase text-muted fw-bold text-end">ชำระแล้ว</th>
                        <th class="small text-uppercase text-muted fw-bold text-end">ค้างชำระ</th>
                        <th class="small text-uppercase text-muted fw-bold text-center">สถานะ</th>
                        <th class="small text-uppercase text-muted fw-bold pe-4 text-center">จัดการ</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($registrations as $r):
                    $statusCfg = [
                        'paid'    => ['bg-success',  'text-success',  'Paid',    'ชำระครบ'],
                        'partial' => ['bg-warning',  'text-warning',  'Partial', 'บางส่วน'],
                        'unpaid'  => ['bg-danger',   'text-danger',   'Unpaid',  'ยังไม่ชำระ'],
                    ][$r['pay_status']];
                    $pct = $r['price'] > 0 ? round($r['paid_amount'] / $r['price'] * 100) : 0;
                ?>
                <tr>
                    <td class="ps-4">
                        <span class="badge bg-secondary bg-opacity-25 text-secondary fw-bold"><?= htmlspecialchars($r['route_code']) ?></span>
                    </td>
                    <td class="small fw-bold text-muted"><?= htmlspecialchars($r['student_id']) ?></td>
                    <td>
                        <div class="fw-bold small"><?= htmlspecialchars($r['fullname']) ?></div>
                        <?php if ($r['pending_slips'] > 0): ?>
                        <span class="badge bg-warning text-dark" style="font-size:.65rem"><i class="bi bi-image-fill me-1"></i>สลิปรอ <?= (int)$r['pending_slips'] ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="small text-muted"><?= htmlspecialchars($r['classroom']) ?></td>
                    <td class="small text-muted"><?= htmlspecialchars($r['route_name']) ?></td>
                    <td class="text-end fw-bold small"><?= number_format($r['price'], 0) ?></td>
                    <td class="text-end small <?= $statusCfg[1] ?> fw-bold"><?= number_format($r['paid_amount'], 0) ?></td>
                    <td class="text-end small">
                        <?php if ($r['balance'] > 0.01): ?>
                        <span class="fw-bold text-danger"><?= number_format($r['balance'], 0) ?></span>
                        <?php else: ?>
                        <i class="bi bi-check-lg text-success"></i>
                        <?php endif; ?>
                    </td>
                    <td class="text-center">
                        <div>
                            <span class="badge <?= $statusCfg[0] ?> bg-opacity-15 <?= $statusCfg[1] ?> fw-bold px-2 py-1"><?= $statusCfg[3] ?></span>
                        </div>
                        <?php if ($r['pay_status'] !== 'unpaid'): ?>
                        <div class="progress mt-1" style="height:3px;width:60px;margin:auto">
                            <div class="progress-bar bg-success" style="width:<?= $pct ?>%"></div>
                        </div>
                        <?php endif; ?>
                    </td>
                    <td class="pe-4 text-center">
                        <div class="d-flex gap-1 justify-content-center flex-wrap">
                            <?php if ($r['balance'] > 0.01 && in_array($r['reg_status'], ['active','pending_cancel'])): ?>
                            <button type="button" class="btn btn-success btn-sm fw-bold"
                                onclick="openPayModal(<?= (int)$r['reg_id'] ?>,'<?= htmlspecialchars(addslashes($r['fullname'])) ?>',<?= (float)$r['balance'] ?>,<?= (float)$r['price'] ?>)">
                                <i class="bi bi-plus-circle-fill me-1"></i>ชำระ
                            </button>
                            <?php endif; ?>
                            <button type="button" class="btn btn-outline-secondary btn-sm"
                                onclick="openHistoryModal(<?= (int)$r['reg_id'] ?>,'<?= htmlspecialchars(addslashes($r['fullname'])) ?>')">
                                <i class="bi bi-clock-history"></i>
                            </button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot class="table-light">
                    <tr>
                        <td colspan="5" class="ps-4 fw-bold small text-muted">รวมทั้งหมด <?= count($registrations) ?> คน</td>
                        <td class="text-end fw-black small"><?= number_format($totalAmount, 0) ?></td>
                        <td class="text-end fw-black small text-success"><?= number_format($totalCollected, 0) ?></td>
                        <td class="text-end fw-black small text-danger"><?= number_format($totalBalance, 0) ?></td>
                        <td colspan="2"></td>
                    </tr>
                </tfoot>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Pending Slips Section -->
<?php if (!empty($pendingSlips)): ?>
<div class="card border-0 shadow-sm mb-4" id="slipSection">
    <div class="card-header bg-white border-0 d-flex align-items-center gap-2 py-3">
        <h6 class="fw-bold mb-0"><i class="bi bi-image-fill me-2 text-warning"></i>สลิปรอตรวจสอบ</h6>
        <span class="badge bg-warning text-dark"><?= count($pendingSlips) ?></span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4 small text-uppercase text-muted fw-bold">นักเรียน</th>
                        <th class="small text-uppercase text-muted fw-bold">ห้อง</th>
                        <th class="small text-uppercase text-muted fw-bold text-end">ยอดสลิป (฿)</th>
                        <th class="small text-uppercase text-muted fw-bold text-end">ค้างชำระ (฿)</th>
                        <th class="small text-uppercase text-muted fw-bold">หมายเหตุ</th>
                        <th class="small text-uppercase text-muted fw-bold">สลิป</th>
                        <th class="small text-uppercase text-muted fw-bold">วันที่ส่ง</th>
                        <th class="small text-uppercase text-muted fw-bold pe-4">จัดการ</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($pendingSlips as $sp):
                    $spBalance = max(0, (float)$sp['route_price'] - (float)$sp['already_paid']); ?>
                <tr>
                    <td class="ps-4">
                        <div class="fw-bold small"><?= htmlspecialchars($sp['student_name']) ?></div>
                        <div class="small text-muted"><?= htmlspecialchars($sp['student_sid']) ?></div>
                    </td>
                    <td class="small"><?= htmlspecialchars($sp['classroom']) ?></td>
                    <td class="text-end fw-bold small text-primary"><?= number_format($sp['amount'], 0) ?></td>
                    <td class="text-end small <?= $spBalance > 0.01 ? 'text-danger' : 'text-success' ?>"><?= number_format($spBalance, 0) ?></td>
                    <td class="small text-muted"><?= htmlspecialchars($sp['note'] ?? '') ?></td>
                    <td>
                        <a href="/bus/uploads/slips/<?= htmlspecialchars($sp['slip_image']) ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-eye-fill me-1"></i>ดูสลิป
                        </a>
                    </td>
                    <td class="small text-muted"><?= htmlspecialchars(date('d/m/Y H:i', strtotime($sp['created_at']))) ?></td>
                    <td class="pe-4">
                        <div class="d-flex gap-1">
                            <button type="button" class="btn btn-sm btn-success fw-bold"
                                onclick="approveSlip(<?= (int)$sp['id'] ?>,'<?= htmlspecialchars(addslashes($sp['student_name'])) ?>',<?= number_format($sp['amount'], 2, '.', '') ?>)">
                                <i class="bi bi-check-lg"></i> อนุมัติ
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-danger"
                                onclick="rejectSlip(<?= (int)$sp['id'] ?>,'<?= htmlspecialchars(addslashes($sp['student_name'])) ?>')">
                                <i class="bi bi-x-lg"></i> ปฏิเสธ
                            </button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ── Pay Modal ─────────────────────────────────────────────────────────── -->
<div class="modal fade" id="payModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-success text-white border-0">
                <h6 class="modal-title fw-bold"><i class="bi bi-plus-circle-fill me-2"></i>บันทึกการชำระเงิน</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="payForm">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="record_payment">
                <input type="hidden" name="reg_id" id="payRegId">
                <div class="modal-body">
                    <div class="alert alert-light border rounded-3 mb-3">
                        <div class="fw-bold" id="payStudentName"></div>
                        <div class="small text-muted mt-1">ค้างชำระ: <strong id="payBalanceText" class="text-danger"></strong></div>
                    </div>
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label fw-bold small">จำนวนเงิน (บาท) <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-currency-exchange"></i></span>
                                <input type="number" name="amount" id="payAmount" class="form-control fw-bold fs-5" min="1" step="50" required>
                                <span class="input-group-text">฿</span>
                            </div>
                            <div class="d-flex gap-1 mt-2" id="quickAmounts"></div>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-bold small">หมายเหตุ</label>
                            <input type="text" name="note" class="form-control" maxlength="255" placeholder="เช่น ชำระครั้งที่ 1, เงินสด">
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn btn-success fw-bold px-4"><i class="bi bi-save me-1"></i>บันทึก</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ── History Modal ──────────────────────────────────────────────────────── -->
<div class="modal fade" id="historyModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-0">
                <h6 class="modal-title fw-bold"><i class="bi bi-clock-history me-2 text-primary"></i>ประวัติการชำระ — <span id="histName"></span></h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0">
                <div id="histBody" class="p-4 text-center text-muted"><i class="bi bi-hourglass-split me-2"></i>กำลังโหลด...</div>
            </div>
        </div>
    </div>
</div>

<!-- ── Approve Slip Modal ─────────────────────────────────────────────────── -->
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
                    <p class="small">อนุมัติสลิปของ <strong id="approveStudentName"></strong> จำนวน <strong id="approveAmount" class="text-success"></strong> บาท?</p>
                    <input type="text" name="note" class="form-control" placeholder="หมายเหตุ (ไม่บังคับ)" maxlength="255">
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn btn-success fw-bold"><i class="bi bi-check-lg me-1"></i>ยืนยัน</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ── Reject Slip Modal ──────────────────────────────────────────────────── -->
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
                    <p class="small">ปฏิเสธสลิปของ <strong id="rejectStudentName"></strong>?</p>
                    <textarea name="admin_note" class="form-control" rows="3" placeholder="เหตุผล..." required maxlength="500"></textarea>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn btn-danger fw-bold"><i class="bi bi-x-lg me-1"></i>ยืนยัน</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// ── Pay Modal ─────────────────────────────────────────────────────────────────
function openPayModal(regId, name, balance, price) {
    document.getElementById('payRegId').value   = regId;
    document.getElementById('payStudentName').textContent = name;
    document.getElementById('payBalanceText').textContent = balance.toLocaleString('th-TH', {maximumFractionDigits:0}) + ' บาท';
    const amtInput = document.getElementById('payAmount');
    amtInput.value = Math.round(balance);
    amtInput.max   = balance;

    // Quick amount buttons
    const qDiv = document.getElementById('quickAmounts');
    qDiv.innerHTML = '';
    const suggestions = [];
    if (balance > 0) suggestions.push(Math.round(balance));          // full
    if (price >= 200 && balance > price / 2) suggestions.push(Math.round(price / 2)); // half
    [...new Set(suggestions)].slice(0,3).forEach(v => {
        const b = document.createElement('button');
        b.type = 'button';
        b.className = 'btn btn-sm btn-outline-success';
        b.textContent = v.toLocaleString('th-TH') + ' ฿';
        b.onclick = () => amtInput.value = v;
        qDiv.appendChild(b);
    });

    new bootstrap.Modal(document.getElementById('payModal')).show();
}

// ── History Modal ─────────────────────────────────────────────────────────────
async function openHistoryModal(regId, name) {
    document.getElementById('histName').textContent = name;
    document.getElementById('histBody').innerHTML = '<div class="text-center text-muted py-4"><i class="bi bi-hourglass-split me-2"></i>กำลังโหลด...</div>';
    const m = new bootstrap.Modal(document.getElementById('historyModal'));
    m.show();
    try {
        const res  = await fetch('/bus/finance/api/payment_history.php?reg_id=' + regId);
        const data = await res.json();
        if (!data.payments || data.payments.length === 0) {
            document.getElementById('histBody').innerHTML = '<p class="text-center text-muted py-4">ยังไม่มีรายการชำระเงิน</p>';
            return;
        }
        let html = '<div class="table-responsive"><table class="table align-middle mb-0"><thead class="table-light"><tr>'
                 + '<th class="ps-4 small text-muted fw-bold">#</th>'
                 + '<th class="small text-muted fw-bold">วันที่-เวลา</th>'
                 + '<th class="small text-muted fw-bold text-end">จำนวน (฿)</th>'
                 + '<th class="small text-muted fw-bold">หมายเหตุ</th>'
                 + '<th class="small text-muted fw-bold pe-4">บันทึกโดย</th>'
                 + '</tr></thead><tbody>';
        data.payments.forEach((p, i) => {
            html += `<tr>
                <td class="ps-4 text-muted small">${i+1}</td>
                <td class="small">${p.paid_at}</td>
                <td class="text-end fw-bold text-success">${Number(p.amount).toLocaleString('th-TH', {maximumFractionDigits:0})}</td>
                <td class="small text-muted">${p.note || '-'}</td>
                <td class="small text-muted pe-4">${p.staff_name || '-'}</td>
            </tr>`;
        });
        const total = data.payments.reduce((s, p) => s + Number(p.amount), 0);
        html += `</tbody><tfoot class="table-light">
            <tr><td colspan="2" class="ps-4 fw-bold small">รวม ${data.payments.length} รายการ</td>
            <td class="text-end fw-black text-success">${total.toLocaleString('th-TH', {maximumFractionDigits:0})}</td>
            <td colspan="2"></td></tr></tfoot></table></div>`;
        document.getElementById('histBody').innerHTML = html;
    } catch(e) {
        document.getElementById('histBody').innerHTML = '<p class="text-center text-danger py-4">โหลดข้อมูลไม่สำเร็จ</p>';
    }
}

// ── Slip actions ──────────────────────────────────────────────────────────────
function approveSlip(id, name, amount) {
    document.getElementById('approveSlipId').value = id;
    document.getElementById('approveStudentName').textContent = name;
    document.getElementById('approveAmount').textContent = Number(amount).toLocaleString('th-TH', {maximumFractionDigits:0});
    new bootstrap.Modal(document.getElementById('approveModal')).show();
}
function rejectSlip(id, name) {
    document.getElementById('rejectSlipId').value = id;
    document.getElementById('rejectStudentName').textContent = name;
    new bootstrap.Modal(document.getElementById('rejectModal')).show();
}
</script>

<?php require_once __DIR__ . '/../../components/layout_end.php'; ?>
