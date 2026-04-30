<?php
session_start();
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../config.php';
busRequireStaff();

$pdo      = getPdo();
$semester = busGetSemester();

$filterSem   = $_GET['semester'] ?? $semester;
$filterRoute = (int)($_GET['route_id'] ?? 0);
$filterClass = trim($_GET['classroom'] ?? '');
$filterStatus = $_GET['status'] ?? '';

try {
    // All semesters for filter
    $semesters = $pdo->query("SELECT DISTINCT semester FROM bus_registrations ORDER BY semester DESC")->fetchAll(PDO::FETCH_COLUMN);

    // All routes
    $allRoutes = $pdo->query("SELECT id, route_code, route_name FROM bus_routes ORDER BY route_code")->fetchAll(PDO::FETCH_ASSOC);

    // Build query
    $conditions = ['reg.semester = ?'];
    $params     = [$filterSem];

    if ($filterRoute > 0) {
        $conditions[] = 'reg.route_id = ?';
        $params[]     = $filterRoute;
    }
    if ($filterClass !== '') {
        $conditions[] = 's.classroom LIKE ?';
        $params[]     = "%$filterClass%";
    }
    if (in_array($filterStatus, ['active','pending_cancel','cancelled'])) {
        $conditions[] = 'reg.status = ?';
        $params[]     = $filterStatus;
    }

    $where = 'WHERE ' . implode(' AND ', $conditions);

    $regStmt = $pdo->prepare("
        SELECT reg.id, reg.status, reg.registered_at,
               s.student_id, s.fullname, s.classroom,
               rt.route_code, rt.route_name, rt.price,
               COALESCE(SUM(p.amount),0) as paid
        FROM bus_registrations reg
        JOIN bus_students s ON s.id = reg.student_id
        JOIN bus_routes rt ON rt.id = reg.route_id
        LEFT JOIN bus_payments p ON p.registration_id = reg.id
        $where
        GROUP BY reg.id
        ORDER BY s.classroom, s.student_id
    ");
    $regStmt->execute($params);
    $registrations = $regStmt->fetchAll(PDO::FETCH_ASSOC);

    // Summary stats
    $totalReg    = count($registrations);
    $totalPrice  = array_sum(array_column($registrations, 'price'));
    $totalPaid   = array_sum(array_column($registrations, 'paid'));
    $totalUnpaid = $totalPrice - $totalPaid;

} catch (Exception $e) {
    error_log($e->getMessage());
    $registrations = [];
    $semesters     = [$semester];
    $allRoutes     = [];
    $totalReg = $totalPrice = $totalPaid = $totalUnpaid = 0;
}

$semLabel     = busSemesterLabel($filterSem);
$pageTitle    = 'รายงาน';
$pageSubtitle = 'รายงานการลงทะเบียนและการชำระเงิน';
$activeSystem = 'bus';
require_once __DIR__ . '/../../components/layout_start.php';
?>

<div class="container-fluid">

  <!-- Filters -->
  <div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
      <form method="GET" class="row g-3 align-items-end">
        <div class="col-12 col-md-3">
          <label class="form-label fw-bold small">ภาคเรียน</label>
          <select name="semester" class="form-select">
            <?php foreach ($semesters as $sem): ?>
            <option value="<?= htmlspecialchars($sem) ?>" <?= $sem === $filterSem ? 'selected' : '' ?>><?= htmlspecialchars(busSemesterLabel($sem)) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-12 col-md-3">
          <label class="form-label fw-bold small">สายรถ</label>
          <select name="route_id" class="form-select">
            <option value="0">ทุกสาย</option>
            <?php foreach ($allRoutes as $r): ?>
            <option value="<?= (int)$r['id'] ?>" <?= (int)$r['id'] === $filterRoute ? 'selected' : '' ?>><?= htmlspecialchars($r['route_code'] . ' — ' . $r['route_name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-12 col-md-2">
          <label class="form-label fw-bold small">ห้องเรียน</label>
          <input type="text" name="classroom" value="<?= htmlspecialchars($filterClass) ?>" class="form-control" placeholder="เช่น ม.3">
        </div>
        <div class="col-12 col-md-2">
          <label class="form-label fw-bold small">สถานะ</label>
          <select name="status" class="form-select">
            <option value="">ทุกสถานะ</option>
            <option value="active" <?= $filterStatus === 'active' ? 'selected' : '' ?>>ใช้งาน</option>
            <option value="pending_cancel" <?= $filterStatus === 'pending_cancel' ? 'selected' : '' ?>>รอยกเลิก</option>
            <option value="cancelled" <?= $filterStatus === 'cancelled' ? 'selected' : '' ?>>ยกเลิกแล้ว</option>
          </select>
        </div>
        <div class="col-12 col-md-2">
          <button type="submit" class="btn btn-primary fw-bold w-100"><i class="fas fa-search me-1"></i>กรอง</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Summary KPIs -->
  <div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
      <div class="card border-0 shadow-sm text-center py-3">
        <div class="fs-2 fw-black"><?= number_format($totalReg) ?></div>
        <div class="small text-muted fw-bold">รายการทั้งหมด</div>
      </div>
    </div>
    <div class="col-6 col-lg-3">
      <div class="card border-0 shadow-sm text-center py-3">
        <div class="fs-2 fw-black"><?= number_format($totalPrice, 0) ?></div>
        <div class="small text-muted fw-bold">รายรับรวม (บาท)</div>
      </div>
    </div>
    <div class="col-6 col-lg-3">
      <div class="card border-0 shadow-sm text-center py-3">
        <div class="fs-2 fw-black text-success"><?= number_format($totalPaid, 0) ?></div>
        <div class="small text-muted fw-bold">ชำระแล้ว (บาท)</div>
      </div>
    </div>
    <div class="col-6 col-lg-3">
      <div class="card border-0 shadow-sm text-center py-3">
        <div class="fs-2 fw-black text-danger"><?= number_format($totalUnpaid, 0) ?></div>
        <div class="small text-muted fw-bold">ค้างชำระ (บาท)</div>
      </div>
    </div>
  </div>

  <!-- Report Table -->
  <div class="card border-0 shadow-sm">
    <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
      <h6 class="fw-black mb-0"><i class="fas fa-table me-2 text-primary"></i>รายละเอียด (<?= number_format($totalReg) ?> รายการ)</h6>
      <button onclick="window.print()" class="btn btn-sm btn-outline-secondary"><i class="fas fa-print me-1"></i>พิมพ์</button>
    </div>
    <div class="card-body p-0">
      <?php if (empty($registrations)): ?>
      <p class="text-center text-muted py-5">ไม่พบข้อมูล</p>
      <?php else: ?>
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" id="reportTable">
          <thead class="table-light">
            <tr>
              <th class="text-xs fw-bold text-uppercase text-muted ps-4">#</th>
              <th class="text-xs fw-bold text-uppercase text-muted">นักเรียน</th>
              <th class="text-xs fw-bold text-uppercase text-muted">ห้อง</th>
              <th class="text-xs fw-bold text-uppercase text-muted">สายรถ</th>
              <th class="text-xs fw-bold text-uppercase text-muted text-center">สถานะ</th>
              <th class="text-xs fw-bold text-uppercase text-muted text-end">ราคา</th>
              <th class="text-xs fw-bold text-uppercase text-muted text-end">ชำระ</th>
              <th class="text-xs fw-bold text-uppercase text-muted text-end pe-4">ค้าง</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($registrations as $i => $r):
              $balance = (float)$r['price'] - (float)$r['paid'];
              $statusClass = ['active'=>'success','pending_cancel'=>'warning','cancelled'=>'secondary'][$r['status']] ?? 'secondary';
              $statusLabel = ['active'=>'ใช้งาน','pending_cancel'=>'รอยกเลิก','cancelled'=>'ยกเลิก'][$r['status']] ?? $r['status'];
            ?>
            <tr>
              <td class="ps-4 text-muted small"><?= $i + 1 ?></td>
              <td>
                <div class="fw-bold"><?= htmlspecialchars($r['fullname']) ?></div>
                <div class="small text-muted"><?= htmlspecialchars($r['student_id']) ?></div>
              </td>
              <td class="small"><?= htmlspecialchars($r['classroom']) ?></td>
              <td class="small"><?= htmlspecialchars($r['route_code']) ?> <?= htmlspecialchars($r['route_name']) ?></td>
              <td class="text-center"><span class="badge bg-<?= $statusClass ?> bg-opacity-10 text-<?= $statusClass ?> fw-bold"><?= $statusLabel ?></span></td>
              <td class="text-end fw-bold"><?= number_format($r['price'], 0) ?></td>
              <td class="text-end text-success fw-bold"><?= number_format($r['paid'], 0) ?></td>
              <td class="text-end pe-4 <?= $balance > 0 ? 'text-danger fw-bold' : 'text-muted' ?>"><?= number_format($balance, 0) ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
          <tfoot class="table-light fw-bold">
            <tr>
              <td colspan="5" class="ps-4">รวม</td>
              <td class="text-end"><?= number_format($totalPrice, 0) ?></td>
              <td class="text-end text-success"><?= number_format($totalPaid, 0) ?></td>
              <td class="text-end pe-4 text-danger"><?= number_format($totalUnpaid, 0) ?></td>
            </tr>
          </tfoot>
        </table>
      </div>
      <?php endif; ?>
    </div>
  </div>

</div>

<style>
@media print {
  .app-sidebar, .app-header, .breadcrumb-header, .btn { display: none !important; }
  .content-wrapper { margin: 0 !important; }
}
</style>

<?php require_once __DIR__ . '/../../components/layout_end.php'; ?>
