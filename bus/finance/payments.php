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
$student  = null;
$reg      = null;
$payments = [];

$search = trim($_GET['q'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'record_payment') {
        $regId    = (int)($_POST['reg_id'] ?? 0);
        $amount   = (float)($_POST['amount'] ?? 0);
        $note     = trim($_POST['note'] ?? '');
        $staffId  = (int)($_SESSION['user_id'] ?? 0);

        if ($regId <= 0 || $amount <= 0) {
            $err = 'กรุณาระบุจำนวนเงินที่ถูกต้อง';
        } else {
            try {
                // Verify registration exists and is payable
                $rStmt = $pdo->prepare("SELECT reg.id, reg.status, rt.price FROM bus_registrations reg JOIN bus_routes rt ON rt.id = reg.route_id WHERE reg.id = ? AND reg.semester = ?");
                $rStmt->execute([$regId, $semester]);
                $regRow = $rStmt->fetch(PDO::FETCH_ASSOC);

                if (!$regRow || !in_array($regRow['status'], ['active', 'pending_cancel'])) {
                    $err = 'ไม่พบรายการลงทะเบียนหรือไม่สามารถบันทึกการชำระเงินได้';
                } else {
                    $alreadyPaid = (float)$pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM bus_payments WHERE registration_id = ?")->execute([$regId]) ? 0 : 0;
                    $paidStmt    = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM bus_payments WHERE registration_id = ?");
                    $paidStmt->execute([$regId]);
                    $alreadyPaid = (float)$paidStmt->fetchColumn();

                    if ($alreadyPaid + $amount > (float)$regRow['price'] + 0.01) {
                        $err = 'จำนวนเงินเกินกว่ายอดที่ต้องชำระ';
                    } else {
                        $ins = $pdo->prepare("INSERT INTO bus_payments (registration_id, amount, note, recorded_by) VALUES (?,?,?,?)");
                        $ins->execute([$regId, $amount, $note, $staffId ?: null]);
                        $msg = 'บันทึกการชำระเงิน ' . number_format($amount, 0) . ' บาท เรียบร้อยแล้ว';
                        $search = $_POST['student_search'] ?? $search;
                    }
                }
            } catch (Exception $e) {
                error_log($e->getMessage());
                $err = 'เกิดข้อผิดพลาด กรุณาลองใหม่';
            }
        }
    }
}

// Search student
if ($search !== '') {
    try {
        $stuStmt = $pdo->prepare("
            SELECT s.*, reg.id as reg_id, reg.status as reg_status, rt.route_name, rt.price as route_price
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
                SELECT p.*, u.firstname as staff_name
                FROM bus_payments p
                LEFT JOIN llw_users u ON u.user_id = p.recorded_by
                WHERE p.registration_id = ?
                ORDER BY p.paid_at DESC
            ");
            $pStmt->execute([$selectedRegId]);
            $payments    = $pStmt->fetchAll(PDO::FETCH_ASSOC);
            $totalPaid   = array_sum(array_column($payments, 'amount'));
            $balance     = (float)$reg['price'] - $totalPaid;
        }
    } catch (Exception $e) {
        error_log($e->getMessage());
    }
}

$pageTitle    = 'บันทึกการชำระเงิน';
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
  <div class="row g-4">

    <!-- Search -->
    <div class="col-12 col-xl-4">
      <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-0">
          <h6 class="fw-black mb-0"><i class="fas fa-search me-2 text-primary"></i>ค้นหานักเรียน</h6>
        </div>
        <div class="card-body">
          <form method="GET" class="d-flex gap-2 mb-3">
            <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="รหัสนักเรียน หรือชื่อ..." class="form-control" autofocus>
            <button type="submit" class="btn btn-primary fw-bold px-3"><i class="fas fa-search"></i></button>
          </form>

          <?php if (!empty($students)): ?>
          <div class="list-group">
            <?php foreach ($students as $s): ?>
            <a href="/bus/finance/payments.php?q=<?= urlencode($search) ?>&reg_id=<?= (int)$s['reg_id'] ?>"
               class="list-group-item list-group-item-action <?= (int)$s['reg_id'] === $selectedRegId ? 'active' : '' ?>">
              <div class="d-flex justify-content-between">
                <div>
                  <div class="fw-bold small"><?= htmlspecialchars($s['fullname']) ?></div>
                  <div class="small opacity-75"><?= htmlspecialchars($s['student_id']) ?> · <?= htmlspecialchars($s['classroom']) ?></div>
                </div>
                <?php if ($s['reg_id']): ?>
                <span class="badge bg-<?= $s['reg_status'] === 'active' ? 'success' : 'warning' ?> bg-opacity-25 text-<?= $s['reg_status'] === 'active' ? 'success' : 'warning' ?> small self-start">
                  <?= $s['reg_status'] === 'active' ? 'ลงทะเบียนแล้ว' : 'รอยกเลิก' ?>
                </span>
                <?php else: ?>
                <span class="badge bg-secondary bg-opacity-10 text-secondary small self-start">ยังไม่ลงทะเบียน</span>
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

    <!-- Payment Detail -->
    <div class="col-12 col-xl-8">
      <?php if ($reg): ?>
      <!-- Student Info Card -->
      <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
          <div class="row align-items-center">
            <div class="col">
              <h5 class="fw-black mb-1"><?= htmlspecialchars($reg['fullname']) ?></h5>
              <div class="text-muted small"><?= htmlspecialchars($reg['student_id']) ?> · <?= htmlspecialchars($reg['classroom']) ?></div>
              <div class="text-muted small mt-1">สาย <?= htmlspecialchars($reg['route_code']) ?> <?= htmlspecialchars($reg['route_name']) ?> · <?= number_format($reg['price'], 0) ?> บาท/ภาคเรียน</div>
            </div>
            <div class="col-auto text-end">
              <div class="text-muted small">ค้างชำระ</div>
              <div class="fs-4 fw-black <?= $balance > 0 ? 'text-danger' : 'text-success' ?>"><?= number_format($balance, 0) ?> ฿</div>
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
          <h6 class="fw-black mb-0"><i class="fas fa-plus-circle me-2 text-success"></i>บันทึกการชำระเงิน</h6>
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
                <button type="submit" class="btn btn-success fw-bold w-100"><i class="fas fa-save me-1"></i>บันทึก</button>
              </div>
            </div>
          </form>
        </div>
      </div>
      <?php elseif ($balance <= 0.01): ?>
      <div class="alert alert-success border-0"><i class="fas fa-check-circle me-2"></i>ชำระเงินครบถ้วนแล้ว</div>
      <?php endif; ?>

      <!-- Payment History -->
      <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-0">
          <h6 class="fw-black mb-0"><i class="fas fa-history me-2 text-primary"></i>ประวัติการชำระเงิน (<?= count($payments) ?> รายการ)</h6>
        </div>
        <div class="card-body p-0">
          <?php if (empty($payments)): ?>
          <p class="text-center text-muted py-4">ยังไม่มีรายการชำระเงิน</p>
          <?php else: ?>
          <div class="table-responsive">
            <table class="table align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th class="text-xs fw-bold text-uppercase text-muted ps-4">#</th>
                  <th class="text-xs fw-bold text-uppercase text-muted">วันที่-เวลา</th>
                  <th class="text-xs fw-bold text-uppercase text-muted text-end">จำนวน</th>
                  <th class="text-xs fw-bold text-uppercase text-muted">หมายเหตุ</th>
                  <th class="text-xs fw-bold text-uppercase text-muted pe-4">บันทึกโดย</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($payments as $i => $p): ?>
                <tr>
                  <td class="ps-4 text-muted small"><?= $i + 1 ?></td>
                  <td class="small"><?= date('d/m/Y H:i', strtotime($p['paid_at'])) ?></td>
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
          <i class="fas fa-search fa-3x mb-3 opacity-25"></i>
          <p class="fw-bold">ค้นหานักเรียนด้านซ้ายเพื่อดูข้อมูลการชำระเงิน</p>
        </div>
      </div>
      <?php endif; ?>
    </div>

  </div>
</div>

<?php require_once __DIR__ . '/../../components/layout_end.php'; ?>
