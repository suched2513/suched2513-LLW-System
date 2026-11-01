<?php
namespace PP5\App\Services;

use PDO;
use PP5\App\Requests\CourseRequest;
use Throwable;

/**
 * CourseService — บริการจัดการรายวิชา ปพ.5, ชิ้นงาน, การคัดลอกโครงสร้าง, การบันทึกคะแนน และการคำนวณเกรดทั้งห้อง
 */
class CourseService
{
    private PDO $pdo;
    private GradeCalculator $gradeCalculator;
    private AttendanceService $attendanceService;
    private MasterDataService $masterService;
    private ActivityLogger $logger;

    public function __construct(PDO $pdo)
    {
        $this->pdo               = $pdo;
        $this->gradeCalculator   = new GradeCalculator();
        $this->attendanceService = new AttendanceService($pdo);
        $this->masterService     = new MasterDataService($pdo);
        $this->logger            = new ActivityLogger($pdo);
    }

    /**
     * ดึงข้อมูลรายวิชา ปพ.5 (pp5_courses) พร้อมข้อมูลวิชา กลุ่มสาระ ห้องเรียน และครูผู้สอน
     */
    public function getCourseById(int $courseId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT cr.*,
                   s.`code` AS `subject_code`, s.`name` AS `subject_name`,
                   s.`type` AS `subject_type`, s.`credit`, s.`hours`, s.`department_id`, s.`grade_level` AS `subject_grade_level`,
                   d.`name` AS `department_name`, d.`code` AS `department_code`, d.`head_user_id`,
                   c.`classroom_name`, c.`grade_level`, c.`room_no`, c.`study_plan`, c.`advisor_user_id`, c.`co_advisor_user_id`,
                   TRIM(CONCAT(COALESCE(u.`prefix`, ''), COALESCE(u.`firstname`, ''), ' ', COALESCE(u.`lastname`, ''))) AS `teacher_name`,
                   TRIM(CONCAT(COALESCE(adv.`prefix`, ''), COALESCE(adv.`firstname`, ''), ' ', COALESCE(adv.`lastname`, ''))) AS `advisor_name`,
                   TRIM(CONCAT(COALESCE(coadv.`prefix`, ''), COALESCE(coadv.`firstname`, ''), ' ', COALESCE(coadv.`lastname`, ''))) AS `co_advisor_name`,
                   TRIM(CONCAT(COALESCE(hd.`prefix`, ''), COALESCE(hd.`firstname`, ''), ' ', COALESCE(hd.`lastname`, ''))) AS `head_name`
            FROM `pp5_courses` cr
            INNER JOIN `pp5_subjects` s ON s.`id` = cr.`subject_id`
            INNER JOIN `pp5_departments` d ON d.`id` = s.`department_id`
            INNER JOIN `pp5_classrooms` c ON c.`id` = cr.`classroom_id`
            LEFT JOIN `llw_users` u ON u.`user_id` = cr.`teacher_user_id`
            LEFT JOIN `llw_users` adv ON adv.`user_id` = c.`advisor_user_id`
            LEFT JOIN `llw_users` coadv ON coadv.`user_id` = c.`co_advisor_user_id`
            LEFT JOIN `llw_users` hd ON hd.`user_id` = d.`head_user_id`
            WHERE cr.`id` = ?
            LIMIT 1
        ");
        $stmt->execute([$courseId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * ดึงรายชื่อนักเรียนที่เรียนในรายวิชา ปพ.5 นี้ เรียงตามเลขที่ในห้องเรียน
     * รองรับทั้งการส่ง array ข้อมูลวิชา ($course) หรือ int ($classroomId)
     */
    public function getCourseStudents($courseOrClassroomId): array
    {
        if (is_array($courseOrClassroomId)) {
            $classroomId   = (int)($courseOrClassroomId['classroom_id'] ?? 0);
            $classroomName = (string)($courseOrClassroomId['classroom_name'] ?? '');
        } else {
            $classroomId   = (int)$courseOrClassroomId;
            $classroomName = '';
        }

        if ($classroomId > 0 && $classroomName === '') {
            $stCls = $this->pdo->prepare("SELECT `classroom_name` FROM `pp5_classrooms` WHERE `id` = ? LIMIT 1");
            $stCls->execute([$classroomId]);
            $classroomName = (string)($stCls->fetchColumn() ?: '');
        }

        $stmt = $this->pdo->prepare("
            SELECT s.`id`, s.`student_id` AS `student_code`, s.`prefix`, s.`first_name`, s.`last_name`,
                   s.`name`, s.`gender`,
                   COALESCE(cs.`student_number`, s.`student_number`, 1) AS `student_number`,
                   COALESCE(cs.`status`, s.`status`, 'active') AS `student_status`
            FROM `att_students` s
            LEFT JOIN `pp5_classroom_students` cs
                ON cs.`classroom_id` = ? AND cs.`student_id` = s.`id`
            WHERE s.`classroom` = ?
              AND s.`status` = 'active'
              AND s.`student_id` REGEXP '^[0-9]+$'
            ORDER BY COALESCE(cs.`student_number`, s.`student_number`, 999) ASC, s.`student_id` ASC
        ");
        $stmt->execute([$classroomId, $classroomName]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * ดึงรายการชิ้นงาน / คะแนนเก็บและสอบ (pp5_score_items) ของรายวิชา ปพ.5
     */
    public function getCourseScoreItems(int $courseId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT si.*, ind.`code` AS `indicator_code`, ind.`description` AS `indicator_description`
            FROM `pp5_score_items` si
            LEFT JOIN `pp5_indicators` ind ON ind.`id` = si.`indicator_id`
            WHERE si.`course_id` = ?
            ORDER BY FIELD(si.`phase`, 'before_mid', 'midterm', 'after_mid', 'final'), si.`sort_order` ASC, si.`id` ASC
        ");
        $stmt->execute([$courseId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * อัปเดตสัดส่วนคะแนนของรายวิชา (ratio_collect : ratio_final)
     */
    public function updateCourseRatio(int $courseId, int $ratioCollect, int $ratioFinal): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE `pp5_courses`
            SET `ratio_collect` = ?, `ratio_final` = ?
            WHERE `id` = ?
        ");
        $stmt->execute([$ratioCollect, $ratioFinal, $courseId]);
        $this->logger->log(
            'course.update_ratio',
            "เปลี่ยนสัดส่วนคะแนนเป็น {$ratioCollect}:{$ratioFinal}",
            'pp5_courses',
            $courseId,
            $courseId,
            null,
            ['ratio_collect' => $ratioCollect, 'ratio_final' => $ratioFinal]
        );
    }

    /**
     * บันทึกรายการชิ้นงาน / คะแนนสอบ (เพิ่มหรือแก้ไข)
     */
    public function saveScoreItem(int $courseId, array $data): int
    {
        if (!empty($data['id'])) {
            $stmt = $this->pdo->prepare("
                UPDATE `pp5_score_items`
                SET `phase` = ?, `name` = ?, `max_score` = ?, `indicator_id` = ?, `sort_order` = ?
                WHERE `id` = ? AND `course_id` = ?
            ");
            $stmt->execute([
                $data['phase'],
                $data['name'],
                $data['max_score'],
                $data['indicator_id'],
                $data['sort_order'],
                $data['id'],
                $courseId,
            ]);
            $itemId = (int)$data['id'];
        } else {
            $stmt = $this->pdo->prepare("
                INSERT INTO `pp5_score_items` (`course_id`, `phase`, `name`, `max_score`, `indicator_id`, `sort_order`)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $courseId,
                $data['phase'],
                $data['name'],
                $data['max_score'],
                $data['indicator_id'],
                $data['sort_order'],
            ]);
            $itemId = (int)$this->pdo->lastInsertId();
        }

        $this->logger->log(
            'score_item.save',
            "บันทึกชิ้นงาน '{$data['name']}' (เต็ม {$data['max_score']}) หมวด {$data['phase']}",
            'pp5_score_items',
            $itemId,
            $courseId,
            null,
            $data
        );

        $this->recalculateCourseGrades($courseId);
        return $itemId;
    }

    /**
     * ลบรายการชิ้นงาน พร้อมคำนวณเกรดใหม่
     */
    public function deleteScoreItem(int $courseId, int $itemId): void
    {
        $stmtGet = $this->pdo->prepare("SELECT * FROM `pp5_score_items` WHERE `id` = ? AND `course_id` = ? LIMIT 1");
        $stmtGet->execute([$itemId, $courseId]);
        $old = $stmtGet->fetch(PDO::FETCH_ASSOC);
        if (!$old) {
            return;
        }

        $stmt = $this->pdo->prepare("DELETE FROM `pp5_score_items` WHERE `id` = ? AND `course_id` = ?");
        $stmt->execute([$itemId, $courseId]);

        $this->logger->log(
            'score_item.delete',
            "ลบชิ้นงาน '{$old['name']}' (เต็ม {$old['max_score']})",
            'pp5_score_items',
            $itemId,
            $courseId,
            $old,
            null
        );

        $this->recalculateCourseGrades($courseId);
    }

    /**
     * สร้างโครงสร้างชิ้นงานเริ่มต้นอัตโนมัติ (70:30 มาตรฐาน) ให้ครูเริ่มต้นใช้งานได้ในคลิกเดียว
     */
    public function createDefaultScoreStructure(int $courseId): void
    {
        $course = $this->getCourseById($courseId);
        if (!$course) {
            return;
        }

        $indicators = $this->masterService->getSubjectIndicators((int)$course['subject_id']);
        $ind1       = isset($indicators[0]['id']) ? (int)$indicators[0]['id'] : null;
        $ind2       = isset($indicators[1]['id']) ? (int)$indicators[1]['id'] : $ind1;
        $ind3       = isset($indicators[2]['id']) ? (int)$indicators[2]['id'] : $ind2;

        $defaults = [
            ['before_mid', 'ชิ้นงานที่ 1 (ก่อนกลางภาค)', 15.00, $ind1, 1],
            ['before_mid', 'ชิ้นงานที่ 2 (ก่อนกลางภาค)', 10.00, $ind1, 2],
            ['midterm',    'สอบวัดผลกลางภาค',             20.00, $ind2, 1],
            ['after_mid',  'ชิ้นงานที่ 3 (หลังกลางภาค)', 15.00, $ind2, 1],
            ['after_mid',  'ชิ้นงานที่ 4 (หลังกลางภาค)', 10.00, $ind3, 2],
            ['final',      'สอบวัดผลปลายภาค',             30.00, $ind3, 1],
        ];

        $this->pdo->beginTransaction();
        try {
            $this->pdo->prepare("UPDATE `pp5_courses` SET `ratio_collect` = 70, `ratio_final` = 30 WHERE `id` = ?")->execute([$courseId]);
            $stmtIns = $this->pdo->prepare("
                INSERT INTO `pp5_score_items` (`course_id`, `phase`, `name`, `max_score`, `indicator_id`, `sort_order`)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            foreach ($defaults as $d) {
                $stmtIns->execute([$courseId, $d[0], $d[1], $d[2], $d[3], $d[4]]);
            }
            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }

        $this->recalculateCourseGrades($courseId);
    }

    /**
     * ดึงรายวิชาห้องอื่นที่เป็นรหัสวิชาเดียวกัน (subject_id เดียวกัน) เพื่อใช้คัดลอกโครงสร้างชิ้นงาน
     */
    public function getSiblingCoursesForCopy(array $course): array
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT cr.`id`, cr.`academic_year`, cr.`semester`, cr.`ratio_collect`, cr.`ratio_final`,
                       c.`classroom_name`,
                       TRIM(CONCAT(COALESCE(u.`prefix`, ''), COALESCE(u.`firstname`, ''), ' ', COALESCE(u.`lastname`, ''))) AS `teacher_name`,
                       (SELECT COUNT(*) FROM `pp5_score_items` si WHERE si.`course_id` = cr.`id`) AS `item_count`
                FROM `pp5_courses` cr
                INNER JOIN `pp5_classrooms` c ON c.`id` = cr.`classroom_id`
                LEFT JOIN `llw_users` u ON u.`user_id` = cr.`teacher_user_id`
                WHERE cr.`subject_id` = ?
                  AND cr.`id` != ?
                  AND EXISTS (SELECT 1 FROM `pp5_score_items` si2 WHERE si2.`course_id` = cr.`id`)
                ORDER BY cr.`academic_year` DESC, cr.`semester` DESC, c.`grade_level` ASC, c.`room_no` ASC
            ");
            $stmt->execute([(int)$course['subject_id'], (int)$course['id']]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            error_log('[PP5 getSiblingCoursesForCopy] ' . $e->getMessage());
            return [];
        }
    }

    /**
     * คัดลอกโครงสร้างสัดส่วนคะแนนและรายการชิ้นงานจากห้องอื่นที่เป็นวิชาเดียวกัน
     */
    public function copyStructureFromCourse(int $targetCourseId, int $sourceCourseId): int
    {
        $target = $this->getCourseById($targetCourseId);
        $source = $this->getCourseById($sourceCourseId);
        if (!$target || !$source || (int)$target['subject_id'] !== (int)$source['subject_id']) {
            return 0;
        }

        $sourceItems = $this->getCourseScoreItems($sourceCourseId);
        if (empty($sourceItems)) {
            return 0;
        }

        $this->pdo->beginTransaction();
        try {
            // อัปเดตสัดส่วนคะแนนให้ตรงกับต้นทาง
            $this->pdo->prepare("
                UPDATE `pp5_courses`
                SET `ratio_collect` = ?, `ratio_final` = ?
                WHERE `id` = ?
            ")->execute([(int)$source['ratio_collect'], (int)$source['ratio_final'], $targetCourseId]);

            // ล้างรายการชิ้นงานเดิมของวิชาปลายทาง แล้วคัดลอกจากต้นทาง
            $this->pdo->prepare("DELETE FROM `pp5_score_items` WHERE `course_id` = ?")->execute([$targetCourseId]);

            $stmtIns = $this->pdo->prepare("
                INSERT INTO `pp5_score_items` (`course_id`, `phase`, `name`, `max_score`, `indicator_id`, `sort_order`)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $copied = 0;
            foreach ($sourceItems as $si) {
                $stmtIns->execute([
                    $targetCourseId,
                    $si['phase'],
                    $si['name'],
                    (float)$si['max_score'],
                    $si['indicator_id'] ? (int)$si['indicator_id'] : null,
                    (int)$si['sort_order'],
                ]);
                $copied++;
            }

            $this->pdo->commit();

            $this->logger->log(
                'course.copy_structure',
                "คัดลอกโครงสร้างคะแนน {$copied} รายการ จากห้อง {$source['classroom_name']} มายัง {$target['classroom_name']}",
                'pp5_courses',
                $targetCourseId,
                $targetCourseId
            );

            $this->recalculateCourseGrades($targetCourseId);
            return $copied;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * ดึงคะแนนทั้งหมดของรายวิชา ปพ.5 ในรูปแบบ Map [student_id][score_item_id] => float|null
     */
    public function getCourseScoresGrid(int $courseId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT sc.`student_id`, sc.`score_item_id`, sc.`score`
            FROM `pp5_scores` sc
            INNER JOIN `pp5_score_items` si ON si.`id` = sc.`score_item_id`
            WHERE si.`course_id` = ?
        ");
        $stmt->execute([$courseId]);
        $grid = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $grid[(int)$r['student_id']][(int)$r['score_item_id']] = $r['score'] !== null ? (float)$r['score'] : null;
        }
        return $grid;
    }

    /**
     * ดึงผลการเรียนสรุป (pp5_grades) ของรายวิชา ปพ.5 ในรูปแบบ Map [student_id] => array
     */
    public function getCourseGradesMap(int $courseId): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM `pp5_grades` WHERE `course_id` = ?");
        $stmt->execute([$courseId]);
        $map = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $map[(int)$r['student_id']] = $r;
        }
        return $map;
    }

    /**
     * บันทึกคะแนนเป็นชุด (รองรับทั้ง Auto-save รายเซลล์ และการวางข้อมูลจาก Clipboard หลายเซลล์พร้อมกัน)
     * พร้อมตรวจสอบคะแนนเต็ม (ห้ามกรอกเกิน max_score) บันทึกประวัติการแก้คะแนน และคำนวณเกรดใหม่ทันที
     *
     * @param int   $courseId
     * @param array $entries [['student_id' => int, 'score_item_id' => int, 'score' => mixed], ...]
     * @param int   $userId
     */
    public function saveBatchScores(int $courseId, array $entries, int $userId): array
    {
        $scoreItems = $this->getCourseScoreItems($courseId);
        $itemMap    = [];
        foreach ($scoreItems as $si) {
            $itemMap[(int)$si['id']] = $si;
        }

        $oldGrid       = $this->getCourseScoresGrid($courseId);
        $errors        = [];
        $validUpdates  = [];
        $changedLogs   = [];
        $affectedStus  = [];

        foreach ($entries as $entry) {
            $stuId  = (int)($entry['student_id'] ?? 0);
            $itemId = (int)($entry['score_item_id'] ?? 0);
            $rawVal = $entry['score'] ?? null;

            if ($stuId <= 0 || !isset($itemMap[$itemId])) {
                continue;
            }

            $maxScore = (float)$itemMap[$itemId]['max_score'];
            $valCheck = CourseRequest::validateScoreValue($rawVal, $maxScore);
            if (!$valCheck['valid']) {
                $errors[] = [
                    'student_id'    => $stuId,
                    'score_item_id' => $itemId,
                    'message'       => $valCheck['error'],
                ];
                continue;
            }

            $newScore = $valCheck['score'];
            $oldScore = $oldGrid[$stuId][$itemId] ?? null;

            $validUpdates[] = [
                'student_id'    => $stuId,
                'score_item_id' => $itemId,
                'score'         => $newScore,
            ];
            $affectedStus[$stuId] = true;

            if ($oldScore !== $newScore) {
                $changedLogs[] = [
                    'student_id' => $stuId,
                    'item_id'    => $itemId,
                    'item_name'  => $itemMap[$itemId]['name'],
                    'old'        => $oldScore,
                    'new'        => $newScore,
                ];
            }
        }

        if (!empty($validUpdates)) {
            $this->pdo->beginTransaction();
            try {
                $stmtUpsert = $this->pdo->prepare("
                    INSERT INTO `pp5_scores` (`score_item_id`, `student_id`, `score`, `updated_by`)
                    VALUES (?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE
                        `score`      = VALUES(`score`),
                        `updated_by` = VALUES(`updated_by`)
                ");
                foreach ($validUpdates as $u) {
                    $stmtUpsert->execute([$u['score_item_id'], $u['student_id'], $u['score'], $userId]);
                }
                $this->pdo->commit();
            } catch (Throwable $e) {
                if ($this->pdo->inTransaction()) {
                    $this->pdo->rollBack();
                }
                throw $e;
            }

            // บันทึกประวัติการแก้คะแนนลง ActivityLogger ตามข้อกำหนดด้านความปลอดภัย
            if (!empty($changedLogs)) {
                $firstChange = $changedLogs[0];
                $desc = count($changedLogs) === 1
                    ? "แก้ไขคะแนนชิ้นงาน '{$firstChange['item_name']}' ของนักเรียน ID #{$firstChange['student_id']} (" . ($firstChange['old'] ?? 'NULL') . " → " . ($firstChange['new'] ?? 'NULL') . ")"
                    : "แก้ไขคะแนนแบบกลุ่ม จำนวน " . count($changedLogs) . " รายการ";
                $this->logger->log(
                    'score.update',
                    $desc,
                    'pp5_scores',
                    null,
                    $courseId,
                    array_map(fn($c) => ['stu' => $c['student_id'], 'item' => $c['item_id'], 'score' => $c['old']], array_slice($changedLogs, 0, 20)),
                    array_map(fn($c) => ['stu' => $c['student_id'], 'item' => $c['item_id'], 'score' => $c['new']], array_slice($changedLogs, 0, 20))
                );
            }
        }

        // คำนวณเกรดทั้งห้องใหม่และดึงผลลัพธ์ของนักเรียนที่เปลี่ยนแปลงส่งกลับไปอัปเดตหน้าเว็บแบบ Real-time
        $updatedGradesMap = $this->recalculateCourseGrades($courseId);
        $studentSummaries = [];
        foreach (array_keys($affectedStus) as $stuId) {
            if (isset($updatedGradesMap[$stuId])) {
                $studentSummaries[$stuId] = $updatedGradesMap[$stuId];
            }
        }

        return [
            'saved_count'       => count($validUpdates),
            'errors'            => $errors,
            'student_summaries' => $studentSummaries,
        ];
    }

    /**
     * คำนวณคะแนนรวม เปอร์เซ็นต์เวลาเรียน และตัดเกรดของนักเรียนทุกคนในรายวิชา ปพ.5 บันทึกลงตาราง pp5_grades
     */
    public function recalculateCourseGrades(int $courseId): array
    {
        $course = $this->getCourseById($courseId);
        if (!$course) {
            return [];
        }

        $students       = $this->getCourseStudents($course);
        $scoreItems     = $this->getCourseScoreItems($courseId);
        $scoresGrid     = $this->getCourseScoresGrid($courseId);
        $sessions       = $this->attendanceService->getCourseSessions($courseId);
        $attMap         = $this->attendanceService->getCourseAttendanceMap($courseId);
        $schoolSettings = $this->masterService->getSchoolSettings();
        $criteria       = $this->masterService->getGradeCriteria();
        $existingGrades = $this->getCourseGradesMap($courseId);

        $minAttPct   = (float)($schoolSettings['min_attendance_percent'] ?? 80.0);
        $subjectType = (string)($course['subject_type'] ?? 'basic');

        $stmtUpsertGrade = $this->pdo->prepare("
            INSERT INTO `pp5_grades` (
                `course_id`, `student_id`,
                `score_before_mid`, `score_midterm`, `score_after_mid`,
                `score_collect`, `score_final`, `total`,
                `attended_hours`, `total_hours`, `attendance_percent`,
                `has_missing_score`, `grade`, `original_grade`, `is_remedial`, `remedial_date`
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                `score_before_mid`   = VALUES(`score_before_mid`),
                `score_midterm`      = VALUES(`score_midterm`),
                `score_after_mid`    = VALUES(`score_after_mid`),
                `score_collect`      = VALUES(`score_collect`),
                `score_final`        = VALUES(`score_final`),
                `total`              = VALUES(`total`),
                `attended_hours`     = VALUES(`attended_hours`),
                `total_hours`        = VALUES(`total_hours`),
                `attendance_percent` = VALUES(`attendance_percent`),
                `has_missing_score`  = VALUES(`has_missing_score`),
                `grade`              = VALUES(`grade`),
                `original_grade`     = VALUES(`original_grade`),
                `is_remedial`        = VALUES(`is_remedial`),
                `remedial_date`      = VALUES(`remedial_date`)
        ");

        $resultGrades = [];
        $this->pdo->beginTransaction();
        try {
            foreach ($students as $stu) {
                $stuId = (int)$stu['id'];

                // 1. คำนวณเวลาเรียนของนักเรียนคนนี้
                $stuSessionRecs = [];
                foreach ($sessions as $sess) {
                    $sessId = (int)$sess['id'];
                    $stVal  = $attMap[$sessId][$stuId]['status'] ?? 'present';
                    $stuSessionRecs[] = [
                        'hours'  => (float)$sess['hours'],
                        'status' => $stVal,
                    ];
                }
                $attSummary = $this->attendanceService->calculateStudentAttendanceSummary($stuSessionRecs, 0.0, $schoolSettings);

                // 2. คำนวณคะแนนและตัดเกรด
                $stuScores = $scoresGrid[$stuId] ?? [];
                $calc      = $this->gradeCalculator->calculateStudentGrade(
                    $scoreItems,
                    $stuScores,
                    $attSummary['attendance_percent'],
                    $subjectType,
                    $criteria,
                    $minAttPct
                );

                // 3. ตรวจสอบกรณีที่เคยบันทึกแก้ตัว (is_remedial = 1) ไว้แล้ว
                $prev          = $existingGrades[$stuId] ?? null;
                $finalGrade    = $calc['grade'];
                $originalGrade = $prev['original_grade'] ?? null;
                $isRemedial    = (int)($prev['is_remedial'] ?? 0);
                $remedialDate  = $prev['remedial_date'] ?? null;

                if ($isRemedial === 1 && $originalGrade !== null) {
                    $remResult     = $this->gradeCalculator->applyRemedialGrade($finalGrade, $originalGrade, $finalGrade, $remedialDate);
                    $finalGrade    = $remResult['grade'];
                    $originalGrade = $remResult['original_grade'];
                }

                $stmtUpsertGrade->execute([
                    $courseId,
                    $stuId,
                    $calc['score_before_mid'],
                    $calc['score_midterm'],
                    $calc['score_after_mid'],
                    $calc['score_collect'],
                    $calc['score_final'],
                    $calc['total'],
                    $attSummary['attended_hours'],
                    $attSummary['total_hours'],
                    $attSummary['attendance_percent'],
                    $calc['has_missing_score'] ? 1 : 0,
                    $finalGrade,
                    $originalGrade,
                    $isRemedial,
                    $remedialDate,
                ]);

                $resultGrades[$stuId] = [
                    'student_id'         => $stuId,
                    'score_before_mid'   => $calc['score_before_mid'],
                    'score_midterm'      => $calc['score_midterm'],
                    'score_after_mid'    => $calc['score_after_mid'],
                    'score_collect'      => $calc['score_collect'],
                    'score_final'        => $calc['score_final'],
                    'total'              => $calc['total'],
                    'attended_hours'     => $attSummary['attended_hours'],
                    'total_hours'        => $attSummary['total_hours'],
                    'attendance_percent' => $attSummary['attendance_percent'],
                    'has_missing_score'  => $calc['has_missing_score'] ? 1 : 0,
                    'grade'              => $finalGrade,
                    'original_grade'     => $originalGrade,
                    'is_remedial'        => $isRemedial,
                ];
            }
            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }

        return $resultGrades;
    }

    /**
     * บันทึกผลการสอบแก้ตัว (0 / ร / มส / มผ) สำหรับนักเรียนรายบุคคล
     * ตามกฎข้อที่ 7: บันทึก original_grade เดิม และจำกัดผลการสอบแก้ 0/ร/มส ได้ไม่เกินเกรด "1"
     */
    public function saveStudentRemedialGrade(int $courseId, int $studentId, string $newGrade, string $remedialDate, int $userId): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM `pp5_grades` WHERE `course_id` = ? AND `student_id` = ? LIMIT 1");
        $stmt->execute([$courseId, $studentId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return ['ok' => false, 'message' => 'ไม่พบข้อมูลผลการเรียนของนักเรียนในรายวิชานี้'];
        }

        $currentGrade = (string)$row['grade'];
        $origGrade    = (!empty($row['original_grade'])) ? (string)$row['original_grade'] : $currentGrade;

        if (!in_array($origGrade, ['0', 'ร', 'มส', 'มผ'], true) && (int)$row['is_remedial'] === 0) {
            return ['ok' => false, 'message' => "นักเรียนมีผลการเรียน {$currentGrade} ซึ่งไม่ได้อยู่ในเกณฑ์ที่ต้องสอบแก้ตัว (0, ร, มส, มผ)"];
        }

        $remResult = $this->gradeCalculator->applyRemedialGrade($currentGrade, $origGrade, $newGrade, $remedialDate);

        $this->pdo->beginTransaction();
        try {
            $upd = $this->pdo->prepare("
                UPDATE `pp5_grades`
                SET `grade`          = ?,
                    `original_grade` = ?,
                    `is_remedial`    = 1,
                    `remedial_date`  = ?
                WHERE `course_id` = ? AND `student_id` = ?
            ");
            $upd->execute([
                $remResult['grade'],
                $remResult['original_grade'],
                $remedialDate,
                $courseId,
                $studentId,
            ]);

            $this->pdo->commit();

            $this->logger->log(
                'grade.remedial',
                "บันทึกผลสอบแก้ตัว นักเรียน #{$studentId} ในวิชา #{$courseId} จากเกรดเดิม {$remResult['original_grade']} เป็นเกรดใหม่ {$remResult['grade']} (วันที่ {$remedialDate})",
                'pp5_grades',
                (int)$row['id'],
                $courseId,
                ['grade' => $currentGrade, 'original_grade' => $row['original_grade']],
                ['grade' => $remResult['grade'], 'original_grade' => $remResult['original_grade'], 'remedial_date' => $remedialDate]
            );

            return [
                'ok'             => true,
                'grade'          => $remResult['grade'],
                'original_grade' => $remResult['original_grade'],
                'remedial_date'  => $remedialDate,
                'message'        => "บันทึกผลการสอบแก้ตัวสำเร็จ! (เกรดเดิม: {$remResult['original_grade']} -> เกรดใหม่: {$remResult['grade']})",
            ];
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }
}
