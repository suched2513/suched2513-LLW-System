<?php
namespace PP5\App\Services;

use PDO;
use Throwable;

/**
 * MasterDataService — บริการจัดการข้อมูลพื้นฐานของระบบ ปพ.5 ออนไลน์ (Service Layer)
 */
class MasterDataService
{
    private PDO $pdo;
    private ActivityLogger $logger;

    public function __construct(PDO $pdo)
    {
        $this->pdo    = $pdo;
        $this->logger = new ActivityLogger($pdo);
    }

    /**
     * ดึงข้อมูลตั้งค่าโรงเรียนและเกณฑ์การประเมิน
     */
    public function getSchoolSettings(): array
    {
        $row = $this->pdo->query("SELECT * FROM `pp5_school_settings` ORDER BY `id` ASC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        return $row ?: [
            'school_name'              => 'โรงเรียนละลมวิทยา',
            'school_code'              => '1033720344',
            'affiliation'              => 'สำนักงานเขตพื้นที่การศึกษามัธยมศึกษาศรีสะเกษ ยโสธร',
            'province'                 => 'ศรีสะเกษ',
            'director_name'            => 'ผู้อำนวยการโรงเรียนละลมวิทยา',
            'director_position'        => 'ผู้อำนวยการโรงเรียนละลมวิทยา',
            'deputy_academic_name'     => 'รองผู้อำนวยการกลุ่มบริหารวิชาการ',
            'deputy_academic_position' => 'รองผู้อำนวยการกลุ่มบริหารวิชาการ',
            'measurement_head_name'    => 'หัวหน้างานวัดและประเมินผล',
            'min_attendance_percent'   => 80.00,
            'count_late_as_present'    => 1,
            'count_leave_as_present'   => 0,
            'count_sick_as_present'    => 0,
            'assessment_summary_mode'  => 'mode',
            'zero_item_forces_fail'    => 1,
            'mean_min_excellent'       => 2.50,
            'mean_min_good'            => 1.50,
            'mean_min_pass'            => 1.00,
        ];
    }

    /**
     * บันทึกการตั้งค่าโรงเรียนและเกณฑ์การประเมิน
     */
    public function saveSchoolSettings(array $data): void
    {
        $old = $this->getSchoolSettings();
        $stmt = $this->pdo->prepare("
            UPDATE `pp5_school_settings`
            SET `school_name`              = :school_name,
                `school_code`              = :school_code,
                `affiliation`              = :affiliation,
                `province`                 = :province,
                `director_name`            = :director_name,
                `director_position`        = :director_position,
                `deputy_academic_name`     = :deputy_academic_name,
                `deputy_academic_position` = :deputy_academic_position,
                `measurement_head_name`    = :measurement_head_name,
                `min_attendance_percent`   = :min_attendance_percent,
                `count_late_as_present`    = :count_late_as_present,
                `count_leave_as_present`   = :count_leave_as_present,
                `count_sick_as_present`    = :count_sick_as_present,
                `assessment_summary_mode`  = :assessment_summary_mode,
                `zero_item_forces_fail`    = :zero_item_forces_fail,
                `mean_min_excellent`       = :mean_min_excellent,
                `mean_min_good`            = :mean_min_good,
                `mean_min_pass`            = :mean_min_pass
            WHERE `id` = 1
        ");
        $stmt->execute($data);
        $this->logger->log('settings.update', 'อัปเดตการตั้งค่าโรงเรียนและเกณฑ์การวัดผล', 'pp5_school_settings', 1, null, $old, $data);
    }

    /**
     * ดึงเกณฑ์การตัดเกรดทั้งหมด
     */
    public function getGradeCriteria(): array
    {
        return $this->pdo->query("SELECT * FROM `pp5_grade_criteria` ORDER BY `sort_order` ASC, `min_score` DESC")->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * บันทึกเกณฑ์การตัดเกรด
     */
    public function saveGradeCriteria(array $criteriaData): void
    {
        $old = $this->getGradeCriteria();
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare("
                UPDATE `pp5_grade_criteria`
                SET `min_score` = ?, `max_score` = ?
                WHERE `grade` = ?
            ");
            foreach ($criteriaData as $grade => $bounds) {
                $stmt->execute([(int)$bounds['min_score'], (int)$bounds['max_score'], (string)$grade]);
            }
            $this->pdo->commit();
            $this->logger->log('grade_criteria.update', 'ปรับปรุงเกณฑ์การตัดเกรดระดับโรงเรียน', 'pp5_grade_criteria', null, null, $old, $criteriaData);
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * บันทึกปีการศึกษาและภาคเรียน
     */
    public function saveAcademicTerm(array $data): void
    {
        $this->pdo->beginTransaction();
        try {
            if (!empty($data['is_active'])) {
                $this->pdo->exec("UPDATE `llw_academic_terms` SET `is_active` = 0");
            }
            $stmt = $this->pdo->prepare("
                INSERT INTO `llw_academic_terms` (`academic_year`, `semester`, `start_date`, `end_date`, `is_active`)
                VALUES (?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    `start_date` = VALUES(`start_date`),
                    `end_date`   = VALUES(`end_date`),
                    `is_active`  = VALUES(`is_active`)
            ");
            $stmt->execute([
                $data['academic_year'],
                $data['semester'],
                $data['start_date'],
                $data['end_date'],
                $data['is_active'],
            ]);

            // สร้างห้องเรียนมาตรฐาน 19 ห้องให้ภาคเรียนนี้ด้วยหากยังไม่มี
            $this->ensureClassroomsForTerm((int)$data['academic_year'], (int)$data['semester']);

            $this->pdo->commit();
            $this->logger->log('academic_term.save', "บันทึกภาคเรียนที่ {$data['semester']}/{$data['academic_year']}", 'llw_academic_terms', null, null, null, $data);
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * สร้างห้องเรียนมาตรฐาน 19 ห้องสำหรับปีการศึกษาและภาคเรียนที่ระบุ (หากยังไม่มี)
     */
    public function ensureClassroomsForTerm(int $academicYear, int $semester): void
    {
        // ลบห้อง ม.1/4 ที่ไม่มีอยู่จริงออก
        $this->pdo->exec("DELETE FROM `pp5_classrooms` WHERE `classroom_name` = 'ม.1/4'");

        $standardRooms = [
            [1, 1, 'ม.1/1', 'วิทย์-คณิต'], [1, 2, 'ม.1/2', 'วิทย์-คณิต'], [1, 3, 'ม.1/3', 'ทั่วไป'],
            [2, 1, 'ม.2/1', 'วิทย์-คณิต'], [2, 2, 'ม.2/2', 'ทั่วไป'],     [2, 3, 'ม.2/3', 'ทั่วไป'],     [2, 4, 'ม.2/4', 'ทั่วไป'],
            [3, 1, 'ม.3/1', 'วิทย์-คณิต'], [3, 2, 'ม.3/2', 'ทั่วไป'],     [3, 3, 'ม.3/3', 'ทั่วไป'],
            [4, 1, 'ม.4/1', 'วิทย์-คณิต'], [4, 2, 'ม.4/2', 'ศิลป์-ภาษา'], [4, 3, 'ม.4/3', 'ศิลป์-ทั่วไป'],
            [5, 1, 'ม.5/1', 'วิทย์-คณิต'], [5, 2, 'ม.5/2', 'ศิลป์-ภาษา'], [5, 3, 'ม.5/3', 'ศิลป์-ทั่วไป'],
            [6, 1, 'ม.6/1', 'วิทย์-คณิต'], [6, 2, 'ม.6/2', 'ศิลป์-ภาษา'], [6, 3, 'ม.6/3', 'ศิลป์-ทั่วไป'],
        ];
        $stmt = $this->pdo->prepare("
            INSERT IGNORE INTO `pp5_classrooms` (`academic_year`, `semester`, `grade_level`, `room_no`, `classroom_name`, `study_plan`)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        foreach ($standardRooms as [$gl, $rn, $cn, $sp]) {
            $stmt->execute([$academicYear, $semester, $gl, $rn, $cn, $sp]);
        }
    }

    /**
     * ดึงกลุ่มสาระการเรียนรู้ทั้งหมด พร้อมชื่อหัวหน้ากลุ่มสาระและจำนวนวิชา
     */
    public function getDepartments(): array
    {
        return $this->pdo->query("
            SELECT d.*,
                   TRIM(CONCAT(COALESCE(u.`prefix`, ''), COALESCE(u.`firstname`, ''), ' ', COALESCE(u.`lastname`, ''))) AS `head_name`,
                   (SELECT COUNT(*) FROM `pp5_subjects` s WHERE s.`department_id` = d.`id`) AS `subject_count`,
                   (SELECT COUNT(*) FROM `llw_users` u2 WHERE u2.`department_id` = d.`id` AND u2.`status` = 'active') AS `teacher_count`
            FROM `pp5_departments` d
            LEFT JOIN `llw_users` u ON u.`user_id` = d.`head_user_id`
            ORDER BY d.`sort_order` ASC, d.`id` ASC
        ")->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * อัปเดตหัวหน้ากลุ่มสาระการเรียนรู้
     */
    public function updateDepartmentHead(int $departmentId, ?int $headUserId): void
    {
        $stmt = $this->pdo->prepare("UPDATE `pp5_departments` SET `head_user_id` = ? WHERE `id` = ?");
        $stmt->execute([$headUserId ?: null, $departmentId]);

        if ($headUserId && $headUserId > 0) {
            $this->pdo->prepare("UPDATE `llw_users` SET `department_id` = ? WHERE `user_id` = ?")->execute([$departmentId, $headUserId]);
        }
        $this->logger->log('department.update_head', "ตั้งค่าหัวหน้ากลุ่มสาระ ID #{$departmentId}", 'pp5_departments', $departmentId, null, null, ['head_user_id' => $headUserId]);
    }

    /**
     * ดึงรายชื่อครูและบุคลากรทั้งหมดในระบบ
     */
    public function getAllTeachers(): array
    {
        return $this->pdo->query("
            SELECT u.`user_id`, u.`username`, u.`prefix`, u.`firstname`, u.`lastname`,
                   TRIM(CONCAT(COALESCE(u.`prefix`, ''), COALESCE(u.`firstname`, ''), ' ', COALESCE(u.`lastname`, ''))) AS `full_name`,
                   u.`role`, u.`department_id`, u.`email`, u.`status`,
                   d.`name` AS `department_name`
            FROM `llw_users` u
            LEFT JOIN `pp5_departments` d ON d.`id` = u.`department_id`
            WHERE u.`role` != 'student' AND u.`status` = 'active'
            ORDER BY u.`firstname` ASC, u.`lastname` ASC
        ")->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * ดึงห้องเรียนทั้ง 19 ห้องในปีการศึกษา/ภาคเรียนที่ระบุ พร้อมจำนวนนักเรียนและชื่อครูที่ปรึกษา
     */
    public function getClassrooms(int $academicYear, int $semester): array
    {
        $this->ensureClassroomsForTerm($academicYear, $semester);

        $stmt = $this->pdo->prepare("
            SELECT c.*,
                   TRIM(CONCAT(COALESCE(u1.`prefix`, ''), COALESCE(u1.`firstname`, ''), ' ', COALESCE(u1.`lastname`, ''))) AS `advisor_name`,
                   TRIM(CONCAT(COALESCE(u2.`prefix`, ''), COALESCE(u2.`firstname`, ''), ' ', COALESCE(u2.`lastname`, ''))) AS `co_advisor_name`,
                   (SELECT COUNT(*) FROM `pp5_classroom_students` cs WHERE cs.`classroom_id` = c.`id` AND cs.`status` = 'active') AS `student_count`,
                   (SELECT COUNT(*) FROM `pp5_courses` cr WHERE cr.`classroom_id` = c.`id`) AS `course_count`
            FROM `pp5_classrooms` c
            LEFT JOIN `llw_users` u1 ON u1.`user_id` = c.`advisor_user_id`
            LEFT JOIN `llw_users` u2 ON u2.`user_id` = c.`co_advisor_user_id`
            WHERE c.`academic_year` = ? AND c.`semester` = ?
            ORDER BY c.`grade_level` ASC, c.`room_no` ASC
        ");
        $stmt->execute([$academicYear, $semester]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * อัปเดตข้อมูลห้องเรียนและครูที่ปรึกษา พร้อมซิงค์กับ llw_class_advisors
     */
    public function updateClassroomAdvisor(int $classroomId, string $studyPlan, ?int $advisorId, ?int $coAdvisorId): void
    {
        $stmtGet = $this->pdo->prepare("SELECT * FROM `pp5_classrooms` WHERE `id` = ? LIMIT 1");
        $stmtGet->execute([$classroomId]);
        $cls = $stmtGet->fetch(PDO::FETCH_ASSOC);
        if (!$cls) {
            return;
        }

        $stmt = $this->pdo->prepare("
            UPDATE `pp5_classrooms`
            SET `study_plan` = ?, `advisor_user_id` = ?, `co_advisor_user_id` = ?
            WHERE `id` = ?
        ");
        $stmt->execute([
            trim($studyPlan) ?: 'ทั่วไป',
            $advisorId ?: null,
            ($coAdvisorId && $coAdvisorId !== $advisorId) ? $coAdvisorId : null,
            $classroomId,
        ]);

        // ซิงค์ไปยังตารางส่วนกลาง llw_class_advisors
        try {
            $roomName = $cls['classroom_name'];
            $this->pdo->prepare("DELETE FROM `llw_class_advisors` WHERE `classroom` = ?")->execute([$roomName]);
            $insAdv = $this->pdo->prepare("INSERT IGNORE INTO `llw_class_advisors` (`classroom`, `user_id`) VALUES (?, ?)");
            if ($advisorId && $advisorId > 0) {
                $insAdv->execute([$roomName, $advisorId]);
            }
            if ($coAdvisorId && $coAdvisorId > 0 && $coAdvisorId !== $advisorId) {
                $insAdv->execute([$roomName, $coAdvisorId]);
            }
        } catch (Throwable $e) {}

        $this->logger->log('classroom.update_advisor', "อัปเดตครูที่ปรึกษาห้อง {$cls['classroom_name']}", 'pp5_classrooms', $classroomId);
    }

    /**
     * ดึงรายชื่อนักเรียนในห้องเรียน พร้อมถอดรหัส/ปิดบังเลขบัตรประชาชนตามสิทธิ์ PDPA
     */
    public function getStudentsByClassroom(int $academicYear, int $semester, string $classroomName, bool $canViewFullCitizenId = false): array
    {
        $stmt = $this->pdo->prepare("
            SELECT s.`id`, s.`student_id` AS `student_code`, s.`prefix`, s.`first_name`, s.`last_name`,
                   s.`name`, s.`gender`, s.`classroom`, s.`status`,
                   s.`citizen_id_encrypted`, s.`national_id_masked`,
                   COALESCE(cs.`student_number`, s.`student_number`, 1) AS `student_number`,
                   COALESCE(cs.`status`, s.`status`, 'active') AS `term_status`
            FROM `att_students` s
            LEFT JOIN `pp5_classrooms` c
                ON c.`classroom_name` = s.`classroom`
               AND c.`academic_year` = ?
               AND c.`semester` = ?
            LEFT JOIN `pp5_classroom_students` cs
                ON cs.`classroom_id` = c.`id`
               AND cs.`student_id` = s.`id`
            WHERE s.`classroom` = ?
              AND s.`student_id` REGEXP '^[0-9]+$'
            ORDER BY COALESCE(cs.`student_number`, s.`student_number`, 999) ASC, s.`student_id` ASC
        ");
        $stmt->execute([$academicYear, $semester, $classroomName]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as &$r) {
            $plainCitizen = null;
            if (!empty($r['citizen_id_encrypted'])) {
                $plainCitizen = CitizenIdCrypto::decrypt($r['citizen_id_encrypted']);
            }
            if ($canViewFullCitizenId) {
                $r['citizen_id_display'] = $plainCitizen
                    ? CitizenIdCrypto::formatFull($plainCitizen)
                    : ($r['national_id_masked'] ?: '-');
                $r['citizen_id_raw'] = $plainCitizen ?: '';
            } else {
                $r['citizen_id_display'] = $plainCitizen
                    ? CitizenIdCrypto::formatMasked($plainCitizen)
                    : CitizenIdCrypto::formatMasked($r['national_id_masked'] ?? '');
                $r['citizen_id_raw'] = '';
            }
        }
        unset($r);

        return $rows;
    }

    /**
     * บันทึกข้อมูลนักเรียน (เพิ่มใหม่หรือแก้ไข) พร้อมเข้ารหัสเลขบัตรประชาชนตาม PDPA
     */
    public function saveStudent(array $data, int $academicYear, int $semester): int
    {
        $this->pdo->beginTransaction();
        try {
            $encryptedNid = null;
            $hashNid      = null;
            $maskedNid    = null;

            if (!empty($data['citizen_id'])) {
                $encryptedNid = CitizenIdCrypto::encrypt($data['citizen_id']);
                $hashNid      = password_hash($data['citizen_id'], PASSWORD_BCRYPT);
                $maskedNid    = CitizenIdCrypto::formatFull($data['citizen_id']);
            }

            $studentId = (int)($data['id'] ?? 0);
            if ($studentId <= 0) {
                $chk = $this->pdo->prepare("SELECT `id` FROM `att_students` WHERE `student_id` = ? LIMIT 1");
                $chk->execute([$data['student_code']]);
                $studentId = (int)$chk->fetchColumn();
            }

            if ($studentId > 0) {
                $sql = "
                    UPDATE `att_students`
                    SET `student_id`     = :code,
                        `prefix`         = :prefix,
                        `first_name`     = :fname,
                        `last_name`      = :lname,
                        `name`           = :full_name,
                        `gender`         = :gender,
                        `classroom`      = :classroom,
                        `student_number` = :num,
                        `status`         = :status
                ";
                $params = [
                    ':code'      => $data['student_code'],
                    ':prefix'    => $data['prefix'],
                    ':fname'     => $data['first_name'],
                    ':lname'     => $data['last_name'],
                    ':full_name' => $data['full_name'],
                    ':gender'    => $data['gender'],
                    ':classroom' => $data['classroom'],
                    ':num'       => $data['student_number'],
                    ':status'    => $data['status'],
                    ':id'        => $studentId,
                ];
                if ($encryptedNid !== null) {
                    $sql .= ", `citizen_id_encrypted` = :enc, `national_id_hash` = :hash, `national_id_masked` = :masked";
                    $params[':enc']    = $encryptedNid;
                    $params[':hash']   = $hashNid;
                    $params[':masked'] = $maskedNid;
                }
                $sql .= " WHERE `id` = :id";
                $this->pdo->prepare($sql)->execute($params);
            } else {
                $stmtIns = $this->pdo->prepare("
                    INSERT INTO `att_students` (
                        `student_id`, `prefix`, `first_name`, `last_name`, `name`, `gender`,
                        `classroom`, `student_number`, `citizen_id_encrypted`, `national_id_hash`, `national_id_masked`, `status`
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmtIns->execute([
                    $data['student_code'],
                    $data['prefix'],
                    $data['first_name'],
                    $data['last_name'],
                    $data['full_name'],
                    $data['gender'],
                    $data['classroom'],
                    $data['student_number'],
                    $encryptedNid,
                    $hashNid,
                    $maskedNid,
                    $data['status'],
                ]);
                $studentId = (int)$this->pdo->lastInsertId();
            }

            // ซิงค์เข้า pp5_classroom_students
            $stmtCls = $this->pdo->prepare("SELECT `id` FROM `pp5_classrooms` WHERE `academic_year` = ? AND `semester` = ? AND `classroom_name` = ? LIMIT 1");
            $stmtCls->execute([$academicYear, $semester, $data['classroom']]);
            $clsId = (int)$stmtCls->fetchColumn();

            if ($clsId > 0) {
                $stmtCs = $this->pdo->prepare("
                    INSERT INTO `pp5_classroom_students` (`classroom_id`, `student_id`, `student_code`, `student_number`, `status`)
                    VALUES (?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE
                        `student_code`   = VALUES(`student_code`),
                        `student_number` = VALUES(`student_number`),
                        `status`         = VALUES(`status`)
                ");
                $stmtCs->execute([$clsId, $studentId, $data['student_code'], $data['student_number'], $data['status']]);
            }

            $this->pdo->commit();
            $this->logger->log('student.save', "บันทึกข้อมูลนักเรียน {$data['student_code']} {$data['full_name']} ({$data['classroom']})", 'att_students', $studentId);
            return $studentId;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * ดึงรายวิชาทั้งหมดตามตัวกรองกลุ่มสาระและระดับชั้น
     */
    public function getSubjects(?int $departmentId = null, ?int $gradeLevel = null): array
    {
        $where  = ['1=1'];
        $params = [];
        if ($departmentId !== null && $departmentId > 0) {
            $where[]  = 's.`department_id` = ?';
            $params[] = $departmentId;
        }
        if ($gradeLevel !== null && $gradeLevel > 0) {
            $where[]  = 's.`grade_level` = ?';
            $params[] = $gradeLevel;
        }

        $sql = "
            SELECT s.*, d.`name` AS `department_name`, d.`code` AS `department_code`,
                   (SELECT COUNT(*) FROM `pp5_indicators` ind WHERE ind.`subject_id` = s.`id`) AS `indicator_count`
            FROM `pp5_subjects` s
            INNER JOIN `pp5_departments` d ON d.`id` = s.`department_id`
            WHERE " . implode(' AND ', $where) . "
            ORDER BY d.`sort_order` ASC, s.`grade_level` ASC, s.`code` ASC
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * บันทึกรายวิชา (pp5_subjects)
     */
    public function saveSubject(array $data): int
    {
        $desc = isset($data['description']) ? trim((string)$data['description']) : null;
        if (!empty($data['id'])) {
            $stmt = $this->pdo->prepare("
                UPDATE `pp5_subjects`
                SET `code` = ?, `name` = ?, `department_id` = ?, `type` = ?, `credit` = ?, `hours` = ?, `grade_level` = ?, `description` = ?
                WHERE `id` = ?
            ");
            $stmt->execute([
                $data['code'], $data['name'], $data['department_id'],
                $data['type'], $data['credit'], $data['hours'], $data['grade_level'],
                $desc,
                $data['id'],
            ]);
            $id = (int)$data['id'];
        } else {
            $stmt = $this->pdo->prepare("
                INSERT INTO `pp5_subjects` (`code`, `name`, `department_id`, `type`, `credit`, `hours`, `grade_level`, `description`)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    `name`          = VALUES(`name`),
                    `department_id` = VALUES(`department_id`),
                    `type`          = VALUES(`type`),
                    `credit`        = VALUES(`credit`),
                    `hours`         = VALUES(`hours`),
                    `grade_level`   = VALUES(`grade_level`),
                    `description`   = VALUES(`description`)
            ");
            $stmt->execute([
                $data['code'], $data['name'], $data['department_id'],
                $data['type'], $data['credit'], $data['hours'], $data['grade_level'],
                $desc,
            ]);
            $id = (int)$this->pdo->lastInsertId();
            if ($id === 0) {
                $st = $this->pdo->prepare("SELECT `id` FROM `pp5_subjects` WHERE `code` = ? LIMIT 1");
                $st->execute([$data['code']]);
                $id = (int)$st->fetchColumn();
            }
        }
        $this->logger->log('subject.save', "บันทึกรายวิชา {$data['code']} {$data['name']}", 'pp5_subjects', $id, null, null, $data);
        return $id;
    }

    /**
     * ดึงตัวชี้วัดของรายวิชา
     */
    public function getSubjectIndicators(int $subjectId): array
    {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM `pp5_indicators` WHERE `subject_id` = ? ORDER BY `sort_order` ASC, `id` ASC");
            $stmt->execute([$subjectId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            error_log('[PP5 getSubjectIndicators] ' . $e->getMessage());
            return [];
        }
    }

    /**
     * ดึงตัวชี้วัดทั้งหมดของรายวิชา (alias ของ getSubjectIndicators)
     */
    public function getIndicators(int $subjectId): array
    {
        return $this->getSubjectIndicators($subjectId);
    }

    /**
     * บันทึกตัวชี้วัดของรายวิชา (รองรับทั้งแบบส่ง parameters แยก หรือส่งเป็น array ข้อมูล)
     */
    public function saveIndicator($subjectIdOrData, ?string $code = null, ?string $description = null, int $sortOrder = 1): void
    {
        $id = 0;
        if (is_array($subjectIdOrData)) {
            $id          = (int)($subjectIdOrData['id'] ?? 0);
            $subjectId   = (int)($subjectIdOrData['subject_id'] ?? 0);
            $code        = (string)($subjectIdOrData['code'] ?? '');
            $description = (string)($subjectIdOrData['description'] ?? '');
            $sortOrder   = (int)($subjectIdOrData['sort_order'] ?? 1);
        } else {
            $subjectId   = (int)$subjectIdOrData;
            $code        = (string)$code;
            $description = (string)$description;
        }

        if ($id > 0) {
            $stmt = $this->pdo->prepare("
                UPDATE `pp5_indicators`
                SET `code` = ?, `description` = ?, `sort_order` = ?
                WHERE `id` = ? AND `subject_id` = ?
            ");
            $stmt->execute([trim($code), trim($description), $sortOrder, $id, $subjectId]);
        } else {
            $stmt = $this->pdo->prepare("
                INSERT INTO `pp5_indicators` (`subject_id`, `code`, `description`, `sort_order`)
                VALUES (?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    `description` = VALUES(`description`),
                    `sort_order`  = VALUES(`sort_order`)
            ");
            $stmt->execute([$subjectId, trim($code), trim($description), $sortOrder]);
        }

        if ($this->logger) {
            $this->logger->log('indicator.save', "บันทึกตัวชี้วัด {$code} ของวิชา #{$subjectId}", 'pp5_indicators', $subjectId);
        }
    }

    /**
     * ลบตัวชี้วัด
     */
    public function deleteIndicator(int $indicatorId): void
    {
        $this->pdo->prepare("DELETE FROM `pp5_indicators` WHERE `id` = ?")->execute([$indicatorId]);
    }

    /**
     * บันทึกคำอธิบายรายวิชา (บันทึกทั้งระดับห้องเรียน และสำรองไว้ที่ระดับรายวิชาแม่)
     */
    public function saveCourseDescription(int $courseId, int $subjectId, string $description, ?int $actorId = null): void
    {
        $desc = trim($description);
        $stmt = $this->pdo->prepare("UPDATE `pp5_courses` SET `description` = ? WHERE `id` = ?");
        $stmt->execute([$desc, $courseId]);

        // ถ้าที่รายวิชาแม่ยังไม่มี ให้บันทึกลง pp5_subjects ด้วย
        if ($subjectId > 0 && $desc !== '') {
            $stmtSubj = $this->pdo->prepare("UPDATE `pp5_subjects` SET `description` = ? WHERE `id` = ? AND (`description` IS NULL OR `description` = '')");
            $stmtSubj->execute([$desc, $subjectId]);
        }

        if ($this->logger) {
            $this->logger->log('course.save_description', "บันทึกคำอธิบายรายวิชา ปพ.5 #{$courseId}", 'pp5_courses', $courseId, $courseId, null, ['description_length' => mb_strlen($desc)]);
        }
    }

    /**
     * บันทึกแผนการวัดและประเมินผลตัวชี้วัด (K, P, A, กลางภาค, ปลายภาค) แบบกลุ่ม
     */
    public function saveIndicatorKpaBatch(int $subjectId, array $items, ?int $actorId = null): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE `pp5_indicators`
            SET `score_k`       = ?,
                `score_p`       = ?,
                `score_a`       = ?,
                `score_midterm` = ?,
                `score_final`   = ?
            WHERE `id` = ? AND `subject_id` = ?
        ");

        foreach ($items as $item) {
            $indId = (int)($item['id'] ?? 0);
            if ($indId <= 0) continue;
            $k   = max(0.0, (float)($item['score_k'] ?? 0.0));
            $p   = max(0.0, (float)($item['score_p'] ?? 0.0));
            $a   = max(0.0, (float)($item['score_a'] ?? 0.0));
            $mid = max(0.0, (float)($item['score_midterm'] ?? 0.0));
            $fin = max(0.0, (float)($item['score_final'] ?? 0.0));

            $stmt->execute([$k, $p, $a, $mid, $fin, $indId, $subjectId]);
        }

        if ($this->logger) {
            $this->logger->log('indicator.save_kpa', "บันทึกแผนการวัดผลประเมินผลตัวชี้วัด (K-P-A) วิชา #{$subjectId}", 'pp5_indicators', $subjectId, null, null, ['items_count' => count($items)]);
        }
    }

    /**
     * ดึงรายการ ปพ.5 (pp5_courses: 1 วิชา × 1 ห้อง = 1 ปพ.5) ประจำภาคเรียน
     */
    public function getCourses(
        int $academicYear,
        int $semester,
        ?int $departmentId = null,
        ?int $gradeLevel = null,
        ?int $teacherUserId = null,
        ?string $status = null,
        ?string $classroomName = null
    ): array {
        $where  = ['cr.`academic_year` = ?', 'cr.`semester` = ?'];
        $params = [$academicYear, $semester];

        if ($departmentId !== null && $departmentId > 0) {
            $where[]  = 's.`department_id` = ?';
            $params[] = $departmentId;
        }
        if ($gradeLevel !== null && $gradeLevel > 0) {
            $where[]  = 'c.`grade_level` = ?';
            $params[] = $gradeLevel;
        }
        if ($teacherUserId !== null && $teacherUserId > 0) {
            $where[]  = '(cr.`teacher_user_id` = ? OR EXISTS (SELECT 1 FROM `pp5_course_teachers` ct WHERE ct.`course_id` = cr.`id` AND ct.`user_id` = ?))';
            $params[] = $teacherUserId;
            $params[] = $teacherUserId;
        }
        if ($status !== null && $status !== '') {
            $where[]  = 'cr.`status` = ?';
            $params[] = $status;
        }
        if ($classroomName !== null && $classroomName !== '') {
            $where[]  = 'c.`classroom_name` = ?';
            $params[] = $classroomName;
        }

        $sql = "
            SELECT cr.*,
                   s.`code` AS `subject_code`, s.`name` AS `subject_name`,
                   s.`type` AS `subject_type`, s.`credit`, s.`hours`, s.`department_id`,
                   d.`name` AS `department_name`, d.`code` AS `department_code`, d.`head_user_id`,
                   c.`classroom_name`, c.`grade_level`, c.`room_no`, c.`advisor_user_id`,
                   TRIM(CONCAT(COALESCE(u.`prefix`, ''), COALESCE(u.`firstname`, ''), ' ', COALESCE(u.`lastname`, ''))) AS `teacher_name`,
                   (SELECT COUNT(*) FROM `pp5_classroom_students` cs WHERE cs.`classroom_id` = cr.`classroom_id` AND cs.`status` = 'active') AS `student_count`,
                   (SELECT COUNT(*) FROM `pp5_score_items` si WHERE si.`course_id` = cr.`id`) AS `score_item_count`,
                   (SELECT COALESCE(SUM(si2.`max_score`), 0) FROM `pp5_score_items` si2 WHERE si2.`course_id` = cr.`id`) AS `total_max_score`,
                   (SELECT COUNT(*) FROM `pp5_attendance_sessions` ats WHERE ats.`course_id` = cr.`id`) AS `session_count`
            FROM `pp5_courses` cr
            INNER JOIN `pp5_subjects` s ON s.`id` = cr.`subject_id`
            INNER JOIN `pp5_departments` d ON d.`id` = s.`department_id`
            INNER JOIN `pp5_classrooms` c ON c.`id` = cr.`classroom_id`
            LEFT JOIN `llw_users` u ON u.`user_id` = cr.`teacher_user_id`
            WHERE " . implode(' AND ', $where) . "
            ORDER BY c.`grade_level` ASC, c.`room_no` ASC, s.`code` ASC
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * บันทึกการจัดครูผู้สอน / เปิดรายวิชา ปพ.5 (pp5_courses + pp5_course_teachers)
     */
    public function saveCourse(array $data): int
    {
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO `pp5_courses` (
                    `academic_year`, `semester`, `subject_id`, `classroom_id`,
                    `teacher_user_id`, `ratio_collect`, `ratio_final`, `status`
                ) VALUES (?, ?, ?, ?, ?, ?, ?, 'draft')
                ON DUPLICATE KEY UPDATE
                    `teacher_user_id` = VALUES(`teacher_user_id`),
                    `ratio_collect`   = VALUES(`ratio_collect`),
                    `ratio_final`     = VALUES(`ratio_final`)
            ");
            $stmt->execute([
                $data['academic_year'],
                $data['semester'],
                $data['subject_id'],
                $data['classroom_id'],
                $data['teacher_user_id'],
                $data['ratio_collect'],
                $data['ratio_final'],
            ]);

            $courseId = (int)$this->pdo->lastInsertId();
            if ($courseId === 0) {
                $st = $this->pdo->prepare("
                    SELECT `id` FROM `pp5_courses`
                    WHERE `academic_year` = ? AND `semester` = ? AND `subject_id` = ? AND `classroom_id` = ?
                    LIMIT 1
                ");
                $st->execute([$data['academic_year'], $data['semester'], $data['subject_id'], $data['classroom_id']]);
                $courseId = (int)$st->fetchColumn();
            }

            if ($courseId > 0) {
                $this->pdo->prepare("DELETE FROM `pp5_course_teachers` WHERE `course_id` = ?")->execute([$courseId]);
                $insCt = $this->pdo->prepare("INSERT IGNORE INTO `pp5_course_teachers` (`course_id`, `user_id`, `role`) VALUES (?, ?, ?)");
                $insCt->execute([$courseId, $data['teacher_user_id'], 'primary']);
                if (!empty($data['co_teacher_user_id'])) {
                    $insCt->execute([$courseId, (int)$data['co_teacher_user_id'], 'co_teacher']);
                }
            }

            $this->pdo->commit();
            $this->logger->log('course.save', "บันทึกการจัดวิชาเปิดสอน ปพ.5 Course #{$courseId}", 'pp5_courses', $courseId, $courseId, null, $data);
            return $courseId;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * ส่งตรวจเล่ม ปพ.5 (ครูผู้สอนส่งให้หัวหน้ากลุ่มสาระตรวจสอบ)
     */
    public function submitCourse(int $courseId, int $userId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT c.*, s.subject_code, s.subject_name, s.department_id, d.head_user_id
            FROM `pp5_courses` c
            JOIN `pp5_subjects` s ON c.subject_id = s.id
            LEFT JOIN `pp5_departments` d ON s.department_id = d.id
            WHERE c.id = ? LIMIT 1
        ");
        $stmt->execute([$courseId]);
        $course = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$course) {
            return ['ok' => false, 'message' => 'ไม่พบข้อมูลรายวิชา ปพ.5'];
        }

        if ($course['status'] !== 'draft') {
            return ['ok' => false, 'message' => 'รายวิชานี้ถูกส่งตรวจหรืออนุมัติไปแล้ว ไม่สามารถส่งซ้ำได้'];
        }

        // ตรวจสอบความพร้อมของเล่ม ปพ.5
        $reportService = new Pp5ReportService($this->pdo);
        $data = $reportService->compileCourseReportData($courseId);
        if ($data && !empty($data['readiness']) && !$data['readiness']['all_ready']) {
            return [
                'ok'      => false,
                'message' => 'ข้อมูลยังไม่ครบถ้วนตามรายการตรวจสอบความพร้อม กรุณากรอกคะแนน/เวลาเรียน/คุณลักษณะให้ครบทุกช่องก่อนส่งตรวจ',
            ];
        }

        $this->pdo->beginTransaction();
        try {
            $upd = $this->pdo->prepare("
                UPDATE `pp5_courses`
                SET `status` = 'submitted', `submitted_at` = NOW(), `rejected_reason` = NULL, `rejected_by` = NULL
                WHERE `id` = ?
            ");
            $upd->execute([$courseId]);

            $app = $this->pdo->prepare("
                INSERT INTO `pp5_approvals` (`course_id`, `step`, `approver_id`, `action`, `comment`)
                VALUES (?, 'teacher_submit', ?, 'submit', 'ส่งตรวจเล่ม ปพ.5 ต่อหัวหน้ากลุ่มสาระการเรียนรู้')
            ");
            $app->execute([$courseId, $userId]);

            $this->pdo->commit();

            $this->logger->log(
                'course.submit',
                "ส่งตรวจเล่ม ปพ.5 Course #{$courseId} ({$course['subject_code']}) ให้หัวหน้ากลุ่มสาระตรวจ",
                'pp5_courses',
                $courseId,
                $courseId,
                ['status' => 'draft'],
                ['status' => 'submitted']
            );

            // แจ้งเตือนหัวหน้ากลุ่มสาระ
            if (!empty($course['head_user_id'])) {
                $this->logger->notify(
                    (int)$course['head_user_id'],
                    'มีเล่ม ปพ.5 ส่งมาให้ตรวจสอบ',
                    "ครูผู้สอนได้ส่งเล่ม ปพ.5 รายวิชา {$course['subject_code']} ({$course['subject_name']}) ให้ตรวจสอบความถูกต้อง",
                    'info',
                    "/pp5/course_summary.php?course_id={$courseId}",
                    $courseId
                );
            }

            return ['ok' => true, 'message' => 'ส่งตรวจเล่ม ปพ.5 เรียบร้อยแล้ว (สถานะ: รอหัวหน้ากลุ่มสาระตรวจ)'];
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * อนุมัติเล่ม ปพ.5 ตามลำดับขั้น (head -> deputy -> director)
     */
    public function approveCourse(int $courseId, int $userId, string $step, ?string $comment = null): array
    {
        $stmt = $this->pdo->prepare("
            SELECT c.*, s.subject_code, s.subject_name
            FROM `pp5_courses` c
            JOIN `pp5_subjects` s ON c.subject_id = s.id
            WHERE c.id = ? LIMIT 1
        ");
        $stmt->execute([$courseId]);
        $course = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$course) {
            return ['ok' => false, 'message' => 'ไม่พบข้อมูลรายวิชา ปพ.5'];
        }

        $oldStatus = $course['status'];
        $cleanComment = trim((string)$comment);

        $this->pdo->beginTransaction();
        try {
            if ($step === 'head') {
                if ($oldStatus !== 'submitted') {
                    $this->pdo->rollBack();
                    return ['ok' => false, 'message' => 'สถานะปัจจุบันต้องเป็น "รอหัวหน้ากลุ่มสาระตรวจ" จึงจะให้ความเห็นชอบได้'];
                }
                $upd = $this->pdo->prepare("UPDATE `pp5_courses` SET `status` = 'head_approved', `head_approved_at` = NOW() WHERE `id` = ?");
                $upd->execute([$courseId]);

                $appStep = 'head_review';
                $appAction = 'approve';
                $defaultNote = 'ตรวจสอบแล้วถูกต้องครบถ้วน เห็นควรส่งต่อฝ่ายวิชาการ';
                $newStatus = 'head_approved';
                $notifyMsg = "หัวหน้ากลุ่มสาระฯ ได้ตรวจสอบและให้ความเห็นชอบเล่ม ปพ.5 รายวิชา {$course['subject_code']} แล้ว";
            } elseif ($step === 'deputy') {
                if (!in_array($oldStatus, ['head_approved', 'submitted'], true)) {
                    $this->pdo->rollBack();
                    return ['ok' => false, 'message' => 'สถานะปัจจุบันไม่ถูกต้องสำหรับการตรวจเห็นชอบของฝ่ายวิชาการ'];
                }
                $upd = $this->pdo->prepare("UPDATE `pp5_courses` SET `status` = 'deputy_approved', `deputy_approved_at` = NOW() WHERE `id` = ?");
                $upd->execute([$courseId]);

                $appStep = 'deputy_review';
                $appAction = 'approve';
                $defaultNote = 'ตรวจสอบแล้วเห็นควรอนุมัติ เสนอผู้อำนวยการสถานศึกษา';
                $newStatus = 'deputy_approved';
                $notifyMsg = "รองผู้อำนวยการกลุ่มบริหารวิชาการ ได้เห็นควรอนุมัติเล่ม ปพ.5 รายวิชา {$course['subject_code']} แล้ว";
            } elseif ($step === 'director') {
                if (!in_array($oldStatus, ['deputy_approved', 'head_approved', 'submitted'], true)) {
                    $this->pdo->rollBack();
                    return ['ok' => false, 'message' => 'สถานะปัจจุบันไม่ถูกต้องสำหรับการอนุมัติขั้นสุดท้าย'];
                }
                $upd = $this->pdo->prepare("UPDATE `pp5_courses` SET `status` = 'approved', `approved_at` = NOW(), `locked_at` = NOW() WHERE `id` = ?");
                $upd->execute([$courseId]);

                $appStep = 'director_review';
                $appAction = 'approve';
                $defaultNote = 'อนุมัติผลการเรียนและล็อกเล่ม ปพ.5 สมบูรณ์';
                $newStatus = 'approved';
                $notifyMsg = "ผู้อำนวยการสถานศึกษา ได้อนุมัติผลการเรียนเล่ม ปพ.5 รายวิชา {$course['subject_code']} เรียบร้อยแล้ว (ล็อกเล่มสมบูรณ์)";
            } else {
                $this->pdo->rollBack();
                return ['ok' => false, 'message' => 'ขั้นตอนการอนุมัติไม่ถูกต้อง'];
            }

            $app = $this->pdo->prepare("
                INSERT INTO `pp5_approvals` (`course_id`, `step`, `approver_id`, `action`, `comment`)
                VALUES (?, ?, ?, ?, ?)
            ");
            $app->execute([$courseId, $appStep, $userId, $appAction, $cleanComment !== '' ? $cleanComment : $defaultNote]);

            $this->pdo->commit();

            $this->logger->log(
                "course.{$appStep}",
                "อนุมัติเล่ม ปพ.5 Course #{$courseId} ({$course['subject_code']}) โดยผู้ใช้ #{$userId} สถานะใหม่: {$newStatus}",
                'pp5_courses',
                $courseId,
                $courseId,
                ['status' => $oldStatus],
                ['status' => $newStatus, 'comment' => $cleanComment]
            );

            // แจ้งเตือนครูผู้สอน
            $this->logger->notify(
                (int)$course['teacher_user_id'],
                'อัปเดตผลการอนุมัติ ปพ.5',
                $notifyMsg,
                $step === 'director' ? 'success' : 'info',
                "/pp5/course_summary.php?course_id={$courseId}",
                $courseId
            );

            return ['ok' => true, 'message' => 'บันทึกการอนุมัติเล่ม ปพ.5 เรียบร้อยแล้ว'];
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * ตีกลับเล่ม ปพ.5 ให้ครูผู้สอนแก้ไข (Reject with Reason)
     */
    public function rejectCourse(int $courseId, int $userId, string $step, string $reason): array
    {
        $stmt = $this->pdo->prepare("
            SELECT c.*, s.subject_code, s.subject_name
            FROM `pp5_courses` c
            JOIN `pp5_subjects` s ON c.subject_id = s.id
            WHERE c.id = ? LIMIT 1
        ");
        $stmt->execute([$courseId]);
        $course = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$course) {
            return ['ok' => false, 'message' => 'ไม่พบข้อมูลรายวิชา ปพ.5'];
        }

        $cleanReason = trim($reason);
        if ($cleanReason === '') {
            return ['ok' => false, 'message' => 'กรุณาระบุเหตุผลการตีกลับให้ครูผู้สอนแก้ไข'];
        }

        $oldStatus = $course['status'];
        if (in_array($oldStatus, ['draft', 'locked'], true)) {
            return ['ok' => false, 'message' => 'ไม่สามารถตีกลับเล่ม ปพ.5 ในสถานะนี้ได้'];
        }

        $this->pdo->beginTransaction();
        try {
            $upd = $this->pdo->prepare("
                UPDATE `pp5_courses`
                SET `status` = 'draft', `rejected_reason` = ?, `rejected_by` = ?, `locked_at` = NULL
                WHERE `id` = ?
            ");
            $upd->execute([$cleanReason, $userId, $courseId]);

            $app = $this->pdo->prepare("
                INSERT INTO `pp5_approvals` (`course_id`, `step`, `approver_id`, `action`, `comment`)
                VALUES (?, ?, ?, 'reject', ?)
            ");
            $app->execute([$courseId, $step . '_reject', $userId, $cleanReason]);

            $this->pdo->commit();

            $this->logger->log(
                'course.reject',
                "ตีกลับเล่ม ปพ.5 Course #{$courseId} ({$course['subject_code']}) ให้ครูแก้ไข เหตุผล: {$cleanReason}",
                'pp5_courses',
                $courseId,
                $courseId,
                ['status' => $oldStatus],
                ['status' => 'draft', 'rejected_reason' => $cleanReason]
            );

            $this->logger->notify(
                (int)$course['teacher_user_id'],
                'เล่ม ปพ.5 ถูกส่งกลับเพื่อแก้ไข',
                "เล่ม ปพ.5 รายวิชา {$course['subject_code']} ถูกส่งกลับแก้ไขโดยผู้ตรวจสอบ เหตุผล: {$cleanReason}",
                'warning',
                "/pp5/course_summary.php?course_id={$courseId}",
                $courseId
            );

            return ['ok' => true, 'message' => 'ส่งกลับเล่ม ปพ.5 ให้ครูผู้สอนแก้ไขเรียบร้อยแล้ว (สถานะเปลี่ยนเป็น: ฉบับร่าง)'];
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * ดึงประวัติการส่งตรวจ / อนุมัติ / ตีกลับ / ปลดล็อก ของรายวิชา ปพ.5
     */
    public function getCourseApprovals(int $courseId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT a.*, u.firstname, u.lastname, u.role AS user_role
            FROM `pp5_approvals` a
            LEFT JOIN `llw_users` u ON a.approver_id = u.user_id
            WHERE a.course_id = ?
            ORDER BY a.created_at ASC, a.id ASC
        ");
        $stmt->execute([$courseId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * ปลดล็อก ปพ.5 ที่อยู่ในสถานะ locked หรือส่งอนุมัติแล้ว ให้กลับมาเป็น draft (เฉพาะงานทะเบียนและวัดผล)
     * พร้อมบันทึกประวัติการปลดล็อกทุกครั้ง
     */
    public function unlockCourse(int $courseId, int $adminUserId, string $reason): bool
    {
        $stmt = $this->pdo->prepare("SELECT * FROM `pp5_courses` WHERE `id` = ? LIMIT 1");
        $stmt->execute([$courseId]);
        $course = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$course) {
            return false;
        }

        $oldStatus = $course['status'];
        $this->pdo->beginTransaction();
        try {
            $upd = $this->pdo->prepare("
                UPDATE `pp5_courses`
                SET `status` = 'draft', `rejected_reason` = ?, `locked_at` = NULL
                WHERE `id` = ?
            ");
            $upd->execute(["[ปลดล็อกโดยงานทะเบียน]: " . trim($reason), $courseId]);

            $app = $this->pdo->prepare("
                INSERT INTO `pp5_approvals` (`course_id`, `step`, `approver_id`, `action`, `comment`)
                VALUES (?, 'admin_unlock', ?, 'unlock', ?)
            ");
            $app->execute([$courseId, $adminUserId, trim($reason)]);

            $this->pdo->commit();

            $this->logger->log(
                'course.unlock',
                "ปลดล็อก ปพ.5 Course #{$courseId} จากสถานะ {$oldStatus} เป็น draft เหตุผล: {$reason}",
                'pp5_courses',
                $courseId,
                $courseId,
                ['status' => $oldStatus],
                ['status' => 'draft', 'reason' => $reason]
            );

            $this->logger->notify(
                (int)$course['teacher_user_id'],
                'ปลดล็อก ปพ.5 เรียบร้อยแล้ว',
                "งานทะเบียนและวัดผลได้ปลดล็อก ปพ.5 ของคุณแล้ว เหตุผล: {$reason}",
                'warning',
                "/pp5/index.php",
                $courseId
            );

            return true;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * ซิงค์ข้อมูลนักเรียน ห้องเรียน ครูที่ปรึกษา และรายวิชาจากระบบ LLW System เข้าสู่ระบบ ปพ.5 ออนไลน์
     */
    public function syncFromLlwSystem(int $academicYear, int $semester): array
    {
        $seedFile = __DIR__ . '/../../../database/seeds/13_pp5_initial_seed.php';
        if (file_exists($seedFile)) {
            $seed = require $seedFile;
            if (is_array($seed) && isset($seed['run'])) {
                $seed['run']($this->pdo);
            }
        }
        $this->ensureClassroomsForTerm($academicYear, $semester);

        $clsCount  = (int)$this->pdo->query("SELECT COUNT(*) FROM `pp5_classrooms` WHERE `academic_year` = {$academicYear} AND `semester` = {$semester}")->fetchColumn();
        $stuCount  = (int)$this->pdo->query("SELECT COUNT(*) FROM `att_students` WHERE `status` = 'active' AND `student_id` REGEXP '^[0-9]+$'")->fetchColumn();
        $subjCount = (int)$this->pdo->query("SELECT COUNT(*) FROM `pp5_subjects`")->fetchColumn();
        $crsCount  = (int)$this->pdo->query("SELECT COUNT(*) FROM `pp5_courses` WHERE `academic_year` = {$academicYear} AND `semester` = {$semester}")->fetchColumn();

        $this->logger->log('system.sync_llw', "ซิงค์ข้อมูลจาก LLW System สำเร็จ (ห้องเรียน {$clsCount} ห้อง, นักเรียน {$stuCount} คน, รายวิชา {$subjCount} วิชา, ปพ.5 {$crsCount} รายการ)");

        return [
            'classrooms' => $clsCount,
            'students'   => $stuCount,
            'subjects'   => $subjCount,
            'courses'    => $crsCount,
        ];
    }
}
