<?php
namespace PP5\App\Services;

use PDO;
use Throwable;

/**
 * AttendanceService — บริการจัดการและคำนวณเวลาเรียนประจำรายวิชา (ปพ.5)
 *
 * กฎการคำนวณเวลาเรียน (ห้ามผิด):
 * 1. "มส" = เวลาเรียนน้อยกว่าเกณฑ์ขั้นต่ำ (ค่าเริ่มต้น 80%) ของเวลาเรียนทั้งหมดของรายวิชา
 * 2. การนับสถานะ present (มา), late (สาย), leave (ลากิจ), sick (ลาป่วย) เป็นเวลาเรียนหรือไม่
 *    อ่านจากค่าที่ Admin ตั้งไว้ใน pp5_school_settings (ค่าเริ่มต้น: นับ present และ late เป็นมาเรียน, นับ absent เป็นขาด)
 * 3. ระบบตรวจ "มส" ก่อนตัดเกรดเสมอ
 */
class AttendanceService
{
    private PDO $pdo;
    private ?ActivityLogger $logger = null;

    public function __construct(?PDO $pdo = null)
    {
        if ($pdo !== null) {
            $this->pdo    = $pdo;
            $this->logger = new ActivityLogger($pdo);
        }
    }

    /**
     * คำนวณสรุปเวลาเรียนและตรวจสอบสิทธิ์การสอบ ("มส") ของนักเรียน 1 คน (Pure Business Logic สำหรับ Unit Test และใช้งานจริง)
     *
     * @param array $sessionRecords รายการคาบเรียนและสถานะ เช่น [['hours' => 1.0, 'status' => 'present'], ...]
     * @param float $totalCourseHours จำนวนชั่วโมงเต็มของรายวิชา (ถ้า <= 0 จะใช้ผลรวมชั่วโมงของคาบที่มีการเช็คชื่อ)
     * @param array $settings ตั้งค่าเกณฑ์จาก pp5_school_settings
     * @return array ['attended_hours' => float, 'total_hours' => float, 'attendance_percent' => float, 'is_ms' => bool, 'counts' => array]
     */
    public function calculateStudentAttendanceSummary(
        array $sessionRecords,
        float $totalCourseHours = 0.0,
        array $settings = []
    ): array {
        $minPercent          = isset($settings['min_attendance_percent']) ? (float)$settings['min_attendance_percent'] : 80.0;
        $countLateAsPresent  = isset($settings['count_late_as_present']) ? (bool)$settings['count_late_as_present'] : true;
        $countLeaveAsPresent = isset($settings['count_leave_as_present']) ? (bool)$settings['count_leave_as_present'] : false;
        $countSickAsPresent  = isset($settings['count_sick_as_present']) ? (bool)$settings['count_sick_as_present'] : false;

        $counts = [
            'present' => 0,
            'late'    => 0,
            'leave'   => 0,
            'sick'    => 0,
            'absent'  => 0,
        ];

        $recordedTotalHours = 0.0;
        $attendedHours      = 0.0;

        foreach ($sessionRecords as $rec) {
            $hrs    = isset($rec['hours']) ? max(0.0, (float)$rec['hours']) : 1.0;
            $status = strtolower(trim((string)($rec['status'] ?? 'present')));
            if (!isset($counts[$status])) {
                $status = 'present';
            }

            $counts[$status]++;
            $recordedTotalHours += $hrs;

            $isCounted = false;
            if ($status === 'present') {
                $isCounted = true;
            } elseif ($status === 'late' && $countLateAsPresent) {
                $isCounted = true;
            } elseif ($status === 'leave' && $countLeaveAsPresent) {
                $isCounted = true;
            } elseif ($status === 'sick' && $countSickAsPresent) {
                $isCounted = true;
            }

            if ($isCounted) {
                $attendedHours += $hrs;
            }
        }

        // หากยังไม่มีการบันทึกคาบเรียนเลย ให้ถือว่าเวลาเรียน 100% (ยังไม่ติด มส)
        if (empty($sessionRecords) || $recordedTotalHours <= 0.0) {
            $baseHours = $totalCourseHours > 0.0 ? $totalCourseHours : 0.0;
            return [
                'attended_hours'     => $baseHours,
                'total_hours'        => $baseHours,
                'attendance_percent' => 100.00,
                'is_ms'              => false,
                'counts'             => $counts,
            ];
        }

        // ใช้ชั่วโมงรวมของคาบที่เช็คชื่อจริง (หรือ totalCourseHours ถ้าระบุให้คิดจากชั่วโมงเต็ม)
        $effectiveTotalHours = $totalCourseHours > 0.0 ? $totalCourseHours : $recordedTotalHours;
        $percent             = round(($attendedHours / $effectiveTotalHours) * 100, 2);
        if ($percent > 100.00) {
            $percent = 100.00;
        }

        // ติด มส เมื่อเปอร์เซ็นต์เวลาเรียนน้อยกว่าเกณฑ์ขั้นต่ำ (เช่น < 80.00%)
        $isMs = ($percent + 0.0001) < $minPercent;

        return [
            'attended_hours'     => round($attendedHours, 1),
            'total_hours'        => round($effectiveTotalHours, 1),
            'attendance_percent' => $percent,
            'is_ms'              => $isMs,
            'counts'             => $counts,
        ];
    }

    /**
     * ดึงรายการคาบเรียนทั้งหมดของรายวิชา ปพ.5
     */
    public function getCourseSessions(int $courseId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM `pp5_attendance_sessions`
            WHERE `course_id` = ?
            ORDER BY `session_date` ASC, `period` ASC
        ");
        $stmt->execute([$courseId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * ดึงข้อมูลการเช็คชื่อทั้งหมดของรายวิชา ปพ.5 ในรูปแบบ Map [session_id][student_id] => ['status' => ..., 'remark' => ...]
     */
    public function getCourseAttendanceMap(int $courseId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT a.`session_id`, a.`student_id`, a.`status`, a.`remark`
            FROM `pp5_attendances` a
            INNER JOIN `pp5_attendance_sessions` s ON s.`id` = a.`session_id`
            WHERE s.`course_id` = ?
        ");
        $stmt->execute([$courseId]);
        $map = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $map[(int)$row['session_id']][(int)$row['student_id']] = [
                'status' => $row['status'],
                'remark' => $row['remark'] ?? '',
            ];
        }
        return $map;
    }

    /**
     * บันทึกการเช็คชื่อประจำคาบเรียน (ค่าเริ่มต้นเป็น 'present' มาทั้งห้อง แล้วคลิกแก้รายคน)
     */
    public function saveSessionAttendance(
        int $courseId,
        string $sessionDate,
        int $period,
        float $hours,
        ?string $topic,
        array $studentStatuses,
        array $studentRemarks = [],
        ?int $userId = null
    ): int {
        $this->pdo->beginTransaction();
        try {
            $stmtSess = $this->pdo->prepare("
                INSERT INTO `pp5_attendance_sessions` (`course_id`, `session_date`, `period`, `hours`, `topic`, `created_by`)
                VALUES (?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    `hours` = VALUES(`hours`),
                    `topic` = VALUES(`topic`)
            ");
            $stmtSess->execute([$courseId, $sessionDate, $period, $hours, $topic, $userId]);

            $stmtGetId = $this->pdo->prepare("
                SELECT `id` FROM `pp5_attendance_sessions`
                WHERE `course_id` = ? AND `session_date` = ? AND `period` = ?
                LIMIT 1
            ");
            $stmtGetId->execute([$courseId, $sessionDate, $period]);
            $sessionId = (int)$stmtGetId->fetchColumn();

            $stmtAtt = $this->pdo->prepare("
                INSERT INTO `pp5_attendances` (`session_id`, `student_id`, `status`, `remark`)
                VALUES (?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    `status` = VALUES(`status`),
                    `remark` = VALUES(`remark`)
            ");

            $allowedStatuses = ['present', 'late', 'leave', 'sick', 'absent'];
            foreach ($studentStatuses as $stuId => $stVal) {
                $stuId = (int)$stuId;
                if ($stuId <= 0) {
                    continue;
                }
                $stVal = strtolower(trim((string)$stVal));
                if (!in_array($stVal, $allowedStatuses, true)) {
                    $stVal = 'present';
                }
                $rem = isset($studentRemarks[$stuId]) ? trim((string)$studentRemarks[$stuId]) : null;
                $stmtAtt->execute([$sessionId, $stuId, $stVal, $rem ?: null]);
            }

            $this->pdo->commit();

            if ($this->logger) {
                $this->logger->log(
                    'attendance.save',
                    "บันทึกเวลาเรียน วันที่ {$sessionDate} คาบที่ {$period} ({$hours} ชม.)",
                    'pp5_attendance_sessions',
                    $sessionId,
                    $courseId
                );
            }

            return $sessionId;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * ลบคาบเรียนที่บันทึกไว้
     */
    public function deleteSession(int $courseId, int $sessionId): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM `pp5_attendance_sessions` WHERE `id` = ? AND `course_id` = ?");
        $stmt->execute([$sessionId, $courseId]);
        $deleted = $stmt->rowCount() > 0;
        if ($deleted && $this->logger) {
            $this->logger->log('attendance.delete', "ลบคาบเรียน #{$sessionId}", 'pp5_attendance_sessions', $sessionId, $courseId);
        }
        return $deleted;
    }

    /**
     * ดึงการตั้งค่าเกณฑ์การนับเวลาเรียนและการติด มส (< 80%)
     */
    public function getCountingRules(): array
    {
        $defaults = [
            'min_attendance_percent' => 80.0,
            'count_late_as_present'  => true,
            'count_leave_as_present' => false,
            'count_sick_as_present'  => false,
        ];

        try {
            if ($this->pdo) {
                $stmt = $this->pdo->query("SELECT * FROM `pp5_school_settings` LIMIT 1");
                $row  = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;
                if ($row) {
                    return [
                        'min_attendance_percent' => isset($row['min_attendance_percent']) ? (float)$row['min_attendance_percent'] : 80.0,
                        'count_late_as_present'  => !empty($row['count_late_as_present']),
                        'count_leave_as_present' => !empty($row['count_leave_as_present']),
                        'count_sick_as_present'  => !empty($row['count_sick_as_present']),
                    ];
                }
            }
        } catch (Throwable $e) {
            // fallback to defaults
        }

        return $defaults;
    }

    /**
     * ดึงสรุปเวลาเรียนของนักเรียนทุกคนในรายวิชา
     *
     * @param int $courseId
     * @param int[] $studentIds
     * @return array<int, array>
     */
    public function getCourseStudentsAttendanceSummary(int $courseId, array $studentIds): array
    {
        $rules    = $this->getCountingRules();
        $sessions = $this->getCourseSessions($courseId);
        $totalCourseHours = 0.0;
        foreach ($sessions as $s) {
            $totalCourseHours += (float)($s['hours'] ?? 1.0);
        }

        $recordsByStudent = [];
        foreach ($studentIds as $sid) {
            $recordsByStudent[(int)$sid] = [];
        }

        if (!empty($sessions) && !empty($studentIds) && $this->pdo) {
            $sessionIds = array_map(fn($s) => (int)$s['id'], $sessions);
            $sessionHoursMap = [];
            foreach ($sessions as $s) {
                $sessionHoursMap[(int)$s['id']] = (float)($s['hours'] ?? 1.0);
            }

            $inSessions = implode(',', array_fill(0, count($sessionIds), '?'));
            $inStudents = implode(',', array_fill(0, count($studentIds), '?'));
            $stmt = $this->pdo->prepare("
                SELECT `session_id`, `student_id`, `status`
                FROM `pp5_attendances`
                WHERE `session_id` IN ({$inSessions})
                  AND `student_id` IN ({$inStudents})
            ");
            $params = array_merge($sessionIds, array_map('intval', $studentIds));
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($rows as $r) {
                $sid    = (int)$r['student_id'];
                $sessId = (int)$r['session_id'];
                $hrs    = $sessionHoursMap[$sessId] ?? 1.0;
                $recordsByStudent[$sid][] = [
                    'session_id' => $sessId,
                    'status'     => $r['status'],
                    'hours'      => $hrs,
                ];
            }
        }

        $summaryMap = [];
        foreach ($studentIds as $sid) {
            $sid  = (int)$sid;
            $recs = $recordsByStudent[$sid] ?? [];
            $summaryMap[$sid] = $this->calculateStudentAttendanceSummary($recs, $totalCourseHours, $rules);
        }

        return $summaryMap;
    }

    /**
     * ซิงค์ข้อมูลการเช็คชื่อจากระบบเช็คชื่อรายคาบ (att_attendance) เข้าสู่ ปพ.5 อัตโนมัติ
     */
    public function syncFromLlwAttendance(int $courseId, ?int $academicYear = null, ?int $semester = null): array
    {
        $stmtCourse = $this->pdo->prepare("
            SELECT cr.*, s.`code` AS `subject_code`, c.`classroom_name`
            FROM `pp5_courses` cr
            INNER JOIN `pp5_subjects` s ON s.`id` = cr.`subject_id`
            INNER JOIN `pp5_classrooms` c ON c.`id` = cr.`classroom_id`
            WHERE cr.`id` = ? LIMIT 1
        ");
        $stmtCourse->execute([$courseId]);
        $course = $stmtCourse->fetch(PDO::FETCH_ASSOC);
        if (!$course) {
            return ['synced_sessions' => 0, 'synced_records' => 0];
        }

        $academicYear = ($academicYear !== null && $academicYear > 2500) ? $academicYear : (int)($course['academic_year'] ?? 2569);
        $semester     = ($semester !== null && $semester >= 1 && $semester <= 3) ? $semester : (int)($course['semester'] ?? 2);

        $attSubjectId = (int)($course['att_subject_id'] ?? 0);
        if ($attSubjectId <= 0) {
            $stmtFind = $this->pdo->prepare("
                SELECT `id` FROM `att_subjects`
                WHERE `subject_code` = ? AND `classroom` = ?
                ORDER BY (`academic_year` = ? AND `semester` = ?) DESC, `id` DESC
                LIMIT 1
            ");
            $stmtFind->execute([$course['subject_code'], $course['classroom_name'], $academicYear, $semester]);
            $attSubjectId = (int)$stmtFind->fetchColumn();
        }

        if ($attSubjectId <= 0) {
            return ['synced_sessions' => 0, 'synced_records' => 0];
        }

        $termDates = function_exists('llw_get_term_dates')
            ? llw_get_term_dates($academicYear, $semester, $this->pdo)
            : ['start_date' => '2025-05-01', 'end_date' => '2027-04-30'];

        $stmtRaw = $this->pdo->prepare("
            SELECT `date`, `period`, `student_id`, `status`, `note`
            FROM `att_attendance`
            WHERE `subject_id` = ? AND `date` BETWEEN ? AND ?
            ORDER BY `date` ASC, `period` ASC
        ");
        $stmtRaw->execute([$attSubjectId, $termDates['start_date'], $termDates['end_date']]);
        $rows = $stmtRaw->fetchAll(PDO::FETCH_ASSOC);
        if (empty($rows)) {
            return ['synced_sessions' => 0, 'synced_records' => 0];
        }

        $statusMap = [
            'มา'  => 'present',
            'สาย' => 'late',
            'ลา'  => 'leave',
            'ขาด' => 'absent',
            'โดด' => 'absent',
        ];

        $grouped = [];
        foreach ($rows as $r) {
            $key = $r['date'] . '#' . (int)$r['period'];
            $grouped[$key]['date']   = $r['date'];
            $grouped[$key]['period'] = max(1, (int)$r['period']);
            $grouped[$key]['statuses'][(int)$r['student_id']] = $statusMap[$r['status']] ?? 'present';
            $grouped[$key]['remarks'][(int)$r['student_id']]  = $r['note'] ?? '';
        }

        $syncedSessions = 0;
        foreach ($grouped as $g) {
            $this->saveSessionAttendance(
                $courseId,
                $g['date'],
                $g['period'],
                1.0,
                'ซิงค์จากระบบเช็คชื่อรายคาบ',
                $g['statuses'],
                $g['remarks']
            );
            $syncedSessions++;
        }

        return ['synced_sessions' => $syncedSessions, 'synced_records' => count($rows)];
    }
}
