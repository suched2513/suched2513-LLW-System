<?php
namespace PP5\App\Requests;

/**
 * CourseRequest — คลาสตรวจสอบความถูกต้องของข้อมูล (Form Request Validation)
 * สำหรับการตั้งค่ารายวิชา ปพ.5, ชิ้นงาน/รายการคะแนน, การเช็คชื่อ และการกรอกคะแนน
 */
class CourseRequest
{
    /**
     * ตรวจสอบสัดส่วนคะแนนเก็บและคะแนนปลายภาค
     */
    public static function validateCourseRatio(array $input, string $subjectType = 'basic'): array
    {
        $errors       = [];
        $ratioCollect = (int)($input['ratio_collect'] ?? 70);
        $ratioFinal   = (int)($input['ratio_final'] ?? 30);

        if ($subjectType !== 'activity') {
            if ($ratioCollect < 0 || $ratioFinal < 0 || ($ratioCollect + $ratioFinal) !== 100) {
                $errors[] = "สัดส่วนคะแนนเก็บ ({$ratioCollect}) + คะแนนปลายภาค ({$ratioFinal}) ต้องรวมกันได้ 100 คะแนนพอดี";
            }
        } else {
            $ratioCollect = 100;
            $ratioFinal   = 0;
        }

        return [
            'valid'  => empty($errors),
            'errors' => $errors,
            'data'   => [
                'ratio_collect' => $ratioCollect,
                'ratio_final'   => $ratioFinal,
            ],
        ];
    }

    /**
     * ตรวจสอบข้อมูลชิ้นงาน / รายการคะแนน (pp5_score_items)
     */
    public static function validateScoreItem(array $input): array
    {
        $errors      = [];
        $id          = (int)($input['id'] ?? 0);
        $phase       = trim((string)($input['phase'] ?? 'before_mid'));
        $name        = trim((string)($input['name'] ?? ''));
        $maxScore    = (float)($input['max_score'] ?? 0);
        $indicatorId = (int)($input['indicator_id'] ?? 0);
        $sortOrder   = max(1, (int)($input['sort_order'] ?? 1));

        if (!in_array($phase, ['before_mid', 'midterm', 'after_mid', 'final'], true)) {
            $errors[] = 'หมวดหมู่ช่วงคะแนนไม่ถูกต้อง';
        }
        if ($name === '') {
            $errors[] = 'กรุณาระบุชื่อชิ้นงานหรือการทดสอบ';
        }
        if ($maxScore <= 0 || $maxScore > 100) {
            $errors[] = 'คะแนนเต็มของชิ้นงานต้องมากกว่า 0 และไม่เกิน 100 คะแนน';
        }

        return [
            'valid'  => empty($errors),
            'errors' => $errors,
            'data'   => [
                'id'           => $id,
                'phase'        => $phase,
                'name'         => $name,
                'max_score'    => round($maxScore, 2),
                'indicator_id' => $indicatorId > 0 ? $indicatorId : null,
                'sort_order'   => $sortOrder,
            ],
        ];
    }

    /**
     * ตรวจสอบการกรอกคะแนนรายรายการ (ห้ามติดลบ ห้ามเกินคะแนนเต็ม รองรับค่าว่าง = NULL ติด ร)
     */
    public static function validateScoreValue($rawScore, float $maxScore): array
    {
        if ($rawScore === null || trim((string)$rawScore) === '' || strtoupper(trim((string)$rawScore)) === 'ร') {
            return [
                'valid' => true,
                'error' => null,
                'score' => null,
            ];
        }

        $str = trim((string)$rawScore);
        if (!is_numeric($str)) {
            return [
                'valid' => false,
                'error' => "ค่าคะแนน '{$str}' ต้องเป็นตัวเลข หรือเว้นว่างไว้กรณีติด ร",
                'score' => null,
            ];
        }

        $val = round((float)$str, 2);
        if ($val < 0.0) {
            return [
                'valid' => false,
                'error' => 'ห้ามกรอกคะแนนติดลบ',
                'score' => null,
            ];
        }
        if ($val > ($maxScore + 0.0001)) {
            return [
                'valid' => false,
                'error' => "ห้ามกรอกเกินคะแนนเต็ม ({$maxScore} คะแนน)",
                'score' => null,
            ];
        }

        return [
            'valid' => true,
            'error' => null,
            'score' => $val,
        ];
    }

    /**
     * ตรวจสอบการบันทึกเวลาเรียนประจำคาบ
     */
    public static function validateAttendanceSession(array $input): array
    {
        $errors      = [];
        $sessionDate = trim((string)($input['session_date'] ?? ''));
        $period      = (int)($input['period'] ?? 1);
        $hours       = (float)($input['hours'] ?? 1.0);
        $topic       = trim((string)($input['topic'] ?? ''));
        $statuses    = is_array($input['statuses'] ?? null) ? $input['statuses'] : [];
        $remarks     = is_array($input['remarks'] ?? null) ? $input['remarks'] : [];

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $sessionDate)) {
            $errors[] = 'กรุณาเลือกวันที่สอนให้ถูกต้อง';
        }
        if ($period < 1 || $period > 10) {
            $errors[] = 'คาบเรียนต้องอยู่ระหว่างคาบที่ 1 ถึง 10';
        }
        if ($hours <= 0 || $hours > 8) {
            $errors[] = 'จำนวนชั่วโมงเรียนต่อครั้งต้องอยู่ระหว่าง 0.5 ถึง 8 ชั่วโมง';
        }

        return [
            'valid'  => empty($errors),
            'errors' => $errors,
            'data'   => [
                'session_date' => $sessionDate,
                'period'       => $period,
                'hours'        => round($hours, 1),
                'topic'        => $topic !== '' ? $topic : null,
                'statuses'     => $statuses,
                'remarks'      => $remarks,
            ],
        ];
    }
}
