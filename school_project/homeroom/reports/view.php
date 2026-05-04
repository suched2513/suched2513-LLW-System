<?php
session_start();
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../includes/layout.php';
requireLogin();

$u       = getCurrentUser();
$db      = getDB();
$id      = (int)($_GET['id'] ?? 0);
$isAdmin = in_array($u['role'], ['admin', 'super_admin']);

$stmt = $db->prepare("
    SELECT r.*, CONCAT(u.firstname,' ',u.lastname) AS teacher_name
    FROM hr_reports r
    JOIN llw_users u ON u.user_id = r.teacher_id
    WHERE r.id = ?
");
$stmt->execute([$id]);
$report = $stmt->fetch();

if (!$report) { flashMessage('danger', 'ไม่พบรายงาน'); header('Location: list.php'); exit; }
if (!$isAdmin && (int)$report['teacher_id'] !== (int)$u['id']) {
    http_response_code(403); die('<p class="p-4">ไม่มีสิทธิ์ดูรายงานนี้</p>');
}

$actStmt = $db->prepare("SELECT * FROM hr_report_activities WHERE report_id = ? ORDER BY order_no, activity_date");
$actStmt->execute([$id]);
$activities = $actStmt->fetchAll();

$phStmt = $db->prepare("SELECT * FROM hr_report_photos WHERE report_id = ? ORDER BY order_no");
$phStmt->execute([$id]);
$photos = $phStmt->fetchAll();

$reviewerName = '';
if ($report['reviewed_by']) {
    $rv = $db->prepare("SELECT CONCAT(firstname,' ',lastname) AS name FROM llw_users WHERE user_id = ?");
    $rv->execute([$report['reviewed_by']]);
    $reviewerName = $rv->fetchColumn() ?: '';
}

$statusLabels = ['draft' => 'ร่าง', 'submitted' => 'รออนุมัติ', 'approved' => 'อนุมัติแล้ว', 'revision' => 'ส่งคืน'];
$statusColors = ['draft' => 'secondary', 'submitted' => 'warning', 'approved' => 'success', 'revision' => 'danger'];

// Attendance rate
$totalSlots    = (int)$report['total_students'] * (int)$report['school_days'];
$attendancePct = $totalSlots > 0 ? round($report['present_count'] / $totalSlots * 100, 1) : 0;

renderHead('รายงานโฮมรูม — ' . h($report['classroom']));
echo '<div class="d-flex">'; renderSidebar('/homeroom/reports/list.php'); echo '<div class="main-content flex-grow-1">'; renderTopbar('รายงานโฮมรูม'); echo '<div class="page-content" id="mainContent">'; showFlash();
?>

<!-- Toolbar -->
<div class="d-flex justify-content-between align-items-center mb-3 no-print flex-wrap gap-2">
  <div>
    <a href="list.php" class="btn btn-light btn-sm">← กลับรายการ</a>
    <?php if (in_array($report['status'], ['draft', 'revision']) && ($isAdmin || $report['teacher_id'] == $u['id'])): ?>
      <a href="edit.php?id=<?= $id ?>" class="btn btn-outline-primary btn-sm ms-1">
        <i class="bi bi-pencil me-1"></i>แก้ไข
      </a>
    <?php endif; ?>
    <?php if ($isAdmin && $report['status'] === 'submitted'): ?>
      <button class="btn btn-success btn-sm ms-1" onclick="openReview('approved')">
        <i class="bi bi-check-circle me-1"></i>อนุมัติ
      </button>
      <button class="btn btn-outline-danger btn-sm ms-1" onclick="openReview('revision')">
        <i class="bi bi-arrow-return-left me-1"></i>ส่งคืน
      </button>
    <?php endif; ?>
  </div>
  <button onclick="window.print()" class="btn btn-outline-secondary btn-sm">
    <i class="bi bi-printer me-1"></i>พิมพ์รายงาน
  </button>
</div>

<!-- ===== PRINT DOCUMENT ===== -->
<div id="printDocument">

  <!-- Print header -->
  <div class="print-header text-center mb-4 d-none d-print-block">
    <h5 class="fw-bold mb-0"><?= h(SCHOOL_NAME) ?></h5>
    <p class="small mb-1"><?= h(SCHOOL_DISTRICT) ?> <?= h(SCHOOL_PROVINCE) ?></p>
    <h6 class="fw-bold mt-2 mb-0">
      รายงานโฮมรูม<?= $report['report_type'] === 'weekly' ? 'รายสัปดาห์' : 'ประจำเดือน' ?>
    </h6>
    <p class="small mb-0">
      ห้อง <?= h($report['classroom']) ?> |
      ครูที่ปรึกษา: <?= h($report['teacher_name']) ?> |
      ปีการศึกษา <?= $report['academic_year'] ?> ภาคเรียนที่ <?= $report['semester'] ?>
    </p>
    <p class="small mb-0">
      <?= formatDate($report['period_start']) ?> — <?= formatDate($report['period_end']) ?>
    </p>
  </div>

  <div class="row g-3">
    <!-- Report info -->
    <div class="col-lg-8">

      <!-- Header card -->
      <div class="card mb-3">
        <div class="card-body">
          <div class="row g-3">
            <div class="col-sm-6">
              <label class="text-muted small">ห้องเรียน</label>
              <div class="fw-bold fs-5"><?= h($report['classroom']) ?></div>
            </div>
            <div class="col-sm-6">
              <label class="text-muted small">ครูที่ปรึกษา</label>
              <div><?= h($report['teacher_name']) ?></div>
            </div>
            <div class="col-sm-6">
              <label class="text-muted small">ประเภทรายงาน</label>
              <div>
                <?= $report['report_type'] === 'weekly'
                    ? '<span class="badge bg-info text-dark">รายสัปดาห์</span>'
                    : '<span class="badge bg-primary">รายเดือน</span>' ?>
                ปีการศึกษา <?= $report['academic_year'] ?> ภาคเรียน <?= $report['semester'] ?>
              </div>
            </div>
            <div class="col-sm-6">
              <label class="text-muted small">ช่วงเวลา</label>
              <div><?= formatDate($report['period_start']) ?> — <?= formatDate($report['period_end']) ?></div>
            </div>
          </div>
        </div>
      </div>

      <!-- Attendance stats -->
      <div class="card mb-3">
        <div class="card-header fw-bold"><i class="bi bi-bar-chart me-2"></i>สถิติการเข้าแถว</div>
        <div class="card-body">
          <div class="row g-3 text-center mb-3">
            <div class="col-6 col-md-3">
              <div class="border rounded p-2">
                <div class="fs-4 fw-bold text-primary"><?= $report['total_students'] ?></div>
                <div class="small text-muted">นักเรียนทั้งหมด</div>
              </div>
            </div>
            <div class="col-6 col-md-3">
              <div class="border rounded p-2">
                <div class="fs-4 fw-bold"><?= $report['school_days'] ?></div>
                <div class="small text-muted">วันเรียน</div>
              </div>
            </div>
            <div class="col-6 col-md-2">
              <div class="border rounded p-2 border-success">
                <div class="fs-4 fw-bold text-success"><?= $report['present_count'] ?></div>
                <div class="small text-muted">มาเรียน (ครั้ง)</div>
              </div>
            </div>
            <div class="col-6 col-md-2">
              <div class="border rounded p-2 border-danger">
                <div class="fs-4 fw-bold text-danger"><?= $report['absent_count'] ?></div>
                <div class="small text-muted">ขาดเรียน</div>
              </div>
            </div>
            <div class="col-6 col-md-2">
              <div class="border rounded p-2 border-warning">
                <div class="fs-4 fw-bold text-warning"><?= $report['late_count'] ?></div>
                <div class="small text-muted">สาย</div>
              </div>
            </div>
          </div>
          <?php if ($totalSlots > 0): ?>
          <div>
            <div class="d-flex justify-content-between small text-muted mb-1">
              <span>อัตราการเข้าเรียนเฉลี่ย</span>
              <span class="fw-semibold <?= $attendancePct >= 80 ? 'text-success' : ($attendancePct >= 60 ? 'text-warning' : 'text-danger') ?>">
                <?= $attendancePct ?>%
              </span>
            </div>
            <div class="progress" style="height:8px">
              <div class="progress-bar <?= $attendancePct >= 80 ? 'bg-success' : ($attendancePct >= 60 ? 'bg-warning' : 'bg-danger') ?>"
                   style="width:<?= $attendancePct ?>%"></div>
            </div>
          </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Activities -->
      <?php if ($activities): ?>
      <div class="card mb-3">
        <div class="card-header fw-bold"><i class="bi bi-list-task me-2"></i>กิจกรรมในงวดนี้</div>
        <div class="card-body p-0">
          <table class="table table-sm mb-0" style="font-size:14px">
            <thead class="table-light">
              <tr><th class="ps-3">#</th><th>วันที่</th><th>กิจกรรม</th><th>รายละเอียด</th><th class="text-center">ผู้ร่วม</th><th>ผลที่ได้</th></tr>
            </thead>
            <tbody>
            <?php foreach ($activities as $i => $act): ?>
            <tr>
              <td class="ps-3 text-muted"><?= $i + 1 ?></td>
              <td class="text-nowrap"><?= $act['activity_date'] ? formatDate($act['activity_date']) : '—' ?></td>
              <td class="fw-semibold"><?= h($act['activity_name']) ?></td>
              <td><small><?= h($act['description'] ?? '—') ?></small></td>
              <td class="text-center"><?= $act['participants_count'] ? number_format($act['participants_count']) . ' คน' : '—' ?></td>
              <td><small><?= h($act['outcome'] ?? '—') ?></small></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
      <?php endif; ?>

      <!-- Summary sections -->
      <?php
      $sections = [
        ['label' => 'สรุปกิจกรรมโดยรวม',  'icon' => 'bi-journal-text',   'field' => 'activities_summary'],
        ['label' => 'ปัญหาที่พบ / อุปสรรค', 'icon' => 'bi-exclamation-circle','field' => 'problems_noted'],
        ['label' => 'ข้อเสนอแนะ',           'icon' => 'bi-lightbulb',      'field' => 'suggestions'],
        ['label' => 'แผนงานงวดถัดไป',       'icon' => 'bi-calendar-check', 'field' => 'next_period_plan'],
      ];
      ?>
      <div class="card mb-3">
        <div class="card-header fw-bold"><i class="bi bi-pencil-square me-2"></i>บทสรุป</div>
        <div class="card-body">
          <?php foreach ($sections as $sec): ?>
          <div class="mb-3">
            <label class="fw-semibold small text-muted text-uppercase">
              <i class="bi <?= $sec['icon'] ?> me-1"></i><?= $sec['label'] ?>
            </label>
            <div class="border rounded p-2 bg-light" style="min-height:2.5rem;font-size:14px;white-space:pre-wrap">
              <?= $report[$sec['field']] ? h($report[$sec['field']]) : '<span class="text-muted fst-italic">—</span>' ?>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- Photos -->
      <?php if ($photos): ?>
      <div class="card mb-3">
        <div class="card-header fw-bold"><i class="bi bi-images me-2"></i>ภาพกิจกรรม (<?= count($photos) ?> ภาพ)</div>
        <div class="card-body">
          <div class="row g-2">
            <?php foreach ($photos as $ph): ?>
            <div class="col-6 col-md-4">
              <a href="<?= BASE_URL ?>/uploads/<?= h($ph['file_path']) ?>" target="_blank">
                <img src="<?= BASE_URL ?>/uploads/<?= h($ph['file_path']) ?>"
                     class="img-fluid rounded w-100"
                     style="height:140px;object-fit:cover"
                     alt="<?= h($ph['caption'] ?? '') ?>">
              </a>
              <?php if ($ph['caption']): ?>
                <p class="small text-muted text-center mt-1 mb-0"><?= h($ph['caption']) ?></p>
              <?php endif; ?>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
      <?php endif; ?>

      <!-- Signature block (print only) -->
      <div class="d-none d-print-block mt-4">
        <div class="row text-center">
          <div class="col-4">
            <div style="height:50px"></div>
            <div class="border-top pt-1">ลงชื่อ ครูที่ปรึกษา<br><small><?= h($report['teacher_name']) ?></small></div>
          </div>
          <div class="col-4">
            <div style="height:50px"></div>
            <div class="border-top pt-1">ลงชื่อ หัวหน้าระดับ<br><small>(.....................................)</small></div>
          </div>
          <div class="col-4">
            <div style="height:50px"></div>
            <div class="border-top pt-1">ลงชื่อ รองผู้อำนวยการ<br><small>(.....................................)</small></div>
          </div>
        </div>
      </div>

    </div><!-- /col-lg-8 -->

    <!-- Status sidebar -->
    <div class="col-lg-4 no-print">
      <div class="card mb-3">
        <div class="card-header fw-bold">สถานะรายงาน</div>
        <div class="card-body">
          <div class="text-center mb-3">
            <span class="badge bg-<?= $statusColors[$report['status']] ?? 'secondary' ?> fs-6 px-3 py-2">
              <?= $statusLabels[$report['status']] ?? $report['status'] ?>
            </span>
          </div>
          <table class="table table-sm">
            <tr><td class="text-muted">สร้างเมื่อ</td><td><?= formatDate($report['created_at'], true) ?></td></tr>
            <?php if ($report['submitted_at']): ?>
            <tr><td class="text-muted">ส่งเมื่อ</td><td><?= formatDate($report['submitted_at'], true) ?></td></tr>
            <?php endif; ?>
            <?php if ($report['reviewed_at']): ?>
            <tr><td class="text-muted">ตรวจสอบโดย</td><td><?= h($reviewerName) ?></td></tr>
            <tr><td class="text-muted">ตรวจสอบเมื่อ</td><td><?= formatDate($report['reviewed_at'], true) ?></td></tr>
            <?php endif; ?>
          </table>
          <?php if ($report['review_note']): ?>
          <div class="alert alert-<?= $report['status'] === 'approved' ? 'success' : 'warning' ?> py-2 small">
            <strong>ความเห็น:</strong> <?= h($report['review_note']) ?>
          </div>
          <?php endif; ?>
        </div>
      </div>
    </div>

  </div><!-- /row -->
</div><!-- /printDocument -->

<!-- Admin review modal -->
<?php if ($isAdmin && $report['status'] === 'submitted'): ?>
<div class="modal fade" id="reviewModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="POST" action="<?= BASE_URL ?>/homeroom/admin/review.php">
      <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
      <input type="hidden" name="report_id" value="<?= $id ?>">
      <input type="hidden" name="review_action" id="reviewAction" value="">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="reviewModalTitle">ตรวจสอบรายงาน</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">ความเห็น / หมายเหตุ (ไม่บังคับสำหรับการอนุมัติ)</label>
            <textarea name="review_note" class="form-control" rows="3"
                      placeholder="บันทึกความเห็นประกอบการพิจารณา..."></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">ยกเลิก</button>
          <button type="submit" class="btn" id="reviewSubmitBtn">ยืนยัน</button>
        </div>
      </div>
    </form>
  </div>
</div>
<script>
function openReview(action) {
    document.getElementById('reviewAction').value = action;
    const title = action === 'approved' ? 'อนุมัติรายงาน' : 'ส่งคืนเพื่อแก้ไข';
    const btnClass = action === 'approved' ? 'btn-success' : 'btn-danger';
    document.getElementById('reviewModalTitle').textContent = title;
    const btn = document.getElementById('reviewSubmitBtn');
    btn.className = 'btn ' + btnClass;
    btn.textContent = title;
    new bootstrap.Modal(document.getElementById('reviewModal')).show();
}
</script>
<?php endif; ?>

<style>
@media print {
  .no-print, .sidebar, .topbar, .sidebar-footer { display: none !important; }
  .main-content { margin-left: 0 !important; }
  .page-content { padding: 0 !important; }
  .card { border: 1px solid #ddd !important; box-shadow: none !important; break-inside: avoid; }
  .card-header { background: #f8f8f8 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
  img { max-height: 160px; object-fit: cover; }
  .col-md-4 { width: 33% !important; }
  .col-md-3 { width: 25% !important; }
}
</style>

<?php echo '</div></div></div>'; renderFooter(); ?>
