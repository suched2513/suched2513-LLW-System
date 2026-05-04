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

// ห้องเรียนของครู
$myClassrooms = [];
try {
    $cs = $db->prepare("SELECT classroom FROM llw_class_advisors WHERE user_id = ? ORDER BY classroom");
    $cs->execute([$u['id']]);
    $myClassrooms = $cs->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) { $myClassrooms = []; }

// โหลดรายงานที่มีอยู่
$report     = null;
$activities = [];
$photos     = [];

if ($id > 0) {
    $stmt = $db->prepare("SELECT * FROM hr_reports WHERE id = ?");
    $stmt->execute([$id]);
    $report = $stmt->fetch();

    if (!$report) { flashMessage('danger', 'ไม่พบรายงาน'); header('Location: list.php'); exit; }

    if (!$isAdmin && (int)$report['teacher_id'] !== (int)$u['id']) {
        http_response_code(403);
        die('<p class="p-4">ไม่มีสิทธิ์แก้ไขรายงานนี้</p>');
    }

    if (!in_array($report['status'], ['draft', 'revision'])) {
        header('Location: view.php?id=' . $id); exit;
    }

    $actStmt = $db->prepare("SELECT * FROM hr_report_activities WHERE report_id = ? ORDER BY order_no, activity_date");
    $actStmt->execute([$id]);
    $activities = $actStmt->fetchAll();

    $phStmt = $db->prepare("SELECT * FROM hr_report_photos WHERE report_id = ? ORDER BY order_no");
    $phStmt->execute([$id]);
    $photos = $phStmt->fetchAll();
}

// ==============================
// POST handler
// ==============================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $classroom         = trim($_POST['classroom'] ?? '');
    $reportType        = ($_POST['report_type'] ?? '') === 'monthly' ? 'monthly' : 'weekly';
    $periodStart       = $_POST['period_start'] ?? '';
    $periodEnd         = $_POST['period_end'] ?? '';
    $academicYear      = (int)($_POST['academic_year'] ?? 2568);
    $semester          = max(1, min(2, (int)($_POST['semester'] ?? 1)));
    $totalStudents     = (int)($_POST['total_students'] ?? 0);
    $schoolDays        = (int)($_POST['school_days'] ?? 0);
    $presentCount      = (int)($_POST['present_count'] ?? 0);
    $absentCount       = (int)($_POST['absent_count'] ?? 0);
    $lateCount         = (int)($_POST['late_count'] ?? 0);
    $activitiesSummary = trim($_POST['activities_summary'] ?? '');
    $problemsNoted     = trim($_POST['problems_noted'] ?? '');
    $suggestions       = trim($_POST['suggestions'] ?? '');
    $nextPeriodPlan    = trim($_POST['next_period_plan'] ?? '');
    $action            = $_POST['save_action'] ?? 'draft';

    if (!$classroom || !$periodStart || !$periodEnd) {
        flashMessage('danger', 'กรุณากรอกข้อมูลห้องเรียนและช่วงเวลาให้ครบ');
        header('Location: edit.php' . ($id ? '?id=' . $id : '')); exit;
    }
    if (!$isAdmin && $myClassrooms && !in_array($classroom, $myClassrooms)) {
        flashMessage('danger', 'ไม่มีสิทธิ์สำหรับห้อง ' . $classroom);
        header('Location: list.php'); exit;
    }

    // บันทึก captions ของภาพที่มีอยู่
    $captionUpdates = $_POST['photo_caption'] ?? [];

    $status      = ($action === 'submit') ? 'submitted' : 'draft';
    $submittedAt = ($action === 'submit') ? date('Y-m-d H:i:s') : null;

    try {
        $db->beginTransaction();

        if ($id > 0) {
            $db->prepare("
                UPDATE hr_reports SET
                    classroom=?, report_type=?, period_start=?, period_end=?,
                    academic_year=?, semester=?,
                    total_students=?, school_days=?, present_count=?, absent_count=?, late_count=?,
                    activities_summary=?, problems_noted=?, suggestions=?, next_period_plan=?,
                    status=?,
                    submitted_at = CASE WHEN status != 'submitted' THEN ? ELSE submitted_at END,
                    updated_at=NOW()
                WHERE id=?
            ")->execute([
                $classroom, $reportType, $periodStart, $periodEnd,
                $academicYear, $semester,
                $totalStudents, $schoolDays, $presentCount, $absentCount, $lateCount,
                $activitiesSummary, $problemsNoted, $suggestions, $nextPeriodPlan,
                $status, $submittedAt, $id,
            ]);
        } else {
            $db->prepare("
                INSERT INTO hr_reports
                    (classroom, teacher_id, report_type, period_start, period_end, academic_year, semester,
                     total_students, school_days, present_count, absent_count, late_count,
                     activities_summary, problems_noted, suggestions, next_period_plan, status, submitted_at)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
            ")->execute([
                $classroom, $u['id'], $reportType, $periodStart, $periodEnd, $academicYear, $semester,
                $totalStudents, $schoolDays, $presentCount, $absentCount, $lateCount,
                $activitiesSummary, $problemsNoted, $suggestions, $nextPeriodPlan, $status, $submittedAt,
            ]);
            $id = (int)$db->lastInsertId();
        }

        // บันทึกกิจกรรม (ลบเก่า แล้ว insert ใหม่)
        $db->prepare("DELETE FROM hr_report_activities WHERE report_id = ?")->execute([$id]);

        $actNames        = $_POST['act_name']         ?? [];
        $actDates        = $_POST['act_date']         ?? [];
        $actDescs        = $_POST['act_desc']         ?? [];
        $actParticipants = $_POST['act_participants'] ?? [];
        $actOutcomes     = $_POST['act_outcome']      ?? [];

        $actIns = $db->prepare("
            INSERT INTO hr_report_activities
                (report_id, activity_date, activity_name, description, participants_count, outcome, order_no)
            VALUES (?,?,?,?,?,?,?)
        ");
        foreach ($actNames as $i => $name) {
            if (trim($name) === '') continue;
            $actIns->execute([
                $id,
                $actDates[$i] ?: null,
                $name,
                $actDescs[$i] ?? '',
                ((int)($actParticipants[$i] ?? 0)) ?: null,
                $actOutcomes[$i] ?? '',
                $i + 1,
            ]);
        }

        // อัปเดต captions ภาพ
        $capStmt = $db->prepare("UPDATE hr_report_photos SET caption=? WHERE id=? AND report_id=?");
        foreach ($captionUpdates as $pid => $cap) {
            $capStmt->execute([trim($cap), (int)$pid, $id]);
        }

        $db->commit();

        if ($action === 'submit') {
            auditLog('hr_report_submit', 'hr_reports', $id);
            flashMessage('success', 'ส่งรายงานเรียบร้อยแล้ว รอผู้บริหารตรวจสอบ');
            header('Location: list.php'); exit;
        }

        flashMessage('success', 'บันทึกร่างเรียบร้อย');
        header('Location: edit.php?id=' . $id); exit;

    } catch (Exception $e) {
        if ($db->inTransaction()) $db->rollBack();
        error_log('[HR] edit save error: ' . $e->getMessage());
        flashMessage('danger', 'เกิดข้อผิดพลาด กรุณาลองใหม่');
        header('Location: edit.php' . ($id ? '?id=' . $id : '')); exit;
    }
}

// ==============================
// ค่า default สำหรับฟอร์มใหม่
// ==============================
$defaultClassroom = $report['classroom'] ?? ($myClassrooms[0] ?? '');
$defaultType      = $report['report_type'] ?? 'weekly';
$defaultStart     = $report['period_start'] ?? '';
$defaultEnd       = $report['period_end'] ?? '';
$defaultYear      = $report['academic_year'] ?? 2568;
$defaultSemester  = $report['semester'] ?? 1;

$pageTitle = $id > 0 ? 'แก้ไขรายงานโฮมรูม' : 'สร้างรายงานโฮมรูม';

renderHead($pageTitle);
echo '<div class="d-flex">'; renderSidebar('/homeroom/reports/list.php'); echo '<div class="main-content flex-grow-1">'; renderTopbar($pageTitle); echo '<div class="page-content">'; showFlash();
?>

<?php if ($report && $report['status'] === 'revision' && $report['review_note']): ?>
<div class="alert alert-danger mb-3">
  <i class="bi bi-arrow-return-left me-2"></i>
  <strong>ส่งคืนจากผู้บริหาร:</strong> <?= h($report['review_note']) ?>
</div>
<?php endif; ?>

<form method="POST" id="reportForm">
  <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">

  <div class="row g-3">
    <!-- Left: form sections -->
    <div class="col-lg-8">

      <!-- ส่วนที่ 1: ข้อมูลงวด -->
      <div class="card mb-3">
        <div class="card-header fw-bold"><i class="bi bi-calendar3 me-2"></i>ข้อมูลงวดรายงาน</div>
        <div class="card-body">
          <div class="row g-3">
            <div class="col-md-4">
              <label class="form-label">ห้องเรียน <span class="text-danger">*</span></label>
              <?php if ($isAdmin || count($myClassrooms) > 1): ?>
              <input type="text" name="classroom" class="form-control" value="<?= h($defaultClassroom) ?>" required placeholder="เช่น ม.3/2" list="classroomList">
              <datalist id="classroomList">
                <?php foreach ($myClassrooms as $c): ?>
                  <option value="<?= h($c) ?>">
                <?php endforeach; ?>
              </datalist>
              <?php else: ?>
              <input type="text" name="classroom" class="form-control" value="<?= h($defaultClassroom) ?>" readonly required>
              <?php endif; ?>
            </div>
            <div class="col-md-4">
              <label class="form-label">ประเภทรายงาน</label>
              <select name="report_type" id="reportType" class="form-select" onchange="autoFillPeriod()">
                <option value="weekly"  <?= $defaultType === 'weekly'  ? 'selected' : '' ?>>รายสัปดาห์</option>
                <option value="monthly" <?= $defaultType === 'monthly' ? 'selected' : '' ?>>รายเดือน</option>
              </select>
            </div>
            <div class="col-md-2">
              <label class="form-label">ปีการศึกษา</label>
              <select name="academic_year" class="form-select">
                <?php for ($y = 2567; $y <= 2570; $y++): ?>
                  <option value="<?= $y ?>" <?= $defaultYear == $y ? 'selected' : '' ?>><?= $y ?></option>
                <?php endfor; ?>
              </select>
            </div>
            <div class="col-md-2">
              <label class="form-label">ภาคเรียน</label>
              <select name="semester" class="form-select">
                <option value="1" <?= $defaultSemester == 1 ? 'selected' : '' ?>>1</option>
                <option value="2" <?= $defaultSemester == 2 ? 'selected' : '' ?>>2</option>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label">วันที่เริ่มต้น <span class="text-danger">*</span></label>
              <input type="date" name="period_start" id="periodStart" class="form-control" value="<?= h($defaultStart) ?>" required onchange="autoFillEnd()">
            </div>
            <div class="col-md-6">
              <label class="form-label">วันที่สิ้นสุด <span class="text-danger">*</span></label>
              <input type="date" name="period_end" id="periodEnd" class="form-control" value="<?= h($defaultEnd) ?>" required>
            </div>
          </div>
        </div>
      </div>

      <!-- ส่วนที่ 2: สถิติการเข้าแถว -->
      <div class="card mb-3">
        <div class="card-header fw-bold"><i class="bi bi-bar-chart me-2"></i>สถิติการเข้าแถว</div>
        <div class="card-body">
          <div class="row g-3">
            <div class="col-6 col-md-3">
              <label class="form-label">นักเรียนทั้งหมด</label>
              <input type="number" name="total_students" class="form-control" min="0" value="<?= (int)($report['total_students'] ?? 0) ?>">
            </div>
            <div class="col-6 col-md-3">
              <label class="form-label">วันเรียนในงวด</label>
              <input type="number" name="school_days" class="form-control" min="0" value="<?= (int)($report['school_days'] ?? 0) ?>">
            </div>
            <div class="col-6 col-md-2">
              <label class="form-label">มาเรียน (ครั้ง)</label>
              <input type="number" name="present_count" class="form-control text-success" min="0" value="<?= (int)($report['present_count'] ?? 0) ?>">
            </div>
            <div class="col-6 col-md-2">
              <label class="form-label">ขาดเรียน (ครั้ง)</label>
              <input type="number" name="absent_count" class="form-control text-danger" min="0" value="<?= (int)($report['absent_count'] ?? 0) ?>">
            </div>
            <div class="col-6 col-md-2">
              <label class="form-label">สาย (ครั้ง)</label>
              <input type="number" name="late_count" class="form-control text-warning" min="0" value="<?= (int)($report['late_count'] ?? 0) ?>">
            </div>
          </div>
        </div>
      </div>

      <!-- ส่วนที่ 3: กิจกรรม -->
      <div class="card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">
          <span class="fw-bold"><i class="bi bi-list-task me-2"></i>กิจกรรมในงวดนี้</span>
          <button type="button" class="btn btn-sm btn-outline-primary" onclick="addActivityRow()">
            <i class="bi bi-plus-circle me-1"></i>เพิ่มกิจกรรม
          </button>
        </div>
        <div class="card-body p-2">
          <div class="table-responsive">
            <table class="table table-sm mb-1" id="activityTable">
              <thead class="table-light">
                <tr>
                  <th style="width:120px">วันที่</th>
                  <th>ชื่อกิจกรรม <span class="text-danger">*</span></th>
                  <th>รายละเอียด</th>
                  <th style="width:80px">ผู้ร่วม</th>
                  <th>ผลที่ได้</th>
                  <th style="width:36px"></th>
                </tr>
              </thead>
              <tbody id="activityRows">
                <?php if (empty($activities)): ?>
                <tr class="activity-row">
                  <td><input type="date" name="act_date[]" class="form-control form-control-sm"></td>
                  <td><input type="text" name="act_name[]" class="form-control form-control-sm" placeholder="ชื่อกิจกรรม"></td>
                  <td><input type="text" name="act_desc[]" class="form-control form-control-sm" placeholder="รายละเอียด"></td>
                  <td><input type="number" name="act_participants[]" class="form-control form-control-sm" min="0" placeholder="จำนวน"></td>
                  <td><input type="text" name="act_outcome[]" class="form-control form-control-sm" placeholder="ผล"></td>
                  <td><button type="button" class="btn btn-sm btn-link text-danger p-0" onclick="removeRow(this)"><i class="bi bi-trash"></i></button></td>
                </tr>
                <?php else: ?>
                <?php foreach ($activities as $act): ?>
                <tr class="activity-row">
                  <td><input type="date" name="act_date[]" class="form-control form-control-sm" value="<?= h($act['activity_date'] ?? '') ?>"></td>
                  <td><input type="text" name="act_name[]" class="form-control form-control-sm" value="<?= h($act['activity_name']) ?>" placeholder="ชื่อกิจกรรม"></td>
                  <td><input type="text" name="act_desc[]" class="form-control form-control-sm" value="<?= h($act['description'] ?? '') ?>" placeholder="รายละเอียด"></td>
                  <td><input type="number" name="act_participants[]" class="form-control form-control-sm" min="0" value="<?= h($act['participants_count'] ?? '') ?>" placeholder="จำนวน"></td>
                  <td><input type="text" name="act_outcome[]" class="form-control form-control-sm" value="<?= h($act['outcome'] ?? '') ?>" placeholder="ผล"></td>
                  <td><button type="button" class="btn btn-sm btn-link text-danger p-0" onclick="removeRow(this)"><i class="bi bi-trash"></i></button></td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- ส่วนที่ 4: เนื้อหาสรุป -->
      <div class="card mb-3">
        <div class="card-header fw-bold"><i class="bi bi-pencil-square me-2"></i>บทสรุปและแผนงาน</div>
        <div class="card-body">
          <div class="mb-3">
            <label class="form-label fw-semibold">สรุปกิจกรรมโดยรวม</label>
            <textarea name="activities_summary" class="form-control" rows="3"
              placeholder="สรุปภาพรวมกิจกรรมที่ดำเนินการในงวดนี้..."><?= h($report['activities_summary'] ?? '') ?></textarea>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">ปัญหาที่พบ / อุปสรรค</label>
            <textarea name="problems_noted" class="form-control" rows="3"
              placeholder="ระบุปัญหาหรืออุปสรรคที่พบ (ถ้ามี)..."><?= h($report['problems_noted'] ?? '') ?></textarea>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">ข้อเสนอแนะ</label>
            <textarea name="suggestions" class="form-control" rows="3"
              placeholder="ข้อเสนอแนะต่อผู้บริหารหรือหน่วยงาน..."><?= h($report['suggestions'] ?? '') ?></textarea>
          </div>
          <div class="mb-0">
            <label class="form-label fw-semibold">แผนงานงวดถัดไป</label>
            <textarea name="next_period_plan" class="form-control" rows="3"
              placeholder="แผนกิจกรรมที่วางไว้สำหรับงวดถัดไป..."><?= h($report['next_period_plan'] ?? '') ?></textarea>
          </div>
        </div>
      </div>

    </div><!-- /col-lg-8 -->

    <!-- Right: photos + actions -->
    <div class="col-lg-4">

      <!-- Action card -->
      <div class="card mb-3 border-primary">
        <div class="card-header bg-primary text-white fw-bold">
          <i class="bi bi-send me-2"></i>บันทึก / ส่งรายงาน
        </div>
        <div class="card-body">
          <?php if ($id > 0): ?>
          <div class="mb-2 small text-muted">
            <i class="bi bi-clock me-1"></i>
            สร้างเมื่อ: <?= formatDate($report['created_at'], true) ?>
            <?php if ($report['updated_at']): ?>
              <br>แก้ไขล่าสุด: <?= formatDate($report['updated_at'], true) ?>
            <?php endif; ?>
          </div>
          <?php endif; ?>
          <div class="d-grid gap-2">
            <button type="submit" name="save_action" value="draft" class="btn btn-outline-secondary">
              <i class="bi bi-floppy me-1"></i>บันทึกร่าง
            </button>
            <button type="submit" name="save_action" value="submit" class="btn btn-success"
              onclick="return confirm('ยืนยันการส่งรายงานให้ผู้บริหารตรวจสอบ?')">
              <i class="bi bi-send me-1"></i>ส่งรายงาน
            </button>
          </div>
          <?php if ($id > 0): ?>
          <div class="mt-2">
            <a href="view.php?id=<?= $id ?>" class="btn btn-light btn-sm w-100">
              <i class="bi bi-eye me-1"></i>ดูตัวอย่าง
            </a>
          </div>
          <?php endif; ?>
          <div class="mt-2">
            <a href="list.php" class="btn btn-light btn-sm w-100">← กลับรายการ</a>
          </div>
        </div>
      </div>

      <!-- Photos card -->
      <div class="card">
        <div class="card-header fw-bold d-flex justify-content-between align-items-center">
          <span><i class="bi bi-images me-2"></i>ภาพกิจกรรม</span>
          <span class="badge bg-secondary" id="photoCount"><?= count($photos) ?></span>
        </div>
        <div class="card-body">
          <?php if ($id > 0): ?>
          <!-- Upload zone -->
          <div id="uploadZone"
               class="border border-2 border-dashed rounded-3 text-center p-3 mb-3 cursor-pointer"
               style="border-color:#cbd5e1 !important;cursor:pointer"
               onclick="document.getElementById('photoInput').click()">
            <i class="bi bi-cloud-upload" style="font-size:1.8rem;color:#94a3b8"></i>
            <p class="small text-muted mb-0 mt-1">คลิกหรือลากภาพมาวาง<br>JPG / PNG / WebP — สูงสุด 5MB/ภาพ</p>
          </div>
          <input type="file" id="photoInput" accept="image/jpeg,image/png,image/webp"
                 multiple style="display:none" onchange="uploadPhotos(this.files)">

          <!-- Upload progress -->
          <div id="uploadProgress" class="d-none mb-2">
            <div class="progress" style="height:6px">
              <div class="progress-bar progress-bar-striped progress-bar-animated bg-primary w-100"></div>
            </div>
            <small class="text-muted" id="uploadStatus">กำลังอัปโหลด...</small>
          </div>

          <!-- Photo gallery -->
          <div id="photoGallery">
            <?php foreach ($photos as $ph): ?>
            <div class="photo-item mb-2" id="photo-<?= $ph['id'] ?>">
              <div class="position-relative">
                <img src="<?= BASE_URL ?>/uploads/<?= h($ph['file_path']) ?>"
                     class="img-fluid rounded" style="max-height:160px;width:100%;object-fit:cover"
                     alt="<?= h($ph['caption'] ?? '') ?>">
                <button type="button" class="btn btn-danger btn-sm position-absolute top-0 end-0 m-1 p-1 lh-1"
                        onclick="deletePhoto(<?= $ph['id'] ?>)" title="ลบภาพ">
                  <i class="bi bi-x"></i>
                </button>
              </div>
              <input type="text" name="photo_caption[<?= $ph['id'] ?>]"
                     class="form-control form-control-sm mt-1"
                     value="<?= h($ph['caption'] ?? '') ?>"
                     placeholder="คำบรรยายภาพ (ไม่บังคับ)">
            </div>
            <?php endforeach; ?>
          </div>

          <?php else: ?>
          <div class="text-center text-muted py-3">
            <i class="bi bi-image" style="font-size:1.5rem"></i>
            <p class="small mt-1 mb-0">บันทึกร่างก่อน<br>จึงจะอัปโหลดภาพได้</p>
          </div>
          <?php endif; ?>
        </div>
      </div>

    </div>
  </div><!-- /row -->
</form>

<!-- Drag & drop overlay -->
<div id="dropOverlay" class="d-none position-fixed top-0 start-0 w-100 h-100 d-flex align-items-center justify-content-center"
     style="background:rgba(37,99,235,.15);z-index:9999;pointer-events:none">
  <div class="bg-white rounded-3 shadow-lg p-4 text-center">
    <i class="bi bi-cloud-upload text-primary" style="font-size:3rem"></i>
    <p class="fw-bold text-primary mt-2 mb-0">ปล่อยเพื่ออัปโหลดภาพ</p>
  </div>
</div>

<script>
const REPORT_ID   = <?= $id ?>;
const UPLOAD_URL  = '<?= BASE_URL ?>/homeroom/api/upload_photo.php';
const DELETE_URL  = '<?= BASE_URL ?>/homeroom/api/delete_photo.php';
const CSRF_TOKEN  = '<?= csrfToken() ?>';

// ── Auto-fill period ─────────────────────────────────────────────────────────
function autoFillPeriod() {
    const type  = document.getElementById('reportType').value;
    const start = document.getElementById('periodStart');
    if (!start.value) autoFillFromStart(type, start, document.getElementById('periodEnd'));
}
function autoFillEnd() {
    const type  = document.getElementById('reportType').value;
    const start = document.getElementById('periodStart');
    const end   = document.getElementById('periodEnd');
    autoFillFromStart(type, start, end);
}
function autoFillFromStart(type, startEl, endEl) {
    if (!startEl.value) return;
    const d = new Date(startEl.value);
    if (isNaN(d)) return;
    if (type === 'weekly') {
        // จันทร์–ศุกร์ของสัปดาห์นั้น
        const dow = d.getDay() || 7; // 1=Mon..7=Sun
        const mon = new Date(d); mon.setDate(d.getDate() - dow + 1);
        const fri = new Date(mon); fri.setDate(mon.getDate() + 4);
        startEl.value = mon.toISOString().slice(0, 10);
        endEl.value   = fri.toISOString().slice(0, 10);
    } else {
        // วันแรก–วันสุดท้ายของเดือน
        const first = new Date(d.getFullYear(), d.getMonth(), 1);
        const last  = new Date(d.getFullYear(), d.getMonth() + 1, 0);
        startEl.value = first.toISOString().slice(0, 10);
        endEl.value   = last.toISOString().slice(0, 10);
    }
}

// ── Activity rows ────────────────────────────────────────────────────────────
function addActivityRow() {
    const tbody = document.getElementById('activityRows');
    const tr    = document.createElement('tr');
    tr.className = 'activity-row';
    tr.innerHTML = `
      <td><input type="date" name="act_date[]" class="form-control form-control-sm"></td>
      <td><input type="text" name="act_name[]" class="form-control form-control-sm" placeholder="ชื่อกิจกรรม"></td>
      <td><input type="text" name="act_desc[]" class="form-control form-control-sm" placeholder="รายละเอียด"></td>
      <td><input type="number" name="act_participants[]" class="form-control form-control-sm" min="0" placeholder="จำนวน"></td>
      <td><input type="text" name="act_outcome[]" class="form-control form-control-sm" placeholder="ผล"></td>
      <td><button type="button" class="btn btn-sm btn-link text-danger p-0" onclick="removeRow(this)"><i class="bi bi-trash"></i></button></td>`;
    tbody.appendChild(tr);
    tr.querySelector('input[type=text]')?.focus();
}
function removeRow(btn) {
    const row = btn.closest('tr');
    if (document.querySelectorAll('#activityRows .activity-row').length > 1) {
        row.remove();
    } else {
        row.querySelectorAll('input').forEach(i => i.value = '');
    }
}

// ── Photo upload ─────────────────────────────────────────────────────────────
async function uploadPhotos(files) {
    if (!REPORT_ID) return;
    if (!files || files.length === 0) return;
    const prog   = document.getElementById('uploadProgress');
    const status = document.getElementById('uploadStatus');
    prog.classList.remove('d-none');

    for (let i = 0; i < files.length; i++) {
        const f  = files[i];
        status.textContent = `กำลังอัปโหลด ${i + 1}/${files.length}: ${f.name}`;
        const fd = new FormData();
        fd.append('photo', f);
        fd.append('report_id', REPORT_ID);
        try {
            const res  = await fetch(UPLOAD_URL, { method: 'POST', body: fd });
            const data = await res.json();
            if (data.ok) {
                appendPhotoToGallery(data.id, data.url);
            } else {
                alert('อัปโหลดล้มเหลว: ' + data.msg);
            }
        } catch (e) {
            alert('เกิดข้อผิดพลาดในการอัปโหลด');
        }
    }
    prog.classList.add('d-none');
    document.getElementById('photoInput').value = '';
    updatePhotoCount();
}

function appendPhotoToGallery(id, url) {
    const gallery = document.getElementById('photoGallery');
    const div = document.createElement('div');
    div.className = 'photo-item mb-2';
    div.id = 'photo-' + id;
    div.innerHTML = `
      <div class="position-relative">
        <img src="${url}" class="img-fluid rounded" style="max-height:160px;width:100%;object-fit:cover">
        <button type="button" class="btn btn-danger btn-sm position-absolute top-0 end-0 m-1 p-1 lh-1"
                onclick="deletePhoto(${id})" title="ลบภาพ">
          <i class="bi bi-x"></i>
        </button>
      </div>
      <input type="text" name="photo_caption[${id}]" class="form-control form-control-sm mt-1"
             placeholder="คำบรรยายภาพ (ไม่บังคับ)">`;
    gallery.appendChild(div);
}

async function deletePhoto(photoId) {
    if (!confirm('ลบภาพนี้?')) return;
    try {
        const res  = await fetch(DELETE_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ photo_id: photoId })
        });
        const data = await res.json();
        if (data.ok) {
            document.getElementById('photo-' + photoId)?.remove();
            updatePhotoCount();
        } else {
            alert('ลบไม่ได้: ' + data.msg);
        }
    } catch (e) { alert('เกิดข้อผิดพลาด'); }
}

function updatePhotoCount() {
    document.getElementById('photoCount').textContent =
        document.querySelectorAll('#photoGallery .photo-item').length;
}

// ── Drag & drop ──────────────────────────────────────────────────────────────
if (REPORT_ID) {
    const overlay = document.getElementById('dropOverlay');
    document.addEventListener('dragover', e => { e.preventDefault(); overlay.classList.remove('d-none'); });
    document.addEventListener('dragleave', e => { if (!e.relatedTarget) overlay.classList.add('d-none'); });
    document.addEventListener('drop', e => {
        e.preventDefault();
        overlay.classList.add('d-none');
        const files = e.dataTransfer?.files;
        if (files && files.length) uploadPhotos(files);
    });
}
</script>

<?php echo '</div></div></div>'; renderFooter(); ?>
