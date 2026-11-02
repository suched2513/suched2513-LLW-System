<?php
/**
 * pp5/course_setup.php — ตั้งค่ารายวิชา ปพ.5, สัดส่วนคะแนน, ชิ้นงาน/รายการคะแนน และคัดลอกโครงสร้าง
 */

require_once __DIR__ . '/_bootstrap.php';

use PP5\App\Policies\Pp5Policy;
use PP5\App\Requests\CourseRequest;
use PP5\App\Services\CourseService;
use PP5\App\Services\GradeCalculator;
use PP5\App\Services\MasterDataService;

pp5_require_login();

try {

$policy        = new Pp5Policy($pdo);
$courseService = new CourseService($pdo);
$gradeCalc     = new GradeCalculator($pdo);
$masterService = new MasterDataService($pdo);
$termCtx       = pp5_get_term_context($pdo);

$courseId = (int)($_GET['course_id'] ?? $_POST['course_id'] ?? 0);
$course   = $courseService->getCourseById($courseId);

if (!$course) {
    header('Location: /pp5/index.php');
    exit();
}

if (!$policy->canViewCourse($course)) {
    header('Location: /pp5/index.php');
    exit();
}

$canEdit   = $policy->canEditCourse($course);
$flashMsg  = '';
$flashType = 'success';

// ─── ประมวลผล POST Actions ───
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$canEdit) {
        $flashMsg  = 'คุณไม่มีสิทธิ์แก้ไขตั้งค่ารายวิชานี้ หรือรายวิชาถูกล็อกแล้ว';
        $flashType = 'error';
    } else {
        $action = (string)($_POST['form_action'] ?? '');

        try {
            if ($action === 'update_ratio') {
                $val = CourseRequest::validateCourseRatio($_POST, (string)$course['subject_type']);
                if (!($val['ok'] ?? $val['valid'] ?? false)) {
                    $flashMsg  = !empty($val['errors']) ? implode(' ', $val['errors']) : 'สัดส่วนคะแนนไม่ถูกต้อง';
                    $flashType = 'error';
                } else {
                    $courseService->updateCourseRatio(
                        $courseId,
                        $val['data']['ratio_collect'],
                        $val['data']['ratio_final'],
                        $policy->getUserId()
                    );
                    $courseService->recalculateCourseGrades($courseId);
                    $course   = $courseService->getCourseById($courseId);
                    $flashMsg = 'บันทึกสัดส่วนคะแนนเรียบร้อยแล้ว';
                }
            } elseif ($action === 'save_score_item') {
                $val = CourseRequest::validateScoreItem($_POST);
                if (!($val['ok'] ?? $val['valid'] ?? false)) {
                    $flashMsg  = !empty($val['errors']) ? implode(' ', $val['errors']) : 'ข้อมูลชิ้นงานไม่ถูกต้อง';
                    $flashType = 'error';
                } else {
                    $courseService->saveScoreItem($courseId, $val['data'], $policy->getUserId());
                    $courseService->recalculateCourseGrades($courseId);
                    $flashMsg = 'บันทึกรายการคะแนน/ชิ้นงานเรียบร้อยแล้ว';
                }
            } elseif ($action === 'delete_score_item') {
                $itemId = (int)($_POST['item_id'] ?? 0);
                if ($courseService->deleteScoreItem($courseId, $itemId, $policy->getUserId())) {
                    $courseService->recalculateCourseGrades($courseId);
                    $flashMsg = 'ลบรายการคะแนนเรียบร้อยแล้ว';
                } else {
                    $flashMsg  = 'ไม่พบรายการคะแนนที่ต้องการลบ';
                    $flashType = 'error';
                }
            } elseif ($action === 'create_default_structure') {
                $cnt = $courseService->createDefaultScoreStructure($courseId, $policy->getUserId());
                if ($cnt > 0) {
                    $courseService->recalculateCourseGrades($courseId);
                    $flashMsg = "สร้างโครงสร้างรายการคะแนนมาตรฐาน {$cnt} รายการเรียบร้อยแล้ว";
                } else {
                    $flashMsg  = 'รายวิชานี้มีรายการคะแนนอยู่แล้ว ไม่สามารถสร้างซ้ำได้';
                    $flashType = 'warning';
                }
            } elseif ($action === 'copy_from_sibling') {
                $sourceCourseId = (int)($_POST['source_course_id'] ?? 0);
                $overwrite      = !empty($_POST['overwrite_existing']);
                $res            = $courseService->copyStructureFromCourse($sourceCourseId, $courseId, $overwrite, $policy->getUserId());
                if ($res['ok']) {
                    $courseService->recalculateCourseGrades($courseId);
                    $course   = $courseService->getCourseById($courseId);
                    $flashMsg = $res['message'];
                } else {
                    $flashMsg  = $res['message'];
                    $flashType = 'error';
                }
            } elseif ($action === 'save_indicator') {
                $indCode = trim((string)($_POST['indicator_code'] ?? ''));
                $indDesc = trim((string)($_POST['indicator_desc'] ?? ''));
                if ($indCode === '' || $indDesc === '') {
                    $flashMsg  = 'กรุณาระบุรหัสตัวชี้วัดและคำอธิบายตัวชี้วัด';
                    $flashType = 'error';
                } else {
                    $masterService->saveIndicator([
                        'id'          => (int)($_POST['indicator_id'] ?? 0),
                        'subject_id'  => (int)$course['subject_id'],
                        'code'        => $indCode,
                        'description' => $indDesc,
                        'sort_order'  => (int)($_POST['sort_order'] ?? 1),
                    ], $policy->getUserId());
                    $flashMsg = 'บันทึกตัวชี้วัด/ผลการเรียนรู้เรียบร้อยแล้ว';
                }
            } elseif ($action === 'delete_indicator') {
                $indId = (int)($_POST['indicator_id'] ?? 0);
                if ($indId > 0) {
                    $masterService->deleteIndicator($indId);
                    $flashMsg = 'ลบตัวชี้วัดเรียบร้อยแล้ว';
                }
            } elseif ($action === 'save_course_description') {
                $desc = trim((string)($_POST['course_description'] ?? ''));
                $masterService->saveCourseDescription($courseId, (int)$course['subject_id'], $desc, $policy->getUserId());
                $course = $courseService->getCourseById($courseId);
                $flashMsg = 'บันทึกคำอธิบายรายวิชาเรียบร้อยแล้ว';
            } elseif ($action === 'save_indicator_kpa') {
                $kpaItems = $_POST['kpa'] ?? [];
                if (is_array($kpaItems)) {
                    $masterService->saveIndicatorKpaBatch((int)$course['subject_id'], $kpaItems, $policy->getUserId());
                    $flashMsg = 'บันทึกแผนการวัดและประเมินผลตัวชี้วัด (K-P-A) เรียบร้อยแล้ว';
                }
            }
        } catch (Throwable $e) {
            error_log('[PP5 course_setup] ' . $e->getMessage());
            $flashMsg  = 'เกิดข้อผิดพลาดในการบันทึกข้อมูล';
            $flashType = 'error';
        }
    }
}

try {
    $scoreItems     = $courseService->getCourseScoreItems($courseId);
} catch (\Throwable $e) {
    error_log('[PP5 course_setup getCourseScoreItems] ' . $e->getMessage());
    $scoreItems = [];
}

try {
    $indicators     = $masterService->getIndicators((int)$course['subject_id']);
} catch (\Throwable $e) {
    error_log('[PP5 course_setup getIndicators] ' . $e->getMessage());
    $indicators = [];
}

try {
    $siblingCourses = $courseService->getSiblingCoursesForCopy($course);
} catch (\Throwable $e) {
    error_log('[PP5 course_setup getSiblingCoursesForCopy] ' . $e->getMessage());
    $siblingCourses = [];
}

try {
    $ratioCheck = $gradeCalc->validateScoreItemsRatio(
        $scoreItems,
        (int)($course['ratio_collect'] ?? 70),
        (int)($course['ratio_final'] ?? 30),
        (string)($course['subject_type'] ?? 'basic')
    );
} catch (\Throwable $e) {
    error_log('[PP5 course_setup validateRatio] ' . $e->getMessage());
    $ratioCheck = [
        'is_valid'         => false,
        'warnings'         => ['ยังไม่ได้กำหนดรายการคะแนนหรือสัดส่วนไม่ถูกต้อง'],
        'sum_collect'      => 0,
        'sum_final'        => 0,
        'sum_total'        => 0,
        'sum_before_mid'   => 0,
        'sum_midterm'      => 0,
        'sum_after_mid'    => 0,
        'expected_collect' => (int)($course['ratio_collect'] ?? 70),
        'expected_final'   => (int)($course['ratio_final'] ?? 30),
    ];
}

$phasesMeta = [
    'before_mid' => ['label' => '1. คะแนนเก็บก่อนกลางภาค', 'badge' => 'bg-indigo-50 text-indigo-700 border-indigo-200', 'icon' => 'bi-1-circle-fill text-indigo-600'],
    'midterm'    => ['label' => '2. สอบกลางภาค (Midterm)',  'badge' => 'bg-cyan-50 text-cyan-700 border-cyan-200',       'icon' => 'bi-2-circle-fill text-cyan-600'],
    'after_mid'  => ['label' => '3. คะแนนเก็บหลังกลางภาค', 'badge' => 'bg-blue-50 text-blue-700 border-blue-200',       'icon' => 'bi-3-circle-fill text-blue-600'],
    'final'      => ['label' => '4. สอบปลายภาค (Final)',    'badge' => 'bg-rose-50 text-rose-700 border-rose-200',       'icon' => 'bi-4-circle-fill text-rose-600'],
];

$groupedItems = [
    'before_mid' => [],
    'midterm'    => [],
    'after_mid'  => [],
    'final'      => [],
];
foreach ($scoreItems as $si) {
    $ph = $si['phase'] ?? 'before_mid';
    if (isset($groupedItems[$ph])) {
        $groupedItems[$ph][] = $si;
    }
}

$pageTitle    = 'ตั้งค่ารายวิชา & ชิ้นงาน — ' . $course['subject_code'] . ' (' . $course['classroom_name'] . ')';
$pageSubtitle = 'กำหนดสัดส่วนคะแนน รายการคะแนนเก็บ/กลางภาค/ปลายภาค ผูกตัวชี้วัด และคัดลอกโครงสร้าง';
$activeSystem = 'pp5';

require_once __DIR__ . '/../components/layout_start.php';
echo pp5_render_top_bar($pdo, $termCtx, $policy, 'dashboard');
echo pp5_render_course_nav($course, $policy, 'setup');
?>

<?php if ($flashMsg !== ''): ?>
    <div class="mb-6 rounded-2xl p-4 border flex items-center gap-3 <?= $flashType === 'error' ? 'bg-rose-50 border-rose-200 text-rose-700' : ($flashType === 'warning' ? 'bg-amber-50 border-amber-200 text-amber-700' : 'bg-emerald-50 border-emerald-200 text-emerald-700') ?>">
        <i class="bi <?= $flashType === 'error' ? 'bi-exclamation-octagon-fill' : ($flashType === 'warning' ? 'bi-exclamation-triangle-fill' : 'bi-check-circle-fill') ?> text-xl"></i>
        <span class="text-sm font-bold"><?= htmlspecialchars($flashMsg, ENT_QUOTES, 'UTF-8') ?></span>
    </div>
<?php endif; ?>

<!-- แถบตรวจสอบความถูกต้องของคะแนนเต็มรวมเทียบกับสัดส่วนที่ตั้งไว้ (กฎข้อที่ 2) -->
<?php if ($course['subject_type'] !== 'activity'): ?>
    <div class="mb-6 rounded-2xl p-5 border shadow-lg <?= $ratioCheck['is_valid'] ? 'bg-emerald-50/90 border-emerald-200 shadow-emerald-100/50' : 'bg-amber-50/95 border-amber-300 shadow-amber-100/50' ?>">
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
            <div class="flex items-start gap-3.5">
                <div class="w-11 h-11 rounded-2xl flex items-center justify-center shrink-0 <?= $ratioCheck['is_valid'] ? 'bg-emerald-600 text-white' : 'bg-amber-500 text-white' ?>">
                    <i class="bi <?= $ratioCheck['is_valid'] ? 'bi-patch-check-fill' : 'bi-exclamation-triangle-fill' ?> text-xl"></i>
                </div>
                <div>
                    <h3 class="text-sm sm:text-base font-black <?= $ratioCheck['is_valid'] ? 'text-emerald-900' : 'text-amber-900' ?>">
                        <?= $ratioCheck['is_valid']
                            ? 'โครงสร้างคะแนนเต็มรวมถูกต้องตามสัดส่วน (' . (int)$course['ratio_collect'] . ' : ' . (int)$course['ratio_final'] . ' = 100 คะแนน)'
                            : 'แจ้งเตือน: คะแนนเต็มรวมของรายการคะแนนยังไม่ตรงกับสัดส่วนที่ตั้งไว้!' ?>
                    </h3>
                    <?php if (!empty($ratioCheck['warnings'])): ?>
                        <ul class="mt-1.5 space-y-1 text-xs font-semibold text-amber-800 list-disc list-inside">
                            <?php foreach ($ratioCheck['warnings'] as $w): ?>
                                <li><?= htmlspecialchars($w, ENT_QUOTES, 'UTF-8') ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p class="text-xs text-emerald-700 mt-0.5 font-semibold">
                            คะแนนเก็บ (ก่อนกลางภาค <?= $ratioCheck['sum_before_mid'] ?? 0 ?> + กลางภาค <?= $ratioCheck['sum_midterm'] ?? 0 ?> + หลังกลางภาค <?= $ratioCheck['sum_after_mid'] ?? 0 ?> = <?= $ratioCheck['sum_collect'] ?? 0 ?> คะแนน) + ปลายภาค (<?= $ratioCheck['sum_final'] ?? 0 ?> คะแนน) = รวม <?= $ratioCheck['sum_total'] ?? 0 ?> คะแนน
                        </p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- สรุปตัวเลขคะแนนเต็มแต่ละช่วง -->
            <div class="grid grid-cols-3 gap-2 shrink-0">
                <div class="bg-white/90 rounded-xl px-3.5 py-2 border border-slate-200 text-center">
                    <div class="text-[10px] font-black text-slate-400 uppercase">คะแนนเก็บรวม</div>
                    <div class="text-sm font-black <?= abs(($ratioCheck['sum_collect'] ?? 0) - ($ratioCheck['expected_collect'] ?? (int)$course['ratio_collect'])) < 0.01 ? 'text-emerald-600' : 'text-amber-600' ?>">
                        <?= $ratioCheck['sum_collect'] ?? 0 ?> / <?= $ratioCheck['expected_collect'] ?? (int)$course['ratio_collect'] ?>
                    </div>
                </div>
                <div class="bg-white/90 rounded-xl px-3.5 py-2 border border-slate-200 text-center">
                    <div class="text-[10px] font-black text-slate-400 uppercase">สอบปลายภาค</div>
                    <div class="text-sm font-black <?= abs(($ratioCheck['sum_final'] ?? 0) - ($ratioCheck['expected_final'] ?? (int)$course['ratio_final'])) < 0.01 ? 'text-emerald-600' : 'text-amber-600' ?>">
                        <?= $ratioCheck['sum_final'] ?? 0 ?> / <?= $ratioCheck['expected_final'] ?? (int)$course['ratio_final'] ?>
                    </div>
                </div>
                <div class="bg-white/90 rounded-xl px-3.5 py-2 border border-slate-200 text-center">
                    <div class="text-[10px] font-black text-slate-400 uppercase">รวมทั้งหมด</div>
                    <div class="text-sm font-black <?= abs(($ratioCheck['sum_total'] ?? 0) - 100) < 0.01 ? 'text-indigo-600' : 'text-rose-600' ?>">
                        <?= $ratioCheck['sum_total'] ?? 0 ?> / 100
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
    <!-- กล่องที่ 1: ตั้งค่าสัดส่วนคะแนน (เช่น 70:30, 80:20) -->
    <div class="bg-white rounded-2xl shadow-xl shadow-blue-100/50 p-6 border border-slate-100">
        <div class="flex items-center gap-2.5 mb-4">
            <div class="w-9 h-9 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center">
                <i class="bi bi-pie-chart-fill text-lg"></i>
            </div>
            <div>
                <h3 class="text-base font-black text-slate-800">สัดส่วนคะแนนรายวิชา</h3>
                <p class="text-xs text-slate-500">คะแนนเก็บ : สอบปลายภาค (รวมต้องเท่ากับ 100)</p>
            </div>
        </div>

        <?php if ($course['subject_type'] === 'activity'): ?>
            <div class="bg-amber-50 border border-amber-200 rounded-xl p-4 text-xs text-amber-800 font-semibold">
                <i class="bi bi-info-circle-fill"></i> วิชานี้เป็น <strong>กิจกรรมพัฒนาผู้เรียน</strong> ประเมินผลเป็น <strong>ผ่าน (ผ) / ไม่ผ่าน (มผ)</strong> โดยไม่ต้องใช้สัดส่วนคะแนน 100 คะแนน
            </div>
        <?php else: ?>
            <form method="POST" class="space-y-4">
                <input type="hidden" name="form_action" value="update_ratio">
                <input type="hidden" name="course_id" value="<?= $courseId ?>">

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-bold text-slate-600 mb-1">คะแนนเก็บระหว่างภาค</label>
                        <input type="number" name="ratio_collect" id="ratio_collect" min="0" max="100"
                               value="<?= (int)$course['ratio_collect'] ?>"
                               <?= !$canEdit ? 'disabled' : '' ?>
                               oninput="document.getElementById('ratio_final').value = Math.max(0, 100 - (parseInt(this.value || 0, 10)))"
                               class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 text-sm font-black text-indigo-700 outline-none focus:ring-2 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-600 mb-1">คะแนนสอบปลายภาค</label>
                        <input type="number" name="ratio_final" id="ratio_final" min="0" max="100"
                               value="<?= (int)$course['ratio_final'] ?>"
                               <?= !$canEdit ? 'disabled' : '' ?>
                               oninput="document.getElementById('ratio_collect').value = Math.max(0, 100 - (parseInt(this.value || 0, 10)))"
                               class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 text-sm font-black text-rose-600 outline-none focus:ring-2 focus:ring-indigo-500">
                    </div>
                </div>

                <?php if ($canEdit): ?>
                    <div class="flex flex-wrap items-center gap-1.5 text-[11px]">
                        <span class="text-slate-400 font-bold">ค่ามาตรฐาน:</span>
                        <?php foreach ([[70, 30], [80, 20], [60, 40]] as [$rc, $rf]): ?>
                            <button type="button"
                                    onclick="document.getElementById('ratio_collect').value='<?= $rc ?>'; document.getElementById('ratio_final').value='<?= $rf ?>';"
                                    class="px-2.5 py-1 rounded-lg bg-slate-100 hover:bg-indigo-50 text-slate-600 hover:text-indigo-600 font-bold transition-all">
                                <?= $rc ?> : <?= $rf ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                    <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white py-2.5 rounded-xl text-xs font-bold shadow-md shadow-indigo-200 transition-all">
                        <i class="bi bi-save"></i> บันทึกสัดส่วนคะแนน
                    </button>
                <?php endif; ?>
            </form>
        <?php endif; ?>
    </div>

    <!-- กล่องที่ 2: คัดลอกโครงสร้างคะแนนจากห้องอื่น (วิชาเดียวกัน) หรือสร้างโครงสร้างเริ่มต้น -->
    <div class="bg-white rounded-2xl shadow-xl shadow-blue-100/50 p-6 border border-slate-100">
        <div class="flex items-center gap-2.5 mb-4">
            <div class="w-9 h-9 rounded-xl bg-cyan-50 text-cyan-600 flex items-center justify-center">
                <i class="bi bi-Copy text-lg bi-files"></i>
            </div>
            <div>
                <h3 class="text-base font-black text-slate-800">ตัวช่วยสร้าง & คัดลอกโครงสร้าง</h3>
                <p class="text-xs text-slate-500">ลดเวลาตั้งค่าเมื่อสอนวิชาเดียวกันหลายห้อง</p>
            </div>
        </div>

        <?php if (!empty($siblingCourses)): ?>
            <form method="POST" class="space-y-3 mb-4">
                <input type="hidden" name="form_action" value="copy_from_sibling">
                <input type="hidden" name="course_id" value="<?= $courseId ?>">

                <div>
                    <label class="block text-xs font-bold text-slate-600 mb-1">คัดลอกจากห้องเรียนอื่น (วิชา <?= htmlspecialchars($course['subject_code'], ENT_QUOTES, 'UTF-8') ?>)</label>
                    <select name="source_course_id" required <?= !$canEdit ? 'disabled' : '' ?>
                            class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-xs font-bold text-slate-700 outline-none focus:ring-2 focus:ring-cyan-500">
                        <option value="">-- เลือกห้องต้นทางที่ต้องการคัดลอก --</option>
                        <?php foreach ($siblingCourses as $sib): ?>
                            <option value="<?= (int)$sib['id'] ?>">
                                ห้อง <?= htmlspecialchars($sib['classroom_name'], ENT_QUOTES, 'UTF-8') ?>
                                (สัดส่วน <?= (int)$sib['ratio_collect'] ?>:<?= (int)$sib['ratio_final'] ?> — <?= (int)$sib['item_count'] ?> ชิ้นงาน)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <label class="flex items-center gap-2 text-xs text-slate-600 font-semibold cursor-pointer">
                    <input type="checkbox" name="overwrite_existing" value="1" class="rounded text-cyan-600" <?= !$canEdit ? 'disabled' : '' ?>>
                    <span>เขียนทับโครงสร้างเดิม (ถ้ามีรายการคะแนนที่ยังไม่ได้กรอก)</span>
                </label>

                <?php if ($canEdit): ?>
                    <button type="submit" class="w-full bg-cyan-600 hover:bg-cyan-700 text-white py-2.5 rounded-xl text-xs font-bold shadow-md shadow-cyan-200 transition-all">
                        <i class="bi bi-Copy bi-files"></i> คัดลอกสัดส่วนและชิ้นงานมาห้องนี้
                    </button>
                <?php endif; ?>
            </form>
        <?php else: ?>
            <div class="bg-slate-50 border border-slate-200 rounded-xl p-3.5 text-xs text-slate-500 mb-4">
                ยังไม่มีห้องเรียนอื่นที่เปิดสอนวิชา <strong><?= htmlspecialchars($course['subject_code'], ENT_QUOTES, 'UTF-8') ?></strong> ในภาคเรียนนี้
            </div>
        <?php endif; ?>

        <?php if ($canEdit && empty($scoreItems) && $course['subject_type'] !== 'activity'): ?>
            <form method="POST">
                <input type="hidden" name="form_action" value="create_default_structure">
                <input type="hidden" name="course_id" value="<?= $courseId ?>">
                <button type="submit" class="w-full bg-emerald-600 hover:bg-emerald-700 text-white py-2.5 rounded-xl text-xs font-bold shadow-md shadow-emerald-200 transition-all">
                    <i class="bi bi-magic"></i> สร้างชุดรายการคะแนนมาตรฐานอัตโนมัติ (ครบ 100 คะแนน)
                </button>
            </form>
        <?php endif; ?>
    </div>

    <!-- กล่องที่ 3: เพิ่มรายการคะแนน / ชิ้นงานใหม่ -->
    <div class="bg-white rounded-2xl shadow-xl shadow-blue-100/50 p-6 border border-slate-100">
        <div class="flex items-center gap-2.5 mb-4">
            <div class="w-9 h-9 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center">
                <i class="bi bi-plus-circle-fill text-lg"></i>
            </div>
            <div>
                <h3 class="text-base font-black text-slate-800" id="scoreItemFormTitle">เพิ่มรายการคะแนน / ชิ้นงาน</h3>
                <p class="text-xs text-slate-500">กำหนดชื่อชิ้นงาน คะแนนเต็ม และตัวชี้วัด</p>
            </div>
        </div>

        <form method="POST" class="space-y-3" id="scoreItemForm">
            <input type="hidden" name="form_action" value="save_score_item">
            <input type="hidden" name="course_id" value="<?= $courseId ?>">
            <input type="hidden" name="item_id" id="form_item_id" value="0">

            <div class="grid grid-cols-2 gap-2.5">
                <div>
                    <label class="block text-xs font-bold text-slate-600 mb-1">ช่วงการวัดผล</label>
                    <select name="phase" id="form_phase" required <?= !$canEdit ? 'disabled' : '' ?>
                            class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs font-bold text-slate-700 outline-none focus:ring-2 focus:ring-indigo-500">
                        <option value="before_mid">1. ก่อนกลางภาค</option>
                        <option value="midterm">2. สอบกลางภาค</option>
                        <option value="after_mid">3. หลังกลางภาค</option>
                        <option value="final">4. สอบปลายภาค</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-600 mb-1">คะแนนเต็ม</label>
                    <input type="number" step="0.5" min="0.5" max="100" name="max_score" id="form_max_score" value="10" required <?= !$canEdit ? 'disabled' : '' ?>
                           class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs font-black text-indigo-700 outline-none focus:ring-2 focus:ring-indigo-500">
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-600 mb-1">ชื่อรายการคะแนน / ชิ้นงาน</label>
                <input type="text" name="name" id="form_name" placeholder="เช่น ใบงานที่ 1 / ทดสอบย่อยบทที่ 1" required <?= !$canEdit ? 'disabled' : '' ?>
                       class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs font-semibold text-slate-800 outline-none focus:ring-2 focus:ring-indigo-500">
            </div>

            <div class="grid grid-cols-3 gap-2.5">
                <div class="col-span-2">
                    <label class="block text-xs font-bold text-slate-600 mb-1">ตัวชี้วัด / ผลการเรียนรู้</label>
                    <select name="indicator_id" id="form_indicator_id" <?= !$canEdit ? 'disabled' : '' ?>
                            class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs text-slate-700 outline-none focus:ring-2 focus:ring-indigo-500">
                        <option value="">-- ไม่ระบุตัวชี้วัด --</option>
                        <?php foreach ($indicators as $ind): ?>
                            <option value="<?= (int)$ind['id'] ?>">
                                <?= htmlspecialchars($ind['code'] . ' — ' . mb_strimwidth($ind['description'], 0, 45, '...'), ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-600 mb-1">ลำดับที่</label>
                    <input type="number" name="sort_order" id="form_sort_order" value="<?= count($scoreItems) + 1 ?>" min="1" <?= !$canEdit ? 'disabled' : '' ?>
                           class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs font-bold text-slate-700 outline-none focus:ring-2 focus:ring-indigo-500">
                </div>
            </div>

            <?php if ($canEdit): ?>
                <div class="flex items-center gap-2 pt-1">
                    <button type="submit" class="flex-1 bg-emerald-600 hover:bg-emerald-700 text-white py-2.5 rounded-xl text-xs font-bold shadow-md shadow-emerald-200 transition-all">
                        <i class="bi bi-check2-circle"></i> <span id="scoreItemSubmitText">เพิ่มรายการคะแนน</span>
                    </button>
                    <button type="button" id="scoreItemResetBtn" onclick="resetScoreItemForm()" class="hidden px-3 py-2.5 rounded-xl bg-slate-100 text-slate-600 text-xs font-bold hover:bg-slate-200">
                        ยกเลิก
                    </button>
                </div>
            <?php endif; ?>
        </form>
    </div>
</div>

<!-- ตารางรายการคะแนนแยกตาม 4 ช่วงการวัดผล -->
<div class="bg-white rounded-2xl shadow-xl shadow-blue-100/50 border border-slate-100 overflow-hidden mb-6">
    <div class="p-6 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
            <h3 class="text-lg font-black text-slate-800 flex items-center gap-2">
                <i class="bi bi-list-Columns-reverse text-indigo-600"></i>
                รายการคะแนน/ชิ้นงานทั้งหมดของรายวิชา (<?= count($scoreItems) ?> รายการ)
            </h3>
            <p class="text-xs text-slate-500 mt-0.5">แบ่งตาม 4 ช่วง: ก่อนกลางภาค • สอบกลางภาค • หลังกลางภาค • สอบปลายภาค</p>
        </div>
        <a href="/pp5/course_scores.php?course_id=<?= $courseId ?>" class="px-4 py-2.5 rounded-xl bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold shadow-md shadow-blue-200 transition-all flex items-center gap-1.5 self-start sm:self-auto">
            <i class="bi bi-table"></i> ไปหน้ากรอกคะแนนนักเรียน <i class="bi bi-arrow-right"></i>
        </a>
    </div>

    <div class="p-6 grid grid-cols-1 lg:grid-cols-2 gap-6">
        <?php foreach ($phasesMeta as $phaseKey => $pMeta):
            $itemsInPhase = $groupedItems[$phaseKey] ?? [];
            $phaseSum     = 0.0;
            foreach ($itemsInPhase as $it) {
                $phaseSum += (float)$it['max_score'];
            }
        ?>
            <div class="border border-slate-200 rounded-2xl overflow-hidden flex flex-col">
                <div class="bg-slate-50 px-4 py-3 border-b border-slate-200 flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <i class="bi <?= $pMeta['icon'] ?> text-base"></i>
                        <span class="text-sm font-black text-slate-800"><?= $pMeta['label'] ?></span>
                    </div>
                    <span class="px-2.5 py-1 rounded-full border text-xs font-black <?= $pMeta['badge'] ?>">
                        รวม <?= round($phaseSum, 2) ?> คะแนน (<?= count($itemsInPhase) ?> ชิ้น)
                    </span>
                </div>

                <?php if (empty($itemsInPhase)): ?>
                    <div class="p-6 text-center text-xs text-slate-400 flex-1 flex flex-col items-center justify-center">
                        <i class="bi bi-inbox text-2xl mb-1 text-slate-300"></i>
                        ยังไม่มีรายการคะแนนในช่วงนี้
                    </div>
                <?php else: ?>
                    <div class="divide-y divide-slate-100">
                        <?php foreach ($itemsInPhase as $item): ?>
                            <div class="px-4 py-3 flex items-center justify-between gap-3 hover:bg-slate-50/80 transition-all">
                                <div class="min-w-0">
                                    <div class="flex items-center gap-2">
                                        <span class="text-xs font-black text-slate-400">#<?= (int)$item['sort_order'] ?></span>
                                        <span class="text-xs sm:text-sm font-bold text-slate-800 truncate">
                                            <?= htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8') ?>
                                        </span>
                                    </div>
                                    <?php if (!empty($item['indicator_code'])): ?>
                                        <div class="text-[11px] text-indigo-600 font-semibold mt-0.5 truncate">
                                            <i class="bi bi-bookmark-check-fill"></i>
                                            <?= htmlspecialchars($item['indicator_code'] . ': ' . ($item['indicator_description'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <div class="flex items-center gap-2 shrink-0">
                                    <span class="px-2.5 py-1 rounded-lg bg-slate-100 text-slate-800 text-xs font-black">
                                        เต็ม <?= (float)$item['max_score'] ?>
                                    </span>
                                    <?php if ($canEdit): ?>
                                        <button type="button"
                                                onclick='editScoreItem(<?= json_encode([
                                                    'id'           => (int)$item['id'],
                                                    'phase'        => $item['phase'],
                                                    'name'         => $item['name'],
                                                    'max_score'    => (float)$item['max_score'],
                                                    'indicator_id' => $item['indicator_id'] ? (int)$item['indicator_id'] : '',
                                                    'sort_order'   => (int)$item['sort_order'],
                                                ], JSON_UNESCAPED_UNICODE) ?>)'
                                                class="w-7 h-7 rounded-lg bg-indigo-50 text-indigo-600 hover:bg-indigo-100 flex items-center justify-center text-xs transition-all"
                                                title="แก้ไขรายการนี้">
                                            <i class="bi bi-pencil-fill"></i>
                                        </button>
                                        <form method="POST" onsubmit="return confirm('ยืนยันการลบรายการคะแนนนี้? คะแนนที่กรอกไว้ในช่องนี้จะถูกลบด้วย');">
                                            <input type="hidden" name="form_action" value="delete_score_item">
                                            <input type="hidden" name="course_id" value="<?= $courseId ?>">
                                            <input type="hidden" name="item_id" value="<?= (int)$item['id'] ?>">
                                            <button type="submit" class="w-7 h-7 rounded-lg bg-rose-50 text-rose-600 hover:bg-rose-100 flex items-center justify-center text-xs transition-all" title="ลบรายการนี้">
                                                <i class="bi bi-trash3-fill"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- ส่วนจัดการคำอธิบายรายวิชา (สำหรับพิมพ์ ปพ.5 หน้า 2 / ชีต คำอธิบาย) -->
<div class="bg-white rounded-2xl shadow-xl shadow-blue-100/50 border border-slate-100 p-6 mb-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-4">
        <div>
            <h3 class="text-base font-black text-slate-800 flex items-center gap-2">
                <i class="bi bi-file-earmark-text-fill text-indigo-600"></i>
                คำอธิบายรายวิชา (Course Description)
            </h3>
            <p class="text-xs text-slate-500">
                สำหรับแสดงและพิมพ์ในแบบ ปพ.5 หน้าที่ 2 (ชีต "คำอธิบาย") ส่งฝ่ายวิชาการ
            </p>
        </div>
        <div class="flex items-center gap-2">
            <a href="/pp5/course_pdf.php?course_id=<?= $courseId ?>&mode=desc" target="_blank"
               class="px-3.5 py-1.5 rounded-xl bg-slate-100 hover:bg-indigo-50 text-slate-700 hover:text-indigo-600 text-xs font-bold transition-all flex items-center gap-1.5">
                <i class="bi bi-printer-fill"></i> ดูตัวอย่างหน้าพิมพ์ (ชีต คำอธิบาย)
            </a>
        </div>
    </div>

    <form method="POST" class="space-y-4">
        <input type="hidden" name="form_action" value="save_course_description">
        <input type="hidden" name="course_id" value="<?= $courseId ?>">

        <div class="bg-slate-50 border border-slate-200 rounded-xl p-3 text-xs text-slate-600 flex flex-wrap items-center gap-x-4 gap-y-1">
            <span><strong>วิชา:</strong> <?= htmlspecialchars($course['subject_name'], ENT_QUOTES, 'UTF-8') ?></span>
            <span><strong>รหัสวิชา:</strong> <?= htmlspecialchars($course['subject_code'], ENT_QUOTES, 'UTF-8') ?></span>
            <span><strong>ระดับชั้น:</strong> มัธยมศึกษาปีที่ <?= htmlspecialchars($course['grade_level'] . '/' . ($course['room_no'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
            <span><strong>เวลา:</strong> <?= (int)$course['hours'] ?> ชั่วโมง (<?= (float)$course['credit'] ?> หน่วยกิต)</span>
        </div>

        <div>
            <textarea name="course_description" rows="5" <?= !$canEdit ? 'disabled' : '' ?>
                      placeholder="พิมพ์หรือคัดลอกข้อความคำอธิบายรายวิชาตามหลักสูตรแกนกลางฯ หรือของโรงเรียนมาวางที่นี่..."
                      class="w-full bg-slate-50 border border-slate-200 rounded-xl p-3.5 text-xs text-slate-800 leading-relaxed outline-none focus:ring-2 focus:ring-indigo-500 transition-all"><?= htmlspecialchars($course['course_description'] ?? ($course['description'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
        </div>

        <?php if ($canEdit): ?>
            <div class="flex justify-end">
                <button type="submit" class="px-5 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold shadow-md shadow-indigo-200 transition-all flex items-center gap-2">
                    <i class="bi bi-save-fill"></i> บันทึกคำอธิบายรายวิชา
                </button>
            </div>
        <?php endif; ?>
    </form>
</div>

<!-- ส่วนจัดการตัวชี้วัด / ผลการเรียนรู้ประจำรายวิชา -->
<div class="bg-white rounded-2xl shadow-xl shadow-blue-100/50 border border-slate-100 p-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-4">
        <div>
            <h3 class="text-base font-black text-slate-800 flex items-center gap-2">
                <i class="bi bi-bookmark-star-fill text-indigo-600"></i>
                ตัวชี้วัด / ผลการเรียนรู้ของวิชา <?= htmlspecialchars($course['subject_code'] . ' ' . $course['subject_name'], ENT_QUOTES, 'UTF-8') ?>
            </h3>
            <p class="text-xs text-slate-500">ใช้ผูกกับรายการคะแนนและแสดงในรายงาน ปพ.5 (ส่วนที่ 3)</p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2">
            <?php if (empty($indicators)): ?>
                <div class="bg-slate-50 border border-slate-200 rounded-xl p-6 text-center text-xs text-slate-400">
                    ยังไม่มีตัวชี้วัดสำหรับวิชานี้ สามารถเพิ่มได้ทางด้านขวา
                </div>
            <?php else: ?>
                <div class="border border-slate-200 rounded-xl divide-y divide-slate-100 max-h-72 overflow-y-auto">
                    <?php foreach ($indicators as $ind): ?>
                        <div class="px-4 py-2.5 flex items-center justify-between gap-3 text-xs hover:bg-slate-50 transition-colors">
                            <div class="flex items-start gap-3 flex-1 min-w-0">
                                <span class="px-2 py-0.5 rounded bg-indigo-50 text-indigo-700 font-black shrink-0">
                                    <?= htmlspecialchars($ind['code'], ENT_QUOTES, 'UTF-8') ?>
                                </span>
                                <span class="text-slate-700 font-medium">
                                    <?= htmlspecialchars($ind['description'], ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            </div>
                            <?php if ($canEdit): ?>
                                <div class="flex items-center gap-1 shrink-0">
                                    <button type="button" onclick="editIndicator(<?= htmlspecialchars(json_encode($ind), ENT_QUOTES, 'UTF-8') ?>)"
                                            class="p-1.5 text-slate-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition-all" title="แก้ไขตัวชี้วัดนี้">
                                        <i class="bi bi-pencil-fill"></i>
                                    </button>
                                    <form method="POST" class="inline" onsubmit="return confirm('ยืนยันลบตัวชี้วัด <?= htmlspecialchars($ind['code'], ENT_QUOTES, 'UTF-8') ?> หรือไม่?')">
                                        <input type="hidden" name="form_action" value="delete_indicator">
                                        <input type="hidden" name="course_id" value="<?= $courseId ?>">
                                        <input type="hidden" name="indicator_id" value="<?= (int)$ind['id'] ?>">
                                        <button type="submit" class="p-1.5 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition-all" title="ลบตัวชี้วัดนี้">
                                            <i class="bi bi-trash-fill"></i>
                                        </button>
                                    </form>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($canEdit): ?>
            <div class="bg-slate-50 rounded-xl p-4 border border-slate-200">
                <div class="flex items-center justify-between mb-3">
                    <h4 id="indFormTitle" class="text-xs font-black text-slate-700 uppercase tracking-wider">เพิ่มตัวชี้วัด / ผลการเรียนรู้</h4>
                    <button type="button" id="indResetBtn" onclick="resetIndicatorForm()" class="hidden text-[11px] text-slate-500 hover:text-slate-800 font-semibold">
                        <i class="bi bi-x-circle"></i> ยกเลิก
                    </button>
                </div>
                <form method="POST" class="space-y-2.5">
                    <input type="hidden" name="form_action" value="save_indicator">
                    <input type="hidden" name="course_id" value="<?= $courseId ?>">
                    <input type="hidden" name="indicator_id" id="form_ind_id" value="0">
                    <div class="grid grid-cols-3 gap-2">
                        <div class="col-span-2">
                            <input type="text" name="indicator_code" id="form_ind_code" placeholder="รหัส เช่น ท 1.1 ม.2/1" required
                                   class="w-full bg-white border border-slate-200 rounded-xl px-3 py-2 text-xs font-bold text-slate-800 outline-none focus:ring-2 focus:ring-indigo-500">
                        </div>
                        <div>
                            <input type="number" name="sort_order" id="form_ind_sort" value="<?= count($indicators) + 1 ?>" min="1"
                                   class="w-full bg-white border border-slate-200 rounded-xl px-3 py-2 text-xs font-bold text-slate-700 outline-none">
                        </div>
                    </div>
                    <textarea name="indicator_desc" id="form_ind_desc" rows="2" placeholder="คำอธิบายตัวชี้วัดหรือผลการเรียนรู้..." required
                              class="w-full bg-white border border-slate-200 rounded-xl px-3 py-2 text-xs text-slate-800 outline-none focus:ring-2 focus:ring-indigo-500"></textarea>
                    <button type="submit" id="indSubmitBtn" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white py-2 rounded-xl text-xs font-bold transition-all flex items-center justify-center gap-1.5">
                        <i class="bi bi-plus-lg"></i> <span id="indSubmitText">บันทึกตัวชี้วัด</span>
                    </button>
                </form>
            </div>
        <?php endif; ?>
    </div>

    <!-- แผนการวัดและประเมินผลตัวชี้วัด (K-P-A) -->
    <?php if (!empty($indicators)): ?>
    <div class="mt-8 pt-6 border-t border-slate-100">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-3">
            <div>
                <h4 class="text-sm font-black text-slate-800 flex items-center gap-2">
                    <i class="bi bi-table text-indigo-600"></i>
                    แผนการวัดและประเมินผลรายตัวชี้วัด (แจงคะแนน K - P - A - กลางภาค - ปลายภาค)
                </h4>
                <p class="text-[11px] text-slate-500">
                    สำหรับแสดงในแบบ ปพ.5 หน้าที่ 3 (ชีต "ตัวชี้วัด") โดยคะแนนรวมของทุกตัวชี้วัดต้องรวมได้ 100 คะแนน
                </p>
            </div>
            <div>
                <a href="/pp5/course_pdf.php?course_id=<?= $courseId ?>&mode=plan" target="_blank"
                   class="px-3.5 py-1.5 rounded-xl bg-slate-100 hover:bg-indigo-50 text-slate-700 hover:text-indigo-600 text-xs font-bold transition-all flex items-center gap-1.5">
                    <i class="bi bi-printer-fill"></i> ดูตัวอย่างหน้าพิมพ์ (ชีต ตัวชี้วัด)
                </a>
            </div>
        </div>

        <form method="POST">
            <input type="hidden" name="form_action" value="save_indicator_kpa">
            <input type="hidden" name="course_id" value="<?= $courseId ?>">

            <div class="border border-slate-200 rounded-xl overflow-hidden shadow-sm">
                <div class="overflow-x-auto">
                    <table class="w-full text-xs text-left border-collapse" id="kpaTable">
                        <thead>
                            <tr class="bg-slate-100 text-slate-700 font-bold border-b border-slate-200">
                                <th rowspan="2" class="p-3 border-r border-slate-200 min-w-[200px]">ตัวชี้วัด / ผลการเรียนรู้</th>
                                <th colspan="3" class="p-2 border-r border-slate-200 text-center bg-indigo-50/50 text-indigo-900">ประเมินระหว่างเรียน</th>
                                <th rowspan="2" class="p-2 border-r border-slate-200 text-center min-w-[70px]">กลางภาค</th>
                                <th rowspan="2" class="p-2 border-r border-slate-200 text-center min-w-[70px]">ปลายภาค</th>
                                <th rowspan="2" class="p-2 text-center min-w-[70px] bg-slate-200/50">รวม</th>
                            </tr>
                            <tr class="bg-indigo-50/30 text-indigo-800 font-bold border-b border-slate-200 text-center">
                                <th class="p-2 border-r border-slate-200 min-w-[65px]">K (ความรู้)</th>
                                <th class="p-2 border-r border-slate-200 min-w-[65px]">P (ทักษะ)</th>
                                <th class="p-2 border-r border-slate-200 min-w-[65px]">A (เจตคติ)</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            <?php foreach ($indicators as $idx => $ind): 
                                $kVal   = (float)($ind['score_k'] ?? 0);
                                $pVal   = (float)($ind['score_p'] ?? 0);
                                $aVal   = (float)($ind['score_a'] ?? 0);
                                $midVal = (float)($ind['score_midterm'] ?? 0);
                                $finVal = (float)($ind['score_final'] ?? 0);
                                $rowTot = $kVal + $pVal + $aVal + $midVal + $finVal;
                            ?>
                                <tr class="hover:bg-slate-50 transition-colors">
                                    <td class="p-2.5 border-r border-slate-200">
                                        <input type="hidden" name="kpa[<?= $idx ?>][id]" value="<?= (int)$ind['id'] ?>">
                                        <div class="font-bold text-indigo-700"><?= htmlspecialchars($ind['code'], ENT_QUOTES, 'UTF-8') ?></div>
                                        <div class="text-[11px] text-slate-500 line-clamp-1"><?= htmlspecialchars($ind['description'], ENT_QUOTES, 'UTF-8') ?></div>
                                    </td>
                                    <td class="p-1.5 border-r border-slate-200 text-center">
                                        <input type="number" step="0.5" min="0" max="100" name="kpa[<?= $idx ?>][score_k]" value="<?= $kVal > 0 ? $kVal : '' ?>" placeholder="0"
                                               oninput="recalcKpaRow(this)" class="kpa-k w-16 text-center border border-slate-200 rounded-lg py-1 px-1 text-xs font-bold text-slate-800 outline-none focus:border-indigo-500">
                                    </td>
                                    <td class="p-1.5 border-r border-slate-200 text-center">
                                        <input type="number" step="0.5" min="0" max="100" name="kpa[<?= $idx ?>][score_p]" value="<?= $pVal > 0 ? $pVal : '' ?>" placeholder="0"
                                               oninput="recalcKpaRow(this)" class="kpa-p w-16 text-center border border-slate-200 rounded-lg py-1 px-1 text-xs font-bold text-slate-800 outline-none focus:border-indigo-500">
                                    </td>
                                    <td class="p-1.5 border-r border-slate-200 text-center">
                                        <input type="number" step="0.5" min="0" max="100" name="kpa[<?= $idx ?>][score_a]" value="<?= $aVal > 0 ? $aVal : '' ?>" placeholder="0"
                                               oninput="recalcKpaRow(this)" class="kpa-a w-16 text-center border border-slate-200 rounded-lg py-1 px-1 text-xs font-bold text-slate-800 outline-none focus:border-indigo-500">
                                    </td>
                                    <td class="p-1.5 border-r border-slate-200 text-center">
                                        <input type="number" step="0.5" min="0" max="100" name="kpa[<?= $idx ?>][score_midterm]" value="<?= $midVal > 0 ? $midVal : '' ?>" placeholder="0"
                                               oninput="recalcKpaRow(this)" class="kpa-mid w-16 text-center border border-slate-200 rounded-lg py-1 px-1 text-xs font-bold text-slate-800 outline-none focus:border-indigo-500">
                                    </td>
                                    <td class="p-1.5 border-r border-slate-200 text-center">
                                        <input type="number" step="0.5" min="0" max="100" name="kpa[<?= $idx ?>][score_final]" value="<?= $finVal > 0 ? $finVal : '' ?>" placeholder="0"
                                               oninput="recalcKpaRow(this)" class="kpa-fin w-16 text-center border border-slate-200 rounded-lg py-1 px-1 text-xs font-bold text-slate-800 outline-none focus:border-indigo-500">
                                    </td>
                                    <td class="p-2 text-center font-black text-indigo-700 bg-slate-50 kpa-row-total">
                                        <?= $rowTot > 0 ? $rowTot : '0' ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr class="bg-slate-100 font-black border-t-2 border-slate-300 text-slate-800">
                                <td class="p-2.5 border-r border-slate-200 text-right pr-4">รวมคะแนนทั้งหมด</td>
                                <td class="p-2 border-r border-slate-200 text-center text-indigo-700" id="kpaSumK">0</td>
                                <td class="p-2 border-r border-slate-200 text-center text-indigo-700" id="kpaSumP">0</td>
                                <td class="p-2 border-r border-slate-200 text-center text-indigo-700" id="kpaSumA">0</td>
                                <td class="p-2 border-r border-slate-200 text-center text-indigo-700" id="kpaSumMid">0</td>
                                <td class="p-2 border-r border-slate-200 text-center text-indigo-700" id="kpaSumFin">0</td>
                                <td class="p-2 text-center text-base font-black bg-indigo-50 text-indigo-800" id="kpaSumAll">0</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <?php if ($canEdit): ?>
                <div class="mt-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <div class="text-xs font-semibold text-slate-500 flex items-center gap-2">
                        <span id="kpaStatusBadge" class="px-2.5 py-1 rounded-full text-xs font-bold bg-amber-50 text-amber-700">กำลังตรวจสอบ...</span>
                        <span>(คะแนนรวมทุกช่องต้องเท่ากับ 100 คะแนน)</span>
                    </div>
                    <button type="submit" class="px-5 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold shadow-md shadow-indigo-200 transition-all flex items-center gap-2">
                        <i class="bi bi-save-fill"></i> บันทึกแผนการวัดและประเมินผล (K-P-A)
                    </button>
                </div>
            <?php endif; ?>
        </form>
    </div>
    <?php endif; ?>
</div>

<script>
function editScoreItem(item) {
    document.getElementById('form_item_id').value = item.id;
    document.getElementById('form_phase').value = item.phase;
    document.getElementById('form_name').value = item.name;
    document.getElementById('form_max_score').value = item.max_score;
    document.getElementById('form_indicator_id').value = item.indicator_id || '';
    document.getElementById('form_sort_order').value = item.sort_order;
    document.getElementById('scoreItemFormTitle').textContent = 'แก้ไขรายการคะแนน #' + item.sort_order;
    document.getElementById('scoreItemSubmitText').textContent = 'บันทึกการแก้ไข';
    document.getElementById('scoreItemResetBtn').classList.remove('hidden');
    document.getElementById('form_name').focus();
}

function resetScoreItemForm() {
    document.getElementById('form_item_id').value = '0';
    document.getElementById('form_name').value = '';
    document.getElementById('form_max_score').value = '10';
    document.getElementById('form_indicator_id').value = '';
    document.getElementById('scoreItemFormTitle').textContent = 'เพิ่มรายการคะแนน / ชิ้นงาน';
    document.getElementById('scoreItemSubmitText').textContent = 'เพิ่มรายการคะแนน';
    document.getElementById('scoreItemResetBtn').classList.add('hidden');
}

function editIndicator(ind) {
    var idEl = document.getElementById('form_ind_id');
    var codeEl = document.getElementById('form_ind_code');
    var sortEl = document.getElementById('form_ind_sort');
    var descEl = document.getElementById('form_ind_desc');
    var titleEl = document.getElementById('indFormTitle');
    var submitTextEl = document.getElementById('indSubmitText');
    var resetBtn = document.getElementById('indResetBtn');

    if (!idEl || !codeEl) return;

    idEl.value = ind.id || 0;
    codeEl.value = ind.code || '';
    sortEl.value = ind.sort_order || 1;
    descEl.value = ind.description || '';
    if (titleEl) titleEl.textContent = 'แก้ไขตัวชี้วัด ' + (ind.code || '');
    if (submitTextEl) submitTextEl.textContent = 'บันทึกการแก้ไข';
    if (resetBtn) resetBtn.classList.remove('hidden');
    codeEl.focus();
}

function resetIndicatorForm() {
    var idEl = document.getElementById('form_ind_id');
    var codeEl = document.getElementById('form_ind_code');
    var sortEl = document.getElementById('form_ind_sort');
    var descEl = document.getElementById('form_ind_desc');
    var titleEl = document.getElementById('indFormTitle');
    var submitTextEl = document.getElementById('indSubmitText');
    var resetBtn = document.getElementById('indResetBtn');

    if (idEl) idEl.value = '0';
    if (codeEl) codeEl.value = '';
    if (sortEl) sortEl.value = '<?= count($indicators) + 1 ?>';
    if (descEl) descEl.value = '';
    if (titleEl) titleEl.textContent = 'เพิ่มตัวชี้วัด / ผลการเรียนรู้';
    if (submitTextEl) submitTextEl.textContent = 'บันทึกตัวชี้วัด';
    if (resetBtn) resetBtn.classList.add('hidden');
}

function recalcKpaTable() {
    var rows = document.querySelectorAll('#kpaTable tbody tr');
    var sumK = 0, sumP = 0, sumA = 0, sumMid = 0, sumFin = 0;

    rows.forEach(function(row) {
        var k = parseFloat(row.querySelector('.kpa-k')?.value || 0) || 0;
        var p = parseFloat(row.querySelector('.kpa-p')?.value || 0) || 0;
        var a = parseFloat(row.querySelector('.kpa-a')?.value || 0) || 0;
        var mid = parseFloat(row.querySelector('.kpa-mid')?.value || 0) || 0;
        var fin = parseFloat(row.querySelector('.kpa-fin')?.value || 0) || 0;
        var rTot = k + p + a + mid + fin;

        var totEl = row.querySelector('.kpa-row-total');
        if (totEl) totEl.textContent = rTot > 0 ? (rTot % 1 === 0 ? rTot : rTot.toFixed(1)) : '0';

        sumK += k;
        sumP += p;
        sumA += a;
        sumMid += mid;
        sumFin += fin;
    });

    var sumAll = sumK + sumP + sumA + sumMid + sumFin;

    var elK = document.getElementById('kpaSumK');
    var elP = document.getElementById('kpaSumP');
    var elA = document.getElementById('kpaSumA');
    var elMid = document.getElementById('kpaSumMid');
    var elFin = document.getElementById('kpaSumFin');
    var elAll = document.getElementById('kpaSumAll');
    var badge = document.getElementById('kpaStatusBadge');

    if (elK) elK.textContent = sumK % 1 === 0 ? sumK : sumK.toFixed(1);
    if (elP) elP.textContent = sumP % 1 === 0 ? sumP : sumP.toFixed(1);
    if (elA) elA.textContent = sumA % 1 === 0 ? sumA : sumA.toFixed(1);
    if (elMid) elMid.textContent = sumMid % 1 === 0 ? sumMid : sumMid.toFixed(1);
    if (elFin) elFin.textContent = sumFin % 1 === 0 ? sumFin : sumFin.toFixed(1);
    if (elAll) elAll.textContent = sumAll % 1 === 0 ? sumAll : sumAll.toFixed(1);

    if (badge) {
        if (Math.abs(sumAll - 100) < 0.01) {
            badge.className = 'px-2.5 py-1 rounded-full text-xs font-bold bg-emerald-50 text-emerald-700 border border-emerald-200';
            badge.innerHTML = '<i class="bi bi-check-circle-fill"></i> คะแนนรวมครบ 100 คะแนนถูกต้อง';
        } else {
            badge.className = 'px-2.5 py-1 rounded-full text-xs font-bold bg-amber-50 text-amber-700 border border-amber-200';
            badge.innerHTML = '<i class="bi bi-exclamation-triangle-fill"></i> คะแนนรวมปัจจุบัน: ' + (sumAll % 1 === 0 ? sumAll : sumAll.toFixed(1)) + ' / 100 คะแนน';
        }
    }
}

function recalcKpaRow(input) {
    recalcKpaTable();
}

document.addEventListener('DOMContentLoaded', function() {
    recalcKpaTable();
});
</script>


<?php
require_once __DIR__ . '/../components/layout_end.php';
} catch (\Throwable $_page_err) {
    error_log('[PP5 course_setup] ' . $_page_err->getMessage() . ' in ' . $_page_err->getFile() . ':' . $_page_err->getLine());
    $pageTitle = 'เกิดข้อผิดพลาดในการโหลดหน้าตั้งค่ารายวิชา';
    $pageSubtitle = htmlspecialchars($_page_err->getMessage(), ENT_QUOTES, 'UTF-8');
    $activeSystem = 'pp5';
    require_once __DIR__ . '/../components/layout_start.php';
    ?>
    <div class="bg-rose-50 border border-rose-200 rounded-2xl p-6 text-rose-800 m-6 shadow-sm">
        <h3 class="text-base font-black flex items-center gap-2 mb-2">
            <i class="bi bi-exclamation-octagon-fill text-rose-600 text-xl"></i> ไม่สามารถโหลดหน้าตั้งค่ารายวิชาได้
        </h3>
        <p class="text-xs text-rose-700 leading-relaxed font-semibold">
            ข้อผิดพลาด: <?= htmlspecialchars($_page_err->getMessage(), ENT_QUOTES, 'UTF-8') ?>
        </p>
        <div class="mt-4 flex items-center gap-2">
            <a href="/pp5/index.php" class="px-4 py-2 rounded-xl bg-indigo-600 text-white text-xs font-bold hover:bg-indigo-700 transition-all">
                กลับแดชบอร์ด ปพ.5
            </a>
            <a href="/pp5/course_scores.php?course_id=<?= (int)($_GET['course_id'] ?? 0) ?>" class="px-4 py-2 rounded-xl bg-slate-100 text-slate-700 text-xs font-bold hover:bg-slate-200 transition-all">
                ไปหน้ากรอกคะแนน
            </a>
        </div>
    </div>
    <?php
    require_once __DIR__ . '/../components/layout_end.php';
}
