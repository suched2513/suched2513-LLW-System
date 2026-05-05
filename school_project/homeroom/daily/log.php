<?php
session_start();
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../includes/layout.php';
requireLogin();

$u       = getCurrentUser();
$db      = getDB();
$isAdmin = in_array($u['role'], ['admin', 'super_admin']);

// ห้องเรียนของครู
$myClassrooms = [];
try {
    $cs = $db->prepare("SELECT classroom FROM llw_class_advisors WHERE user_id = ? ORDER BY classroom");
    $cs->execute([$u['id']]);
    $myClassrooms = $cs->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) { $myClassrooms = []; }

// ถ้าเป็น admin ให้เลือก classroom ได้ทั้งหมด
if ($isAdmin && empty($myClassrooms)) {
    try {
        $cs = $db->prepare("SELECT DISTINCT classroom FROM llw_class_advisors ORDER BY classroom");
        $cs->execute();
        $myClassrooms = $cs->fetchAll(PDO::FETCH_COLUMN);
    } catch (Exception $e) { $myClassrooms = []; }
}

$selectedClassroom = trim($_GET['classroom'] ?? ($myClassrooms[0] ?? ''));
$selectedDate      = $_GET['date'] ?? date('Y-m-d');
$logId             = (int)($_GET['id'] ?? 0);

// ============================================================
// POST handler
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $postClassroom = trim($_POST['classroom'] ?? '');
    $postDate      = $_POST['log_date'] ?? date('Y-m-d');
    $postYear      = (int)($_POST['academic_year'] ?? (int)date('Y') + 543);
    $postSemester  = (int)($_POST['semester'] ?? 1);
    $postNotes     = trim($_POST['notes'] ?? '');
    $postAction    = $_POST['action'] ?? 'draft';

    // กิจกรรม
    $actNames = $_POST['act_name'] ?? [];
    $actDescs = $_POST['act_desc'] ?? [];
    $activities = [];
    foreach ($actNames as $i => $n) {
        $n = trim($n);
        if ($n !== '') {
            $activities[] = ['name' => $n, 'desc' => trim($actDescs[$i] ?? '')];
        }
    }

    // สถิติการมาเรียน
    $totalSt  = (int)($_POST['total_students'] ?? 0);
    $present  = (int)($_POST['present_count'] ?? 0);
    $absent   = (int)($_POST['absent_count'] ?? 0);
    $late     = (int)($_POST['late_count'] ?? 0);
    $leave    = (int)($_POST['leave_count'] ?? 0);
    $attSync  = (int)($_POST['att_synced'] ?? 0);

    $newStatus     = ($postAction === 'submit') ? 'submitted' : 'draft';
    $submittedAt   = ($postAction === 'submit') ? date('Y-m-d H:i:s') : null;

    try {
        $editId = (int)($_POST['log_id'] ?? 0);

        if ($editId > 0) {
            // ตรวจสิทธิ์
            $chk = $db->prepare("SELECT teacher_id, status FROM hr_daily_logs WHERE id = ?");
            $chk->execute([$editId]);
            $existing = $chk->fetch();
            if (!$existing || (!$isAdmin && (int)$existing['teacher_id'] !== (int)$u['id'])) {
                flashMessage('danger', 'ไม่มีสิทธิ์แก้ไขรายการนี้'); header('Location: log.php'); exit;
            }
            if (!in_array($existing['status'], ['draft', 'revision'])) {
                flashMessage('warning', 'รายการนี้ส่งแล้ว ไม่สามารถแก้ไขได้'); header('Location: log.php?date=' . $postDate . '&classroom=' . urlencode($postClassroom)); exit;
            }

            $stmt = $db->prepare("UPDATE hr_daily_logs SET
                notes=?, activities=?, total_students=?, present_count=?, absent_count=?,
                late_count=?, leave_count=?, att_synced=?, status=?, submitted_at=?, updated_at=NOW()
                WHERE id=?");
            $stmt->execute([
                $postNotes, json_encode($activities, JSON_UNESCAPED_UNICODE),
                $totalSt, $present, $absent, $late, $leave, $attSync,
                $newStatus, $submittedAt, $editId
            ]);
            $logId = $editId;
        } else {
            // ตรวจว่ามีของวันนี้อยู่แล้วหรือเปล่า
            $chk = $db->prepare("SELECT id FROM hr_daily_logs WHERE classroom=? AND log_date=?");
            $chk->execute([$postClassroom, $postDate]);
            $dup = $chk->fetch();
            if ($dup) {
                flashMessage('warning', 'มีบันทึกของวันนี้ในห้อง ' . $postClassroom . ' แล้ว');
                header('Location: log.php?date=' . $postDate . '&classroom=' . urlencode($postClassroom) . '&id=' . $dup['id']); exit;
            }

            $stmt = $db->prepare("INSERT INTO hr_daily_logs
                (teacher_id, classroom, log_date, academic_year, semester, notes, activities,
                 total_students, present_count, absent_count, late_count, leave_count, att_synced, status, submitted_at)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
            $stmt->execute([
                $u['id'], $postClassroom, $postDate, $postYear, $postSemester,
                $postNotes, json_encode($activities, JSON_UNESCAPED_UNICODE),
                $totalSt, $present, $absent, $late, $leave, $attSync,
                $newStatus, $submittedAt
            ]);
            $logId = (int)$db->lastInsertId();
        }

        if ($postAction === 'submit') {
            flashMessage('success', 'ส่งบันทึกโฮมรูม ' . $postDate . ' เรียบร้อย');
            header('Location: log.php?date=' . $postDate . '&classroom=' . urlencode($postClassroom)); exit;
        } else {
            flashMessage('success', 'บันทึกร่างแล้ว');
            header('Location: log.php?date=' . $postDate . '&classroom=' . urlencode($postClassroom) . '&id=' . $logId); exit;
        }

    } catch (Exception $e) {
        error_log('hr_daily_log save: ' . $e->getMessage());
        flashMessage('danger', 'เกิดข้อผิดพลาด กรุณาลองใหม่');
        header('Location: log.php?date=' . $postDate . '&classroom=' . urlencode($postClassroom)); exit;
    }
}

// ============================================================
// โหลดข้อมูลสำหรับแสดงฟอร์ม
// ============================================================
$existingLog = null;
$existingPhotos = [];

// โหลด log ที่มีอยู่ (จาก id หรือค้นหาจาก date+classroom)
if ($logId > 0) {
    $stmt = $db->prepare("SELECT * FROM hr_daily_logs WHERE id = ?");
    $stmt->execute([$logId]);
    $existingLog = $stmt->fetch();
    if ($existingLog) {
        $selectedClassroom = $existingLog['classroom'];
        $selectedDate      = $existingLog['log_date'];

        $phStmt = $db->prepare("SELECT * FROM hr_daily_photos WHERE log_id = ? ORDER BY order_no, id");
        $phStmt->execute([$logId]);
        $existingPhotos = $phStmt->fetchAll();
    }
} else {
    try {
        $stmt = $db->prepare("SELECT * FROM hr_daily_logs WHERE classroom = ? AND log_date = ?");
        $stmt->execute([$selectedClassroom, $selectedDate]);
        $existingLog = $stmt->fetch() ?: null;
        if ($existingLog) {
            $logId = $existingLog['id'];
            $phStmt = $db->prepare("SELECT * FROM hr_daily_photos WHERE log_id = ? ORDER BY order_no, id");
            $phStmt->execute([$logId]);
            $existingPhotos = $phStmt->fetchAll();
        }
    } catch (Exception $e) { $existingLog = null; }
}

$isReadOnly = $existingLog && !in_array($existingLog['status'], ['draft', 'revision']);

// ============================================================
// ดึงนักเรียนจาก att_students + att_attendance วันนี้
// ============================================================
$students       = [];
$attDoneToday   = false;   // ครูเช็คชื่อในระบบ att แล้วหรือยัง
$attWarning     = '';

if ($selectedClassroom !== '') {
    try {
        // นักเรียนในห้อง
        $stmtSt = $db->prepare("SELECT student_id, name FROM att_students WHERE classroom = ? ORDER BY name");
        $stmtSt->execute([$selectedClassroom]);
        $rawStudents = $stmtSt->fetchAll();

        if (!empty($rawStudents)) {
            // ดึงสถานะการเช็คชื่อวันนี้ period 1 (เข้าแถว)
            $sidList = array_column($rawStudents, 'student_id');
            $inPlaceholders = implode(',', array_fill(0, count($sidList), '?'));

            $attParams = array_merge([$selectedDate, 1], $sidList);
            $attStmt = $db->prepare("
                SELECT student_id, status, time_in
                FROM att_attendance
                WHERE date = ? AND period = ? AND student_id IN ($inPlaceholders)
            ");
            $attStmt->execute($attParams);
            $attMap = [];
            foreach ($attStmt->fetchAll() as $row) {
                $attMap[$row['student_id']] = $row;
            }
            $attDoneToday = !empty($attMap);

            foreach ($rawStudents as $s) {
                $att = $attMap[$s['student_id']] ?? null;
                $students[] = [
                    'student_id' => $s['student_id'],
                    'name'       => $s['name'],
                    'att_status' => $att ? $att['status'] : null,
                    'att_time'   => $att ? $att['time_in'] : null,
                ];
            }
        }
    } catch (Exception $e) {
        // att tables might not be accessible
    }
}

// คำนวณสถิติอัตโนมัติจาก att
$autoStats = ['total' => count($students), 'present' => 0, 'absent' => 0, 'late' => 0, 'leave' => 0];
$statusMap  = ['มา' => 'present', 'สาย' => 'late', 'ขาด' => 'absent', 'ลา' => 'leave', 'โดด' => 'absent'];
foreach ($students as $s) {
    if ($s['att_status'] && isset($statusMap[$s['att_status']])) {
        $autoStats[$statusMap[$s['att_status']]]++;
    } elseif ($s['att_status'] === 'มา') {
        $autoStats['present']++;
    }
}

// warning ถ้ายังไม่ได้เช็คชื่อในระบบ att
if (!$attDoneToday && !empty($students) && $selectedDate === date('Y-m-d')) {
    $attWarning = 'ยังไม่มีข้อมูลเช็คชื่อเข้าแถวในระบบเช็คชื่อวันนี้ ข้อมูลจำนวนนักเรียนจะต้องกรอกเอง';
}

// ค่าเริ่มต้นสำหรับ form
$formStats = $existingLog ? [
    'total'   => $existingLog['total_students'],
    'present' => $existingLog['present_count'],
    'absent'  => $existingLog['absent_count'],
    'late'    => $existingLog['late_count'],
    'leave'   => $existingLog['leave_count'],
] : $autoStats;

$formActivities = [];
if ($existingLog && $existingLog['activities']) {
    $formActivities = json_decode($existingLog['activities'], true) ?: [];
}

$statusLabels = ['draft' => 'ร่าง', 'submitted' => 'รออนุมัติ', 'approved' => 'อนุมัติแล้ว', 'revision' => 'ส่งคืน'];
$statusColors = ['draft' => 'secondary', 'submitted' => 'warning', 'approved' => 'success', 'revision' => 'danger'];

hrRenderHead('บันทึกโฮมรูมประจำวัน');
echo '<div class="d-flex">'; hrRenderSidebar(); echo '<div class="main-content flex-grow-1">'; hrRenderTopbar('บันทึกโฮมรูมประจำวัน'); echo '<div class="page-content">'; hrShowFlash();
?>

<!-- Date / Classroom selector -->
<div class="card mb-3">
  <div class="card-body py-2">
    <form method="GET" class="row g-2 align-items-end">
      <div class="col-auto">
        <label class="form-label small mb-1">วันที่</label>
        <input type="date" name="date" class="form-control form-control-sm" value="<?= h($selectedDate) ?>"
               max="<?= date('Y-m-d') ?>" onchange="this.form.submit()">
      </div>
      <?php if (count($myClassrooms) > 1 || $isAdmin): ?>
      <div class="col-auto">
        <label class="form-label small mb-1">ห้อง</label>
        <select name="classroom" class="form-select form-select-sm" onchange="this.form.submit()">
          <?php foreach ($myClassrooms as $cr): ?>
          <option value="<?= h($cr) ?>" <?= $cr === $selectedClassroom ? 'selected' : '' ?>><?= h($cr) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php endif; ?>
      <?php if ($existingLog): ?>
      <div class="col-auto">
        <span class="badge bg-<?= $statusColors[$existingLog['status']] ?> px-3 py-2">
          <?= $statusLabels[$existingLog['status']] ?>
        </span>
      </div>
      <?php endif; ?>
    </form>
  </div>
</div>

<?php if ($selectedClassroom === ''): ?>
<div class="alert alert-warning">ไม่พบข้อมูลห้องเรียนที่รับผิดชอบ กรุณาติดต่อผู้ดูแลระบบเพื่อตั้งค่าห้องเรียน</div>
<?php else: ?>

<?php if ($attWarning): ?>
<div class="alert alert-warning d-flex align-items-center gap-2 mb-3">
  <i class="bi bi-exclamation-triangle-fill fs-5"></i>
  <div>
    <strong>แจ้งเตือน:</strong> <?= h($attWarning) ?>
    <a href="<?= BASE_URL ?>/../../attendance_system/attendance.php" class="ms-2 btn btn-sm btn-warning" target="_blank">
      <i class="bi bi-arrow-right-circle me-1"></i>ไปเช็คชื่อ
    </a>
  </div>
</div>
<?php endif; ?>

<?php if ($isReadOnly): ?>
<div class="alert alert-info d-flex align-items-center gap-2">
  <i class="bi bi-lock-fill"></i>
  รายการนี้ถูกส่งแล้ว สถานะ: <strong><?= $statusLabels[$existingLog['status']] ?></strong>
  <?php if ($existingLog['status'] === 'approved'): ?>
  — ผ่านการอนุมัติแล้ว
  <?php endif; ?>
  <?php if ($existingLog['review_note']): ?>
  <span class="ms-2 text-danger">หมายเหตุ: <?= h($existingLog['review_note']) ?></span>
  <?php endif; ?>
</div>
<?php endif; ?>

<form method="POST" id="logForm">
  <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
  <input type="hidden" name="log_id" value="<?= $logId ?>">
  <input type="hidden" name="classroom" value="<?= h($selectedClassroom) ?>">
  <input type="hidden" name="log_date" value="<?= h($selectedDate) ?>">
  <input type="hidden" name="academic_year" value="<?= (int)date('Y') + 543 ?>">
  <input type="hidden" name="semester" value="1">
  <input type="hidden" name="att_synced" value="<?= $attDoneToday ? 1 : 0 ?>">

<div class="row g-3">

  <!-- คอลัมน์ซ้าย: สถิติ + กิจกรรม -->
  <div class="col-lg-8">

    <!-- สถิติการมา -->
    <div class="card mb-3">
      <div class="card-header py-2 d-flex align-items-center justify-content-between">
        <span class="fw-semibold"><i class="bi bi-people me-1 text-primary"></i>สถิติการมาโรงเรียน — <?= h($selectedClassroom) ?> วันที่ <?= date('d/m/Y', strtotime($selectedDate)) ?></span>
        <?php if ($attDoneToday): ?>
        <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>ดึงจากระบบเช็คชื่อแล้ว</span>
        <?php else: ?>
        <span class="badge bg-secondary">กรอกเอง</span>
        <?php endif; ?>
      </div>
      <div class="card-body">
        <div class="row g-3 text-center">
          <?php
          $statFields = [
            ['key'=>'total_students',  'label'=>'นร.ทั้งหมด', 'color'=>'primary',   'icon'=>'bi-people'],
            ['key'=>'present_count',   'label'=>'มาเรียน',    'color'=>'success',   'icon'=>'bi-check-circle'],
            ['key'=>'absent_count',    'label'=>'ขาด/โดด',    'color'=>'danger',    'icon'=>'bi-x-circle'],
            ['key'=>'late_count',      'label'=>'มาสาย',      'color'=>'warning',   'icon'=>'bi-clock'],
            ['key'=>'leave_count',     'label'=>'ลา',         'color'=>'info',      'icon'=>'bi-calendar-check'],
          ];
          foreach ($statFields as $sf): ?>
          <div class="col">
            <div class="border rounded p-2">
              <i class="bi <?= $sf['icon'] ?> text-<?= $sf['color'] ?>"></i>
              <div class="small text-muted mt-1"><?= $sf['label'] ?></div>
              <?php if ($isReadOnly): ?>
              <div class="fw-bold fs-5 text-<?= $sf['color'] ?>"><?= $formStats[str_replace(['_students','_count'], ['',''], $sf['key'])] ?? $existingLog[$sf['key']] ?></div>
              <?php else: ?>
              <input type="number" name="<?= $sf['key'] ?>" class="form-control form-control-sm text-center mt-1 fw-bold text-<?= $sf['color'] ?>"
                     value="<?= $sf['key'] === 'total_students' ? $formStats['total'] : $formStats[str_replace('_count','',$sf['key'])] ?>"
                     min="0" style="max-width:70px;margin:auto">
              <?php endif; ?>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <!-- ตารางนักเรียน (ถ้ามี att data) -->
    <?php if (!empty($students)): ?>
    <div class="card mb-3">
      <div class="card-header py-2">
        <span class="fw-semibold"><i class="bi bi-list-check me-1"></i>รายชื่อนักเรียน (<?= count($students) ?> คน)</span>
        <?php if (!$attDoneToday): ?>
        <small class="text-warning ms-2"><i class="bi bi-exclamation-circle me-1"></i>ยังไม่มีข้อมูลเช็คชื่อจากระบบ</small>
        <?php endif; ?>
      </div>
      <div class="card-body p-0">
        <table class="table table-sm table-hover mb-0" style="font-size:13px">
          <thead class="table-light">
            <tr>
              <th class="ps-3">#</th>
              <th>รหัส</th>
              <th>ชื่อ-สกุล</th>
              <th class="text-center">สถานะ (คาบ 1)</th>
              <th class="text-center">เวลา</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($students as $i => $s):
            $stColor = ['มา'=>'success','สาย'=>'warning','ขาด'=>'danger','ลา'=>'info','โดด'=>'danger'];
            $stIcon  = ['มา'=>'bi-check-circle','สาย'=>'bi-clock','ขาด'=>'bi-x-circle','ลา'=>'bi-calendar-check','โดด'=>'bi-exclamation-circle'];
            $sc = $stColor[$s['att_status']] ?? 'secondary';
            $si = $stIcon[$s['att_status']]  ?? 'bi-dash';
          ?>
          <tr>
            <td class="ps-3 text-muted"><?= $i+1 ?></td>
            <td class="text-muted"><?= h($s['student_id']) ?></td>
            <td><?= h($s['name']) ?></td>
            <td class="text-center">
              <?php if ($s['att_status']): ?>
              <span class="badge bg-<?= $sc ?>"><i class="bi <?= $si ?> me-1"></i><?= h($s['att_status']) ?></span>
              <?php else: ?>
              <span class="text-muted">—</span>
              <?php endif; ?>
            </td>
            <td class="text-center text-muted small"><?= $s['att_time'] ? substr($s['att_time'],0,5) : '—' ?></td>
          </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endif; ?>

    <!-- กิจกรรม -->
    <div class="card mb-3">
      <div class="card-header py-2 d-flex align-items-center justify-content-between">
        <span class="fw-semibold"><i class="bi bi-calendar-event me-1"></i>กิจกรรม / ข่าวสาร</span>
        <?php if (!$isReadOnly): ?>
        <button type="button" class="btn btn-sm btn-outline-primary" onclick="addActivity()">
          <i class="bi bi-plus me-1"></i>เพิ่มกิจกรรม
        </button>
        <?php endif; ?>
      </div>
      <div class="card-body" id="activityList">
        <?php if ($isReadOnly): ?>
          <?php if (empty($formActivities)): ?>
          <p class="text-muted small mb-0">— ไม่มีกิจกรรม —</p>
          <?php else: ?>
          <?php foreach ($formActivities as $i => $act): ?>
          <div class="border rounded p-2 mb-2 bg-light">
            <div class="fw-semibold"><?= h($act['name']) ?></div>
            <?php if ($act['desc']): ?><div class="small text-muted"><?= nl2br(h($act['desc'])) ?></div><?php endif; ?>
          </div>
          <?php endforeach; ?>
          <?php endif; ?>
        <?php else: ?>
          <?php if (empty($formActivities)): ?>
          <div id="noActivity" class="text-muted small">กดปุ่มเพิ่มกิจกรรม เพื่อบันทึกกิจกรรมที่เกิดขึ้น</div>
          <?php else: ?>
          <?php foreach ($formActivities as $i => $act): ?>
          <div class="activity-row row g-2 mb-2">
            <div class="col-md-4">
              <input type="text" name="act_name[]" class="form-control form-control-sm" placeholder="ชื่อกิจกรรม" value="<?= h($act['name']) ?>">
            </div>
            <div class="col">
              <input type="text" name="act_desc[]" class="form-control form-control-sm" placeholder="รายละเอียด (ไม่บังคับ)" value="<?= h($act['desc']) ?>">
            </div>
            <div class="col-auto">
              <button type="button" class="btn btn-outline-danger btn-sm" onclick="removeRow(this)">
                <i class="bi bi-trash"></i>
              </button>
            </div>
          </div>
          <?php endforeach; ?>
          <?php endif; ?>
        <?php endif; ?>
      </div>
    </div>

  </div><!-- /col-lg-8 -->

  <!-- คอลัมน์ขวา: โน้ต + รูปภาพ -->
  <div class="col-lg-4">

    <!-- บันทึกเพิ่มเติม -->
    <div class="card mb-3">
      <div class="card-header py-2">
        <span class="fw-semibold"><i class="bi bi-pencil-square me-1"></i>บันทึกประจำวัน</span>
      </div>
      <div class="card-body">
        <?php if ($isReadOnly): ?>
        <p class="text-muted small mb-0"><?= $existingLog['notes'] ? nl2br(h($existingLog['notes'])) : '— ไม่มีบันทึก —' ?></p>
        <?php else: ?>
        <textarea name="notes" class="form-control" rows="5"
                  placeholder="บันทึกเหตุการณ์สำคัญ ปัญหา หรือข้อสังเกตประจำวัน..."><?= h($existingLog['notes'] ?? '') ?></textarea>
        <?php endif; ?>
      </div>
    </div>

    <!-- รูปภาพ -->
    <div class="card mb-3">
      <div class="card-header py-2">
        <span class="fw-semibold"><i class="bi bi-images me-1"></i>รูปภาพกิจกรรม</span>
      </div>
      <div class="card-body">

        <?php if (!$isReadOnly && $logId > 0): ?>
        <!-- Drop zone -->
        <div id="dropZone" class="border border-2 border-dashed rounded text-center p-3 mb-3 text-muted"
             style="cursor:pointer" onclick="document.getElementById('photoInput').click()"
             ondragover="event.preventDefault();this.classList.add('border-primary')"
             ondragleave="this.classList.remove('border-primary')"
             ondrop="handleDrop(event)">
          <i class="bi bi-cloud-upload fs-4"></i>
          <p class="small mb-0 mt-1">คลิกหรือลากไฟล์มาวาง<br><span class="text-muted" style="font-size:11px">JPEG/PNG/WEBP ≤ 5MB</span></p>
        </div>
        <input type="file" id="photoInput" accept="image/*" multiple class="d-none" onchange="uploadPhotos(this.files)">
        <?php elseif (!$isReadOnly && $logId === 0): ?>
        <div class="text-muted small text-center py-2">
          <i class="bi bi-save me-1"></i>บันทึกร่างก่อนเพื่ออัปโหลดรูปภาพ
        </div>
        <?php endif; ?>

        <!-- Gallery -->
        <div id="photoGallery" class="row g-2">
          <?php foreach ($existingPhotos as $ph): ?>
          <div class="col-6" id="photo-<?= $ph['id'] ?>">
            <div class="position-relative">
              <img src="<?= BASE_URL ?>/uploads/hr/daily/<?= $logId ?>/<?= h($ph['filename']) ?>"
                   class="img-fluid rounded" style="height:80px;width:100%;object-fit:cover">
              <?php if (!$isReadOnly): ?>
              <button type="button" class="btn btn-danger btn-sm position-absolute top-0 end-0 m-1 py-0 px-1"
                      onclick="deletePhoto(<?= $ph['id'] ?>)" style="font-size:10px">
                <i class="bi bi-x"></i>
              </button>
              <?php endif; ?>
            </div>
          </div>
          <?php endforeach; ?>
        </div>

      </div>
    </div>

    <!-- Action buttons -->
    <?php if (!$isReadOnly): ?>
    <div class="d-grid gap-2">
      <button type="submit" name="action" value="draft" class="btn btn-outline-secondary">
        <i class="bi bi-save me-1"></i>บันทึกร่าง
      </button>
      <button type="submit" name="action" value="submit" class="btn btn-primary"
              onclick="return confirm('ยืนยันส่งบันทึกโฮมรูมวันนี้?')">
        <i class="bi bi-send me-1"></i>ส่งให้ผู้บริหารตรวจ
      </button>
    </div>
    <?php endif; ?>

  </div><!-- /col-lg-4 -->
</div><!-- /row -->
</form>
<?php endif; // selectedClassroom ?>

<script>
function addActivity() {
    const noMsg = document.getElementById('noActivity');
    if (noMsg) noMsg.remove();
    const list = document.getElementById('activityList');
    const row = document.createElement('div');
    row.className = 'activity-row row g-2 mb-2';
    row.innerHTML = `
        <div class="col-md-4">
            <input type="text" name="act_name[]" class="form-control form-control-sm" placeholder="ชื่อกิจกรรม">
        </div>
        <div class="col">
            <input type="text" name="act_desc[]" class="form-control form-control-sm" placeholder="รายละเอียด (ไม่บังคับ)">
        </div>
        <div class="col-auto">
            <button type="button" class="btn btn-outline-danger btn-sm" onclick="removeRow(this)">
                <i class="bi bi-trash"></i>
            </button>
        </div>`;
    list.appendChild(row);
}

function removeRow(btn) {
    btn.closest('.activity-row').remove();
}

function handleDrop(e) {
    e.preventDefault();
    document.getElementById('dropZone').classList.remove('border-primary');
    uploadPhotos(e.dataTransfer.files);
}

function uploadPhotos(files) {
    Array.from(files).forEach(file => {
        if (!file.type.startsWith('image/')) return;
        const fd = new FormData();
        fd.append('photo', file);
        fd.append('log_id', <?= $logId ?>);
        fd.append('type', 'daily');
        fetch('<?= BASE_URL ?>/homeroom/api/upload_daily_photo.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(d => { if (d.ok) appendPhoto(d.id, d.url); });
    });
}

function appendPhoto(id, url) {
    const gallery = document.getElementById('photoGallery');
    const col = document.createElement('div');
    col.className = 'col-6'; col.id = 'photo-' + id;
    col.innerHTML = `<div class="position-relative">
        <img src="${url}" class="img-fluid rounded" style="height:80px;width:100%;object-fit:cover">
        <button type="button" class="btn btn-danger btn-sm position-absolute top-0 end-0 m-1 py-0 px-1"
                onclick="deletePhoto(${id})" style="font-size:10px"><i class="bi bi-x"></i></button>
    </div>`;
    gallery.appendChild(col);
}

function deletePhoto(id) {
    if (!confirm('ลบรูปนี้?')) return;
    fetch('<?= BASE_URL ?>/homeroom/api/delete_daily_photo.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({photo_id: id})
    }).then(r => r.json()).then(d => {
        if (d.ok) document.getElementById('photo-' + id)?.remove();
    });
}
</script>

<?php echo '</div></div></div>'; hrRenderFooter(); ?>
