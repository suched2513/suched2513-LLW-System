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
if ($isAdmin) {
    // Admin: ดึงห้องทั้งหมดจาก att_students — ไม่ต้องรอผูกครูที่ปรึกษา
    try {
        $cs = $db->prepare("SELECT DISTINCT classroom FROM att_students WHERE classroom != '' ORDER BY classroom");
        $cs->execute();
        $myClassrooms = $cs->fetchAll(PDO::FETCH_COLUMN);
    } catch (Exception $e) { $myClassrooms = []; }
} else {
    // ครู: ดึงเฉพาะห้องที่ตัวเองรับผิดชอบ
    try {
        $cs = $db->prepare("SELECT classroom FROM llw_class_advisors WHERE user_id = ? ORDER BY classroom");
        $cs->execute([$u['id']]);
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

    // สถานะนักเรียนรายคน (key = student_id)
    $studentStatuses = $_POST['stu_status'] ?? [];   // ['04684' => 'มา', ...]
    $studentNotes    = $_POST['stu_note']   ?? [];

    // คำนวณสถิติจากสถานะรายคน
    $totalSt = count($studentStatuses);
    $present = $absent = $late = $leave = 0;
    foreach ($studentStatuses as $sid => $st) {
        if ($st === 'มา')                    $present++;
        elseif ($st === 'ขาด' || $st === 'โดด') $absent++;
        elseif ($st === 'สาย')               $late++;
        elseif ($st === 'ลา')                $leave++;
    }

    $newStatus   = ($postAction === 'submit') ? 'submitted' : 'draft';
    $submittedAt = ($postAction === 'submit') ? date('Y-m-d H:i:s') : null;

    try {
        $db->beginTransaction();

        $editId = (int)($_POST['log_id'] ?? 0);

        if ($editId > 0) {
            $chk = $db->prepare("SELECT teacher_id, status FROM hr_daily_logs WHERE id = ?");
            $chk->execute([$editId]);
            $existing = $chk->fetch();
            if (!$existing || (!$isAdmin && (int)$existing['teacher_id'] !== (int)$u['id'])) {
                $db->rollBack();
                flashMessage('danger', 'ไม่มีสิทธิ์แก้ไข'); header('Location: log.php'); exit;
            }
            if (!in_array($existing['status'], ['draft', 'revision'])) {
                $db->rollBack();
                flashMessage('warning', 'รายการนี้ส่งแล้ว ไม่สามารถแก้ไขได้');
                header('Location: log.php?date=' . $postDate . '&classroom=' . urlencode($postClassroom)); exit;
            }
            $db->prepare("UPDATE hr_daily_logs SET
                notes=?, activities=?, total_students=?, present_count=?, absent_count=?,
                late_count=?, leave_count=?, att_synced=1, status=?, submitted_at=?, updated_at=NOW()
                WHERE id=?")->execute([
                $postNotes, json_encode($activities, JSON_UNESCAPED_UNICODE),
                $totalSt, $present, $absent, $late, $leave,
                $newStatus, $submittedAt, $editId
            ]);
            $logId = $editId;
        } else {
            $chk = $db->prepare("SELECT id FROM hr_daily_logs WHERE classroom=? AND log_date=?");
            $chk->execute([$postClassroom, $postDate]);
            $dup = $chk->fetch();
            if ($dup) {
                $db->rollBack();
                flashMessage('warning', 'มีบันทึกของวันนี้ในห้อง ' . $postClassroom . ' แล้ว');
                header('Location: log.php?date=' . $postDate . '&classroom=' . urlencode($postClassroom) . '&id=' . $dup['id']); exit;
            }
            $db->prepare("INSERT INTO hr_daily_logs
                (teacher_id,classroom,log_date,academic_year,semester,notes,activities,
                 total_students,present_count,absent_count,late_count,leave_count,att_synced,status,submitted_at)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,1,?,?)")->execute([
                $u['id'], $postClassroom, $postDate, $postYear, $postSemester,
                $postNotes, json_encode($activities, JSON_UNESCAPED_UNICODE),
                $totalSt, $present, $absent, $late, $leave,
                $newStatus, $submittedAt
            ]);
            $logId = (int)$db->lastInsertId();
        }

        // บันทึกสถานะรายคน
        if ($logId > 0 && !empty($studentStatuses)) {
            $db->prepare("DELETE FROM hr_daily_attendance WHERE log_id = ?")->execute([$logId]);
            $ins = $db->prepare("INSERT INTO hr_daily_attendance (log_id,student_id,student_name,status,note) VALUES (?,?,?,?,?)");
            foreach ($studentStatuses as $sid => $st) {
                $sname = trim($_POST['stu_name'][$sid] ?? '');
                $snote = trim($studentNotes[$sid] ?? '');
                $ins->execute([$logId, $sid, $sname, $st, $snote]);
            }
        }

        $db->commit();

        $msg = $postAction === 'submit' ? 'ส่งบันทึกโฮมรูมเรียบร้อย' : 'บันทึกร่างแล้ว';
        flashMessage('success', $msg);
        header('Location: log.php?date=' . $postDate . '&classroom=' . urlencode($postClassroom) . '&id=' . $logId); exit;

    } catch (Exception $e) {
        if ($db->inTransaction()) $db->rollBack();
        error_log('hr_daily_log save: ' . $e->getMessage());
        flashMessage('danger', 'เกิดข้อผิดพลาด: ' . $e->getMessage());
        header('Location: log.php?date=' . $postDate . '&classroom=' . urlencode($postClassroom)); exit;
    }
}

// ============================================================
// โหลด log ที่มีอยู่
// ============================================================
$existingLog    = null;
$existingPhotos = [];
$savedAttendance = [];  // ['student_id' => ['status'=>..,'note'=>..]]

$migrationReady = false;
try {
    $db->query("SELECT 1 FROM hr_daily_logs LIMIT 1");
    $migrationReady = true;
} catch (Exception $e) {}

if ($migrationReady) {
    if ($logId > 0) {
        $stmt = $db->prepare("SELECT * FROM hr_daily_logs WHERE id = ?");
        $stmt->execute([$logId]);
        $existingLog = $stmt->fetch();
        if ($existingLog) {
            $selectedClassroom = $existingLog['classroom'];
            $selectedDate      = $existingLog['log_date'];
        }
    } else {
        try {
            $stmt = $db->prepare("SELECT * FROM hr_daily_logs WHERE classroom = ? AND log_date = ?");
            $stmt->execute([$selectedClassroom, $selectedDate]);
            $existingLog = $stmt->fetch() ?: null;
            if ($existingLog) $logId = $existingLog['id'];
        } catch (Exception $e) {}
    }

    if ($logId > 0) {
        try {
            $phStmt = $db->prepare("SELECT * FROM hr_daily_photos WHERE log_id = ? ORDER BY order_no, id");
            $phStmt->execute([$logId]);
            $existingPhotos = $phStmt->fetchAll();
        } catch (Exception $e) {}

        try {
            $attStmt = $db->prepare("SELECT student_id, status, note FROM hr_daily_attendance WHERE log_id = ?");
            $attStmt->execute([$logId]);
            foreach ($attStmt->fetchAll() as $row) {
                $savedAttendance[$row['student_id']] = $row;
            }
        } catch (Exception $e) {}
    }
}

$isReadOnly = $existingLog && !in_array($existingLog['status'], ['draft', 'revision']);

// ============================================================
// ดึงรายชื่อนักเรียน + att_attendance วันนี้ (คาบ 1) สำหรับอ้างอิง
// ============================================================
$students   = [];
$attMapExt  = [];   // สถานะจากระบบเช็คชื่อรายคาบ (อ้างอิง)

if ($selectedClassroom !== '') {
    try {
        $stmtSt = $db->prepare("SELECT student_id, name FROM att_students WHERE classroom = ? ORDER BY student_id");
        $stmtSt->execute([$selectedClassroom]);
        $rawStudents = $stmtSt->fetchAll();

        if (!empty($rawStudents)) {
            $sidList = array_column($rawStudents, 'student_id');
            $ph      = implode(',', array_fill(0, count($sidList), '?'));
            // ดึงทุก period ของวันนั้น เอา period น้อยสุดของแต่ละคน (เข้าแถว = คาบแรกที่เช็ค)
            $attStmt = $db->prepare("
                SELECT student_id, status
                FROM att_attendance
                WHERE date = ? AND student_id IN ($ph)
                ORDER BY period ASC
            ");
            $attStmt->execute(array_merge([$selectedDate], $sidList));
            foreach ($attStmt->fetchAll() as $r) {
                // เก็บเฉพาะอันแรก (period น้อยสุด) ต่อนักเรียน 1 คน
                if (!isset($attMapExt[$r['student_id']])) {
                    $attMapExt[$r['student_id']] = $r['status'];
                }
            }
        }

        foreach ($rawStudents as $s) {
            // ลำดับความสำคัญ: hr_daily_attendance (บันทึกแล้ว) > att_attendance > มา (default)
            $savedStatus = $savedAttendance[$s['student_id']]['status'] ?? null;
            $attStatus   = $attMapExt[$s['student_id']] ?? null;
            $students[]  = [
                'student_id'   => $s['student_id'],
                'name'         => $s['name'],
                'status'       => $savedStatus ?? $attStatus ?? 'มา',
                'note'         => $savedAttendance[$s['student_id']]['note'] ?? '',
                'from_att'     => ($savedStatus === null && $attStatus !== null),
            ];
        }
    } catch (Exception $e) {}
}

// สถิติ live จาก $students (ใช้ JS เพื่ออัปเดต real-time)
$initStats = ['total' => count($students), 'present' => 0, 'absent' => 0, 'late' => 0, 'leave' => 0];
foreach ($students as $s) {
    if ($s['status'] === 'มา')                        $initStats['present']++;
    elseif (in_array($s['status'], ['ขาด','โดด']))    $initStats['absent']++;
    elseif ($s['status'] === 'สาย')                   $initStats['late']++;
    elseif ($s['status'] === 'ลา')                    $initStats['leave']++;
}
if ($existingLog) {
    $initStats = [
        'total'   => $existingLog['total_students'],
        'present' => $existingLog['present_count'],
        'absent'  => $existingLog['absent_count'],
        'late'    => $existingLog['late_count'],
        'leave'   => $existingLog['leave_count'],
    ];
}

$formActivities = [];
if ($existingLog && $existingLog['activities']) {
    $formActivities = json_decode($existingLog['activities'], true) ?: [];
}

$statusLabels = ['draft'=>'ร่าง','submitted'=>'รออนุมัติ','approved'=>'อนุมัติแล้ว','revision'=>'ส่งคืน'];
$statusColors = ['draft'=>'secondary','submitted'=>'warning','approved'=>'success','revision'=>'danger'];

hrRenderHead('บันทึกโฮมรูมประจำวัน');
echo '<div class="d-flex">'; hrRenderSidebar(); echo '<div class="main-content flex-grow-1">'; hrRenderTopbar('บันทึกโฮมรูมประจำวัน'); echo '<div class="page-content">'; hrShowFlash();
?>

<?php if (!$migrationReady): ?>
<div class="alert alert-warning">
  ยังไม่ได้รัน migration — <a href="<?= BASE_URL ?>/../_migrate.php?run=1" class="alert-link">คลิกที่นี่เพื่อรัน</a>
</div>
<?php else: ?>

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
<div class="alert alert-warning">ไม่พบข้อมูลห้องเรียนที่รับผิดชอบ กรุณาติดต่อผู้ดูแลระบบ</div>
<?php else: ?>

<?php if ($isReadOnly && $existingLog): ?>
<div class="alert alert-info d-flex align-items-center gap-2">
  <i class="bi bi-lock-fill"></i>
  สถานะ: <strong><?= $statusLabels[$existingLog['status']] ?></strong>
  <?php if ($existingLog['review_note']): ?>
  — <span class="text-danger"><?= h($existingLog['review_note']) ?></span>
  <?php endif; ?>
</div>
<?php endif; ?>

<form method="POST" id="logForm">
  <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
  <input type="hidden" name="log_id"       value="<?= $logId ?>">
  <input type="hidden" name="classroom"    value="<?= h($selectedClassroom) ?>">
  <input type="hidden" name="log_date"     value="<?= h($selectedDate) ?>">
  <input type="hidden" name="academic_year" value="<?= (int)date('Y') + 543 ?>">
  <input type="hidden" name="semester"     value="1">

<div class="row g-3">

  <!-- คอลัมน์ซ้าย -->
  <div class="col-lg-8">

    <!-- เช็คชื่อเข้าแถว -->
    <div class="card mb-3">
      <div class="card-header py-2 d-flex align-items-center justify-content-between flex-wrap gap-2">
        <span class="fw-semibold">
          <i class="bi bi-people-fill me-1 text-primary"></i>
          เช็คชื่อเข้าแถว — <?= h($selectedClassroom) ?>
          <span class="text-muted fw-normal ms-1" style="font-size:13px"><?= date('d/m/Y', strtotime($selectedDate)) ?></span>
        </span>
        <?php if (!$isReadOnly && !empty($students)): ?>
        <div class="d-flex gap-2 flex-wrap">
          <button type="button" class="btn btn-outline-primary btn-sm" onclick="syncFromAtt()" id="syncBtn">
            <i class="bi bi-arrow-repeat me-1"></i>ดึงข้อมูลจากระบบเช็คชื่อ
          </button>
          <button type="button" class="btn btn-outline-success btn-sm" onclick="setAllStatus('มา')">
            <i class="bi bi-check-all me-1"></i>มาทั้งหมด
          </button>
        </div>
        <?php endif; ?>
      </div>

      <!-- KPI row -->
      <div class="card-body border-bottom py-2">
        <div class="row g-2 text-center" id="statsRow">
          <?php
          $statDefs = [
            ['key'=>'present','label'=>'มาเรียน','color'=>'success','icon'=>'bi-check-circle-fill'],
            ['key'=>'absent', 'label'=>'ขาด/โดด','color'=>'danger', 'icon'=>'bi-x-circle-fill'],
            ['key'=>'late',   'label'=>'มาสาย',  'color'=>'warning','icon'=>'bi-clock-fill'],
            ['key'=>'leave',  'label'=>'ลา',      'color'=>'info',   'icon'=>'bi-calendar-check-fill'],
            ['key'=>'total',  'label'=>'ทั้งหมด', 'color'=>'secondary','icon'=>'bi-people-fill'],
          ];
          foreach ($statDefs as $sd): ?>
          <div class="col">
            <div class="border rounded py-2 px-1">
              <i class="bi <?= $sd['icon'] ?> text-<?= $sd['color'] ?>"></i>
              <div class="fw-bold text-<?= $sd['color'] ?>" id="stat_<?= $sd['key'] ?>"><?= $initStats[$sd['key']] ?></div>
              <div class="text-muted" style="font-size:11px"><?= $sd['label'] ?></div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- รายชื่อ -->
      <?php if (empty($students)): ?>
      <div class="card-body text-center text-muted py-4">
        <i class="bi bi-person-x fs-3"></i>
        <p class="mt-2 mb-0">ไม่พบข้อมูลนักเรียนในห้อง <?= h($selectedClassroom) ?></p>
      </div>
      <?php else: ?>
      <div class="card-body p-0">
        <table class="table table-sm table-hover mb-0 align-middle" style="font-size:13px">
          <thead class="table-light sticky-top">
            <tr>
              <th class="ps-3" style="width:40px">#</th>
              <th>ชื่อ-สกุล</th>
              <th class="text-center" style="width:220px">สถานะ</th>
              <th style="width:140px">หมายเหตุ</th>
            </tr>
          </thead>
          <tbody id="studentTable">
          <?php foreach ($students as $i => $s):
            $sc = ['มา'=>'success','ขาด'=>'danger','สาย'=>'warning','ลา'=>'info','โดด'=>'danger'];
          ?>
          <tr id="row_<?= h($s['student_id']) ?>" class="<?= $s['status'] !== 'มา' ? 'table-'.(($sc[$s['status']]??'secondary')) : '' ?>" style="opacity:<?= $s['status']==='มา'?'1':'1' ?>">
            <td class="ps-3 text-muted"><?= $i+1 ?></td>
            <td>
              <div class="fw-semibold"><?= h($s['name']) ?></div>
              <?php if ($s['from_att']): ?>
              <span class="badge bg-light text-secondary border" style="font-size:10px">
                <i class="bi bi-arrow-down-circle me-1"></i>จากระบบเช็คชื่อ
              </span>
              <?php endif; ?>
              <input type="hidden" name="stu_name[<?= h($s['student_id']) ?>]" value="<?= h($s['name']) ?>">
            </td>
            <td class="text-center">
              <?php if ($isReadOnly): ?>
              <span class="badge bg-<?= $sc[$s['status']] ?? 'secondary' ?>"><?= h($s['status']) ?></span>
              <?php else: ?>
              <input type="hidden" name="stu_status[<?= h($s['student_id']) ?>]"
                     id="stu_<?= h($s['student_id']) ?>" value="<?= h($s['status']) ?>">
              <div class="btn-group btn-group-sm" role="group">
                <?php foreach (['มา'=>'success','สาย'=>'warning','ขาด'=>'danger','ลา'=>'info'] as $st => $col): ?>
                <button type="button"
                        class="btn btn-<?= $s['status']===$st ? $col : 'outline-'.$col ?> status-btn"
                        data-sid="<?= h($s['student_id']) ?>" data-st="<?= $st ?>" data-col="<?= $col ?>"
                        onclick="setStatus('<?= h($s['student_id']) ?>','<?= $st ?>','<?= $col ?>',this)">
                  <?= $st ?>
                </button>
                <?php endforeach; ?>
              </div>
              <?php endif; ?>
            </td>
            <td>
              <?php if ($isReadOnly): ?>
              <span class="text-muted small"><?= h($s['note']) ?></span>
              <?php else: ?>
              <input type="text" name="stu_note[<?= h($s['student_id']) ?>]"
                     class="form-control form-control-sm" placeholder="โน้ต..."
                     value="<?= h($s['note']) ?>" style="font-size:12px">
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>

    <!-- กิจกรรม -->
    <div class="card mb-3">
      <div class="card-header py-2 d-flex align-items-center justify-content-between">
        <span class="fw-semibold"><i class="bi bi-calendar-event me-1"></i>กิจกรรม / ข่าวสาร</span>
        <?php if (!$isReadOnly): ?>
        <button type="button" class="btn btn-sm btn-outline-primary" onclick="addActivity()">
          <i class="bi bi-plus me-1"></i>เพิ่ม
        </button>
        <?php endif; ?>
      </div>
      <div class="card-body" id="activityList">
        <?php if ($isReadOnly): ?>
          <?php if (empty($formActivities)): ?>
          <p class="text-muted small mb-0">— ไม่มีกิจกรรม —</p>
          <?php else: ?>
          <?php foreach ($formActivities as $act): ?>
          <div class="border rounded p-2 mb-2 bg-light">
            <div class="fw-semibold"><?= h($act['name']) ?></div>
            <?php if ($act['desc']): ?><div class="small text-muted"><?= nl2br(h($act['desc'])) ?></div><?php endif; ?>
          </div>
          <?php endforeach; ?>
          <?php endif; ?>
        <?php else: ?>
          <?php if (empty($formActivities)): ?>
          <div id="noActivity" class="text-muted small">กดปุ่มเพิ่มเพื่อบันทึกกิจกรรมที่เกิดขึ้นในวันนี้</div>
          <?php else: ?>
          <?php foreach ($formActivities as $act): ?>
          <div class="activity-row row g-2 mb-2">
            <div class="col-md-4">
              <input type="text" name="act_name[]" class="form-control form-control-sm" placeholder="ชื่อกิจกรรม" value="<?= h($act['name']) ?>">
            </div>
            <div class="col">
              <input type="text" name="act_desc[]" class="form-control form-control-sm" placeholder="รายละเอียด" value="<?= h($act['desc']) ?>">
            </div>
            <div class="col-auto">
              <button type="button" class="btn btn-outline-danger btn-sm" onclick="this.closest('.activity-row').remove()">
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

  <!-- คอลัมน์ขวา -->
  <div class="col-lg-4">

    <!-- บันทึก -->
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
        <div id="dropZone" class="border border-2 border-dashed rounded text-center p-3 mb-3 text-muted"
             style="cursor:pointer" onclick="document.getElementById('photoInput').click()"
             ondragover="event.preventDefault();this.classList.add('border-primary')"
             ondragleave="this.classList.remove('border-primary')"
             ondrop="handleDrop(event)">
          <i class="bi bi-cloud-upload fs-4"></i>
          <p class="small mb-0 mt-1">คลิกหรือลากไฟล์มาวาง<br><span style="font-size:11px">JPEG/PNG ≤ 5MB</span></p>
        </div>
        <input type="file" id="photoInput" accept="image/*" multiple class="d-none" onchange="uploadPhotos(this.files)">
        <?php elseif (!$isReadOnly): ?>
        <div class="text-muted small text-center py-2"><i class="bi bi-save me-1"></i>บันทึกร่างก่อนเพื่ออัปโหลดรูป</div>
        <?php endif; ?>

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
<?php endif; // migrationReady ?>

<style>
.status-btn { min-width: 44px; font-size: 12px; padding: 2px 6px; }
tr.absent-row { background: rgba(220,53,69,.04); }
</style>

<script>
// สถานะนักเรียน initial จาก PHP
const initStatuses = <?= json_encode(array_column(array_combine(
    array_column($students,'student_id'),
    $students
), 'status', 'student_id'), JSON_UNESCAPED_UNICODE) ?>;

let currentStats = <?= json_encode($initStats) ?>;

function setStatus(sid, st, col, btn) {
    // อัปเดต hidden input
    document.getElementById('stu_' + sid).value = st;

    // อัปเดตปุ่ม
    const row = document.getElementById('row_' + sid);
    row.querySelectorAll('.status-btn').forEach(b => {
        const bc = b.dataset.col;
        b.className = b.dataset.st === st
            ? 'btn btn-' + bc + ' btn-sm status-btn'
            : 'btn btn-outline-' + bc + ' btn-sm status-btn';
    });

    // สีแถว
    row.className = st !== 'มา' ? 'table-' + col : '';

    recalcStats();
}

function setAllStatus(st) {
    document.querySelectorAll('[id^="stu_"]').forEach(inp => {
        const sid = inp.id.replace('stu_', '');
        const col = {มา:'success',สาย:'warning',ขาด:'danger',ลา:'info'}[st] || 'secondary';
        const btn = document.querySelector(`.status-btn[data-sid="${sid}"][data-st="${st}"]`);
        setStatus(sid, st, col, btn);
    });
}

function recalcStats() {
    let p=0, a=0, l=0, lv=0;
    document.querySelectorAll('[id^="stu_"]').forEach(inp => {
        const v = inp.value;
        if (v==='มา') p++;
        else if (v==='ขาด'||v==='โดด') a++;
        else if (v==='สาย') l++;
        else if (v==='ลา') lv++;
    });
    const total = p+a+l+lv;
    document.getElementById('stat_present').textContent = p;
    document.getElementById('stat_absent').textContent  = a;
    document.getElementById('stat_late').textContent    = l;
    document.getElementById('stat_leave').textContent   = lv;
    document.getElementById('stat_total').textContent   = total;
}

function syncFromAtt() {
    const btn = document.getElementById('syncBtn');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>กำลังดึงข้อมูล...';

    const classroom = <?= json_encode($selectedClassroom) ?>;
    const date      = <?= json_encode($selectedDate) ?>;

    fetch(`<?= BASE_URL ?>/homeroom/api/sync_att.php?classroom=${encodeURIComponent(classroom)}&date=${date}`)
        .then(r => r.json())
        .then(d => {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-arrow-repeat me-1"></i>ดึงข้อมูลจากระบบเช็คชื่อ';

            if (!d.ok) {
                alert('ไม่สามารถดึงข้อมูลได้: ' + d.msg);
                return;
            }
            if (!d.has_data) {
                alert('ไม่พบข้อมูลเช็คชื่อในระบบสำหรับ ' + classroom + ' วันที่ ' + date);
                return;
            }

            // อัปเดตสถานะแต่ละคน
            let updated = 0;
            d.students.forEach(s => {
                if (s.status && document.getElementById('stu_' + s.student_id)) {
                    const col = {มา:'success',สาย:'warning',ขาด:'danger',ลา:'info',โดด:'danger'}[s.status] || 'secondary';
                    setStatus(s.student_id, s.status, col, null);
                    updated++;
                }
            });

            // อัปเดต badge "จากระบบเช็คชื่อ"
            d.students.forEach(s => {
                if (!s.status) return;
                const row = document.getElementById('row_' + s.student_id);
                if (!row) return;
                let badge = row.querySelector('.att-badge');
                if (!badge) {
                    const nameCell = row.querySelector('td:nth-child(2)');
                    badge = document.createElement('span');
                    badge.className = 'badge bg-light text-secondary border att-badge ms-1';
                    badge.style.fontSize = '10px';
                    badge.innerHTML = '<i class="bi bi-arrow-down-circle me-1"></i>จากระบบเช็คชื่อ' + (s.period ? ' คาบ'+s.period : '');
                    nameCell.appendChild(badge);
                }
            });

            alert(`ดึงข้อมูลสำเร็จ ${updated} คน\nมา: ${d.counts['มา']||0}  ขาด: ${d.counts['ขาด']||0}  สาย: ${d.counts['สาย']||0}  ลา: ${d.counts['ลา']||0}`);
        })
        .catch(() => {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-arrow-repeat me-1"></i>ดึงข้อมูลจากระบบเช็คชื่อ';
            alert('เกิดข้อผิดพลาดในการเชื่อมต่อ');
        });
}

function addActivity() {
    const noMsg = document.getElementById('noActivity');
    if (noMsg) noMsg.remove();
    const row = document.createElement('div');
    row.className = 'activity-row row g-2 mb-2';
    row.innerHTML = `
        <div class="col-md-4"><input type="text" name="act_name[]" class="form-control form-control-sm" placeholder="ชื่อกิจกรรม"></div>
        <div class="col"><input type="text" name="act_desc[]" class="form-control form-control-sm" placeholder="รายละเอียด"></div>
        <div class="col-auto"><button type="button" class="btn btn-outline-danger btn-sm" onclick="this.closest('.activity-row').remove()"><i class="bi bi-trash"></i></button></div>`;
    document.getElementById('activityList').appendChild(row);
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
        fetch('<?= BASE_URL ?>/homeroom/api/upload_daily_photo.php', {method:'POST',body:fd})
            .then(r=>r.json()).then(d=>{if(d.ok)appendPhoto(d.id,d.url);});
    });
}
function appendPhoto(id, url) {
    const col = document.createElement('div');
    col.className='col-6'; col.id='photo-'+id;
    col.innerHTML=`<div class="position-relative"><img src="${url}" class="img-fluid rounded" style="height:80px;width:100%;object-fit:cover">
        <button type="button" class="btn btn-danger btn-sm position-absolute top-0 end-0 m-1 py-0 px-1" onclick="deletePhoto(${id})" style="font-size:10px"><i class="bi bi-x"></i></button></div>`;
    document.getElementById('photoGallery').appendChild(col);
}
function deletePhoto(id) {
    if (!confirm('ลบรูปนี้?')) return;
    fetch('<?= BASE_URL ?>/homeroom/api/delete_daily_photo.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({photo_id:id})})
        .then(r=>r.json()).then(d=>{if(d.ok)document.getElementById('photo-'+id)?.remove();});
}
</script>

<?php echo '</div></div></div>'; hrRenderFooter(); ?>
