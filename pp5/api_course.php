<?php
/**
 * pp5/api_course.php — REST JSON API สำหรับตารางกรอกคะแนน & การประเมินคุณลักษณะ/อ่านคิดเขียน/สมรรถนะ (ระยะที่ 2–3)
 *
 * รองรับ Action:
 * - save_scores        : บันทึกคะแนนรายช่องหรือแบบกลุ่ม (Clipboard Paste) พร้อมคำนวณเกรดทันที
 * - recalculate_grades : คำนวณคะแนนรวม เวลาเรียน % และเกรดของนักเรียนทั้งห้องใหม่ทั้งหมด
 * - save_assessments   : บันทึกระดับผลการประเมิน (0–3) ของคุณลักษณะอันพึงประสงค์ / อ่านคิดเขียน / สมรรถนะ
 *                        (รองรับทั้งแก้ทีละช่อง, ปุ่มกรอกทั้งคอลัมน์, กรอกทั้งตาราง และวางจาก Excel)
 */

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/_bootstrap.php';

use PP5\App\Policies\Pp5Policy;
use PP5\App\Services\AssessmentService;
use PP5\App\Services\CourseService;
use PP5\App\Services\MasterDataService;

pp5_require_login(true);

$policy        = new Pp5Policy($pdo);
$courseService = new CourseService($pdo);
$masterService = new MasterDataService($pdo);

$rawInput = file_get_contents('php://input');
$input    = json_decode($rawInput ?: '{}', true);
if (!is_array($input)) {
    $input = $_POST;
}

$action   = (string)($input['action'] ?? $_GET['action'] ?? '');
$courseId = (int)($input['course_id'] ?? $_GET['course_id'] ?? 0);

if ($courseId <= 0) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'ไม่พบรหัสรายวิชา ปพ.5 (course_id)'], JSON_UNESCAPED_UNICODE);
    exit;
}

$course = $courseService->getCourseById($courseId);
if (!$course) {
    http_response_code(404);
    echo json_encode(['status' => 'error', 'message' => 'ไม่พบข้อมูลรายวิชา ปพ.5 ที่ระบุ'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    // ─── การกรอกคะแนนและคำนวณเกรด ───
    if ($action === 'save_scores') {
        if (!$policy->canEditCourse($course)) {
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'คุณไม่มีสิทธิ์แก้ไขคะแนนในรายวิชานี้ หรือรายวิชาถูกส่งตรวจ/อนุมัติแล้ว'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $entries = $input['entries'] ?? [];
        if (!is_array($entries) || empty($entries)) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'ไม่มีข้อมูลคะแนนที่จะบันทึก'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $res = $courseService->saveBatchScores($courseId, $entries, $policy->getUserId());
        if (!$res['ok']) {
            http_response_code(422);
            echo json_encode([
                'status'  => 'error',
                'message' => implode("\n", $res['errors']),
                'errors'  => $res['errors'],
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $gradesMap = $courseService->getCourseGradesMap($courseId);
        $dist      = [
            '4' => 0, '3.5' => 0, '3' => 0, '2.5' => 0,
            '2' => 0, '1.5' => 0, '1' => 0, '0' => 0,
            'ร' => 0, 'มส' => 0, 'ผ' => 0, 'มผ' => 0,
        ];
        foreach ($gradesMap as $gRow) {
            $g = (string)($gRow['grade'] ?? '');
            if (isset($dist[$g])) {
                $dist[$g]++;
            }
        }

        echo json_encode([
            'status'       => 'success',
            'saved_count'  => $res['saved_count'],
            'grades'       => $res['grades'],
            'distribution' => $dist,
            'warnings'     => $res['errors'] ?? [],
            'saved_at'     => date('H:i:s'),
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($action === 'recalculate_grades') {
        if (!$policy->canEditCourse($course)) {
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'คุณไม่มีสิทธิ์แก้ไขข้อมูลในรายวิชานี้'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $grades = $courseService->recalculateCourseGrades($courseId);
        $dist   = [
            '4' => 0, '3.5' => 0, '3' => 0, '2.5' => 0,
            '2' => 0, '1.5' => 0, '1' => 0, '0' => 0,
            'ร' => 0, 'มส' => 0, 'ผ' => 0, 'มผ' => 0,
        ];
        foreach ($grades as $gRow) {
            $g = (string)($gRow['grade'] ?? '');
            if (isset($dist[$g])) {
                $dist[$g]++;
            }
        }

        echo json_encode([
            'status'       => 'success',
            'message'      => 'คำนวณคะแนนรวมและตัดเกรดทั้งห้องเรียบร้อยแล้ว',
            'grades'       => $grades,
            'distribution' => $dist,
            'saved_at'     => date('H:i:s'),
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // ─── การประเมิน 3 ด้าน ───
    if ($action === 'save_assessments') {
        if (!$policy->canEditCourse($course)) {
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'คุณไม่มีสิทธิ์แก้ไขข้อมูลผลการประเมินในรายวิชานี้'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $assessmentType = (string)($input['assessment_type'] ?? 'characteristic');
        if (!in_array($assessmentType, ['characteristic', 'rtw', 'competency'], true)) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'ประเภทการประเมินไม่ถูกต้อง'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $entries = $input['entries'] ?? [];
        if (!is_array($entries) || empty($entries)) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'ไม่มีข้อมูลผลการประเมินที่จะบันทึก'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $students   = $courseService->getCourseStudents($course);
        $studentIds = array_map(fn($s) => (int)$s['id'], $students);

        $assessmentService = new AssessmentService($pdo);
        $res = $assessmentService->saveBatchAssessments($courseId, $assessmentType, $entries, $studentIds, $policy->getUserId());

        if (!$res['ok']) {
            http_response_code(422);
            echo json_encode([
                'status'  => 'error',
                'message' => implode("\n", $res['errors']),
                'errors'  => $res['errors'],
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        echo json_encode([
            'status'      => 'success',
            'saved_count' => $res['saved_count'],
            'summaries'   => $res['summaries'],
            'dist'        => $res['dist'],
            'saved_at'    => date('H:i:s'),
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // ─── ระบบส่งตรวจและอนุมัติเล่ม ปพ.5 (Approval Workflow) ───
    if ($action === 'submit_course') {
        if (!$policy->canSubmitCourse($course)) {
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'คุณไม่มีสิทธิ์ส่งตรวจเล่ม ปพ.5 นี้ หรือสถานะไม่ใช่ฉบับร่าง'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $res = $masterService->submitCourse($courseId, $policy->getUserId());
        if (!$res['ok']) {
            http_response_code(422);
            echo json_encode(['status' => 'error', 'message' => $res['message']], JSON_UNESCAPED_UNICODE);
            exit;
        }

        echo json_encode([
            'status'     => 'success',
            'message'    => $res['message'],
            'new_status' => 'submitted',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($action === 'approve_course') {
        $step = (string)($input['step'] ?? 'head');
        if (!in_array($step, ['head', 'deputy', 'director'], true)) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'ขั้นตอนการอนุมัติไม่ถูกต้อง'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if (!$policy->canApproveCourse($course, $step)) {
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'คุณไม่มีสิทธิ์อนุมัติเล่ม ปพ.5 ในขั้นตอนนี้'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $comment = (string)($input['comment'] ?? '');
        $res     = $masterService->approveCourse($courseId, $policy->getUserId(), $step, $comment);
        if (!$res['ok']) {
            http_response_code(422);
            echo json_encode(['status' => 'error', 'message' => $res['message']], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $newStatusMap = ['head' => 'head_approved', 'deputy' => 'deputy_approved', 'director' => 'approved'];
        echo json_encode([
            'status'     => 'success',
            'message'    => $res['message'],
            'new_status' => $newStatusMap[$step] ?? 'approved',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($action === 'reject_course') {
        if (!$policy->canRejectCourse($course)) {
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'คุณไม่มีสิทธิ์ตีกลับเล่ม ปพ.5 นี้'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $reason = trim((string)($input['reason'] ?? ''));
        if ($reason === '') {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'กรุณาระบุเหตุผลในการส่งกลับแก้ไข'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $step = 'reviewer';
        if ($policy->isDirector()) {
            $step = 'director';
        } elseif ($policy->isDeputy()) {
            $step = 'deputy';
        } elseif ($policy->isDepartmentHead((int)($course['department_id'] ?? 0))) {
            $step = 'head';
        }

        $res = $masterService->rejectCourse($courseId, $policy->getUserId(), $step, $reason);
        if (!$res['ok']) {
            http_response_code(422);
            echo json_encode(['status' => 'error', 'message' => $res['message']], JSON_UNESCAPED_UNICODE);
            exit;
        }

        echo json_encode([
            'status'     => 'success',
            'message'    => $res['message'],
            'new_status' => 'draft',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($action === 'unlock_course') {
        if (!$policy->canUnlockCourse()) {
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'เฉพาะงานทะเบียนและวัดผลเท่านั้นที่มีสิทธิ์ปลดล็อกเล่ม ปพ.5'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $reason = trim((string)($input['reason'] ?? ''));
        if ($reason === '') {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'กรุณาระบุเหตุผลการปลดล็อกเล่ม ปพ.5'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $ok = $masterService->unlockCourse($courseId, $policy->getUserId(), $reason);
        if (!$ok) {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'ไม่สามารถปลดล็อกเล่ม ปพ.5 ได้'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        echo json_encode([
            'status'     => 'success',
            'message'    => 'ปลดล็อกเล่ม ปพ.5 เรียบร้อยแล้ว (สถานะเปลี่ยนเป็น: ฉบับร่าง)',
            'new_status' => 'draft',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($action === 'get_approvals') {
        $approvals = $masterService->getCourseApprovals($courseId);
        echo json_encode([
            'status'    => 'success',
            'approvals' => $approvals,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($action === 'save_remedial') {
        if (!$policy->canEditCourse($course) && !$policy->isAdmin()) {
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'คุณไม่มีสิทธิ์บันทึกผลการสอบแก้ตัวในรายวิชานี้ หรือรายวิชาถูกล็อกแล้ว'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $studentId    = (int)($input['student_id'] ?? 0);
        $newGrade     = trim((string)($input['new_grade'] ?? ''));
        $remedialDate = trim((string)($input['remedial_date'] ?? date('Y-m-d')));

        if ($studentId <= 0 || $newGrade === '') {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'ข้อมูลนักเรียนหรือเกรดใหม่ไม่ถูกต้อง'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $res = $courseService->saveStudentRemedialGrade($courseId, $studentId, $newGrade, $remedialDate, $policy->getUserId());
        if (!$res['ok']) {
            http_response_code(422);
            echo json_encode(['status' => 'error', 'message' => $res['message']], JSON_UNESCAPED_UNICODE);
            exit;
        }

        echo json_encode([
            'status'         => 'success',
            'message'        => $res['message'],
            'grade'          => $res['grade'],
            'original_grade' => $res['original_grade'],
            'remedial_date'  => $res['remedial_date'],
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'ไม่รู้จักคำสั่ง (action) ที่ร้องขอ'], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('[PP5 api_course] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาดในการประมวลผล: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
