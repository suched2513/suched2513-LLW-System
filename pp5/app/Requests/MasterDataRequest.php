<?php
namespace PP5\App\Requests;

use PP5\App\Services\CitizenIdCrypto;

/**
 * MasterDataRequest — คลาสตรวจสอบความถูกต้องของข้อมูล (Form Request Validation)
 * สำหรับการจัดการข้อมูลพื้นฐานของระบบ ปพ.5 ออนไลน์
 */
class MasterDataRequest
{
    /**
     * ตรวจสอบข้อมูลตั้งค่าโรงเรียนและเกณฑ์การประเมิน
     */
    public static function validateSchoolSettings(array $input): array
    {
        $errors = [];

        $schoolName = trim((string)($input['school_name'] ?? ''));
        if ($schoolName === '') {
            $errors[] = 'กรุณาระบุชื่อโรงเรียน';
        }

        $minAtt = (float)($input['min_attendance_percent'] ?? 80);
        if ($minAtt < 50 || $minAtt > 100) {
            $errors[] = 'เกณฑ์เวลาเรียนขั้นต่ำต้องอยู่ระหว่าง 50% ถึง 100%';
        }

        $summaryMode = (string)($input['assessment_summary_mode'] ?? 'mode');
        if (!in_array($summaryMode, ['mode', 'mean'], true)) {
            $summaryMode = 'mode';
        }

        return [
            'valid'  => empty($errors),
            'errors' => $errors,
            'data'   => [
                'school_name'              => $schoolName,
                'school_code'              => trim((string)($input['school_code'] ?? '1033720344')),
                'affiliation'              => trim((string)($input['affiliation'] ?? '')),
                'province'                 => trim((string)($input['province'] ?? 'ศรีสะเกษ')),
                'director_name'            => trim((string)($input['director_name'] ?? '')),
                'director_position'        => trim((string)($input['director_position'] ?? 'ผู้อำนวยการสถานศึกษา')),
                'deputy_academic_name'     => trim((string)($input['deputy_academic_name'] ?? '')),
                'deputy_academic_position' => trim((string)($input['deputy_academic_position'] ?? 'รองผู้อำนวยการกลุ่มบริหารวิชาการ')),
                'measurement_head_name'    => trim((string)($input['measurement_head_name'] ?? '')),
                'min_attendance_percent'   => $minAtt,
                'count_late_as_present'    => !empty($input['count_late_as_present']) ? 1 : 0,
                'count_leave_as_present'   => !empty($input['count_leave_as_present']) ? 1 : 0,
                'count_sick_as_present'    => !empty($input['count_sick_as_present']) ? 1 : 0,
                'assessment_summary_mode'  => $summaryMode,
                'zero_item_forces_fail'    => !empty($input['zero_item_forces_fail']) ? 1 : 0,
                'mean_min_excellent'       => (float)($input['mean_min_excellent'] ?? 2.50),
                'mean_min_good'            => (float)($input['mean_min_good'] ?? 1.50),
                'mean_min_pass'            => (float)($input['mean_min_pass'] ?? 1.00),
            ],
        ];
    }

    /**
     * ตรวจสอบข้อมูลเกณฑ์การตัดเกรด (pp5_grade_criteria)
     */
    public static function validateGradeCriteria(array $criteriaInput): array
    {
        $errors = [];
        $clean  = [];

        foreach ($criteriaInput as $grade => $bounds) {
            $min = isset($bounds['min_score']) ? (int)$bounds['min_score'] : -1;
            $max = isset($bounds['max_score']) ? (int)$bounds['max_score'] : -1;
            if ($min < 0 || $max > 100 || $min > $max) {
                $errors[] = "ช่วงคะแนนของเกรด {$grade} ไม่ถูกต้อง ({$min} - {$max})";
            }
            $clean[(string)$grade] = [
                'min_score' => $min,
                'max_score' => $max,
            ];
        }

        return [
            'valid'  => empty($errors),
            'errors' => $errors,
            'data'   => $clean,
        ];
    }

    /**
     * ตรวจสอบข้อมูลปีการศึกษาและภาคเรียน
     */
    public static function validateAcademicTerm(array $input): array
    {
        $errors = [];
        $year   = (int)($input['academic_year'] ?? 0);
        $sem    = (int)($input['semester'] ?? 0);
        $start  = trim((string)($input['start_date'] ?? ''));
        $end    = trim((string)($input['end_date'] ?? ''));

        if ($year < 2560 || $year > 2600) {
            $errors[] = 'ปีการศึกษา (พ.ศ.) ต้องอยู่ระหว่าง 2560 ถึง 2600';
        }
        if (!in_array($sem, [1, 2], true)) {
            $errors[] = 'ภาคเรียนต้องเป็น 1 หรือ 2 เท่านั้น';
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $end)) {
            $errors[] = 'รูปแบบวันเปิด-ปิดภาคเรียนไม่ถูกต้อง';
        } elseif ($start > $end) {
            $errors[] = 'วันเปิดภาคเรียนต้องมาก่อนวันปิดภาคเรียน';
        }

        return [
            'valid'  => empty($errors),
            'errors' => $errors,
            'data'   => [
                'academic_year' => $year,
                'semester'      => $sem,
                'start_date'    => $start,
                'end_date'      => $end,
                'is_active'     => !empty($input['is_active']) ? 1 : 0,
            ],
        ];
    }

    /**
     * ตรวจสอบข้อมูลรายวิชา (pp5_subjects)
     */
    public static function validateSubject(array $input): array
    {
        $errors = [];
        $code   = trim((string)($input['code'] ?? ''));
        $name   = trim((string)($input['name'] ?? ''));
        $deptId = (int)($input['department_id'] ?? 0);
        $type   = trim((string)($input['type'] ?? 'basic'));
        $credit = (float)($input['credit'] ?? 1.5);
        $hours  = (int)($input['hours'] ?? 60);
        $grade  = (int)($input['grade_level'] ?? 1);
        $desc   = trim((string)($input['description'] ?? ''));

        if ($code === '') {
            $errors[] = 'กรุณาระบุรหัสวิชา (เช่น ค21101)';
        }
        if ($name === '') {
            $errors[] = 'กรุณาระบุชื่อรายวิชา';
        }
        if ($deptId <= 0) {
            $errors[] = 'กรุณาเลือกกลุ่มสาระการเรียนรู้';
        }
        if (!in_array($type, ['basic', 'additional', 'activity'], true)) {
            $errors[] = 'ประเภทรายวิชาไม่ถูกต้อง';
        }
        if ($type === 'activity') {
            $credit = 0.0;
        } elseif ($credit < 0 || $credit > 6.0) {
            $errors[] = 'หน่วยกิตต้องอยู่ระหว่าง 0.0 ถึง 6.0';
        }
        if ($hours < 10 || $hours > 240) {
            $errors[] = 'จำนวนชั่วโมงเรียนต่อภาคเรียนต้องอยู่ระหว่าง 10 ถึง 240 ชั่วโมง';
        }
        if ($grade < 1 || $grade > 6) {
            $errors[] = 'ระดับชั้นต้องเป็น ม.1 ถึง ม.6 (1-6)';
        }

        return [
            'valid'  => empty($errors),
            'errors' => $errors,
            'data'   => [
                'id'            => (int)($input['id'] ?? 0),
                'code'          => $code,
                'name'          => $name,
                'department_id' => $deptId,
                'type'          => $type,
                'credit'        => $credit,
                'hours'         => $hours,
                'grade_level'   => $grade,
                'description'   => $desc,
            ],
        ];
    }

    /**
     * ตรวจสอบข้อมูลนักเรียนรายบุคคล
     */
    public static function validateStudent(array $input): array
    {
        $errors      = [];
        $studentCode = trim((string)($input['student_code'] ?? ''));
        if (ctype_digit($studentCode) && strlen($studentCode) < 5) {
            $studentCode = str_pad($studentCode, 5, '0', STR_PAD_LEFT);
        }

        $prefix    = trim((string)($input['prefix'] ?? ''));
        $firstName = trim((string)($input['first_name'] ?? ''));
        $lastName  = trim((string)($input['last_name'] ?? ''));
        $classroom = preg_replace('/^ม\.+/', 'ม.', trim((string)($input['classroom'] ?? '')));
        $number    = max(1, (int)($input['student_number'] ?? 1));
        $citizenId = preg_replace('/[^0-9]/', '', trim((string)($input['citizen_id'] ?? '')));
        $status    = trim((string)($input['status'] ?? 'active'));

        if ($studentCode === '' || !preg_match('/^\d{4,10}$/', $studentCode)) {
            $errors[] = 'รหัสประจำตัวนักเรียนต้องเป็นตัวเลข 4-10 หลัก';
        }
        if ($firstName === '') {
            $errors[] = 'กรุณาระบุชื่อนักเรียน';
        }
        if ($classroom === '') {
            $errors[] = 'กรุณาเลือกห้องเรียน';
        }
        if ($citizenId !== '' && strlen($citizenId) !== 13) {
            $errors[] = 'เลขประจำตัวประชาชนต้องมี 13 หลัก';
        }
        if (!in_array($status, ['active', 'moved', 'graduated', 'dropped'], true)) {
            $status = 'active';
        }

        $gender = trim((string)($input['gender'] ?? ''));
        if (!in_array($gender, ['male', 'female', 'unspecified'], true)) {
            $gender = in_array($prefix, ['เด็กชาย', 'ด.ช.', 'นาย'], true) ? 'male'
                : (in_array($prefix, ['เด็กหญิง', 'ด.ญ.', 'นางสาว', 'น.ส.'], true) ? 'female' : 'unspecified');
        }

        return [
            'valid'  => empty($errors),
            'errors' => $errors,
            'data'   => [
                'id'             => (int)($input['id'] ?? 0),
                'student_code'   => $studentCode,
                'prefix'         => $prefix,
                'first_name'     => $firstName,
                'last_name'      => $lastName,
                'full_name'      => trim($prefix . $firstName . ' ' . $lastName),
                'gender'         => $gender,
                'classroom'      => $classroom,
                'student_number' => $number,
                'citizen_id'     => $citizenId,
                'status'         => $status,
            ],
        ];
    }

    /**
     * ตรวจสอบการจัดวิชาเปิดสอนประจำภาคเรียน (pp5_courses: 1 วิชา × 1 ห้อง = 1 ปพ.5)
     */
    public static function validateCourse(array $input): array
    {
        $errors       = [];
        $year         = (int)($input['academic_year'] ?? 0);
        $sem          = (int)($input['semester'] ?? 0);
        $subjectId    = (int)($input['subject_id'] ?? 0);
        $classroomId  = (int)($input['classroom_id'] ?? 0);
        $teacherId    = (int)($input['teacher_user_id'] ?? 0);
        $coTeacherId  = (int)($input['co_teacher_user_id'] ?? 0);
        $ratioCollect = (int)($input['ratio_collect'] ?? 70);
        $ratioFinal   = (int)($input['ratio_final'] ?? 30);

        if ($subjectId <= 0) {
            $errors[] = 'กรุณาเลือกรายวิชา';
        }
        if ($classroomId <= 0) {
            $errors[] = 'กรุณาเลือกห้องเรียน';
        }
        if ($teacherId <= 0) {
            $errors[] = 'กรุณาเลือกครูผู้สอนหลัก';
        }
        if (($ratioCollect + $ratioFinal) !== 100 || $ratioCollect < 0 || $ratioFinal < 0) {
            $errors[] = 'สัดส่วนคะแนนเก็บ + คะแนนปลายภาค ต้องรวมกันได้ 100 คะแนนพอดี';
        }

        return [
            'valid'  => empty($errors),
            'errors' => $errors,
            'data'   => [
                'id'                 => (int)($input['id'] ?? 0),
                'academic_year'      => $year,
                'semester'           => $sem,
                'subject_id'         => $subjectId,
                'classroom_id'       => $classroomId,
                'teacher_user_id'    => $teacherId,
                'co_teacher_user_id' => $coTeacherId > 0 && $coTeacherId !== $teacherId ? $coTeacherId : null,
                'ratio_collect'      => $ratioCollect,
                'ratio_final'        => $ratioFinal,
            ],
        ];
    }
}
