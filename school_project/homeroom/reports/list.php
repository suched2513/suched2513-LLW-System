<?php
session_start();
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../includes/layout.php';
requireLogin();

$u       = getCurrentUser();
$db      = getDB();
$isAdmin = in_array($u['role'], ['admin', 'super_admin']);

// ห้องเรียนของครูคนนี้
$myClassrooms = [];
try {
    $cs = $db->prepare("SELECT classroom FROM llw_class_advisors WHERE user_id = ? ORDER BY classroom");
    $cs->execute([$u['id']]);
    $myClassrooms = $cs->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) { $myClassrooms = []; }

// Filter params
$filterStatus    = $_GET['status'] ?? 'all';
$filterClassroom = $_GET['classroom'] ?? '';
$filterYear      = (int)($_GET['year'] ?? 0);
$filterType      = $_GET['type'] ?? '';

// Build query
$where  = [];
$params = [];

if (!$isAdmin) {
    $where[]  = 'r.teacher_id = ?';
    $params[] = $u['id'];
}
if ($filterStatus && $filterStatus !== 'all') {
    $where[]  = 'r.status = ?';
    $params[] = $filterStatus;
}
if ($filterClassroom) {
    $where[]  = 'r.classroom = ?';
    $params[] = $filterClassroom;
}
if ($filterYear) {
    $where[]  = 'r.academic_year = ?';
    $params[] = $filterYear;
}
if ($filterType) {
    $where[]  = 'r.report_type = ?';
    $params[] = $filterType;
}

$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$rows = [];
$migrationReady = false;
try {
    $reports = $db->prepare("
        SELECT r.*,
               CONCAT(u.firstname,' ',u.lastname) AS teacher_name,
               (SELECT COUNT(*) FROM hr_report_photos p WHERE p.report_id = r.id) AS photo_count,
               (SELECT COUNT(*) FROM hr_report_activities a WHERE a.report_id = r.id) AS activity_count
        FROM hr_reports r
        JOIN llw_users u ON u.user_id = r.teacher_id
        $whereSQL
        ORDER BY r.period_start DESC, r.created_at DESC
    ");
    $reports->execute($params);
    $rows = $reports->fetchAll();
    $migrationReady = true;
} catch (Exception $e) {
    $migrationReady = false;
}

// Counts per status (for tabs) — same filters minus status
$countParams = array_slice($params, $isAdmin ? 0 : 1);
if (!$isAdmin) array_unshift($countParams, $u['id']);

$statusLabels = [
    'all'       => 'ทั้งหมด',
    'draft'     => 'ร่าง',
    'submitted' => 'รออนุมัติ',
    'approved'  => 'อนุมัติแล้ว',
    'revision'  => 'ส่งคืน',
];
$statusColors = [
    'draft'     => 'secondary',
    'submitted' => 'warning',
    'approved'  => 'success',
    'revision'  => 'danger',
];

renderHead('รายงานโฮมรูม');
echo '<div class="d-flex">'; renderSidebar('/homeroom/reports/list.php'); echo '<div class="main-content flex-grow-1">'; renderTopbar('รายงานโฮมรูม'); echo '<div class="page-content">'; showFlash();
?>

<?php if (!$migrationReady): ?>
<div class="alert alert-danger">
  <i class="bi bi-exclamation-octagon me-2"></i>
  <strong>ยังไม่ได้ run migration</strong> — ตารางฐานข้อมูลสำหรับระบบโฮมรูมยังไม่มี<br>
  กรุณาเปิด <a href="<?= BASE_URL ?>/_migrate.php?run=1" target="_blank"><strong>_migrate.php?run=1</strong></a> แล้วรีโหลดหน้านี้
</div>
<?php elseif (!$isAdmin && empty($myClassrooms)): ?>
<div class="alert alert-warning">
  <i class="bi bi-exclamation-triangle me-2"></i>
  <strong>ยังไม่มีห้องเรียนที่รับผิดชอบ</strong>
  กรุณาติดต่อผู้ดูแลระบบเพื่อมอบหมายห้องเรียนก่อนสร้างรายงาน
</div>
<?php endif; ?>

<!-- Header row -->
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <h5 class="mb-0 fw-bold">
    <i class="bi bi-journal-text me-2 text-amber-600"></i>รายงานโฮมรูม
    <?php if (!$isAdmin && $myClassrooms): ?>
      <small class="text-muted fw-normal">(<?= h(implode(', ', $myClassrooms)) ?>)</small>
    <?php endif; ?>
  </h5>
  <?php if (!$isAdmin && $myClassrooms): ?>
  <a href="edit.php" class="btn btn-primary btn-sm">
    <i class="bi bi-plus-circle me-1"></i>สร้างรายงานใหม่
  </a>
  <?php endif; ?>
</div>

<!-- Filters -->
<div class="card mb-3">
  <div class="card-body py-2">
    <form method="GET" class="row g-2 align-items-end">
      <div class="col-auto">
        <label class="form-label small mb-1">สถานะ</label>
        <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
          <?php foreach ($statusLabels as $k => $v): ?>
            <option value="<?= $k ?>" <?= $filterStatus === $k ? 'selected' : '' ?>><?= $v ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-auto">
        <label class="form-label small mb-1">ประเภท</label>
        <select name="type" class="form-select form-select-sm" onchange="this.form.submit()">
          <option value="">ทั้งหมด</option>
          <option value="weekly" <?= $filterType === 'weekly' ? 'selected' : '' ?>>รายสัปดาห์</option>
          <option value="monthly" <?= $filterType === 'monthly' ? 'selected' : '' ?>>รายเดือน</option>
        </select>
      </div>
      <?php if ($isAdmin): ?>
      <div class="col-auto">
        <label class="form-label small mb-1">ห้องเรียน</label>
        <input type="text" name="classroom" class="form-control form-control-sm" value="<?= h($filterClassroom) ?>" placeholder="เช่น ม.3/2">
      </div>
      <?php endif; ?>
      <div class="col-auto">
        <label class="form-label small mb-1">ปีการศึกษา</label>
        <select name="year" class="form-select form-select-sm" onchange="this.form.submit()">
          <option value="">ทั้งหมด</option>
          <?php for ($y = 2568; $y <= 2570; $y++): ?>
            <option value="<?= $y ?>" <?= $filterYear === $y ? 'selected' : '' ?>><?= $y ?></option>
          <?php endfor; ?>
        </select>
      </div>
      <div class="col-auto">
        <button type="submit" class="btn btn-outline-secondary btn-sm">
          <i class="bi bi-search"></i>
        </button>
        <a href="list.php" class="btn btn-light btn-sm ms-1">รีเซ็ต</a>
      </div>
    </form>
  </div>
</div>

<!-- Table -->
<div class="card">
  <div class="card-body p-0">
    <?php if (empty($rows)): ?>
    <div class="text-center py-5 text-muted">
      <i class="bi bi-journal-x" style="font-size:2.5rem"></i>
      <p class="mt-2 mb-0">ยังไม่มีรายงาน</p>
      <?php if (!$isAdmin && $myClassrooms): ?>
        <a href="edit.php" class="btn btn-primary btn-sm mt-2">
          <i class="bi bi-plus-circle me-1"></i>สร้างรายงานแรก
        </a>
      <?php endif; ?>
    </div>
    <?php else: ?>
    <table class="table table-hover mb-0" style="font-size:14px">
      <thead>
        <tr>
          <th class="ps-3">งวด</th>
          <th>ห้อง</th>
          <?php if ($isAdmin): ?><th>ครูที่ปรึกษา</th><?php endif; ?>
          <th class="text-center">กิจกรรม</th>
          <th class="text-center">ภาพ</th>
          <th class="text-center">สถานะ</th>
          <th class="text-center">ดำเนินการ</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($rows as $r):
        $isEditable = in_array($r['status'], ['draft', 'revision']);
      ?>
      <tr>
        <td class="ps-3">
          <div class="fw-semibold">
            <?= $r['report_type'] === 'weekly' ? '<span class="badge bg-info text-dark me-1">สัปดาห์</span>' : '<span class="badge bg-primary me-1">เดือน</span>' ?>
            <?= h($r['academic_year']) ?>/<?= $r['semester'] ?>
          </div>
          <small class="text-muted">
            <?= formatDate($r['period_start']) ?> — <?= formatDate($r['period_end']) ?>
          </small>
        </td>
        <td><span class="badge bg-light text-dark border"><?= h($r['classroom']) ?></span></td>
        <?php if ($isAdmin): ?>
        <td><small><?= h($r['teacher_name']) ?></small></td>
        <?php endif; ?>
        <td class="text-center">
          <?= $r['activity_count'] > 0 ? '<span class="badge bg-light text-dark border">' . $r['activity_count'] . ' รายการ</span>' : '<span class="text-muted">—</span>' ?>
        </td>
        <td class="text-center">
          <?= $r['photo_count'] > 0 ? '<span class="badge bg-light text-dark border"><i class="bi bi-images me-1"></i>' . $r['photo_count'] . '</span>' : '<span class="text-muted">—</span>' ?>
        </td>
        <td class="text-center">
          <span class="badge bg-<?= $statusColors[$r['status']] ?? 'secondary' ?>">
            <?= $statusLabels[$r['status']] ?? $r['status'] ?>
          </span>
          <?php if ($r['review_note'] && $r['status'] === 'revision'): ?>
            <i class="bi bi-chat-left-text text-danger ms-1" title="<?= h($r['review_note']) ?>"></i>
          <?php endif; ?>
        </td>
        <td class="text-center">
          <div class="btn-group btn-group-sm">
            <a href="view.php?id=<?= $r['id'] ?>" class="btn btn-outline-secondary" title="ดู">
              <i class="bi bi-eye"></i>
            </a>
            <?php if (($isAdmin || $r['teacher_id'] == $u['id']) && $isEditable): ?>
            <a href="edit.php?id=<?= $r['id'] ?>" class="btn btn-outline-primary" title="แก้ไข">
              <i class="bi bi-pencil"></i>
            </a>
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

<?php echo '</div></div></div>'; renderFooter(); ?>
