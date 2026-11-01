<?php
namespace PP5\App\Services;

/**
 * GradeCalculator — บริการคำนวณคะแนนรวม ตัดเกรด ตรวจสอบ มส/ร การแก้ตัว และสรุปผลคุณลักษณะฯ
 *
 * กฎการคำนวณ (ห้ามผิด):
 * 1. คะแนนรวม = คะแนนเก็บ (ก่อนกลางภาค + กลางภาค + หลังกลางภาค) + คะแนนปลายภาค ปัดเป็นจำนวนเต็มครั้งเดียว (ROUND ครึ่งขึ้น) ก่อนตัดเกรด
 * 2. เตือนหากคะแนนเต็มรวมของ score_items ไม่ตรงกับสัดส่วนที่ตั้งไว้ (รวมต้องได้ 100)
 * 3. เกณฑ์เกรดเริ่มต้น: 80–100 = 4, 75–79 = 3.5, 70–74 = 3, 65–69 = 2.5, 60–64 = 2, 55–59 = 1.5, 50–54 = 1, 0–49 = 0
 * 4. "มส" = เวลาเรียนน้อยกว่าเกณฑ์ขั้นต่ำ (80%) ระบบตรวจ มส ก่อนตัดเกรดเสมอ
 * 5. "ร" = มีคะแนนรายการใดรายการหนึ่งเป็น NULL
 * 6. วิชากิจกรรม (type = activity) ใช้ผล "ผ" หรือ "มผ" ไม่มีเกรดตัวเลข
 * 7. การแก้ 0 / ร / มส: เก็บเกรดเดิมไว้ใน original_grade ผลหลังแก้ 0 และ ร ได้ไม่เกิน '1' และบันทึกวันที่แก้ไข
 * 8. คุณลักษณะอันพึงประสงค์ / อ่าน คิดวิเคราะห์ และเขียน / สมรรถนะ: 3=ดีเยี่ยม, 2=ดี, 1=ผ่าน, 0=ไม่ผ่าน สรุปด้วย Mode หรือ Mean ตามตั้งค่า
 */
class GradeCalculator
{
    /**
     * เกณฑ์การตัดเกรดมาตรฐานเริ่มต้น 8 ระดับ
     */
    public static function getDefaultCriteria(): array
    {
        return [
            ['min_score' => 80, 'max_score' => 100, 'grade' => '4'],
            ['min_score' => 75, 'max_score' => 79,  'grade' => '3.5'],
            ['min_score' => 70, 'max_score' => 74,  'grade' => '3'],
            ['min_score' => 65, 'max_score' => 69,  'grade' => '2.5'],
            ['min_score' => 60, 'max_score' => 64,  'grade' => '2'],
            ['min_score' => 55, 'max_score' => 59,  'grade' => '1.5'],
            ['min_score' => 50, 'max_score' => 54,  'grade' => '1'],
            ['min_score' => 0,  'max_score' => 49,  'grade' => '0'],
        ];
    }

    /**
     * กฎข้อ 2: ตรวจสอบว่าคะแนนเต็มรวมของ score_items ตรงกับสัดส่วนคะแนนเก็บ (ratio_collect) และคะแนนปลายภาค (ratio_final) รวม 100 หรือไม่
     *
     * @param array $scoreItems รายการชิ้นงาน [['phase' => 'before_mid'|'midterm'|'after_mid'|'final', 'max_score' => float], ...]
     * @param int   $ratioCollect สัดส่วนคะแนนเก็บ (เช่น 70)
     * @param int   $ratioFinal   สัดส่วนคะแนนปลายภาค (เช่น 30)
     * @param string $subjectType basic | additional | activity
     */
    public function validateScoreItemsRatio(
        array $scoreItems,
        int $ratioCollect = 70,
        int $ratioFinal = 30,
        string $subjectType = 'basic'
    ): array {
        $phaseMax = [
            'before_mid' => 0.0,
            'midterm'    => 0.0,
            'after_mid'  => 0.0,
            'final'      => 0.0,
        ];

        foreach ($scoreItems as $item) {
            $phase = (string)($item['phase'] ?? 'before_mid');
            $max   = max(0.0, (float)($item['max_score'] ?? 0.0));
            if (isset($phaseMax[$phase])) {
                $phaseMax[$phase] += $max;
            }
        }

        $collectMax = round($phaseMax['before_mid'] + $phaseMax['midterm'] + $phaseMax['after_mid'], 2);
        $finalMax   = round($phaseMax['final'], 2);
        $totalMax   = round($collectMax + $finalMax, 2);

        $warnings = [];
        if ($subjectType !== 'activity') {
            if (($ratioCollect + $ratioFinal) !== 100) {
                $warnings[] = "สัดส่วนคะแนนที่ตั้งไว้ ({$ratioCollect} : {$ratioFinal}) รวมกันไม่เท่ากับ 100 คะแนน";
            }
            if (empty($scoreItems)) {
                $warnings[] = 'ยังไม่ได้กำหนดรายการชิ้นงาน/คะแนนสอบ (คะแนนเต็มรวมต้องเท่ากับ 100 คะแนน)';
            } else {
                if (abs($collectMax - $ratioCollect) > 0.001) {
                    $warnings[] = "คะแนนเต็มรวมของคะแนนเก็บ (ก่อนกลางภาค+กลางภาค+หลังกลางภาค) ปัจจุบันคือ {$collectMax} คะแนน ไม่ตรงกับสัดส่วนคะแนนเก็บที่ตั้งไว้ ({$ratioCollect} คะแนน)";
                }
                if (abs($finalMax - $ratioFinal) > 0.001) {
                    $warnings[] = "คะแนนเต็มของสอบปลายภาค ปัจจุบันคือ {$finalMax} คะแนน ไม่ตรงกับสัดส่วนปลายภาคที่ตั้งไว้ ({$ratioFinal} คะแนน)";
                }
                if (abs($totalMax - 100.0) > 0.001) {
                    $warnings[] = "คะแนนเต็มรวมทุกรายการปัจจุบันคือ {$totalMax} คะแนน (ต้องรวมได้ 100 คะแนนพอดี)";
                }
            }
        }

        return [
            'is_valid'    => empty($warnings),
            'warnings'    => $warnings,
            'phase_max'   => $phaseMax,
            'collect_max' => $collectMax,
            'final_max'   => $finalMax,
            'total_max'   => $totalMax,
        ];
    }

    /**
     * กฎข้อ 1, 3, 4, 5, 6: คำนวณคะแนนและตัดเกรดของนักเรียน 1 คน
     *
     * @param array       $scoreItems          รายการชิ้นงานทั้งหมดของวิชา [['id' => 1, 'phase' => 'before_mid', 'max_score' => 10], ...]
     * @param array       $studentScoresByItem คะแนนของนักเรียน [score_item_id => float|null] (ถ้าไม่มีคีย์หรือค่าเป็น null ถือว่ายังไม่ส่ง/ติด ร)
     * @param float       $attendancePercent   เปอร์เซ็นต์เวลาเรียนของนักเรียน (0.00 - 100.00)
     * @param string      $subjectType         'basic' | 'additional' | 'activity'
     * @param array|null  $customCriteria      เกณฑ์การตัดเกรดจาก pp5_grade_criteria
     * @param float       $minAttendancePct    เกณฑ์เวลาเรียนขั้นต่ำไม่ติด มส (ค่าเริ่มต้น 80.0)
     * @param string|null $activityPassStatus  สำหรับวิชากิจกรรม ('ผ' หรือ 'มผ' ถ้าระบุโดยตรง หรือคำนวณจากเวลาเรียน/ชิ้นงาน)
     */
    public function calculateStudentGrade(
        array $scoreItems,
        array $studentScoresByItem,
        float $attendancePercent = 100.0,
        string $subjectType = 'basic',
        ?array $customCriteria = null,
        float $minAttendancePct = 80.0,
        ?string $activityPassStatus = null
    ): array {
        $beforeMid      = 0.0;
        $midterm        = 0.0;
        $afterMid       = 0.0;
        $final          = 0.0;
        $hasMissingItem = false;

        foreach ($scoreItems as $item) {
            $itemId = (int)$item['id'];
            $phase  = (string)($item['phase'] ?? 'before_mid');
            $max    = (float)($item['max_score'] ?? 0.0);

            if (!array_key_exists($itemId, $studentScoresByItem) || $studentScoresByItem[$itemId] === null || $studentScoresByItem[$itemId] === '') {
                $hasMissingItem = true;
                continue;
            }

            $val = (float)$studentScoresByItem[$itemId];
            // ป้องกันคะแนนติดลบหรือเกินคะแนนเต็มของชิ้นงาน
            if ($val < 0.0) {
                $val = 0.0;
            }
            if ($max > 0.0 && $val > $max) {
                $val = $max;
            }

            if ($phase === 'before_mid') {
                $beforeMid += $val;
            } elseif ($phase === 'midterm') {
                $midterm += $val;
            } elseif ($phase === 'after_mid') {
                $afterMid += $val;
            } elseif ($phase === 'final') {
                $final += $val;
            }
        }

        $scoreCollect = round($beforeMid + $midterm + $afterMid, 2);
        $scoreFinal   = round($final, 2);
        $rawTotal     = $scoreCollect + $scoreFinal;

        // กฎข้อ 1: ปัดที่คะแนนรวมสุทธิครั้งเดียว (ROUND ครึ่งขึ้น) ก่อนตัดเกรด
        $roundedTotal = (int)round($rawTotal, 0, PHP_ROUND_HALF_UP);
        if ($roundedTotal > 100) {
            $roundedTotal = 100;
        }
        if ($roundedTotal < 0) {
            $roundedTotal = 0;
        }

        $isMs = ($attendancePercent + 0.0001) < $minAttendancePct;

        // ─── ลำดับการตัดสินผลการเรียน ───
        // 1. กฎข้อ 6: วิชากิจกรรมพัฒนาผู้เรียน (type = activity) ใช้ผล "ผ" หรือ "มผ" ไม่มีเกรดตัวเลข
        if ($subjectType === 'activity') {
            if ($isMs || $hasMissingItem) {
                $grade = 'มผ';
            } elseif ($activityPassStatus !== null && in_array($activityPassStatus, ['ผ', 'มผ'], true)) {
                $grade = $activityPassStatus;
            } else {
                // ถ้ามีชิ้นงาน ต้องได้คะแนนรวม >= 50% ของคะแนนเต็มรวมจึงจะ "ผ"
                $totalMax = array_sum(array_map(fn($i) => (float)($i['max_score'] ?? 0), $scoreItems));
                if ($totalMax > 0) {
                    $grade = ($rawTotal >= ($totalMax * 0.5)) ? 'ผ' : 'มผ';
                } else {
                    $grade = 'ผ';
                }
            }
        } else {
            // 2. กฎข้อ 4: ตรวจ "มส" ก่อนตัดเกรดเสมอ (เวลาเรียน < 80%)
            if ($isMs) {
                $grade = 'มส';
            }
            // 3. กฎข้อ 5: ตรวจ "ร" (มีคะแนนรายการใดรายการหนึ่งเป็น NULL)
            elseif ($hasMissingItem) {
                $grade = 'ร';
            }
            // 4. กฎข้อ 3: เทียบเกณฑ์การตัดเกรดจากคะแนนรวมที่ปัดเป็นจำนวนเต็มแล้ว
            else {
                $grade = $this->mapScoreToGrade($roundedTotal, $customCriteria);
            }
        }

        return [
            'score_before_mid'   => round($beforeMid, 2),
            'score_midterm'      => round($midterm, 2),
            'score_after_mid'    => round($afterMid, 2),
            'score_collect'      => $scoreCollect,
            'score_final'        => $scoreFinal,
            'raw_total'          => round($rawTotal, 2),
            'total'              => $roundedTotal,
            'attendance_percent' => round($attendancePercent, 2),
            'is_ms'              => $isMs,
            'has_missing_score'  => $hasMissingItem,
            'grade'              => $grade,
        ];
    }

    /**
     * เทียบคะแนนรวมจำนวนเต็ม (0-100) กับตารางเกณฑ์การตัดเกรด
     */
    public function mapScoreToGrade(int $roundedTotalScore, ?array $customCriteria = null): string
    {
        $criteria = !empty($customCriteria) ? $customCriteria : self::getDefaultCriteria();

        // เรียงจาก min_score มากไปน้อยเสมอ
        usort($criteria, fn($a, $b) => ((int)$b['min_score']) <=> ((int)$a['min_score']));

        foreach ($criteria as $c) {
            $min = (int)$c['min_score'];
            $max = isset($c['max_score']) ? (int)$c['max_score'] : 100;
            if ($roundedTotalScore >= $min && $roundedTotalScore <= $max) {
                return (string)$c['grade'];
            }
        }

        // Fallback กรณีคะแนน >= min_score
        foreach ($criteria as $c) {
            if ($roundedTotalScore >= (int)$c['min_score']) {
                return (string)$c['grade'];
            }
        }

        return '0';
    }

    /**
     * กฎข้อ 7: การบันทึกผลแก้ 0 / ร / มส / มผ
     * - เก็บเกรดเดิมไว้ใน original_grade เสมอ
     * - กรณีแก้ 0 และ ร: ผลการเรียนหลังแก้ได้ไม่เกิน '1' (แม้คะแนนใหม่จะคำนวณได้มากกว่า 1 ก็ตาม)
     * - กรณีแก้ มส: เมื่อซ่อมเวลาเรียนผ่านแล้ว ถ้าเป็นกรณีขาดเรียนโดยมีเหตุสุดวิสัย/ซ่อมเวลาเรียนครบ
     *   หากครูระบุ capAtOne=true จะได้ไม่เกิน '1' หรือตามเกรดที่สอบได้
     * - บันทึกวันที่แก้ไข (remedial_date) และสถานะ is_remedial = 1
     */
    public function applyRemedialGrade(
        string $currentGrade,
        ?string $existingOriginalGrade,
        string $calculatedNewGrade,
        ?string $remedialDate = null,
        bool $capMsAtOne = true
    ): array {
        $orig = ($existingOriginalGrade !== null && $existingOriginalGrade !== '')
            ? $existingOriginalGrade
            : $currentGrade;

        $finalGrade = $calculatedNewGrade;

        // กฎเหล็ก: ผลหลังแก้ 0 และ ร ได้ไม่เกิน 1
        if (in_array($orig, ['0', 'ร'], true)) {
            if (is_numeric($calculatedNewGrade) && (float)$calculatedNewGrade > 1.0) {
                $finalGrade = '1';
            }
        } elseif ($orig === 'มส' && $capMsAtOne) {
            if (is_numeric($calculatedNewGrade) && (float)$calculatedNewGrade > 1.0) {
                $finalGrade = '1';
            }
        } elseif ($orig === 'มผ') {
            $finalGrade = ($calculatedNewGrade === 'ผ') ? 'ผ' : 'มผ';
        }

        return [
            'grade'          => $finalGrade,
            'original_grade' => $orig,
            'is_remedial'    => 1,
            'remedial_date'  => $remedialDate ?: date('Y-m-d'),
        ];
    }

    /**
     * กฎข้อ 8: สรุปผลประเมินคุณลักษณะอันพึงประสงค์ (8 ข้อ), อ่าน คิดวิเคราะห์ และเขียน (5 ข้อ), สมรรถนะสำคัญ (5 ด้าน)
     * ระดับคะแนน: 3 = ดีเยี่ยม, 2 = ดี, 1 = ผ่าน, 0 = ไม่ผ่าน
     *
     * @param int[] $itemLevels รายการระดับคะแนนของแต่ละข้อย่อย เช่น [3, 3, 2, 3, 2, 3, 3, 3]
     * @param array $settings   ['assessment_summary_mode' => 'mode'|'mean', 'zero_item_forces_fail' => 1|0, ...]
     * @return int ระดับสรุปรวม (0, 1, 2, หรือ 3)
     */
    public function summarizeAssessmentLevels(array $itemLevels, array $settings = []): int
    {
        if (empty($itemLevels)) {
            return 3;
        }

        $cleanLevels = [];
        foreach ($itemLevels as $lv) {
            $v = max(0, min(3, (int)$lv));
            $cleanLevels[] = $v;
        }

        $zeroForcesFail = isset($settings['zero_item_forces_fail']) ? (bool)$settings['zero_item_forces_fail'] : true;
        if ($zeroForcesFail && in_array(0, $cleanLevels, true)) {
            return 0;
        }

        $modeType = (string)($settings['assessment_summary_mode'] ?? 'mode');
        if ($modeType === 'mean') {
            $avg      = array_sum($cleanLevels) / count($cleanLevels);
            $minEx    = isset($settings['mean_min_excellent']) ? (float)$settings['mean_min_excellent'] : 2.50;
            $minGood  = isset($settings['mean_min_good']) ? (float)$settings['mean_min_good'] : 1.50;
            $minPass  = isset($settings['mean_min_pass']) ? (float)$settings['mean_min_pass'] : 1.00;

            if ($avg >= $minEx) {
                return 3;
            }
            if ($avg >= $minGood) {
                return 2;
            }
            if ($avg >= $minPass) {
                return 1;
            }
            return 0;
        }

        // ค่าเริ่มต้น: ใช้ฐานนิยม (Mode) — หากมีความถี่สูงสุดเท่ากันหลายค่า ให้เลือกค่าที่สูงกว่าตามหลักเกณฑ์วัดผล
        $freq = [0 => 0, 1 => 0, 2 => 0, 3 => 0];
        foreach ($cleanLevels as $v) {
            $freq[$v]++;
        }

        $bestLevel = 0;
        $maxCount  = -1;
        foreach ([3, 2, 1, 0] as $candidate) {
            if ($freq[$candidate] > $maxCount) {
                $maxCount  = $freq[$candidate];
                $bestLevel = $candidate;
            }
        }

        return $bestLevel;
    }

    /**
     * แปลงตัวเลขระดับ (0-3) เป็นความหมายภาษาไทย
     */
    public static function levelToText(int $level): string
    {
        return match ($level) {
            3       => 'ดีเยี่ยม (3)',
            2       => 'ดี (2)',
            1       => 'ผ่าน (1)',
            default => 'ไม่ผ่าน (0)',
        };
    }
}
