<?php
namespace PP5\App\Services;

use PDO;

/**
 * Pp5ReportService — บริการรวบรวมข้อมูลและสร้างเอกสารรายงาน ปพ.5 ขนาด A4 ครบ 5 ส่วน
 * ตามรูปแบบกระทรวงศึกษาธิการ (หลักสูตรแกนกลางการศึกษาขั้นพื้นฐาน พ.ศ. 2551)
 *
 * ครบทั้ง 5 ส่วน:
 * - ส่วนที่ 1: ปกหน้า ปพ.5 (ข้อมูลโรงเรียน รายวิชา ตารางสรุปผลการเรียน ร้อยละ และลายเซ็นอนุมัติครบทุกขั้น)
 * - ส่วนที่ 2: บันทึกเวลาเรียนรายคาบ และสรุปเวลาเรียนสะสมรายคน
 * - ส่วนที่ 3: บันทึกคะแนนตามตัวชี้วัด/ผลการเรียนรู้ (ก่อนกลางภาค, กลางภาค, หลังกลางภาค, ปลายภาค, เกรด)
 * - ส่วนที่ 4: บันทึกการประเมินคุณลักษณะอันพึงประสงค์ 8 ข้อ (ระดับ 0–3 และสรุปผลรวม)
 * - ส่วนที่ 5: บันทึกการประเมินการอ่าน คิดวิเคราะห์ และเขียน 5 ข้อ และสมรรถนะสำคัญของผู้เรียน 5 ด้าน
 */
class Pp5ReportService
{
    private PDO $pdo;
    private CourseService $courseService;
    private AttendanceService $attendanceService;
    private AssessmentService $assessmentService;
    private GradeCalculator $gradeCalc;
    private MasterDataService $masterService;

    public function __construct(PDO $pdo)
    {
        $this->pdo               = $pdo;
        $this->courseService     = new CourseService($pdo);
        $this->attendanceService = new AttendanceService($pdo);
        $this->assessmentService = new AssessmentService($pdo);
        $this->gradeCalc         = new GradeCalculator($pdo);
        $this->masterService     = new MasterDataService($pdo);
    }

    /**
     * ดึงข้อมูลครบทุกส่วนของรายวิชา ปพ.5 สำหรับแสดงผลหน้าสรุปและพิมพ์รายงาน A4 5 ส่วน
     */
    public function compileCourseReportData(int $courseId): ?array
    {
        $course = $this->courseService->getCourseById($courseId);
        if (!$course) {
            return null;
        }

        $schoolSettings = $this->masterService->getSchoolSettings();
        $students       = $this->courseService->getCourseStudents($course);
        $studentIds     = array_map(fn($s) => (int)$s['id'], $students);
        $totalStudents  = count($students);

        $scoreItems     = $this->courseService->getCourseScoreItems($courseId);
        $scoresGrid     = $this->courseService->getCourseScoresGrid($courseId);
        $gradesMap      = $this->courseService->recalculateCourseGrades($courseId);
        $indicators     = $this->masterService->getIndicators((int)$course['subject_id']);

        $sessions       = $this->attendanceService->getCourseSessions($courseId);
        $attendanceMap  = $this->attendanceService->getCourseAttendanceMap($courseId);
        $attSummaryMap  = $this->attendanceService->getCourseStudentsAttendanceSummary($courseId, $studentIds);

        $assessments    = $this->assessmentService->getAllCourseAssessments($courseId, $studentIds);
        $ratioCheck     = $this->gradeCalc->validateScoreItemsRatio(
            $scoreItems,
            (int)$course['ratio_collect'],
            (int)$course['ratio_final'],
            (string)$course['subject_type']
        );

        // ดึงประวัติการอนุมัติแต่ละขั้นของรายวิชา
        $stmtApp = $this->pdo->prepare("
            SELECT a.*, u.`prefix`, u.`firstname`, u.`lastname`, u.`position`
            FROM `pp5_approvals` a
            LEFT JOIN `llw_users` u ON u.`user_id` = a.`approver_id`
            WHERE a.`course_id` = ?
            ORDER BY a.`id` ASC
        ");
        $stmtApp->execute([$courseId]);
        $approvals = $stmtApp->fetchAll(PDO::FETCH_ASSOC);

        // คำนวณสถิติจำนวนและร้อยละของผลการเรียน
        $gradeKeys = ['4', '3.5', '3', '2.5', '2', '1.5', '1', '0', 'ร', 'มส', 'ผ', 'มผ'];
        $gradeCounts = array_fill_keys($gradeKeys, 0);
        $gradePercents = array_fill_keys($gradeKeys, 0.0);

        $remedialStudents = [];
        foreach ($students as $std) {
            $sid  = (int)$std['id'];
            $gRow = $gradesMap[$sid] ?? [];
            $g    = (string)($gRow['grade'] ?? 'ร');
            if (isset($gradeCounts[$g])) {
                $gradeCounts[$g]++;
            }
            if (in_array($g, ['0', 'ร', 'มส', 'มผ'], true)) {
                $remedialStudents[] = [
                    'student' => $std,
                    'grade'   => $gRow,
                ];
            }
        }

        if ($totalStudents > 0) {
            foreach ($gradeCounts as $k => $cnt) {
                $gradePercents[$k] = round(($cnt / $totalStudents) * 100.0, 2);
            }
        }

        // ตรวจสอบความครบถ้วนของเล่ม ปพ.5 ทั้ง 5 ส่วน (Readiness Check)
        $hasSessions      = count($sessions) > 0;
        $hasScoreItems    = count($scoreItems) > 0;
        $missingScoreStds = $gradeCounts['ร'];
        $charIncomplete   = (int)($assessments['characteristic']['dist']['incomplete'] ?? 0);
        $rtwIncomplete    = (int)($assessments['rtw']['dist']['incomplete'] ?? 0);
        $compIncomplete   = (int)($assessments['competency']['dist']['incomplete'] ?? 0);

        $readiness = [
            'ratio_ok'          => (bool)$ratioCheck['is_valid'] && $hasScoreItems,
            'attendance_ok'     => $hasSessions,
            'scores_ok'         => $hasScoreItems && $missingScoreStds === 0,
            'characteristic_ok' => $totalStudents > 0 && $charIncomplete === 0,
            'rtw_ok'            => $totalStudents > 0 && $rtwIncomplete === 0,
            'competency_ok'     => $totalStudents > 0 && $compIncomplete === 0,
        ];
        $readiness['all_ready'] = !in_array(false, $readiness, true);

        return [
            'course'           => $course,
            'school'           => $schoolSettings,
            'students'         => $students,
            'total_students'   => $totalStudents,
            'score_items'      => $scoreItems,
            'scores_grid'      => $scoresGrid,
            'grades_map'       => $gradesMap,
            'indicators'       => $indicators,
            'sessions'         => $sessions,
            'attendance_map'   => $attendanceMap,
            'att_summary_map'  => $attSummaryMap,
            'assessments'      => $assessments,
            'ratio_check'      => $ratioCheck,
            'approvals'        => $approvals,
            'grade_counts'     => $gradeCounts,
            'grade_percents'   => $gradePercents,
            'remedial_list'    => $remedialStudents,
            'readiness'        => $readiness,
        ];
    }

    /**
     * สร้าง HTML ฉบับสมบูรณ์สำหรับพิมพ์/ส่งออก PDF รายงาน ปพ.5 ขนาด A4 ครบ 5 ส่วน
     * ใช้ฟอนต์ TH Sarabun New / Sarabun ที่แสดงสระและวรรณยุกต์ภาษาไทยถูกต้อง
     *
     * @param array $data ข้อมูลที่ได้จาก compileCourseReportData()
     * @param array $sections รายการส่วนที่ต้องการแสดง เช่น [1, 2, 3, 4, 5]
     * @param bool  $standalone หากเป็น true จะรวมชุดคำสั่งเปิด/ปิด <html><head> สำหรับดาวน์โหลดหรือสร้างไฟล์เดี่ยว
     */
    public function renderReportHtml(array $data, array $sections = [1, 2, 3, 4, 5], bool $standalone = true): string
    {
        $course        = $data['course'];
        $school        = $data['school'];
        $students      = $data['students'];
        $totalStudents = (int)$data['total_students'];
        $scoreItems    = $data['score_items'];
        $scoresGrid    = $data['scores_grid'];
        $gradesMap     = $data['grades_map'];
        $indicators    = $data['indicators'];
        $sessions      = $data['sessions'];
        $attendanceMap = $data['attendance_map'];
        $attSummaryMap = $data['att_summary_map'];
        $assessments   = $data['assessments'];
        $gradeCounts   = $data['grade_counts'];
        $gradePercents = $data['grade_percents'];

        $teacherFullName = trim(($course['teacher_prefix'] ?? '') . ($course['teacher_first_name'] ?? '') . ' ' . ($course['teacher_last_name'] ?? ''));
        if ($teacherFullName === '') {
            $teacherFullName = '.......................................................';
        }
        $headTeacherName = trim(($course['head_prefix'] ?? '') . ($course['head_first_name'] ?? '') . ' ' . ($course['head_last_name'] ?? ''));
        if ($headTeacherName === '') {
            $headTeacherName = '.......................................................';
        }
        $advisorFullName = trim(($course['advisor_prefix'] ?? '') . ($course['advisor_first_name'] ?? '') . ' ' . ($course['advisor_last_name'] ?? ''));
        if ($advisorFullName === '') {
            $advisorFullName = '.......................................................';
        }

        // แยกรายการคะแนนตามช่วง
        $itemsByPhase = [
            'before_mid' => [],
            'midterm'    => [],
            'after_mid'  => [],
            'final'      => [],
        ];
        foreach ($scoreItems as $si) {
            $ph = $si['phase'] ?? 'before_mid';
            if (isset($itemsByPhase[$ph])) {
                $itemsByPhase[$ph][] = $si;
            }
        }
        $orderedItems = array_merge(
            $itemsByPhase['before_mid'],
            $itemsByPhase['midterm'],
            $itemsByPhase['after_mid'],
            $itemsByPhase['final']
        );

        ob_start();
        if ($standalone):
        ?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>ปพ.5 — <?= htmlspecialchars($course['subject_code'] . ' ' . $course['subject_name'] . ' (' . $course['classroom_name'] . ')', ENT_QUOTES, 'UTF-8') ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        @page {
            size: A4 portrait;
            margin: 12mm 12mm 12mm 15mm;
        }
        * {
            box-sizing: border-box;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }
        body {
            font-family: 'TH Sarabun New', 'THSarabunNew', 'Sarabun', sans-serif;
            font-size: 14pt;
            line-height: 1.35;
            color: #111827;
            margin: 0;
            padding: 0;
            background: #f1f5f9;
        }
        .pp5-page {
            width: 210mm;
            min-height: 297mm;
            margin: 8mm auto;
            padding: 12mm 14mm;
            background: #ffffff;
            box-shadow: 0 4px 20px rgba(15, 23, 42, 0.12);
            page-break-after: always;
            position: relative;
        }
        .pp5-page:last-child {
            page-break-after: auto;
        }
        @media print {
            body {
                background: #ffffff;
            }
            .pp5-page {
                width: 100%;
                min-height: auto;
                margin: 0;
                padding: 0;
                box-shadow: none;
            }
            .no-print {
                display: none !important;
            }
        }
        table.pp5-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 11.5pt;
        }
        table.pp5-table th,
        table.pp5-table td {
            border: 1px solid #1e293b;
            padding: 3px 5px;
            vertical-align: middle;
        }
        table.pp5-table th {
            background-color: #f8fafc;
            font-weight: 700;
            text-align: center;
        }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .text-left { text-align: left; }
        .font-bold { font-weight: 700; }
        .section-badge {
            display: inline-block;
            border: 1.5px solid #1e293b;
            padding: 2px 10px;
            font-weight: 700;
            font-size: 13pt;
            border-radius: 4px;
        }
    </style>
</head>
<body>
        <?php endif; ?>

        <?php if (in_array(1, $sections, true)): ?>
        <!-- =========================================================================
             ส่วนที่ 1: ปกหน้า ปพ.5 (สรุปผลการเรียนและลายเซ็นอนุมัติครบทุกขั้น)
             ========================================================================= -->
        <div class="pp5-page">
            <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:6px;">
                <div style="font-size:11pt; color:#334155;">
                    แบบบันทึกผลการพัฒนาคุณภาพผู้เรียนรายวิชา<br>
                    ตามหลักสูตรแกนกลางการศึกษาขั้นพื้นฐาน พุทธศักราช 2551
                </div>
                <div class="section-badge">ปพ.5 (ส่วนที่ 1 : ปกหน้า)</div>
            </div>

            <div class="text-center" style="margin-bottom:10px;">
                <div style="font-size:18pt; font-weight:700; line-height:1.2;">
                    <?= htmlspecialchars($school['school_name'] ?? 'โรงเรียนละลมวิทยา', ENT_QUOTES, 'UTF-8') ?>
                </div>
                <div style="font-size:13pt;">
                    <?= htmlspecialchars($school['affiliation'] ?? 'สำนักงานเขตพื้นที่การศึกษามัธยมศึกษาศรีสะเกษ ยโสธร', ENT_QUOTES, 'UTF-8') ?>
                </div>
                <div style="font-size:15pt; font-weight:700; margin-top:4px;">
                    แบบบันทึกผลการพัฒนาคุณภาพผู้เรียน (ปพ.5) ประจำภาคเรียนที่ <?= (int)$course['semester'] ?> ปีการศึกษา <?= (int)$course['academic_year'] ?>
                </div>
            </div>

            <!-- ข้อมูลรายวิชา -->
            <table class="pp5-table" style="margin-bottom:10px; font-size:12.5pt;">
                <tr>
                    <td style="width:50%;">
                        <strong>รหัสวิชา:</strong> <?= htmlspecialchars($course['subject_code'], ENT_QUOTES, 'UTF-8') ?>
                        &nbsp;&nbsp;<strong>ชื่อรายวิชา:</strong> <?= htmlspecialchars($course['subject_name'], ENT_QUOTES, 'UTF-8') ?>
                    </td>
                    <td style="width:50%;">
                        <strong>กลุ่มสาระการเรียนรู้:</strong> <?= htmlspecialchars($course['department_name'] ?? '-', ENT_QUOTES, 'UTF-8') ?>
                    </td>
                </tr>
                <tr>
                    <td>
                        <strong>ระดับชั้นมัธยมศึกษาปีที่:</strong> <?= (int)$course['grade_level'] ?>
                        &nbsp;&nbsp;<strong>ห้องเรียน:</strong> <?= htmlspecialchars($course['classroom_name'], ENT_QUOTES, 'UTF-8') ?>
                        &nbsp;&nbsp;<strong>จำนวนนักเรียน:</strong> <?= $totalStudents ?> คน
                    </td>
                    <td>
                        <strong>ประเภทวิชา:</strong>
                        <?= $course['subject_type'] === 'basic' ? 'รายวิชาพื้นฐาน' : ($course['subject_type'] === 'additional' ? 'รายวิชาเพิ่มเติม' : 'กิจกรรมพัฒนาผู้เรียน') ?>
                        &nbsp;&nbsp;<strong>หน่วยกิต:</strong> <?= number_format((float)$course['credit'], 1) ?> นก.
                        &nbsp;(<?= (int)$course['total_hours'] ?> ชั่วโมง/ภาคเรียน)
                    </td>
                </tr>
                <tr>
                    <td>
                        <strong>ครูผู้สอน:</strong> <?= htmlspecialchars($teacherFullName, ENT_QUOTES, 'UTF-8') ?>
                    </td>
                    <td>
                        <strong>ครูที่ปรึกษา:</strong> <?= htmlspecialchars($advisorFullName, ENT_QUOTES, 'UTF-8') ?>
                        &nbsp;&nbsp;<strong>สัดส่วนคะแนน:</strong>
                        <?= $course['subject_type'] === 'activity' ? 'ผ่าน (ผ) / ไม่ผ่าน (มผ)' : ((int)$course['ratio_collect'] . ' : ' . (int)$course['ratio_final']) ?>
                    </td>
                </tr>
            </table>

            <!-- ตารางที่ 1.1: สรุปจำนวนและร้อยละของระดับผลการเรียน -->
            <div style="font-weight:700; font-size:13pt; margin-bottom:3px;">1. สรุปผลการประเมินความรู้ความสามารถ (ระดับผลการเรียน)</div>
            <table class="pp5-table" style="margin-bottom:10px;">
                <thead>
                    <tr>
                        <th rowspan="2" style="width:14%;">จำนวนนักเรียนทั้งหมด</th>
                        <th colspan="8">ระดับผลการเรียน (จำนวนคน / ร้อยละ)</th>
                        <th colspan="2">ไม่ตัดสินผล</th>
                        <th colspan="2">กิจกรรมฯ</th>
                    </tr>
                    <tr>
                        <th>4</th>
                        <th>3.5</th>
                        <th>3</th>
                        <th>2.5</th>
                        <th>2</th>
                        <th>1.5</th>
                        <th>1</th>
                        <th>0</th>
                        <th>ร</th>
                        <th>มส</th>
                        <th>ผ</th>
                        <th>มผ</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="text-center">
                        <td class="font-bold"><?= $totalStudents ?> คน</td>
                        <?php foreach (['4', '3.5', '3', '2.5', '2', '1.5', '1', '0', 'ร', 'มส', 'ผ', 'มผ'] as $gk): ?>
                            <td class="font-bold"><?= (int)($gradeCounts[$gk] ?? 0) ?></td>
                        <?php endforeach; ?>
                    </tr>
                    <tr class="text-center">
                        <td>ร้อยละ (%)</td>
                        <?php foreach (['4', '3.5', '3', '2.5', '2', '1.5', '1', '0', 'ร', 'มส', 'ผ', 'มผ'] as $gk): ?>
                            <td><?= number_format((float)($gradePercents[$gk] ?? 0), 1) ?></td>
                        <?php endforeach; ?>
                    </tr>
                </tbody>
            </table>

            <!-- ตารางที่ 1.2: สรุปผลคุณลักษณะอันพึงประสงค์ อ่านคิดวิเคราะห์เขียน และสมรรถนะสำคัญ -->
            <div style="font-weight:700; font-size:13pt; margin-bottom:3px;">2. สรุปผลการประเมินคุณลักษณะอันพึงประสงค์ การอ่าน คิดวิเคราะห์ และเขียน และสมรรถนะสำคัญของผู้เรียน</div>
            <table class="pp5-table" style="margin-bottom:14px;">
                <thead>
                    <tr>
                        <th style="width:36%;">รายการประเมิน</th>
                        <th>จำนวนนักเรียน</th>
                        <th>ระดับ 3 (ดีเยี่ยม)</th>
                        <th>ระดับ 2 (ดี)</th>
                        <th>ระดับ 1 (ผ่าน)</th>
                        <th>ระดับ 0 (ไม่ผ่าน)</th>
                        <th>คิดเป็นร้อยละระดับดีขึ้นไป (2–3)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $assRows = [
                        'characteristic' => '1. คุณลักษณะอันพึงประสงค์ (8 ข้อ)',
                        'rtw'            => '2. การอ่าน คิดวิเคราะห์ และเขียน (5 ข้อ)',
                        'competency'     => '3. สมรรถนะสำคัญของผู้เรียน (5 ด้าน)',
                    ];
                    foreach ($assRows as $aKey => $aLabel):
                        $d = $assessments[$aKey]['dist'] ?? [3 => 0, 2 => 0, 1 => 0, 0 => 0];
                        $goodPlusPct = $totalStudents > 0 ? round(((($d[3] ?? 0) + ($d[2] ?? 0)) / $totalStudents) * 100.0, 2) : 0.0;
                    ?>
                        <tr class="text-center">
                            <td class="text-left font-bold"><?= $aLabel ?></td>
                            <td><?= $totalStudents ?></td>
                            <td><?= (int)($d[3] ?? 0) ?> (<?= $totalStudents > 0 ? number_format(($d[3] / $totalStudents) * 100, 1) : '0.0' ?>%)</td>
                            <td><?= (int)($d[2] ?? 0) ?> (<?= $totalStudents > 0 ? number_format(($d[2] / $totalStudents) * 100, 1) : '0.0' ?>%)</td>
                            <td><?= (int)($d[1] ?? 0) ?> (<?= $totalStudents > 0 ? number_format(($d[1] / $totalStudents) * 100, 1) : '0.0' ?>%)</td>
                            <td><?= (int)($d[0] ?? 0) ?> (<?= $totalStudents > 0 ? number_format(($d[0] / $totalStudents) * 100, 1) : '0.0' ?>%)</td>
                            <td class="font-bold"><?= number_format($goodPlusPct, 2) ?>%</td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <!-- ส่วนที่ 1.3: การตรวจสอบและอนุมัติผลการเรียนตามลำดับขั้น -->
            <div style="font-weight:700; font-size:13pt; margin-bottom:4px;">3. การตรวจสอบและอนุมัติผลการพัฒนาคุณภาพผู้เรียน</div>
            <table class="pp5-table" style="font-size:12pt;">
                <tr>
                    <td style="width:50%; padding:10px 12px; vertical-align:top;">
                        <div class="font-bold text-center" style="margin-bottom:18px;">1. ครูผู้สอน</div>
                        <div class="text-center">
                            ลงชื่อ ...........................................................................<br>
                            ( <?= htmlspecialchars($teacherFullName, ENT_QUOTES, 'UTF-8') ?> )<br>
                            ครูผู้สอนรายวิชา <?= htmlspecialchars($course['subject_code'], ENT_QUOTES, 'UTF-8') ?><br>
                            วันที่ <?= $course['submitted_at'] ? pp5_thai_date($course['submitted_at']) : '........./..................../.............' ?>
                        </div>
                    </td>
                    <td style="width:50%; padding:10px 12px; vertical-align:top;">
                        <div class="font-bold text-center" style="margin-bottom:18px;">2. หัวหน้ากลุ่มสาระการเรียนรู้</div>
                        <div class="text-center">
                            [ <?= in_array($course['status'], ['head_approved', 'deputy_approved', 'approved', 'locked'], true) ? '✓' : ' ' ?> ] ตรวจสอบแล้วถูกต้องครบถ้วน<br><br>
                            ลงชื่อ ...........................................................................<br>
                            ( <?= htmlspecialchars($headTeacherName, ENT_QUOTES, 'UTF-8') ?> )<br>
                            หัวหน้า<?= htmlspecialchars($course['department_name'] ?? 'กลุ่มสาระฯ', ENT_QUOTES, 'UTF-8') ?><br>
                            วันที่ <?= $course['head_approved_at'] ? pp5_thai_date($course['head_approved_at']) : '........./..................../.............' ?>
                        </div>
                    </td>
                </tr>
                <tr>
                    <td style="padding:10px 12px; vertical-align:top;">
                        <div class="font-bold text-center" style="margin-bottom:14px;">3. งานทะเบียนและวัดผล / รองผู้อำนวยการฝ่ายวิชาการ</div>
                        <div class="text-center">
                            [ <?= in_array($course['status'], ['deputy_approved', 'approved', 'locked'], true) ? '✓' : ' ' ?> ] ตรวจสอบแล้วเห็นควรอนุมัติ<br><br>
                            ลงชื่อ ...........................................................................<br>
                            ( <?= htmlspecialchars($school['deputy_academic_name'] ?? '.......................................................', ENT_QUOTES, 'UTF-8') ?> )<br>
                            <?= htmlspecialchars($school['deputy_academic_position'] ?? 'รองผู้อำนวยการกลุ่มบริหารวิชาการ', ENT_QUOTES, 'UTF-8') ?><br>
                            วันที่ <?= $course['deputy_approved_at'] ? pp5_thai_date($course['deputy_approved_at']) : '........./..................../.............' ?>
                        </div>
                    </td>
                    <td style="padding:10px 12px; vertical-align:top;">
                        <div class="font-bold text-center" style="margin-bottom:14px;">4. คำสั่งผู้อำนวยการสถานศึกษา</div>
                        <div class="text-center">
                            [ <?= in_array($course['status'], ['approved', 'locked'], true) ? '✓' : ' ' ?> ] อนุมัติผลการเรียน &nbsp;&nbsp; [ &nbsp; ] ไม่อนุมัติ<br><br>
                            ลงชื่อ ...........................................................................<br>
                            ( <?= htmlspecialchars($school['director_name'] ?? '.......................................................', ENT_QUOTES, 'UTF-8') ?> )<br>
                            <?= htmlspecialchars($school['director_position'] ?? 'ผู้อำนวยการโรงเรียนละลมวิทยา', ENT_QUOTES, 'UTF-8') ?><br>
                            วันที่ <?= (!empty($course['approved_at']) || !empty($course['director_approved_at'])) ? pp5_thai_date($course['approved_at'] ?? $course['director_approved_at']) : '........./..................../.............' ?>
                        </div>
                    </td>
                </tr>
            </table>
        </div>
        <?php endif; ?>

        <?php if (in_array(2, $sections, true)): ?>
        <!-- =========================================================================
             ส่วนที่ 2: บันทึกเวลาเรียนรายคาบ และสรุปเวลาเรียนสะสมรายคน
             ========================================================================= -->
        <div class="pp5-page">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                <div>
                    <strong style="font-size:15pt;">ส่วนที่ 2 : บันทึกเวลาเรียนรายวิชา <?= htmlspecialchars($course['subject_code'] . ' ' . $course['subject_name'], ENT_QUOTES, 'UTF-8') ?></strong><br>
                    <span>ชั้นมัธยมศึกษาปีที่ <?= htmlspecialchars($course['classroom_name'], ENT_QUOTES, 'UTF-8') ?> • ภาคเรียนที่ <?= (int)$course['semester'] ?>/<?= (int)$course['academic_year'] ?> • เวลาเรียนตามโครงสร้าง <?= (int)$course['total_hours'] ?> ชั่วโมง</span>
                </div>
                <div class="section-badge">ปพ.5 (ส่วนที่ 2 : เวลาเรียน)</div>
            </div>

            <?php
            // แสดงคาบเรียนสูงสุด 18 คาบล่าสุดบนหน้าเดียวพร้อมสรุปสะสมครบทุกคาบ
            $displaySessions = array_slice($sessions, 0, 18);
            $statusSymMap = [
                'present' => '/',
                'late'    => 'ส',
                'leave'   => 'ล',
                'sick'    => 'ป',
                'absent'  => 'ข',
            ];
            ?>
            <table class="pp5-table" style="font-size:10.5pt;">
                <thead>
                    <tr>
                        <th rowspan="2" style="width:32px;">เลขที่</th>
                        <th rowspan="2" style="width:68px;">รหัส</th>
                        <th rowspan="2" style="min-width:140px;">ชื่อ - นามสกุล</th>
                        <?php if (!empty($displaySessions)): ?>
                            <th colspan="<?= count($displaySessions) ?>">บันทึกการมาเรียนรายคาบ (/ = มา, ส = สาย, ล = ลากิจ, ป = ป่วย, ข = ขาด)</th>
                        <?php endif; ?>
                        <th colspan="6">สรุปเวลาเรียนสะสม (ชั่วโมง)</th>
                    </tr>
                    <tr>
                        <?php foreach ($displaySessions as $idxS => $ss): ?>
                            <th style="font-size:8.5pt; width:22px; padding:2px 1px;">
                                <?= date('d/m', strtotime($ss['session_date'])) ?><br>ค.<?= (int)$ss['period'] ?>
                            </th>
                        <?php endforeach; ?>
                        <th style="width:32px;">มา</th>
                        <th style="width:32px;">สาย</th>
                        <th style="width:32px;">ลา/ป่วย</th>
                        <th style="width:32px;">ขาด</th>
                        <th style="width:45px;">ร้อยละ</th>
                        <th style="width:38px;">สิทธิ์</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($students as $std):
                        $sid = (int)$std['id'];
                        $attSum = $attSummaryMap[$sid] ?? [
                            'present_hours' => 0, 'late_hours' => 0, 'leave_hours' => 0, 'sick_hours' => 0,
                            'absent_hours' => 0, 'attendance_percent' => 100.0, 'is_ms' => false,
                        ];
                    ?>
                        <tr class="text-center">
                            <td><?= (int)$std['student_number'] ?></td>
                            <td><?= htmlspecialchars($std['student_id'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="text-left"><?= htmlspecialchars($std['name'], ENT_QUOTES, 'UTF-8') ?></td>
                            <?php foreach ($displaySessions as $ss):
                                $ssId = (int)$ss['id'];
                                $stCode = $attendanceMap[$ssId][$sid] ?? 'present';
                                $sym = $statusSymMap[$stCode] ?? '/';
                            ?>
                                <td style="<?= $stCode === 'absent' ? 'color:#dc2626; font-weight:700;' : '' ?>"><?= $sym ?></td>
                            <?php endforeach; ?>
                            <td><?= $attSum['present_hours'] ?></td>
                            <td><?= $attSum['late_hours'] ?></td>
                            <td><?= $attSum['leave_hours'] + $attSum['sick_hours'] ?></td>
                            <td style="<?= $attSum['absent_hours'] > 0 ? 'color:#dc2626; font-weight:700;' : '' ?>"><?= $attSum['absent_hours'] ?></td>
                            <td class="font-bold"><?= number_format((float)$attSum['attendance_percent'], 1) ?>%</td>
                            <td class="font-bold" style="<?= !empty($attSum['is_ms']) ? 'color:#dc2626;' : '' ?>">
                                <?= !empty($attSum['is_ms']) ? 'มส' : 'ปกติ' ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <?php if (in_array(3, $sections, true)): ?>
        <!-- =========================================================================
             ส่วนที่ 3: บันทึกคะแนนตามตัวชี้วัด/ผลการเรียนรู้ และตัดสินผลการเรียน
             ========================================================================= -->
        <div class="pp5-page">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                <div>
                    <strong style="font-size:15pt;">ส่วนที่ 3 : บันทึกคะแนนรายตัวชี้วัดและผลการเรียน วิชา <?= htmlspecialchars($course['subject_code'] . ' ' . $course['subject_name'], ENT_QUOTES, 'UTF-8') ?></strong><br>
                    <span>ชั้นมัธยมศึกษาปีที่ <?= htmlspecialchars($course['classroom_name'], ENT_QUOTES, 'UTF-8') ?> • สัดส่วนคะแนน <?= (int)$course['ratio_collect'] ?> : <?= (int)$course['ratio_final'] ?> • ครูผู้สอน <?= htmlspecialchars($teacherFullName, ENT_QUOTES, 'UTF-8') ?></span>
                </div>
                <div class="section-badge">ปพ.5 (ส่วนที่ 3 : คะแนนและเกรด)</div>
            </div>

            <?php if (!empty($indicators)): ?>
                <div style="border:1px solid #cbd5e1; padding:4px 8px; margin-bottom:8px; font-size:10.5pt; background:#f8fafc;">
                    <strong>ตัวชี้วัด / ผลการเรียนรู้ประจำรายวิชา:</strong>
                    <?php
                    $indParts = [];
                    foreach ($indicators as $ind) {
                        $indParts[] = htmlspecialchars($ind['code'] . ' ' . $ind['description'], ENT_QUOTES, 'UTF-8');
                    }
                    echo implode(' | ', $indParts);
                    ?>
                </div>
            <?php endif; ?>

            <table class="pp5-table" style="font-size:10.5pt;">
                <thead>
                    <tr>
                        <th rowspan="2" style="width:32px;">เลขที่</th>
                        <th rowspan="2" style="width:68px;">รหัส</th>
                        <th rowspan="2" style="min-width:140px;">ชื่อ - นามสกุล</th>
                        <?php foreach ($orderedItems as $item): ?>
                            <th style="font-size:9.5pt;">
                                <?= htmlspecialchars(mb_strimwidth($item['name'], 0, 16, '..'), ENT_QUOTES, 'UTF-8') ?>
                                <?php if (!empty($item['indicator_code'])): ?>
                                    <br><span style="font-weight:400; font-size:8.5pt;">(<?= htmlspecialchars($item['indicator_code'], ENT_QUOTES, 'UTF-8') ?>)</span>
                                <?php endif; ?>
                            </th>
                        <?php endforeach; ?>
                        <th>รวมเก็บ<br>(<?= (int)$course['ratio_collect'] ?>)</th>
                        <th>ปลายภาค<br>(<?= (int)$course['ratio_final'] ?>)</th>
                        <th>รวมสุทธิ<br>(100)</th>
                        <th>เวลาเรียน<br>(%)</th>
                        <th>เกรด</th>
                        <th>หมายเหตุ</th>
                    </tr>
                    <tr>
                        <?php foreach ($orderedItems as $item): ?>
                            <th style="font-size:9pt;">เต็ม <?= (float)$item['max_score'] ?></th>
                        <?php endforeach; ?>
                        <th colspan="6">เกณฑ์ตัดสิน: ปัดเศษครึ่งขึ้นที่คะแนนรวมสุทธิ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($students as $std):
                        $sid  = (int)$std['id'];
                        $gRow = $gradesMap[$sid] ?? [
                            'score_collect' => 0, 'score_final' => 0, 'total_score' => 0,
                            'attendance_percent' => 100.0, 'grade' => 'ร', 'original_grade' => null, 'is_remedial' => 0,
                        ];
                    ?>
                        <tr class="text-center">
                            <td><?= (int)$std['student_number'] ?></td>
                            <td><?= htmlspecialchars($std['student_id'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="text-left"><?= htmlspecialchars($std['name'], ENT_QUOTES, 'UTF-8') ?></td>
                            <?php foreach ($orderedItems as $item):
                                $sc = $scoresGrid[$sid][(int)$item['id']] ?? null;
                            ?>
                                <td><?= $sc === null ? 'ร' : (float)$sc ?></td>
                            <?php endforeach; ?>
                            <td class="font-bold"><?= (float)$gRow['score_collect'] ?></td>
                            <td class="font-bold"><?= (float)$gRow['score_final'] ?></td>
                            <td class="font-bold"><?= (int)$gRow['total_score'] ?></td>
                            <td><?= number_format((float)$gRow['attendance_percent'], 1) ?>%</td>
                            <td class="font-bold" style="<?= in_array((string)$gRow['grade'], ['0', 'ร', 'มส', 'มผ'], true) ? 'color:#dc2626;' : '' ?>">
                                <?= htmlspecialchars((string)$gRow['grade'], ENT_QUOTES, 'UTF-8') ?>
                            </td>
                            <td style="font-size:9.5pt;">
                                <?= !empty($gRow['is_remedial']) && !empty($gRow['original_grade']) ? 'แก้ ' . htmlspecialchars((string)$gRow['original_grade'], ENT_QUOTES, 'UTF-8') : '' ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <?php if (in_array(4, $sections, true)): ?>
        <!-- =========================================================================
             ส่วนที่ 4: บันทึกการประเมินคุณลักษณะอันพึงประสงค์ 8 ข้อ (ระดับ 0–3)
             ========================================================================= -->
        <div class="pp5-page">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                <div>
                    <strong style="font-size:15pt;">ส่วนที่ 4 : บันทึกการประเมินคุณลักษณะอันพึงประสงค์ 8 ข้อ วิชา <?= htmlspecialchars($course['subject_code'] . ' ' . $course['subject_name'], ENT_QUOTES, 'UTF-8') ?></strong><br>
                    <span>ชั้นมัธยมศึกษาปีที่ <?= htmlspecialchars($course['classroom_name'], ENT_QUOTES, 'UTF-8') ?> • ระดับคุณภาพ: 3 = ดีเยี่ยม, 2 = ดี, 1 = ผ่าน, 0 = ไม่ผ่าน</span>
                </div>
                <div class="section-badge">ปพ.5 (ส่วนที่ 4 : คุณลักษณะฯ)</div>
            </div>

            <table class="pp5-table" style="font-size:10.5pt;">
                <thead>
                    <tr>
                        <th style="width:32px;">เลขที่</th>
                        <th style="width:68px;">รหัส</th>
                        <th style="min-width:150px;">ชื่อ - นามสกุล</th>
                        <?php foreach (AssessmentService::CHARACTERISTIC_ITEMS as $itemNo => $meta): ?>
                            <th style="font-size:9.5pt; width:56px;"><?= htmlspecialchars($meta['short'], ENT_QUOTES, 'UTF-8') ?></th>
                        <?php endforeach; ?>
                        <th style="width:55px;">สรุประดับ</th>
                        <th style="width:75px;">ผลการประเมิน</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $charGrid = $assessments['characteristic']['grid'] ?? [];
                    $charSum  = $assessments['characteristic']['summaries'] ?? [];
                    foreach ($students as $std):
                        $sid = (int)$std['id'];
                        $sInfo = $charSum[$sid] ?? ['level' => null, 'text' => '-'];
                    ?>
                        <tr class="text-center">
                            <td><?= (int)$std['student_number'] ?></td>
                            <td><?= htmlspecialchars($std['student_id'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="text-left"><?= htmlspecialchars($std['name'], ENT_QUOTES, 'UTF-8') ?></td>
                            <?php foreach (array_keys(AssessmentService::CHARACTERISTIC_ITEMS) as $itemNo):
                                $lv = $charGrid[$sid][$itemNo] ?? null;
                            ?>
                                <td><?= $lv === null ? '-' : (int)$lv ?></td>
                            <?php endforeach; ?>
                            <td class="font-bold"><?= $sInfo['level'] === null ? '-' : (int)$sInfo['level'] ?></td>
                            <td class="font-bold"><?= htmlspecialchars($sInfo['text'], ENT_QUOTES, 'UTF-8') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <?php if (in_array(5, $sections, true)): ?>
        <!-- =========================================================================
             ส่วนที่ 5: บันทึกการประเมินการอ่าน คิดวิเคราะห์ และเขียน (5 ข้อ) และสมรรถนะสำคัญ (5 ด้าน)
             ========================================================================= -->
        <div class="pp5-page">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                <div>
                    <strong style="font-size:15pt;">ส่วนที่ 5 : บันทึกการอ่าน คิดวิเคราะห์ และเขียน และสมรรถนะสำคัญของผู้เรียน</strong><br>
                    <span>วิชา <?= htmlspecialchars($course['subject_code'] . ' ' . $course['subject_name'], ENT_QUOTES, 'UTF-8') ?> • ชั้นมัธยมศึกษาปีที่ <?= htmlspecialchars($course['classroom_name'], ENT_QUOTES, 'UTF-8') ?> • ระดับคุณภาพ: 3 = ดีเยี่ยม, 2 = ดี, 1 = ผ่าน, 0 = ไม่ผ่าน</span>
                </div>
                <div class="section-badge">ปพ.5 (ส่วนที่ 5 : อ่านคิดเขียน & สมรรถนะ)</div>
            </div>

            <table class="pp5-table" style="font-size:10pt;">
                <thead>
                    <tr>
                        <th rowspan="2" style="width:30px;">เลขที่</th>
                        <th rowspan="2" style="width:64px;">รหัส</th>
                        <th rowspan="2" style="min-width:135px;">ชื่อ - นามสกุล</th>
                        <th colspan="6">การอ่าน คิดวิเคราะห์ และเขียน (5 ข้อ)</th>
                        <th colspan="6">สมรรถนะสำคัญของผู้เรียน (5 ด้าน)</th>
                    </tr>
                    <tr>
                        <th>ข้อ 1</th>
                        <th>ข้อ 2</th>
                        <th>ข้อ 3</th>
                        <th>ข้อ 4</th>
                        <th>ข้อ 5</th>
                        <th>สรุป</th>
                        <th>1.สื่อสาร</th>
                        <th>2.การคิด</th>
                        <th>3.แก้ปัญหา</th>
                        <th>4.ทักษะชีวิต</th>
                        <th>5.เทคโนโลยี</th>
                        <th>สรุป</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $rtwGrid  = $assessments['rtw']['grid'] ?? [];
                    $rtwSum   = $assessments['rtw']['summaries'] ?? [];
                    $compGrid = $assessments['competency']['grid'] ?? [];
                    $compSum  = $assessments['competency']['summaries'] ?? [];
                    foreach ($students as $std):
                        $sid = (int)$std['id'];
                        $rInfo = $rtwSum[$sid] ?? ['level' => null, 'text' => '-'];
                        $cInfo = $compSum[$sid] ?? ['level' => null, 'text' => '-'];
                    ?>
                        <tr class="text-center">
                            <td><?= (int)$std['student_number'] ?></td>
                            <td><?= htmlspecialchars($std['student_id'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="text-left"><?= htmlspecialchars($std['name'], ENT_QUOTES, 'UTF-8') ?></td>
                            <?php for ($i = 1; $i <= 5; $i++):
                                $lv = $rtwGrid[$sid][$i] ?? null;
                            ?>
                                <td><?= $lv === null ? '-' : (int)$lv ?></td>
                            <?php endfor; ?>
                            <td class="font-bold"><?= $rInfo['level'] === null ? '-' : ((int)$rInfo['level'] . ' (' . $rInfo['text'] . ')') ?></td>
                            <?php for ($i = 1; $i <= 5; $i++):
                                $lv = $compGrid[$sid][$i] ?? null;
                            ?>
                                <td><?= $lv === null ? '-' : (int)$lv ?></td>
                            <?php endfor; ?>
                            <td class="font-bold"><?= $cInfo['level'] === null ? '-' : ((int)$cInfo['level'] . ' (' . $cInfo['text'] . ')') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <?php if ($standalone): ?>
</body>
</html>
        <?php
        endif;
        return ob_get_clean();
    }

    /**
     * สร้างไฟล์ .zip รวมเล่มรายงาน ปพ.5 (ครบทั้ง 5 ส่วน) ของหลายรายวิชาพร้อมกัน
     *
     * @param int[] $courseIds รายการ course_id ที่ต้องการรวมในไฟล์ ZIP
     * @return string|null คืน path ของไฟล์ .zip ชั่วคราว หรือ null หากไม่สำเร็จ
     */
    public function createBatchZipArchive(array $courseIds): ?string
    {
        if (!class_exists('\ZipArchive') || empty($courseIds)) {
            return null;
        }

        $tmpZip = tempnam(sys_get_temp_dir(), 'pp5_batch_') . '.zip';
        $zip    = new \ZipArchive();
        if ($zip->open($tmpZip, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            return null;
        }

        $added = 0;
        foreach ($courseIds as $cid) {
            $cid  = (int)$cid;
            $data = $this->compileCourseReportData($cid);
            if (!$data) {
                continue;
            }
            $course   = $data['course'];
            $safeCode = preg_replace('/[^a-zA-Z0-9ก-๙_\-]/u', '_', (string)$course['subject_code']);
            $safeRoom = str_replace('/', '-', (string)$course['classroom_name']);
            $html     = $this->renderReportHtml($data, [1, 2, 3, 4, 5], true);

            $fileName = "PP5_{$course['academic_year']}_{$course['semester']}_{$safeCode}_ห้อง_{$safeRoom}.html";
            $zip->addFromString($fileName, $html);
            $added++;
        }

        $zip->close();

        if ($added === 0 && file_exists($tmpZip)) {
            @unlink($tmpZip);
            return null;
        }

        return $tmpZip;
    }
}
