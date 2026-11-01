<?php
namespace PP5\App\Services;

use PDO;

/**
 * Pp5UnitTestSuite — ชุดทดสอบอัตโนมัติ (Unit Tests) ครอบคลุมกฎการคำนวณ ปพ.5 ทั้ง 9 ข้อ
 *
 * สามารถรันได้ทั้งผ่านหน้าเว็บ (`/pp5/unit_tests.php`) และผ่าน CLI (`php pp5/unit_tests.php`)
 */
class Pp5UnitTestSuite
{
    private PDO $pdo;
    private GradeCalculator $gradeCalc;
    private AttendanceService $attendanceService;

    public function __construct(PDO $pdo)
    {
        $this->pdo               = $pdo;
        $this->gradeCalc         = new GradeCalculator($pdo);
        $this->attendanceService = new AttendanceService($pdo);
    }

    /**
     * รันกรณีทดสอบทั้งหมดและคืนผลลัพธ์ละเอียด
     */
    public function runAll(): array
    {
        $results = [];
        $passed  = 0;
        $failed  = 0;

        $assert = function (string $group, string $title, bool $condition, string $expected, string $actual) use (&$results, &$passed, &$failed): void {
            if ($condition) {
                $passed++;
            } else {
                $failed++;
            }
            $results[] = [
                'group'    => $group,
                'title'    => $title,
                'passed'   => $condition,
                'expected' => $expected,
                'actual'   => $actual,
            ];
        };

        // ─── กฎข้อที่ 1: คะแนนรวม = คะแนนเก็บ + ปลายภาค ปัดเป็นจำนวนเต็ม (Half-up) ก่อนตัดเกรด ───
        $sampleItems = [
            ['id' => 101, 'phase' => 'before_mid', 'name' => 'เก็บก่อนกลางภาค', 'max_score' => 25],
            ['id' => 102, 'phase' => 'midterm',    'name' => 'สอบกลางภาค',      'max_score' => 20],
            ['id' => 103, 'phase' => 'after_mid',  'name' => 'เก็บหลังกลางภาค', 'max_score' => 25],
            ['id' => 104, 'phase' => 'final',      'name' => 'สอบปลายภาค',      'max_score' => 30],
        ];

        $calc1 = $this->gradeCalc->calculateStudentGrade(
            $sampleItems,
            [101 => 20.25, 102 => 18.0, 103 => 21.25, 104 => 20.0], // รวมดิบ = 79.50 -> ปัดเป็น 80 -> เกรด 4
            95.0,
            'basic'
        );
        $assert(
            'กฎข้อที่ 1: การรวมคะแนนและปัดเศษ',
            'คะแนนดิบ 79.50 (เก็บ 59.5 + ปลายภาค 20.0) ปัดครึ่งขึ้นเป็น 80 ได้เกรด 4',
            $calc1['total_score'] === 80 && $calc1['grade'] === '4',
            'total=80, grade=4',
            "total={$calc1['total_score']}, grade={$calc1['grade']}"
        );

        $calc2 = $this->gradeCalc->calculateStudentGrade(
            $sampleItems,
            [101 => 20.20, 102 => 18.0, 103 => 21.20, 104 => 20.0], // รวมดิบ = 79.40 -> ปัดเป็น 79 -> เกรด 3.5
            95.0,
            'basic'
        );
        $assert(
            'กฎข้อที่ 1: การรวมคะแนนและปัดเศษ',
            'คะแนนดิบ 79.40 ปัดลงเป็น 79 ได้เกรด 3.5',
            $calc2['total_score'] === 79 && $calc2['grade'] === '3.5',
            'total=79, grade=3.5',
            "total={$calc2['total_score']}, grade={$calc2['grade']}"
        );

        // ─── กฎข้อที่ 2: แจ้งเตือนเมื่อคะแนนเต็มรวมของ score_items ไม่ตรงกับสัดส่วน (รวมต้องได้ 100) ───
        $ratioValid = $this->gradeCalc->validateScoreItemsRatio($sampleItems, 70, 30, 'basic');
        $assert(
            'กฎข้อที่ 2: ตรวจสอบสัดส่วนคะแนน 100 คะแนน',
            'เมื่อรายการคะแนนเก็บ=70 และปลายภาค=30 ตรงตามสัดส่วน 70:30 ต้องผ่าน (is_valid=true)',
            $ratioValid['is_valid'] === true && empty($ratioValid['warnings']),
            'is_valid=true',
            'is_valid=' . ($ratioValid['is_valid'] ? 'true' : 'false')
        );

        $incompleteItems = [
            ['id' => 101, 'phase' => 'before_mid', 'name' => 'งาน 1', 'max_score' => 20],
            ['id' => 104, 'phase' => 'final',      'name' => 'ปลายภาค', 'max_score' => 25],
        ];
        $ratioInvalid = $this->gradeCalc->validateScoreItemsRatio($incompleteItems, 70, 30, 'basic');
        $assert(
            'กฎข้อที่ 2: ตรวจสอบสัดส่วนคะแนน 100 คะแนน',
            'เมื่อคะแนนเต็มรวมได้เพียง 45/100 ไม่ตรงกับ 70:30 ระบบต้องแจ้งเตือน (is_valid=false)',
            $ratioInvalid['is_valid'] === false && count($ratioInvalid['warnings']) >= 2,
            'is_valid=false พร้อมข้อความเตือน',
            'is_valid=' . ($ratioInvalid['is_valid'] ? 'true' : 'false') . ' (' . count($ratioInvalid['warnings']) . ' warnings)'
        );

        // ─── กฎข้อที่ 3: เกณฑ์การตัดเกรด 8 ระดับ (4, 3.5, 3, 2.5, 2, 1.5, 1, 0) และเกณฑ์ที่ Admin กำหนดเอง ───
        $defaultCriteria = [
            ['min_score' => 80, 'max_score' => 100, 'grade' => '4'],
            ['min_score' => 75, 'max_score' => 79,  'grade' => '3.5'],
            ['min_score' => 70, 'max_score' => 74,  'grade' => '3'],
            ['min_score' => 65, 'max_score' => 69,  'grade' => '2.5'],
            ['min_score' => 60, 'max_score' => 64,  'grade' => '2'],
            ['min_score' => 55, 'max_score' => 59,  'grade' => '1.5'],
            ['min_score' => 50, 'max_score' => 54,  'grade' => '1'],
            ['min_score' => 0,  'max_score' => 49,  'grade' => '0'],
        ];
        $boundaryTests = [
            100 => '4',   80 => '4',
            79  => '3.5', 75 => '3.5',
            74  => '3',   70 => '3',
            69  => '2.5', 65 => '2.5',
            64  => '2',   60 => '2',
            59  => '1.5', 55 => '1.5',
            54  => '1',   50 => '1',
            49  => '0',   0  => '0',
        ];
        $allBoundariesOk = true;
        $mismatchDetail  = '';
        foreach ($boundaryTests as $scorePt => $expGrade) {
            $got = $this->gradeCalc->mapScoreToGrade($scorePt, $defaultCriteria);
            if ($got !== $expGrade) {
                $allBoundariesOk = false;
                $mismatchDetail .= "{$scorePt}=>{$got}(คาดหวัง {$expGrade}) ";
            }
        }
        $assert(
            'กฎข้อที่ 3: เกณฑ์การตัดเกรด 8 ระดับ',
            'ทดสอบค่าขอบเขตทุกช่วงคะแนน (0–49=0, 50–54=1, 55–59=1.5, 60–64=2, 65–69=2.5, 70–74=3, 75–79=3.5, 80–100=4)',
            $allBoundariesOk,
            'ตรงทุกช่วงคะแนน 16 จุดขอบเขต',
            $allBoundariesOk ? 'ตรงทุกช่วงคะแนน 16 จุดขอบเขต' : $mismatchDetail
        );

        $customCriteria = [
            ['min_score' => 85, 'max_score' => 100, 'grade' => '4'],
            ['min_score' => 50, 'max_score' => 84,  'grade' => '1'],
            ['min_score' => 0,  'max_score' => 49,  'grade' => '0'],
        ];
        $customGot = $this->gradeCalc->mapScoreToGrade(82, $customCriteria);
        $assert(
            'กฎข้อที่ 3: เกณฑ์การตัดเกรด 8 ระดับ',
            'รองรับการปรับเปลี่ยนเกณฑ์โดย Admin โดยไม่ต้องแก้โค้ด (เช่น ตั้งเกรด 4 ที่ 85 คะแนน)',
            $customGot === '1',
            'grade=1 เมื่อได้ 82 คะแนนตามเกณฑ์ใหม่',
            "grade={$customGot}"
        );

        // ─── กฎข้อที่ 4: "มส" เวลาเรียน < 80% ตรวจก่อนตัดเกรดเสมอ และตั้งค่านับสาย/ลา/ป่วยได้ ───
        $msCalc = $this->gradeCalc->calculateStudentGrade(
            $sampleItems,
            [101 => 25, 102 => 20, 103 => 25, 104 => null], // แม้คะแนนสูงและยังไม่สอบปลายภาค (NULL) แต่เวลาเรียน 75% ต้องติด มส ก่อน ร
            75.0,
            'basic'
        );
        $assert(
            'กฎข้อที่ 4: การตรวจสอบ "มส" (< 80%)',
            'เวลาเรียน 75.0% (< 80%) ต้องตัดสินเป็น "มส" ก่อน "ร" และก่อนเกรดตัวเลขเสมอ',
            $msCalc['grade'] === 'มส' && $msCalc['is_ms'] === true,
            'grade=มส',
            "grade={$msCalc['grade']}"
        );

        $sessionsSample = [
            ['id' => 1, 'hours' => 10], // มา
            ['id' => 2, 'hours' => 4],  // สาย
            ['id' => 3, 'hours' => 4],  // ลาป่วย
            ['id' => 4, 'hours' => 2],  // ขาด
        ];
        $statusSample = [1 => 'present', 2 => 'late', 3 => 'sick', 4 => 'absent'];

        // กรณีค่าเริ่มต้น: นับ present + late + leave + sick เป็นมาเรียน (18/20 ชม. = 90% ไม่ติด มส)
        $attDefault = $this->attendanceService->calculateStudentAttendanceSummary($sessionsSample, $statusSample, [
            'count_late_as_present'  => true,
            'count_leave_as_present' => true,
            'count_sick_as_present'  => true,
        ]);
        $assert(
            'กฎข้อที่ 4: การตรวจสอบ "มส" (< 80%)',
            'ค่าเริ่มต้น (นับสาย/ลากิจ/ลาป่วยเป็นมาเรียน): เรียน 18/20 ชม. = 90.00% (ไม่ติด มส)',
            abs($attDefault['attendance_percent'] - 90.0) < 0.01 && $attDefault['is_ms'] === false,
            '90%, is_ms=false',
            "{$attDefault['attendance_percent']}%, is_ms=" . ($attDefault['is_ms'] ? 'true' : 'false')
        );

        // กรณี Admin ตั้งค่าไม่นับสายและลาป่วยเป็นเวลาเรียน (เหลือมาเรียน 10/20 ชม. = 50% ติด มส)
        $attStrict = $this->attendanceService->calculateStudentAttendanceSummary($sessionsSample, $statusSample, [
            'count_late_as_present'  => false,
            'count_leave_as_present' => false,
            'count_sick_as_present'  => false,
        ]);
        $assert(
            'กฎข้อที่ 4: การตรวจสอบ "มส" (< 80%)',
            'เมื่อ Admin ตั้งค่าไม่นับสาย/ลาเป็นเวลาเรียน: เหลือ 10/20 ชม. = 50.00% (ติด มส)',
            abs($attStrict['attendance_percent'] - 50.0) < 0.01 && $attStrict['is_ms'] === true,
            '50%, is_ms=true',
            "{$attStrict['attendance_percent']}%, is_ms=" . ($attStrict['is_ms'] ? 'true' : 'false')
        );

        // ─── กฎข้อที่ 5: "ร" เมื่อมีคะแนนรายการใดรายการหนึ่งเป็น NULL (เวลาเรียน >= 80%) ───
        $rCalc = $this->gradeCalc->calculateStudentGrade(
            $sampleItems,
            [101 => 24, 102 => 19, 103 => null, 104 => 28], // ช่อง 103 เป็น NULL
            92.0,
            'basic'
        );
        $assert(
            'กฎข้อที่ 5: การตัดสิน "ร" เมื่อคะแนนเป็น NULL',
            'เมื่อเวลาเรียนผ่าน (92%) แต่มีรายการคะแนนเป็น NULL 1 ช่อง ต้องได้ผลเป็น "ร"',
            $rCalc['grade'] === 'ร' && $rCalc['has_missing_score'] === true,
            'grade=ร, missing_count=1',
            "grade={$rCalc['grade']}, missing_count={$rCalc['missing_count']}"
        );

        // ─── กฎข้อที่ 6: วิชากิจกรรมพัฒนาผู้เรียน (type = activity) ใช้ผล "ผ" หรือ "มผ" ───
        $actPass = $this->gradeCalc->calculateStudentGrade(
            [['id' => 201, 'phase' => 'before_mid', 'name' => 'เข้าร่วมกิจกรรม', 'max_score' => 100]],
            [201 => 85],
            90.0,
            'activity'
        );
        $actFail = $this->gradeCalc->calculateStudentGrade(
            [['id' => 201, 'phase' => 'before_mid', 'name' => 'เข้าร่วมกิจกรรม', 'max_score' => 100]],
            [201 => 85],
            70.0, // เวลาเรียนต่ำกว่า 80% ในวิชากิจกรรม -> มผ
            'activity'
        );
        $assert(
            'กฎข้อที่ 6: วิชากิจกรรมพัฒนาผู้เรียน (ผ / มผ)',
            'วิชากิจกรรมให้ผลเป็น "ผ" เมื่อเวลาเรียน >= 80% และส่งงานครบ และให้ "มผ" เมื่อไม่ผ่านเกณฑ์',
            $actPass['grade'] === 'ผ' && $actFail['grade'] === 'มผ',
            'ผ่าน=ผ, ไม่ผ่าน=มผ',
            "ผ่าน={$actPass['grade']}, ไม่ผ่าน={$actFail['grade']}"
        );

        // ─── กฎข้อที่ 7: การแก้ 0 / ร / มส เก็บ original_grade และแก้ 0/ร ได้ไม่เกิน 1 ───
        $remZero = $this->gradeCalc->applyRemedialGrade('0', '3', '2026-10-15');
        $assert(
            'กฎข้อที่ 7: การแก้ 0 / ร / มส และเพดานเกรด',
            'แก้ "0" แม้คะแนนใหม่จะได้เกรด 3 ต้องถูกจำกัดผลลัพธ์ไม่เกินเกรด "1" และเก็บ original_grade="0"',
            $remZero['grade'] === '1' && $remZero['original_grade'] === '0' && $remZero['is_remedial'] === 1 && $remZero['remedial_date'] === '2026-10-15',
            'grade=1, original_grade=0, is_remedial=1',
            "grade={$remZero['grade']}, original_grade={$remZero['original_grade']}, is_remedial={$remZero['is_remedial']}"
        );

        $remR = $this->gradeCalc->applyRemedialGrade('ร', '4', '2026-10-16');
        $assert(
            'กฎข้อที่ 7: การแก้ 0 / ร / มส และเพดานเกรด',
            'แก้ "ร" แม้คะแนนรวมหลังส่งงานจะได้เกรด 4 ต้องได้ผลหลังแก้ไม่เกินเกรด "1" และเก็บ original_grade="ร"',
            $remR['grade'] === '1' && $remR['original_grade'] === 'ร',
            'grade=1, original_grade=ร',
            "grade={$remR['grade']}, original_grade={$remR['original_grade']}"
        );

        // ─── กฎข้อที่ 8: คุณลักษณะอันพึงประสงค์ / อ่านคิดเขียน / สมรรถนะ (0–3) สรุปด้วย Mode / Mean ───
        $modeTieSummary = $this->gradeCalc->summarizeAssessmentLevels([3, 3, 2, 2, 1], 'mode', true);
        $assert(
            'กฎข้อที่ 8: การสรุปผลประเมินคุณลักษณะ/อ่านคิดเขียน/สมรรถนะ (0–3)',
            'สรุปด้วยฐานนิยม (Mode): กรณีความถี่เท่ากัน ([3,3,2,2,1]) เลือกระดับที่สูงกว่าได้ 3 (ดีเยี่ยม)',
            $modeTieSummary === 3 && $this->gradeCalc->levelToText($modeTieSummary) === 'ดีเยี่ยม',
            'level=3 (ดีเยี่ยม)',
            "level={$modeTieSummary} (" . $this->gradeCalc->levelToText($modeTieSummary) . ')'
        );

        $zeroFailSummary = $this->gradeCalc->summarizeAssessmentLevels([3, 3, 3, 0, 3], 'mode', true);
        $assert(
            'กฎข้อที่ 8: การสรุปผลประเมินคุณลักษณะ/อ่านคิดเขียน/สมรรถนะ (0–3)',
            'กฎ zero_item_forces_fail: หากมีข้อใดได้ 0 (ไม่ผ่าน) ผลสรุปรวมต้องเป็น 0 (ไม่ผ่าน) ทันที',
            $zeroFailSummary === 0 && $this->gradeCalc->levelToText($zeroFailSummary) === 'ไม่ผ่าน',
            'level=0 (ไม่ผ่าน)',
            "level={$zeroFailSummary} (" . $this->gradeCalc->levelToText($zeroFailSummary) . ')'
        );

        $meanSummary = $this->gradeCalc->summarizeAssessmentLevels([3, 2, 2, 2, 2], 'mean', true);
        $assert(
            'กฎข้อที่ 8: การสรุปผลประเมินคุณลักษณะ/อ่านคิดเขียน/สมรรถนะ (0–3)',
            'รองรับโหมดค่าเฉลี่ยปัดเศษ (Mean): [3,2,2,2,2] เฉลี่ย 2.2 ปัดเป็นระดับ 2 (ดี)',
            $meanSummary === 2 && $this->gradeCalc->levelToText($meanSummary) === 'ดี',
            'level=2 (ดี)',
            "level={$meanSummary} (" . $this->gradeCalc->levelToText($meanSummary) . ')'
        );

        // ─── กฎข้อที่ 9: การเข้ารหัสเลขบัตรประชาชน 13 หลักตาม PDPA (CitizenIdCrypto) ───
        $sampleCid = '1330400123456';
        $cipher    = CitizenIdCrypto::encrypt($sampleCid);
        $plain     = CitizenIdCrypto::decrypt($cipher);
        $masked    = CitizenIdCrypto::mask($sampleCid);
        $assert(
            'กฎข้อที่ 9: ความปลอดภัยข้อมูลส่วนบุคคล PDPA (Encrypted Citizen ID)',
            'เข้ารหัส AES-256-CBC + HMAC-SHA256 และถอดรหัสกลับได้ตรงกัน พร้อม Masking สำหรับแสดงผล',
            $cipher !== $sampleCid && $plain === $sampleCid && $masked === '1-3304-XXXXX-56-6',
            'plain=1330400123456, masked=1-3304-XXXXX-56-6',
            "plain={$plain}, masked={$masked}"
        );

        return [
            'total'   => $passed + $failed,
            'passed'  => $passed,
            'failed'  => $failed,
            'all_ok'  => ($failed === 0),
            'results' => $results,
        ];
    }
}
