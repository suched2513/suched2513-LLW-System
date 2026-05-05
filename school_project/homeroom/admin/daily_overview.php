<?php
session_start();
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../includes/layout.php';
requireRole(['admin', 'super_admin']);

$u  = getCurrentUser();
$db = getDB();

$selectedDate = $_GET['date'] ?? date('Y-m-d');

// ดึงห้องทั้งหมดที่มีครูที่ปรึกษา
// ดึงห้องทั้งหมดจาก att_students LEFT JOIN ครูที่ปรึกษา (ถ้ามี)
$classrooms = [];
try {
    $stmt = $db->prepare("
        SELECT
            s.classroom,
            COALESCE(CONCAT(u.firstname,' ',u.lastname), '— ยังไม่ผูกครู —') AS teacher_name,
            u.user_id AS teacher_id
        FROM (SELECT DISTINCT classroom FROM att_students WHERE classroom != '') s
        LEFT JOIN llw_class_advisors ca ON ca.classroom = s.classroom AND ca.role_type = 'primary'
        LEFT JOIN llw_users u ON u.user_id = ca.user_id
        ORDER BY s.classroom
    ");
    $stmt->execute();
    $classrooms = $stmt->fetchAll();
} catch (Exception $e) { $classrooms = []; }

// ดึง daily log วันที่เลือก
$logsByClass = [];
try {
    $stmt = $db->prepare("SELECT * FROM hr_daily_logs WHERE log_date = ?");
    $stmt->execute([$selectedDate]);
    foreach ($stmt->fetchAll() as $row) {
        $logsByClass[$row['classroom']] = $row;
    }
} catch (Exception $e) { $logsByClass = []; }

// ตรวจว่า att_attendance มีข้อมูลเข้าแถวของแต่ละห้องหรือยัง (period 1)
$attDoneByClass = [];
try {
    // ดึงห้องที่มีการเช็คชื่อ period 1 วันนี้
    $stmt = $db->prepare("
        SELECT s.classroom, COUNT(DISTINCT a.student_id) AS checked_count
        FROM att_attendance a
        JOIN att_students s ON s.student_id = a.student_id
        WHERE a.date = ? AND a.period = 1
        GROUP BY s.classroom
    ");
    $stmt->execute([$selectedDate]);
    foreach ($stmt->fetchAll() as $row) {
        $attDoneByClass[$row['classroom']] = (int)$row['checked_count'];
    }
} catch (Exception $e) { $attDoneByClass = []; }

// สรุป KPI
$total        = count($classrooms);
$submitted    = 0;
$noLog        = 0;
$noAttRoll    = 0;
foreach ($classrooms as $cr) {
    $cls = $cr['classroom'];
    $log = $logsByClass[$cls] ?? null;
    if ($log && in_array($log['status'], ['submitted', 'approved'])) $submitted++;
    if (!$log) $noLog++;
    if (!isset($attDoneByClass[$cls])) $noAttRoll++;
}

$statusLabels = ['draft' => 'ร่าง', 'submitted' => 'รออนุมัติ', 'approved' => 'อนุมัติแล้ว', 'revision' => 'ส่งคืน'];
$statusColors = ['draft' => 'secondary', 'submitted' => 'warning', 'approved' => 'success', 'revision' => 'danger'];

hrRenderHead('ภาพรวมโฮมรูมประจำวัน');
echo '<div class="d-flex">'; hrRenderSidebar(); echo '<div class="main-content flex-grow-1">'; hrRenderTopbar('ภาพรวมโฮมรูมประจำวัน'); echo '<div class="page-content">'; hrShowFlash();
?>

<!-- Date picker -->
<div class="card mb-3">
  <div class="card-body py-2">
    <form method="GET" class="d-flex align-items-center gap-3">
      <label class="fw-semibold small mb-0">วันที่:</label>
      <input type="date" name="date" class="form-control form-control-sm" style="width:170px"
             value="<?= h($selectedDate) ?>" max="<?= date('Y-m-d') ?>" onchange="this.form.submit()">
      <span class="text-muted small"><?= date('l', strtotime($selectedDate)) ?></span>
    </form>
  </div>
</div>

<!-- KPI cards -->
<div class="row g-3 mb-3">
  <div class="col-6 col-md-3">
    <div class="card text-center py-3">
      <div class="text-primary mb-1"><i class="bi bi-building" style="font-size:1.5rem"></i></div>
      <div class="fw-bold fs-4 text-primary"><?= $total ?></div>
      <div class="small text-muted">ห้องทั้งหมด</div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card text-center py-3">
      <div class="text-success mb-1"><i class="bi bi-check-circle" style="font-size:1.5rem"></i></div>
      <div class="fw-bold fs-4 text-success"><?= $submitted ?></div>
      <div class="small text-muted">ส่งแล้ว</div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card text-center py-3 <?= $noLog > 0 ? 'border-danger border-2' : '' ?>">
      <div class="text-danger mb-1"><i class="bi bi-file-earmark-x" style="font-size:1.5rem"></i></div>
      <div class="fw-bold fs-4 text-danger"><?= $noLog ?></div>
      <div class="small text-muted">ยังไม่บันทึก</div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card text-center py-3 <?= $noAttRoll > 0 ? 'border-warning border-2' : '' ?>">
      <div class="text-warning mb-1"><i class="bi bi-exclamation-triangle" style="font-size:1.5rem"></i></div>
      <div class="fw-bold fs-4 text-warning"><?= $noAttRoll ?></div>
      <div class="small text-muted">ยังไม่เช็คเข้าแถว</div>
    </div>
  </div>
</div>

<?php if ($noLog > 0 && $selectedDate === date('Y-m-d')): ?>
<div class="alert alert-danger d-flex align-items-center gap-2 mb-3">
  <i class="bi bi-bell-fill fs-5"></i>
  <div>
    <strong>แจ้งเตือน:</strong> มี <?= $noLog ?> ห้องที่ยังไม่ได้บันทึกโฮมรูมประจำวันนี้
  </div>
</div>
<?php endif; ?>

<?php if ($noAttRoll > 0 && $selectedDate === date('Y-m-d')): ?>
<div class="alert alert-warning d-flex align-items-center gap-2 mb-3">
  <i class="bi bi-exclamation-triangle-fill fs-5"></i>
  <div>
    <strong>แจ้งเตือน:</strong> มี <?= $noAttRoll ?> ห้องที่ยังไม่มีข้อมูลเช็คชื่อเข้าแถว (คาบ 1) ในระบบเช็คชื่อวันนี้
  </div>
</div>
<?php endif; ?>

<!-- ตารางภาพรวม -->
<div class="card">
  <div class="card-header py-2">
    <span class="fw-semibold">สถานะรายห้อง — <?= date('d/m/Y', strtotime($selectedDate)) ?></span>
  </div>
  <div class="card-body p-0">
    <?php if (empty($classrooms)): ?>
    <div class="text-center py-5 text-muted">
      <i class="bi bi-building-x" style="font-size:2rem"></i>
      <p class="mt-2">ไม่พบข้อมูลห้องเรียน กรุณาตั้งค่า llw_class_advisors</p>
    </div>
    <?php else: ?>
    <table class="table table-hover mb-0" style="font-size:14px">
      <thead class="table-light">
        <tr>
          <th class="ps-3">ห้อง</th>
          <th>ครูที่ปรึกษา</th>
          <th class="text-center">เช็คเข้าแถว</th>
          <th class="text-center">บันทึกโฮมรูม</th>
          <th class="text-center">สถานะ</th>
          <th class="text-center">มา / ขาด / สาย</th>
          <th class="text-center">ดำเนินการ</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($classrooms as $cr):
        $cls     = $cr['classroom'];
        $log     = $logsByClass[$cls] ?? null;
        $attDone = isset($attDoneByClass[$cls]);
        $rowClass = '';
        if (!$log) $rowClass = 'table-danger';
        elseif ($log['status'] === 'draft') $rowClass = 'table-warning';
      ?>
      <tr class="<?= $rowClass ?>">
        <td class="ps-3 fw-semibold"><?= h($cls) ?></td>
        <td><?= h($cr['teacher_name']) ?></td>
        <td class="text-center">
          <?php if ($attDone): ?>
          <span class="badge bg-success"><i class="bi bi-check-lg me-1"></i>เช็คแล้ว (<?= $attDoneByClass[$cls] ?> คน)</span>
          <?php else: ?>
          <span class="badge bg-danger"><i class="bi bi-x-lg me-1"></i>ยังไม่เช็ค</span>
          <?php endif; ?>
        </td>
        <td class="text-center">
          <?php if ($log): ?>
          <span class="badge bg-<?= $statusColors[$log['status']] ?? 'secondary' ?>">
            <?= $statusLabels[$log['status']] ?? $log['status'] ?>
          </span>
          <?php else: ?>
          <span class="badge bg-danger"><i class="bi bi-x-lg me-1"></i>ยังไม่บันทึก</span>
          <?php endif; ?>
        </td>
        <td class="text-center">
          <?php if ($log): ?>
          <span class="text-success"><?= $log['present_count'] ?></span>
          / <span class="text-danger"><?= $log['absent_count'] ?></span>
          / <span class="text-warning"><?= $log['late_count'] ?></span>
          <?php else: ?>
          <span class="text-muted">—</span>
          <?php endif; ?>
        </td>
        <td class="text-center">
          <?php if ($log): ?>
          <a href="<?= BASE_URL ?>/homeroom/daily/log.php?id=<?= $log['id'] ?>" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-eye"></i>
          </a>
          <?php if ($log['status'] === 'submitted'): ?>
          <button class="btn btn-success btn-sm ms-1" onclick="quickApprove(<?= $log['id'] ?>)" title="อนุมัติ">
            <i class="bi bi-check-lg"></i>
          </button>
          <button class="btn btn-outline-danger btn-sm" onclick="quickReturn(<?= $log['id'] ?>)" title="ส่งคืน">
            <i class="bi bi-arrow-return-left"></i>
          </button>
          <?php endif; ?>
          <?php else: ?>
          <a href="<?= BASE_URL ?>/homeroom/daily/log.php?date=<?= $selectedDate ?>&classroom=<?= urlencode($cls) ?>"
             class="btn btn-outline-primary btn-sm">
            <i class="bi bi-plus me-1"></i>บันทึก
          </a>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>
</div>

<!-- Quick review modal -->
<div class="modal fade" id="quickModal" tabindex="-1">
  <div class="modal-dialog modal-sm">
    <form method="POST" action="review_daily.php">
      <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
      <input type="hidden" name="log_id" id="qLogId">
      <input type="hidden" name="action" id="qAction">
      <div class="modal-content">
        <div class="modal-header py-2">
          <h6 class="modal-title" id="qTitle">ยืนยัน</h6>
          <button type="button" class="btn-close btn-sm" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <label class="form-label small">ความเห็น (ไม่บังคับ)</label>
          <textarea name="review_note" class="form-control form-control-sm" rows="2" placeholder="บันทึกความเห็น..."></textarea>
        </div>
        <div class="modal-footer py-2">
          <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">ยกเลิก</button>
          <button type="submit" class="btn btn-sm" id="qBtn">ยืนยัน</button>
        </div>
      </div>
    </form>
  </div>
</div>

<script>
function quickApprove(id) {
    document.getElementById('qLogId').value  = id;
    document.getElementById('qAction').value = 'approved';
    document.getElementById('qTitle').textContent = 'อนุมัติบันทึกโฮมรูม';
    const btn = document.getElementById('qBtn');
    btn.className = 'btn btn-success btn-sm'; btn.textContent = 'อนุมัติ';
    new bootstrap.Modal(document.getElementById('quickModal')).show();
}
function quickReturn(id) {
    document.getElementById('qLogId').value  = id;
    document.getElementById('qAction').value = 'revision';
    document.getElementById('qTitle').textContent = 'ส่งคืนเพื่อแก้ไข';
    const btn = document.getElementById('qBtn');
    btn.className = 'btn btn-danger btn-sm'; btn.textContent = 'ส่งคืน';
    new bootstrap.Modal(document.getElementById('quickModal')).show();
}
</script>

<?php echo '</div></div></div>'; hrRenderFooter(); ?>
