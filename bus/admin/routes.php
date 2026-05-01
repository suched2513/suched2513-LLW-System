<?php
session_start();
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../config.php';
busRequireStaff(['bus_admin', 'super_admin', 'wfh_admin']);

$pdo      = getPdo();
$semester = busGetSemester();
$msg = '';
$err = '';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'add' || $action === 'edit') {
            $routeCode  = trim($_POST['route_code'] ?? '');
            $routeName  = trim($_POST['route_name'] ?? '');
            $price      = (float)($_POST['price'] ?? 0);
            $seats      = (int)($_POST['seats'] ?? 0);
            $driverName = trim($_POST['driver_name'] ?? '');
            $desc       = trim($_POST['description'] ?? '');
            $isActive   = isset($_POST['is_active']) ? 1 : 0;

            if ($routeCode === '' || $routeName === '' || $price <= 0) {
                $err = 'กรุณากรอกรหัสสาย ชื่อสาย และราคาให้ครบ';
            } else {
                if ($action === 'add') {
                    $stmt = $pdo->prepare("INSERT INTO bus_routes (route_code, route_name, price, seats, driver_name, description, is_active) VALUES (?,?,?,?,?,?,?)");
                    $stmt->execute([$routeCode, $routeName, $price, $seats, $driverName, $desc, $isActive]);
                    $msg = 'เพิ่มสายรถเรียบร้อยแล้ว';
                } else {
                    $id = (int)($_POST['route_id'] ?? 0);
                    $stmt = $pdo->prepare("UPDATE bus_routes SET route_code=?, route_name=?, price=?, seats=?, driver_name=?, description=?, is_active=? WHERE id=?");
                    $stmt->execute([$routeCode, $routeName, $price, $seats, $driverName, $desc, $isActive, $id]);
                    $msg = 'แก้ไขสายรถเรียบร้อยแล้ว';
                }
            }
        } elseif ($action === 'delete') {
            $id = (int)($_POST['route_id'] ?? 0);
            // Check no active registrations
            $check = $pdo->prepare("SELECT COUNT(*) FROM bus_registrations WHERE route_id = ? AND status IN ('active','pending_cancel')");
            $check->execute([$id]);
            if ((int)$check->fetchColumn() > 0) {
                $err = 'ไม่สามารถลบสายที่มีนักเรียนลงทะเบียนอยู่ได้';
            } else {
                $pdo->prepare("DELETE FROM bus_routes WHERE id=?")->execute([$id]);
                $msg = 'ลบสายรถเรียบร้อยแล้ว';
            }
        } elseif ($action === 'toggle') {
            $id = (int)($_POST['route_id'] ?? 0);
            $pdo->prepare("UPDATE bus_routes SET is_active = NOT is_active WHERE id=?")->execute([$id]);
            $msg = 'อัพเดทสถานะเรียบร้อยแล้ว';
        }
    } catch (Exception $e) {
        error_log($e->getMessage());
        $err = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
    }
}

// Check tables exist
$tablesOk = true;
try {
    $pdo->query("SELECT 1 FROM bus_routes LIMIT 1");
} catch (Exception $e) {
    $tablesOk = false;
}

// Fetch routes with registration counts
try {
    $stmt = $pdo->prepare("
        SELECT r.*,
               COUNT(CASE WHEN reg.status IN ('active','pending_cancel') AND reg.semester = ? THEN 1 END) as taken_now,
               COUNT(reg.id) as total_regs
        FROM bus_routes r
        LEFT JOIN bus_registrations reg ON reg.route_id = r.id
        GROUP BY r.id
        ORDER BY r.is_active DESC, r.route_code
    ");
    $stmt->execute([$semester]);
    $routes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log($e->getMessage());
    $routes = [];
}

$pageTitle    = 'จัดการสายรถ';
$pageSubtitle = 'เพิ่ม/แก้ไข/ลบสายรถรับส่งนักเรียน';
$activeSystem = 'bus';
require_once __DIR__ . '/../../components/layout_start.php';
?>

<?php if (!$tablesOk): ?>
<div class="alert alert-warning border-0">
  <i class="fas fa-database me-2"></i>
  <strong>ตารางฐานข้อมูลยังไม่ถูกสร้าง</strong> —
  <a href="/bus/admin/migrate.php" class="alert-link">คลิกที่นี่เพื่อสร้างตาราง Bus System</a>
</div>
<?php endif; ?>
<?php if ($msg): ?>
<div class="alert alert-success alert-dismissible fade show"><i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($msg) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>
<?php if ($err): ?>
<div class="alert alert-danger alert-dismissible fade show"><i class="fas fa-exclamation-triangle me-2"></i><?= htmlspecialchars($err) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>

<div class="container-fluid">
  <div class="row g-4">

    <!-- Add Route Form -->
    <div class="col-12 col-xl-4">
      <div class="card border-0 shadow-sm" id="routeFormCard">
        <div class="card-header bg-white border-0">
          <h6 class="fw-black mb-0" id="formTitle"><i class="fas fa-plus me-2 text-primary"></i>เพิ่มสายรถใหม่</h6>
        </div>
        <div class="card-body">
          <form method="POST" id="routeForm">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add" id="formAction">
            <input type="hidden" name="route_id" value="" id="formRouteId">

            <div class="mb-3">
              <label class="form-label fw-bold small">รหัสสาย <span class="text-danger">*</span></label>
              <input type="text" name="route_code" id="fCode" class="form-control" placeholder="เช่น A1, B2" required maxlength="20">
            </div>
            <div class="mb-3">
              <label class="form-label fw-bold small">ชื่อสาย <span class="text-danger">*</span></label>
              <input type="text" name="route_name" id="fName" class="form-control" placeholder="เช่น สายบ้านโนนดอกไม้" required maxlength="200">
            </div>
            <div class="row g-3 mb-3">
              <div class="col-6">
                <label class="form-label fw-bold small">ราคา (บาท/ภาคเรียน) <span class="text-danger">*</span></label>
                <input type="number" name="price" id="fPrice" class="form-control" min="0" step="50" required>
              </div>
              <div class="col-6">
                <label class="form-label fw-bold small">จำนวนที่นั่ง (0=ไม่จำกัด)</label>
                <input type="number" name="seats" id="fSeats" class="form-control" min="0" value="0">
              </div>
            </div>
            <div class="mb-3">
              <label class="form-label fw-bold small">ชื่อคนขับ</label>
              <input type="text" name="driver_name" id="fDriver" class="form-control" maxlength="200">
            </div>
            <div class="mb-3">
              <label class="form-label fw-bold small">รายละเอียด/เส้นทาง</label>
              <textarea name="description" id="fDesc" class="form-control" rows="3" maxlength="500"></textarea>
            </div>
            <div class="mb-3 form-check">
              <input type="checkbox" name="is_active" class="form-check-input" id="fActive" checked>
              <label class="form-check-label fw-bold small" for="fActive">เปิดให้ลงทะเบียน</label>
            </div>
            <div class="d-flex gap-2">
              <button type="submit" class="btn btn-primary fw-bold flex-fill"><i class="fas fa-save me-1"></i><span id="btnLabel">เพิ่มสาย</span></button>
              <button type="button" class="btn btn-light fw-bold" onclick="resetForm()">ยกเลิก</button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- Route List -->
    <div class="col-12 col-xl-8">
      <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-0">
          <h6 class="fw-black mb-0"><i class="fas fa-list me-2 text-primary"></i>รายการสายรถทั้งหมด (<?= count($routes) ?> สาย)</h6>
        </div>
        <div class="card-body p-0">
          <?php if (empty($routes)): ?>
          <p class="text-center text-muted py-5">ยังไม่มีสายรถ</p>
          <?php else: ?>
          <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th class="text-xs fw-bold text-uppercase text-muted ps-4">สาย</th>
                  <th class="text-xs fw-bold text-uppercase text-muted text-center">ราคา</th>
                  <th class="text-xs fw-bold text-uppercase text-muted text-center">ที่นั่ง</th>
                  <th class="text-xs fw-bold text-uppercase text-muted text-center">สถานะ</th>
                  <th class="text-xs fw-bold text-uppercase text-muted text-end pe-4">จัดการ</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($routes as $rt): ?>
                <tr>
                  <td class="ps-4">
                    <div class="fw-bold"><?= htmlspecialchars($rt['route_name']) ?></div>
                    <div class="small text-muted">สาย <?= htmlspecialchars($rt['route_code']) ?><?= $rt['driver_name'] ? ' · ' . htmlspecialchars($rt['driver_name']) : '' ?></div>
                  </td>
                  <td class="text-center fw-bold"><?= number_format($rt['price'], 0) ?> ฿</td>
                  <td class="text-center">
                    <span class="badge bg-secondary bg-opacity-10 text-secondary">
                      <?= (int)$rt['taken_now'] ?>/<?= (int)$rt['seats'] > 0 ? (int)$rt['seats'] : '∞' ?>
                    </span>
                  </td>
                  <td class="text-center">
                    <form method="POST" class="d-inline">
                      <?= csrf_field() ?>
                      <input type="hidden" name="action" value="toggle">
                      <input type="hidden" name="route_id" value="<?= (int)$rt['id'] ?>">
                      <button type="submit" class="badge border-0 bg-<?= $rt['is_active'] ? 'success' : 'secondary' ?> bg-opacity-10 text-<?= $rt['is_active'] ? 'success' : 'secondary' ?> fw-bold px-3 py-2 cursor-pointer">
                        <?= $rt['is_active'] ? 'เปิดอยู่' : 'ปิดอยู่' ?>
                      </button>
                    </form>
                  </td>
                  <td class="text-end pe-4">
                    <button type="button"
                      onclick="editRoute(<?= htmlspecialchars(json_encode($rt), ENT_QUOTES) ?>)"
                      class="btn btn-sm btn-outline-primary me-1"><i class="fas fa-edit"></i></button>
                    <?php if ((int)$rt['taken_now'] === 0): ?>
                    <form method="POST" class="d-inline" onsubmit="return confirm('ลบสายนี้?')">
                      <?= csrf_field() ?>
                      <input type="hidden" name="action" value="delete">
                      <input type="hidden" name="route_id" value="<?= (int)$rt['id'] ?>">
                      <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                    </form>
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
  </div>
</div>

<script>
function editRoute(rt) {
    document.getElementById('formTitle').innerHTML = '<i class="fas fa-edit me-2 text-warning"></i>แก้ไขสายรถ';
    document.getElementById('btnLabel').textContent = 'บันทึกการแก้ไข';
    document.getElementById('formAction').value = 'edit';
    document.getElementById('formRouteId').value = rt.id;
    document.getElementById('fCode').value = rt.route_code;
    document.getElementById('fName').value = rt.route_name;
    document.getElementById('fPrice').value = rt.price;
    document.getElementById('fSeats').value = rt.seats;
    document.getElementById('fDriver').value = rt.driver_name ?? '';
    document.getElementById('fDesc').value = rt.description ?? '';
    document.getElementById('fActive').checked = rt.is_active == 1;
    document.getElementById('routeFormCard').scrollIntoView({behavior:'smooth'});
}
function resetForm() {
    document.getElementById('formTitle').innerHTML = '<i class="fas fa-plus me-2 text-primary"></i>เพิ่มสายรถใหม่';
    document.getElementById('btnLabel').textContent = 'เพิ่มสาย';
    document.getElementById('formAction').value = 'add';
    document.getElementById('formRouteId').value = '';
    document.getElementById('routeForm').reset();
    document.getElementById('fActive').checked = true;
}
</script>

<?php require_once __DIR__ . '/../../components/layout_end.php'; ?>
