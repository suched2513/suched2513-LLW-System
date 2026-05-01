<?php
session_start();
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../config.php';
busRequireStaff(['bus_admin', 'bus_finance', 'super_admin', 'wfh_admin', 'att_teacher']);

$pdo      = getPdo();
$semester = busGetSemester();
$semLabel = busSemesterLabel($semester);

try {
    $stats = [];

    // Total registered (active + pending_cancel)
    $s = $pdo->prepare("SELECT COUNT(*) FROM bus_registrations WHERE semester = ? AND status IN ('active','pending_cancel')");
    $s->execute([$semester]);
    $stats['registered'] = (int)$s->fetchColumn();

    // Total students in system
    $stats['total_students'] = (int)$pdo->query("SELECT COUNT(*) FROM bus_students WHERE is_active = 1")->fetchColumn();

    // Pending cancel requests
    $s = $pdo->prepare("SELECT COUNT(*) FROM bus_cancel_requests cr JOIN bus_registrations r ON r.id = cr.registration_id WHERE r.semester = ? AND cr.status = 'pending'");
    $s->execute([$semester]);
    $stats['pending_cancel'] = (int)$s->fetchColumn();

    // Total collected this semester
    $s = $pdo->prepare("SELECT COALESCE(SUM(p.amount),0) FROM bus_payments p JOIN bus_registrations r ON r.id = p.registration_id WHERE r.semester = ?");
    $s->execute([$semester]);
    $stats['collected'] = (float)$s->fetchColumn();

    // Total expected revenue (route price × registered)
    $s = $pdo->prepare("SELECT COALESCE(SUM(rt.price),0) FROM bus_registrations reg JOIN bus_routes rt ON rt.id = reg.route_id WHERE reg.semester = ? AND reg.status IN ('active','pending_cancel')");
    $s->execute([$semester]);
    $stats['expected'] = (float)$s->fetchColumn();

    // Routes with seat usage
    $routes = $pdo->prepare("
        SELECT r.id, r.route_code, r.route_name, r.seats, r.price, r.driver_name,
               COUNT(CASE WHEN reg.status IN ('active','pending_cancel') AND reg.semester = ? THEN 1 END) as taken
        FROM bus_routes r
        LEFT JOIN bus_registrations reg ON reg.route_id = r.id
        WHERE r.is_active = 1
        GROUP BY r.id
        ORDER BY r.route_code
    ");
    $routes->execute([$semester]);
    $routeList = $routes->fetchAll(PDO::FETCH_ASSOC);

    // Recent payments (last 10)
    $recent = $pdo->prepare("
        SELECT p.amount, p.paid_at, p.note, s.fullname, s.classroom, rt.route_name,
               u.firstname AS staff_name
        FROM bus_payments p
        JOIN bus_registrations reg ON reg.id = p.registration_id
        JOIN bus_students s ON s.id = reg.student_id
        JOIN bus_routes rt ON rt.id = reg.route_id
        LEFT JOIN llw_users u ON u.user_id = p.recorded_by
        WHERE reg.semester = ?
        ORDER BY p.paid_at DESC
        LIMIT 10
    ");
    $recent->execute([$semester]);
    $recentPayments = $recent->fetchAll(PDO::FETCH_ASSOC);

    // Recent cancel requests (pending)
    $cancels = $pdo->prepare("
        SELECT cr.id, cr.reason, cr.created_at, s.fullname, s.classroom, rt.route_name
        FROM bus_cancel_requests cr
        JOIN bus_registrations reg ON reg.id = cr.registration_id
        JOIN bus_students s ON s.id = reg.student_id
        JOIN bus_routes rt ON rt.id = reg.route_id
        WHERE reg.semester = ? AND cr.status = 'pending'
        ORDER BY cr.created_at DESC
        LIMIT 5
    ");
    $cancels->execute([$semester]);
    $pendingCancels = $cancels->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    error_log($e->getMessage());
    $stats = ['registered' => 0, 'total_students' => 0, 'pending_cancel' => 0, 'collected' => 0, 'expected' => 0];
    $routeList = [];
    $recentPayments = [];
    $pendingCancels = [];
}

$pageTitle    = 'ภาพรวมระบบ';
$pageSubtitle = $semLabel;
$activeSystem = 'bus';
require_once __DIR__ . '/../../components/layout_start.php';
?>

<div class="container-fluid">

  <!-- KPI Row -->
  <div class="row g-4 mb-4">
    <div class="col-6 col-lg-3">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body">
          <div class="d-flex align-items-center gap-3">
            <div class="bg-primary bg-opacity-10 rounded-3 p-3">
              <i class="fas fa-user-check text-primary fa-lg"></i>
            </div>
            <div>
              <div class="text-muted small fw-bold text-uppercase">ลงทะเบียนแล้ว</div>
              <div class="fs-3 fw-black"><?= number_format($stats['registered']) ?></div>
              <div class="text-muted small">จาก <?= number_format($stats['total_students']) ?> คน</div>
            </div>
          </div>
        </div>
      </div>
    </div>
    <div class="col-6 col-lg-3">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body">
          <div class="d-flex align-items-center gap-3">
            <div class="bg-success bg-opacity-10 rounded-3 p-3">
              <i class="fas fa-money-bill-wave text-success fa-lg"></i>
            </div>
            <div>
              <div class="text-muted small fw-bold text-uppercase">ชำระแล้ว</div>
              <div class="fs-3 fw-black text-success"><?= number_format($stats['collected'], 0) ?></div>
              <div class="text-muted small">บาท</div>
            </div>
          </div>
        </div>
      </div>
    </div>
    <div class="col-6 col-lg-3">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body">
          <div class="d-flex align-items-center gap-3">
            <div class="bg-warning bg-opacity-10 rounded-3 p-3">
              <i class="fas fa-clock text-warning fa-lg"></i>
            </div>
            <div>
              <div class="text-muted small fw-bold text-uppercase">ค้างชำระ</div>
              <div class="fs-3 fw-black text-warning"><?= number_format($stats['expected'] - $stats['collected'], 0) ?></div>
              <div class="text-muted small">บาท</div>
            </div>
          </div>
        </div>
      </div>
    </div>
    <div class="col-6 col-lg-3">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body">
          <div class="d-flex align-items-center gap-3">
            <div class="bg-danger bg-opacity-10 rounded-3 p-3">
              <i class="fas fa-times-circle text-danger fa-lg"></i>
            </div>
            <div>
              <div class="text-muted small fw-bold text-uppercase">รอยกเลิก</div>
              <div class="fs-3 fw-black text-danger"><?= number_format($stats['pending_cancel']) ?></div>
              <a href="/bus/finance/cancellations.php" class="small text-danger">ดูทั้งหมด →</a>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="row g-4">

    <!-- Route Utilization -->
    <div class="col-12 col-xl-6">
      <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
          <h6 class="fw-black mb-0"><i class="fas fa-bus me-2 text-primary"></i>สถานะสายรถ — <?= htmlspecialchars($semLabel) ?></h6>
          <?php if (busCanAdmin()): ?>
          <a href="/bus/admin/routes.php" class="btn btn-sm btn-outline-primary">จัดการสาย</a>
          <?php endif; ?>
        </div>
        <div class="card-body p-0">
          <?php if (empty($routeList)): ?>
          <p class="text-center text-muted py-4">ยังไม่มีสายรถ</p>
          <?php else: ?>
          <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th class="text-xs fw-bold text-uppercase text-muted ps-4">สาย</th>
                  <th class="text-xs fw-bold text-uppercase text-muted text-center">ที่นั่ง</th>
                  <th class="text-xs fw-bold text-uppercase text-muted text-end pe-4">สถานะ</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($routeList as $rt):
                  $taken = (int)$rt['taken'];
                  $seats = (int)$rt['seats'];
                  $pct   = $seats > 0 ? min(100, round($taken / $seats * 100)) : 0;
                  $cls   = $pct >= 100 ? 'danger' : ($pct >= 80 ? 'warning' : 'success');
                ?>
                <tr>
                  <td class="ps-4">
                    <div class="fw-bold"><?= htmlspecialchars($rt['route_name']) ?></div>
                    <div class="small text-muted">สาย <?= htmlspecialchars($rt['route_code']) ?><?= $rt['driver_name'] ? ' · ' . htmlspecialchars($rt['driver_name']) : '' ?></div>
                  </td>
                  <td class="text-center">
                    <span class="badge bg-<?= $cls ?> bg-opacity-10 text-<?= $cls ?> fw-bold">
                      <?= $taken ?>/<?= $seats > 0 ? $seats : '∞' ?>
                    </span>
                  </td>
                  <td class="pe-4">
                    <div class="progress" style="height:6px;width:80px;margin-left:auto">
                      <div class="progress-bar bg-<?= $cls ?>" style="width:<?= $pct ?>%"></div>
                    </div>
                    <div class="small text-end text-muted mt-1"><?= $pct ?>%</div>
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

    <!-- Pending Cancels -->
    <div class="col-12 col-xl-6">
      <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
          <h6 class="fw-black mb-0"><i class="fas fa-times-circle me-2 text-danger"></i>คำขอยกเลิกที่รอดำเนินการ</h6>
          <a href="/bus/finance/cancellations.php" class="btn btn-sm btn-outline-danger">ดูทั้งหมด</a>
        </div>
        <div class="card-body p-0">
          <?php if (empty($pendingCancels)): ?>
          <p class="text-center text-muted py-4"><i class="fas fa-check-circle text-success me-1"></i> ไม่มีคำขอที่รอดำเนินการ</p>
          <?php else: ?>
          <div class="list-group list-group-flush">
            <?php foreach ($pendingCancels as $c): ?>
            <div class="list-group-item border-0 px-4">
              <div class="d-flex justify-content-between align-items-start">
                <div>
                  <div class="fw-bold"><?= htmlspecialchars($c['fullname']) ?></div>
                  <div class="small text-muted"><?= htmlspecialchars($c['classroom']) ?> · <?= htmlspecialchars($c['route_name']) ?></div>
                  <div class="small text-muted mt-1"><?= htmlspecialchars(mb_strimwidth($c['reason'], 0, 50, '...')) ?></div>
                </div>
                <span class="badge bg-danger-subtle text-danger fw-bold"><?= date('d/m', strtotime($c['created_at'])) ?></span>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Recent Payments -->
    <div class="col-12">
      <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
          <h6 class="fw-black mb-0"><i class="fas fa-receipt me-2 text-success"></i>การชำระเงินล่าสุด</h6>
          <a href="/bus/finance/payments.php" class="btn btn-sm btn-outline-success">บันทึกการเงิน</a>
        </div>
        <div class="card-body p-0">
          <?php if (empty($recentPayments)): ?>
          <p class="text-center text-muted py-4">ยังไม่มีรายการชำระเงิน</p>
          <?php else: ?>
          <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th class="text-xs fw-bold text-uppercase text-muted ps-4">นักเรียน</th>
                  <th class="text-xs fw-bold text-uppercase text-muted">สาย</th>
                  <th class="text-xs fw-bold text-uppercase text-muted text-end">จำนวน</th>
                  <th class="text-xs fw-bold text-uppercase text-muted text-end pe-4">วันที่</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($recentPayments as $p): ?>
                <tr>
                  <td class="ps-4">
                    <div class="fw-bold"><?= htmlspecialchars($p['fullname']) ?></div>
                    <div class="small text-muted"><?= htmlspecialchars($p['classroom']) ?></div>
                  </td>
                  <td class="small text-muted"><?= htmlspecialchars($p['route_name']) ?></td>
                  <td class="text-end fw-bold text-success"><?= number_format($p['amount'], 0) ?></td>
                  <td class="text-end text-muted small pe-4"><?= date('d/m/Y H:i', strtotime($p['paid_at'])) ?></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <?php endif; ?>
        </div>
      </div>
    </div>

  </div>
</div>

<?php require_once __DIR__ . '/../../components/layout_end.php'; ?>
