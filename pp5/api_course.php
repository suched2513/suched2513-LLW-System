<?php
/**
 * pp5/api_course.php — REST JSON API สำหรับตารางกรอกคะแนนแบบ Excel & คำนวณเกรดอัตโนมัติ (ระยะที่ 2)
 *
 * รองรับ Action:
 * - save_scores        : บันทึกคะแนนรายช่องหรือแบบกลุ่ม (Clipboard Paste) พร้อมคำนวณเกรดทันที
 * - recalculate_grades : คำนวณคะแนนรวม เวลาเรียน % และเกรดของนักเรียนทั้งห้องใหม่ทั้งหมด
 */

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/_bootstrap.php';

use PP5\App\Policies\Pp5Policy;
use PP5\App\Services\CourseService;

pp5_require_login(true);

$policy        = new Pp5Policy($pdo);
$courseService = new CourseService($pdo);

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

// ตรวจสอบสิทธิ์ด้วย Pp5Policy
if (!$policy->canEditCourse($course)) {
    http_response_code(403);
    echo json_encode([
        'status'  => 'error',
        'message' => 'คุณไม่มีสิทธิ์แก้ไขคะแนนในรายวิชานี้ หรือรายวิชาถูกล็อก/ส่งอนุมัติแล้ว',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    if ($action === 'save_scores') {
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

        // ดึงสถิติการกระจายเกรดล่าสุดของทั้งห้องกลับไปด้วย
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

    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'ไม่รู้จักคำสั่ง (action) ที่ร้องขอ'], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('[PP5 api_course] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาดในการประมวลผล กรุณาลองใหม่อีกครั้ง'], JSON_UNESCAPED_UNICODE);
}
