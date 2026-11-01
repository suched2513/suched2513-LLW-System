<?php
/**
 * pp5/course_attendance.php — บันทึกเวลาเรียนรายคาบ (เช็กชื่อ) และคำนวณสิทธิ์สอบ "มส" (< 80%)
 */

require_once __DIR__ . '/_bootstrap.php';

use PP5\App\Policies\Pp5Policy;
use PP5\App\Requests\CourseRequest;
use PP5\App\Services\AttendanceService;
use PP5\App\Services\CourseService;

pp5_require_login();

$policy            = new Pp5Policy($pdo);
$courseService     = new CourseService($pdo);
$attendanceService = new AttendanceService($pdo);
$termCtx           = pp5_get_term_context($pdo);

$courseId = (int)($_GET['course_id'] ?? $_POST['course_id'] ?? 0);
$course   = $courseService->getCourseById($courseId);

if (!$course || !$policy->canViewCourse($course)) {
    header('Location: /pp5/index.php');
    exit();
}

$canEdit   = $policy->canEditCourse($course);
$students  = $courseService->getCourseStudents((int)$course['classroom_id']);
$studentIds = array_map(fn($s) => (int)$s['id'], $students);

$flashMsg  = '';
$flashType = 'success';

// ─── ประมวลผล POST Actions ───
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$canEdit) {
        $flashMsg  = 'คุณไม่มีสิทธิ์บันทึกหรือแก้ไขเวลาเรียนในรายวิชานี้';
        $flashType = 'error';
    } else {
        $action = (string)($_POST['form_action'] ?? '');

        try {
            if ($action === 'save_session') {
                $val = CourseRequest::validateAttendanceSession($_POST);
                if (!$val['ok']) {
                    $flashMsg  = implode(' ', $val['errors']);
                    $flashType = 'error';
                } else {
                    $statuses = $_POST['statuses'] ?? [];
                    if (!is_array($statuses)) {
                        $statuses = [];
                    }
                    $attendanceService->saveSessionAttendance(
                        $courseId,
                        $val['data']['session_date'],
                        $val['data']['period'],
                        $val['data']['hours'],
                        $val['data']['topic'],
                        $statuses,
                        $studentIds,
                        $policy->getUserId()
                    );
                    $courseService->recalculateCourseGrades($courseId);
                    $flashMsg = 'บันทึกเวลาเรียนวันที่ ' . pp5_thai_date($val['data']['session_date'], true) . ' คาบที่ ' . $val['data']['period'] . ' เรียบร้อยแล้ว';
                }
            } elseif ($action === 'delete_session') {
                $sessionId = (int)($_POST['session_id'] ?? 0);
                if ($attendanceService->deleteSession($courseId, $sessionId, $policy->getUserId())) {
                    $courseService->recalculateCourseGrades($courseId);
                    $flashMsg = 'ลบคาบเรียนเรียบร้อยแล้ว';
                } else {
                    $flashMsg  = 'ไม่พบคาบเรียนที่ต้องการลบ';
                    $flashType = 'error';
                }
            } elseif ($action === 'sync_from_llw') {
                $syncRes = $attendanceService->syncFromLlwAttendance($courseId, $policy->getUserId());
                if ($syncRes['synced_sessions'] > 0) {
                    $courseService->recalculateCourseGrades($courseId);
                    $flashMsg = "ซิงค์ข้อมูลเช็กชื่อจากระบบเช็กชื่อ LLW สำเร็จ {$syncRes['synced_sessions']} คาบเรียน ({$syncRes['synced_records']} รายการ)";
                } else {
                    $flashMsg  = 'ไม่พบรายการเช็กชื่อใหม่ในระบบ LLW ที่ตรงกับรหัสวิชาและห้องเรียนนี้';
                    $flashType = 'warning';
                }
            }
        } catch (Throwable $e) {
            error_log('[PP5 course_attendance] ' . $e->getMessage());
            $flashMsg  = 'เกิดข้อผิดพลาดในการบันทึกเวลาเรียน';
            $flashType = 'error';
        }
    }
}

$sessions      = $attendanceService->getCourseSessions($courseId);
$attendanceMap = $attendanceService->getCourseAttendanceMap($courseId);
$summaryByStd  = $attendanceService->getCourseStudentsAttendanceSummary($courseId, $studentIds);
$countingRules = $attendanceService->getCountingRules();

// หากเลือกคาบเรียนเพื่อโหลดขึ้นมาแก้ไข
$selectedSessionId = (int)($_GET['session_id'] ?? 0);
$editingSession    = null;
foreach ($sessions as $ss) {
    if ((int)$ss['id'] === $selectedSessionId) {
        $editingSession = $ss;
        break;
    }
}

$formDate   = $editingSession['session_date'] ?? date('Y-m-d');
$formPeriod = (int)($editingSession['period'] ?? 1);
$formHours  = (float)($editingSession['hours'] ?? 1.0);
$formTopic  = (string)($editingSession['topic'] ?? '');

// นับจำนวนนักเรียนที่ติด มส (< 80%)
$msCount = 0;
foreach ($summaryByStd as $sumRow) {
    if (!empty($sumRow['is_ms'])) {
        $msCount++;
    }
}

$statusConfig = [
    'present' => ['label' => 'มา',     'short' => 'มา',  'activeClass' => 'bg-emerald-600 text-white shadow-sm shadow-emerald-200', 'idleClass' => 'bg-slate-100 text-slate-500 hover:bg-emerald-50 hover:text-emerald-600'],
    'late'    => ['label' => 'สาย',    'short' => 'สาย', 'activeClass' => 'bg-amber-500 text-white shadow-sm shadow-amber-200',     'idleClass' => 'bg-slate-100 text-slate-500 hover:bg-amber-50 hover:text-amber-600'],
    'leave'   => ['label' => 'ลากิจ',  'short' => 'ลา',  'activeClass' => 'bg-cyan-600 text-white shadow-sm shadow-cyan-200',       'idleClass' => 'bg-slate-100 text-slate-500 hover:bg-cyan-50 hover:text-cyan-600'],
    'sick'    => ['label' => 'ลาป่วย', 'short' => 'ป่วย','activeClass' => 'bg-blue-600 text-white shadow-sm shadow-blue-200',       'idleClass' => 'bg-slate-100 text-slate-500 hover:bg-blue-50 hover:text-blue-600'],
    'absent'  => ['label' => 'ขาด',    'short' => 'ขาด', 'activeClass' => 'bg-rose-600 text-white shadow-sm shadow-rose-200',       'idleClass' => 'bg-slate-100 text-slate-500 hover:bg-rose-50 hover:text-rose-600'],
];

$pageTitle    = 'บันทึกเวลาเรียน — ' . $course['subject_code'] . ' (' . $course['classroom_name'] . ')';
$pageSubtitle = 'เช็กชื่อรายคาบ (ค่าเริ่มต้น "มา" ทั้งห้อง คลิกเฉพาะคนขาด/ลา/สาย) และคำนวณเวลาเรียน 80% (มส)';
$activeSystem = 'pp5';

require_once __DIR__ . '/../components/layout_start.php';
echo pp5_render_top_bar($pdo, $termCtx, $policy, 'dashboard');
echo pp5_render_course_nav($course, $policy, 'attendance');
?>

<?php if ($flashMsg !== ''): ?>
    <div class="mb-6 rounded-2xl p-4 border flex items-center gap-3 <?= $flashType === 'error' ? 'bg-rose-50 border-rose-200 text-rose-700' : ($flashType === 'warning' ? 'bg-amber-50 border-amber-200 text-amber-700' : 'bg-emerald-50 border-emerald-200 text-emerald-700') ?>">
        <i class="bi <?= $flashType === 'error' ? 'bi-exclamation-octagon-fill' : ($flashType === 'warning' ? 'bi-exclamation-triangle-fill' : 'bi-check-circle-fill') ?> text-xl"></i>
        <span class="text-sm font-bold"><?= htmlspecialchars($flashMsg, ENT_QUOTES, 'UTF-8') ?></span>
    </div>
<?php endif; ?>

<!-- สรุปภาพรวมเวลาเรียนของรายวิชา -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <div class="bg-gradient-to-br from-emerald-500 to-teal-600 rounded-2xl p-5 text-white shadow-xl shadow-emerald-200/50">
        <p class="text-[11px] font-bold opacity-85 uppercase tracking-wider">คาบเรียนที่บันทึกแล้ว</p>
        <p class="text-3xl font-black mt-1"><?= count($sessions) ?> <span class="text-sm font-bold opacity-80">ครั้ง</span></p>
        <p class="text-xs opacity-90 mt-1">
            รวม <?= array_sum(array_map(fn($s) => (float)$s['hours'], $sessions)) ?> / <?= (int)$course['total_hours'] ?> ชั่วโมงตามโครงสร้าง
        </p>
    </div>

    <div class="bg-white rounded-2xl p-5 border border-slate-100 shadow-xl shadow-blue-100/40">
        <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">นักเรียนในห้อง <?= htmlspecialchars($course['classroom_name'], ENT_QUOTES, 'UTF-8') ?></p>
        <p class="text-3xl font-black text-slate-800 mt-1"><?= count($students) ?> <span class="text-sm font-bold text-slate-400">คน</span></p>
        <p class="text-xs text-emerald-600 font-semibold mt-1">
            <i class="bi bi-check2-circle"></i> ค่าเริ่มต้นตั้งเป็น "มาเรียน" ทั้งห้อง
        </p>
    </div>

    <div class="bg-white rounded-2xl p-5 border border-slate-100 shadow-xl shadow-blue-100/40">
        <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">นักเรียนเวลาเรียนต่ำกว่า 80% (มส)</p>
        <p class="text-3xl font-black <?= $msCount > 0 ? 'text-rose-600' : 'text-emerald-600' ?> mt-1">
            <?= $msCount ?> <span class="text-sm font-bold text-slate-400">คน</span>
        </p>
        <p class="text-xs text-slate-500 mt-1">ระบบตรวจ "มส" ก่อนตัดเกรดอัตโนมัติ</p>
    </div>

    <div class="bg-white rounded-2xl p-5 border border-slate-100 shadow-xl shadow-blue-100/40 flex flex-col justify-between">
        <div>
            <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">เกณฑ์การนับเวลาเรียน</p>
            <div class="flex flex-wrap gap-1 mt-2">
                <span class="px-2 py-0.5 rounded bg-emerald-50 text-emerald-700 text-[11px] font-bold">มา = นับ</span>
                <span class="px-2 py-0.5 rounded <?= $countingRules['count_late_as_present'] ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700' ?> text-[11px] font-bold">
                    สาย = <?= $countingRules['count_late_as_present'] ? 'นับมาเรียน' : 'ไม่นับ' ?>
                </span>
                <span class="px-2 py-0.5 rounded <?= $countingRules['count_leave_as_present'] ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700' ?> text-[11px] font-bold">
                    ลากิจ = <?= $countingRules['count_leave_as_present'] ? 'นับมาเรียน' : 'ไม่นับ' ?>
                </span>
                <span class="px-2 py-0.5 rounded <?= $countingRules['count_sick_as_present'] ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700' ?> text-[11px] font-bold">
                    ลาป่วย = <?= $countingRules['count_sick_as_present'] ? 'นับมาเรียน' : 'ไม่นับ' ?>
                </span>
                <span class="px-2 py-0.5 rounded bg-rose-50 text-rose-700 text-[11px] font-bold">ขาด = ไม่นับ</span>
            </div>
        </div>
        <?php if ($canEdit): ?>
            <form method="POST" class="mt-3">
                <input type="hidden" name="form_action" value="sync_from_llw">
                <input type="hidden" name="course_id" value="<?= $courseId ?>">
                <button type="submit" class="w-full py-1.5 px-3 rounded-xl bg-indigo-50 hover:bg-indigo-100 text-indigo-700 text-xs font-bold transition-all flex items-center justify-center gap-1.5">
                    <i class="bi bi-arrow-repeat"></i> ดึงข้อมูลจากระบบเช็กชื่อ LLW
                </button>
            </form>
        <?php endif; ?>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-12 gap-6 mb-6">
    <!-- ซ้าย (7 คอลัมน์): ฟอร์มบันทึกเช็กชื่อรายคาบ (ค่าเริ่มต้น "มา" ทั้งห้อง) -->
    <div class="lg:col-span-7 bg-white rounded-2xl shadow-xl shadow-blue-100/50 border border-slate-100 overflow-hidden">
        <div class="p-5 border-b border-slate-100 bg-slate-50/60 flex flex-wrap items-center justify-between gap-3">
            <div>
                <h3 class="text-base font-black text-slate-800 flex items-center gap-2">
                    <i class="bi bi-calendar2-plus-fill text-emerald-600"></i>
                    <?= $editingSession ? 'แก้ไขเวลาเรียน: ' . pp5_thai_date($editingSession['session_date'], true) . ' (คาบ ' . (int)$editingSession['period'] . ')' : 'บันทึกเวลาเรียนรายคาบใหม่' ?>
                </h3>
                <p class="text-xs text-slate-500 mt-0.5">ค่าเริ่มต้นตั้งเป็น <strong class="text-emerald-600">"มา"</strong> ทั้งห้อง คลิกเปลี่ยนเฉพาะนักเรียนที่มาสาย ลากิจ ลาป่วย หรือขาด</p>
            </div>
            <?php if ($editingSession): ?>
                <a href="/pp5/course_attendance.php?course_id=<?= $courseId ?>" class="px-3 py-1.5 rounded-xl bg-slate-200 hover:bg-slate-300 text-slate-700 text-xs font-bold">
                    + สร้างคาบเรียนใหม่
                </a>
            <?php endif; ?>
        </div>

        <form method="POST" class="p-5 space-y-5">
            <input type="hidden" name="form_action" value="save_session">
            <input type="hidden" name="course_id" value="<?= $courseId ?>">

            <!-- เลือกวันที่ คาบ จำนวนชั่วโมง และหัวข้อที่สอน -->
            <div class="grid grid-cols-1 sm:grid-cols-4 gap-3 bg-slate-50 p-4 rounded-2xl border border-slate-200">
                <div>
                    <label class="block text-xs font-bold text-slate-600 mb-1">วันที่เรียน</label>
                    <input type="date" name="session_date" value="<?= htmlspecialchars($formDate, ENT_QUOTES, 'UTF-8') ?>" required <?= !$canEdit ? 'disabled' : '' ?>
                           class="w-full bg-white border border-slate-200 rounded-xl px-3 py-2 text-xs font-bold text-slate-800 outline-none focus:ring-2 focus:ring-emerald-500">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-600 mb-1">คาบที่ (1–10)</label>
                    <select name="period" <?= !$canEdit ? 'disabled' : '' ?>
                            class="w-full bg-white border border-slate-200 rounded-xl px-3 py-2 text-xs font-bold text-slate-800 outline-none focus:ring-2 focus:ring-emerald-500">
                        <?php for ($p = 1; $p <= 10; $p++): ?>
                            <option value="<?= $p ?>" <?= $formPeriod === $p ? 'selected' : '' ?>>คาบที่ <?= $p ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-600 mb-1">จำนวนชั่วโมง</label>
                    <select name="hours" <?= !$canEdit ? 'disabled' : '' ?>
                            class="w-full bg-white border border-slate-200 rounded-xl px-3 py-2 text-xs font-bold text-slate-800 outline-none focus:ring-2 focus:ring-emerald-500">
                        <?php foreach ([1.0, 1.5, 2.0, 3.0, 4.0] as $hOpt): ?>
                            <option value="<?= $hOpt ?>" <?= abs($formHours - $hOpt) < 0.01 ? 'selected' : '' ?>><?= $hOpt ?> ชั่วโมง</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-600 mb-1">หัวข้อ / กิจกรรม (ถ้ามี)</label>
                    <input type="text" name="topic" value="<?= htmlspecialchars($formTopic, ENT_QUOTES, 'UTF-8') ?>" placeholder="เช่น บทที่ 1..." <?= !$canEdit ? 'disabled' : '' ?>
                           class="w-full bg-white border border-slate-200 rounded-xl px-3 py-2 text-xs text-slate-800 outline-none focus:ring-2 focus:ring-emerald-500">
                </div>
            </div>

            <!-- ปุ่มลัดตั้งค่าทั้งห้อง -->
            <?php if ($canEdit): ?>
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <span class="text-xs font-bold text-slate-600">
                        รายชื่อนักเรียน (<?= count($students) ?> คน) — คลิกปุ่มสถานะเพื่อเปลี่ยน:
                    </span>
                    <div class="flex items-center gap-2">
                        <button type="button" onclick="setAllAttendanceStatus('present')"
                                class="px-3 py-1.5 rounded-xl bg-emerald-50 hover:bg-emerald-100 text-emerald-700 border border-emerald-200 text-xs font-bold transition-all">
                            <i class="bi bi-check-all"></i> ตั้งค่า "มาเรียน" ทั้งห้อง
                        </button>
                    </div>
                </div>
            <?php endif; ?>

            <!-- รายชื่อนักเรียนพร้อมปุ่มกดสลับสถานะ -->
            <div class="border border-slate-200 rounded-2xl divide-y divide-slate-100 max-h-[520px] overflow-y-auto">
                <?php foreach ($students as $std):
                    $sid = (int)$std['id'];
                    $currentSt = 'present';
                    if ($editingSession && isset($attendanceMap[(int)$editingSession['id']][$sid])) {
                        $currentSt = $attendanceMap[(int)$editingSession['id']][$sid];
                    }
                ?>
                    <div class="px-4 py-2.5 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 hover:bg-slate-50/80 transition-all" data-student-row="<?= $sid ?>">
                        <div class="flex items-center gap-3 min-w-0">
                            <span class="w-7 h-7 rounded-lg bg-slate-100 text-slate-700 text-xs font-black flex items-center justify-center shrink-0">
                                <?= (int)$std['student_number'] ?>
                            </span>
                            <span class="text-xs font-mono font-bold text-slate-400 shrink-0">
                                <?= htmlspecialchars($std['student_id'], ENT_QUOTES, 'UTF-8') ?>
                            </span>
                            <span class="text-xs sm:text-sm font-bold text-slate-800 truncate">
                                <?= htmlspecialchars($std['name'], ENT_QUOTES, 'UTF-8') ?>
                            </span>
                        </div>

                        <div class="flex items-center gap-1 shrink-0">
                            <input type="hidden" name="statuses[<?= $sid ?>]" id="att_input_<?= $sid ?>" value="<?= htmlspecialchars($currentSt, ENT_QUOTES, 'UTF-8') ?>" class="att-hidden-input">
                            <?php foreach ($statusConfig as $stKey => $stCfg):
                                $isActive = ($currentSt === $stKey);
                            ?>
                                <button type="button"
                                        <?= !$canEdit ? 'disabled' : '' ?>
                                        data-student-id="<?= $sid ?>"
                                        data-status-key="<?= $stKey ?>"
                                        data-active-class="<?= $stCfg['activeClass'] ?>"
                                        data-idle-class="<?= $stCfg['idleClass'] ?>"
                                        onclick="selectStudentAttendance(<?= $sid ?>, '<?= $stKey ?>')"
                                        class="att-btn-<?= $sid ?> px-2.5 py-1 rounded-lg text-xs font-bold transition-all <?= $isActive ? $stCfg['activeClass'] : $stCfg['idleClass'] ?>">
                                    <?= $stCfg['label'] ?>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if ($canEdit): ?>
                <div class="pt-2">
                    <button type="submit" class="w-full bg-emerald-600 hover:bg-emerald-700 text-white py-3 rounded-2xl text-sm font-bold shadow-lg shadow-emerald-200 transition-all flex items-center justify-center gap-2">
                        <i class="bi bi-save2-fill"></i> บันทึกเวลาเรียนคาบนี้ และคำนวณเวลาเรียน 80% อัตโนมัติ
                    </button>
                </div>
            <?php endif; ?>
        </form>
    </div>

    <!-- ขวา (5 คอลัมน์): ประวัติคาบเรียนที่บันทึกไว้ -->
    <div class="lg:col-span-5 bg-white rounded-2xl shadow-xl shadow-blue-100/50 border border-slate-100 overflow-hidden flex flex-col">
        <div class="p-5 border-b border-slate-100 bg-slate-50/60">
            <h3 class="text-base font-black text-slate-800 flex items-center gap-2">
                <i class="bi bi-clock-history text-indigo-600"></i>
                ประวัติคาบเรียนที่บันทึกแล้ว (<?= count($sessions) ?> ครั้ง)
            </h3>
            <p class="text-xs text-slate-500 mt-0.5">คลิกที่คาบเรียนเพื่อดูหรือแก้ไขการเช็กชื่อย้อนหลัง</p>
        </div>

        <?php if (empty($sessions)): ?>
            <div class="p-8 text-center text-xs text-slate-400 flex-1 flex flex-col items-center justify-center">
                <i class="bi bi-calendar2-x text-3xl text-slate-300 mb-2"></i>
                ยังไม่มีประวัติการเช็กชื่อในรายวิชานี้<br>
                เลือกวันที่และกดปุ่ม "บันทึกเวลาเรียนคาบนี้" ทางด้านซ้ายได้เลย
            </div>
        <?php else: ?>
            <div class="divide-y divide-slate-100 max-h-[640px] overflow-y-auto">
                <?php foreach ($sessions as $ss):
                    $ssId = (int)$ss['id'];
                    $ssMap = $attendanceMap[$ssId] ?? [];
                    $cnts  = ['present' => 0, 'late' => 0, 'leave' => 0, 'sick' => 0, 'absent' => 0];
                    foreach ($students as $stRow) {
                        $stVal = $ssMap[(int)$stRow['id']] ?? 'present';
                        if (isset($cnts[$stVal])) {
                            $cnts[$stVal]++;
                        }
                    }
                    $isSelected = ($selectedSessionId === $ssId);
                ?>
                    <div class="p-4 hover:bg-slate-50 transition-all <?= $isSelected ? 'bg-indigo-50/60 border-l-4 border-indigo-600' : '' ?>">
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <div class="flex items-center gap-2">
                                    <span class="text-xs sm:text-sm font-black text-slate-800">
                                        <?= pp5_thai_date($ss['session_date'], true) ?>
                                    </span>
                                    <span class="px-2 py-0.5 rounded bg-indigo-50 text-indigo-700 text-[11px] font-black">
                                        คาบที่ <?= (int)$ss['period'] ?> (<?= (float)$ss['hours'] ?> ชม.)
                                    </span>
                                </div>
                                <?php if (!empty($ss['topic'])): ?>
                                    <p class="text-xs text-slate-500 mt-0.5"><?= htmlspecialchars($ss['topic'], ENT_QUOTES, 'UTF-8') ?></p>
                                <?php endif; ?>
                            </div>

                            <div class="flex items-center gap-1.5 shrink-0">
                                <a href="/pp5/course_attendance.php?course_id=<?= $courseId ?>&session_id=<?= $ssId ?>"
                                   class="px-2.5 py-1 rounded-lg bg-slate-100 hover:bg-indigo-100 text-slate-700 hover:text-indigo-700 text-xs font-bold transition-all">
                                    <i class="bi bi-pencil-square"></i> แก้ไข
                                </a>
                                <?php if ($canEdit): ?>
                                    <form method="POST" onsubmit="return confirm('ยืนยันการลบคาบเรียนนี้?');">
                                        <input type="hidden" name="form_action" value="delete_session">
                                        <input type="hidden" name="course_id" value="<?= $courseId ?>">
                                        <input type="hidden" name="session_id" value="<?= $ssId ?>">
                                        <button type="submit" class="px-2 py-1 rounded-lg bg-rose-50 hover:bg-rose-100 text-rose-600 text-xs font-bold transition-all">
                                            <i class="bi bi-trash3"></i>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="flex flex-wrap items-center gap-1.5 mt-2.5 text-[11px] font-bold">
                            <span class="px-2 py-0.5 rounded bg-emerald-50 text-emerald-700">มา <?= $cnts['present'] ?></span>
                            <?php if ($cnts['late'] > 0): ?>
                                <span class="px-2 py-0.5 rounded bg-amber-50 text-amber-700">สาย <?= $cnts['late'] ?></span>
                            <?php endif; ?>
                            <?php if ($cnts['leave'] > 0): ?>
                                <span class="px-2 py-0.5 rounded bg-cyan-50 text-cyan-700">ลากิจ <?= $cnts['leave'] ?></span>
                            <?php endif; ?>
                            <?php if ($cnts['sick'] > 0): ?>
                                <span class="px-2 py-0.5 rounded bg-blue-50 text-blue-700">ป่วย <?= $cnts['sick'] ?></span>
                            <?php endif; ?>
                            <?php if ($cnts['absent'] > 0): ?>
                                <span class="px-2 py-0.5 rounded bg-rose-50 text-rose-700">ขาด <?= $cnts['absent'] ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- ตารางสรุปเวลาเรียนสะสมรายบุคคลและสถานะสิทธิ์สอบ (มส) -->
<div class="bg-white rounded-2xl shadow-xl shadow-blue-100/50 border border-slate-100 overflow-hidden">
    <div class="p-5 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
            <h3 class="text-base font-black text-slate-800 flex items-center gap-2">
                <i class="bi bi-person-check-fill text-indigo-600"></i>
                สรุปเวลาเรียนสะสมรายบุคคล & การตรวจสอบ "มส" (ต่ำกว่า 80%)
            </h3>
            <p class="text-xs text-slate-500 mt-0.5">คำนวณจากชั่วโมงที่เช็กชื่อจริงทั้งหมด หากเวลาเรียนน้อยกว่า 80.00% ระบบจะตัดสินเป็น "มส" ก่อนตัดเกรดทันที</p>
        </div>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="bg-slate-50 border-b border-slate-200 text-[11px] font-black text-slate-500 uppercase tracking-wider">
                    <th class="py-3.5 px-4 text-center w-16">เลขที่</th>
                    <th class="py-3.5 px-4">รหัสนักเรียน</th>
                    <th class="py-3.5 px-4">ชื่อ - นามสกุล</th>
                    <th class="py-3.5 px-3 text-center text-emerald-700">มา (ชม.)</th>
                    <th class="py-3.5 px-3 text-center text-amber-700">สาย (ชม.)</th>
                    <th class="py-3.5 px-3 text-center text-cyan-700">ลากิจ (ชม.)</th>
                    <th class="py-3.5 px-3 text-center text-blue-700">ป่วย (ชม.)</th>
                    <th class="py-3.5 px-3 text-center text-rose-700">ขาด (ชม.)</th>
                    <th class="py-3.5 px-4 text-center">เวลาเรียนสะสม</th>
                    <th class="py-3.5 px-4 text-center">ร้อยละเวลาเรียน</th>
                    <th class="py-3.5 px-4 text-center">สถานะสิทธิ์สอบ</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 text-xs sm:text-sm">
                <?php foreach ($students as $std):
                    $sid = (int)$std['id'];
                    $sum = $summaryByStd[$sid] ?? [
                        'total_recorded_hours' => 0,
                        'attended_hours'       => 0,
                        'present_hours'        => 0,
                        'late_hours'           => 0,
                        'leave_hours'          => 0,
                        'sick_hours'           => 0,
                        'absent_hours'         => 0,
                        'attendance_percent'   => 100.0,
                        'is_ms'                => false,
                    ];
                ?>
                    <tr class="hover:bg-slate-50/80 <?= $sum['is_ms'] ? 'bg-rose-50/40' : '' ?>">
                        <td class="py-3 px-4 text-center font-black text-slate-700"><?= (int)$std['student_number'] ?></td>
                        <td class="py-3 px-4 font-mono font-bold text-slate-600"><?= htmlspecialchars($std['student_id'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td class="py-3 px-4 font-bold text-slate-800"><?= htmlspecialchars($std['name'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td class="py-3 px-3 text-center font-bold text-emerald-600"><?= $sum['present_hours'] ?></td>
                        <td class="py-3 px-3 text-center font-bold text-amber-600"><?= $sum['late_hours'] ?></td>
                        <td class="py-3 px-3 text-center font-bold text-cyan-600"><?= $sum['leave_hours'] ?></td>
                        <td class="py-3 px-3 text-center font-bold text-blue-600"><?= $sum['sick_hours'] ?></td>
                        <td class="py-3 px-3 text-center font-bold <?= $sum['absent_hours'] > 0 ? 'text-rose-600' : 'text-slate-400' ?>"><?= $sum['absent_hours'] ?></td>
                        <td class="py-3 px-4 text-center font-black text-slate-700">
                            <?= $sum['attended_hours'] ?> / <?= $sum['total_recorded_hours'] ?> ชม.
                        </td>
                        <td class="py-3 px-4 text-center">
                            <span class="font-black <?= $sum['is_ms'] ? 'text-rose-600' : 'text-emerald-600' ?>">
                                <?= number_format((float)$sum['attendance_percent'], 2) ?>%
                            </span>
                        </td>
                        <td class="py-3 px-4 text-center">
                            <?php if ($sum['is_ms']): ?>
                                <span class="px-3 py-1 rounded-full bg-rose-100 text-rose-700 border border-rose-300 text-xs font-black">
                                    ติด มส (&lt; 80%)
                                </span>
                            <?php else: ?>
                                <span class="px-3 py-1 rounded-full bg-emerald-50 text-emerald-700 text-xs font-bold">
                                    มีสิทธิ์สอบปกติ
                                </span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function selectStudentAttendance(studentId, statusKey) {
    const input = document.getElementById('att_input_' + studentId);
    if (!input) return;
    input.value = statusKey;

    const buttons = document.querySelectorAll('.att-btn-' + studentId);
    buttons.forEach(btn => {
        const key = btn.getAttribute('data-status-key');
        const activeCls = btn.getAttribute('data-active-class').split(' ');
        const idleCls = btn.getAttribute('data-idle-class').split(' ');

        btn.classList.remove(...activeCls, ...idleCls);
        if (key === statusKey) {
            btn.classList.add(...activeCls);
        } else {
            btn.classList.add(...idleCls);
        }
    });
}

function setAllAttendanceStatus(statusKey) {
    const inputs = document.querySelectorAll('.att-hidden-input');
    inputs.forEach(inp => {
        const idParts = inp.id.split('_');
        const sid = parseInt(idParts[idParts.length - 1], 10);
        if (sid > 0) {
            selectStudentAttendance(sid, statusKey);
        }
    });
}
</script>

<?php require_once __DIR__ . '/../components/layout_end.php'; ?>
