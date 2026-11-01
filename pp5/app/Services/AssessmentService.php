<?php
namespace PP5\App\Services;

use PDO;

/**
 * AssessmentService — บริการจัดการการประเมินคุณลักษณะอันพึงประสงค์ (8 ข้อ),
 * การอ่าน คิดวิเคราะห์ และเขียน (5 ข้อ) และสมรรถนะสำคัญของผู้เรียน (5 ด้าน)
 * ตามหลักสูตรแกนกลางการศึกษาขั้นพื้นฐาน พ.ศ. 2551
 */
class AssessmentService
{
    private PDO $pdo;
    private GradeCalculator $gradeCalc;
    private ActivityLogger $logger;

    /**
     * คุณลักษณะอันพึงประสงค์ 8 ข้อ ตามหลักสูตรแกนกลางฯ 2551
     */
    public const CHARACTERISTIC_ITEMS = [
        1 => ['short' => '1. รักชาติ ศาสน์ กษัตริย์', 'full' => 'ข้อ 1 รักชาติ ศาสน์ กษัตริย์'],
        2 => ['short' => '2. ซื่อสัตย์สุจริต',       'full' => 'ข้อ 2 ซื่อสัตย์สุจริต'],
        3 => ['short' => '3. มีวินัย',               'full' => 'ข้อ 3 มีวินัย'],
        4 => ['short' => '4. ใฝ่เรียนรู้',            'full' => 'ข้อ 4 ใฝ่เรียนรู้'],
        5 => ['short' => '5. อยู่อย่างพอเพียง',       'full' => 'ข้อ 5 อยู่อย่างพอเพียง'],
        6 => ['short' => '6. มุ่งมั่นในการทำงาน',     'full' => 'ข้อ 6 มุ่งมั่นในการทำงาน'],
        7 => ['short' => '7. รักความเป็นไทย',         'full' => 'ข้อ 7 รักความเป็นไทย'],
        8 => ['short' => '8. มีจิตสาธารณะ',           'full' => 'ข้อ 8 มีจิตสาธารณะ'],
    ];

    /**
     * การอ่าน คิดวิเคราะห์ และเขียน 5 ข้อ ตามหลักสูตรแกนกลางฯ 2551
     */
    public const RTW_ITEMS = [
        1 => ['short' => '1. อ่านเพื่อหาข้อมูลสารสนเทศ',      'full' => 'ข้อ 1 สามารถอ่านเพื่อการศึกษา ค้นคว้า เพิ่มพูนความรู้ประสบการณ์และการประยุกต์ใช้ในชีวิตประจำวัน'],
        2 => ['short' => '2. จับประเด็นสำคัญ/เชื่อมโยง',      'full' => 'ข้อ 2 สามารถจับประเด็นสำคัญ ลำดับเหตุการณ์จากการอ่านสื่อที่มีความซับซ้อน'],
        3 => ['short' => '3. วิเคราะห์/สังเคราะห์/วิพากษ์',   'full' => 'ข้อ 3 สามารถวิเคราะห์ สังเคราะห์ สรุปความ และวิพากษ์วิจารณ์เรื่องที่อ่านได้อย่างมีเหตุผล'],
        4 => ['short' => '4. ประเมินคุณค่า/แนวคิดจากการอ่าน', 'full' => 'ข้อ 4 สามารถประเมินความน่าเชื่อถือ คุณค่า แนวคิดที่ได้จากการอ่านอย่างหลากหลาย'],
        5 => ['short' => '5. เขียนสื่อสารสร้างสรรค์ถูกต้อง',   'full' => 'ข้อ 5 สามารถแสดงความคิดเห็นโต้แย้ง สรุป ด้วยการเขียนสื่อสารในรูปแบบต่างๆ ได้อย่างสร้างสรรค์'],
    ];

    /**
     * สมรรถนะสำคัญของผู้เรียน 5 ด้าน ตามหลักสูตรแกนกลางฯ 2551
     */
    public const COMPETENCY_ITEMS = [
        1 => ['short' => '1. ความสามารถในการสื่อสาร',        'full' => 'ด้านที่ 1 ความสามารถในการสื่อสาร'],
        2 => ['short' => '2. ความสามารถในการคิด',            'full' => 'ด้านที่ 2 ความสามารถในการคิด'],
        3 => ['short' => '3. ความสามารถในการแก้ปัญหา',       'full' => 'ด้านที่ 3 ความสามารถในการแก้ปัญหา'],
        4 => ['short' => '4. ความสามารถในการใช้ทักษะชีวิต',   'full' => 'ด้านที่ 4 ความสามารถในการใช้ทักษะชีวิต'],
        5 => ['short' => '5. ความสามารถในการใช้เทคโนโลยี',    'full' => 'ด้านที่ 5 ความสามารถในการใช้เทคโนโลยี'],
    ];

    public function __construct(PDO $pdo)
    {
        $this->pdo       = $pdo;
        $this->gradeCalc = new GradeCalculator($pdo);
        $this->logger    = new ActivityLogger($pdo);
    }

    /**
     * คืนรายการหัวข้อประเมินตามประเภท (characteristic | rtw | competency)
     */
    public function getItemsMeta(string $type): array
    {
        return match ($type) {
            'characteristic' => self::CHARACTERISTIC_ITEMS,
            'rtw'            => self::RTW_ITEMS,
            'competency'     => self::COMPETENCY_ITEMS,
            default          => self::CHARACTERISTIC_ITEMS,
        };
    }

    /**
     * คืนชื่อตารางฐานข้อมูลตามประเภทการประเมิน
     */
    private function getTableName(string $type): string
    {
        return match ($type) {
            'characteristic' => 'pp5_characteristic_assessments',
            'rtw'            => 'pp5_rtw_assessments',
            'competency'     => 'pp5_competency_assessments',
            default          => throw new \InvalidArgumentException('Invalid assessment type'),
        };
    }

    /**
     * ดึงกฎการสรุปผลจาก pp5_school_settings (mode หรือ mean, และ zero_item_forces_fail)
     */
    public function getAssessmentSettings(): array
    {
        try {
            $stmt = $this->pdo->query("
                SELECT `assessment_summary_mode`, `zero_item_forces_fail`
                FROM `pp5_school_settings`
                ORDER BY `id` ASC LIMIT 1
            ");
            $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : false;
        } catch (\Throwable $e) {
            $row = false;
        }

        return [
            'method'                => ($row && ($row['assessment_summary_mode'] ?? 'mode') === 'mean') ? 'mean' : 'mode',
            'zero_item_forces_fail' => $row ? ((int)($row['zero_item_forces_fail'] ?? 1) === 1) : true,
        ];
    }

    /**
     * ดึงข้อมูลผลประเมินรายข้อของนักเรียนทั้งห้อง พร้อมคำนวณผลสรุปรายคน
     *
     * @return array [
     *   'grid'      => [student_id => [item_no => int|null]],
     *   'summaries' => [student_id => ['level' => int|null, 'text' => string, 'filled_count' => int, 'total_items' => int]],
     *   'dist'      => [3 => int, 2 => int, 1 => int, 0 => int, 'incomplete' => int],
     * ]
     */
    public function getCourseAssessments(int $courseId, string $type, array $studentIds): array
    {
        $table     = $this->getTableName($type);
        $itemsMeta = $this->getItemsMeta($type);
        $itemCount = count($itemsMeta);
        $settings  = $this->getAssessmentSettings();

        $stmt = $this->pdo->prepare("
            SELECT `student_id`, `item_no`, `level`
            FROM `{$table}`
            WHERE `course_id` = ?
        ");
        $stmt->execute([$courseId]);

        $grid = [];
        foreach ($studentIds as $sid) {
            $sid = (int)$sid;
            $grid[$sid] = [];
            foreach (array_keys($itemsMeta) as $itemNo) {
                $grid[$sid][$itemNo] = null;
            }
        }

        while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $sid    = (int)$r['student_id'];
            $itemNo = (int)$r['item_no'];
            if (isset($grid[$sid]) && array_key_exists($itemNo, $grid[$sid])) {
                $grid[$sid][$itemNo] = $r['level'] === null ? null : (int)$r['level'];
            }
        }

        $summaries = [];
        $dist      = [3 => 0, 2 => 0, 1 => 0, 0 => 0, 'incomplete' => 0];

        $calcSettings = [
            'assessment_summary_mode' => $settings['method'] ?? 'mode',
            'zero_item_forces_fail'   => $settings['zero_item_forces_fail'] ?? true,
        ];

        foreach ($studentIds as $sid) {
            $sid    = (int)$sid;
            $levels = $grid[$sid] ?? [];
            $filled = array_filter($levels, fn($v) => $v !== null);

            if (count($filled) < $itemCount) {
                // หากยังประเมินไม่ครบทุกข้อ ให้คำนวณผลเบื้องต้นจากข้อที่กรอกแล้วแต่ระบุว่ายังไม่ครบ
                $partialLevel = !empty($filled)
                    ? $this->gradeCalc->summarizeAssessmentLevels($filled, $calcSettings)
                    : null;
                $summaries[$sid] = [
                    'level'        => $partialLevel,
                    'text'         => $partialLevel !== null ? $this->gradeCalc->levelToText($partialLevel) : '-',
                    'is_complete'  => false,
                    'filled_count' => count($filled),
                    'total_items'  => $itemCount,
                ];
                $dist['incomplete']++;
            } else {
                $finalLevel = $this->gradeCalc->summarizeAssessmentLevels($levels, $calcSettings);
                $summaries[$sid] = [
                    'level'        => $finalLevel,
                    'text'         => $this->gradeCalc->levelToText($finalLevel),
                    'is_complete'  => true,
                    'filled_count' => $itemCount,
                    'total_items'  => $itemCount,
                ];
                if ($finalLevel !== null && isset($dist[$finalLevel])) {
                    $dist[$finalLevel]++;
                }
            }
        }

        return [
            'grid'      => $grid,
            'summaries' => $summaries,
            'dist'      => $dist,
            'settings'  => $settings,
        ];
    }

    /**
     * บันทึกระดับผลการประเมิน (0–3) แบบกลุ่ม (รองรับทั้งแก้ทีละช่อง, กรอกทั้งคอลัมน์, กรอกทั้งห้อง และวางจาก Excel)
     *
     * @param array $entries [['student_id' => int, 'item_no' => int, 'level' => 0|1|2|3|''|null], ...]
     */
    public function saveBatchAssessments(int $courseId, string $type, array $entries, array $allStudentIds, ?int $actorId = null): array
    {
        $table     = $this->getTableName($type);
        $itemsMeta = $this->getItemsMeta($type);
        $maxItemNo = count($itemsMeta);

        $errors     = [];
        $savedCount = 0;

        $this->pdo->beginTransaction();
        try {
            $stmtUpsert = $this->pdo->prepare("
                INSERT INTO `{$table}` (`course_id`, `student_id`, `item_no`, `level`)
                VALUES (?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    `level`      = VALUES(`level`),
                    `updated_at` = CURRENT_TIMESTAMP
            ");
            $stmtDelete = $this->pdo->prepare("
                DELETE FROM `{$table}`
                WHERE `course_id` = ? AND `student_id` = ? AND `item_no` = ?
            ");

            foreach ($entries as $entry) {
                $studentId = (int)($entry['student_id'] ?? 0);
                $itemNo    = (int)($entry['item_no'] ?? 0);
                $rawLevel  = $entry['level'] ?? null;

                if ($studentId <= 0 || $itemNo < 1 || $itemNo > $maxItemNo) {
                    continue;
                }

                if ($rawLevel === '' || $rawLevel === null || $rawLevel === '-') {
                    $stmtDelete->execute([$courseId, $studentId, $itemNo]);
                    $savedCount++;
                    continue;
                }

                if (!is_numeric($rawLevel) || !in_array((int)$rawLevel, [0, 1, 2, 3], true)) {
                    $errors[] = "ข้อที่ {$itemNo}: ระดับผลการประเมินต้องเป็น 0, 1, 2 หรือ 3 เท่านั้น (ค่าที่ระบุ: {$rawLevel})";
                    continue;
                }

                $stmtUpsert->execute([$courseId, $studentId, $itemNo, (int)$rawLevel]);
                $savedCount++;
            }

            if (!empty($errors) && $savedCount === 0) {
                $this->pdo->rollBack();
                return ['ok' => false, 'errors' => $errors, 'saved_count' => 0];
            }

            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }

        if ($savedCount > 0) {
            $this->logger->log(
                'assessment.save_' . $type,
                "บันทึกผลประเมิน ({$type}) จำนวน {$savedCount} ช่อง",
                $table,
                $courseId,
                $courseId,
                null,
                ['type' => $type, 'saved_count' => $savedCount]
            );
        }

        $latest = $this->getCourseAssessments($courseId, $type, $allStudentIds);

        return [
            'ok'          => true,
            'saved_count' => $savedCount,
            'errors'      => $errors,
            'summaries'   => $latest['summaries'],
            'dist'        => $latest['dist'],
        ];
    }

    /**
     * ดึงข้อมูลผลประเมินครบทั้ง 3 ด้านของรายวิชาในครั้งเดียว (ใช้ในหน้าสรุปและรายงาน PDF ปพ.5)
     */
    public function getAllCourseAssessments(int $courseId, array $studentIds): array
    {
        return [
            'characteristic' => $this->getCourseAssessments($courseId, 'characteristic', $studentIds),
            'rtw'            => $this->getCourseAssessments($courseId, 'rtw', $studentIds),
            'competency'     => $this->getCourseAssessments($courseId, 'competency', $studentIds),
        ];
    }
}
