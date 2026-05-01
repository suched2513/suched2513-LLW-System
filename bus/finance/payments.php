<?php
session_start();
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../config.php';
busRequireStaff(['bus_admin', 'bus_finance', 'super_admin']);

$pdo      = getPdo();
$semester = busGetSemester();
$semLabel = busSemesterLabel($semester);
$msg      = '';
$err      = '';

$search      = trim($_GET['q'] ?? '');
$filterClass = trim($_GET['classroom'] ?? '');
$staffId     = (int)($_SESSION['user_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'record_payment') {
        $regId   = (int)($_POST['reg_id'] ?? 0);
        $amount  = (float)($_POST['amount'] ?? 0);
        $note    = trim($_POST['note'] ?? '');

        if ($regId <= 0 || $amount <= 0) {
            $err = 'กรุณาระบุจำนวนเงินที่ถูกต้อง';
        } else {
            try {
                $rStmt = $pdo->prepare("SELECT reg.id, reg.status, rt.price FROM bus_registrations reg JOIN bus_routes rt ON rt.id = reg.route_id WHERE reg.id = ? AND reg.semester = ?");
                $rStmt->execute([$regId, $semester]);
                $regRow = $rStmt->fetch(PDO::FETCH_ASSOC);

                if (!$regRow || !in_array($regRow['status'], ['active', 'pending_cancel'])) {
                    $err = 'ไม่พบรายการลงทะเบียนหรือไม่สามารถบันทึกการชำระเงินได้';
                } else {
                    $paidStmt = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM bus_payments WHERE registration_id = ?");
                    $paidStmt->execute([$regId]);
                    $alreadyPaid = (float)$paidStmt->fetchColumn();

                    if ($alreadyPaid + $amount > (float)$regRow['price'] + 0.01) {
                        $err = 'จำนวนเงินเกินกว่ายอดที่ต้องชำระ';
                    } else {
                        $ins = $pdo->prepare("INSERT INTO bus_payments (registration_id, amount, note, recorded_by) VALUES (?,?,?,?)");
                        $ins->execute([$regId, $amount, $note, $staffId ?: null]);
                        $msg    = 'บันทึกการชำระเงิน ' . number_format($amount, 0) . ' บาท เรียบร้อยแล้ว';
                        $search = $_POST['student_search'] ?? $search;
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

        if ($slipId <= 0) {
            $err = 'ข้อมูลสลิปไม่ถูกต้อง';
        } else {
            try {
                $pdo->beginTransaction();

                // Fetch the slip and verify it's pending
                $slipStmt = $pdo->prepare("
                    SELECT sp.*, reg.status AS reg_status, rt.price,
                           COALESCE((SELECT SUM(amount) FROM bus_payments WHERE registration_id = sp.registration_id), 0) AS already_paid
                    FROM bus_payment_slips sp
                    JOIN bus_registrations reg ON reg.id = sp.registration_id
                    JOIN bus_routes rt ON rt.id = reg.route_id
                    WHERE sp.id = ? AND sp.status = 'pending'
                ");
                $slipStmt->execute([$slipId]);
                $slip = $slipStmt->fetch(PDO::FETCH_ASSOC);

                if (!$slip) {
                    $pdo->rollBack();
                    $err = 'ไม่พบสลิปหรือสลิปนี้ถูกตรวจสอบแล้ว';
                } elseif (!in_array($slip['reg_status'], ['active', 'pending_cancel'])) {
                    $pdo->rollBack();
                    $err = 'ไม่สามารถอนุมัติได้ — สถานะการลงทะเบียนไม่ถูกต้อง';
                } else {
                    $balance = (float)$slip['price'] - (float)$slip['already_paid'];
                    $amount  = min((float)$slip['amount'], $balance);
                    if ($amount <= 0) {
                        $pdo->rollBack();
                        $err = 'ชำระครบแล้ว ไม่ต้องอนุมัติสลิปนี้';
                    } else {
                        // Insert payment
                        $ins = $pdo->prepare("INSERT INTO bus_payments (registration_id, amount, note, recorded_by) VALUES (?,?,?,?)");
                        $ins->execute([$slip['registration_id'], $amount, 'อนุมัติจากสลิป #' . $slipId . ($note ? ' — ' . $note : ''), $staffId ?: null]);
                        // Update slip status
                        $upd = $pdo->prepare("UPDATE bus_payment_slips SET status='approved', admin_note=?, reviewed_by=?, reviewed_at=NOW() WHERE id=?");
                        $upd->execute([$note ?: null, $staffId ?: null, $slipId]);
                        $pdo->commit();
                        $msg = 'อนุมัติสลิปและบันทึกการชำระเงิน ' . number_format($amount, 0) . ' บาท เรียบร้อยแล้ว';
                    }
                }
            } catch (Exception $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                error_log($e->getMessage());
                $err = 'เกิดข้อผิดพลาด กรุณาลองใหม่';
            }
        }
    } elseif ($action === 'reject_slip') {
        $slipId    = (int)($_POST['slip_id'] ?? 0);
        $adminNote = trim($_POST['admin_note'] ?? '');

        if ($slipId <= 0) {
            $err = 'ข้อมูลสลิปไม่ถูกต้อง';
        } else {
            try {
                $upd = $pdo->prepare("UPDATE bus_payment_slips SET status='rejected', admin_note=?, reviewed_by=?, reviewed_at=NOW() WHERE id=? AND status='pending'");
                $upd->execute([$adminNote ?: null, $staffId ?: null, $slipId]);
                if ($upd->rowCount() > 0) {
                    $msg = 'ปฏิเสธสลิปเรียบร้อยแล้ว';
                } else {
                    $err = 'ไม่พบสลิปหรือสลิปนี้ถูกตรวจสอบแล้ว';
                }
            } catch (Exception $e) {
                error_log($e->getMessage());
                $err = 'เกิดข้อผิดพลาด กรุณาลองใหม่';
            }
        }
    }
}

// Load classrooms
try {
    $clsStmt = $pdo->query("SELECT DISTINCT classroom FROM bus_students WHERE is_active=1 AND classroom != '' ORDER BY classroom");
    $classrooms = $clsStmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    error_log($e->getMessage());
    $classrooms = [];
}

// Load students by classroom filter
$classStudents = [];
if ($filterClass !== '') {
    try {
        $csStmt = $pdo->prepare("
            SELECT s.id, s.student_id, s.fullname, s.classroom,
                   reg.id AS reg_id, reg.status AS reg_status, rt.price AS route_price,
                   COALESCE((SELECT SUM(p.amount) FROM bus_payments p WHERE p.registration_id = reg.id), 0) AS total_paid,
                   (SELECT COUNT(*) FROM bus_payment_slips sp WHERE sp.registration_id = reg.id AND sp.status = 'pending') AS pending_slips
            FROM bus_students s
            LEFT JOIN bus_registrations reg ON reg.student_id = s.id AND reg.semester = ?
            LEFT JOIN bus_routes rt ON rt.id = reg.route_id
            WHERE s.is_active = 1 AND s.classroom = ?
            ORDER BY s.fullname
        ");
        $csStmt->execute([$semester, $filterClass]);
        $classStudents = $csStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log($e->getMessage());
        $classStudents = [];
    }
}

// Search student
if ($search !== '') {
    try {
        $stuStmt = $pdo->prepare("
            SELECT s.*, reg.id AS reg_id, reg.status AS reg_status, rt.route_name, rt.price AS route_price
            FROM bus_students s
            LEFT JOIN bus_registrations reg ON reg.student_id = s.id AND reg.semester = ?
            LEFT JOIN bus_routes rt ON rt.id = reg.route_id
            WHERE s.is_active = 1 AND (s.student_id LIKE ? OR s.fullname LIKE ?)
            ORDER BY s.classroom, s.fullname
            LIMIT 20
        ");
        $stuStmt->execute([$semester, "%$search%", "%$search%"]);
        $students = $stuStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log($e->getMessage());
        $students = [];
    }
} else {
    $students = [];
}

// Load selected student's full payment detail
$selectedRegId = (int)($_GET['reg_id'] ?? $_POST['reg_id'] ?? 0);
$reg = null; $payments = []; $totalPaid = 0; $balance = 0;
if ($selectedRegId > 0) {
    try {
        $rStmt = $pdo->prepare("
            SELECT reg.id, reg.status, reg.registered_at,
                   s.student_id, s.fullname, s.classroom, s.national_id_masked,
                   rt.route_code, rt.route_name, rt.price
            FROM bus_registrations reg
            JOIN bus_students s ON s.id = reg.student_id
            JOIN bus_routes rt ON rt.id = reg.route_id
            WHERE reg.id = ? AND reg.semester = ?
        ");
        $rStmt->execute([$selectedRegId, $semester]);
        $reg = $rStmt->fetch(PDO::FETCH_ASSOC);

        if ($reg) {
            $pStmt = $pdo->prepare("
                SELECT p.*, u.firstname AS staff_name
                FROM bus_payments p
                LEFT JOIN llw_users u ON u.user_id = p.recorded_by
                WHERE p.registration_id = ?
                ORDER BY p.paid_at DESC
            ");
            $pStmt->execute([$selectedRegId]);
            $payments  = $pStmt->fetchAll(PDO::FETCH_ASSOC);
            $totalPaid = array_sum(array_column($payments, 'amount'));
            $balance   = (float)$reg['price'] - $totalPaid;
        }
    } catch (Exception $e) {
        error_log($e->getMessage());
    }
}

// Load pending slips for current semester
try {
    $spStmt = $pdo->prepare("
        SELECT sp.*,
               s.fullname AS student_name, s.classroom, s.student_id AS student_sid,
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
} catch (Exception $e) {
    error_log($e->getMessage());
    $pendingSlips = [];
}

$pageTitle    = 'บันทึกการชำระเงิน';
$pageSubtitle = $semLabel;
$activeSystem = 'bus';
require_once __DIR__ . '/../../components/layout_start.php';
?>

<?php if ($msg): ?>
<div class="alert alert-success alert-dismissible fade show"><i class="bi bi-check-circle-fill me-2"></i><?= htmlspecialchars($msg) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>
<?php if ($err): ?>
<div class="alert alert-danger alert-dismissible fade show"><i class="bi bi-exclamation-triangle-fill me-2"></i><?= htmlspecialchars($err) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>

<div class="container-fluid">
  <div class="row g-4">

    <!-- Left Panel: Classroom filter + search -->
    <div class="col-12 col-xl-4">

      <!-- Classroom Filter -->
      <?php if (!empty($classrooms)): ?>
      <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white border-0">
          <h6 class="fw-bold mb-0"><i class="bi bi-funnel-fill me-2 text-warning"></i>กรองตามห้อง</h6>
        </div>
        <div class="card-body pt-0">
          <form method="GET" class="d-flex gap-2 align-items-center">
            <?php if ($search !== ''): ?>
            <input type="hidden" name="q" value="<?= htmlspecialchars($search) ?>">
            <?php endif; ?>
            <select name="classroom" class="form-select form-select-sm" onchange="this.form.submit()">
              <option value="">— เลือกห้อง —</option>
              <?php foreach ($classrooms as $cls): ?>
              <option value="<?= htmlspecialchars($cls) ?>" <?= $filterClass === $cls ? 'selected' : '' ?>>
                <?= htmlspecialchars($cls) ?>
              </option>
              <?php endforeach; ?>
            </select>
            <?php if ($filterClass !== ''): ?>
            <a href="/bus/finance/payments.php<?= $search ? '?q=' . urlencode($search) : '' ?>" class="btn btn-sm btn-light text-nowrap"><i class="bi bi-x"></i></a>
            <?php endif; ?>
          </form>

          <?php if ($filterClass !== '' && !empty($classStudents)): ?>
          <div class="mt-3">
            <div class="small fw-bold text-muted mb-2">นักเรียน <?= htmlspecialchars($filterClass) ?> (<?= count($classStudents) ?> คน)</div>
            <div class="list-group list-group-flush" style="max-height:320px;overflow-y:auto">
              <?php foreach ($classStudents as $cs): ?>
              <?php
                $csBalance    = $cs['reg_id'] ? max(0, (float)$cs['route_price'] - (float)$cs['total_paid']) : null;
                $hasPending   = (int)$cs['pending_slips'] > 0;
                $isSelected   = (int)$cs['reg_id'] === $selectedRegId;
              ?>
              <a href="/bus/finance/payments.php?classroom=<?= urlencode($filterClass) ?><?= $search ? '&q=' . urlencode($search) : '' ?>&reg_id=<?= (int)$cs['reg_id'] ?>"
                 class="list-group-item list-group-item-action py-2 <?= $isSelected ? 'active' : '' ?> <?= !$cs['reg_id'] ? 'text-muted' : '' ?>">
                <div class="d-flex justify-content-between align-items-start gap-1">
                  <div class="min-w-0">
                    <div class="small fw-bold text-truncate"><?= htmlspecialchars($cs['fullname']) ?></div>
                    <div class="small opacity-75"><?= htmlspecialchars($cs['student_id']) ?></div>
                  </div>
                  <div class="text-end flex-shrink-0">
                    <?php if ($cs['reg_id'] && $csBalance !== null): ?>
                    <div class="small fw-bold <?= $csBalance > 0.01 ? 'text-danger' : 'text-success' ?>">
                      <?= $csBalance > 0.01 ? number_format($csBalance, 0) . ' ฿' : '<i class="bi bi-check-circle-fill"></i>' ?>
                    </div>
                    <?php if ($hasPending): ?>
                    <span class="badge bg-warning text-dark" style="font-size:0.65rem">สลิปรอ</span>
                    <?php endif; ?>
                    <?php else: ?>
                    <span class="badge bg-secondary bg-opacity-25 text-secondary" style="font-size:0.65rem">ยังไม่ลง</span>
                    <?php endif; ?>
                  </div>
                </div>
              </a>
              <?php endforeach; ?>
            </div>
          </div>
          <?php elseif ($filterClass !== ''): ?>
          <p class="text-muted small text-center py-2 mt-2">ไม่พบนักเรียนในห้อง <?= htmlspecialchars($filterClass) ?></p>
          <?php endif; ?>
        </div>
      </div>
      <?php endif; ?>

      <!-- Search -->
      <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-0">
          <h6 class="fw-bold mb-0"><i class="bi bi-search me-2 text-primary"></i>ค้นหานักเรียน</h6>
        </div>
        <div class="card-body">
          <form method="GET" class="d-flex gap-2 mb-3">
            <?php if ($filterClass !== ''): ?>
            <input type="hidden" name="classroom" value="<?= htmlspecialchars($filterClass) ?>">
            <?php endif; ?>
            <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="รหัสนักเรียน หรือชื่อ..." class="form-control" autofocus>
            <button type="submit" class="btn btn-primary fw-bold px-3"><i class="bi bi-search"></i></button>
          </form>

          <?php if (!empty($students)): ?>
          <div class="list-group">
            <?php foreach ($students as $s): ?>
            <a href="/bus/finance/payments.php?q=<?= urlencode($search) ?><?= $filterClass ? '&classroom=' . urlencode($filterClass) : '' ?>&reg_id=<?= (int)$s['reg_id'] ?>"
               class="list-group-item list-group-item-action <?= (int)$s['reg_id'] === $selectedRegId ? 'active' : '' ?>">
              <div class="d-flex justify-content-between">
                <div>
                  <div class="fw-bold small"><?= htmlspecialchars($s['fullname']) ?></div>
                  <div class="small opacity-75"><?= htmlspecialchars($s['student_id']) ?> · <?= htmlspecialchars($s['classroom']) ?></div>
                </div>
                <?php if ($s['reg_id']): ?>
                <span class="badge bg-<?= $s['reg_status'] === 'active' ? 'success' : 'warning' ?> bg-opacity-25 text-<?= $s['reg_status'] === 'active' ? 'success' : 'warning' ?> small align-self-start">
                  <?= $s['reg_status'] === 'active' ? 'ลงทะเบียนแล้ว' : 'รอยกเลิก' ?>
                </span>
                <?php else: ?>
                <span class="badge bg-secondary bg-opacity-10 text-secondary small align-self-start">ยังไม่ลงทะเบียน</span>
                <?php endif; ?>
              </div>
            </a>
            <?php endforeach; ?>
          </div>
          <?php elseif ($search !== ''): ?>
          <p class="text-center text-muted small py-3">ไม่พบนักเรียน</p>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Right Panel: Payment detail -->
    <div class="col-12 col-xl-8">
      <?php if ($reg): ?>
      <!-- Student Info Card -->
      <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
          <div class="row align-items-center">
            <div class="col">
              <h5 class="fw-bold mb-1"><?= htmlspecialchars($reg['fullname']) ?></h5>
              <div class="text-muted small"><?= htmlspecialchars($reg['student_id']) ?> · <?= htmlspecialchars($reg['classroom']) ?></div>
              <div class="text-muted small mt-1">สาย <?= htmlspecialchars($reg['route_code']) ?> <?= htmlspecialchars($reg['route_name']) ?> · <?= number_format($reg['price'], 0) ?> บาท/ภาคเรียน</div>
            </div>
            <div class="col-auto text-end">
              <div class="text-muted small">ค้างชำระ</div>
              <div class="fs-4 fw-bold <?= $balance > 0 ? 'text-danger' : 'text-success' ?>"><?= number_format($balance, 0) ?> ฿</div>
              <div class="progress mt-2" style="height:6px;width:120px;margin-left:auto">
                <div class="progress-bar bg-success" style="width:<?= $reg['price'] > 0 ? min(100, round($totalPaid / $reg['price'] * 100)) : 0 ?>%"></div>
              </div>
              <div class="text-muted small mt-1"><?= number_format($totalPaid, 0) ?>/<?= number_format($reg['price'], 0) ?> บาท</div>
            </div>
          </div>
        </div>
      </div>

      <!-- Record Payment Form -->
      <?php if ($balance > 0.01 && in_array($reg['status'], ['active','pending_cancel'])): ?>
      <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white border-0">
          <h6 class="fw-bold mb-0"><i class="bi bi-plus-circle-fill me-2 text-success"></i>บันทึกการชำระเงิน</h6>
        </div>
        <div class="card-body">
          <form method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="record_payment">
            <input type="hidden" name="reg_id" value="<?= (int)$reg['id'] ?>">
            <input type="hidden" name="student_search" value="<?= htmlspecialchars($search) ?>">
            <div class="row g-3 align-items-end">
              <div class="col-12 col-md-5">
                <label class="form-label fw-bold small">จำนวนเงิน (บาท) <span class="text-danger">*</span></label>
                <input type="number" name="amount" class="form-control fw-bold" min="1" max="<?= $balance ?>" step="50" value="<?= number_format($balance, 0, '.', '') ?>" required>
                <div class="form-text">ค้างชำระ: <?= number_format($balance, 0) ?> บาท</div>
              </div>
              <div class="col-12 col-md-5">
                <label class="form-label fw-bold small">หมายเหตุ</label>
                <input type="text" name="note" class="form-control" maxlength="255" placeholder="เช่น ชำระครั้งที่ 1">
              </div>
              <div class="col-12 col-md-2">
                <button type="submit" class="btn btn-success fw-bold w-100"><i class="bi bi-save me-1"></i>บันทึก</button>
              </div>
            </div>
          </form>
        </div>
      </div>
      <?php elseif ($balance <= 0.01): ?>
      <div class="alert alert-success border-0"><i class="bi bi-check-circle-fill me-2"></i>ชำระเงินครบถ้วนแล้ว</div>
      <?php endif; ?>

      <!-- Payment History -->
      <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-0">
          <h6 class="fw-bold mb-0"><i class="bi bi-clock-history me-2 text-primary"></i>ประวัติการชำระเงิน (<?= count($payments) ?> รายการ)</h6>
        </div>
        <div class="card-body p-0">
          <?php if (empty($payments)): ?>
          <p class="text-center text-muted py-4">ยังไม่มีรายการชำระเงิน</p>
          <?php else: ?>
          <div class="table-responsive">
            <table class="table align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th class="ps-4 small text-uppercase text-muted fw-bold">#</th>
                  <th class="small text-uppercase text-muted fw-bold">วันที่-เวลา</th>
                  <th class="small text-uppercase text-muted fw-bold text-end">จำนวน</th>
                  <th class="small text-uppercase text-muted fw-bold">หมายเหตุ</th>
                  <th class="small text-uppercase text-muted fw-bold pe-4">บันทึกโดย</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($payments as $i => $p): ?>
                <tr>
                  <td class="ps-4 text-muted small"><?= $i + 1 ?></td>
                  <td class="small"><?= htmlspecialchars(date('d/m/Y H:i', strtotime($p['paid_at']))) ?></td>
                  <td class="text-end fw-bold text-success"><?= number_format($p['amount'], 0) ?> ฿</td>
                  <td class="small text-muted"><?= htmlspecialchars($p['note'] ?? '') ?></td>
                  <td class="small text-muted pe-4"><?= htmlspecialchars($p['staff_name'] ?? '-') ?></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <?php endif; ?>
        </div>
      </div>

      <?php else: ?>
      <div class="card border-0 shadow-sm">
        <div class="card-body text-center py-5 text-muted">
          <i class="bi bi-search fs-1 mb-3 opacity-25 d-block"></i>
          <p class="fw-bold">ค้นหาหรือเลือกนักเรียนด้านซ้ายเพื่อดูข้อมูลการชำระเงิน</p>
        </div>
      </div>
      <?php endif; ?>
    </div>

  </div><!-- /.row -->

  <!-- Pending Slips Section -->
  <div class="row mt-4">
    <div class="col-12">
      <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-0 d-flex align-items-center gap-2">
          <h6 class="fw-bold mb-0"><i class="bi bi-image-fill me-2 text-warning"></i>สลิปรอตรวจสอบ</h6>
          <?php if (!empty($pendingSlips)): ?>
          <span class="badge bg-warning text-dark"><?= count($pendingSlips) ?></span>
          <?php endif; ?>
        </div>
        <div class="card-body p-0">
          <?php if (empty($pendingSlips)): ?>
          <p class="text-center text-muted py-4 small"><i class="bi bi-check2-all me-1"></i>ไม่มีสลิปรอตรวจสอบ</p>
          <?php else: ?>
          <div class="table-responsive">
            <table class="table align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th class="ps-4 small text-uppercase text-muted fw-bold">นักเรียน</th>
                  <th class="small text-uppercase text-muted fw-bold">ห้อง</th>
                  <th class="small text-uppercase text-muted fw-bold text-end">ยอด (฿)</th>
                  <th class="small text-uppercase text-muted fw-bold text-end">คงเหลือ (฿)</th>
                  <th class="small text-uppercase text-muted fw-bold">หมายเหตุ</th>
                  <th class="small text-uppercase text-muted fw-bold">สลิป</th>
                  <th class="small text-uppercase text-muted fw-bold">วันที่ส่ง</th>
                  <th class="small text-uppercase text-muted fw-bold pe-4">จัดการ</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($pendingSlips as $sp): ?>
                <?php $spBalance = max(0, (float)$sp['route_price'] - (float)$sp['already_paid']); ?>
                <tr id="slip-row-<?= (int)$sp['id'] ?>">
                  <td class="ps-4">
                    <div class="fw-bold small"><?= htmlspecialchars($sp['student_name']) ?></div>
                    <div class="small text-muted"><?= htmlspecialchars($sp['student_sid']) ?></div>
                  </td>
                  <td class="small"><?= htmlspecialchars($sp['classroom']) ?></td>
                  <td class="text-end fw-bold small text-primary"><?= number_format($sp['amount'], 0) ?></td>
                  <td class="text-end small <?= $spBalance > 0.01 ? 'text-danger' : 'text-success' ?>"><?= number_format($spBalance, 0) ?></td>
                  <td class="small text-muted"><?= htmlspecialchars($sp['note'] ?? '') ?></td>
                  <td>
                    <a href="/bus/uploads/slips/<?= htmlspecialchars($sp['slip_image']) ?>" target="_blank"
                       class="btn btn-sm btn-outline-primary">
                      <i class="bi bi-eye-fill me-1"></i>ดูสลิป
                    </a>
                  </td>
                  <td class="small text-muted"><?= htmlspecialchars(date('d/m/Y H:i', strtotime($sp['created_at']))) ?></td>
                  <td class="pe-4">
                    <div class="d-flex gap-1">
                      <button type="button" class="btn btn-sm btn-success"
                        onclick="approveSlip(<?= (int)$sp['id'] ?>, '<?= htmlspecialchars(addslashes($sp['student_name'])) ?>', <?= number_format($sp['amount'], 2, '.', '') ?>)">
                        <i class="bi bi-check-lg"></i> อนุมัติ
                      </button>
                      <button type="button" class="btn btn-sm btn-outline-danger"
                        onclick="rejectSlip(<?= (int)$sp['id'] ?>, '<?= htmlspecialchars(addslashes($sp['student_name'])) ?>')">
                        <i class="bi bi-x-lg"></i> ปฏิเสธ
                      </button>
                    </div>
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
  </div><!-- /.row pending slips -->

</div><!-- /.container-fluid -->

<!-- Approve Slip Modal -->
<div class="modal fade" id="approveModal" tabindex="-1" aria-labelledby="approveModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow">
      <div class="modal-header bg-success text-white border-0">
        <h6 class="modal-title fw-bold" id="approveModalLabel"><i class="bi bi-check-circle-fill me-2"></i>อนุมัติสลิปการชำระเงิน</h6>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST" id="approveForm">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="approve_slip">
        <input type="hidden" name="slip_id" id="approveSlipId">
        <div class="modal-body">
          <p class="mb-3 small">อนุมัติสลิปของ <strong id="approveStudentName"></strong> จำนวน <strong id="approveAmount" class="text-success"></strong> บาท?</p>
          <div class="mb-3">
            <label class="form-label fw-bold small">หมายเหตุ (ไม่บังคับ)</label>
            <input type="text" name="note" class="form-control" placeholder="หมายเหตุเพิ่มเติม..." maxlength="255">
          </div>
        </div>
        <div class="modal-footer border-0">
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">ยกเลิก</button>
          <button type="submit" class="btn btn-success fw-bold"><i class="bi bi-check-lg me-1"></i>ยืนยันอนุมัติ</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Reject Slip Modal -->
<div class="modal fade" id="rejectModal" tabindex="-1" aria-labelledby="rejectModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow">
      <div class="modal-header bg-danger text-white border-0">
        <h6 class="modal-title fw-bold" id="rejectModalLabel"><i class="bi bi-x-circle-fill me-2"></i>ปฏิเสธสลิปการชำระเงิน</h6>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST" id="rejectForm">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="reject_slip">
        <input type="hidden" name="slip_id" id="rejectSlipId">
        <div class="modal-body">
          <p class="mb-3 small">ปฏิเสธสลิปของ <strong id="rejectStudentName"></strong>?</p>
          <div class="mb-3">
            <label class="form-label fw-bold small">เหตุผลที่ปฏิเสธ <span class="text-danger">*</span></label>
            <textarea name="admin_note" class="form-control" rows="3" placeholder="กรุณาระบุเหตุผล..." required maxlength="500"></textarea>
          </div>
        </div>
        <div class="modal-footer border-0">
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">ยกเลิก</button>
          <button type="submit" class="btn btn-danger fw-bold"><i class="bi bi-x-lg me-1"></i>ยืนยันปฏิเสธ</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function approveSlip(slipId, name, amount) {
    document.getElementById('approveSlipId').value = slipId;
    document.getElementById('approveStudentName').textContent = name;
    document.getElementById('approveAmount').textContent = amount.toLocaleString('th-TH', {minimumFractionDigits:0, maximumFractionDigits:0});
    new bootstrap.Modal(document.getElementById('approveModal')).show();
}
function rejectSlip(slipId, name) {
    document.getElementById('rejectSlipId').value = slipId;
    document.getElementById('rejectStudentName').textContent = name;
    new bootstrap.Modal(document.getElementById('rejectModal')).show();
}
</script>

<?php require_once __DIR__ . '/../../components/layout_end.php'; ?>
