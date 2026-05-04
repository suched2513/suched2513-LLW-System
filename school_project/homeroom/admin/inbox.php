<?php
session_start();
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../includes/layout.php';
requireRole(['admin']);

$u  = getCurrentUser();
$db = getDB();

// Counts per status
$counts = [];
foreach (['submitted' => 0, 'approved' => 0, 'revision' => 0, 'draft' => 0] as $s => $default) {
    $c = $db->prepare("SELECT COUNT(*) FROM hr_reports WHERE status = ?");
    $c->execute([$s]);
    $counts[$s] = (int)$c->fetchColumn();
}

// Filters
$filterStatus    = $_GET['status'] ?? 'submitted';
$filterClassroom = trim($_GET['classroom'] ?? '');
$filterType      = $_GET['type'] ?? '';
$filterYear      = (int)($_GET['year'] ?? 0);

$where  = [];
$params = [];
if ($filterStatus && $filterStatus !== 'all') { $where[] = 'r.status = ?'; $params[] = $filterStatus; }
if ($filterClassroom)                         { $where[] = 'r.classroom LIKE ?'; $params[] = '%' . $filterClassroom . '%'; }
if ($filterType)                              { $where[] = 'r.report_type = ?'; $params[] = $filterType; }
if ($filterYear)                              { $where[] = 'r.academic_year = ?'; $params[] = $filterYear; }

$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$reports = $db->prepare("
    SELECT r.*,
           CONCAT(u.firstname,' ',u.lastname) AS teacher_name,
           (SELECT COUNT(*) FROM hr_report_photos p WHERE p.report_id = r.id) AS photo_count,
           (SELECT COUNT(*) FROM hr_report_activities a WHERE a.report_id = r.id) AS activity_count
    FROM hr_reports r
    JOIN llw_users u ON u.user_id = r.teacher_id
    $whereSQL
    ORDER BY
        FIELD(r.status,'submitted','revision','approved','draft'),
        r.submitted_at DESC, r.created_at DESC
");
$reports->execute($params);
$rows = $reports->fetchAll();

$statusLabels = ['draft' => 'ร่าง', 'submitted' => 'รออนุมัติ', 'approved' => 'อนุมัติแล้ว', 'revision' => 'ส่งคืน'];
$statusColors = ['draft' => 'secondary', 'submitted' => 'warning', 'approved' => 'success', 'revision' => 'danger'];

renderHead('Inbox รายงานโฮมรูม');
echo '<div class="d-flex">'; renderSidebar('/homeroom/admin/inbox.php'); echo '<div class="main-content flex-grow-1">'; renderTopbar('Inbox รายงานโฮมรูม'); echo '<div class="page-content">'; showFlash();
?>

<!-- KPI cards -->
<div class="row g-3 mb-3">
  <?php
  $kpis = [
    ['label' => 'รออนุมัติ', 'status' => 'submitted', 'color' => 'warning', 'icon' => 'bi-hourglass-split'],
    ['label' => 'อนุมัติแล้ว', 'status' => 'approved', 'color' => 'success', 'icon' => 'bi-check-circle'],
    ['label' => 'ส่งคืน', 'status' => 'revision',  'color' => 'danger',  'icon' => 'bi-arrow-return-left'],
    ['label' => 'ร่าง', 'status' => 'draft',    'color' => 'secondary','icon' => 'bi-file-earmark'],
  ];
  foreach ($kpis as $k): ?>
  <div class="col-6 col-md-3">
    <a href="?status=<?= $k['status'] ?>" class="text-decoration-none">
      <div class="card text-center py-3 <?= $filterStatus === $k['status'] ? 'border-' . $k['color'] . ' border-2' : '' ?>">
        <div class="text-<?= $k['color'] ?> mb-1"><i class="bi <?= $k['icon'] ?>" style="font-size:1.5rem"></i></div>
        <div class="fw-bold fs-4 text-<?= $k['color'] ?>"><?= $counts[$k['status']] ?></div>
        <div class="small text-muted"><?= $k['label'] ?></div>
      </div>
    </a>
  </div>
  <?php endforeach; ?>
</div>

<!-- Filters -->
<div class="card mb-3">
  <div class="card-body py-2">
    <form method="GET" class="row g-2 align-items-end">
      <div class="col-auto">
        <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
          <option value="all" <?= $filterStatus === 'all' ? 'selected' : '' ?>>ทุกสถานะ</option>
          <?php foreach ($statusLabels as $s => $l): ?>
          <option value="<?= $s ?>" <?= $filterStatus === $s ? 'selected' : '' ?>><?= $l ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-auto">
        <select name="type" class="form-select form-select-sm" onchange="this.form.submit()">
          <option value="">ทุกประเภท</option>
          <option value="weekly" <?= $filterType === 'weekly' ? 'selected' : '' ?>>สัปดาห์</option>
          <option value="monthly" <?= $filterType === 'monthly' ? 'selected' : '' ?>>เดือน</option>
        </select>
      </div>
      <div class="col-auto">
        <input type="text" name="classroom" class="form-control form-control-sm" value="<?= h($filterClassroom) ?>" placeholder="ห้องเรียน เช่น ม.3">
      </div>
      <div class="col-auto">
        <select name="year" class="form-select form-select-sm" onchange="this.form.submit()">
          <option value="">ทุกปี</option>
          <?php for ($y = 2567; $y <= 2570; $y++): ?>
          <option value="<?= $y ?>" <?= $filterYear === $y ? 'selected' : '' ?>><?= $y ?></option>
          <?php endfor; ?>
        </select>
      </div>
      <div class="col-auto">
        <button type="submit" class="btn btn-secondary btn-sm"><i class="bi bi-search"></i></button>
        <a href="inbox.php" class="btn btn-light btn-sm ms-1">รีเซ็ต</a>
      </div>
    </form>
  </div>
</div>

<!-- Table -->
<div class="card">
  <div class="card-body p-0">
  <?php if (empty($rows)): ?>
  <div class="text-center py-5 text-muted">
    <i class="bi bi-inbox" style="font-size:2.5rem"></i>
    <p class="mt-2">ไม่มีรายงานในกล่องนี้</p>
  </div>
  <?php else: ?>
  <table class="table table-hover mb-0" style="font-size:14px">
    <thead>
      <tr>
        <th class="ps-3">งวด</th>
        <th>ห้อง / ครู</th>
        <th class="text-center">กิจกรรม</th>
        <th class="text-center">ภาพ</th>
        <th>ส่งเมื่อ</th>
        <th class="text-center">สถานะ</th>
        <th class="text-center">ดำเนินการ</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
    <tr>
      <td class="ps-3">
        <span class="badge <?= $r['report_type'] === 'weekly' ? 'bg-info text-dark' : 'bg-primary' ?> me-1">
          <?= $r['report_type'] === 'weekly' ? 'สัปดาห์' : 'เดือน' ?>
        </span>
        <div class="small text-muted">
          <?= formatDate($r['period_start']) ?> — <?= formatDate($r['period_end']) ?>
        </div>
        <div class="small text-muted">ปี <?= $r['academic_year'] ?>/<?= $r['semester'] ?></div>
      </td>
      <td>
        <div class="fw-semibold"><?= h($r['classroom']) ?></div>
        <small class="text-muted"><?= h($r['teacher_name']) ?></small>
      </td>
      <td class="text-center">
        <span class="badge bg-light text-dark border"><?= $r['activity_count'] ?></span>
      </td>
      <td class="text-center">
        <?php if ($r['photo_count'] > 0): ?>
          <span class="badge bg-light text-dark border"><i class="bi bi-images me-1"></i><?= $r['photo_count'] ?></span>
        <?php else: ?>
          <span class="text-muted">—</span>
        <?php endif; ?>
      </td>
      <td>
        <small><?= $r['submitted_at'] ? formatDate($r['submitted_at'], true) : '—' ?></small>
      </td>
      <td class="text-center">
        <span class="badge bg-<?= $statusColors[$r['status']] ?? 'secondary' ?>">
          <?= $statusLabels[$r['status']] ?? $r['status'] ?>
        </span>
      </td>
      <td class="text-center">
        <div class="btn-group btn-group-sm">
          <a href="<?= BASE_URL ?>/homeroom/reports/view.php?id=<?= $r['id'] ?>" class="btn btn-outline-secondary" title="ดูรายงาน">
            <i class="bi bi-eye"></i>
          </a>
          <?php if ($r['status'] === 'submitted'): ?>
          <button class="btn btn-success" onclick="quickApprove(<?= $r['id'] ?>)" title="อนุมัติ">
            <i class="bi bi-check-lg"></i>
          </button>
          <button class="btn btn-outline-danger" onclick="quickReturn(<?= $r['id'] ?>)" title="ส่งคืน">
            <i class="bi bi-arrow-return-left"></i>
          </button>
          <?php endif; ?>
        </div>
      </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
  </div>
</div>

<!-- Quick action modal -->
<div class="modal fade" id="quickModal" tabindex="-1">
  <div class="modal-dialog modal-sm">
    <form method="POST" action="review.php">
      <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
      <input type="hidden" name="report_id" id="qReportId">
      <input type="hidden" name="review_action" id="qAction">
      <div class="modal-content">
        <div class="modal-header py-2">
          <h6 class="modal-title" id="qTitle">ยืนยัน</h6>
          <button type="button" class="btn-close btn-sm" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <label class="form-label small">ความเห็น (ไม่บังคับ)</label>
          <textarea name="review_note" class="form-control form-control-sm" rows="2"
                    placeholder="บันทึกความเห็น..."></textarea>
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
    document.getElementById('qReportId').value = id;
    document.getElementById('qAction').value   = 'approved';
    document.getElementById('qTitle').textContent = 'อนุมัติรายงาน';
    const btn = document.getElementById('qBtn');
    btn.className = 'btn btn-success btn-sm'; btn.textContent = 'อนุมัติ';
    new bootstrap.Modal(document.getElementById('quickModal')).show();
}
function quickReturn(id) {
    document.getElementById('qReportId').value = id;
    document.getElementById('qAction').value   = 'revision';
    document.getElementById('qTitle').textContent = 'ส่งคืนเพื่อแก้ไข';
    const btn = document.getElementById('qBtn');
    btn.className = 'btn btn-danger btn-sm'; btn.textContent = 'ส่งคืน';
    new bootstrap.Modal(document.getElementById('quickModal')).show();
}
</script>

<?php echo '</div></div></div>'; renderFooter(); ?>
