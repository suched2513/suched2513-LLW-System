<?php
/**
 * pp5/course_assessments.php — บันทึกการประเมินคุณลักษณะอันพึงประสงค์ (8 ข้อ),
 * การอ่าน คิดวิเคราะห์ และเขียน (5 ข้อ) และสมรรถนะสำคัญของผู้เรียน (5 ด้าน)
 * พร้อมปุ่ม "กรอกทั้งคอลัมน์" / "กรอกทั้งห้อง" และตารางแบบ Excel (Auto-save)
 */

require_once __DIR__ . '/_bootstrap.php';

use PP5\App\Policies\Pp5Policy;
use PP5\App\Services\AssessmentService;
use PP5\App\Services\CourseService;

pp5_require_login();

try {

$policy            = new Pp5Policy($pdo);
$courseService     = new CourseService($pdo);
$assessmentService = new AssessmentService($pdo);
$termCtx           = pp5_get_term_context($pdo);

$courseId = (int)($_GET['course_id'] ?? 0);
$course   = $courseService->getCourseById($courseId);

if (!$course || !$policy->canViewCourse($course)) {
    header('Location: /pp5/index.php');
    exit();
}

$canEdit    = $policy->canEditCourse($course);
$students   = $courseService->getCourseStudents($course);
$studentIds = array_map(fn($s) => (int)$s['id'], $students);

$activeType = (string)($_GET['type'] ?? 'characteristic');
if (!in_array($activeType, ['characteristic', 'rtw', 'competency'], true)) {
    $activeType = 'characteristic';
}

$typeTabs = [
    'characteristic' => [
        'title'    => 'คุณลักษณะอันพึงประสงค์ (8 ข้อ)',
        'subtitle' => 'รักชาติ ศาสน์ กษัตริย์ • ซื่อสัตย์ • มีวินัย • ใฝ่เรียนรู้ • พอเพียง • มุ่งมั่น • รักความเป็นไทย • จิตสาธารณะ',
        'icon'     => 'bi-heart-pulse-fill',
        'color'    => 'indigo',
    ],
    'rtw' => [
        'title'    => 'การอ่าน คิดวิเคราะห์ และเขียน (5 ข้อ)',
        'subtitle' => 'อ่านหาสารสนเทศ • จับประเด็นสำคัญ • วิเคราะห์/สังเคราะห์ • ประเมินคุณค่า • เขียนสื่อสารสร้างสรรค์',
        'icon'     => 'bi-book-half',
        'color'    => 'cyan',
    ],
    'competency' => [
        'title'    => 'สมรรถนะสำคัญของผู้เรียน (5 ด้าน)',
        'subtitle' => 'ความสามารถในการสื่อสาร • การคิด • การแก้ปัญหา • การใช้ทักษะชีวิต • การใช้เทคโนโลยี',
        'icon'     => 'bi-stars',
        'color'    => 'emerald',
    ],
];

// ดึงข้อมูลของทั้ง 3 ด้านเพื่อแสดงตัวเลขความครบถ้วนบนแท็บ
$allAssessments = $assessmentService->getAllCourseAssessments($courseId, $studentIds);
$currentData    = $allAssessments[$activeType];
$itemsMeta      = $assessmentService->getItemsMeta($activeType);
$grid           = $currentData['grid'];
$summaries      = $currentData['summaries'];
$dist           = $currentData['dist'];
$settings       = $currentData['settings'];

$levelBadgeClass = function (?int $lv): string {
    return match ($lv) {
        3       => 'bg-emerald-100 text-emerald-800 border-emerald-300',
        2       => 'bg-blue-100 text-blue-800 border-blue-300',
        1       => 'bg-amber-100 text-amber-800 border-amber-300',
        0       => 'bg-rose-100 text-rose-800 border-rose-300',
        default => 'bg-slate-100 text-slate-500 border-slate-200',
    };
};

$pageTitle    = 'ประเมินคุณลักษณะ/อ่านคิดเขียน/สมรรถนะ — ' . $course['subject_code'] . ' (' . $course['classroom_name'] . ')';
$pageSubtitle = 'ประเมินระดับคุณภาพ 0–3 (3 = ดีเยี่ยม, 2 = ดี, 1 = ผ่าน, 0 = ไม่ผ่าน) พร้อมปุ่มกรอกทั้งคอลัมน์และสรุปผลอัตโนมัติ';
$activeSystem = 'pp5';

require_once __DIR__ . '/../components/layout_start.php';
echo pp5_render_top_bar($pdo, $termCtx, $policy, 'dashboard');
echo pp5_render_course_nav($course, $policy, 'assessments');
?>

<!-- แท็บสลับ 3 ด้านการประเมิน -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
    <?php foreach ($typeTabs as $tKey => $tMeta):
        $tDist = $allAssessments[$tKey]['dist'];
        $tIncomplete = (int)($tDist['incomplete'] ?? 0);
        $tCompleted  = count($students) - $tIncomplete;
        $isAct       = ($activeType === $tKey);
    ?>
        <a href="/pp5/course_assessments.php?course_id=<?= $courseId ?>&type=<?= $tKey ?>"
           class="rounded-2xl p-4 border transition-all flex items-start justify-between gap-3 <?= $isAct ? 'bg-indigo-600 text-white border-indigo-600 shadow-xl shadow-indigo-200/60' : 'bg-white text-slate-800 border-slate-200 hover:border-indigo-300 shadow-md shadow-slate-100' ?>">
            <div class="flex items-start gap-3">
                <div class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0 <?= $isAct ? 'bg-white/20 text-white' : 'bg-indigo-50 text-indigo-600' ?>">
                    <i class="bi <?= $tMeta['icon'] ?> text-lg"></i>
                </div>
                <div>
                    <div class="text-sm font-black"><?= htmlspecialchars($tMeta['title'], ENT_QUOTES, 'UTF-8') ?></div>
                    <div class="text-[11px] mt-0.5 <?= $isAct ? 'text-indigo-100' : 'text-slate-500' ?>">
                        ประเมินครบแล้ว <?= $tCompleted ?> / <?= count($students) ?> คน
                    </div>
                </div>
            </div>
            <span class="px-2.5 py-1 rounded-full text-[11px] font-black shrink-0 <?= $tIncomplete === 0 && count($students) > 0 ? ($isAct ? 'bg-emerald-400 text-emerald-950' : 'bg-emerald-100 text-emerald-800') : ($isAct ? 'bg-white/20 text-white' : 'bg-amber-100 text-amber-800') ?>">
                <?= $tIncomplete === 0 && count($students) > 0 ? 'ครบแล้ว ✓' : ('ค้าง ' . $tIncomplete) ?>
            </span>
        </a>
    <?php endforeach; ?>
</div>

<!-- แถบสรุปสถิติผลการประเมิน + ปุ่มลัดกรอกทั้งห้อง -->
<div class="bg-white rounded-2xl shadow-xl shadow-blue-100/50 p-5 border border-slate-100 mb-5">
    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4 pb-4 border-b border-slate-100">
        <div>
            <h3 class="text-base font-black text-slate-800 flex items-center gap-2">
                <i class="bi <?= $typeTabs[$activeType]['icon'] ?> text-indigo-600"></i>
                <?= htmlspecialchars($typeTabs[$activeType]['title'], ENT_QUOTES, 'UTF-8') ?>
            </h3>
            <p class="text-xs text-slate-500 mt-0.5">
                เกณฑ์การสรุปผลรายคน: <strong class="text-indigo-600"><?= $settings['method'] === 'mean' ? 'ค่าเฉลี่ยปัดเศษ (Mean)' : 'ฐานนิยม (Mode — ถ้าน้ำหนักเท่ากันเลือกระดับที่สูงกว่า)' ?></strong>
                <?= $settings['zero_item_forces_fail'] ? ' • หากมีข้อใดได้ 0 สรุปรวมเป็น 0 (ไม่ผ่าน) ทันที' : '' ?>
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <div id="autosaveStatus" class="px-3 py-1.5 rounded-xl bg-emerald-50 text-emerald-700 border border-emerald-200 text-xs font-bold flex items-center gap-1.5">
                <i class="bi bi-cloud-check-fill"></i>
                <span>พร้อมบันทึกอัตโนมัติ</span>
            </div>

            <?php if ($canEdit): ?>
                <div class="flex items-center gap-1.5 bg-slate-100 p-1 rounded-xl border border-slate-200">
                    <span class="text-[11px] font-black text-slate-600 px-2"><i class="bi bi-lightning-charge-fill text-amber-500"></i> กรอกทั้งตาราง:</span>
                    <button type="button" onclick="fillEntireAssessmentTable(3)" class="px-2.5 py-1 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold transition-all">
                        3 (ดีเยี่ยม) ทั้งหมด
                    </button>
                    <button type="button" onclick="fillEntireAssessmentTable(2)" class="px-2.5 py-1 rounded-lg bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold transition-all">
                        2 (ดี) ทั้งหมด
                    </button>
                    <button type="button" onclick="fillEntireAssessmentTable(1)" class="px-2.5 py-1 rounded-lg bg-amber-500 hover:bg-amber-600 text-white text-xs font-bold transition-all">
                        1 (ผ่าน) ทั้งหมด
                    </button>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- สถิติจำนวนคนในแต่ละระดับคุณภาพ -->
    <div class="grid grid-cols-2 sm:grid-cols-5 gap-3 pt-4">
        <div class="rounded-xl border border-emerald-200 bg-emerald-50/70 px-3.5 py-2.5 flex items-center justify-between">
            <div>
                <div class="text-[10px] font-black text-emerald-700 uppercase">ระดับ 3 (ดีเยี่ยม)</div>
                <div class="text-xs text-emerald-600 font-semibold">ผ่านเกณฑ์ระดับดีเยี่ยม</div>
            </div>
            <span class="text-2xl font-black text-emerald-800" id="dist_lv_3"><?= (int)($dist[3] ?? 0) ?></span>
        </div>
        <div class="rounded-xl border border-blue-200 bg-blue-50/70 px-3.5 py-2.5 flex items-center justify-between">
            <div>
                <div class="text-[10px] font-black text-blue-700 uppercase">ระดับ 2 (ดี)</div>
                <div class="text-xs text-blue-600 font-semibold">ผ่านเกณฑ์ระดับดี</div>
            </div>
            <span class="text-2xl font-black text-blue-800" id="dist_lv_2"><?= (int)($dist[2] ?? 0) ?></span>
        </div>
        <div class="rounded-xl border border-amber-200 bg-amber-50/70 px-3.5 py-2.5 flex items-center justify-between">
            <div>
                <div class="text-[10px] font-black text-amber-700 uppercase">ระดับ 1 (ผ่าน)</div>
                <div class="text-xs text-amber-600 font-semibold">ผ่านเกณฑ์ขั้นต่ำ</div>
            </div>
            <span class="text-2xl font-black text-amber-800" id="dist_lv_1"><?= (int)($dist[1] ?? 0) ?></span>
        </div>
        <div class="rounded-xl border border-rose-200 bg-rose-50/70 px-3.5 py-2.5 flex items-center justify-between">
            <div>
                <div class="text-[10px] font-black text-rose-700 uppercase">ระดับ 0 (ไม่ผ่าน)</div>
                <div class="text-xs text-rose-600 font-semibold">ต้องปรับปรุง/ซ่อมเสริม</div>
            </div>
            <span class="text-2xl font-black text-rose-800" id="dist_lv_0"><?= (int)($dist[0] ?? 0) ?></span>
        </div>
        <div class="rounded-xl border border-slate-200 bg-slate-50 px-3.5 py-2.5 flex items-center justify-between">
            <div>
                <div class="text-[10px] font-black text-slate-500 uppercase">ยังประเมินไม่ครบ</div>
                <div class="text-xs text-slate-400 font-semibold">รอการกรอกข้อมูล</div>
            </div>
            <span class="text-2xl font-black text-slate-700" id="dist_lv_incomplete"><?= (int)($dist['incomplete'] ?? 0) ?></span>
        </div>
    </div>
</div>

<!-- ตารางกรอกผลประเมินแบบ Excel + ปุ่มกรอกทั้งคอลัมน์ -->
<div class="bg-slate-800 text-white rounded-t-2xl px-4 py-2.5 flex flex-wrap items-center justify-between gap-2 text-xs">
    <div class="flex flex-wrap items-center gap-x-4 gap-y-1">
        <span class="font-bold text-indigo-300"><i class="bi bi-magic"></i> ปุ่มกรอกทั้งคอลัมน์:</span>
        <span>คลิกปุ่ม <kbd class="px-1.5 py-0.5 rounded bg-emerald-600 text-white font-bold">3</kbd> <kbd class="px-1.5 py-0.5 rounded bg-blue-600 text-white font-bold">2</kbd> <kbd class="px-1.5 py-0.5 rounded bg-amber-500 text-white font-bold">1</kbd> บนหัวคอลัมน์เพื่อกรอกทั้งคอลัมน์ในคลิกเดียว แล้วแก้เฉพาะรายคน</span>
    </div>
    <span class="text-[11px] text-slate-300">รองรับการวางข้อมูลจาก Excel (<kbd class="px-1.5 py-0.5 rounded bg-slate-700 font-mono">Ctrl+V</kbd>) และเลื่อนด้วยปุ่มลูกศร</span>
</div>

<div class="bg-white rounded-b-2xl shadow-xl shadow-blue-100/50 border border-t-0 border-slate-200 overflow-x-auto">
    <table class="w-full text-left border-collapse select-none">
        <thead>
            <tr class="bg-slate-50 border-b-2 border-slate-300 text-xs font-bold text-slate-700 text-center">
                <th class="py-3 px-2.5 border-r border-slate-300 bg-slate-100 sticky left-0 z-20 w-12">เลขที่</th>
                <th class="py-3 px-3 border-r border-slate-300 bg-slate-100 w-24">รหัส</th>
                <th class="py-3 px-3 border-r border-slate-300 bg-slate-100 min-w-[190px] text-left">ชื่อ - นามสกุล</th>

                <?php
                $colIdx = 0;
                foreach ($itemsMeta as $itemNo => $meta):
                ?>
                    <th class="py-2.5 px-2 border-r border-slate-200 min-w-[118px] align-top bg-indigo-50/40">
                        <div class="text-[11px] font-black text-slate-800 leading-tight" title="<?= htmlspecialchars($meta['full'], ENT_QUOTES, 'UTF-8') ?>">
                            <?= htmlspecialchars($meta['short'], ENT_QUOTES, 'UTF-8') ?>
                        </div>
                        <?php if ($canEdit): ?>
                            <div class="mt-2 flex items-center justify-center gap-1" title="คลิกเพื่อกรอกระดับคะแนนนี้ทั้งคอลัมน์">
                                <button type="button" onclick="fillAssessmentColumn(<?= $colIdx ?>, <?= (int)$itemNo ?>, 3)"
                                        class="px-1.5 py-0.5 rounded bg-emerald-600 hover:bg-emerald-700 text-white text-[10px] font-black transition-all">3</button>
                                <button type="button" onclick="fillAssessmentColumn(<?= $colIdx ?>, <?= (int)$itemNo ?>, 2)"
                                        class="px-1.5 py-0.5 rounded bg-blue-600 hover:bg-blue-700 text-white text-[10px] font-black transition-all">2</button>
                                <button type="button" onclick="fillAssessmentColumn(<?= $colIdx ?>, <?= (int)$itemNo ?>, 1)"
                                        class="px-1.5 py-0.5 rounded bg-amber-500 hover:bg-amber-600 text-white text-[10px] font-black transition-all">1</button>
                                <button type="button" onclick="fillAssessmentColumn(<?= $colIdx ?>, <?= (int)$itemNo ?>, 0)"
                                        class="px-1.5 py-0.5 rounded bg-rose-500 hover:bg-rose-600 text-white text-[10px] font-black transition-all">0</button>
                            </div>
                        <?php endif; ?>
                    </th>
                <?php
                    $colIdx++;
                endforeach;
                ?>

                <th class="py-3 px-3 border-r border-slate-200 bg-slate-800 text-white font-black w-24">
                    สรุประดับ<br><span class="text-[10px] font-normal text-slate-300">(0–3)</span>
                </th>
                <th class="py-3 px-3 bg-slate-800 text-white font-black w-28">
                    ผลการประเมิน
                </th>
            </tr>
        </thead>

        <tbody class="divide-y divide-slate-200 text-xs">
            <?php foreach ($students as $rowIdx => $std):
                $sid   = (int)$std['id'];
                $sInfo = $summaries[$sid] ?? ['level' => null, 'text' => '-', 'is_complete' => false];
                $lv    = $sInfo['level'];
            ?>
                <tr class="hover:bg-indigo-50/25 transition-colors" data-student-id="<?= $sid ?>">
                    <td class="py-2 px-2.5 border-r border-slate-200 bg-white sticky left-0 z-10 text-center font-black text-slate-700">
                        <?= (int)$std['student_number'] ?>
                    </td>
                    <td class="py-2 px-3 border-r border-slate-200 font-mono font-bold text-slate-500">
                        <?= htmlspecialchars($std['student_id'], ENT_QUOTES, 'UTF-8') ?>
                    </td>
                    <td class="py-2 px-3 border-r border-slate-200 font-bold text-slate-800 truncate max-w-[220px]">
                        <?= htmlspecialchars($std['name'], ENT_QUOTES, 'UTF-8') ?>
                    </td>

                    <?php
                    $cIdx = 0;
                    foreach ($itemsMeta as $itemNo => $meta):
                        $val    = $grid[$sid][$itemNo] ?? null;
                        $valStr = ($val === null) ? '' : (string)(int)$val;
                    ?>
                        <td class="p-0 border-r border-slate-200">
                            <input type="text"
                                   inputmode="numeric"
                                   maxlength="1"
                                   <?= !$canEdit ? 'readonly' : '' ?>
                                   data-row="<?= $rowIdx ?>"
                                   data-col="<?= $cIdx ?>"
                                   data-student-id="<?= $sid ?>"
                                   data-item-no="<?= (int)$itemNo ?>"
                                   data-item-name="<?= htmlspecialchars($meta['short'], ENT_QUOTES, 'UTF-8') ?>"
                                   value="<?= htmlspecialchars($valStr, ENT_QUOTES, 'UTF-8') ?>"
                                   placeholder="-"
                                   class="pp5-assess-cell w-full h-9 px-2 text-center font-black text-slate-800 bg-transparent focus:bg-indigo-50 focus:ring-2 focus:ring-inset focus:ring-indigo-600 outline-none transition-all <?= $val === null ? 'bg-amber-50/30' : '' ?>">
                        </td>
                    <?php
                        $cIdx++;
                    endforeach;
                    ?>

                    <td class="py-2 px-3 border-r border-slate-200 text-center font-black text-sm text-slate-900 bg-slate-50" id="sum_level_<?= $sid ?>">
                        <?= $lv === null ? '-' : (int)$lv ?>
                    </td>
                    <td class="py-2 px-3 text-center bg-slate-50">
                        <span id="sum_badge_<?= $sid ?>" class="inline-block px-2.5 py-0.5 rounded-full border text-xs font-black <?= $levelBadgeClass($lv) ?>">
                            <?= htmlspecialchars($sInfo['text'], ENT_QUOTES, 'UTF-8') ?>
                        </span>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<script>
const PP5_COURSE_ID  = <?= (int)$courseId ?>;
const PP5_ASSESS_TYPE = <?= json_encode($activeType) ?>;
const PP5_CAN_EDIT   = <?= $canEdit ? 'true' : 'false' ?>;

function getLevelBadgeClasses(lv) {
    if (lv === 3) return 'bg-emerald-100 text-emerald-800 border-emerald-300';
    if (lv === 2) return 'bg-blue-100 text-blue-800 border-blue-300';
    if (lv === 1) return 'bg-amber-100 text-amber-800 border-amber-300';
    if (lv === 0) return 'bg-rose-100 text-rose-800 border-rose-300';
    return 'bg-slate-100 text-slate-500 border-slate-200';
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

function getAssessCell(row, col) {
    return document.querySelector(`.pp5-assess-cell[data-row="${row}"][data-col="${col}"]`);
}

function validateAssessLevel(rawVal) {
    const trimmed = String(rawVal ?? '').trim();
    if (trimmed === '' || trimmed === '-') {
        return { ok: true, value: '' };
    }
    if (!['0', '1', '2', '3'].includes(trimmed)) {
        return { ok: false, error: `ระดับผลการประเมินต้องเป็นตัวเลข 0, 1, 2 หรือ 3 เท่านั้น (คุณกรอก "${trimmed}")` };
    }
    return { ok: true, value: parseInt(trimmed, 10) };
}

async function sendAssessmentBatch(entries) {
    if (!PP5_CAN_EDIT || !entries || entries.length === 0) return;
    setAutosaveIndicator('saving');

    try {
        const res = await fetch('/pp5/api_course.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'save_assessments',
                course_id: PP5_COURSE_ID,
                assessment_type: PP5_ASSESS_TYPE,
                entries: entries
            })
        });
        const data = await res.json();
        if (!res.ok || data.status !== 'success') {
            setAutosaveIndicator('error', data.message || 'ข้อผิดพลาด');
            Swal.fire({
                icon: 'error',
                title: 'ไม่สามารถบันทึกผลการประเมินได้',
                text: data.message || 'กรุณาตรวจสอบข้อมูลอีกครั้ง',
                confirmButtonColor: '#2563eb'
            });
            return;
        }

        if (data.summaries) {
            Object.keys(data.summaries).forEach(sid => {
                const s = data.summaries[sid];
                const lvEl = document.getElementById('sum_level_' + sid);
                const bdEl = document.getElementById('sum_badge_' + sid);
                if (lvEl) lvEl.textContent = (s.level === null || s.level === undefined) ? '-' : s.level;
                if (bdEl) {
                    bdEl.textContent = s.text || '-';
                    bdEl.className = 'inline-block px-2.5 py-0.5 rounded-full border text-xs font-black ' + getLevelBadgeClasses(s.level);
                }
            });
        }

        if (data.dist) {
            [0, 1, 2, 3, 'incomplete'].forEach(k => {
                const el = document.getElementById('dist_lv_' + k);
                if (el && data.dist[k] !== undefined) {
                    el.textContent = data.dist[k];
                }
            });
        }

        setAutosaveIndicator('saved', 'บันทึกอัตโนมัติแล้ว (' + (data.saved_at || '') + ')');
    } catch (err) {
        console.error(err);
        setAutosaveIndicator('error', 'การเชื่อมต่อขัดข้อง');
    }
}

// ปุ่มกรอกทั้งคอลัมน์
function fillAssessmentColumn(colIdx, itemNo, levelVal) {
    if (!PP5_CAN_EDIT) return;
    const colCells = document.querySelectorAll(`.pp5-assess-cell[data-col="${colIdx}"]`);
    const entries = [];
    colCells.forEach(cell => {
        cell.value = String(levelVal);
        cell.setAttribute('data-prev-val', String(levelVal));
        cell.classList.remove('bg-amber-50/30');
        entries.push({
            student_id: parseInt(cell.getAttribute('data-student-id'), 10),
            item_no: itemNo,
            level: levelVal
        });
    });
    sendAssessmentBatch(entries);
}

// ปุ่มกรอกทั้งตาราง
function fillEntireAssessmentTable(levelVal) {
    if (!PP5_CAN_EDIT) return;
    const allCells = document.querySelectorAll('.pp5-assess-cell');
    const entries = [];
    allCells.forEach(cell => {
        cell.value = String(levelVal);
        cell.setAttribute('data-prev-val', String(levelVal));
        cell.classList.remove('bg-amber-50/30');
        entries.push({
            student_id: parseInt(cell.getAttribute('data-student-id'), 10),
            item_no: parseInt(cell.getAttribute('data-item-no'), 10),
            level: levelVal
        });
    });
    sendAssessmentBatch(entries);
}

document.addEventListener('DOMContentLoaded', () => {
    const cells = document.querySelectorAll('.pp5-assess-cell');

    cells.forEach(cell => {
        cell.addEventListener('focus', () => {
            cell.setAttribute('data-prev-val', cell.value);
            cell.select();
        });

        cell.addEventListener('change', () => {
            if (!PP5_CAN_EDIT) return;
            const check = validateAssessLevel(cell.value);
            if (!check.ok) {
                cell.value = cell.getAttribute('data-prev-val') || '';
                Swal.fire({
                    icon: 'warning',
                    title: 'ระดับคะแนนไม่ถูกต้อง',
                    text: check.error,
                    confirmButtonColor: '#2563eb'
                });
                return;
            }

            cell.value = check.value === '' ? '' : String(check.value);
            cell.setAttribute('data-prev-val', cell.value);
            if (cell.value === '') {
                cell.classList.add('bg-amber-50/30');
            } else {
                cell.classList.remove('bg-amber-50/30');
            }

            sendAssessmentBatch([{
                student_id: parseInt(cell.getAttribute('data-student-id'), 10),
                item_no: parseInt(cell.getAttribute('data-item-no'), 10),
                level: check.value
            }]);
        });

        cell.addEventListener('keydown', (e) => {
            const row = parseInt(cell.getAttribute('data-row'), 10);
            const col = parseInt(cell.getAttribute('data-col'), 10);

            if (e.key === 'Enter' || e.key === 'ArrowDown') {
                e.preventDefault();
                const next = getAssessCell(row + 1, col);
                if (next) next.focus();
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                const prev = getAssessCell(row - 1, col);
                if (prev) prev.focus();
            } else if (e.key === 'ArrowRight') {
                e.preventDefault();
                const right = getAssessCell(row, col + 1);
                if (right) right.focus();
            } else if (e.key === 'ArrowLeft') {
                e.preventDefault();
                const left = getAssessCell(row, col - 1);
                if (left) left.focus();
            }
        });

        cell.addEventListener('paste', (e) => {
            if (!PP5_CAN_EDIT) return;
            const clipboardData = e.clipboardData || window.clipboardData;
            if (!clipboardData) return;
            const pastedText = clipboardData.getData('text');
            if (!pastedText) return;

            e.preventDefault();
            const startRow = parseInt(cell.getAttribute('data-row'), 10);
            const startCol = parseInt(cell.getAttribute('data-col'), 10);

            const rows = pastedText.replace(/\r\n/g, '\n').replace(/\r/g, '\n').split('\n');
            if (rows.length > 1 && rows[rows.length - 1] === '') {
                rows.pop();
            }

            const batchEntries = [];
            const errors = [];

            rows.forEach((rowStr, rOffset) => {
                const cols = rowStr.split('\t');
                cols.forEach((cellVal, cOffset) => {
                    const targetCell = getAssessCell(startRow + rOffset, startCol + cOffset);
                    if (!targetCell) return;

                    const check = validateAssessLevel(cellVal);
                    if (!check.ok) {
                        errors.push(`แถวที่ ${startRow + rOffset + 1}: ${check.error}`);
                        return;
                    }

                    targetCell.value = check.value === '' ? '' : String(check.value);
                    targetCell.setAttribute('data-prev-val', targetCell.value);
                    if (targetCell.value === '') {
                        targetCell.classList.add('bg-amber-50/30');
                    } else {
                        targetCell.classList.remove('bg-amber-50/30');
                    }

                    batchEntries.push({
                        student_id: parseInt(targetCell.getAttribute('data-student-id'), 10),
                        item_no: parseInt(targetCell.getAttribute('data-item-no'), 10),
                        level: check.value
                    });
                });
            });

            if (errors.length > 0) {
                Swal.fire({
                    icon: 'warning',
                    title: 'พบข้อมูลบางช่องไม่ถูกต้อง',
                    html: '<div class="text-left text-xs space-y-1 max-h-48 overflow-y-auto">' + errors.map(err => '• ' + err).join('<br>') + '</div>',
                    confirmButtonColor: '#2563eb'
                });
            }

            if (batchEntries.length > 0) {
                sendAssessmentBatch(batchEntries);
            }
        });
    });
});
</script>

<?php
require_once __DIR__ . '/../components/layout_end.php';
} catch (\Throwable $_page_err) {
    error_log('[PP5 course_assessments] ' . $_page_err->getMessage() . ' in ' . $_page_err->getFile() . ':' . $_page_err->getLine());
    $pageTitle = 'เกิดข้อผิดพลาดในการโหลดหน้าประเมินคุณลักษณะ';
    $pageSubtitle = htmlspecialchars($_page_err->getMessage(), ENT_QUOTES, 'UTF-8');
    $activeSystem = 'pp5';
    require_once __DIR__ . '/../components/layout_start.php';
    ?>
    <div class="bg-rose-50 border border-rose-200 rounded-2xl p-6 text-rose-800 m-6 shadow-sm">
        <h3 class="text-base font-black flex items-center gap-2 mb-2">
            <i class="bi bi-exclamation-octagon-fill text-rose-600 text-xl"></i> ไม่สามารถโหลดหน้าประเมินคุณลักษณะได้
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

