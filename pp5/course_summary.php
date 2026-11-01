<?php
/**
 * pp5/course_summary.php — สรุปผลการเรียนรายวิชา ตรวจสอบความครบถ้วนของเล่ม ปพ.5 ทั้ง 5 ส่วน และสั่งพิมพ์/ดาวน์โหลด
 */

require_once __DIR__ . '/_bootstrap.php';

use PP5\App\Policies\Pp5Policy;
use PP5\App\Services\MasterDataService;
use PP5\App\Services\Pp5ReportService;

pp5_require_login();

try {

$policy        = new Pp5Policy($pdo);
$masterService = new MasterDataService($pdo);
$reportService = new Pp5ReportService($pdo);
$termCtx       = pp5_get_term_context($pdo);

$courseId = (int)($_GET['course_id'] ?? 0);
$data     = $reportService->compileCourseReportData($courseId);

if (!$data || !$policy->canViewCourse($data['course'])) {
    header('Location: /pp5/index.php');
    exit();
}

$course        = $data['course'];
$students      = $data['students'];
$totalStudents = (int)$data['total_students'];
$gradeCounts   = $data['grade_counts'];
$gradePercents = $data['grade_percents'];
$assessments   = $data['assessments'];
$remedialList  = $data['remedial_list'];
$readiness     = $data['readiness'];
$approvals     = $masterService->getCourseApprovals($courseId);

$checklistItems = [
    [
        'title'  => '1. โครงสร้างสัดส่วนคะแนนและรายการคะแนน (ส่วนที่ 3)',
        'ok'     => $readiness['ratio_ok'],
        'detail' => $readiness['ratio_ok']
            ? 'ตั้งค่ารายการคะแนนครบถ้วนตามสัดส่วน (' . count($data['score_items']) . ' รายการ)'
            : 'ยังไม่มีรายการคะแนน หรือคะแนนเต็มรวมยังไม่ตรงกับสัดส่วน 100 คะแนน',
        'link'   => "/pp5/course_setup.php?course_id={$courseId}",
        'btn'    => 'ตั้งค่าชิ้นงาน',
    ],
    [
        'title'  => '2. บันทึกเวลาเรียนรายคาบ (ส่วนที่ 2)',
        'ok'     => $readiness['attendance_ok'],
        'detail' => $readiness['attendance_ok']
            ? 'บันทึกการเช็กชื่อแล้ว ' . count($data['sessions']) . ' คาบเรียน'
            : 'ยังไม่ได้บันทึกคาบเรียนในรายวิชานี้',
        'link'   => "/pp5/course_attendance.php?course_id={$courseId}",
        'btn'    => 'บันทึกเวลาเรียน',
    ],
    [
        'title'  => '3. การกรอกคะแนนและตัดสินผลการเรียน (ส่วนที่ 3)',
        'ok'     => $readiness['scores_ok'],
        'detail' => $readiness['scores_ok']
            ? 'กรอกคะแนนครบทุกช่องแล้ว'
            : 'มีนักเรียนที่ยังมีช่องคะแนนว่าง (ติด ร) จำนวน ' . (int)$gradeCounts['ร'] . ' คน',
        'link'   => "/pp5/course_scores.php?course_id={$courseId}",
        'btn'    => 'กรอกคะแนน',
    ],
    [
        'title'  => '4. ประเมินคุณลักษณะอันพึงประสงค์ 8 ข้อ (ส่วนที่ 4)',
        'ok'     => $readiness['characteristic_ok'],
        'detail' => $readiness['characteristic_ok']
            ? 'ประเมินครบทุกคนแล้ว (' . $totalStudents . ' คน)'
            : 'ยังประเมินไม่ครบอีก ' . (int)($assessments['characteristic']['dist']['incomplete'] ?? 0) . ' คน',
        'link'   => "/pp5/course_assessments.php?course_id={$courseId}&type=characteristic",
        'btn'    => 'ประเมินคุณลักษณะฯ',
    ],
    [
        'title'  => '5. ประเมินการอ่าน คิดวิเคราะห์ และเขียน 5 ข้อ (ส่วนที่ 5)',
        'ok'     => $readiness['rtw_ok'],
        'detail' => $readiness['rtw_ok']
            ? 'ประเมินครบทุกคนแล้ว (' . $totalStudents . ' คน)'
            : 'ยังประเมินไม่ครบอีก ' . (int)($assessments['rtw']['dist']['incomplete'] ?? 0) . ' คน',
        'link'   => "/pp5/course_assessments.php?course_id={$courseId}&type=rtw",
        'btn'    => 'ประเมินอ่านคิดเขียน',
    ],
    [
        'title'  => '6. ประเมินสมรรถนะสำคัญของผู้เรียน 5 ด้าน (ส่วนที่ 5)',
        'ok'     => $readiness['competency_ok'],
        'detail' => $readiness['competency_ok']
            ? 'ประเมินครบทุกคนแล้ว (' . $totalStudents . ' คน)'
            : 'ยังประเมินไม่ครบอีก ' . (int)($assessments['competency']['dist']['incomplete'] ?? 0) . ' คน',
        'link'   => "/pp5/course_assessments.php?course_id={$courseId}&type=competency",
        'btn'    => 'ประเมินสมรรถนะ',
    ],
];

$pageTitle    = 'สรุปผล & พิมพ์เล่ม ปพ.5 — ' . $course['subject_code'] . ' (' . $course['classroom_name'] . ')';
$pageSubtitle = 'ตรวจสอบความครบถ้วนของข้อมูลทั้ง 5 ส่วน สถิติผลการเรียน และสั่งพิมพ์เอกสาร ปพ.5 ขนาด A4';
$activeSystem = 'pp5';

require_once __DIR__ . '/../components/layout_start.php';
echo pp5_render_top_bar($pdo, $termCtx, $policy, 'dashboard');
echo pp5_render_course_nav($course, $policy, 'summary');
?>

<!-- แจ้งเตือนกรณีถูกส่งกลับแก้ไข -->
<?php if (!empty($course['rejected_reason'])): ?>
    <div class="bg-rose-50 border border-rose-200 rounded-2xl p-4 mb-6 flex items-start gap-3 shadow-sm">
        <div class="w-9 h-9 rounded-xl bg-rose-500 text-white flex items-center justify-center shrink-0 mt-0.5">
            <i class="bi bi-exclamation-octagon-fill text-lg"></i>
        </div>
        <div class="flex-1">
            <div class="text-xs font-black text-rose-800">เล่ม ปพ.5 นี้ถูกส่งกลับเพื่อแก้ไข</div>
            <div class="text-xs text-rose-700 mt-1 leading-relaxed"><?= htmlspecialchars($course['rejected_reason'], ENT_QUOTES, 'UTF-8') ?></div>
            <div class="text-[11px] text-rose-500 mt-1 font-semibold">เมื่อแก้ไขข้อมูลตามข้อคิดเห็นครบถ้วนแล้ว ให้กดส่งตรวจอีกครั้ง</div>
        </div>
    </div>
<?php endif; ?>

<!-- การดำเนินงานและลำดับขั้นตอนการอนุมัติ (Approval Workflow Stepper) -->
<div class="bg-white rounded-2xl shadow-xl shadow-blue-100/50 border border-slate-100 p-6 mb-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-5 pb-4 border-b border-slate-100">
        <div>
            <h3 class="text-base font-black text-slate-800 flex items-center gap-2">
                <i class="bi bi-diagram-3-fill text-indigo-600"></i>
                สถานะและขั้นตอนการส่งตรวจเล่ม ปพ.5 (Approval Workflow)
            </h3>
            <p class="text-xs text-slate-500 mt-0.5">การตรวจสอบและลงนามดิจิทัล 4 ลำดับขั้นตามระเบียบงานวัดผลโรงเรียนละลมวิทยา</p>
        </div>
        <div>
            <?php
            $statusBadges = [
                'draft'           => ['label' => 'ฉบับร่าง (กำลังบันทึก)',       'class' => 'bg-slate-100 text-slate-700 border-slate-200'],
                'submitted'       => ['label' => 'รอหัวหน้ากลุ่มสาระตรวจ',       'class' => 'bg-amber-100 text-amber-800 border-amber-300'],
                'head_approved'   => ['label' => 'รองฯ วิชาการรออนุมัติ',         'class' => 'bg-blue-100 text-blue-800 border-blue-300'],
                'deputy_approved' => ['label' => 'รอผู้อำนวยการอนุมัติ',          'class' => 'bg-indigo-100 text-indigo-800 border-indigo-300'],
                'approved'        => ['label' => 'อนุมัติเรียบร้อย (ล็อกเล่ม)', 'class' => 'bg-emerald-100 text-emerald-800 border-emerald-300'],
                'locked'          => ['label' => 'ล็อกเล่มสมบูรณ์',            'class' => 'bg-purple-100 text-purple-800 border-purple-300'],
            ];
            $curBadge = $statusBadges[$course['status']] ?? $statusBadges['draft'];
            ?>
            <span class="px-3.5 py-1.5 rounded-full text-xs font-black border inline-flex items-center gap-1.5 <?= $curBadge['class'] ?>">
                <span class="w-2 h-2 rounded-full <?= in_array($course['status'], ['approved', 'locked'], true) ? 'bg-emerald-500' : ($course['status'] === 'draft' ? 'bg-slate-400' : 'bg-amber-500 animate-pulse') ?>"></span>
                สถานะปัจจุบัน: <?= htmlspecialchars($curBadge['label'], ENT_QUOTES, 'UTF-8') ?>
            </span>
        </div>
    </div>

    <!-- Stepper 4 ขั้นตอน -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 mb-5">
        <!-- 1. ครูผู้สอนจัดทำ -->
        <?php
        $step1Done = in_array($course['status'], ['submitted', 'head_approved', 'deputy_approved', 'approved', 'locked'], true);
        ?>
        <div class="p-3.5 rounded-2xl border <?= $step1Done ? 'bg-emerald-50/60 border-emerald-200' : 'bg-slate-50 border-slate-200' ?>">
            <div class="flex items-center gap-2.5">
                <div class="w-7 h-7 rounded-xl flex items-center justify-center font-black text-xs <?= $step1Done ? 'bg-emerald-600 text-white' : 'bg-slate-300 text-slate-700' ?>">
                    <?= $step1Done ? '<i class="bi bi-check-lg"></i>' : '1' ?>
                </div>
                <div>
                    <div class="text-xs font-black text-slate-800">1. ครูผู้สอนจัดทำ</div>
                    <div class="text-[10px] <?= $step1Done ? 'text-emerald-700 font-bold' : 'text-slate-500' ?>">
                        <?= $step1Done ? 'ส่งตรวจเมื่อ ' . pp5_thai_date($course['submitted_at'] ?? '') : 'กำลังดำเนินการ' ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- 2. หัวหน้ากลุ่มสาระตรวจ -->
        <?php
        $step2Done = in_array($course['status'], ['head_approved', 'deputy_approved', 'approved', 'locked'], true);
        $step2Cur  = ($course['status'] === 'submitted');
        ?>
        <div class="p-3.5 rounded-2xl border <?= $step2Done ? 'bg-emerald-50/60 border-emerald-200' : ($step2Cur ? 'bg-amber-50 border-amber-300 shadow-sm' : 'bg-slate-50 border-slate-200') ?>">
            <div class="flex items-center gap-2.5">
                <div class="w-7 h-7 rounded-xl flex items-center justify-center font-black text-xs <?= $step2Done ? 'bg-emerald-600 text-white' : ($step2Cur ? 'bg-amber-500 text-white' : 'bg-slate-300 text-slate-700') ?>">
                    <?= $step2Done ? '<i class="bi bi-check-lg"></i>' : '2' ?>
                </div>
                <div>
                    <div class="text-xs font-black text-slate-800">2. หน.กลุ่มสาระตรวจ</div>
                    <div class="text-[10px] <?= $step2Done ? 'text-emerald-700 font-bold' : ($step2Cur ? 'text-amber-700 font-black animate-pulse' : 'text-slate-500') ?>">
                        <?= $step2Done ? 'เห็นชอบ ' . pp5_thai_date($course['head_approved_at'] ?? '') : ($step2Cur ? 'รอการตรวจเห็นชอบ' : 'รอดำเนินการ') ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- 3. รองฯ วิชาการเห็นชอบ -->
        <?php
        $step3Done = in_array($course['status'], ['deputy_approved', 'approved', 'locked'], true);
        $step3Cur  = ($course['status'] === 'head_approved');
        ?>
        <div class="p-3.5 rounded-2xl border <?= $step3Done ? 'bg-emerald-50/60 border-emerald-200' : ($step3Cur ? 'bg-blue-50 border-blue-300 shadow-sm' : 'bg-slate-50 border-slate-200') ?>">
            <div class="flex items-center gap-2.5">
                <div class="w-7 h-7 rounded-xl flex items-center justify-center font-black text-xs <?= $step3Done ? 'bg-emerald-600 text-white' : ($step3Cur ? 'bg-blue-600 text-white' : 'bg-slate-300 text-slate-700') ?>">
                    <?= $step3Done ? '<i class="bi bi-check-lg"></i>' : '3' ?>
                </div>
                <div>
                    <div class="text-xs font-black text-slate-800">3. รองฯ วิชาการเห็นชอบ</div>
                    <div class="text-[10px] <?= $step3Done ? 'text-emerald-700 font-bold' : ($step3Cur ? 'text-blue-700 font-black animate-pulse' : 'text-slate-500') ?>">
                        <?= $step3Done ? 'เห็นชอบ ' . pp5_thai_date($course['deputy_approved_at'] ?? '') : ($step3Cur ? 'รอฝ่ายวิชาการตรวจ' : 'รอดำเนินการ') ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- 4. ผู้อำนวยการอนุมัติ & ล็อกเล่ม -->
        <?php
        $step4Done = in_array($course['status'], ['approved', 'locked'], true);
        $step4Cur  = ($course['status'] === 'deputy_approved');
        ?>
        <div class="p-3.5 rounded-2xl border <?= $step4Done ? 'bg-emerald-50/60 border-emerald-200' : ($step4Cur ? 'bg-indigo-50 border-indigo-300 shadow-sm' : 'bg-slate-50 border-slate-200') ?>">
            <div class="flex items-center gap-2.5">
                <div class="w-7 h-7 rounded-xl flex items-center justify-center font-black text-xs <?= $step4Done ? 'bg-emerald-600 text-white' : ($step4Cur ? 'bg-indigo-600 text-white' : 'bg-slate-300 text-slate-700') ?>">
                    <?= $step4Done ? '<i class="bi bi-check-lg"></i>' : '4' ?>
                </div>
                <div>
                    <div class="text-xs font-black text-slate-800">4. ผอ.อนุมัติ & ล็อกเล่ม</div>
                    <div class="text-[10px] <?= $step4Done ? 'text-emerald-700 font-bold' : ($step4Cur ? 'text-indigo-700 font-black animate-pulse' : 'text-slate-500') ?>">
                        <?= $step4Done ? 'อนุมัติเรียบร้อย ' . pp5_thai_date($course['approved_at'] ?? '') : ($step4Cur ? 'รอ ผอ. อนุมัติขั้นสุดท้าย' : 'รอดำเนินการ') ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- แถบปุ่ม Action สำหรับบทบาทต่างๆ -->
    <div class="pt-4 border-t border-slate-100 flex flex-wrap items-center justify-between gap-3">
        <div class="text-xs text-slate-600 font-medium">
            <?php if ($course['status'] === 'draft'): ?>
                <i class="bi bi-info-circle text-indigo-600 mr-1"></i> เมื่อกรอกข้อมูลครบทุกช่องแล้ว ครูผู้สอนสามารถกดส่งตรวจเล่ม ปพ.5 ได้ทันที
            <?php elseif (in_array($course['status'], ['approved', 'locked'], true)): ?>
                <i class="bi bi-lock-fill text-emerald-600 mr-1"></i> เล่ม ปพ.5 ได้รับการอนุมัติและล็อกเล่มเรียบร้อยแล้ว หากต้องการแก้ไขกรุณาติดต่อฝ่ายทะเบียนเพื่อปลดล็อก
            <?php else: ?>
                <i class="bi bi-hourglass-split text-amber-600 mr-1"></i> อยู่ระหว่างการตรวจสอบของคณะกรรมการตามลำดับขั้น
            <?php endif; ?>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <!-- ปุ่มส่งตรวจของครูผู้สอน -->
            <?php if ($policy->canSubmitCourse($course)): ?>
                <?php if ($readiness['all_ready']): ?>
                    <button type="button" onclick="submitCourseForReview()"
                            class="px-5 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-black shadow-md shadow-indigo-200 transition-all flex items-center gap-2">
                        <i class="bi bi-send-fill"></i> ส่งตรวจเล่ม ปพ.5 ต่อหัวหน้ากลุ่มสาระ
                    </button>
                <?php else: ?>
                    <button type="button" disabled
                            title="กรุณากรอกข้อมูลให้ครบถ้วนทั้ง 6 รายการด้านล่างก่อนส่งตรวจ"
                            class="px-5 py-2.5 rounded-xl bg-slate-200 text-slate-400 text-xs font-black cursor-not-allowed flex items-center gap-2">
                        <i class="bi bi-exclamation-triangle-fill"></i> ข้อมูลยังไม่ครบตามเกณฑ์ (ดูรายการด้านล่าง)
                    </button>
                <?php endif; ?>
            <?php endif; ?>

            <!-- ปุ่มตรวจของหัวหน้ากลุ่มสาระ -->
            <?php if ($policy->canApproveCourse($course, 'head')): ?>
                <button type="button" onclick="approveCourse('head')"
                        class="px-4 py-2.5 rounded-xl bg-teal-600 hover:bg-teal-700 text-white text-xs font-bold shadow-md shadow-teal-200 transition-all flex items-center gap-1.5">
                    <i class="bi bi-check-circle-fill"></i> หน.กลุ่มสาระ: เห็นชอบเล่ม ปพ.5
                </button>
                <button type="button" onclick="rejectCourse('head')"
                        class="px-3.5 py-2.5 rounded-xl bg-rose-50 text-rose-700 hover:bg-rose-100 border border-rose-200 text-xs font-bold transition-all flex items-center gap-1.5">
                    <i class="bi bi-arrow-counterclockwise"></i> ส่งกลับแก้ไข
                </button>
            <?php endif; ?>

            <!-- ปุ่มตรวจของรองฯ วิชาการ -->
            <?php if ($policy->canApproveCourse($course, 'deputy') && $course['status'] === 'head_approved'): ?>
                <button type="button" onclick="approveCourse('deputy')"
                        class="px-4 py-2.5 rounded-xl bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold shadow-md shadow-blue-200 transition-all flex items-center gap-1.5">
                    <i class="bi bi-check2-circle"></i> รองฯ วิชาการ: เห็นชอบเล่ม ปพ.5
                </button>
                <button type="button" onclick="rejectCourse('deputy')"
                        class="px-3.5 py-2.5 rounded-xl bg-rose-50 text-rose-700 hover:bg-rose-100 border border-rose-200 text-xs font-bold transition-all flex items-center gap-1.5">
                    <i class="bi bi-arrow-counterclockwise"></i> ส่งกลับแก้ไข
                </button>
            <?php endif; ?>

            <!-- ปุ่มอนุมัติของผู้อำนวยการ -->
            <?php if ($policy->canApproveCourse($course, 'director') && in_array($course['status'], ['deputy_approved', 'head_approved', 'submitted'], true)): ?>
                <button type="button" onclick="approveCourse('director')"
                        class="px-5 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-black shadow-md shadow-emerald-200 transition-all flex items-center gap-2">
                    <i class="bi bi-patch-check-fill text-amber-300"></i> ผู้อำนวยการ: อนุมัติผลการเรียน & ล็อกเล่ม ปพ.5
                </button>
                <button type="button" onclick="rejectCourse('director')"
                        class="px-3.5 py-2.5 rounded-xl bg-rose-50 text-rose-700 hover:bg-rose-100 border border-rose-200 text-xs font-bold transition-all flex items-center gap-1.5">
                    <i class="bi bi-arrow-counterclockwise"></i> ส่งกลับแก้ไข
                </button>
            <?php endif; ?>

            <!-- ปุ่มปลดล็อกของงานทะเบียน (Admin Unlock) -->
            <?php if ($policy->canUnlockCourse() && $course['status'] !== 'draft'): ?>
                <button type="button" onclick="unlockCourse()"
                        class="px-4 py-2.5 rounded-xl bg-purple-600 hover:bg-purple-700 text-white text-xs font-bold shadow-md shadow-purple-200 transition-all flex items-center gap-1.5">
                    <i class="bi bi-unlock-fill"></i> งานทะเบียน: ปลดล็อกเล่มกลับเป็นฉบับร่าง
                </button>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- กล่องสั่งพิมพ์ / ดาวน์โหลดเล่ม ปพ.5 (ชีต ปกน(p) วิชาการ & ชีต sgs(p) SGS-ONLINE) -->
<div class="bg-gradient-to-r from-indigo-700 via-blue-700 to-indigo-800 rounded-3xl p-6 text-white shadow-xl shadow-indigo-200/60 mb-6">
    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4 mb-5">
        <div>
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-white/20 text-xs font-black mb-2">
                <i class="bi bi-printer-fill"></i> แบบฟอร์มทางการโรงเรียนละลมวิทยา (A4 แนวนอน • TH Sarabun New)
            </div>
            <h3 class="text-xl sm:text-2xl font-black">พิมพ์เอกสาร ปพ.5 ส่งงานวิชาการและวัดผล</h3>
            <p class="text-xs sm:text-sm text-indigo-100 mt-1">
                ตรงตามรูปแบบชีต <strong>ปกน(p)</strong> และชีต <strong>sgs(p)</strong> ของโรงเรียน 100% พร้อมลายเซ็นครบ 4 ลำดับขั้น
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-2 shrink-0">
            <a href="/pp5/course_pdf.php?course_id=<?= $courseId ?>&mode=all" target="_blank"
               class="px-5 py-3 rounded-2xl bg-white text-indigo-700 hover:bg-indigo-50 text-xs sm:text-sm font-black shadow-lg transition-all flex items-center gap-2">
                <i class="bi bi-collection-fill text-indigo-600"></i> เปิดดู / พิมพ์ รวมทุกชีต (A4)
            </a>
            <a href="/pp5/course_pdf.php?course_id=<?= $courseId ?>&mode=all&download=1"
               class="px-4 py-3 rounded-2xl bg-indigo-900/60 hover:bg-indigo-900 text-white border border-white/25 text-xs font-bold transition-all flex items-center gap-1.5">
                <i class="bi bi-download"></i> ดาวน์โหลด HTML
            </a>
        </div>
    </div>

    <!-- แถบ 2 ปุ่มหลักที่วิชาการกำหนด: ชีต ปกน(p) และ ชีต sgs(p) -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 pt-4 border-t border-white/15">
        <div class="bg-white/10 backdrop-blur-md rounded-2xl p-4 border border-white/20 flex flex-col justify-between gap-3 hover:bg-white/15 transition-all">
            <div>
                <div class="flex items-center justify-between mb-1">
                    <span class="text-xs font-black uppercase tracking-wider text-blue-200">ชีตหลักที่ 1</span>
                    <span class="px-2 py-0.5 rounded-md bg-blue-500/40 text-[11px] font-bold">ปพ.5-พ</span>
                </div>
                <h4 class="text-base font-black text-white flex items-center gap-2">
                    <i class="bi bi-file-earmark-text-fill text-blue-300"></i> ชีต ปกน(p) (หน้าปก & สรุปผลการประเมิน)
                </h4>
                <p class="text-xs text-indigo-100 mt-1 leading-relaxed">
                    ตารางสรุปผล 14 ระดับคะแนน (4.0–0.0, ร, มส, ผ, มผ) • สรุปอ่านคิดเขียน • คุณลักษณะอันพึงประสงค์ • ลายเซ็นครูผู้สอน หัวหน้ากลุ่ม งานวัดผล ฝ่ายวิชาการ และ ผอ.
                </p>
            </div>
            <a href="/pp5/course_pdf.php?course_id=<?= $courseId ?>&mode=pok" target="_blank"
               class="w-full py-2.5 rounded-xl bg-blue-500 hover:bg-blue-600 text-white font-black text-xs text-center shadow transition-all flex items-center justify-center gap-2">
                <i class="bi bi-printer-fill"></i> พิมพ์ใบปก ปพ.5-พ (ชีต ปกน(p))
            </a>
        </div>

        <div class="bg-white/10 backdrop-blur-md rounded-2xl p-4 border border-white/20 flex flex-col justify-between gap-3 hover:bg-white/15 transition-all">
            <div>
                <div class="flex items-center justify-between mb-1">
                    <span class="text-xs font-black uppercase tracking-wider text-cyan-200">ชีตหลักที่ 2</span>
                    <span class="px-2 py-0.5 rounded-md bg-cyan-500/40 text-[11px] font-bold">SGS-ONLINE</span>
                </div>
                <h4 class="text-base font-black text-white flex items-center gap-2">
                    <i class="bi bi-table text-cyan-300"></i> ชีต sgs(p) (ตารางบันทึกคะแนน 43 คอลัมน์)
                </h4>
                <p class="text-xs text-indigo-100 mt-1 leading-relaxed">
                    ตารางคะแนน 43 คอลัมน์ตรงตาม SGS: หน่วย 1–6, กลางภาค, หน่วย 7–12, ปลายภาค, รวม, เกรด, คุณลักษณะฯ 1–8+ผล, อ่านคิดเขียน 1–5+ผล, เวลาเรียน (สาย ป่วย กิจ ขาด ร้อยละ)
                </p>
            </div>
            <a href="/pp5/course_pdf.php?course_id=<?= $courseId ?>&mode=sgs" target="_blank"
               class="w-full py-2.5 rounded-xl bg-cyan-600 hover:bg-cyan-700 text-white font-black text-xs text-center shadow transition-all flex items-center justify-center gap-2">
                <i class="bi bi-printer-fill"></i> พิมพ์แบบบันทึกคะแนน SGS (ชีต sgs(p))
            </a>
        </div>
    </div>
</div>

<!-- รายการตรวจสอบความพร้อมของเล่ม ปพ.5 (Readiness Checklist) -->
<div class="bg-white rounded-2xl shadow-xl shadow-blue-100/50 border border-slate-100 p-6 mb-6">
    <div class="flex items-center justify-between gap-3 mb-4">
        <div>
            <h3 class="text-base font-black text-slate-800 flex items-center gap-2">
                <i class="bi bi-clipboard2-check-fill text-indigo-600"></i>
                รายการตรวจสอบความครบถ้วนของเล่ม ปพ.5 (Readiness Checklist)
            </h3>
            <p class="text-xs text-slate-500 mt-0.5">ตรวจสอบความสมบูรณ์ของข้อมูลทั้ง 6 รายการก่อนส่งหัวหน้ากลุ่มสาระตรวจสอบ</p>
        </div>
        <span class="px-3.5 py-1.5 rounded-full text-xs font-black <?= $readiness['all_ready'] ? 'bg-emerald-100 text-emerald-800 border border-emerald-300' : 'bg-amber-100 text-amber-800 border border-amber-300' ?>">
            <?= $readiness['all_ready'] ? 'ข้อมูลครบถ้วนพร้อมส่งตรวจ ✓' : 'ยังมีรายการที่ต้องกรอกเพิ่ม' ?>
        </span>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
        <?php foreach ($checklistItems as $chk): ?>
            <div class="p-4 rounded-2xl border flex items-center justify-between gap-3 <?= $chk['ok'] ? 'bg-emerald-50/50 border-emerald-200' : 'bg-amber-50/60 border-amber-200' ?>">
                <div class="flex items-start gap-3">
                    <div class="w-8 h-8 rounded-xl flex items-center justify-center shrink-0 mt-0.5 <?= $chk['ok'] ? 'bg-emerald-600 text-white' : 'bg-amber-500 text-white' ?>">
                        <i class="bi <?= $chk['ok'] ? 'bi-check-lg' : 'bi-exclamation-lg' ?>"></i>
                    </div>
                    <div>
                        <div class="text-xs sm:text-sm font-black text-slate-800"><?= htmlspecialchars($chk['title'], ENT_QUOTES, 'UTF-8') ?></div>
                        <div class="text-xs mt-0.5 font-semibold <?= $chk['ok'] ? 'text-emerald-700' : 'text-amber-800' ?>">
                            <?= htmlspecialchars($chk['detail'], ENT_QUOTES, 'UTF-8') ?>
                        </div>
                    </div>
                </div>
                <a href="<?= htmlspecialchars($chk['link'], ENT_QUOTES, 'UTF-8') ?>"
                   class="px-3 py-1.5 rounded-xl text-xs font-bold shrink-0 transition-all <?= $chk['ok'] ? 'bg-white text-slate-600 hover:bg-slate-100 border border-slate-200' : 'bg-indigo-600 text-white hover:bg-indigo-700 shadow-sm' ?>">
                    <?= htmlspecialchars($chk['btn'], ENT_QUOTES, 'UTF-8') ?>
                </a>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- ตารางสรุปสถิติผลการเรียน (ปกหน้า ปพ.5) -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
    <!-- ซ้าย: ตารางจำนวนและร้อยละของระดับผลการเรียน -->
    <div class="bg-white rounded-2xl shadow-xl shadow-blue-100/50 border border-slate-100 p-6">
        <h3 class="text-base font-black text-slate-800 mb-3 flex items-center gap-2">
            <i class="bi bi-bar-chart-fill text-indigo-600"></i>
            สรุปจำนวนและร้อยละของระดับผลการเรียน (<?= $totalStudents ?> คน)
        </h3>

        <div class="overflow-x-auto">
            <table class="w-full text-center border-collapse text-xs">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-200 font-black text-slate-600">
                        <th class="py-2.5 px-2 text-left">ระดับผลการเรียน</th>
                        <?php foreach (['4', '3.5', '3', '2.5', '2', '1.5', '1', '0', 'ร', 'มส'] as $gk): ?>
                            <th class="py-2.5 px-2"><?= $gk ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr>
                        <td class="py-2.5 px-2 text-left font-bold text-slate-700">จำนวน (คน)</td>
                        <?php foreach (['4', '3.5', '3', '2.5', '2', '1.5', '1', '0', 'ร', 'มส'] as $gk): ?>
                            <td class="py-2.5 px-2 font-black text-slate-900"><?= (int)$gradeCounts[$gk] ?></td>
                        <?php endforeach; ?>
                    </tr>
                    <tr>
                        <td class="py-2.5 px-2 text-left font-bold text-slate-500">ร้อยละ (%)</td>
                        <?php foreach (['4', '3.5', '3', '2.5', '2', '1.5', '1', '0', 'ร', 'มส'] as $gk): ?>
                            <td class="py-2.5 px-2 font-semibold text-indigo-600"><?= number_format((float)$gradePercents[$gk], 1) ?></td>
                        <?php endforeach; ?>
                    </tr>
                </tbody>
            </table>
        </div>

        <?php
        $grade3UpCnt = $gradeCounts['4'] + $gradeCounts['3.5'] + $gradeCounts['3'];
        $grade3UpPct = $totalStudents > 0 ? round(($grade3UpCnt / $totalStudents) * 100, 2) : 0;
        ?>
        <div class="mt-4 p-3.5 rounded-xl bg-indigo-50/70 border border-indigo-100 flex items-center justify-between text-xs">
            <span class="font-bold text-indigo-900">นักเรียนที่ได้เกรด 3.00 ขึ้นไป (ระดับดี–ดีเยี่ยม):</span>
            <span class="font-black text-indigo-700 text-sm"><?= $grade3UpCnt ?> คน (<?= number_format($grade3UpPct, 2) ?>%)</span>
        </div>
    </div>

    <!-- ขวา: สรุปผลคุณลักษณะฯ / อ่านคิดเขียน / สมรรถนะ -->
    <div class="bg-white rounded-2xl shadow-xl shadow-blue-100/50 border border-slate-100 p-6">
        <h3 class="text-base font-black text-slate-800 mb-3 flex items-center gap-2">
            <i class="bi bi-award-fill text-emerald-600"></i>
            สรุปผลคุณลักษณะอันพึงประสงค์ • อ่านคิดเขียน • สมรรถนะสำคัญ
        </h3>

        <div class="overflow-x-auto">
            <table class="w-full text-center border-collapse text-xs">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-200 font-black text-slate-600">
                        <th class="py-2.5 px-3 text-left">ด้านการประเมิน</th>
                        <th class="py-2.5 px-2 text-emerald-700">3 (ดีเยี่ยม)</th>
                        <th class="py-2.5 px-2 text-blue-700">2 (ดี)</th>
                        <th class="py-2.5 px-2 text-amber-700">1 (ผ่าน)</th>
                        <th class="py-2.5 px-2 text-rose-700">0 (ไม่ผ่าน)</th>
                        <th class="py-2.5 px-2 text-slate-400">ค้าง</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php
                    $assLabels = [
                        'characteristic' => 'คุณลักษณะอันพึงประสงค์ (8 ข้อ)',
                        'rtw'            => 'อ่าน คิดวิเคราะห์ และเขียน (5 ข้อ)',
                        'competency'     => 'สมรรถนะสำคัญของผู้เรียน (5 ด้าน)',
                    ];
                    foreach ($assLabels as $aKey => $aName):
                        $d = $assessments[$aKey]['dist'];
                    ?>
                        <tr>
                            <td class="py-2.5 px-3 text-left font-bold text-slate-800"><?= $aName ?></td>
                            <td class="py-2.5 px-2 font-black text-emerald-700"><?= (int)$d[3] ?></td>
                            <td class="py-2.5 px-2 font-black text-blue-700"><?= (int)$d[2] ?></td>
                            <td class="py-2.5 px-2 font-black text-amber-700"><?= (int)$d[1] ?></td>
                            <td class="py-2.5 px-2 font-black text-rose-700"><?= (int)$d[0] ?></td>
                            <td class="py-2.5 px-2 font-bold text-slate-400"><?= (int)$d['incomplete'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- รายชื่อนักเรียนที่ติด 0 / ร / มส / มผ (ถ้ามี) -->
<div class="bg-white rounded-2xl shadow-xl shadow-blue-100/50 border border-slate-100 overflow-hidden">
    <div class="p-5 border-b border-slate-100 flex items-center justify-between">
        <div>
            <h3 class="text-base font-black text-slate-800 flex items-center gap-2">
                <i class="bi bi-exclamation-diamond-fill text-rose-600"></i>
                รายชื่อนักเรียนที่มีผลการเรียน 0 / ร / มส / มผ (<?= count($remedialList) ?> คน)
            </h3>
            <p class="text-xs text-slate-500 mt-0.5">ตรวจสอบสาเหตุเพื่อติดตามงานค้างหรือดำเนินการสอนซ่อมเสริม</p>
        </div>
    </div>

    <?php if (empty($remedialList)): ?>
        <div class="p-8 text-center text-xs text-emerald-700 font-bold bg-emerald-50/40">
            <i class="bi bi-check-circle-fill text-xl block mb-1 text-emerald-600"></i>
            ไม่มีนักเรียนที่ติด 0, ร, มส หรือ มผ ในรายวิชานี้
        </div>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-xs">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-200 font-black text-slate-500 uppercase">
                        <th class="py-3 px-4 text-center w-16">เลขที่</th>
                        <th class="py-3 px-4">รหัสนักเรียน</th>
                        <th class="py-3 px-4">ชื่อ - นามสกุล</th>
                        <th class="py-3 px-4 text-center">คะแนนเก็บ</th>
                        <th class="py-3 px-4 text-center">ปลายภาค</th>
                        <th class="py-3 px-4 text-center">รวมสุทธิ</th>
                        <th class="py-3 px-4 text-center">เวลาเรียน (%)</th>
                        <th class="py-3 px-4 text-center">ผลการเรียน</th>
                        <?php if ($policy->canEditCourse($course) || $policy->isAdmin()): ?>
                            <th class="py-3 px-4 text-center">บันทึกผลสอบแก้ตัว</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach ($remedialList as $rm):
                        $st = $rm['student'];
                        $gr = $rm['grade'];
                    ?>
                        <tr class="hover:bg-rose-50/30">
                            <td class="py-2.5 px-4 text-center font-black"><?= (int)$st['student_number'] ?></td>
                            <td class="py-2.5 px-4 font-mono font-bold text-slate-600"><?= htmlspecialchars($st['student_id'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="py-2.5 px-4 font-bold text-slate-800"><?= htmlspecialchars($st['name'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="py-2.5 px-4 text-center font-bold"><?= (float)$gr['score_collect'] ?></td>
                            <td class="py-2.5 px-4 text-center font-bold"><?= (float)$gr['score_final'] ?></td>
                            <td class="py-2.5 px-4 text-center font-black"><?= (int)$gr['total_score'] ?></td>
                            <td class="py-2.5 px-4 text-center font-bold <?= (float)$gr['attendance_percent'] < 80 ? 'text-rose-600' : 'text-emerald-700' ?>">
                                <?= number_format((float)$gr['attendance_percent'], 1) ?>%
                            </td>
                            <td class="py-2.5 px-4 text-center">
                                <span class="px-2.5 py-0.5 rounded-full bg-rose-100 text-rose-800 border border-rose-300 font-black">
                                    <?= htmlspecialchars((string)$gr['grade'], ENT_QUOTES, 'UTF-8') ?>
                                </span>
                                <?php if (!empty($gr['is_remedial']) && !empty($gr['original_grade'])): ?>
                                    <span class="block text-[10px] text-amber-700 font-bold mt-0.5">แก้จาก <?= htmlspecialchars((string)$gr['original_grade'], ENT_QUOTES, 'UTF-8') ?></span>
                                <?php endif; ?>
                            </td>
                            <?php if ($policy->canEditCourse($course) || $policy->isAdmin()): ?>
                                <td class="py-2.5 px-4 text-center">
                                    <button type="button"
                                            onclick="openRemedialModal(<?= (int)$st['id'] ?>, '<?= htmlspecialchars(addslashes($st['name']), ENT_QUOTES, 'UTF-8') ?>', '<?= htmlspecialchars((string)$gr['grade'], ENT_QUOTES, 'UTF-8') ?>')"
                                            class="px-3 py-1 rounded-xl bg-indigo-50 text-indigo-700 hover:bg-indigo-100 border border-indigo-200 text-xs font-bold transition-all inline-flex items-center gap-1">
                                        <i class="bi bi-pencil-square"></i> บันทึกผลแก้ตัว
                                    </button>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- ประวัติการส่งตรวจ • อนุมัติ • ส่งกลับแก้ไข • ปลดล็อก (Approval History Log) -->
<div class="bg-white rounded-2xl shadow-xl shadow-blue-100/50 border border-slate-100 overflow-hidden mt-6">
    <div class="p-5 border-b border-slate-100 flex items-center justify-between">
        <div>
            <h3 class="text-base font-black text-slate-800 flex items-center gap-2">
                <i class="bi bi-clock-history text-indigo-600"></i>
                ประวัติการส่งตรวจและลงนามอนุมัติ (Approval History Log)
            </h3>
            <p class="text-xs text-slate-500 mt-0.5">บันทึกประวัติการตรวจสอบทุกขั้นตอนตามระเบียบงานทะเบียนและวัดผล</p>
        </div>
        <span class="px-3 py-1 rounded-full bg-slate-100 text-slate-700 text-xs font-bold">
            <?= count($approvals) ?> รายการ
        </span>
    </div>

    <?php if (empty($approvals)): ?>
        <div class="p-8 text-center text-xs text-slate-500 bg-slate-50/50">
            <i class="bi bi-inbox text-2xl block mb-1 text-slate-400"></i>
            ยังไม่มีประวัติการส่งตรวจในรายวิชานี้ (สถานะปัจจุบัน: ฉบับร่าง)
        </div>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-xs">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-200 font-black text-slate-500 uppercase tracking-wider">
                        <th class="py-3 px-4 text-center w-14">#</th>
                        <th class="py-3 px-4 w-44">วันเวลาที่บันทึก</th>
                        <th class="py-3 px-4">ขั้นตอน</th>
                        <th class="py-3 px-4">ผู้ดำเนินการ</th>
                        <th class="py-3 px-4 text-center w-28">ผลการพิจารณา</th>
                        <th class="py-3 px-4">ข้อคิดเห็น / เหตุผล</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php
                    $stepNames = [
                        'teacher_submit' => 'ครูผู้สอนส่งตรวจ',
                        'head_review'    => 'หัวหน้ากลุ่มสาระตรวจ',
                        'deputy_review'  => 'รองฯ วิชาการตรวจ',
                        'director_review'=> 'ผู้อำนวยการอนุมัติ',
                        'admin_unlock'   => 'งานทะเบียนปลดล็อก',
                        'head_reject'    => 'หน.กลุ่มสาระส่งกลับแก้ไข',
                        'deputy_reject'  => 'รองฯ วิชาการส่งกลับแก้ไข',
                        'director_reject'=> 'ผอ. ส่งกลับแก้ไข',
                        'reviewer_reject'=> 'ผู้ตรวจสอบส่งกลับแก้ไข',
                    ];
                    $actionBadges = [
                        'submit'  => ['label' => 'ส่งตรวจ',      'class' => 'bg-indigo-100 text-indigo-800 border-indigo-200'],
                        'approve' => ['label' => 'เห็นชอบ/อนุมัติ', 'class' => 'bg-emerald-100 text-emerald-800 border-emerald-200'],
                        'reject'  => ['label' => 'ส่งกลับแก้ไข', 'class' => 'bg-rose-100 text-rose-800 border-rose-200'],
                        'unlock'  => ['label' => 'ปลดล็อกเล่ม',  'class' => 'bg-purple-100 text-purple-800 border-purple-200'],
                        'lock'    => ['label' => 'ล็อกเล่ม',      'class' => 'bg-slate-100 text-slate-800 border-slate-200'],
                    ];
                    foreach ($approvals as $idx => $ap):
                        $stName = $stepNames[$ap['step']] ?? $ap['step'];
                        $actBg  = $actionBadges[$ap['action']] ?? ['label' => $ap['action'], 'class' => 'bg-slate-100 text-slate-700'];
                        $userName = trim(($ap['firstname'] ?? '') . ' ' . ($ap['lastname'] ?? ''));
                        if ($userName === '') $userName = 'ผู้ใช้ #' . $ap['approver_id'];
                    ?>
                        <tr class="hover:bg-slate-50/70">
                            <td class="py-3 px-4 text-center font-black text-slate-400"><?= $idx + 1 ?></td>
                            <td class="py-3 px-4 font-mono text-slate-600">
                                <?= pp5_thai_date($ap['created_at']) ?>
                                <span class="text-slate-400 text-[11px]"><?= date('H:i น.', strtotime($ap['created_at'])) ?></span>
                            </td>
                            <td class="py-3 px-4 font-bold text-slate-800"><?= htmlspecialchars($stName, ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="py-3 px-4 font-bold text-indigo-700"><?= htmlspecialchars($userName, ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="py-3 px-4 text-center">
                                <span class="px-2.5 py-0.5 rounded-full text-[11px] font-black border <?= $actBg['class'] ?>">
                                    <?= htmlspecialchars($actBg['label'], ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            </td>
                            <td class="py-3 px-4 text-slate-600">
                                <?= htmlspecialchars((string)($ap['comment'] ?? '-'), ENT_QUOTES, 'UTF-8') ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<script>
const PP5_COURSE_ID = <?= (int)$courseId ?>;

// ส่งตรวจเล่ม ปพ.5 (ครูผู้สอน)
async function submitCourseForReview() {
    const result = await Swal.fire({
        title: 'ยืนยันการส่งตรวจเล่ม ปพ.5?',
        html: `<p class="text-xs text-slate-600 leading-relaxed">
            เมื่อส่งตรวจแล้ว สถานะจะเปลี่ยนเป็น <b>"รอหัวหน้ากลุ่มสาระตรวจ"</b><br>
            และจะไม่สามารถแก้ไขคะแนนหรือเวลาเรียนได้จนกว่าจะได้รับการอนุมัติหรือส่งกลับแก้ไข
        </p>`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#4f46e5',
        cancelButtonColor: '#94a3b8',
        confirmButtonText: '<i class="bi bi-send-fill"></i> ยืนยันส่งตรวจ',
        cancelButtonText: 'ยกเลิก',
    });

    if (!result.isConfirmed) return;

    Swal.fire({
        title: 'กำลังส่งข้อมูล...',
        allowOutsideClick: false,
        didOpen: () => Swal.showLoading(),
    });

    try {
        const res = await fetch('/pp5/api_course.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'submit_course',
                course_id: PP5_COURSE_ID,
            })
        });
        const data = await res.json();
        if (!res.ok || data.status !== 'success') {
            throw new Error(data.message || 'เกิดข้อผิดพลาดในการส่งตรวจ');
        }

        await Swal.fire({
            icon: 'success',
            title: 'ส่งตรวจเรียบร้อยแล้ว!',
            text: data.message,
            confirmButtonColor: '#4f46e5',
            confirmButtonText: 'ตกลง',
        });
        window.location.reload();
    } catch (err) {
        Swal.fire({
            icon: 'error',
            title: 'ส่งตรวจไม่สำเร็จ',
            text: err.message,
            confirmButtonColor: '#ef4444',
        });
    }
}

// อนุมัติ / ให้ความเห็นชอบ (head, deputy, director)
async function approveCourse(step) {
    let title = 'ยืนยันการให้ความเห็นชอบ?';
    let btnText = 'ยืนยันเห็นชอบ';
    if (step === 'director') {
        title = 'ยืนยันการอนุมัติผลการเรียน & ล็อกเล่ม ปพ.5?';
        btnText = 'อนุมัติผลการเรียน';
    }

    const { value: comment, isConfirmed } = await Swal.fire({
        title: title,
        input: 'textarea',
        inputLabel: 'ข้อคิดเห็น / คำสั่งการเพิ่มเติม (ถ้ามี)',
        inputPlaceholder: 'ระบุข้อคิดเห็น (เว้นว่างได้ ระบบจะใส่ข้อความมาตรฐานให้)',
        showCancelButton: true,
        confirmButtonColor: step === 'director' ? '#059669' : '#0d9488',
        cancelButtonColor: '#94a3b8',
        confirmButtonText: `<i class="bi bi-check-circle-fill"></i> ${btnText}`,
        cancelButtonText: 'ยกเลิก',
    });

    if (!isConfirmed) return;

    Swal.fire({
        title: 'กำลังบันทึกการอนุมัติ...',
        allowOutsideClick: false,
        didOpen: () => Swal.showLoading(),
    });

    try {
        const res = await fetch('/pp5/api_course.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'approve_course',
                course_id: PP5_COURSE_ID,
                step: step,
                comment: comment || '',
            })
        });
        const data = await res.json();
        if (!res.ok || data.status !== 'success') {
            throw new Error(data.message || 'เกิดข้อผิดพลาดในการบันทึก');
        }

        await Swal.fire({
            icon: 'success',
            title: 'บันทึกสำเร็จ!',
            text: data.message,
            confirmButtonColor: '#059669',
            confirmButtonText: 'ตกลง',
        });
        window.location.reload();
    } catch (err) {
        Swal.fire({
            icon: 'error',
            title: 'ไม่สามารถอนุมัติได้',
            text: err.message,
            confirmButtonColor: '#ef4444',
        });
    }
}

// ส่งกลับให้ครูแก้ไข (Reject with Reason)
async function rejectCourse(step) {
    const { value: reason, isConfirmed } = await Swal.fire({
        title: 'ส่งกลับให้ครูผู้สอนแก้ไข',
        input: 'textarea',
        inputLabel: 'เหตุผลการตีกลับ / รายการที่ต้องแก้ไข *',
        inputPlaceholder: 'เช่น คะแนนชิ้นงานที่ 3 ไม่ตรงกับแบบประเมิน หรือยังเช็คชื่อไม่ครบ',
        inputValidator: (value) => {
            if (!value || !value.trim()) {
                return 'กรุณาระบุเหตุผลการตีกลับให้ครูผู้สอนทราบ!';
            }
        },
        showCancelButton: true,
        confirmButtonColor: '#e11d48',
        cancelButtonColor: '#94a3b8',
        confirmButtonText: '<i class="bi bi-arrow-counterclockwise"></i> ยืนยันส่งกลับแก้ไข',
        cancelButtonText: 'ยกเลิก',
    });

    if (!isConfirmed || !reason) return;

    Swal.fire({
        title: 'กำลังดำเนินการ...',
        allowOutsideClick: false,
        didOpen: () => Swal.showLoading(),
    });

    try {
        const res = await fetch('/pp5/api_course.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'reject_course',
                course_id: PP5_COURSE_ID,
                reason: reason.trim(),
            })
        });
        const data = await res.json();
        if (!res.ok || data.status !== 'success') {
            throw new Error(data.message || 'เกิดข้อผิดพลาดในการส่งกลับแก้ไข');
        }

        await Swal.fire({
            icon: 'success',
            title: 'ส่งกลับแก้ไขเรียบร้อยแล้ว',
            text: data.message,
            confirmButtonColor: '#e11d48',
            confirmButtonText: 'ตกลง',
        });
        window.location.reload();
    } catch (err) {
        Swal.fire({
            icon: 'error',
            title: 'เกิดข้อผิดพลาด',
            text: err.message,
            confirmButtonColor: '#ef4444',
        });
    }
}

// ปลดล็อกเล่ม ปพ.5 (Admin Unlock)
async function unlockCourse() {
    const { value: reason, isConfirmed } = await Swal.fire({
        title: 'ปลดล็อกเล่ม ปพ.5 (งานทะเบียนและวัดผล)',
        input: 'textarea',
        inputLabel: 'ระบุเหตุผลการปลดล็อกเล่มกลับเป็นฉบับร่าง *',
        inputPlaceholder: 'เช่น ได้รับคำร้องขอแก้ไขคะแนนสอบปลายภาคจากครูผู้สอน',
        inputValidator: (value) => {
            if (!value || !value.trim()) {
                return 'กรุณาระบุเหตุผลในการปลดล็อกเล่ม!';
            }
        },
        showCancelButton: true,
        confirmButtonColor: '#9333ea',
        cancelButtonColor: '#94a3b8',
        confirmButtonText: '<i class="bi bi-unlock-fill"></i> ยืนยันปลดล็อกเล่ม',
        cancelButtonText: 'ยกเลิก',
    });

    if (!isConfirmed || !reason) return;

    Swal.fire({
        title: 'กำลังปลดล็อกเล่ม...',
        allowOutsideClick: false,
        didOpen: () => Swal.showLoading(),
    });

    try {
        const res = await fetch('/pp5/api_course.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'unlock_course',
                course_id: PP5_COURSE_ID,
                reason: reason.trim(),
            })
        });
        const data = await res.json();
        if (!res.ok || data.status !== 'success') {
            throw new Error(data.message || 'เกิดข้อผิดพลาดในการปลดล็อกเล่ม');
        }

        await Swal.fire({
            icon: 'success',
            title: 'ปลดล็อกเล่มเรียบร้อยแล้ว!',
            text: data.message,
            confirmButtonColor: '#9333ea',
            confirmButtonText: 'ตกลง',
        });
        window.location.reload();
    } catch (err) {
        Swal.fire({
            icon: 'error',
            title: 'ปลดล็อกไม่สำเร็จ',
            text: err.message,
            confirmButtonColor: '#ef4444',
        });
    }
}

// บันทึกผลการสอบแก้ตัว 0 / ร / มส / มผ
async function openRemedialModal(studentId, studentName, currentGrade) {
    const isAct = <?= $course['subject_type'] === 'activity' ? 'true' : 'false' ?>;
    const gradeOptions = isAct
        ? `<option value="ผ">ผ (ผ่าน)</option><option value="มผ">มผ (ไม่ผ่าน)</option>`
        : `<option value="1">1 (ผ่านเกณฑ์ - สูงสุดตามระเบียบกระทรวงฯ)</option><option value="0">0 (ไม่ผ่านการสอบแก้ตัว)</option>`;

    const todayStr = new Date().toISOString().split('T')[0];

    const { value: formValues, isConfirmed } = await Swal.fire({
        title: 'บันทึกผลสอบแก้ตัว',
        html: `
            <div class="text-left text-xs space-y-3">
                <div class="p-3 rounded-xl bg-slate-50 border border-slate-200">
                    <p class="font-black text-slate-800 text-sm">${studentName}</p>
                    <p class="text-slate-500 mt-0.5">ผลการเรียนเดิม: <span class="font-black text-rose-600">${currentGrade}</span></p>
                </div>
                <div class="p-2.5 rounded-xl bg-amber-50 border border-amber-200 text-amber-800 text-[11px] leading-relaxed">
                    <i class="bi bi-info-circle-fill"></i> ตามระเบียบกระทรวงศึกษาธิการ: สอบแก้ตัว 0 หรือ ร หรือ มส จะได้รับผลการเรียนไม่เกินเกรด 1
                </div>
                <div>
                    <label class="block font-bold text-slate-700 mb-1">ผลการเรียนหลังสอบแก้ตัว *</label>
                    <select id="swal_new_grade" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs font-bold outline-none focus:ring-2 focus:ring-indigo-500">
                        ${gradeOptions}
                    </select>
                </div>
                <div>
                    <label class="block font-bold text-slate-700 mb-1">วันที่สอบแก้ตัว *</label>
                    <input type="date" id="swal_rem_date" value="${todayStr}" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs outline-none focus:ring-2 focus:ring-indigo-500">
                </div>
            </div>
        `,
        focusConfirm: false,
        showCancelButton: true,
        confirmButtonColor: '#4f46e5',
        cancelButtonColor: '#94a3b8',
        confirmButtonText: '<i class="bi bi-check-lg"></i> บันทึกผลสอบแก้ตัว',
        cancelButtonText: 'ยกเลิก',
        preConfirm: () => {
            const newGrade = document.getElementById('swal_new_grade').value;
            const remDate  = document.getElementById('swal_rem_date').value;
            if (!newGrade || !remDate) {
                Swal.showValidationMessage('กรุณากรอกผลการเรียนและวันที่ให้ครบถ้วน');
                return false;
            }
            return { newGrade, remDate };
        }
    });

    if (!isConfirmed || !formValues) return;

    Swal.fire({
        title: 'กำลังบันทึกข้อมูล...',
        allowOutsideClick: false,
        didOpen: () => Swal.showLoading(),
    });

    try {
        const res = await fetch('/pp5/api_course.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'save_remedial',
                course_id: PP5_COURSE_ID,
                student_id: studentId,
                new_grade: formValues.newGrade,
                remedial_date: formValues.remDate,
            })
        });
        const data = await res.json();
        if (!res.ok || data.status !== 'success') {
            throw new Error(data.message || 'เกิดข้อผิดพลาดในการบันทึกผลสอบแก้ตัว');
        }

        await Swal.fire({
            icon: 'success',
            title: 'บันทึกสำเร็จ!',
            text: data.message,
            confirmButtonColor: '#4f46e5',
            confirmButtonText: 'ตกลง',
        });
        window.location.reload();
    } catch (err) {
        Swal.fire({
            icon: 'error',
            title: 'บันทึกไม่สำเร็จ',
            text: err.message,
            confirmButtonColor: '#ef4444',
        });
    }
}
</script>

<?php
require_once __DIR__ . '/../components/layout_end.php';
} catch (\Throwable $e) {
    error_log('[PP5 course_summary] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    $pageTitle = 'เกิดข้อผิดพลาดในการโหลดหน้าสรุป ปพ.5';
    $pageSubtitle = htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
    $activeSystem = 'pp5';
    require_once __DIR__ . '/../components/layout_start.php';
    ?>
    <div class="bg-rose-50 border border-rose-200 rounded-2xl p-6 text-rose-800 m-6 shadow-sm">
        <h3 class="text-base font-black flex items-center gap-2 mb-2">
            <i class="bi bi-exclamation-octagon-fill text-rose-600 text-xl"></i> ไม่สามารถโหลดหน้าสรุป ปพ.5 ได้
        </h3>
        <p class="text-xs text-rose-700 leading-relaxed font-semibold">
            ข้อผิดพลาด: <?= htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') ?>
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

