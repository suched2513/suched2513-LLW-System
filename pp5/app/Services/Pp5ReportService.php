<?php
namespace PP5\App\Services;

use PDO;

/**
 * Pp5ReportService — บริการรวบรวมข้อมูลและสร้างเอกสารรายงาน ปพ.5 ขนาด A4 ครบทุกส่วน
 * ออกแบบตามแบบฟอร์มทางการของโรงเรียนละลมวิทยา 100%:
 * - ชีต "ปกน(p)": แบบบันทึกผลการพัฒนาคุณภาพผู้เรียน (ปพ.5-พ) A4 แนวนอน
 * - ชีต "sgs(p)": แบบบันทึกคะแนน SGS-ONLINE 43 คอลัมน์ A4 แนวนอน
 * - เอกสาร ปพ.5 ครบ 5 ส่วนตามมาตรฐานกระทรวงศึกษาธิการ (หลักสูตรแกนกลางฯ พ.ศ. 2551)
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
     * ดึงข้อมูลครบทุกส่วนของรายวิชา ปพ.5 สำหรับแสดงผลหน้าสรุปและพิมพ์รายงาน
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

        // คำนวณสถิติจำนวนและร้อยละของผลการเรียนตาม 14 ช่องของแบบฟอร์ม ปพ.5-พ
        $gradeKeys = ['4', '3.5', '3', '2.5', '2', '1.5', '1', '0', 'ร', 'มส', 'มก', 'ผ', 'มผ', 'อื่นๆ'];
        $gradeCounts = array_fill_keys($gradeKeys, 0);
        $gradePercents = array_fill_keys($gradeKeys, 0.0);

        $remedialStudents = [];
        foreach ($students as $std) {
            $sid  = (int)$std['id'];
            $gRow = $gradesMap[$sid] ?? [];
            $rawG = (string)($gRow['grade'] ?? 'ร');
            $normG = match ($rawG) {
                '4.0' => '4',
                '3.0' => '3',
                '2.0' => '2',
                '1.0' => '1',
                '0.0' => '0',
                default => $rawG,
            };

            if (isset($gradeCounts[$normG])) {
                $gradeCounts[$normG]++;
            } else {
                $gradeCounts['อื่นๆ']++;
            }

            if (in_array($normG, ['0', 'ร', 'มส', 'มผ'], true)) {
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

        // ตรวจสอบความครบถ้วนของเล่ม ปพ.5 (Readiness Check)
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
     * ดึงชื่อและข้อมูลบุคลากรเพื่อแสดงในเอกสารอย่างเป็นระเบียบ
     */
    private function extractPersonnelNames(array $course, array $school): array
    {
        $teacherName = trim((string)($course['teacher_name'] ?? ''));
        if ($teacherName === '') {
            $teacherName = trim(($course['teacher_prefix'] ?? '') . ($course['teacher_first_name'] ?? '') . ' ' . ($course['teacher_last_name'] ?? ''));
        }
        if ($teacherName === '') {
            $teacherName = '.......................................................';
        }

        $headName = trim((string)($course['head_name'] ?? ''));
        if ($headName === '') {
            $headName = trim(($course['head_prefix'] ?? '') . ($course['head_first_name'] ?? '') . ' ' . ($course['head_last_name'] ?? ''));
        }
        if ($headName === '') {
            $headName = '.......................................................';
        }

        $advisorName = trim((string)($course['advisor_name'] ?? ''));
        if ($advisorName === '') {
            $advisorName = trim(($course['advisor_prefix'] ?? '') . ($course['advisor_first_name'] ?? '') . ' ' . ($course['advisor_last_name'] ?? ''));
        }
        if ($advisorName === '') {
            $advisorName = '.......................................................';
        }

        $coAdvisorName = trim((string)($course['co_advisor_name'] ?? ''));

        return [
            'teacher'     => $teacherName,
            'head'        => $headName,
            'advisor'     => $advisorName,
            'co_advisor'  => $coAdvisorName,
            'measurement' => 'นางสาวผ่องพรรณ   คำโสภา',
            'academic'    => 'นายเฉลิมชัย  ศรีชัย',
            'deputy'      => 'นางสาววรรณธนา  วงศ์พิทักษ์',
            'director'    => 'นายสถาน  ปรางมาศ',
        ];
    }

    /**
     * ชีต "ปกน(p)": สร้างหน้า HTML ใบปก ปพ.5-พ ตามแบบฟอร์มทางการของโรงเรียนละลมวิทยา 100%
     * ขนาด A4 แนวนอน (Landscape) พร้อมสรุปผล 14 ระดับ, ตารางประเมินอ่านคิดเขียน & คุณลักษณะฯ และบล็อกอนุมัติ 4 ขั้น
     */
    public function renderPokPageHtml(array $data, bool $standalone = false): string
    {
        $course        = $data['course'];
        $school        = $data['school'];
        $totalStudents = (int)$data['total_students'];
        $gradeCounts   = $data['grade_counts'];
        $gradePercents = $data['grade_percents'];
        $assessments   = $data['assessments'];
        $names         = $this->extractPersonnelNames($course, $school);

        $credit        = (float)($course['credit'] ?? 0);
        $hoursPerWeek  = $credit > 0 ? round($credit / 0.5) : 0;
        $levelGrade    = (int)($course['grade_level'] ?? 1);
        $roomNo        = (int)($course['room_no'] ?? 1);
        if ($roomNo === 0 && !empty($course['classroom_name'])) {
            if (preg_match('#/([0-9]+)#', (string)$course['classroom_name'], $m)) {
                $roomNo = (int)$m[1];
            }
        }
        $levelText     = $levelGrade >= 4 ? 'ระดับมัธยมศึกษาตอนปลาย' : 'ระดับมัธยมศึกษาตอนต้น';

        $rtwDist  = $assessments['rtw']['dist'] ?? [3 => 0, 2 => 0, 1 => 0, 0 => 0, 'incomplete' => 0];
        $charDist = $assessments['characteristic']['dist'] ?? [3 => 0, 2 => 0, 1 => 0, 0 => 0, 'incomplete' => 0];

        ob_start();
        if ($standalone): ?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>ใบปก ปพ.5-พ — <?= htmlspecialchars($course['subject_code'] . ' ' . $course['subject_name'] . ' (' . $course['classroom_name'] . ')', ENT_QUOTES, 'UTF-8') ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        @page {
            size: A4 landscape;
            margin: 6mm 10mm 6mm 10mm;
        }
        * {
            box-sizing: border-box;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }
        body {
            font-family: 'TH Sarabun New', 'THSarabunNew', 'Sarabun', sans-serif;
            font-size: 13.5pt;
            line-height: 1.35;
            color: #000000;
            margin: 0;
            padding: 0;
            background: #f1f5f9;
        }
        .pp5-page-landscape {
            width: 297mm;
            min-height: 210mm;
            margin: 6mm auto;
            padding: 8mm 12mm;
            background: #ffffff;
            box-shadow: 0 4px 20px rgba(15, 23, 42, 0.12);
            page-break-after: always;
            box-sizing: border-box;
        }
        @media print {
            body { background: #ffffff; }
            .pp5-page-landscape {
                width: 100% !important;
                min-height: auto !important;
                margin: 0 !important;
                padding: 0 !important;
                box-shadow: none !important;
            }
            .no-print { display: none !important; }
        }
        table.pp5-pok-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 11pt;
            table-layout: fixed;
        }
        table.pp5-pok-table th,
        table.pp5-pok-table td {
            border: 1px solid #000000;
            padding: 4px 2px;
            vertical-align: middle;
            text-align: center;
        }
        table.pp5-pok-table th {
            font-weight: bold;
            background: #ffffff;
        }
    </style>
</head>
<body>
        <?php endif; ?>

        <div class="pp5-page-landscape" style="position: relative;">
            <!-- Top Right: ปพ.5-พ -->
            <div style="text-align: right; font-weight: bold; font-size: 14pt; margin-bottom: 2mm;">
                ปพ.5-พ
            </div>

            <!-- School Header (Rows 5-6) -->
            <div style="text-align: center; margin-bottom: 4mm;">
                <div style="font-size: 20pt; font-weight: bold; line-height: 1.2;">แบบบันทึกผลการพัฒนาคุณภาพผู้เรียน</div>
                <div style="font-size: 15pt; font-weight: bold; margin-top: 1mm;">
                    <?= $levelText ?> &nbsp;&nbsp;โรงเรียนละลมวิทยา &nbsp;อำเภอภูสิงห์ &nbsp;จังหวัดศรีสะเกษ
                </div>
            </div>

            <!-- Course Metadata (Rows 7-9) -->
            <div style="font-size: 13.5pt; line-height: 1.6; margin-bottom: 4mm;">
                <div style="display: flex; justify-content: space-between;">
                    <div>
                        <strong>ชั้นมัธยมศึกษาปีที่</strong> <?= $levelGrade ?> / <?= $roomNo ?>
                        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<strong>ภาคเรียนที่</strong> <?= (int)$course['semester'] ?>
                        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<strong>ปีการศึกษา</strong> <?= (int)$course['academic_year'] ?>
                    </div>
                    <div>
                        <strong>กลุ่มสาระการเรียนรู้</strong> <?= htmlspecialchars($course['department_name'] ?? '-', ENT_QUOTES, 'UTF-8') ?>
                    </div>
                </div>
                <div style="display: flex; justify-content: space-between;">
                    <div>
                        <strong>รายวิชา</strong> <?= htmlspecialchars($course['subject_name'], ENT_QUOTES, 'UTF-8') ?>
                        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<strong>รหัสวิชา</strong> <?= htmlspecialchars($course['subject_code'], ENT_QUOTES, 'UTF-8') ?>
                        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<strong>จำนวนหน่วยกิต</strong> <?= number_format($credit, 1) ?>
                    </div>
                    <div>
                        <strong>เวลาเรียน</strong> <?= $hoursPerWeek ?> คาบ/สัปดาห์
                    </div>
                </div>
                <div style="display: flex; justify-content: space-between;">
                    <div>
                        <strong>ครูผู้สอน</strong> <?= htmlspecialchars($names['teacher'], ENT_QUOTES, 'UTF-8') ?>
                    </div>
                    <div>
                        <strong>ครูที่ปรึกษา</strong> <?= htmlspecialchars($names['advisor'], ENT_QUOTES, 'UTF-8') ?><?= !empty($names['co_advisor']) ? ' &nbsp;&nbsp;&nbsp;&nbsp;' . htmlspecialchars($names['co_advisor'], ENT_QUOTES, 'UTF-8') : '' ?>
                    </div>
                </div>
            </div>

            <!-- Section 1: สรุปผลการประเมิน (Rows 10-14) -->
            <div style="font-weight: bold; font-size: 14pt; margin-bottom: 1.5mm;">สรุปผลการประเมิน</div>
            <table class="pp5-pok-table" style="margin-bottom: 4mm;">
                <thead>
                    <tr>
                        <th rowspan="2" style="width: 14%; vertical-align: middle;">จำนวนนักเรียน</th>
                        <th colspan="14" style="text-align: center;">ระดับผลการเรียน</th>
                    </tr>
                    <tr>
                        <th style="width: 6%;">4.0</th>
                        <th style="width: 6%;">3.5</th>
                        <th style="width: 6%;">3.0</th>
                        <th style="width: 6%;">2.5</th>
                        <th style="width: 6%;">2.0</th>
                        <th style="width: 6%;">1.5</th>
                        <th style="width: 6%;">1.0</th>
                        <th style="width: 6%;">0.0</th>
                        <th style="width: 6%;">ร</th>
                        <th style="width: 6%;">มส</th>
                        <th style="width: 6%;">มก</th>
                        <th style="width: 6%;">ผ</th>
                        <th style="width: 6%;">มผ</th>
                        <th style="width: 8%;">อื่นๆ</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td style="font-weight: bold;"><?= $totalStudents ?></td>
                        <td><?= (int)($gradeCounts['4'] ?? 0) ?></td>
                        <td><?= (int)($gradeCounts['3.5'] ?? 0) ?></td>
                        <td><?= (int)($gradeCounts['3'] ?? 0) ?></td>
                        <td><?= (int)($gradeCounts['2.5'] ?? 0) ?></td>
                        <td><?= (int)($gradeCounts['2'] ?? 0) ?></td>
                        <td><?= (int)($gradeCounts['1.5'] ?? 0) ?></td>
                        <td><?= (int)($gradeCounts['1'] ?? 0) ?></td>
                        <td><?= (int)($gradeCounts['0'] ?? 0) ?></td>
                        <td><?= (int)($gradeCounts['ร'] ?? 0) ?></td>
                        <td><?= (int)($gradeCounts['มส'] ?? 0) ?></td>
                        <td><?= (int)($gradeCounts['มก'] ?? 0) ?></td>
                        <td><?= (int)($gradeCounts['ผ'] ?? 0) ?></td>
                        <td><?= (int)($gradeCounts['มผ'] ?? 0) ?></td>
                        <td><?= (int)($gradeCounts['อื่นๆ'] ?? 0) ?></td>
                    </tr>
                    <tr>
                        <td style="font-weight: bold;">คิดเป็นร้อยละ</td>
                        <td><?= number_format((float)($gradePercents['4'] ?? 0), 2) ?></td>
                        <td><?= number_format((float)($gradePercents['3.5'] ?? 0), 2) ?></td>
                        <td><?= number_format((float)($gradePercents['3'] ?? 0), 2) ?></td>
                        <td><?= number_format((float)($gradePercents['2.5'] ?? 0), 2) ?></td>
                        <td><?= number_format((float)($gradePercents['2'] ?? 0), 2) ?></td>
                        <td><?= number_format((float)($gradePercents['1.5'] ?? 0), 2) ?></td>
                        <td><?= number_format((float)($gradePercents['1'] ?? 0), 2) ?></td>
                        <td><?= number_format((float)($gradePercents['0'] ?? 0), 2) ?></td>
                        <td><?= number_format((float)($gradePercents['ร'] ?? 0), 2) ?></td>
                        <td><?= number_format((float)($gradePercents['มส'] ?? 0), 2) ?></td>
                        <td><?= number_format((float)($gradePercents['มก'] ?? 0), 2) ?></td>
                        <td><?= number_format((float)($gradePercents['ผ'] ?? 0), 2) ?></td>
                        <td><?= number_format((float)($gradePercents['มผ'] ?? 0), 2) ?></td>
                        <td><?= number_format((float)($gradePercents['อื่นๆ'] ?? 0), 2) ?></td>
                    </tr>
                </tbody>
            </table>

            <!-- Section 2: Dual side-by-side tables (Rows 16-19) -->
            <div style="display: flex; gap: 4mm; margin-bottom: 4mm;">
                <!-- Left: อ่าน คิดวิเคราะห์ และเขียน -->
                <div style="flex: 1;">
                    <table class="pp5-pok-table">
                        <thead>
                            <tr>
                                <th rowspan="2" style="width: 25%; vertical-align: middle;">จำนวนนักเรียน</th>
                                <th colspan="5">ผลการประเมินการอ่าน คิดวิเคราะห์ และเขียน</th>
                            </tr>
                            <tr>
                                <th style="width: 15%;">3 (ดีเยี่ยม)</th>
                                <th style="width: 15%;">2 (ดี)</th>
                                <th style="width: 15%;">1 (ผ่านเกณฑ์)</th>
                                <th style="width: 15%;">0 (ไม่ผ่านเกณฑ์)</th>
                                <th style="width: 15%;">ไม่ได้ประเมิน</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td style="font-weight: bold;"><?= $totalStudents ?></td>
                                <td><?= (int)($rtwDist[3] ?? 0) ?></td>
                                <td><?= (int)($rtwDist[2] ?? 0) ?></td>
                                <td><?= (int)($rtwDist[1] ?? 0) ?></td>
                                <td><?= (int)($rtwDist[0] ?? 0) ?></td>
                                <td><?= (int)($rtwDist['incomplete'] ?? 0) ?></td>
                            </tr>
                            <tr>
                                <td style="font-weight: bold;">คิดเป็นร้อยละ</td>
                                <td><?= $totalStudents > 0 ? number_format((($rtwDist[3] ?? 0) / $totalStudents) * 100, 2) : '0.00' ?></td>
                                <td><?= $totalStudents > 0 ? number_format((($rtwDist[2] ?? 0) / $totalStudents) * 100, 2) : '0.00' ?></td>
                                <td><?= $totalStudents > 0 ? number_format((($rtwDist[1] ?? 0) / $totalStudents) * 100, 2) : '0.00' ?></td>
                                <td><?= $totalStudents > 0 ? number_format((($rtwDist[0] ?? 0) / $totalStudents) * 100, 2) : '0.00' ?></td>
                                <td><?= $totalStudents > 0 ? number_format((($rtwDist['incomplete'] ?? 0) / $totalStudents) * 100, 2) : '0.00' ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Right: คุณลักษณะอันพึงประสงค์ -->
                <div style="flex: 1;">
                    <table class="pp5-pok-table">
                        <thead>
                            <tr>
                                <th rowspan="2" style="width: 25%; vertical-align: middle;">จำนวนนักเรียน</th>
                                <th colspan="5">ผลการประเมินคุณลักษณะอันพึงประสงค์</th>
                            </tr>
                            <tr>
                                <th style="width: 15%;">3 (ดีเยี่ยม)</th>
                                <th style="width: 15%;">2 (ดี)</th>
                                <th style="width: 15%;">1 (ผ่านเกณฑ์)</th>
                                <th style="width: 15%;">0 (ไม่ผ่านเกณฑ์)</th>
                                <th style="width: 15%;">ไม่ได้ประเมิน</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td style="font-weight: bold;"><?= $totalStudents ?></td>
                                <td><?= (int)($charDist[3] ?? 0) ?></td>
                                <td><?= (int)($charDist[2] ?? 0) ?></td>
                                <td><?= (int)($charDist[1] ?? 0) ?></td>
                                <td><?= (int)($charDist[0] ?? 0) ?></td>
                                <td><?= (int)($charDist['incomplete'] ?? 0) ?></td>
                            </tr>
                            <tr>
                                <td style="font-weight: bold;">คิดเป็นร้อยละ</td>
                                <td><?= $totalStudents > 0 ? number_format((($charDist[3] ?? 0) / $totalStudents) * 100, 2) : '0.00' ?></td>
                                <td><?= $totalStudents > 0 ? number_format((($charDist[2] ?? 0) / $totalStudents) * 100, 2) : '0.00' ?></td>
                                <td><?= $totalStudents > 0 ? number_format((($charDist[1] ?? 0) / $totalStudents) * 100, 2) : '0.00' ?></td>
                                <td><?= $totalStudents > 0 ? number_format((($charDist[0] ?? 0) / $totalStudents) * 100, 2) : '0.00' ?></td>
                                <td><?= $totalStudents > 0 ? number_format((($charDist['incomplete'] ?? 0) / $totalStudents) * 100, 2) : '0.00' ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Section 3: การอนุมัติผลการเรียน (Rows 21-31) -->
            <div style="font-weight: bold; font-size: 14pt; margin-bottom: 1.5mm;">การอนุมัติผลการเรียน</div>

            <!-- 4 Signatures Line 1 -->
            <div style="display: flex; justify-content: space-between; text-align: center; font-size: 12.5pt; line-height: 1.45; margin-bottom: 3.5mm;">
                <div style="flex: 1;">
                    ลงชื่อ....................................................ครูผู้สอน<br>
                    ( <?= htmlspecialchars($names['teacher'], ENT_QUOTES, 'UTF-8') ?> )
                </div>
                <div style="flex: 1;">
                    ลงชื่อ.......................................................หัวหน้ากลุ่มฯ<br>
                    ( <?= htmlspecialchars($names['head'], ENT_QUOTES, 'UTF-8') ?> )
                </div>
                <div style="flex: 1;">
                    ลงชื่อ............................................................งานวัดผลฯ<br>
                    ( <?= htmlspecialchars($names['measurement'], ENT_QUOTES, 'UTF-8') ?> )
                </div>
                <div style="flex: 1;">
                    ลงชื่อ..............................................ฝ่ายวิชาการ<br>
                    ( <?= htmlspecialchars($names['academic'], ENT_QUOTES, 'UTF-8') ?> )
                </div>
            </div>

            <!-- Proposal Block -->
            <div style="text-align: center; margin-bottom: 3.5mm;">
                <div style="font-size: 13pt; font-weight: bold; margin-bottom: 1mm;">เรียนเสนอเพื่อโปรดพิจารณา</div>
                <div style="font-size: 13pt; font-weight: bold;">
                    [ &nbsp; ] อนุมัติ &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; [ &nbsp; ] ไม่อนุมัติ
                </div>
            </div>

            <!-- 2 Director Signatures Line 2 -->
            <div style="display: flex; justify-content: space-around; text-align: center; font-size: 12.5pt; line-height: 1.45;">
                <div style="width: 45%;">
                    ลงชื่อ............................................................รองผู้อำนวยการโรงเรียนละลมวิทยา<br>
                    ( <?= htmlspecialchars($names['deputy'], ENT_QUOTES, 'UTF-8') ?> )<br>
                    วันที่........เดือน...................พ.ศ........
                </div>
                <div style="width: 45%;">
                    ลงชื่อ..................................................................ผู้อำนวยการโรงเรียนละลมวิทยา<br>
                    ( <?= htmlspecialchars($names['director'], ENT_QUOTES, 'UTF-8') ?> )<br>
                    วันที่........เดือน...................พ.ศ........
                </div>
            </div>
        </div>

        <?php if ($standalone): ?>
</body>
</html>
        <?php endif;

        return (string)ob_get_clean();
    }

    /**
     * ชีต "sgs(p)": สร้างหน้า HTML ตารางแบบบันทึกคะแนน SGS-ONLINE 43 คอลัมน์
     * ตามแบบฟอร์มทางการของโรงเรียนละลมวิทยา 100% ขนาด A4 แนวนอน (Landscape)
     */
    public function renderSgsPageHtml(array $data, bool $standalone = false): string
    {
        $course        = $data['course'];
        $school        = $data['school'];
        $students      = $data['students'];
        $totalStudents = (int)$data['total_students'];
        $scoreItems    = $data['score_items'];
        $scoresGrid    = $data['scores_grid'];
        $gradesMap     = $data['grades_map'];
        $assessments   = $data['assessments'];
        $attSummaryMap = $data['att_summary_map'];
        $names         = $this->extractPersonnelNames($course, $school);

        $credit        = (float)($course['credit'] ?? 0);
        $levelGrade    = (int)($course['grade_level'] ?? 1);
        $roomNo        = (int)($course['room_no'] ?? 1);
        if ($roomNo === 0 && !empty($course['classroom_name'])) {
            if (preg_match('#/([0-9]+)#', (string)$course['classroom_name'], $m)) {
                $roomNo = (int)$m[1];
            }
        }

        // จัดสรรรายการชิ้นงานลง 6 สล็อตก่อนกลางภาค และ 6 สล็อตหลังกลางภาค
        $beforeMidItems = array_values(array_filter($scoreItems, fn($i) => ($i['phase'] ?? '') === 'before_mid'));
        $midtermItems   = array_values(array_filter($scoreItems, fn($i) => ($i['phase'] ?? '') === 'midterm'));
        $afterMidItems  = array_values(array_filter($scoreItems, fn($i) => ($i['phase'] ?? '') === 'after_mid'));
        $finalItems     = array_values(array_filter($scoreItems, fn($i) => ($i['phase'] ?? '') === 'final'));

        $unit1_6 = [];
        $beforeMidMaxSum = 0;
        for ($u = 0; $u < 6; $u++) {
            if (isset($beforeMidItems[$u])) {
                $unit1_6[$u] = $beforeMidItems[$u];
                $beforeMidMaxSum += (float)($beforeMidItems[$u]['max_score'] ?? 0);
            } else {
                $unit1_6[$u] = null;
            }
        }

        $midtermMax = 0;
        foreach ($midtermItems as $mi) {
            $midtermMax += (float)($mi['max_score'] ?? 0);
        }

        $unit7_12 = [];
        $afterMidMaxSum = 0;
        for ($u = 0; $u < 6; $u++) {
            if (isset($afterMidItems[$u])) {
                $unit7_12[$u] = $afterMidItems[$u];
                $afterMidMaxSum += (float)($afterMidItems[$u]['max_score'] ?? 0);
            } else {
                $unit7_12[$u] = null;
            }
        }

        $finalMax = 0;
        foreach ($finalItems as $fi) {
            $finalMax += (float)($fi['max_score'] ?? 0);
        }

        $totalMax = $beforeMidMaxSum + $midtermMax + $afterMidMaxSum + $finalMax;
        if ($totalMax == 0) {
            $totalMax = 100;
        }

        ob_start();
        if ($standalone): ?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>SGS-ONLINE — <?= htmlspecialchars($course['subject_code'] . ' ' . $course['subject_name'] . ' (' . $course['classroom_name'] . ')', ENT_QUOTES, 'UTF-8') ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        @page {
            size: A4 landscape;
            margin: 4mm 6mm 4mm 6mm;
        }
        * {
            box-sizing: border-box;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }
        body {
            font-family: 'TH Sarabun New', 'THSarabunNew', 'Sarabun', sans-serif;
            font-size: 10pt;
            line-height: 1.25;
            color: #000000;
            margin: 0;
            padding: 0;
            background: #f1f5f9;
        }
        .pp5-page-sgs {
            width: 297mm;
            min-height: 210mm;
            margin: 4mm auto;
            padding: 6mm 8mm;
            background: #ffffff;
            box-shadow: 0 4px 20px rgba(15, 23, 42, 0.12);
            page-break-after: always;
            box-sizing: border-box;
        }
        @media print {
            body { background: #ffffff; }
            .pp5-page-sgs {
                width: 100% !important;
                min-height: auto !important;
                margin: 0 !important;
                padding: 0 !important;
                box-shadow: none !important;
            }
            .no-print { display: none !important; }
        }
        table.pp5-sgs-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 8pt;
            table-layout: fixed;
        }
        table.pp5-sgs-table th,
        table.pp5-sgs-table td {
            border: 1px solid #000000;
            padding: 1.5px 1px;
            vertical-align: middle;
            text-align: center;
            overflow: hidden;
            white-space: nowrap;
        }
        table.pp5-sgs-table th {
            font-weight: bold;
            background: #ffffff;
        }
        table.pp5-sgs-table td.sgs-name {
            text-align: left;
            padding-left: 3px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
    </style>
</head>
<body>
        <?php endif; ?>

        <div class="pp5-page-sgs" style="position: relative;">
            <!-- Header Titles (Rows 1-3) -->
            <div style="margin-bottom: 2mm; font-size: 11pt; line-height: 1.3;">
                <div style="font-weight: bold; font-size: 13.5pt;">โรงเรียนละลมวิทยา</div>
                <div>
                    วิชา <?= htmlspecialchars($course['subject_code'] . ' ' . $course['subject_name'], ENT_QUOTES, 'UTF-8') ?>
                    &nbsp;&nbsp;นก <?= number_format($credit, 1) ?>
                    &nbsp;&nbsp;ปีการศึกษา <?= (int)$course['academic_year'] ?>
                    &nbsp;&nbsp;ภาคเรียนที่ <?= (int)$course['semester'] ?>
                    &nbsp;&nbsp;ระดับชั้น ม.<?= $levelGrade ?>
                    &nbsp;&nbsp;กลุ่มที่ <?= $roomNo ?>
                </div>
                <div>ครูผู้สอน <?= htmlspecialchars($names['teacher'], ENT_QUOTES, 'UTF-8') ?></div>
            </div>

            <!-- SGS Table 43 Columns (Rows 4-7 + Students + Footer) -->
            <table class="pp5-sgs-table">
                <thead>
                    <!-- Row 4: Top Headers -->
                    <tr>
                        <th rowspan="3" style="width: 2.5%;">ห้อง</th>
                        <th rowspan="3" style="width: 5%;">เลขประจำตัว</th>
                        <th rowspan="3" style="width: 14%;">ชื่อ - สกุล</th>
                        <th style="width: 2.5%;"></th>
                        <th colspan="7" style="width: 16%;">ก่อนกลางภาค</th>
                        <th rowspan="2" style="width: 3%;">กลาง<br>ภาค</th>
                        <th colspan="7" style="width: 16%;">หลังกลางภาค</th>
                        <th rowspan="2" style="width: 3%;">ปลาย<br>ภาค</th>
                        <th rowspan="2" style="width: 3.2%;">รวม</th>
                        <th rowspan="2" style="width: 2.8%;">ร้อย<br>ละ</th>
                        <th rowspan="3" style="width: 3%;">ผล<br>การ<br>เรียน</th>
                        <th colspan="9" style="width: 13.5%;">คุณลักษณะอันพึงประสงค์</th>
                        <th colspan="6" style="width: 9%;">อ่าน คิด<br>วิเคราะห์ เขียน</th>
                        <th rowspan="2" style="width: 1.8%;">มา<br>สาย</th>
                        <th rowspan="2" style="width: 1.8%;">ลา<br>ป่วย</th>
                        <th rowspan="2" style="width: 1.8%;">ลา<br>กิจ</th>
                        <th rowspan="2" style="width: 1.8%;">ขาด</th>
                        <th rowspan="2" style="width: 2.8%;">ร้อย<br>ละ</th>
                    </tr>

                    <!-- Row 5: Units 1-6, Units 7-12 -->
                    <tr>
                        <th style="font-size: 7.5pt;">หน่วย</th>
                        <th style="width: 2.2%;">1.0</th>
                        <th style="width: 2.2%;">2.0</th>
                        <th style="width: 2.2%;">3.0</th>
                        <th style="width: 2.2%;">4.0</th>
                        <th style="width: 2.2%;">5.0</th>
                        <th style="width: 2.2%;">6.0</th>
                        <th style="width: 2.8%;">รวม</th>
                        <th style="width: 2.2%;">7.0</th>
                        <th style="width: 2.2%;">8.0</th>
                        <th style="width: 2.2%;">9.0</th>
                        <th style="width: 2.2%;">10.0</th>
                        <th style="width: 2.2%;">11.0</th>
                        <th style="width: 2.2%;">12.0</th>
                        <th style="width: 2.8%;">รวม</th>
                        <th colspan="9" style="font-size: 7.5pt;">ข้อ 1 - 8 + ผลสรุป</th>
                        <th colspan="6" style="font-size: 7.5pt;">ข้อ 1 - 5 + ผลสรุป</th>
                    </tr>

                    <!-- Row 6: Max scores & Item numbers -->
                    <tr>
                        <th style="font-size: 7.5pt;">เลขที่</th>
                        <?php for ($u = 0; $u < 6; $u++): ?>
                            <th><?= $unit1_6[$u] ? (float)$unit1_6[$u]['max_score'] : '0' ?></th>
                        <?php endfor; ?>
                        <th><?= $beforeMidMaxSum ?></th>
                        <th><?= $midtermMax ?></th>
                        <?php for ($u = 0; $u < 6; $u++): ?>
                            <th><?= $unit7_12[$u] ? (float)$unit7_12[$u]['max_score'] : '0' ?></th>
                        <?php endfor; ?>
                        <th><?= $afterMidMaxSum ?></th>
                        <th><?= $finalMax ?></th>
                        <th><?= $totalMax ?></th>
                        <th>100</th>
                        <th>1.0</th>
                        <th>2.0</th>
                        <th>3.0</th>
                        <th>4.0</th>
                        <th>5.0</th>
                        <th>6.0</th>
                        <th>7.0</th>
                        <th>8.0</th>
                        <th style="background: #f1f5f9;">ผล</th>
                        <th>1.0</th>
                        <th>2.0</th>
                        <th>3.0</th>
                        <th>4.0</th>
                        <th>5.0</th>
                        <th style="background: #f1f5f9;">ผล</th>
                        <th></th>
                        <th></th>
                        <th></th>
                        <th></th>
                        <th></th>
                    </tr>

                    <!-- Row 7: Class indicator -->
                    <tr style="background: #fafafa;">
                        <th colspan="4" style="text-align: left; padding-left: 4px; font-weight: normal; font-size: 8pt;">
                            ชั้น/ห้อง ม.<?= $levelGrade ?>/<?= $roomNo ?>
                        </th>
                        <th colspan="39"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($students as $std):
                        $sid   = (int)$std['id'];
                        $gRow  = $gradesMap[$sid] ?? [];
                        $grade = (string)($gRow['grade'] ?? 'ร');
                        $charGrid = $assessments['characteristic']['grid'][$sid] ?? [];
                        $charSum  = $assessments['characteristic']['summaries'][$sid]['level'] ?? '';
                        $rtwGrid  = $assessments['rtw']['grid'][$sid] ?? [];
                        $rtwSum   = $assessments['rtw']['summaries'][$sid]['level'] ?? '';
                        $attSum   = $attSummaryMap[$sid] ?? [
                            'late' => 0, 'sick' => 0, 'leave' => 0, 'absent' => 0, 'attendance_percent' => 100.0,
                        ];
                        $counts   = $attSum['counts'] ?? [
                            'late' => 0, 'sick' => 0, 'leave' => 0, 'absent' => 0,
                        ];

                        $scoreBfMid = (float)($gRow['score_before_mid'] ?? 0);
                        $scoreMid   = (float)($gRow['score_midterm'] ?? 0);
                        $scoreAfMid = (float)($gRow['score_after_mid'] ?? 0);
                        $scoreFin   = (float)($gRow['score_final'] ?? 0);
                        $scoreTotal = (float)($gRow['total'] ?? 0);
                    ?>
                        <tr>
                            <td><?= $roomNo ?></td>
                            <td><?= htmlspecialchars($std['student_code'] ?? $std['student_id'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="sgs-name"><?= htmlspecialchars($std['name'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= (int)$std['student_number'] ?></td>

                            <!-- หน่วย 1-6 ก่อนกลางภาค -->
                            <?php for ($u = 0; $u < 6; $u++):
                                if ($unit1_6[$u] !== null) {
                                    $sc = $scoresGrid[$sid][(int)$unit1_6[$u]['id']] ?? null;
                                    $scVal = ($sc === null) ? ' ' : (float)$sc;
                                } else {
                                    $scVal = ' ';
                                }
                            ?>
                                <td><?= $scVal ?></td>
                            <?php endfor; ?>
                            <td style="font-weight: bold;"><?= $scoreBfMid > 0 ? $scoreBfMid : ($beforeMidMaxSum > 0 ? '0' : ' ') ?></td>

                            <!-- กลางภาค -->
                            <td style="font-weight: bold;"><?= $scoreMid > 0 ? $scoreMid : ($midtermMax > 0 ? '0' : ' ') ?></td>

                            <!-- หน่วย 7-12 หลังกลางภาค -->
                            <?php for ($u = 0; $u < 6; $u++):
                                if ($unit7_12[$u] !== null) {
                                    $sc = $scoresGrid[$sid][(int)$unit7_12[$u]['id']] ?? null;
                                    $scVal = ($sc === null) ? ' ' : (float)$sc;
                                } else {
                                    $scVal = ' ';
                                }
                            ?>
                                <td><?= $scVal ?></td>
                            <?php endfor; ?>
                            <td style="font-weight: bold;"><?= $scoreAfMid > 0 ? $scoreAfMid : ($afterMidMaxSum > 0 ? '0' : ' ') ?></td>

                            <!-- ปลายภาค -->
                            <td style="font-weight: bold;"><?= $scoreFin > 0 ? $scoreFin : ($finalMax > 0 ? '0' : ' ') ?></td>

                            <!-- รวม และ ร้อยละ -->
                            <td style="font-weight: bold;"><?= $scoreTotal ?></td>
                            <td style="font-weight: bold;"><?= $scoreTotal ?></td>

                            <!-- ผลการเรียน -->
                            <td style="font-weight: bold; background: <?= in_array($grade, ['0', 'ร', 'มส', 'มผ'], true) ? '#fee2e2;' : '#f8fafc;' ?>">
                                <?= htmlspecialchars($grade, ENT_QUOTES, 'UTF-8') ?>
                            </td>

                            <!-- คุณลักษณะอันพึงประสงค์ 1-8 + ผล -->
                            <?php for ($ci = 1; $ci <= 8; $ci++):
                                $cVal = $charGrid[$ci] ?? '';
                            ?>
                                <td><?= $cVal !== '' && $cVal !== null ? (int)$cVal : ' ' ?></td>
                            <?php endfor; ?>
                            <td style="font-weight: bold; background: #f1f5f9;"><?= $charSum !== '' && $charSum !== null ? (int)$charSum : ' ' ?></td>

                            <!-- อ่าน คิดวิเคราะห์ เขียน 1-5 + ผล -->
                            <?php for ($ri = 1; $ri <= 5; $ri++):
                                $rVal = $rtwGrid[$ri] ?? '';
                            ?>
                                <td><?= $rVal !== '' && $rVal !== null ? (int)$rVal : ' ' ?></td>
                            <?php endfor; ?>
                            <td style="font-weight: bold; background: #f1f5f9;"><?= $rtwSum !== '' && $rtwSum !== null ? (int)$rtwSum : ' ' ?></td>

                            <!-- เวลาเรียน: มาสาย, ลาป่วย, ลากิจ, ขาด, ร้อยละ -->
                            <td><?= !empty($counts['late']) ? (int)$counts['late'] : ' ' ?></td>
                            <td><?= !empty($counts['sick']) ? (int)$counts['sick'] : ' ' ?></td>
                            <td><?= !empty($counts['leave']) ? (int)$counts['leave'] : ' ' ?></td>
                            <td style="<?= !empty($counts['absent']) ? 'color: #dc2626; font-weight: bold;' : '' ?>">
                                <?= !empty($counts['absent']) ? (int)$counts['absent'] : ' ' ?>
                            </td>
                            <td><?= number_format((float)($attSum['attendance_percent'] ?? 100.0), 1) ?></td>
                        </tr>
                    <?php endforeach; ?>

                    <!-- Footer Row (Row 53+) -->
                    <tr style="font-weight: bold; background: #f8fafc;">
                        <td colspan="4" style="text-align: left; padding-left: 6px;">
                            รวม ม.<?= $levelGrade ?>/<?= $roomNo ?> : <?= $totalStudents ?> คน
                        </td>
                        <td colspan="39"></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <?php if ($standalone): ?>
</body>
</html>
        <?php endif;

        return (string)ob_get_clean();
    }

    /**
     * สร้าง HTML ฉบับสมบูรณ์สำหรับพิมพ์/ส่งออก PDF รายงาน ปพ.5
     * รองรับโหมด:
     * - $template = 'pok' : พิมพ์เฉพาะใบปก ปพ.5-พ (ชีต ปกน(p))
     * - $template = 'sgs' : พิมพ์เฉพาะแบบบันทึกคะแนน SGS-ONLINE (ชีต sgs(p))
     * - $template = 'default' | 'all' : รวมเล่ม ปพ.5 ครบชุดตามที่เลือกใน $sections
     */
    public function renderReportHtml(array $data, array $sections = [1, 2, 3, 4, 5], bool $standalone = true, string $template = 'default'): string
    {
        if ($template === 'pok' || $sections === [1] || $sections === ['pok']) {
            return $this->renderPokPageHtml($data, $standalone);
        }

        if ($template === 'sgs' || $sections === ['sgs'] || $sections === [3] && count($sections) === 1 && !empty($_GET['sgs_only'])) {
            return $this->renderSgsPageHtml($data, $standalone);
        }

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
        $names         = $this->extractPersonnelNames($course, $school);

        ob_start();
        if ($standalone): ?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>ปพ.5 — <?= htmlspecialchars($course['subject_code'] . ' ' . $course['subject_name'] . ' (' . $course['classroom_name'] . ')', ENT_QUOTES, 'UTF-8') ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        @page {
            size: A4 landscape;
            margin: 6mm 8mm 6mm 8mm;
        }
        * {
            box-sizing: border-box;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }
        body {
            font-family: 'TH Sarabun New', 'THSarabunNew', 'Sarabun', sans-serif;
            font-size: 13.5pt;
            line-height: 1.35;
            color: #000000;
            margin: 0;
            padding: 0;
            background: #f1f5f9;
        }
        .pp5-page-landscape, .pp5-page-sgs {
            width: 297mm;
            min-height: 210mm;
            margin: 6mm auto;
            padding: 8mm 12mm;
            background: #ffffff;
            box-shadow: 0 4px 20px rgba(15, 23, 42, 0.12);
            page-break-after: always;
            box-sizing: border-box;
        }
        .pp5-page-landscape:last-child, .pp5-page-sgs:last-child {
            page-break-after: auto;
        }
        @media print {
            body { background: #ffffff; }
            .pp5-page-landscape, .pp5-page-sgs {
                width: 100% !important;
                min-height: auto !important;
                margin: 0 !important;
                padding: 0 !important;
                box-shadow: none !important;
            }
            .no-print { display: none !important; }
        }
        table.pp5-pok-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 11pt;
            table-layout: fixed;
        }
        table.pp5-pok-table th,
        table.pp5-pok-table td {
            border: 1px solid #000000;
            padding: 4px 2px;
            vertical-align: middle;
            text-align: center;
        }
        table.pp5-pok-table th {
            font-weight: bold;
            background: #ffffff;
        }
        table.pp5-sgs-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 8pt;
            table-layout: fixed;
        }
        table.pp5-sgs-table th,
        table.pp5-sgs-table td {
            border: 1px solid #000000;
            padding: 1.5px 1px;
            vertical-align: middle;
            text-align: center;
            overflow: hidden;
            white-space: nowrap;
        }
        table.pp5-sgs-table th {
            font-weight: bold;
            background: #ffffff;
        }
        table.pp5-sgs-table td.sgs-name {
            text-align: left;
            padding-left: 3px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
    </style>
</head>
<body>
        <?php endif;

        // 1. หน้าปก ปพ.5-พ (ชีต ปกน(p))
        if (in_array(1, $sections, true) || in_array('pok', $sections, true)) {
            echo $this->renderPokPageHtml($data, false);
        }

        // 2. แบบบันทึกคะแนน SGS-ONLINE (ชีต sgs(p))
        if (in_array(3, $sections, true) || in_array('sgs', $sections, true)) {
            echo $this->renderSgsPageHtml($data, false);
        }

        if ($standalone): ?>
</body>
</html>
        <?php endif;

        return (string)ob_get_clean();
    }

    /**
     * สร้างไฟล์ ZIP บีบอัดเอกสาร ปพ.5 รายวิชาแบบ Batch หลายเล่มพร้อมกัน
     */
    public function createBatchZipArchive(array $courseIds): ?string
    {
        if (empty($courseIds) || !class_exists('ZipArchive')) {
            return null;
        }

        $zip = new \ZipArchive();
        $tmpDir = sys_get_temp_dir();
        $zipName = $tmpDir . DIRECTORY_SEPARATOR . 'PP5_Batch_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.zip';

        if ($zip->open($zipName, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            return null;
        }

        $added = 0;
        foreach ($courseIds as $cId) {
            $cId = (int)$cId;
            $cData = $this->compileCourseReportData($cId);
            if (!$cData) {
                continue;
            }
            $c = $cData['course'];
            $html = $this->renderReportHtml($cData, [1, 2, 3, 4, 5], true);
            $safeRoom = str_replace(['/', '\\'], '-', (string)$c['classroom_name']);
            $entryName = "PP5_{$c['academic_year']}_{$c['semester']}_{$c['subject_code']}_{$safeRoom}.html";
            $zip->addFromString($entryName, $html);
            $added++;
        }

        $zip->close();
        return $added > 0 ? $zipName : null;
    }
}