<?php
/**
 * pp5/master_data.php — หน้าจัดการข้อมูลพื้นฐานทั้งหมดของระบบ ปพ.5 ออนไลน์
 * เข้าถึงได้เฉพาะ: งานทะเบียนและวัดผล (Admin) — ตรวจสิทธิ์ด้วย Pp5Policy::authorizeAdmin()
 */

require_once __DIR__ . '/_bootstrap.php';
pp5_require_login();

use PP5\App\Policies\Pp5Policy;
use PP5\App\Requests\MasterDataRequest;
use PP5\App\Services\MasterDataService;

$policy = new Pp5Policy($pdo);
$policy->authorizeAdmin();

$masterService = new MasterDataService($pdo);
$termCtx       = pp5_get_term_context($pdo);
$curYear       = (int)$termCtx['year'];
$curSem        = (int)$termCtx['sem'];

$tab     = trim((string)($_GET['tab'] ?? 'settings'));
$msg     = '';
$msgType = 'success';

// ─── ประมวลผล POST Actions ───
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = trim((string)($_POST['action'] ?? ''));

    try {
        if ($action === 'save_school_settings') {
            $tab = 'settings';
            $val = MasterDataRequest::validateSchoolSettings($_POST);
            if (!$val['valid']) {
                $msg = implode(' · ', $val['errors']);
                $msgType = 'error';
            } else {
                $masterService->saveSchoolSettings($val['data']);
                $msg = 'บันทึกการตั้งค่าโรงเรียนและเกณฑ์การประเมินเรียบร้อยแล้ว';
            }
        } elseif ($action === 'save_grade_criteria') {
            $tab = 'settings';
            $val = MasterDataRequest::validateGradeCriteria($_POST['criteria'] ?? []);
            if (!$val['valid']) {
                $msg = implode(' · ', $val['errors']);
                $msgType = 'error';
            } else {
                $masterService->saveGradeCriteria($val['data']);
                $msg = 'บันทึกเกณฑ์การตัดเกรดเรียบร้อยแล้ว';
            }
        } elseif ($action === 'save_academic_term') {
            $tab = 'settings';
            $val = MasterDataRequest::validateAcademicTerm($_POST);
            if (!$val['valid']) {
                $msg = implode(' · ', $val['errors']);
                $msgType = 'error';
            } else {
                $masterService->saveAcademicTerm($val['data']);
                $termCtx = pp5_get_term_context($pdo);
                $msg = 'บันทึกข้อมูลปีการศึกษาและภาคเรียนเรียบร้อยแล้ว';
            }
        } elseif ($action === 'update_department_head') {
            $tab    = 'departments';
            $deptId = (int)($_POST['department_id'] ?? 0);
            $headId = (int)($_POST['head_user_id'] ?? 0);
            if ($deptId > 0) {
                $masterService->updateDepartmentHead($deptId, $headId ?: null);
                $msg = 'อัปเดตหัวหน้ากลุ่มสาระการเรียนรู้เรียบร้อยแล้ว';
            }
        } elseif ($action === 'update_classroom_advisor') {
            $tab         = 'classrooms';
            $classroomId = (int)($_POST['classroom_id'] ?? 0);
            $studyPlan   = trim((string)($_POST['study_plan'] ?? 'ทั่วไป'));
            $advisorId   = (int)($_POST['advisor_user_id'] ?? 0);
            $coAdvisorId = (int)($_POST['co_advisor_user_id'] ?? 0);
            if ($classroomId > 0) {
                $masterService->updateClassroomAdvisor($classroomId, $studyPlan, $advisorId ?: null, $coAdvisorId ?: null);
                $msg = 'บันทึกแผนการเรียนและครูที่ปรึกษาเรียบร้อยแล้ว';
            }
        } elseif ($action === 'save_student') {
            $tab = 'students';
            $val = MasterDataRequest::validateStudent($_POST);
            if (!$val['valid']) {
                $msg = implode(' · ', $val['errors']);
                $msgType = 'error';
            } else {
                $masterService->saveStudent($val['data'], $curYear, $curSem);
                $msg = "บันทึกข้อมูลนักเรียน {$val['data']['student_code']} {$val['data']['full_name']} เรียบร้อยแล้ว";
            }
        } elseif ($action === 'save_subject') {
            $tab = 'subjects';
            $val = MasterDataRequest::validateSubject($_POST);
            if (!$val['valid']) {
                $msg = implode(' · ', $val['errors']);
                $msgType = 'error';
            } else {
                $masterService->saveSubject($val['data']);
                $msg = "บันทึกรายวิชา {$val['data']['code']} {$val['data']['name']} เรียบร้อยแล้ว";
            }
        } elseif ($action === 'save_indicator') {
            $tab       = 'subjects';
            $subjectId = (int)($_POST['subject_id'] ?? 0);
            $code      = trim((string)($_POST['code'] ?? ''));
            $desc      = trim((string)($_POST['description'] ?? ''));
            $sortOrder = max(1, (int)($_POST['sort_order'] ?? 1));
            if ($subjectId <= 0 || $code === '' || $desc === '') {
                $msg = 'กรุณาระบุรหัสตัวชี้วัดและคำอธิบายให้ครบถ้วน';
                $msgType = 'error';
            } else {
                $masterService->saveIndicator($subjectId, $code, $desc, $sortOrder);
                $msg = "บันทึกตัวชี้วัด {$code} เรียบร้อยแล้ว";
            }
        } elseif ($action === 'delete_indicator') {
            $tab = 'subjects';
            $indId = (int)($_POST['indicator_id'] ?? 0);
            if ($indId > 0) {
                $masterService->deleteIndicator($indId);
                $msg = 'ลบตัวชี้วัดเรียบร้อยแล้ว';
            }
        } elseif ($action === 'save_course') {
            $tab = 'courses';
            $_POST['academic_year'] = $curYear;
            $_POST['semester']      = $curSem;
            $val = MasterDataRequest::validateCourse($_POST);
            if (!$val['valid']) {
                $msg = implode(' · ', $val['errors']);
                $msgType = 'error';
            } else {
                $masterService->saveCourse($val['data']);
                $msg = 'บันทึกการจัดวิชาเปิดสอน ปพ.5 เรียบร้อยแล้ว';
            }
        } elseif ($action === 'unlock_course') {
            $tab      = 'courses';
            $courseId = (int)($_POST['course_id'] ?? 0);
            $reason   = trim((string)($_POST['reason'] ?? ''));
            if ($courseId <= 0 || $reason === '') {
                $msg = 'กรุณาระบุเหตุผลในการปลดล็อก ปพ.5';
                $msgType = 'error';
            } else {
                $masterService->unlockCourse($courseId, $policy->getUserId(), $reason);
                $msg = "ปลดล็อก ปพ.5 รหัส #{$courseId} ให้กลับมาแก้ไขได้เรียบร้อยแล้ว";
            }
        } elseif ($action === 'sync_from_llw') {
            $tab = 'courses';
            $stats = $masterService->syncFromLlwSystem($curYear, $curSem);
            $msg = "ซิงค์ข้อมูลจากระบบ LLW สำเร็จ! (ห้องเรียน {$stats['classrooms']} ห้อง, นักเรียน {$stats['students']} คน, รายวิชา {$stats['subjects']} วิชา, ปพ.5 {$stats['courses']} เล่ม)";
        }
    } catch (Throwable $e) {
        error_log('[PP5 MasterData] ' . $e->getMessage());
        $msg     = 'เกิดข้อผิดพลาดในการบันทึกข้อมูล กรุณาตรวจสอบข้อมูลอีกครั้ง';
        $msgType = 'error';
    }
}

// ─── โหลดข้อมูลสำหรับแสดงผลตามแท็บ ───
$schoolSettings = $masterService->getSchoolSettings();
$gradeCriteria  = $masterService->getGradeCriteria();
$departments    = $masterService->getDepartments();
$teachers       = $masterService->getAllTeachers();
$classrooms     = $masterService->getClassrooms($curYear, $curSem);

$selectedRoom = trim((string)($_GET['room'] ?? ($classrooms[0]['classroom_name'] ?? 'ม.1/1')));
$students     = ($tab === 'students')
    ? $masterService->getStudentsByClassroom($curYear, $curSem, $selectedRoom, $policy->canViewFullCitizenId($selectedRoom))
    : [];

$filterSubjDept  = (int)($_GET['subj_dept'] ?? 0);
$filterSubjGrade = (int)($_GET['subj_grade'] ?? 0);
$subjects        = ($tab === 'subjects' || $tab === 'courses')
    ? $masterService->getSubjects($filterSubjDept ?: null, $filterSubjGrade ?: null)
    : [];

$inspectSubjectId = (int)($_GET['inspect_subject_id'] ?? 0);
$indicators       = ($tab === 'subjects' && $inspectSubjectId > 0)
    ? $masterService->getSubjectIndicators($inspectSubjectId)
    : [];

$filterCourseDept  = (int)($_GET['course_dept'] ?? 0);
$filterCourseGrade = (int)($_GET['course_grade'] ?? 0);
$courses           = ($tab === 'courses')
    ? $masterService->getCourses($curYear, $curSem, $filterCourseDept ?: null, $filterCourseGrade ?: null)
    : [];

$tabsList = [
    'settings'    => ['label' => '1. ตั้งค่าโรงเรียน & เกณฑ์เกรด', 'icon' => 'bi-sliders2-vertical'],
    'departments' => ['label' => '2. กลุ่มสาระการเรียนรู้ (8+1)',  'icon' => 'bi-diagram-3-fill'],
    'classrooms'  => ['label' => '3. ห้องเรียน & ที่ปรึกษา (19 ห้อง)', 'icon' => 'bi-door-open-fill'],
    'students'    => ['label' => '4. ข้อมูลนักเรียน & PDPA',        'icon' => 'bi-people-fill'],
    'subjects'    => ['label' => '5. รายวิชา & ตัวชี้วัด',          'icon' => 'bi-book-half'],
    'courses'     => ['label' => '6. จัดครูสอน & ปลดล็อก ปพ.5',     'icon' => 'bi-journal-bookmark-fill'],
];

$pageTitle    = 'จัดการข้อมูลพื้นฐาน ปพ.5';
$pageSubtitle = 'งานทะเบียนและวัดผล · ตั้งค่าหลักสูตร ห้องเรียน นักเรียน รายวิชา และการจัดครูผู้สอน';
$activeSystem = 'pp5';

require_once __DIR__ . '/../components/layout_start.php';
?>

<?= pp5_render_top_bar($pdo, $termCtx, $policy, 'master') ?>

<?php if ($msg !== ''): ?>
    <div class="mb-6 p-4 rounded-2xl border flex items-center gap-3 shadow-sm <?= $msgType === 'error' ? 'bg-rose-50 border-rose-200 text-rose-700' : 'bg-emerald-50 border-emerald-200 text-emerald-700' ?>">
        <i class="bi <?= $msgType === 'error' ? 'bi-exclamation-octagon-fill text-rose-500' : 'bi-check-circle-fill text-emerald-500' ?> text-lg"></i>
        <span class="text-sm font-bold"><?= htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') ?></span>
    </div>
<?php endif; ?>

<!-- ─── แถบเมนู 6 หมวดข้อมูลพื้นฐาน ─── -->
<div class="flex flex-wrap items-center gap-2 mb-6">
    <?php foreach ($tabsList as $tKey => $tMeta): ?>
        <a href="/pp5/master_data.php?tab=<?= $tKey ?>"
           class="px-4 py-2.5 rounded-2xl text-xs sm:text-sm font-bold transition-all flex items-center gap-2 <?= $tab === $tKey ? 'bg-blue-600 text-white shadow-lg shadow-blue-200' : 'bg-white text-slate-600 border border-slate-200 hover:bg-blue-50 hover:text-blue-600' ?>">
            <i class="bi <?= $tMeta['icon'] ?>"></i>
            <?= htmlspecialchars($tMeta['label'], ENT_QUOTES, 'UTF-8') ?>
        </a>
    <?php endforeach; ?>
</div>

<?php if ($tab === 'settings'): ?>
<!-- ═════════════════════════════════════════════════════════════════════════
     TAB 1: ตั้งค่าโรงเรียน, เกณฑ์เวลาเรียน/คุณลักษณะ, เกณฑ์ตัดเกรด และภาคเรียน
     ═════════════════════════════════════════════════════════════════════════ -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- คอลัมน์ซ้าย (2 ส่วน): ตั้งค่าโรงเรียน & กฎการวัดผล -->
    <div class="lg:col-span-2 bg-white rounded-2xl shadow-xl shadow-blue-100/50 p-6 border border-slate-100">
        <h3 class="text-base font-black text-slate-800 flex items-center gap-2 mb-4">
            <i class="bi bi-building-fill-gear text-blue-600"></i> ข้อมูลโรงเรียน ผู้ลงนามใน PDF ปพ.5 และเกณฑ์การประเมิน
        </h3>
        <form method="POST" class="space-y-4">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="save_school_settings">

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-[10px] font-black text-slate-400 uppercase tracking-wider mb-1">ชื่อโรงเรียน</label>
                    <input type="text" name="school_name" value="<?= htmlspecialchars($schoolSettings['school_name'], ENT_QUOTES, 'UTF-8') ?>" required
                           class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-4 py-2.5 text-sm font-bold focus:ring-2 focus:ring-blue-500 outline-none">
                </div>
                <div>
                    <label class="block text-[10px] font-black text-slate-400 uppercase tracking-wider mb-1">รหัสสถานศึกษา 10 หลัก</label>
                    <input type="text" name="school_code" value="<?= htmlspecialchars($schoolSettings['school_code'], ENT_QUOTES, 'UTF-8') ?>"
                           class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-4 py-2.5 text-sm font-bold focus:ring-2 focus:ring-blue-500 outline-none">
                </div>
                <div>
                    <label class="block text-[10px] font-black text-slate-400 uppercase tracking-wider mb-1">สังกัด (แสดงบนหน้าปก ปพ.5)</label>
                    <input type="text" name="affiliation" value="<?= htmlspecialchars($schoolSettings['affiliation'], ENT_QUOTES, 'UTF-8') ?>"
                           class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-4 py-2.5 text-sm font-bold focus:ring-2 focus:ring-blue-500 outline-none">
                </div>
                <div>
                    <label class="block text-[10px] font-black text-slate-400 uppercase tracking-wider mb-1">จังหวัด</label>
                    <input type="text" name="province" value="<?= htmlspecialchars($schoolSettings['province'], ENT_QUOTES, 'UTF-8') ?>"
                           class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-4 py-2.5 text-sm font-bold focus:ring-2 focus:ring-blue-500 outline-none">
                </div>
            </div>

            <div class="pt-3 border-t border-slate-100 grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label class="block text-[10px] font-black text-slate-400 uppercase tracking-wider mb-1">ชื่อผู้อำนวยการโรงเรียน</label>
                    <input type="text" name="director_name" value="<?= htmlspecialchars($schoolSettings['director_name'], ENT_QUOTES, 'UTF-8') ?>"
                           class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-4 py-2.5 text-sm font-bold focus:ring-2 focus:ring-blue-500 outline-none">
                </div>
                <div>
                    <label class="block text-[10px] font-black text-slate-400 uppercase tracking-wider mb-1">ชื่อรองฯ ฝ่ายวิชาการ</label>
                    <input type="text" name="deputy_academic_name" value="<?= htmlspecialchars($schoolSettings['deputy_academic_name'], ENT_QUOTES, 'UTF-8') ?>"
                           class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-4 py-2.5 text-sm font-bold focus:ring-2 focus:ring-blue-500 outline-none">
                </div>
                <div>
                    <label class="block text-[10px] font-black text-slate-400 uppercase tracking-wider mb-1">ชื่อหัวหน้างานวัดและประเมินผล</label>
                    <input type="text" name="measurement_head_name" value="<?= htmlspecialchars($schoolSettings['measurement_head_name'], ENT_QUOTES, 'UTF-8') ?>"
                           class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-4 py-2.5 text-sm font-bold focus:ring-2 focus:ring-blue-500 outline-none">
                </div>
            </div>

            <div class="pt-4 border-t border-slate-100">
                <h4 class="text-sm font-black text-slate-700 mb-3"><i class="bi bi-calculator-fill text-indigo-600"></i> กฎการคำนวณเวลาเรียน (มส) และการสรุปผลคุณลักษณะฯ</h4>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-[10px] font-black text-slate-400 uppercase tracking-wider mb-1">เกณฑ์เวลาเรียนขั้นต่ำไม่ติด มส (%)</label>
                        <input type="number" step="0.5" min="50" max="100" name="min_attendance_percent" value="<?= htmlspecialchars((string)$schoolSettings['min_attendance_percent'], ENT_QUOTES, 'UTF-8') ?>"
                               class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-4 py-2.5 text-sm font-bold focus:ring-2 focus:ring-blue-500 outline-none">
                    </div>
                    <div>
                        <label class="block text-[10px] font-black text-slate-400 uppercase tracking-wider mb-1">วิธีสรุปผลรวมคุณลักษณะฯ / อ่านคิดเขียน / สมรรถนะ</label>
                        <select name="assessment_summary_mode" class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-4 py-2.5 text-sm font-bold focus:ring-2 focus:ring-blue-500 outline-none">
                            <option value="mode" <?= ($schoolSettings['assessment_summary_mode'] ?? 'mode') === 'mode' ? 'selected' : '' ?>>ฐานนิยม (Mode) — มาตรฐานวัดผล</option>
                            <option value="mean" <?= ($schoolSettings['assessment_summary_mode'] ?? 'mode') === 'mean' ? 'selected' : '' ?>>ค่าเฉลี่ย (Mean) เทียบช่วงคะแนน</option>
                        </select>
                    </div>
                </div>

                <div class="mt-3 flex flex-wrap gap-4 bg-slate-50 p-3.5 rounded-2xl border border-slate-200/80 text-xs font-bold text-slate-700">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="count_late_as_present" value="1" <?= !empty($schoolSettings['count_late_as_present']) ? 'checked' : '' ?> class="rounded text-blue-600">
                        นับ "มาสาย" เป็นเวลาเรียนปกติ
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="count_leave_as_present" value="1" <?= !empty($schoolSettings['count_leave_as_present']) ? 'checked' : '' ?> class="rounded text-blue-600">
                        นับ "ลากิจ" เป็นเวลาเรียน
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="count_sick_as_present" value="1" <?= !empty($schoolSettings['count_sick_as_present']) ? 'checked' : '' ?> class="rounded text-blue-600">
                        นับ "ลาป่วย" เป็นเวลาเรียน
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer text-rose-700">
                        <input type="checkbox" name="zero_item_forces_fail" value="1" <?= !empty($schoolSettings['zero_item_forces_fail']) ? 'checked' : '' ?> class="rounded text-rose-600">
                        หากมีข้อย่อยใดได้ 0 ให้สรุปผลเป็น 0 (ไม่ผ่าน) ทันที
                    </label>
                </div>
            </div>

            <div class="pt-2">
                <button type="submit" class="bg-blue-600 text-white px-6 py-3 rounded-2xl text-sm font-bold shadow-lg shadow-blue-200 hover:bg-blue-700 transition-all">
                    <i class="bi bi-save2-fill"></i> บันทึกการตั้งค่าโรงเรียนและกฎการวัดผล
                </button>
            </div>
        </form>
    </div>

    <!-- คอลัมน์ขวา: เกณฑ์ตัดเกรด 8 ระดับ & ปีการศึกษา -->
    <div class="space-y-6">
        <div class="bg-white rounded-2xl shadow-xl shadow-blue-100/50 p-6 border border-slate-100">
            <h3 class="text-base font-black text-slate-800 flex items-center gap-2 mb-3">
                <i class="bi bi-reception-4 text-emerald-600"></i> เกณฑ์การตัดเกรด (แก้ไขได้)
            </h3>
            <form method="POST" class="space-y-2.5">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="save_grade_criteria">
                <?php foreach ($gradeCriteria as $gc):
                    $g = $gc['grade'];
                ?>
                    <div class="flex items-center justify-between gap-2 bg-slate-50 px-3 py-2 rounded-xl border border-slate-200/70">
                        <span class="w-12 text-center py-1 rounded-lg bg-indigo-600 text-white font-black text-xs">
                            เกรด <?= htmlspecialchars($g, ENT_QUOTES, 'UTF-8') ?>
                        </span>
                        <div class="flex items-center gap-1.5 text-xs font-bold text-slate-600">
                            <input type="number" name="criteria[<?= htmlspecialchars($g, ENT_QUOTES, 'UTF-8') ?>][min_score]" value="<?= (int)$gc['min_score'] ?>" min="0" max="100"
                                   class="w-16 bg-white border border-slate-200 rounded-lg px-2 py-1 text-center font-black">
                            <span>ถึง</span>
                            <input type="number" name="criteria[<?= htmlspecialchars($g, ENT_QUOTES, 'UTF-8') ?>][max_score]" value="<?= (int)$gc['max_score'] ?>" min="0" max="100"
                                   class="w-16 bg-white border border-slate-200 rounded-lg px-2 py-1 text-center font-black">
                            <span>คะแนน</span>
                        </div>
                    </div>
                <?php endforeach; ?>
                <button type="submit" class="w-full mt-2 bg-emerald-600 text-white py-2.5 rounded-xl text-xs font-bold shadow-md shadow-emerald-200 hover:bg-emerald-700 transition-all">
                    <i class="bi bi-check2-circle"></i> บันทึกเกณฑ์การตัดเกรด
                </button>
            </form>
        </div>

        <div class="bg-white rounded-2xl shadow-xl shadow-blue-100/50 p-6 border border-slate-100">
            <h3 class="text-base font-black text-slate-800 flex items-center gap-2 mb-3">
                <i class="bi bi-calendar-range text-indigo-600"></i> ปีการศึกษาและภาคเรียน
            </h3>
            <form method="POST" class="space-y-3">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="save_academic_term">
                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label class="block text-[10px] font-black text-slate-400 uppercase mb-1">ปีการศึกษา (พ.ศ.)</label>
                        <input type="number" name="academic_year" value="<?= $curYear ?>" required class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs font-bold">
                    </div>
                    <div>
                        <label class="block text-[10px] font-black text-slate-400 uppercase mb-1">ภาคเรียน</label>
                        <select name="semester" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs font-bold">
                            <option value="1" <?= $curSem === 1 ? 'selected' : '' ?>>ภาคเรียนที่ 1</option>
                            <option value="2" <?= $curSem === 2 ? 'selected' : '' ?>>ภาคเรียนที่ 2</option>
                        </select>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label class="block text-[10px] font-black text-slate-400 uppercase mb-1">วันเปิดภาคเรียน</label>
                        <input type="date" name="start_date" value="<?= date('Y-05-16') ?>" required class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs font-bold">
                    </div>
                    <div>
                        <label class="block text-[10px] font-black text-slate-400 uppercase mb-1">วันปิดภาคเรียน</label>
                        <input type="date" name="end_date" value="<?= date('Y-10-31') ?>" required class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs font-bold">
                    </div>
                </div>
                <label class="flex items-center gap-2 text-xs font-bold text-slate-600 cursor-pointer">
                    <input type="checkbox" name="is_active" value="1" checked class="rounded text-indigo-600">
                    ตั้งเป็นภาคเรียนที่ใช้งานเป็นค่าเริ่มต้น
                </label>
                <button type="submit" class="w-full bg-indigo-600 text-white py-2.5 rounded-xl text-xs font-bold shadow-md shadow-indigo-200 hover:bg-indigo-700 transition-all">
                    <i class="bi bi-calendar-plus"></i> บันทึกภาคเรียน
                </button>
            </form>
        </div>
    </div>
</div>

<?php elseif ($tab === 'departments'): ?>
<!-- ═════════════════════════════════════════════════════════════════════════
     TAB 2: 8 กลุ่มสาระการเรียนรู้ + กิจกรรมพัฒนาผู้เรียน & ตั้งหัวหน้ากลุ่มสาระ
     ═════════════════════════════════════════════════════════════════════════ -->
<div class="bg-white rounded-2xl shadow-xl shadow-blue-100/50 overflow-hidden border border-slate-100">
    <div class="p-6 border-b border-slate-100">
        <h3 class="text-base font-black text-slate-800">8 กลุ่มสาระการเรียนรู้ และกิจกรรมพัฒนาผู้เรียน</h3>
        <p class="text-xs text-slate-500 mt-1">กำหนดครูผู้ทำหน้าที่หัวหน้ากลุ่มสาระการเรียนรู้ เพื่อตรวจและอนุมัติ ปพ.5 ในกลุ่มสาระของตนเอง</p>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead class="bg-slate-50 border-b border-slate-100">
                <tr>
                    <th class="text-xs font-bold text-slate-400 uppercase px-6 py-4">รหัส</th>
                    <th class="text-xs font-bold text-slate-400 uppercase px-6 py-4">ชื่อกลุ่มสาระการเรียนรู้</th>
                    <th class="text-xs font-bold text-slate-400 uppercase px-6 py-4 text-center">จำนวนวิชา</th>
                    <th class="text-xs font-bold text-slate-400 uppercase px-6 py-4">หัวหน้ากลุ่มสาระ (ผู้อนุมัติขั้นที่ 1)</th>
                    <th class="text-xs font-bold text-slate-400 uppercase px-6 py-4 text-right">บันทึก</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 text-sm">
                <?php foreach ($departments as $d): ?>
                    <tr class="hover:bg-slate-50/70">
                        <form method="POST">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="update_department_head">
                            <input type="hidden" name="department_id" value="<?= (int)$d['id'] ?>">
                            <td class="px-6 py-4">
                                <span class="px-3 py-1 rounded-lg bg-indigo-50 text-indigo-700 font-black text-xs"><?= htmlspecialchars($d['code'], ENT_QUOTES, 'UTF-8') ?></span>
                            </td>
                            <td class="px-6 py-4 font-bold text-slate-800">
                                <?= htmlspecialchars($d['name'], ENT_QUOTES, 'UTF-8') ?>
                                <?php if (!empty($d['is_activity'])): ?>
                                    <span class="ml-2 px-2 py-0.5 rounded-full bg-teal-50 text-teal-700 text-[10px] font-bold">ประเมิน ผ/มผ</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <span class="px-3 py-1 rounded-full bg-slate-100 text-slate-700 text-xs font-bold"><?= (int)$d['subject_count'] ?> วิชา</span>
                            </td>
                            <td class="px-6 py-4">
                                <select name="head_user_id" class="w-full max-w-xs bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs font-bold text-slate-700 outline-none focus:ring-2 focus:ring-blue-500">
                                    <option value="0">— ยังไม่ระบุหัวหน้ากลุ่มสาระ —</option>
                                    <?php foreach ($teachers as $t): ?>
                                        <option value="<?= (int)$t['user_id'] ?>" <?= (int)$d['head_user_id'] === (int)$t['user_id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($t['full_name'] ?: $t['username'], ENT_QUOTES, 'UTF-8') ?> (<?= htmlspecialchars($t['username'], ENT_QUOTES, 'UTF-8') ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-xl text-xs font-bold shadow-md shadow-blue-200 hover:bg-blue-700 transition-all">
                                    <i class="bi bi-check2"></i> บันทึก
                                </button>
                            </td>
                        </form>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php elseif ($tab === 'classrooms'): ?>
<!-- ═════════════════════════════════════════════════════════════════════════
     TAB 3: ห้องเรียน 19 ห้องเรียน (ม.1–ม.6) และครูที่ปรึกษา
     ═════════════════════════════════════════════════════════════════════════ -->
<div class="bg-white rounded-2xl shadow-xl shadow-blue-100/50 overflow-hidden border border-slate-100">
    <div class="p-6 border-b border-slate-100 flex flex-wrap items-center justify-between gap-2">
        <div>
            <h3 class="text-base font-black text-slate-800">จัดการห้องเรียนและครูที่ปรึกษา ทั้ง 19 ห้องเรียน · ภาคเรียนที่ <?= $curSem ?>/<?= $curYear ?></h3>
            <p class="text-xs text-slate-500 mt-1">ครูที่ปรึกษาจะได้รับสิทธิ์ดูผลการเรียนทุกวิชาของนักเรียนในห้องที่ตนเองดูแลโดยอัตโนมัติ</p>
        </div>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead class="bg-slate-50 border-b border-slate-100">
                <tr>
                    <th class="text-xs font-bold text-slate-400 uppercase px-5 py-3.5">ห้องเรียน</th>
                    <th class="text-xs font-bold text-slate-400 uppercase px-4 py-3.5">แผนการเรียน</th>
                    <th class="text-xs font-bold text-slate-400 uppercase px-4 py-3.5 text-center">นักเรียน</th>
                    <th class="text-xs font-bold text-slate-400 uppercase px-4 py-3.5">ครูที่ปรึกษาคนที่ 1</th>
                    <th class="text-xs font-bold text-slate-400 uppercase px-4 py-3.5">ครูที่ปรึกษาคนที่ 2</th>
                    <th class="text-xs font-bold text-slate-400 uppercase px-5 py-3.5 text-right">การดำเนินการ</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 text-sm">
                <?php foreach ($classrooms as $cl): ?>
                    <tr class="hover:bg-slate-50/70">
                        <form method="POST">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="update_classroom_advisor">
                            <input type="hidden" name="classroom_id" value="<?= (int)$cl['id'] ?>">
                            <td class="px-5 py-3">
                                <span class="px-3 py-1 rounded-xl bg-indigo-50 text-indigo-700 font-black text-xs border border-indigo-100">
                                    <?= htmlspecialchars($cl['classroom_name'], ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <input type="text" name="study_plan" value="<?= htmlspecialchars($cl['study_plan'], ENT_QUOTES, 'UTF-8') ?>"
                                       class="w-36 bg-slate-50 border border-slate-200 rounded-xl px-3 py-1.5 text-xs font-bold text-slate-700">
                            </td>
                            <td class="px-4 py-3 text-center">
                                <a href="/pp5/master_data.php?tab=students&room=<?= urlencode($cl['classroom_name']) ?>"
                                   class="px-3 py-1 rounded-full bg-emerald-50 text-emerald-700 text-xs font-bold hover:underline">
                                    <?= (int)$cl['student_count'] ?> คน
                                </a>
                            </td>
                            <td class="px-4 py-3">
                                <select name="advisor_user_id" class="w-52 bg-slate-50 border border-slate-200 rounded-xl px-2.5 py-1.5 text-xs font-bold text-slate-700">
                                    <option value="0">— เลือกครูที่ปรึกษา 1 —</option>
                                    <?php foreach ($teachers as $t): ?>
                                        <option value="<?= (int)$t['user_id'] ?>" <?= (int)$cl['advisor_user_id'] === (int)$t['user_id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($t['full_name'] ?: $t['username'], ENT_QUOTES, 'UTF-8') ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td class="px-4 py-3">
                                <select name="co_advisor_user_id" class="w-52 bg-slate-50 border border-slate-200 rounded-xl px-2.5 py-1.5 text-xs font-bold text-slate-700">
                                    <option value="0">— เลือกครูที่ปรึกษา 2 (ถ้ามี) —</option>
                                    <?php foreach ($teachers as $t): ?>
                                        <option value="<?= (int)$t['user_id'] ?>" <?= (int)$cl['co_advisor_user_id'] === (int)$t['user_id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($t['full_name'] ?: $t['username'], ENT_QUOTES, 'UTF-8') ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td class="px-5 py-3 text-right">
                                <button type="submit" class="bg-blue-600 text-white px-3.5 py-1.5 rounded-xl text-xs font-bold shadow-sm hover:bg-blue-700 transition-all">
                                    บันทึก
                                </button>
                            </td>
                        </form>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php elseif ($tab === 'students'): ?>
<!-- ═════════════════════════════════════════════════════════════════════════
     TAB 4: ข้อมูลนักเรียนรายห้อง & เลขบัตรประชาชนเข้ารหัสตาม PDPA
     ═════════════════════════════════════════════════════════════════════════ -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- ฟอร์มเพิ่ม/แก้ไขนักเรียน -->
    <div class="bg-white rounded-2xl shadow-xl shadow-blue-100/50 p-6 border border-slate-100 h-fit">
        <h3 class="text-base font-black text-slate-800 flex items-center gap-2 mb-4">
            <i class="bi bi-person-plus-fill text-blue-600"></i> เพิ่ม / แก้ไขข้อมูลนักเรียน
        </h3>
        <form method="POST" class="space-y-3" id="studentForm">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="save_student">
            <input type="hidden" name="id" id="stu_id" value="0">

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-[10px] font-black text-slate-400 uppercase mb-1">รหัสนักเรียน (5 หลัก)</label>
                    <input type="text" name="student_code" id="stu_code" required placeholder="เช่น 04849"
                           class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs font-bold">
                </div>
                <div>
                    <label class="block text-[10px] font-black text-slate-400 uppercase mb-1">เลขที่ในห้อง</label>
                    <input type="number" name="student_number" id="stu_number" value="<?= count($students) + 1 ?>" min="1" max="99" required
                           class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs font-bold">
                </div>
            </div>

            <div class="grid grid-cols-3 gap-2">
                <div>
                    <label class="block text-[10px] font-black text-slate-400 uppercase mb-1">คำนำหน้า</label>
                    <select name="prefix" id="stu_prefix" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-2 py-2 text-xs font-bold">
                        <option value="เด็กชาย">เด็กชาย</option>
                        <option value="เด็กหญิง">เด็กหญิง</option>
                        <option value="นาย">นาย</option>
                        <option value="นางสาว">นางสาว</option>
                    </select>
                </div>
                <div>
                    <label class="block text-[10px] font-black text-slate-400 uppercase mb-1">ชื่อ</label>
                    <input type="text" name="first_name" id="stu_fname" required
                           class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs font-bold">
                </div>
                <div>
                    <label class="block text-[10px] font-black text-slate-400 uppercase mb-1">นามสกุล</label>
                    <input type="text" name="last_name" id="stu_lname" required
                           class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs font-bold">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-[10px] font-black text-slate-400 uppercase mb-1">ห้องเรียน</label>
                    <select name="classroom" id="stu_room" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs font-bold">
                        <?php foreach ($classrooms as $cl): ?>
                            <option value="<?= htmlspecialchars($cl['classroom_name'], ENT_QUOTES, 'UTF-8') ?>" <?= $selectedRoom === $cl['classroom_name'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cl['classroom_name'], ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-[10px] font-black text-slate-400 uppercase mb-1">สถานะนักเรียน</label>
                    <select name="status" id="stu_status" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs font-bold">
                        <option value="active">กำลังศึกษา (Active)</option>
                        <option value="moved">ย้ายสถานศึกษา (Moved)</option>
                        <option value="graduated">จบการศึกษา (Graduated)</option>
                        <option value="dropped">ออกกลางคัน (Dropped)</option>
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-[10px] font-black text-slate-400 uppercase mb-1">
                    <i class="bi bi-shield-lock-fill text-emerald-600"></i> เลขบัตรประชาชน 13 หลัก (เข้ารหัส AES-256 ตาม PDPA)
                </label>
                <input type="text" name="citizen_id" id="stu_cid" maxlength="13" placeholder="กรอกเฉพาะเมื่อต้องการตั้งค่า/แก้ไข"
                       class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs font-bold">
            </div>

            <div class="flex gap-2 pt-2">
                <button type="submit" class="flex-1 bg-blue-600 text-white py-2.5 rounded-xl text-xs font-bold shadow-lg shadow-blue-200 hover:bg-blue-700 transition-all">
                    <i class="bi bi-save2-fill"></i> บันทึกข้อมูลนักเรียน
                </button>
                <button type="button" onclick="resetStudentForm()" class="px-3 py-2.5 rounded-xl bg-slate-100 text-slate-600 text-xs font-bold hover:bg-slate-200">
                    ล้างฟอร์ม
                </button>
            </div>
        </form>
    </div>

    <!-- ตารางรายชื่อนักเรียนตามห้อง -->
    <div class="lg:col-span-2 bg-white rounded-2xl shadow-xl shadow-blue-100/50 overflow-hidden border border-slate-100">
        <div class="p-5 border-b border-slate-100">
            <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
                <h3 class="text-base font-black text-slate-800">รายชื่อนักเรียนห้อง <?= htmlspecialchars($selectedRoom, ENT_QUOTES, 'UTF-8') ?> (<?= count($students) ?> คน)</h3>
                <span class="px-3 py-1 rounded-full bg-emerald-50 text-emerald-700 text-xs font-bold">
                    <i class="bi bi-shield-check"></i> เข้ารหัสเลขบัตรประชาชนตาม PDPA
                </span>
            </div>
            <div class="flex flex-wrap gap-1.5">
                <?php foreach ($classrooms as $cl): ?>
                    <a href="/pp5/master_data.php?tab=students&room=<?= urlencode($cl['classroom_name']) ?>"
                       class="px-3 py-1.5 rounded-xl text-xs font-bold transition-all <?= $selectedRoom === $cl['classroom_name'] ? 'bg-indigo-600 text-white shadow-sm' : 'bg-slate-100 text-slate-600 hover:bg-indigo-50' ?>">
                        <?= htmlspecialchars($cl['classroom_name'], ENT_QUOTES, 'UTF-8') ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead class="bg-slate-50 border-b border-slate-100">
                    <tr>
                        <th class="text-xs font-bold text-slate-400 uppercase px-4 py-3 text-center">เลขที่</th>
                        <th class="text-xs font-bold text-slate-400 uppercase px-4 py-3">รหัส</th>
                        <th class="text-xs font-bold text-slate-400 uppercase px-4 py-3">ชื่อ - นามสกุล</th>
                        <th class="text-xs font-bold text-slate-400 uppercase px-4 py-3">เลขบัตรประชาชน (PDPA)</th>
                        <th class="text-xs font-bold text-slate-400 uppercase px-4 py-3 text-center">สถานะ</th>
                        <th class="text-xs font-bold text-slate-400 uppercase px-4 py-3 text-right">แก้ไข</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-xs sm:text-sm">
                    <?php foreach ($students as $st): ?>
                        <tr class="hover:bg-slate-50/80">
                            <td class="px-4 py-3 text-center font-black text-slate-700"><?= (int)$st['student_number'] ?></td>
                            <td class="px-4 py-3 font-mono font-bold text-indigo-700"><?= htmlspecialchars($st['student_code'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="px-4 py-3 font-bold text-slate-800"><?= htmlspecialchars($st['name'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="px-4 py-3 font-mono text-xs text-slate-600"><?= htmlspecialchars($st['citizen_id_display'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="px-4 py-3 text-center">
                                <span class="px-2.5 py-0.5 rounded-full text-[11px] font-bold <?= $st['term_status'] === 'active' ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700' ?>">
                                    <?= htmlspecialchars($st['term_status'], ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <button type="button"
                                        onclick='fillStudentForm(<?= json_encode([
                                            "id"             => (int)$st["id"],
                                            "student_code"   => $st["student_code"],
                                            "student_number" => (int)$st["student_number"],
                                            "prefix"         => $st["prefix"],
                                            "first_name"     => $st["first_name"],
                                            "last_name"      => $st["last_name"],
                                            "classroom"      => $st["classroom"],
                                            "status"         => $st["term_status"],
                                            "citizen_id"     => $st["citizen_id_raw"],
                                        ], JSON_UNESCAPED_UNICODE) ?>)'
                                        class="px-2.5 py-1 rounded-lg bg-slate-100 text-slate-700 hover:bg-blue-50 hover:text-blue-600 text-xs font-bold">
                                    <i class="bi bi-pencil-fill"></i> แก้ไข
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<script>
function fillStudentForm(data) {
    document.getElementById('stu_id').value = data.id || 0;
    document.getElementById('stu_code').value = data.student_code || '';
    document.getElementById('stu_number').value = data.student_number || 1;
    if (data.prefix) document.getElementById('stu_prefix').value = data.prefix;
    document.getElementById('stu_fname').value = data.first_name || '';
    document.getElementById('stu_lname').value = data.last_name || '';
    document.getElementById('stu_room').value = data.classroom || '';
    document.getElementById('stu_status').value = data.status || 'active';
    document.getElementById('stu_cid').value = data.citizen_id || '';
    window.scrollTo({ top: 0, behavior: 'smooth' });
}
function resetStudentForm() {
    document.getElementById('studentForm').reset();
    document.getElementById('stu_id').value = 0;
}
</script>

<?php elseif ($tab === 'subjects'): ?>
<!-- ═════════════════════════════════════════════════════════════════════════
     TAB 5: รายวิชาตามหลักสูตรแกนกลางฯ 2551 และตัวชี้วัด/ผลการเรียนรู้
     ═════════════════════════════════════════════════════════════════════════ -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="space-y-6">
        <!-- ฟอร์มเพิ่ม/แก้ไขรายวิชา -->
        <div class="bg-white rounded-2xl shadow-xl shadow-blue-100/50 p-6 border border-slate-100">
            <h3 class="text-base font-black text-slate-800 flex items-center gap-2 mb-4">
                <i class="bi bi-book-half text-blue-600"></i> เพิ่ม / แก้ไขรายวิชา
            </h3>
            <form method="POST" class="space-y-3" id="subjectForm">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="save_subject">
                <input type="hidden" name="id" id="subj_id" value="0">

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-[10px] font-black text-slate-400 uppercase mb-1">รหัสวิชา</label>
                        <input type="text" name="code" id="subj_code" required placeholder="เช่น ค21101"
                               class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs font-bold">
                    </div>
                    <div>
                        <label class="block text-[10px] font-black text-slate-400 uppercase mb-1">ระดับชั้น</label>
                        <select name="grade_level" id="subj_grade" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs font-bold">
                            <?php for ($g = 1; $g <= 6; $g++): ?>
                                <option value="<?= $g ?>">มัธยมศึกษาปีที่ <?= $g ?> (ม.<?= $g ?>)</option>
                            <?php endfor; ?>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="block text-[10px] font-black text-slate-400 uppercase mb-1">ชื่อรายวิชา</label>
                    <input type="text" name="name" id="subj_name" required placeholder="เช่น คณิตศาสตร์พื้นฐาน 1"
                           class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs font-bold">
                </div>

                <div>
                    <label class="block text-[10px] font-black text-slate-400 uppercase mb-1">กลุ่มสาระการเรียนรู้</label>
                    <select name="department_id" id="subj_dept" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs font-bold">
                        <?php foreach ($departments as $d): ?>
                            <option value="<?= (int)$d['id'] ?>"><?= htmlspecialchars($d['name'], ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="grid grid-cols-3 gap-2">
                    <div>
                        <label class="block text-[10px] font-black text-slate-400 uppercase mb-1">ประเภทวิชา</label>
                        <select name="type" id="subj_type" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-2 py-2 text-xs font-bold">
                            <option value="basic">พื้นฐาน</option>
                            <option value="additional">เพิ่มเติม</option>
                            <option value="activity">กิจกรรมฯ (ผ/มผ)</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[10px] font-black text-slate-400 uppercase mb-1">หน่วยกิต</label>
                        <input type="number" step="0.5" min="0" max="6" name="credit" id="subj_credit" value="1.5"
                               class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs font-bold">
                    </div>
                    <div>
                        <label class="block text-[10px] font-black text-slate-400 uppercase mb-1">ชั่วโมง/ภาค</label>
                        <input type="number" step="10" min="10" max="240" name="hours" id="subj_hours" value="60"
                               class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs font-bold">
                    </div>
                </div>

                <button type="submit" class="w-full bg-blue-600 text-white py-2.5 rounded-xl text-xs font-bold shadow-lg shadow-blue-200 hover:bg-blue-700 transition-all">
                    <i class="bi bi-save2-fill"></i> บันทึกรายวิชา
                </button>
            </form>
        </div>

        <!-- จัดการตัวชี้วัดของวิชาที่เลือก -->
        <?php if ($inspectSubjectId > 0): ?>
        <div class="bg-white rounded-2xl shadow-xl shadow-indigo-100/50 p-6 border border-indigo-200">
            <h3 class="text-sm font-black text-indigo-800 flex items-center gap-2 mb-3">
                <i class="bi bi-list-check"></i> ตัวชี้วัด / ผลการเรียนรู้ (วิชา #<?= $inspectSubjectId ?>)
            </h3>
            <form method="POST" class="space-y-2.5 mb-4">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="save_indicator">
                <input type="hidden" name="subject_id" value="<?= $inspectSubjectId ?>">
                <div class="grid grid-cols-3 gap-2">
                    <input type="text" name="code" required placeholder="รหัส เช่น ค1.1 ม.1/1" class="col-span-2 bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs font-bold">
                    <input type="number" name="sort_order" value="<?= count($indicators) + 1 ?>" min="1" class="bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs font-bold">
                </div>
                <textarea name="description" rows="2" required placeholder="คำอธิบายตัวชี้วัดหรือผลการเรียนรู้..." class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs font-bold"></textarea>
                <button type="submit" class="w-full bg-indigo-600 text-white py-2 rounded-xl text-xs font-bold hover:bg-indigo-700 transition-all">
                    <i class="bi bi-plus-circle"></i> เพิ่มตัวชี้วัด
                </button>
            </form>
            <div class="space-y-2 max-h-64 overflow-y-auto">
                <?php foreach ($indicators as $ind): ?>
                    <div class="p-2.5 rounded-xl bg-slate-50 border border-slate-200 flex items-start justify-between gap-2 text-xs">
                        <div>
                            <span class="font-black text-indigo-700"><?= htmlspecialchars($ind['code'], ENT_QUOTES, 'UTF-8') ?>:</span>
                            <span class="text-slate-600"><?= htmlspecialchars($ind['description'], ENT_QUOTES, 'UTF-8') ?></span>
                        </div>
                        <form method="POST" onsubmit="return confirm('ยืนยันการลบตัวชี้วัดนี้?');">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="delete_indicator">
                            <input type="hidden" name="indicator_id" value="<?= (int)$ind['id'] ?>">
                            <button type="submit" class="text-rose-500 hover:text-rose-700"><i class="bi bi-trash"></i></button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- ตารางรายวิชาทั้งหมด -->
    <div class="lg:col-span-2 bg-white rounded-2xl shadow-xl shadow-blue-100/50 overflow-hidden border border-slate-100">
        <div class="p-5 border-b border-slate-100 flex flex-wrap items-center justify-between gap-3">
            <h3 class="text-base font-black text-slate-800">คลังรายวิชาทั้งหมด (<?= count($subjects) ?> วิชา)</h3>
            <form method="GET" class="flex flex-wrap gap-2">
                <input type="hidden" name="tab" value="subjects">
                <select name="subj_dept" onchange="this.form.submit()" class="bg-slate-50 border border-slate-200 rounded-xl px-3 py-1.5 text-xs font-bold">
                    <option value="0">— ทุกกลุ่มสาระ —</option>
                    <?php foreach ($departments as $d): ?>
                        <option value="<?= (int)$d['id'] ?>" <?= $filterSubjDept === (int)$d['id'] ? 'selected' : '' ?>><?= htmlspecialchars($d['name'], ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
                <select name="subj_grade" onchange="this.form.submit()" class="bg-slate-50 border border-slate-200 rounded-xl px-3 py-1.5 text-xs font-bold">
                    <option value="0">— ทุกระดับชั้น —</option>
                    <?php for ($g = 1; $g <= 6; $g++): ?>
                        <option value="<?= $g ?>" <?= $filterSubjGrade === $g ? 'selected' : '' ?>>ม.<?= $g ?></option>
                    <?php endfor; ?>
                </select>
            </form>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead class="bg-slate-50 border-b border-slate-100">
                    <tr>
                        <th class="text-xs font-bold text-slate-400 uppercase px-4 py-3">รหัสวิชา</th>
                        <th class="text-xs font-bold text-slate-400 uppercase px-4 py-3">ชื่อรายวิชา</th>
                        <th class="text-xs font-bold text-slate-400 uppercase px-4 py-3">กลุ่มสาระ</th>
                        <th class="text-xs font-bold text-slate-400 uppercase px-4 py-3 text-center">นก. / ชม.</th>
                        <th class="text-xs font-bold text-slate-400 uppercase px-4 py-3 text-center">ตัวชี้วัด</th>
                        <th class="text-xs font-bold text-slate-400 uppercase px-4 py-3 text-right">จัดการ</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-xs sm:text-sm">
                    <?php foreach ($subjects as $sb): ?>
                        <tr class="hover:bg-slate-50/80">
                            <td class="px-4 py-3 font-black text-indigo-700"><?= htmlspecialchars($sb['code'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="px-4 py-3 font-bold text-slate-800">
                                <?= htmlspecialchars($sb['name'], ENT_QUOTES, 'UTF-8') ?>
                                <span class="ml-1 text-[11px] text-slate-400">(ม.<?= (int)$sb['grade_level'] ?>)</span>
                            </td>
                            <td class="px-4 py-3 text-xs text-slate-600"><?= htmlspecialchars($sb['department_name'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="px-4 py-3 text-center text-xs font-bold"><?= number_format((float)$sb['credit'], 1) ?> / <?= (int)$sb['hours'] ?></td>
                            <td class="px-4 py-3 text-center">
                                <a href="/pp5/master_data.php?tab=subjects&subj_dept=<?= $filterSubjDept ?>&subj_grade=<?= $filterSubjGrade ?>&inspect_subject_id=<?= (int)$sb['id'] ?>"
                                   class="px-2.5 py-1 rounded-full bg-indigo-50 text-indigo-700 text-xs font-bold hover:bg-indigo-100">
                                    <?= (int)$sb['indicator_count'] ?> ข้อ
                                </a>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <button type="button"
                                        onclick='fillSubjectForm(<?= json_encode($sb, JSON_UNESCAPED_UNICODE) ?>)'
                                        class="px-2.5 py-1 rounded-lg bg-slate-100 text-slate-700 hover:bg-blue-50 hover:text-blue-600 text-xs font-bold">
                                    แก้ไข
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<script>
function fillSubjectForm(sb) {
    document.getElementById('subj_id').value = sb.id || 0;
    document.getElementById('subj_code').value = sb.code || '';
    document.getElementById('subj_name').value = sb.name || '';
    document.getElementById('subj_dept').value = sb.department_id || '';
    document.getElementById('subj_type').value = sb.type || 'basic';
    document.getElementById('subj_credit').value = sb.credit || 1.5;
    document.getElementById('subj_hours').value = sb.hours || 60;
    document.getElementById('subj_grade').value = sb.grade_level || 1;
    window.scrollTo({ top: 0, behavior: 'smooth' });
}
</script>

<?php elseif ($tab === 'courses'): ?>
<!-- ═════════════════════════════════════════════════════════════════════════
     TAB 6: การจัดครูสอนประจำภาคเรียน (1 วิชา × 1 ห้อง = 1 ปพ.5) & ปลดล็อก ปพ.5
     ═════════════════════════════════════════════════════════════════════════ -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="space-y-6">
        <!-- ฟอร์มจัดครูผู้สอน ปพ.5 -->
        <div class="bg-white rounded-2xl shadow-xl shadow-blue-100/50 p-6 border border-slate-100">
            <h3 class="text-base font-black text-slate-800 flex items-center gap-2 mb-4">
                <i class="bi bi-journal-plus text-blue-600"></i> จัดวิชาเปิดสอน ปพ.5 (<?= $curSem ?>/<?= $curYear ?>)
            </h3>
            <form method="POST" class="space-y-3">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="save_course">

                <div>
                    <label class="block text-[10px] font-black text-slate-400 uppercase mb-1">รายวิชา</label>
                    <select name="subject_id" required class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs font-bold">
                        <option value="">— เลือกรายวิชา —</option>
                        <?php foreach ($subjects as $sb): ?>
                            <option value="<?= (int)$sb['id'] ?>"><?= htmlspecialchars($sb['code'] . ' — ' . $sb['name'], ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-[10px] font-black text-slate-400 uppercase mb-1">ห้องเรียน (1 วิชา × 1 ห้อง = 1 ปพ.5)</label>
                    <select name="classroom_id" required class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs font-bold">
                        <?php foreach ($classrooms as $cl): ?>
                            <option value="<?= (int)$cl['id'] ?>"><?= htmlspecialchars($cl['classroom_name'], ENT_QUOTES, 'UTF-8') ?> (<?= (int)$cl['student_count'] ?> คน)</option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-[10px] font-black text-slate-400 uppercase mb-1">ครูผู้สอนหลัก</label>
                    <select name="teacher_user_id" required class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs font-bold">
                        <?php foreach ($teachers as $t): ?>
                            <option value="<?= (int)$t['user_id'] ?>"><?= htmlspecialchars($t['full_name'] ?: $t['username'], ENT_QUOTES, 'UTF-8') ?> (<?= htmlspecialchars($t['username'], ENT_QUOTES, 'UTF-8') ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-[10px] font-black text-slate-400 uppercase mb-1">ครูสอนร่วม (ถ้ามี)</label>
                    <select name="co_teacher_user_id" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs font-bold">
                        <option value="0">— ไม่มีครูสอนร่วม —</option>
                        <?php foreach ($teachers as $t): ?>
                            <option value="<?= (int)$t['user_id'] ?>"><?= htmlspecialchars($t['full_name'] ?: $t['username'], ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-[10px] font-black text-slate-400 uppercase mb-1">คะแนนเก็บ (%)</label>
                        <input type="number" name="ratio_collect" value="70" min="0" max="100" required class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs font-bold">
                    </div>
                    <div>
                        <label class="block text-[10px] font-black text-slate-400 uppercase mb-1">คะแนนปลายภาค (%)</label>
                        <input type="number" name="ratio_final" value="30" min="0" max="100" required class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs font-bold">
                    </div>
                </div>

                <button type="submit" class="w-full bg-blue-600 text-white py-2.5 rounded-xl text-xs font-bold shadow-lg shadow-blue-200 hover:bg-blue-700 transition-all">
                    <i class="bi bi-plus-circle-fill"></i> บันทึกการจัดวิชาเปิดสอน
                </button>
            </form>
        </div>

        <!-- ปุ่มซิงค์ข้อมูลจาก LLW System -->
        <div class="bg-gradient-to-br from-slate-800 to-slate-900 rounded-2xl p-6 text-white shadow-xl">
            <h4 class="text-sm font-black flex items-center gap-2">
                <i class="bi bi-arrow-repeat text-emerald-400"></i> ซิงค์ข้อมูลจากระบบเช็คชื่อ LLW
            </h4>
            <p class="text-xs text-slate-300 mt-1.5 leading-relaxed">
                ดึงข้อมูลนักเรียน 19 ห้องเรียน ครูที่ปรึกษา และรายวิชาจากตาราง <code class="text-emerald-300">att_students</code> และ <code class="text-emerald-300">att_subjects</code> เข้าสู่ระบบ ปพ.5 อัตโนมัติ
            </p>
            <form method="POST" class="mt-4">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="sync_from_llw">
                <button type="submit" class="w-full bg-emerald-500 text-white py-2.5 rounded-xl text-xs font-bold hover:bg-emerald-600 transition-all">
                    <i class="bi bi-cloud-arrow-down-fill"></i> ซิงค์ข้อมูลเดี๋ยวนี้
                </button>
            </form>
        </div>
    </div>

    <!-- ตารางรายวิชาที่เปิดสอน + ปุ่มปลดล็อก ปพ.5 -->
    <div class="lg:col-span-2 bg-white rounded-2xl shadow-xl shadow-blue-100/50 overflow-hidden border border-slate-100">
        <div class="p-5 border-b border-slate-100 flex flex-wrap items-center justify-between gap-2">
            <div>
                <h3 class="text-base font-black text-slate-800">รายวิชาที่เปิดสอนประจำภาคเรียนที่ <?= $curSem ?>/<?= $curYear ?> (<?= count($courses) ?> เล่ม)</h3>
                <p class="text-xs text-slate-400">งานทะเบียนสามารถปลดล็อก ปพ.5 ที่ส่งแล้วหรือล็อกแล้วเพื่อให้ครูแก้ไขได้</p>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead class="bg-slate-50 border-b border-slate-100">
                    <tr>
                        <th class="text-xs font-bold text-slate-400 uppercase px-4 py-3">รหัสวิชา / ชื่อวิชา</th>
                        <th class="text-xs font-bold text-slate-400 uppercase px-4 py-3">ห้อง</th>
                        <th class="text-xs font-bold text-slate-400 uppercase px-4 py-3">ครูผู้สอน</th>
                        <th class="text-xs font-bold text-slate-400 uppercase px-4 py-3 text-center">สัดส่วน</th>
                        <th class="text-xs font-bold text-slate-400 uppercase px-4 py-3 text-center">สถานะ</th>
                        <th class="text-xs font-bold text-slate-400 uppercase px-4 py-3 text-right">ปลดล็อก</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-xs sm:text-sm">
                    <?php foreach ($courses as $cr): ?>
                        <tr class="hover:bg-slate-50/80">
                            <td class="px-4 py-3">
                                <span class="font-black text-indigo-700"><?= htmlspecialchars($cr['subject_code'], ENT_QUOTES, 'UTF-8') ?></span>
                                <span class="block text-xs font-bold text-slate-700"><?= htmlspecialchars($cr['subject_name'], ENT_QUOTES, 'UTF-8') ?></span>
                            </td>
                            <td class="px-4 py-3 font-black text-slate-800"><?= htmlspecialchars($cr['classroom_name'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="px-4 py-3 text-xs font-bold text-slate-700"><?= htmlspecialchars($cr['teacher_name'] ?: '-', ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="px-4 py-3 text-center font-bold text-blue-700"><?= (int)$cr['ratio_collect'] ?>:<?= (int)$cr['ratio_final'] ?></td>
                            <td class="px-4 py-3 text-center">
                                <span class="px-2.5 py-0.5 rounded-full text-[11px] font-bold <?= $cr['status'] === 'draft' ? 'bg-slate-100 text-slate-700' : 'bg-amber-100 text-amber-800' ?>">
                                    <?= htmlspecialchars($cr['status'], ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <?php if ($cr['status'] !== 'draft'): ?>
                                    <button type="button" onclick="promptUnlockCourse(<?= (int)$cr['id'] ?>, '<?= htmlspecialchars($cr['subject_code'] . ' ' . $cr['classroom_name'], ENT_QUOTES, 'UTF-8') ?>')"
                                            class="px-3 py-1 rounded-xl bg-rose-500 text-white text-xs font-bold hover:bg-rose-600 transition-all">
                                        <i class="bi bi-unlock-fill"></i> ปลดล็อก
                                    </button>
                                <?php else: ?>
                                    <span class="text-[11px] text-slate-400">เปิดแก้ไขอยู่</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ฟอร์มซ่อนสำหรับปลดล็อก ปพ.5 -->
<form method="POST" id="unlockCourseForm" class="hidden">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="unlock_course">
    <input type="hidden" name="course_id" id="unlock_course_id" value="0">
    <input type="hidden" name="reason" id="unlock_reason" value="">
</form>
<script>
function promptUnlockCourse(courseId, label) {
    Swal.fire({
        title: 'ปลดล็อกเล่ม ปพ.5 (' + label + ')',
        input: 'text',
        inputLabel: 'กรุณาระบุเหตุผลในการปลดล็อก (จะบันทึกในประวัติระบบ)',
        inputPlaceholder: 'เช่น ครูผู้สอนขอแก้ไขคะแนนสอบปลายภาค',
        showCancelButton: true,
        confirmButtonColor: '#2563eb',
        confirmButtonText: 'ยืนยันปลดล็อก',
        cancelButtonText: 'ยกเลิก',
        inputValidator: (value) => {
            if (!value || !value.trim()) {
                return 'กรุณาระบุเหตุผลในการปลดล็อก!';
            }
        }
    }).then((result) => {
        if (result.isConfirmed) {
            document.getElementById('unlock_course_id').value = courseId;
            document.getElementById('unlock_reason').value = result.value.trim();
            document.getElementById('unlockCourseForm').submit();
        }
    });
}
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/../components/layout_end.php'; ?>
