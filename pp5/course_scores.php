<?php
/**
 * pp5/course_scores.php — ตารางกรอกคะแนนแบบ Excel (Clipboard Paste, Keyboard Nav, Auto-save) & ตัดเกรดอัตโนมัติ
 */

require_once __DIR__ . '/_bootstrap.php';

use PP5\App\Policies\Pp5Policy;
use PP5\App\Services\AttendanceService;
use PP5\App\Services\CourseService;
use PP5\App\Services\GradeCalculator;

pp5_require_login();

$policy            = new Pp5Policy($pdo);
$courseService     = new CourseService($pdo);
$attendanceService = new AttendanceService($pdo);
$gradeCalc         = new GradeCalculator($pdo);
$termCtx           = pp5_get_term_context($pdo);

$courseId = (int)($_GET['course_id'] ?? 0);
$course   = $courseService->getCourseById($courseId);

if (!$course || !$policy->canViewCourse($course)) {
    header('Location: /pp5/index.php');
    exit();
}

$canEdit    = $policy->canEditCourse($course);
$students   = $courseService->getCourseStudents($course);
$scoreItems = $courseService->getCourseScoreItems($courseId);
$scoresGrid = $courseService->getCourseScoresGrid($courseId);

// คำนวณเกรดล่าสุดให้เป็นปัจจุบันเสมอเมื่อเปิดหน้าตารางคะแนน
$gradesMap  = $courseService->recalculateCourseGrades($courseId);

$ratioCheck = $gradeCalc->validateScoreItemsRatio(
    $scoreItems,
    (int)$course['ratio_collect'],
    (int)$course['ratio_final'],
    (string)$course['subject_type']
);

// จัดกลุ่มรายการคะแนนตาม 4 ช่วง
$phasesMeta = [
    'before_mid' => ['label' => 'ก่อนกลางภาค', 'thBg' => 'bg-indigo-50 text-indigo-800 border-indigo-200'],
    'midterm'    => ['label' => 'สอบกลางภาค',  'thBg' => 'bg-cyan-50 text-cyan-800 border-cyan-200'],
    'after_mid'  => ['label' => 'หลังกลางภาค', 'thBg' => 'bg-blue-50 text-blue-800 border-blue-200'],
    'final'      => ['label' => 'สอบปลายภาค',  'thBg' => 'bg-rose-50 text-rose-800 border-rose-200'],
];

$itemsByPhase = [
    'before_mid' => [],
    'midterm'    => [],
    'after_mid'  => [],
    'final'      => [],
];
$orderedItems = [];
foreach ($scoreItems as $si) {
    $ph = $si['phase'] ?? 'before_mid';
    if (isset($itemsByPhase[$ph])) {
        $itemsByPhase[$ph][] = $si;
    }
}
foreach (['before_mid', 'midterm', 'after_mid', 'final'] as $phKey) {
    foreach ($itemsByPhase[$phKey] as $si) {
        $orderedItems[] = $si;
    }
}

// สถิติการกระจายเกรด
$dist = [
    '4' => 0, '3.5' => 0, '3' => 0, '2.5' => 0,
    '2' => 0, '1.5' => 0, '1' => 0, '0' => 0,
    'ร' => 0, 'มส' => 0, 'ผ' => 0, 'มผ' => 0,
];
foreach ($students as $std) {
    $sid = (int)$std['id'];
    $g   = (string)($gradesMap[$sid]['grade'] ?? '');
    if (isset($dist[$g])) {
        $dist[$g]++;
    }
}

$gradeBadgeClass = function (string $g): string {
    return match ($g) {
        '4', '3.5', 'ผ' => 'bg-emerald-100 text-emerald-800 border-emerald-300',
        '3', '2.5'      => 'bg-blue-100 text-blue-800 border-blue-300',
        '2', '1.5', '1' => 'bg-indigo-100 text-indigo-800 border-indigo-300',
        '0', 'มผ'       => 'bg-rose-100 text-rose-800 border-rose-300',
        'ร'             => 'bg-amber-100 text-amber-800 border-amber-300',
        'มส'            => 'bg-purple-100 text-purple-800 border-purple-300',
        default         => 'bg-slate-100 text-slate-600 border-slate-200',
    };
};

$pageTitle    = 'ตารางกรอกคะแนน & ตัดเกรด — ' . $course['subject_code'] . ' (' . $course['classroom_name'] . ')';
$pageSubtitle = 'กรอกคะแนนแบบ Excel (วางข้อมูลจาก Clipboard, เลื่อนด้วยปุ่มลูกศร/Enter, บันทึกอัตโนมัติ และตัดเกรดทันที)';
$activeSystem = 'pp5';

require_once __DIR__ . '/../components/layout_start.php';
echo pp5_render_top_bar($pdo, $termCtx, $policy, 'dashboard');
echo pp5_render_course_nav($course, $policy, 'scores');
?>

<!-- แจ้งเตือนกรณีคะแนนเต็มรวมไม่ตรงกับสัดส่วนที่ตั้งไว้ (กฎข้อที่ 2) -->
<?php if (!$ratioCheck['is_valid'] && $course['subject_type'] !== 'activity'): ?>
    <div class="mb-5 rounded-2xl p-4 bg-amber-50 border border-amber-300 shadow-md shadow-amber-100/50 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div class="flex items-start gap-3">
            <i class="bi bi-exclamation-triangle-fill text-amber-600 text-xl shrink-0 mt-0.5"></i>
            <div class="text-xs text-amber-900">
                <strong class="font-black text-sm">คำเตือนสัดส่วนคะแนน:</strong>
                <ul class="list-disc list-inside mt-1 space-y-0.5 font-semibold">
                    <?php foreach ($ratioCheck['warnings'] as $w): ?>
                        <li><?= htmlspecialchars($w, ENT_QUOTES, 'UTF-8') ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
        <a href="/pp5/course_setup.php?course_id=<?= $courseId ?>" class="px-3.5 py-2 rounded-xl bg-amber-600 hover:bg-amber-700 text-white text-xs font-bold shrink-0 self-start sm:self-center">
            <i class="bi bi-sliders"></i> ไปตั้งค่ารายการคะแนน
        </a>
    </div>
<?php endif; ?>

<!-- แถบสรุปการกระจายผลการเรียน (Grade Distribution Strip) -->
<div class="bg-white rounded-2xl shadow-xl shadow-blue-100/50 p-4 border border-slate-100 mb-5">
    <div class="flex flex-wrap items-center justify-between gap-3 mb-3">
        <div class="flex items-center gap-2">
            <i class="bi bi-bar-chart-steps text-indigo-600"></i>
            <span class="text-xs sm:text-sm font-black text-slate-800">สรุปผลการเรียนทั้งห้อง (<?= count($students) ?> คน)</span>
            <span class="text-[11px] text-slate-400 font-semibold hidden sm:inline">• อัปเดตแบบเรียลไทม์เมื่อกรอกคะแนน (ปัดคะแนนรวมสุทธิเป็นจำนวนเต็มก่อนตัดเกรด)</span>
        </div>

        <!-- สถานะ Auto-save และปุ่มคำนวณใหม่ -->
        <div class="flex items-center gap-2">
            <div id="autosaveStatus" class="px-3 py-1.5 rounded-xl bg-emerald-50 text-emerald-700 border border-emerald-200 text-xs font-bold flex items-center gap-1.5">
                <i class="bi bi-cloud-check-fill"></i>
                <span>พร้อมบันทึกอัตโนมัติ</span>
            </div>
            <?php if ($canEdit): ?>
                <button type="button" onclick="triggerRecalculateGrades()" class="px-3 py-1.5 rounded-xl bg-indigo-50 hover:bg-indigo-100 text-indigo-700 text-xs font-bold transition-all flex items-center gap-1">
                    <i class="bi bi-calculator-fill"></i> คำนวณเกรดใหม่ทั้งห้อง
                </button>
            <?php endif; ?>
        </div>
    </div>

    <div class="grid grid-cols-4 sm:grid-cols-6 lg:grid-cols-10 gap-2">
        <?php
        $distKeys = $course['subject_type'] === 'activity'
            ? ['ผ', 'มผ', 'ร', 'มส']
            : ['4', '3.5', '3', '2.5', '2', '1.5', '1', '0', 'ร', 'มส'];
        foreach ($distKeys as $gKey):
        ?>
            <div class="rounded-xl border px-3 py-2 text-center <?= $gradeBadgeClass($gKey) ?>">
                <div class="text-[10px] font-black uppercase opacity-75">เกรด <?= htmlspecialchars($gKey, ENT_QUOTES, 'UTF-8') ?></div>
                <div class="text-lg font-black" id="dist_count_<?= htmlspecialchars($gKey, ENT_QUOTES, 'UTF-8') ?>">
                    <?= (int)($dist[$gKey] ?? 0) ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<?php if (empty($orderedItems)): ?>
    <!-- กรณีรายวิชายังไม่ได้สร้างรายการคะแนน -->
    <div class="bg-white rounded-2xl shadow-xl shadow-blue-100/50 p-10 text-center border border-slate-100">
        <div class="w-16 h-16 rounded-2xl bg-indigo-50 text-indigo-600 flex items-center justify-center mx-auto mb-4 text-3xl">
            <i class="bi bi-journal-plus"></i>
        </div>
        <h3 class="text-lg font-black text-slate-800">ยังไม่มีรายการคะแนน / ชิ้นงานในรายวิชานี้</h3>
        <p class="text-xs sm:text-sm text-slate-500 mt-1 max-w-lg mx-auto">
            กรุณาตั้งค่ารายการคะแนน (ก่อนกลางภาค, สอบกลางภาค, หลังกลางภาค, สอบปลายภาค) หรือกดสร้างโครงสร้างมาตรฐานอัตโนมัติก่อนเริ่มกรอกคะแนน
        </p>
        <div class="mt-5 flex flex-wrap items-center justify-center gap-3">
            <a href="/pp5/course_setup.php?course_id=<?= $courseId ?>" class="px-5 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold shadow-lg shadow-indigo-200 transition-all">
                <i class="bi bi-sliders"></i> ไปหน้าตั้งค่ารายวิชา & เพิ่มรายการคะแนน
            </a>
        </div>
    </div>
<?php else: ?>
    <!-- คำแนะนำคีย์ลัดการใช้งานตารางแบบ Excel -->
    <div class="bg-slate-800 text-white rounded-t-2xl px-4 py-2.5 flex flex-wrap items-center justify-between gap-2 text-xs">
        <div class="flex flex-wrap items-center gap-x-4 gap-y-1">
            <span class="font-bold text-indigo-300"><i class="bi bi-keyboard-fill"></i> โหมดตาราง Excel:</span>
            <span><kbd class="px-1.5 py-0.5 rounded bg-slate-700 text-[11px] font-mono">Ctrl+V</kbd> วางคะแนนจาก Excel ได้หลายแถว/คอลัมน์พร้อมกัน</span>
            <span><kbd class="px-1.5 py-0.5 rounded bg-slate-700 text-[11px] font-mono">Enter</kbd> / <kbd class="px-1.5 py-0.5 rounded bg-slate-700 text-[11px] font-mono">↑↓←→</kbd> เลื่อนช่องถัดไป</span>
            <span><strong class="text-amber-300">เว้นว่าง (ลบตัวเลขออก)</strong> = ยังไม่ส่งงาน/ขาดสอบ (<strong class="text-amber-300">ติด ร</strong>)</span>
        </div>
        <span class="text-[11px] text-slate-300">ห้ามกรอกเกินคะแนนเต็มของแต่ละช่อง ระบบจะบล็อกอัตโนมัติ</span>
    </div>

    <!-- ตารางกรอกคะแนนแบบ Excel -->
    <div class="bg-white rounded-b-2xl shadow-xl shadow-blue-100/50 border border-t-0 border-slate-200 overflow-x-auto">
        <table class="w-full text-left border-collapse select-none" id="pp5ScoreGridTable">
            <thead>
                <!-- แถวหัวตารางชั้นที่ 1: กลุ่มช่วงการวัดผล -->
                <tr class="bg-slate-100 border-b border-slate-300 text-[11px] font-black uppercase tracking-wider text-center">
                    <th rowspan="2" class="py-3 px-2.5 border-r border-slate-300 bg-slate-100 sticky left-0 z-20 w-12">เลขที่</th>
                    <th rowspan="2" class="py-3 px-3 border-r border-slate-300 bg-slate-100 w-24">รหัส</th>
                    <th rowspan="2" class="py-3 px-3 border-r border-slate-300 bg-slate-100 min-w-[180px] text-left">ชื่อ - นามสกุล</th>

                    <?php foreach ($phasesMeta as $phKey => $phCfg):
                        $cntInPh = count($itemsByPhase[$phKey]);
                        if ($cntInPh === 0) continue;
                    ?>
                        <th colspan="<?= $cntInPh ?>" class="py-2 px-2 border-r border-slate-300 <?= $phCfg['thBg'] ?>">
                            <?= $phCfg['label'] ?>
                        </th>
                    <?php endforeach; ?>

                    <th colspan="6" class="py-2 px-2 bg-slate-800 text-white border-l border-slate-700">
                        สรุปคะแนน & ผลการเรียน (คำนวณอัตโนมัติ)
                    </th>
                </tr>

                <!-- แถวหัวตารางชั้นที่ 2: ชื่อชิ้นงาน + คะแนนเต็ม -->
                <tr class="bg-slate-50 border-b-2 border-slate-300 text-[11px] font-bold text-slate-700 text-center">
                    <?php foreach ($orderedItems as $colIdx => $item):
                        $phKey = $item['phase'];
                        $phBadge = $phasesMeta[$phKey]['thBg'] ?? 'bg-slate-50';
                    ?>
                        <th class="py-2.5 px-2 border-r border-slate-200 min-w-[92px] max-w-[120px] align-top <?= $phBadge ?>">
                            <div class="font-black text-slate-800 truncate" title="<?= htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8') ?>">
                                <?= htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8') ?>
                            </div>
                            <?php if (!empty($item['indicator_code'])): ?>
                                <div class="text-[10px] text-indigo-600 font-semibold truncate">
                                    <?= htmlspecialchars($item['indicator_code'], ENT_QUOTES, 'UTF-8') ?>
                                </div>
                            <?php endif; ?>
                            <div class="mt-1 inline-block px-2 py-0.5 rounded bg-white/90 border border-slate-300 text-[10px] font-black text-slate-900">
                                เต็ม <?= (float)$item['max_score'] ?>
                            </div>
                        </th>
                    <?php endforeach; ?>

                    <th class="py-2.5 px-2.5 border-r border-slate-200 bg-indigo-50/70 text-indigo-900 font-black w-20">
                        รวมเก็บ<br><span class="text-[10px] font-bold">(<?= (int)$course['ratio_collect'] ?>)</span>
                    </th>
                    <th class="py-2.5 px-2.5 border-r border-slate-200 bg-rose-50/70 text-rose-900 font-black w-20">
                        ปลายภาค<br><span class="text-[10px] font-bold">(<?= (int)$course['ratio_final'] ?>)</span>
                    </th>
                    <th class="py-2.5 px-2.5 border-r border-slate-200 bg-slate-100 text-slate-900 font-black w-20" title="ปัดเป็นจำนวนเต็มแบบครึ่งขึ้น (Half-up) ก่อนตัดเกรด">
                        รวมสุทธิ<br><span class="text-[10px] font-bold">(100)</span>
                    </th>
                    <th class="py-2.5 px-2.5 border-r border-slate-200 bg-emerald-50/60 text-emerald-900 font-black w-20">
                        เวลาเรียน<br><span class="text-[10px] font-bold">(%)</span>
                    </th>
                    <th class="py-2.5 px-3 border-r border-slate-200 bg-slate-100 text-slate-900 font-black w-20">
                        เกรด
                    </th>
                    <th class="py-2.5 px-2.5 bg-slate-50 text-slate-600 font-bold w-24">
                        หมายเหตุ
                    </th>
                </tr>
            </thead>

            <tbody class="divide-y divide-slate-200 text-xs">
                <?php foreach ($students as $rowIdx => $std):
                    $sid  = (int)$std['id'];
                    $gRow = $gradesMap[$sid] ?? [
                        'score_collect'      => 0,
                        'score_final'        => 0,
                        'total_score'        => 0,
                        'attendance_percent' => 100.0,
                        'grade'              => 'ร',
                        'original_grade'     => null,
                        'is_remedial'        => 0,
                    ];
                    $currentGrade = (string)($gRow['grade'] ?? '');
                ?>
                    <tr class="hover:bg-blue-50/30 transition-colors" data-student-id="<?= $sid ?>" data-row-idx="<?= $rowIdx ?>">
                        <td class="py-2 px-2.5 border-r border-slate-200 bg-white sticky left-0 z-10 text-center font-black text-slate-700">
                            <?= (int)$std['student_number'] ?>
                        </td>
                        <td class="py-2 px-3 border-r border-slate-200 font-mono font-bold text-slate-500">
                            <?= htmlspecialchars($std['student_id'], ENT_QUOTES, 'UTF-8') ?>
                        </td>
                        <td class="py-2 px-3 border-r border-slate-200 font-bold text-slate-800 truncate max-w-[220px]">
                            <?= htmlspecialchars($std['name'], ENT_QUOTES, 'UTF-8') ?>
                        </td>

                        <?php foreach ($orderedItems as $colIdx => $item):
                            $itemId   = (int)$item['id'];
                            $maxScore = (float)$item['max_score'];
                            $val      = $scoresGrid[$sid][$itemId] ?? null;
                            $valStr   = ($val === null) ? '' : (string)(float)$val;
                        ?>
                            <td class="p-0 border-r border-slate-200 relative">
                                <input type="text"
                                       inputmode="decimal"
                                       <?= !$canEdit ? 'readonly' : '' ?>
                                       data-row="<?= $rowIdx ?>"
                                       data-col="<?= $colIdx ?>"
                                       data-student-id="<?= $sid ?>"
                                       data-item-id="<?= $itemId ?>"
                                       data-max-score="<?= $maxScore ?>"
                                       data-phase="<?= htmlspecialchars($item['phase'], ENT_QUOTES, 'UTF-8') ?>"
                                       data-item-name="<?= htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8') ?>"
                                       value="<?= htmlspecialchars($valStr, ENT_QUOTES, 'UTF-8') ?>"
                                       placeholder="-"
                                       class="pp5-score-cell w-full h-9 px-2 text-center font-bold text-slate-800 bg-transparent focus:bg-indigo-50/80 focus:ring-2 focus:ring-inset focus:ring-indigo-600 outline-none transition-all <?= $val === null ? 'bg-amber-50/30' : '' ?>">
                            </td>
                        <?php endforeach; ?>

                        <!-- คอลัมน์สรุปผลรายบุคคล (อัปเดตสดเมื่อกรอกคะแนน) -->
                        <td class="py-2 px-2.5 border-r border-slate-200 text-center font-black text-indigo-700 bg-indigo-50/25" id="cell_collect_<?= $sid ?>">
                            <?= (float)$gRow['score_collect'] ?>
                        </td>
                        <td class="py-2 px-2.5 border-r border-slate-200 text-center font-black text-rose-700 bg-rose-50/25" id="cell_final_<?= $sid ?>">
                            <?= (float)$gRow['score_final'] ?>
                        </td>
                        <td class="py-2 px-2.5 border-r border-slate-200 text-center font-black text-slate-900 bg-slate-50 text-sm" id="cell_total_<?= $sid ?>">
                            <?= (int)$gRow['total_score'] ?>
                        </td>
                        <td class="py-2 px-2.5 border-r border-slate-200 text-center font-bold <?= ((float)$gRow['attendance_percent'] < 80.0) ? 'text-rose-600 bg-rose-50/50' : 'text-emerald-700' ?>" id="cell_att_<?= $sid ?>">
                            <?= number_format((float)$gRow['attendance_percent'], 1) ?>%
                        </td>
                        <td class="py-2 px-3 border-r border-slate-200 text-center" id="cell_grade_wrap_<?= $sid ?>">
                            <span id="cell_grade_<?= $sid ?>" class="inline-block px-2.5 py-0.5 rounded-full border text-xs font-black <?= $gradeBadgeClass($currentGrade) ?>">
                                <?= htmlspecialchars($currentGrade, ENT_QUOTES, 'UTF-8') ?>
                            </span>
                        </td>
                        <td class="py-2 px-2.5 text-center text-[11px] font-semibold text-slate-500" id="cell_remark_<?= $sid ?>">
                            <?php if (!empty($gRow['is_remedial']) && !empty($gRow['original_grade'])): ?>
                                <span class="text-amber-700 font-bold">แก้ <?= htmlspecialchars($gRow['original_grade'], ENT_QUOTES, 'UTF-8') ?></span>
                            <?php elseif ($currentGrade === 'มส'): ?>
                                <span class="text-purple-700 font-bold">เวลาเรียน &lt;80%</span>
                            <?php elseif ($currentGrade === 'ร'): ?>
                                <span class="text-amber-600 font-bold">คะแนนไม่ครบ</span>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<script>
const PP5_COURSE_ID = <?= (int)$courseId ?>;
const PP5_CAN_EDIT  = <?= $canEdit ? 'true' : 'false' ?>;
const PP5_TOTAL_ROWS = <?= count($students) ?>;
const PP5_TOTAL_COLS = <?= count($orderedItems) ?>;

function getGradeBadgeClasses(grade) {
    if (['4', '3.5', 'ผ'].includes(grade)) return 'bg-emerald-100 text-emerald-800 border-emerald-300';
    if (['3', '2.5'].includes(grade)) return 'bg-blue-100 text-blue-800 border-blue-300';
    if (['2', '1.5', '1'].includes(grade)) return 'bg-indigo-100 text-indigo-800 border-indigo-300';
    if (['0', 'มผ'].includes(grade)) return 'bg-rose-100 text-rose-800 border-rose-300';
    if (grade === 'ร') return 'bg-amber-100 text-amber-800 border-amber-300';
    if (grade === 'มส') return 'bg-purple-100 text-purple-800 border-purple-300';
    return 'bg-slate-100 text-slate-600 border-slate-200';
}

function setAutosaveIndicator(state, text) {
    const el = document.getElementById('autosaveStatus');
    if (!el) return;
    if (state === 'saving') {
        el.className = 'px-3 py-1.5 rounded-xl bg-amber-50 text-amber-700 border border-amber-200 text-xs font-bold flex items-center gap-1.5';
        el.innerHTML = '<i class="bi bi-arrow-repeat animate-spin"></i><span>กำลังบันทึกอัตโนมัติ...</span>';
    } else if (state === 'saved') {
        el.className = 'px-3 py-1.5 rounded-xl bg-emerald-50 text-emerald-700 border border-emerald-200 text-xs font-bold flex items-center gap-1.5';
        el.innerHTML = '<i class="bi bi-cloud-check-fill"></i><span>' + (text || 'บันทึกอัตโนมัติแล้ว') + '</span>';
    } else if (state === 'error') {
        el.className = 'px-3 py-1.5 rounded-xl bg-rose-50 text-rose-700 border border-rose-200 text-xs font-bold flex items-center gap-1.5';
        el.innerHTML = '<i class="bi bi-exclamation-triangle-fill"></i><span>' + (text || 'บันทึกไม่สำเร็จ') + '</span>';
    }
}

function getScoreInputCell(row, col) {
    return document.querySelector(`.pp5-score-cell[data-row="${row}"][data-col="${col}"]`);
}

function validateCellValue(inputEl, rawVal) {
    const trimmed = String(rawVal ?? '').trim();
    if (trimmed === '' || trimmed === '-') {
        return { ok: true, value: '' };
    }
    const num = Number(trimmed);
    const maxScore = parseFloat(inputEl.getAttribute('data-max-score') || '100');
    const itemName = inputEl.getAttribute('data-item-name') || 'รายการคะแนน';

    if (Number.isNaN(num)) {
        return { ok: false, error: `ช่อง "${itemName}" ต้องกรอกเป็นตัวเลขเท่านั้น` };
    }
    if (num < 0) {
        return { ok: false, error: `ช่อง "${itemName}" ห้ามกรอกคะแนนติดลบ` };
    }
    if (num > maxScore) {
        return { ok: false, error: `ห้ามกรอกคะแนนเกินคะแนนเต็ม! ช่อง "${itemName}" มีคะแนนเต็ม ${maxScore} คะแนน (คุณกรอก ${num})` };
    }
    return { ok: true, value: Math.round(num * 100) / 100 };
}

async function sendScoreBatch(entries) {
    if (!PP5_CAN_EDIT || !entries || entries.length === 0) return;
    setAutosaveIndicator('saving');

    try {
        const res = await fetch('/pp5/api_course.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'save_scores',
                course_id: PP5_COURSE_ID,
                entries: entries
            })
        });
        const data = await res.json();
        if (!res.ok || data.status !== 'success') {
            setAutosaveIndicator('error', data.message || 'ข้อผิดพลาด');
            Swal.fire({
                icon: 'error',
                title: 'ไม่สามารถบันทึกคะแนนได้',
                text: data.message || 'กรุณาตรวจสอบข้อมูลอีกครั้ง',
                confirmButtonColor: '#2563eb'
            });
            return;
        }

        // อัปเดตผลรวมและเกรดรายบุคคลบนตารางทันที
        if (data.grades) {
            Object.keys(data.grades).forEach(sid => {
                const g = data.grades[sid];
                const collectEl = document.getElementById('cell_collect_' + sid);
                const finalEl   = document.getElementById('cell_final_' + sid);
                const totalEl   = document.getElementById('cell_total_' + sid);
                const attEl     = document.getElementById('cell_att_' + sid);
                const gradeEl   = document.getElementById('cell_grade_' + sid);
                const remarkEl  = document.getElementById('cell_remark_' + sid);

                if (collectEl) collectEl.textContent = g.score_collect;
                if (finalEl)   finalEl.textContent   = g.score_final;
                if (totalEl)   totalEl.textContent   = g.total_score;
                if (attEl)     attEl.textContent     = Number(g.attendance_percent).toFixed(1) + '%';
                if (gradeEl) {
                    gradeEl.textContent = g.grade;
                    gradeEl.className = 'inline-block px-2.5 py-0.5 rounded-full border text-xs font-black ' + getGradeBadgeClasses(g.grade);
                }
                if (remarkEl) {
                    if (g.grade === 'มส') {
                        remarkEl.innerHTML = '<span class="text-purple-700 font-bold">เวลาเรียน &lt;80%</span>';
                    } else if (g.grade === 'ร') {
                        remarkEl.innerHTML = '<span class="text-amber-600 font-bold">คะแนนไม่ครบ</span>';
                    } else {
                        remarkEl.textContent = '-';
                    }
                }
            });
        }

        // อัปเดตตัวเลขสถิติการกระจายเกรดด้านบน
        if (data.distribution) {
            Object.keys(data.distribution).forEach(gKey => {
                const cntEl = document.getElementById('dist_count_' + gKey);
                if (cntEl) cntEl.textContent = data.distribution[gKey];
            });
        }

        setAutosaveIndicator('saved', 'บันทึกอัตโนมัติแล้ว (' + (data.saved_at || '') + ')');
    } catch (err) {
        console.error(err);
        setAutosaveIndicator('error', 'การเชื่อมต่อขัดข้อง');
    }
}

async function triggerRecalculateGrades() {
    setAutosaveIndicator('saving');
    try {
        const res = await fetch('/pp5/api_course.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'recalculate_grades',
                course_id: PP5_COURSE_ID
            })
        });
        const data = await res.json();
        if (data.status === 'success') {
            window.location.reload();
        }
    } catch (e) {
        setAutosaveIndicator('error');
    }
}

document.addEventListener('DOMContentLoaded', () => {
    const cells = document.querySelectorAll('.pp5-score-cell');

    cells.forEach(cell => {
        // เก็บค่าก่อนแก้ไขไว้ใช้ย้อนกลับหากกรอกเกินคะแนนเต็ม
        cell.addEventListener('focus', () => {
            cell.setAttribute('data-prev-val', cell.value);
            cell.select();
        });

        // บันทึกอัตโนมัติเมื่อเปลี่ยนค่าในช่อง
        cell.addEventListener('change', () => {
            if (!PP5_CAN_EDIT) return;
            const check = validateCellValue(cell, cell.value);
            if (!check.ok) {
                cell.value = cell.getAttribute('data-prev-val') || '';
                cell.classList.add('bg-rose-100');
                setTimeout(() => cell.classList.remove('bg-rose-100'), 1200);
                Swal.fire({
                    icon: 'warning',
                    title: 'คะแนนเกินเกณฑ์ที่กำหนด',
                    text: check.error,
                    confirmButtonColor: '#2563eb'
                });
                return;
            }

            cell.value = check.value;
            cell.setAttribute('data-prev-val', check.value);
            if (check.value === '') {
                cell.classList.add('bg-amber-50/30');
            } else {
                cell.classList.remove('bg-amber-50/30');
            }

            sendScoreBatch([{
                student_id: parseInt(cell.getAttribute('data-student-id'), 10),
                score_item_id: parseInt(cell.getAttribute('data-item-id'), 10),
                score: check.value
            }]);
        });

        // ปุ่มลูกศรและ Enter เลื่อนช่องแบบ Excel
        cell.addEventListener('keydown', (e) => {
            const row = parseInt(cell.getAttribute('data-row'), 10);
            const col = parseInt(cell.getAttribute('data-col'), 10);

            if (e.key === 'Enter' || e.key === 'ArrowDown') {
                e.preventDefault();
                const next = getScoreInputCell(row + 1, col);
                if (next) next.focus();
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                const prev = getScoreInputCell(row - 1, col);
                if (prev) prev.focus();
            } else if (e.key === 'ArrowRight' && (cell.selectionEnd === cell.value.length)) {
                e.preventDefault();
                const right = getScoreInputCell(row, col + 1);
                if (right) right.focus();
            } else if (e.key === 'ArrowLeft' && (cell.selectionStart === 0)) {
                e.preventDefault();
                const left = getScoreInputCell(row, col - 1);
                if (left) left.focus();
            }
        });

        // รองรับการคัดลอกจาก Excel แล้ววาง (Paste) ลงตารางหลายแถว/หลายคอลัมน์พร้อมกัน
        cell.addEventListener('paste', (e) => {
            if (!PP5_CAN_EDIT) return;
            const clipboardData = e.clipboardData || window.clipboardData;
            if (!clipboardData) return;
            const pastedText = clipboardData.getData('text');
            if (!pastedText) return;

            // หากเป็นข้อมูลหลายช่อง (มี Tab หรือ Newline) หรือวางลงช่องเดียว
            e.preventDefault();
            const startRow = parseInt(cell.getAttribute('data-row'), 10);
            const startCol = parseInt(cell.getAttribute('data-col'), 10);

            const rows = pastedText.replace(/\r\n/g, '\n').replace(/\r/g, '\n').split('\n');
            // ตัดบรรทัดว่างท้ายสุดจากการ Copy ใน Excel
            if (rows.length > 1 && rows[rows.length - 1] === '') {
                rows.pop();
            }

            const batchEntries = [];
            const errors = [];

            rows.forEach((rowStr, rOffset) => {
                const cols = rowStr.split('\t');
                cols.forEach((cellVal, cOffset) => {
                    const targetCell = getScoreInputCell(startRow + rOffset, startCol + cOffset);
                    if (!targetCell) return;

                    const check = validateCellValue(targetCell, cellVal);
                    if (!check.ok) {
                        errors.push(`แถวที่ ${startRow + rOffset + 1}: ${check.error}`);
                        return;
                    }

                    targetCell.value = check.value;
                    targetCell.setAttribute('data-prev-val', check.value);
                    if (check.value === '') {
                        targetCell.classList.add('bg-amber-50/30');
                    } else {
                        targetCell.classList.remove('bg-amber-50/30');
                    }

                    batchEntries.push({
                        student_id: parseInt(targetCell.getAttribute('data-student-id'), 10),
                        score_item_id: parseInt(targetCell.getAttribute('data-item-id'), 10),
                        score: check.value
                    });
                });
            });

            if (errors.length > 0) {
                Swal.fire({
                    icon: 'warning',
                    title: 'พบข้อมูลบางช่องเกินคะแนนเต็ม',
                    html: '<div class="text-left text-xs space-y-1 max-h-48 overflow-y-auto">' + errors.map(err => '• ' + err).join('<br>') + '</div>',
                    confirmButtonColor: '#2563eb'
                });
            }

            if (batchEntries.length > 0) {
                sendScoreBatch(batchEntries);
            }
        });
    });
});
</script>

<?php require_once __DIR__ . '/../components/layout_end.php'; ?>
